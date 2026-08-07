<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Shift settlement discovery + reconcile against Stripe platform balance.
 *
 * LOCKED (Mark 2026-08-07) — Doc #1036:
 *   Partner cash = America/Chicago Mon–Sun accepted_hours × $20, paid next Monday.
 *   No Shift remittance API; match hours-feed week to Monday deposit (bank/Stripe).
 *   Per person = that worker's accepted hours × $20 in the week.
 *   Late Sunday accepts may miss Monday batch → carry forward.
 *   Contract #707 14-day/quality tiers = legal fallback only.
 *
 * Ops import / Stripe balance webhooks book settlement_events; then disburse.
 */

require_once __DIR__ . '/stripe-client.php';

/**
 * Record a settlement event (idempotent on source_key).
 *
 * @param array<string, mixed> $meta
 * @return array{ok: bool, id?: int, created?: bool, error?: string}
 */
function repsSettlementRecord(
    string $source,
    string $sourceKey,
    int $amountCents,
    string $currency = 'usd',
    string $status = 'recorded',
    array $meta = []
): array {
    if ($sourceKey === '' || $amountCents < 0) {
        return ['ok' => false, 'error' => 'invalid_args'];
    }
    $pdo = repsDashDb();
    $existing = $pdo->prepare('SELECT id FROM settlement_events WHERE source = ? AND source_key = ? LIMIT 1');
    $existing->execute([$source, $sourceKey]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return ['ok' => true, 'id' => (int) $row['id'], 'created' => false];
    }
    $pdo->prepare(
        'INSERT INTO settlement_events (source, source_key, amount_cents, currency, status, meta_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $source,
        $sourceKey,
        $amountCents,
        strtolower($currency),
        $status,
        $meta === [] ? '{}' : (string) json_encode($meta, JSON_UNESCAPED_SLASHES),
    ]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'created' => true];
}

/**
 * Snapshot platform Stripe balance into settlement_events (source=stripe_balance).
 *
 * @return array{ok: bool, id?: int, available_cents?: int, pending_cents?: int, error?: string}
 */
function repsSettlementReconcileStripeBalance(?string $label = null): array
{
    $bal = repsStripeBalance();
    if (!$bal['ok']) {
        // Soft path when keys missing: still record a discovery stub for ops.
        if (($bal['error'] ?? '') === 'missing_secret_key' || !repsStripeConfigured()) {
            $key = 'discovery_' . gmdate('Y-m-d');
            $rec = repsSettlementRecord(
                'shift_discovery',
                $key,
                0,
                'usd',
                'pending_api',
                [
                    'note' => 'Shift settlement API not wired; Stripe keys empty or balance call failed.',
                    'stripe_error' => $bal['error'] ?? 'not_configured',
                    'label' => $label,
                ]
            );
            return [
                'ok' => (bool) ($rec['ok'] ?? false),
                'id' => $rec['id'] ?? null,
                'available_cents' => 0,
                'pending_cents' => 0,
                'error' => $bal['error'] ?? 'not_configured',
            ];
        }
        return ['ok' => false, 'error' => $bal['error'] ?? 'balance_failed'];
    }

    $key = 'bal_' . gmdate('Y-m-d\TH:i:s\Z') . '_' . $bal['available_cents'];
    $rec = repsSettlementRecord(
        'stripe_balance',
        $key,
        (int) $bal['available_cents'],
        'usd',
        'available',
        [
            'pending_cents' => $bal['pending_cents'],
            'label' => $label,
        ]
    );
    return [
        'ok' => (bool) ($rec['ok'] ?? false),
        'id' => $rec['id'] ?? null,
        'available_cents' => (int) $bal['available_cents'],
        'pending_cents' => (int) $bal['pending_cents'],
    ];
}

/**
 * Import a Shift (or bank) settlement amount once known.
 *
 * @return array{ok: bool, id?: int, created?: bool, error?: string}
 */
function repsSettlementImportShift(string $weekOrId, int $amountCents, array $meta = []): array
{
    return repsSettlementRecord(
        'shift',
        $weekOrId,
        $amountCents,
        'usd',
        'recorded',
        $meta
    );
}

/**
 * Mark settlement reconciled when ledger coverage matches (ops check).
 */
function repsSettlementMarkReconciled(int $settlementId, string $note = ''): bool
{
    $pdo = repsDashDb();
    // Keep meta as opaque JSON string append via replace when note set.
    if ($note !== '') {
        $pdo->prepare(
            'UPDATE settlement_events SET status = ?, meta_json = ?, updated_at = datetime(\'now\') WHERE id = ?'
        )->execute(['reconciled', json_encode(['reconcile_note' => $note]), $settlementId]);
    } else {
        $pdo->prepare(
            'UPDATE settlement_events SET status = ?, updated_at = datetime(\'now\') WHERE id = ?'
        )->execute(['reconciled', $settlementId]);
    }
    return true;
}

/** @return list<array<string, mixed>> */
function repsSettlementList(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = repsDashDb()->query(
        'SELECT * FROM settlement_events ORDER BY id DESC LIMIT ' . (int) $limit
    );
    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/**
 * Compare open ledger owed (affiliate+capture pending) vs Stripe available.
 *
 * @return array{ledger_owed_cents: int, stripe_available_cents: int|null, gap_cents: int|null, stripe_ok: bool}
 */
function repsSettlementCoverage(): array
{
    $pdo = repsDashDb();
    $owed = (int) $pdo->query(
        "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger_lines
         WHERE bucket IN ('affiliate','capture') AND status IN ('pending','owed')"
    )->fetchColumn();

    $stripeOk = false;
    $available = null;
    if (repsStripeConfigured()) {
        $bal = repsStripeBalance();
        $stripeOk = (bool) ($bal['ok'] ?? false);
        if ($stripeOk) {
            $available = (int) $bal['available_cents'];
        }
    }

    return [
        'ledger_owed_cents' => $owed,
        'stripe_available_cents' => $available,
        'gap_cents' => $available === null ? null : ($available - $owed),
        'stripe_ok' => $stripeOk,
    ];
}
