<?php
declare(strict_types=1);

if (!defined('REPS_DASH_LOADED')) {
    die('Direct access not permitted');
}

/**
 * @return array{type: string, src: string, poster: string, caption: string}
 */
function repsDashEduVideo(string $mp4, string $poster, string $caption = ''): array
{
    return [
        'type' => 'video',
        'src' => '/assets/video/web/' . $mp4,
        'poster' => '/assets/video/web/' . $poster,
        'caption' => $caption,
    ];
}

/**
 * Education Center catalog — Shift FAQ/reject help + DSC field coaching +
 * Reps marketing capture multimedia (`/assets/video/web/`).
 *
 * Card list shows title + teaser; full write-up lives on education-article.php.
 *
 * @return list<array<string, mixed>>
 */
function repsDashEducationCatalog(): array
{
    $allLearners = ['sales', 'business_owner', 'employee', 'individual'];
    $workers = ['employee', 'individual', 'business_owner', 'sales'];
    $owners = ['business_owner', 'sales'];
    $sales = ['sales'];

    return [
        [
            'id' => 'setup-app',
            'section' => 'Setup',
            'title' => 'Install the Shift app',
            'teaser' => 'Download Shift on the phone you’ll record with, sign up, and (for teams) get invited by name + phone.',
            'tags' => ['setup', 'app', 'team'],
            'roles' => $allLearners,
            'source' => 'Shift app · Get started / FAQ',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Real capture work — install before your first session.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Everything starts on the phone that will sit in the headset. Install the Shift app there — not on a spare phone you leave in a drawer.'],
                ['type' => 'h', 'text' => 'If you’re joining as a worker'],
                ['type' => 'ol', 'items' => [
                    'Install Shift from the App Store / Play Store (or the QR your coach shows you).',
                    'Sign up with the same phone number your owner used when they invited you.',
                    'Complete the short onboarding, then put the phone in the headset and run a practice take.',
                ]],
                ['type' => 'h', 'text' => 'If you’re an owner or sales coach'],
                ['type' => 'ol', 'items' => [
                    'Open Shift for Business → Team.',
                    'Add each person with their real name and phone. Shift texts them a sign-in link (QR works too).',
                    'Confirm they’re matched to a recording account before you expect hours to show.',
                ]],
                ['type' => 'callout', 'text' => 'Invite is phone/SMS — not email. Wrong number = silent failure.'],
            ],
        ],
        [
            'id' => 'setup-headset',
            'section' => 'Setup',
            'title' => 'Set up your headset',
            'teaser' => 'Two-minute in-app walkthrough, then mount so hands stay in the work — Shift-sourced gear only for partner rollouts.',
            'tags' => ['setup', 'headset'],
            'roles' => $allLearners,
            'source' => 'Shift app · walkthrough + partner ops',
            'media' => [
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Bright, sharp, unobstructed view of the work.'),
                repsDashEduVideo('hands-1.mp4', 'hands-poster.jpg', 'Mount so hands stay in frame.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Shift’s Get-started walkthrough is about two minutes. Do it before a paid session so framing isn’t a surprise on the clock.'],
                ['type' => 'h', 'text' => 'Mount check'],
                ['type' => 'ul', 'items' => [
                    'Phone locked in the approved headset / hat mount.',
                    'Lens clean and pointed at the task — not your face.',
                    'Preview shows both hands when you work (use a second screen if needed — see Screen-mirror).',
                ]],
                ['type' => 'h', 'text' => 'Partner rollout rule'],
                ['type' => 'p', 'text' => 'Headsets for DSC partner shops should come through Shift — not a random Amazon mount — so inventory, support, and firmware stay visible.'],
                ['type' => 'callout', 'text' => 'Apply / eligibility first on the marketing path; gear ships after clearance. Partner shops follow the Shift purchase path your OL gives you.'],
            ],
        ],
        [
            'id' => 'setup-partner-codes',
            'section' => 'Setup',
            'title' => 'Partner code vs install paperwork',
            'teaser' => 'Each business gets its own Shift partner code. Download paperwork codes are not the same thing.',
            'tags' => ['setup', 'partner', 'sales'],
            'roles' => array_values(array_unique(array_merge($sales, $owners))),
            'source' => 'Shift dashboard',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Hours only land when workers are tied to the right partner book.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Two different codes show up in the wild. Mixing them breaks onboarding.'],
                ['type' => 'ul', 'items' => [
                    'Partner code — shown under Settings when you’re on admin/ops. Ties workers and sessions to your Partner account.',
                    'Install / download paperwork code — only used to get the app from a specific download path. Not the book identifier.',
                ]],
                ['type' => 'callout', 'text' => 'Sales: every shop you onboard needs its own partner code from Shift. Don’t reuse one shop’s code across a whole city.'],
            ],
        ],
        [
            'id' => 'setup-match',
            'section' => 'Setup',
            'title' => 'Why hours don’t show yet',
            'teaser' => 'Business approved + member matched on Team. Nightly auto-match exists; stats can lag.',
            'tags' => ['setup', 'hours', 'team'],
            'roles' => $allLearners,
            'source' => 'Shift app · FAQ',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Recording happened — matching is what makes it appear on the desk.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'If someone recorded but the desk is empty, check matching before you rewrite the pitch.'],
                ['type' => 'ol', 'items' => [
                    'Is the business approved in Shift?',
                    'Is the member matched to their recording account on Team?',
                    'Wait for nightly auto-match / rollup lag, then re-check.',
                    'Still missing → Shift support (owners) or ask your owner (workers).',
                ]],
            ],
        ],
        [
            'id' => 'record-what',
            'section' => 'How to record',
            'title' => 'What to record',
            'teaser' => 'Normal workday, headset on, hands in frame. ~2 hours/day recommended — start/stop as needed.',
            'tags' => ['capture', 'hours'],
            'roles' => $workers,
            'source' => 'Shift app · FAQ + Reps marketing',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Continuous, natural work — not idle time.'),
                repsDashEduVideo('hands-2.mp4', 'hands-poster.jpg', 'Two-handed work stays visible.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Film the work you already do: kitchens, trades, warehouse, assembly, cleaning, cooking at home. Buyers pay for real hands-on activity — not posing for the camera.'],
                ['type' => 'ul', 'items' => [
                    'About two hours a day is the Shift recommendation (morning + afternoon blocks).',
                    'Start and stop whenever the real task starts and stops.',
                    'Stay on the assigned task; pause when you leave it.',
                ]],
                ['type' => 'callout', 'text' => 'Same coaching we use on the Reps marketing site: clear camera, hands visible, continuous real work.'],
            ],
        ],
        [
            'id' => 'record-hands',
            'section' => 'How to record',
            'title' => 'Keep hands visible',
            'teaser' => 'Hands out of frame for a meaningful stretch cuts accepted quality — and can tank the whole session.',
            'tags' => ['capture', 'reject', 'hands'],
            'roles' => $allLearners,
            'source' => 'Shift reject catalog + field coaching',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'Both hands in frame as much as possible.'),
                repsDashEduVideo('hands-1.mp4', 'hands-poster.jpg', 'Hands in frame while the task moves.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Hand visibility is the single biggest lever most new operators control. Aim at the work, not your face. If the preview loses your hands, remount before you grind another hour.'],
                ['type' => 'ul', 'items' => [
                    'Shift reduces quality proportional to how long hands are out of frame.',
                    'Combined “health too low” often includes hand visibility as a component.',
                    'Use screen-mirroring on day one — don’t guess.',
                ]],
            ],
        ],
        [
            'id' => 'record-mirror',
            'section' => 'How to record',
            'title' => 'Screen-mirror to aim faster',
            'teaser' => 'Cast or mirror the preview to a second phone/TV so you can see framing without guessing.',
            'tags' => ['capture', 'coaching', 'hands'],
            'roles' => $allLearners,
            'source' => 'Mark field coaching (partner + Seven teach-back)',
            'media' => [
                repsDashEduVideo('camera-1.mp4', 'camera-poster.jpg', 'Steady capture — verify on a second screen while you learn the angle.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Most people already own a cracked spare phone or can cast to a TV. Mirror the Shift preview there while you work. You’ll stop burning sessions on bad angles within one practice block.'],
                ['type' => 'ol', 'items' => [
                    'Start recording / preview on the headset phone.',
                    'Mirror or cast to a second device in front of you.',
                    'Adjust mount until hands + task fill the frame.',
                    'Once muscle memory sticks, you can drop the second screen.',
                ]],
                ['type' => 'callout', 'text' => 'Field tip from Mark’s partner coaching: this saves people hours of lost acceptance on day one.'],
            ],
        ],
        [
            'id' => 'record-overheat',
            'section' => 'How to record',
            'title' => 'Phone overheat / vignette',
            'teaser' => 'Some Galaxy phones crop the frame when hot — hands fall out and health tanks.',
            'tags' => ['capture', 'device', 'reject', 'coaching'],
            'roles' => $allLearners,
            'source' => 'Mark field coaching (Shift partner call)',
            'media' => [
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Full clean frame — not a vignetted crop.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Multi-lens Androids can vignette or shrink the usable frame when they overheat. Hands disappear from the edges even though you’re still working — and Shift scores that as low health.'],
                ['type' => 'ul', 'items' => [
                    'Cool the phone; take short breaks between takes.',
                    'Close background apps that cook the SoC.',
                    'Prefer a device that holds a full, clean preview under load.',
                ]],
            ],
        ],
        [
            'id' => 'record-teachback',
            'section' => 'How to record',
            'title' => 'Learn it so you can teach it',
            'teaser' => 'Sales and coaches: wear the headset yourself first, then hand the kit to the next person.',
            'tags' => ['coaching', 'sales', 'setup'],
            'roles' => ['sales', 'business_owner'],
            'source' => 'Mark↔Seven coaching (Omi 2026-08-04 afternoon)',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'You should be able to demo “hands in frame” live.'),
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Walk the app end-to-end before you coach a shop.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Mark’s coaching with Seven: the point of the first session isn’t a one-time demo — it’s so you understand the kit well enough to explain install, framing, and rejects when a shop asks.'],
                ['type' => 'ol', 'items' => [
                    'Install the app on your own phone.',
                    'Mount the headset and record a short real task.',
                    'Check acceptance / reject reasons on the desk.',
                    'Then hand the equipment to the next person and teach from what you just did.',
                ]],
                ['type' => 'callout', 'text' => 'If you can’t teach hands-in-frame without reading a card, you’re not ready to train a kitchen crew.'],
            ],
        ],
        [
            'id' => 'reject-hands',
            'section' => 'Why footage gets rejected',
            'title' => 'Hands not visible',
            'teaser' => 'Quality reduced for how long hands were out of frame.',
            'tags' => ['reject', 'hands'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'Good: both hands visible on the task.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Shift: “Hands weren’t clearly visible for part of the recording. Quality was reduced proportionally to how long hands were out of frame.”'],
                ['type' => 'p', 'text' => 'Fix: remount, use mirroring, keep the task — not the ceiling — in view.'],
            ],
        ],
        [
            'id' => 'reject-camera',
            'section' => 'Why footage gets rejected',
            'title' => 'Camera quality degraded',
            'teaser' => 'Dirty lens, poor light, or unsteady framing.',
            'tags' => ['reject', 'camera'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Bright, sharp, unobstructed.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Clean the lens, stabilize the mount, improve light. Same “camera quality” bar we show on the public Reps site.'],
            ],
        ],
        [
            'id' => 'reject-tasks',
            'section' => 'Why footage gets rejected',
            'title' => 'Off-task footage',
            'teaser' => 'Parts of the recording weren’t the assigned task activity.',
            'tags' => ['reject', 'task'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Stay on continuous real work.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Pause when you leave the task. Idle wandering and off-topic activity get scored down.'],
            ],
        ],
        [
            'id' => 'reject-health',
            'section' => 'Why footage gets rejected',
            'title' => 'Health too low',
            'teaser' => 'Combined score (hands + camera + on-task) fell below the minimum — most common live reject.',
            'tags' => ['reject', 'health'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'Hands'),
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Camera'),
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'On-task'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Code you’ll see: REJECTED_HEALTH_TOO_LOW. Improving any of hand visibility, camera quality, or on-task time raises the score.'],
                ['type' => 'callout', 'text' => 'This is the most common reject on DSC’s live Partner book — coach it first.'],
            ],
        ],
        [
            'id' => 'reject-iv',
            'section' => 'Why footage gets rejected',
            'title' => 'Instruction violation review',
            'teaser' => 'Flagged by classifier — hours don’t count until review concludes.',
            'tags' => ['reject', 'review'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Stay on assigned task activity.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Codes: REJECTED_REVIEWED_IV / instruction violation pending. Don’t coach workarounds — fix framing and task relevance, then wait for review.'],
            ],
        ],
        [
            'id' => 'reject-fps',
            'section' => 'Why footage gets rejected',
            'title' => 'Low FPS (under 27.5)',
            'teaser' => 'Device couldn’t hold the required frame rate — usually hardware/performance.',
            'tags' => ['reject', 'fps', 'device'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('camera-1.mp4', 'camera-poster.jpg', 'Steady device that can hold frame rate.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Close background apps or use a newer device. Chronic FPS fails → replace the phone, don’t blame the operator.'],
            ],
        ],
        [
            'id' => 'reject-blurry',
            'section' => 'Why footage gets rejected',
            'title' => 'Blurry video',
            'teaser' => 'Too blurry to process — clean lens, hold steady.',
            'tags' => ['reject', 'blur'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Sharp, clean lens.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Laplacian variance below threshold in Shift’s words. Wipe the lens; stabilize the mount.'],
            ],
        ],
        [
            'id' => 'reject-stationary',
            'section' => 'Why footage gets rejected',
            'title' => 'Stationary device',
            'teaser' => 'Motion sensors said the device didn’t move — wear or hold it.',
            'tags' => ['reject', 'imu'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('hands-2.mp4', 'hands-poster.jpg', 'Wear it while you work.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Don’t prop the phone on a shelf. The operator must wear or hold the device while recording.'],
            ],
        ],
        [
            'id' => 'reject-imu',
            'section' => 'Why footage gets rejected',
            'title' => 'IMU / motion sensor issue',
            'teaser' => 'Almost always device/firmware — not operator error.',
            'tags' => ['reject', 'imu', 'device'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('camera-1.mp4', 'camera-poster.jpg', 'If IMU keeps failing, swap the device.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Re-record once. If it repeats, flag the device for replacement or firmware — don’t grind the same phone.'],
            ],
        ],
        [
            'id' => 'reject-short',
            'section' => 'Why footage gets rejected',
            'title' => 'Under 60 seconds',
            'teaser' => 'Below the minimum session length — plan a full task pass.',
            'tags' => ['reject', 'duration'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Record a full pass before you stop.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Anything under 60 seconds is rejected. Start the recorder when the task starts, not after.'],
            ],
        ],
        [
            'id' => 'reject-blank',
            'section' => 'Why footage gets rejected',
            'title' => 'Blank / black / white frames',
            'teaser' => 'Usually covered lens or camera not capturing — check preview first.',
            'tags' => ['reject', 'camera'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Confirm a live preview before you start.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Uncover the camera and verify preview. Black/white sampled frames mean nothing useful was captured.'],
            ],
        ],
        [
            'id' => 'reject-corrupt',
            'section' => 'Why footage gets rejected',
            'title' => 'Corrupted / bad upload',
            'teaser' => 'Missing files, bad metadata, IMU, or duplicate upload — re-record.',
            'tags' => ['reject', 'upload'],
            'roles' => $allLearners,
            'source' => 'Shift app · reject help',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Clean re-record after a bad upload.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Don’t stack overlapping uploads. Re-record a clean session once the phone is stable.'],
            ],
        ],
        [
            'id' => 'reject-quarantine',
            'section' => 'Why footage gets rejected',
            'title' => 'Quarantined fraud',
            'teaser' => 'QUARANTINED_FRAUD — hours don’t count until review. Don’t coach workarounds.',
            'tags' => ['reject', 'fraud'],
            'roles' => $allLearners,
            'source' => 'Shift reject codes',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Legitimate continuous work only.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Seen live on DSC’s Partner book. Escalate; don’t invent fixes. Treat as serious.'],
            ],
        ],
        [
            'id' => 'faq-pay-worker',
            'section' => 'Pay & team',
            'title' => 'When do workers get paid?',
            'teaser' => 'Your business pays for accepted footage — ask owner/Shift for timing.',
            'tags' => ['pay', 'worker'],
            'roles' => ['employee', 'individual'],
            'source' => 'Shift app · FAQ + Reps marketing',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Accepted uploads are what pay.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'You earn on accepted uploads after quality review. Exact timing/method comes from your business / onboarding — Reps won’t invent a fake hourly quote here.'],
            ],
        ],
        [
            'id' => 'faq-pay-owner',
            'section' => 'Pay & team',
            'title' => 'When and how does the business get paid?',
            'teaser' => 'Shift pays via Wise — add bank info; pass the employee split in Settings.',
            'tags' => ['pay', 'owner'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Accepted team hours drive the book.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Add US bank info in Shift Settings. Payout scheduling details: Shift team. In Reps, owners use My pay for the shop keep view.'],
            ],
        ],
        [
            'id' => 'faq-split',
            'section' => 'Pay & team',
            'title' => 'Employee payout split',
            'teaser' => 'Settings % you pass to the team — rates differ by business.',
            'tags' => ['pay', 'split'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ / Settings',
            'media' => [
                repsDashEduVideo('hands-2.mp4', 'hands-poster.jpg', 'Workers earn on accepted capture.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Owners can enable a split when they sign the Shift contract. Ask Shift support for rate specifics for your vertical.'],
            ],
        ],
        [
            'id' => 'faq-acceptance-drop',
            'section' => 'Pay & team',
            'title' => 'Why did acceptance drop?',
            'teaser' => 'Usually hands, off-task, or camera/device health — open Worker detail and coach from rejects.',
            'tags' => ['reject', 'coaching'],
            'roles' => $owners,
            'source' => 'Shift app · FAQ',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'Start with hands-in-frame coaching.'),
                repsDashEduVideo('camera-0.mp4', 'camera-poster.jpg', 'Then camera / device health.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'In Reps: Money or Team → worker name → rejection reasons. Those cards are the coaching script.'],
            ],
        ],
        [
            'id' => 'faq-ol',
            'section' => 'Pay & team',
            'title' => 'How onboarding a business works',
            'teaser' => 'OL demos gear, registers the business, installs the app, sets up the team.',
            'tags' => ['sales', 'onboarding'],
            'roles' => $sales,
            'source' => 'Shift app · FAQ',
            'media' => [
                repsDashEduVideo('record-0.mp4', 'record-poster.jpg', 'Demo real capture when you onboard.'),
            ],
            'article' => [
                ['type' => 'ol', 'items' => [
                    'Demo the headset and app (teach-back first).',
                    'Register the business on Shift; get a unique partner code.',
                    'Install app + invite team by phone.',
                    'Watch Money for producers and reject drag.',
                ]],
            ],
        ],
        [
            'id' => 'reps-sales',
            'section' => 'Your Reps seat',
            'title' => 'Sales desk in Reps',
            'teaser' => 'Use Shops for your pipeline, Money for earnings, and Settings for your public landing page link.',
            'tags' => ['reps', 'sales'],
            'roles' => $sales,
            'source' => 'Reps Education',
            'media' => [
                repsDashEduVideo('task-0.mp4', 'task-poster.jpg', 'Your job is producing shops — not QA’ing every clip.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Open a producer from Money to see their hours by day. Your landing page link is on Home, Money, and Settings — share it when recruiting operators or shops.'],
            ],
        ],
        [
            'id' => 'reps-owner',
            'section' => 'Your Reps seat',
            'title' => 'Owner desk in Reps',
            'teaser' => 'Team, sessions, My pay — scoped to your shop only.',
            'tags' => ['reps', 'owner'],
            'roles' => ['business_owner'],
            'source' => 'Reps Education',
            'media' => [
                repsDashEduVideo('hands-1.mp4', 'hands-poster.jpg', 'Coach your crew from reject reasons.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Invite workers by phone in Shift Team. Tap a name for acceptance, rejects, and day-by-day hours.'],
            ],
        ],
        [
            'id' => 'reps-worker',
            'section' => 'Your Reps seat',
            'title' => 'Worker / individual desk in Reps',
            'teaser' => 'Home and Sessions show only your work — match reject codes to the articles here.',
            'tags' => ['reps', 'worker'],
            'roles' => ['employee', 'individual'],
            'source' => 'Reps Education',
            'media' => [
                repsDashEduVideo('hands-0.mp4', 'hands-poster.jpg', 'Your acceptance is mostly framing + task quality.'),
            ],
            'article' => [
                ['type' => 'p', 'text' => 'Replay the Home wizard anytime from Settings. When a reject shows up, open the matching article and fix the next take.'],
            ],
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
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
 * @return array<string, mixed>|null
 */
function repsDashEducationArticleById(string $id, string $role): ?array
{
    foreach (repsDashEducationArticlesForRole($role) as $row) {
        if (($row['id'] ?? '') === $id) {
            return $row;
        }
    }
    return null;
}

function repsDashEducationArticleHref(string $id): string
{
    return '/dashboard/education-article.php?id=' . rawurlencode($id);
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

/**
 * Render article body blocks (escaped).
 *
 * @param list<array<string, mixed>> $blocks
 */
function repsDashRenderEducationBlocks(array $blocks): void
{
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? 'p');
        if ($type === 'h') {
            echo '<h2 class="h5 mt-4 mb-2">' . htmlspecialchars((string) ($block['text'] ?? '')) . '</h2>';
            continue;
        }
        if ($type === 'callout') {
            echo '<div class="alert alert-warning border-0 my-3">' . htmlspecialchars((string) ($block['text'] ?? '')) . '</div>';
            continue;
        }
        if ($type === 'ol' || $type === 'ul') {
            $tag = $type === 'ol' ? 'ol' : 'ul';
            echo '<' . $tag . ' class="mb-3">';
            foreach (($block['items'] ?? []) as $item) {
                echo '<li>' . htmlspecialchars((string) $item) . '</li>';
            }
            echo '</' . $tag . '>';
            continue;
        }
        echo '<p class="mb-3">' . htmlspecialchars((string) ($block['text'] ?? '')) . '</p>';
    }
}

