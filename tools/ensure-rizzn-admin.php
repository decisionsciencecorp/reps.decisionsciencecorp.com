#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Live book: rizzn is the only admin; drop demo/fixture seats.
 *
 * Does not print the password. Reads it from REPS_RIZZN_PASSWORD or
 * REPS_RIZZN_PASSWORD_FILE (one line).
 *
 *   REPS_RIZZN_PASSWORD_FILE=/tmp/x php tools/ensure-rizzn-admin.php
 *
 * Idempotent. Locks demo seed so bootstrap cannot recreate fixture users.
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$pass = (string) (getenv('REPS_RIZZN_PASSWORD') ?: '');
$file = (string) (getenv('REPS_RIZZN_PASSWORD_FILE') ?: '');
if ($pass === '' && $file !== '' && is_readable($file)) {
    $pass = trim((string) file_get_contents($file));
}
if (strlen($pass) < REPS_DASH_PASSWORD_MIN) {
    fwrite(STDERR, "Set REPS_RIZZN_PASSWORD or REPS_RIZZN_PASSWORD_FILE (min " . REPS_DASH_PASSWORD_MIN . " chars).\n");
    exit(2);
}

$fake = ['ops', 'agent', 'maria', 'alex', 'pat', 'mark'];
$keep = ['rizzn', 'jim', 'seven', 'chuck', 'leon', 'chuck-work', 'jim-work'];
$out = ['ok' => true, 'rizzn_id' => null, 'renamed_mark' => false, 'deleted' => [], 'password_set' => false];

$pdo = repsDashDb();
repsDashAppMetaSet('dash.skip_demo_seed', '1');
repsDashAppMetaSet('dash.dev_mode', '0');

$rizzn = repsDashFindUserRawByUsername('rizzn');
$mark = repsDashFindUserRawByUsername('mark');
if ($rizzn === null && $mark !== null) {
    $pdo->prepare(
        "UPDATE users SET username = 'rizzn', display_name = 'Mark Hopkins',
         role = 'admin', is_active = 1, updated_at = datetime('now') WHERE id = ?"
    )->execute([(int) $mark['id']]);
    $out['renamed_mark'] = true;
    $rizzn = repsDashFindUserRawByUsername('rizzn');
}
if ($rizzn === null) {
    $created = repsDashCreateUser([
        'username' => 'rizzn',
        'display_name' => 'Mark Hopkins',
        'email' => 'mark@decisionsciencecorp.com',
        'role' => 'admin',
        'password' => $pass,
    ]);
    if (!($created['ok'] ?? false)) {
        fwrite(STDERR, json_encode(['ok' => false, 'error' => $created['error'] ?? 'create_failed']) . "\n");
        exit(1);
    }
    $rizzn = repsDashFindUserRawByUsername('rizzn');
    $out['password_set'] = true;
}

$rizznId = (int) ($rizzn['id'] ?? 0);
$out['rizzn_id'] = $rizznId;
if ($rizznId <= 0) {
    fwrite(STDERR, json_encode(['ok' => false, 'error' => 'rizzn_missing']) . "\n");
    exit(1);
}

if (!($out['password_set'])) {
    $set = repsDashSetUserPassword($rizznId, $pass);
    if (!($set['ok'] ?? false)) {
        fwrite(STDERR, json_encode(['ok' => false, 'error' => $set['error'] ?? 'password_failed']) . "\n");
        exit(1);
    }
    $out['password_set'] = true;
}

$pdo->prepare(
    "UPDATE users SET role = 'admin', is_active = 1, display_name = 'Mark Hopkins',
     updated_at = datetime('now') WHERE id = ?"
)->execute([$rizznId]);

$pdo->prepare(
    "UPDATE shops SET assigned_sales_rep = 'rizzn', updated_at = datetime('now')
     WHERE assigned_sales_rep = 'mark'"
)->execute();

$lower = array_map('strtolower', $fake);
$placeholders = implode(',', array_fill(0, count($lower), '?'));
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE lower(username) IN ($placeholders)");
$stmt->execute($lower);
$toDelete = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($toDelete as $row) {
    $uid = (int) $row['id'];
    $uname = strtolower((string) $row['username']);
    if ($uname === 'rizzn' || $uid === $rizznId) {
        continue;
    }
    if (in_array($uname, $keep, true)) {
        continue;
    }
    $pdo->prepare('DELETE FROM api_keys WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare(
        'UPDATE operators SET matched_user_id = NULL, matched_at = NULL, matched_by_user_id = NULL,
         updated_at = datetime(\'now\') WHERE matched_user_id = ?'
    )->execute([$uid]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
    $out['deleted'][] = $uname;
}

// Belt: anything still active that is not in the keep list and not rizzn
$extra = $pdo->query(
    "SELECT id, username, role FROM users WHERE is_active = 1"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($extra as $row) {
    $uname = strtolower((string) $row['username']);
    if ($uname === 'rizzn' || in_array($uname, $keep, true)) {
        continue;
    }
    $uid = (int) $row['id'];
    $pdo->prepare('DELETE FROM api_keys WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
    $out['deleted'][] = $uname;
}

$remaining = [];
foreach ($pdo->query('SELECT username, role, is_active FROM users ORDER BY id') as $r) {
    $remaining[] = [
        'username' => $r['username'],
        'role' => $r['role'],
        'active' => (int) $r['is_active'] === 1,
    ];
}
$out['remaining'] = $remaining;

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
exit($out['ok'] ? 0 : 1);
