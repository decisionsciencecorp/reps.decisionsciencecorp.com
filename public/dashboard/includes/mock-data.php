<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Slice A fixtures only — no ACL, no rollups.
 * Slice C: replace via repository.php (keep these for local/demo seeds if useful).
 */

/** @return list<array<string, mixed>> */
function repsDashMockShops(): array
{
    return [
        [
            'id' => 101,
            'name' => 'Empanada Empire — Richardson',
            'status' => 'active',
            'assigned_sales_rep' => 'mark',
            'contact_name' => 'Kitchen lead',
            'contact_phone' => '(214) 555-0101',
            'agreed_shop_split' => 0.0,
            'notes' => 'Internal DSC lane — 100% DSC economics (lorem).',
            'operators' => 3,
            'accepted_hours_7d' => 42.5,
            'reject_rate' => 0.08,
        ],
        [
            'id' => 102,
            'name' => 'Chuck’s Detail Garage',
            'status' => 'pitched',
            'assigned_sales_rep' => 'chuck',
            'contact_name' => 'Chuck',
            'contact_phone' => '(469) 555-0144',
            'agreed_shop_split' => 0.5,
            'notes' => 'Waiting on MicroAGI business paperwork (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
        [
            'id' => 103,
            'name' => 'Seven Mobile Detail',
            'status' => 'signed',
            'assigned_sales_rep' => 'seven',
            'contact_name' => 'Seven Stone',
            'contact_phone' => '(972) 555-0199',
            'agreed_shop_split' => 0.5,
            'notes' => 'Onboarding Moto G 5G + spare phone (lorem).',
            'operators' => 1,
            'accepted_hours_7d' => 6.2,
            'reject_rate' => 0.22,
        ],
        [
            'id' => 104,
            'name' => 'North Texas Fleet Wash',
            'status' => 'active',
            'assigned_sales_rep' => 'jim',
            'contact_name' => 'Maria Lopez',
            'contact_phone' => '(817) 555-0177',
            'agreed_shop_split' => 0.5,
            'notes' => 'Two headsets live; third delayed (lorem).',
            'operators' => 4,
            'accepted_hours_7d' => 61.0,
            'reject_rate' => 0.11,
        ],
        [
            'id' => 105,
            'name' => 'Lake Highlands Auto Spa',
            'status' => 'prospect',
            'assigned_sales_rep' => 'jim',
            'contact_name' => 'Dee Patel',
            'contact_phone' => '(214) 555-0120',
            'agreed_shop_split' => 0.5,
            'notes' => 'Warm intro from Jim — pitch Friday (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
        [
            'id' => 106,
            'name' => 'Unassigned Pool Shop',
            'status' => 'prospect',
            'assigned_sales_rep' => null,
            'contact_name' => 'TBD',
            'contact_phone' => '',
            'agreed_shop_split' => 0.5,
            'notes' => 'Inbound apply lead — not yet assigned (lorem).',
            'operators' => 0,
            'accepted_hours_7d' => 0.0,
            'reject_rate' => 0.0,
        ],
    ];
}

/** @return list<array<string, mixed>> */
function repsDashMockOperators(): array
{
    return [
        // Fields mirror Shift Team + Worker surfaces (Doc #818 + live walk 2026-08-05).
        ['id' => 1, 'name' => 'Alex Rivera', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1001', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 18.5, 'rejected_7d' => 2.0, 'last_session' => '2026-08-04 14:12'],
        ['id' => 2, 'name' => 'Jordan Lee', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1002', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 14.0, 'rejected_7d' => 1.5, 'last_session' => '2026-08-04 11:40'],
        ['id' => 3, 'name' => 'Sam Okonkwo', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1003', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 16.0, 'rejected_7d' => 3.0, 'last_session' => '2026-08-03 19:05'],
        ['id' => 4, 'name' => 'Casey Nguyen', 'shop_id' => 104, 'shop' => 'North Texas Fleet Wash', 'phone' => '(817) 555-1004', 'status' => 'invited', 'matched' => false, 'assigned_sales_rep' => null, 'accepted_7d' => 0.0, 'rejected_7d' => 0.0, 'last_session' => '—'],
        ['id' => 5, 'name' => 'Seven Stone', 'shop_id' => 103, 'shop' => 'Seven Mobile Detail', 'phone' => '(972) 555-0199', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 6.2, 'rejected_7d' => 1.8, 'last_session' => '2026-08-04 16:01'],
        ['id' => 6, 'name' => 'Kit Empanada A', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2001', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 20.0, 'rejected_7d' => 1.0, 'last_session' => '2026-08-04 15:22'],
        ['id' => 7, 'name' => 'Kit Empanada B', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2002', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 12.5, 'rejected_7d' => 2.0, 'last_session' => '2026-08-04 09:18'],
        ['id' => 8, 'name' => 'Kit Empanada C', 'shop_id' => 101, 'shop' => 'Empanada Empire — Richardson', 'phone' => '(214) 555-2003', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 10.0, 'rejected_7d' => 0.5, 'last_session' => '2026-08-03 21:44'],
        // Solo individuals — shop_id 0; affiliate attribution via assigned_sales_rep (not a shop).
        ['id' => 9, 'name' => 'Pat Solo', 'shop_id' => 0, 'shop' => '— (individual)', 'phone' => '(469) 555-0188', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => 'jim', 'accepted_7d' => 9.5, 'rejected_7d' => 1.0, 'last_session' => '2026-08-04 13:55'],
        ['id' => 10, 'name' => 'Riley Mobile', 'shop_id' => 0, 'shop' => '— (individual)', 'phone' => '(214) 555-0190', 'status' => 'invited', 'matched' => false, 'assigned_sales_rep' => 'seven', 'accepted_7d' => 0.0, 'rejected_7d' => 0.0, 'last_session' => '—'],
        ['id' => 11, 'name' => 'Unaffiliated Solo', 'shop_id' => 0, 'shop' => '— (individual)', 'phone' => '(972) 555-0191', 'status' => 'active', 'matched' => true, 'assigned_sales_rep' => null, 'accepted_7d' => 4.0, 'rejected_7d' => 0.5, 'last_session' => '2026-08-04 10:00'],
    ];
}

/** @return list<array<string, mixed>> */
function repsDashMockSessions(): array
{
    $rows = [];
    $ops = repsDashMockOperators();
    // Status vocabulary matches Shift hours-feed: completed | rejected (+ pending for in-flight mock).
    $statuses = ['completed', 'completed', 'completed', 'rejected', 'pending'];
    $reasons = ['', '', '', 'REJECTED_HEALTH_TOO_LOW', '', 'REJECTED_REVIEWED_IV', 'QUARANTINED_FRAUD'];
    $n = 1;
    foreach ($ops as $op) {
        if ($op['status'] !== 'active') {
            continue;
        }
        for ($i = 0; $i < 5; $i++) {
            $st = $statuses[($n + $i) % count($statuses)];
            $hours = round(0.4 + (($n + $i) % 5) * 0.35, 2);
            $accepted = $st === 'completed' ? round($hours * (0.55 + (($n + $i) % 4) * 0.1), 2) : 0.0;
            $day = 1 + (($n + $i) % 5);
            $hour = 3 + ($i * 2);
            $rows[] = [
                'session_id' => 'sess_mock_' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'operator_id' => (int) $op['id'],
                'operator' => $op['name'],
                'shop' => $op['shop'],
                'shop_id' => $op['shop_id'],
                'status' => $st,
                'duration_hours' => $hours,
                'accepted_hours' => $accepted,
                'rejection_reason' => $st === 'rejected' ? $reasons[($n + $i) % count($reasons)] ?: 'REJECTED_HEALTH_TOO_LOW' : '',
                'partner_code' => 'C6N9T7',
                'completed_at' => sprintf('2026-08-%02d %02d:%02d', $day, $hour, 10 + $i),
                'day' => sprintf('2026-08-%02d', $day),
            ];
            $n++;
        }
    }
    return $rows;
}
