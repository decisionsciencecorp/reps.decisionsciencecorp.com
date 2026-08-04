<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$pulse = repsDashPulseForUser($user);
$shops = repsDashShopsForUser($user);

repsDashRenderHeader('Home', 'home');
repsDashRenderPageHeader(
    'Home',
    'Pulse for your seat · mock Shift sync · Partner ' . $pulse['partner_code']
);
?>

<div class="alert alert-warning border-0 mb-3">
  <strong>Slice A — mock data.</strong> Numbers and sessions are fake for layout audit.
  Real Shift polling is Slice C (PRD Doc #990). Last sync shown: <?php echo htmlspecialchars($pulse['last_sync']); ?>.
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours (sample set)</div>
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
      <div class="text-muted small">Shops visible</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['shops_visible']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Zero-upload shops</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $pulse['shops_zero_upload']; ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="surface p-3">
      <h2 class="h5 mb-3">Shops needing attention</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Shop</th><th>Status</th><th>7d hours</th><th>Reject</th></tr></thead>
          <tbody>
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
  <div class="col-lg-5">
    <div class="surface p-3 mb-3">
      <h2 class="h5 mb-2">Apply leads</h2>
      <p class="mb-2"><?php echo (int) $pulse['apply_leads_open']; ?> open inbound applications (lorem).</p>
      <a class="btn btn-sm btn-outline-primary" href="https://decisionsciencecorp.com/" target="_blank" rel="noopener">DSC Messages · Reps channel</a>
    </div>
    <div class="surface p-3">
      <h2 class="h5 mb-2">Signed in as</h2>
      <p class="mb-1"><strong><?php echo htmlspecialchars($user['display_name']); ?></strong></p>
      <p class="mb-0 text-muted small">Role <code><?php echo htmlspecialchars($user['role']); ?></code> —
        <?php echo repsDashIsAdminOrOps($user) ? 'sees all shops' : 'sees assigned shops + unassigned pool'; ?>.</p>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
