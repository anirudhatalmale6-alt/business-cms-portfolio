# Administrator guide

Written for whoever maintains the site day to day. No code anywhere in here.

---

## 1. Adding a project

**Portfolio → Add Project.**

1. **Title** — the headline of the case study. Write it as an outcome, not a
   label. "Cutting a month-end close from nine days to two" works harder than
   "Ardent Capital".
2. **Hero image** — right-hand sidebar, *Hero image*. Upload at least
   1600 × 1200. The system crops its own versions for cards, heroes and social
   previews; you never need to resize anything yourself.
3. **Project details** (sidebar panel) — client, year, location, engagement
   length, and the one-line summary. The summary is what appears on the
   portfolio card and, if nothing else is set, in Google results.
4. **Headline results** — up to four figures with labels. These render as the
   band across the top of the case study.
5. **Industry and Services** — sidebar. Industry drives the portfolio filter, so
   every project should have exactly one. Services can have several.
6. **Body** — write it in the block editor. A useful shape: the situation, the
   brief you were given, what you changed, what it was worth.
7. **Feature on the homepage** — the toggle in Project details. Anything switched
   on here is eligible for the homepage grid.
8. **Publish.**

The project now appears in the portfolio, on its industry archive, in the
sitemap and in the site search. Nothing else needs doing.

### Reordering projects

The grid sorts by the **Order** field (Page Attributes panel), lowest first,
then by date. Set 1, 2, 3 on the projects you want at the front and leave the
rest at 0.

---

## 2. Adding a new industry category

**Portfolio → Industries → Add New.**

Give it a name and, ideally, a description — the description appears as the
introduction on that industry's archive page.

The moment a project is tagged with it, the industry gets:
- its own page at `/industry/<name>/`
- a chip in the portfolio filter, with a live count
- an entry in the XML sitemap

There is nothing to build and no template to create.

---

## 3. Building and editing pages

**Pages → Add New**, then write with blocks.

To reuse a designed section rather than starting from scratch: click the **+**
button, open the **Patterns** tab and choose the **Meridian** category. Six
sections are ready to drop in — hero, client logos, selected work,
capabilities, proof band and closing call to action. Once inserted, every word
and colour in them is editable.

### Page templates

In the right-hand sidebar under **Template** you can switch a page to:

- **Default** — narrow, comfortable reading width. Use for text pages.
- **Page — full width** — edge-to-edge. Use for landing pages built from
  patterns.
- **Page — contact** — two columns, with the enquiry form on the right.

### The portfolio blocks

Insert any of these on any page via the **+** button → **Portfolio** category:

| Block | What it does |
|---|---|
| Project grid | A grid of projects. Set how many, how many columns, whether to filter to one industry, whether to show only featured projects, and whether to show a button underneath. |
| Headline results | The results band on a case study. Reads the figures from Project details. |
| Project facts | The client / industry / services / year list on a case study. |
| Contact form | Enquiry form with spam filtering. Optional company and phone fields. |

---

## 4. Enquiries

**Enquiries** in the left menu. Every submission is listed with the sender,
their email, the first line of the message and whether it reached your CRM.

Click one to see everything: the full message, the page they submitted from,
where they came from before that, and their IP.

Replying by email to the notification goes straight back to the enquirer —
the Reply-To is set for you.

**Nothing is ever lost.** The enquiry is saved before the email is sent and
before the CRM is contacted. If the mail server or your Zap is down, the
message is still here.

---

## 5. Who can do what

**Users → Add New**, then pick a role.

| Role | Can | Cannot |
|---|---|---|
| Administrator | Everything | — |
| Editor | Everything content-related, including the inbox | Plugins, themes, users |
| **Portfolio Manager** | Projects, industries, services, pages, media, the enquiry inbox | Plugins, themes, users, settings |
| **Case Study Writer** | Write and edit projects, upload images | Publish projects, see the enquiry inbox, edit pages |

Give freelance writers the **Case Study Writer** role. Their drafts wait for
someone with publishing rights, and they never see visitors' contact details.

---

## 6. Settings

**Portfolio → Settings.** Everything is optional — an empty field means that
feature is off.

**Company** — name, tagline, public email, phone, address, LinkedIn. These feed
the footer and the structured data search engines read.

**Portfolio** — the URL slug for the portfolio (`/work/` by default) and how
many projects appear per archive page. Changing a slug updates every link on
the site automatically.

**Analytics** — paste a Google Analytics 4 ID (`G-XXXXXXXXXX`) and/or a Matomo
URL plus site ID. Both can run together. Logged-in editors are never tracked,
so your own browsing does not appear in the reports.

**Contact form** — where enquiry notifications go; optionally a Cloudflare
Turnstile key pair; and the minimum number of seconds before a submission is
accepted. The honeypot and timing checks work with or without Turnstile.

**CRM hand-off** — paste a Zapier (or Make, or n8n) catch-hook URL and press
**Send a test enquiry**. The result shown is the live response from your
webhook, so a wrong URL is obvious immediately rather than at the first real
lead. Every enquiry is then POSTed there as JSON. If it fails, the system
retries four times over roughly two hours and the enquiry list shows *Failed*
until it succeeds.

---

## 7. Changing the look

**Appearance → Editor.** This is the Site Editor and it changes the whole site,
so it is the one area worth being careful in.

- **Styles** (the paintbrush) — colours, fonts and spacing for the entire site.
- **Templates** — the layout of case studies, archives and pages.
- **Patterns → Header / Footer** — edit the header and footer once and every
  page follows.

Everything here has full undo, and revisions are kept. If a change goes wrong,
**Styles → Revisions** rolls it back.

To change the logo: **Appearance → Editor → Patterns → Header**, click the logo
block, replace the image.

---

## 8. Routine maintenance

- **Updates** — Dashboard → Updates. WordPress applies security patches
  automatically; feature updates need one click. Do plugin updates before core.
- **Backups** — whatever your host provides, plus one off-site copy. Verify a
  restore once, at the start, rather than discovering the problem later.
- **Adding a blog** — already built. Posts → Add New; the layout exists.
- **Adding a shop** — install WooCommerce. The site's colours and typography
  apply to it automatically.

---

## 9. If something looks wrong

| Symptom | Almost always |
|---|---|
| New project 404s | Settings → Permalinks → Save. Nothing else needed. |
| Filter chip missing | That industry has no published projects yet. |
| Old content still showing | A caching plugin or your host's cache. Purge it. |
| Enquiry emails not arriving | Check the Enquiries list first — if the record is there, the site worked and the problem is mail delivery. Check spam, then the host's mail logs. |
| CRM column says *Failed* | The webhook URL is wrong or the Zap is off. Fix it, then use **Send a test enquiry** to confirm. Queued retries will then go through. |
| Site slow after adding a plugin | Deactivate it and re-test. Nothing in this build requires a performance plugin. |
