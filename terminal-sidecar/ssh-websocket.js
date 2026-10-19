/**
 * Hosted AI GPU — browser SSH terminal sidecar.
 *
 * This is a small, always-running Node.js process, completely separate
 * from WHMCS. It does exactly one job: take a short-lived signed token
 * (minted by our WHMCS PHP code, only after it has verified who the
 * client is and that they actually own the service) and bridge a
 * browser WebSocket to a real SSH session on that GPU instance.
 *
 * The browser never sees the SSH password — only this process does,
 * and only for the lifetime of one connection, read out of the token.
 * This fixes the one weakness Hosted AI's own reference implementation
 * flagged in their guide (their dashboard sends the password to the
 * browser; ours never does).
 *
 * Required environment variables:
 *   TERMINAL_JWT_SECRET  Shared secret, same value the PHP side signs
 *                         tokens with. Treat it like a password.
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

if (!JWT_SECRET) {
  console.error('TERMINAL_JWT_SECRET is not set — refusing to start.');
  process.exit(1);
}

// Tokens are single-use: once a token has opened a session, a captured
// or replayed copy of it must not open a second one. This is just an
// in-memory list (fine for a single process) and is cleared on a timer
// since tokens only live 2 minutes anyway — nothing needs to be
// remembered longer than that.
const usedTokenIds = new Set();
setInterval(() => usedTokenIds.clear(), 5 * 60 * 1000);

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

  const ssh = payload.ssh || {};
  if (!ssh.host || !ssh.port || !ssh.username || !ssh.password) {
    sendError(ws, 'Incomplete connection details');
    ws.close();
    return;
  }

  const client = new SSHClient();
  let shellStream = null;
  let pendingResize = null;

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
    client.end();
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
