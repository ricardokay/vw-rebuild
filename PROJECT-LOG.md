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

**Image handling:** Stage 21 JPEGs from zip to `/tmp/elliott-brood-import/` (scratch, never wp-content until approved). `wp media import` uploads to `uploads/YYYY/MM/`; each attachment gets `post_parent=67693`, `post_author=172`, caption "Photo by Ryan Johnson"; alt_text blank with `_needs_alt_review` meta flag set. Credit lives in the caption; images stay in the alt-review queue.

**Reversibility:**
- Before any write: full DB backup (both destinations) + isolated text backup of current post_content → `db-backups/post-67693-jig2-content.txt`
- Log all 21 attachment IDs to `db-backups/post-67693-attachment-ids.txt` immediately after import
- Reverse without full DB restore: restore post_content from text file, reset author/excerpt, delete `_thumbnail_id` meta, remove Photography term, `wp media delete` the 21 attachment IDs from the log

**5-gate sequence (for actual repair):**
- Gate 0 — Fresh DB backup (both destinations, confirm ≥ 140 MB) + text backup of current post_content. **STOP.**
  - *Reversal:* None needed; nothing written to DB yet, backups are additive.
- Gate 1 — Extract 21 JPEGs to `/tmp/elliott-brood-import/`, confirm count = 21. **STOP.**
  - *Reversal:* Delete scratch dir `/tmp/elliott-brood-import/`. Nothing in wp-content or DB yet.
- Gate 2 — `wp post update 67693 --post_status=draft`; `wp media import` 21 files parented to 67693; set caption on each; set `_needs_alt_review` flag; leave alt_text blank; log attachment IDs. Confirm 21 attachment rows in DB. **STOP.**
  - *Reversal:* `wp media delete` the 21 IDs from `db-backups/post-67693-attachment-ids.txt`, then `wp post update 67693 --post_status=publish` to restore prior status.
- Gate 3 — Set author, excerpt, featured image, Photography category, new gallery block content. Preview in browser as draft. **STOP.**
  - *Reversal:* Restore `post_content` from `db-backups/post-67693-jig2-content.txt`; reset `post_author` to 1; clear `post_excerpt`; delete `_thumbnail_id` meta; remove Photography term (6) leaving Uncategorized.
- Gate 4 — `wp post update 67693 --post_status=publish` only after visual approval.
  - *Reversal:* `wp post update 67693 --post_status=draft`.

Status: dry-run complete. Awaiting go for actual repair.

Also note: working tree has unrelated uncommitted changes (`VW-MASTER-PLAN.md`, `text-modules-preview.html` modified; logo files, regen logs, screenshots, `uncategorized-review.csv` untracked) — none related to today's work, left as-is.

---

## 2026-06-18 — WP-CLI DB connection fix (runtime-only, no wp-config.php change)

WP-CLI could not connect to the database: `DB_HOST=localhost` in wp-config.php forces a TCP connection to 127.0.0.1:3306, but Local only listens on a Unix socket. Fix is runtime-only — add two `-d` flags to every WP-CLI invocation:

```
-d mysqli.default_socket="/Users/ricardokhayatte/Library/Application Support/Local/run/HKOO9D7DI/mysql/mysqld.sock"
-d pdo_mysql.default_socket="/Users/ricardokhayatte/Library/Application Support/Local/run/HKOO9D7DI/mysql/mysqld.sock"
```

Verified with `wp option get siteurl` returning `http://vancouverweekly-local.local`, exit 0. wp-config.php is not modified. These flags are required for all Gate 2 `wp media import` and `wp post update` calls.

---

## 2026-06-19 — Elliott Brood post 67693 repair: COMPLETE

**Status:** Published on local. All 5 gates passed with verification.

- **Content:** Dead JIG2 `jigSgConnect` markup replaced with native `<!-- wp:gallery -->` block (3-col, inner `wp:image` blocks), 21 images in FB album JSON order
- **post_author:** 172 (Ryan Johnson) — per FB album credit, not uploader identity
- **post_excerpt:** "Photos by Ryan Johnson"
- **Featured image:** attachment 73141 (`1322623491180260.jpg`, FB album cover photo)
- **Categories:** Uncategorized (1) + Photography (6)
- **post_date:** 2017-10-02 08:55:56 — preserved, unchanged through publish
- **Slug/URL:** `photos-of-elliott-brood-at-the-commodore-ballroom-in-vancouver58522-2/` — frozen, HTTP 200 confirmed
- **Attachment IDs:** 73122–73142 (in FB album order), logged to `db-backups/post-67693-attachment-ids.txt`
- **Metadata per attachment:** caption "Photo by Ryan Johnson", alt_text blank, `_needs_alt_review=1`
- **Reversal path:** documented in the dry-run entry above (2026-06-18)
- **Pre-repair DB backup:** `db-backups/vancouverweekly_local_2026-06-18_183819_pre-67693-repair.sql` (144 MB, also in iCloud)

**Lessons learned (apply to remaining ~547 album imports):**

1. **WP-CLI socket flags required on every call.** `DB_HOST=localhost` forces TCP; Local only listens on a Unix socket. Use the `WP()` wrapper function with `-d mysqli.default_socket` and `-d pdo_mysql.default_socket` on every invocation.

