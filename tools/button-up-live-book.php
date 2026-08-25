#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Live-book hygiene: Dev Mode off, retire fixture shops/seats, dual affiliate+worker
 * seats for Leon/Chuck/Jim, match Partner workers to those worker seats.
 *
 *   php tools/button-up-live-book.php
 *
 * Idempotent. Does not poll hours — run tools/poll-shift.php first so operators exist.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$pdo = repsDashDb();
$out = [
    'ok' => true,
    'dev_mode' => null,
    'retired_shops' => [],
    'deactivated_users' => [],
    'dual_seats' => [],
    'matches' => [],
    'shop_touches' => [],
];

repsDashAppMetaSet('dash.dev_mode', '0');
$out['dev_mode'] = repsDashIsDevMode() ? 'on' : 'off';

foreach ([104, 105, 106] as $sid) {
    $pdo->prepare(
        "UPDATE shops SET status = 'retired', updated_at = datetime('now')
         WHERE id = ? AND status != 'retired'"
    )->execute([$sid]);
    $out['retired_shops'][] = $sid;
}

$pdo->prepare(
    "UPDATE operators SET status = 'retired', updated_at = datetime('now')
     WHERE shift_user_id LIKE 'sandbox-%' AND COALESCE(status,'') != 'retired'"
)->execute();

foreach (['maria', 'alex', 'pat'] as $uname) {
    $u = repsDashFindUserByUsername($uname);
    if ($u && !empty($u['is_active'])) {
        $res = repsDashUpdateUser((int) $u['id'], ['is_active' => 0]);
        $out['deactivated_users'][] = ['username' => $uname, 'ok' => $res['ok'] ?? false];
    }
}

// Alex was wrongly pointed at Mark's live operator id 1.
$markOp = $pdo->query(
    "SELECT id FROM operators WHERE display_name = 'Mark Hopkins' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if ($markOp) {
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\') WHERE id = ?'
    )->execute([(int) $markOp['id']]);
}

$pdo->prepare(
    "UPDATE shops SET notes = ?, updated_at = datetime('now') WHERE id = 102"
)->execute(['Chuck Cutler — affiliate @chuck, worker @chuck-work.']);
$out['shop_touches'] = [102];

$jim = repsDashFindUserByUsername('jim');
if ($jim) {
    repsDashUpdateUser((int) $jim['id'], ['display_name' => 'Jim']);
}

$admin = repsDashFindUserByUsername('rizzn') ?? repsDashFindUserByUsername('mark');
$actorId = $admin ? (int) $admin['id'] : 1;

foreach (repsDashDualSeatPairs() as $pair) {
    $seat = repsDashEnsureLinkedWorkerSeat(
        $pair['affiliate'],
        $pair['worker'],
        $pair['display_name']
    );
    $out['dual_seats'][] = $pair + $seat;
    if (!($seat['ok'] ?? false)) {
        $out['ok'] = false;
        continue;
    }
    $workerId = (int) ($seat['worker_id'] ?? 0);
    $needles = [];
    $dn = strtolower($pair['display_name']);
    $needles[] = $dn;
    if ($pair['worker'] === 'leon') {
        $needles[] = 'leon gardner';
        $needles[] = 'leon';
    } elseif ($pair['worker'] === 'chuck-work') {
        $needles[] = 'chuck';
    } elseif ($pair['worker'] === 'jim-work') {
        $needles[] = 'jim';
    }

    $ops = $pdo->query(
        "SELECT id, display_name, matched_user_id FROM operators
         WHERE shift_user_id NOT LIKE 'reps-user-%'
           AND shift_user_id NOT LIKE 'sandbox-%'
           AND COALESCE(status,'') != 'retired'"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $matchedOp = null;
    foreach ($ops as $op) {
        $name = strtolower(trim((string) ($op['display_name'] ?? '')));
        foreach ($needles as $n) {
            if ($name === $n || ($n !== '' && str_contains($name, $n))) {
                $matchedOp = $op;
                break 2;
            }
        }
    }
    if ($matchedOp === null) {
        $out['matches'][] = [
            'worker' => $pair['worker'],
            'ok' => false,
            'error' => 'operator_not_found',
        ];
        continue;
    }
    $already = (int) ($matchedOp['matched_user_id'] ?? 0);
    if ($already === $workerId) {
        $pdo->prepare(
            'UPDATE operators SET assigned_sales_rep = ?, shop_id = NULL, updated_at = datetime(\'now\')
             WHERE id = ?'
        )->execute([$pair['affiliate'], (int) $matchedOp['id']]);
        $out['matches'][] = [
            'worker' => $pair['worker'],
            'operator_id' => (int) $matchedOp['id'],
            'ok' => true,
            'already' => true,
        ];
        continue;
    }
    $res = repsOperatorMatchUser((int) $matchedOp['id'], $workerId, $actorId, 'button-up dual seat');
    if ($res['ok'] ?? false) {
        $pdo->prepare(
            'UPDATE operators SET assigned_sales_rep = ?, shop_id = NULL, updated_at = datetime(\'now\')
             WHERE id = ?'
        )->execute([$pair['affiliate'], (int) $matchedOp['id']]);
    }
    $out['matches'][] = [
        'worker' => $pair['worker'],
        'operator_id' => (int) $matchedOp['id'],
        'operator_name' => (string) ($matchedOp['display_name'] ?? ''),
        'ok' => $res['ok'] ?? false,
        'error' => $res['error'] ?? null,
    ];
    if (!($res['ok'] ?? false)) {
        $out['ok'] = false;
    }
}

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit($out['ok'] ? 0 : 1);
