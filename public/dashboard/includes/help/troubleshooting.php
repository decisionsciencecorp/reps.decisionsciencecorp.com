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
      <td>API <code>403 forbidden</code> on Partner routes</td>
      <td>Caller not admin/ops/agent</td>
      <td>Use a permitted seat or key</td>
    </tr>
    <tr>
      <td><code>shift_upstream</code> / upstream errors</td>
      <td>Hours JSON failure, JoinShift cookie, or network</td>
      <td>Check MicroPS credentials in the dashboard database (<code>app_meta</code>) for hours; JoinShift jar for team/invite</td>
    </tr>
    <tr>
      <td>Partner web shows no hours / empty feed</td>
      <td>Temporary Partner outage (auth can still work)</td>
      <td>Do <strong>not</strong> force-ingest hours. Sync refuses empty hours when we already have local sessions. Team matching still runs. Wait; re-poll later.</td>
    </tr>
    <tr>
      <td>Sync <code>empty_feed_refused</code> (HTTP 409)</td>
      <td>Hours JSON returned <code>sessions: []</code> while SQLite still has rows</td>
      <td>Expected protection. Team roster may still have updated. Only override hours with <code>--force-empty</code> / <code>REPS_SHIFT_ALLOW_EMPTY_INGEST=1</code> if you truly mean it.</td>
    </tr>
    <tr>
      <td>Sync <code>missing_cookie_jar</code></td>
      <td>Host cannot read JoinShift cookie file (team/invite lane)</td>
      <td>Place Netscape jar where <code>REPS_SHIFT_COOKIE_JAR</code> points. Hours still poll if the MicroPS jar is readable.</td>
    </tr>
    <tr>
      <td>Sync <code>missing_microps_cookie</code></td>
      <td>Hours lane has no MicroPS session in app_meta (and no staging jar)</td>
      <td>Restage the Netscape jar with <code>tools/deposit-microps-cookies.php</code> into the dashboard database. Do not put the cookie in site env.</td>
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
