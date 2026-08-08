<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();

$sessionId = trim((string) ($_GET['id'] ?? ''));
$session = $sessionId !== '' ? repsDashFindSession($sessionId) : null;

if ($session === null || !repsDashCanViewSession($user, $sessionId)) {
    http_response_code(404);
    repsDashRenderHeader('Session', 'sessions');
    echo '<div class="alert alert-danger">Session not found or not in your scope.</div>';
    echo '<a class="btn btn-outline-primary" href="/dashboard/sessions.php">Back to sessions</a>';
    repsDashRenderFooter();
    exit;
}

$role = (string) $user['role'];
$oid = (int) ($session['operator_id'] ?? 0);
$shopId = (int) ($session['shop_id'] ?? 0);
$rate = repsDashMoneyHourlyRate();
$earn = (float) $session['accepted_hours'] * $rate;
$reason = (string) ($session['rejection_reason'] ?? '');
$eduId = $reason !== '' ? repsDashEducationIdForRejectReason($reason) : null;
$activeNav = in_array($role, ['sales'], true) ? 'money' : 'sessions';
if (!in_array('sessions', repsDashNavKeysForRole($role), true) && $role === 'sales') {
    $activeNav = 'money';
}

$back = $oid > 0 && !empty($session['day'])
    ? repsDashDayHref((string) $session['day'], $oid)
    : '/dashboard/sessions.php';
$backLabel = $oid > 0 && !empty($session['day']) ? 'Back to day' : 'Back to sessions';

repsDashRenderHeader((string) $session['session_id'], $activeNav);
?>
<p class="mb-3">
  <a class="small text-decoration-none" href="<?php echo htmlspecialchars($back); ?>">
    <i class="bi bi-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
  </a>
</p>
<?php
repsDashRenderPageHeader(
    (string) $session['session_id'],
    'Hours-feed session · Shift field shape (Doc #818)'
        . (repsDashLiveDataEnabled() ? ' · live' : ' · fixture fallback')
);
?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <?php repsDashStatusPill((string) $session['status']); ?>
  <span class="small text-muted">Partner <code><?php echo htmlspecialchars((string) ($session['partner_code'] ?? 'C6N9T7')); ?></code></span>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Duration</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $session['duration_hours']); ?> h</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Accepted</div>
      <div class="fs-3 fw-semibold"><?php echo htmlspecialchars((string) $session['accepted_hours']); ?> h</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Est. earnings</div>
      <div class="fs-3 fw-semibold">$<?php echo number_format($earn, 2); ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="surface p-3 h-100">
      <div class="text-muted small">Completed</div>
      <div class="fs-6 fw-semibold"><?php echo htmlspecialchars((string) $session['completed_at']); ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Links</h2>
      <dl class="row mb-0 small">
        <dt class="col-4">Operator</dt>
        <dd class="col-8">
          <?php
          if ($oid > 0) {
              echo repsDashOperatorLinkHtml($oid, (string) $session['operator']);
          } else {
              echo htmlspecialchars((string) $session['operator']);
          }
          ?>
        </dd>
        <dt class="col-4">Shop</dt>
        <dd class="col-8">
          <?php if ($shopId > 0): ?>
            <a href="<?php echo htmlspecialchars(repsDashShopHref($shopId)); ?>"><?php echo htmlspecialchars((string) $session['shop']); ?></a>
          <?php else: ?>
            <?php echo htmlspecialchars((string) $session['shop']); ?>
          <?php endif; ?>
        </dd>
        <dt class="col-4">Day</dt>
        <dd class="col-8">
          <?php if (!empty($session['day'])): ?>
            <a href="<?php echo htmlspecialchars(repsDashDayHref((string) $session['day'], $oid > 0 ? $oid : null)); ?>">
              <?php echo htmlspecialchars((string) $session['day']); ?>
            </a>
          <?php else: ?>
            —
          <?php endif; ?>
        </dd>
      </dl>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="surface p-3 h-100">
      <h2 class="h5 mb-3">Rejection / quality</h2>
      <?php if ($reason === ''): ?>
        <p class="mb-0 text-muted small">No rejection reason on this row (completed or pending).</p>
      <?php else: ?>
        <p class="mb-1"><strong><?php echo htmlspecialchars(repsDashRejectionReasonLabel($reason)); ?></strong></p>
        <p class="small text-muted mb-2"><code><?php echo htmlspecialchars($reason); ?></code></p>
        <?php if ($eduId && in_array('education', repsDashNavKeysForRole($role), true)): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(repsDashEducationArticleHref($eduId)); ?>">
            Open coaching article
          </a>
        <?php endif; ?>
      <?php endif; ?>
      <p class="small text-muted mt-3 mb-0">
        Session video is not in Shift’s hours-feed API. When media exists, this page is the slot for it.
      </p>
    </div>
  </div>
</div>

<?php repsDashRenderFooter(); ?>
