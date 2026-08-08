# Reps — reps.decisionsciencecorp.com

Marketing site for **Reps**, Decision Science Corp’s branded capture network (Athena-named). Apes the Shift consumer pitch under DSC brand.

## Apply / join funnel

Operator and shop applications go to **`/join.php`** (optional `?rep=jim`). Affiliate / sales seat applications go to **`/join/partner.php`** (soft footer link only). Both write to dashboard SQLite **`apply_leads`** (not the DSC Messages inbox). Legacy **`/apply.php`** redirects to `/join.php`.

Sales CRM desk: `/dashboard/leads.php` + lead detail with activity timeline, graduate-to-Users, outbound webhooks (`REPS_LEADS_WEBHOOK_URL` + `REPS_LEADS_WEBHOOK_SECRET`). Phase PRD: Tasks Doc **#997**.

### Tests (≥90% on join CRM slice)

```bash
composer install
./vendor/bin/phpunit --coverage-clover coverage/clover.xml
php tools/coverage-gate.php --min=90
bash tools/e2e-join-crm.sh
```


## Dashboard (`/dashboard`)

Login platform for DSC Reps ops + affiliate seats. PRD: Tasks Doc **#990**.

- Slice A: visual shell + skins + mock data.
- Slice B: real session auth, SQLite users, admin provisioning, CSRF, Dev Mode env-gated.
- Join funnel + sales Leads CRM: migration `004_join_funnel`, public `/join*`, partial CRM.
- Slice C: Shift hours/workers poll + Admin/Ops worker matcher (`shift-match.php`).
- Slice D: JSON API under `/dashboard/api/` (session + API key) — see `public/dashboard/api/README.md`.
- Later: SDK/SMCP (E).
- Payouts phase: Stripe Connect Transfers, ledger 25/25/50, Money UI ledger totals — see `docs/STRIPE-INTEGRATION-PLAN-CONSTRAINED.md`, Tasks Docs **#1032** / **#1033**. Keys in `~/.ssh/reps-stripe.pass` (never commit).

### Auth (Slice B)

| Item | Value |
|------|--------|
| DB | `db/dashboard.sqlite` (sibling of `public/` / multihost `html/`) |
| Override | `REPS_DASH_DB_PATH` |
| Dev Mode | **on by default** (demo); set `REPS_DASH_DEV_MODE=0` to lock down |
| Seed password | `reps-demo` (listed on login while Dev Mode is on) |
| Login | username + password at `/dashboard/login.php` |

### Code layout (`public/dashboard/includes/`)

| File | Role |
|------|------|
| `config.php` / `db.php` / `csrf.php` / `auth.php` | Config, SQLite users, CSRF, sessions |
| `mock-data.php` | Fixture fallback when live sessions empty / `REPS_DASH_FORCE_MOCK` |
| `repository.php` | Data access seam (live SQLite or fixtures) |
| `api.php` | Slice D JSON auth + helpers |
| `scope.php` | Seat ACL (`*ForUser`, `CanView*`); sales book = shops ∪ sourced individuals |
| `economics.php` | Hourly rate + shop splits |
| `rollups.php` | Worker/day stats + URL helpers |
| `partials.php` | Shared session/operator table HTML |
| `access.php` | Nav / home / Money mode contract |
| `leads-crm.php` | Join funnel assignment, events, graduate, webhooks |
| `money-views.php` | Money peer HTML only |

Scope regression (CLI):

```bash
php tools/scope-matrix-smoke.php
```

Local: open `http://127.0.0.1:8787/dashboard/login.php` after the preview server below.

## Local preview

```bash
php -S 127.0.0.1:8787 -t public
```

## Deploy

Ada provisions `reps.decisionsciencecorp.com` on multihost from this repo (`main`), `SRC_DIR` / `WEB_ROOT` / `DB_PARENT` standard pattern. `deploy.sh` rsyncs `public/` and ensures `db/`.
