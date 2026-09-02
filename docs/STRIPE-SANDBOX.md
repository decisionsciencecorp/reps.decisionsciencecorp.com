# Stripe sandbox harness (Connect Express)

Canonical Tasks: **#3220**. Related: Doc **#1236** §4 Stripe path, Doc **#1032**.

## What this is

A one-command path to exercise **Connect Express onboarding** for a dashboard seat (default sales user `jim`) without Mark’s live Stripe account.

| Mode | Flag | Network | Keys |
|------|------|---------|------|
| **Mock (default)** | none / `--mock` | None — HTTP mock | Synthetic `sk_test_sandbox_harness` |
| **Live test** | `--live-test` | Stripe test API | `app_meta` / `~/.ssh/reps-stripe.pass` **test** keys only |

Live **production** keys are refused.

There is a separate transfer-path smoke at `tools/sandbox-payout-smoke.php` (Custom Connect + Transfer). This harness covers **Account Links + `account.updated`**.

## Run

```bash
# CI / local — mocked Account Link URL + signed webhook → payee ready
php tools/stripe-sandbox-smoke.php

# Named seat
php tools/stripe-sandbox-smoke.php --username=jim

# Skip webhook simulation (URL only)
php tools/stripe-sandbox-smoke.php --no-webhook

# Real Stripe test-mode Account Link (needs test secret in DB or pass file)
php tools/stripe-sandbox-smoke.php --live-test
```

Stdout is JSON. On success, `onboarding_url` is present; with webhook simulation, `payee.payouts_enabled` is `1`.

## Code map

| Piece | Path |
|-------|------|
| Harness | `repsStripeSandboxConnectHarness()` in `public/dashboard/includes/stripe-connect.php` |
| CLI | `tools/stripe-sandbox-smoke.php` |
| Sign helper | `repsStripeSignWebhookPayload()` |
| Unit tests | `tests/StripeSandboxSmokeTest.php` |
| Browser smoke | `tools/design-smoke/money_connect_sales.py` (sales → Connect start) |
| Return page | `/dashboard/connect/return.php` |
| Webhook | `/dashboard/api/stripe-webhook.php` |

## Deposit test keys (live-test only)

```bash
# Staging pass → app_meta (never commit values)
php tools/deposit-stripe-secrets.php
```

Confirm `stripe.mode=test` and secret starts with `sk_test_` / `rk_test_`.

## Done criteria (#3220)

- [x] One command yields an onboarding URL (mock or test)
- [x] Mock path simulates signed `account.updated` and marks payee ready
- [x] No live keys required for default run
- [x] Docs + unit coverage for harness
