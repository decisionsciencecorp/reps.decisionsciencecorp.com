<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/** Roles that get first-run Home wizard + Education Center. */
function repsDashUsesLearnerChrome(string $role): bool
{
    return in_array($role, ['sales', 'business_owner', 'employee', 'individual'], true);
}

function repsDashOnboardingState(?array $user = null): string
{
    $user = $user ?? repsDashCurrentUser();
    if (!$user || !repsDashUsesLearnerChrome((string) $user['role'])) {
        return 'done';
    }
    $state = $_SESSION['reps_dash_onboarding'] ?? null;
    if ($state === 'wizard' || $state === 'done') {
        return $state;
    }
    // First visit this session → wizard for learner seats.
    return 'wizard';
}

function repsDashSetOnboardingState(string $state): void
{
    if (!in_array($state, ['wizard', 'done'], true)) {
        return;
    }
    $_SESSION['reps_dash_onboarding'] = $state;
    $user = repsDashCurrentUser();
    if ($user) {
        $_SESSION['reps_dash_onboarding_user'] = (string) $user['username'];
    }
}

function repsDashIsWizardHome(?array $user = null): bool
{
    return repsDashOnboardingState($user) === 'wizard';
}

/**
 * Wizard steps for a role (Home tour).
 *
 * @return list<array{id: string, title: string, body?: string, body_html?: string, panel?: string, cta?: string, href?: string}>
 */
function repsDashWizardStepsForRole(string $role, ?array $user = null): array
{
    return match ($role) {
        'sales' => repsDashSalesWizardSteps($user),
        'business_owner' => [
            [
                'id' => 'welcome',
                'title' => 'Welcome — your shop desk',
                'body' => 'This seat is for your business only: team, hours, and what your shop keeps. You won’t see other shops or DSC’s internal ledger.',
            ],
            [
                'id' => 'shop',
                'title' => 'My shop',
                'body' => 'Status and notes for your location live under My shop.',
                'cta' => 'Open My shop',
                'href' => '/dashboard/shops.php',
            ],
            [
                'id' => 'team',
                'title' => 'Team',
                'body' => 'Invite and open workers here. Tap a name for acceptance, rejection reasons, and day-by-day activity.',
                'cta' => 'Open Team',
                'href' => '/dashboard/operators.php',
            ],
            [
                'id' => 'pay',
                'title' => 'My pay',
                'body' => 'See what your shop keeps and who produced hours. Set up the shop’s Stripe payout bank account on that page (not during signup).',
                'cta' => 'Open My pay',
                'href' => '/dashboard/money.php',
            ],
            [
                'id' => 'edu',
                'title' => 'Education Center',
                'body' => 'Onboarding your crew, headset tips, and why footage gets rejected — all in Education Center.',
                'cta' => 'Open Education',
                'href' => '/dashboard/education.php',
            ],
            [
                'id' => 'done',
                'title' => 'Tour complete',
                'body' => 'Finish to land on your normal home screen for the shop.',
            ],
        ],
        'individual' => [
            [
                'id' => 'welcome',
                'title' => 'Welcome — your personal desk',
                'body' => 'You’re capturing on your own (not under a shop book). This tour covers sessions, payout setup, and where to learn the basics.',
            ],
            [
                'id' => 'sessions',
                'title' => 'My sessions',
                'body' => 'Accepted and rejected captures show here. Open a day or session row to see duration, accepted hours, and rejection reasons.',
                'cta' => 'Open My sessions',
                'href' => '/dashboard/sessions.php',
            ],
            [
                'id' => 'pay',
                'title' => 'My pay',
                'body' => 'Link your bank for solo capture payouts through Stripe. This stays on My pay in the dashboard — never on the public join form.',
                'cta' => 'Open My pay',
                'href' => '/dashboard/money.php',
            ],
            [
                'id' => 'edu',
                'title' => 'Education Center',
                'body' => 'Hands in frame, steady camera, FPS, and other acceptance tips live in Education Center — revisit anytime.',
                'cta' => 'Open Education',
                'href' => '/dashboard/education.php',
            ],
            [
                'id' => 'done',
                'title' => 'You’re set',
                'body' => 'Finish the tour for your normal home screen (your hours and recent sessions).',
            ],
        ],
        'employee' => [
            [
                'id' => 'welcome',
                'title' => 'Welcome — your worker desk',
                'body' => 'You’re on a shop team. This tour covers your hours and where to learn capture basics. Your shop handles payouts outside Reps.',
            ],
            [
                'id' => 'sessions',
                'title' => 'My sessions',
                'body' => 'Accepted and rejected captures show here. Open a day or session row to see duration, accepted hours, and rejection reasons.',
                'cta' => 'Open My sessions',
                'href' => '/dashboard/sessions.php',
            ],
            [
                'id' => 'edu',
                'title' => 'Education Center',
                'body' => 'Hands in frame, steady camera, FPS, and other acceptance tips live in Education Center — revisit anytime.',
                'cta' => 'Open Education',
                'href' => '/dashboard/education.php',
            ],
            [
                'id' => 'done',
                'title' => 'You’re set',
                'body' => 'Finish the tour for your normal home screen (your hours and recent sessions).',
            ],
        ],
        default => [
            [
                'id' => 'done',
                'title' => 'No wizard for this seat',
                'body' => 'Admin, ops, and agent seats go straight to the normal desk.',
            ],
        ],
    };
}

