<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$id = (int) ($_GET['id'] ?? 0);
if (!repsDashCanOpenOperatorDesk($user)) {
    header('Location: /dashboard/');
    exit;
}

$op = $id > 0 ? repsDashFindOperator($id) : null;

if ($op === null || !repsDashCanViewOperator($user, $id)) {
    http_response_code(404);
    repsDashRenderHeader('Operator', 'operators');
    echo '<div class="alert alert-danger">Operator not found or not in your scope.</div>';
    echo '<a class="btn btn-outline-primary" href="/dashboard/">Back home</a>';
    repsDashRenderFooter();
    exit;
}

$stats = repsDashOperatorDetailStats($id);
$rate = repsDashMoneyHourlyRate();
$role = (string) $user['role'];
$backHref = match ($role) {
    'business_owner' => '/dashboard/operators.php',
    'sales' => '/dashboard/money.php',
    'employee', 'individual' => '/dashboard/',
    default => '/dashboard/operators.php',
};
$backLabel = match ($role) {
    'business_owner' => 'Back to team',
    'sales' => 'Back to money',
    default => 'Back to operators',
};
$activeNav = in_array($role, ['sales'], true) ? 'money' : (in_array($role, ['employee', 'individual'], true) ? 'sessions' : 'operators');

repsDashRenderHeader($op['name'], $activeNav);
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="<?php echo htmlspecialchars($backHref); ?>">
    <i class="bi bi-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
  </a>
</p>
<?php
repsDashRenderPageHeader(
    $op['name'],
    ($op['shop'] !== '— (individual)' ? $op['shop'] : 'Independent operator') . ' · hours and day detail'
);
?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?php repsDashStatusPill($op['status']); ?>
  <?php if (!empty($op['matched'])): ?>
    <span class="badge text-bg-success">Matched</span>
  <?php else: ?>
    <span class="badge text-bg-secondary">Invited</span>
  <?php endif; ?>
  <span class="small text-muted"><?php echo htmlspecialchars($op['phone']); ?></span>
  <?php if ($op['shop'] !== '— (individual)'): ?>
    <span class="small text-muted">· <?php echo htmlspecialchars($op['shop']); ?></span>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted hours</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $stats['accepted_hours']); ?> h</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Earnings (est.)</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format((float) $stats['earnings'], 0); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Sessions</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $stats['completed']; ?></div>
      <div class="small text-muted"><?php echo (int) $stats['rejected']; ?> rejected · <?php echo (int) $stats['pending']; ?> pending</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Acceptance rate</div>
      <div class="fs-3 fw-semibold"><?php echo $stats['acceptance_rate'] === null ? '—' : ((int) $stats['acceptance_rate'] . '%'); ?></div>
      <div class="small text-muted"><?php echo htmlspecialchars((string) $stats['recorded_hours']); ?> h recorded · <?php echo htmlspecialchars((string) $stats['rejected_hours']); ?> h rejected</div>
    </div>
  </div>
</div>

<?php if ($stats['reasons'] !== []): ?>
<div class="surface p-3 mb-3">
  <h2 class="h5 mb-2">Why footage got rejected</h2>
  <p class="small text-muted mb-3">
    <?php echo (int) $stats['rejected']; ?> rejected sessions ·
    <strong>−$<?php echo number_format((float) $stats['lost_payouts'], 0); ?></strong> estimated lost payouts
    (<?php echo (int) round(repsDashMoneyShareCapture() * 100); ?>% capture share of partner payout)
  </p>
  <div class="row g-2">
    <?php foreach ($stats['reasons'] as $r): ?>
      <div class="col-md-4">
        <div class="border rounded p-2 h-100">
          <div class="fw-semibold small">
            <?php
            $eduId = repsDashEducationIdForRejectReason((string) $r['reason']);
            $label = str_replace('_', ' ', (string) $r['reason']);
            if ($eduId !== null && repsDashUsesLearnerChrome($role)):
            ?>
              <a href="<?php echo htmlspecialchars(repsDashEducationArticleHref($eduId)); ?>">
                <?php echo htmlspecialchars($label); ?>
              </a>
            <?php else: ?>
              <?php echo htmlspecialchars($label); ?>
            <?php endif; ?>
          </div>
          <div class="small text-muted"><?php echo (int) $r['sessions']; ?> sessions · <?php echo htmlspecialchars((string) round($r['hours'], 1)); ?> h · −$<?php echo number_format($r['lost'], 0); ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="surface p-0 mb-3">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
    <h2 class="h5 mb-0">By day</h2>
    <span class="small text-muted">Click a day for session list</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Day</th>
          <th>Total hours</th>
          <th>Accepted</th>
          <th>Acceptance</th>
          <th>Sessions</th>
          <th>Earnings</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($stats['by_day'] === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No sessions yet for this worker.</td></tr>
      <?php endif; ?>
      <?php foreach ($stats['by_day'] as $d): ?>
        <tr>
          <td>
            <a href="<?php echo htmlspecialchars(repsDashDayHref($d['day'], $id)); ?>">
              <?php echo htmlspecialchars($d['day']); ?>
            </a>
          </td>
          <td><?php echo htmlspecialchars((string) round($d['total_hours'], 1)); ?> h</td>
          <td><?php echo htmlspecialchars((string) round($d['accepted'], 1)); ?> h</td>
          <td><?php echo (int) $d['acceptance']; ?>%</td>
          <td><?php echo (int) $d['sessions']; ?></td>
          <td>$<?php echo number_format($d['earnings'], 0); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (in_array($role, ['admin', 'ops', 'business_owner', 'employee', 'individual'], true)): ?>
  <a class="btn btn-outline-primary btn-sm" href="/dashboard/sessions.php?operator_id=<?php echo (int) $id; ?>">
    All sessions for this worker
  </a>
<?php endif; ?>

<?php repsDashRenderFooter(); ?>
