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
 * @return list<array{id: string, title: string, body: string, cta?: string, href?: string}>
 */
function repsDashWizardStepsForRole(string $role): array
{
    return match ($role) {
        'sales' => [
            [
                'id' => 'welcome',
                'title' => 'Welcome to Reps',
                'body' => 'You’re on the affiliate / sales seat. This short tour shows where your book lives and how money works — then you can jump into the normal home pulse.',
            ],
            [
                'id' => 'shops',
                'title' => 'Your pipeline is Shops',
                'body' => 'Assigned shops plus the unassigned pool live under Shops. That’s where you pitch, track status, and pick up new leads.',
                'cta' => 'Open Shops',
                'href' => '/dashboard/shops.php',
            ],
            [
                'id' => 'money',
                'title' => 'Money is your book view',
                'body' => 'Earnings estimates and who’s producing at each shop live under Money — not a separate Operators desk, and not a session inbox.',
                'cta' => 'Open Money',
                'href' => '/dashboard/money.php',
            ],
            [
                'id' => 'edu',
                'title' => 'Education Center',
                'body' => 'How-tos, pitch notes, and Shift capture basics stay in Education Center whenever you need a refresher.',
                'cta' => 'Open Education',
                'href' => '/dashboard/education.php',
            ],
            [
                'id' => 'done',
                'title' => 'You’re ready',
                'body' => 'Finish the tour to open your normal Home pulse. You can restart the tour anytime from Settings or Dev Mode.',
            ],
        ],
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
                'body' => 'See what your shop keeps and who produced hours. Names link into the same worker drill-down as Team.',
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
                'body' => 'Finish to land on your normal Home pulse for the shop.',
            ],
        ],
        'employee', 'individual' => [
            [
                'id' => 'welcome',
                'title' => $role === 'individual' ? 'Welcome — your personal desk' : 'Welcome — your worker desk',
                'body' => $role === 'individual'
                    ? 'You’re capturing on your own (not under a shop book). This tour covers sessions and where to learn the basics.'
                    : 'You’re on a shop team. This tour covers your hours and where to learn capture basics.',
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
                'body' => 'Finish the tour for your normal Home pulse (your hours and recent sessions).',
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

