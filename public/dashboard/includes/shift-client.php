<?php
declare(strict_types=1);

/**
 * JoinShift (Partner) HTTP client — matching / invite lane (Doc #818, Doc #1093).
 *
 * Hours / sessions / acceptance come from MicroPS (microps-client.php), not this host.
 * This client is team roster, SMS invite, and leftover account/auth writes.
 *
 * CARDINAL: app.joinshift.us is PRODUCTION. There is no Shift sandbox.
 * - Live Partner: read-only GETs are approved for matching/verification.
 * - Writes: develop and test only against the fake stub
 *   (tools/fake-shift-partner/, REPS_SHIFT_API_BASE=http://127.0.0.1:…).
 * - Never mutate live Partner as automated verification.
 * - PHPUnit must not point write tests at joinshift.us (see repsShiftAssertSafeBaseForWrites).
 * - Do not poll /api/dashboard/hours-feed for ingest — that feed is empty; use MicroPS.
 *
 * Canonical secret: SQLite app_meta `joinshift.cookie_jar` (manual inject).
 * Pass file is Otto staging for deposit/dev only — never site env, never git.
 */

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

function repsShiftApiBase(): string
{
    $base = getenv('REPS_SHIFT_API_BASE');
    if (is_string($base) && $base !== '') {
        return rtrim($base, '/');
    }
    return 'https://app.joinshift.us';
}

function repsShiftIsLiveJoinshiftBase(?string $base = null): bool
{
    $base = $base ?? repsShiftApiBase();
    $host = strtolower((string) (parse_url($base, PHP_URL_HOST) ?: ''));
    return str_contains($host, 'joinshift.us') || str_contains($host, 'micro-agi.com');
}

function repsShiftIsFakeInline(?string $base = null): bool
{
    $base = $base ?? repsShiftApiBase();
    return getenv('FAKE_SHIFT_INLINE') === '1' || $base === 'fake://shift';
}

/** Live HTTPS JoinShift (not in-process fake) — needs the Partner cookie jar. */
function repsShiftUsesLiveHttp(): bool
{
    if (repsShiftIsFakeInline()) {
        return false;
    }
    return repsShiftIsLiveJoinshiftBase();
}

/**
 * Partner code stamped on ingested sessions and operators (JoinShift matching identity).
 * Do not use MicroPS GM code M3WRBU here — that trips partner_mismatch vs C6N9T7.
 */
function repsShiftMatchingPartnerCode(): string
{
    $env = getenv('REPS_SHIFT_PARTNER_CODE');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    $stored = (string) repsDashAppMetaGet('shift.partner_code', '');
    if ($stored !== '') {
        return $stored;
    }
    return 'C6N9T7';
}

/**
 * Guard for automated tests: refuse live Partner writes.
 * Human ops with live base remain allowed unless REPS_SHIFT_FORBID_LIVE_WRITES=1
 * (set in PHPUnit bootstrap).
 */
function repsShiftAssertSafeBaseForWrites(): void
{
    if (!repsShiftIsLiveJoinshiftBase()) {
        return;
    }
    $forbid = filter_var(getenv('REPS_SHIFT_FORBID_LIVE_WRITES') ?: '0', FILTER_VALIDATE_BOOLEAN);
    if (!$forbid && !defined('REPS_PHPUNIT')) {
        return;
    }
    throw new RuntimeException(
        'CARDINAL: refusing Shift write against live Partner base ' . repsShiftApiBase()
        . '. Point REPS_SHIFT_API_BASE at fake://shift or tools/fake-shift-partner.'
    );
}

function repsShiftCookieJarPath(): string
{
    $explicit = getenv('REPS_SHIFT_COOKIE_JAR');
    if (is_string($explicit) && $explicit !== '') {
        return $explicit;
    }
    $home = (string) (getenv('HOME') ?: '/root');
    foreach ([
        $home . '/.ssh/joinshift-cookies.txt',
        $home . '/.ssh/joinshift-cookies.pass',
        '/tmp/joinshift/cookies.txt',
    ] as $p) {
        if (is_readable($p)) {
            return $p;
        }
    }
    return $home . '/.ssh/joinshift-cookies.txt';
}

const REPS_JOINSHIFT_COOKIE_META = 'joinshift.cookie_jar';

function repsShiftCookieJarFromDb(): string
{
    try {
        return trim(repsDashAppMetaGet(REPS_JOINSHIFT_COOKIE_META, ''));
    } catch (Throwable $e) {
        return '';
    }
}

/** Staging file contents (deposit/dev). Empty if unreadable. */
function repsShiftCookieJarFromFile(): string
{
    $path = repsShiftCookieJarPath();
    if (!is_readable($path)) {
        return '';
    }
    $raw = file_get_contents($path);
    return is_string($raw) ? trim($raw) : '';
}

