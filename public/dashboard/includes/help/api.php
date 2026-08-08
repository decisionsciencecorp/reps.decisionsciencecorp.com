<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
$base = 'https://reps.decisionsciencecorp.com/dashboard/api';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="h4 mb-0"><i class="bi bi-code-slash me-2"></i>HTTP API — Reps book</h2>
  <span class="badge text-bg-primary">REST · JSON · v1</span>
</div>

<div class="alert alert-info">
  <strong>Base:</strong> <code><?php echo htmlspecialchars($base); ?>/</code><br>
  <strong>Auth:</strong> session cookie <em>or</em> <code>X-API-Key: …</code> / <code>Authorization: Bearer …</code> (never query-string keys).<br>
  <strong>Content-Type:</strong> <code>application/json</code> for JSON bodies.<br>
  <strong>Success:</strong> <code>{"ok": true, …}</code> · <strong>Error:</strong> <code>{"ok": false, "error": "code", "message": "…"}</code> + HTTP status.
</div>

<p>This chapter covers the <strong>SQLite book</strong> endpoints (Slice D). Shift Partner proxy is a separate chapter:
<?php if (repsDashCanSeeHelpPage('api-shift')): ?>
  <a href="/dashboard/help.php?page=api-shift">Shift Partner API</a>.
<?php else: ?>
  (not available for your role).
<?php endif; ?>
</p>

<h3 class="h5 mt-4">Authentication</h3>
<div class="row g-3">
  <div class="col-md-6">
    <div class="border rounded p-3 h-100">
      <h4 class="h6">API key</h4>
      <pre class="bg-light p-2 small mb-2"><code>X-API-Key: rd_live_…</code></pre>
      <pre class="bg-light p-2 small mb-0"><code>Authorization: Bearer rd_live_…</code></pre>
      <p class="small text-muted mt-2 mb-0">If a key is present it wins over the session cookie. Prefer keys on the <strong>agent</strong> seat. Agent keys elevate to ops-equivalent book scope for reads.</p>
    </div>
  </div>
  <div class="col-md-6">
    <div class="border rounded p-3 h-100">
      <h4 class="h6">Session cookie</h4>
      <p class="small mb-0">Same browser login as the dashboard. Useful for internal tools and Help examples. Scoped exactly like the UI for that role.</p>
    </div>
  </div>
</div>

<h3 class="h5 mt-4">Who may call what</h3>
<table class="table table-sm">
  <thead><tr><th>Surface</th><th>Admin</th><th>Ops</th><th>Agent key</th><th>Other roles</th></tr></thead>
  <tbody>
    <tr><td>health / me / list-* / get-* / money-summary</td><td>Yes</td><td>Yes</td><td>Yes (elevated reads)</td><td>Yes, scoped</td></tr>
    <tr><td>create / list / revoke API keys</td><td>Yes</td><td>—</td><td>—</td><td>—</td></tr>
    <tr><td>stripe-webhook</td><td colspan="4">Stripe signature (no seat auth)</td></tr>
    <tr><td>v1/shift/*</td><td>Yes</td><td>Yes</td><td>Yes</td><td>403</td></tr>
  </tbody>
</table>

<div class="accordion mt-4" id="rdApiBook">

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH0">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC0">Health &amp; identity</button>
    </h3>
    <div id="rdApiC0" class="accordion-collapse collapse show" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>GET health.php</code> — liveness / version-ish payload. No auth required in typical deploys (treat as public ping).</p>
        <p><code>GET me.php</code> — requires auth. Returns:</p>
        <pre class="bg-light border rounded p-2 small"><code>{
  "ok": true,
  "me": {
    "id": 1,
    "username": "otto",
    "display_name": "…",
    "role": "admin",
    "shop_id": null,
    "operator_id": null,
    "auth": "api_key" | "session",
    "live_data": true
  }
}</code></pre>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH1">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC1">Shops</button>
    </h3>
    <div id="rdApiC1" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>GET list-shops.php</code> → <code>{ ok, count, live_data, shops: […] }</code> scoped via <code>repsDashShopsForUser</code>.</p>
        <p><code>GET get-shop.php?id={int}</code> → single shop or 404 if out of scope.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH2">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC2">Operators</button>
    </h3>
    <div id="rdApiC2" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>GET list-operators.php</code> — roster rows the caller may see.</p>
        <p><code>GET get-operator.php?id={int}</code> — detail; 404 out of scope.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH3">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC3">Sessions</button>
    </h3>
    <div id="rdApiC3" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>GET list-sessions.php</code> — capture/hours list (query filters may apply per implementation).</p>
        <p><code>GET get-session.php?id={int}</code> — one session; media availability depends on Shift exposing clips.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH4">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC4">Money</button>
    </h3>
    <div id="rdApiC4" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>GET money-summary.php</code> — <code>pulse</code> for the caller; admin/ops/agent also get ledger totals when applicable. Mode follows role (portfolio vs affiliate vs solo, etc.).</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH5">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC5">API keys (admin)</button>
    </h3>
    <div id="rdApiC5" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>POST create-api-key.php</code> — admin only. JSON body:</p>
        <pre class="bg-light border rounded p-2 small"><code>{ "user_id": 12, "name": "ci-agent" }</code></pre>
        <p>Response <code>201</code> includes the raw <code>key</code> <strong>once</strong>, plus <code>preview</code>.</p>
        <p><code>GET list-api-keys.php</code> — list keys (previews only).</p>
        <p><code>POST revoke-api-key.php</code> — body <code>{ "key_id": N }</code>.</p>
        <p>UI equivalent: Users ledger → expand seat → API keys.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header" id="rdApiH6">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdApiC6">Stripe webhook</button>
    </h3>
    <div id="rdApiC6" class="accordion-collapse collapse" data-bs-parent="#rdApiBook">
      <div class="accordion-body">
        <p><code>POST stripe-webhook.php</code> — Stripe-Signature header; no Reps API key. Configure the endpoint secret on the host. Used for Connect / payout lifecycle events.</p>
      </div>
    </div>
  </div>

</div>

<h3 class="h5 mt-4">cURL examples</h3>
<pre class="bg-light border rounded p-2 small"><code># Session (after logging in — cookie jar)
curl -sS -b cookies.txt '<?php echo htmlspecialchars($base); ?>/me.php'

# API key
curl -sS -H 'X-API-Key: YOUR_KEY' '<?php echo htmlspecialchars($base); ?>/list-shops.php'

# Create key (admin)
curl -sS -X POST -H 'X-API-Key: ADMIN_KEY' -H 'Content-Type: application/json' \
  -d '{"user_id":12,"name":"worker"}' \
  '<?php echo htmlspecialchars($base); ?>/create-api-key.php'</code></pre>

<p class="small text-muted mb-0">Canonical machine notes also live in <code>public/dashboard/api/README.md</code> in the repo.</p>
