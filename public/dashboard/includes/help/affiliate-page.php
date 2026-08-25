<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

$user = repsDashCurrentUser();
$info = is_array($user) ? repsDashAffiliatePageInfo($user) : null;
$isSales = is_array($user) && ($user['role'] ?? '') === 'sales';
?>
<h2 class="h4 mb-3"><i class="bi bi-link-45deg me-2"></i>Affiliate landing page</h2>

<p class="lead">Every active <strong>sales / affiliate</strong> seat gets a public intro page on the Reps site. Share that link when you recruit operators or shop owners — applications are credited to you automatically.</p>

<?php if ($isSales && $info !== null): ?>
<div class="alert alert-primary border-0 mb-4">
  <strong>Your seat.</strong> Copy your live links below, or find the same panel on Home, Money, and Settings.
</div>
<?php repsDashRenderAffiliatePagePanel($user); ?>
<?php endif; ?>

<h3 class="h5 mt-4">What you get</h3>
<table class="table table-sm align-middle">
  <thead>
    <tr><th>Item</th><th>What it is</th><th>When to use it</th></tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Landing page</strong></td>
      <td>Public marketing page at <code>https://reps.decisionsciencecorp.com/a/{username}/</code></td>
      <td>Default — send this in texts, email, or social bios. No code to type.</td>
    </tr>
    <tr>
      <td><strong>Direct apply link</strong></td>
      <td><code>/join.php?rep={username}</code> on the same host</td>
      <td>When you only need the application form (shorter URL).</td>
    </tr>
    <tr>
      <td><strong>Affiliate code</strong></td>
      <td>Your sales-seat <strong>username</strong></td>
      <td>Backup if someone applies from the main Reps site without your link.</td>
    </tr>
  </tbody>
</table>

<h3 class="h5 mt-4">How the page is configured</h3>
<p>There is no drag-and-drop page builder. Two fields on your seat control what prospects see:</p>
<ul>
  <li><strong>Username</strong> — becomes the URL path (<code>/a/rizzn-aff/</code>, <code>/a/seven-aff/</code>, etc.). Chosen when the seat is created. Usernames must be lowercase letters, numbers, and hyphens; reserved words like <code>dashboard</code> or <code>join</code> are not allowed.</li>
  <li><strong>Display name</strong> — shown in the page headline (“Invited by <em>Your Name</em>”). Only an admin can change this under <strong>Users → Roster &amp; seats</strong>.</li>
</ul>
<p>The page content (how Reps works, FAQ, apply button) is shared for all affiliates. Your personalization is the display name and automatic attribution on apply.</p>

<?php if (!$isSales): ?>
<h3 class="h5 mt-4">Admin / ops — publishing a new sales seat</h3>
<ol>
  <li>Create an active <strong>sales</strong> seat with a valid username (that username becomes the slug).</li>
  <li>Run <code>php tools/sync_affiliate_page_stubs.php</code> on the host (or deploy) so <code>public/a/{username}/</code> exists.</li>
  <li>Confirm the page loads at <code>https://reps.decisionsciencecorp.com/a/{username}/</code>.</li>
  <li>Tell the affiliate their links appear on Home, Money, Settings, and in this Help chapter when they sign in.</li>
</ol>
<?php endif; ?>

<h3 class="h5 mt-4">Where affiliates find their links in the dashboard</h3>
<ul>
  <li><a href="/dashboard/">Home</a> — panel at the top (after the first-run tour)</li>
  <li><a href="/dashboard/money.php">Money</a> — same panel above earnings</li>
  <li><a href="/dashboard/settings.php">Settings</a> — full panel on the right</li>
  <li><a href="/dashboard/help.php?page=affiliate-page">Help → Affiliate landing page</a> — this chapter (you are here)</li>
</ul>
<p>New sales seats also walk through landing-page steps on the <strong>Home wizard</strong> before the normal desk opens.</p>

<h3 class="h5 mt-4">Attribution — how you get credit</h3>
<ul>
  <li>Someone applies from <strong>your landing page</strong> → join form carries your code automatically.</li>
  <li>Someone uses <strong>your direct apply link</strong> → same.</li>
  <li>Someone types <strong>your affiliate code</strong> on the main join form → same.</li>
  <li>Someone applies with no code → you do <em>not</em> get attribution; ops may assign manually in Leads.</li>
</ul>

<h3 class="h5 mt-4">FAQ</h3>
<div class="faq-list">
  <details class="mb-2">
    <summary>Can I change my URL?</summary>
    <p class="small text-muted mb-0">The path is your username. Changing it requires a new sales seat (or admin rename, which breaks old links). Pick a stable slug when provisioning.</p>
  </details>
  <details class="mb-2">
    <summary>My page says “pending” or won’t load</summary>
    <p class="small text-muted mb-0">The public stub may not be published yet. Use the <strong>direct apply link</strong> until ops runs the affiliate stub sync. If the link still fails, contact admin.</p>
  </details>
  <details class="mb-2">
    <summary>Is this the same as the Partner code in Settings?</summary>
    <p class="small text-muted mb-0">No. <strong>Partner code</strong> (e.g. C6N9T7) identifies DSC’s Shift/MicroPS program for hours sync. <strong>Affiliate code</strong> is your sales username for recruiting attribution on the join funnel.</p>
  </details>
  <details class="mb-2">
    <summary>Can I edit the page copy or video?</summary>
    <p class="small text-muted mb-0">Not per affiliate today. The template is shared. Request marketing changes through DSC ops if something is wrong for everyone.</p>
  </details>
</div>

<p class="mb-0 mt-4 small text-muted">See also <a href="/dashboard/help.php?page=money">Money &amp; Stripe</a> for how affiliate earnings show in your book, and <a href="/dashboard/help.php?page=desks">Desks &amp; screens</a> for where Leads and Shops fit in your workflow.</p>
