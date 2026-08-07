# Shift settle calibration — hours × bank (2026-08-07)

**Status:** Calibrated · Hybrid locked weekly  
**Tasks:** Doc #1036 · #2403 · Phase PRD #1033 §7.2

## Mark bank deposits

| Posted | Amount |
|--------|--------|
| 2026-07-20 | $55.00 |
| 2026-07-27 | $26.80 |
| 2026-08-03 | $46.40 |

## Rule

**America/Chicago Mon–Sun accepted hours × $20 → paid the following Monday.**

| Deposit Mon | Hours window | Accepted h | Expected | Bank | Notes |
|-------------|--------------|------------|----------|------|-------|
| 2026-07-20 | 07-13 → 07-19 | 2.750 | $55.00 | $55.00 | exact |
| 2026-07-27 | 07-20 → 07-26 | 1.340 | $26.80 | $26.80 | exact |
| 2026-08-03 | 07-27 → 08-02 | 2.320 | $46.40 | $46.40 | excludes 0.16h @ 22:14 CT Sun (batch cutoff) |

Contract 14-day / quality tiers (#707) = legal fallback. **Automation uses weekly observed.**

Next check: Mon 2026-08-10 ≈ prior week Aug 3–9 × $20.
