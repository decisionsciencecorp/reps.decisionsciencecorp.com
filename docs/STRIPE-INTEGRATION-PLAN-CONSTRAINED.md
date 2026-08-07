# Stripe integration plan — Reps (constrained; no MCP planner)

**Date:** 2026-08-07 · **Author:** Otto  
**Why this file:** Cursor Stripe plugin / `https://mcp.stripe.com` / `stripe_implementation_planner` are **not available** in this Otto environment. Canonical research remains [Doc #1032](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1032). Bootstrap prompt: `docs/STRIPE-MCP-BOOTSTRAP-PROMPT.md` (run only when Mark orders + MCP is wired).

## Locked rail (do not reopen)

| Item | Choice |
|------|--------|
| Settlement | Shift → **DSC platform Stripe** balance (top-up / deposit / ops import) |
| Payees | Connect **Express** recipients (receive-only; no card-accepting v1) |
| Money-out | **`Transfers`** platform → connected |
| Economics | $20/hr → DSC 25 / affiliate 25 (none→DSC) / capture 50 shop XOR operator |
| Out of scope | Square, PaymentIntents for hours, destination charges, Billing/Issuing/Treasury |

## Implemented modules (this repo)

| Module | Path |
|--------|------|
| HTTP client + webhook verify | `public/dashboard/includes/stripe-client.php` |
| Express create + Account Links | `public/dashboard/includes/stripe-connect.php` |
| Settlement reconcile | `public/dashboard/includes/settlement.php` |
| Ledger 25/25/50 | `public/dashboard/includes/ledger.php` |
| Disbursement batch | `public/dashboard/includes/payouts-disburse.php` |
| Webhook | `public/dashboard/api/stripe-webhook.php` |
| Connect return/refresh | `public/dashboard/connect/*.php` |
| Schema | migration `005_payouts` in `db.php` |
| Keys | `~/.ssh/reps-stripe.pass` (never git) |

## Build order vs Stripe’s generic planner

Generic Stripe “marketplace charges” planners push Checkout / destination charges. **Ignore that.** Reps is **disburse-only** after Shift settlement.

1. Keys in pass file (test) → webhook endpoint secrets  
2. Create Express recipients + Account Links for affiliates / shops / operators  
3. Ledger from accepted hours (Slice C when live; mock seed until then)  
4. Top-up / balance coverage check  
5. `Transfers` batch with `Idempotency-Key: reps-transfer-{ledger_line_id}`  
6. Money UI from ledger + Stripe balance (done for admin peer)

## When to re-run Stripe MCP planner

Only after Mark installs the Stripe plugin, authenticates MCP, and pastes the bootstrap prompt — then **constrain** the planner output to this rail before changing code.
