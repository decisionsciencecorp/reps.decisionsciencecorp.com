<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('sessions', $user);
$sessions = repsDashSessionsForUser($user);

repsDashRenderHeader('Sessions', 'sessions');
repsDashRenderPageHeader('Sessions / hours', 'Mock hours-feed rows · Partner C6N9T7');
?>

<div class="surface p-0">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Session</th>
          <th>Operator</th>
          <th>Shop</th>
          <th>Status</th>
          <th>Duration</th>
          <th>Accepted</th>
          <th>Reason</th>
          <th>Completed</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="small font-monospace"><?php echo htmlspecialchars($s['session_id']); ?></td>
          <td><?php echo htmlspecialchars($s['operator']); ?></td>
          <td><?php echo htmlspecialchars($s['shop']); ?></td>
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
