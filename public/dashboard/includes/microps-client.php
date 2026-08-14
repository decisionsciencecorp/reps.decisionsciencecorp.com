<?php
declare(strict_types=1);

/**
 * MicroPS (www.microps.ai) HTTP client — stats / session lane (Doc #1093).
 *
 * JoinShift (app.joinshift.us) remains matching/invite only.
 * Live GETs need a Google Flask session cookie jar (Netscape).
 * Never paste cookie values into git, Tasks, or chat.
 */

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

function repsMicropsApiBase(): string
{
    $base = getenv('REPS_MICROPS_API_BASE');
    if (is_string($base) && $base !== '') {
        return rtrim($base, '/');
    }
    return 'https://www.microps.ai';
}

function repsMicropsIsFakeBase(?string $base = null): bool
{
    $base = $base ?? repsMicropsApiBase();
    if ($base === 'fake://microps') {
        return true;
    }
    return getenv('FAKE_MICROPS_INLINE') === '1';
}

function repsMicropsCookieJarPath(): string
{
    $explicit = getenv('REPS_MICROPS_COOKIE_JAR');
    if (is_string($explicit) && $explicit !== '') {
        return $explicit;
    }
    $home = (string) (getenv('HOME') ?: '/root');
    foreach ([
        $home . '/.ssh/microps-cookies.pass',
        $home . '/.ssh/microps-cookies.txt',
        '/tmp/microps/cookies.txt',
    ] as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }
    return $home . '/.ssh/microps-cookies.pass';
}

function repsMicropsSetHttpMock(?callable $fn): void
{
    if ($fn === null) {
        unset($GLOBALS['reps_microps_http_mock']);
        return;
    }
    $GLOBALS['reps_microps_http_mock'] = $fn;
}

/**
 * @param array<string, mixed>|null $jsonBody
 * @return array{ok: bool, status?: int, body?: array<string, mixed>|string, error?: string, raw?: string}
 */
function repsMicropsHttpRequest(string $method, string $path, ?array $jsonBody = null): array
{
    $method = strtoupper($method);
    $path = '/' . ltrim($path, '/');

    if (isset($GLOBALS['reps_microps_http_mock']) && is_callable($GLOBALS['reps_microps_http_mock'])) {
        $fn = $GLOBALS['reps_microps_http_mock'];
        $out = $fn($method, $path, $jsonBody);
        return is_array($out) ? $out : ['ok' => false, 'error' => 'bad_mock'];
    }

    if (repsMicropsIsFakeBase()) {
        $stateFile = dirname(__DIR__, 3) . '/tools/fake-microps/state.php';
        require_once $stateFile;
        $res = fakeMicropsHandle($method, $path, $jsonBody);
        $status = (int) $res['status'];
        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $res['body'],
            'error' => $ok ? null : ('http_' . $status),
        ];
    }

    $base = repsMicropsApiBase();
    $url = $base . $path;
    $cookie = repsMicropsCookieJarPath();
    $headers = [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (compatible; RepsMicropsClient/1.0)',
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if (is_readable($cookie)) {
        $opts[CURLOPT_COOKIEFILE] = $cookie;
        $opts[CURLOPT_COOKIEJAR] = $cookie;
    }
    if ($jsonBody !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'error' => $cerr !== '' ? $cerr : 'curl_failed'];
    }
    if ($status >= 300 && $status < 400) {
        return [
            'ok' => false,
            'status' => $status,
            'error' => 'auth_redirect',
            'raw' => $redirect !== '' ? $redirect : substr((string) $raw, 0, 200),
        ];
    }
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => 'invalid_json', 'raw' => substr((string) $raw, 0, 400)];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'body' => $decoded, 'error' => 'http_' . $status];
    }
    return ['ok' => true, 'status' => $status, 'body' => $decoded];
}

function repsMicropsHttpGet(string $path): array
{
    return repsMicropsHttpRequest('GET', $path, null);
}

/** @param array<string, mixed> $body */
function repsMicropsHttpPost(string $path, array $body): array
{
    return repsMicropsHttpRequest('POST', $path, $body);
}

function repsMicropsDateFrom(): string
{
    $from = getenv('REPS_MICROPS_DATE_FROM');
    if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        return $from;
    }
    return '2026-01-01';
}

function repsMicropsDateTo(): string
{
    $to = getenv('REPS_MICROPS_DATE_TO');
    if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return $to;
    }
    return (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');
}

function repsMicropsDateQuery(): string
{
    return 'date_from=' . rawurlencode(repsMicropsDateFrom())
        . '&date_to=' . rawurlencode(repsMicropsDateTo());
}

function repsMicropsGetDashboardData(): array
{
    return repsMicropsHttpGet('/api/mobile-dashboard/data?' . repsMicropsDateQuery());
}

function repsMicropsGetPerUser(): array
{
    return repsMicropsHttpGet('/api/mobile-dashboard/per-user?' . repsMicropsDateQuery());
}

function repsMicropsGetPageSummary(): array
{
    return repsMicropsHttpGet('/api/mobile-dashboard/page-summary?' . repsMicropsDateQuery());
}

function repsMicropsGetAuthMe(): array
{
    return repsMicropsHttpGet('/api/auth/me');
}

function repsMicropsIsLiveBase(?string $base = null): bool
{
    $base = $base ?? repsMicropsApiBase();
    $host = strtolower((string) (parse_url($base, PHP_URL_HOST) ?: ''));
    return str_contains($host, 'microps.ai');
}

/** Live HTTPS (not in-process fake) — needs the Google session cookie jar. */
function repsMicropsUsesLiveHttp(): bool
{
    if (repsMicropsIsFakeBase()) {
        return false;
    }
    return repsMicropsIsLiveBase();
}

/**
 * GM code from /api/auth/me (e.g. M3WRBU). Stored as meta only — never as session partner_code.
 *
 * @param array<string, mixed> $me
 */
function repsMicropsExtractGmCode(array $me): string
{
    foreach (['gm_code', 'code'] as $k) {
        $v = trim((string) ($me[$k] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    $gms = $me['gms'] ?? $me['ground_managers'] ?? null;
    if (is_array($gms)) {
        foreach ($gms as $gm) {
            if (!is_array($gm)) {
                continue;
            }
            $v = trim((string) ($gm['code'] ?? $gm['gm_code'] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
    }
    $gm = $me['gm'] ?? $me['ground_manager'] ?? null;
    if (is_array($gm)) {
        $v = trim((string) ($gm['code'] ?? $gm['gm_code'] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}
