<?php
declare(strict_types=1);

/**
 * Scope matrix smoke test — CLI regression for seat ACL.
 *
 * Usage (from repo root):
 *   php tools/scope-matrix-smoke.php
 *
 * Exit 0 = all assertions pass; non-zero = failures printed.
 */

$root = dirname(__DIR__);
$tmpDb = sys_get_temp_dir() . '/reps-dash-scope-smoke-' . getmypid() . '.sqlite';
@unlink($tmpDb);
putenv('REPS_DASH_DB_PATH=' . $tmpDb);
putenv('REPS_DASH_DEV_MODE=0');
require_once $root . '/public/dashboard/includes/bootstrap.php';
register_shutdown_function(static function () use ($tmpDb): void {
    @unlink($tmpDb);
});

/** @var list<string> $failures */
$failures = [];

function assertTrue(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        $failures[] = $msg;
    }
}

function assertEq(mixed $got, mixed $want, string $msg): void
{
    assertTrue($got === $want, $msg . ' (got=' . var_export($got, true) . ' want=' . var_export($want, true) . ')');
}

/** @return array<string, array<string, mixed>> */
function accountsByUsername(): array
{
    $out = [];
    foreach (repsDashDemoAccounts() as $a) {
        $out[(string) $a['username']] = $a;
    }
    return $out;
}

$by = accountsByUsername();

// --- Pat Solo (id 9): admin/ops/self + sourcing affiliate (jim) ---
assertTrue(repsDashCanViewOperator($by['mark'], 9), 'admin can view Pat');
assertTrue(repsDashCanViewOperator($by['ops'], 9), 'ops can view Pat');
assertTrue(repsDashCanViewOperator($by['pat'], 9), 'individual can view self (Pat)');
assertTrue(repsDashCanViewOperator($by['jim'], 9), 'sales jim (sourcer) can view Pat');
assertTrue(!repsDashCanViewOperator($by['seven'], 9), 'sales seven cannot view Pat (jim-sourced)');
assertTrue(!repsDashCanViewOperator($by['chuck'], 9), 'sales chuck cannot view Pat');
assertTrue(!repsDashCanViewOperator($by['maria'], 9), 'owner cannot view Pat');
assertTrue(!repsDashCanViewOperator($by['alex'], 9), 'employee cannot view Pat');
assertTrue(!repsDashCanViewOperator($by['agent'], 9), 'agent cannot view Pat');

$adminOps = repsDashOperatorsForUser($by['mark']);
$adminIds = array_map(static fn($o) => (int) $o['id'], $adminOps);
assertTrue(in_array(9, $adminIds, true), 'admin operators list includes Pat');
assertEq(count($adminOps), count(repsDashAllOperators()), 'admin sees all operators');

$jimInd = repsDashIndividualsForSalesUser($by['jim']);
assertEq(count($jimInd), 1, 'jim has one sourced individual');
assertEq((int) $jimInd[0]['id'], 9, 'jim sourced individual is Pat');

$sevenInd = repsDashIndividualsForSalesUser($by['seven']);
assertEq(count($sevenInd), 1, 'seven has one sourced individual (Riley invited)');
assertEq((int) $sevenInd[0]['id'], 10, 'seven sourced individual is Riley');

assertTrue(repsDashCanViewOperator($by['seven'], 10), 'seven can view Riley');
assertTrue(!repsDashCanViewOperator($by['jim'], 10), 'jim cannot view Riley');
assertTrue(!repsDashCanViewOperator($by['jim'], 11), 'jim cannot view unsourced solo');
assertTrue(repsDashCanViewOperator($by['mark'], 11), 'admin can view unsourced solo');

$jimOpIds = array_map(static fn($o) => (int) $o['id'], repsDashOperatorsForUser($by['jim']));
assertTrue(in_array(9, $jimOpIds, true), 'jim operators include Pat');
assertTrue(in_array(1, $jimOpIds, true), 'jim operators still include shop workers');

$jimSessOps = array_unique(array_map(
    static fn($s) => (int) $s['operator_id'],
    repsDashSessionsForUser($by['jim'])
));
assertTrue(in_array(9, $jimSessOps, true), 'jim sessions include Pat');
assertTrue(!in_array(11, $jimSessOps, true), 'jim sessions exclude unsourced solo');