2. **Category assignment trap.** `wp post term add <id> category 6` treats a bare number as a slug, not a term_id, and silently creates a junk term named "6". Always add by slug (`wp post term add <id> category photography`) or pass `--by=term_id` explicitly. Verify terms with a direct DB query after — not just `wp post term list`, which can display term_taxonomy_id in the term_id column and mislead.

3. **Gallery image src: use the real attachment GUID.** Build `src=` from the actual uploaded URL (`wp post list --fields=ID,guid`), not from a reconstructed `BASE/filename` string. WordPress may rename files on collision. It worked here because the `uploads/2017/10/` folder was clean, but at scale this is a real risk.

4. **Preview URL requires a logged-in admin session.** `?p=ID&preview=true` returns 404 for anonymous requests — that is not a repair failure. Always preview as a logged-in admin in the browser.

5. **Gate sequence is the right template.** Backup → extract to scratch → import+draft (Gate 2) → fields+content (Gate 3) → publish (Gate 4), with per-gate reversals, worked cleanly. Use this pattern for the remaining albums.

**Next:** This repair is the proof-of-concept for single-album gallery restoration. The remaining ~547 Facebook albums can follow the same 5-gate pattern.

---

## 2026-07-06 — Session: return after gap, GitHub push, environment verification, working-tree cleanup

Returned to the project after a ~2.5 week gap. Housekeeping and environment-safety session; no DB writes.