/**
 * Netscape jar text. DB first, pass-file fallback for deposit/dev only.
 */
function repsShiftCookieJarText(): string
{
    $fromDb = repsShiftCookieJarFromDb();
    if ($fromDb !== '') {
        return $fromDb;
    }
    return repsShiftCookieJarFromFile();
}

function repsShiftHasCredentials(): bool
{
    return repsShiftCookieJarText() !== '';
}

/**
 * @return array{ok: bool, bytes: int, source: string}
 */
function repsShiftStoreCookieJarInDb(string $jarText): array
{
    $jarText = trim($jarText);
    if ($jarText === '') {
        return ['ok' => false, 'bytes' => 0, 'source' => 'empty'];
    }
    repsDashAppMetaSet(REPS_JOINSHIFT_COOKIE_META, $jarText);
    return ['ok' => true, 'bytes' => strlen($jarText), 'source' => 'db'];
}

/**
 * Write jar text to a 0600 temp file for curl. Caller must repsShiftReleaseCookieFile().
 *
 * @return array{path: string, ephemeral: bool}|null
 */
function repsShiftPrepareCookieFile(): ?array
{
    $text = repsShiftCookieJarText();
    if ($text === '') {
        return null;
    }
    $fromDb = repsShiftCookieJarFromDb();
    if ($fromDb !== '') {
        $path = tempnam(sys_get_temp_dir(), 'reps-joinshift-jar-');
        if ($path === false) {
            return null;
        }
        file_put_contents($path, $text . "\n");
        chmod($path, 0600);
        return ['path' => $path, 'ephemeral' => true];
    }
    $path = repsShiftCookieJarPath();
    if (!is_readable($path)) {
        return null;
    }
    return ['path' => $path, 'ephemeral' => false];
}

/** Persist curl Set-Cookie rotations back to app_meta when the jar came from DB. */
function repsShiftReleaseCookieFile(?array $prepared, string $originalText): void
{
    if ($prepared === null) {
        return;
    }
    $path = (string) ($prepared['path'] ?? '');
    $ephemeral = !empty($prepared['ephemeral']);
    if ($path !== '' && is_readable($path) && $ephemeral) {
        $now = (string) file_get_contents($path);
        if (trim($now) !== '' && trim($now) !== trim($originalText)) {
            repsShiftStoreCookieJarInDb($now);
        }
    }
    if ($ephemeral && $path !== '' && is_file($path)) {
        @unlink($path);
    }
}

/**
 * Optional in-process mock: fn(string $method, string $path, ?array $json): array{ok,status?,body?,error?}
 */
function repsShiftSetHttpMock(?callable $fn): void
{
    if ($fn === null) {
        unset($GLOBALS['reps_shift_http_mock']);
        return;
    }
    $GLOBALS['reps_shift_http_mock'] = $fn;
}

/**
 * @param array<string, mixed>|null $jsonBody
 * @return array{ok: bool, status?: int, body?: array<string, mixed>, error?: string, raw?: string}
 */
function repsShiftHttpRequest(string $method, string $path, ?array $jsonBody = null): array
{
    $method = strtoupper($method);
    $path = '/' . ltrim($path, '/');

    if (isset($GLOBALS['reps_shift_http_mock']) && is_callable($GLOBALS['reps_shift_http_mock'])) {
        $fn = $GLOBALS['reps_shift_http_mock'];
        $out = $fn($method, $path, $jsonBody);
        return is_array($out) ? $out : ['ok' => false, 'error' => 'bad_mock'];
    }

    // Prefer in-process fake when base is the special sentinel or FAKE_SHIFT_INLINE=1
    if (repsShiftIsFakeInline()) {
        // public/dashboard/includes → repo root
        $stateFile = dirname(__DIR__, 3) . '/tools/fake-shift-partner/state.php';
        require_once $stateFile;
        $res = fakeShiftHandle($method, $path, $jsonBody);
        $status = (int) $res['status'];
        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $status,
            'body' => $res['body'],
            'error' => $ok ? null : ('http_' . $status),
        ];
    }

    $base = repsShiftApiBase();
    $url = $base . $path;
    $headers = [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (compatible; RepsShiftClient/1.0)',
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $originalJar = repsShiftCookieJarText();
    $prepared = null;
    if (repsShiftIsLiveJoinshiftBase($base)) {
        $prepared = repsShiftPrepareCookieFile();
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
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
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $jsonBody === null) {
        $opts[CURLOPT_POSTFIELDS] = '{}';
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    repsShiftReleaseCookieFile($prepared, $originalJar);

    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'error' => $cerr !== '' ? $cerr : 'curl_failed'];
    }
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => 'invalid_json', 'raw' => (string) $raw];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'body' => $decoded, 'error' => 'http_' . $status];
    }
    return ['ok' => true, 'status' => $status, 'body' => $decoded];
}

