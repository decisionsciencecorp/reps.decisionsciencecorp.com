# Reps — reps.decisionsciencecorp.com

Marketing site for **Reps**, Decision Science Corp’s branded capture network (Athena-named). Apes the Shift consumer pitch under DSC brand.

## Apply form

Posts to DSC central contact intake:

`POST https://decisionsciencecorp.com/api/inbound-contact.php` with `channel=reps`

Land in **Admin → Messages → Reps** tab on decisionsciencecorp.com (same inbox as General contact, separate channel).


## Dashboard (`/dashboard`)

Login platform for DSC Reps ops + affiliate seats. PRD: Tasks Doc **#990**.

- Slice A: visual shell + skins + mock data.
- Slice B (now): real session auth, SQLite users, admin provisioning, CSRF, Dev Mode env-gated.
- Later: Shift sync (C), JSON API (D), SDK/SMCP (E).

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
| `mock-data.php` | Fixtures only (until Slice C) |
| `repository.php` | Data access seam (Slice C swaps here) |
| `scope.php` | Seat ACL (`*ForUser`, `CanView*`); sales book = shops ∪ sourced individuals |
| `economics.php` | Hourly rate + shop splits |
| `rollups.php` | Worker/day stats + URL helpers |
| `partials.php` | Shared session/operator table HTML |
| `access.php` | Nav / home / Money mode contract |
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
