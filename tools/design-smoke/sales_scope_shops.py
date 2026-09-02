#!/usr/bin/env python3
"""E2E: sales seat Shops list excludes unassigned / other-rep shops.

Run: /root/.venv-playwright/bin/python tools/design-smoke/sales_scope_shops.py
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

    db_fd, db_path = tempfile.mkstemp(suffix="-reps-sales-scope.sqlite")
    os.close(db_fd)
    env = os.environ.copy()
    env["REPS_DASH_DB_PATH"] = db_path
    env["REPS_SHIFT_API_BASE"] = "fake://shift"
    env["FAKE_SHIFT_INLINE"] = "1"
    env["REPS_MICROPS_API_BASE"] = "fake://microps"
    env["FAKE_MICROPS_INLINE"] = "1"

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

            # --- Sales scope ---
            page = browser.new_page(viewport={"width": 1280, "height": 800})
            page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
            page.fill("#username", "jim")
            page.fill("#password", "reps-demo")
            page.locator('button[type="submit"]').click()
            page.wait_for_url("**/dashboard/**", timeout=30000)
            page.goto(f"{base}/dashboard/shops.php", wait_until="domcontentloaded")
            body = page.inner_text("body")
            page.screenshot(path=str(OUT / "sales_scope_shops_desktop.png"), full_page=True)
            assert "North Texas Fleet Wash" in body, "jim should see own shop"
            assert "Unassigned Pool Shop" not in body, "jim must not see unassigned pool"
            assert "Seven Mobile Detail" not in body, "jim must not see seven's shop"
            page.set_viewport_size({"width": 390, "height": 844})
            page.goto(f"{base}/dashboard/shops.php", wait_until="domcontentloaded")
            page.screenshot(path=str(OUT / "sales_scope_shops_mobile.png"), full_page=True)
            page.close()

            # --- Admin reassign UI (isolated context) ---
            ctx = browser.new_context(viewport={"width": 1280, "height": 800})
            admin = ctx.new_page()
            admin.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
            admin.fill("#username", "mark")
            admin.fill("#password", "reps-demo")
            admin.locator('button[type="submit"]').click()
            admin.wait_for_url("**/dashboard/**", timeout=30000)
            admin.goto(f"{base}/dashboard/shop.php?id=104", wait_until="domcontentloaded")
            assert admin.locator("text=Reassign sales rep").count() > 0
            admin.screenshot(path=str(OUT / "admin_shop_reassign_desktop.png"), full_page=True)
            ctx.close()

            print("OK sales scope shops + admin reassign UI")
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
