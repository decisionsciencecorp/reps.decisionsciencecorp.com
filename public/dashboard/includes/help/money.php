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
  <li>Connect onboarding for payout seats (owner / individual / <strong>sales</strong>) lives on Money — Stripe hosts the form (name, DOB, address, SSN last 4, bank). We never collect those in Reps.</li>
  <li>Finishing the form is not enough: payouts open when Stripe reports <code>payouts_enabled</code> (return page refresh and/or <code>account.updated</code> webhook). If Stripe asks for more later, use <strong>Continue payout setup</strong>.</li>
  <li>Affiliates, shop owners, and solos all use the same Express KYC path; only the Reps copy differs. Linked worker+affiliate seats share <strong>one</strong> Connect account.</li>
  <li>Webhook endpoint: <code>POST /dashboard/api/stripe-webhook.php</code> (Stripe signature; not session auth). Never enable <code>REPS_STRIPE_WEBHOOK_INSECURE</code> on prod.</li>
  <li>Sandbox vs live is controlled by keys in the dashboard database — never paste secrets into the UI.</li>
  <li>Detail: <code>docs/STRIPE-EXPRESS-KYC.md</code> in the repo.</li>
</ul>

<p class="small text-muted">Locked payout rates (accrual): capture $10/hr · standard affiliate $5/hr · Chuck-tree affiliate $3/hr (admin flag; $2/hr DSC holdback is internal). Settlement still requires Stripe cash ≥ hours × $20.</p>

<h3 class="h5 mt-4">API</h3>
<p><code>GET /dashboard/api/money-summary.php</code> returns pulse + ledger shaped for the caller’s role (agent keys elevate to ops-equivalent reads). Details in <a href="/dashboard/help.php?page=api">HTTP API</a>.</p>
