# Vancouver Weekly Rebuild — Project Rules

This file governs all Claude Code sessions on this project. Rules here override defaults.

---

## CURRENT STATE *(overwrite this section each session — do not append)*

**Archive:** 3,373 published posts. **85536 (getty-rights-hold) is the only held draft** — never publish without license confirmation; flagged via `_vw_publish_exclude` + `db-backups/publish-exclusions.json`.

**Done and verified:**
- Phases 1–3: 2,789 posts imported, 3,585 images, child theme + 4 section fronts. Spam cleanup, photographer account consolidation, album classification (563 albums).
- **Archive publish COMPLETE (2026-07-18):** 362/362 repaired galleries live (~11k images), body-chrome cleanup on 84 lives, residual chrome scan 0.
- **207 event listings retired (2026-07-25):** published 3,580 → 3,373, reversal manifest MD5-verified.
- **Dead-image suppression COMPLETE** (commit `d0c35ad`, render-sweep gated): splice-only `the_content` filter — deletes byte ranges, never reserializes, so retained bytes are bit-identical. DOMDocument was rejected on a 7%-real-divergence gate. **1,764 posts affected; 1,498 now render image-free; ~1,200 photo credits display-suppressed until the image is recovered** (nothing deleted — credits return automatically). Sweep: 1,764/1,764 HTTP 200, zero surviving dead-host images, zero orphaned captions. `recovery-inventory.csv` (1,764 rows) is the manual-recovery worklist.
- **Single posts:** forced to `single-wide.php` (1200px) via a child-theme `get_post_metadata` filter — **zero DB writes**, beats both meta states (2,215 unset / 1,158 "default"), gated off in admin/REST.
- **FB galleries:** 3-column CSS-columns masonry, natural aspect ratios, `column-count` is a one-number switch. **"Powered by Newspack" removed** (gettext filter + CSS).
- **Section fronts:** nav 404s fixed (six sections, real `/category/` URLs); same-section kickers suppressed; junk-author bylines display-suppressed (Contests 49 / Photography 10 / News Feed 5 = 64 posts; authors unchanged); depth cap 17 stories + "Browse all N" handoff; display-layer title dedupe, newer copy wins.
- **Archive pages** inherit the design system (serif headlines, palette, byline treatment, no "Category:" label) and paginate properly — `category.php` now falls through to the archive when `is_paged()`.
- **Timezone FIXED:** `America/Vancouver` (gmt_offset −7). WP clock matches local time.
- **Contributor Kit source captured** (2026-09-11): five JPGs + full verbatim transcription in gitignored `source-material/contributor-kit/`, mirrored to iCloud. DB page 68 is empty, so this is the only surviving copy.
- **Sitewide v2 masthead LIVE (2026-09-12, commit `d417a70`):** dateline, centred SVG wordmark, motto, six-section nav, red `#C41230` active item. `header.php` calls `vw_masthead_render()` directly; the front page is skipped because the homepage part renders its own inline. The legacy `.vw-nav` header renders **nowhere** — 0 occurrences across 16 swept surfaces. `?vw_masthead=1` is a retained no-op.
- **Adaptive article header LIVE on single posts (2026-09-12, commit `d417a70`):** case A full-width ≥1200px / B split / C stacked, accent rule, sentence-case bylines. Shipped as a child override of `template-parts/header/entry-header.php` — the part `single.php` and all four `large-featured-image.php` branches already request — so no fork of `single.php`. Newspack's duplicate hero is suppressed by filtering `newspack_featured_image_position`. Photo-led dedup drops the header image to case C when the featured attachment also opens the body gallery; desk-label authors omit the By line. `?vw_header=1` is retired.
- Rollback assets valid: `pre-publish` + `pre-chrome-cleanup` dumps + reversal manifests, local + iCloud.

**Launch decisions (Ricardo):**
- **B1:** retire the **230 JIG/noscript posts** to draft pre-launch (their galleries are inside `<noscript>` and never render).
- **THIS WEEK strip hidden at launch.**
- **Rights transfer does not gate launch.**
- **Hosting PENDING.** Domains at **GoDaddy** (incl. the sister-city set); **Namecheap Stellar Plus cPanel alive**; shortlist **Cloudways / Kinsta**. Payload: 388 MB DB, 4.4 GB originals (12 GB regenerable thumbnails), 263k files — needs 20 GB+.
- **Entity wording "Vancouver Weekly" everywhere**, pending legal advice (Privacy + Terms currently say "Vancouver Weekly Corp.").
- **Jobs page = simple email-us** (currently a FreshGigs affiliate iframe).
- **Newsletter = re-consent** (19 legacy subscribers, signup dates lost).

