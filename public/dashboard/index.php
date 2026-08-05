<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$role = (string) $user['role'];
$pulse = repsDashPulseForUser($user);
$shops = repsDashShopsForUser($user);
$sessions = repsDashSessionsForUser($user);
$blocks = repsDashHomeBlocksForRole($role);

$subtitle = match ($role) {
    'employee', 'individual' => 'Your personal pulse · mock data',
    'business_owner' => 'Your shop pulse · mock data',
    'agent' => 'Service principal · not a human desk',
    default => 'Pulse for your seat · mock Shift sync'
        . (repsDashCanSeePartnerCode($user) ? ' · Partner ' . $pulse['partner_code'] : ''),
};

repsDashRenderHeader('Home', 'home');
repsDashRenderPageHeader('Home', $subtitle);
?>

<div class="alert alert-warning border-0 mb-3">
  <strong>Slice A — mock data.</strong> Numbers are fake for layout and scoping audit.
  Real Shift polling is Slice C (PRD Doc #990).
  <?php if (repsDashCanSeePartnerCode($user)): ?>
    Last sync shown: <?php echo htmlspecialchars($pulse['last_sync']); ?>.
  <?php endif; ?>
</div>

<?php if (in_array('shop_metrics', $blocks, true)): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (sample)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $pulse['accepted_hours_sample']); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Rejected sessions</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['rejected_sessions']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small"><?php echo $role === 'business_owner' ? 'Your shop' : 'Shops visible'; ?></div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['shops_visible']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small"><?php echo $role === 'business_owner' ? 'Active team' : 'Zero-upload shops'; ?></div>
      <div class="fs-3 fw-semibold"><?php echo (int) ($role === 'business_owner' ? $pulse['operators_active'] : $pulse['shops_zero_upload']); ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (in_array('personal_metrics', $blocks, true)): ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Your accepted hours (sample)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $pulse['accepted_hours_sample']); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Rejected</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['rejected_sessions']; ?></div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Pending review</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['pending_sessions']; ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (in_array('agent_stub', $blocks, true)): ?>
<div class="surface p-4 mb-4">
  <h2 class="h5 mb-2">Agent / API principal</h2>
  <p class="mb-2">This seat is <strong>not</strong> a human ops login. Sync workers and SMCP tools authenticate with an API key against <code>/dashboard/api/</code> (Slice D–E).</p>
  <p class="mb-0 text-muted small">No shops, operators, sessions, or money screens. Use Dev Mode only to confirm agents stay off the human desk.</p>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php if (in_array('shops_attention', $blocks, true)): ?>
  <div class="col-lg-7">
    <div class="surface p-3">
      <h2 class="h5 mb-3"><?php echo $role === 'business_owner' ? 'Your shop' : 'Shops needing attention'; ?></h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Shop</th><th>Status</th><th>7d hours</th><th>Reject</th></tr></thead>
          <tbody>
          <?php if ($shops === []): ?>
            <tr><td colspan="4" class="text-muted">No shops in scope for this seat.</td></tr>
          <?php endif; ?>
          <?php foreach ($shops as $shop): ?>
            <tr>
              <td><?php echo htmlspecialchars($shop['name']); ?></td>
              <td><?php repsDashStatusPill($shop['status']); ?></td>
              <td><?php echo htmlspecialchars((string) $shop['accepted_hours_7d']); ?></td>
              <td><?php echo htmlspecialchars((string) round($shop['reject_rate'] * 100)) . '%'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array('recent_sessions', $blocks, true)): ?>
  <div class="col-lg-7">
    <div class="surface p-3">
      <h2 class="h5 mb-3">Your recent sessions</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>When</th><th>Status</th><th>Accepted</th><th>Reason</th></tr></thead>
          <tbody>
          <?php
          $recent = array_slice($sessions, 0, 8);
          if ($recent === []):
          ?>
            <tr><td colspan="4" class="text-muted">No sessions yet (mock).</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $s): ?>
            <tr>
              <td class="small"><?php echo htmlspecialchars($s['completed_at']); ?></td>
              <td><?php repsDashStatusPill($s['status']); ?></td>
              <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?></td>
              <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? $s['rejection_reason'] : '—'); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a class="btn btn-sm btn-outline-primary mt-3" href="/dashboard/sessions.php">All my sessions</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-lg-5">
    <?php if (in_array('apply_leads', $blocks, true)): ?>
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Apply leads</h2>
      <p class="mb-2"><?php echo (int) $pulse['apply_leads_open']; ?> open inbound applications (lorem).</p>
      <a class="btn btn-sm btn-outline-primary" href="https://decisionsciencecorp.com/" target="_blank" rel="noopener">DSC Messages · Reps channel</a>
    </div>
    <?php endif; ?>

    <?php if (in_array('team_pulse', $blocks, true)): ?>
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Team</h2>
      <p class="mb-2"><?php echo (int) $pulse['operators_active']; ?> active operators on your shop (mock).</p>
      <a class="btn btn-sm btn-outline-primary" href="/dashboard/operators.php">Open team roster</a>
    </div>
    <?php endif; ?>

    <?php if (in_array('signed_in', $blocks, true)): ?>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Signed in as</h2>
      <p class="mb-1"><strong><?php echo htmlspecialchars($user['display_name']); ?></strong></p>
      <p class="mb-0 text-muted small">
        Role <strong><?php echo htmlspecialchars(repsDashRoleLabel($role)); ?></strong>
        (<code><?php echo htmlspecialchars($role); ?></code>) —
        <?php echo htmlspecialchars(repsDashScopeBlurb($role)); ?>
        Use Dev Mode to switch seats; see <a href="/dashboard/access.php">Views × roles</a>.
      </p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
