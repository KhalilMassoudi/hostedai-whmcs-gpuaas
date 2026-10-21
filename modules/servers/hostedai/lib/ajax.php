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

if (!defined('HOSTEDAI_TERMINAL_JWT_SECRET')) {
    // Must match TERMINAL_JWT_SECRET in the terminal-sidecar's systemd
    // environment exactly — this is how the browser terminal feature's
    // two halves (this PHP endpoint and the Node sidecar) trust each
    // other. Generate one with: openssl rand -hex 32
    define('HOSTEDAI_TERMINAL_JWT_SECRET', '');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_otl') {
        generateOTL();
    } elseif ($action === 'generate_terminal_token') {
        generateTerminalToken();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function generateOTL() {
    try {
        $serviceId = $_POST['service_id'] ?? '';
        $userEmail = $_POST['user_email'] ?? '';
        $staticLoginUrl = $_POST['static_login_url'] ?? '';
        
        if (empty($serviceId) || empty($userEmail)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required parameters',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }
        
        // Get service details to retrieve server configuration
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) {
            echo json_encode([
                'success' => false,
                'message' => 'Service not found',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }
        
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

/**
 * Mints a short-lived, single-use token for the browser SSH terminal
 * sidecar. The browser never sees the SSH password directly — only this
 * token, which the sidecar decodes to get connection details, and which
 * expires in 2 minutes and can only open one session.
 *
 * Ownership is checked against the logged-in client's own session
 * ($_SESSION['uid']), not anything the client supplies — the client only
 * ever sends a service_id, same pattern as the fix applied to
 * generateOTL() in the hostedai-module-audit branch.
 */
function generateTerminalToken()
{
    try {
        if (empty($_SESSION['uid'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            return;
        }

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        if (!$serviceId) {
            echo json_encode(['success' => false, 'message' => 'Missing service_id']);
            return;
        }

        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service || (int) $service->userid !== (int) $_SESSION['uid']) {
            logActivity("hostedai terminal: denied token request for service #{$serviceId} (session client #{$_SESSION['uid']})");
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            return;
        }

        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        if (!$server) {
            echo json_encode(['success' => false, 'message' => 'Server configuration not found']);
            return;
        }

        $helper = new Helper([
            'serverhostname' => $server->hostname,
            'serverpassword' => decrypt($server->password),
        ]);

        $instanceId = $helper->getPoolSubscriptionIdFromNotes($serviceId);
        if (empty($instanceId)) {
            echo json_encode(['success' => false, 'message' => 'Instance not provisioned yet']);
            return;
        }

        $credResponse = $helper->getInstanceCredentials($instanceId);
        if ($credResponse['httpcode'] != 200 || empty($credResponse['result'])) {
            echo json_encode(['success' => false, 'message' => 'Could not retrieve SSH credentials']);
            return;
        }

        $cred = $credResponse['result'];
        if (empty($cred->ip) || empty($cred->username) || empty($cred->password)) {
            echo json_encode(['success' => false, 'message' => 'SSH credentials not available yet']);
            return;
        }

        if (HOSTEDAI_TERMINAL_JWT_SECRET === '') {
            logActivity('hostedai terminal: HOSTEDAI_TERMINAL_JWT_SECRET is not configured in ajax.php');
            echo json_encode(['success' => false, 'message' => 'Terminal feature is not configured yet']);
            return;
        }

        $payload = [
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + 120,
            'ssh' => [
                'host'     => $cred->ip,
                'port'     => (int) ($cred->port ?: 22),
                'username' => $cred->username,
                'password' => $cred->password,
            ],
        ];

        $token = hostedai_terminal_jwt_encode($payload, HOSTEDAI_TERMINAL_JWT_SECRET);

        echo json_encode(['success' => true, 'token' => $token]);
    } catch (Exception $e) {
        logActivity('hostedai terminal token AJAX error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
}

/** Minimal dependency-free HS256 JWT encoder — matches what the Node
 *  sidecar verifies with jsonwebtoken's jwt.verify(). */
function hostedai_terminal_jwt_encode(array $payload, $secret)
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $segments = [
        hostedai_terminal_base64url_encode(json_encode($header)),
        hostedai_terminal_base64url_encode(json_encode($payload)),
    ];
    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = hostedai_terminal_base64url_encode($signature);
    return implode('.', $segments);
}

function hostedai_terminal_base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}