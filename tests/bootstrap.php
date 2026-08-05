<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . '/reps-phpunit-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('REPS_DASH_DB_PATH=' . $tmp);
putenv('REPS_DASH_DEV_MODE=0');
putenv('REPS_LEADS_WEBHOOK_URL=');
putenv('REPS_LEADS_WEBHOOK_SECRET=');

require_once dirname(__DIR__) . '/public/dashboard/includes/bootstrap.php';

register_shutdown_function(static function () use ($tmp): void {
    @unlink($tmp);
});
