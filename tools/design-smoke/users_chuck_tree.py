#!/usr/bin/env python3
"""E2E: admin Users edit shows Chuck-tree checkbox (admin-only page).

Run: /root/.venv-playwright/bin/python tools/design-smoke/users_chuck_tree.py
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

    db_fd, db_path = tempfile.mkstemp(suffix="-reps-chuck-users.sqlite")
    os.close(db_fd)
    env = os.environ.copy()
    env["REPS_DASH_DB_PATH"] = db_path
    env["REPS_SHIFT_API_BASE"] = "fake://shift"
    env["FAKE_SHIFT_INLINE"] = "1"
    env["REPS_MICROPS_API_BASE"] = "fake://microps"
    env["FAKE_MICROPS_INLINE"] = "1"

    init = subprocess.run(
        [
            "php",
            "-r",
            "require '"
            + str(ROOT / "public/dashboard/includes/bootstrap.php")
            + "';"
            "echo repsDashFindUserByUsername('mark')['role'];",
        ],
        cwd=str(ROOT),
        env=env,
        capture_output=True,
        text=True,
    )
    if init.returncode != 0 or "admin" not in init.stdout:
        print(init.stdout, init.stderr, file=sys.stderr)
        return 1

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
            page.fill('input[name="username"]', "mark")
            page.fill('input[name="password"]', "reps-demo")
            page.click('button[type="submit"]')
            page.wait_for_url("**/dashboard/**")

            # Sales seat Jim — Chuck-tree checkbox visible when expanded
            page.goto(f"{base}/dashboard/users.php?q=jim", wait_until="domcontentloaded")
            page.locator(".rd-ledger__toggle").first.click()
            page.wait_for_selector('input[name="chuck_tree"]', state="visible", timeout=10000)
            assert page.locator("label:text-is('Chuck-tree')").count() >= 1
            desk = OUT / "users_chuck_tree_desktop.png"
            page.screenshot(path=str(desk), full_page=True)

            # Sales seat must not edit Users / Chuck-tree
            page.goto(f"{base}/dashboard/logout.php", wait_until="domcontentloaded")
            page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
            page.fill('input[name="username"]', "jim")
            page.fill('input[name="password"]', "reps-demo")
            page.click('button[type="submit"]')
            page.wait_for_url("**/dashboard/**")
            page.goto(f"{base}/dashboard/users.php", wait_until="domcontentloaded")
            assert "users.php" not in page.url or page.locator('input[name="chuck_tree"]').count() == 0

            # Mobile — admin again, Jim row
            page.goto(f"{base}/dashboard/logout.php", wait_until="domcontentloaded")
            page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded")
            page.fill('input[name="username"]', "mark")
            page.fill('input[name="password"]', "reps-demo")
            page.click('button[type="submit"]')
            page.set_viewport_size({"width": 390, "height": 844})
            page.goto(f"{base}/dashboard/users.php?q=jim", wait_until="domcontentloaded")
            page.locator(".rd-ledger__toggle").first.click()
            page.wait_for_selector('input[name="chuck_tree"]', state="visible", timeout=10000)
            mob = OUT / "users_chuck_tree_mobile.png"
            page.screenshot(path=str(mob), full_page=True)
            browser.close()
        print("ok", desk, mob)
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
    sys.exit(main())