### What happened
- **Pushed 14 stale local commits to GitHub** (`5be6fcd..a81f414`) via Mac Terminal. Local `main` was ahead of `origin/main`; remote now current through `a81f414`.
- **Caught a remote-sandbox Claude Code session.** A Claude Code "on the web" session was running in a remote sandbox (`/home/user`, root user, a fresh git clone) — it **cannot** touch the real Mac filesystem or the Local WordPress DB. Confirmed the desktop Claude Code app runs against the real Mac filesystem (`/Users/ricardokhayatte/...`, Local's `lightning-services`/`run` present).
- **Committed two leftover June working-tree files** in separate scoped commits:
  - `VW-MASTER-PLAN.md` → `5fff3fa` (Phase 2 status expansion)
  - `text-modules-preview.html` → `47b533c` (prototype markup on the design scratch file)
  - Working tree now clean.
- **Restored a dropped bullet.** "Mobile-first responsive design" had been dropped from the VW-MASTER-PLAN Phase 2 list during a rewrite; re-added to the Phase 2 "Remaining" list before committing.

### Lesson learned
**Verify the environment at the start of every session before any file or DB work.** Run `pwd` / `whoami` (and confirm the Local support paths exist) first. If `pwd` shows `/home/user` or the project/Local paths are missing, the session is a remote sandbox and cannot do this project's local file or database work — stop before attempting it.

### Resolution survey (read-only) — FB album export

Ran a read-only pixel-dimension survey of the Meta export (`facebook-VancouverWeekly-2026-06-18-54FRaXvE.zip`, 2.34 GB, not unpacked). Read actual JPEG SOF header dimensions by streaming files through `unzip -p` — nothing extracted or written to the archive.

- **Inventory:** 557 album folders, 15,883 images (15,818 `.jpg` + 65 `.png`) — slightly above the earlier ~548/~15,500 estimate.
- **Sample:** every ~28th album (20 albums, 60 images) for representative spread.
- **Finding: images are NOT 800px-capped.** Long-edge range 800–2048px, most common 1200px, with a strong 2048px cluster (several whole albums at 2048: Skookum, Tech N9ne, Tift Merritt, Vancouver Folk, David Newberry). ~93% of the sample (56/60) exceeds 800px on the long edge. Resolution is consistent within each album.
- **Elliott Brood's 800px was a low-end outlier**, not representative of the set.
- **Caveat:** 2048px is Meta's *export* ceiling, not proof of press-original quality. True high-res originals (if any) may live in the Vancouver Weekly Gmail/Drive, not the FB export. Confirm before planning any high-res swap.
- Findings written to `fb-resolution-survey.md`; committed and pushed (`843ed54`).

### Album inventory classification (REPAIR vs ADD vs NEEDS_REVIEW) — read-only

Built a full classification of the 563 Facebook album JSONs in the export against the WordPress post archive, to decide per-album import strategy. Read-only throughout: SELECT-only DB access over Local's socket, album metadata parsed in-memory from the zip (no unpack), no DB writes, no post edits.

- **Method:** strict token-set title matching (editorial affixes stripped — `Photos:`, `NN Photos of`, album-only `| venue | date`; empty-guard so a title is never stripped to nothing) with containment ≥0.80, plus post_date within ±7 days as a disambiguator (not a matcher). REPAIR = strong title + in-window date + a dead-gallery marker; ADD = no real title match; NEEDS_REVIEW = everything ambiguous (subtyped: date-off-repeat, partial-match, no-marker-match, no-date).
- **Two matcher flaws caught during sample verification** (both were inflating ADD / hiding REPAIRs): the editorial-prefix `Photos:`/`NN Photos of` deflated title similarity, and short artist-name albums vs long descriptive titles scored near-zero under raw `difflib` ratio. Switching to affix-stripped token-set containment corrected the count from a provisional REPAIR 49 to 273.
- **SCOPE FINDING — the dead-gallery marker set undercounted broken posts.** The original 5 markers (`jig2`, `[jig`, `facebook.com/vancouverweekly`, `graph.facebook`, `OAuthException`) missed a class of broken galleries that embed dead Facebook-CDN images. Verified candidate new markers on a 20-post sample: **`fbcdn` kept** (~95% genuinely-broken galleries — expired `scontent.xx.fbcdn.net` `<img>` sets, 8–25 images each); **`facebook.com/photo` (0 hits), bare `/plugins/` (matches legit `wp-content/plugins/`), and `facebook.com/plugins` (FB post embeds, not galleries) tested and excluded as imprecise.** Marked broken posts rose **583 → 687**.
- **Final buckets: REPAIR 319 / ADD 22 / NEEDS_REVIEW 222** (563 total). The marker expansion moved **46** rows from `no-marker-match` → REPAIR (each a strong title + in-window date whose matched post carries an `fbcdn` dead gallery).
- **The true broken-gallery universe is ~687 published posts, larger than the 583 the original markers implied.** Repair scope revised up accordingly — the remaining Facebook-gallery repair work is bigger than previously logged.
- **Output:** `fb-album-inventory.csv` (committed `ab8a0b6`, then regenerated with expanded markers). The `marker` column records which pattern(s) fired per matched post so any REPAIR is traceable; 46 rows carry a `reclassified: fbcdn gallery` note in `reason`; 4 suspected false-ADDs (Hayley Kiyoko, Sting, Mother Mother, USS) are flagged for hand-review.
- **Still requires human review before import:** the 22 ADD (esp. the 4 flagged) and the 222 NEEDS_REVIEW (triage by `needs_review_subtype`).

### Hand-review of ADD + partial-match — headliner-dilution failure mode

Hand-reviewed the buckets and found a recurring matcher weakness: album names carrying support-act / promoter / venue / tour tokens dilute token-set containment below the 0.80 REPAIR threshold, hiding a real headliner-to-broken-post match. Fixed the affected rows (read-only DB confirmation, no writes):

- **22 ADD reviewed:** 7 were false-ADDs (a broken photo post existed on the headliner, same date) — reclassified ADD → REPAIR: Hayley Kiyoko→67591, Lissie→67641, Mother Mother→67722, Sting→67741, The Eagles→67853, Behemoth→67511, USS→67766. (3 beyond the 4 originally flagged.) Buckets went 319/22/222 → 326/15/222.
- **98 partial-match re-scanned** with a headliner-only match (first act before pipe/comma/"with", minus venue/promoter/tour words) requiring date within ±7d + a broken marker. Found **48 more hidden REPAIRs**; reclassified partial-match → REPAIR.
- **3 multi-day-festival mis-targets caught** where the coarse headliner matched the wrong day/part (distinct broken posts exist per day): **Rifflandia 2015 part 2 → 67474** (not part-one 67473), **Westward Day 3 → 68932** (not Day-2 68931, both "Busty" and "Charlotte" album folders).
- **Buckets now REPAIR 374 / ADD 15 / NEEDS_REVIEW 174.** Session bucket journey: 273/22/268 (initial) → 319/22/222 (fbcdn markers) → 326/15/222 (7 ADD false-ADDs) → 374/15/174 (48 partial-match dilution REPAIRs).
- **Lesson for the matcher:** headliner-token weighting (match on the lead act, treat support/venue/tour tokens as secondary) would have caught all 55 up front. The remaining 174 NEEDS_REVIEW have no clean in-window broken headliner match (date-off repeats, matched-a-review, or genuinely ambiguous) and still need human eyes.

### Launch-readiness audit — Elementor homepage + deploy gap

Ran a read-only launch-readiness audit (DB SELECTs, theme file reads, git log, and live HTTP against the running Local site). Two real blockers identified: **(1) the homepage** and **(2) deploy tooling**. The gallery-repair backlog is a quality issue, not a launch blocker.

- **HOMEPAGE — blocker.** The live front page is page 9, built in **Elementor** (author admin/ID 1, created 2024-06-10, i.e. the earlier failed agency restoration this rebuild exists to replace). It uses the `elementor_header_footer` page template and renders Elementor widget content. This contradicts the frozen architecture (no page builders; Newspack-native editorial layer). **Launching on the Elementor homepage is rejected** — it is the failed build this project replaces. Must be rebuilt Newspack-native before launch.
- **ELEMENTOR SCOPE — smaller than the DB implied.** DB footprint *looked* site-wide: Elementor Pro theme-builder Header #11 + Footer #25, ElementsKit mega-menu, 18 `elementor_library` + 8 `elementskit_content` objects, and 2 Elementor-authored articles (65340, 65350). **But a live-render check (HTTP against the running site) proved the chrome is ALREADY NATIVE on every template** — homepage, single post, and section front all render `header class="vw-nav"` (child theme) + Newspack, with zero `elementor-location-header/footer` and zero ElementsKit markup. The Elementor theme-builder chrome and mega-menu are **dormant — they do not render** (child theme `header.php` wins). The only live Elementor is the **homepage body (page 9)** plus the 2 Elementor articles.
- **EXTRACTION SCOPE — homepage-only.** To remove Elementor: rebuild page 9 Newspack-native, switch its page template off `elementor_header_footer`, handle the 2 Elementor posts (65340, 65350), then deactivate Elementor Pro / ElementsKit / Essential Addons. **The chrome needs no work — it is already native.** This substantially de-risks the earlier DB finding.
- **DEPLOY — unbuilt (greenfield).** No CI, Dockerfile, deploy/rsync/ssh scripts, production `wp-config`, `.env`, or Namecheap/DNS config exist in the repo — only doc *mentions* of deploy/production in planning files. Full local→production go-live tooling is greenfield.
- **CONTENT — visibly-broken number.** 687 of 3,580 published posts (19%) carry a dead-gallery marker = the count that would render visibly broken at launch. The other ~2,893 are clean. This is quality/backlog, not a hard launch gate.

### Session end — DB backup + next action

Session end: took a fresh verified two-location DB backup (`vancouverweekly_local_2026-07-06_pre-import-batch.sql`, ~144 MB, MD5-matched local + iCloud, ends with the `-- Dump completed` marker) as the restore point for all upcoming destructive work. Gallery-import test batch is the next action: dry-run 10 varied REPAIR albums, review, then execute one gated chunk. No imports run yet; DB unchanged this session (all DB work was read-only SELECTs).

### Pre-launch cleanup task — comment spam

Read-only SELECT confirmed: **5,899 total comments — 5,598 pending, 301 approved** (0 in spam/trash queues). Pending comments are visibly spam (crypto/Binance referral links, generic flattery, `shorturl.fm` links) imported alongside the Wayback-recovered posts. Pending do not display publicly, but the **301 approved need auditing** — some may be auto-approved spam that WOULD display to readers.

- **Pre-launch task (gated destructive op, its own session):**
  1. Back up, dry-run the pending-spam delete count, then purge the 5,598 pending.
  2. Audit the 301 approved for real-vs-spam **before** deleting any.
  3. Decide whether to disable comments on old recovered posts to stop recurrence.
- **Cleanup track — this joins existing deferred items:** 808 Uncategorized posts (editorial review), 308 empty spam categories (bulk delete). Comment spam is now part of the same pre-launch cleanup track.

### Mobile-responsive — a per-template launch requirement

**Mobile-responsive is a launch requirement across ALL templates, not a separate phase.** Every build step must be verified at mobile width (~375px) before it is considered done: homepage, article/single template, section fronts, and galleries. Checks: grid reflow, caption legibility, lightbox usability with touch/swipe/pinch, and no hover-dependent UI.

- **Galleries specifically:** the gallery grid must reflow to 1–2 columns on mobile; captions must not rely on hover; the native lightbox must be touch-usable — swipe/pinch, the close X reachable with a thumb, and tap-outside-to-close; and the bottom-center lightbox caption (from the planned credit-in-lightbox enhancement) must not crowd the native close/nav controls. To be verified when the caption + lightbox work is done.
- This is the **"Mobile-first responsive design"** item in VW-MASTER-PLAN, now with a concrete per-template check (not a deferred phase — each template signs off at 375px).

### Decision — v1 launches AD-FREE

**v1 launches ad-free. No display-ad sidebar in the first version.** Rationale: avoids a layer of layout/mobile complexity that would otherwise gate the article template and homepage; gives a cleaner reader experience for the relaunch; and matches the branded-sponsorship revenue model (sponsorships are content-woven, not sidebar display ads).

- **Advertising/sponsorship placement is deferred as a CONFIGURABLE future feature** — added deliberately when the approach is decided, not guessed at now.
- **Build constraint:** the article template and homepage are built single-column / content-focused **without assuming a sidebar**, but in a way that does **not preclude** adding sponsorship units or a sidebar later. Clean, flexible layout now; the ad/sponsorship layer is added as a feature when reached. **Check Newspack-native ad/sponsorship handling at that point before building custom.**

### Design direction to explore — masonry / Pinterest-style collage (v2 / design phase)

**Not v1.** A site-level image-presentation direction to mock up and test in the Claude Design phase: **masonry / Pinterest-style collage** — uneven-height tiles packed densely, rather than uniform feature-image rows.

- **Rationale:** reads as more alive / "more vibe," especially on mobile for a younger audience scrolling a dense image wall; fits VW's photography-first positioning and large photo volume (15,000+). Suggested by Ricardo's daughter re: teen/mobile viewing habits.
- **Scope note:** this is **site-level image presentation** (section fronts, homepage, archive browsing), **NOT the in-article gallery** (which is being finished now). Belongs in the Claude Design phase where it can be mocked up and tested on mobile before committing. Masonry done well is a real layout system (aspect-ratio handling, performance, responsive reflow), so scope it deliberately when reached. **Does not block v1.**

### Article / single-post template — empty sidebar is the default, needs custom single.php

**Observed:** single posts (e.g. draft 73383) render an **empty right sidebar column**. **Cause:** there is no custom `single.php` yet, so Newspack's default content+sidebar template renders and the sidebar column is empty (v1 is ad-free, no widgets). **Not a bug — it's the unstyled default.**

- **Resolve in the article-template design work (Claude Design phase):** decide the article layout (full-width content vs centered with intentional margin — **not a dead sidebar column**), content measure/width, typography, and whether **galleries should break out WIDER than the text column** (magazine-style, relevant for a photography-forward publication). Aligns with the ad-free / no-assumed-sidebar decision already logged.
- Custom `single.php` is already on the VW-MASTER-PLAN "Remaining" list; **this is the layout spec for it.**

### Gallery design reference — Scene in the Dark

**Reference for the Claude Design phase:** Scene in the Dark (sceneinthedark.com) concert-photo galleries — a strong model for VW gallery treatment. Key takeaways to consider for VW galleries specifically:

1. **DARK theme for galleries/photography context** — a black ground makes concert photos pop, even if the rest of the site stays on the off-white `#F7F6F4` ground. **Highest-impact idea; explore a gallery-context dark mode.**
2. **Large immersive grid tiles** (~3-per-row, generous size), not small thumbnails.
3. **Designed gallery header block:** artist name (serif display), venue, date, photographer credit, over a hero image — gives the gallery an identity beyond just the post title.
4. **Numbered photos** (01/16 …) for a curated, sequential feel.
5. **"More from the dark" related-galleries cross-linking** at the bottom — ties to VW's connected-content / archive roadmap.

**Caveat:** they're a dedicated concert-photography site; VW is a broader arts publication, so adopt the gallery treatment **without making the whole site photo-only**. The current VW gallery (grid-hide + native lightbox + per-image credit) is functionally solid; this is the **design-phase aspiration**, not a v1 blocker.

### Infinite-scroll / load-more — permalink & URL requirements

Refines the earlier "Pagination / infinite-scroll plan" (2026-06-17). Infinite scroll must be built **correctly, not naive JS-only** — and it must respect the frozen-URL architecture:

1. **Frozen-permalink rule preserved.** Infinite scroll is a **browsing/display layer only**; it must **NEVER** alter existing posts' slugs / URLs / dates (the frozen architecture rule). It loads *lists*, it does not touch individual post permalinks.
2. **Real paginated URLs underneath.** `/section/page/2/`, `/page/3/`, etc. must genuinely exist, render on direct load, and be crawlable. Infinite scroll is **progressive enhancement OVER working pagination** — strip the JS and it degrades to real pagination.
3. **URL updates as you scroll** (History API) so refresh/bookmark preserves position, the back button behaves sanely, and content is never trapped in JS-only state.
4. **SEO:** crawlers must reach paginated content via the real URLs behind the scroll.

- **"Done wrong"** = pure lazy JS infinite scroll (no real URLs, refresh loses place, footer unreachable, crawlers see only page 1). **Avoid.**
- **Check Newspack-native pagination / load-more first** — it likely handles the URL layer correctly (the current interim "Load more" is the Homepage Posts block's `moreButton`).

### Bulk gallery import — COMPLETE (drafts)

Bulk import of all clean REPAIR albums, done as **reversible DRAFTS** via the v3 tool (`tools/vw_gallery_import.php`) driven by an unattended, chunked, halt-on-error runner:

- **343 bulk drafts + 10,082 attachments** (247 path A / 96 path B), plus the earlier 15-album batch = **358 REPAIR posts drafted**. Of **364 unique REPAIR posts total: 358 drafted + 6 backlog.**
- **Live posts UNTOUCHED throughout** — published-post count still **3,580**. All galleries exist as drafts beside untouched live posts; **nothing is published or visible yet.**
- **Pre-bulk backup:** `db-backups/vancouverweekly_local_2026-07-07_pre-bulk-import.sql` (156,639,375 bytes, MD5 `f900df32ec02c771e7e95b813efcf34f`, local + iCloud).
- **Reversal:** the full run's draft+attachment list is `/tmp/vw_bulk_created.json` (343 drafts + 10,082 atts). **`/tmp` is ephemeral** — copied to durable `db-backups/vw_bulk_created_2026-07-07.json` (+ `vw_manual_backlog_2026-07-07.json`, `vw_bulk_import_2026-07-07.php`) so the reversal survives a reboot.
- **Halts during the run were the safety working, not failures:** the duplicate-guard caught stale test drafts (66353/67909/67771 from the v2 lightbox test), and the count-mismatch guard caught a same-name album (VFMF Day 2). Each halted the whole run cleanly; the run resumed after resolving.

**MANUAL BACKLOG (6 posts not imported + 2 duplicate-draft cleanups):**
- **Multi-album (album-merge decision):** 67730 (Bison / Black Wizard / Red Fang), 67694 (Father John Misty ×2), 67756 (Napalm Death / The Melvins), 68931 (Westward Day 2), 68932 (Westward Day 3).
- **Same-name (pick correct folder):** 67903 (VFMF Day 2 — 35 vs 50 images).
- **Duplicate-draft cleanup:** 73383 (dup of 74064, AC/DC 65497), 73318 (Westward 68931).

**NEXT MILESTONE (not started) — the PUBLISH/REPLACE step:** applying approved drafts onto the live published posts, preserving the frozen slug + date. **This is the first operation that modifies live content** — needs its own session, its own backup, a single-post test first, then halt-safe batching.

### Backup to-do — off-machine image archive

`wp-content/uploads/` is **17 GB** (11 GB re-derivable from the FB export zip via the import tool + `wp media regenerate`; ~6 GB original Wayback-recovered images). iCloud (`vw-rebuild-backups/`) now holds the **DB backups + reversal manifests + the FB export zip** (`source-exports/…54FRaXvE.zip`, MD5 `1de098699c67b533f198ec8c06d1457f`) — but **NOT** the full 17 GB uploads.

- **To-do:** stand up a proper **off-machine uploads archive** on cheap bulk object storage — **Backblaze B2 or Wasabi** (a few dollars/month for ~17 GB). An external drive is a stopgap; B2/Wasabi is the durable answer and ties into the eventual production media strategy.
- **Not blocking the publish step:** publish/replace modifies the DB (post_content), not the image files, and the DB is fully backed up (pre- and post-bulk `.sql`, two locations, MD5-verified). The imported images are also re-derivable from the FB zip (now off-machine on iCloud). The 17 GB archive is a durability upgrade, not a launch gate.

### Heritage identity thread — accuracy-critical

Research into Vancouver newspaper history: the **"Vancouver Weekly Herald" (Jan 15, 1886)** was among the **first papers in Vancouver — predating the city's official founding**. This is a **NAME RESEMBLANCE and thematic kinship, NOT documented lineage** to the current Vancouver Weekly.

- **DO NOT CLAIM DIRECT DESCENT.** Never say "Vancouver Weekly since 1886" or imply the current publication *is* / descends from the 1886 Weekly Herald — unsubstantiated, easily debunked, and a **credibility risk for a publication whose entire value is trust**.
- **What IS defensible/usable — an honest thematic framing:** *"Vancouver has had weekly papers since before it was a city; we carry that tradition forward."* This invokes the heritage of Vancouver weekly journalism (the **form and civic role**) **without** falsely claiming to be a specific historical title — reframing the VW name as rooted in the city's oldest press tradition.
- **Real, provable heritage** = VW's own ~20-year run + the recovered archive. The 1886 connection is **thematic color, not a lineage claim.**
- **Trademark note:** the VW name is now **trademarked (Ricardo)** — protects the mark going forward; this is separate from (and does **not** create) historical lineage.
- **Ties to:** the cultural-memory identity direction; the CIPO / trademark work; the copycat-site situation.

### Differentiation beyond "real" — accuracy-critical refinement

**"Real / local / authentic" is now TABLE STAKES** — every scene publication, indie brand, and Substack reaches for it. It differentiates VW from Instagram, but **NOT from peers.** Real differentiation = **function / role / depth**, not feeling:

1. **DEPTH OF TIME (structurally uncopyable).** Anyone can be authentic *today*; nobody else is authentic across **20 continuous years**. Peers can't show 2007, or a venue that closed in 2013. **Time is the moat — it can't be adopted, only had.**
2. **CUSTODIAL, not nostalgic.** Not "remember the good old days" content (crowded) but **BEING the archive of record** — the institution that *holds* the scene's memory, not an account that *evokes* it. **A role, not a vibe.**
3. **DIFFERENTIATION AS FUNCTION, not feeling:** the definitive Vancouver culture **archive of record**; browse-by-venue / browse-by-time as real **tools** (own the use case *"reconstruct every show at [closed venue]"*); **photographer preservation + credit as a real service to the makers** (exactly the dead-FB-page problem VW just solved) — differentiating **to the community that creates the content**.
4. **ENDURANCE.** Much of the moat is what VW consistently **does** that peers won't — keep publishing so the archive grows, maintain the custodial role. **"Still here in 5 years when peers burned out / pivoted" is itself a widening moat.**

- **CAUTION (logged):** the deepest differentiation reveals itself in **USE, not theory.** Two weeks live will teach more about VW's real edge than more armchair strategy. **Vision work has diminishing returns now; shipping is the highest-value next act.**

### VW IDENTITY & STRATEGY — consolidated brainstorm (design/identity phase, not v1-blocking)

**1. FRAMING PRINCIPLE (the criterion for the whole identity phase).** VW's identity must hold **BOTH the earned past AND the intended future as one throughline.** Earned past (credibility, uncopyable): 20 years of coverage, the recovered archive, thousands of concert photos, scene/photographer relationships, the trademark, roots in Vancouver's weekly-press tradition. Intended future (ambition): connected-article format (archive cards + interview audio), nostalgia feeds, browse-by-venue, AI search, regional network, a weekly curated experience, a reader-supporter model. **Past alone = tired nostalgia; future alone = rootless hype; together = the rare compelling story** ("we remember, and we're building what comes next"). The identity STATEMENT should carry both a **memory claim** and a **forward claim**.

**2. EDITORIAL STRATEGY (identity depends on this; Ricardo's call as owner/editor).** Two decisions bigger than the technical rebuild: **(a) forward content emphasis** — photography-first vs written journalism vs connected deep-dive vs curated/aggregated (likely a mix, but the *emphasis* drives hiring / homepage / identity); **(b) archive framing** — living reference vs nostalgia vs foundation-for-new-work vs plain back-catalog. **v1 RECOMMENDATION (bounded, launch-enabling):** lead with **archive + photography** (ready now, differentiated, no full newsroom day one), frame the archive as **living / foundational** (not just nostalgia); journalism + deep-dives grow with editorial capacity.

**3. WHY VW MATTERS NOW (emotional core).** Favorable countercurrent, especially for younger audiences: **AI saturation → hunger for the verifiably REAL** (credited photos of real shows gain value; VW's archive = thousands of instances of the real); **loneliness / fragmentation → turn toward PLACE / SCENE / belonging** (VW connects a real local scene = belonging, not content); **Vancouver's gentrifying / changing landscape → VW as CUSTODIAL** (holds the record of a scene people feel is slipping). Cool-to-young-people: archive as **immersive time-travel** (not a database), **belonging markers** ("I was at this show"), the scene as a **living human-curated map**, photography as **the honest medium** in an AI-image world.

**4. DIFFERENTIATION (beyond "real," which is table stakes).** "Authentic/real" now differentiates VW from Instagram but **NOT from peers** (everyone claims it). Real moat = **function / role / depth / endurance**: (a) **depth of time** (20yr continuous, structurally uncopyable — can't be adopted, only had); (b) **custodial not nostalgic** (BE the archive of record, a role not a vibe); (c) **function not feeling** (archive of record; browse-by-venue/time as real tools; photographer preservation + credit as a service to the makers); (d) **endurance** (still here in 5yr when peers burned out = a widening moat). **Caveat:** deepest differentiation reveals itself in USE — shipping teaches more than theory.

**5. VISUAL IDENTITY — "modernized stamp."** The existing VW logo reads like a **STAMP pressed on the page** (heavy condensed caps, boxed "WEEKLY" tab, tagline like official fine print, single-weight black). It's strong because it **unifies visual + strategic identity**: a stamp = **mark of the REAL** + a **RECORD / imprint** + **PERMANENCE**. **ANCHOR ASSET:** the existing social **"V" mark** (bold V + vertical WEEKLY tab in a **CIRCLE**) is already a mark-only variant AND already stamp-like (circle-with-a-mark = **seal / postmark** form) — **develop it, don't reinvent.** Explorations: the WEEKLY tab as a recurring **seal motif**; **postmark / date-stamp** treatment on archive content ("stamped July 2011"); the stamp as an **authenticity / credit mark** on photos ("shot live, stamped real"); mock up **2–3 rendered directions.**

**6. LOGO SYSTEM + NEW TAGLINE.** **Responsive logo system** (solves small-size legibility): **FULL** lockup (wordmark + tab + tagline) for large / print; **REDUCED** (wordmark + tab, no tagline) for mobile / nav; **MARK-ONLY** (the "V" / stamp glyph) for favicon / avatar / watermark. **New tagline needed** — "Vancouver's Weekly News Source" both over- and under-claims ("Weekly" = a cadence check to cash; "News Source" undersells the archive/memory identity and overclaims hard-news). The tagline = the **verbal half of the stamp**; it should compress the **real / custodial / record** identity. **"Alternative newsweekly"** (from VW's own FB bio) is a truer starting point than "news source." A **shorter** tagline is BOTH more identity-true AND more legible small — **the two problems solve each other.**

- **RESOURCE NOTE:** Ricardo knows the **Scene in the Dark** creator, who just did a similar AI-assisted rebuild — ask about his process / timeline / what broke.
- **REALITY-CHECK NOTE:** none of this reaches anyone until the site is **LIVE**. The archive is currently **drafts.** Finishing the technical work (credit fix → publish/replace → deploy) is what makes the identity real for its audience.

---

## FUTURE IDEAS / SOMEDAY-MAYBE (not scheduled, parked for after launch)

**These are unscheduled ideas and open questions — NOT committed work, NOT active plan items. Nothing here is prioritized or approved. Captured so they survive context loss.**

---

### 1. Next session (concrete, near-term)

Two read-only surveys before any bulk import:
- **Image-resolution survey** across the full FB export: learn whether other albums are all 800px (like Elliott Brood) or whether higher-res originals exist (e.g. noted 2048px shots). Determines whether it's worth waiting for high-res before importing.
- **Album inventory/classification CSV:** REPAIR vs ADD per album, credited vs uncredited photographer, photo counts, messy-name flags. Needed before any batch run.

Then a batch of ~10 **deliberately varied** albums (oldest, newest, uncredited, special-char photographer name, very large, very small, clean-add, middle repair) to prove the 5-gate pattern generalizes before committing to a full run.

Decisions to lock before the batch:
- Attribution rule for fully-uncredited albums (no FB album description credit, no WP author match)
- Repair-vs-add code paths (same gate sequence, different post target)
- Automation level: approve per album-checkpoint, not per command

---

### 2. Phased launch model

- Decouple "all 548 galleries imported" from "site live." Launch on core site + a strong subset of galleries; keep importing the rest after go-live. Drafts and ongoing imports don't block launch.
- Write a "launch-blocking vs post-launch" line early so design polish doesn't expand scope indefinitely.
- Lock structural decisions pre-launch (URL/permalink structure, deployment plan, hosting). Defer design refinement, image quality upgrades, and remaining gallery imports to post-launch in-place iteration.

---

### 3. High-res image swap (later quality upgrade)

If the photo editor delivers high-res originals, replace images in place via file-level swap + `wp media regenerate`. Attachment IDs stay stable, so posts, captions, attribution, and gallery layout are untouched.

**Critical matching key:** ask the editor to preserve the original 16-digit Facebook filenames OR provide an old-name-to-new-file mapping. Without it, matching 547 albums' worth of images becomes much harder. Do folder-at-a-time, reversible (back up originals first).

Confirm that high-res originals actually exist before planning this — coverage may be partial.

---

### 4. Live-site migration (own gated project)

The old live site is pharma-spam compromised. Approach:
- Take a full forensic backup (DB + files), store **cold/inert/offline**, never restore to live, never copy old-to-new. The clean rebuild is the source of truth; data flows rebuild-to-live only.
- Map the infection's entry point before cutover so the clean site isn't re-compromised the same way.
- Cutover is the highest-stakes, least-reversible step in the whole project: full backups of both sites, tested rollback plan, maintenance window, verification checklist before DNS flip. First step is hosting/DNS discovery.

---

### 5. Future features (speculative — validate against real traffic first)

- **User personalization:** saved items, follow topics/photographers, dark mode, personalized feed. Adds accounts, privacy obligations, and moderation maintenance. Do not build until there is evidence of demand.
- **AI navigation assistant:** technically feasible via Anthropic API. Build only AFTER good conventional search and IA are in place and real user behavior shows the need. **Must** be grounded strictly in published content with source citations — never free-form answers about Vancouver music/events (credibility and liability risk). Squarely a step-4 item, not launch-related.

---

## 2026-07-08

### Credit-accuracy pass — COMPLETE (pre-publish)

Read-only investigation found the import drafts' photographer credits were mostly correct but had a
small, specific set of errors. Scope was **SMALL and verified — ~324 drafts already fine, ~5 fixed —
NOT a mass re-credit.** All work done on reversible **drafts**; live posts untouched; published count
stayed **3580**. Frozen-date rule re-verified: draft `post_date` matches each live parent exactly
(10/10 sample MATCH; the "2026" seen in a draft preview is preview chrome, not stored data).

**Per-image credit enhancement (import tool v4 → v5).** The tool now reads each Facebook photo's OWN
per-photo description (FB user-tag `@[id:id:Name]`, `© YEAR Studio`, `[Band -] Photo by X`), using the
album-level description only as a fallback. Credits canonicalize to a WP author account (correct
spelling) or a studio→person alias. This fixes co-shot albums that were previously stamped with one
name — or both names — on every image.

**Fixes applied (reverse + recreate with v5):**
- **83303 → 85675** (live 67890, Trampled by Turtles): was "Ryan Johnson" on all 20 — a genuine
  author-box wrong-person override. Now **Kristina Kimlickova ×20** (the album's real photographer).
- **83692 → 85697** (live 68811, VFMF Day 3): was the band lineup ("Leo Moran and Anthony
  Thistlethwaite" ×104) pulled from an album desc with no photographer. Now **Jennifer McInnis /
  Creative Copper Images ×104** (from per-photo `© 2014 Creative Copper Images`).
- **85438 → 85803** (live 67901, VFMF 2017): was "Ryan Johnson and Mary Matheson" on all 96. Now
  per-image **Ryan Johnson ×72 / Mary Matheson ×24**.
- **85086 → 85901** (live 67903, VFMF Day 2): was "Mariko Margetson" on all 35. Now per-image
  **Mariko Margetson ×22 / Ryan Johnson ×13**.

**Timothy Nguyên spelling fix (16 drafts, in place — no recreation).** WP user 175 held a misspelled
display name ("Timothy Nyguyen"); corrected to **Timothy Nguyên** (matching account 372). Key finding:
gallery credits are **literal stored text** (in each draft's `post_content` figcaptions and each
attachment's `post_excerpt`), **not** rendered from the account — so the account fix alone changes
nothing readers see. Did an in-place string replacement across **16 import drafts: 16 content updates +
545 attachment excerpts**, 0 residual misspellings. Live attachments (spelled "Timothy Nguyen", no
extra "y") were not matched and stayed untouched.

**Getty / rights hold.** Draft **85536** (live 67584, "Netflix Golden Globe Awards After Party") is
**licensed agency press**, not VW original: 53 of 100 per-photo descriptions read "Photo by Kevin
Mazur/Getty Images for Netflix" with Getty markers, press-wire filenames, and celebrity-subject
captions. Editorial decision: **exclude from the live archive unless a license is confirmed.** Kept as a
draft (not deleted); flagged durably with post_meta `_vw_publish_exclude='getty-rights-hold'` and
recorded in `db-backups/publish-exclusions.json`. The publish/replace step must skip it on either signal.

**Reversibility.** Before/after manifests in `db-backups/reversal-manifests/`
(`vw_timothy_before_2026-07-08.json`, `vw_reverse4_credit_2026-07-08.json`,
`vw_credit4_created_2026-07-08.json`).

**Verified:** the per-image split renders correctly (e.g. 85803 shows "Photo by Ryan Johnson" on
Ryan's 72 photos and "Photo by Mary Matheson" on Mary's 24, per-photo, not both names on every image).

**Date check (read-only):** frozen-date rule intact. 10/10 varied drafts have `post_date` (and
`post_date_gmt`) identical to their live parent's original — the tool preserves the archive date. The
"2026" seen on a draft preview is WordPress draft-preview display chrome, NOT the stored `post_date`.

**OPEN before publish — body-byline artifact (scoped this session, fix PENDING, nothing applied):**
Read-only scan of all 364 import drafts found **61 with a leftover byline line in the BODY prose**
(separate from the intended gallery figcaptions), inherited from the Wayback-recovered source body and
not caught by the current `vw_clean_body`. Three shapes:
- **21 mangled** — old inline photo captions fused with FB filename digit-strings, e.g.
  "Angus and Julia Stone - photo by Jennifer McInnis10547921_616233141819302…_oAngus and Julia Stone -
  photo by…". Clearly corrupt; safe to strip (signature: a `\d{10,}_o` filename run glued into prose).
- **39 rights-lines** — "All photos by <Name> (<url>). All rights reserved." Legible but now duplicates
  the per-image figcaption credit.
- **1 "By." line** — draft 85803: "By. Ryan Johnson and Mary Matheson".
Proposed fix (future session, reversible): extend the body cleaner to strip (a) any text node containing
a `\d{10,}_o` FB-filename run, (b) a trailing "All photos by … All rights reserved." credit line, and
(c) a paragraph-leading "By."/"By:" byline — while leaving real prose, performer lineups, and figcaptions
untouched. Do NOT strip on a raw global regex; verify prose survives per draft. Affected-ID list captured
this session. Not a launch gate, but should run before publish so the body credit is clean.
