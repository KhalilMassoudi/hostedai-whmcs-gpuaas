{assign var=unique_id value=10|mt_rand:20}
<link href="{$assets}/css/style.css?v={$unique_id}" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/validator/13.7.0/validator.min.js"></script>
<script src="{$assets}/js/custom.js?v={$unique_id}"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/xterm.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/xterm.min.js"></script>

{literal}
<style>
/* ── GPU Dashboard Theme ─────────────────────────── */
.gpu-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Resource Cards */
.gpu-resource-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 16px;
}
.gpu-resource-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.gpu-resource-card .card-icon {
    font-size: 28px;
    width: 48px;
    text-align: center;
    flex-shrink: 0;
}
.gpu-resource-card .card-info .card-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #555555 !important;
    margin: 0;
}
.gpu-resource-card .card-info .card-value {
    font-size: 20px;
    font-weight: 700;
    color: #111111 !important;
    margin: 2px 0 0 0;
}
.gpu-resource-card .card-info .card-limit {
    font-size: 11px;
    color: #777777 !important;
    margin: 0;
}

/* Fix resource card text visibility */
.overview-card h3,
.overview-card p,
.overview-card span,
.overview-card div {
    color: #333 !important;
}
[class*="card"] h3,
[class*="card"] p {
    color: #333 !important;
}

/* Section panels */
.gpu-section {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    margin: 0 16px 16px 16px;
    overflow: hidden;
}
.gpu-section-header {
    background: rgba(255,255,255,0.07);
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 600;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    gap: 8px;
}
.gpu-section-body {
    padding: 16px;
}

/* Info table */
.gpu-info-table {
    width: 100%;
    border-collapse: collapse;
}
.gpu-info-table tr {
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.gpu-info-table tr:last-child {
    border-bottom: none;
}
.gpu-info-table th {
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,0.5) !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    width: 160px;
    vertical-align: middle;
}
.gpu-info-table td {
    padding: 10px 12px;
    font-size: 14px;
    color: #ffffff !important;
    vertical-align: middle;
}

/* Status badges */
.gpu-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.gpu-badge-running {
    background: rgba(63,185,80,0.2);
    color: #3fb950 !important;
    border: 1px solid rgba(63,185,80,0.4);
}
.gpu-badge-stopped {
    background: rgba(248,81,73,0.2);
    color: #f85149 !important;
    border: 1px solid rgba(248,81,73,0.4);
}
.gpu-badge-provisioning {
    background: rgba(227,179,65,0.2);
    color: #e3b341 !important;
    border: 1px solid rgba(227,179,65,0.4);
}

