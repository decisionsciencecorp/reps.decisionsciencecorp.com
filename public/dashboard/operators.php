<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('operators', $user);
$operators = repsDashOperatorsForUser($user);

$isOwner = (string) $user['role'] === 'business_owner';
repsDashRenderHeader($isOwner ? 'Team' : 'Operators', 'operators');
repsDashRenderPageHeader(
    $isOwner ? 'Team' : 'Operators',
    $isOwner
        ? 'Manage who’s on your shop and open a worker for acceptance / day drill-down (Shift-shaped mock)'
        : 'Workers in your scope — open a name for Worker detail (mock)'
);
?>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Shop</th>
          <th>Status</th>
          <th>Accepted</th>
          <th>Accept rate</th>
          <th>Last session</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($operators === []): ?>
        <tr><td colspan="6" class="text-muted p-3">No operators in scope.</td></tr>
      <?php endif; ?>
      <?php foreach ($operators as $op):
          $st = repsDashOperatorDetailStats((int) $op['id']);
          ?>
        <tr>
          <td>
            <a class="fw-semibold text-decoration-none" href="<?php echo htmlspecialchars(repsDashOperatorHref((int) $op['id'])); ?>">
              <?php echo htmlspecialchars($op['name']); ?>
            </a>
            <div class="small text-muted"><?php echo htmlspecialchars($op['phone']); ?></div>
          </td>
          <td><?php echo htmlspecialchars($op['shop']); ?></td>
          <td>
            <?php repsDashStatusPill($op['status']); ?>
            <?php if (!empty($op['matched'])): ?>
              <span class="badge text-bg-light border ms-1">Matched</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars((string) $st['accepted_hours']); ?> h</td>
          <td><?php echo $st['acceptance_rate'] === null ? '—' : ((int) $st['acceptance_rate'] . '%'); ?></td>
          <td class="small"><?php echo htmlspecialchars($op['last_session']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
