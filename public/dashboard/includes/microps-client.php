<?php
declare(strict_types=1);

/**
 * MicroPS (www.microps.ai) HTTP client — stats / session lane (Doc #1093).
 *
 * JoinShift (app.joinshift.us) remains matching/invite only.
 * Live GETs need a Google Flask session cookie jar (Netscape).
 * Canonical secret: SQLite app_meta `microps.cookie_jar` (manual inject).
 * Pass file is Otto staging for deposit/dev only — never site env, never git.
 */

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

const REPS_MICROPS_COOKIE_META = 'microps.cookie_jar';

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

function repsMicropsCookieJarFromDb(): string
{
    try {
        return trim(repsDashAppMetaGet(REPS_MICROPS_COOKIE_META, ''));
    } catch (Throwable $e) {
        return '';
    }
}

/** Staging file contents (deposit/dev). Empty if unreadable. */
function repsMicropsCookieJarFromFile(): string
{
    $path = repsMicropsCookieJarPath();
    if (!is_readable($path)) {
        return '';
    }
    $raw = file_get_contents($path);
    return is_string($raw) ? trim($raw) : '';
}

/**
 * Netscape jar text. DB first, pass-file fallback for deposit/dev only.
 */
function repsMicropsCookieJarText(): string
{
    $fromDb = repsMicropsCookieJarFromDb();
    if ($fromDb !== '') {
        return $fromDb;
    }
    return repsMicropsCookieJarFromFile();
}

function repsMicropsHasCredentials(): bool
{
    return repsMicropsCookieJarText() !== '';
}

/**
 * @return array{ok: bool, bytes: int, source: string}
 */
function repsMicropsStoreCookieJarInDb(string $jarText): array
{
    $jarText = trim($jarText);
    if ($jarText === '') {
        return ['ok' => false, 'bytes' => 0, 'source' => 'empty'];
    }
    repsDashAppMetaSet(REPS_MICROPS_COOKIE_META, $jarText);
    return ['ok' => true, 'bytes' => strlen($jarText), 'source' => 'db'];
}

/**
 * Write jar text to a 0600 temp file for curl. Caller must repsMicropsReleaseCookieFile().
 *
 * @return array{path: string, ephemeral: bool}|null
 */
function repsMicropsPrepareCookieFile(): ?array
{
    $text = repsMicropsCookieJarText();
    if ($text === '') {
        return null;
    }
    $fromDb = repsMicropsCookieJarFromDb();
    if ($fromDb !== '') {
        $path = tempnam(sys_get_temp_dir(), 'reps-microps-jar-');
        if ($path === false) {
            return null;
        }
        file_put_contents($path, $text . "\n");
        chmod($path, 0600);
        return ['path' => $path, 'ephemeral' => true];
    }
    $path = repsMicropsCookieJarPath();
    if (!is_readable($path)) {
        return null;
    }
    return ['path' => $path, 'ephemeral' => false];
}

/** Persist curl Set-Cookie rotations back to app_meta when the jar came from DB. */
function repsMicropsReleaseCookieFile(?array $prepared, string $originalText): void
{
    if ($prepared === null) {
        return;
    }
    $path = (string) ($prepared['path'] ?? '');
    $ephemeral = !empty($prepared['ephemeral']);
    if ($path !== '' && is_readable($path) && $ephemeral) {
        $now = (string) file_get_contents($path);
        if (trim($now) !== '' && trim($now) !== trim($originalText)) {
            repsMicropsStoreCookieJarInDb($now);
        }
    }
    if ($ephemeral && $path !== '' && is_file($path)) {
        @unlink($path);
    }
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
    $headers = [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (compatible; RepsMicropsClient/1.0)',
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $originalJar = repsMicropsCookieJarText();
    $prepared = repsMicropsPrepareCookieFile();

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($prepared !== null) {
        $opts[CURLOPT_COOKIEFILE] = $prepared['path'];
        $opts[CURLOPT_COOKIEJAR] = $prepared['path'];
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
    repsMicropsReleaseCookieFile($prepared, $originalJar);

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

/** Live HTTPS (not in-process fake) — needs app_meta jar or staging file. */
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