/* Team / instances table */
.gpu-members-table {
    width: 100%;
    border-collapse: collapse;
}
.gpu-members-table th {
    padding: 8px 12px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,0.4) !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.gpu-members-table td {
    padding: 10px 12px;
    font-size: 13px;
    color: rgba(255,255,255,0.8) !important;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* Info / empty message */
.gpu-info-msg {
    color: rgba(255,255,255,0.5) !important;
    font-size: 13px;
    text-align: center;
    padding: 8px 0;
    margin: 0;
}

/* SSH / monospace values */
.gpu-mono {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: #4a9fd4 !important;
}

/* Action buttons */
.gpu-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.gpu-btn {
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    border: 1px solid transparent;
    background: transparent;
}
.gpu-btn-start {
    background: rgba(63,185,80,0.2);
    color: #3fb950 !important;
    border-color: rgba(63,185,80,0.4);
}
.gpu-btn-start:hover { background: rgba(63,185,80,0.35); color: #3fb950 !important; }
.gpu-btn-stop {
    background: rgba(227,179,65,0.2);
    color: #e3b341 !important;
    border-color: rgba(227,179,65,0.4);
}
.gpu-btn-stop:hover { background: rgba(227,179,65,0.35); color: #e3b341 !important; }
.gpu-btn-restart {
    background: rgba(74,159,212,0.2);
    color: #4a9fd4 !important;
    border-color: rgba(74,159,212,0.4);
}
.gpu-btn-restart:hover { background: rgba(74,159,212,0.35); color: #4a9fd4 !important; }
.gpu-btn-delete {
    background: rgba(248,81,73,0.15);
    color: #f85149 !important;
    border-color: rgba(248,81,73,0.3);
    margin-left: auto;
}
.gpu-btn-delete:hover { background: rgba(248,81,73,0.3); color: #f85149 !important; }

/* Terminal logs */
.gpu-terminal {
    background: #161b22;
    border-radius: 8px;
    padding: 14px;
    max-height: 220px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    border: 1px solid rgba(255,255,255,0.06);
}
.gpu-terminal * { color: inherit; }
.gpu-log-entry {
    display: flex;
    gap: 10px;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    flex-wrap: wrap;
}
.gpu-log-time   { color: #58a6ff; flex-shrink: 0; }
.gpu-log-event-stop  { color: #f85149; }
.gpu-log-event-start { color: #3fb950; }
.gpu-log-event-other { color: #e3b341; }
.gpu-log-desc   { color: #c9d1d9; }
</style>
{/literal}

<div class="gpu-dashboard">

  <!-- Resource Overview -->
  <div class="gpu-section" style="margin:16px">
    <div class="gpu-section-header">
      &#9889; Resource Overview
      {if $loginURL}
        <a href="{$loginURL}" class="gpu-btn gpu-btn-restart" style="margin-left:auto;padding:4px 14px;font-size:12px">Login</a>
      {/if}
    </div>
    <div class="gpu-resource-grid">
      {foreach from=$resourcesData key=key item=item}
      <div class="gpu-resource-card">
        <div class="card-icon">
          {if $key == 'cores'}&#128421;
          {elseif $key == 'memory'}&#128190;
          {elseif $key == 'storage'}&#128465;
          {elseif $key == 'gpus'}&#127918;
          {elseif $key == 'ephemeral_storage'}&#9889;
          {elseif $key == 'shared_storage'}&#128279;
          {else}&#128230;{/if}
        </div>
        <div class="card-info">
          <p class="card-label">{$key|upper|replace:'_':' '}</p>
          <p class="card-value">
            {$item->used}
            <span style="font-size:13px;font-weight:400;opacity:0.6">
              {if $key == 'cores'}{$LANG['cores']}
              {elseif $key == 'gpus'}{$LANG['no_of_cards']}
              {else}{$LANG['storage_GB']}{/if}
            </span>
          </p>
          <p class="card-limit">
            {if $item->available == -1}
              {$LANG['unlimited']}
            {else}
              Limit: {$item->available}
              {if $key == 'cores'} {$LANG['cores']}
              {elseif $key == 'gpus'} {$LANG['no_of_cards']}
              {else} {$LANG['storage_GB']}{/if}
              ({$item->percent}%)
            {/if}
          </p>
        </div>
      </div>
      {foreachelse}
      <div style="grid-column:1/-1;text-align:center;padding:20px;color:rgba(255,255,255,0.3)">
        No resource data available
      </div>
      {/foreach}
    </div>
  </div>

  <!-- Team Members -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128101; Team Members</div>
    <div class="gpu-section-body" style="padding:0">
      <table class="gpu-members-table">
        <thead>
          <tr>
            <th>{$LANG['email']}</th>
            <th>{$LANG['role']}</th>
            <th>{$LANG['status']}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$teammembers key=key item=member}
          <tr>
            <td>{$member->user->email|default:'&#8212;'}</td>
            <td>{$member->role->label|default:'&#8212;'}</td>
            <td>{$member->status|ucfirst|default:'&#8212;'}</td>
          </tr>
          {foreachelse}
          <tr>
            <td colspan="3" style="text-align:center;color:rgba(255,255,255,0.3);padding:20px">
              No team members found
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>

  {* ── GPU Pool section ── *}
  {if $poolId > 0 && $poolData}

  <!-- GPU Pool -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128311; GPU Pool</div>
    <div class="gpu-section-body" style="padding:0">
      <table class="gpu-info-table">
        <tr>
          <th>Pool Name</th>
          <td>{$poolData->pool_label}{if $poolData->pool_label && $poolData->pool_name} &mdash; {/if}{$poolData->pool_name}</td>
        </tr>
        <tr>
          <th>Pool ID</th>
          <td style="font-size:12px;opacity:0.7">{$poolData->pool_id}</td>
        </tr>
        <tr>
          <th>Region</th>
          <td>{if $poolData->region_name}{$poolData->region_name}{else}{$poolData->city}{/if}</td>
        </tr>
        <tr>
          <th>Country</th>
          <td>{$poolData->country_code|upper}</td>
        </tr>
        <tr>
          <th>Subscription</th>
          <td>
            {if $poolSubscription}
              {if $poolSubscription->status == 'active'}
                <span class="gpu-badge gpu-badge-running">Active</span>
              {elseif $poolSubscription->status == 'suspended'}
                <span class="gpu-badge gpu-badge-stopped">Suspended</span>
              {else}
                <span class="gpu-badge gpu-badge-provisioning">{$poolSubscription->status|ucfirst}</span>
              {/if}
            {else}
              <span class="gpu-badge gpu-badge-provisioning">Unknown</span>
            {/if}
          </td>
        </tr>
        <tr>
          <th>Source</th>
          <td>{$poolData->pool_source|ucfirst}</td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Running Instances -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128640; Running Instances</div>
    <div class="gpu-section-body" style="padding:0">
      {if $instances}
      <table class="gpu-members-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Status</th>
            <th>GPU</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$instances item=instance}
          <tr>
            <td>{$instance->name}</td>
            <td>{$instance->status|ucfirst}</td>
            <td>{$instance->gpu}</td>
            <td>{$instance->created_at}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      {else}
      <div class="gpu-section-body">
        <p class="gpu-info-msg">No running instances on this pool.</p>
      </div>
      {/if}
    </div>
  </div>

  {/if}

  {* ── GPU Instance monitoring ── *}
  {if $instanceId}

  <!-- GPU Instance Info -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128421; GPU Instance</div>
    <div class="gpu-section-body" style="padding:0">
      {if $instanceDetails}
      {assign var="instanceStatus" value=$instanceDetails->status|lower}
      <table class="gpu-info-table">
        <tr>
          <th>Name</th>
          <td>{$instanceDetails->name|default:'&#8212;'}</td>
        </tr>
        <tr>
          <th>Instance ID</th>
          <td style="font-family:monospace;font-size:12px;opacity:0.7">{$instanceId}</td>
        </tr>
        <tr>
          <th>Status</th>
          <td>
            {if $instanceStatus == 'running'}
              <span class="gpu-badge gpu-badge-running">&#9679; Running</span>
            {elseif $instanceStatus == 'stopped' || $instanceStatus == 'stop'}
              <span class="gpu-badge gpu-badge-stopped">&#9679; Stopped</span>
            {elseif $instanceStatus == 'provisioning' || $instanceStatus == 'starting'}
              <span class="gpu-badge gpu-badge-provisioning">&#9679; Provisioning</span>
            {else}
              <span class="gpu-badge gpu-badge-provisioning">&#9679; {$instanceDetails->status|default:'Unknown'}</span>
            {/if}
          </td>
        </tr>
        <tr>
          <th>Region</th>
          <td>
            {if $instanceDetails->region}
              {$instanceDetails->region->region_name|default:'&#8212;'}
              {if $instanceDetails->region->city}
                <span style="opacity:0.5;font-size:12px">({$instanceDetails->region->city})</span>
              {/if}
            {else}&#8212;{/if}
          </td>
        </tr>
        <tr>
          <th>Instance Type</th>
          <td>
            {if $instanceDetails->instance_type}
              {assign var="instCpu" value=$instanceDetails->instance_type->cpu_cores}
              {assign var="instRam" value=$instanceDetails->instance_type->ram_mb}
              {$instanceDetails->instance_type->name|default:'&#8212;'}
              {if $instCpu || $instRam}
                <span style="opacity:0.5;font-size:12px">
                  &mdash; {if $instCpu}{$instCpu} vCPU{/if}{if $instCpu && $instRam}, {/if}{if $instRam}{$instRam} MB RAM{/if}
                </span>
              {/if}
            {else}&#8212;{/if}
          </td>
        </tr>
        <tr>
          <th>IP Address</th>
          <td class="gpu-mono">
            {if $instanceDetails->ip && $instanceDetails->ip.0}
              {$instanceDetails->ip.0}
            {else}<span style="opacity:0.4">Pending</span>{/if}
          </td>
        </tr>
        <tr>
          <th>Created</th>
          <td>
            {if $instanceDetails->created_at}{$instanceDetails->created_at|date_format:"%d %b %Y %H:%M"}{else}&#8212;{/if}
          </td>
        </tr>
      </table>
      {else}
      <div class="gpu-section-body">
        <p class="gpu-info-msg">Instance details not available.</p>
      </div>
      {/if}
    </div>
  </div>

  <!-- Resource Usage -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128202; Resource Usage</div>
    <div class="gpu-section-body">
      <p class="gpu-info-msg">
        &#128161; Real-time GPU metrics are available directly on your instance. Connect via SSH to monitor usage.
      </p>
    </div>
  </div>

  <!-- Current Month Cost -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128176; Current Month Cost</div>
    <div class="gpu-section-body" style="{if !$instanceBilling}padding:16px{else}padding:0{/if}">
      {if $instanceBilling}
      <table class="gpu-info-table">
        <tr>
          <th>Total Cost</th>
          <td>{$instanceBilling->total_cost|default:'0'} {$instanceBilling->currency|default:'USD'}</td>
        </tr>
        <tr>
          <th>GPU Hours</th>
          <td>{$instanceBilling->gpu_hours|default:'0'}</td>
        </tr>
      </table>
      {else}
      <p class="gpu-info-msg">
        &#128197; Billing data will appear here after your first billing cycle completes.
      </p>
      {/if}
    </div>
  </div>

  <!-- SSH Access -->
  <div class="gpu-section">
    <div class="gpu-section-header" style="display:flex;justify-content:space-between;align-items:center">
      <span>&#128272; SSH Access</span>
      {if $instanceCredentials}
      <button type="button" class="gpu-btn gpu-btn-restart" style="padding:4px 12px;font-size:12px" onclick="hostedaiOpenTerminal()">
        &#128421; Terminal
      </button>
      {/if}
    </div>
    <div class="gpu-section-body" style="padding:0">
      {if $instanceCredentials}
      <table class="gpu-info-table">
        <tr>
          <th>Host</th>
          <td class="gpu-mono">{$instanceCredentials->ip|default:'&#8212;'}</td>
        </tr>
        <tr>
          <th>Port</th>
          <td class="gpu-mono">{$instanceCredentials->port|default:'22'}</td>
        </tr>
        <tr>
          <th>Username</th>
          <td class="gpu-mono">{$instanceCredentials->username|default:'ubuntu'}</td>
        </tr>
        <tr>
          <th>Password</th>
          <td>
            <span id="ssh-password" style="display:none;font-family:monospace;color:#4a9fd4">
              {$instanceCredentials->password|default:'&#8212;'}
            </span>
            <span id="ssh-password-mask" style="font-family:monospace;color:rgba(255,255,255,0.3)">
              &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;
            </span>
            &nbsp;
            <button type="button" id="ssh-toggle-btn" onclick="togglePassword()"
              class="gpu-btn gpu-btn-restart" style="padding:3px 10px;font-size:12px">
              Show
            </button>
          </td>
        </tr>
        <tr>
          <th>SSH Command</th>
          <td class="gpu-mono" style="font-size:12px">
            ssh {$instanceCredentials->username|default:'ubuntu'}@{$instanceCredentials->ip|default:''} -p {$instanceCredentials->port|default:'22'}
          </td>
        </tr>
      </table>
      {else}
      <div class="gpu-section-body">
        <p class="gpu-info-msg">SSH credentials not yet available.</p>
      </div>
      {/if}
    </div>
  </div>

  <!-- Browser SSH Terminal Modal -->
  <div id="hostedai-terminal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;" onclick="if (event.target === this) hostedaiCloseTerminal();">
    <div style="max-width:900px;margin:40px auto;background:#1e1e1e;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;height:calc(100vh - 80px);">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#2d2d2d;color:#fff;font-family:monospace;">
        <span id="hostedai-terminal-status">Connecting&hellip;</span>
        <button type="button" onclick="hostedaiCloseTerminal()" class="gpu-btn gpu-btn-delete" style="padding:3px 10px;font-size:12px">Close</button>
      </div>
      <div id="hostedai-terminal-container" style="flex:1;padding:8px;min-height:0"></div>
    </div>
  </div>

  <!-- Recent Logs -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#128203; Recent Logs</div>
    <div class="gpu-section-body">
      {if $instanceLogs && $instanceLogs->items}
      <div class="gpu-terminal">
        {foreach from=$instanceLogs->items item=logEntry}
        <div class="gpu-log-entry">
          <span class="gpu-log-time">
            {if $logEntry->started_at}{$logEntry->started_at|date_format:"%d %b %H:%M:%S"}{/if}
          </span>
          {assign var="logCssClass" value="gpu-log-event-other"}
          {if $logEntry->event == 'stop'}{assign var="logCssClass" value="gpu-log-event-stop"}{/if}
          {if $logEntry->event == 'start'}{assign var="logCssClass" value="gpu-log-event-start"}{/if}
          <span class="{$logCssClass}">[{$logEntry->event|upper|default:''}]</span>
          <span class="gpu-log-desc">{$logEntry->event_description|default:''}</span>
        </div>
        {/foreach}
      </div>
      {else}
      <p class="gpu-info-msg">No logs available yet.</p>
      {/if}
    </div>
  </div>

  <!-- Manage Instance -->
  <div class="gpu-section">
    <div class="gpu-section-header">&#9881; Manage Instance</div>
    <div class="gpu-section-body">
      <div class="gpu-actions">

        <form method="post" style="margin:0">
          <input type="hidden" name="instance_action" value="start">
          <button type="submit" class="gpu-btn gpu-btn-start">&#9654; Start</button>
        </form>

        <form method="post" style="margin:0">
          <input type="hidden" name="instance_action" value="stop">
          <button type="submit" class="gpu-btn gpu-btn-stop">&#9632; Stop</button>
        </form>

        <form method="post" style="margin:0">
          <input type="hidden" name="instance_action" value="restart">
          <button type="submit" class="gpu-btn gpu-btn-restart">&#8635; Restart</button>
        </form>

        <form method="post" style="margin:0;margin-left:auto" onsubmit="return confirmDelete()">
          <input type="hidden" name="delete_instance" value="1">
          <button type="submit" class="gpu-btn gpu-btn-delete">&#128465; Delete Instance</button>
        </form>

      </div>
    </div>
  </div>

  <script type="text/javascript">
  var HOSTEDAI_SERVICE_ID = {$serviceId};
  </script>
  {literal}
  <script type="text/javascript">
  function togglePassword() {
      var p = document.getElementById('ssh-password');
      var m = document.getElementById('ssh-password-mask');
      var b = document.getElementById('ssh-toggle-btn');
      if (p.style.display === 'none') {
          p.style.display = 'inline';
          m.style.display = 'none';
          b.textContent = 'Hide';
      } else {
          p.style.display = 'none';
          m.style.display = 'inline';
          b.textContent = 'Show';
      }
  }
  function confirmDelete() {
      return confirm('WARNING: This will permanently delete your instance and all data.\n\nThis cannot be undone.\n\nAre you sure?');
  }

  // ── Browser SSH Terminal ──────────────────────────────────────────
  var hostedaiTerm = null;
  var hostedaiTermSocket = null;

  function hostedaiTermToBase64(str) {
      return btoa(String.fromCharCode.apply(null, new TextEncoder().encode(str)));
  }
  function hostedaiTermFromBase64(str) {
      var bytes = Uint8Array.from(atob(str), function (c) { return c.charCodeAt(0); });
      return new TextDecoder().decode(bytes);
  }

  function hostedaiSetTerminalStatus(text) {
      var el = document.getElementById('hostedai-terminal-status');
      if (el) el.textContent = text;
  }

  function hostedaiOpenTerminal() {
      document.getElementById('hostedai-terminal-overlay').style.display = 'block';
      hostedaiSetTerminalStatus('Requesting session…');

      var formData = new FormData();
      formData.append('action', 'generate_terminal_token');
      formData.append('service_id', HOSTEDAI_SERVICE_ID);

      fetch('/modules/servers/hostedai/lib/ajax.php', { method: 'POST', body: formData })
          .then(function (r) { return r.json(); })
          .then(function (data) {
              if (!data.success || !data.token) {
                  hostedaiSetTerminalStatus('Error: ' + (data.message || 'could not start session'));
                  return;
              }
              hostedaiConnectTerminal(data.token);
          })
          .catch(function () {
              hostedaiSetTerminalStatus('Error: request failed');
          });
  }

  function hostedaiConnectTerminal(token) {
      var container = document.getElementById('hostedai-terminal-container');
      container.innerHTML = '';

      hostedaiTerm = new Terminal({ cols: 120, rows: 30, theme: { background: '#1e1e1e' } });
      hostedaiTerm.open(container);

      var proto = (location.protocol === 'https:') ? 'wss:' : 'ws:';
      hostedaiTermSocket = new WebSocket(proto + '//' + location.host + '/ssh-ws?token=' + encodeURIComponent(token));

      hostedaiTermSocket.onopen = function () {
          hostedaiSetTerminalStatus('Connected');
      };
      hostedaiTermSocket.onmessage = function (event) {
          var msg;
          try { msg = JSON.parse(event.data); } catch (e) { return; }

          if (msg.type === 'data') {
              hostedaiTerm.write(hostedaiTermFromBase64(msg.data));
          } else if (msg.type === 'error') {
              hostedaiSetTerminalStatus('Error: ' + msg.message);
          } else if (msg.type === 'connected') {
              hostedaiSetTerminalStatus('Connected');
          }
      };
      hostedaiTermSocket.onclose = function () {
          hostedaiSetTerminalStatus('Disconnected');
      };
      hostedaiTermSocket.onerror = function () {
          hostedaiSetTerminalStatus('Connection error');
      };

      hostedaiTerm.onData(function (data) {
          if (hostedaiTermSocket && hostedaiTermSocket.readyState === WebSocket.OPEN) {
              hostedaiTermSocket.send(JSON.stringify({ type: 'data', data: hostedaiTermToBase64(data) }));
          }
      });
  }

  function hostedaiCloseTerminal() {
      document.getElementById('hostedai-terminal-overlay').style.display = 'none';
      if (hostedaiTermSocket) { hostedaiTermSocket.close(); hostedaiTermSocket = null; }
      if (hostedaiTerm) { hostedaiTerm.dispose(); hostedaiTerm = null; }
  }

  document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hostedaiCloseTerminal();
  });
  </script>
  {/literal}

  {/if}

</div>{* end gpu-dashboard *}