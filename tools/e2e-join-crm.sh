#!/usr/bin/env bash
# E2E smoke for join funnel + CRM routes (PHP built-in server).
# Usage: bash tools/e2e-join-crm.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${REPS_E2E_PORT:-8765}"
DB="${TMPDIR:-/tmp}/reps-e2e-$$.sqlite"
rm -f "$DB"
export REPS_DASH_DB_PATH="$DB"
export REPS_LEADS_WEBHOOK_URL=
export REPS_LEADS_WEBHOOK_SECRET=

cd "$ROOT/public"
php -S "127.0.0.1:${PORT}" >/tmp/reps-e2e-server.log 2>&1 &
PID=$!
cleanup() { kill "$PID" 2>/dev/null || true; rm -f "$DB"; }
trap cleanup EXIT
sleep 0.4

base="http://127.0.0.1:${PORT}"
fail=0
check() {
  local path="$1" expect="${2:-200}"
  code=$(curl -sS -o /tmp/reps-e2e-body -w '%{http_code}' "$base$path" || echo 000)
  if [[ "$code" != "$expect" && "$code" != "302" ]]; then
    echo "FAIL $path → HTTP $code (want $expect or 302)"
    fail=1
  else
    echo "OK   $path → HTTP $code"
  fi
}

check /join.php
check /join/partner.php
check /join/thanks.php
check /join/thanks.php?kind=partner
check /apply.php 302
check /dashboard/login.php
check /dashboard/leads.php 302
check /dashboard/lead.php?id=1 302
check /

# CSRF-backed POST to join
tok=$(php -r '
  session_name("reps_dash");
  session_start();
  require "'"$ROOT"'/public/dashboard/includes/config.php";
  require "'"$ROOT"'/public/dashboard/includes/csrf.php";
  echo repsDashCsrfToken();
')
# Need same session cookie — use curl jar with PHPSESSID from a GET that starts session
jar=$(mktemp)
curl -sS -c "$jar" "$base/join.php" >/dev/null
# Extract CSRF from HTML
csrf=$(grep -oP 'name="csrf_token" value="\K[^"]+' /tmp/reps-e2e-body 2>/dev/null || true)
if [[ -z "$csrf" ]]; then
  curl -sS -c "$jar" -b "$jar" "$base/join.php" -o /tmp/reps-e2e-join.html
  csrf=$(grep -oP 'name="csrf_token" value="\K[^"]+' /tmp/reps-e2e-join.html || true)
fi
if [[ -n "$csrf" ]]; then
  code=$(curl -sS -c "$jar" -b "$jar" -o /tmp/reps-e2e-post -w '%{http_code}' \
    -X POST "$base/join.php" \
    --data-urlencode "csrf_token=$csrf" \
    --data-urlencode "name=E2E Tester" \
    --data-urlencode "phone=2145550000" \
    --data-urlencode "email=e2e@example.com" \
    --data-urlencode "path=on_job" \
    --data-urlencode "metro=Dallas" \
    --data-urlencode "expectations_ack=1" \
    --data-urlencode "affiliate_code=jim" \
    --data-urlencode "company_website=")
  if [[ "$code" == "302" || "$code" == "200" ]]; then
    echo "OK   POST /join.php → HTTP $code"
  else
    echo "FAIL POST /join.php → HTTP $code"
    fail=1
  fi
else
  echo "WARN could not extract CSRF; skip POST join"
fi
rm -f "$jar"

if [[ "$fail" -ne 0 ]]; then
  echo "e2e-join-crm: FAILED"
  exit 1
fi
echo "e2e-join-crm: OK"
exit 0
