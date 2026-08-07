# LOCKED — Shift → DSC settlement model (2026-08-07)

Canonical Tasks copy: **Doc #1036**.

## Formula

America/Chicago Mon–Sun **accepted hours × $20** → cash **following Monday**.

Per person: their accepted hours that week × $20 (sums to deposit).

## Proof

| Deposit | Window | Bank |
|---------|--------|------|
| 2026-07-20 | prior week 2.750h | $55.00 |
| 2026-07-27 | 1.340h | $26.80 |
| 2026-08-03 | 2.320h (cutoff) | $46.40 |

## Reps

Book `settlement_events` on Monday cash → attach week → disburse 25/25/50 via Stripe Connect.

Contract #707 14-day/tiers = legal fallback only.
