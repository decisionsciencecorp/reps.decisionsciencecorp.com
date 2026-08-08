# reps_sdk (Python)

Thin client for the Reps dashboard JSON API at `/dashboard/api/`.

Pattern matches `tasks_sdk` / `docket_sdk` / `invoicing_sdk`.

## Install

```bash
cd reps_sdk
pip install -e .
```

## Auth

Prefer an **agent** seat API key (Admin → Users → expand agent → Create key).

```bash
export REPS_API_KEY=…          # or REPS_SMCP_API_KEY / REPS_DSC_AGENT_API_KEY
export REPS_API_BASE_URL=https://reps.decisionsciencecorp.com   # optional
```

Pass file (Otto): `~/.ssh/reps-dsc-agent.pass` — load with `set -a && . ~/.ssh/reps-dsc-agent.pass && set +a`.

Never put keys in Tasks bodies or git.

## Quick use

```python
from reps_sdk import RepsClient

c = RepsClient()
print(c.health())
print(c.me())
print(c.list_shops())
```

## Partner proxy

Admin / ops / agent only:

```python
c.partner_sync()
c.partner_hours_feed(ingest=False)
c.partner_derived_issues()
```

Live Partner writes (invite, account mutations) are real ops — see repo `public/dashboard/api/README.md` developer notes. Use the fake stub for automated write proof.

## Smoke

```bash
# With key in env:
python tools/smoke_api.py

# Or create a key via admin session then smoke:
python tools/smoke_api.py --mint-agent-key
```

## SMCP

See `../smcp_plugin/README.md`.