/**
 * Sales / affiliate seat — Home wizard (landing page is step 2–3).
 *
 * @return list<array{id: string, title: string, body?: string, body_html?: string, panel?: string, cta?: string, href?: string}>
 */
function repsDashSalesWizardSteps(?array $user): array
{
    $info = repsDashAffiliatePageInfo($user);
    $pageUrl = $info['page_url'] ?? 'https://reps.decisionsciencecorp.com/a/your-username/';
    $joinUrl = $info['join_url'] ?? 'https://reps.decisionsciencecorp.com/join.php?rep=your-username';
    $code = $info['affiliate_code'] ?? 'your-username';
    $name = $info['display_name'] ?? 'Your name';

    return [
        [
            'id' => 'welcome',
            'title' => 'Welcome to Reps',
            'body' => 'You’re on the affiliate / sales seat. This tour covers how you recruit operators and shops, where your links live, and how money shows up in your book.',
        ],
        [
            'id' => 'affiliate_intro',
            'title' => 'Your public landing page',
            'body_html' => '<p>Every active sales seat gets a <strong>public intro page</strong> on the Reps marketing site. Send that link to prospects instead of asking them to hunt for a form or type a code.</p>'
                . '<ul class="mb-3">'
                . '<li><strong>Landing page</strong> — branded intro with your name; the apply button already credits you.</li>'
                . '<li><strong>Direct apply link</strong> — shorter URL straight to the join form with your code filled in.</li>'
                . '<li><strong>Affiliate code</strong> — your username; used only if someone applies from the main site without your link.</li>'
                . '</ul>'
                . '<p class="mb-0 small text-muted">Full reference anytime under <a href="/dashboard/help.php?page=affiliate-page">Help → Affiliate landing page</a>.</p>',
        ],
        [
            'id' => 'affiliate_links',
            'title' => 'Your links (copy & share)',
            'body_html' => '<p class="mb-3">These are <strong>your</strong> live links. Copy and text them, drop them in email, or paste into social bios. Applications from either link attribute to <code>'
                . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                . '</code>.</p>',
            'panel' => 'affiliate_page',
        ],
        [
            'id' => 'affiliate_setup',
            'title' => 'How the page is configured',
            'body_html' => '<p>There is no separate page editor. Two fields control what prospects see:</p>'
                . '<table class="table table-sm align-middle mb-3">'
                . '<thead><tr><th>Setting</th><th>What it controls</th><th>How to change</th></tr></thead>'
                . '<tbody>'
                . '<tr><td><strong>Username</strong><br><code>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '<td>URL path — <code>/a/' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '/</code></td>'
                . '<td class="small">Set when your seat is created. Ask admin if you need a different slug.</td></tr>'
                . '<tr><td><strong>Display name</strong><br>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>Headline on the page (“Invited by …”)</td>'
                . '<td class="small">Admin updates under Users → your seat.</td></tr>'
                . '</tbody></table>'
                . '<p class="mb-0">After this tour, the same copy panel stays on <strong>Home</strong>, <strong>Money</strong>, and <strong>Settings</strong>. '
                . 'Bookmark <a href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">your landing page</a> or save the direct apply link: '
                . '<a href="' . htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') . '</a>.</p>',
            'cta' => 'Read Help chapter',
            'href' => '/dashboard/help.php?page=affiliate-page',
        ],
        [
            'id' => 'shops',
            'title' => 'Your pipeline is Shops',
            'body' => 'Assigned shops plus the unassigned pool live under Shops. That’s where you pitch, track status, and pick up new leads after someone applies.',
            'cta' => 'Open Shops',
            'href' => '/dashboard/shops.php',
        ],
        [
            'id' => 'money',
            'title' => 'Money is your book view',
            'body' => 'Earnings estimates for shops you own and individuals you sourced live under Money. Your landing-page panel is also at the top of this screen.',
            'cta' => 'Open Money',
            'href' => '/dashboard/money.php',
        ],
        [
            'id' => 'edu',
            'title' => 'Education Center',
            'body' => 'Pitch notes, capture basics, and reject coaching live in Education Center whenever you need a refresher.',
            'cta' => 'Open Education',
            'href' => '/dashboard/education.php',
        ],
        [
            'id' => 'done',
            'title' => 'You’re ready',
            'body' => 'Finish the tour to open your normal home screen. Replay it from Settings, or open Help → Affiliate landing page when you need the link reference again.',
        ],
    ];
}

