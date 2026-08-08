# Reps Dashboard API v1

Base: `https://reps.decisionsciencecorp.com/dashboard/api/`

Auth: session cookie **or** `X-API-Key` / `Authorization: Bearer` (no query-string keys).

---

## Surfaces

### A. Reps book (Slice D — SQLite)

| Endpoint | Notes |
|----------|--------|
| `GET health.php` | Liveness |
| `GET me.php` | Principal |
| `GET list-shops.php` / `get-shop.php?id=` | Scoped shops |
| `GET list-operators.php` / `get-operator.php?id=` | Scoped operators |
| `GET list-sessions.php` / `get-session.php?id=` | Sessions |
| `GET money-summary.php` | Pulse + ledger (role-aware) |
| `POST create-api-key.php` | Admin |
| `GET list-api-keys.php` / `POST revoke-api-key.php` | Keys |
| `POST stripe-webhook.php` | Stripe signature |

### B. Partner proxy (`v1/shift/*`) — admin / ops / agent

Path namespace is `/v1/shift/`; product copy calls this the Partner API.

| Endpoint | Behavior |
|----------|----------|
| `GET v1/shift/hours-feed.php` | Upstream GET (+ optional `?ingest=1`) |
| `GET v1/shift/workers.php` | Upstream GET |
| `GET\|POST\|DELETE v1/shift/team-members.php` | Team roster / invite / delete (`DELETE ?id=`) |
| `POST v1/shift/sync.php` | Poll + ingest |
| `POST v1/shift/account/*.php` | payout-split, sms-schedule, bank-info, profile, legal-address, shipping-address, active-view, reminders |
| `POST v1/shift/auth/*.php` | request-code, verify-code, logout |
| `POST v1/shift/support-chat.php` | Support |
| `GET v1/shift/derived/worker.php?id=` | Computed from SQLite |
| `GET v1/shift/derived/day.php?date=` | Computed |
| `GET v1/shift/derived/issues.php` | Computed |

Configure base: `REPS_SHIFT_API_BASE`. Cookie jar for live: `REPS_SHIFT_COOKIE_JAR`.

### C. Parked

Consumer `api.micro-agi.com` (Doc #816) — Tasks card parked on list **Partner API v1**.

---

## Developer notes (not for in-app Help)

**CARDINAL — Partner upstream is production.** Default live base is `https://app.joinshift.us`. There is no Partner sandbox.

| Allowed against live Partner | Forbidden against live Partner (automation) |
|------------------------------|-----------------------------------------------|
| **Read-only GETs** (hours-feed, workers, team/members, other mapped GETs) for sync and verification | Invites, deletes, bank/profile/split/SMS/address changes, logout, support spam, admin/impersonate, OTP “for a test” |
| Cookie/session health (non-destructive) | Any create/mutate/remove of Partner state for verification |

**Writes** are developed and proven against the **fake Partner stub** (`tools/fake-shift-partner/`, `REPS_SHIFT_API_BASE=fake://shift` or a local `php -S` base). PHPUnit sets `REPS_SHIFT_FORBID_LIVE_WRITES=1` and refuses live write bases.

Human Admin/Ops may intentionally invite against live when `REPS_SHIFT_API_BASE` points at the live Partner host — that is real ops, not automated proof.

Fake stub: see `tools/fake-shift-partner/README.md`.

Mini-PRD: Tasks Doc **#1038**. Partner RE: Doc **#818**.