/**
 * @param list<array<string, mixed>> $media
 * @param 'hero'|'grid'|'thumb' $layout
 */
function repsDashRenderEducationMedia(array $media, string $layout = 'grid'): void
{
    if ($media === []) {
        return;
    }
    $wrap = $layout === 'hero' ? 'rd-edu-media rd-edu-media--hero' : ($layout === 'thumb' ? 'rd-edu-media rd-edu-media--thumb' : 'rd-edu-media');
    echo '<div class="' . htmlspecialchars($wrap) . '">';
    foreach ($media as $m) {
        if (($m['type'] ?? '') !== 'video') {
            continue;
        }
        $src = (string) ($m['src'] ?? '');
        $poster = (string) ($m['poster'] ?? '');
        $caption = (string) ($m['caption'] ?? '');
        echo '<figure class="rd-edu-clip">';
        echo '<video class="rd-edu-video" controls playsinline preload="metadata"'
            . ($poster !== '' ? ' poster="' . htmlspecialchars($poster) . '"' : '')
            . '>';
        echo '<source src="' . htmlspecialchars($src) . '" type="video/mp4">';
        echo '</video>';
        if ($caption !== '') {
            echo '<figcaption class="small text-muted">' . htmlspecialchars($caption) . '</figcaption>';
        }
        echo '</figure>';
    }
    echo '</div>';
}
