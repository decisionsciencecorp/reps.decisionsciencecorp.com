<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShiftSyncTest extends TestCase
{
    public function testIngestFeedCreatesOperatorsAndSessions(): void
    {
        $feed = [
            'partnerCode' => 'C6N9T7',
            'sessions' => [
                [
                    'session_id' => 'sync_sess_a',
                    'user_id' => 'shift-user-aaa',
                    'first_name' => 'Ada',
                    'last_name' => 'Worker',
                    'status' => 'completed',
                    'duration_hours' => 1.0,
                    'accepted_hours' => 0.8,
                    'rejection_reason' => '',
                    'partner_code' => 'C6N9T7',
                    'created_at' => '2026-08-05T15:00:00-05:00',
                    'completed_at' => '2026-08-05T16:00:00-05:00',
                ],
                [
                    'session_id' => 'sync_sess_b',
                    'user_id' => 'shift-user-bbb',
                    'first_name' => 'Bob',
                    'last_name' => 'Cam',
                    'status' => 'rejected',
                    'duration_hours' => 0.5,
                    'accepted_hours' => 0.0,
                    'rejection_reason' => 'REJECTED_HEALTH_TOO_LOW',
                    'partner_code' => 'C6N9T7',
                    'created_at' => '2026-08-06T10:00:00-05:00',
                    'completed_at' => '2026-08-06T10:30:00-05:00',
                ],
            ],
        ];
        $team = [
            'members' => [
                [
                    'id' => 'mem-a',
                    'userId' => 'shift-user-aaa',
                    'name' => 'Ada Worker',
                    'phone' => '+15551212',
                ],
            ],
        ];

        $r = repsShiftIngestFeed($feed, $team, null);
        $this->assertTrue($r['ok']);
        $this->assertSame('C6N9T7', $r['partner_code']);
        $this->assertGreaterThanOrEqual(2, $r['sessions_upserted']);
        $this->assertTrue(repsDashLiveDataEnabled());

        $op = repsOperatorByShiftUserId('shift-user-aaa');
        $this->assertNotNull($op);
        $this->assertSame('Ada Worker', $op['display_name']);
        $this->assertSame('+15551212', $op['phone']);

        $sessions = repsDashDbSessionsAsRows();
        $ids = array_column($sessions, 'session_id');
        $this->assertContains('sync_sess_a', $ids);
        $this->assertContains('sync_sess_b', $ids);
    }

    public function testMatchAndUnmatchUser(): void
    {
        $feed = [
            'partnerCode' => 'C6N9T7',
            'sessions' => [
                [
                    'session_id' => 'match_sess_1',
                    'user_id' => 'shift-match-user-1',
                    'first_name' => 'Match',
                    'last_name' => 'Me',
                    'status' => 'completed',
                    'duration_hours' => 1.0,
                    'accepted_hours' => 1.0,
                    'rejection_reason' => '',
                    'completed_at' => '2026-08-07T12:00:00-05:00',
                ],
            ],
        ];
        repsShiftIngestFeed($feed);

        $op = repsOperatorByShiftUserId('shift-match-user-1');
        $this->assertNotNull($op);

        repsDashDb()->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, role)
             VALUES (?, ?, ?, ?, ?)'
        )->execute(['match_seat_' . uniqid(), 'm@example.com', 'x', 'Match Seat', 'individual']);
        $userId = (int) repsDashDb()->lastInsertId();

        $m = repsOperatorMatchUser((int) $op['id'], $userId, 1, 'test');
        $this->assertTrue($m['ok']);

        $op2 = repsOperatorById((int) $op['id']);
        $this->assertSame($userId, (int) $op2['matched_user_id']);

        $u = repsDashDb()->query('SELECT operator_id FROM users WHERE id = ' . $userId)->fetchColumn();
        $this->assertSame((int) $op['id'], (int) $u);

        $un = repsOperatorUnmatch((int) $op['id'], 1);
        $this->assertTrue($un['ok']);
        $op3 = repsOperatorById((int) $op['id']);
        $this->assertEmpty($op3['matched_user_id']);
    }

    public function testRepositoryUsesLiveAfterIngest(): void
    {
        putenv('REPS_DASH_FORCE_MOCK');
        unset($_ENV['REPS_DASH_FORCE_MOCK']);
        $feed = [
            'partnerCode' => 'C6N9T7',
            'sessions' => [
                [
                    'session_id' => 'live_repo_sess',
                    'user_id' => 'shift-live-repo',
                    'first_name' => 'Live',
                    'last_name' => 'Repo',
                    'status' => 'completed',
                    'duration_hours' => 0.5,
                    'accepted_hours' => 0.5,
                    'rejection_reason' => '',
                    'completed_at' => '2026-08-07T14:00:00-05:00',
                ],
            ],
        ];
        repsShiftIngestFeed($feed);
        $ops = repsDashAllOperators();
        $names = array_column($ops, 'name');
        $this->assertContains('Live Repo', $names);
        $found = false;
        foreach (repsDashAllSessions() as $s) {
            if (($s['session_id'] ?? '') === 'live_repo_sess') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testEmptyFeedRefusedWhenLocalSessionsExist(): void
    {
        $feed = [
            'partnerCode' => 'C6N9T7',
            'sessions' => [
                [
                    'session_id' => 'guard_sess_keep',
                    'user_id' => 'shift-guard-user',
                    'first_name' => 'Guard',
                    'last_name' => 'Keep',
                    'status' => 'completed',
                    'duration_hours' => 1.0,
                    'accepted_hours' => 1.0,
                    'rejection_reason' => '',
                    'completed_at' => '2026-08-07T14:00:00-05:00',
                ],
            ],
        ];
        $ok = repsShiftIngestFeed($feed);
        $this->assertTrue($ok['ok']);
        $before = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
        $this->assertGreaterThan(0, $before);

        $empty = [
            'partnerCode' => 'C6N9T7',
            'sessions' => [],
            'bannedUserIds' => [],
        ];
        $refused = repsShiftIngestFeed($empty);
        $this->assertFalse($refused['ok']);
        $this->assertSame('empty_feed_refused', $refused['error'] ?? null);
        $this->assertTrue($refused['refused'] ?? false);
        $after = (int) repsDashDb()->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
        $this->assertSame($before, $after);

        $forced = repsShiftIngestFeed($empty, null, null, ['allow_empty_sessions' => true]);
        $this->assertTrue($forced['ok']);
        $this->assertSame(0, (int) ($forced['sessions_upserted'] ?? -1));
    }

    public function testPartnerMismatchRefused(): void
    {
        repsShiftIngestFeed([
            'partnerCode' => 'C6N9T7',
            'sessions' => [
                [
                    'session_id' => 'partner_guard_sess',
                    'user_id' => 'shift-partner-guard',
                    'first_name' => 'P',
                    'last_name' => 'G',
                    'status' => 'completed',
                    'duration_hours' => 0.1,
                    'accepted_hours' => 0.1,
                    'rejection_reason' => '',
                    'completed_at' => '2026-08-07T15:00:00-05:00',
                ],
            ],
        ]);
        $bad = repsShiftIngestFeed([
            'partnerCode' => 'OTHER99',
            'sessions' => [
                [
                    'session_id' => 'evil_sess',
                    'user_id' => 'evil',
                    'first_name' => 'Evil',
                    'last_name' => 'Feed',
                    'status' => 'completed',
                    'duration_hours' => 9.0,
                    'accepted_hours' => 9.0,
                    'rejection_reason' => '',
                    'completed_at' => '2026-08-07T16:00:00-05:00',
                ],
            ],
        ]);
        $this->assertFalse($bad['ok']);
        $this->assertSame('partner_mismatch', $bad['error'] ?? null);
        $this->assertNull(repsOperatorByShiftUserId('evil'));
    }
}
