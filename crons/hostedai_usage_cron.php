<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HosteDai\Helper;

$whmcspath = "";
if (file_exists(dirname(__FILE__) . "/config.php"))
    require_once dirname(__FILE__) . "/config.php";

if (!empty($whmcspath)) {
    require_once $whmcspath . "/init.php";
} else {
    require(__DIR__ . "/../init.php");
}

// This file sits under the public webroot like any other WHMCS file, so
// without this check anyone who found/guessed its URL could trigger a
// billing run (or hammer Hosted AI's API) on demand, repeatedly, with no
// authentication at all. Allow it only from the server's own CLI cron
// (the normal, recommended way to run this), or from an HTTP request
// carrying a secret key that matches — never a plain unauthenticated hit.
if (php_sapi_name() !== 'cli') {
    if (!defined('HOSTEDAI_CRON_SECRET')) {
        // Set a real value here (e.g. openssl rand -hex 32) only if you
        // need to trigger this cron via a URL instead of the server's
        // own crontab, then pass it as ?key=<value>.
        define('HOSTEDAI_CRON_SECRET', '');
    }
    if (HOSTEDAI_CRON_SECRET === '' || !hash_equals(HOSTEDAI_CRON_SECRET, (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../includes/hostedai_wallet.php';

$helper = new Helper();

/**
 * Usage-based billing cron for hostedai.
 *
 * Meant to run every 5 minutes. For every Active hostedai service it fetches
 * the running hours and vendor cost reported by the Hosted AI billing API
 * since the last successful poll. Charge = vendor cost (driven by the
 * team's Pricing Policy on Hosted AI's side — already includes our margin
 * once a real, non-zero policy is assigned) + our own flat WHMCS-side
 * platform rate (configoption19, $/hour). Recipe pricing is NOT part of
 * this recurring charge — it's a one-time setup fee charged once at
 * CreateAccount instead (see hostedai.php), not billed again every window.
 * The total here is deducted from the client's Hosted AI Wallet balance —
 * a pool fully separate from WHMCS's native account credit — which also
 * drives the warn/suspend logic on low/exhausted wallet balance.
 *
 * Never double-bills: last_billed_at only advances after a confirmed charge
 * (or a confirmed zero-charge window). On any billing-API failure the window
 * is left untouched so it is retried in full next run.
 */
try {
    logActivity('hostedai usage cron: started on ' . gmdate('Y-m-d H:i:s') . ' UTC');

    // ── Step 1: lazily create the usage-tracking table ──────────────────────
    if (!Capsule::schema()->hasTable('mod_hostedai_usage')) {
        Capsule::schema()->create('mod_hostedai_usage', function ($table) {
            $table->integer('service_id')->unsigned();
            $table->string('instance_id', 64)->nullable();
            $table->dateTime('last_billed_at')->nullable();
            $table->decimal('total_hours', 12, 4)->default(0);
            $table->decimal('total_charged', 12, 4)->default(0);
            $table->decimal('pending_charge', 12, 6)->default(0);
            $table->tinyInteger('warned_5h')->default(0);
            $table->tinyInteger('warned_1h')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary('service_id');
        });
        logActivity('hostedai usage cron: created mod_hostedai_usage table');
    }

    // Existing installs: backfill pending_charge if the table predates it.
    if (Capsule::schema()->hasTable('mod_hostedai_usage')
        && !Capsule::schema()->hasColumn('mod_hostedai_usage', 'pending_charge')) {
        Capsule::schema()->table('mod_hostedai_usage', function ($table) {
            $table->decimal('pending_charge', 12, 6)->default(0);
        });
        logActivity('hostedai usage cron: added pending_charge column');
    }

    // ── Step 2: all Active services on a hostedai-type server ───────────────
    $services = Capsule::table('tblhosting')
        ->join('tblservers', 'tblhosting.server', '=', 'tblservers.id')
        ->where('tblhosting.domainstatus', 'Active')
        ->where('tblservers.type', 'hostedai')
        ->select('tblhosting.*')
        ->get();

    $utc = new \DateTimeZone('UTC');

    $servicesChecked   = 0;
    $totalChargedThisRun = 0.0;
    $suspendedCount    = 0;

    foreach ($services as $service) {
        $serviceId = $service->id;

        try {
            $servicesChecked++;

            // ── Step 3a: resolve instance ────────────────────────────────
            $instanceId = $helper->getPoolSubscriptionIdFromNotes($serviceId);
            if (empty($instanceId)) {
                continue;
            }

            // ── Step 3b: load or create the usage row ───────────────────
            $usageRow = Capsule::table('mod_hostedai_usage')
                ->where('service_id', $serviceId)
                ->first();

            $nowDt  = new \DateTime('now', $utc);
            $nowStr = $nowDt->format('Y-m-d H:i:s');

            if (!$usageRow) {
                // First time we've seen this service — start the clock,
                // nothing to bill yet.
                Capsule::table('mod_hostedai_usage')->insert([
                    'service_id'     => $serviceId,
                    'instance_id'    => $instanceId,
                    'last_billed_at' => $nowStr,
                    'total_hours'    => 0,
                    'total_charged'  => 0,
                    'warned_5h'      => 0,
                    'warned_1h'      => 0,
                    'updated_at'     => $nowStr,
                ]);
                logActivity("hostedai usage cron: started tracking service {$serviceId} (instance {$instanceId})");
                continue;
            }

            // Keep instance_id current in case it changed (e.g. re-provisioned).
            if ($usageRow->instance_id !== $instanceId) {
                Capsule::table('mod_hostedai_usage')
                    ->where('service_id', $serviceId)
                    ->update(['instance_id' => $instanceId]);
            }

            // ── Rate (needed for both charging and the credit-check below) ─
            // Recipe pricing is NOT part of this: it's a one-time setup fee
            // charged once at CreateAccount (see hostedai.php), not a
            // recurring per-hour charge — clients pay once for e.g. an
            // Ollama setup, not again every billing window it runs.
            $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
            $rate = (float) ($product->configoption19 ?? 0);

            // Fixed Subscription products are billed entirely through their
            // WHMCS recurring invoice (+ native overdue-suspension automation,
            // see hostedai_SuspendAccount/TerminateAccount). Credit must never
            // be touched for them, or clients get double-billed / wrongly
            // suspended for having $0 credit they were never meant to fund.
            $billingMode = !empty($product->configoption22) ? $product->configoption22 : 'usage';
            $isSubscription = ($billingMode === 'subscription');

            // ── Step 3c: time window ─────────────────────────────────────
            $startDt = new \DateTime($usageRow->last_billed_at, $utc);
            $endDt   = $nowDt;

            if ($startDt >= $endDt) {
                // Nothing has elapsed since the last poll (e.g. cron ran twice
                // in quick succession) — nothing to do.
                continue;
            }

            $startStr = $startDt->format('Y-m-d\TH:i');
            $endStr   = $endDt->format('Y-m-d\TH:i');

            // ── Step 3d: fetch running hours + vendor cost ───────────────
            $usage = $helper->getWindowUsage($instanceId, $startStr, $endStr);

            if ($usage === null) {
                $hours = null;
                logActivity("hostedai usage cron: getWindowUsage failed for service {$serviceId} (instance {$instanceId}), window {$startStr} -> {$endStr} — will retry next run");
                // Do NOT advance last_billed_at — the window is retried whole next run.
            } else {
                $hours      = $usage['hours'];
                $vendorCost = $usage['cost'];

                if ($isSubscription) {
                    // Fixed Subscription: already paid in full via the WHMCS
                    // recurring invoice. Track hours for visibility only —
                    // never deduct credit, never touch pending_charge.
                    Capsule::table('mod_hostedai_usage')
                        ->where('service_id', $serviceId)
                        ->update([
                            'last_billed_at' => $endDt->format('Y-m-d H:i:s'),
                            'total_hours'    => $usageRow->total_hours + $hours,
                            'updated_at'     => $nowStr,
                        ]);
                    logActivity("hostedai usage cron: service {$serviceId} is Fixed Subscription — tracked {$hours}h, no credit deducted");

                    // Fixed Subscription services skip the credit-based
                    // warning/suspend logic entirely (Step 4) — WHMCS's own
                    // overdue-invoice automation handles their suspension.
                    continue;
                }

                // ── Step 3e/f: compute charge ────────────────────────────
                // Charge = vendor cost reported by Hosted AI for this window
                // (driven by the team's Pricing Policy — already includes our
                // margin once a real, non-zero policy is assigned) PLUS the
                // admin's flat platform rate (configoption19, $/hour). Recipe
                // pricing is a separate one-time fee charged once at
                // CreateAccount — not part of this recurring rate.
                //
                // WHMCS AddCredit only accepts 2 decimal places, but hourly
                // usage at low rates produces fractions of a cent. Never
                // round those away — bank them in pending_charge until they
                // cross the $0.01 minimum, so no usage is ever silently lost.
                $rawCharge = $vendorCost + ($hours * $rate);

                if ($rawCharge > 0) {
                    $totalDue   = $rawCharge + (float) $usageRow->pending_charge;
                    $chargeable = floor($totalDue * 100) / 100; // 2dp, rounded DOWN — never overcharge
                    $remainder  = $totalDue - $chargeable;      // carried forward, never lost

                    if ($chargeable >= 0.01) {
                        // ── Step 3g: deduct from the Hosted AI Wallet ────
                        // (never touches native WHMCS account credit) ────
                        $walletRef = "usage_{$serviceId}_" . $startDt->format('YmdHis') . '_' . $endDt->format('YmdHis');
                        $debited = hostedai_wallet_debit(
                            $service->userid,
                            $chargeable,
                            "Hosted AI usage: {$hours}h, vendor cost \${$vendorCost} + platform rate \${$rate}/h (service #{$serviceId}, {$startStr} to {$endStr})",
                            $walletRef
                        );

                        if ($debited) {
                            Capsule::table('mod_hostedai_usage')
                                ->where('service_id', $serviceId)
                                ->update([
                                    'last_billed_at'  => $endDt->format('Y-m-d H:i:s'),
                                    'total_hours'     => $usageRow->total_hours + $hours,
                                    'total_charged'   => $usageRow->total_charged + $chargeable,
                                    'pending_charge'  => $remainder,
                                    'updated_at'      => $nowStr,
                                ]);
                            $totalChargedThisRun += $chargeable;
                            logActivity("hostedai usage cron: charged \${$chargeable} ({$hours}h, vendor cost \${$vendorCost} + platform rate \${$rate}/h, remainder \${$remainder} banked) from wallet of client {$service->userid} for service {$serviceId}");
                        } else {
                            logActivity("hostedai usage cron: wallet debit failed for client {$service->userid} service {$serviceId} — will retry window next run");
                            // Do NOT advance last_billed_at — retry the whole window next run.
                        }
                    } else {
                        // Too small to bill this run — bank it. The hours ARE
                        // accounted for, so the window must still advance.
                        Capsule::table('mod_hostedai_usage')
                            ->where('service_id', $serviceId)
                            ->update([
                                'last_billed_at' => $endDt->format('Y-m-d H:i:s'),
                                'total_hours'    => $usageRow->total_hours + $hours,
                                'pending_charge' => $totalDue,
                                'updated_at'     => $nowStr,
                            ]);
                        logActivity("hostedai usage cron: banked \${$totalDue} (below \$0.01 minimum) for service {$serviceId}");
                    }
                } else {
                    // Zero vendor cost and zero platform rate — nothing to
                    // charge, but hours were real; advance.
                    Capsule::table('mod_hostedai_usage')
                        ->where('service_id', $serviceId)
                        ->update([
                            'last_billed_at' => $endDt->format('Y-m-d H:i:s'),
                            'total_hours'    => $usageRow->total_hours + $hours,
                            'updated_at'     => $nowStr,
                        ]);
                }
            }

            // ── Step 4: credit checks (independent of whether this run charged) ─
            // Fixed Subscription services never reach here in the normal case
            // (handled by the `continue` above), but this guard also covers the
            // getWindowUsage-failed path, which falls through to here with no
            // usage data — credit balance is irrelevant to a subscription client
            // regardless, since they're billed via invoice, not credit.
            if ($isSubscription) {
                continue;
            }

            $client = Capsule::table('tblclients')->where('id', $service->userid)->first();
            if (!$client) {
                continue;
            }

            $currentCredit = hostedai_wallet_get_balance($service->userid);

            // Re-read the usage row (may have just been inserted/updated above).
            $usageRowNow = Capsule::table('mod_hostedai_usage')->where('service_id', $serviceId)->first();
            $warned5h = (int) $usageRowNow->warned_5h;
            $warned1h = (int) $usageRowNow->warned_1h;

            // Effective $/hour burn rate: now that vendor cost (not a static
            // admin rate) drives most of the charge, $rate alone no longer
            // reflects real spend. Prefer this window's actual rawCharge/hours;
            // fall back to the lifetime average if the instance was idle this
            // run (no hours = no fresh signal); fall back to $rate only if
            // neither is available yet (brand new service, nothing billed).
            if (isset($hours) && $hours > 0 && isset($rawCharge)) {
                $hourlyBurn = $rawCharge / $hours;
            } elseif ($usageRowNow->total_hours > 0) {
                $hourlyBurn = $usageRowNow->total_charged / $usageRowNow->total_hours;
            } else {
                $hourlyBurn = $rate;
            }
            $hoursLeft = $hourlyBurn > 0 ? $currentCredit / $hourlyBurn : 999999;

            $flagUpdates = [];

            if ($hoursLeft >= 5) {
                // Credit topped back up above the warning thresholds — reset.
                if ($warned5h || $warned1h) {
                    $flagUpdates['warned_5h'] = 0;
                    $flagUpdates['warned_1h'] = 0;
                }
            } else {
                if ($hoursLeft < 5 && !$warned5h) {
                    hostedai_sendLowCreditEmail($client, $service, $hoursLeft, 'Low GPU Credits Warning',
                        "Your Hosted AI service (#{$serviceId}) has approximately " . round($hoursLeft, 2)
                        . " hours of GPU Credits remaining at your current usage rate. Please top up your GPU Credits to avoid interruption.");
                    $flagUpdates['warned_5h'] = 1;
                    logActivity("hostedai usage cron: sent 5h low-credit warning to client {$service->userid} (service {$serviceId}, hoursLeft={$hoursLeft})");
                }

                if ($hoursLeft < 1 && !$warned1h) {
                    hostedai_sendLowCreditEmail($client, $service, $hoursLeft, 'Urgent: GPU Credits Almost Exhausted',
                        "Your Hosted AI service (#{$serviceId}) has less than 1 hour of GPU Credits remaining at your current usage rate. "
                        . "Your service will be suspended automatically once your GPU Credits balance reaches zero.");
                    $flagUpdates['warned_1h'] = 1;
                    logActivity("hostedai usage cron: sent 1h urgent low-credit warning to client {$service->userid} (service {$serviceId}, hoursLeft={$hoursLeft})");
                }
            }

            if (!empty($flagUpdates)) {
                $flagUpdates['updated_at'] = $nowStr;
                Capsule::table('mod_hostedai_usage')->where('service_id', $serviceId)->update($flagUpdates);
            }

            // A service can be a "real" cost either through the admin-set flat
            // $rate (configoption19) or purely through Hosted AI's own
            // vendor-cost billing (many usage-based products run $rate=0 and
            // let vendor cost drive 100% of the charge — see Step 3e/f above).
            // Gating suspension on $rate alone meant those services could run
            // up an unbounded negative wallet balance and never be suspended,
            // since $rate was always 0. total_charged > 0 catches that case:
            // if the service has ever actually been charged real money, an
            // exhausted wallet must suspend it regardless of $rate.
            $hasRealCost = $rate > 0 || (float) $usageRowNow->total_charged > 0;

            if ($hasRealCost && $currentCredit <= 0 && $service->domainstatus === 'Active') {
                $helper->stopInstance($instanceId);
                Capsule::table('tblhosting')->where('id', $serviceId)->update(['domainstatus' => 'Suspended']);
                $suspendedCount++;
                logActivity("hostedai usage cron: suspended service {$serviceId} (client {$service->userid}) — wallet exhausted (balance: {$currentCredit})");

                hostedai_sendLowCreditEmail($client, $service, 0, 'Service Suspended: GPU Credits Exhausted',
                    "Your Hosted AI service (#{$serviceId}) has been suspended because your GPU Credits balance reached \$0. "
                    . "Please top up your GPU Credits to resume this service.");
                logActivity("hostedai usage cron: sent suspension notice to client {$service->userid} (service {$serviceId})");
            }
        } catch (\Exception $e) {
            // One service failing must never break the loop.
            logActivity("hostedai usage cron: error processing service {$serviceId}: " . $e->getMessage());
        }
    }

    logActivity("hostedai usage billing: {$servicesChecked} services checked, \${$totalChargedThisRun} charged total, {$suspendedCount} suspended");
} catch (\Exception $e) {
    logActivity('hostedai usage cron: fatal error: ' . $e->getMessage());
}

/**
 * Send a low-credit warning email via the WHMCS API.
 * Uses an ad-hoc custom message (customtype 'general') rather than a
 * pre-created email template, since no such template is guaranteed to
 * exist in a given WHMCS install.
 */
function hostedai_sendLowCreditEmail($client, $service, $hoursLeft, $subject, $message)
{
    try {
        $result = localAPI('SendEmail', [
            'id'            => $client->id,
            'customtype'    => 'general',
            'customsubject' => 'Hosted AI: ' . $subject,
            'custommessage' => $message,
        ]);

        if (!isset($result['result']) || $result['result'] !== 'success') {
            logActivity('hostedai usage cron: SendEmail failed for client ' . $client->id . ': ' . json_encode($result));
        }

        return $result;
    } catch (\Exception $e) {
        logActivity('hostedai usage cron: SendEmail exception for client ' . $client->id . ': ' . $e->getMessage());
        return null;
    }
}