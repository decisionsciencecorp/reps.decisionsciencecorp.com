#!/usr/bin/env bash
# Install/refresh Reps live poll cron on multihost (root crontab).
# Idempotent — replaces prior AGENT_CRON reps-poll-shift lines only.
set -euo pipefail
REPO="${REPS_REPO:-/root/repos/reps.decisionsciencecorp.com}"
DB="${REPS_DASH_DB_PATH:-/var/www/reps.decisionsciencecorp.com/db/dashboard.sqlite}"
LOG="${REPS_POLL_LOG:-/var/log/reps-poll-shift.log}"
PHP_BIN="$(command -v php)"
TAG="# AGENT_CRON reps-poll-shift"
# Every 15 minutes
LINE="*/15 * * * * cd ${REPO} && REPS_DASH_DB_PATH=${DB} ${PHP_BIN} tools/poll-shift.php >> ${LOG} 2>&1 ${TAG}"

tmp="$(mktemp)"
crontab -l 2>/dev/null | grep -v 'AGENT_CRON reps-poll-shift' > "$tmp" || true
printf '%s\n' "$LINE" >> "$tmp"
crontab "$tmp"
rm -f "$tmp"
touch "$LOG"
chmod 644 "$LOG" || true
echo "installed: $LINE"
crontab -l | grep 'reps-poll-shift' || true