**Known dirt, not blocking:**
- **~104 duplicate-title pairs** archive-wide (14 in Photography) — distinct post IDs, display-deduped on fronts, **pending editorial review**; two pairs have conflicting dates.
- **48 posts carry scraped comment chrome** in `post_content` (deks cleaned display-layer only).
- `must-see-films` front is inconsistent — `.html` template, so no depth cap or dedupe.
- Archive page layout is consistent but **undesigned**.
- Wrapper-div shells parked (cosmetic). `uncategorized` holds 1,637 posts; 391 empty spam categories.

**REMAINING LAUNCH MAP (in order):**
1. Architecture investigation
2. **Curation system — HARD GATE: the homepage does not cut over without admin curation** + template switcher
3. Settings panel + footer (light/dark)
4. Institutional pages
5. Discovery + card system (incl. missing-image variants)
6. Credits panel
7. Metadata / SEO round
8. Mobile round
9. ~~Rollouts (article header, masthead)~~ — **DONE 2026-09-12, `d417a70`**
10. Operator tutorial + walkthrough
11. Staging deploy + sweep
12. Cutover + 301s

**STANDING RULES FOR ALL NEW WORK: mobile-first is mandatory. NO new third-party plugins — custom child-theme code only.**

**Environment:** local MySQL socket run-ID changes when Local restarts — **use `tools/wp.sh`**, which re-detects it. Working tree clean.

---

## HARD CONSTRAINTS: URLs (NEVER CHANGE)

**Permalink structure is FROZEN at `/%postname%/`**
- Flat slugs, no date prefix, no category prefix
- Existing post slugs are preserved exactly as imported
- Never change `permalink_structure` in WordPress options
- Never change `category_base` (stays at default `/category/`)
- Rationale: ~2,800 archived posts have citable URLs; changing the structure breaks every external link and search index entry

**Category archive URLs are native and permanent**
- Section fronts live at `/category/{slug}/` — these are native WordPress core URLs
- NO redirect plugins sitting between a reader and any content URL
- NO "Page + redirect" pattern for section fronts (breaks if plugin deactivates)
- Section fronts render via `category.php` routing → `section-parts/{slug}.html` block templates

