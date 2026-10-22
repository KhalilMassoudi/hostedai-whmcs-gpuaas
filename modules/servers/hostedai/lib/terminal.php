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
  :root {
    --bg: #1e1e1e; --bar-bg: #2d2d2d; --text: #fff;
    --key-bg: #3a3a3a; --key-fg: #fff; --key-border: #555; --key-bg-active: #4a4a4a;
  }
  body.light {
    --bg: #f5f5f5; --bar-bg: #e4e4e4; --text: #1e1e1e;
    --key-bg: #d8d8d8; --key-fg: #1e1e1e; --key-border: #bbb; --key-bg-active: #c4c4c4;
  }
  html, body { margin: 0; padding: 0; height: 100%; background: var(--bg); overflow: hidden; }
  #bar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 16px; background: var(--bar-bg); color: var(--text);
    font-family: monospace; font-size: 13px; box-sizing: border-box;
    height: 36px;
  }
  #bar-left { display: flex; align-items: center; gap: 14px; }
  #theme-toggle {
    background: var(--key-bg); color: var(--key-fg); border: 1px solid var(--key-border);
    border-radius: 4px; padding: 3px 10px; font-size: 12px; cursor: pointer; font-family: monospace;
  }
  #keys {
    display: flex; gap: 6px; align-items: center; overflow-x: auto; white-space: nowrap;
    padding: 6px 10px; background: var(--bar-bg); box-sizing: border-box; height: 34px;
    border-top: 1px solid var(--key-border);
  }
  .key-btn {
    flex: 0 0 auto;
    background: var(--key-bg); color: var(--key-fg); border: 1px solid var(--key-border);
    border-radius: 4px; padding: 3px 10px; font-size: 12px; cursor: pointer; font-family: monospace;
  }
  .key-btn:active { background: var(--key-bg-active); }
  #terminal { position: absolute; top: 70px; left: 0; right: 0; bottom: 0; padding: 8px; box-sizing: border-box; }
</style>
</head>
<body>
  <div id="bar">
    <div id="bar-left">
      <span id="status">Connecting&hellip;</span>
      <span>Service #<?php echo $serviceId; ?></span>
    </div>
    <button type="button" id="theme-toggle" onclick="toggleTheme()">&#9728; Light</button>
  </div>
  <div id="keys">
    <button type="button" class="key-btn" data-seq="\x1b">Esc</button>
    <button type="button" class="key-btn" data-seq="\t">Tab</button>
    <button type="button" class="key-btn" data-seq="\x03">Ctrl+C</button>
    <button type="button" class="key-btn" data-seq="\x04">Ctrl+D</button>
    <button type="button" class="key-btn" data-seq="\x0c">Ctrl+L</button>
    <button type="button" class="key-btn" data-seq="\x1a">Ctrl+Z</button>
    <button type="button" class="key-btn" data-seq="\x1b[A">&#8593;</button>
    <button type="button" class="key-btn" data-seq="\x1b[B">&#8595;</button>
    <button type="button" class="key-btn" data-seq="\x1b[D">&#8592;</button>
    <button type="button" class="key-btn" data-seq="\x1b[C">&#8594;</button>
    <button type="button" class="key-btn" data-seq="\x1b[H">Home</button>
    <button type="button" class="key-btn" data-seq="\x1b[F">End</button>
    <button type="button" class="key-btn" data-seq="\x1b[5~">PgUp</button>
    <button type="button" class="key-btn" data-seq="\x1b[6~">PgDn</button>
    <button type="button" class="key-btn" data-seq="/">/</button>
    <button type="button" class="key-btn" data-seq="|">|</button>
    <button type="button" class="key-btn" data-seq="~">~</button>
  </div>
  <div id="terminal"></div>

  <script>
  var HOSTEDAI_SERVICE_ID = <?php echo $serviceId; ?>;

  var DARK_THEME = { background: '#1e1e1e', foreground: '#d4d4d4', cursor: '#d4d4d4' };
  var LIGHT_THEME = { background: '#ffffff', foreground: '#1e1e1e', cursor: '#1e1e1e' };

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

  function getSavedTheme() {
      try { return localStorage.getItem('hostedai_terminal_theme') || 'dark'; }
      catch (e) { return 'dark'; }
  }
  function saveTheme(name) {
      try { localStorage.setItem('hostedai_terminal_theme', name); } catch (e) { /* ignore */ }
  }

  var currentTheme = getSavedTheme();
  if (currentTheme === 'light') document.body.classList.add('light');

  var term = new Terminal({ theme: currentTheme === 'light' ? LIGHT_THEME : DARK_THEME });
  var fitAddon = new FitAddon.FitAddon();
  term.loadAddon(fitAddon);
  term.open(document.getElementById('terminal'));
  fitAddon.fit();
  updateThemeButtonLabel();

  function toggleTheme() {
      currentTheme = (currentTheme === 'light') ? 'dark' : 'light';
      document.body.classList.toggle('light', currentTheme === 'light');
      term.options.theme = currentTheme === 'light' ? LIGHT_THEME : DARK_THEME;
      saveTheme(currentTheme);
      updateThemeButtonLabel();
  }
  function updateThemeButtonLabel() {
      document.getElementById('theme-toggle').innerHTML =
          currentTheme === 'light' ? '&#9728; Light' : '&#9790; Dark';
  }

  // ── Virtual keyboard ────────────────────────────────────────────
  // Sends the same bytes a physical keypress would, straight into the
  // shell — identical path to term.onData below.
  Array.prototype.forEach.call(document.querySelectorAll('.key-btn'), function (btn) {
      btn.addEventListener('click', function () {
          var seq = btn.getAttribute('data-seq')
              .replace(/\\x([0-9a-fA-F]{2})/g, function (m, hex) { return String.fromCharCode(parseInt(hex, 16)); })
              .replace(/\\t/g, '\t');
          sendInput(seq);
          term.focus();
      });
  });

  function sendInput(data) {
      if (socket && socket.readyState === WebSocket.OPEN) {
          socket.send(JSON.stringify({ type: 'data', data: toBase64(data) }));
      }
  }

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
          sendInput(data);
      });
  }

  window.addEventListener('beforeunload', function () {
      if (socket) socket.close();
  });
  </script>
</body>
</html>
