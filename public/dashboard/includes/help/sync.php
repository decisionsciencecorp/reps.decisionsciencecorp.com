<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-gear me-2"></i>Settings &amp; sync</h2>

<h3 class="h5">Theme</h3>
<p>Every seat can pick a look (hey / ledger / brutalist / obsidian). Preference is stored on your account.</p>

<h3 class="h5 mt-4">Hours sync (admin / ops)</h3>
<ul>
  <li>When live hours are on, the desk shows Partner sync data — not demo numbers.</li>
  <li>Hours come from MicroPS; team roster / invite from JoinShift.</li>
  <li>Login cookies for those services live in the dashboard database (same pattern as Stripe keys).</li>
  <li>The footer notes whether hours are from Partner sync or demo mode.</li>
  <li>Partner code (JoinShift matching identity) may be stored in app meta. MicroPS GM code is stored separately.</li>
</ul>

<h3 class="h5 mt-4">Integrations (admin / agent)</h3>
<p>API keys for agents are managed under Users (admin) or the create-api-key endpoint. Prefer Settings → Integrations for day-to-day links.</p>

<p class="small text-muted mb-0">Host env names for developers are in the repo API README — not required to operate the desk day to day.</p>
