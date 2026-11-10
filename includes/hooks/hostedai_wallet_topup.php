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

    if (!$item) {
        logActivity("hostedai wallet: top-up service {$hosting->id} matched the top-up group but has no"
            . " invoice item at all — nothing credited.");
        return false;
    }

    // Crediting the raw line-item price ignored any coupon — a 100%
    // discount still credited full value. When this invoice contains
    // only this one top-up (the normal case — a top-up is never mixed
    // into a cart with anything else), the invoice's own total is
    // already correctly net of any coupon/discount and tax, so use that
    // instead of the undiscounted item price. A mixed-cart invoice (rare
    // for a top-up) falls back to the item's own price, same as before.
    $itemCountOnInvoice = Capsule::table('tblinvoiceitems')->where('invoiceid', $item->invoiceid)->count();
    if ($itemCountOnInvoice <= 1) {
        $invoice = Capsule::table('tblinvoices')->where('id', $item->invoiceid)->first();
        $amount  = $invoice ? (float) $invoice->total : (float) $item->amount;
    } else {
        $amount = (float) $item->amount;
    }

    if ($amount <= 0) {
        logActivity("hostedai wallet: top-up service {$hosting->id} matched the top-up group but had no"
            . " positive invoice amount — nothing credited (invoice #{$item->invoiceid}, possibly a 100% coupon).");
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
        logActivity("hostedai wallet: credited {$amount} DT to client {$hosting->userid} (service #{$hosting->id}) {$context}");
    }

    return $applied;
}

/**
 * Primary trigger: fires when the order is accepted — but, since an
 * order can be accepted before its invoice is actually paid (a staff
 * member bulk-accepting a batch of pending orders was handing out free
 * credit this way), this still requires the invoice to be marked Paid.
 * Offline payments (cash/bank transfer) still work exactly as before:
 * staff marks the invoice Paid first, then accepts — both orderings
 * reach this same check correctly, since InvoicePaid below also fires
 * and shares the same idempotent ref.
 */
add_hook('AcceptOrder', 1, function ($vars) {
    try {
        $orderId = $vars['orderid'] ?? null;
        if (!$orderId) {
            return;
        }

        $order = Capsule::table('tblorders')->where('id', $orderId)->first();
        if (!$order || !$order->invoiceid) {
            return;
        }

        $invoice = Capsule::table('tblinvoices')->where('id', $order->invoiceid)->first();
        if (!$invoice || $invoice->status !== 'Paid') {
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

/**
 * Claws back a previously-granted top-up credit when the invoice that
 * paid for it is later refunded or cancelled/voided — previously, none
 * of these ever took the credit back, so a refunded top-up left the
 * client with wallet credit they didn't pay for.
 *
 * Reverses the exact amount that was actually credited (read from the
 * wallet ledger via the same ref the original credit used), not a
 * recomputed invoice amount — the invoice's own numbers may have
 * already changed by the time this fires. Idempotent via its own
 * ref=topup_refund_{serviceid}, same pattern as the credit side.
 */
function hostedai_wallet_claw_back_topup_service($hosting, $context = '')
{
    if (!hostedai_wallet_is_topup_product($hosting->packageid)) {
        return false;
    }

    $creditRef = 'topup_service_' . $hosting->id;
    $ledgerRow = Capsule::table('mod_hostedai_wallet_ledger')->where('ref', $creditRef)->first();
    if (!$ledgerRow) {
        // Never actually credited (e.g. the order was never paid in the
        // first place) — nothing to claw back.
        return false;
    }

    $amount = (float) $ledgerRow->amount;
    if ($amount <= 0) {
        return false;
    }

    $refundRef   = 'topup_refund_' . $hosting->id;
    $product     = Capsule::table('tblproducts')->where('id', $hosting->packageid)->first();
    $productName = $product ? $product->name : 'Top-Up';
    $description = "GPU Credits Top-Up reversed: {$productName}" . ($context ? " ({$context})" : '');

    $applied = hostedai_wallet_debit($hosting->userid, $amount, $description, $refundRef);

    if ($applied) {
        logActivity("hostedai wallet: clawed back {$amount} DT from client {$hosting->userid} (service #{$hosting->id}) {$context}");
    }

    return $applied;
}

function hostedai_wallet_claw_back_topups_for_invoice($invoiceId, $context)
{
    $items = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('type', 'Hosting')
        ->get();

    foreach ($items as $item) {
        $hosting = Capsule::table('tblhosting')->where('id', $item->relid)->first();
        if (!$hosting) {
            continue;
        }

        hostedai_wallet_claw_back_topup_service($hosting, $context);
    }
}

add_hook('InvoiceRefunded', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'] ?? null;
        if (!$invoiceId) {
            return;
        }

        hostedai_wallet_claw_back_topups_for_invoice($invoiceId, "Invoice #{$invoiceId} refunded");
    } catch (\Exception $e) {
        logActivity('hostedai wallet topup (InvoiceRefunded) hook error: ' . $e->getMessage());
    }
});

// NOTE: verify 'InvoiceCancelled' is the correct hook name for your WHMCS
// version after deploying (some versions may use a different name for
// this event) — if a cancelled/voided top-up invoice doesn't get
// clawed back, this is the first thing to check.
add_hook('InvoiceCancelled', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'] ?? null;
        if (!$invoiceId) {
            return;
        }

        hostedai_wallet_claw_back_topups_for_invoice($invoiceId, "Invoice #{$invoiceId} cancelled");
    } catch (\Exception $e) {
        logActivity('hostedai wallet topup (InvoiceCancelled) hook error: ' . $e->getMessage());
    }
});