/** @return array{ok: bool, status?: int, body?: array<string, mixed>, error?: string} */
function repsShiftHttpGet(string $path): array
{
    return repsShiftHttpRequest('GET', $path, null);
}

/** @param array<string, mixed> $body */
function repsShiftHttpPost(string $path, array $body): array
{
    repsShiftAssertSafeBaseForWrites();
    return repsShiftHttpRequest('POST', $path, $body);
}

function repsShiftHttpDelete(string $path): array
{
    repsShiftAssertSafeBaseForWrites();
    return repsShiftHttpRequest('DELETE', $path, null);
}

/** @param array<string, mixed> $body */
function repsShiftHttpPatch(string $path, array $body): array
{
    repsShiftAssertSafeBaseForWrites();
    return repsShiftHttpRequest('PATCH', $path, $body);
}

// --- Named Doc #818 wrappers (reads — live OK) ---

/** JoinShift hours-feed. Do not use for ingest — live payload is empty. See repsMicropsGetMappedHoursFeed(). */
function repsShiftGetHoursFeed(): array
{
    return repsShiftHttpGet('/api/dashboard/hours-feed');
}

function repsShiftGetWorkers(): array
{
    return repsShiftHttpGet('/api/dashboard/workers');
}

function repsShiftGetTeamMembers(): array
{
    return repsShiftHttpGet('/api/team/members');
}

// --- Writes (assert safe base) ---

function repsShiftInviteTeamMember(string $name, string $phone): array
{
    return repsShiftHttpPost('/api/team/members', ['name' => $name, 'phone' => $phone]);
}

function repsShiftDeleteTeamMember(string $memberId): array
{
    return repsShiftHttpDelete('/api/team/members/' . rawurlencode($memberId));
}

function repsShiftPostPayoutSplit(float $split): array
{
    return repsShiftHttpPost('/api/account/payout-split', ['split' => $split]);
}

/** @param list<string> $days */
function repsShiftPostSmsSchedule(array $days, ?string $timezone = null): array
{
    $body = ['days' => $days];
    if ($timezone !== null) {
        $body['timezone'] = $timezone;
    }
    return repsShiftHttpPost('/api/account/sms-schedule', $body);
}

/** @param array<string, mixed> $fields */
function repsShiftPostBankInfo(array $fields): array
{
    return repsShiftHttpPost('/api/account/bank-info', $fields);
}

/** @param array<string, mixed> $fields */
function repsShiftPostProfile(array $fields): array
{
    return repsShiftHttpPost('/api/account/profile', $fields);
}

/** @param array<string, mixed> $fields */
function repsShiftPostLegalAddress(array $fields): array
{
    return repsShiftHttpPost('/api/account/legal-address', $fields);
}

/** @param array<string, mixed> $fields */
function repsShiftPostShippingAddress(array $fields): array
{
    return repsShiftHttpPost('/api/account/shipping-address', $fields);
}

function repsShiftPostActiveView(string $view): array
{
    return repsShiftHttpPost('/api/account/active-view', ['view' => $view]);
}

/** @param array<string, mixed> $fields */
function repsShiftPostReminders(array $fields): array
{
    return repsShiftHttpPost('/api/account/reminders', $fields);
}

function repsShiftAuthRequestCode(string $phone): array
{
    return repsShiftHttpPost('/api/auth/login/request-code', ['phone' => $phone]);
}

function repsShiftAuthVerifyCode(string $phone, string $code): array
{
    return repsShiftHttpPost('/api/auth/login/verify-code', ['phone' => $phone, 'code' => $code]);
}

function repsShiftAuthLogout(): array
{
    return repsShiftHttpPost('/api/auth/logout', []);
}

/** @param array<string, mixed> $fields */
function repsShiftSupportChat(array $fields): array
{
    return repsShiftHttpPost('/api/support/chat', $fields);
}

function repsShiftPatchReferralLink(string $id, string $customCode): array
{
    return repsShiftHttpPatch('/api/account/referral-links/' . rawurlencode($id), ['customCode' => $customCode]);
}

function repsShiftAdminUsers(string $q = ''): array
{
    $path = '/api/admin/users';
    if ($q !== '') {
        $path .= '?q=' . rawurlencode($q);
    }
    return repsShiftHttpGet($path);
}

/** @param array<string, mixed> $fields */
function repsShiftAdminImpersonate(array $fields): array
{
    return repsShiftHttpPost('/api/admin/impersonate', $fields);
}

/**
 * Whether the current Reps user may call Shift proxy mutations / sync.
 */
function repsShiftApiCallerAllowed(array $user): bool
{
    return in_array((string) ($user['role'] ?? ''), ['admin', 'ops', 'agent'], true);
}
