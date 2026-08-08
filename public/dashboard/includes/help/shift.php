<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-link-45deg me-2"></i>Partner sync &amp; match</h2>

<p>Reps pulls hours and workers from the Partner program into the local book, then lets staff link workers to seats for attribution.</p>

<h3 class="h5 mt-4">Ingest policy</h3>
<p>Reps <strong>ingests all Partner data regardless of match</strong>. Matching a worker to a Reps seat is attribution for money/desk views — it does not gate whether hours land in SQLite.</p>

<h3 class="h5 mt-4">Settings → Sync</h3>
<p>Admin/Ops can trigger poll/ingest from Settings when live data is enabled. Failed upstream calls surface as sync errors — check cookie/session health if GETs start failing.</p>

<h3 class="h5 mt-4">Users → Worker match</h3>
<ul>
  <li>List unmatched / matched workers.</li>
  <li>Link or unlink a worker to a Reps user / operator id.</li>
  <li>Invite flows (when used) hit Partner for real.</li>
</ul>

<h3 class="h5 mt-4">Programmatic sync</h3>
<p>Prefer <code>POST /dashboard/api/v1/shift/sync.php</code> and read GETs under <code>/dashboard/api/v1/shift/</code>. Full route table: <a href="/dashboard/help.php?page=api-shift">Partner API</a>.</p>
