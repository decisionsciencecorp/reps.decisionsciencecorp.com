<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LeadsCrmTest extends TestCase
{
    public function testJoinKindFromPath(): void
    {
        $this->assertSame('shop', repsDashJoinKindFromPath('company'));
        $this->assertSame('operator', repsDashJoinKindFromPath('on_job'));
        $this->assertSame('operator', repsDashJoinKindFromPath('at_home'));
    }

    public function testGraduateRoleMap(): void
    {
        $this->assertSame('individual', repsDashGraduateRoleForJoinKind('operator'));
        $this->assertSame('business_owner', repsDashGraduateRoleForJoinKind('shop'));
        $this->assertSame('sales', repsDashGraduateRoleForJoinKind('affiliate'));
    }

    public function testReferralAssignsClaimed(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Ref Lead',
            'phone' => '2145550001',
            'email' => 'ref@example.com',
            'path' => 'on_job',
            'metro' => 'Dallas',
            'notes' => '',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $this->assertTrue($r['ok']);
        $lead = $r['lead'];
        $this->assertSame('jim', $lead['assigned_sales_rep']);
        $this->assertSame('referral', $lead['assign_source']);
        $this->assertSame('claimed', $lead['status']);
        $this->assertSame('operator', $lead['join_kind']);
    }

    public function testRoundRobinCyclesSales(): void
    {
        $pool = repsDashSalesUsernames();
        $this->assertNotEmpty($pool);
        $seen = [];
        for ($i = 0; $i < count($pool) + 1; $i++) {
            $r = repsDashCreateApplyLead([
                'name' => 'RR ' . $i,
                'phone' => '21455501' . $i,
                'email' => 'rr' . $i . '@example.com',
                'path' => 'at_home',
                'expectations_ack' => 1,
            ]);
            $this->assertTrue($r['ok'], $r['error'] ?? '');
            $seen[] = $r['lead']['assigned_sales_rep'];
            $this->assertSame('round_robin', $r['lead']['assign_source']);
            $this->assertSame('claimed', $r['lead']['status']);
        }
        $this->assertContains($pool[0], $seen);
    }

    public function testAffiliateGoesAdminQueue(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Partner Hopeful',
            'phone' => '4695559999',
            'email' => 'partner@example.com',
            'path' => 'affiliate',
            'join_kind' => 'affiliate',
            'notes' => 'DFW network',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $this->assertTrue($r['ok']);
        $this->assertNull($r['lead']['assigned_sales_rep']);
        $this->assertSame('none', $r['lead']['assign_source']);
        $this->assertSame('open', $r['lead']['status']);
        $this->assertSame('affiliate', $r['lead']['join_kind']);
    }

    public function testGraduateOperatorCreatesIndividual(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Grad Me',
            'phone' => '8175551212',
            'email' => 'gradme@example.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
            'affiliate_code' => 'seven',
        ]);
        $this->assertTrue($r['ok']);
        $admin = repsDashFindUserByUsername('mark');
        $this->assertNotNull($admin);
        $g = repsDashGraduateLeadToUser((int) $r['id'], $admin);
        $this->assertTrue($g['ok'], $g['error'] ?? '');
        $this->assertSame('individual', $g['user']['role']);
        $this->assertNotEmpty($g['temp_password']);
        $again = repsDashGraduateLeadToUser((int) $r['id'], $admin);
        $this->assertTrue($again['ok']);
        $this->assertNull($again['temp_password']);
    }

    public function testGraduateShopAndAffiliateRoles(): void
    {
        $shop = repsDashCreateApplyLead([
            'name' => 'Shop Co',
            'phone' => '2145550100',
            'email' => 'shopco@example.com',
            'path' => 'company',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $admin = repsDashFindUserByUsername('mark');
        $g = repsDashGraduateLeadToUser((int) $shop['id'], $admin);
        $this->assertTrue($g['ok'], $g['error'] ?? '');
        $this->assertSame('business_owner', $g['user']['role']);

        $aff = repsDashCreateApplyLead([
            'name' => 'Aff Grad',
            'phone' => '2145550101',
            'email' => 'affgrad@example.com',
            'join_kind' => 'affiliate',
            'path' => 'affiliate',
            'expectations_ack' => 1,
        ]);
        $jim = repsDashFindUserByUsername('jim');
        $this->assertFalse(repsDashCanGraduateLead($jim, $aff['lead']));
        $g2 = repsDashGraduateLeadToUser((int) $aff['id'], $admin);
        $this->assertTrue($g2['ok'], $g2['error'] ?? '');
        $this->assertSame('sales', $g2['user']['role']);
    }

    public function testSalesCannotGraduateAffiliate(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Aff',
            'phone' => '9725550000',
            'email' => 'aff@example.com',
            'join_kind' => 'affiliate',
            'path' => 'affiliate',
            'expectations_ack' => 1,
        ]);
        $jim = repsDashFindUserByUsername('jim');
        $this->assertFalse(repsDashCanGraduateLead($jim, $r['lead']));
        $denied = repsDashGraduateLeadToUser((int) $r['id'], $jim);
        $this->assertFalse($denied['ok']);
    }

    public function testWebhookPayloadHmacShape(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Hook',
            'phone' => '2145557777',
            'email' => 'hook@example.com',
            'path' => 'company',
            'expectations_ack' => 1,
            'affiliate_code' => 'chuck',
        ]);
        $chuck = repsDashFindUserByUsername('chuck');
        $json = repsDashWebhookPayload('created', (int) $r['id'], (int) $chuck['id'], 'test');
        $data = json_decode($json, true);
        $this->assertSame('created', $data['event']);
        $this->assertSame('shop', $data['join_kind']);
        $this->assertSame('chuck', $data['assigned_sales_rep']);
        $this->assertSame('chuck', $data['actor_username']);
        $sig = hash_hmac('sha256', $json, 'secret');
        $this->assertSame(64, strlen($sig));
    }

    public function testEmitWebhookNoopAndSignedAttempt(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Emit',
            'phone' => '2145556666',
            'email' => 'emit@example.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        putenv('REPS_LEADS_WEBHOOK_URL=');
        putenv('REPS_LEADS_WEBHOOK_SECRET=');
        $this->assertFalse(repsDashEmitLeadWebhook('note', (int) $r['id'], null, 'noop'));

        putenv('REPS_LEADS_WEBHOOK_URL=http://127.0.0.1:1/webhook');
        putenv('REPS_LEADS_WEBHOOK_SECRET=test-secret');
        // Connection fails fast; still exercises HMAC + POST path.
        $this->assertFalse(repsDashEmitLeadWebhook('note', (int) $r['id'], null, 'signed'));
        putenv('REPS_LEADS_WEBHOOK_URL=');
        putenv('REPS_LEADS_WEBHOOK_SECRET=');
    }

    public function testLeadEventsCreated(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Events',
            'phone' => '2145558888',
            'email' => 'events@example.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $events = repsDashListLeadEvents((int) $r['id']);
        $types = array_column($events, 'event_type');
        $this->assertContains('created', $types);
        $this->assertContains('assigned', $types);
        $jim = repsDashFindUserByUsername('jim');
        $note = repsDashAddLeadEvent((int) $r['id'], 'note', 'Hello', (int) $jim['id']);
        $this->assertTrue($note['ok']);
        $bad = repsDashAddLeadEvent((int) $r['id'], 'nope', 'x', null);
        $this->assertFalse($bad['ok']);
        $missing = repsDashAddLeadEvent(999999, 'note', 'x', null);
        $this->assertFalse($missing['ok']);
    }

    public function testSalesCannotSeeAffiliateLeads(): void
    {
        $r = repsDashCreateApplyLead([
            'name' => 'Hidden Aff',
            'phone' => '2145550002',
            'email' => 'hiddenaff@example.com',
            'join_kind' => 'affiliate',
            'path' => 'affiliate',
            'expectations_ack' => 1,
        ]);
        $this->assertTrue($r['ok']);
        $jim = repsDashFindUserByUsername('jim');
        $mark = repsDashFindUserByUsername('mark');
        $this->assertFalse(repsDashCanViewAffiliateLeads($jim));
        $this->assertTrue(repsDashCanViewAffiliateLeads($mark));
        $this->assertFalse(repsDashCanViewLead($jim, $r['lead']));
        $this->assertTrue(repsDashCanViewLead($mark, $r['lead']));

        $jimList = repsDashListApplyLeadsForUser($jim, null, null, true);
        foreach ($jimList as $l) {
            $this->assertNotSame('affiliate', $l['join_kind']);
        }
        $this->assertSame([], repsDashListApplyLeadsForUser($jim, null, 'affiliate', true));
        $this->assertSame([], repsDashListApplyLeadsForUser($jim, null, null, true, 'affiliate'));

        $adminAff = repsDashListApplyLeadsForUser($mark, null, null, false, 'affiliate');
        $this->assertNotEmpty($adminAff);
        foreach ($adminAff as $l) {
            $this->assertSame('affiliate', $l['path']);
        }
    }

    public function testAdminPathFilterOnJob(): void
    {
        repsDashCreateApplyLead([
            'name' => 'Path On Job',
            'phone' => '2145550003',
            'email' => 'pathonjob@example.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $mark = repsDashFindUserByUsername('mark');
        $rows = repsDashListApplyLeadsForUser($mark, null, null, false, 'on_job');
        $this->assertNotEmpty($rows);
        foreach ($rows as $l) {
            $this->assertSame('on_job', $l['path']);
            $this->assertNotSame('affiliate', $l['join_kind']);
        }
    }

    public function testSalesQueueScopingAndFeedBadge(): void
    {
        repsDashCreateApplyLead([
            'name' => 'Jim Only',
            'phone' => '2145553333',
            'email' => 'jimonly@example.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
            'affiliate_code' => 'jim',
        ]);
        $jim = repsDashFindUserByUsername('jim');
        $seven = repsDashFindUserByUsername('seven');
        $mark = repsDashFindUserByUsername('mark');
        $jimLeads = repsDashListApplyLeadsForUser($jim, null, null, true);
        foreach ($jimLeads as $l) {
            $this->assertSame('jim', $l['assigned_sales_rep']);
        }
        $sevenMine = repsDashListApplyLeadsForUser($seven, null, null, true);
        foreach ($sevenMine as $l) {
            $this->assertSame('seven', $l['assigned_sales_rep']);
        }
        $allAdmin = repsDashListApplyLeadsForUser($mark, 'claimed', 'operator', false);
        $this->assertNotEmpty($allAdmin);

        $feed = repsDashListLeadFeedForUser($jim, 5);
        $this->assertNotEmpty($feed);
        $adminFeed = repsDashListLeadFeedForUser($mark, 5);
        $this->assertNotEmpty($adminFeed);
        $emptyFeed = repsDashListLeadFeedForUser(['role' => 'individual', 'username' => 'x', 'id' => 0], 5);
        $this->assertSame([], $emptyFeed);

        repsDashMarkLeadsSeen($jim);
        $badgeAfter = repsDashLeadsBadgeCount($jim);
        $this->assertSame(0, $badgeAfter);

        // Backdate seen so a new event counts as "newer".
        repsDashAppMetaSet(repsDashLeadsSeenMetaKey((int) $jim['id']), '2000-01-01 00:00:00');
        repsDashAppMetaSet(repsDashLeadsSeenMetaKey((int) $mark['id']), '2000-01-01 00:00:00');
        repsDashAddLeadEvent((int) $jimLeads[0]['id'], 'called', 'Ping', (int) $jim['id']);
        $this->assertGreaterThan(0, repsDashLeadsBadgeCount($jim));
        $this->assertGreaterThan(0, repsDashLeadsBadgeCount($mark));
        $this->assertSame(0, repsDashLeadsBadgeCount(['role' => 'individual', 'id' => 1, 'username' => 'x']));
    }

    public function testCreateLeadValidation(): void
    {
        $this->assertFalse(repsDashCreateApplyLead([
            'name' => '',
            'phone' => '1',
            'email' => 'a@b.com',
            'path' => 'on_job',
            'expectations_ack' => 1,
        ])['ok']);
        $this->assertFalse(repsDashCreateApplyLead([
            'name' => 'N',
            'phone' => '1',
            'email' => 'bad',
            'path' => 'on_job',
            'expectations_ack' => 1,
        ])['ok']);
        $this->assertFalse(repsDashCreateApplyLead([
            'name' => 'N',
            'phone' => '1',
            'email' => 'ok@example.com',
            'path' => 'on_job',
            'expectations_ack' => 0,
        ])['ok']);
        $this->assertFalse(repsDashCreateApplyLead([
            'name' => 'N',
            'phone' => '1',
            'email' => 'ok2@example.com',
            'path' => 'weird',
            'expectations_ack' => 1,
        ])['ok']);
    }

    public function testAppMetaAndRrHelpers(): void
    {
        repsDashAppMetaSet('test_key', 'v1');
        $this->assertSame('v1', repsDashAppMetaGet('test_key'));
        $this->assertSame('fallback', repsDashAppMetaGet('missing_key_xyz', 'fallback'));
        $this->assertTrue(repsDashIsActiveSalesUsername('jim'));
        $this->assertFalse(repsDashIsActiveSalesUsername(''));
        $this->assertFalse(repsDashIsActiveSalesUsername('nobody'));
        $pick = repsDashNextRoundRobinSalesUsername();
        $this->assertNotNull($pick);
        $this->assertSame('leads_seen_at_9', repsDashLeadsSeenMetaKey(9));
        repsDashMarkLeadsSeen(['id' => 0]); // no-op
    }

    public function testGraduateMissingLead(): void
    {
        $admin = repsDashFindUserByUsername('mark');
        $r = repsDashGraduateLeadToUser(999999, $admin);
        $this->assertFalse($r['ok']);
    }
}
