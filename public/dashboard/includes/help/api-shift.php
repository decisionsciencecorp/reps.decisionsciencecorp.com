<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
$base = 'https://reps.decisionsciencecorp.com/dashboard/api/v1/shift';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="h4 mb-0"><i class="bi bi-broadcast me-2"></i>Partner API (proxy)</h2>
  <span class="badge text-bg-primary">admin · ops · agent</span>
</div>

<p>These routes proxy the Partner program into Reps. <strong>Hours</strong> come from MicroPS; <strong>team invite / matching</strong> stay on JoinShift. Callers must be <strong>admin</strong>, <strong>ops</strong>, or <strong>agent</strong> (session or API key). Others get <code>403 forbidden</code>.</p>
<p><strong>Base path:</strong> <code><?php echo htmlspecialchars($base); ?>/</code></p>

<div class="accordion" id="rdApiShift">

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC0">Read GETs</button>
    </h3>
    <div id="rdShC0" class="accordion-collapse collapse show" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <table class="table table-sm">
          <thead><tr><th>Endpoint</th><th>Notes</th></tr></thead>
          <tbody>
            <tr>
              <td><code>GET hours-feed.php</code></td>
              <td>Mapped MicroPS hours (same JSON shape as the old hours-feed). Optional <code>?ingest=1</code> refreshes local session data and JoinShift team roster.</td>
            </tr>
            <tr>
              <td><code>GET workers.php</code></td>
              <td>Worker roster from Partner.</td>
            </tr>
            <tr>
              <td><code>GET team-members.php</code></td>
              <td>Team roster (read). Same path also supports write methods below.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC1">Sync</button>
    </h3>
    <div id="rdShC1" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p class="mb-0"><code>POST sync.php</code> — poll MicroPS hours + JoinShift team, then ingest. Prefer this for scheduled jobs. Empty hours do not skip team updates.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC2">Team members</button>
    </h3>
    <div id="rdShC2" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p><code>POST team-members.php</code> — JSON <code>{"name":"…","phone":"…"}</code>. Invites a member. Returns <code>201</code> plus optional <code>ingest</code> snapshot.</p>
        <p class="mb-0"><code>DELETE team-members.php?id={id}</code> — remove a team member.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC3">Account</button>
    </h3>
    <div id="rdShC3" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p>All <code>POST</code> under <code>account/</code> — JSON body mapped to Partner account settings:</p>
        <ul class="mb-0">
          <li><code>account/payout-split.php</code></li>
          <li><code>account/sms-schedule.php</code></li>
          <li><code>account/bank-info.php</code></li>
          <li><code>account/profile.php</code></li>
          <li><code>account/legal-address.php</code></li>
          <li><code>account/shipping-address.php</code></li>
          <li><code>account/active-view.php</code></li>
          <li><code>account/reminders.php</code></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC4">Auth</button>
    </h3>
    <div id="rdShC4" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <ul class="mb-0">
          <li><code>POST auth/request-code.php</code> — body <code>phone</code></li>
          <li><code>POST auth/verify-code.php</code> — body <code>phone</code> + <code>code</code></li>
          <li><code>POST auth/logout.php</code></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC5">Support</button>
    </h3>
    <div id="rdShC5" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p class="mb-0"><code>POST support-chat.php</code> — Partner support channel.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC6">Derived (local data)</button>
    </h3>
    <div id="rdShC6" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p>Computed from ingested Reps data — no Partner mutation:</p>
        <ul class="mb-0">
          <li><code>GET derived/worker.php?id=</code> — operator stats</li>
          <li><code>GET derived/day.php?date=YYYY-MM-DD</code></li>
          <li><code>GET derived/issues.php</code> — issue list / count</li>
        </ul>
      </div>
    </div>
  </div>

</div>

<p class="small text-muted mt-4 mb-0">Path segment <code>/v1/shift/</code> is the API namespace; UI copy refers to this surface as the Partner API.</p>
