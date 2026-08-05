# Reps — reps.decisionsciencecorp.com

Marketing site for **Reps**, Decision Science Corp’s branded capture network (Athena-named). Apes the Shift consumer pitch under DSC brand.

## Apply form

Posts to DSC central contact intake:

`POST https://decisionsciencecorp.com/api/inbound-contact.php` with `channel=reps`

Land in **Admin → Messages → Reps** tab on decisionsciencecorp.com (same inbox as General contact, separate channel).


## Dashboard (`/dashboard`)

Login platform for DSC Reps ops + affiliate seats. PRD: Tasks Doc **#990**.

- Slice A (now): visual shell + skins + mock data — no real Shift poll yet.
- Later: auth, schema, Shift sync, JSON API, SDK, SMCP.

### Code layout (`public/dashboard/includes/`)

| File | Role |
|------|------|
| `mock-data.php` | Fixtures only |
| `repository.php` | Data access seam (Slice C swaps here) |
| `scope.php` | Seat ACL (`*ForUser`, `CanView*`); sales book = shops ∪ sourced individuals (`assigned_sales_rep` on solo ops) |
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
