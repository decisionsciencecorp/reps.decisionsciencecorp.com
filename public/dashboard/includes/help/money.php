<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-cash-coin me-2"></i>Money &amp; Stripe</h2>

<p>Money is not one table — each role gets a different mode:</p>
<ul>
  <li><strong>Admin</strong> — DSC portfolio command / ledger totals</li>
  <li><strong>Ops</strong> — hours health and reject drag</li>
  <li><strong>Sales</strong> — affiliate share (25% of partner payout) + Connect bank + <a href="/dashboard/help.php?page=affiliate-page">landing page links</a> at the top of the screen</li>
  <li><strong>Business owner</strong> — shop keep (50% of partner payout) + Connect bank setup</li>
  <li><strong>Individual</strong> — solo capture (50% of partner payout) + Connect bank</li>
  <li><strong>Employee</strong> — no Money nav (shop keeps capture $)</li>
</ul>

<h3 class="h5 mt-4">Stripe</h3>
<ul>
  <li>Connect onboarding for payout seats (owner / individual / <strong>sales</strong>) lives on Money.</li>
  <li>Webhook endpoint: <code>POST /dashboard/api/stripe-webhook.php</code> (Stripe signature; not session auth).</li>
  <li>Sandbox vs live is controlled by host env / Square-style Stripe keys on the server — never paste secrets into the UI.</li>
</ul>

<h3 class="h5 mt-4">API</h3>
<p><code>GET /dashboard/api/money-summary.php</code> returns pulse + ledger shaped for the caller’s role (agent keys elevate to ops-equivalent reads). Details in <a href="/dashboard/help.php?page=api">HTTP API</a>.</p>
