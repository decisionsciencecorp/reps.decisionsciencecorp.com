<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('leads', $user);

if (!repsDashCanManageApplyLeads($user)) {
    header('Location: /dashboard/');
    exit;
}

$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['open', 'claimed', 'closed'], true)) {
    $statusFilter = '';
}

$leads = repsDashListApplyLeads($statusFilter !== '' ? $statusFilter : null);
$openCount = repsDashCountOpenApplyLeads();

$pathLabels = [
    'on_job' => 'On the job',
    'at_home' => 'At home',
    'company' => 'Company / team',
];

repsDashRenderHeader('Leads', 'leads');
repsDashRenderPageHeader(
    'Apply leads',
    'Inbound from reps.decisionsciencecorp.com apply form (channel=reps) · ' . $openCount . ' open/claimed'
);
?>

<p class="mb-3 small">
  Filter:
  <a href="/dashboard/leads.php" class="<?php echo $statusFilter === '' ? 'fw-semibold' : ''; ?>">All</a> ·
  <a href="/dashboard/leads.php?status=open" class="<?php echo $statusFilter === 'open' ? 'fw-semibold' : ''; ?>">Open</a> ·
  <a href="/dashboard/leads.php?status=claimed" class="<?php echo $statusFilter === 'claimed' ? 'fw-semibold' : ''; ?>">Claimed</a> ·
  <a href="/dashboard/leads.php?status=closed" class="<?php echo $statusFilter === 'closed' ? 'fw-semibold' : ''; ?>">Closed</a>
</p>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Path</th>
          <th>Contact</th>
          <th>Status</th>
          <th>Assigned</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($leads === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No leads in this filter.</td></tr>
      <?php endif; ?>
      <?php foreach ($leads as $lead): ?>
        <tr>
          <td class="fw-semibold">
            <a href="<?php echo htmlspecialchars(repsDashLeadHref((int) $lead['id'])); ?>">
              <?php echo htmlspecialchars($lead['name']); ?>
            </a>
          </td>
          <td class="small"><?php echo htmlspecialchars($pathLabels[$lead['path']] ?? $lead['path']); ?></td>
          <td class="small">
            <?php echo htmlspecialchars($lead['phone']); ?>
            <div class="text-muted"><?php echo htmlspecialchars($lead['email']); ?></div>
          </td>
          <td><?php repsDashStatusPill($lead['status']); ?></td>
          <td class="small"><?php echo htmlspecialchars((string) ($lead['assigned_sales_rep'] ?? '—')); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($lead['created_at']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
