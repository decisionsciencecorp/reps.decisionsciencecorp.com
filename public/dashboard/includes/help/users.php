<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-person-gear me-2"></i>Users &amp; seats</h2>

<p>Admin opens <strong>Users → Roster &amp; seats</strong>. The page is a compact ledger: one row per seat. Expand a row to edit profile/scope, reset password, or manage API keys. Create user is collapsed behind a summary so the list stays scannable as volume grows.</p>

<h3 class="h5 mt-4">Provisioning checklist</h3>
<ol>
  <li>Create username (2–40: letters, numbers, <code>._-</code>), display name, role, password.</li>
  <li>Set <strong>Shop ID</strong> / <strong>Operator ID</strong> when the seat should be scoped (owner, employee, individual).</li>
  <li>For automation, create an <strong>agent</strong> seat and issue an API key from the expanded row (shown once).</li>
  <li>Link Shift workers under <a href="/dashboard/shift-match.php">Users → Shift match</a> (admin + ops).</li>
</ol>

<h3 class="h5 mt-4">Who can provision</h3>
<p>Only <strong>admin</strong> can open the roster and create/reset seats. Ops sees the Users menu for <em>Shift match</em> only — if they hit the roster URL they are redirected to Shift match.</p>

<h3 class="h5 mt-4">API keys (UI)</h3>
<ul>
  <li>Created and revoked on the expanded user row.</li>
  <li>Authenticate <code>/dashboard/api/*</code> via <code>X-API-Key</code> or <code>Authorization: Bearer</code>.</li>
  <li>Never put keys in query strings, Tasks bodies, or chat.</li>
</ul>

<p class="mb-0">See <a href="/dashboard/help.php?page=api">HTTP API (book)</a> for programmatic key create/revoke (admin only).</p>
