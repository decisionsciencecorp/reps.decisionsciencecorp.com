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

/**
 * Test-mode Custom connected account that is immediately Transfer-ready
 * (no browser Express onboarding). Refuses non-test keys.
 *
 * @return array{ok: bool, account_id?: string, error?: string, body?: array<string, mixed>}
 */
function repsStripeCreateSandboxReadyRecipient(string $email, string $country = 'US'): array
{
    $key = repsStripeSecretKey();
    if ($key === '' || (!str_starts_with($key, 'sk_test_') && !str_starts_with($key, 'rk_test_'))) {
        return ['ok' => false, 'error' => 'sandbox_recipient_requires_test_key'];
    }
    $email = trim($email) !== '' ? trim($email) : ('sandbox+' . time() . '@example.com');
    $res = repsStripeRequest('POST', '/v1/accounts', [
        'type' => 'custom',
        'country' => $country,
        'email' => $email,
        'capabilities[transfers][requested]' => 'true',
        'business_type' => 'individual',
        'individual[first_name]' => 'Sandbox',
        'individual[last_name]' => 'Operator',
        'individual[email]' => $email,
        'individual[phone]' => '0000000000',
        'individual[dob][day]' => '1',
        'individual[dob][month]' => '1',
        'individual[dob][year]' => '1990',
        'individual[address][line1]' => 'address_full_match',
        'individual[address][city]' => 'San Francisco',
        'individual[address][state]' => 'CA',
        'individual[address][postal_code]' => '94111',
        'individual[address][country]' => 'US',
        'individual[ssn_last_4]' => '0000',
        'individual[id_number]' => '000000000',
        'tos_acceptance[date]' => (string) time(),
        'tos_acceptance[ip]' => '127.0.0.1',
        'business_profile[mcc]' => '5734',
        'business_profile[url]' => 'https://reps.decisionsciencecorp.com',
        'external_account' => 'btok_us_verified',
        'metadata[reps_sandbox]' => '1',
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
 * Attach a sandbox-ready Connect account to a payout_payees row (test only).
 *
 * @param 'user'|'shop'|'operator' $entityType
 * @return array{ok: bool, payee_id?: int, account_id?: string, error?: string}
 */
function repsStripeEnsureSandboxPayee(
    string $entityType,
    int $entityId,
    string $email,
    string $displayName
): array {
    $created = repsStripeCreateSandboxReadyRecipient($email);
    if (!$created['ok']) {
        return ['ok' => false, 'error' => $created['error'] ?? 'create_failed'];
    }
    $accountId = (string) $created['account_id'];
    $pdo = repsDashDb();
    $stmt = $pdo->prepare(
        'SELECT id FROM payout_payees WHERE entity_type = ? AND entity_id = ? LIMIT 1'
    );
    $stmt->execute([$entityType, $entityId]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        $pdo->prepare(
            'UPDATE payout_payees SET stripe_account_id = ?, display_name = ?, email = ?,
             onboarding_status = ?, payouts_enabled = 1, charges_enabled = 1,
             payouts_enabled_at = COALESCE(payouts_enabled_at, datetime(\'now\')),
             updated_at = datetime(\'now\') WHERE id = ?'
        )->execute([$accountId, $displayName, $email, 'ready', (int) $existingId]);
        $payeeId = (int) $existingId;
    } else {
        $pdo->prepare(
            'INSERT INTO payout_payees (
                entity_type, entity_id, display_name, email, stripe_account_id,
                onboarding_status, payouts_enabled, charges_enabled, payouts_enabled_at
             ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, datetime(\'now\'))'
        )->execute([$entityType, $entityId, $displayName, $email, $accountId, 'ready']);
        $payeeId = (int) $pdo->lastInsertId();
    }
    return ['ok' => true, 'payee_id' => $payeeId, 'account_id' => $accountId];
}
