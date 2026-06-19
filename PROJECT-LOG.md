# Vancouver Weekly Rebuild — Project Log

Running source-of-truth log for the full site rebuild. Append new entries at the bottom as work continues.

**Repo:** github.com/ricardokay/vw-rebuild
**Local dev:** Local by WP Engine — site `vancouverweekly-local`
**Parent theme:** Newspack (directory slug: `newspack-theme`)
**Child theme:** `vancouver-weekly` (at `wp-content/themes/vancouver-weekly/`)

---

## Background

Vancouver Weekly (`vancouverweekly.com`) was a Vancouver-based arts and culture publication — local, independent, Pitchfork-influenced in format. Covered music (live reviews, album reviews, interviews), film, food & drink, photography, politics, and local events. The site ran from roughly 2011 to 2019.

The site was compromised by a pharma/spam injection attack: hundreds of spam posts about ivermectin, generic drugs, and other pill keywords were injected into the WordPress database. By the time recovery started, the live domain was dead and the hosting account was either deleted or inaccessible.

### Recovery assets available

| Asset | Status |
|---|---|
| Wayback Machine crawl of the live site | Extensive — used as primary content source |
| SQL database backup (Feb 2019) | `vanctcjx_vweekly2016a_feb2019.sql.zip` — 29 MB compressed, 209 MB uncompressed. 48 tables, includes `wp_posts` + `wp_postmeta` |
| Original media files | Not recovered — server gone. Partial recovery from Wayback CDX |
| Logo files | `VancouverWeekly Logo.eps`, `VW_logo.psd`, `logo_VW.png` (clean transparent PNG) |

---

## Phase 1 — Content Recovery (Complete)

**Completed: June 2026**

### Method

Custom Python recovery pipeline (`vw-rebuild-starter-kit/recovery/`):

1. **Wayback CDX crawl** — fetched the full CDX index for `vancouverweekly.com` to identify all archived page URLs
2. **Content extraction** — for each post URL, fetched the Wayback snapshot and parsed the article HTML (title, body, author, date, categories, tags, featured image URL)
3. **Spam audit** — filtered ivermectin/pharma injected posts before import; 197 posts failed or were excluded
4. **WordPress import** — `import_to_wordpress.py` used WP-CLI via Local's bundled PHP to create posts directly in the local WordPress database

### Results

| Metric | Count |
|---|---|
| Posts successfully imported | 2,789 |
| Posts failed / excluded (spam or parse errors) | 197 |
| Total attempted | 2,986 |

Import ran June 13, 2026. Log at `vw-rebuild-starter-kit/recovery/import_run.log`.

### Category inventory (confirmed June 17, 2026)

Queried via WP-CLI against the live local database. Counts are live post counts. Duplicate slugs (a known import bug — see Database Issues below) are collapsed into the canonical slug for this table.

| Category Name | Canonical Slug | Post Count |
|---|---|---|
| Uncategorized | `uncategorized` | 1,835 |
| A La Music | `a-la-music` | 490 |
| Live Music Reviews | `live-music-reviews` | 245 |
| Out 'N' About | `out-n-about` | 210 |
| Must See Films | `must-see-films` | 170 |
| Album Reviews | `album-reviews` | 129 |
| Music Videos | `music-videos` | 93 |
| Book Reviews | `book-reviews` | 90 |
| Video | `video` | 76 |
| Music Interviews | `music-interviews` | 70 |
| Contests | `contests` | 63 |
| Political Megaphone | `political-megaphone` | 55 |
| Netflix Films | `netflix-films` | 37 |
| Photography | `photography` | 30 |
| Food & Drink | `food-drink` | 26 |
| Business | `business` | 24 |
| Fiction & Essays | `fiction-and-essays` | 22 |
| Hungry Social | `hungry-social` | 22 |
| Music Editorials | `music-editorials` | 15 |
| Upcoming Events | `upcoming-events` | 9 |
| Contest | `contest` | 7 |
| Featured | `featured` | 5 |
| Netflix Reviews | `netflix-reviews` | 1 |

All seven originally expected categories confirmed present: A La Music, Photography, Food & Drink, Out 'N' About, Must See Films, Political Megaphone, Business. ✓

**Note on Uncategorized (1,835 posts):** This is the largest bucket and likely contains real content that wasn't assigned a category during the Wayback recovery parse (original site may have used tags, custom taxonomies, or a section structure not captured in the category field). Needs audit before launch.

---

## Phase 2 — Image Recovery (Complete)

**Completed: June 16–17, 2026**

### Source analysis

The Feb 2019 SQL dump's `wp_posts.guid` field and `wp_postmeta._wp_attached_file` entries were parsed to extract all original upload URLs.

| Metric | Count |
|---|---|
| Unique original image URLs in SQL dump | 8,333 |
| Of those: in the Wayback CDX index (status 200) | 3,659 |
| Of those: full-size originals (no WxH resize suffix) | 2,606 |
| Of those: large thumbnails (1024px wide, usable quality) | 1,053 |
| Successfully downloaded | 3,653 |
| Network failures (in Wayback, download failed) | 4 |
| Never archived by Wayback | 4,674 |

### Why Wayback had limited coverage

Wayback Machine crawls pages, not direct file links. It captured thumbnail variants embedded in article HTML (e.g. `-240x150.jpg`, `-150x150.jpg`, `-1024x683.jpg`) but rarely crawled the full-size originals. Only 2,606 originals were reachable. The 1,053 large thumbnails (1024px wide) are high enough resolution for a news site.

### Recovery script

`recover_images.py` — fully resumable, CDX-based downloader. Uses `if_` modifier on Wayback URLs to get raw file bytes, not the toolbar HTML wrapper. Respects rate limits with exponential backoff on 429 responses. Progress ledger in `image-recovery-ledger.csv`.

```bash
python3 recover_images.py extract              # rebuild URL list from CDX
python3 recover_images.py recover              # download (resumable)
python3 recover_images.py recover --limit 20  # test run
python3 recover_images.py report               # progress summary
```

### Recovered files

Stored in `recovered-images/YYYY/MM/filename.ext` — mirrors WordPress's `wp-content/uploads/` structure exactly, so files can be bulk-copied into the uploads folder for a clean media library import.

### Gaps

`image-recovery-gaps.txt` — 7,000 URLs not recovered (6,996 never archived + 4 network failures). Secondary recovery sources to try: archive.today, Google cache, original photographers/contributors, press/PR sources for album art and press photos.

---

## Phase 3 — WordPress Theme Build (In Progress)

**Started: June 13, 2026**

### Design system (final, as built and approved)

The design went through significant iteration before locking. Full rationale in `VW-DESIGN-BRIEF.md`.

