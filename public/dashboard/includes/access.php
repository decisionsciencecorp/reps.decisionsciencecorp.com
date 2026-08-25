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
        // users = Users dropdown host (roster + worker match). shift_match stays for route ACL.
        'admin' => ['home', 'shops', 'leads', 'operators', 'sessions', 'money', 'users', 'shift_match', 'help', 'settings'],
        'ops' => ['home', 'shops', 'leads', 'operators', 'sessions', 'money', 'users', 'shift_match', 'help', 'settings'],
        // Sales = pipeline (Shops) + Money. Operators appear inside Money, not a roster desk.
        // No session drill-down / vids.
        'sales' => ['home', 'shops', 'leads', 'money', 'education', 'help', 'settings'],
        // Owner: "Shops" = their shop card (not a pipeline book).
        'business_owner' => ['home', 'shops', 'operators', 'sessions', 'money', 'education', 'help', 'settings'],
        // Solo operators get My pay (Connect payout setup). Shop employees do not — shop keeps capture $.
        'individual' => ['home', 'sessions', 'money', 'education', 'help', 'settings'],
        'employee' => ['home', 'sessions', 'education', 'help', 'settings'],
        // Agent is an API principal — Help carries API book; desk is minimal.
        'agent' => ['home', 'help', 'settings'],
        default => ['home', 'help', 'settings'],
    };
}

/**
 * Help map pages this role may open (CRM/Tasks-style in-app docs).
 *
 * @return list<string> page slugs
 */
function repsDashHelpPagesForRole(string $role): array
{
    $common = ['overview', 'desks', 'roles'];
    return match ($role) {
        'admin' => array_merge($common, ['affiliate-page', 'users', 'shift', 'money', 'api', 'api-shift', 'sync', 'troubleshooting']),
        'ops' => array_merge($common, ['affiliate-page', 'users', 'shift', 'money', 'api', 'api-shift', 'sync', 'troubleshooting']),
        'agent' => ['overview', 'roles', 'api', 'api-shift', 'troubleshooting'],
        'sales' => array_merge($common, ['affiliate-page', 'money', 'api-session']),
        'business_owner' => array_merge($common, ['money', 'api-session']),
        'individual' => array_merge($common, ['money', 'api-session']),
        'employee' => array_merge($common, ['api-session']),
        default => $common,
    };
}

/** @return bool */
function repsDashCanSeeHelpPage(string $slug, ?array $user = null): bool
{
    $user = $user ?? repsDashCurrentUser();
    if (!$user) {
        return false;
    }
    return in_array($slug, repsDashHelpPagesForRole((string) $user['role']), true);
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
        'sales' => ['skin', 'affiliate_page'],
        'business_owner' => ['skin'],
        'employee', 'individual' => ['skin'],
        'agent' => ['platform'],
        default => ['skin'],
    };
}

/**
 * Money page peer mode — each role gets a distinct redesign (not one table).
 *
 * - dsc_command: admin portfolio ledger
 * - ops_pulse: production / reject-drag monitoring
 * - affiliate_book: sales earnings + producers
 * - owner_payout: single-shop keep + team contribution
 * - solo_payout: individual capture pay + Connect bank setup
 */
