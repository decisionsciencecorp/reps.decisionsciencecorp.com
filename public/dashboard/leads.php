<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('leads', $user);

if (!repsDashCanManageApplyLeads($user)) {
    header('Location: /dashboard/');
    exit;
}

repsDashMarkLeadsSeen($user);

$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['open', 'claimed', 'closed'], true)) {
    $statusFilter = '';
}
$kindFilter = trim((string) ($_GET['kind'] ?? ''));
if ($kindFilter !== '' && !in_array($kindFilter, ['operator', 'shop', 'affiliate'], true)) {
    $kindFilter = '';
}

$role = (string) $user['role'];
$myDefault = $role === 'sales';
$scope = trim((string) ($_GET['scope'] ?? ($myDefault ? 'mine' : 'all')));
if (!in_array($scope, ['mine', 'all'], true)) {
    $scope = $myDefault ? 'mine' : 'all';
}
// Sales always mine unless somehow admin; admin/ops can choose.
$myQueueOnly = ($role === 'sales') || ($scope === 'mine' && in_array($role, ['admin', 'ops'], true));

$leads = repsDashListApplyLeadsForUser(
    $user,
    $statusFilter !== '' ? $statusFilter : null,
    $kindFilter !== '' ? $kindFilter : null,
    $myQueueOnly
);
$openCount = repsDashCountOpenApplyLeads();

$pathLabels = [
    'on_job' => 'On the job',
    'at_home' => 'At home',
    'company' => 'Company / team',
    'affiliate' => 'Affiliate seat',
];
$kindLabels = [
    'operator' => 'Operator',
    'shop' => 'Shop',
    'affiliate' => 'Affiliate',
];
$sourceLabels = [
    'referral' => 'Referral',
    'round_robin' => 'Round-robin',
    'manual' => 'Manual',
    'none' => 'Unassigned',
];

$subtitle = $myQueueOnly
    ? 'My queue · join funnel CRM'
    : 'All queues · ' . $openCount . ' open/claimed';

repsDashRenderHeader('Leads', 'leads');
repsDashRenderPageHeader('Leads CRM', $subtitle);
?>

<p class="mb-3 small d-flex flex-wrap gap-2 align-items-center">
  <?php if (in_array($role, ['admin', 'ops'], true)): ?>
    Scope:
    <a href="?scope=mine<?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?><?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>"
       class="<?= $scope === 'mine' ? 'fw-semibold' : '' ?>">Mine</a> ·
    <a href="?scope=all<?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?><?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>"
       class="<?= $scope === 'all' ? 'fw-semibold' : '' ?>">All</a>
    <span class="text-muted">|</span>
  <?php endif; ?>
  Status:
  <a href="?scope=<?= urlencode($scope) ?><?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>" class="<?= $statusFilter === '' ? 'fw-semibold' : '' ?>">All</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&status=open<?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>" class="<?= $statusFilter === 'open' ? 'fw-semibold' : '' ?>">Open</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&status=claimed<?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>" class="<?= $statusFilter === 'claimed' ? 'fw-semibold' : '' ?>">Claimed</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&status=closed<?= $kindFilter !== '' ? '&kind=' . urlencode($kindFilter) : '' ?>" class="<?= $statusFilter === 'closed' ? 'fw-semibold' : '' ?>">Closed</a>
  <span class="text-muted">|</span>
  Kind:
  <a href="?scope=<?= urlencode($scope) ?><?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?>" class="<?= $kindFilter === '' ? 'fw-semibold' : '' ?>">All</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&kind=operator<?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?>" class="<?= $kindFilter === 'operator' ? 'fw-semibold' : '' ?>">Operator</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&kind=shop<?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?>" class="<?= $kindFilter === 'shop' ? 'fw-semibold' : '' ?>">Shop</a> ·
  <a href="?scope=<?= urlencode($scope) ?>&kind=affiliate<?= $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '' ?>" class="<?= $kindFilter === 'affiliate' ? 'fw-semibold' : '' ?>">Affiliate</a>
</p>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Kind</th>
          <th>Path</th>
          <th>Contact</th>
          <th>Status</th>
          <th>Source</th>
          <th>Assigned</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($leads === []): ?>
        <tr><td colspan="8" class="text-muted p-3">No leads in this filter.</td></tr>
      <?php endif; ?>
      <?php foreach ($leads as $lead): ?>
        <tr>
          <td class="fw-semibold">
            <a href="<?php echo htmlspecialchars(repsDashLeadHref((int) $lead['id'])); ?>">
              <?php echo htmlspecialchars($lead['name']); ?>
            </a>
          </td>
          <td>
            <span class="badge text-bg-secondary"><?php echo htmlspecialchars($kindLabels[$lead['join_kind']] ?? $lead['join_kind']); ?></span>
          </td>
          <td class="small"><?php echo htmlspecialchars($pathLabels[$lead['path']] ?? $lead['path']); ?></td>
          <td class="small">
            <?php echo htmlspecialchars($lead['phone']); ?>
            <div class="text-muted"><?php echo htmlspecialchars($lead['email']); ?></div>
            <?php if (($lead['metro'] ?? '') !== ''): ?>
              <div class="text-muted"><?php echo htmlspecialchars($lead['metro']); ?></div>
            <?php endif; ?>
          </td>
          <td><?php repsDashStatusPill($lead['status']); ?></td>
          <td class="small">
            <span class="badge text-bg-light border"><?php echo htmlspecialchars($sourceLabels[$lead['assign_source']] ?? $lead['assign_source']); ?></span>
          </td>
          <td class="small"><?php echo htmlspecialchars((string) ($lead['assigned_sales_rep'] ?? '—')); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($lead['last_event_at'] ?: $lead['created_at']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
