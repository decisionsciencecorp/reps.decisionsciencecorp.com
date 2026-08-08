<?php
declare(strict_types=1);

/**
 * php -S 127.0.0.1:8765 -t tools/fake-shift-partner tools/fake-shift-partner/router.php
 */

require_once __DIR__ . '/state.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$body = null;
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    $body = is_array($decoded) ? $decoded : null;
}

$result = fakeShiftHandle($method, $uri, $body);
http_response_code((int) $result['status']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
