<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MicropsMapTest extends TestCase
{
    public function testSplitFullName(): void
    {
        $this->assertSame(['Mark', 'Hopkins'], repsMicropsSplitFullName('Mark Hopkins'));
        $this->assertSame(['Jess', 'Marie Poll'], repsMicropsSplitFullName('Jess Marie Poll'));
        $this->assertSame(['', ''], repsMicropsSplitFullName('  '));
    }

    public function testMapUsesMatchingPartnerCodeNotGmCode(): void
    {
        $data = [
            'sessions' => [
                [
                    'session_id' => 'map_sess_1',
                    'user_id' => 'u-1',
                    'user_full_name' => 'Ada Worker',
                    'length_seconds' => 3600,
                    'date_recorded' => '2026-08-05T15:00:00-05:00',
                    'uploaded_at' => '2026-08-05T16:00:00-05:00',
                    'rejection_reason' => null,
                ],
            ],
        ];
        $perUser = [
            'users' => [
                ['user_id' => 'u-1', 'accepted_hours' => 0.5],
            ],
        ];
        $feed = repsMicropsMapHoursFeed($data, $perUser, 'C6N9T7');
        $this->assertSame('C6N9T7', $feed['partnerCode']);
        $this->assertSame('microps', $feed['source']);
        $this->assertCount(1, $feed['sessions']);
        $s = $feed['sessions'][0];
        $this->assertSame('Ada', $s['first_name']);
        $this->assertSame('Worker', $s['last_name']);
        $this->assertSame('completed', $s['status']);
        $this->assertEqualsWithDelta(0.5, (float) $s['accepted_hours'], 0.0001);
        $this->assertEqualsWithDelta(1.0, (float) $s['duration_hours'], 0.0001);
        $this->assertSame('C6N9T7', $s['partner_code']);
    }

    public function testNullRejectionReasonIsScaledNotFullyAccepted(): void
    {
        $data = [
            'sessions' => [
                [
                    'session_id' => 'over_a',
                    'user_id' => 'u-scale',
                    'user_full_name' => 'Scale Me',
                    'length_seconds' => 3600,
                    'rejection_reason' => null,
                    'date_recorded' => '2026-08-01T10:00:00-05:00',
                    'uploaded_at' => '2026-08-01T11:00:00-05:00',
                ],
                [
                    'session_id' => 'over_b',
                    'user_id' => 'u-scale',
                    'user_full_name' => 'Scale Me',
                    'length_seconds' => 3600,
                    'rejection_reason' => null,
                    'date_recorded' => '2026-08-01T12:00:00-05:00',
                    'uploaded_at' => '2026-08-01T13:00:00-05:00',
                ],
            ],
        ];
        $perUser = [
            'users' => [
                ['user_id' => 'u-scale', 'accepted_hours' => 1.0],
            ],
        ];
        $feed = repsMicropsMapHoursFeed($data, $perUser, 'C6N9T7');
        $sum = 0.0;
        foreach ($feed['sessions'] as $s) {
            $this->assertSame('completed', $s['status']);
            $sum += (float) $s['accepted_hours'];
        }
        $this->assertEqualsWithDelta(1.0, $sum, 0.0001);
        $this->assertNotEquals(2.0, $sum);
    }

    public function testRejectedRowGetsZeroAccepted(): void
    {
        $data = [
            'sessions' => [
                [
                    'session_id' => 'rej_1',
                    'user_id' => 'u-rej',
                    'user_full_name' => 'Rej Ect',
                    'length_seconds' => 1800,
                    'rejection_reason' => 'REJECTED_HEALTH_TOO_LOW',
                    'date_recorded' => '2026-08-02T10:00:00-05:00',
                    'uploaded_at' => '2026-08-02T10:30:00-05:00',
                ],
            ],
        ];
        $feed = repsMicropsMapHoursFeed($data, [
            'users' => [['user_id' => 'u-rej', 'accepted_hours' => 0.0]],
        ], 'C6N9T7');
        $this->assertSame('rejected', $feed['sessions'][0]['status']);
        $this->assertSame(0.0, $feed['sessions'][0]['accepted_hours']);
        $this->assertSame('REJECTED_HEALTH_TOO_LOW', $feed['sessions'][0]['rejection_reason']);
    }

    public function testExtractGmCode(): void
    {
        $this->assertSame('M3WRBU', repsMicropsExtractGmCode(['gm' => ['code' => 'M3WRBU']]));
        $this->assertSame('M3WRBU', repsMicropsExtractGmCode(['gms' => [['code' => 'M3WRBU']]]));
        $this->assertSame('', repsMicropsExtractGmCode([]));
    }

    public function testHttpMockAndCookieJar(): void
    {
        $this->assertStringContainsString('microps-cookies', repsMicropsCookieJarPath());
        repsMicropsSetHttpMock(static function (string $method, string $path, ?array $json): array {
            unset($json);
            if ($method === 'GET' && str_starts_with($path, '/api/auth/me')) {
                return ['ok' => true, 'status' => 200, 'body' => ['gm' => ['code' => 'M3WRBU']]];
            }
            return ['ok' => false, 'status' => 500, 'error' => 'unexpected ' . $path];
        });
        try {
            $me = repsMicropsGetAuthMe();
            $this->assertTrue($me['ok']);
            $this->assertSame('M3WRBU', $me['body']['gm']['code'] ?? null);
        } finally {
            repsMicropsSetHttpMock(null);
        }
    }

    public function testMappedFeedFromFakeClient(): void
    {
        $res = repsMicropsGetMappedHoursFeed();
        $this->assertTrue($res['ok'] ?? false);
        $this->assertSame('C6N9T7', $res['body']['partnerCode'] ?? null);
        $ids = array_column($res['body']['sessions'] ?? [], 'session_id');
        $this->assertContains('fake_sess_1', $ids);
        $this->assertContains('fake_sess_2', $ids);
        $this->assertSame('M3WRBU', (string) repsDashAppMetaGet('shift.microps_gm_code', ''));
    }
}
