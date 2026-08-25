<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="h4 mb-0"><i class="bi bi-house me-2"></i>Overview</h2>
  <span class="badge text-bg-primary">Reps Dashboard</span>
</div>

<p class="lead">Reps is Decision Science Corp’s operator desk for the Partner capture program: shops and people in the book, hours, money views, and (for staff) sync and worker matching.</p>

<div class="alert alert-info">
  <strong>Two layers of truth:</strong>
  <ul class="mb-0 mt-2">
    <li><strong>Partner upstream</strong> — source of hours, workers, and team invites.</li>
    <li><strong>Reps book</strong> — seats, shop/operator scope, match links, money pulse, API keys. Ingest copies upstream rows into the book; match is attribution, not a gate on ingest.</li>
  </ul>
</div>

<h3 class="h5 mt-4">Who this Help is for</h3>
<p>Chapters in the left map are filtered by your seat. Admin and Ops see the full manual including HTTP API and Partner proxy. Learner seats (sales, owners, operators) get desk guides plus a short note on session-scoped API. Agent seats get API-first chapters.</p>

<h3 class="h5 mt-4">Quick links</h3>
<ul>
  <li><a href="/dashboard/">Home</a> — role pulse / wizard for learners</li>
  <?php if (repsDashCanSeeHelpPage('affiliate-page')): ?>
    <li><a href="/dashboard/help.php?page=affiliate-page">Affiliate landing page</a> — your public recruit link, apply URL, and code</li>
  <?php endif; ?>
  <li><a href="/dashboard/settings.php">Settings</a> — theme; sync controls for admin/ops; affiliate links for sales</li>
  <li><a href="/dashboard/help.php?page=roles">Roles &amp; access</a> — what each badge means</li>
  <?php if (repsDashCanSeeHelpPage('api')): ?>
    <li><a href="/dashboard/help.php?page=api">HTTP API (book)</a> — programmatic access</li>
  <?php endif; ?>
  <?php if (repsDashCanSeeHelpPage('api-shift')): ?>
    <li><a href="/dashboard/help.php?page=api-shift">Partner API</a> — proxy routes</li>
  <?php endif; ?>
</ul>

<h3 class="h5 mt-4">Partner code</h3>
<p>Staff and sales seats may see the partner code in the footer (default <code>C6N9T7</code>). It identifies DSC’s Partner program; it is not an API secret.</p>
