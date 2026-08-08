# Reps Dashboard API (Slice D)

JSON endpoints under `/dashboard/api/*.php`. Same entities and seat scope as the HTML dashboard. Pattern matches Sanctum Tasks / Docket (`X-API-Key` or session cookie).

**Base:** `https://reps.decisionsciencecorp.com/dashboard/api/`

## Auth

| Method | How |
|--------|-----|
| **API key** | Header `X-API-Key: reps_…` **or** `Authorization: Bearer reps_…` |
| **Session** | Logged-in dashboard cookie (browser / same-origin tools) |

Query-string `?api_key=` is **rejected** (400).

**Agent role:** human chrome stays empty-book; with an API key, agent is elevated to **ops-equivalent** read of the full book (service principal).

Create keys: `POST create-api-key.php` as **admin** (JSON `user_id`, optional `name`), or Users admin UI. The raw key is returned **once**.

## Endpoints

| Endpoint | Auth | Notes |
|----------|------|--------|
| `GET health.php` | none | Liveness + `live_data` flag |
| `GET me.php` | yes | Principal + auth mode |
| `GET list-shops.php` | yes | Scoped shops |
| `GET get-shop.php?id=` | yes | One shop |
| `GET list-operators.php` | yes | Scoped operators |
| `GET get-operator.php?id=` | yes | Operator + rollup stats |
| `GET list-sessions.php` | yes | `limit` (default 100, max 500), `offset` |
| `GET get-session.php?id=` | yes | Hours-feed session id |
| `GET money-summary.php` | yes | Pulse + mode; ledger totals for admin/ops/agent |
| `POST create-api-key.php` | admin | Body JSON: `user_id`, `name` |
| `GET list-api-keys.php` | yes | Own keys; admin may pass `user_id` |
| `POST revoke-api-key.php` | yes | Body JSON: `id` |
| `POST stripe-webhook.php` | Stripe signature | Not session/API-key auth |

## Example

```bash
curl -sS -H "X-API-Key: $REPS_API_KEY" \
  'https://reps.decisionsciencecorp.com/dashboard/api/list-sessions.php?limit=20'
```

## UI wiring

HTML pages read through `repository.php` + `scope.php` (not `mock-data.php` directly). When Shift sync has landed rows, `live_data` is true and chrome drops the Slice A mock banner. Fixtures remain the fallback when `REPS_DASH_FORCE_MOCK=1` or the sessions table is empty.

## Slice E

Python `reps_sdk/` and SMCP stubs call this surface.
