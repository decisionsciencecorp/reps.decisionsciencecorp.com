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
        ? 'People on your shop — open a name for hours and day detail'
        : 'Workers in your scope — open a name for hours and day detail'
);
?>

<div class="surface p-0">
  <?php repsDashRenderOperatorRoster($operators); ?>
</div>

<?php repsDashRenderFooter(); ?>
