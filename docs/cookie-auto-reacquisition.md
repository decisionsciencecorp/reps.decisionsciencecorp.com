# Cookie auto-reacquisition — research + options (2026-09-02)

**Source:** Mark clarification + live probes. Canonical Tasks: #3224, Doc #1236.

## What actually failed

- Staging jars last touched **2026-08-25**. Poll cron on multihost **is** installed (`*/15` → `tools/poll-shift.php`).
- Once a jar is invalid, every poll hits Google OAuth / JoinShift `/login` and **does not** warm the session — so “use it daily” only works while the jar is still good.
- Mark’s rule (standing): **a healthy daily (or sub-daily) use of a valid jar keeps JoinShift from expiring.** Idle / broken auth → SMS challenge on next human login.

## Lane split

| Lane | Host | Auth today | What we need |
|------|------|------------|--------------|
| Hours | `www.microps.ai` | Flask `session` cookie from **Google OAuth** | Dedicated Google account (Mark has one) + keep-alive + re-login path |
| Matching / invite | `app.joinshift.us` | Supabase `sb-…-auth-token` | Keep-alive on valid jar; re-login needs **phone SMS challenge** |

Partner API key (`MICROPS_API_KEY`) reaches `/api/v1/exports/hours` but requires a `job_id` — **not** a drop-in for `GET /api/mobile-dashboard/data` until we learn the export contract.

---

## JoinShift — options

### J1 — Keep-alive (required regardless)
- Poll already hits JoinShift every 15m when the jar is valid.
- Code already writes rotated `Set-Cookie` back to `app_meta` (`repsShiftReleaseCookieFile`).
- **Done when:** jar is fresh again; then leave cron alone. Do not let auth_redirect sit for days without alert (#3228).

### J2 — Semi-auto re-login (SMS)
When jar dies:
1. Playwright (peacekeeper SOCKS) opens JoinShift login.
2. Fills email/password from vault.
3. **Pauses** for Mark’s SMS code (AgentMail/Telegram/Tasks ping).
4. Completes login → exports Netscape jar → deposits to prod DB.
- Cost: Mark taps one code when (rarely) keep-alive fails.
- Matches Mark’s “phone text challenge” reality.

### J3 — Pure API (if JoinShift ever exposes partner tokens)
Not available today. Park.

**Recommendation:** J1 + J2.

---

## MicroPS / Google OAuth — options

MicroPS is **their** OAuth client (redirect to `accounts.google.com` → `www.microps.ai/auth/callback`). We do **not** own their `client_id` / `client_secret`, so classic “store Google refresh token for MicroPS scopes” is usually **not** available unless MicroPS cooperates.

### M1 — Keep-alive + rare manual capture (lowest drama)
1. One headed login as the **dedicated MicroPS Google account** (peacekeeper).
2. Export Netscape jar → deposit.
3. Poll every 15m keeps the Flask session warm (same principle as JoinShift).
4. On death: alert Mark → one headed re-login (minutes, not daily).

### M2 — Password robot for the dedicated Google account (partial automation)
- Vault: Google email + password for the MicroPS-only account.
- Playwright on **peacekeeper** (residential), headed/stealth, fills Google → lands on MicroPS → export jar.
- **Risk:** Google actively blocks headless / datacenter automation (“this browser may not be secure”). Peacekeeper helps; not guaranteed forever.
- If Google sends 2FA/SMS/prompt: pause for Mark (same as JoinShift SMS).

### M3 — Capture **Google accounts session** cookies (this is the automation Mark asked about)
Because we **own** the dedicated Google account, we do **not** need MicroPS’s OAuth client secret.

What to capture (one headed login on peacekeeper):
1. Log into Google as the MicroPS-only account.
2. Hit MicroPS once so OAuth completes and Flask `session` is set.
3. Export a **combined** Netscape jar (and Playwright `storageState`) that includes:
   - `.google.com` / `accounts.google.com` session cookies (`SID` / `__Secure-*PSID` / `SAPISID` family — names vary)
   - `www.microps.ai` Flask `session`
4. Deposit that whole jar to `app_meta` `microps.cookie_jar` (today’s deposit path already accepts any Netscape text — we were under-capturing if we only saved MicroPS).

Re-auth automation then becomes:
- Open MicroPS (or Google OAuth URL) **with the Google cookies already loaded**
- Google sees an existing session → **skips email/password** (and usually skips 2FA)
- If MicroPS consent was already granted for that Google user → often **silent redirect** back with a code → new Flask `session`
- Persist updated Google + MicroPS cookies after each run

Keep Google warm the same way as JoinShift: periodic authenticated hits (e.g. `accounts.google.com` / My Account) from the **same** jar + same egress (peacekeeper), write rotations back to DB.

Limits (honest):
- This is still a **browser session**, not a forever refresh token — Google can invalidate on IP/UA jump, long idle, or “suspicious sign-in.”
- Do **not** replay the jar from bare NewDev/datacenter after capturing on peacekeeper.
- Headless Chromium can still get challenged; prefer headed/persistent context on peacekeeper.

### M4 — Partner export API instead of mobile-dashboard cookie (best long-term if viable)
- We already have `MICROPS_API_KEY`.
- `/api/v1/exports/hours` returns `malformed job_id` — API is real, contract incomplete.
- **Research:** discover `job_id` shape (GM code? date range? export job create endpoint). If hours can come from Bearer API, **cookie path for hours goes away**.
- Matching/invite still need JoinShift (J1/J2).

### M5 — Ask MicroPS/MicroAGI for a non-Google partner credential
- Service token, export webhook, or official hours dump.
- Political/ops ask, not a code trick. Highest durability if they say yes.

### M6 — Intercept OAuth code / steal refresh token from MicroPS’s client
- Technically interesting, legally/ToS ugly, brittle, and needs their client secret to refresh.
- **Do not.**

---

## Locked recommendation (2026-09-02, post Mark + Q capture)

| Priority | Action |
|----------|--------|
| **Done** | Fresh jars deposited — MicroPS (Google + site) + JoinShift (Doc **#1237** / Q). Poll green: `hours_ok` + `matching_ok`. |
| **Next** | #3228 — hard alert on first `auth_redirect` / `http_401` so we never sit a week dead |
| **Next** | #3225/#3226 — keep-alive daemon + JoinShift SMS / MicroPS Google-session robot (M3 combined jar) |
| Parallel | #3230 / M4 — Partner export `job_id` research |
| Avoid | M6; daily Mark hand-login; posting full jars in Tasks **comments** (2000-char truncate — use docs/attachments) |

**Storage:** prod canonical is already `app_meta` (`microps.cookie_jar`, `joinshift.cookie_jar`). Staging pass files are inject-only.

## Sequence (happy path)

```
cron poll (15m)
  → JoinShift GET team (cookie) → rotate Set-Cookie → app_meta
  → MicroPS GET mobile-dashboard (cookie) → rotate Set-Cookie → app_meta
  → on 401/auth_redirect → alert once + enqueue re-auth robot
       JoinShift: password + wait SMS
       MicroPS: load Google+MicroPS jar → silent OAuth when warm
```
