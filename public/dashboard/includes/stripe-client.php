<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Minimal Stripe HTTP client (no Composer SDK).
 *
 * Canonical keys: SQLite app_meta (stripe.*). Optional pass-file/env is local
 * deposit/dev fallback only — never commit secrets; not multihost site env.
 */

/** @var array<string, string> */
const REPS_STRIPE_META_KEYS = [
    'secret' => 'stripe.secret_key',
    'publishable' => 'stripe.publishable_key',
    'webhook' => 'stripe.webhook_secret',
    'webhook_connect' => 'stripe.connect_webhook_secret',
    'api_base' => 'stripe.api_base',
    'mode' => 'stripe.mode',
];

/**
 * Optional ~/.ssh/reps-stripe.pass (or REPS_STRIPE_PASS_FILE) for Otto deposit scripts.
 */
function repsStripeLoadPassFile(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $path = getenv('REPS_STRIPE_PASS_FILE') ?: (getenv('HOME') ?: '/root') . '/.ssh/reps-stripe.pass';
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

function repsStripeMetaGet(string $metaKey): string
{
    try {
        return trim(repsDashAppMetaGet($metaKey, ''));
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Upsert Stripe secrets into app_meta. Never log values.
 *
 * @param array{
 *   secret_key?: string,
 *   publishable_key?: string,
 *   webhook_secret?: string,
 *   connect_webhook_secret?: string,
 *   api_base?: string,
 *   mode?: string
 * } $keys
 * @return array{ok: bool, written: list<string>}
 */
function repsStripeStoreSecretsInDb(array $keys): array
{
    $map = [
        'secret_key' => REPS_STRIPE_META_KEYS['secret'],
        'publishable_key' => REPS_STRIPE_META_KEYS['publishable'],
        'webhook_secret' => REPS_STRIPE_META_KEYS['webhook'],
        'connect_webhook_secret' => REPS_STRIPE_META_KEYS['webhook_connect'],
        'api_base' => REPS_STRIPE_META_KEYS['api_base'],
        'mode' => REPS_STRIPE_META_KEYS['mode'],
    ];
    $written = [];
    foreach ($map as $in => $metaKey) {
        if (!array_key_exists($in, $keys)) {
            continue;
        }
        $val = trim((string) $keys[$in]);
        if ($val === '') {
            continue;
        }
        repsDashAppMetaSet($metaKey, $val);
        $written[] = $metaKey;
    }
    return ['ok' => $written !== [], 'written' => $written];
}

function repsStripeSecretKey(): string
{
    $fromDb = repsStripeMetaGet(REPS_STRIPE_META_KEYS['secret']);
    if ($fromDb !== '') {
        return $fromDb;
    }
    repsStripeLoadPassFile();
    return (string) (getenv('STRIPE_SECRET_KEY') ?: '');
}

function repsStripePublishableKey(): string
{
    $fromDb = repsStripeMetaGet(REPS_STRIPE_META_KEYS['publishable']);
    if ($fromDb !== '') {
        return $fromDb;
    }
    repsStripeLoadPassFile();
    return (string) (getenv('STRIPE_PUBLISHABLE_KEY') ?: '');
}

function repsStripeWebhookSecret(bool $connect = false): string
{
    if ($connect) {
        $c = repsStripeMetaGet(REPS_STRIPE_META_KEYS['webhook_connect']);
        if ($c !== '') {
            return $c;
        }
    }
    $w = repsStripeMetaGet(REPS_STRIPE_META_KEYS['webhook']);
    if ($w !== '') {
        return $w;
    }
    repsStripeLoadPassFile();
    if ($connect) {
        $c = (string) (getenv('STRIPE_CONNECT_WEBHOOK_SECRET') ?: '');
        if ($c !== '') {
            return $c;
        }
    }
    return (string) (getenv('STRIPE_WEBHOOK_SECRET') ?: '');
}

function repsStripeConfigured(): bool
{
    $k = repsStripeSecretKey();
    // sk_* standard secret; rk_* restricted key (Dashboard → API keys → Restricted)
    return $k !== '' && (str_starts_with($k, 'sk_') || str_starts_with($k, 'rk_'));
}

function repsStripeApiBase(): string
{
    $fromDb = repsStripeMetaGet(REPS_STRIPE_META_KEYS['api_base']);
    if ($fromDb !== '') {
        return rtrim($fromDb, '/');
    }
    repsStripeLoadPassFile();
    $b = (string) (getenv('STRIPE_API_BASE') ?: 'https://api.stripe.com');
    return rtrim($b, '/');
}

/**
 * Test double for HTTP — set via repsStripeSetHttpMock(); null clears.
 *
 * @param null|callable(string,string,array,?string,?string): array{ok:bool,status:int,body:array,raw:string,error?:string} $fn
 */
function repsStripeSetHttpMock(?callable $fn): void
{
    $GLOBALS['reps_stripe_http_mock'] = $fn;
}

function repsStripeClearHttpMock(): void
{
    unset($GLOBALS['reps_stripe_http_mock']);
}

/**
 * @param array<string, mixed> $params
 * @return array{ok: bool, status: int, body: array<string, mixed>, raw: string, error?: string}|null
 */
function repsStripeInvokeHttpMock(
    string $method,
    string $path,
    array $params,
    ?string $idempotencyKey,
    ?string $stripeVersion
): ?array {
    if (!isset($GLOBALS['reps_stripe_http_mock']) || !is_callable($GLOBALS['reps_stripe_http_mock'])) {
        return null;
    }
    /** @var callable $fn */
    $fn = $GLOBALS['reps_stripe_http_mock'];
    return $fn($method, $path, $params, $idempotencyKey, $stripeVersion);
}

/**
 * @param array<string, mixed> $params
 * @return array{ok: bool, status: int, body: array<string, mixed>, raw: string, error?: string}
 */
function repsStripeRequest(
    string $method,
    string $path,
    array $params = [],
    ?string $idempotencyKey = null,
    ?string $stripeVersion = null
): array {
    $key = repsStripeSecretKey();
    if ($key === '') {
        return ['ok' => false, 'status' => 0, 'body' => [], 'raw' => '', 'error' => 'missing_secret_key'];
    }

    $mocked = repsStripeInvokeHttpMock($method, $path, $params, $idempotencyKey, $stripeVersion);
    if ($mocked !== null) {
        return $mocked;
    }

    require_once __DIR__ . '/stripe-http-curl.php';
    $url = repsStripeApiBase() . $path;
    $headers = [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    if ($stripeVersion !== null && $stripeVersion !== '') {
        $headers[] = 'Stripe-Version: ' . $stripeVersion;
    }

    $method = strtoupper($method);
    $body = null;
    if ($method === 'GET' && $params !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    } elseif ($method !== 'GET') {
        $body = http_build_query($params);
    }
    $tr = repsStripeCurl($method, $url, $headers, $body);
    $raw = $tr['raw'];
    $status = $tr['status'];
    $cerr = $tr['error'];

    if ($raw === '' && $cerr !== '') {
        return ['ok' => false, 'status' => $status, 'body' => [], 'raw' => '', 'error' => $cerr];
    }
    $bodyArr = json_decode($raw, true);
    if (!is_array($bodyArr)) {
        $bodyArr = [];
    }
    $ok = $status >= 200 && $status < 300 && !isset($bodyArr['error']);
    $out = ['ok' => $ok, 'status' => $status, 'body' => $bodyArr, 'raw' => $raw];
    if (!$ok) {
        $out['error'] = (string) ($bodyArr['error']['message'] ?? ('http_' . $status));
    }
    return $out;
}

/**
 * JSON body for Accounts v2.
 *
 * @param array<string, mixed> $json
 * @return array{ok: bool, status: int, body: array<string, mixed>, raw: string, error?: string}
 */
function repsStripeRequestJson(
    string $method,
    string $path,
    array $json = [],
    ?string $idempotencyKey = null,
    string $stripeVersion = '2025-03-31.basil'
): array {
    $key = repsStripeSecretKey();
    if ($key === '') {
        return ['ok' => false, 'status' => 0, 'body' => [], 'raw' => '', 'error' => 'missing_secret_key'];
    }
    $mocked = repsStripeInvokeHttpMock($method, $path, $json, $idempotencyKey, $stripeVersion);
    if ($mocked !== null) {
        return $mocked;
    }
    require_once __DIR__ . '/stripe-http-curl.php';
    $url = repsStripeApiBase() . $path;
    $headers = [
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Stripe-Version: ' . $stripeVersion,
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    $payload = $json === [] ? '{}' : (string) json_encode($json);
    $tr = repsStripeCurl($method, $url, $headers, $payload);
    $raw = $tr['raw'];
    $status = $tr['status'];
    $cerr = $tr['error'];
    if ($raw === '' && $cerr !== '') {
        return ['ok' => false, 'status' => $status, 'body' => [], 'raw' => '', 'error' => $cerr];
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }
    $ok = $status >= 200 && $status < 300 && !isset($body['error']);
    $out = ['ok' => $ok, 'status' => $status, 'body' => $body, 'raw' => $raw];
    if (!$ok) {
        $out['error'] = (string) ($body['error']['message'] ?? ('http_' . $status));
    }
    return $out;
}

/**
 * Verify Stripe-Signature header (HMAC SHA256, tolerance 300s).
 *
 * @return array<string, mixed>|null decoded event or null
 */
function repsStripeVerifyWebhook(string $payload, string $sigHeader, string $secret): ?array
{
    if ($secret === '' || $sigHeader === '') {
        return null;
    }
    $parts = [];
    foreach (explode(',', $sigHeader) as $piece) {
        $kv = explode('=', trim($piece), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]][] = $kv[1];
        }
    }
    $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
    if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
        return null;
    }
    $signed = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    $ok = false;
    foreach ($parts['v1'] ?? [] as $sig) {
        if (hash_equals($expected, $sig)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        return null;
    }
    $event = json_decode($payload, true);
    return is_array($event) ? $event : null;
}

/** @return array{ok: bool, available_cents: int, pending_cents: int, raw?: array<string, mixed>, error?: string} */
function repsStripeBalance(): array
{
    $res = repsStripeRequest('GET', '/v1/balance');
    if (!$res['ok']) {
        return ['ok' => false, 'available_cents' => 0, 'pending_cents' => 0, 'error' => $res['error'] ?? 'balance_failed'];
    }
    $available = 0;
    $pending = 0;
    foreach ($res['body']['available'] ?? [] as $row) {
        if (($row['currency'] ?? '') === 'usd') {
            $available += (int) ($row['amount'] ?? 0);
        }
    }
    foreach ($res['body']['pending'] ?? [] as $row) {
        if (($row['currency'] ?? '') === 'usd') {
            $pending += (int) ($row['amount'] ?? 0);
        }
    }
    return ['ok' => true, 'available_cents' => $available, 'pending_cents' => $pending, 'raw' => $res['body']];
}

/**
 * Test-mode only: charge tok_bypassPending so funds land in available balance
 * (needed before Transfers). Refuses non-test secret keys.
 *
 * @return array{ok: bool, charge_id?: string, available_cents?: int, error?: string}
 */
function repsStripeSandboxTopUpAvailable(int $amountCents = 20000): array
{
    $key = repsStripeSecretKey();
    if ($key === '' || (!str_starts_with($key, 'sk_test_') && !str_starts_with($key, 'rk_test_'))) {
        return ['ok' => false, 'error' => 'sandbox_topup_requires_test_key'];
    }
    $amountCents = max(50, min(500000, $amountCents));
    $res = repsStripeRequest('POST', '/v1/charges', [
        'amount' => (string) $amountCents,
        'currency' => 'usd',
        'source' => 'tok_bypassPending',
        'description' => 'Reps sandbox available-balance top-up',
    ], 'reps-sandbox-topup-' . $amountCents . '-' . gmdate('YmdHis'));
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] ?? 'topup_failed'];
    }
    $bal = repsStripeBalance();
    return [
        'ok' => true,
        'charge_id' => (string) ($res['body']['id'] ?? ''),
        'available_cents' => (int) ($bal['available_cents'] ?? 0),
    ];
}
