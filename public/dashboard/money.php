<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/money-views.php';

$user = repsDashRequireLogin();
repsDashRequireNavKey('money', $user);
$mode = repsDashMoneyModeForRole((string) $user['role']);
$shops = repsDashShopsForUser($user);

switch ($mode) {
    case 'dsc_command':
        repsDashRenderMoneyAdmin($user, $shops);
        break;
    case 'ops_pulse':
        repsDashRenderMoneyOps($user, $shops);
        break;
    case 'affiliate_book':
        repsDashRenderMoneySales($user, $shops);
        break;
    case 'owner_payout':
        repsDashRenderMoneyOwner($user, $shops);
        break;
    default:
        header('Location: /dashboard/');
        exit;
}
