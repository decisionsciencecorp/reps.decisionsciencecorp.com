# Reps — reps.decisionsciencecorp.com

Marketing site for **Reps**, Decision Science Corp’s branded capture network (Athena-named). Apes the Shift consumer pitch under DSC brand.

## Stack

- Static/PHP under `public/`
- Apply form → `public/apply.php` appends JSONL under site `db/` (outside web root on multihost: `/var/www/reps.decisionsciencecorp.com/db/`)
- No `.htaccess`; app-level only

## Local preview

```bash
php -S 127.0.0.1:8787 -t public
```

## Deploy

Ada provisions `reps.decisionsciencecorp.com` on multihost from this repo (`main`), `SRC_DIR` / `WEB_ROOT` / `DB_PARENT` standard pattern. `deploy.sh` rsyncs `public/` and ensures `db/`.
