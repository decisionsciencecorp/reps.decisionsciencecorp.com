<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

$date = trim((string) ($_GET['date'] ?? ''));
$operatorId = isset($_GET['operator_id']) ? (int) $_GET['operator_id'] : 0;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Location: /dashboard/');
    exit;
}

if ($operatorId > 0) {
    if (!repsDashCanViewOperator($user, $operatorId)) {
        http_response_code(403);
        repsDashRenderHeader('Day', 'sessions');
        echo '<div class="alert alert-danger">Not in your scope.</div>';
        repsDashRenderFooter();
        exit;
    }
    $op = repsDashFindOperator($operatorId);
    $sessions = array_values(array_filter(
        repsDashSessionsForOperator($operatorId),
        static fn(array $s): bool => (($s['day'] ?? '') === $date)
    ));
    $title = ($op['name'] ?? 'Worker') . ' · ' . $date;
    $back = repsDashOperatorHref($operatorId);
    $backLabel = 'Back to worker';
    $active = 'operators';
} else {
    // Shop/book day — sessions in user scope for that date
    $sessions = array_values(array_filter(
        repsDashSessionsForUser($user),
        static fn(array $s): bool => (($s['day'] ?? '') === $date)
    ));
    $title = $date;
    $back = '/dashboard/';
    $backLabel = 'Back home';
    $active = 'sessions';
    $op = null;
}

$rate = 20.0;
$accepted = 0.0;
$recorded = 0.0;
$rejectedH = 0.0;
foreach ($sessions as $s) {
    $recorded += (float) $s['duration_hours'];
    $accepted += (float) $s['accepted_hours'];
    if ($s['status'] === 'rejected') {
        $rejectedH += (float) $s['duration_hours'];
    }
}
$acceptance = ($accepted + $rejectedH) > 0 ? round(($accepted / ($accepted + $rejectedH)) * 100) : null;

repsDashRenderHeader($title, $active);
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="<?php echo htmlspecialchars($back); ?>">
    <i class="bi bi-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
  </a>
</p>
<?php
repsDashRenderPageHeader($title, 'A single day’s activity (mock · Shift day drill-down)');
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Earnings</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($accepted * $rate, 0); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Sessions</div>
      <div class="fs-3 fw-semibold"><?php echo count($sessions); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Acceptance</div>
      <div class="fs-3 fw-semibold"><?php echo $acceptance === null ? '—' : ($acceptance . '%'); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Recorded / rejected</div>
      <div class="fs-5 fw-semibold"><?php echo htmlspecialchars((string) round($recorded, 1)); ?> h</div>
      <div class="small text-muted"><?php echo htmlspecialchars((string) round($rejectedH, 1)); ?> h rejected</div>
    </div>
  </div>
</div>

<div class="surface p-0">
  <div class="p-3 border-bottom"><h2 class="h5 mb-0">Sessions</h2></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>When</th>
          <?php if ($operatorId <= 0): ?><th>Operator</th><?php endif; ?>
          <th>Duration</th>
          <th>Accepted</th>
          <th>Status</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($sessions === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No sessions this day in mock scope.</td></tr>
      <?php endif; ?>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="small"><?php echo htmlspecialchars(substr((string) $s['completed_at'], 11)); ?></td>
          <?php if ($operatorId <= 0): ?>
            <td>
              <?php if (!empty($s['operator_id'])): ?>
                <a href="<?php echo htmlspecialchars(repsDashOperatorHref((int) $s['operator_id'])); ?>">
                  <?php echo htmlspecialchars($s['operator']); ?>
                </a>
              <?php else: ?>
                <?php echo htmlspecialchars($s['operator']); ?>
              <?php endif; ?>
            </td>
          <?php endif; ?>
          <td><?php echo htmlspecialchars((string) $s['duration_hours']); ?> h</td>
          <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?> h</td>
          <td><?php repsDashStatusPill($s['status']); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? $s['rejection_reason'] : '—'); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
