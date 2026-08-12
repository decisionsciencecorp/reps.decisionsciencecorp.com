#!/usr/bin/env bash
# Fetch Shift for Business hours-feed (needs Netscape cookie jar for app.joinshift.us).
# Does NOT ingest into Reps — safe during upstream empties. Pipe to poll-shift only when sessions > 0
# or pass --force-empty deliberately.
set -euo pipefail
COOKIE="${SHIFT_COOKIE_JAR:-${REPS_SHIFT_COOKIE_JAR:-/tmp/joinshift/cookies.txt}}"
OUT="${1:-/tmp/shift-hours-feed.json}"
curl -sS -f -b "$COOKIE" -H 'Accept: application/json' -H 'User-Agent: Mozilla/5.0' \
  'https://app.joinshift.us/api/dashboard/hours-feed' -o "$OUT"
python3 -c "
import json,sys
d=json.load(open(sys.argv[1]))
n=len(d.get('sessions') or [])
print('sessions', n, 'partner', d.get('partnerCode'))
if n==0:
    print('WARN: empty hours-feed — do not ingest into Reps without --force-empty', file=sys.stderr)
    sys.exit(3)
" "$OUT"
