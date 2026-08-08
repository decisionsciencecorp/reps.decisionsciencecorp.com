<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/money-views.php';

$user = repsDashRequireLogin();
repsDashRequireNavKey('money', $user);

// Bootstrap ledger from fixtures only when live Shift data is not in play.
$ledgerEmpty = (int) repsDashDb()->query('SELECT COUNT(*) FROM ledger_lines')->fetchColumn() === 0;
if ($ledgerEmpty && !repsDashLiveDataEnabled()) {
    repsLedgerSeedFromMockShops();
}
repsSettlementReconcileStripeBalance('money_page_open');

$mode = repsDashMoneyModeForRole((string) $user['role']);
$shops = repsDashShopsForUser($user);
$repFilter = trim((string) ($_GET['rep'] ?? ''));
if ($repFilter === '') {
    $repFilter = null;
}

if ((string) $user['role'] === 'admin' && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    repsDashRequireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'disburse_batch') {
        repsDisburseRunBatch('manual_' . gmdate('Ymd_His'), false);
        header('Location: /dashboard/money.php?disbursed=1');
        exit;
    }
}

switch ($mode) {
    case 'dsc_command':
        repsDashRenderMoneyAdmin($user, $shops, $repFilter);
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
    case 'solo_payout':
        repsDashRenderMoneySolo($user);
        break;
    default:
        header('Location: /dashboard/');
        exit;
}
