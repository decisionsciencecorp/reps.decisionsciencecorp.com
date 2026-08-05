<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('shops', $user);

$id = (int) ($_GET['id'] ?? 0);
$shop = $id > 0 ? repsDashFindShop($id) : null;

if ($shop === null || !repsDashCanViewShop($user, $id)) {
    http_response_code(404);
    repsDashRenderHeader('Shop', 'shops');
    echo '<div class="alert alert-danger">Shop not found or not in your scope.</div>';
    echo '<a class="btn btn-outline-primary" href="/dashboard/shops.php">Back to shops</a>';
    repsDashRenderFooter();
    exit;
}

$flash = '';
$flashErr = '';
$canEditNotes = repsDashCanEditShopNotes($user, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditNotes) {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_notes') {
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (strlen($notes) > 4000) {
            $flashErr = 'Notes must be 4000 characters or fewer.';
        } else {
            repsDashSaveShopNotes($id, $notes, (int) $user['id']);
            $shop = repsDashFindShop($id) ?? $shop;
            $flash = 'Notes saved.';
        }
    }
}

$ops = array_values(array_filter(
    repsDashOperatorsForUser($user),
    static fn(array $o): bool => (int) ($o['shop_id'] ?? -1) === $id
));
$role = (string) $user['role'];
$backLabel = $role === 'business_owner' ? 'Back to My shop' : 'Back to shops';

repsDashRenderHeader((string) $shop['name'], 'shops');
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="/dashboard/shops.php">
    <i class="bi bi-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
  </a>
</p>
<?php
repsDashRenderPageHeader(
    (string) $shop['name'],
    'Shop detail · contact, notes, and team (mock hours until Slice C)'
);
?>

<?php if ($flash !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($flashErr); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?php repsDashStatusPill($shop['status']); ?>
  <span class="small text-muted">
    Sales:
    <?php echo htmlspecialchars((string) ($shop['assigned_sales_rep'] ?? '— unassigned')); ?>
  </span>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Operators</div>
      <div class="fs-3 fw-semibold"><?php echo (int) $shop['operators']; ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted 7d</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $shop['accepted_hours_7d']); ?> h</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Reject rate</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round((float) $shop['reject_rate'] * 100)); ?>%</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Shop split (mock)</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) round((float) $shop['agreed_shop_split'] * 100)); ?>%</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Contact</h2>
      <dl class="row mb-0 small">
        <dt class="col-4">Name</dt>
        <dd class="col-8"><?php echo htmlspecialchars((string) $shop['contact_name']); ?></dd>
        <dt class="col-4">Phone</dt>
        <dd class="col-8">
          <?php if ((string) $shop['contact_phone'] !== ''): ?>
            <?php echo htmlspecialchars((string) $shop['contact_phone']); ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </dd>
      </dl>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Notes</h2>
      <?php if ($canEditNotes): ?>
        <form method="post">
          <?php echo repsDashCsrfField(); ?>
          <input type="hidden" name="action" value="save_notes">
          <label class="form-label visually-hidden" for="shop-notes">Pipeline notes</label>
          <textarea class="form-control mb-2" id="shop-notes" name="notes" rows="5" maxlength="4000"><?php echo htmlspecialchars((string) $shop['notes']); ?></textarea>
          <button type="submit" class="btn btn-primary btn-sm">Save notes</button>
          <span class="small text-muted ms-2">Saved on this dashboard (survives reload). Shift sync comes in Slice C.</span>
        </form>
      <?php else: ?>
        <p class="mb-0 small"><?php echo htmlspecialchars((string) $shop['notes']); ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="surface p-0">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
    <h2 class="h5 mb-0">Team on this shop</h2>
    <?php if (in_array('operators', repsDashNavKeysForRole($role), true)): ?>
      <a class="small" href="/dashboard/operators.php">All operators</a>
    <?php endif; ?>
  </div>
  <?php if ($ops === []): ?>
    <p class="p-3 mb-0 text-muted small">No operators in scope for this shop yet.</p>
  <?php else: ?>
    <?php repsDashRenderOperatorRoster($ops); ?>
  <?php endif; ?>
</div>

<?php repsDashRenderFooter(); ?>
