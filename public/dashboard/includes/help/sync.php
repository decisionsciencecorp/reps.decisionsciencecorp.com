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
  <li>Controls whether the desk prefers live Shift polling vs fixture fallback.</li>
  <li>Footer shows <em>live Shift data</em> or <em>fixture fallback</em>.</li>
  <li>Partner code may be stored in app meta (<code>shift.partner_code</code>).</li>
</ul>

<h3 class="h5 mt-4">Platform (admin / agent)</h3>
<p>Stubs and keys that belong on the host env, not in browser localStorage. API keys for agents are managed under Users (admin UI) or <code>create-api-key.php</code>.</p>

<h3 class="h5 mt-4">Host configuration (ops reference)</h3>
<p>Common env names (values live only on the server / pass files):</p>
<ul>
  <li><code>REPS_SHIFT_API_BASE</code> — Partner base (default joinshift) or <code>fake://shift</code> for tests</li>
  <li><code>REPS_SHIFT_COOKIE_JAR</code> — cookie file for live GETs</li>
  <li><code>REPS_SHIFT_FORBID_LIVE_WRITES</code> — set in PHPUnit / CI</li>
  <li><code>REPS_DASH_DEV_MODE</code> — role switcher bar</li>
</ul>
