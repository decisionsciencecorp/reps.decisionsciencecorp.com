# Stripe MCP / plugin bootstrap prompt (Reps)

**Do not run until Mark orders** and test API keys exist in `~/.ssh/reps-stripe.pass`.

**Product constraints (do not reopen):** platform Stripe balance + Connect Express **recipients** + `Transfers`; $20/hr pie DSC 25 / affiliate 25 / capture 50 (shop XOR operator); Square out of scope for Reps.

---

Help me get started building on Stripe. Here's my context:

Business: http://decisionsciencecorp.com
Description: Operate data driven business lines. Consulting, development, analysis and more. Issue payments for data submissions.

---

Follow these steps in order. The Stripe plugin is the preferred method — only use alternatives if installation fails.

1. Install the Stripe plugin:
  - /add-plugin stripe or install from https://cursor.com/marketplace/stripe
  - If the plugin installed but tools aren't available, reload your tools or start a new session.
2. Connect to the Stripe MCP server:
  - Add https://mcp.stripe.com as an MCP server and authenticate when prompted (https://docs.stripe.com/mcp.md).
  - Confirm stripe_implementation_planner is available. If not, reload your tools or start a new session.
3. Generate my integration plan:
  - Use the stripe_implementation_planner tool with my business context to generate a tailored, best-practices Stripe integration plan for my use case.
  - Only if stripe_implementation_planner is still unavailable after steps 1 and 2, fall back to: npx skills add https://docs.stripe.com

Then help me build a Stripe integration using my API keys. If I already have an integration, review it against the plan and suggest improvements.
