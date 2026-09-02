#!/usr/bin/env python3
"""Visual + functional e2e: Money does not invent mock ledger when demo seed is locked.

Uses ephemeral php -S + temp SQLite. Inspect screenshots before claiming pass.
Run: /root/.venv-playwright/bin/python tools/design-smoke/money_mock_seed_gate.py
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
VENV_PY = Path("/root/.venv-playwright/bin/python")


def free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("playwright missing; use /root/.venv-playwright/bin/python", file=sys.stderr)
        return 2

    db_fd, db_path = tempfile.mkstemp(suffix="-reps-mock-seed.sqlite")
    os.close(db_fd)
    env = os.environ.copy()
    env["REPS_DASH_DB_PATH"] = db_path
    env["REPS_SHIFT_API_BASE"] = "fake://shift"
    env["FAKE_SHIFT_INLINE"] = "1"
    env["REPS_MICROPS_API_BASE"] = "fake://microps"
    env["FAKE_MICROPS_INLINE"] = "1"
    # Prod-like: gate must refuse mock seed
    env["APP_ENV"] = "production"
    env["REPS_PUBLIC_HOST"] = "reps.decisionsciencecorp.com"
    env.pop("REPS_DASH_ALLOW_MOCK_LEDGER", None)

    init = subprocess.run(
        [
            "php",
            "-r",
            (
                "require '"
                + str(ROOT / "public/dashboard/includes/bootstrap.php")
                + "';"
                "repsDashAppMetaSet('dash.skip_demo_seed','1');"
                "repsDashAppMetaSet('shift.live_data','0');"
                "repsDashDb()->exec('DELETE FROM ledger_lines');"
                "echo 'ledger='.(int)repsDashDb()->query('SELECT COUNT(*) FROM ledger_lines')->fetchColumn()"
                ". ' users='.(int)repsDashDb()->query('SELECT COUNT(*) FROM users')->fetchColumn();"
            ),
        ],
        cwd=str(ROOT),
        env=env,
        capture_output=True,
        text=True,
        check=False,
    )
    if init.returncode != 0:
        print(init.stdout, init.stderr, file=sys.stderr)
        return 1
    print("init:", init.stdout.strip())

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
            page.goto(f"{base}/dashboard/login.php", wait_until="domcontentloaded", timeout=60000)
            page.fill('input[name="username"]', "mark")
            page.fill('input[name="password"]', "reps-demo")
            page.click('button[type="submit"]')
            page.wait_for_load_state("networkidle", timeout=30000)
            if "login.php" in page.url:
                print("LOGIN FAIL", page.url, file=sys.stderr)
                page.screenshot(path=str(OUT / "mock_seed_login_fail.png"))
                return 1

            page.goto(f"{base}/dashboard/money.php", wait_until="domcontentloaded", timeout=60000)
            page.wait_for_load_state("networkidle", timeout=30000)
            desk = OUT / "money_mock_seed_desktop.png"
            page.screenshot(path=str(desk), full_page=True)
            body = page.inner_text("body")
            assert "Ledger owed" in body, "admin Money missing ledger owed"
            owed_el = page.locator("text=Ledger owed").locator("xpath=..")
            owed_text = owed_el.inner_text()
            assert "$0.00" in owed_text, f"mock seed leaked into ledger: {owed_text!r}"

            page.set_viewport_size({"width": 390, "height": 844})
            page.goto(f"{base}/dashboard/money.php", wait_until="domcontentloaded", timeout=60000)
            mob = OUT / "money_mock_seed_mobile.png"
            page.screenshot(path=str(mob), full_page=True)

            for path in (desk, mob):
                assert path.stat().st_size > 5000, f"screenshot too small: {path}"
                print("screenshot ok", path.name, path.stat().st_size)

            browser.close()

        check = subprocess.run(
            [
                "php",
                "-r",
                (
                    "require '"
                    + str(ROOT / "public/dashboard/includes/bootstrap.php")
                    + "';"
                    "echo (int)repsDashDb()->query('SELECT COUNT(*) FROM ledger_lines')->fetchColumn();"
                ),
            ],
            cwd=str(ROOT),
            env=env,
            capture_output=True,
            text=True,
            check=True,
        )
        n = int(check.stdout.strip() or "0")
        assert n == 0, f"ledger_lines not empty after Money: {n}"
        print("OK money mock-seed gate (functional + screenshots)")
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
