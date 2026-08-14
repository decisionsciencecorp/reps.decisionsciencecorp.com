<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-gear me-2"></i>Settings &amp; sync</h2>

<h3 class="h5">Skin</h3>
<p>Every seat can pick a visual skin (hey / ledger / brutalist / obsidian). Preference is stored on the user row.</p>

<h3 class="h5 mt-4">Sync (admin / ops)</h3>
<ul>
  <li>Controls whether the desk prefers live polling vs fixture fallback.</li>
  <li>Hours poll MicroPS; team roster / invite poll JoinShift.</li>
  <li>MicroPS login cookie lives in the dashboard database (same pattern as Stripe keys), not in server env.</li>
  <li>Footer shows <em>live</em> data or <em>fixture fallback</em>.</li>
  <li>Partner code (JoinShift matching identity, e.g. C6N9T7) may be stored in app meta (<code>shift.partner_code</code>). MicroPS GM code is stored separately and is not used as session partner_code.</li>
</ul>

<h3 class="h5 mt-4">Platform (admin / agent)</h3>
<p>Stubs and keys that belong on the host env, not in browser localStorage. API keys for agents are managed under Users (admin UI) or <code>create-api-key.php</code>.</p>

<p class="small text-muted mb-0">Host env names for Partner base and cookie jar are documented in the repo API README for developers — not required to operate the desk day to day.</p>
