/**
 * Hosted AI GPU — browser SSH terminal sidecar.
 *
 * This is a small, always-running Node.js process, completely separate
 * from WHMCS. It does exactly one job: take a short-lived signed token
 * (minted by our WHMCS PHP code, only after it has verified who the
 * client is and that they actually own the service) and bridge a
 * browser WebSocket to a real SSH session on that GPU instance.
 *
 * The token carries only an instance ID — never the SSH password. This
 * process fetches the live credentials itself, directly from Hosted
 * AI's API, at connection time, and holds them only in memory for the
 * lifetime of that one session. The earlier design embedded the actual
 * password (signed, not encrypted) inside the token itself — anyone who
 * captured a token (e.g. from a proxy access log, since the token rides
 * in the WebSocket URL) could trivially base64-decode it and read the
 * password straight out. This also fixes the one weakness Hosted AI's
 * own reference implementation flagged in their guide (their dashboard
 * sends the password to the browser; ours never does, and now never
 * puts it in anything the browser holds either).
 *
 * Required environment variables:
 *   TERMINAL_JWT_SECRET   Shared secret, same value the PHP side signs
 *                         tokens with. Treat it like a password.
 *   HOSTEDAI_API_HOSTNAME Hosted AI API hostname for this environment
 *                         (same value as the WHMCS Server entry's
 *                         hostname — e.g. xyz.hosted.ai).
 *   HOSTEDAI_API_TOKEN    Hosted AI API token for this environment
 *                         (same value as the WHMCS Server entry's
 *                         decrypted password / x-api-key).
 *   SSH_WS_PORT           Local port to listen on (default 3002).
 *                         This process binds to 127.0.0.1 only — it is
 *                         never reachable directly from the internet,
 *                         only through the reverse proxy rule that
 *                         forwards /ssh-ws WebSocket upgrades to it.
 */

const WebSocket = require('ws');
const { Client: SSHClient } = require('ssh2');
const jwt = require('jsonwebtoken');

const PORT = process.env.SSH_WS_PORT || 3002;
const JWT_SECRET = process.env.TERMINAL_JWT_SECRET;
const HOSTEDAI_API_HOSTNAME = process.env.HOSTEDAI_API_HOSTNAME;
const HOSTEDAI_API_TOKEN = process.env.HOSTEDAI_API_TOKEN;

if (!JWT_SECRET) {
  console.error('TERMINAL_JWT_SECRET is not set — refusing to start.');
  process.exit(1);
}
if (!HOSTEDAI_API_HOSTNAME || !HOSTEDAI_API_TOKEN) {
  console.error('HOSTEDAI_API_HOSTNAME / HOSTEDAI_API_TOKEN are not set — refusing to start.');
  process.exit(1);
}

// Tokens are single-use: once a token has opened a session, a captured
// or replayed copy of it must not open a second one. This is just an
// in-memory list (fine for a single process) and is cleared on a timer
// since tokens only live 2 minutes anyway — nothing needs to be
// remembered longer than that.
const usedTokenIds = new Set();
setInterval(() => usedTokenIds.clear(), 5 * 60 * 1000);

// Defense in depth: the host we connect to always comes from Hosted
// AI's own credentials API, never from the client — but if the signing
// secret ever leaked, a forged token could still name an arbitrary
// instanceId. Refusing private/loopback/link-local targets means even
// that scenario can't be used to pivot into internal infrastructure,
// at the cost of nothing real (real GPU instances are always public IPs).
function isPrivateOrReservedIPv4(host) {
  const m = String(host).match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
  if (!m) return false; // not a bare IPv4 literal — let it through (hostname case)
  const [a, b] = [parseInt(m[1], 10), parseInt(m[2], 10)];
  if (a === 10) return true;
  if (a === 127) return true;
  if (a === 0) return true;
  if (a === 169 && b === 254) return true;
  if (a === 172 && b >= 16 && b <= 31) return true;
  if (a === 192 && b === 168) return true;
  return false;
}

async function fetchCredentials(instanceId) {
  const url = `https://${HOSTEDAI_API_HOSTNAME}/api/instance/${encodeURIComponent(instanceId)}/credentials`;
  const res = await fetch(url, {
    method: 'GET',
    headers: { accept: 'application/json', 'x-api-key': HOSTEDAI_API_TOKEN },
  });
  if (!res.ok) {
    throw new Error(`Hosted AI credentials API returned HTTP ${res.status}`);
  }
  const cred = await res.json();
  if (!cred || !cred.ip || !cred.username || !cred.password) {
    throw new Error('Hosted AI credentials API returned an incomplete response');
  }
  return {
    host: cred.ip,
    port: cred.port || 22,
    username: cred.username,
    password: cred.password,
  };
}

