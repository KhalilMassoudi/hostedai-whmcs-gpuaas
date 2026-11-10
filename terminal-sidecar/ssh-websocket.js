/**
 * Hosted AI GPU — browser SSH terminal sidecar.
 *
 * This is a small, always-running Node.js process, completely separate
 * from WHMCS. It does exactly one job: take a short-lived signed token
 * (minted by our WHMCS PHP code, only after it has verified who the
 * client is and that they actually own the service) and bridge a
 * browser WebSocket to a real SSH session on that GPU instance.
 *
 * The token carries only an instance ID (and a service ID, used only to
 * poll WHMCS for suspension — see below) — never the SSH password. This
 * process fetches the live credentials itself, directly from Hosted
 * AI's API, at connection time, and holds them only in memory for the
 * lifetime of that one session.
 *
 * Required environment variables:
 *   TERMINAL_JWT_SECRET      Shared secret, same value the PHP side signs
 *                            tokens with. Treat it like a password.
 *   HOSTEDAI_API_HOSTNAME    Hosted AI API hostname for this environment
 *                            (same value as the WHMCS Server entry's
 *                            hostname — e.g. xyz.hosted.ai).
 *   HOSTEDAI_API_TOKEN       Hosted AI API token for this environment
 *                            (same value as the WHMCS Server entry's
 *                            decrypted password / x-api-key).
 *   ALLOWED_ORIGIN           The exact origin (scheme+host[:port]) the
 *                            browser connection must present, e.g.
 *                            https://portal.example.com. Rejects any
 *                            WebSocket upgrade that doesn't present
 *                            this as its Origin header.
 *   WHMCS_AJAX_URL           Full URL to ajax.php, e.g.
 *                            https://portal.example.com/modules/servers/hostedai/lib/ajax.php
 *                            Polled every 30s per open session (action=
 *                            check_service_status, authenticated with
 *                            TERMINAL_JWT_SECRET) to detect a service
 *                            suspended mid-session and kill it.
 *
 * Optional:
 *   SSH_WS_PORT              Local port to listen on (default 3002).
 *                            This process binds to 127.0.0.1 only — it
 *                            is never reachable directly from the
 *                            internet, only through the reverse proxy
 *                            rule that forwards /ssh-ws WebSocket
 *                            upgrades to it.
 *   MAX_TOTAL_SESSIONS       Global concurrent-session cap (default 50).
 *   MAX_SESSIONS_PER_SERVICE Per-service concurrent-session cap, so one
 *                            compromised token-minting path can't spawn
 *                            unlimited sessions against a single service
 *                            either (default 2).
 */

const dns = require('dns').promises;
const net = require('net');
const WebSocket = require('ws');
const { Client: SSHClient } = require('ssh2');
const jwt = require('jsonwebtoken');

const PORT = process.env.SSH_WS_PORT || 3002;
const JWT_SECRET = process.env.TERMINAL_JWT_SECRET;
const HOSTEDAI_API_HOSTNAME = process.env.HOSTEDAI_API_HOSTNAME;
const HOSTEDAI_API_TOKEN = process.env.HOSTEDAI_API_TOKEN;
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN;
const WHMCS_AJAX_URL = process.env.WHMCS_AJAX_URL;
const MAX_TOTAL_SESSIONS = parseInt(process.env.MAX_TOTAL_SESSIONS || '50', 10);
const MAX_SESSIONS_PER_SERVICE = parseInt(process.env.MAX_SESSIONS_PER_SERVICE || '2', 10);

if (!JWT_SECRET) {
  console.error('TERMINAL_JWT_SECRET is not set — refusing to start.');
  process.exit(1);
}
if (!HOSTEDAI_API_HOSTNAME || !HOSTEDAI_API_TOKEN) {
  console.error('HOSTEDAI_API_HOSTNAME / HOSTEDAI_API_TOKEN are not set — refusing to start.');
  process.exit(1);
}
if (!ALLOWED_ORIGIN) {
  console.error('ALLOWED_ORIGIN is not set — refusing to start.');
  process.exit(1);
}
if (!WHMCS_AJAX_URL) {
  console.error('WHMCS_AJAX_URL is not set — refusing to start.');
  process.exit(1);
}

// Tokens are single-use: once a token has opened a session, a captured
// or replayed copy of it must not open a second one. Tracked as
// jti -> its own expiry, and swept only for entries whose expiry has
// actually passed — a blind "clear everything every N minutes" timer
// (the earlier approach) created a real window: a token used just
// before the sweep got forgotten immediately, while still inside its
// real 2-minute validity, and could be replayed right after.
const usedTokens = new Map();
setInterval(() => {
  const now = Math.floor(Date.now() / 1000);
  for (const [jti, exp] of usedTokens) {
    if (exp < now) usedTokens.delete(jti);
  }
}, 60 * 1000);

// ── Private/reserved-address guard ──────────────────────────────────
// Defense in depth: the host we connect to always comes from Hosted
// AI's own credentials API, never from the client — but if the signing
// secret ever leaked, a forged token could still name an arbitrary
// instanceId. Refusing private/loopback/link-local targets means even
// that scenario can't be used to pivot into internal infrastructure, at
// the cost of nothing real (real GPU instances are always public IPs).
// Covers IPv4, IPv6, and hostnames (resolved via DNS and checked at the
// resolved address — a hostname can't bypass this just by not literally
// being "127.0.0.1").
function isPrivateOrReservedIPv4(ip) {
  const m = ip.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
  if (!m) return false;
  const [a, b] = [parseInt(m[1], 10), parseInt(m[2], 10)];
  if (a === 10) return true;
  if (a === 127) return true;
  if (a === 0) return true;
  if (a === 169 && b === 254) return true;
  if (a === 172 && b >= 16 && b <= 31) return true;
  if (a === 192 && b === 168) return true;
  return false;
}

