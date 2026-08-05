<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('shops', $user);
$shops = repsDashShopsForUser($user);

$subtitle = match ((string) $user['role']) {
    'admin', 'ops' => 'All shops in the Reps book — open a shop for notes and team',
    'business_owner' => 'Your shop — open it to update notes',
    default => 'Your assigned shops plus the unassigned pool — open a shop for notes and team',
};
$title = (string) $user['role'] === 'business_owner' ? 'My shop' : 'Shops';

repsDashRenderHeader($title, 'shops');
repsDashRenderPageHeader($title, $subtitle);
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
      <?php if ($shops === []): ?>
        <tr><td colspan="7" class="text-muted p-3">No shops in scope for this seat.</td></tr>
      <?php endif; ?>
      <?php foreach ($shops as $shop): ?>
        <?php
        $href = repsDashShopHref((int) $shop['id']);
        $notes = trim((string) $shop['notes']);
        $notesPreview = $notes === ''
            ? 'Add notes…'
            : (strlen($notes) > 72 ? substr($notes, 0, 72) . '…' : $notes);
        ?>
        <tr>
          <td class="fw-semibold">
            <a href="<?php echo htmlspecialchars($href); ?>"><?php echo htmlspecialchars($shop['name']); ?></a>
          </td>
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
          <td class="small" style="max-width:16rem">
            <a class="text-decoration-none <?php echo $notes === '' ? 'text-muted fst-italic' : 'text-body'; ?>" href="<?php echo htmlspecialchars($href); ?>#shop-notes">
              <?php echo htmlspecialchars($notesPreview); ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
