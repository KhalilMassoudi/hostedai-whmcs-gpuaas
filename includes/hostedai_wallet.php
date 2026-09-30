<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!defined('HOSTEDAI_WALLET_TOPUP_GROUP')) {
    // Must match the WHMCS Product Group name the admin creates for the
    // fixed-amount top-up products (150/200/250/500 DT etc.). Any product
    // placed in this group is treated as a wallet top-up by
    // hostedai_wallet_topup.php.
    define('HOSTEDAI_WALLET_TOPUP_GROUP', 'Hosted AI Wallet Top-Up');
}

/**
 * Hosted AI Wallet — a credit pool fully separate from WHMCS's native
 * account credit (tblclients.credit). Pay-per-use hostedai services are
 * charged from this wallet only (see crons/hostedai_usage_cron.php);
 * nothing else in WHMCS ever debits it, and it never touches
 * tblclients.credit in either direction.
 */

function hostedai_wallet_ensure_tables()
{
    if (!Capsule::schema()->hasTable('mod_hostedai_wallet')) {
        Capsule::schema()->create('mod_hostedai_wallet', function ($table) {
            $table->integer('client_id')->unsigned();
            $table->decimal('balance', 12, 4)->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('client_id');
        });
    }

    if (!Capsule::schema()->hasTable('mod_hostedai_wallet_ledger')) {
        Capsule::schema()->create('mod_hostedai_wallet_ledger', function ($table) {
            $table->increments('id');
            $table->integer('client_id')->unsigned();
            $table->decimal('amount', 12, 4);        // signed: +topup, -usage/-adjustment
            $table->decimal('balance_after', 12, 4);
            $table->string('type', 20);              // topup | usage | adjustment
            $table->string('description', 255)->nullable();
            $table->string('ref', 100)->nullable();   // idempotency key
            $table->dateTime('created_at');
            $table->index('client_id');
            $table->index('ref');
        });
    }
}

/**
 * Current wallet balance for a client. Always 0.0 for a client who has
 * never had a wallet transaction (no row created until the first one).
 */
function hostedai_wallet_get_balance($clientId)
{
    hostedai_wallet_ensure_tables();

    $row = Capsule::table('mod_hostedai_wallet')->where('client_id', $clientId)->first();
    return $row ? (float) $row->balance : 0.0;
}

/**
 * Itemized wallet ledger for a client, newest first, formatted for direct
 * use in a {foreach} template loop (date/description/amount/type/isPositive).
 */
function hostedai_wallet_get_history($clientId, $limit = 100)
{
    hostedai_wallet_ensure_tables();

    $rows = Capsule::table('mod_hostedai_wallet_ledger')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->orderBy('id', 'desc')
        ->limit($limit)
        ->get();

    $history = [];
    foreach ($rows as $row) {
        $type = 'Adjustment';
        if ($row->type === 'topup') {
            $type = 'Top-Up';
        } elseif ($row->type === 'usage') {
            $type = 'Usage Deduction';
        }

        $history[] = [
            'date'        => $row->created_at,
            'description' => (string) $row->description,
            'amount'      => abs((float) $row->amount),
            'type'        => $type,
            'isPositive'  => (float) $row->amount >= 0,
        ];
    }

    return $history;
}

/**
 * Apply a signed change to a client's wallet balance inside a locked
 * transaction (guards against a top-up and a usage debit landing at the
 * same moment). When $ref is given, a repeated call with the same $ref is
 * a no-op that still returns true — so a retried cron pass or a duplicate
 * payment webhook can never double-apply the same charge/credit.
 */
function hostedai_wallet_apply($clientId, $signedAmount, $type, $description, $ref = null)
{
    hostedai_wallet_ensure_tables();

    try {
        if ($ref !== null) {
            $already = Capsule::table('mod_hostedai_wallet_ledger')->where('ref', $ref)->first();
            if ($already) {
                return true;
            }
        }

        $nowStr = gmdate('Y-m-d H:i:s');

        Capsule::connection()->transaction(function () use ($clientId, $signedAmount, $type, $description, $ref, $nowStr) {
            $walletRow = Capsule::table('mod_hostedai_wallet')->where('client_id', $clientId)->lockForUpdate()->first();

            if (!$walletRow) {
                Capsule::table('mod_hostedai_wallet')->insert([
                    'client_id'  => $clientId,
                    'balance'    => 0,
                    'updated_at' => $nowStr,
                ]);
                $currentBalance = 0.0;
            } else {
                $currentBalance = (float) $walletRow->balance;
            }

            $newBalance = round($currentBalance + $signedAmount, 4);

            Capsule::table('mod_hostedai_wallet')->where('client_id', $clientId)->update([
                'balance'    => $newBalance,
                'updated_at' => $nowStr,
            ]);

            Capsule::table('mod_hostedai_wallet_ledger')->insert([
                'client_id'     => $clientId,
                'amount'        => $signedAmount,
                'balance_after' => $newBalance,
                'type'          => $type,
                'description'   => $description,
                'ref'           => $ref,
                'created_at'    => $nowStr,
            ]);
        });

        return true;
    } catch (\Exception $e) {
        logActivity('hostedai wallet: apply failed for client ' . $clientId . ': ' . $e->getMessage());
        return false;
    }
}

function hostedai_wallet_credit($clientId, $amount, $description, $ref = null)
{
    return hostedai_wallet_apply($clientId, abs((float) $amount), 'topup', $description, $ref);
}

function hostedai_wallet_debit($clientId, $amount, $description, $ref = null)
{
    return hostedai_wallet_apply($clientId, -abs((float) $amount), 'usage', $description, $ref);
}

/** tblproductgroups.id for HOSTEDAI_WALLET_TOPUP_GROUP, or 0 if not created yet. */
function hostedai_wallet_get_topup_group_id()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $group = Capsule::table('tblproductgroups')->where('name', HOSTEDAI_WALLET_TOPUP_GROUP)->first();
    $cached = $group ? (int) $group->id : 0;
    return $cached;
}

/** Whether a product belongs to the Hosted AI Wallet top-up group. */
function hostedai_wallet_is_topup_product($productId)
{
    $groupId = hostedai_wallet_get_topup_group_id();
    if (!$groupId) {
        return false;
    }

    $product = Capsule::table('tblproducts')->where('id', $productId)->first();
    return $product && (int) $product->gid === $groupId;
}

/**
 * Storefront URL for the wallet top-up products — deep-links to the group
 * when it exists, otherwise falls back to the main store so the link is
 * never broken while the admin is still setting the group up.
 */
function hostedai_wallet_get_topup_url()
{
    $groupId = hostedai_wallet_get_topup_group_id();
    return $groupId ? 'cart.php?gid=' . $groupId : 'cart.php';
}
