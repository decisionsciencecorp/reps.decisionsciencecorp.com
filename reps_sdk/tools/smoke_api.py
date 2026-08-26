#!/usr/bin/env python3
"""
Prod/staging smoke for reps_sdk against a live API key path.

Usage:
  REPS_API_KEY=… python tools/smoke_api.py
  python tools/smoke_api.py --mint-agent-key   # admin login + create agent key once
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from reps_sdk import RepsClient  # noqa: E402

DEFAULT_BASE = "https://reps.decisionsciencecorp.com"
PASS_FILE = Path.home() / ".ssh" / "reps-dsc-agent.pass"


def _load_pass_file() -> dict[str, str]:
    out: dict[str, str] = {}
    if not PASS_FILE.is_file():
        return out
    for line in PASS_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        out[k.strip()] = v.strip().strip("'\"")
    return out


def _write_pass_file(api_key: str, base: str, agent_user_id: int) -> None:
    PASS_FILE.parent.mkdir(parents=True, exist_ok=True)
    body = (
        "# Reps dashboard API — agent seat (Slice E smoke / SMCP)\n"
        f"REPS_API_BASE_URL={base}\n"
        f"REPS_DASH_BASE_URL={base}\n"
        f"REPS_DSC_AGENT_USER_ID={agent_user_id}\n"
        f"REPS_DSC_AGENT_API_KEY={api_key}\n"
        f"REPS_API_KEY={api_key}\n"
        f"REPS_SMCP_API_KEY={api_key}\n"
    )
    PASS_FILE.write_text(body, encoding="utf-8")
    os.chmod(PASS_FILE, 0o600)


def _csrf_from_html(html: str) -> str:
    m = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
    if not m:
        m = re.search(r'name="csrf"\s+value="([^"]+)"', html)
    if not m:
        raise RuntimeError("CSRF token not found on login page")
    return m.group(1)


def mint_agent_key(base: str) -> tuple[str, int]:
    """Admin password login → create API key for agent seat. Returns (key, agent_user_id)."""
    s = requests.Session()
    login_url = f"{base}/dashboard/login.php"
    r = s.get(login_url, timeout=30)
    r.raise_for_status()
    csrf = _csrf_from_html(r.text)
    admin_user = os.environ.get("REPS_DASH_ADMIN_USERNAME", "rizzn")
    admin_pass = os.environ.get("REPS_DASH_ADMIN_PASSWORD", "")
    if not admin_pass:
        pf = _load_pass_file()
        admin_pass = pf.get("REPS_DASH_ADMIN_PASSWORD", "") or os.environ.get(
            "REPS_DASH_SEED_PASSWORD", "reps-demo"
        )
    r = s.post(
        login_url,
        data={
            "csrf_token": csrf,
            "username": admin_user,
            "password": admin_pass,
        },
        timeout=30,
        allow_redirects=True,
    )
    me = s.get(f"{base}/dashboard/api/me.php", timeout=30)
    me.raise_for_status()
    me_j = me.json()
    if not me_j.get("ok"):
        raise RuntimeError(f"Admin me failed: {me_j}")

    # Find agent user id via users page scrape is fragile — use list from seed order via API:
    # create-api-key needs user_id; discover by trying me as admin then scanning operators won't work.
    # Hit users.php is HTML. Prefer: login as agent after mint? We need agent id from DB.
    # Workaround: create key for a known username by posting create with user_id from
    # GET that doesn't exist — instead parse users.php for @agent row id… messy.
    # Seed agent is typically username agent.
    # Session as mark can create key; get agent id from list-api-keys after creating for guessed ids.
    # Simplest reliable path: POST create-api-key with user_id discovered from
    # a small PHP-less approach — scrape users.php ledger for @agent.

    users = s.get(f"{base}/dashboard/users.php", timeout=30)
    users.raise_for_status()
    m = re.search(
        r'@agent\b[\s\S]{0,4000}?name="user_id"\s+value="(\d+)"',
        users.text,
    )
    if not m:
        raise RuntimeError("Could not find agent user_id on users.php")
    agent_id = int(m.group(1))

    created = s.post(
        f"{base}/dashboard/api/create-api-key.php",
        json={"user_id": agent_id, "name": "slice-e-smoke"},
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        timeout=30,
    )
    body = created.json()
    if created.status_code >= 400 or not body.get("ok"):
        raise RuntimeError(f"create-api-key failed: {created.status_code} {body}")
    key = str(body.get("key") or "")
    if not key:
        raise RuntimeError(f"No key in create response: {body}")
    return key, agent_id


def main() -> int:
    p = argparse.ArgumentParser(description="Reps API smoke (SDK)")
    p.add_argument("--base-url", default=os.environ.get("REPS_API_BASE_URL", DEFAULT_BASE))
    p.add_argument("--mint-agent-key", action="store_true")
    args = p.parse_args()
    base = args.base_url.rstrip("/")

    env = _load_pass_file()
    for k, v in env.items():
        os.environ.setdefault(k, v)

    key = (
        os.environ.get("REPS_API_KEY")
        or os.environ.get("REPS_SMCP_API_KEY")
        or os.environ.get("REPS_DSC_AGENT_API_KEY")
        or ""
    ).strip()

    if args.mint_agent_key or not key:
        print("Minting agent API key via admin session…", file=sys.stderr)
        key, agent_id = mint_agent_key(base)
        _write_pass_file(key, base, agent_id)
        print(f"Wrote {PASS_FILE} (agent user_id={agent_id})", file=sys.stderr)
        os.environ["REPS_API_KEY"] = key

    client = RepsClient(api_key=key, base_url=base)
    health = client.health()
    me = client.me()
    shops = client.list_shops()
    out = {
        "ok": True,
        "health": health,
        "me": me.get("me"),
        "shops_count": shops.get("count"),
        "live_data": shops.get("live_data"),
    }
    print(json.dumps(out, indent=2))
    if not health.get("ok") or not me.get("ok"):
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
