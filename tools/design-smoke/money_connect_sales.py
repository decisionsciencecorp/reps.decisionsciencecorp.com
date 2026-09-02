#!/usr/bin/env python3
"""E2E: sales seat Money → Set up payouts reaches Connect start (not 403).

Without Stripe keys: expect warning page (not 403). With keys: Stripe redirect.
Run: /root/.venv-playwright/bin/python tools/design-smoke/money_connect_sales.py
"""
from __future__ import annotations

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


def free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    from playwright.sync_api import sync_playwright

    db_fd, db_path = tempfile.mkstemp(suffix="-reps-sales-connect.sqlite")
    os.close(db_fd)
    env = os.environ.copy()
    env["REPS_DASH_DB_PATH"] = db_path
    env["REPS_SHIFT_API_BASE"] = "fake://shift"
    env["FAKE_SHIFT_INLINE"] = "1"
    env["REPS_MICROPS_API_BASE"] = "fake://microps"
    env["FAKE_MICROPS_INLINE"] = "1"
    # No Stripe keys → onboarding page with warning, not 403
    env.pop("STRIPE_SECRET_KEY", None)
    env["STRIPE_SECRET_KEY"] = ""

    init = subprocess.run(
        [
            "php",
            "-r",
            "require '"
            + str(ROOT / "public/dashboard/includes/bootstrap.php")
            + "';"
            "echo repsDashFindUserByUsername('jim')['role'];",
        ],
        cwd=str(ROOT),
        env=env,
        capture_output=True,
        text=True,
    )
    if init.returncode != 0 or "sales" not in init.stdout:
        print(init.stdout, init.stderr, file=sys.stderr)
        return 1
    print("sales seat:", init.stdout.strip())

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
        time.sleep(0.5)
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            page = browser.new_page(viewport={"width": 1280, "height": 800})
            page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
            page.fill('input[name="username"]', "jim")
            page.fill('input[name="password"]', "reps-demo")
            page.click('button[type="submit"]')
            page.wait_for_load_state("networkidle")
            assert "login.php" not in page.url, "login failed"

            page.goto(f"{base}/dashboard/money.php", wait_until="domcontentloaded")
            page.wait_for_load_state("networkidle")
            desk = OUT / "sales_money_desktop.png"
            page.screenshot(path=str(desk), full_page=True)

            # Prefer explicit Connect CTA
            form = page.locator('form[action="/dashboard/connect/start.php"]').first
            assert form.count() > 0, "sales Money missing Connect start form"
            csrf = form.locator('input[name="csrf_token"]').input_value()
            # POST as the logged-in sales seat (button may be disabled without Stripe keys).
            resp = page.request.post(
                f"{base}/dashboard/connect/start.php",
                form={"csrf_token": csrf},
                max_redirects=5,
            )
            status = resp.status
            body = resp.text()
            final_url = resp.url
            assert status != 403, f"sales Connect still 403: {body[:300]!r}"
            assert "for business owners and solo operators" not in body.lower()
            # Render result page for visual inspect
            page.goto(final_url if final_url.startswith(base) else f"{base}/dashboard/connect/start.php", wait_until="domcontentloaded")
            # If POST consumed CSRF, re-open money then show result HTML via set_content if needed
            if status >= 400 and "Payout setup" not in body and "Stripe" not in body:
                page.set_content(body)
            vis = page.inner_text("body") if page.locator("body").count() else body
            ok = (
                status in (200, 302, 303)
                or "stripe.com" in final_url
                or "Payout setup" in body
                or "Stripe" in body
                or "not loaded" in body.lower()
                or "not configured" in body.lower()
                or "unavailable" in body.lower()
                or "Back to My pay" in body
            )
            assert ok, f"unexpected connect result status={status} url={final_url!r} body={body[:400]!r}"
            page.screenshot(path=str(OUT / "sales_connect_result_desktop.png"), full_page=True)
            page.set_viewport_size({"width": 390, "height": 844})
            page.screenshot(path=str(OUT / "sales_connect_mobile.png"), full_page=True)
            print("OK sales Connect start (not 403)", status, final_url)
            browser.close()
            return 0
    finally:
        php.terminate()
        try:
            php.wait(timeout=5)
        except subprocess.TimeoutExpired:
            php.kill()
        try:
            os.unlink(db_path)
        except OSError:
            pass


if __name__ == "__main__":
    raise SystemExit(main())
