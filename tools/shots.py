#!/usr/bin/env python3
"""Viewport screenshots of the demo build."""

import os
import sys

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8734"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "shots")

# (filename, path, scroll-y)
SHOTS = [
    ("01-home-hero", "/", 0),
    ("02-home-work", "/", 1080),
    ("03-home-capabilities", "/", 2100),
    ("04-home-proof", "/", 2900),
    ("05-work-archive", "/projects/", 0),
    ("06-work-archive-scrolled", "/projects/", 640),
    ("07-case-study", "/projects/ardent-capital-reporting/", 0),
    ("08-case-study-body", "/projects/ardent-capital-reporting/", 900),
    ("09-industry-filter", "/industry/healthcare/", 0),
    ("10-contact", "/contact/", 0),
    ("14-services", "/services/", 0),
    ("15-services-list", "/services/", 700),
    ("16-clients", "/clients/", 0),
    ("17-clients-quotes", "/clients/", 900),
    ("18-service-archive", "/service/data-platform/", 0),
]

MOBILE = [
    ("m1-home", "/", 0),
    ("m2-work", "/projects/", 300),
    ("m3-case", "/projects/ardent-capital-reporting/", 0),
]


def main() -> int:
    os.makedirs(OUT, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch()

        page = browser.new_page(viewport={"width": 1280, "height": 720})
        for name, path, scroll in SHOTS:
            page.goto(BASE + path, wait_until="networkidle")
            if scroll:
                page.evaluate(f"window.scrollTo(0, {scroll})")
                page.wait_for_timeout(400)
            page.screenshot(path=os.path.join(OUT, f"{name}.png"))
            print(name)
        page.close()

        mobile = browser.new_page(
            viewport={"width": 390, "height": 780},
            device_scale_factor=1,
            is_mobile=True,
            has_touch=True,
        )
        for name, path, scroll in MOBILE:
            mobile.goto(BASE + path, wait_until="networkidle")
            if scroll:
                mobile.evaluate(f"window.scrollTo(0, {scroll})")
                mobile.wait_for_timeout(400)
            mobile.screenshot(path=os.path.join(OUT, f"{name}.png"))
            print(name)
        mobile.close()

        browser.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
