<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Views × roles — single source for Slice A chrome + PRD §5.5.
 *
 * Legend for cells:
 * - full / own / book / self / limited / — (none)
 */

/** @return list<string> */
function repsDashAllRoles(): array
{
    return ['admin', 'ops', 'sales', 'business_owner', 'employee', 'individual', 'agent'];
}

/**
 * Nav keys each role may open (route access).
 *
 * @return list<string>
 */
function repsDashNavKeysForRole(string $role): array
{
    return match ($role) {
        'admin' => ['home', 'shops', 'operators', 'sessions', 'money', 'users', 'settings'],
        'ops' => ['home', 'shops', 'operators', 'sessions', 'money', 'settings'],
        'sales' => ['home', 'shops', 'operators', 'sessions', 'money', 'settings'],
        // Owner: "Shops" = their shop card (not a pipeline book).
        'business_owner' => ['home', 'shops', 'operators', 'sessions', 'money', 'settings'],
        'employee', 'individual' => ['home', 'sessions', 'settings'],
        // Agent is an API principal — not a human ops desk.
        'agent' => ['home', 'settings'],
        default => ['home', 'settings'],
    };
}

/**
 * Home blocks for a role (order = render order).
 *
 * @return list<string>
 */
function repsDashHomeBlocksForRole(string $role): array
{
    return match ($role) {
        'admin', 'ops' => ['shop_metrics', 'shops_attention', 'apply_leads', 'signed_in'],
        'sales' => ['shop_metrics', 'shops_attention', 'apply_leads', 'signed_in'],
        'business_owner' => ['shop_metrics', 'shops_attention', 'team_pulse', 'signed_in'],
        'employee', 'individual' => ['personal_metrics', 'recent_sessions', 'signed_in'],
        'agent' => ['agent_stub', 'signed_in'],
        default => ['signed_in'],
    };
}

/**
 * Settings panels for a role.
 *
 * @return list<string>
 */
function repsDashSettingsPanelsForRole(string $role): array
{
    return match ($role) {
        'admin' => ['skin', 'sync', 'platform'],
        'ops' => ['skin', 'sync'],
        'sales', 'business_owner' => ['skin'],
        'employee', 'individual' => ['skin'],
        'agent' => ['platform'],
        default => ['skin'],
    };
}

/** Money page: which economic columns a role may see. */
function repsDashMoneyModeForRole(string $role): string
{
    return match ($role) {
        'admin', 'ops' => 'dsc_full',       // gross + DSC share + shop share + lane
        'sales' => 'affiliate_book',        // book hours/gross/shop pay — no DSC take column
        'business_owner' => 'owner_shop',   // own shop hours/gross/your pay — no DSC take
        default => 'none',
    };
}

function repsDashCanSeePartnerCode(?array $user = null): bool
{
    $user = $user ?? repsDashCurrentUser();
    if (!$user) {
        return false;
    }
    return in_array($user['role'], ['admin', 'ops', 'sales', 'agent'], true);
}

function repsDashScopeBlurb(string $role): string
{
    return match ($role) {
        'admin' => 'Full desk: all shops, hours, economics, and user provisioning.',
        'ops' => 'Same operational desk as admin, without user provisioning or platform keys.',
        'sales' => 'Your assigned shops plus the unassigned pool — pipeline and hours for that book only.',
        'business_owner' => 'Your shop only: roster, hours, and your shop’s pay display.',
        'employee' => 'Your own sessions and hours — not the shop ledger or other workers.',
        'individual' => 'Your own capture/work only — no shop book.',
        'agent' => 'API/service principal — no human ops screens (use /dashboard/api/).',
        default => 'Limited scope.',
    };
}

/**
 * Matrix rows for Access page + PRD.
 * Each row: view key, label, purpose, then role => cell label.
 *
 * @return list<array<string, mixed>>
 */
function repsDashViewsRolesMatrix(): array
{
    $roles = repsDashAllRoles();
    $cells = static function (array $map) use ($roles): array {
        $out = [];
        foreach ($roles as $r) {
            $out[$r] = $map[$r] ?? '—';
        }
        return $out;
    };

    return [
        [
            'view' => 'home',
            'label' => 'Home',
            'purpose' => 'Landing pulse for this seat',
            'cells' => $cells([
                'admin' => 'All-shop pulse + leads',
                'ops' => 'All-shop pulse + leads',
                'sales' => 'Book pulse + leads',
                'business_owner' => 'My shop pulse + team',
                'employee' => 'My hours only',
                'individual' => 'My hours only',
                'agent' => 'API stub only',
            ]),
        ],
        [
            'view' => 'shops',
            'label' => 'Shops',
            'purpose' => 'Shop directory / pipeline',
            'cells' => $cells([
                'admin' => 'All shops',
                'ops' => 'All shops',
                'sales' => 'Assigned + unassigned',
                'business_owner' => 'Own shop (read)',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'operators',
            'label' => 'Operators',
            'purpose' => 'Workers on shops you can see',
            'cells' => $cells([
                'admin' => 'All',
                'ops' => 'All',
                'sales' => 'Book shops',
                'business_owner' => 'Own shop roster',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'sessions',
            'label' => 'Sessions',
            'purpose' => 'Hours / capture rows',
            'cells' => $cells([
                'admin' => 'All',
                'ops' => 'All',
                'sales' => 'Book shops',
                'business_owner' => 'Own shop',
                'employee' => 'Self only',
                'individual' => 'Self only',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'money',
            'label' => 'Money',
            'purpose' => 'Period economics display (not payroll)',
            'cells' => $cells([
                'admin' => 'Full (DSC + shop)',
                'ops' => 'Full (DSC + shop)',
                'sales' => 'Book · no DSC take',
                'business_owner' => 'Own shop pay',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'users',
            'label' => 'Users',
            'purpose' => 'Provision DSC / platform seats',
            'cells' => $cells([
                'admin' => 'Full',
                'ops' => '—',
                'sales' => '—',
                'business_owner' => '— (team invite = Slice B)',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'settings',
            'label' => 'Settings',
            'purpose' => 'Skin + sync / platform (by role)',
            'cells' => $cells([
                'admin' => 'Skin + sync + platform',
                'ops' => 'Skin + sync',
                'sales' => 'Skin only',
                'business_owner' => 'Skin only',
                'employee' => 'Skin only',
                'individual' => 'Skin only',
                'agent' => 'Platform stubs',
            ]),
        ],
        [
            'view' => 'partner_code',
            'label' => 'Partner code (C6N9T7)',
            'purpose' => 'Shift partner identifier in chrome',
            'cells' => $cells([
                'admin' => 'Yes',
                'ops' => 'Yes',
                'sales' => 'Yes',
                'business_owner' => '—',
                'employee' => '—',
                'individual' => '—',
                'agent' => 'Yes (API)',
            ]),
        ],
        [
            'view' => 'apply_leads',
            'label' => 'Apply leads (home card)',
            'purpose' => 'Inbound join applications',
            'cells' => $cells([
                'admin' => 'Yes',
                'ops' => 'Yes',
                'sales' => 'Yes',
                'business_owner' => '—',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'api',
            'label' => 'HTTP API',
            'purpose' => '/dashboard/api/* (Slice D+)',
            'cells' => $cells([
                'admin' => 'Session + key',
                'ops' => 'Session',
                'sales' => 'Session (scoped)',
                'business_owner' => 'Session (scoped)',
                'employee' => 'Session (self)',
                'individual' => 'Session (self)',
                'agent' => 'API key (primary)',
            ]),
        ],
    ];
}