**Multisite: SUBDOMAIN or separate-domain ONLY**
- Never use subdirectory multisite (prefixes Vancouver's URLs permanently once set)
- Each city site has its own domain or subdomain from day one
- Changing network type after sites exist is URL-destructive

**Microsites: own network site, own domain, from day one**
- Never a subdirectory path on the main site for anything meant to last
- Any microsite can be promoted to permanent status without URL changes

---

## SECURITY: NEVER COMMIT

- `.env`
- `wp-config.php`
- `*.pem`, `*.key`
- `*.sql`, `*.zip`
- `source-material/` directory
- `db-backups/` directory
- GitHub tokens (rotate immediately if ever pasted into chat)

These contain credentials or PII (user emails, hashed passwords). The SQL dump is gitignored. iCloud backup path `~/Library/Mobile Documents/com~apple~CloudDocs/vw-rebuild-backups/` is private only.

---

## ARCHITECTURE DECISIONS (FROZEN)

### Editorial layer: Newspack-native
- Block tool: Newspack Homepage Posts block (`newspack-blocks` plugin — **installed**, free/open source, from GitHub not wordpress.org)
- NO page builders (Elementor, Beaver Builder, Bricks) — high lock-in, fragile on multisite
- NO bloated custom PHP card grids — Newspack blocks handle layout/query
- Custom PHP only as a thin routing layer (~25 lines `category.php`) delegating to `.html` block templates
- `archive-section.php` (old draft, never activated in template hierarchy) — **removed**

### Two-layer content model
- **Archive layer**: ~2,800 posts, permanent/stable. Post URLs are untouchable.
- **Editorial layer**: curated Newspack Homepage Posts modules surfacing archive + new content. Layouts can change freely; post URLs underneath stay fixed.

### Section fronts
- Real category archive URLs render curated layouts directly (Option B — no redirect)
- Implemented: minimal `category.php` (~25 lines) routes curated slugs → `section-parts/{slug}.html` via `do_blocks()`; all other categories fall through to Newspack default archive
- Phase 1 curated sections: `a-la-music` (umbrella, queries 6 music category IDs: 7,9,8,11,20,10), `out-n-about` (17), `must-see-films` (15)
- To add a section: add slug to `$curated` array in `category.php` AND create `section-parts/{slug}.html`
- Lead story: WordPress sticky post → auto-rises to lead slot. Fallback: most recent post with `_thumbnail_id`.
- **Photography section deferred (P3)**: real gallery content is 90% in Uncategorized, not the Photography category (30 posts). Next pass: P1-additive — ADD photography category to photographer-authored posts, never remove existing categories.

### Image quality tiers (automatic, never manual)
- PHP function reads `wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' )` → checks `$width`
- Must also detect broken/missing files (dead Facebook-sourced images) and NOT give them large treatment
- Tier 1 (≥1024px): full-bleed image on top of card
- Tier 3 (<480px): small image LEFT-ALIGNED beside content — no large container, no dead space
- Tier 0 (no image or broken): text-forward card with 3px red left bar

### Monetization (designed in, not yet built)
- Branded sponsorships as native sponsored module types
- Display ads in defined slots served DYNAMICALLY (never hard-coded into permanent posts)
- Voluntary supporter tier (ties to user accounts)
- Affiliate links with disclosure
- Newspack's native ad/sponsored tooling

---

## LOCAL DEV ENVIRONMENT

- WordPress local path: `/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public`
- Local URL: `http://vancouverweekly-local.local`
- DB: MySQL socket `/Users/ricardokhayatte/Library/Application Support/Local/run/HKOO9D7DI/mysql/mysqld.sock`, credentials root/root, dbname `local`, table prefix `wptg_`
- PHP: `/Users/ricardokhayatte/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php`
- PHP ini: `-c /tmp/wp-cli-php.ini`
- WP-CLI: `/tmp/wp-cli.phar`
- Full WP-CLI invocation: `"$PHP_BIN" -c /tmp/wp-cli-php.ini /tmp/wp-cli.phar --path="$SITE_PATH" [command]`
- mysqldump: `/Users/ricardokhayatte/Library/Application Support/Local/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysqldump`

### Child theme location
`/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-content/themes/vancouver-weekly/`

### Design tokens (frozen)
```
--bg:      #F7F6F4
--surface: #FFFFFF
--ink:     #1A161E
--muted:   #767676
--border:  #E8E8E8
--red:     #C41230   (single accent, no per-section colors)
--serif:   PT Serif Bold 700
--sans:    Inter 400/500/600
--radius:  4px (cards); 0 (article images — editorial, not app UI)
```

---

## PROJECT LOG & MASTER PLAN

- `PROJECT-LOG.md` — append an entry after every significant phase: what was built, decisions made, results
- `VW-MASTER-PLAN.md` — update after every phase: current status, what changed, what's next

---

## WORKING STYLE

- DRY RUN FIRST for any bulk database operation. Report. Stop for approval. Then write.
- Consolidate work into the fewest approval-gated invocations per stage: one read-only investigation script per question set, one committed runner invocation per batch. Never split what can be one script into many ad-hoc commands.
- Every approval prompt's description line states READ-ONLY or WRITES + what it writes to (e.g. "WRITES: 25 live post_content").
- Stop for user approval at every phase gate. Never chain phases without a stop.
- Keep responses concise. No trailing summaries ("Here's what I did...") — the diff speaks.
- No comments in code unless the WHY is non-obvious.
- No placeholder/TODO code in committed files.
- Verify completion end-to-end against rendered output or the DB before marking any task done or logging it complete. Never accept "done" on faith.
- SCOPE DISCIPLINE: Ricardo sets the project's goals; the reviewer serves them. Out-of-scope concerns (legal, rights, strategy beyond the asked task) get AT MOST one flag of 1-2 sentences with a concrete next step, clearly marked [FLAG - reply 'noted' to dismiss]. A dismissal is final for that topic unless material new facts arise. No concern may become a multi-message thread, block a task, or generate work Ricardo didn't request. When context is missing, ASK what Ricardo knows before arguing from assumptions - he has 20 years of primary knowledge of this publication.
