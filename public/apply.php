<?php
declare(strict_types=1);

/**
 * Legacy local handler — forwards to the DSC central contact intake (Reps channel).
 * Prefer form action pointing at decisionsciencecorp.com/api/inbound-contact.php.
 */

function reps_redirect(string $qs): void
{
    header('Location: /?' . $qs . '#apply');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /#apply');
    exit;
}

if (!empty($_POST['company_website'] ?? '')) {
    reps_redirect('ok=1');
}

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$path = trim((string) ($_POST['path'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$allowedPaths = ['on_job', 'at_home', 'company'];

if ($name === '' || $phone === '' || $email === '' || !in_array($path, $allowedPaths, true)) {
    reps_redirect('err=1');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reps_redirect('err=1');
}

$payload = http_build_query([
    'channel' => 'reps',
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'path' => $path,
    'notes' => $notes,
    'return_url' => 'https://reps.decisionsciencecorp.com/#apply',
]);

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            . "Content-Length: " . strlen($payload) . "\r\n",
        'content' => $payload,
        'timeout' => 20,
        'follow_location' => 0,
        'ignore_errors' => true,
    ],
]);

$raw = @file_get_contents('https://decisionsciencecorp.com/api/inbound-contact.php', false, $ctx);
$code = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $code = (int) $m[1];
}
// 302/303 success redirect from intake, or 200 JSON
if ($code >= 200 && $code < 400) {
    reps_redirect('ok=1');
}

error_log('reps apply forward failed HTTP ' . $code . ' body=' . substr((string) $raw, 0, 200));
reps_redirect('err=1');
