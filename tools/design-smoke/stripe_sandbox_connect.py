#!/usr/bin/env python3
"""E2E: Stripe sandbox harness → Connect return page (sales).

Runs the PHP mock harness, then logs in as jim and opens return.php.
Screenshots desktop + mobile; asserts payee ready copy.

Run: /root/.venv-playwright/bin/python tools/design-smoke/stripe_sandbox_connect.py
"""
from __future__ import annotations

import json
import os
import socket
import subprocess
import sys
import tempfile
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent / "output"
PUBLIC = ROOT / "public"
PY = os.environ.get("REPS_PLAYWRIGHT_PYTHON", "/root/.venv-playwright/bin/python")


def free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    from playwright.sync_api import sync_playwright

    db_fd, db_path = tempfile.mkstemp(suffix="-reps-stripe-sandbox.sqlite")
    os.close(db_fd)
    env = os.environ.copy()
    env["REPS_DASH_DB_PATH"] = db_path
    env["REPS_SHIFT_API_BASE"] = "fake://shift"
    env["FAKE_SHIFT_INLINE"] = "1"
    env["REPS_MICROPS_API_BASE"] = "fake://microps"
    env["FAKE_MICROPS_INLINE"] = "1"
    env["REPS_PUBLIC_BASE"] = "https://reps.decisionsciencecorp.com"
    env.pop("STRIPE_SECRET_KEY", None)

    # Seed DB + run harness
    harness = subprocess.run(
        ["php", str(ROOT / "tools/stripe-sandbox-smoke.php"), "--username=jim"],
        cwd=str(ROOT),
        env=env,
        capture_output=True,
        text=True,
    )
    if harness.returncode != 0:
        print(harness.stdout, harness.stderr, file=sys.stderr)
        return 1
    data = json.loads(harness.stdout)
    if not data.get("ok"):
        print(data, file=sys.stderr)
        return 1
    payee_id = (data.get("payee") or {}).get("id")
    if not payee_id:
        print("missing payee id", data, file=sys.stderr)
        return 1
    print("harness ok payee_id=", payee_id, "url=", data.get("onboarding_url", "")[:60])

    port = free_port()
    base = f"http://127.0.0.1:{port}"
    php = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(PUBLIC)],
        cwd=str(ROOT),
        env=env,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    try:
        time.sleep(0.4)
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            for label, w, h in (("desktop", 1280, 800), ("mobile", 390, 844)):
                page = browser.new_page(viewport={"width": w, "height": h})
                page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
                page.fill('input[name="username"]', "jim")
                page.fill('input[name="password"]', "reps-demo")
                page.click('button[type="submit"]')
                page.wait_for_url("**/dashboard/**", timeout=15000)
                page.goto(
                    f"{base}/dashboard/connect/return.php?payee_id={payee_id}",
                    wait_until="domcontentloaded",
                )
                body = page.inner_text("body")
                assert "Back from Stripe" in body or "Payout setup" in body
                assert "payouts_enabled=1" in body or "ready" in body.lower()
                shot = OUT / f"stripe_sandbox_return_{label}.png"
                page.screenshot(path=str(shot), full_page=True)
                print("wrote", shot)
                page.close()
            browser.close()
    finally:
        php.terminate()
        try:
            php.wait(timeout=3)
        except subprocess.TimeoutExpired:
            php.kill()
        try:
            os.unlink(db_path)
        except OSError:
            pass

    return 0


if __name__ == "__main__":
    sys.exit(main())
