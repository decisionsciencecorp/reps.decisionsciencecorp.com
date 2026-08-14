# Fake Shift Partner stub

**CARDINAL:** `app.joinshift.us` is production. Use this stub for **all write-path tests and local invite UI**. Do not invite or mutate live Partner for verification. Live **read-only GETs** for **team/matching** are fine when deliberately polling JoinShift. **Hours ingest uses MicroPS**, not this stub’s hours-feed (kept for leftover GET tests).

## Run

```bash
php -S 127.0.0.1:8765 -t tools/fake-shift-partner tools/fake-shift-partner/router.php
export REPS_SHIFT_API_BASE=http://127.0.0.1:8765
# cookie jar ignored by fake; any path is fine
php tools/poll-shift.php
```

State file (in-memory per process): members/sessions reset when the server process restarts. Optional persist: `FAKE_SHIFT_STATE=/tmp/fake-shift-state.json`.

## Routes

Implements Doc #818 Partner shapes: hours-feed, workers, team members (GET/POST/DELETE), account posts, auth request/verify/logout, support chat, admin stubs (401).
