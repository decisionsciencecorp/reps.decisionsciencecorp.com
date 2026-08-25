#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Hard reset to the live core Mark wants:
 *   - seats: rizzn (Mark), chuck + chuck-work, and individual seats for real
 *     MicroPS workers already in operators (uuid shift_user_id)
 *   - no CRM fixture shops (Chuck’s Detail Garage, Seven Mobile, Empanada, …)
 *   - no jim / seven / jim-work / other demo affiliates
 *
 * Keeps real uuid operators + their sessions/ledger. Rebuild shops later.
 *
 *   php tools/reset-live-book-core.php
 *
 * Idempotent. Locks demo seed, Dev Mode off, live_data on.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$pdo = repsDashDb();
$out = [
    'ok' => true,
    'kept_users' => [],
    'deleted_users' => [],
    'deleted_shops' => [],
    'unlinked_operators' => [],
    'flags' => [],
];

repsDashAppMetaSet('dash.skip_demo_seed', '1');
repsDashAppMetaSet('dash.dev_mode', '0');
repsDashSetLiveDataEnabled(true);
$out['flags'] = [
    'skip_demo_seed' => '1',
    'dev_mode' => repsDashIsDevMode() ? 'on' : 'off',
    'live_data' => repsDashLiveDataEnabled() ? '1' : '0',
];

/** Always keep Mark + Chuck dual seat. */
$alwaysKeep = ['rizzn', 'chuck', 'chuck-work'];

/** Real live workers already in operators (uuid, not sandbox/reps-user). */
$workerUsernames = [];
$ops = $pdo->query(
    "SELECT id, display_name, shift_user_id, matched_user_id FROM operators
     WHERE shift_user_id NOT LIKE 'sandbox-%'
       AND shift_user_id NOT LIKE 'reps-user-%'
       AND COALESCE(status,'') != 'retired'"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($ops as $op) {
    $matched = (int) ($op['matched_user_id'] ?? 0);
    if ($matched > 0) {
        $u = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $u->execute([$matched]);
        $uname = strtolower((string) $u->fetchColumn());
        if ($uname !== '' && $uname !== 'rizzn' && !str_starts_with($uname, 'chuck')) {
            $workerUsernames[$uname] = true;
        }
    }
    // Leon-style: named live worker without a keep seat yet — keep matched individual only
}

$keep = $alwaysKeep;
foreach (array_keys($workerUsernames) as $w) {
    $keep[] = $w;
}
$keep = array_values(array_unique($keep));

// Prefer keeping `leon` if Leon Gardner is a live operator (even if match was to deleted seven)
foreach ($ops as $op) {
    $name = strtolower(trim((string) ($op['display_name'] ?? '')));
    if ($name === 'leon gardner' || $name === 'leon') {
        if (!in_array('leon', $keep, true)) {
            $keep[] = 'leon';
        }
    }
}

$keepLower = array_map('strtolower', $keep);

function repsResetDeleteUser(PDO $pdo, int $uid): void
{
    $pdo->prepare('DELETE FROM api_keys WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\') WHERE matched_user_id = ?'
    )->execute([$uid]);
    $pdo->prepare(
        'UPDATE users SET linked_user_id = NULL WHERE linked_user_id = ?'
    )->execute([$uid]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
}

// Delete every user not on the keep list
foreach ($pdo->query('SELECT id, username FROM users') as $row) {
    $uname = strtolower((string) $row['username']);
    $uid = (int) $row['id'];
    if (in_array($uname, $keepLower, true)) {
        $out['kept_users'][] = $uname;
        continue;
    }
    repsResetDeleteUser($pdo, $uid);
    $out['deleted_users'][] = $uname;
}

// Ensure leon (if kept) is an individual worker, not hanging off a deleted affiliate
$leon = repsDashFindUserRawByUsername('leon');
if ($leon !== null) {
    $pdo->prepare(
        "UPDATE users SET role = 'individual', linked_user_id = NULL, email = '',
         updated_at = datetime('now') WHERE id = ?"
    )->execute([(int) $leon['id']]);
    $leonOp = $pdo->query(
        "SELECT id FROM operators WHERE lower(display_name) LIKE '%leon%' LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if ($leonOp) {
        $pdo->prepare(
            'UPDATE operators SET matched_user_id = ?, matched_at = datetime(\'now\'),
             assigned_sales_rep = NULL, shop_id = NULL, updated_at = datetime(\'now\')
             WHERE id = ?'
        )->execute([(int) $leon['id'], (int) $leonOp['id']]);
    }
}

// Wipe all CRM shops — rebuild later
foreach ($pdo->query('SELECT id, name FROM shops') as $shop) {
    $sid = (int) $shop['id'];
    $pdo->prepare('DELETE FROM shop_notes WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('UPDATE sessions SET shop_id = NULL WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('UPDATE operators SET shop_id = NULL, assigned_sales_rep = NULL WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('UPDATE ledger_lines SET shop_id = NULL WHERE shop_id = ?')->execute([$sid]);
    $pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$sid]);
    $out['deleted_shops'][] = ['id' => $sid, 'name' => (string) $shop['name']];
}

// Drop leftover fixture operators
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
    $pdo->prepare("DELETE FROM payout_payees WHERE entity_type = 'operator' AND entity_id = ?")->execute([$oid]);
    $pdo->prepare('DELETE FROM operators WHERE id = ?')->execute([$oid]);
    $out['unlinked_operators'][] = $op;
}

// Clear apply leads again
$pdo->exec('DELETE FROM lead_events');
$pdo->exec('DELETE FROM apply_leads');

$remaining = ['users' => [], 'shops' => [], 'operators' => []];
foreach ($pdo->query('SELECT id, username, role, display_name FROM users ORDER BY id') as $r) {
    $remaining['users'][] = $r;
}
foreach ($pdo->query('SELECT id, name FROM shops ORDER BY id') as $r) {
    $remaining['shops'][] = $r;
}
foreach ($pdo->query('SELECT id, display_name, shift_user_id, matched_user_id FROM operators ORDER BY id') as $r) {
    $remaining['operators'][] = $r;
}
$out['remaining'] = $remaining;
$out['keep_policy'] = $keep;

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit(0);
