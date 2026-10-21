<?php

/**
 * Full-page browser SSH terminal. Opened in a new tab from the
 * "Terminal" button on the service management page (manage.tpl),
 * instead of the in-page modal — a real, resizable, full-tab terminal
 * rather than a cramped popup.
 *
 * This page only checks the client owns the service and then renders
 * the terminal shell; the actual token (and the ownership check that
 * matters for security) is handled by ajax.php's generate_terminal_token
 * action, called from the JS below exactly as it was from the modal.
 */

if (!defined('WHMCS')) {
    $possiblePaths = [
        dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php',
        '../../../init.php',
        '../../../../init.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/init.php',
        $_SERVER['DOCUMENT_ROOT'] . '/init.php',
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
        http_response_code(500);
        exit('WHMCS initialization failed');
    }
}

use WHMCS\Database\Capsule;

if (empty($_SESSION['uid'])) {
    http_response_code(403);
    exit('Please log in to the client area first.');
}

$serviceId = (int) ($_GET['service_id'] ?? 0);
$service = $serviceId ? Capsule::table('tblhosting')->where('id', $serviceId)->first() : null;

if (!$service || (int) $service->userid !== (int) $_SESSION['uid']) {
    http_response_code(403);
    exit('Service not found.');
}

?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Terminal — Service #<?php echo $serviceId; ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
<style>
  html, body { margin: 0; padding: 0; height: 100%; background: #1e1e1e; overflow: hidden; }
  #bar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 16px; background: #2d2d2d; color: #fff;
    font-family: monospace; font-size: 13px; box-sizing: border-box;
    height: 36px;
  }
  #terminal { position: absolute; top: 36px; left: 0; right: 0; bottom: 0; padding: 8px; box-sizing: border-box; }
</style>
</head>
<body>
  <div id="bar">
    <span id="status">Connecting&hellip;</span>
    <span>Service #<?php echo $serviceId; ?></span>
  </div>
  <div id="terminal"></div>

  <script>
  var HOSTEDAI_SERVICE_ID = <?php echo $serviceId; ?>;

  function toBase64(str) {
      return btoa(String.fromCharCode.apply(null, new TextEncoder().encode(str)));
  }
  function fromBase64(str) {
      var bytes = Uint8Array.from(atob(str), function (c) { return c.charCodeAt(0); });
      return new TextDecoder().decode(bytes);
  }
  function setStatus(text) {
      document.getElementById('status').textContent = text;
  }

  var term = new Terminal({ theme: { background: '#1e1e1e' } });
  var fitAddon = new FitAddon.FitAddon();
  term.loadAddon(fitAddon);
  term.open(document.getElementById('terminal'));
  fitAddon.fit();

  var socket = null;

  window.addEventListener('resize', function () {
      fitAddon.fit();
      if (socket && socket.readyState === WebSocket.OPEN) {
          socket.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
      }
  });

  var formData = new FormData();
  formData.append('action', 'generate_terminal_token');
  formData.append('service_id', HOSTEDAI_SERVICE_ID);

  fetch('/modules/servers/hostedai/lib/ajax.php', { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (data) {
          if (!data.success || !data.token) {
              setStatus('Error: ' + (data.message || 'could not start session'));
              return;
          }
          connect(data.token);
      })
      .catch(function () {
          setStatus('Error: request failed');
      });

  function connect(token) {
      var proto = (location.protocol === 'https:') ? 'wss:' : 'ws:';
      socket = new WebSocket(proto + '//' + location.host + '/ssh-ws?token=' + encodeURIComponent(token));

      socket.onopen = function () {
          setStatus('Connected');
          socket.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
      };
      socket.onmessage = function (event) {
          var msg;
          try { msg = JSON.parse(event.data); } catch (e) { return; }
          if (msg.type === 'data') {
              term.write(fromBase64(msg.data));
          } else if (msg.type === 'error') {
              setStatus('Error: ' + msg.message);
          } else if (msg.type === 'connected') {
              setStatus('Connected');
          }
      };
      socket.onclose = function () { setStatus('Disconnected'); };
      socket.onerror = function () { setStatus('Connection error'); };

      term.onData(function (data) {
          if (socket && socket.readyState === WebSocket.OPEN) {
              socket.send(JSON.stringify({ type: 'data', data: toBase64(data) }));
          }
      });
  }

  window.addEventListener('beforeunload', function () {
      if (socket) socket.close();
  });
  </script>
</body>
</html>
