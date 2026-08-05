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
  <?php repsDashRenderOperatorRoster($operators); ?>
</div>

<?php repsDashRenderFooter(); ?>
