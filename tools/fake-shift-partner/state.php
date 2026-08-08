<?php
declare(strict_types=1);

/**
 * In-memory / file-backed state for fake Shift Partner.
 */

function fakeShiftStatePath(): string
{
    $p = getenv('FAKE_SHIFT_STATE');
    if (is_string($p) && $p !== '') {
        return $p;
    }
    return sys_get_temp_dir() . '/fake-shift-partner-state.json';
}

/** @return array<string, mixed> */
function fakeShiftLoadState(): array
{
    $path = fakeShiftStatePath();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return fakeShiftDefaultState();
}

/** @return array<string, mixed> */
function fakeShiftDefaultState(): array
{
    return [
        'partnerCode' => 'C6N9T7',
        'members' => [
            [
                'id' => 'mem-mark',
                'name' => 'Mark Hopkins',
                'phone' => '+14694506344',
                'userId' => 'shift-user-mark',
                'remindersOptIn' => true,
                'invitedAt' => '2026-07-01T00:00:00Z',
                'acceptedHours' => 4.0,
                'rejectedHours' => 1.0,
                'totalHours' => 5.0,
                'sessionsCount' => 5,
                'acceptanceRate' => 0.8,
                'onboardedAt' => null,
                'language' => 'en',
            ],
        ],
        'sessions' => [
            [
                'session_id' => 'fake_sess_1',
                'user_id' => 'shift-user-mark',
                'first_name' => 'Mark',
                'last_name' => 'Hopkins',
                'partner_code' => 'C6N9T7',
                'status' => 'completed',
                'created_at' => '2026-08-05T15:00:00-05:00',
                'completed_at' => '2026-08-05T16:00:00-05:00',
                'duration_hours' => 1.0,
                'accepted_hours' => 0.8,
                'rejection_reason' => '',
            ],
            [
                'session_id' => 'fake_sess_2',
                'user_id' => 'shift-user-mark',
                'first_name' => 'Mark',
                'last_name' => 'Hopkins',
                'partner_code' => 'C6N9T7',
                'status' => 'rejected',
                'created_at' => '2026-08-06T10:00:00-05:00',
                'completed_at' => '2026-08-06T10:30:00-05:00',
                'duration_hours' => 0.5,
                'accepted_hours' => 0.0,
                'rejection_reason' => 'REJECTED_HEALTH_TOO_LOW',
            ],
        ],
        'bannedUserIds' => [],
        'otp_codes' => [],
        'account' => [
            'split' => 0,
            'sms_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'timezone' => 'America/Chicago',
            'active_view' => 'business_owner',
            'profile' => ['businessName' => 'Decision Science Corp'],
        ],
        'next_member' => 1,
    ];
}

