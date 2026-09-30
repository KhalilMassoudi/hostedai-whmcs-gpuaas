<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Fixed Subscription products can't price a paid GPU Environment recipe as
 * a flat WHMCS Configurable Option: the same shared recipe (e.g. a 10%
 * uplift) must translate to a different dollar amount per product, since
 * it's a percentage of that product's own recurring amount. So the recipe
 * option itself is left at $0 in WHMCS, and this hook adds the surcharge
 * as its own invoice line item — recomputed from the product's *current*
 * recurring amount every time an invoice is generated, so it never drifts
 * from the base price (initial invoice and every renewal alike).
 *
 * Usage-based products are NOT touched here — their recipe surcharge
 * (configoption20, $/hour) is already applied by hostedai_usage_cron.php
 * against account credit, independent of WHMCS invoicing.
 */
add_hook('InvoiceCreation', 1, function ($vars) {
    try {
        $invoiceId = $vars['invoiceid'] ?? null;
        if (!$invoiceId) {
            return;
        }

        $items = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', $invoiceId)
            ->where('type', 'Hosting')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $totalSurcharge = 0.0;

        foreach ($items as $item) {
            $serviceId = $item->relid;
            if (!$serviceId) {
                continue;
            }

            $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if (!$service) {
                continue;
            }

            $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
            if (!$product || empty($product->servertype) || $product->servertype !== 'hostedai') {
                continue;
            }

            $billingMode = !empty($product->configoption22) ? $product->configoption22 : 'usage';
            if ($billingMode !== 'subscription') {
                continue; // usage-based products: cron handles recipe billing, not us
            }

            $percentMapJson = $product->configoption23 ?? '';
            $percentMap = [];
            if (!empty(trim($percentMapJson))) {
                $percentMap = json_decode($percentMapJson, true) ?: [];
            }
            if (empty($percentMap)) {
                continue;
            }

            $recipe = hostedai_resolveSelectedRecipe($service);
            $percent = (float) ($percentMap[$recipe] ?? 0);
            if ($percent <= 0) {
                continue;
            }

            $surcharge = round(((float) $service->amount) * $percent / 100, 2);
            if ($surcharge <= 0) {
                continue;
            }

            Capsule::table('tblinvoiceitems')->insert([
                'invoiceid'   => $invoiceId,
                'userid'      => $service->userid,
                'type'        => 'Hosting',
                'relid'       => $serviceId,
                'description' => "{$item->description} - GPU Environment recipe surcharge ({$recipe}, {$percent}%)",
                'amount'      => $surcharge,
                'taxed'       => $item->taxed,
                'duedate'     => $item->duedate,
            ]);

            $totalSurcharge += $surcharge;

            logActivity("hostedai: invoice #{$invoiceId} service #{$serviceId} — added {$percent}% recipe surcharge (\${$surcharge}) for recipe '{$recipe}'");
        }

        if ($totalSurcharge > 0) {
            Capsule::table('tblinvoices')->where('id', $invoiceId)->update([
                'subtotal' => Capsule::raw("subtotal + {$totalSurcharge}"),
                'total'    => Capsule::raw("total + {$totalSurcharge}"),
            ]);
        }
    } catch (\Exception $e) {
        logActivity('hostedai: recipe-surcharge hook error: ' . $e->getMessage());
    }
});

/**
 * Recipe is normally read from the "recipe:{name}" note written by
 * saveRecipeNote() during CreateAccount (see cron for the same pattern).
 * For a brand new order, InvoiceCreation fires for the first invoice
 * *before* CreateAccount has run (provisioning happens after payment),
 * so the note doesn't exist yet — fall back to the client's raw
 * Configurable Option selection for that case.
 */
function hostedai_resolveSelectedRecipe($service)
{
    if (!empty($service->notes) && preg_match('/recipe:([^\r\n]+)/', $service->notes, $m)) {
        return trim($m[1]);
    }

    try {
        $selection = Capsule::table('tblhostingconfigoptions as hco')
            ->join('tblproductconfigoptions as pco', 'pco.id', '=', 'hco.configid')
            ->join('tblproductconfigoptionssub as pcos', 'pcos.id', '=', 'hco.optionid')
            ->where('hco.relid', $service->id)
            ->where('pco.optionname', 'GPU Environment')
            ->value('pcos.optionname');

        if (!empty($selection)) {
            return trim($selection);
        }
    } catch (\Exception $e) {
        logActivity('hostedai: recipe-surcharge fallback lookup failed: ' . $e->getMessage());
    }

    return 'No Recipe';
}