#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * End-to-end Stripe *test mode* payout smoke — no live KYC required.
 *
 * 1. Top up platform available balance (tok_bypassPending)
 * 2. Create sandbox-ready Custom Connect recipient for an operator
 * 3. Post one sandbox accepted-hour ledger (capture → operator)
 * 4. Run filtered disbursement batch → real Transfer
 *
 * Usage:
 *   REPS_DASH_DB_PATH=... php tools/sandbox-payout-smoke.php
 *   php tools/sandbox-payout-smoke.php --amount-cents=1000 --topup-cents=5000
 */

$root = dirname(__DIR__);
require_once $root . '/public/dashboard/includes/bootstrap.php';

$opts = getopt('', ['amount-cents:', 'topup-cents:', 'dry-run::']);
$amountCents = isset($opts['amount-cents']) ? (int) $opts['amount-cents'] : 1000;
$topupCents = isset($opts['topup-cents']) ? (int) $opts['topup-cents'] : 20000;
$dryRaw = $opts['dry-run'] ?? null;
$dry = $dryRaw !== null && ($dryRaw === false || $dryRaw === '' || (string) $dryRaw === '1');

if (!repsStripeConfigured()) {
    fwrite(STDERR, "Stripe not configured (app_meta / pass file)\n");
    exit(2);
}

$mode = repsStripeMetaGet(REPS_STRIPE_META_KEYS['mode']);
$key = repsStripeSecretKey();
if ($mode === 'live' || str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_')) {
    fwrite(STDERR, "Refusing: live Stripe keys — sandbox smoke is test-mode only\n");
    exit(2);
}

repsOperatorsEnsureSchema();

$shiftUserId = 'sandbox-op-' . gmdate('Ymd');
$opId = repsOperatorEnsure($shiftUserId, 'Sandbox Operator', 'sandbox.operator@example.com');
$hourKey = 'sandbox_smoke_' . gmdate('Ymd_His');

$out = [
    'mode' => 'test',
    'operator_id' => $opId,
    'hour_key' => $hourKey,
    'dry_run' => $dry,
];

$bal = repsStripeBalance();
$out['balance_before'] = [
    'available_cents' => $bal['available_cents'] ?? 0,
    'pending_cents' => $bal['pending_cents'] ?? 0,
];

if (!$dry && (int) ($bal['available_cents'] ?? 0) < $amountCents) {
    $top = repsStripeSandboxTopUpAvailable($topupCents);
    $out['topup'] = $top;
    if (!($top['ok'] ?? false)) {
        fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}

$payee = repsStripeEnsureSandboxPayee(
    'operator',
    $opId,
    'sandbox.operator@example.com',
    'Sandbox Operator'
);
$out['payee'] = $payee;
if (!($payee['ok'] ?? false)) {
    fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

$hours = max(0.01, round($amountCents / 1000, 2)); // capture is 50% of $20/h → $10/h
$ledger = repsLedgerPostAcceptedHour([
    'hour_key' => $hourKey,
    'hours' => $hours,
    'operator_id' => $opId,
    'has_shop' => false,
    'has_affiliate' => false,
    'accepted_at' => gmdate('Y-m-d H:i:s'),
]);
$out['ledger'] = $ledger;
if (!($ledger['ok'] ?? false)) {
    fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

$batch = repsDisburseRunBatch('sandbox_smoke_' . gmdate('Ymd_His'), $dry, [
    'hour_key_prefix' => 'sandbox_smoke_',
]);
$out['batch'] = $batch;

$balAfter = repsStripeBalance();
$out['balance_after'] = [
    'available_cents' => $balAfter['available_cents'] ?? 0,
    'pending_cents' => $balAfter['pending_cents'] ?? 0,
];

$pdo = repsDashDb();
$lines = $pdo->prepare(
    'SELECT id, bucket, amount_cents, status, stripe_transfer_id, operator_id
     FROM ledger_lines WHERE hour_key = ? ORDER BY id'
);
$lines->execute([$hourKey]);
$out['ledger_lines'] = $lines->fetchAll(PDO::FETCH_ASSOC) ?: [];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$ok = ($payee['ok'] ?? false)
    && ($ledger['ok'] ?? false)
    && ($batch['ok'] ?? false)
    && ($dry || (int) ($batch['transferred'] ?? 0) > 0);
exit($ok ? 0 : 1);
