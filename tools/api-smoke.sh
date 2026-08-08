#!/usr/bin/env bash
# Smoke Slice D API against a base URL (default prod).
# Usage: REPS_API_KEY=reps_… bash tools/api-smoke.sh [base]
set -euo pipefail
BASE="${1:-https://reps.decisionsciencecorp.com/dashboard/api}"
BASE="${BASE%/}"

echo "GET $BASE/health.php"
curl -sS -f "$BASE/health.php" | head -c 400
echo

if [[ -z "${REPS_API_KEY:-}" ]]; then
  echo "REPS_API_KEY unset — skipping authed checks"
  exit 0
fi

auth=(-H "X-API-Key: ${REPS_API_KEY}")
for path in me.php list-shops.php list-operators.php 'list-sessions.php?limit=5' money-summary.php; do
  echo "GET $path"
  curl -sS -f "${auth[@]}" "$BASE/$path" | head -c 500
  echo
done
echo "api-smoke ok"
