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
| `GET v1/shift/hours-feed.php` | Mapped MicroPS hours (+ optional `?ingest=1`, also pulls JoinShift team) |
| `GET v1/shift/workers.php` | JoinShift workers GET |
| `GET\|POST\|DELETE v1/shift/team-members.php` | JoinShift team roster / invite / delete (`DELETE ?id=`) |
| `POST v1/shift/sync.php` | Poll MicroPS hours + JoinShift team, then ingest |
| `POST v1/shift/account/*.php` | payout-split, sms-schedule, bank-info, profile, legal-address, shipping-address, active-view, reminders |
| `POST v1/shift/auth/*.php` | request-code, verify-code, logout |
| `POST v1/shift/support-chat.php` | Support |
| `GET v1/shift/derived/worker.php?id=` | Computed from SQLite |
| `GET v1/shift/derived/day.php?date=` | Computed |
| `GET v1/shift/derived/issues.php` | Computed |

Configure hours: `REPS_MICROPS_API_BASE`, cookie jar `REPS_MICROPS_COOKIE_JAR` (default `~/.ssh/microps-cookies.pass`).
Configure matching/invite: `REPS_SHIFT_API_BASE`, cookie jar `REPS_SHIFT_COOKIE_JAR`.

### C. Parked

Consumer `api.micro-agi.com` (Doc #816) — Tasks card parked on list **Partner API v1**.

---

## Developer notes (not for in-app Help)

**CARDINAL — two live upstreams, both production.**

| Lane | Host | What Reps uses |
|------|------|----------------|
| **Hours / sessions / acceptance** | `https://www.microps.ai` | Cookie JSON `GET /api/mobile-dashboard/data` (+ `/per-user`). Do **not** poll JoinShift hours-feed. |
| **Team invite / matching** | `https://app.joinshift.us` | Team GET/POST/DELETE. Writes: fake stub in tests; live invite is real ops. |

There is no Partner sandbox. Do **not** dual-ingest hours. Session `partnerCode` stays the JoinShift matching code (C6N9T7), never the MicroPS GM code (M3WRBU).

| Allowed against live | Forbidden against live (automation) |
|----------------------|--------------------------------------|
| **Read-only GETs** on both hosts for sync and verification | JoinShift invites/deletes/account writes, OTP “for a test”; MicroPS mutations |
| Cookie/session health (non-destructive) | Any create/mutate/remove of Partner state for verification |

**Writes** (invite, etc.) are developed against the **fake JoinShift stub** (`tools/fake-shift-partner/`, `REPS_SHIFT_API_BASE=fake://shift`). Hours mapping is proven against **fake MicroPS** (`tools/fake-microps/`, `REPS_MICROPS_API_BASE=fake://microps`). PHPUnit sets `REPS_SHIFT_FORBID_LIVE_WRITES=1` and refuses live write bases.

**Empty hours guard:** if MicroPS returns `sessions: []` while the local book already has session rows, **hours ingest is refused** (`empty_feed_refused`, HTTP 409 on sync / `?ingest=1`). **JoinShift team still ingests** on that refuse. Override hours only with `poll-shift.php --force-empty`, `REPS_SHIFT_ALLOW_EMPTY_INGEST=1`, or `?force_empty=1`. Wrong `partnerCode` vs stored meta is also refused (`partner_mismatch`).

Human Admin/Ops may intentionally invite against live when `REPS_SHIFT_API_BASE` points at the live JoinShift host — that is real ops, not automated proof.

Fake stubs: `tools/fake-shift-partner/README.md`, `tools/fake-microps/README.md`.

Mini-PRD: Tasks Doc **#1038**. JoinShift RE: Doc **#818**. MicroPS recon: Docs **#1091**, **#1093**.