// --- Cross-shop isolation ---
assertTrue(repsDashCanViewOperator($by['alex'], 1), 'employee can view self (Alex)');
assertTrue(!repsDashCanViewOperator($by['alex'], 2), 'employee cannot view teammate Jordan');
assertTrue(repsDashCanViewOperator($by['maria'], 1), 'owner can view Alex in shop 104');
assertTrue(!repsDashCanViewOperator($by['maria'], 5), 'owner cannot view Seven (other shop)');
assertTrue(repsDashCanViewOperator($by['jim'], 1), 'jim can view Alex (book)');
assertTrue(!repsDashCanViewOperator($by['jim'], 5), 'jim cannot view Seven');
assertTrue(repsDashCanViewOperator($by['seven'], 5), 'seven can view Seven Stone');
assertTrue(!repsDashCanViewOperator($by['seven'], 1), 'seven cannot view Alex');
assertTrue(!repsDashCanViewOperator($by['chuck'], 1), 'chuck pitched shop has no Alex');

// --- Agent empty desk ---
assertEq(repsDashShopsForUser($by['agent']), [], 'agent shops empty');
assertEq(repsDashOperatorsForUser($by['agent']), [], 'agent operators empty');
assertEq(repsDashSessionsForUser($by['agent']), [], 'agent sessions empty');
assertTrue(!repsDashCanOpenOperatorDesk($by['agent']), 'agent cannot open operator desk');
assertTrue(!repsDashCanOpenBookWideDay($by['agent']), 'agent cannot open book-wide day');
assertTrue(!repsDashCanViewOperator($by['agent'], 1), 'agent cannot view any operator');

// --- Sales book-wide day blocked; admin allowed ---
assertTrue(!repsDashCanOpenBookWideDay($by['jim']), 'sales cannot open book-wide day');
assertTrue(repsDashCanOpenBookWideDay($by['mark']), 'admin can open book-wide day');
assertTrue(repsDashCanOpenBookWideDay($by['maria']), 'owner can open shop day');
assertTrue(repsDashCanOpenBookWideDay($by['alex']), 'employee can open self day feed');

// --- Sales still drills into in-book workers ---
assertTrue(repsDashCanViewOperator($by['jim'], 1), 'sales Money drill-down Alex OK');
assertTrue(repsDashCanOpenOperatorDesk($by['jim']), 'sales can open operator desk');

// --- Nav education matrix ---
assertTrue(in_array('education', repsDashNavKeysForRole('sales'), true), 'sales has education');
assertTrue(in_array('education', repsDashNavKeysForRole('business_owner'), true), 'owner has education');
assertTrue(!in_array('education', repsDashNavKeysForRole('admin'), true), 'admin no education');
assertTrue(!in_array('education', repsDashNavKeysForRole('ops'), true), 'ops no education');
assertTrue(!in_array('education', repsDashNavKeysForRole('agent'), true), 'agent no education');
assertTrue(in_array('leads', repsDashNavKeysForRole('sales'), true), 'sales has leads nav');
assertTrue(!in_array('sessions', repsDashNavKeysForRole('sales'), true), 'sales no sessions nav');
assertTrue(in_array('leads', repsDashNavKeysForRole('admin'), true), 'admin has leads nav');
assertTrue(!in_array('leads', repsDashNavKeysForRole('employee'), true), 'employee no leads');
assertTrue(in_array('money', repsDashNavKeysForRole('individual'), true), 'individual has My pay');
assertTrue(!in_array('money', repsDashNavKeysForRole('employee'), true), 'employee no money (shop keeps capture)');
assertTrue(repsDashMoneyModeForRole('individual') === 'solo_payout', 'individual solo_payout mode');
assertTrue(repsDashMoneyModeForRole('business_owner') === 'owner_payout', 'owner_payout mode');

// --- Economics single source ---
assertEq(repsDashMoneyHourlyRate(), 20.0, 'hourly rate constant');
$st = repsDashOperatorDetailStats(1);
assertTrue(isset($st['accepted_hours'], $st['by_day']), 'operator rollup shape');

// --- Repository seam callable ---
assertTrue(repsDashFindOperator(9) !== null, 'find Pat via repository');
assertTrue(repsDashFindShop(104) !== null, 'find Fleet Wash via repository');

if ($failures === []) {
    echo "OK — scope matrix smoke (" . count(repsDashDemoAccounts()) . " seats)\n";
    exit(0);
}

fwrite(STDERR, 'FAIL — ' . count($failures) . " assertion(s):\n");
foreach ($failures as $f) {
    fwrite(STDERR, '  - ' . $f . "\n");
}
exit(1);
