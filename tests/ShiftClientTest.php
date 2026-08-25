<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CARDINAL: tests use fake://shift only (see tests/bootstrap.php).
 */
final class ShiftClientTest extends TestCase
{
    protected function setUp(): void
    {
        $this->assertSame('fake://shift', repsShiftApiBase());
        $this->assertFalse(repsShiftUsesLiveHttp());
        $this->assertSame('fake://microps', repsMicropsApiBase());
        $this->assertFalse(repsMicropsUsesLiveHttp());
    }

    public function testHoursFeedAndInviteAgainstFake(): void
    {
        $feed = repsShiftGetHoursFeed();
        $this->assertTrue($feed['ok']);
        $this->assertSame('C6N9T7', $feed['body']['partnerCode'] ?? null);
        $this->assertNotEmpty($feed['body']['sessions'] ?? []);

        $invite = repsShiftInviteTeamMember('Test Worker', '+15550001111');
        $this->assertTrue($invite['ok']);
        $this->assertTrue($invite['body']['smsSent'] ?? false);

        $team = repsShiftGetTeamMembers();
        $this->assertTrue($team['ok']);
        $names = array_column($team['body']['members'] ?? [], 'name');
        $this->assertContains('Test Worker', $names);

        $id = (string) ($invite['body']['id'] ?? '');
        $this->assertNotSame('', $id);
        $del = repsShiftDeleteTeamMember($id);
        $this->assertTrue($del['ok']);
    }

    public function testForbidLiveWrites(): void
    {
        $prev = getenv('REPS_SHIFT_API_BASE') ?: '';
        putenv('REPS_SHIFT_API_BASE=https://app.joinshift.us');
        try {
            $this->expectException(RuntimeException::class);
            repsShiftInviteTeamMember('Nope', '+15550002222');
        } finally {
            putenv('REPS_SHIFT_API_BASE=' . $prev);
        }
    }

    public function testPollLiveIngestsFromFake(): void
    {
        $r = repsShiftPollLive();
        $this->assertTrue($r['ok'] ?? false);
        $this->assertTrue(repsDashLiveDataEnabled());
        $this->assertSame('microps', $r['hours_source'] ?? null);
        $this->assertSame('joinshift', $r['matching_source'] ?? null);
        $this->assertTrue($r['hours_ok'] ?? false);
        $this->assertTrue($r['matching_ok'] ?? false);
        $sessions = repsDashDbSessionsAsRows();
        $this->assertNotEmpty($sessions);
        $ids = array_column($sessions, 'session_id');
        $this->assertContains('fake_sess_1', $ids);
        $this->assertContains('fake_sess_2', $ids);
        $mark = repsOperatorByShiftUserId('shift-user-mark');
        $this->assertNotNull($mark);
    }

    public function testPollLiveEmptyHoursStillIngestsTeam(): void
    {
        require_once dirname(__DIR__) . '/tools/fake-microps/state.php';
        require_once dirname(__DIR__) . '/tools/fake-shift-partner/state.php';

        $ok = repsShiftPollLive();
        $this->assertTrue($ok['ok'] ?? false);
        $before = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
        $this->assertGreaterThan(0, $before);

        $mp = fakeMicropsLoadState();
        $mp['sessions'] = [];
        fakeMicropsSaveState($mp);

        $st = fakeShiftLoadState();
        $st['members'][] = [
            'id' => 'mem-jess-poll',
            'name' => 'Jess Poll',
            'phone' => '+15550009999',
            'userId' => 'shift-user-jess-poll',
        ];
        fakeShiftSaveState($st);

        try {
            $r = repsShiftPollLive();
            $this->assertFalse($r['ok'] ?? true);
            $this->assertSame('empty_feed_refused', $r['error'] ?? null);
            $this->assertTrue($r['matching_ok'] ?? false);
            $jess = repsOperatorByShiftUserId('shift-user-jess-poll');
            $this->assertNotNull($jess);
            $this->assertSame('Jess Poll', $jess['display_name']);
            $after = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
            $this->assertSame($before, $after);
        } finally {
            fakeMicropsSaveState(fakeMicropsDefaultState());
        }
    }

    public function testDerivedIssuesAndDay(): void
    {
        repsShiftPollLive();
        $mark = repsDashFindUserByUsername('mark');
        $this->assertNotNull($mark);
        $issues = repsShiftDerivedIssues($mark);
        $this->assertIsArray($issues);

        $day = repsShiftDerivedDay('2026-08-05', null, $mark);
        $this->assertNotNull($day);
        $this->assertSame('2026-08-05', $day['day']);
    }

    public function testAccountWriteOnFake(): void
    {
        $r = repsShiftPostPayoutSplit(0.25);
        $this->assertTrue($r['ok']);
        $this->assertSame(0.25, $r['body']['split'] ?? null);
    }

    public function testJoinshiftCookieJarPrefersAppMetaOverFile(): void
    {
        $dummy = "# Netscape HTTP Cookie File\napp.joinshift.us\tFALSE\t/\tTRUE\t0\tsb-test-auth-token\tphpunit-db-jar";
        $store = repsShiftStoreCookieJarInDb($dummy);
        $this->assertTrue($store['ok']);
        $this->assertSame($dummy, repsShiftCookieJarFromDb());
        $this->assertSame($dummy, repsShiftCookieJarText());
        $this->assertTrue(repsShiftHasCredentials());

        $prepared = repsShiftPrepareCookieFile();
        $this->assertNotNull($prepared);
        $this->assertTrue($prepared['ephemeral']);
        $this->assertFileExists($prepared['path']);
        $onDisk = trim((string) file_get_contents($prepared['path']));
        $this->assertSame($dummy, $onDisk);
        repsShiftReleaseCookieFile($prepared, $dummy);
        $this->assertFileDoesNotExist($prepared['path']);
        repsDashDb()->prepare('DELETE FROM app_meta WHERE key = ?')->execute([REPS_JOINSHIFT_COOKIE_META]);
    }
}
