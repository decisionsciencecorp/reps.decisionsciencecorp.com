#!/usr/bin/env bash
# Fetch MicroPS mobile-dashboard hours JSON (Netscape jar for www.microps.ai).
# Does NOT ingest into Reps — safe during upstream empties.
# Default jar: ~/.ssh/microps-cookies.pass
set -euo pipefail
COOKIE="${MICROPS_COOKIE_JAR:-${REPS_MICROPS_COOKIE_JAR:-${HOME}/.ssh/microps-cookies.pass}}"
OUT="${1:-/tmp/microps-hours.json}"
FROM="${REPS_MICROPS_DATE_FROM:-2026-01-01}"
TO="${REPS_MICROPS_DATE_TO:-$(date +%Y-%m-%d)}"
curl -sS -f --max-redirs 0 -b "$COOKIE" -c "$COOKIE" \
  -H 'Accept: application/json' -H 'User-Agent: Mozilla/5.0' \
  "https://www.microps.ai/api/mobile-dashboard/data?date_from=${FROM}&date_to=${TO}" -o "$OUT"
python3 -c "
import json,sys
d=json.load(open(sys.argv[1]))
rows=d.get('sessions') or d.get('data') or []
n=len(rows) if isinstance(rows, list) else 0
print('sessions', n)
if n==0:
    print('WARN: empty MicroPS hours — do not force-ingest into Reps', file=sys.stderr)
    sys.exit(3)
" "$OUT"
