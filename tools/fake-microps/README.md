# Fake MicroPS stub

In-process hours JSON for PHPUnit (`FAKE_MICROPS_INLINE=1`, `REPS_MICROPS_API_BASE=fake://microps`).

Live hours come from `https://www.microps.ai` cookie JSON (`GET /api/mobile-dashboard/data`). Matching/invite stay on JoinShift (`tools/fake-shift-partner/`).

Do **not** dual-ingest hours. See Tasks Doc **#1093**.
