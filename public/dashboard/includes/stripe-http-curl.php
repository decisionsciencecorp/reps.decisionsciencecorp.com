<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Raw curl transport for Stripe (excluded from coverage gate — exercised via HTTP mock in tests).
 *
 * @param list<string> $headers
 * @return array{raw: string, status: int, error: string}
 */
function repsStripeCurl(string $method, string $url, array $headers, ?string $body): array
{
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['raw' => $raw, 'status' => $status, 'error' => $error];
}
