<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
repsDashLogout();
header('Location: /dashboard/login.php');
exit;
