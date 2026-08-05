<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

if (!repsDashIsDevMode()) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$roles = repsDashAllRoles();
$matrix = repsDashViewsRolesMatrix();
$current = (string) $user['role'];

repsDashRenderHeader('Views × roles', 'access');
repsDashRenderPageHeader(
    'Views × roles',
    'What each seat can open and what data it sees — PRD Doc #990 §5.5. Live mock enforces this matrix.'
);
?>

<div class="alert alert-info border-0 mb-3">
  You are auditing as <strong><?php echo htmlspecialchars(repsDashRoleLabel($current)); ?></strong>
  (<code><?php echo htmlspecialchars($current); ?></code>).
  <?php echo htmlspecialchars(repsDashScopeBlurb($current)); ?>
  Switch seats from the Dev Mode bar; this table is the contract.
</div>

<div class="surface p-0 mb-4">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0 rd-access-matrix">
      <thead>
        <tr>
          <th>View</th>
          <th>Purpose</th>
          <?php foreach ($roles as $r): ?>
            <th class="<?php echo $r === $current ? 'table-warning' : ''; ?>">
              <?php echo htmlspecialchars(repsDashRoleLabel($r)); ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($matrix as $row): ?>
        <tr>
          <td class="fw-semibold text-nowrap"><?php echo htmlspecialchars($row['label']); ?></td>
          <td class="small text-muted"><?php echo htmlspecialchars($row['purpose']); ?></td>
          <?php foreach ($roles as $r): ?>
            <?php
            $cell = (string) ($row['cells'][$r] ?? '—');
            $none = ($cell === '—' || str_starts_with($cell, '—'));
            ?>
            <td class="small <?php echo $none ? 'rd-cell-none' : ''; ?><?php echo $r === $current ? ' table-warning' : ''; ?>">
              <?php echo htmlspecialchars($cell); ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="surface p-3 mb-4">
  <h2 class="h5 mb-2">Session video / media</h2>
  <p class="mb-2 small text-muted">
    <strong>*</strong> We are not running our own capture app on this surface yet.
    Individual session rows (and any clip playback) only exist if <strong>Shift for Business</strong>
    (or a future DSC app) exposes them over API — no video player in Reps until then.
  </p>
  <p class="mb-0 small text-muted">
    <strong>Money is four peers, not one table:</strong>
    Admin = DSC portfolio · Ops = hours/reject drag · Sales = book earnings + producers ·
    Owner = “My pay” (shop keep + who produced). Sales has no Operators nav — producers live in Money.
  </p>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="surface p-3">
      <h2 class="h5 mb-2">Nav for this seat</h2>
      <ul class="mb-0">
        <?php foreach (repsDashNavKeysForRole($current) as $key): ?>
          <li><code><?php echo htmlspecialchars($key); ?></code></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="surface p-3">
      <h2 class="h5 mb-2">Home blocks / settings panels</h2>
      <p class="small mb-1"><strong>Home:</strong>
        <?php echo htmlspecialchars(implode(', ', repsDashHomeBlocksForRole($current))); ?></p>
      <p class="small mb-1"><strong>Settings:</strong>
        <?php echo htmlspecialchars(implode(', ', repsDashSettingsPanelsForRole($current))); ?></p>
      <p class="small mb-0"><strong>Money mode:</strong>
        <code><?php echo htmlspecialchars(repsDashMoneyModeForRole($current)); ?></code></p>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
