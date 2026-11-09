<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/../hostedai_wallet.php';

/**
 * Credits the Hosted AI Wallet for a single top-up "service" line (the
 * placeholder tblhosting row created for a Hosted AI Wallet Top-Up
 * product). Shared by both hooks below so an admin manually accepting an
 * order (payment settled outside WHMCS — cash/bank transfer) and a client
 * paying through a real gateway both land on the same credit.
 *
 * Idempotent via ref=topup_service_{serviceid}: whichever of the two hooks
 * below fires first is the one that actually credits — the other silently
 * no-ops on the repeat ref instead of crediting twice.
 *
 * The purchased "service" carries no provisioning meaning of its own — it
 * only exists to give the top-up a price in the cart — so it is
 * Terminated immediately after crediting instead of sitting in the
 * client's service list as a fake active/pending product.
 */
function hostedai_wallet_credit_topup_service($hosting, $context = '')
{
    if (!hostedai_wallet_is_topup_product($hosting->packageid)) {
        // Silent on purpose for every OTHER product (this hook fires for
        // every accepted order / paid invoice, not just top-ups) — but
        // log once when a product that looks like it's meant to be a
        // top-up isn't recognised as one, so a Product Group name typo
        // doesn't fail completely invisibly like it just did.
        $product = Capsule::table('tblproducts')->where('id', $hosting->packageid)->first();
        if ($product && stripos($product->name, 'credit') !== false) {
            logActivity("hostedai wallet: service {$hosting->id} (product '{$product->name}') was not recognised as a"
                . " top-up — its Product Group name must match HOSTEDAI_WALLET_TOPUP_GROUP exactly ('"
                . HOSTEDAI_WALLET_TOPUP_GROUP . "'). Check Setup > Products/Services for the group's actual name.");
        }
        return false;
    }

    $item = Capsule::table('tblinvoiceitems')
        ->where('relid', $hosting->id)
        ->where('type', 'Hosting')
        ->first();

    $amount = $item ? (float) $item->amount : 0.0;
    if ($amount <= 0) {
        logActivity("hostedai wallet: top-up service {$hosting->id} matched the top-up group but had no"
            . " positive invoice amount — nothing credited. Check the invoice item for this service.");
        return false;
    }

    $product     = Capsule::table('tblproducts')->where('id', $hosting->packageid)->first();
    $productName = $product ? $product->name : 'Top-Up';
    $ref         = 'topup_service_' . $hosting->id;
    $description = "GPU Credits Top-Up: {$productName}" . ($context ? " ({$context})" : '');

    $applied = hostedai_wallet_credit($hosting->userid, $amount, $description, $ref);

    if ($applied) {
        Capsule::table('tblhosting')->where('id', $hosting->id)->update([
            'domainstatus' => 'Terminated',
        ]);
        logActivity("hostedai wallet: credited \${$amount} to client {$hosting->userid} (service #{$hosting->id}) {$context}");
    }

    return $applied;
}

/**
 * Primary trigger: fires as soon as the order is accepted, regardless of
 * whether the invoice has actually been paid. Top-up orders are often
 * settled outside WHMCS (cash/bank transfer confirmed by staff), so the
 * wallet needs crediting the moment the order is accepted, not gated on an
 * online payment that may never be collected through WHMCS itself.
 */
add_hook('AcceptOrder', 1, function ($vars) {
    try {
        $orderId = $vars['orderid'] ?? null;
        if (!$orderId) {
            return;
        }

        $hostingRows = Capsule::table('tblhosting')->where('orderid', $orderId)->get();

        foreach ($hostingRows as $hosting) {
            hostedai_wallet_credit_topup_service($hosting, "Order #{$orderId}");
        }
    } catch (\Exception $e) {
        logActivity('hostedai wallet topup (AcceptOrder) hook error: ' . $e->getMessage());
    }
});

/**
 * Fallback trigger: covers invoices marked Paid without an explicit Accept
 * step (e.g. an automated online gateway). Shares ref=topup_service_{id}
 * with the AcceptOrder hook above, so if that one already credited this
 * service, this one silently no-ops instead of crediting twice.
 */
add_hook('InvoicePaid', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'] ?? null;
        if (!$invoiceId) {
            return;
        }

        $items = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->where('type', 'Hosting')
            ->get();

        foreach ($items as $item) {
            $hosting = Capsule::table('tblhosting')->where('id', $item->relid)->first();
            if (!$hosting) {
                continue;
            }

            hostedai_wallet_credit_topup_service($hosting, "Invoice #{$invoiceId}");
        }
    } catch (\Exception $e) {
        logActivity('hostedai wallet topup (InvoicePaid) hook error: ' . $e->getMessage());
    }
});
