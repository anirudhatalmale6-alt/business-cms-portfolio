#!/usr/bin/env python3
"""Screenshots of the dashboard, which is the part the client's team lives in."""

import os
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8734"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "shots")
USER, PASS = "demoadmin", "Portfolio2026!demo"


def main() -> int:
    os.makedirs(OUT, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport={"width": 1280, "height": 720})

        page.goto(BASE + "/wp-login.php", wait_until="networkidle")
        page.fill("#user_login", USER)
        page.fill("#user_pass", PASS)
        page.click("#wp-submit")
        page.wait_for_url("**/wp-admin/**", timeout=15000)

        page.goto(BASE + "/wp-admin/index.php", wait_until="networkidle")
        page.wait_for_timeout(600)
        page.screenshot(path=os.path.join(OUT, "20-dashboard.png"))
        print("dashboard")

        page.goto(BASE + "/wp-admin/edit.php?post_type=bcms_project", wait_until="networkidle")
        page.wait_for_timeout(600)
        page.screenshot(path=os.path.join(OUT, "21-projects-list.png"))
        print("projects list")

        # Open the first project and prove the custom sidebar panel renders.
        page.click("a.row-title")
        page.wait_for_selector(".editor-header, .edit-post-header", timeout=60000)
        page.wait_for_timeout(3500)

        # Dismiss the welcome modal if this is a fresh profile.
        close = page.locator('button[aria-label="Close"]').first
        if close.count() and close.is_visible():
            close.click()
            page.wait_for_timeout(500)

        panel = page.locator('.bcms-project-panel')
        if panel.count():
            header = page.locator('.bcms-project-panel .components-panel__body-toggle').first
            if header.count() and header.get_attribute("aria-expanded") == "false":
                header.click()
                page.wait_for_timeout(600)
            panel.first.scroll_into_view_if_needed()
            page.wait_for_timeout(400)
        print("editor panel present:", panel.count() > 0)
        page.screenshot(path=os.path.join(OUT, "22-project-editor.png"))

        page.goto(BASE + "/wp-admin/edit.php?post_type=bcms_enquiry", wait_until="networkidle")
        page.wait_for_timeout(600)
        page.screenshot(path=os.path.join(OUT, "23-enquiries.png"))
        print("enquiries")

        page.goto(
            BASE + "/wp-admin/edit.php?post_type=bcms_project&page=bcms-settings",
            wait_until="networkidle",
        )
        page.wait_for_timeout(600)
        page.screenshot(path=os.path.join(OUT, "24-settings.png"))
        page.evaluate("window.scrollTo(0, 900)")
        page.wait_for_timeout(400)
        page.screenshot(path=os.path.join(OUT, "25-settings-integrations.png"))
        print("settings")

        page.goto(BASE + "/wp-admin/users.php", wait_until="networkidle")
        page.wait_for_timeout(600)
        page.screenshot(path=os.path.join(OUT, "26-roles.png"))
        print("roles")

        browser.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
