# Stripe Express KYC — what each Reps payee sees

**Tasks:** #3223 · Canonical Stripe surface: [Doc #1032](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1032) · Build context: [Doc #1236](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1236)

**Researched:** 2026-09-03 · Source of truth for field lists: Stripe Connect hosted onboarding + Express docs (requirements are dynamic per account).

---

## BLUF

| Seat | Connect type we create | Who completes KYC | Same Express account covers |
|------|------------------------|-------------------|-----------------------------|
| **Sales (affiliate)** | Express, `transfers` only | The affiliate | Affiliate $5/$3 share only |
| **Business owner (shop)** | Express, `transfers` only | Shop owner | Capture $10 (shop keep) |
| **Individual (solo)** | Express, `transfers` only | The operator | Capture $10 (solo) |
| **Linked worker+affiliate** | One Express on `entity_type=user` (affiliate id) | That person once | Both capture + affiliate shares |

We do **not** collect SSN/bank ourselves. Money page → Account Link → Stripe-hosted form. Ready when `payouts_enabled` (webhook `account.updated` and/or return-page refresh).

Employees have **no** Connect path (not paid through Reps).

---

## What Stripe typically asks (US individual / Express)

Exact prompts come from `requirements.currently_due` — Stripe’s UI collects them. Common for **receive-only / transfers** Express:

| Field | Usually required | Notes |
|-------|------------------|-------|
| Legal name | Yes | Must match ID / tax records |
| Date of birth | Yes | |
| Home address | Yes | Physical, not PO box in many cases |
| SSN last 4 (sometimes full SSN) | Yes | Triggers after volume/risk |
| Bank account for payouts | Yes | Instant verify (Plaid) or micro-deposits |
| Phone / email | Often | Prefill from Reps when we create the account |
| Government ID upload | Sometimes | When automated match fails or risk rules fire |
| Business details / EIN | If `business_type=company` | Reps creates **individual** Express today |

**Optional / later:** `eventually_due` items that become blocking after volume or time. Do not ignore — send them back through Account Link when `currently_due` / `past_due` is non-empty.

**Manual review triggers (examples):** identity mismatch, high-risk signals, document quality, sanctions/PEP hits. Platform sees delays via `requirements.disabled_reason` and payouts staying off.

---

## Affiliates vs shops vs solos

Stripe does **not** have a separate “affiliate KYC product.” Differences are product-side only:

| Audience | Reps copy should say | Stripe still asks |
|----------|----------------------|-------------------|
| Affiliate | “Set up payouts for your affiliate share” | Same individual Express KYC |
| Shop owner | “Set up payouts for your shop’s capture share” | Same |
| Solo | “Set up payouts for your accepted-hour pay” | Same |

Capability we request: **`transfers`** (platform → connected balance). We do **not** request card-accepting / merchant capabilities for v1 payees.

---

## One account for linked seats

Locked in code (`repsStripePayeeTargetForUser`): a worker linked to an affiliate seat uses **`entity_type=user`** on the **affiliate** user id. One KYC covers:

- Affiliate bucket lines, and  
- Capture bucket lines for that person  

Do not create a second Express account for the worker seat.

---

## Ready vs “I finished the form”

| Signal | Meaning |
|--------|---------|
| Landed on `/dashboard/connect/return.php` | Form submitted — **not** proof payouts work |
| `payouts_enabled=1` / `onboarding_status=ready` | Safe to Transfer |
| `requirements.currently_due` non-empty | Send through Connect start / refresh again |

Ops: watch `account.updated` webhook; never set `REPS_STRIPE_WEBHOOK_INSECURE` on prod.

---

## Help copy implications

1. Tell payees to use a **personal legal identity** matching their bank.
2. Warn that Stripe may ask for **ID upload** or **full SSN** later — use Continue payout setup on Money.
3. Affiliates do **not** need a business EIN unless they choose company (we default individual).
4. Chuck-tree rate ($3/hr) does **not** change KYC — same Express flow.

---

## References

- https://docs.stripe.com/connect/express-accounts  
- https://docs.stripe.com/connect/hosted-onboarding  
- https://docs.stripe.com/connect/account-links  
- Repo: `docs/STRIPE-SANDBOX.md`, `docs/STRIPE-INTEGRATION-PLAN-CONSTRAINED.md`
