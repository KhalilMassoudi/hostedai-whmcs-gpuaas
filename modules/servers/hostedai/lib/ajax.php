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
        // No path details in the response — this is reachable by anyone,
        // logged in or not, and server filesystem paths are nobody's
        // business. The real paths tried are still in the server's own
        // error/activity log if this ever needs debugging.
        error_log('hostedai ajax.php: WHMCS initialization failed. Tried paths: ' . implode(', ', $possiblePaths));
        echo json_encode([
            'success' => false,
            'message' => 'WHMCS initialization failed'
        ]);
        exit;
    }
}

use WHMCS\Module\Server\HosteDai\Helper;
use WHMCS\Database\Capsule;

if (!defined('HOSTEDAI_TERMINAL_JWT_SECRET')) {
    // The real secret lives outside the webroot entirely — in Plesk's
    // per-domain "private/" folder, a sibling of httpdocs/ that the
    // webserver never serves no matter what (unlike a file sitting
    // inside the module, which only stays safe as long as PHP keeps
    // executing it correctly). That file just does:
    //   <?php define('HOSTEDAI_TERMINAL_JWT_SECRET', 'the-real-secret');
    // Must match TERMINAL_JWT_SECRET in the terminal-sidecar's systemd
    // environment exactly. Generate one with: openssl rand -hex 32
    $secretsFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/hostedai-secrets.php';
    if (file_exists($secretsFile)) {
        require $secretsFile;
    } else {
        define('HOSTEDAI_TERMINAL_JWT_SECRET', '');
    }
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
        check_token("WHMCS.default");

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

        if ($service->domainstatus !== 'Active') {
            logActivity('hostedai: OTL request for service ' . $serviceId
                . ' denied — status is ' . $service->domainstatus . ', not Active');
            echo json_encode([
                'success' => false,
                'message' => 'This service is not active.',
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
        $server = Capsule::table('tblservers')->where('id', $service->server)->where('type', 'hostedai')->first();
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

        // Hosted AI's create-otl endpoint takes only an email and will
        // issue a login link for any existing one — it has no concept of
        // "this WHMCS session" at all. WHMCS's own client email field is
        // self-service editable (Account Details), so without this check
        // a customer could set their own email to any other customer's
        // (or an admin's) Hosted AI email and get a valid login link for
        // that other account. Verifying the email actually belongs to a
        // member of *this client's own team* closes that: a customer
        // can't make themselves a member of someone else's team just by
        // typing that person's email into a WHMCS form.
        $teamId = $helper->getClientTeamId($sessionClientId);
        if (empty($teamId)) {
            logActivity("hostedai: OTL request for service {$serviceId} denied — client {$sessionClientId} has no resolvable team");
            echo json_encode([
                'success' => false,
                'message' => 'Unable to verify your account. Using standard login.',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }

        $teamMembersResponse = $helper->getTeamMembers($teamId);
        $isOwnTeamMember = false;
        if (($teamMembersResponse['httpcode'] ?? 0) == 200 && !empty($teamMembersResponse['result']->members)) {
            foreach ($teamMembersResponse['result']->members as $member) {
                if (strcasecmp($member->user->email ?? '', $userEmail) === 0) {
                    $isOwnTeamMember = true;
                    break;
                }
            }
        }

        if (!$isOwnTeamMember) {
            logActivity("hostedai: OTL request for service {$serviceId} denied — email {$userEmail} is not a member of client {$sessionClientId}'s team {$teamId}");
            echo json_encode([
                'success' => false,
                'message' => 'Unable to verify your account. Using standard login.',
                'fallback_url' => $staticLoginUrl
            ]);
            return;
        }

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
 * sidecar, carrying only an instance ID — never the SSH password. The
 * sidecar fetches live credentials itself from Hosted AI at connection
 * time, so nothing more sensitive than an instance reference ever sits
 * in a token the browser holds or that rides in a URL. The token
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
        check_token("WHMCS.default");

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

        if ($service->domainstatus !== 'Active') {
            logActivity("hostedai terminal: denied token request for service #{$serviceId} — status is {$service->domainstatus}, not Active");
            echo json_encode(['success' => false, 'message' => 'This service is not active.']);
            return;
        }

        // A service on another server module entirely (not hostedai) would
        // otherwise have its own server's decrypted password sent to its
        // own hostname below — harmless in the sense it goes back to its
        // rightful host, but there's no reason this endpoint should ever
        // touch a non-hostedai server at all.
        $server = Capsule::table('tblservers')->where('id', $service->server)->where('type', 'hostedai')->first();
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

        // The token carries only the instance ID — never the SSH password.
        // The sidecar fetches live credentials itself, directly from
        // Hosted AI, at connection time. A signed JWT is not an encrypted
        // one: anything embedded in it is trivially recoverable by
        // base64-decoding the payload, and this token also rides in the
        // WebSocket URL (so it lands in reverse-proxy access logs) — it
        // must never carry anything more sensitive than an instance
        // reference.
        $payload = [
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + 120,
            'instanceId' => $instanceId,
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