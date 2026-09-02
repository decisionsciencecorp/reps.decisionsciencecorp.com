<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression: Money must not invent mock ledger dollars when demo seed is locked
 * or the host is production (task #3216 / #3239).
 */
final class MockLedgerSeedGateTest extends TestCase
{
    private function clearLedger(): void
    {
        repsDashDb()->exec('DELETE FROM ledger_lines');
    }

    private function resetEnvFlags(): void
    {
        putenv('REPS_DASH_ALLOW_MOCK_LEDGER');
        unset($_ENV['REPS_DASH_ALLOW_MOCK_LEDGER']);
        putenv('APP_ENV');
        unset($_ENV['APP_ENV']);
        putenv('REPS_PUBLIC_HOST');
        unset($_ENV['REPS_PUBLIC_HOST']);
        unset($_SERVER['HTTP_HOST']);
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        repsDashAppMetaSet('shift.live_data', '0');
    }

    protected function setUp(): void
    {
        $this->resetEnvFlags();
        $this->clearLedger();
    }

    protected function tearDown(): void
    {
        $this->resetEnvFlags();
    }

    public function testProductionHostDetectedFromHttpHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'reps.decisionsciencecorp.com';
        $this->assertTrue(repsDashIsProductionHost());
        $_SERVER['HTTP_HOST'] = 'reps.decisionsciencecorp.com:443';
        $this->assertTrue(repsDashIsProductionHost());
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->assertFalse(repsDashIsProductionHost());
    }

    public function testProductionHostDetectedFromAppEnv(): void
    {
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $this->assertTrue(repsDashIsProductionHost());
    }

    public function testProductionHostDetectedFromPublicHostEnv(): void
    {
        unset($_SERVER['HTTP_HOST']);
        putenv('REPS_PUBLIC_HOST=reps.decisionsciencecorp.com');
        $_ENV['REPS_PUBLIC_HOST'] = 'reps.decisionsciencecorp.com';
        $this->assertTrue(repsDashIsProductionHost());
        putenv('REPS_PUBLIC_HOST=localhost');
        $_ENV['REPS_PUBLIC_HOST'] = 'localhost';
        $this->assertFalse(repsDashIsProductionHost());
    }

    public function testSkipDemoSeedBlocksAllowUnlessExplicitOverride(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '1');
        $this->assertFalse(repsDashAllowMockLedgerSeed());
        $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());

        putenv('REPS_DASH_ALLOW_MOCK_LEDGER=1');
        $_ENV['REPS_DASH_ALLOW_MOCK_LEDGER'] = '1';
        $this->assertTrue(repsDashAllowMockLedgerSeed());
        $this->assertTrue(repsDashShouldSeedMockLedgerOnMoney());
    }

    public function testProductionHostBlocksEvenWhenSkipDemoSeedOff(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        $_SERVER['HTTP_HOST'] = 'reps.decisionsciencecorp.com';
        $this->assertFalse(repsDashAllowMockLedgerSeed());
        $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());
    }

    public function testLocalDevAllowsWhenLedgerEmptyAndLiveOff(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        repsDashAppMetaSet('shift.live_data', '0');
        $_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
        $this->assertTrue(repsDashAllowMockLedgerSeed());
        $this->assertTrue(repsDashShouldSeedMockLedgerOnMoney());
    }

    public function testLiveDataBlocksSeedEvenWhenAllowTrue(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        repsDashAppMetaSet('shift.live_data', '1');
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $this->assertTrue(repsDashAllowMockLedgerSeed());
        $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());
    }

    public function testNonEmptyLedgerBlocksSeed(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        repsDashAppMetaSet('shift.live_data', '0');
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $seed = repsLedgerSeedFromMockShops();
        $this->assertTrue($seed['ok']);
        $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());
    }

    public function testSkipDemoSeedPathDoesNotInventLedgerRows(): void
    {
        repsDashAppMetaSet('dash.skip_demo_seed', '1');
        repsDashAppMetaSet('shift.live_data', '0');
        $_SERVER['HTTP_HOST'] = 'reps.decisionsciencecorp.com';
        $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());
        if (repsDashShouldSeedMockLedgerOnMoney()) {
            repsLedgerSeedFromMockShops();
        }
        $n = (int) repsDashDb()->query('SELECT COUNT(*) FROM ledger_lines')->fetchColumn();
        $this->assertSame(0, $n);
    }

    public function testShouldSeedReturnsFalseWhenLedgerQueryThrows(): void
    {
        putenv('REPS_DASH_FORCE_MOCK=1'); // force live-data off even if sessions exist
        $_ENV['REPS_DASH_FORCE_MOCK'] = '1';
        repsDashAppMetaSet('dash.skip_demo_seed', '0');
        repsDashAppMetaSet('shift.live_data', '0');
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $this->assertTrue(repsDashAllowMockLedgerSeed());
        $this->assertFalse(repsDashLiveDataEnabled());
        $pdo = repsDashDb();
        $pdo->exec('ALTER TABLE ledger_lines RENAME TO ledger_lines_gate_bak');
        try {
            $this->assertFalse(repsDashShouldSeedMockLedgerOnMoney());
        } finally {
            $pdo->exec('ALTER TABLE ledger_lines_gate_bak RENAME TO ledger_lines');
            putenv('REPS_DASH_FORCE_MOCK');
            unset($_ENV['REPS_DASH_FORCE_MOCK']);
        }
    }
}