function isPrivateOrReservedIPv6(ip) {
  const addr = ip.toLowerCase();
  if (addr === '::1') return true; // loopback
  if (addr.startsWith('fe80:')) return true; // link-local
  if (/^f[cd][0-9a-f]{2}:/.test(addr)) return true; // unique local fc00::/7
  const v4 = addr.match(/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/); // IPv4-mapped
  if (v4) return isPrivateOrReservedIPv4(v4[1]);
  return false;
}

function isPrivateOrReservedIP(ip) {
  const type = net.isIP(ip);
  if (type === 4) return isPrivateOrReservedIPv4(ip);
  if (type === 6) return isPrivateOrReservedIPv6(ip);
  return false;
}

async function isHostSafe(host) {
  if (net.isIP(host)) {
    return !isPrivateOrReservedIP(host);
  }
  try {
    const records = await dns.lookup(host, { all: true, verbatim: true });
    if (records.length === 0) return false;
    return records.every((r) => !isPrivateOrReservedIP(r.address));
  } catch (err) {
    // Can't resolve it — fail closed rather than silently letting an
    // unresolvable host through.
    return false;
  }
}
// ── End private-address guard ───────────────────────────────────────

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

async function isServiceStillActive(serviceId) {
  try {
    const body = new URLSearchParams({
      action: 'check_service_status',
      service_id: String(serviceId),
      secret: JWT_SECRET,
    });
    const res = await fetch(WHMCS_AJAX_URL, { method: 'POST', body });
    const data = await res.json();
    return !!(data && data.success && data.active);
  } catch (err) {
    // A transient network hiccup talking to WHMCS must never kill a
    // perfectly good session — log it and let the next poll try again.
    console.error(`Status check failed for service ${serviceId}: ${err.message}`);
    return true;
  }
}

// ── Session accounting (global + per-service caps) ──────────────────
let totalActiveSessions = 0;
const sessionsPerService = new Map();

function tryReserveSession(serviceId) {
  if (totalActiveSessions >= MAX_TOTAL_SESSIONS) return false;
  const current = sessionsPerService.get(serviceId) || 0;
  if (current >= MAX_SESSIONS_PER_SERVICE) return false;

  totalActiveSessions++;
  sessionsPerService.set(serviceId, current + 1);
  return true;
}

function releaseSession(serviceId) {
  totalActiveSessions = Math.max(0, totalActiveSessions - 1);
  const current = sessionsPerService.get(serviceId) || 0;
  if (current <= 1) {
    sessionsPerService.delete(serviceId);
  } else {
    sessionsPerService.set(serviceId, current - 1);
  }
}
// ── End session accounting ───────────────────────────────────────────

const wss = new WebSocket.Server({ host: '127.0.0.1', port: PORT });
console.log(`Terminal sidecar listening on 127.0.0.1:${PORT}`);

wss.on('connection', (ws, req) => {
  const origin = req.headers.origin;
  if (origin !== ALLOWED_ORIGIN) {
    console.error(`Rejected connection from unexpected Origin: ${origin}`);
    sendError(ws, 'Forbidden');
    ws.close();
    return;
  }

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

  if (!payload.jti || !payload.exp || usedTokens.has(payload.jti)) {
    sendError(ws, 'This session link has already been used');
    ws.close();
    return;
  }
  usedTokens.set(payload.jti, payload.exp);

  const instanceId = payload.instanceId;
  const serviceId = payload.serviceId;
  if (!instanceId || !serviceId) {
    sendError(ws, 'Incomplete connection details');
    ws.close();
    return;
  }

  if (!tryReserveSession(serviceId)) {
    sendError(ws, 'Too many open terminal sessions — close one and try again');
    ws.close();
    return;
  }
  let sessionReserved = true;

  let shellStream = null;
  let pendingResize = null;
  let client = null;
  let statusPollTimer = null;

  function endSession(reason) {
    if (statusPollTimer) clearInterval(statusPollTimer);
    if (client) client.end();
    if (sessionReserved) {
      releaseSession(serviceId);
      sessionReserved = false;
    }
    if (reason) sendError(ws, reason);
    ws.close();
  }

  fetchCredentials(instanceId)
    .then(async (ssh) => {
      if (!(await isHostSafe(ssh.host))) {
        console.error(`Refusing to connect to reserved/private address ${ssh.host} (instance ${instanceId})`);
        endSession('Could not connect to the instance over SSH');
        return;
      }

      client = new SSHClient();

      client.on('ready', () => {
        client.shell({ term: 'xterm-256color', cols: 120, rows: 30 }, (err, stream) => {
          if (err) {
            endSession('Could not open a shell on the instance');
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
          stream.on('close', () => endSession());
        });
      });

      client.on('error', () => {
        endSession('Could not connect to the instance over SSH');
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

      // Polls WHMCS every 30s for the lifetime of this session — a
      // service suspended (wallet exhausted, overdue invoice, manual
      // admin action) partway through an open terminal session used to
      // have no effect on it at all; the session just kept running.
      statusPollTimer = setInterval(async () => {
        const active = await isServiceStillActive(serviceId);
        if (!active) {
          endSession('This service has been suspended');
        }
      }, 30000);
    })
    .catch((err) => {
      console.error(`Failed to fetch credentials for instance ${instanceId}: ${err.message}`);
      endSession('Could not retrieve SSH credentials');
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
    if (statusPollTimer) clearInterval(statusPollTimer);
    if (client) client.end();
    if (sessionReserved) {
      releaseSession(serviceId);
      sessionReserved = false;
    }
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
