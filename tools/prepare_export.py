#!/usr/bin/env python3
"""
Post-process the wget mirror into something publishable.

wget rewrites the links it followed, but leaves the head references it was told
to reject (feeds, oEmbed, the REST root, script-module preloads) pointing at
localhost. Those would 404 for anyone opening the preview, so they are stripped
rather than left to fail quietly.
"""

import os
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent / "export"
LOCAL = "127.0.0.1:8734"

BANNER = """
<div id="bcms-preview-note">
  <span><strong>Static preview.</strong> Real pages, real content, real responsive layout &mdash;
  exported from the working build. The dashboard, the industry filter and the enquiry form
  need a live server; they are running on mine.</span>
  <button type="button" aria-label="Dismiss" onclick="this.parentNode.remove()">&times;</button>
</div>
<style>
#bcms-preview-note{position:fixed;left:0;right:0;bottom:0;z-index:9999;display:flex;gap:1rem;
align-items:center;justify-content:center;padding:.75rem 1.25rem;background:#0e2338;color:#e8edf3;
font:400 13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
box-shadow:0 -1px 0 rgba(255,255,255,.08)}
#bcms-preview-note strong{color:#d9b27e}
#bcms-preview-note button{background:none;border:0;color:inherit;font-size:20px;line-height:1;
cursor:pointer;opacity:.6;padding:0 .25rem}
#bcms-preview-note button:hover{opacity:1}
@media(max-width:600px){#bcms-preview-note{font-size:12px;text-align:left}}
</style>
"""

# Tags whose src/href still points at the dev server after the mirror.
DEAD_TAG = re.compile(
    r"[ \t]*<(?:link|script)\b[^>]*" + re.escape(LOCAL) + r"[^>]*?(?:/>|>(?:</script>)?)\s*\n?",
    re.IGNORECASE,
)


def flatten_query_filenames() -> int:
    """
    wget saves `style.css?ver=1.0.0` as a file whose name literally contains a
    question mark. Almost every static host treats the encoded `?` in the
    resulting URL as the start of a query string and 404s, so the files are
    renamed and every reference rewritten.
    """
    renames = {}

    for path in sorted(ROOT.rglob("*")):
        if not path.is_file() or "?" not in path.name:
            continue
        # Everything from the `?` onwards is cache-busting noise; the real
        # extension is always in front of it.
        clean = path.name.split("?", 1)[0]
        target = path.with_name(clean)
        if target.exists():
            target.unlink()
        path.rename(target)
        renames[path.name] = clean

    if not renames:
        return 0

    for path in ROOT.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in (".html", ".css", ".js"):
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        original = text
        for old, new in renames.items():
            # wget writes the reference percent-encoded; the raw form shows up
            # inside inline styles and scripts.
            text = text.replace(old.replace("?", "%3F"), new).replace(old, new)
        if text != original:
            path.write_text(text, encoding="utf-8")

    return len(renames)


ASSET_REF = re.compile(r"(?:\.\./)*wp-content/")


def fix_asset_depth() -> int:
    """
    wget gets the `../` count wrong for the taxonomy pages, which sit two levels
    deep — their asset references come out one level short and 404. The correct
    prefix is a pure function of how deep the file is, so it is recomputed
    rather than trusted.
    """
    fixed = 0

    for path in ROOT.rglob("*.html"):
        depth = len(path.relative_to(ROOT).parts) - 1
        prefix = "../" * depth

        text = path.read_text(encoding="utf-8", errors="replace")
        new = ASSET_REF.sub(prefix + "wp-content/", text)

        if new != text:
            path.write_text(new, encoding="utf-8")
            fixed += 1

    return fixed


def main() -> None:
    touched = 0
    stripped = 0
    renamed = flatten_query_filenames()
    depth_fixed = fix_asset_depth()

    for path in ROOT.rglob("*.html"):
        html = path.read_text(encoding="utf-8", errors="replace")

        html, n = DEAD_TAG.subn("", html)
        stripped += n

        if "bcms-preview-note" not in html and "</body>" in html:
            html = html.replace("</body>", BANNER + "</body>", 1)

        path.write_text(html, encoding="utf-8")
        touched += 1

    (ROOT / ".nojekyll").write_text("")

    remaining = sum(
        1 for p in ROOT.rglob("*.html") if LOCAL in p.read_text(encoding="utf-8", errors="replace")
    )

    print(f"{renamed} query-string filenames flattened, {depth_fixed} pages depth-corrected")
    print(f"{touched} pages processed, {stripped} dead tags stripped")
    print(f"pages still referencing {LOCAL}: {remaining}")


if __name__ == "__main__":
    main()
