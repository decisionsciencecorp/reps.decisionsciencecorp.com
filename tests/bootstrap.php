<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('REPS_PHPUNIT', true);

$tmp = sys_get_temp_dir() . '/reps-phpunit-' . getmypid() . '.sqlite';
@unlink($tmp);
putenv('REPS_DASH_DB_PATH=' . $tmp);
putenv('REPS_DASH_DEV_MODE=0');
putenv('REPS_LEADS_WEBHOOK_URL=');
putenv('REPS_LEADS_WEBHOOK_SECRET=');

// CARDINAL: Shift Partner is prod — PHPUnit writes only against in-process fake.
putenv('REPS_SHIFT_API_BASE=fake://shift');
putenv('FAKE_SHIFT_INLINE=1');
putenv('REPS_SHIFT_FORBID_LIVE_WRITES=1');
$fakeState = sys_get_temp_dir() . '/fake-shift-phpunit-' . getmypid() . '.json';
@unlink($fakeState);
putenv('FAKE_SHIFT_STATE=' . $fakeState);

// Do not load Mark's ~/.ssh/reps-stripe.pass during unit tests.
$stripePass = sys_get_temp_dir() . '/reps-stripe-phpunit-' . getmypid() . '.pass';
file_put_contents($stripePass, "# phpunit empty stripe pass\n");
putenv('REPS_STRIPE_PASS_FILE=' . $stripePass);
putenv('STRIPE_SECRET_KEY');
putenv('STRIPE_PUBLISHABLE_KEY');
putenv('STRIPE_WEBHOOK_SECRET');
putenv('STRIPE_CONNECT_WEBHOOK_SECRET');

require_once dirname(__DIR__) . '/public/dashboard/includes/bootstrap.php';

register_shutdown_function(static function () use ($tmp, $stripePass, $fakeState): void {
    @unlink($tmp);
    @unlink($stripePass);
    @unlink($fakeState);
});
