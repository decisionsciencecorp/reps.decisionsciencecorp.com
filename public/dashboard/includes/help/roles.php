<?php
declare(strict_types=1);
if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}
?>
<h2 class="h4 mb-3"><i class="bi bi-shield-lock me-2"></i>Roles &amp; access</h2>

<p>Every seat has a <strong>role</strong>. Scope is enforced in PHP for both UI and API — never trust client-only hiding.</p>

<table class="table table-sm">
  <thead><tr><th>Role</th><th>Blurb</th></tr></thead>
  <tbody>
    <?php foreach (repsDashAllRoles() as $r): ?>
      <tr>
        <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars(repsDashRoleLabel($r)); ?></span><br><code class="small"><?php echo htmlspecialchars($r); ?></code></td>
        <td><?php echo htmlspecialchars(repsDashScopeBlurb($r)); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="h5 mt-4">Scope fields</h3>
<ul>
  <li><code>shop_id</code> — ties a seat to one shop (owners, shop employees).</li>
  <li><code>operator_id</code> — ties a seat to one Shift/operator worker (individuals, matched employees).</li>
</ul>

<h3 class="h5 mt-4">Views × roles matrix</h3>
<p>When Dev Mode is on, open <a href="/dashboard/access.php">Views × roles</a> for the full grid used in planning. The matrix in that page mirrors product policy (including Help and Users dropdown).</p>

<div class="alert alert-secondary mb-0">
  <strong>Agent:</strong> Prefer API keys on the <em>agent</em> seat. Browser desk is minimal (Home stub + Help + Settings). API elevation treats agent keys as ops-equivalent for book reads.
</div>
