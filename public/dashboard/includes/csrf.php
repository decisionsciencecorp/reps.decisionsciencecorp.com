<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

function repsDashCsrfToken(): string
{
    if (empty($_SESSION['reps_dash_csrf']) || !is_string($_SESSION['reps_dash_csrf'])) {
        $_SESSION['reps_dash_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['reps_dash_csrf'];
}

function repsDashCsrfField(): string
{
    $t = htmlspecialchars(repsDashCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $t . '">';
}

function repsDashCsrfVerify(?string $token = null): bool
{
    $token = $token ?? (string) ($_POST['csrf_token'] ?? '');
    $sess = $_SESSION['reps_dash_csrf'] ?? '';
    return is_string($sess) && $sess !== '' && hash_equals($sess, $token);
}

function repsDashRequireCsrf(): void
{
    if (!repsDashCsrfVerify()) {
        http_response_code(403);
        echo 'Invalid or missing CSRF token. Go back and try again.';
        exit;
    }
}
