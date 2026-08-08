# SMCP plugin — Reps Dashboard

Wraps `reps_sdk` for agent / SMCP hosts. Pattern: `sanctum-tasks/smcp_plugin`, `docket`, `invoicing`.

## Layout

```
smcp_plugin/
  README.md
  reps/
    __init__.py
    cli.py          # python -m / direct CLI
```

## Env

| Variable | Purpose |
|----------|---------|
| `REPS_SMCP_API_KEY` | Preferred (SMCP injects this) |
| `REPS_API_KEY` / `REPS_DSC_AGENT_API_KEY` | Fallbacks |
| `REPS_API_BASE_URL` | Origin (default `https://reps.decisionsciencecorp.com`) |

Otto pass file: `~/.ssh/reps-dsc-agent.pass` (created by `reps_sdk/tools/smoke_api.py --mint-agent-key`).

## Commands

```bash
cd /path/to/reps.decisionsciencecorp.com
set -a && . ~/.ssh/reps-dsc-agent.pass && set +a

python3 smcp_plugin/reps/cli.py --describe
python3 smcp_plugin/reps/cli.py health
python3 smcp_plugin/reps/cli.py me
python3 smcp_plugin/reps/cli.py list-shops
python3 smcp_plugin/reps/cli.py money-summary
python3 smcp_plugin/reps/cli.py partner-derived-issues
```

Partner sync / hours GETs are available; invite and other Partner **writes** are real ops — do not automate against live without intent.

## Birth note

Full SMCP host registration (stdio wrapper on multihost / Letta tool birth) is a follow-up ops step. This slice ships the **plugin stub + describe schema** agents can mount the same way as Tasks/Docket.
