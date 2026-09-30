<?php

// Set headers for AJAX response
header('Content-Type: application/json');

// Initialize WHMCS if not already done
if (!defined('WHMCS')) {
    $possiblePaths = [
        dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php',
        '../../../init.php',
        '../../../../init.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/init.php',
        $_SERVER['DOCUMENT_ROOT'] . '/init.php'
    ];
    
    $whmcsInitialized = false;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $whmcsInitialized = true;
            break;
        }
    }
    
    if (!$whmcsInitialized) {
        echo json_encode([
            'success' => false, 
            'message' => 'WHMCS initialization failed',
            'debug' => 'Tried paths: ' . implode(', ', $possiblePaths)
        ]);
        exit;
    }
}

use WHMCS\Module\Server\HosteDai\Helper;
use WHMCS\Database\Capsule;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_otl') {
        generateOTL();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function generateOTL() {
    try {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $staticLoginUrl = $_POST['static_login_url'] ?? '';

        if (empty($serviceId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required parameters',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }

        // Never trust a client-supplied email for this: only the WHMCS
        // client actually logged into this session, requesting a login
        // link for a service they own, may generate one. The email used
        // is always looked up server-side from that session's own client
        // record below — $_POST['user_email'] is intentionally ignored.
        $sessionClientId = (int) ($_SESSION['uid'] ?? 0);
        if (!$sessionClientId) {
            echo json_encode([
                'success' => false,
                'message' => 'You must be logged in to do this.',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }

        // Get service details to retrieve server configuration
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service || (int) $service->userid !== $sessionClientId) {
            logActivity('hostedai: OTL request for service ' . $serviceId
                . ' denied — does not belong to logged-in client ' . $sessionClientId);
            echo json_encode([
                'success' => false,
                'message' => 'Service not found',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }

        $client = Capsule::table('tblclients')->where('id', $sessionClientId)->first();
        if (!$client || empty($client->email)) {
            echo json_encode([
                'success' => false,
                'message' => 'Account email not found',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }
        $userEmail = $client->email;

        // Get server details
        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        if (!$server) {
            echo json_encode([
                'success' => false,
                'message' => 'Server configuration not found',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }
        
        // Create helper with server params
        $params = [
            'serverhostname' => $server->hostname,
            'serverpassword' => decrypt($server->password)
        ];
        
        $helper = new Helper($params);
        $otlResponse = $helper->createOneTimeLoginToken($userEmail);
        
        if ($otlResponse && $otlResponse['httpcode'] == 201 && isset($otlResponse['result']->url)) {
            echo json_encode([
                'success' => true,
                'login_url' => $otlResponse['result']->url,
                'message' => 'One-time login link generated successfully'
            ]);
        } else {
            // Log the error for debugging
            $errorMsg = 'OTL generation failed';
            if (isset($otlResponse['result']->message)) {
                $errorMsg .= ': ' . $otlResponse['result']->message;
            }
            logActivity('OTL Generation Failed', $errorMsg);
            
            echo json_encode([
                'success' => false,
                'message' => 'Unable to generate secure login link. Using standard login.',
                'fallback_url' => $staticLoginUrl
            ]);
        }
        
    } catch (Exception $e) {
        logActivity('OTL AJAX Error', $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Using standard login.',
            'fallback_url' => $staticLoginUrl
        ]);
    }
}