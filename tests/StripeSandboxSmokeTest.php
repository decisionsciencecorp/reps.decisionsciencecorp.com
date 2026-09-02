<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StripeSandboxSmokeTest extends TestCase
{
    protected function tearDown(): void
    {
        repsStripeClearHttpMock();
        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        parent::tearDown();
    }

    public function testSignWebhookPayloadRoundTrip(): void
    {
        $payload = '{"id":"evt_x","type":"ping"}';
        $secret = 'whsec_unit';
        $header = repsStripeSignWebhookPayload($payload, $secret, 1_700_000_000);
        // Force time window: use current time for verify
        $header = repsStripeSignWebhookPayload($payload, $secret);
        $event = repsStripeVerifyWebhook($payload, $header, $secret);
        $this->assertNotNull($event);
        $this->assertSame('evt_x', $event['id']);
    }

    public function testSandboxConnectHarnessMockSalesSeat(): void
    {
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');
        $jim = repsDashFindUserByUsername('jim');
        $this->assertNotNull($jim);
        $this->assertSame('sales', (string) ($jim['role'] ?? ''));

        $r = repsStripeSandboxConnectHarness([
            'username' => 'jim',
            'mock' => true,
            'live_test' => false,
            'simulate_webhook' => true,
            'account_id' => 'acct_unit_sales',
            'onboarding_url' => 'https://connect.stripe.com/setup/s/acct_unit_sales',
        ]);

        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('mock', $r['mode']);
        $this->assertSame('sales', $r['role']);
        $this->assertStringContainsString('connect.stripe.com', (string) $r['onboarding_url']);
        $this->assertSame(1, (int) ($r['payee']['payouts_enabled'] ?? 0));
        $this->assertSame('ready', (string) ($r['payee']['onboarding_status'] ?? ''));
        $this->assertSame('user', (string) ($r['payee']['entity_type'] ?? ''));
        $this->assertTrue(($r['webhook']['ok'] ?? false));
    }

    public function testSandboxConnectHarnessRejectsUnknownUser(): void
    {
        $r = repsStripeSandboxConnectHarness([
            'username' => 'no-such-reps-user-xyz',
            'mock' => true,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertSame('user_not_found', $r['error']);
    }

    public function testSandboxConnectHarnessRejectsEmployee(): void
    {
        $emp = repsDashFindUserByUsername('alex');
        if ($emp === null) {
            repsDashDbSeedUsers(repsDashDb());
            $emp = repsDashFindUserByUsername('alex');
        }
        $this->assertNotNull($emp);
        $this->assertSame('employee', (string) ($emp['role'] ?? ''));
        $r = repsStripeSandboxConnectHarness([
            'username' => 'alex',
            'mock' => true,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertSame('user_may_not_start_connect', $r['error']);
    }

    public function testSandboxConnectHarnessLiveTestNeedsTestKey(): void
    {
        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        // Clear DB-stored key so live_test cannot fall through to a prior test's sk_test_*
        repsDashAppMetaSet(REPS_STRIPE_META_KEYS['secret'], '');
        $r = repsStripeSandboxConnectHarness([
            'username' => 'jim',
            'mock' => false,
            'live_test' => true,
            'simulate_webhook' => false,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertSame('live_test_requires_test_key', $r['error']);
    }

    public function testSandboxConnectHarnessSkipsWebhook(): void
    {
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');
        $seven = repsDashFindUserByUsername('seven');
        $this->assertNotNull($seven);
        // Fresh payee for this seat — avoid leftover ready state from jim tests
        repsDashDb()->prepare(
            "DELETE FROM payout_payees WHERE entity_type = 'user' AND entity_id = ?"
        )->execute([(int) $seven['id']]);
        $r = repsStripeSandboxConnectHarness([
            'username' => 'seven',
            'mock' => true,
            'simulate_webhook' => false,
            'account_id' => 'acct_unit_nowebhook',
            'onboarding_url' => 'https://connect.stripe.com/setup/s/acct_unit_nowebhook',
        ]);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertArrayNotHasKey('webhook', $r);
        $this->assertSame(0, (int) ($r['payee']['payouts_enabled'] ?? 0));
        $this->assertSame('pending', (string) ($r['payee']['onboarding_status'] ?? ''));
    }

    public function testSandboxConnectHarnessOnboardingFailure(): void
    {
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');
        putenv('STRIPE_SECRET_KEY=sk_test_sandbox_harness');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_sandbox_harness';
        // Pre-set a broken mock before harness (harness will replace when mock=true)
        $r = repsStripeSandboxConnectHarness([
            'username' => 'jim',
            'mock' => true,
            'simulate_webhook' => false,
            'account_id' => 'acct_fail',
            'onboarding_url' => '', // empty URL → EnsurePayee still returns ok with error, StartOnboarding fails
        ]);
        // Empty mock URL makes link ok with url '' → EnsurePayee returns ok without onboarding_url → start fails
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error'] ?? '');
    }

    public function testSandboxConnectHarnessLiveTestWithExplicitMock(): void
    {
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');
        putenv('STRIPE_SECRET_KEY=sk_test_unit_live_flag');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_unit_live_flag';
        $r = repsStripeSandboxConnectHarness([
            'username' => 'jim',
            'live_test' => true,
            'mock' => true, // keep HTTP mock while exercising live_test branch
            'simulate_webhook' => true,
            'account_id' => 'acct_unit_liveflag',
            'onboarding_url' => 'https://connect.stripe.com/setup/s/acct_unit_liveflag',
        ]);
        $this->assertTrue($r['ok'], json_encode($r));
        $this->assertSame('live_test', $r['mode']);
    }

    public function testSandboxConnectHarnessWebhookVerifyFail(): void
    {
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');
        $r = repsStripeSandboxConnectHarness([
            'username' => 'chuck',
            'mock' => true,
            'simulate_webhook' => true,
            'webhook_secret' => '', // verify rejects empty secret
            'account_id' => 'acct_unit_badwh',
            'onboarding_url' => 'https://connect.stripe.com/setup/s/acct_unit_badwh',
        ]);
        $this->assertFalse($r['ok']);
        $this->assertSame('webhook_verify_failed', $r['error']);
    }

    public function testCliMockExitsZero(): void
    {
        $root = dirname(__DIR__);
        $db = getenv('REPS_DASH_DB_PATH');
        $cmd = 'php ' . escapeshellarg($root . '/tools/stripe-sandbox-smoke.php')
            . ' --username=jim';
        $env = 'REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com';
        if (is_string($db) && $db !== '') {
            $env .= ' REPS_DASH_DB_PATH=' . escapeshellarg($db);
        }
        exec($env . ' ' . $cmd . ' 2>/dev/null', $lines, $code);
        $this->assertSame(0, $code, implode("\n", $lines));
        $json = json_decode(implode("\n", $lines), true);
        $this->assertIsArray($json);
        $this->assertTrue($json['ok'] ?? false);
        $this->assertNotEmpty($json['onboarding_url'] ?? '');
    }
}