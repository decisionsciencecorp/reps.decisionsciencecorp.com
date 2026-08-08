<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-tools me-2"></i>Troubleshooting</h2>

<table class="table table-sm align-middle">
  <thead><tr><th>Symptom</th><th>Likely cause</th><th>What to try</th></tr></thead>
  <tbody>
    <tr>
      <td>Redirected to Home from a page</td>
      <td>Role lacks that nav key</td>
      <td>Check Help → Roles; ask admin to adjust seat</td>
    </tr>
    <tr>
      <td>Empty shops / sessions</td>
      <td>Scope IDs unset, or fixture mode with empty book</td>
      <td>Confirm shop_id / operator_id; admin sync if live</td>
    </tr>
    <tr>
      <td>API <code>401 unauthorized</code></td>
      <td>Missing/expired session or bad API key</td>
      <td>Re-login or rotate key; never query-string keys</td>
    </tr>
    <tr>
      <td>API <code>403 forbidden</code> on Shift routes</td>
      <td>Caller not admin/ops/agent</td>
      <td>Use a permitted seat or key</td>
    </tr>
    <tr>
      <td><code>cardinal_blocked</code></td>
      <td>Write attempted against live Joinshift from a forbid path</td>
      <td>Use fake stub for automation; live writes only intentional ops</td>
    </tr>
    <tr>
      <td><code>shift_upstream</code></td>
      <td>Partner HTTP failure / cookie / network</td>
      <td>Check cookie jar, base URL, Partner status</td>
    </tr>
    <tr>
      <td>Stripe webhook ignored</td>
      <td>Bad signature or wrong endpoint secret</td>
      <td>Verify host Stripe webhook secret; check logs</td>
    </tr>
    <tr>
      <td>Footer says fixture fallback</td>
      <td>Live data disabled</td>
      <td>Settings → Sync (admin/ops) / env</td>
    </tr>
  </tbody>
</table>

<p class="mb-0">Still stuck: leave a Tasks comment with role, URL, timestamp, and response body (redact keys).</p>
