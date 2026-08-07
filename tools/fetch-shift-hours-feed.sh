#!/usr/bin/env bash
# Fetch Shift for Business hours-feed (needs Netscape cookie jar for app.joinshift.us).
set -euo pipefail
COOKIE="${SHIFT_COOKIE_JAR:-/tmp/joinshift/cookies.txt}"
OUT="${1:-/tmp/shift-hours-feed.json}"
curl -sS -f -b "$COOKIE" -H 'Accept: application/json' -H 'User-Agent: Mozilla/5.0' \
  'https://app.joinshift.us/api/dashboard/hours-feed' -o "$OUT"
python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print('sessions',len(d.get('sessions',[])),'partner',d.get('partnerCode'))" "$OUT"
