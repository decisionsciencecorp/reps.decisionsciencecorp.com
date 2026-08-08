<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Disbursement batches — platform Transfers to Connect payees.
 */

require_once __DIR__ . '/stripe-client.php';
require_once __DIR__ . '/stripe-connect.php';
require_once __DIR__ . '/ledger.php';

/**
 * Resolve destination Connect account for a ledger line.
 */
function repsDisburseResolveDestination(array $line): ?string
{
    $pdo = repsDashDb();
    $bucket = (string) ($line['bucket'] ?? '');
    if ($bucket === 'affiliate') {
        $uid = (int) ($line['affiliate_user_id'] ?? 0);
        if ($uid > 0) {
            $stmt = $pdo->prepare(
                "SELECT stripe_account_id FROM payout_payees WHERE entity_type = 'user' AND entity_id = ? AND payouts_enabled = 1 LIMIT 1"
            );
            $stmt->execute([$uid]);
            $acct = $stmt->fetchColumn();
            return $acct ? (string) $acct : null;
        }
        $uname = trim((string) ($line['affiliate_username'] ?? ''));
        if ($uname !== '') {
            $u = $pdo->prepare('SELECT id FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
            $u->execute([$uname]);
            $id = $u->fetchColumn();
            if ($id) {
                $stmt = $pdo->prepare(
                    "SELECT stripe_account_id FROM payout_payees WHERE entity_type = 'user' AND entity_id = ? AND payouts_enabled = 1 LIMIT 1"
                );
                $stmt->execute([(int) $id]);
                $acct = $stmt->fetchColumn();
                return $acct ? (string) $acct : null;
            }
        }
        return null;
    }
    if ($bucket === 'capture') {
        $payee = (string) ($line['capture_payee'] ?? 'shop');
        if ($payee === 'shop') {
            $shopId = (int) ($line['shop_id'] ?? 0);
            if ($shopId <= 0) {
                return null;
            }
            $stmt = $pdo->prepare(
                "SELECT stripe_account_id FROM payout_payees WHERE entity_type = 'shop' AND entity_id = ? AND payouts_enabled = 1 LIMIT 1"
            );
            $stmt->execute([$shopId]);
            $acct = $stmt->fetchColumn();
            return $acct ? (string) $acct : null;
        }
        $opId = (int) ($line['operator_id'] ?? 0);
        if ($opId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare(
            "SELECT stripe_account_id FROM payout_payees WHERE entity_type = 'operator' AND entity_id = ? AND payouts_enabled = 1 LIMIT 1"
        );
        $stmt->execute([$opId]);
        $acct = $stmt->fetchColumn();
        return $acct ? (string) $acct : null;
    }
    return null;
}

/**
 * Create a batch and attempt Transfers for pending lines.
 *
 * @param array{
 *   hour_key_prefix?: string,
 *   settlement_id?: int,
 *   line_ids?: list<int>
 * } $filters
 * @return array{
 *   ok: bool,
 *   batch_id?: int,
 *   transferred?: int,
 *   skipped?: int,
 *   failed?: int,
 *   error?: string,
 *   dry_run?: bool
 * }
 */
function repsDisburseRunBatch(string $label = '', bool $dryRun = false, array $filters = []): array
{
    $pdo = repsDashDb();
    $lines = repsLedgerListPending(200);
    $prefix = trim((string) ($filters['hour_key_prefix'] ?? ''));
    $settlementId = isset($filters['settlement_id']) ? (int) $filters['settlement_id'] : 0;
    /** @var list<int> $onlyIds */
    $onlyIds = [];
    if (!empty($filters['line_ids']) && is_array($filters['line_ids'])) {
        foreach ($filters['line_ids'] as $lid) {
            $onlyIds[] = (int) $lid;
        }
    }
    if ($prefix !== '' || $settlementId > 0 || $onlyIds !== []) {
        $lines = array_values(array_filter($lines, static function (array $line) use ($prefix, $settlementId, $onlyIds): bool {
            if ($onlyIds !== [] && !in_array((int) ($line['id'] ?? 0), $onlyIds, true)) {
                return false;
            }
            if ($settlementId > 0 && (int) ($line['settlement_id'] ?? 0) !== $settlementId) {
                return false;
            }
            if ($prefix !== '' && !str_starts_with((string) ($line['hour_key'] ?? ''), $prefix)) {
                return false;
            }
            return true;
        }));
    }
    if ($lines === []) {
        return ['ok' => true, 'transferred' => 0, 'skipped' => 0, 'failed' => 0];
    }

    $pdo->prepare(
        'INSERT INTO disbursement_batches (label, status, line_count) VALUES (?, ?, ?)'
    )->execute([$label !== '' ? $label : ('batch_' . gmdate('Ymd_His')), 'running', count($lines)]);
    $batchId = (int) $pdo->lastInsertId();
    $transferGroup = 'reps_batch_' . $batchId;

    $transferred = $skipped = $failed = 0;
    $configured = repsStripeConfigured();

    foreach ($lines as $line) {
        $lineId = (int) $line['id'];
        $amount = (int) $line['amount_cents'];
        if ($amount <= 0) {
            $skipped++;
            continue;
        }
        $dest = repsDisburseResolveDestination($line);
        if ($dest === null) {
            $pdo->prepare(
                'UPDATE ledger_lines SET status = ?, updated_at = datetime(\'now\') WHERE id = ?'
            )->execute(['owed', $lineId]);
            $skipped++;
            continue;
        }

        if ($dryRun || !$configured) {
            $skipped++;
            continue;
        }

        $idem = 'reps-transfer-' . $lineId;
        $res = repsStripeRequest('POST', '/v1/transfers', [
            'amount' => (string) $amount,
            'currency' => 'usd',
            'destination' => $dest,
            'transfer_group' => $transferGroup,
            'description' => 'Reps ' . $line['bucket'] . ' hour ' . $line['hour_key'],
            'metadata[reps_ledger_line_id]' => (string) $lineId,
            'metadata[reps_batch_id]' => (string) $batchId,
            'metadata[bucket]' => (string) $line['bucket'],
        ], $idem);

        if ($res['ok']) {
            $trId = (string) ($res['body']['id'] ?? '');
            $pdo->prepare(
                'UPDATE ledger_lines SET status = ?, stripe_transfer_id = ?, disbursement_batch_id = ?,
                 transferred_at = datetime(\'now\'), updated_at = datetime(\'now\') WHERE id = ?'
            )->execute(['transferred', $trId, $batchId, $lineId]);
            $pdo->prepare(
                'INSERT INTO disbursement_transfers (batch_id, ledger_line_id, stripe_transfer_id, amount_cents, destination, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$batchId, $lineId, $trId, $amount, $dest, 'created']);
            $transferred++;
        } else {
            $pdo->prepare(
                'INSERT INTO disbursement_transfers (batch_id, ledger_line_id, stripe_transfer_id, amount_cents, destination, status, error)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $batchId,
                $lineId,
                '',
                $amount,
                $dest,
                'failed',
                (string) ($res['error'] ?? 'transfer_failed'),
            ]);
            $failed++;
        }
    }

    $status = $failed > 0 && $transferred === 0 ? 'failed' : 'done';
    $pdo->prepare(
        'UPDATE disbursement_batches SET status = ?, transferred_count = ?, skipped_count = ?, failed_count = ?,
         finished_at = datetime(\'now\') WHERE id = ?'
    )->execute([$status, $transferred, $skipped, $failed, $batchId]);

    return [
        'ok' => true,
        'batch_id' => $batchId,
        'transferred' => $transferred,
        'skipped' => $skipped,
        'failed' => $failed,
        'dry_run' => $dryRun || !$configured,
    ];
}

function repsDisburseMarkTransferFromWebhook(string $transferId, string $status): void
{
    if ($transferId === '') {
        return;
    }
    $pdo = repsDashDb();
    $pdo->prepare(
        'UPDATE disbursement_transfers SET status = ? WHERE stripe_transfer_id = ?'
    )->execute([$status, $transferId]);
    if ($status === 'reversed') {
        $pdo->prepare(
            "UPDATE ledger_lines SET status = 'owed', updated_at = datetime('now')
             WHERE stripe_transfer_id = ?"
        )->execute([$transferId]);
    }
}

/** @return list<array<string, mixed>> */
function repsDisburseListBatches(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = repsDashDb()->query(
        'SELECT * FROM disbursement_batches ORDER BY id DESC LIMIT ' . (int) $limit
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}
