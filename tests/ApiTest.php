<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    public function testCreateKeyAndResolveUser(): void
    {
        $agent = repsDashFindUserByUsername('agent');
        $this->assertNotNull($agent);
        $admin = repsDashFindUserByUsername('mark');
        $this->assertNotNull($admin);

        $created = repsApiCreateKey((int) $agent['id'], 'phpunit', (int) $admin['id']);
        $this->assertTrue($created['ok']);
        $this->assertNotEmpty($created['key']);
        $this->assertStringStartsWith('reps_', (string) $created['key']);

        $user = repsApiUserFromKey((string) $created['key']);
        $this->assertNotNull($user);
        $this->assertSame('agent', $user['role']);
        $this->assertTrue(!empty($user['api_auth']));

        $elevated = repsApiDataUser($user);
        $this->assertSame('ops', $elevated['role']);
        $shops = repsDashShopsForUser($elevated);
        $this->assertNotEmpty($shops);

        $listed = repsApiListKeysForUser((int) $agent['id'], false);
        $this->assertGreaterThanOrEqual(1, count($listed));

        $rev = repsApiRevokeKey((int) $created['id'], (int) $admin['id']);
        $this->assertTrue($rev['ok']);
        $this->assertNull(repsApiUserFromKey((string) $created['key']));
    }

    public function testMoneySummaryAndMePayload(): void
    {
        $mark = repsDashFindUserByUsername('mark');
        $this->assertNotNull($mark);
        $mark['api_auth'] = false;
        $me = repsApiMePayload($mark);
        $this->assertSame('mark', $me['username']);
        $this->assertSame('session', $me['auth']);

        $summary = repsApiMoneySummary($mark);
        $this->assertArrayHasKey('pulse', $summary);
        $this->assertArrayHasKey('ledger', $summary);
        $this->assertNotNull($summary['ledger']);
        $this->assertSame('dsc_command', $summary['mode']);
    }

    public function testHashStable(): void
    {
        $this->assertSame(repsApiHashKey('abc'), repsApiHashKey('abc'));
        $this->assertNotSame(repsApiHashKey('abc'), repsApiHashKey('abd'));
    }

    public function testSalesScopeViaApiHelpers(): void
    {
        $jim = repsDashFindUserByUsername('jim');
        $this->assertNotNull($jim);
        $jim['api_auth'] = true;
        $data = repsApiDataUser($jim);
        $this->assertSame('sales', $data['role']);
        $shops = repsDashShopsForUser($data);
        foreach ($shops as $s) {
            $rep = $s['assigned_sales_rep'] ?? null;
            $this->assertTrue($rep === 'jim' || $rep === null);
        }
    }

    public function testCreateKeyValidationAndRevokeEdges(): void
    {
        $admin = repsDashFindUserByUsername('mark');
        $this->assertNotNull($admin);
        $bad = repsApiCreateKey(999999, 'x', (int) $admin['id']);
        $this->assertFalse($bad['ok']);
        $this->assertSame('user_not_found', $bad['error']);

        $agent = repsDashFindUserByUsername('agent');
        $this->assertNotNull($agent);
        $long = repsApiCreateKey((int) $agent['id'], str_repeat('n', 81), (int) $admin['id']);
        $this->assertFalse($long['ok']);

        $ok = repsApiCreateKey((int) $agent['id'], '', (int) $admin['id']);
        $this->assertTrue($ok['ok']);

        $this->assertNull(repsApiUserFromKey('short'));
        $missing = repsApiRevokeKey(999999, (int) $admin['id']);
        $this->assertFalse($missing['ok']);

        $jim = repsDashFindUserByUsername('jim');
        $this->assertNotNull($jim);
        $forbidden = repsApiRevokeKey((int) $ok['id'], (int) $jim['id']);
        $this->assertFalse($forbidden['ok']);
        $this->assertSame('forbidden', $forbidden['error']);

        $again = repsApiRevokeKey((int) $ok['id'], (int) $admin['id']);
        $this->assertTrue($again['ok']);
        $dup = repsApiRevokeKey((int) $ok['id'], (int) $admin['id']);
        $this->assertTrue($dup['ok']);
        $this->assertTrue(!empty($dup['already']));

        $withRevoked = repsApiListKeysForUser((int) $agent['id'], true);
        $this->assertNotEmpty($withRevoked);
    }

    public function testEmployeeMoneySummaryOmitsLedger(): void
    {
        $alex = repsDashFindUserByUsername('alex');
        $this->assertNotNull($alex);
        $alex['api_auth'] = false;
        $summary = repsApiMoneySummary($alex);
        $this->assertNull($summary['ledger']);
        $this->assertArrayHasKey('pulse', $summary);
    }

    public function testExtractBearerAndHeaderKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = '  from-header  ';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_GET['api_key']);
        $this->assertSame('from-header', repsApiExtractBearerOrKey());

        unset($_SERVER['HTTP_X_API_KEY']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tok_abc_def';
        $this->assertSame('tok_abc_def', repsApiExtractBearerOrKey());

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->assertSame('', repsApiExtractBearerOrKey());
    }
}
