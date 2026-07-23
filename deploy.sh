#!/usr/bin/env bash
# Multihost deploy helper — mirrors public/ to WEB_ROOT (invoked by host deploy.sh)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
SRC="${ROOT}/public"
DEST="${WEB_ROOT:?WEB_ROOT required}"
RSYNC=(rsync -a --delete)
for keep in uploads; do
  if [[ -d "${DEST}/${keep}" ]]; then
    RSYNC+=(--exclude "${keep}/")
  fi
done
"${RSYNC[@]}" "${SRC}/" "${DEST}/"
# Ensure db parent exists beside html for applications.jsonl
DB_PARENT="$(dirname "${DEST}")"
mkdir -p "${DB_PARENT}/db"
chmod 750 "${DB_PARENT}/db" || true
chown -R "${WWW_USER:-www-data}:${WWW_USER:-www-data}" "${DEST}" "${DB_PARENT}/db"
