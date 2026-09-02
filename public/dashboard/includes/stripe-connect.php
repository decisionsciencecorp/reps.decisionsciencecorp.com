<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Connect Express / Accounts v2 recipient onboarding for payees.
 */

require_once __DIR__ . '/stripe-client.php';
require_once __DIR__ . '/operators.php';

/**
 * Roles allowed to POST /dashboard/connect/start.php for Stripe Express onboarding.
 *
 * @return list<string>
 */
function repsStripeConnectStartRoles(): array
{
    return ['business_owner', 'individual', 'sales'];
}

/**
 * Whether this seat may start Connect onboarding (role allowlist + Money nav).
 */
function repsStripeUserMayStartConnect(array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, repsStripeConnectStartRoles(), true)) {
        return false;
    }
    if (!function_exists('repsDashNavKeysForRole')) {
        return true;
    }
    return in_array('money', repsDashNavKeysForRole($role), true);
}

/**
 * Who this dashboard user is paid as.
 * Linked worker+affiliate seats share entity_type=user on the affiliate so one
 * Connect KYC covers both the 25% book cut and the 50% capture cut.
 *
 * @return array{
 *   entity_type: 'shop'|'operator'|'user',
 *   entity_id: int,
 *   email: string,
 *   display_name: string,
 *   audience: 'shop'|'solo'|'affiliate'
 * }|null
 */
function repsStripePayeeTargetForUser(array $user): ?array
{
    $role = (string) ($user['role'] ?? '');
    $email = trim((string) ($user['email'] ?? ''));
    $name = trim((string) ($user['display_name'] ?? $user['username'] ?? 'Payee'));
    if ($email === '') {
        $email = 'payee+' . (int) ($user['id'] ?? 0) . '@reps.local';
    }

    if ($role === 'business_owner') {
        $shopId = (int) ($user['shop_id'] ?? 0);
        if ($shopId <= 0) {
            return null;
        }
        return [
            'entity_type' => 'shop',
            'entity_id' => $shopId,
            'email' => $email,
            'display_name' => $name,
            'audience' => 'shop',
        ];
    }

    if ($role === 'sales') {
        $uid = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        return [
            'entity_type' => 'user',
            'entity_id' => $uid,
            'email' => $email,
            'display_name' => $name,
            'audience' => 'affiliate',
        ];
    }

    if ($role === 'individual') {
        $uid = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $linked = (int) ($user['linked_user_id'] ?? 0);
        if ($linked > 0) {
            $aff = repsDashFindUserById($linked);
            $affEmail = $aff ? trim((string) ($aff['email'] ?? '')) : '';
            $affName = $aff ? trim((string) ($aff['display_name'] ?? $name)) : $name;
            return [
                'entity_type' => 'user',
                'entity_id' => $linked,
                'email' => $affEmail !== '' ? $affEmail : $email,
                'display_name' => $affName !== '' ? $affName : $name,
                'audience' => 'solo',
            ];
        }
        $cur = (int) ($user['operator_id'] ?? 0);
        if ($cur > 0) {
            $op = repsOperatorById($cur);
            if ($op) {
                return [
                    'entity_type' => 'operator',
                    'entity_id' => $cur,
                    'email' => $email,
                    'display_name' => $name,
                    'audience' => 'solo',
                ];
            }
        }
        $opId = repsOperatorEnsure('reps-user-' . $uid, $name, $email);
        if ($opId <= 0) {
            return null;
        }
        $pdo = repsDashDb();
        if ($cur !== $opId) {
            $pdo->prepare(
                'UPDATE users SET operator_id = ?, updated_at = datetime(\'now\') WHERE id = ?'
            )->execute([$opId, $uid]);
        }
        return [
            'entity_type' => 'operator',
            'entity_id' => $opId,
            'email' => $email,
            'display_name' => $name,
            'audience' => 'solo',
        ];
    }

    return null;
}

/**
 * Start or resume Express onboarding for the signed-in payee seat.
 *
 * @return array{ok: bool, onboarding_url?: string, payee_id?: int, account_id?: string, payee?: array<string, mixed>, error?: string}
 */
