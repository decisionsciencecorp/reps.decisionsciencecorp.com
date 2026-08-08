<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-layout-text-sidebar-reverse me-2"></i>Desks &amp; screens</h2>
<p>Each nav item is a desk. Labels change slightly by role (e.g. owners see <em>My shop</em> / <em>Team</em> / <em>My pay</em>).</p>

<table class="table table-sm align-middle">
  <thead>
    <tr><th>Desk</th><th>Purpose</th><th>Typical users</th></tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Home</strong></td>
      <td>Pulse cards, attention queues, signed-in strip. Learners get an onboarding wizard first.</td>
      <td>Everyone</td>
    </tr>
    <tr>
      <td><strong>Shops</strong></td>
      <td>Shop directory / pipeline. Sales see assigned + unassigned; owners see their shop card.</td>
      <td>Admin, Ops, Sales, Owner</td>
    </tr>
    <tr>
      <td><strong>Leads</strong></td>
      <td>Join-funnel CRM: applications, activity, affiliate/path filters.</td>
      <td>Admin, Ops, Sales</td>
    </tr>
    <tr>
      <td><strong>Operators / Team</strong></td>
      <td>Standalone roster. Sales use Money for producers instead of this nav.</td>
      <td>Admin, Ops, Owner</td>
    </tr>
    <tr>
      <td><strong>Sessions</strong></td>
      <td>Capture / hours rows. Not on the sales desk.</td>
      <td>Admin, Ops, Owner, Employee, Individual</td>
    </tr>
    <tr>
      <td><strong>Money / My pay</strong></td>
      <td>Role-specific economics (portfolio, reject drag, affiliate book, Connect bank).</td>
      <td>All except employee &amp; agent</td>
    </tr>
    <tr>
      <td><strong>Education</strong></td>
      <td>Partner FAQ, reject catalog, field coaching for learner seats.</td>
      <td>Sales, Owner, Employee, Individual</td>
    </tr>
    <tr>
      <td><strong>Users ▾</strong></td>
      <td>Dropdown: roster (admin) and Worker match (admin + ops).</td>
      <td>Admin, Ops</td>
    </tr>
    <tr>
      <td><strong>Help</strong></td>
      <td>This documentation — chapters gated by role.</td>
      <td>Everyone signed in</td>
    </tr>
    <tr>
      <td><strong>Settings</strong></td>
      <td>Skin for all; sync / platform panels for staff &amp; agent.</td>
      <td>Everyone</td>
    </tr>
  </tbody>
</table>

<p class="mb-0">Detail pages (<code>shop.php</code>, <code>operator.php</code>, <code>session.php</code>, <code>lead.php</code>, <code>day.php</code>) inherit the same scope rules as their parent desk — you cannot open another shop’s private row by guessing an ID.</p>