/** @param array<string, mixed> $state */
function fakeShiftSaveState(array $state): void
{
    file_put_contents(fakeShiftStatePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * @param array<string, mixed>|null $body
 * @return array{status: int, body: array<string, mixed>}
 */
function fakeShiftHandle(string $method, string $path, ?array $body = null): array
{
    $method = strtoupper($method);
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    $state = fakeShiftLoadState();
    $body = $body ?? [];

    if ($method === 'GET' && $path === '/api/dashboard/hours-feed') {
        return [
            'status' => 200,
            'body' => [
                'partnerCode' => $state['partnerCode'],
                'sessions' => $state['sessions'],
                'bannedUserIds' => $state['bannedUserIds'],
            ],
        ];
    }

    if ($method === 'GET' && $path === '/api/dashboard/workers') {
        $workers = [];
        foreach ($state['members'] as $m) {
            if (!empty($m['userId'])) {
                $workers[] = ['userId' => $m['userId'], 'name' => $m['name']];
            }
        }
        return ['status' => 200, 'body' => ['workers' => $workers]];
    }

    if ($method === 'GET' && $path === '/api/team/members') {
        return ['status' => 200, 'body' => ['members' => $state['members']]];
    }

    if ($method === 'POST' && $path === '/api/team/members') {
        $name = trim((string) ($body['name'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            return ['status' => 400, 'body' => ['error' => 'name_and_phone_required']];
        }
        $n = (int) ($state['next_member'] ?? 1);
        $id = 'mem-fake-' . $n;
        $state['next_member'] = $n + 1;
        $state['members'][] = [
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'userId' => null,
            'remindersOptIn' => false,
            'invitedAt' => gmdate('c'),
            'acceptedHours' => 0,
            'rejectedHours' => 0,
            'totalHours' => 0,
            'sessionsCount' => 0,
            'acceptanceRate' => 0,
            'onboardedAt' => null,
            'language' => 'en',
            'smsSent' => true,
        ];
        fakeShiftSaveState($state);
        return ['status' => 200, 'body' => ['ok' => true, 'id' => $id, 'smsSent' => true]];
    }

    if ($method === 'DELETE' && preg_match('#^/api/team/members/([^/]+)$#', $path, $m)) {
        $id = $m[1];
        $before = count($state['members']);
        $state['members'] = array_values(array_filter(
            $state['members'],
            static fn($row) => (string) ($row['id'] ?? '') !== $id
        ));
        fakeShiftSaveState($state);
        if (count($state['members']) === $before) {
            return ['status' => 404, 'body' => ['error' => 'not_found']];
        }
        return ['status' => 200, 'body' => ['ok' => true, 'deleted' => $id]];
    }

    $accountPosts = [
        '/api/account/payout-split' => static function (array &$state, array $body): array {
            $state['account']['split'] = (float) ($body['split'] ?? 0);
            return ['ok' => true, 'split' => $state['account']['split']];
        },
        '/api/account/sms-schedule' => static function (array &$state, array $body): array {
            if (isset($body['days'])) {
                $state['account']['sms_days'] = $body['days'];
            }
            if (isset($body['timezone'])) {
                $state['account']['timezone'] = $body['timezone'];
            }
            return ['ok' => true];
        },
        '/api/account/bank-info' => static function (array &$state, array $body): array {
            $state['account']['bank'] = [
                'holder' => (string) ($body['holder'] ?? ''),
                'type' => (string) ($body['type'] ?? ''),
                'routing' => (string) ($body['routing'] ?? ''),
                'bankName' => (string) ($body['bankName'] ?? ''),
                // never echo full account number back
                'accountLast4' => substr((string) ($body['accountNumber'] ?? ''), -4),
            ];
            return ['ok' => true];
        },
        '/api/account/profile' => static function (array &$state, array $body): array {
            if (trim((string) ($body['businessName'] ?? $body['name'] ?? '')) === '' && empty($state['account']['profile']['businessName'])) {
                return ['_status' => 400, 'error' => 'business_name_required'];
            }
            $state['account']['profile'] = array_merge($state['account']['profile'] ?? [], $body);
            return ['ok' => true];
        },
        '/api/account/legal-address' => static function (array &$state, array $body): array {
            $state['account']['legal_address'] = $body;
            return ['ok' => true];
        },
        '/api/account/shipping-address' => static function (array &$state, array $body): array {
            $state['account']['shipping_address'] = $body;
            return ['ok' => true];
        },
        '/api/account/active-view' => static function (array &$state, array $body): array {
            $state['account']['active_view'] = (string) ($body['view'] ?? 'business_owner');
            return ['ok' => true, 'view' => $state['account']['active_view']];
        },
        '/api/account/reminders' => static function (array &$state, array $body): array {
            $state['account']['reminders'] = $body;
            return ['ok' => true];
        },
    ];

    if ($method === 'POST' && isset($accountPosts[$path])) {
        $fn = $accountPosts[$path];
        $out = $fn($state, $body);
        if (isset($out['_status'])) {
            $status = (int) $out['_status'];
            unset($out['_status']);
            return ['status' => $status, 'body' => $out];
        }
        fakeShiftSaveState($state);
        return ['status' => 200, 'body' => $out];
    }

    if ($method === 'POST' && $path === '/api/auth/login/request-code') {
        $phone = trim((string) ($body['phone'] ?? ''));
        if ($phone === '') {
            return ['status' => 400, 'body' => ['error' => 'phone_required']];
        }
        $code = '000000';
        $state['otp_codes'][$phone] = $code;
        fakeShiftSaveState($state);
        return ['status' => 200, 'body' => ['ok' => true, 'fake_code' => $code]];
    }

    if ($method === 'POST' && $path === '/api/auth/login/verify-code') {
        $phone = trim((string) ($body['phone'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));
        $expect = (string) ($state['otp_codes'][$phone] ?? '');
        if ($expect === '' || $code !== $expect) {
            return ['status' => 401, 'body' => ['error' => 'invalid_code']];
        }
        return ['status' => 200, 'body' => ['ok' => true, 'access_token' => 'fake_access', 'refresh_token' => 'fake_refresh']];
    }

    if ($method === 'POST' && $path === '/api/auth/logout') {
        return ['status' => 200, 'body' => ['ok' => true]];
    }

    if ($method === 'POST' && $path === '/api/support/chat') {
        return ['status' => 200, 'body' => ['ok' => true, 'queued' => true]];
    }

    if ($method === 'PATCH' && preg_match('#^/api/account/referral-links/#', $path)) {
        return ['status' => 401, 'body' => ['error' => 'unauthorized']];
    }

    if ($path === '/api/admin/users' || $path === '/api/admin/impersonate') {
        return ['status' => 401, 'body' => ['error' => 'admin_only']];
    }

    return ['status' => 404, 'body' => ['error' => 'not_found', 'path' => $path, 'method' => $method]];
}
