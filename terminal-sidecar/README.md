# Terminal sidecar

This is piece **1 of 3** of the browser SSH terminal feature (see the plan
discussed with the team). On its own, this script does nothing useful yet —
nothing mints tokens for it to verify. The other two pieces, not built yet,
are:

2. A new WHMCS/PHP endpoint that checks the logged-in client actually owns
   the requested service, fetches its SSH credentials, and signs a token
   for this sidecar to verify.
3. A "Terminal" button on the service management page that opens a
   WebSocket to this sidecar with that token.

## What this file is

A single, always-running Node.js process. It listens for WebSocket
connections, checks the signed token each one presents, and — if valid —
opens a real SSH connection to the instance and relays keystrokes/output
in both directions. It binds to `127.0.0.1` only; it is never reachable
directly from the internet, only through a reverse-proxy rule that forwards
WebSocket upgrades on a path like `/ssh-ws` to it.

## Running it

```bash
cd terminal-sidecar
npm install
TERMINAL_JWT_SECRET="<a long random value, same one the PHP side will use>" \
SSH_WS_PORT=3002 \
npm start
```

`TERMINAL_JWT_SECRET` must be identical on the PHP side once that's built —
it's how the two halves trust each other. Generate one with, e.g.:

```bash
openssl rand -hex 32
```

## Still to confirm before this goes live

- **Where it runs**: does Plesk's Node.js app feature manage this for you
  (deploy/restart through the panel), or do we need raw SSH access to set
  it up as a systemd service that restarts automatically and survives a
  reboot?
- **The proxy rule**: whichever domain serves WHMCS needs a rule forwarding
  WebSocket upgrades on `/ssh-ws` to `127.0.0.1:3002` (exact config depends
  on whether Plesk is fronted by nginx or Apache for that domain).
