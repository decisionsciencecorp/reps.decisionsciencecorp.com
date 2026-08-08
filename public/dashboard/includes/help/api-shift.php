<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
$base = 'https://reps.decisionsciencecorp.com/dashboard/api/v1/shift';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="h4 mb-0"><i class="bi bi-broadcast me-2"></i>Shift Partner API (proxy)</h2>
  <span class="badge text-bg-danger">CARDINAL · live = prod</span>
</div>

<div class="alert alert-danger">
  <strong>CARDINAL — Shift Partner is production.</strong><br>
  <code>app.joinshift.us</code> is <strong>PROD</strong>. There is <strong>no</strong> Shift sandbox.
  <table class="table table-sm table-borderless mb-0 mt-2 bg-transparent">
    <tr>
      <td class="ps-0"><strong>Allowed against live</strong></td>
      <td>Read-only <code>GET</code>s (hours-feed, workers, team members, mapped GETs) for sync/verify; non-destructive cookie health.</td>
    </tr>
    <tr>
      <td class="ps-0"><strong>Forbidden against live (automation)</strong></td>
      <td>Invites, deletes, bank/profile/split/SMS/address changes, logout, support spam, admin/impersonate, OTP “for a test”.</td>
    </tr>
  </table>
  <p class="mb-0 mt-2"><strong>Writes</strong> are developed and proven against the <strong>fake Shift stub</strong>
  (<code>tools/fake-shift-partner/</code>, <code>REPS_SHIFT_API_BASE=fake://shift</code> or local <code>php -S</code>).
  PHPUnit sets <code>REPS_SHIFT_FORBID_LIVE_WRITES=1</code> and refuses live write bases.
  Human Admin/Ops may intentionally invite against live when the base points at joinshift — that is real ops, not CI proof.</p>
</div>

<p><strong>Caller ACL:</strong> admin, ops, or agent (session or API key). Others get <code>403 forbidden</code>.</p>
<p><strong>Base path:</strong> <code><?php echo htmlspecialchars($base); ?>/</code></p>
<p>Configure upstream with <code>REPS_SHIFT_API_BASE</code> (default <code>https://app.joinshift.us</code>) and <code>REPS_SHIFT_COOKIE_JAR</code> for live cookies.</p>

<div class="accordion" id="rdApiShift">

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC0">Read GETs (live-safe for automation)</button>
    </h3>
    <div id="rdShC0" class="accordion-collapse collapse show" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <table class="table table-sm">
          <thead><tr><th>Endpoint</th><th>Notes</th></tr></thead>
          <tbody>
            <tr>
              <td><code>GET hours-feed.php</code></td>
              <td>Upstream hours. Optional <code>?ingest=1</code> to refresh local SQLite after fetch.</td>
            </tr>
            <tr>
              <td><code>GET workers.php</code></td>
              <td>Worker roster from Partner.</td>
            </tr>
            <tr>
              <td><code>GET team-members.php</code></td>
              <td>Team roster (read). Same path supports write methods below.</td>
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
        <p><code>POST sync.php</code> — poll Partner and ingest into the Reps book. Prefer this for scheduled jobs over ad-hoc UI clicks when automating.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC2">Team members (invite / delete — write)</button>
    </h3>
    <div id="rdShC2" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p><code>POST team-members.php</code> — JSON <code>{"name":"…","phone":"…"}</code>. Invites a member. Live = real invite. Returns <code>201</code> + optional <code>ingest</code> snapshot.</p>
        <p><code>DELETE team-members.php?id={id}</code> — remove team member. Live = real delete.</p>
        <p>On forbid-live paths these throw <code>cardinal_blocked</code> (HTTP 403).</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC3">Account writes</button>
    </h3>
    <div id="rdShC3" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p>All <code>POST</code> under <code>account/</code> — JSON body forwarded / mapped to Partner. Live writes mutate production accounts.</p>
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
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC4">Auth (OTP / logout)</button>
    </h3>
    <div id="rdShC4" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <ul>
          <li><code>POST auth/request-code.php</code> — body <code>phone</code></li>
          <li><code>POST auth/verify-code.php</code> — body <code>phone</code> + <code>code</code></li>
          <li><code>POST auth/logout.php</code></li>
        </ul>
        <p class="mb-0">Do not burn OTP flows against live for “tests.” Use the fake stub.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC5">Support</button>
    </h3>
    <div id="rdShC5" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p><code>POST support-chat.php</code> — Partner support channel. Not for load testing against live.</p>
      </div>
    </div>
  </div>

  <div class="accordion-item">
    <h3 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rdShC6">Derived (local SQLite — no Partner write)</button>
    </h3>
    <div id="rdShC6" class="accordion-collapse collapse" data-bs-parent="#rdApiShift">
      <div class="accordion-body">
        <p>Computed from ingested book data — safe for automation against any base:</p>
        <ul class="mb-0">
          <li><code>GET derived/worker.php?id=</code> — operator stats</li>
          <li><code>GET derived/day.php?date=YYYY-MM-DD</code></li>
          <li><code>GET derived/issues.php</code> — issue list / count</li>
        </ul>
      </div>
    </div>
  </div>

</div>

<h3 class="h5 mt-4">Fake stub</h3>
<p>Repo path <code>tools/fake-shift-partner/</code> (see its README). Point <code>REPS_SHIFT_API_BASE</code> at the stub for invite/delete/account write proof. UI Shift match against live remains an intentional human ops action.</p>

<h3 class="h5 mt-4">Parked</h3>
<p class="mb-0">Consumer <code>api.micro-agi.com</code> integration is parked (Tasks / MicroAGI). Not part of this proxy surface yet.</p>
