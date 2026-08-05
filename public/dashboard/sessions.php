<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('sessions', $user);
$role = (string) $user['role'];
$sessions = repsDashSessionsForUser($user);
$selfOnly = in_array($role, ['employee', 'individual'], true);
$showShop = !$selfOnly || $role === 'employee';
$showOperator = !$selfOnly;

$subtitle = $selfOnly
    ? 'Your capture / hours rows (mock)'
    : ('Hours-feed rows in your scope (mock)'
        . (repsDashCanSeePartnerCode($user) ? ' · Partner C6N9T7' : ''));

repsDashRenderHeader('Sessions', 'sessions');
repsDashRenderPageHeader($selfOnly ? 'My sessions' : 'Sessions / hours', $subtitle);
?>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Session</th>
          <?php if ($showOperator): ?><th>Operator</th><?php endif; ?>
          <?php if ($showShop): ?><th>Shop</th><?php endif; ?>
          <th>Status</th>
          <th>Duration</th>
          <th>Accepted</th>
          <th>Reason</th>
          <th>Completed</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($sessions === []): ?>
        <tr><td colspan="8" class="text-muted p-3">No sessions in scope for this seat.</td></tr>
      <?php endif; ?>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="small font-monospace"><?php echo htmlspecialchars($s['session_id']); ?></td>
          <?php if ($showOperator): ?>
            <td><?php echo htmlspecialchars($s['operator']); ?></td>
          <?php endif; ?>
          <?php if ($showShop): ?>
            <td><?php echo htmlspecialchars($role === 'individual' ? '—' : $s['shop']); ?></td>
          <?php endif; ?>
          <td><?php repsDashStatusPill($s['status']); ?></td>
          <td><?php echo htmlspecialchars((string) $s['duration_hours']); ?></td>
          <td><?php echo htmlspecialchars((string) $s['accepted_hours']); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($s['rejection_reason'] !== '' ? $s['rejection_reason'] : '—'); ?></td>
          <td class="small"><?php echo htmlspecialchars($s['completed_at']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