function repsDashMoneyModeForRole(string $role): string
{
    return match ($role) {
        'admin' => 'dsc_command',
        'ops' => 'ops_pulse',
        'sales' => 'affiliate_book',
        'business_owner' => 'owner_payout',
        'individual' => 'solo_payout',
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
        'ops' => 'Same operational desk as admin, without user provisioning or platform keys. Users menu → Worker match only.',
        'sales' => 'Pipeline (Shops) plus Money for your book — shops and individuals you sourced. No session inbox.',
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
            'purpose' => 'Landing pulse (learners: wizard first, then normal)',
            'cells' => $cells([
                'admin' => 'Normal desk (no wizard)',
                'ops' => 'Normal desk (no wizard)',
                'sales' => 'Wizard → book pulse + leads',
                'business_owner' => 'Wizard → shop pulse + team',
                'employee' => 'Wizard → my hours',
                'individual' => 'Wizard → my hours',
                'agent' => 'API stub only',
            ]),
        ],
        [
            'view' => 'education',
            'label' => 'Education Center',
            'purpose' => 'Partner FAQ + reject catalog + field coaching (learner seats)',
            'cells' => $cells([
                'admin' => '—',
                'ops' => '—',
                'sales' => 'Yes',
                'business_owner' => 'Yes',
                'employee' => 'Yes',
                'individual' => 'Yes',
                'agent' => '—',
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
            'view' => 'leads',
            'label' => 'Leads CRM',
            'purpose' => 'Join funnel queue + activity desk',
            'cells' => $cells([
                'admin' => 'All queues + affiliate + path filters',
                'ops' => 'All queues + affiliate + path filters',
                'sales' => 'My operator/shop queue only',
                'business_owner' => '—',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'operators',
            'label' => 'Operators (nav)',
            'purpose' => 'Standalone roster desk',
            'cells' => $cells([
                'admin' => 'All',
                'ops' => 'All',
                'sales' => '— (see Money)',
                'business_owner' => 'Own shop roster',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'sessions',
            'label' => 'Sessions',
            'purpose' => 'Individual capture / hours rows (not sales desk)',
            'cells' => $cells([
                'admin' => 'All',
                'ops' => 'All',
                'sales' => '—',
                'business_owner' => 'Own shop',
                'employee' => 'Self only',
                'individual' => 'Self only',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'session_media',
            'label' => 'Session video / media',
            'purpose' => 'Playback of capture clips',
            'cells' => $cells([
                'admin' => 'Only if Partner API exposes it*',
                'ops' => 'Only if Partner API exposes it*',
                'sales' => '—',
                'business_owner' => 'Only if Partner API exposes it*',
                'employee' => 'Self · if API allows*',
                'individual' => 'Self · if API allows*',
                'agent' => 'API only*',
            ]),
        ],
        [
            'view' => 'money',
            'label' => 'Money',
            'purpose' => 'Economics (+ sales: who produces in the book)',
            'cells' => $cells([
                'admin' => 'DSC portfolio command',
                'ops' => 'Hours health + reject drag',
                'sales' => 'Affiliate book $ + shop & individual producers',
                'business_owner' => 'My pay (shop keep + Connect bank)',
                'employee' => '—',
                'individual' => 'My pay (solo capture + Connect bank)',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'users',
            'label' => 'Users (dropdown)',
            'purpose' => 'Roster + Worker match under Users menu',
            'cells' => $cells([
                'admin' => 'Roster + Worker match',
                'ops' => 'Worker match (no provisioning)',
                'sales' => '—',
                'business_owner' => '— (team invite coming)',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'shift_match',
            'label' => 'Worker match',
            'purpose' => 'Link Partner workers ↔ Reps seats (under Users)',
            'cells' => $cells([
                'admin' => 'Yes',
                'ops' => 'Yes',
                'sales' => '—',
                'business_owner' => '—',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'help',
            'label' => 'Help',
            'purpose' => 'In-app docs (API gated by role)',
            'cells' => $cells([
                'admin' => 'Full + API + Partner proxy',
                'ops' => 'Full + API + Partner proxy',
                'sales' => 'Desk + session API note',
                'business_owner' => 'Desk + session API note',
                'employee' => 'Desk + session API note',
                'individual' => 'Desk + session API note',
                'agent' => 'API book + Partner proxy',
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
            'purpose' => 'Partner identifier in chrome',
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
            'label' => 'Apply leads (home + Leads nav)',
            'purpose' => 'Inbound join applications desk',
            'cells' => $cells([
                'admin' => 'Full desk',
                'ops' => 'Full desk',
                'sales' => 'Claim / work',
                'business_owner' => '—',
                'employee' => '—',
                'individual' => '—',
                'agent' => '—',
            ]),
        ],
        [
            'view' => 'api',
            'label' => 'HTTP API',
            'purpose' => 'JSON API for integrations',
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
