# Reps — reps.decisionsciencecorp.com

Marketing site for **Reps**, Decision Science Corp’s branded capture network (Athena-named). Apes the Shift consumer pitch under DSC brand.

## Apply form

Posts to DSC central contact intake:

`POST https://decisionsciencecorp.com/api/inbound-contact.php` with `channel=reps`

Land in **Admin → Messages → Reps** tab on decisionsciencecorp.com (same inbox as General contact, separate channel).


## Local preview

```bash
php -S 127.0.0.1:8787 -t public
```

## Deploy

Ada provisions `reps.decisionsciencecorp.com` on multihost from this repo (`main`), `SRC_DIR` / `WEB_ROOT` / `DB_PARENT` standard pattern. `deploy.sh` rsyncs `public/` and ensures `db/`.
