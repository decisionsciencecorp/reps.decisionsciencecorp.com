# Shift accrual calibration snapshot

- Pulled live: `GET /api/dashboard/hours-feed` (partner **C6N9T7**)
- Sessions: **40** · range **2026-07-17 → 2026-08-07**
- Totals: **9.000** accepted h · **$180.00** dashboard-equivalent (×$20)
- 14-day windows anchored to first session day **2026-07-17** (candidate contract cadence)

## 14-day accrual windows

| Window | Accepted h | Uploaded h | Quality | Dash $ (acc×20) | Contract est (uploaded×tier) |
|--------|------------|------------|---------|-----------------|------------------------------|
| 2026-07-17 to 2026-07-30 | 4.090 | 7.380 | 55% | $81.80 | $73.80 @$10 |
| 2026-07-31 to 2026-08-13 | 4.910 | 6.460 | 76% | $98.20 | $129.20 @$20 |

## ISO weeks

| Week | Accepted h | Uploaded h | Dash $ |
|------|------------|------------|--------|
| 2026-W29 | 2.750 | 4.680 | $55.00 |
| 2026-W30 | 1.340 | 2.510 | $26.80 |
| 2026-W31 | 1.840 | 2.650 | $36.80 |
| 2026-W32 | 3.070 | 4.000 | $61.40 |

## Bank correlation — NEED FROM MARK

Primary Gmail has **no Wise** / Grasshopper alert emails for Shift deposits (Square→Grasshopper noise only).
Please provide for each payout since ~2026-07-17:
1. **Date** cash hit the bank
2. **Amount**
3. **Bank** (Grasshopper vs other) + any Wise reference
4. Optional: screenshot/CSV export

Then we match deposit → nearest closed 14-day window and measure lag.