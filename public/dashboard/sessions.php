<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
$user = repsDashRequireLogin();
repsDashRequireNavKey('sessions', $user);
$role = (string) $user['role'];
$filterOp = isset($_GET['operator_id']) ? (int) $_GET['operator_id'] : 0;

if ($filterOp > 0) {
    if (!repsDashCanViewOperator($user, $filterOp)) {
        header('Location: /dashboard/sessions.php');
        exit;
    }
    $sessions = repsDashSessionsForOperator($filterOp);
    $op = repsDashFindOperator($filterOp);
    $filterLabel = $op['name'] ?? ('#' . $filterOp);
} else {
    $sessions = repsDashSessionsForUser($user);
    $filterLabel = null;
}

$selfOnly = in_array($role, ['employee', 'individual'], true);
$showShop = !$selfOnly || $role === 'employee';
$showOperator = !$selfOnly && $filterOp <= 0;

$subtitle = $selfOnly
    ? 'Your accepted and rejected capture sessions'
    : 'Capture sessions in your scope';
if ($filterLabel !== null) {
    $subtitle = 'Filtered to ' . $filterLabel;
}

repsDashRenderHeader('Sessions', 'sessions');
repsDashRenderPageHeader($selfOnly ? 'My sessions' : 'Sessions', $subtitle);
?>

<?php if ($filterOp > 0): ?>
  <p class="mb-3">
    <a class="small" href="<?php echo htmlspecialchars(repsDashOperatorHref($filterOp)); ?>">← Worker</a>
    · <a class="small" href="/dashboard/sessions.php">Clear filter</a>
  </p>
<?php endif; ?>

<div class="surface p-0">
  <?php
  repsDashRenderSessionTable($sessions, [
      'variant' => 'inbox',
      'show_operator' => $showOperator,
      'show_shop' => $showShop,
      'shop_dash' => $role === 'individual',
      'empty' => 'No sessions in scope for this seat.',
  ]);
  ?>
</div>

<?php repsDashRenderFooter(); ?>
