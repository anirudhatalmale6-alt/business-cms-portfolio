# Business CMS — corporate portfolio platform

A WordPress-based content management system built for one job: presenting a
professional services portfolio properly, and staying maintainable by a
non-technical team afterwards.

Two pieces:

| Component | What it is |
|---|---|
| `business-cms-core` | Plugin. Content structure, blocks, enquiry capture, spam filtering, CRM hand-off, analytics, SEO. Survives a theme change. |
| `meridian` | Block theme. The corporate look: templates, palette, typography, patterns. Survives a plugin change. |

The split is deliberate. Structure and data live in the plugin, presentation
lives in the theme, so a redesign in two years does not put the portfolio at
risk.

---

## What it does

**Portfolio**
- `Projects` custom post type with hero image, case-study body and revisions
- `Industry` (hierarchical) and `Service` (flat) taxonomies for tagging
- Per-project fields: client, year, location, engagement length, one-line
  summary, hero video URL, live site URL, feature-on-homepage flag
- Up to four headline result figures per project, edited in the sidebar
- Filterable project grid, usable on any page, with server-rendered fallback

**Editing**
- Native block editor — no page-builder plugin, no licence, no lock-in
- Four custom blocks: project grid, headline results, project facts, contact form
- Six page patterns (hero, logo wall, selected work, capabilities, proof, CTA)
- Custom templates for pages, case studies, industry archives and contact

**Permissions**
- `Portfolio Manager` — full control of projects, industries and the enquiry
  inbox; cannot touch plugins, themes, users or settings
- `Case Study Writer` — writes and edits projects, cannot publish them, never
  sees the enquiry inbox
- Administrators and Editors keep everything

**Enquiries**
- Contact form block with three independent spam gates: honeypot field,
  minimum fill time, and optional Cloudflare Turnstile
- Every enquiry is stored as a record before anything else happens, so a mail
  or webhook failure can never lose a lead
- Email notification with the sender set as Reply-To
- JSON POST to any Zapier / Make / n8n catch hook, with automatic retry on a
  5 / 25 / 125 minute backoff and a visible Sent / Failed / Off status per lead

**Analytics and SEO**
- GA4 and/or Matomo, skipped for logged-in editors so the team's own browsing
  never pollutes the reports
- Titles, meta descriptions, canonicals, Open Graph, Twitter cards
- JSON-LD: Organization, CreativeWork per case study, BreadcrumbList
- Projects and taxonomies added to the core XML sitemap
- Stands down automatically if Yoast or Rank Math is ever installed, rather
  than emitting a second conflicting set of tags

**Performance**
- Per-block stylesheets rather than one monolithic bundle
- Front-end JavaScript loads only on pages that contain a grid or a form
- No web fonts, no jQuery on the front end, no external CSS
- Case-study hero images are marked high priority instead of lazy-loaded

---

## Requirements

- PHP 8.0+ (8.2 recommended)
- MySQL 5.7+ / MariaDB 10.4+
- WordPress 6.4+
- HTTPS

---

## Installation

1. Install WordPress as normal on the target host.
2. Copy `business-cms-core` into `wp-content/plugins/` and activate it.
3. Copy `meridian` into `wp-content/themes/` and activate it.
4. Settings → Permalinks → Save (registers the `/work/` and `/industry/` URLs).
5. Portfolio → Settings — fill in the company details, analytics IDs, the
   notification address and, if you use one, the CRM webhook.

Nothing else is required. Every integration field is optional; an empty field
means that feature is simply off.

### Demo content

```bash
wp eval-file tools/seed-demo.php            # six projects, five industries, pages, menu
wp eval-file tools/seed-demo.php --clean    # remove them again before real content
```

---

## Repository layout

```
wp-content/plugins/business-cms-core/   the plugin      -> copy to your site
wp-content/themes/meridian/             the theme       -> copy to your site
docs/ADMIN-GUIDE.md                     written for the content team
tools/seed-demo.php                     demo content seeder
tools/make_placeholders.py              generates the placeholder artwork
tools/e2e.py                            end-to-end behaviour tests
tools/shots.py, tools/admin_shots.py    screenshot capture
tools/prepare_export.py                 builds the static preview
assets/placeholders/                    the demo artwork (drawn, not stock)
shots/                                  captured screenshots
```

The `wp-content` tree mirrors a WordPress install, so deploying is a matter of
copying those two directories across — nothing needs building or compiling.

---

## Tests

`tools/e2e.py` drives a real browser against a running site and asserts
behaviour rather than appearance:

```
PASS  filter narrows the grid — 6 -> 1
PASS  filter updates the URL
PASS  clearing the filter returns 200
PASS  filter resets to all
PASS  empty form is blocked
PASS  honeypot submission rejected — HTTP 400
PASS  instant submission rejected — HTTP 400
PASS  bad email + short message rejected with field errors — HTTP 422
PASS  genuine enquiry accepted
PASS  no server errors during the run
```

The CRM hand-off was verified separately against a live listener — a real JSON
payload delivered on success, and on a deliberately unreachable endpoint the
lead was still stored, marked `failed`, and a retry scheduled.

---

## Extending it

The architecture assumes more will be added later.

- **Blog** — already present. WordPress posts use `templates/single.html` and
  `templates/index.html`; nothing needs building.
- **E-commerce** — install WooCommerce. The theme's `theme.json` palette and
  type scale apply to Woo blocks automatically; only checkout templates would
  need styling.
- **A new portfolio category** — add an Industry term. Its archive, filter chip,
  sitemap entry and breadcrumb appear on their own.
- **New project fields** — add one entry to `BCMS_Meta::schema()`. The sidebar
  control, REST exposure and sanitisation follow from that one line.
- **CRM payload** — filter `bcms_crm_payload` to add lead scores, owner routing
  or campaign tags without editing the plugin.
- **Post-enquiry actions** — hook `bcms_enquiry_received`.

---

Built by Anirudha Talmale.
