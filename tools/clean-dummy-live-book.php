#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Strip fixture/sandbox residue from the live book; keep real seats + live hours.
 *
 * Removes: sandbox/reps-user operators, retired fixture shops 104–106, seed/smoke
 * apply leads, sandbox Stripe payee + sandbox disbursement rows.
 * Keeps: rizzn + dual seats (jim/seven/chuck + worker seats), real uuid operators,
 * sessions/ledger tied to those operators, CRM shops 101–103.
 *
 *   php tools/clean-dummy-live-book.php
 *
 * Idempotent. Sets live_data on, demo seed locked, Dev Mode off.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$pdo = repsDashDb();
$out = [
    'ok' => true,
    'deleted_operators' => [],
    'deleted_shops' => [],
    'deleted_leads' => 0,
    'deleted_payees' => [],
    'deleted_disbursements' => [],
    'flags' => [],
];

repsDashAppMetaSet('dash.skip_demo_seed', '1');
repsDashAppMetaSet('dash.dev_mode', '0');
repsDashSetLiveDataEnabled(true);
$out['flags'] = [
    'skip_demo_seed' => repsDashAppMetaGet('dash.skip_demo_seed', ''),
    'dev_mode' => repsDashIsDevMode() ? 'on' : 'off',
    'live_data' => repsDashLiveDataEnabled() ? '1' : '0',
];

$fakeUsers = ['ops', 'agent', 'maria', 'alex', 'pat', 'mark'];
foreach ($fakeUsers as $uname) {
    $u = repsDashFindUserRawByUsername($uname);
    if ($u === null) {
        continue;
    }
    $uid = (int) $u['id'];
    $pdo->prepare('DELETE FROM api_keys WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\') WHERE matched_user_id = ?'
    )->execute([$uid]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
    $out['deleted_operators'][] = ['user' => $uname, 'id' => $uid];
}

$dummyOps = $pdo->query(
    "SELECT id, shift_user_id, display_name FROM operators
     WHERE shift_user_id LIKE 'sandbox-%'
        OR shift_user_id LIKE 'reps-user-%'
        OR lower(display_name) IN ('sandbox operator', 'pat solo')"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($dummyOps as $op) {
    $oid = (int) $op['id'];
    $pdo->prepare('DELETE FROM sessions WHERE operator_id = ?')->execute([$oid]);
    $pdo->prepare('DELETE FROM operator_match_events WHERE operator_id = ?')->execute([$oid]);
    $pdo->prepare('DELETE FROM ledger_lines WHERE operator_id = ?')->execute([$oid]);
    $pdo->prepare(
        "DELETE FROM payout_payees WHERE entity_type = 'operator' AND entity_id = ?"
    )->execute([$oid]);
    $pdo->prepare('DELETE FROM operators WHERE id = ?')->execute([$oid]);
    $out['deleted_operators'][] = [
        'operator_id' => $oid,
        'shift_user_id' => (string) $op['shift_user_id'],
        'display_name' => (string) $op['display_name'],
    ];
}

foreach ([104, 105, 106] as $sid) {
    $pdo->prepare('DELETE FROM shop_notes WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('UPDATE sessions SET shop_id = NULL WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('UPDATE operators SET shop_id = NULL WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$sid]);
    $out['deleted_shops'][] = $sid;
}

$leadIds = [];
foreach ($pdo->query('SELECT id, email, name FROM apply_leads') as $row) {
    $email = strtolower((string) ($row['email'] ?? ''));
    $name = strtolower((string) ($row['name'] ?? ''));
    $dummyEmail = str_ends_with($email, '.example')
        || str_ends_with($email, '@example.com')
        || str_contains($email, 'live-smoke')
        || str_contains($email, 'path-shot')
        || str_contains($email, 'aff-shot');
    $dummyName = str_contains($name, 'smoke')
        || str_contains($name, 'path shot')
        || str_contains($name, 'fleet wash hq');
    if ($dummyEmail || $dummyName) {
        $leadIds[] = (int) $row['id'];
    }
}
if ($leadIds !== []) {
    $ph = implode(',', array_fill(0, count($leadIds), '?'));
    $pdo->prepare("DELETE FROM lead_events WHERE lead_id IN ($ph)")->execute($leadIds);
    $pdo->prepare("DELETE FROM apply_leads WHERE id IN ($ph)")->execute($leadIds);
    $out['deleted_leads'] = count($leadIds);
}

$sandboxBatches = $pdo->query(
    "SELECT id FROM disbursement_batches WHERE label LIKE 'sandbox%'"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
foreach ($sandboxBatches as $bid) {
    $bid = (int) $bid;
    $pdo->prepare('DELETE FROM disbursement_transfers WHERE batch_id = ?')->execute([$bid]);
    $pdo->prepare('DELETE FROM disbursement_batches WHERE id = ?')->execute([$bid]);
    $out['deleted_disbursements'][] = $bid;
}

$sandboxPayees = $pdo->query(
    "SELECT id, display_name, email FROM payout_payees
     WHERE lower(display_name) LIKE '%sandbox%'
        OR lower(email) LIKE '%sandbox%'
        OR lower(email) LIKE '%@example.com'"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($sandboxPayees as $p) {
    $pid = (int) $p['id'];
    $pdo->prepare('DELETE FROM payout_payees WHERE id = ?')->execute([$pid]);
    $out['deleted_payees'][] = [
        'id' => $pid,
        'display_name' => (string) $p['display_name'],
    ];
}

$remaining = [
    'users' => [],
    'shops' => [],
    'operators' => [],
    'sessions' => (int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(),
    'apply_leads' => (int) $pdo->query('SELECT COUNT(*) FROM apply_leads')->fetchColumn(),
];
foreach ($pdo->query('SELECT username, role, is_active FROM users ORDER BY id') as $r) {
    $remaining['users'][] = $r;
}
foreach ($pdo->query('SELECT id, name, status FROM shops ORDER BY id') as $r) {
    $remaining['shops'][] = $r;
}
foreach ($pdo->query('SELECT id, display_name, shift_user_id, status FROM operators ORDER BY id') as $r) {
    $remaining['operators'][] = $r;
}
$out['remaining'] = $remaining;

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit($out['ok'] ? 0 : 1);