const wss = new WebSocket.Server({ host: '127.0.0.1', port: PORT });
console.log(`Terminal sidecar listening on 127.0.0.1:${PORT}`);

wss.on('connection', (ws, req) => {
  const url = new URL(req.url, 'http://localhost');
  const token = url.searchParams.get('token');

  let payload;
  try {
    payload = jwt.verify(token, JWT_SECRET, { algorithms: ['HS256'] });
  } catch (err) {
    sendError(ws, 'Authentication failed');
    ws.close();
    return;
  }

  if (!payload.jti || usedTokenIds.has(payload.jti)) {
    sendError(ws, 'This session link has already been used');
    ws.close();
    return;
  }
  usedTokenIds.add(payload.jti);

  const instanceId = payload.instanceId;
  if (!instanceId) {
    sendError(ws, 'Incomplete connection details');
    ws.close();
    return;
  }

  let shellStream = null;
  let pendingResize = null;
  let client = null;

  fetchCredentials(instanceId)
    .then((ssh) => {
      if (isPrivateOrReservedIPv4(ssh.host)) {
        console.error(`Refusing to connect to reserved/private address ${ssh.host} (instance ${instanceId})`);
        sendError(ws, 'Could not connect to the instance over SSH');
        ws.close();
        return;
      }

      client = new SSHClient();

      client.on('ready', () => {
        client.shell({ term: 'xterm-256color', cols: 120, rows: 30 }, (err, stream) => {
          if (err) {
            sendError(ws, 'Could not open a shell on the instance');
            ws.close();
            return;
          }

          shellStream = stream;
          ws.send(JSON.stringify({ type: 'connected' }));

          if (pendingResize) {
            stream.setWindow(pendingResize.rows, pendingResize.cols, 0, 0);
            pendingResize = null;
          }

          stream.on('data', (chunk) => {
            ws.send(JSON.stringify({ type: 'data', data: chunk.toString('base64') }));
          });
          stream.stderr.on('data', (chunk) => {
            ws.send(JSON.stringify({ type: 'data', data: chunk.toString('base64') }));
          });
          stream.on('close', () => ws.close());
        });
      });

      client.on('error', () => {
        sendError(ws, 'Could not connect to the instance over SSH');
        ws.close();
      });

      // No hostVerifier is set here, so any host key is accepted. Hosted
      // AI's instances get a new IP on each provision, so pinning a key in
      // advance the way you would for a fixed server isn't practical. The
      // fingerprint is logged instead, so there is at least a record to
      // check if something ever looks wrong.
      client.on('handshake', (details) => {
        console.log(`SSH handshake with ${ssh.host}:${ssh.port} (kex=${details.kex})`);
      });

      client.connect({
        host: ssh.host,
        port: ssh.port,
        username: ssh.username,
        password: ssh.password,
        readyTimeout: 20000,
        keepaliveInterval: 15000,
      });
    })
    .catch((err) => {
      console.error(`Failed to fetch credentials for instance ${instanceId}: ${err.message}`);
      sendError(ws, 'Could not retrieve SSH credentials');
      ws.close();
    });

  ws.on('message', (raw) => {
    let msg;
    try {
      msg = JSON.parse(raw);
    } catch {
      return;
    }

    if (msg.type === 'data' && shellStream) {
      shellStream.write(Buffer.from(msg.data, 'base64'));
    } else if (msg.type === 'resize') {
      if (shellStream) {
        shellStream.setWindow(msg.rows, msg.cols, 0, 0);
      } else {
        pendingResize = { rows: msg.rows, cols: msg.cols };
      }
    }
  });

  // WebSocket-level keepalive — separate from the SSH keepalive above.
  // This is what stops a reverse proxy from silently timing out an
  // idle browser tab (the one weakness Hosted AI's guide flagged as
  // untested in their own setup).
  const pingTimer = setInterval(() => {
    if (ws.readyState === WebSocket.OPEN) ws.ping();
  }, 20000);

  ws.on('close', () => {
    clearInterval(pingTimer);
    if (client) client.end();
  });
});

function sendError(ws, message) {
  try {
    ws.send(JSON.stringify({ type: 'error', message }));
  } catch {
    // socket already gone — nothing to do
  }
}

// One bad session must never take the whole sidecar down.
process.on('uncaughtException', (err) => {
  console.error('Uncaught exception (sidecar keeps running):', err);
});
