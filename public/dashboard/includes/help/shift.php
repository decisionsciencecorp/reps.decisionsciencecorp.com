<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-link-45deg me-2"></i>Shift sync &amp; match</h2>

<div class="alert alert-warning">
  <strong>CARDINAL:</strong> <code>app.joinshift.us</code> is production. There is no Shift sandbox.
  Automated tests and CI write against the <strong>fake Shift stub</strong> only. Live GETs for sync are fine; live writes are real ops actions.
</div>

<h3 class="h5 mt-4">Ingest policy</h3>
<p>Reps <strong>ingests all Shift data regardless of match</strong>. Matching a worker to a Reps seat is attribution for money/desk views — it does not gate whether hours land in SQLite.</p>

<h3 class="h5 mt-4">Settings → Sync</h3>
<p>Admin/Ops can trigger poll/ingest from Settings when live data is enabled (<code>REPS_*</code> env on the host). Failed upstream calls surface as sync errors — check cookie jar health if GETs start failing.</p>

<h3 class="h5 mt-4">Users → Shift match</h3>
<ul>
  <li>List unmatched / matched Shift workers.</li>
  <li>Link or unlink a worker to a Reps user / operator id.</li>
  <li>Invite flows (when wired) hit Partner — treat as production.</li>
</ul>

<h3 class="h5 mt-4">Programmatic sync</h3>
<p>Prefer <code>POST /dashboard/api/v1/shift/sync.php</code> and read GETs under <code>/dashboard/api/v1/shift/</code>. Full route table: <a href="/dashboard/help.php?page=api-shift">Shift Partner API</a>.</p>
