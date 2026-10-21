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
    define('HOSTEDAI_WALLET_TOPUP_GROUP', 'GPU Credits Top-Up');
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
            // Unique (not just indexed): a plain index only stops the app-level
            // pre-check race documented on hostedai_wallet_apply() below from
            // being the ONLY guard — this makes double-apply impossible even
            // if two calls with the same ref race past that check together.
            // MySQL treats multiple NULL refs as distinct, so unref'd ledger
            // rows (ref = null) are unaffected.
            $table->unique('ref', 'mod_hostedai_wallet_ledger_ref_unique');
        });
        return;
    }

    hostedai_wallet_ensure_ref_unique_index();
}

/**
 * One-time migration for a wallet ledger table created before the ref
 * column was unique. Cheap to call on every request (a single indexed
 * information_schema lookup); the ALTER itself only ever runs once, the
 * first time this fixed code executes against a given database.
 */
function hostedai_wallet_ensure_ref_unique_index()
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $indexExists = Capsule::connection()->selectOne(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'mod_hostedai_wallet_ledger'
               AND index_name = 'mod_hostedai_wallet_ledger_ref_unique'
             LIMIT 1"
        );

        if (!$indexExists) {
            Capsule::schema()->table('mod_hostedai_wallet_ledger', function ($table) {
                $table->unique('ref', 'mod_hostedai_wallet_ledger_ref_unique');
            });
        }
    } catch (\Exception $e) {
        logActivity('hostedai wallet: could not ensure ref unique index: ' . $e->getMessage());
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
 *
 * The wallet is one shared pool per client, not per service — a top-up or
 * adjustment funds the whole wallet, so those always show. Pass $serviceId
 * when rendering this on a single service's page (e.g. manage.tpl) to also
 * restrict the usage/setup-fee rows to that service, instead of showing
 * every usage-mode service this client has ever run. There's no dedicated
 * service_id column on the ledger — usage/setup rows tag their service in
 * the free-text description ("... (service #123, ...)"), so this matches
 * against that instead.
 */
function hostedai_wallet_get_history($clientId, $limit = 100, $serviceId = null)
{
    hostedai_wallet_ensure_tables();

    $query = Capsule::table('mod_hostedai_wallet_ledger')->where('client_id', $clientId);

    if ($serviceId !== null) {
        $serviceId = (int) $serviceId;
        $query->where(function ($q) use ($serviceId) {
            $q->where('type', '!=', 'usage')
              ->orWhere('description', 'like', '%(service #' . $serviceId . ',%')
              ->orWhere('description', 'like', '%(service #' . $serviceId . ')%');
        });
    }

    $rows = $query->orderBy('created_at', 'desc')
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
 * payment webhook can never double-apply the same charge/credit. This is
 * enforced at the database level (a unique index on ref), not just by the
 * pre-check below, so it holds even if two calls race each other.
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
        // The pre-check above is racy by itself (SELECT, then a later INSERT
        // in a separate transaction) — two near-simultaneous calls with the
        // same $ref (a duplicate payment webhook, an overlapping cron run)
        // can both pass it. The ref column's unique index is what actually
        // stops the double-apply, by rejecting the second INSERT. Treat that
        // rejection the same as the pre-check hit: already applied, not a
        // failure — the caller shouldn't retry or alert on it.
        if ($ref !== null && hostedai_wallet_is_duplicate_ref_error($e)) {
            return true;
        }

        logActivity('hostedai wallet: apply failed for client ' . $clientId . ': ' . $e->getMessage());
        return false;
    }
}

/** Whether $e is a DB duplicate-key violation (MySQL error 1062 / SQLSTATE 23000). */
function hostedai_wallet_is_duplicate_ref_error(\Exception $e)
{
    $previous = method_exists($e, 'getPrevious') ? $e->getPrevious() : null;
    $code = $previous instanceof \Exception ? $previous->getCode() : $e->getCode();

    return (string) $code === '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false;
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
