<?php
declare(strict_types=1);

/**
 * Reps apply form — appends JSON lines under DB_PARENT/applications.jsonl
 * (multihost: /var/www/reps.decisionsciencecorp.com/db/).
 */

function reps_db_dir(): string
{
    $candidates = [
        dirname(__DIR__) . '/db',                 // sibling of public/ in repo / deploy tree
        dirname(__DIR__, 2) . '/db',              // if public is nested oddly
        '/var/www/reps.decisionsciencecorp.com/db',
    ];
    foreach ($candidates as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }
    // Prefer creating next to public when possible
    $fallback = dirname(__DIR__) . '/db';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0750, true);
    }
    return $fallback;
}

function reps_redirect(string $qs): void
{
    header('Location: /?' . $qs . '#apply');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /#apply');
    exit;
}

// Honeypot
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

$cut = static fn(string $s, int $n): string => function_exists('mb_substr')
    ? mb_substr($s, 0, $n)
    : substr($s, 0, $n);

$row = [
    'ts' => gmdate('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    'name' => $cut($name, 120),
    'phone' => $cut($phone, 40),
    'email' => $cut($email, 160),
    'path' => $path,
    'notes' => $cut($notes, 2000),
];

$dir = reps_db_dir();
$file = rtrim($dir, '/') . '/applications.jsonl';
$line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

$ok = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
if ($ok === false) {
    // Still thank the user — mail fallback path exists on the page
    error_log('reps apply write failed: ' . $file);
}

reps_redirect('ok=1');
