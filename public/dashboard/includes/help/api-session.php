<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-key me-2"></i>API for your seat</h2>

<p>You can call the Reps HTTP API while signed into the dashboard — the browser session cookie authenticates you. Responses are <strong>scoped to your role</strong> the same way the UI is (your shops, your sessions, your money pulse).</p>

<div class="alert alert-info">
  Staff API keys and the Shift Partner proxy are documented for admin / ops / agent only.
  If you need a long-lived key, ask an admin to create one on an appropriate seat.
</div>

<h3 class="h5 mt-4">Base URL</h3>
<pre class="bg-light border rounded p-2 small"><code>https://reps.decisionsciencecorp.com/dashboard/api/</code></pre>

<h3 class="h5 mt-4">Useful session calls</h3>
<ul>
  <li><code>GET health.php</code> — liveness</li>
  <li><code>GET me.php</code> — your principal (id, role, scope)</li>
  <li><code>GET list-shops.php</code> / <code>get-shop.php?id=</code> — shops you may see</li>
  <li><code>GET list-operators.php</code> / <code>get-operator.php?id=</code></li>
  <li><code>GET list-sessions.php</code> / <code>get-session.php?id=</code></li>
  <li><code>GET money-summary.php</code> — if your role has Money</li>
</ul>

<p class="mb-0">JSON shape: <code>{"ok": true, …}</code> on success; errors use <code>{"ok": false, "error": "…", "message": "…"}</code> with an HTTP status.</p>
