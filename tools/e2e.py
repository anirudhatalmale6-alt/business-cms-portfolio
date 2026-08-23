#!/usr/bin/env python3
"""
End-to-end checks against the running demo.

These assert behaviour, not appearance: the filter really swaps the grid, the
spam gates really reject, and a good submission really lands in the dashboard.
"""

import os
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8734"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "shots")

results = []


def check(name, ok, detail=""):
    results.append((name, ok, detail))
    print(("PASS  " if ok else "FAIL  ") + name + ((" — " + detail) if detail else ""))


def main() -> int:
    os.makedirs(OUT, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page(viewport={"width": 1280, "height": 720})

        # The spam-gate probes below deliberately provoke 400s, so a raw console
        # listener would always be noisy. 5xx is the signal that matters.
        server_errors = []
        page.on(
            "response",
            lambda r: server_errors.append(f"{r.status} {r.url}") if r.status >= 500 else None,
        )

        # ---- Industry filter swaps the grid without a page load -----------
        page.goto(BASE + "/projects/", wait_until="networkidle")
        before = page.locator(".bcms-card").count()
        page.click('[data-bcms-filter="healthcare"]')
        page.wait_for_timeout(1200)
        after = page.locator(".bcms-card").count()
        check(
            "filter narrows the grid",
            before == 6 and after == 1,
            f"{before} -> {after}",
        )
        check(
            "filter updates the URL",
            "/industry/healthcare/" in page.url,
            page.url,
        )
        page.screenshot(path=os.path.join(OUT, "11-filter-applied.png"))

        # Clearing the filter must swap in place too. If the fetch 500s the
        # script falls back to a full page load, which also ends up showing six
        # cards — so the card count alone would pass on a broken endpoint.
        reset_status = page.evaluate(
            """async () => {
                const r = await fetch(BCMS_FRONT.root + 'grid?industry=&count=6&summary=true');
                return r.status;
            }"""
        )
        check("clearing the filter returns 200", reset_status == 200, f"HTTP {reset_status}")

        page.click('[data-bcms-filter=""]')
        page.wait_for_timeout(1200)
        check("filter resets to all", page.locator(".bcms-card").count() == 6)

        # ---- Client-side validation ---------------------------------------
        page.goto(BASE + "/contact/", wait_until="networkidle")
        page.click('.bcms-form button[type="submit"]')
        page.wait_for_timeout(400)
        check(
            "empty form is blocked",
            page.locator('[data-error-for="name"]').inner_text().strip() != "",
        )
        page.screenshot(path=os.path.join(OUT, "12-form-validation.png"))

        # ---- Honeypot is rejected server-side ------------------------------
        rejected = page.evaluate(
            """async () => {
                const r = await fetch(BCMS_FRONT.root + 'enquiry', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BCMS_FRONT.nonce },
                    body: JSON.stringify({
                        name: 'Spam Bot',
                        email: 'bot@example.com',
                        message: 'Buy cheap things right now from our website.',
                        bcms_website_url: 'http://spam.example.com',
                        rendered_at: Math.floor(Date.now() / 1000) - 60
                    })
                });
                return r.status;
            }"""
        )
        check("honeypot submission rejected", rejected == 400, f"HTTP {rejected}")

        # ---- Timing gate ----------------------------------------------------
        too_fast = page.evaluate(
            """async () => {
                const r = await fetch(BCMS_FRONT.root + 'enquiry', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BCMS_FRONT.nonce },
                    body: JSON.stringify({
                        name: 'Fast Bot',
                        email: 'fast@example.com',
                        message: 'Instant submission from a script, no human typing.',
                        rendered_at: Math.floor(Date.now() / 1000)
                    })
                });
                return r.status;
            }"""
        )
        check("instant submission rejected", too_fast == 400, f"HTTP {too_fast}")

        # ---- Server-side validation ----------------------------------------
        short = page.evaluate(
            """async () => {
                const r = await fetch(BCMS_FRONT.root + 'enquiry', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BCMS_FRONT.nonce },
                    body: JSON.stringify({
                        name: 'Real Person',
                        email: 'not-an-email',
                        message: 'hi',
                        rendered_at: Math.floor(Date.now() / 1000) - 30
                    })
                });
                return [r.status, await r.json()];
            }"""
        )
        check(
            "bad email + short message rejected with field errors",
            short[0] == 422 and "email" in short[1].get("data", {}).get("fields", {}),
            f"HTTP {short[0]}",
        )

        # ---- A genuine submission goes through ------------------------------
        page.goto(BASE + "/contact/", wait_until="networkidle")
        page.fill('[name="name"]', "Helena Ward")
        page.fill('[name="email"]', "helena.ward@example.com")
        page.fill('[name="company"]', "Ward & Pike LLP")
        page.fill('[name="phone"]', "+44 20 7946 0812")
        page.fill(
            '[name="message"]',
            "We are mid-way through a merger and our two finance teams are reporting "
            "different numbers for the same month. Can you take a look before the "
            "September board?",
        )
        page.wait_for_timeout(3500)  # clear the minimum fill time
        page.click('.bcms-form button[type="submit"]')
        page.wait_for_selector(".bcms-form-success", timeout=8000)
        check("genuine enquiry accepted", page.locator(".bcms-form-success").count() == 1)
        page.screenshot(path=os.path.join(OUT, "13-form-success.png"))

        check("no server errors during the run", not server_errors, "; ".join(server_errors[:3]))

        browser.close()

    failed = [r for r in results if not r[1]]
    print(f"\n{len(results) - len(failed)}/{len(results)} passed")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
