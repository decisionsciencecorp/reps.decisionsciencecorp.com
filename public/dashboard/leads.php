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

$role = (string) $user['role'];
$isAdminOps = in_array($role, ['admin', 'ops'], true);

$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['open', 'claimed', 'closed'], true)) {
    $statusFilter = '';
}

// Signup path type (form path) — admin/ops only.
$pathFilter = '';
if ($isAdminOps) {
    $pathFilter = trim((string) ($_GET['path'] ?? ''));
    if ($pathFilter !== '' && !in_array($pathFilter, ['on_job', 'at_home', 'company', 'affiliate'], true)) {
        $pathFilter = '';
    }
}

$myDefault = $role === 'sales';
$scope = trim((string) ($_GET['scope'] ?? ($myDefault ? 'mine' : 'all')));
if (!in_array($scope, ['mine', 'all'], true)) {
    $scope = $myDefault ? 'mine' : 'all';
}
$myQueueOnly = ($role === 'sales') || ($scope === 'mine' && $isAdminOps);

$leads = repsDashListApplyLeadsForUser(
    $user,
    $statusFilter !== '' ? $statusFilter : null,
    null,
    $myQueueOnly,
    $pathFilter !== '' ? $pathFilter : null
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

$qsBase = static function (array $overrides = []) use ($scope, $statusFilter, $pathFilter, $isAdminOps): string {
    $q = ['scope' => $overrides['scope'] ?? $scope];
    $st = array_key_exists('status', $overrides) ? $overrides['status'] : $statusFilter;
    $pt = array_key_exists('path', $overrides) ? $overrides['path'] : $pathFilter;
    if ($st !== null && $st !== '') {
        $q['status'] = $st;
    }
    if ($isAdminOps && $pt !== null && $pt !== '') {
        $q['path'] = $pt;
    }
    return '?' . http_build_query($q);
};

$subtitle = $myQueueOnly
    ? 'My queue · operator & shop leads'
    : 'All queues · ' . $openCount . ' open/claimed';

repsDashRenderHeader('Leads', 'leads');
repsDashRenderPageHeader('Leads CRM', $subtitle);
?>

<p class="mb-3 small d-flex flex-wrap gap-2 align-items-center">
  <?php if ($isAdminOps): ?>
    Scope:
    <a href="<?= htmlspecialchars($qsBase(['scope' => 'mine'])) ?>" class="<?= $scope === 'mine' ? 'fw-semibold' : '' ?>">Mine</a> ·
    <a href="<?= htmlspecialchars($qsBase(['scope' => 'all'])) ?>" class="<?= $scope === 'all' ? 'fw-semibold' : '' ?>">All</a>
    <span class="text-muted">|</span>
  <?php endif; ?>
  Status:
  <a href="<?= htmlspecialchars($qsBase(['status' => ''])) ?>" class="<?= $statusFilter === '' ? 'fw-semibold' : '' ?>">All</a> ·
  <a href="<?= htmlspecialchars($qsBase(['status' => 'open'])) ?>" class="<?= $statusFilter === 'open' ? 'fw-semibold' : '' ?>">Open</a> ·
  <a href="<?= htmlspecialchars($qsBase(['status' => 'claimed'])) ?>" class="<?= $statusFilter === 'claimed' ? 'fw-semibold' : '' ?>">Claimed</a> ·
  <a href="<?= htmlspecialchars($qsBase(['status' => 'closed'])) ?>" class="<?= $statusFilter === 'closed' ? 'fw-semibold' : '' ?>">Closed</a>
  <?php if ($isAdminOps): ?>
    <span class="text-muted">|</span>
    Signup path:
    <a href="<?= htmlspecialchars($qsBase(['path' => ''])) ?>" class="<?= $pathFilter === '' ? 'fw-semibold' : '' ?>">All</a> ·
    <a href="<?= htmlspecialchars($qsBase(['path' => 'on_job'])) ?>" class="<?= $pathFilter === 'on_job' ? 'fw-semibold' : '' ?>">On the job</a> ·
    <a href="<?= htmlspecialchars($qsBase(['path' => 'at_home'])) ?>" class="<?= $pathFilter === 'at_home' ? 'fw-semibold' : '' ?>">At home</a> ·
    <a href="<?= htmlspecialchars($qsBase(['path' => 'company'])) ?>" class="<?= $pathFilter === 'company' ? 'fw-semibold' : '' ?>">Company / team</a> ·
    <a href="<?= htmlspecialchars($qsBase(['path' => 'affiliate'])) ?>" class="<?= $pathFilter === 'affiliate' ? 'fw-semibold' : '' ?>">Affiliate</a>
  <?php endif; ?>
</p>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Kind</th>
          <th>Signup path</th>
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
