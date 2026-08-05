<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * Education Center catalog — Shift for Business app FAQ + reject help
 * (mined from app.joinshift.us dashboard chunks 2026-08-05) plus DSC field
 * coaching (Mark↔Seven / partner onboarding calls).
 *
 * @return list<array{id: string, section: string, title: string, body: string, tags: list<string>, roles: list<string>, source: string}>
 */
function repsDashEducationCatalog(): array
{
    $allLearners = ['sales', 'business_owner', 'employee', 'individual'];
    $workers = ['employee', 'individual', 'business_owner', 'sales'];
    $owners = ['business_owner', 'sales'];
    $sales = ['sales'];

    return [
        // —— Setup (Shift Get started + Team invite) ——
        [
            'id' => 'setup-app',
            'section' => 'Setup',
            'title' => 'Install the Shift app',
            'body' => 'Download the Shift app on the phone you’ll record with, then open it and sign up. Owners: add each person on the Team page with their name and phone — Shift texts them a sign-in link (QR works too). They sign in with that same number, then record with the headset.',
            'tags' => ['setup', 'app', 'team'],
            'roles' => $allLearners,
            'source' => 'Shift app · Get started / FAQ',
        ],
        [
            'id' => 'setup-headset',
            'section' => 'Setup',
            'title' => 'Set up your headset',
            'body' => 'Shift’s in-app walkthrough is about two minutes — do it before your first real session so framing isn’t a surprise. Wear or mount the device so your hands stay in the work area. Headsets for partner rollouts should come through Shift (not a random third-party mount) so inventory and support stay visible.',
            'tags' => ['setup', 'headset'],
            'roles' => $allLearners,
            'source' => 'Shift app · walkthrough + partner ops',
        ],
        [
            'id' => 'setup-partner-codes',
            'section' => 'Setup',
            'title' => 'Partner code vs install paperwork',
            'body' => 'Each business needs its own Shift partner code (example on DSC’s account: C6N9T7). That code ties workers and hours to the Partner book. Separate paperwork codes used only to download the app are not the same thing — don’t mix them when onboarding a shop.',
            'tags' => ['setup', 'partner', 'sales'],
            'roles' => array_values(array_unique(array_merge($sales, $owners))),
            'source' => 'Shift dashboard + DSC research Doc #818',
        ],
        [
            'id' => 'setup-match',
            'section' => 'Setup',
            'title' => 'Why hours don’t show yet',
            'body' => 'Business must be approved, and the member must be matched to their recording account on Team. A nightly job also auto-matches. Stats can lag. Workers: ask your owner. Owners: confirm match on Team; Shift support can help if it’s still missing.',
            'tags' => ['setup', 'hours', 'team'],
            'roles' => $allLearners,
            'source' => 'Shift app · FAQ',
        ],

        // —— How to record ——
        [
            'id' => 'record-what',
            'section' => 'How to record',
            'title' => 'What to record',
            'body' => 'Your normal workday with the headset on and your hands in frame. About two hours a day is recommended (one morning block, one afternoon), and you can start and stop whenever you need.',
            'tags' => ['capture', 'hours'],
            'roles' => $workers,
            'source' => 'Shift app · FAQ',
        ],
        [
            'id' => 'record-hands',
            'section' => 'How to record',
            'title' => 'Keep hands visible',
            'body' => 'Hands out of frame for a meaningful stretch cuts accepted quality (and can tank the session). Aim the camera at the task, not your face. If you’re fighting the angle, use a second screen (below).',
            'tags' => ['capture', 'reject', 'hands'],
            'roles' => $allLearners,
            'source' => 'Shift reject catalog + field coaching',
        ],
        [
            'id' => 'record-mirror',
            'section' => 'How to record',
            'title' => 'Screen-mirror to aim faster (field tip)',
            'body' => 'Most people have a spare phone or can cast to a TV/computer. Mirror the recording preview on a second device so you can see whether hands are in frame without guessing. This is the fastest way to stop burning hours on bad angles — especially the first day on a new task.',
            'tags' => ['capture', 'coaching', 'hands'],
            'roles' => $allLearners,
            'source' => 'Mark field coaching (partner + Seven teach-back)',
        ],
        [
            'id' => 'record-overheat',
            'section' => 'How to record',
            'title' => 'Phone overheat / vignette (Galaxy tip)',
            'body' => 'Some multi-lens Android phones vignette or crop the preview when they overheat — the usable frame shrinks and hands fall out of view, which shows up as low health / low acceptance. Cool the phone, close background apps, take short breaks, or switch devices. Prefer a phone that holds a clean full frame.',
            'tags' => ['capture', 'device', 'reject', 'coaching'],
            'roles' => $allLearners,
            'source' => 'Mark field coaching (Shift partner call)',
        ],
        [
            'id' => 'record-teachback',
            'section' => 'How to record',
            'title' => 'Learn it so you can teach it',
            'body' => 'Sales and coaches: put the headset on yourself first. Walk the app, record a short real task, then hand the kit to the next person. Goal isn’t a one-time demo — it’s so you can explain install, framing, and rejects in plain language when a shop asks.',
            'tags' => ['coaching', 'sales', 'setup'],
            'roles' => ['sales', 'business_owner'],
            'source' => 'Mark↔Seven coaching (Omi 2026-08-04 afternoon)',
        ],

        // —— Reject catalog (Shift client help) ——
        [
            'id' => 'reject-hands',
            'section' => 'Why footage gets rejected',
            'title' => 'Hands not visible',
            'body' => 'Hands weren’t clearly visible for part of the recording. Quality is reduced for how long hands were out of frame. Fix: remount, use mirroring, keep the task in view.',
            'tags' => ['reject', 'hands'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-camera',
            'section' => 'Why footage gets rejected',
            'title' => 'Camera quality degraded',
            'body' => 'Dirty lens, poor lighting, or unsteady framing. Clean the lens, stabilize the mount, improve light.',
            'tags' => ['reject', 'camera'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-tasks',
            'section' => 'Why footage gets rejected',
            'title' => 'Off-task footage',
            'body' => 'Parts of the recording weren’t identified as the assigned task. Stay on the work you’re supposed to capture; pause when you leave the task.',
            'tags' => ['reject', 'task'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-health',
            'section' => 'Why footage gets rejected',
            'title' => 'Health too low (REJECTED_HEALTH_TOO_LOW)',
            'body' => 'Combined operator-health score — hand visibility, camera quality, and on-task time — fell below the minimum. Improving any of those raises the score. This is the most common reject on DSC’s live book.',
            'tags' => ['reject', 'health'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help + live hours-feed',
        ],
        [
            'id' => 'reject-iv',
            'section' => 'Why footage gets rejected',
            'title' => 'Instruction violation review (REJECTED_REVIEWED_IV)',
            'body' => 'Flagged by the instruction-violation classifier — awaiting or completed admin review. Hours don’t count toward payout until review concludes. Don’t try to “game” the classifier; fix framing and task relevance.',
            'tags' => ['reject', 'review'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-fps',
            'section' => 'Why footage gets rejected',
            'title' => 'Low FPS (under 27.5)',
            'body' => 'Device couldn’t hold the required frame rate (minimum 27.5 FPS). Usually hardware or performance — close background apps or use a newer device.',
            'tags' => ['reject', 'fps', 'device'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-blurry',
            'section' => 'Why footage gets rejected',
            'title' => 'Blurry video',
            'body' => 'Recording too blurry to process. Clean the lens and hold the device steady.',
            'tags' => ['reject', 'blur'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-stationary',
            'section' => 'Why footage gets rejected',
            'title' => 'Stationary device',
            'body' => 'Motion sensors said the device didn’t move. You must wear or hold it while recording — don’t leave it propped on a shelf.',
            'tags' => ['reject', 'imu'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-imu',
            'section' => 'Why footage gets rejected',
            'title' => 'IMU / motion sensor issue',
            'body' => 'Motion sensor reported movement too slowly or out of sync with the video. Almost always a device/firmware problem, not operator error. Re-record; if it repeats, flag the device for replacement or firmware update.',
            'tags' => ['reject', 'imu', 'device'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-short',
            'section' => 'Why footage gets rejected',
            'title' => 'Under 60 seconds',
            'body' => 'Recordings under the 60-second minimum are rejected. Plan a full task pass before you stop.',
            'tags' => ['reject', 'duration'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-blank',
            'section' => 'Why footage gets rejected',
            'title' => 'Blank / black / white frames',
            'body' => 'Sampled frames were completely black or white — usually a covered lens or camera not capturing. Uncover the camera and verify preview before you start.',
            'tags' => ['reject', 'camera'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-corrupt',
            'section' => 'Why footage gets rejected',
            'title' => 'Corrupted / bad upload',
            'body' => 'Recording didn’t save correctly — missing files, bad metadata, bad IMU data, or duplicate/overlapping upload. Re-record.',
            'tags' => ['reject', 'upload'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
        ],
        [
            'id' => 'reject-quarantine',
            'section' => 'Why footage gets rejected',
            'title' => 'Quarantined fraud (QUARANTINED_FRAUD)',
            'body' => 'Session flagged/quarantined. Hours don’t count until review. Seen live on DSC’s Partner book — treat as serious; don’t coach workers to work around it.',
            'tags' => ['reject', 'fraud'],
            'roles' => $allLearners,
            'source' => 'Shift hours-feed (live codes)',
        ],

        // —— Money / team FAQ ——
        [
            'id' => 'faq-pay-worker',
            'section' => 'Pay & team',
            'title' => 'When do workers get paid?',
            'body' => 'Your business pays you for accepted footage. Timing and amounts: ask your owner or Shift support. In Reps, workers see their own sessions; owners see My pay.',
            'tags' => ['pay', 'worker'],
            'roles' => ['employee', 'individual'],
            'source' => 'Shift app · FAQ',
        ],
        [
            'id' => 'faq-pay-owner',
            'section' => 'Pay & team',
            'title' => 'When and how does the business get paid?',
            'body' => 'Shift pays the business via Wise — add bank info in Shift Settings. Pass the employee split to your team from Settings. Payout scheduling details: Shift team.',
            'tags' => ['pay', 'owner'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ',
        ],
        [
            'id' => 'faq-split',
            'section' => 'Pay & team',
            'title' => 'Employee payout split',
            'body' => 'In Shift Settings, the employee split sets what % of the payment you pass to the team. Rates differ by business; Shift support has specifics. Owners can enable a split when they sign the contract.',
            'tags' => ['pay', 'split'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ / Settings',
        ],
        [
            'id' => 'faq-acceptance-drop',
            'section' => 'Pay & team',
            'title' => 'Why did acceptance drop?',
            'body' => 'Usually hands weren’t in frame, the task wasn’t relevant, or camera/device health was poor. Open the worker in Reps (Money or Team) → rejection reasons are the coaching script.',
            'tags' => ['reject', 'coaching'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ',
        ],
        [
            'id' => 'faq-ol',
            'section' => 'Pay & team',
            'title' => 'How onboarding a business works (ops lead)',
            'body' => 'An Operations Lead demos the equipment, helps the business register on Shift, installs the app, and sets up the team. Account-specific issues go to Shift support. Sales seats in Reps: use Shops + Money; Education Center for teach-back.',
            'tags' => ['sales', 'onboarding'],
            'roles' => $sales,
            'source' => 'Shift app · FAQ',
        ],

        // —— Reps seat primers (keep / merge prior) ——
        [
            'id' => 'reps-sales',
            'section' => 'Your Reps seat',
            'title' => 'Sales desk in Reps',
            'body' => 'Own the shop pipeline and book economics. Shops = status; Money = earnings and who’s producing. No session inbox — open a producer from Money for worker drill-down.',
            'tags' => ['reps', 'sales'],
            'roles' => $sales,
            'source' => 'Reps Slice A',
        ],
        [
            'id' => 'reps-owner',
            'section' => 'Your Reps seat',
            'title' => 'Owner desk in Reps',
            'body' => 'Team, sessions, and My pay are scoped to your shop. Invite workers by phone in Shift Team (Reps mirrors the roster). Tap a name for acceptance, rejects, and day drill-down.',
            'tags' => ['reps', 'owner'],
            'roles' => ['business_owner'],
            'source' => 'Reps Slice A',
        ],
        [
            'id' => 'reps-worker',
            'section' => 'Your Reps seat',
            'title' => 'Worker / individual desk in Reps',
            'body' => 'Home and Sessions show only your work. Match reject reason codes here to the reject cards above. Replay the Home wizard anytime from Settings.',
            'tags' => ['reps', 'worker'],
            'roles' => ['employee', 'individual'],
            'source' => 'Reps Slice A',
        ],
    ];
}

/**
 * @return list<array{id: string, section: string, title: string, body: string, tags: list<string>, roles: list<string>, source: string}>
 */
function repsDashEducationArticlesForRole(string $role): array
{
    $out = [];
    foreach (repsDashEducationCatalog() as $row) {
        if (in_array($role, $row['roles'], true)) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * Map raw Shift rejection_reason → education article id (when known).
 */
function repsDashEducationIdForRejectReason(string $reason): ?string
{
    $r = strtoupper(trim($reason));
    return match (true) {
        str_contains($r, 'HEALTH') => 'reject-health',
        str_contains($r, 'REVIEWED_IV') || str_contains($r, 'INSTRUCTION') => 'reject-iv',
        str_contains($r, 'QUARANTINE') || str_contains($r, 'FRAUD') => 'reject-quarantine',
        str_contains($r, 'FPS') => 'reject-fps',
        str_contains($r, 'BLUR') => 'reject-blurry',
        str_contains($r, 'STATION') => 'reject-stationary',
        str_contains($r, 'IMU') => 'reject-imu',
        str_contains($r, 'SHORT') || str_contains($r, '60') => 'reject-short',
        str_contains($r, 'BLANK') || str_contains($r, 'BLACK') => 'reject-blank',
        str_contains($r, 'CORRUPT') => 'reject-corrupt',
        str_contains($r, 'HAND') => 'reject-hands',
        default => null,
    };
}
