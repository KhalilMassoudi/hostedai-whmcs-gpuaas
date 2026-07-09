<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\HosteDai\Helper;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * The team lives as long as the client exists in WHMCS (Change 1) — it is
 * no longer deleted on service suspension/termination. This hook is the
 * only place the Hosted AI team is torn down, run right before WHMCS
 * removes the client and its related data.
 */
add_hook('PreDeleteClient', 1, function ($vars) {
    $clientId = $vars['userid'] ?? null;
    if (!$clientId) {
        return;
    }

    require_once __DIR__ . '/../../modules/servers/hostedai/lib/Helper.php';

    try {
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'client')
            ->where('fieldname', 'like', '%hostedai_team_id%')
            ->first();
        if (!$field) {
            return;
        }

        $teamId = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $field->id)
            ->where('relid', $clientId)
            ->value('value');

        if (empty($teamId)) {
            return;
        }

        $helper = new Helper(['serverhostname' => '', 'serverpassword' => '']);
        $helper->terminateHostedaiTeam($teamId);

        logActivity('hostedai: Client ' . $clientId . ' deleted -> team ' . $teamId . ' terminated');
    } catch (\Exception $e) {
        logActivity('hostedai: client-delete hook error: ' . $e->getMessage());
    }
});