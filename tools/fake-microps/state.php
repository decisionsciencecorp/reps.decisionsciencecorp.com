<?php
declare(strict_types=1);

/**
 * In-process fake MicroPS (www.microps.ai) mobile-dashboard JSON.
 * Hours lane only — matching/invite stay on fake JoinShift.
 */

function fakeMicropsStatePath(): string
{
    $p = getenv('FAKE_MICROPS_STATE');
    if (is_string($p) && $p !== '') {
        return $p;
    }
    return sys_get_temp_dir() . '/fake-microps-state.json';
}

/** @return array<string, mixed> */
function fakeMicropsLoadState(): array
{
    $path = fakeMicropsStatePath();
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return fakeMicropsDefaultState();
}

/** @return array<string, mixed> */
function fakeMicropsDefaultState(): array
{
    return [
        'gm_code' => 'M3WRBU',
        'partner_code' => 'C6N9T7',
        'sessions' => [
            [
                'session_id' => 'fake_sess_1',
                'user_id' => 'shift-user-mark',
                'user_full_name' => 'Mark Hopkins',
                'length_seconds' => 3600,
                'date_recorded' => '2026-08-05T15:00:00-05:00',
                'uploaded_at' => '2026-08-05T16:00:00-05:00',
                'rejection_reason' => null,
            ],
            [
                'session_id' => 'fake_sess_2',
                'user_id' => 'shift-user-mark',
                'user_full_name' => 'Mark Hopkins',
                'length_seconds' => 1800,
                'date_recorded' => '2026-08-06T10:00:00-05:00',
                'uploaded_at' => '2026-08-06T10:30:00-05:00',
                'rejection_reason' => 'REJECTED_HEALTH_TOO_LOW',
            ],
        ],
        'per_user' => [
            [
                'user_id' => 'shift-user-mark',
                'accepted_hours' => 0.8,
                'rejected_hours' => 0.5,
                'uploaded_hours' => 1.5,
            ],
        ],
        'page_summary' => [
            'accepted_hours' => 0.8,
            'rejected_hours' => 0.5,
            'uploaded_hours' => 1.5,
            'operators_with_hours' => 1,
        ],
    ];
}

/** @param array<string, mixed> $state */
function fakeMicropsSaveState(array $state): void
{
    file_put_contents(fakeMicropsStatePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * @param array<string, mixed>|null $body
 * @return array{status: int, body: array<string, mixed>}
 */
function fakeMicropsHandle(string $method, string $path, ?array $body = null): array
{
    $method = strtoupper($method);
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    $state = fakeMicropsLoadState();
    unset($body);

    if ($method === 'GET' && $path === '/api/mobile-dashboard/data') {
        return [
            'status' => 200,
            'body' => [
                'sessions' => $state['sessions'],
            ],
        ];
    }

    if ($method === 'GET' && $path === '/api/mobile-dashboard/per-user') {
        return [
            'status' => 200,
            'body' => [
                'users' => $state['per_user'],
            ],
        ];
    }

    if ($method === 'GET' && $path === '/api/mobile-dashboard/page-summary') {
        return [
            'status' => 200,
            'body' => $state['page_summary'],
        ];
    }

    if ($method === 'GET' && $path === '/api/auth/me') {
        return [
            'status' => 200,
            'body' => [
                'email' => 'decisionsciencecorp@gmail.com',
                'role' => 'gm',
                'is_partner' => false,
                'has_device_data' => false,
                'gm' => [
                    'code' => $state['gm_code'],
                    'name' => 'Decision Science Corp',
                ],
            ],
        ];
    }

    return ['status' => 404, 'body' => ['error' => 'not_found', 'path' => $path]];
}
