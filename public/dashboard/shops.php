<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
$shops = repsDashShopsForUser($user);

repsDashRenderHeader('Shops', 'shops');
repsDashRenderPageHeader(
    'Shops',
    repsDashIsAdminOrOps($user)
        ? 'All shops in the Reps book (mock)'
        : 'Your assigned shops plus the unassigned pool (mock)'
);
?>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Shop</th>
          <th>Status</th>
          <th>Sales rep</th>
          <th>Contact</th>
          <th>Ops</th>
          <th>7d hrs</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($shops as $shop): ?>
        <tr>
          <td class="fw-semibold"><?php echo htmlspecialchars($shop['name']); ?></td>
          <td><?php repsDashStatusPill($shop['status']); ?></td>
          <td><?php echo htmlspecialchars((string) ($shop['assigned_sales_rep'] ?? '— unassigned')); ?></td>
          <td>
            <?php echo htmlspecialchars($shop['contact_name']); ?>
            <?php if ($shop['contact_phone'] !== ''): ?>
              <div class="small text-muted"><?php echo htmlspecialchars($shop['contact_phone']); ?></div>
            <?php endif; ?>
          </td>
          <td><?php echo (int) $shop['operators']; ?></td>
          <td><?php echo htmlspecialchars((string) $shop['accepted_hours_7d']); ?></td>
          <td class="small text-muted" style="max-width:16rem"><?php echo htmlspecialchars($shop['notes']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
