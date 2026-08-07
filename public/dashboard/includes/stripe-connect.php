<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Connect Express / Accounts v2 recipient onboarding for payees.
 */

require_once __DIR__ . '/stripe-client.php';

/**
 * Create Express-style recipient (v1 fallback — reliable without preview headers).
 *
 * @return array{ok: bool, account_id?: string, error?: string, body?: array<string, mixed>}
 */
function repsStripeCreateExpressRecipient(string $email, string $country = 'US'): array
{
    $res = repsStripeRequest('POST', '/v1/accounts', [
        'type' => 'express',
        'country' => $country,
        'email' => $email,
        'capabilities[transfers][requested]' => 'true',
        'business_type' => 'individual',
        'metadata[reps]' => '1',
    ]);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] ?? 'create_failed', 'body' => $res['body']];
    }
    $id = (string) ($res['body']['id'] ?? '');
    if ($id === '') {
        return ['ok' => false, 'error' => 'missing_account_id', 'body' => $res['body']];
    }
    return ['ok' => true, 'account_id' => $id, 'body' => $res['body']];
}

/**
 * @return array{ok: bool, url?: string, error?: string}
 */
function repsStripeAccountOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl): array
{
    $res = repsStripeRequest('POST', '/v1/account_links', [
        'account' => $accountId,
        'refresh_url' => $refreshUrl,
        'return_url' => $returnUrl,
        'type' => 'account_onboarding',
    ]);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] ?? 'link_failed'];
    }
    $url = (string) ($res['body']['url'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'error' => 'missing_url'];
    }
    return ['ok' => true, 'url' => $url];
}

/**
 * Upsert payee row and ensure Connect account + onboarding URL.
 *
 * @param 'user'|'shop'|'operator' $entityType
 * @return array{ok: bool, payee_id?: int, account_id?: string, onboarding_url?: string, error?: string}
 */
function repsStripeEnsurePayee(string $entityType, int $entityId, string $email, string $displayName): array
{
    $pdo = repsDashDb();
    $stmt = $pdo->prepare(
        'SELECT * FROM payout_payees WHERE entity_type = ? AND entity_id = ? LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $accountId = $row ? (string) ($row['stripe_account_id'] ?? '') : '';
    if ($accountId === '') {
        if (!repsStripeConfigured()) {
            return ['ok' => false, 'error' => 'stripe_not_configured'];
        }
        $created = repsStripeCreateExpressRecipient($email);
        if (!$created['ok']) {
            return ['ok' => false, 'error' => $created['error'] ?? 'create_failed'];
        }
        $accountId = (string) $created['account_id'];
        if ($row) {
            $pdo->prepare(
                'UPDATE payout_payees SET stripe_account_id = ?, display_name = ?, email = ?,
                 onboarding_status = ?, updated_at = datetime(\'now\') WHERE id = ?'
            )->execute([$accountId, $displayName, $email, 'pending', (int) $row['id']]);
            $payeeId = (int) $row['id'];
        } else {
            $pdo->prepare(
                'INSERT INTO payout_payees (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$entityType, $entityId, $displayName, $email, $accountId, 'pending']);
            $payeeId = (int) $pdo->lastInsertId();
        }
    } else {
        $payeeId = (int) $row['id'];
        $pdo->prepare(
            'UPDATE payout_payees SET display_name = ?, email = ?, updated_at = datetime(\'now\') WHERE id = ?'
        )->execute([$displayName, $email, $payeeId]);
    }

    $base = rtrim((string) (getenv('REPS_PUBLIC_BASE') ?: 'https://reps.decisionsciencecorp.com'), '/');
    $refresh = $base . '/dashboard/connect/refresh.php?payee_id=' . $payeeId;
    $return = $base . '/dashboard/connect/return.php?payee_id=' . $payeeId;
    $link = repsStripeAccountOnboardingLink($accountId, $refresh, $return);
    if (!$link['ok']) {
        return [
            'ok' => true,
            'payee_id' => $payeeId,
            'account_id' => $accountId,
            'error' => $link['error'] ?? 'link_failed',
        ];
    }

    return [
        'ok' => true,
        'payee_id' => $payeeId,
        'account_id' => $accountId,
        'onboarding_url' => $link['url'],
    ];
}

/** @return array<string, mixed>|null */
function repsStripePayeeById(int $payeeId): ?array
{
    $stmt = repsDashDb()->prepare('SELECT * FROM payout_payees WHERE id = ? LIMIT 1');
    $stmt->execute([$payeeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function repsStripeMarkPayeeFromAccountObject(array $account): void
{
    $accountId = (string) ($account['id'] ?? '');
    if ($accountId === '') {
        return;
    }
    $payouts = !empty($account['payouts_enabled']) ? 1 : 0;
    $charges = !empty($account['charges_enabled']) ? 1 : 0;
    $status = $payouts ? 'ready' : 'pending';
    $pdo = repsDashDb();
    $pdo->prepare(
        'UPDATE payout_payees SET payouts_enabled = ?, charges_enabled = ?, onboarding_status = ?,
         updated_at = datetime(\'now\'),
         payouts_enabled_at = CASE WHEN ? = 1 THEN COALESCE(payouts_enabled_at, datetime(\'now\')) ELSE payouts_enabled_at END
         WHERE stripe_account_id = ?'
    )->execute([$payouts, $charges, $status, $payouts, $accountId]);
}