function repsStripeStartOnboardingForUser(array $user): array
{
    $target = repsStripePayeeTargetForUser($user);
    if ($target === null) {
        return ['ok' => false, 'error' => 'no_payee_target_for_role'];
    }
    if (!repsStripeConfigured()) {
        return ['ok' => false, 'error' => 'stripe_not_configured'];
    }
    $ensured = repsStripeEnsurePayee(
        $target['entity_type'],
        $target['entity_id'],
        $target['email'],
        $target['display_name']
    );
    if (!($ensured['ok'] ?? false)) {
        return ['ok' => false, 'error' => $ensured['error'] ?? 'ensure_failed'];
    }
    $payee = repsStripePayeeById((int) ($ensured['payee_id'] ?? 0));
    $out = [
        'ok' => true,
        'payee_id' => (int) ($ensured['payee_id'] ?? 0),
        'account_id' => (string) ($ensured['account_id'] ?? ''),
        'payee' => $payee,
    ];
    if (!empty($ensured['onboarding_url'])) {
        $out['onboarding_url'] = (string) $ensured['onboarding_url'];
    } else {
        $out['ok'] = false;
        $out['error'] = $ensured['error'] ?? 'missing_onboarding_url';
    }
    return $out;
}

/** @return array<string, mixed>|null */
function repsStripePayeeForUser(array $user): ?array
{
    $target = repsStripePayeeTargetForUser($user);
    if ($target === null) {
        return null;
    }
    $stmt = repsDashDb()->prepare(
        'SELECT * FROM payout_payees WHERE entity_type = ? AND entity_id = ? LIMIT 1'
    );
    $stmt->execute([$target['entity_type'], $target['entity_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

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

/**
 * Stripe-Signature header for a payload (test / harness use).
 */
function repsStripeSignWebhookPayload(string $payload, string $secret, ?int $timestamp = null): string
{
    $t = $timestamp ?? time();
    $sig = hash_hmac('sha256', $t . '.' . $payload, $secret);
    return 't=' . $t . ',v1=' . $sig;
}

/**
 * Connect Express sandbox harness: sales (or named) seat → Account Link URL →
 * optional signed account.updated webhook → payee readiness.
 *
 * Default is HTTP-mocked (no network). Pass live_test=true with test-mode keys
 * to hit Stripe test API for a real onboarding URL.
 *
 * @param array{
 *   username?: string,
 *   mock?: bool,
 *   live_test?: bool,
 *   simulate_webhook?: bool,
 *   webhook_secret?: string,
 *   account_id?: string,
 *   onboarding_url?: string
 * } $opts
 * @return array<string, mixed>
 */
function repsStripeSandboxConnectHarness(array $opts = []): array
{
    $username = trim((string) ($opts['username'] ?? 'jim'));
    $liveTest = !empty($opts['live_test']);
    $mock = array_key_exists('mock', $opts) ? (bool) $opts['mock'] : !$liveTest;
    $simulateWebhook = array_key_exists('simulate_webhook', $opts)
        ? (bool) $opts['simulate_webhook']
        : true;
    $webhookSecret = (string) ($opts['webhook_secret'] ?? 'whsec_reps_sandbox_harness');
    $mockAccountId = (string) ($opts['account_id'] ?? ('acct_sandbox_' . substr(sha1($username . microtime(true)), 0, 10)));
    $mockUrl = (string) ($opts['onboarding_url'] ?? ('https://connect.stripe.com/setup/s/' . $mockAccountId));

    $out = [
        'ok' => false,
        'mode' => $liveTest ? 'live_test' : ($mock ? 'mock' : 'unknown'),
        'username' => $username,
    ];

    $user = repsDashFindUserByUsername($username);
    if ($user === null) {
        $out['error'] = 'user_not_found';
        return $out;
    }
    if (!repsStripeUserMayStartConnect($user)) {
        $out['error'] = 'user_may_not_start_connect';
        $out['role'] = (string) ($user['role'] ?? '');
        return $out;
    }
    $out['user_id'] = (int) ($user['id'] ?? 0);
    $out['role'] = (string) ($user['role'] ?? '');

    if ($liveTest) {
        $key = repsStripeSecretKey();
        if ($key === '' || (!str_starts_with($key, 'sk_test_') && !str_starts_with($key, 'rk_test_'))) {
            $out['error'] = 'live_test_requires_test_key';
            return $out;
        }
        // Explicit mock=true still allowed (unit tests); default live_test turns mock off.
        if (!array_key_exists('mock', $opts)) {
            $mock = false;
        }
        $out['mode'] = 'live_test';
    }

    if ($mock) {
        putenv('STRIPE_SECRET_KEY=sk_test_sandbox_harness');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_sandbox_harness';
        repsStripeSetHttpMock(static function (string $method, string $path) use ($mockAccountId, $mockUrl) {
            if ($path === '/v1/accounts') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => ['id' => $mockAccountId, 'type' => 'express'],
                    'raw' => '{}',
                ];
            }
            if ($path === '/v1/account_links') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => ['url' => $mockUrl, 'object' => 'account_link'],
                    'raw' => '{}',
                ];
            }
            return [
                'ok' => false,
                'status' => 404,
                'body' => [],
                'raw' => '',
                'error' => 'unexpected ' . $method . ' ' . $path,
            ];
        });
    }

    try {
        $started = repsStripeStartOnboardingForUser($user);
        $out['onboarding'] = [
            'ok' => (bool) ($started['ok'] ?? false),
            'payee_id' => (int) ($started['payee_id'] ?? 0),
            'account_id' => (string) ($started['account_id'] ?? ''),
            'onboarding_url' => (string) ($started['onboarding_url'] ?? ''),
            'error' => $started['error'] ?? null,
        ];
        if (!($started['ok'] ?? false) || empty($started['onboarding_url'])) {
            $out['error'] = $started['error'] ?? 'onboarding_failed';
            return $out;
        }

        $payeeId = (int) ($started['payee_id'] ?? 0);
        $accountId = (string) ($started['account_id'] ?? '');
        $base = rtrim((string) (getenv('REPS_PUBLIC_BASE') ?: 'https://reps.decisionsciencecorp.com'), '/');
        $out['return_url'] = $base . '/dashboard/connect/return.php?payee_id=' . $payeeId;
        $out['refresh_url'] = $base . '/dashboard/connect/refresh.php?payee_id=' . $payeeId;

        if ($simulateWebhook && $accountId !== '') {
            $eventId = 'evt_sandbox_' . substr(sha1($accountId . microtime(true)), 0, 12);
            $payload = json_encode([
                'id' => $eventId,
                'object' => 'event',
                'type' => 'account.updated',
                'livemode' => false,
                'data' => [
                    'object' => [
                        'id' => $accountId,
                        'object' => 'account',
                        'payouts_enabled' => true,
                        'charges_enabled' => true,
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES);
            $header = repsStripeSignWebhookPayload((string) $payload, $webhookSecret);
            $event = repsStripeVerifyWebhook((string) $payload, $header, $webhookSecret);
            if ($event === null) {
                $out['error'] = 'webhook_verify_failed';
                return $out;
            }
            repsStripeMarkPayeeFromAccountObject($event['data']['object'] ?? []);
            $out['webhook'] = [
                'ok' => true,
                'event_id' => $eventId,
                'type' => 'account.updated',
                'signed' => true,
            ];
        }

        $payee = $payeeId > 0 ? repsStripePayeeById($payeeId) : null;
        $out['payee'] = $payee ? [
            'id' => (int) ($payee['id'] ?? 0),
            'entity_type' => (string) ($payee['entity_type'] ?? ''),
            'entity_id' => (int) ($payee['entity_id'] ?? 0),
            'stripe_account_id' => (string) ($payee['stripe_account_id'] ?? ''),
            'onboarding_status' => (string) ($payee['onboarding_status'] ?? ''),
            'payouts_enabled' => (int) ($payee['payouts_enabled'] ?? 0),
        ] : null;
        $out['ok'] = true;
        $out['onboarding_url'] = (string) ($started['onboarding_url'] ?? '');
        return $out;
    } finally {
        if ($mock) {
            repsStripeClearHttpMock();
            putenv('STRIPE_SECRET_KEY');
            unset($_ENV['STRIPE_SECRET_KEY']);
        }
    }
}
