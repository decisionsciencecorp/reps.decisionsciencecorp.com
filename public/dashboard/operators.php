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
        ? 'Workers on your shop (mock roster)'
        : 'Workers linked to shops you can see (mock Shift roster)'
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
          <th>Accepted 7d</th>
          <th>Rejected 7d</th>
          <th>Last session</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($operators as $op): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?php echo htmlspecialchars($op['name']); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars($op['phone']); ?></div>
          </td>
          <td><?php echo htmlspecialchars($op['shop']); ?></td>
          <td><?php repsDashStatusPill($op['status']); ?></td>
          <td><?php echo htmlspecialchars((string) $op['accepted_7d']); ?></td>
          <td><?php echo htmlspecialchars((string) $op['rejected_7d']); ?></td>
          <td class="small"><?php echo htmlspecialchars($op['last_session']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