**Design journey (what was tried and dropped):**
- Started with a four-color section palette (deep crimson, emerald, amber, indigo — one color per section) + cyan accent + per-section duotone image filters. Felt too festival/tech, not editorial.
- Tested Instrument Serif + Space Grotesk as the type pairing — dropped.
- Moved to Cormorant Garamond Bold as the headline font — too decorative/precious.
- Tried Fraunces at weight 900 — too stylized.
- Landed on **PT Serif Bold (700)** — sturdy, plain, genuinely editorial. Locked.
- Simplified the color system from four section colors to **one red accent (#C41230)** used sparingly. Everything else is near-black ink on off-white/white grounds.

**Final design system:**

| Element | Value |
|---|---|
| Page background | `#F7F6F4` (off-white) |
| Card / surface background | `#FFFFFF` (white) |
| Ink (headlines, body) | `#1A161E` (near-black) |
| Muted ink (bylines, captions) | `#767676` |
| Border / hairlines | `#E8E8E8` |
| Accent red | `#C41230` |
| Red on white contrast | 6.0:1 — WCAG 2.1 AA ✓ |
| White on red contrast | 6.0:1 — WCAG 2.1 AA ✓ |
| Red on off-white contrast | 5.6:1 — WCAG 2.1 AA ✓ |
| Headline font | PT Serif Bold (weight 700), self-hosted woff2 |
| Body / UI font | Inter (weight 400–600), self-hosted woff2 |
| Nav background | `#F7F6F4` — same as page, seamless, no border |
| Cards | 1px solid `#E8E8E8` border, 4px radius, internal padding |
| Images | Natural full color — no duotone treatment |

Red accent used only for: kickers/section labels, category tags, active nav links, article links, filled badge/flag elements. Never as a large background.

**Fonts self-hosted** (OFL licensed, no CDN dependency):
- PT Serif Bold: latin + latin-ext subsets, normal + italic variants (4 woff2 files)
- Inter: latin + latin-ext, weight 400–600 variable (2 woff2 files)
- Cormorant Garamond: retained on disk, currently unused

### Theme file structure

```
theme/
  style.css                   # Child theme header (Template: newspack-theme)
  functions.php               # Enqueues palette.css, fonts.css, section-landing.css
  header.php                  # Custom full-width nav with logo
  archive-section.php         # Section landing template (started, not final)
  palette.css                 # CSS custom properties — single source of truth
  assets/
    css/
      fonts.css               # @font-face declarations
      section-landing.css     # Section landing + article + nav styles
    fonts/                    # 12 woff2 files (PT Serif Bold + Inter)
    images/
      logo_VW.png             # Clean transparent PNG logo (horizontal wordmark)
  previews/
    section-landing.html      # Static self-contained design preview (approved)
  template-parts/
```

### Build steps completed

| Step | Status | Notes |
|---|---|---|
| 1. Child theme scaffold | ✓ Done | `style.css` + `functions.php` — activated on Local |
| 2. Palette + fonts live | ✓ Verified | CSS custom properties on `:root`, font files loading |
| 3. Nav / header | ✓ Done | `header.php` with logo, off-white full-width bar, 4 section links |
| 4. Section landing templates | Pending | `archive-section.php` started but not wired |
| 5. Single article template | Pending | |
| 6. Smoke test across all 4 sections | Pending | |
| Media library import | Pending | 3,653 recovered images need bulk import into WP |

### Header implementation notes

Newspack's `footer.php` closes `</div><!-- #content -->` and `</div><!-- #page -->`. Our `header.php` must open both — missing them causes malformed HTML and a visually constrained header background. Current `header.php` opens:

```html
<div id="page" class="site">
  <header class="vw-nav">...</header>
  <div id="content" class="site-content">
```

Nav is `position: sticky; top: 0; width: 100%; flex-shrink: 0` to span full viewport inside Newspack's flex `#page` column.

Logo is set to `height: 80px; width: auto` in CSS, `height="80"` attribute in HTML. Nav bar is 100px tall (10px breathing room above and below).

---

## Known Database Issues (Fix Before Launch)

### 1. Duplicate category slugs

The import script called `wp post create --post_category` with a category name string. WordPress's `wp_insert_term` creates a new term when a slug collision occurs instead of reusing the existing one, appending `-2`, `-3`, etc. This produced:

- `live-music-reviews` through `live-music-reviews-401` (401 slugs, 626 total posts — should be 1 slug with 626 posts)
- `album-reviews` through `album-reviews-50` (50 slugs, 174 total posts)
- `music-interviews` through `music-interviews-67` (67 slugs, 132 total posts)
- `music-editorials` through `music-editorials-13` (13 slugs, 27 total posts)
- `music-videos` through `music-videos-8` (8 slugs, 100 total posts)
- `out-n-about-2` (2 slugs, 211 total posts)
- `food-drink-2` (2 slugs, 27 total posts)

**Fix:** WP-CLI consolidation script — for each group, reassign all posts to the canonical term ID, then delete the duplicate terms. Ready to build when needed.

### 2. Spam/drug categories (308 entries, all 0 posts)

Ivermectin/pharma category names created during import from spam posts that passed the content filter. All have 0 posts so they don't affect URLs or content. Safe to bulk-delete before launch.

### 3. Uncategorized (1,835 posts)

Largest single category. Needs audit: likely contains legitimate content not properly categorized during Wayback recovery. Some may be spam posts. Approach TBD — could re-parse original slugs to infer categories, or do a manual spot-check.

---

## Infrastructure

| Item | Detail |
|---|---|
| GitHub repo | `github.com/ricardokay/vw-rebuild` (main branch) |
| Credential helper | macOS Keychain (`git config --global credential.helper osxkeychain`) — push from Mac Terminal without pasting tokens |
| Local WP | `vancouverweekly-local` in Local by WP Engine |
| MySQL socket | `/Users/ricardokhayatte/Library/Application Support/Local/run/HKOO9D7DI/mysql/mysqld.sock` |
| WP-CLI | `/tmp/wp-cli.phar` with PHP ini at `/tmp/wp-cli-php.ini` (socket path configured) |
| SQL backup | `vanctcjx_vweekly2016a_feb2019.sql.zip` in project root (gitignored — contains PII) |
| SQL unzipped | `/tmp/vw-sql-inspect/vanctcjx_vweekly2016a.sql` (scratch location, not committed) |
| Recovered images | `recovered-images/` (gitignored — large binary files) |
| Image gaps list | `image-recovery-gaps.txt` (committed — 7,000 URLs for later recovery) |

---

## Next Steps

1. **Fix duplicate categories** — consolidation script to merge `-N` slug variants into canonical terms
2. **Delete spam categories** — bulk remove 308 empty pharma terms
3. **Audit Uncategorized** — determine how many of the 1,835 are real content vs spam
4. **Step 4: Section landing templates** — wire `archive-section.php` to render the approved card grid design for each of the four main sections (A La Music, Photography, Food & Drink, Out 'N' About)
5. **Step 5: Single article template** — `single.php` with the approved article layout
6. **Step 6: Smoke test** — full run through all four sections with live imported content
7. **Media library import** — bulk-copy `recovered-images/` into `wp-content/uploads/` and register in WP media library
8. **Logo** — `logo_VW.png` is a placeholder; replace with final SVG when supplied
9. **Nav finalisation** — section links and active states deferred pending marketing decision on site structure
10. **Uncategorized review** — 808 published posts with no clear category signal need manual review / re-categorization
11. **Empty spam categories** — 308 pharma/drug category terms with 0 posts; safe to bulk-delete before launch

---

## Database Cleanup — Session 1 (June 16, 2026)

Pre-work: full DB backup taken before any changes (`db-backups/vancouverweekly_local_2026-06-16_193823.sql`, 134 MB, gitignored).

### Spam drafts trashed

14 pharma/ivermectin draft posts moved to trash (not permanently deleted — recoverable from WP Admin → Posts → Trash). All 14 were drafts created November 2021, never published, with titles like "Revectina onde comprar rj", "Ivomec ovin prix", "Securo precio ioma".

### Duplicate category slug consolidation

The original import script created `-N` slug variants instead of reusing existing terms. All duplicate terms have been merged back into their canonical term and the empty duplicates deleted.

| Category | Count before | Count after | Dupe terms deleted |
|---|---|---|---|
| live-music-reviews | 245 (canon) + 381 in 400 dupes | **626** | 400 |
| album-reviews | 129 (canon) + 45 in 49 dupes | **174** | 49 |
| music-interviews | 70 (canon) + 62 in 66 dupes | **132** | 66 |
| music-editorials | 15 (canon) + 12 in 12 dupes | **27** | 12 |
| music-videos | 93 (canon) + 7 in 7 dupes | **100** | 7 |
| out-n-about | 210 (canon) + 1 in 1 dupe | **211** | 1 |
| food-drink | 26 (canon) + 1 in 1 dupe | **27** | 1 |

**Total:** ~536 dupe terms deleted. All post counts verified before and after — zero posts lost.

### Post-cleanup backup

Fresh backup taken immediately after cleanup: `db-backups/vancouverweekly_local_2026-06-16_195016.sql`, 134 MB. Also copied to `~/Library/Mobile Documents/com~apple~CloudDocs/vw-rebuild-backups/` (private iCloud only — contains user PII).

### Still pending

- **808 Uncategorized published posts** — see note below; action deferred.
- **366 empty spam categories** — pharma/drug category terms with 0 posts. Safe to bulk-delete, deferred to a later cleanup session.

---

## Uncategorized Posts — Research Note (June 16, 2026)

After auditing and exporting the ~841 uncategorized published posts (full CSV with excerpts at `uncategorized-review.csv`, gitignored), the content is confirmed real — no spam. But it is a **mix of two distinct content types** that likely need different handling:

1. **Genuine editorial articles** — film reviews, comedy coverage, arts features, interviews, and other editorial pieces that simply weren't assigned a category during the Wayback recovery parse. These belong in normal section categories (Must See Films, A La Music, Out 'N' About, etc.) and should be recategorized in a future pass.

2. **Old event listings** — short posts that appear to have been created through an events plugin (e.g., "Trampled by Turtles", "Goldroom", "Mayday Parade" — artist/band name only as title, with date/venue/ticket info as body). These are time-sensitive/dated content from 2013–2016 era and probably don't belong in editorial sections.

### Why the event listings matter

The event listings are a potential asset, not junk. They form a historical gig record of Vancouver's live music scene during the site's active years — consistent with the nostalgia-feed / archive-depth vision for the rebuilt site. A dedicated historical events archive (browseable by year or venue) could be a distinctive feature.

### Deferred decision

These two types need to be separated before recategorizing. The most promising auto-detection approach: check for a shared plugin marker (custom field, post format, or tag) that the original events plugin added. If all event listings share a common `_EventStartDate` meta key or similar, they can be bulk-identified and handled as a group without manual review of all 841 posts.

**Action:** Revisit in a later session once the site's events/archive strategy is settled. Posts are untouched in the database. Export CSV (`uncategorized-review.csv`) is available locally for reference but is not committed (large working file, no PII).

---

## Image Import — Session 1 (June 16, 2026)

Pre-work: full DB backup taken before any changes (`db-backups/vancouverweekly_local_2026-06-16_201650.sql`, 134 MB, gitignored).

### Media import script built

`media_import.py` — Method 3 (proper WP attachment registration + batch thumbnail regen). Commands: `parse`, `plan`, `import [--limit N]`, `regen`, `report`.

Key design decisions:
- Each recovered image registered as a real WordPress media library attachment via `wp_insert_attachment()`
- Post-to-image parent links reconstructed from the Feb 2019 SQL dump: `old attachment.post_parent → old post slug → new WordPress post ID`
- Original post titles from SQL dump used as attachment titles where available
- Alt text intentionally left blank at import time; `_needs_alt_review = 1` postmeta flag set on every image for future AI alt-text pipeline
- `BATCH_SIZE = 50` — attachments inserted in batches of 50 via WP-CLI eval-file

### 20-image test — passed

`python3 media_import.py import --limit 20` confirmed: attachments created in DB, parent post IDs resolved, `_needs_alt_review` flag set, files copied to correct `wp-content/uploads/YYYY/MM/` paths. Media library shows real imported photos.

### Domain search-replace

Posts imported from Wayback had old-domain absolute URLs baked into `post_content` (e.g. `https://vancouverweekly.com/wp-content/uploads/…`). Fixed via `wp search-replace` across three passes (https, http://www., http://), all with `--skip-columns=guid` to preserve attachment GUIDs.

| Pass | Old string | Replacements |
|---|---|---|
| 1 | `https://vancouverweekly.com` | ~2,200 |
| 2 | `http://www.vancouverweekly.com` | ~2,100 |
| 3 | `http://vancouverweekly.com` | ~2,300 |
| **Total** | | **6,632** |

Posts with relative image URLs (no domain prefix) were unaffected — these are correct already.

### Key finding: 'broken images' were empty featured-image slots, not missing photos

During post-test diagnosis, discovered that the widespread broken image boxes visible at the top of article pages were **not** missing recovered images. Root cause:

- The Wayback post importer had set `_thumbnail_id = 64365` (attachment "placeholder", registered as `wp-content/uploads/2024/07/placeholder.jpg`) on every post that lacked a real featured image
- That file was never created on disk — the DB pointer existed but the file did not
- 2,495 posts were affected; the Newspack theme renders a featured-image block whenever `has_post_thumbnail()` returns true, producing a broken image box at the top of each post
- Body images in `post_content` (inline article photos) were recovering correctly and displaying fine — confirmed on 'the-housewife' post

**Fix applied (Option A):** Deleted all 2,495 `wptg_postmeta` rows where `meta_key = '_thumbnail_id'` AND `meta_value = '64365'`. Posts now behave like the 1,710 posts that never had a featured image — `has_post_thumbnail()` returns false, theme skips the featured-image block entirely, no broken box. Confirmed clean on 'the-housewife' and 'overtime-atf' posts.

### Still pending

| Task | Status |
|---|---|
| Full media import — 3,585 recovered images | ✓ Complete — 3,565/3,565 imported, 0 errors, all 72 batches clean |
| `wp media regenerate` — generate all WordPress size variants (300×266, 150×150, etc.) from full-size imports | ✓ Complete — 33/36 chunks confirmed, 3 silent (disk-verified). ~19,000 size variants created across 2012–2019. |
| Option C: back-fill real `_thumbnail_id` from first body image | Deferred — run after regen confirms thumbnails are good. Check for suitable full-size images only; avoid small inline variants. |

**Regen bug caught and fixed:** First regen launch silently failed — `media_import.py` passed `--attachment-id=ID1,ID2,…` which is not a valid flag for this WP-CLI version. The correct syntax is positional args: `wp media regenerate ID1 ID2 …`. Fixed in `regen_ids()` (chunk size reduced to 100, IDs passed as `*chunk`). Verified on 3 attachments — 5 size variants created correctly. Regen relaunched: 36 chunks × 100 images, confirmed `Regenerated 100 of 100` on most chunks; 3 chunks (3, 10, 11) printed "(no output)" due to stderr routing.

**Silent chunk verification — `wp media regenerate --only-missing --yes`:** Run after regen to confirm the 3 silent chunks actually worked. Results: 4,684 of 4,835 total attachments regenerated successfully. The 151 reported failures broke down to 24 unique attachment IDs, all `@2x` retina thumbnail files (e.g. `DSC01237-240x150@2x.jpg`). These were registered as separate attachment records in the original SQL dump by the old site's retina plugin — the current local install has no retina plugin, so WP-CLI can't generate them. All 24 are in chunks 10 and 11; chunk 3 had zero failures. The "(no output)" for chunks 10 and 11 was caused by the @2x `File is not an image` warnings going to stderr (discarded by `wpcli()`), leaving no stdout for the summary line. All standard editorial images across all 3 silent chunks regenerated correctly. Output saved to `regen_only_missing.log` (gitignored).

**Import gap clarification:** The import log showed "Attachment records created: 3,565" (full run) while the regen reported "3,585 attachments" — the gap of 20 is the test batch (m00001–m00020) imported before the full run. Ledger confirmed: 3,585 rows, all status `ok`, zero errors. Nothing was lost.

### Option C — Featured image back-fill (pending approval)

Dry-run completed. **Not yet applied.**

**Resolver v1 → v2 fix:** The original resolver matched body `<img>` src URLs to attachments using exact uploads-relative path only (after stripping size suffix and lowercasing). 161 additional posts were unresolvable because the file existed on disk but under a slightly different `YYYY/MM` path than the src referenced (e.g., src said `2013/06/maxresdefault.jpg`, recovered file landed at `2013/09/maxresdefault.jpg`). Fix: added a basename fallback — when exact path fails, collect all attachment index entries sharing the same filename. Use the match only when unambiguous: exactly 1 candidate on disk, or prefer same `YYYY/MM` directory, then same year. Skip if still ambiguous.

**Dry-run v2 results (not applied):**

| | Count |
|---|---|
| Posts with no `_thumbnail_id` | 2,776 |
| Would assign featured image | **294** |
| — via exact path match | 225 |
| — via basename fallback | 69 |
| Would leave with none (clean fallback) | 2,482 |
| `@2x` srcs skipped | 1 |
| Unresolvable srcs skipped | 13,786 |

Unresolvable breakdown: 9,714 Wayback-proxied external images (avatars, social media CDN — never in `wp-content/uploads`); ~5,200 VW uploads not recovered from Wayback; 6 truly external links. Path matching is not the issue — files are genuinely missing.
| 366 empty spam categories | Deferred |
| 808 Uncategorized posts | Deferred — see research note above |

---

## Spam investigation — static leftover, NOT active (DEFERRED to next session)

### Finding: spam is static import leftover, not an active compromise
Investigation (read-only) confirmed the spam posts are a frozen snapshot from the old site's 2021 compromise, ingested during the Phase 1 Wayback recovery. Nothing is generating new spam.
- No spam being created: newest spam dated Nov 2021, count is fixed.
- Origin: 2021 XML-RPC injection hit the live site before it went offline; Wayback snapshot captured the already-compromised DB. Confirmed by 13-day burst, frozen modification timestamps, 100% Nov 2021 clustering — one-time import, not ongoing.
- No active malicious code: no mu-plugins, no scheduled/future posts, no unfamiliar plugins. Only recently-modified PHP is our own rebuild work (header.php, functions.php, archive-section.php, vw-security.php, June 13-16).
- The unfamiliar `vw_monthly_security_audit` cron event is part of OUR vw-security plugin — it emails an audit report, creates nothing. Not suspicious.

### Site is already protected going forward
- vw-security plugin (active, v1.0.0) blocks any post containing pharma keywords from publishing (forces to draft + logs the attempt) via wp_insert_post_data.
- XML-RPC (original injection vector) is fully disabled. Pingbacks disabled, login throttle active, registration approval gate on.

### Cleanup target (DO NEXT SESSION, gated)
- Target: 3 published + 38 draft spam posts = 41 total.
- The 3 published slipped Phase 1's filter because titles read as borderline-legitimate (e.g. "Securom failed to initialize crysis 3" looks like a tech-support question). Treat the 3 published with extra care in borderline review.
- Cleanup approach: backup DB first, build auditable ID list, borderline-review the 3 published especially, trash (not permanent delete) so recoverable, log counts. Do not permanently delete in first pass.

### Knock-on effect for featured images (DEFERRED)
Featured-image back-fill (Option C) was paused. The 227/388 assignable counts are suspect because some may target spam posts. Revisit featured-image strategy AFTER spam cleanup, on the cleaned post set. See the earlier "Featured image strategy" deferred note.

---

## Spam cleanup — 297 posts trashed (2026-06-17)

### Pre-cleanup backup
`db-backups/vancouverweekly_local_2026-06-16_224119.sql` (142 MB) — taken the night before during prep. Confirmed present before any trashing.

### What was trashed
- **3 published** spam posts (IDs 66586, 68224, 68189): trashed individually via `wp post delete`
- **294 draft** spam posts: trashed in 3 bulk chunks of ~100 via `wp post delete`
- **Total: 297 posts moved to Trash** — not permanently deleted, fully recoverable from WP Admin > Trash

### Detection note: 297 vs the earlier 43
The original spam investigation used a 6-pattern MySQL REGEXP (`iverm|scaboma|ivera|securom|lotion price|chain [0-9]`) and found 43 total (3 published + 38 draft + 2 pre-trashed). The cleanup pass used the full drug-brand regex family (kilox, simpiox, ivergot, ivexterm, revectina, quanox, etc.) and found all 297 posts from the same November 2021 injection batch. The higher count is correct — it's the same single event, more completely detected. No false positives: every match had a pharma drug-brand title and a pharma-specific spam category (e.g. "Ivermectin for cattle and swine").

### Trash count after cleanup
311 total posts in WP Trash = 297 trashed now + 14 already in trash before cleanup (including the 2 pre-trashed spam posts from the original investigation).

### Next: Option C featured-image back-fill
Now that spam is cleared, re-run Option C dry-run on the cleaned post set before applying.

---

## Option C apply — featured-image back-fill (2026-06-17)

Applied `_thumbnail_id` back-fill to all published posts that had none, using first usable body image. Ran against cleaned post set (spam already trashed).

| Metric | Count |
|---|---|
| Published posts with no `_thumbnail_id` (start) | 2,776 |
| Posts updated — featured image set | **294** |
| — of which: exact path match | 225 |
| — of which: basename fallback | 69 |
| Posts left with no featured image | 2,482 |
| `@2x` srcs skipped | 1 |
| Unresolvable srcs skipped | 13,786 |

**Resolver:** exact uploads-relative path match first; basename fallback for unambiguous single-file matches; skips `@2x` and size-variant derivatives; only assigns if file exists on disk.

**Why 2,482 posts remain without a featured image:** body images for those posts are either Wayback-proxied external URLs (avatars, social CDN — never in `wp-content/uploads`), uploads not recovered from Wayback, or genuinely external links. No placeholder or invented image was assigned — those posts fall back to headline/excerpt display.

Script: `/tmp/vw_option_c_apply.php` (idempotent — safe to re-run; only touches posts with no existing `_thumbnail_id`).

---

## Contributor attribution audit — findings (2026-06-17)
- Co-Authors plugin data: ZERO survived the Wayback recovery. Bylines must be rebuilt from body text (JIG image title attributes + figcaptions).
- Real photographer roster: Ryan Johnson (~249), Jennifer McInnis (~137), Sharon Steele (~61), Mariko Margetson (~35), Peter Ruttan (~33), plus ~25 minor contributors (2–17 posts each). This is the true masthead, reconstructed from surviving body credits.
- ~400–450 posts need post_author correction (currently attributed to generic "Vancouver Weekly").
- Duplicate/missing user accounts exist (e.g. McInnis = IDs 137 & 246) — need canonical account per person before/with reassignment.
- 136 concert photo posts had Facebook-sourced featured images (expired fbcdn URLs) — unrecoverable via Option C, need a separate recovery path (photographers' own originals / VW drives).
- NEXT: gated byline reassignment, dry run first — Type A galleries (photographer = author) vs Type B written articles (writer = author, photographer → photo_credit meta), word-count tiebreaker at ~250 words, ambiguous cases to manual review.

---

## Byline reassignment — write pass (2026-06-17)

**Scope:** All posts attributed to "Vancouver Weekly" with a recoverable credit in the body.

**New photographer accounts created (role: Author):**
| Photographer | UID | Login |
|---|---|---|
| Tom Paillé | 407 | tom.paille |
| Ben Hartley | 408 | ben.hartley |
| Sterling Larose | 409 | sterling.larose |
| Scott Place | 410 | scott.place |
| Bob Hanham | 411 | bob.hanham |

**Canonical accounts confirmed (original 2024 import accounts, real email):**
Ryan Johnson 172, Jennifer McInnis 137, Sharon Steele 178, Mariko Margetson 218, Peter Ruttan 188.
The 2026-06-13 auto-created `@contributors.vancouverweekly.com` duplicates (UIDs 246–330) were abandoned — all have 0 posts.

**Classification logic:**
- Type A (photo gallery): "Photos:" title → photographer; photo-only credit + wc<250 → photographer
- Type B (written article): writer byline → writer as author; photographer → `photo_credit` postmeta
- Safety checks: word-count-decided posts verified all photo-credit-only (no writer bylines)
- 7 bare "Sharon" credits confirmed as Sharon Steele (178) by spot-check
- 85 "Photos:" title / no body credit posts → manual review (photographer unidentifiable from body alone)
- 352 fbcdn posts → separate recovery path (deferred)

**Write pass results:**
| | Count |
|---|---|
| Type A post_author updates | 141 |
| Type B post_author updates | 2 |
| photo_credit meta inserted | 0 (neither Type B post had a photographer credit) |
| Errors | 0 |
| Posts still on "Vancouver Weekly" after pass | 2,435 |

**Breakdown of 141 Type A assignments:**
Ryan Johnson (172): 39 · Jennifer McInnis (137): 24 · Sharon Steele (178): 18 · Tom Paillé (407): 11 · Mariko Margetson (218): 7 · Bryce Bladon (131): 6 · Ben Hartley (408): 5 · Peter Ruttan (188): 4 · Jason Martin (194): 4 · Erik Lyon (128): 4 · Scott Place (410): 3 · Sterling Larose (409): 3 · Bob Hanham (411): 2 · others: 11

**Type B:** Issie Patterson (221) — "Cascadia Project Showcases Delightful New Plays"; Laura Sciarpelletti (159) — untitled post (ID 52447).

**DB backup:** `db-backups/vancouverweekly_local_2026-06-17_115330.sql` (145 MB, taken before write pass).

**Deferred follow-ups (logged, not started):**
- (a) Facebook-gallery OAuth-error cleanup: strip ONLY erroring shortcodes from the ~352 fbcdn posts — working galleries must be left alone. Source of truth = photographers' own archives (Ryan Johnson, Jennifer McInnis / creativecopperimages.com, Tom Paillé).
- (b) 85 "Photos:"-title / no-credit-in-body posts: photographer unidentifiable automatically; need manual review or a targeted pass once archive sources are available.
- (c) 7 "Sharon" posts: confirmed Sharon Steele by spot-check, already attributed (UID 178).
- (d) 2,435 posts still on "Vancouver Weekly": bulk are genuinely authored by VW editorial (no individual byline) or have writers without WP accounts. Separate follow-up pass needed.

Scripts: `/tmp/vw_byline_final_dryrun.php` (final dry run), `/tmp/vw_byline_apply.php` (write pass), `/tmp/byline_write_plan.json` (143-entry write plan).

---

## Foundation decision — editorial architecture (2026-06-17, DECIDED)

### Chosen: Newspack-native, NOT a page builder, NOT bloated custom PHP

**Section fronts = Option B (native category archive URL renders curated layout directly)**
- URL `/category/{slug}/` serves the curated layout natively — no redirect, no separate Page, no plugin in the path
- Implemented via a minimal ~25-line `category.php` routing file that delegates to Newspack Homepage Posts block markup in `section-parts/*.html`
- Newspack blocks handle layout/query; URL is native and unbreakable

**Rejected alternatives:**
- Option A (Page + redirect): splits URL equity, breaks to 404 if redirect ever fails
- Page builders (Elementor/Beaver/Bricks): high lock-in (layouts in proprietary post-meta), per-site cost across multisite, fragility — "exactly how the previous agency rebuild broke"
- Original bloated custom category.php (200+ lines replacing Newspack layout logic)

### Why (the deciding insight)

Vox abandoned its custom Chorus CMS in 2023 and moved to WordPress — even a fully-funded newsroom found a bespoke editorial CMS unsustainable. Lesson: lock-in and maintenance are the real enemies; use WordPress well. The one real gap vs Chorus (a real-time drag-and-drop front-page dashboard, ~$3–8K custom plugin) is deliberately NOT being closed for v1; liveable for a one-editor operation.

### Permanent URL rules (HARD constraints, now in CLAUDE.md)

- Permalink structure FROZEN at `/%postname%/` — flat slugs, no date/category prefix. Never change. Category base stays default (`/category/`).
- ALL editorial content (archive + new) = permanent, citable URLs. Existing slugs preserved exactly.
- Curated editorial layer lives at structural URLs (`/`, `/category/{slug}/`); never at content URLs. Arrangement above can change freely; post URLs underneath stay fixed.
- Microsites/campaigns: each is its OWN multisite site with its own domain/subdomain from day one (never a subdirectory path on main) — permanence-ready, promotable to permanent cleanly.

### Multisite (decide BEFORE creating any city site)

- Network type = SUBDOMAIN or separate-domain. NEVER subdirectory (subdirectory prefixes are permanent once set and risk prefixing Vancouver's URLs). Changing network type after sites exist is URL-destructive.
- Each city site has independent category namespace; same child theme + section templates deploy identically. Multisite editorial management via newspack-network (free). Cost across 5+ sites: $0.

### Two-layer model

- **Archive layer**: ~2,800 posts, permanent/stable/preserved, lightly editable (still recovering images/attribution).
- **Editorial layer**: curated Newspack Homepage Posts modules surfacing archive + new content. Per-section layouts can differ and evolve freely.

### Monetization (designed in, not yet built)

Branded sponsorships (primary) as native sponsored module types; limited display ads in defined slots served DYNAMICALLY (never hard-coded into permanent posts); voluntary supporter tier (ties to user accounts/2FA); affiliate links with disclosure. Newspack's native ad/sponsored tooling. $0/multisite.

### Image treatment (design direction)

Three card types:
1. Large/high-res image on top (full-bleed)
2. Small/low-res image LEFT-ALIGNED beside content (no oversized container — avoids dead space)
3. Text-forward with red left bar

Image tier chosen AUTOMATICALLY by a PHP function reading image width. Must also detect broken/missing files (dead Facebook-sourced images) and NOT assign them the large treatment.

### Build order (approved, gated at each phase)

- **Phase 0**: freeze permalink in CLAUDE.md, document subdomain multisite, audit category structure + where photography galleries actually live — STOP for approval
- **Phase 1**: `category.php` routing + `section-parts/` block templates
- **Phase 2**: sticky-post lead story + editor workflow doc
- **Phase 3**: image-quality PHP function
- **Phase 4**: multisite readiness check

### Design reference

`section-cards-preview.html` — static visual card/section designs. Small-image card to be revised: left-aligned thumbnail beside content (not large centered container).

---

## Section build decisions (2026-06-17, Phase 1 complete)

### Curation engine: newspack-blocks (Homepage Posts block)

- `newspack-blocks` plugin now **installed and activated** (free/open-source, GitHub, not wordpress.org; no paid Newspack subscription needed).
- `archive-section.php` (old custom-PHP stub from an earlier session, never activated in the WordPress template hierarchy) **removed**.
- Core Query Loop block was considered and rejected — harder to configure, non-coder-unfriendly, wrong editorial fit.

### Implementation: category.php routing → section-parts block templates

`category.php` (~25 lines): routes curated slugs to `section-parts/{slug}.html` via `do_blocks()`. Non-curated categories fall through to Newspack's default `archive.php`. To add a section: add slug to `$curated` array + create corresponding `.html` file.

### Phase 1 curated section fronts (live)

| Section | URL | Block queries categories |
|---|---|---|
| A La Music (umbrella) | `/category/a-la-music/` | 7 (a-la-music), 9 (live-music-reviews), 8 (album-reviews), 11 (music-interviews), 20 (music-videos), 10 (music-editorials) |
| Out 'n About | `/category/out-n-about/` | 17 (out-n-about) |
| Must-See Films | `/category/must-see-films/` | 15 (must-see-films) |

Block config: 2-column grid, 12 posts, show category/excerpt/date/author, no avatar, "Load more" interim pagination. All three verified rendering correctly via `do_blocks()` before commit.

### Photography — DEFERRED (P3)

- Photography category (term_id 6): 30 posts only — effectively a ghost section.
- Real gallery content: 90%+ Uncategorized. Ryan Johnson has 38/45 posts in Uncategorized; McInnis 22/23; Steele 18/21; etc. Category assignments were never updated after the byline pass.
- **Next pass (P1-additive)**: ADD `photography` category to photographer-authored gallery posts; do NOT remove existing categories (a concert gallery correctly belongs in both Photography AND Live Music Reviews). Dry-run first, show counts + samples before writing.

### Uncategorized — pending audit

1,835 posts in Uncategorized (largest bucket). Audit after Photography re-file pass to understand what else is there.

### 366 empty spam categories — still pending

All the ivermectin/spam categories (term_ids 2044–2945 approx), all 0 posts. Logged earlier; still deferred.

---

## Pagination / infinite-scroll plan (2026-06-17, DESIGNED IN — not built yet)

### Interim: Load more (already live)

The Homepage Posts block's `moreButton: true` provides a "Load more" button on all three section fronts. AJAX loads the next page of posts without a full page reload. This is the simple interim until the custom scroll is built.

### Target: intelligent infinite scroll with permanent URL sync

**How it must work:**
- As a reader scrolls and each post comes into view, JS updates the address bar to that post's PERMANENT URL via the History API (`history.pushState`). Content streams continuously; the address bar always reflects the current post. Bookmark, share, and browser back all work because the URL is real and permanent.
- Infinite scroll is a layer ON TOP of permanent URLs, never a replacement. Landing directly on `/slug/` (from Google, bookmark, citation) serves the full article via WordPress, THEN scroll loads the next post below it.
- Server-side / no-JS / crawler safety: search engines and no-JS visitors get every post fully rendered at its own permanent URL. Scroll is progressive enhancement, never the only path to content.

**The ambitious version — recommendation-ordered loading:**
Rather than loading chronological next, the scroll loads the contextually related next post (same venue / artist / photographer / era, or semantically similar via embeddings). An infinitely scrolling intelligent path through the archive: every stop permanently addressable. This is the differentiator — wander 20 years of connected content, everything shareable and citable.

**Scope:** Custom development (History-API URL sync + standalone fallback + crawler rendering + recommendation-ordered loading). Well-trodden technically but real work. Designed-in v2 feature; depends on the recommendation/entity engine. Build after Phase 1 templates + entity-structuring + recommendation engine.

**Dependencies before building:** Phase 1 section templates ✓, entity-structuring pass, recommendation/similarity engine.

---

## Section front = module library (2026-06-17, design direction, mockup stage)

Section fronts are composed of DIFFERENT module TYPES that alternate down the page (Pitchfork-structure), NOT one repeating feed. Build a reusable module library; editor composes section fronts from it.

### Module types

1. **Hero/lead** — big story, large image, large headline
2. **Featured + list** — one lead (image+standfirst) beside compact list of 4–5
3. **Equal-card grid** — N equal cards, 2–4 cols
4. **Horizontal row/carousel** — scrollable equal cards
5. **Pure headline list** — kicker+headline+byline, dense, NO image (likely most-used)
6. **Text-forward card** — headline-led + excerpt + red left bar, no image expected
7. **Small-thumbnail row** — image as small left-aligned accent, never dominant
8. **Featured + text list** — lead (image only if it has one) over text list

### KEY adaptation: image-poor archive

We have ~1 image per 9 posts. Text-first layouts are FIRST-CLASS, not fallbacks. Visual hierarchy from TYPOGRAPHY + spacing (PT Serif sizes, red kicker, whitespace, rule lines), not imagery. References: broadsheet papers / literary journals / NYRB for text rhythm + Pitchfork for modular composition. Design = **"Pitchfork structure, broadsheet typography."**

### Two-level structure

Umbrella front (`/category/a-la-music/`) = labeled module per subsection (latest few + "See all →"). Each subsection (`/category/live-music-reviews/` etc.) = full feed at its own permanent URL.

### Implementation path per module

| # | Module | Effort | Notes |
|---|--------|--------|-------|
| 1 | Hero/Lead | CSS | homepage-articles (1 post, mediaPosition top) + CSS. Falls back to Module 6 if no image. |
| 2 | Featured + compact list | Custom | Two homepage-articles in Columns block + CSS for 60/40 split. |
| 3 | Equal-card grid | Native | homepage-articles, postLayout:grid, columns:2–3. Already live on 3 section fronts. |
| 4 | Horizontal carousel | Custom | homepage-articles + CSS scroll-snap override. No JS for basic swipe. |
| 5 | Pure headline list | CSS | homepage-articles, showImage:false, postLayout:list + kicker-column CSS. |
| 6 | Text-forward card | CSS | homepage-articles, showImage:false + red left bar via ::before on wrapper. |
| 7 | Small-thumbnail row | CSS | homepage-articles, mediaPosition:left + 64–72px image constraint. No-image = red bar. |
| 8 | Featured + text list | Custom | Group block: one large homepage-articles (1 post) + one list (showImage:false). |

### Responsive approach

All modules are **mobile-first** (base styles target 375px; tablet breakpoint 640px; desktop 1024px). Key behaviors:
- Multi-column grids collapse to 1-col on mobile
- Carousel stays touch-swipeable on mobile (scroll-snap, no JS required) and reverts to static row on desktop
- Featured+list stacks vertically on mobile (lead top, list below)
- Typography uses `clamp()` so PT Serif headlines scale fluidly without overflow at any width
- No-image fallback (3px red bar in Module 7) maintains layout at the same x-offset as a thumbnail — no layout shift

### Mockup

`text-modules-preview.html` — all 8 module types with image-rich and text-only states, implementation table, per-module mobile behavior annotations, and one composed A La Music umbrella example. Fully responsive (open on phone to test).

---

## Phase: Photography P1-Additive Dry Run — 2026-06-17

**Status: DRY RUN COMPLETE — awaiting user approval before any writes.**

Identified photographer-authored gallery posts sitting in Uncategorized that are not yet in the Photography category (term_id=6). Operation is strictly additive — no existing category assignments would be removed.

Photographers queried: Ryan Johnson (172), Jennifer McInnis (137), Sharon Steele (178), Mariko Margetson (218), Peter Ruttan (188), Tom Paillé (407), Ben Hartley (408), Sterling Larose (409), Scott Place (410), Bob Hanham (411).

**Findings:**
- Total candidates: 114 posts
- WOULD ADD Photography: 113 posts
- WOULD EXCLUDE: 1 post (ID 62786 — Jennifer McInnis, "The Voyage: Boca del Lupo - Review" — written theatre review, not a gallery)
- Classification: 93 gallery-title-prefix, 19 gallery-jig-error, 1 gallery-jig-plugin, 1 non-gallery
- Photography category after operation: 30 existing + 113 = 143 posts

**Candidate file:** `photo_refile_candidates.txt` — full list with ID, author, title, current categories, classification, and action for all 114 posts.

**Decision:** Deferred P3 Photography section (real gallery content was 90% in Uncategorized). This dry run confirms that assessment. Awaiting approval to execute the additive write.

---

## Phase: Photography P1-Additive Write — 2026-06-17

**Status: COMPLETE.**

Pre-write DB backup taken: `~/Library/Mobile Documents/com~apple~CloudDocs/vw-rebuild-backups/local-20260617-153739-pre-photo-refile.sql` (145 MB).

Added Photography category (term_id=6, term_taxonomy_id=6) additively to 113 confirmed gallery posts. Operation used `INSERT IGNORE` inside a transaction; existing category assignments untouched. ID 62786 (written theatre review) excluded as planned.

**Result:**
- Photography post count: 30 → 143 (+113)
- Uncategorized count: unchanged (posts remain in Uncategorized + Photography)

**Spot-check (5 posts, before → after):**

| ID | Author | Before | After |
|---|---|---|---|
| 67874 | Ryan Johnson | Uncategorized | photography \| Uncategorized |
| 65945 | Jennifer McInnis | Uncategorized | photography \| Uncategorized |
| 67821 | Sharon Steele | Uncategorized | photography \| Uncategorized |
| 67485 | Tom Paillé | Uncategorized | photography \| Uncategorized |
| 67831 | Sterling Larose | Uncategorized | photography \| Uncategorized |

ID 62786 verified untouched (categories: `out n about` only).

**Candidate audit file:** `photo_refile_candidates.txt` (all 114 evaluated, 113 acted on).

**Git:** Committed locally at `c385458`. Push pending GitHub credential refresh (HTTPS auth not configured in this environment — run `! gh auth login` then `! git push` to publish).

---

### Deferred follow-ups noted from /category/photography/ inspection

Do not fix now. Log for future passes:

1. **"by Photography" byline on some posts** — attribution gap remains. Some photographer-authored posts display the category name as the byline rather than the photographer's display name. Separate from this re-file; affects posts where the WP user display_name was not correctly set during import.

2. **Visible duplicate posts** (Brad Paisley, The Strokes, Black Label Society appear twice in the archive) — duplicate-import issue, known and pending. The Photography re-file surfaced them because both copies are now in Photography. Duplicate cleanup is a separate pass.

3. **"Comment Comment…" artifact text in excerpts** — Jig2 gallery imports have plugin error wrapper text leaking into post excerpts. Affects the 19 `gallery-jig-error` posts (Jennifer McInnis). Separate cleanup pass needed: either strip the excerpt or set a manual excerpt on each.

---

## Phase: A La Music Section Front — Styling State — 2026-06-17

**Status: COMPLETE (styling pass done; live at `/category/a-la-music/`).**

Built custom PHP section template (`section-parts/a-la-music.php`) instead of Newspack block approach — Newspack block output is incompatible with our design system CSS classes. Template included via `category.php` `.php` template check.

### Architecture
- `category.php` checks for `section-parts/{slug}.php` first, falls back to `.html` (do_blocks), then Newspack default archive
- `vw_image_tier()` helper in `functions.php`: checks file existence on disk (guards dead Facebook imports), reads 'full' width → T0 (missing/broken), T1 (≥1024px), T2 (480–1023px), T3 (<480px)
- `vw_primary_cat_name()` helper: returns category name preferring music cats in order
- Music cat IDs: 7 (a-la-music), 9 (live-music-reviews), 8 (album-reviews), 11 (music-interviews), 20 (music-videos), 10 (music-editorials)

### Four-zone structure (live)
- **Zone A — Hero**: single T1 post (≥1024px image, file on disk verified). Prefers sticky post; falls back to most recent T1.
- **Zone B — Featured + list**: image lead + 5 compact text items
- **Zone C — Headline list 2-col**: 10 posts in newspaper 2-column layout
- **Zone D — Card grid**: 6 posts; T1/T2 get image card, T0 gets text-forward card

Post deduplication across zones via `$used_ids` → `post__not_in` in each successive WP_Query.

### CSS state (section-landing.css)
- Container: `max-width: 1440px` (widened from 1100px during this session)
- Nav: full viewport width (no max-width on `.vw-nav__inner`), `padding: 0 40px`
- Module spacing: `padding: 52px 0` on `.vw-module`, 48px bottom on hero module
- Module dividers: `border-top: 1px solid var(--vw-border)` between modules
- Section header: 30px PT Serif centered, reduced padding
- Kickers: `--vw-kicker-ink: #1A1A1A` (near-black, not red)
- Bylines: `--vw-byline-ink: #555555` (quiet dark gray); `strong` wrap on author names → weight 600
- Image cards: image full-bleed, text block has `14px 16px 0` padding
- Text-forward cards: `border-left: 3px solid var(--vw-red)`, byline grouped directly under headline (not bottom-pinned)
- Image border: `1px solid var(--vw-border)` on `.vw-card__img`

### Still pending for this section
- Eyebrow tab: solid black overlapping label on image cards (Zone D) — approved, not yet built
- Single article template (`single.php`)
- out-n-about, must-see-films sections need same styling pass

---

## Design Direction: Pitchfork 3-Column Lead Block — 2026-06-17

**Status: LIVE in `a-la-music.php`.**

Symmetric 3-col lead block replaced the single hero as Zone A of section fronts. Structure:

- **Left column**: text-only headline list, 5 items, kicker + headline + byline, no images
- **Center column**: featured story — image (natural aspect ratio, uncropped, `width:100%; height:auto`) + headline + dek + byline + divider + second text-only story stacked below
- **Right column**: text-only headline list, 5 items, same treatment as left
- Thin vertical rules divide columns. 3-col at 1024px+, mobile collapses center-first.

Post deduplication across zones via `$used_ids` → `post__not_in` in each successive WP_Query.

---

## Section geometric marks — Direction A: Paired Bars — 2026-06-17

**Status: LIVE in `section-landing.css`.**

Replaced placeholder primitive shapes (circle/square/triangle/diamond) with Direction A: Paired Bars. Implemented via `::before`/`::after` pseudo-elements — no extra markup. Each mark uses two rectangles arranged differently per section:

- **A La Music**: 2 equal horizontal bars stacked (red #C41230)
- **Photography**: tall + short bar side by side (slate blue #2A4A6B)
- **Food & Drink**: full-width bar + shorter bar indented right (amber #8B5E3C)
- **Out N About**: 3 descending bars — staircase (forest green #2E6B4A); implemented via span element + 2 pseudo-elements

Three design directions were prototyped in `text-modules-preview.html` (Directions A/B/C) before Direction A was chosen.

---

## Section-front rollout — Photography, Food & Drink, Out N About (2026-06-17)

**Status: COMPLETE.** All three sections now use the same four-zone PHP template system as A La Music.

| Section | File | Category IDs | Post pool |
|---|---|---|---|
| Photography | `section-parts/photography.php` | 6 | 143 |
| Food & Drink | `section-parts/food-drink.php` | 13, 14 | 27 (all 22 hungry-social posts are also in food-drink — net 27 unique) |
| Out N About | `section-parts/out-n-about.php` | 17 | 211 |

Food & Drink Zone D renders empty (graceful — `if ($grid_posts)` guard) because 27 posts are exhausted by Zones A–C. All other zones fill normally.
`out-n-about.php` supersedes the old `out-n-about.html` — `category.php` checks `.php` first.

---

## Section-front heading fix — 2026-06-18

**Root cause:** Category `name` values stored lowercase in the database (`food drink`, `out n about`, `photography`, `must see films`) — a Wayback import artifact. The heading in `category.php` used `$cat->name` directly, so WP returned the stored lowercase string.

**Fix:** Added `$section_display_names` map in `category.php` keyed by slug → proper display name. Heading now uses `$section_display_names[$slug] ?? $cat->name`. No database changes; no CSS changes (`.vw-section-header__title` had no `text-transform` applied).

```
'a-la-music'    => 'A La Music'
'photography'   => 'Photography'
'food-drink'    => 'Food & Drink'
'out-n-about'   => 'Out N About'
'must-see-films' => 'Must See Films'
```

---

## Photography section-front diagnostic findings — 2026-06-18 (read-only)

### 1. Photography duplicate titles — DIFFERENT post IDs (Wayback import duplicates)

The repeated titles are two distinct post IDs imported from different Wayback snapshots of the same page. The template's `$used_ids` dedup prevents the same ID appearing twice per load — it cannot prevent two separate IDs with identical titles. Both IDs are now in Photography (after the P1-additive re-file), so one lands in an early zone and the other in a later zone.

Confirmed duplicate pairs (title, both IDs, publish date):

| Title | ID 1 (author) | ID 2 (author) | Date |
|---|---|---|---|
| Photos: Alan Doyle \| Queen Elizabeth Theatre | 228 (Photography) | 67485 (Tom Paillé) | 2020-03-10 |
| Photos: Brad Paisley \| Abbotsford Centre | 231 (Photography) | 67524 (Tom Paillé) | 2020-03-09 |
| Photos: The Strokes \| Rogers Arena | 234 (Ryan Johnson) | 67874 (Ryan Johnson) | 2020-03-06 |
| Photos: Black Label Society \| Vogue Theatre | 237 (Ryan Johnson) | 67520 (Ryan Johnson) | 2020-03-06 |
| Photos: Doug and The Slugs \| 41st Anniversary | 240 | 67571 | 2020-03-03 |
| Photos: Antibalas \| The Rickshaw Theatre | 243 | 67495 | 2020-02-23 |
| Photos: Platinum Blonde \| The Commodore Ballroom | 246 | 67788 | 2020-02-22 |
| Photos: WWE Friday Night SmackDown \| Rogers Arena | 249 | 67914 | 2020-02-15 |
| Photos: BATTLEWORLD '88 Wrestling \| Rickshaw Theatre | 1429 | 67508 | 2020-02-03 |
| Photos: Sinéad O'Connor \| Vogue Theatre | 1433 | 67821 | 2020-02-02 |
| Photos: ALEXISONFIRE with The Distillers \| Pacific Coliseum | 1438 | 67487 | 2020-01-26 |
| Photos: King Princess \| Queen Elizabeth Theatre | 1442 | 66854 | 2020-01-19 |
| Photos: Tebey \| Commodore Ballroom | 1447 | 67838 | 2020-01-18 |

Pattern: lower IDs were likely imported directly from the 2019 SQL dump; higher IDs (`67xxx`, `66xxx`) were imported from the Wayback HTML crawl. Fix = duplicate post cleanup pass (deferred, separate from this session).

### 2. "By Photography" byline — real WP user accounts, not a fallback

Not a missing-author fallback. Two user accounts with `display_name = 'Photography'`:

| User ID | Login | display_name | Posts |
|---|---|---|---|
| 171 | Photography Contributing Editor | Photography | 9 |
| 318 | photography | Photography | 1 |

**Total: 10 posts** in the Photography section show "By Photography". These are organizational accounts used during original site operation (probably a catch-all for gallery posts without a named photographer). Affects only the Photography section — no other section has a user account named after it. Fix options: (a) update both accounts' `display_name` to a real name or "VW Photography Editor", or (b) suppress byline when `display_name` matches category name. Deferred.

### 3. Junk excerpts — two distinct sources

Both cases: `post_excerpt` field is empty → template falls back to stripping `post_content`. The pollution is in the content, not in a template bug.

**"Comment Comment Comment…" (Chantal Kraviazuk, ID 222 and similar JIG2 gallery posts):**
`post_content` is a Justified Image Grid (JIG2) plugin layout with Facebook SDK markup embedded (`class="_53f _53fl sgs-comment"`). These `<a>` elements have visible text "Comment" — once per image in the gallery. After `strip_shortcodes` + `wp_strip_all_tags`, the Facebook SDK anchor text survives as plain text. `wp_trim_words(…, 25)` picks up 25 of these "Comment" tokens as the excerpt. Source: **Facebook SDK/JIG2 remnant text in `post_content`; `post_excerpt` is empty.** Affects the 19 `gallery-jig-error` posts identified in the Photography re-file audit.

**"Photo from firecrustpizzeria.com…" (ID 1626 "Break your fast, not your wallet" and similar):**
`post_content` begins with `<figcaption class="wp-caption-text">Photo from firecrustpizzeria.com</figcaption>` before the article body paragraphs. After `wp_strip_all_tags`, the first text extracted is the credit line. `wp_trim_words` picks this up as the excerpt start. Source: **`<figcaption>` image credit appears first in `post_content` before the article text; `post_excerpt` is empty.** Affects any post where the Wayback import preserved a leading caption before the first paragraph.

**Fix path (deferred):** Set a manual `post_excerpt` on affected posts, OR strip `<figcaption>` content before excerpt extraction in `$vw_get_excerpt`. The 19 JIG2 posts are the bigger group; the figcaption issue is sporadic.

---

## Photo rights position: Facebook archive galleries — 2026-06-18

This entry records the working rights position for republishing the Vancouver Weekly Facebook photo archive on the rebuilt site.

### Position

Photographers retain copyright in their concert and event photos. Vancouver Weekly holds a license to display them as editorial content.

**Basis:**

1. Show access was obtained via VW editorial requests to publishers, agents, labels, and venues, in VW's name, so photographers could shoot on VW's behalf for VW to publish.
2. VW has email correspondence with each photographer granting access to the photos.

Together these support a documented license to publish the work as VW editorial content.

**Standing practice:** For any use beyond editorial display (print, or other formats), VW requests permission from the photographer on a case-by-case basis. This practice defines the license scope.

### Scope

| Use | Covered |
|---|---|
| Republishing archived galleries as editorial content, with photographer credit, on the rebuilt site (same editorial use, new software) | ✓ OK |
| Selling or licensing the photos to third parties | ✗ Not covered without fresh permission |
| Commercial or sponsored reuse beyond editorial display | ✗ Not covered without fresh permission |
| Print or alternate formats | ✗ Not covered without fresh permission |

### Evidence to preserve

Collect the per-photographer access emails into one location (e.g. a `/rights` folder or a tracked document) so the permission record is retained and not lost in old inboxes.

### Open items before public launch

- Confirm the photo license transfers from Vancouver Weekly Corp. to Brand Megaphone Media Inc. as part of the asset/trademark transfer.

### Notes

- "Published in Canada" sets governing law (BC), not ownership. Not relied on as a rights basis here.
- Photos remaining reachable at old URLs is a technical continuity fact, not a rights basis. Not relied on here.
- **This is a documented working position, not legal advice. Confirm with counsel before commercial use.**

---

## 2026-06-18 — Photographer account cleanup: duplicate consolidation + display-name fixes

### What changed

Applied 9 DB writes in a single transaction after a full dry-run plan approved by Ricardo. Two categories of change: (1) stray posts moved from dormant duplicate accounts to the canonical active account for each photographer; (2) two display-name corrections.

**Pre-operation backup:**
- `db-backups/vancouverweekly_local_2026-06-18_174332_pre-account-cleanup.sql` (144 MB)
- `~/Library/Mobile Documents/com~apple~CloudDocs/vw-rebuild-backups/local-2026-06-18_174332-pre-account-cleanup.sql` (144 MB)

### Post reassignments (7 posts)

| Post ID | Post title | From (dormant) | To (active) |
|---|---|---|---|
| 65478 | A reckless experiment in dialogue and music with an audience | ID 276 (sharon.steele) | ID 178 (Sharon Steele) |
| 65551 | An Evening of Sweet Surprises: Ry X at the Rio Theatre | ID 288 (mariko.margetson) | ID 218 (Mariko Margetson) |
| 65867 | Chain and the Gang, Invisible Rays, Scotty P. & the Virgins at Electric Owl Social Club | ID 317 (jon.vincent) | ID 129 (Jon Vincent) |
| 65871 | Chantal Kraviazuk @ The Massey Theatre in New Westminster | ID 318 (photography) | ID 171 (Photography Contributing Editor) |
| 66069 | Descendents – First of two SOLD OUT shows at The Commodore Ballroom | ID 330 (peter.ruttan) | ID 188 (Peter Ruttan) |
| 67473 | Photo highlights of Rifflandia Music Festival 2015, part one | ID 371 (erik.lyon) | ID 128 (Erik Lyon) |
| 60290 | Photos: A Tribe Called Red @ The Commodore Ballroom | ID 171 (Photography — catch-all) | ID 218 (Mariko Margetson) — credit restored via FB export album ID match |

### Display-name corrections (2 accounts)

| User ID | Login | Before | After |
|---|---|---|---|
| 408 | ben.hartley | Ben Hartley | Ben Hartley-Marjoram |
| 372 | timothy.nyguyen | Timothy Nyguyen | Timothy Nguyên |

Ryan Johnson (ID 172) display_name left unchanged as "Ryan Johnson" per decision.

### Before / after post counts per affected account

| ID | Display name | Before | After |
|---|---|---|---|
| 128 | Erik Lyon (active) | 4 | **5** |
| 371 | Erik Lyon (dormant) | 1 | 0 |
| 129 | Jon Vincent (active) | 2 | **3** |
| 317 | Jon Vincent (dormant) | 1 | 0 |
| 218 | Mariko Margetson (active) | 7 | **9** |
| 288 | Mariko Margetson (dormant) | 1 | 0 |
| 188 | Peter Ruttan (active) | 4 | **5** |
| 330 | Peter Ruttan (dormant) | 1 | 0 |
| 171 | Photography / active catch-all | 10 | 10 |
| 318 | Photography (dormant) | 1 | 0 |
| 178 | Sharon Steele (active) | 21 | **22** |
| 276 | Sharon Steele (dormant) | 1 | 0 |

Jennifer McInnis (ID 137 active / 246 dormant) and Ryan Johnson (ID 172 active / 248 dormant) had 0 stray posts — no changes.

### How to reverse (complete undo)

Restore from either backup above, or apply these exact reversal statements:

```sql
-- Reverse post_author reassignments
UPDATE wptg_posts SET post_author = 276 WHERE ID = 65478;
UPDATE wptg_posts SET post_author = 288 WHERE ID = 65551;
UPDATE wptg_posts SET post_author = 317 WHERE ID = 65867;
UPDATE wptg_posts SET post_author = 318 WHERE ID = 65871;
UPDATE wptg_posts SET post_author = 330 WHERE ID = 66069;
UPDATE wptg_posts SET post_author = 371 WHERE ID = 67473;
UPDATE wptg_posts SET post_author = 171 WHERE ID = 60290;

-- Reverse display_name corrections
UPDATE wptg_users SET display_name = 'Ben Hartley' WHERE ID = 408;
UPDATE wptg_users SET display_name = 'Timothy Nyguyen' WHERE ID = 372;
```

---

## 2026-06-18 — Session end / resume here

Account hygiene complete. 9 DB writes applied and committed (`c7efc0e`). Two 144 MB pre-cleanup backups taken (`db-backups/` and iCloud). Reversal SQL logged in the entry above.

**ATTRIBUTION RULE (do not violate next session):** the real photographer is NOT the Facebook uploader (often Ryan Johnson, who was photo editor and uploaded everyone's work) and NOT the catch-all "Photography" account. Author/credit comes from the existing WP author field where present (100% reliable where a body credit exists), and for uncredited catch-all posts, from the matched FB album description.

**Next task:** rewritten single-album gallery import dry-run for the Elliott Brood album → repair JIG2 post 67693 in place (preserve its frozen URL). It must KEEP the existing post author and only assign credit where it is currently missing. Per the Elliott Brood album, the photographer IS Ryan Johnson in this specific case (FB album credited to him), so 67693's author may end up Ryan — but that is because the album credits him, NOT because he was the uploader. Do not generalize "set author to Ryan" to other albums. Read-only dry-run first, then gate the import behind a fresh DB backup.

---

## 2026-06-18 — Elliott Brood post 67693 repair: dry-run plan complete

**Source:** FB export zip `facebook-VancouverWeekly-2026-06-18-54FRaXvE.zip`, album path `this_profile's_activity_across_facebook/posts/media/ElliottBrood_1322622027847073/`, 21 JPEGs ~1.56 MB total. FB album description: "Photos by Ryan Johnson // Sept.28/2017".

**Current state of 67693:**
- Slug (FROZEN): `photos-of-elliott-brood-at-the-commodore-ballroom-in-vancouver58522-2`
- post_date: 2017-10-02, post_status: publish
- post_author: ID 1 ("Vancouver Weekly") — catch-all admin
- Category: Uncategorized only (term_id 1); no Photography category
- Featured image: not set (`_thumbnail_id` absent)
- Existing attachments parented to 67693: none
- Content: dead JIG2 `jigSgConnect` markup. The `<noscript>` fallback preserved 21 `<img>` tags with Wayback-proxied fbcdn URLs (all dead). Every image alt already reads "Photos by Ryan Johnson"; one reads "ELLIOTT BROOD @COMMODORE SEPT.28/2017 / Photo by Ryan Johnson Sept.28 / 2017". Footer has Ryan Johnson author bio and tags (ryan-johnson, rynstein, concert-photography, elliott-brood, commodore-ballroom, etc.).

**What the repair changes:**
1. `post_content` — replace entire JIG2 HTML with a native `<!-- wp:gallery -->` block, 21 images in FB album JSON order
2. `post_author` — 1 → **172 (Ryan Johnson)**; correct because the FB album credits him, not because he uploaded it
3. `post_excerpt` — empty → "Photos by Ryan Johnson"
4. `_thumbnail_id` — set to attachment ID of cover photo `1322623491180260.jpg` (photo #20, designated as FB album cover)
5. Category — add Photography (term_id 6) alongside existing Uncategorized

**What is NOT changed:** slug, post_name, post_date, post_title, all existing tags, Uncategorized category.

**Image handling:** Stage 21 JPEGs from zip to `/tmp/elliott-brood-import/` (scratch, never wp-content until approved). `wp media import` uploads to `uploads/YYYY/MM/`; each attachment gets `post_parent=67693`, `post_author=172`, caption and alt_text "Photo by Ryan Johnson".

**Reversibility:**
- Before any write: full DB backup (both destinations) + isolated text backup of current post_content → `db-backups/post-67693-jig2-content.txt`
- Log all 21 attachment IDs to `db-backups/post-67693-attachment-ids.txt` immediately after import
- Reverse without full DB restore: restore post_content from text file, reset author/excerpt, delete `_thumbnail_id` meta, remove Photography term, `wp media delete` the 21 attachment IDs from the log

**5-gate sequence (for actual repair):**
- Gate 0 — Fresh DB backup (both destinations, confirm ≥ 140 MB) + text backup of current post_content. **STOP.**
- Gate 1 — Extract 21 JPEGs to `/tmp/elliott-brood-import/`, confirm count = 21. **STOP.**
- Gate 2 — `wp post update 67693 --post_status=draft`; `wp media import` 21 files parented to 67693; set caption + alt on each; log attachment IDs. Confirm 21 attachment rows in DB. **STOP.**
- Gate 3 — Set author, excerpt, featured image, Photography category, new gallery block content. Preview in browser as draft. **STOP.**
- Gate 4 — `wp post update 67693 --post_status=publish` only after visual approval.

Status: dry-run complete. Awaiting go for actual repair.

Also note: working tree has unrelated uncommitted changes (`VW-MASTER-PLAN.md`, `text-modules-preview.html` modified; logo files, regen logs, screenshots, `uncategorized-review.csv` untracked) — none related to today's work, left as-is.
