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
        $this->assertFalse(repsShiftIsLiveJoinshiftBase());
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
        $sessions = repsDashDbSessionsAsRows();
        $this->assertNotEmpty($sessions);
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
}
