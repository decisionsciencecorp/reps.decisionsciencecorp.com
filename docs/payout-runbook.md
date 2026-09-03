# Reps payout runbook

**Source of truth:** [Doc #1236](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1236) · Tasks **#3248** (expand settlement/disburse ops here as those slices land).

Locked partner base for settlement math: **$20 / accepted hour**. Accrual uses **fixed cents**, not a share of whatever Shift paid above that base.

| Bucket | Rate | Who sees it |
|--------|------|-------------|
| Capture | **$10/hr** | Shop owner or solo (Money) |
| Affiliate (standard) | **$5/hr** | Sales affiliate |
| Affiliate (**Chuck-tree**) | **$3/hr** | Sales affiliate flagged by admin |
| DSC base | **$5/hr** | Admin ledger / retained |
| DSC Chuck holdback | **$2/hr** | Admin only (`dsc_chuck_holdback`) — **not** on affiliate Money |

Anything above the $20 base stays with DSC; capture/affiliate lines do **not** grow with a higher real rate.

---

## Chuck-tree lane

**Flag:** `users.chuck_tree` (SQLite). Set only on **Users** (admin). Sales / ops / the affiliate never see or edit it.

**When set on a sales seat:**

1. Accrual posts affiliate **$3/hr** + separate ledger line **`dsc_chuck_holdback` $2/hr** (status `retained`).
2. Standard affiliates still get **$5/hr** (no holdback line).
3. Chuck’s own pay for managing that tree is **W2 / off-platform** — Reps never pays Chuck via Connect for this difference.

**Code:** `repsDashSplitAcceptedHours(..., $chuckTree)`, `repsLedgerPostAcceptedHour` (resolves flag from affiliate user), admin checkbox on `users.php`.

---

## Stripe mode

Sandbox vs live flip is Mark-gated (**#3219**). Connect KYC expectations: `docs/STRIPE-EXPRESS-KYC.md` (**#3223**). Sandbox harness: `docs/STRIPE-SANDBOX.md`.

Settlement gate, Monday cash book, and disburse dry-run details land here when **#3249** / **#3234+** ship — follow Doc #1236 until then.
