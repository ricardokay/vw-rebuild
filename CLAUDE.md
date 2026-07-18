# Vancouver Weekly Rebuild — Project Rules

This file governs all Claude Code sessions on this project. Rules here override defaults.

---

## CURRENT STATE *(overwrite this section each session — do not append)*

**Done:**
- Phase 1: 2,789 posts imported (Wayback recovery)
- Phase 2: 3,585 images imported + thumbnail regen; featured-image back-fill (Option C) applied
- Phase 3: child theme + all 4 section fronts live (A La Music, Photography, Food & Drink, Out N About) — chrome (vw-nav header + Newspack) confirmed native on all templates via live HTTP check
- Spam cleanup: 297 posts trashed; vw-security plugin active; XML-RPC disabled
- Photographer account cleanup: duplicate accounts consolidated, display names corrected (2026-06-18)
- Album classification COMPLETE (`fb-album-inventory.csv`, 563 albums): REPAIR 374 / ADD 15 / NEEDS_REVIEW 174
- **ARCHIVE PUBLISHED (2026-07-18): 358 repaired galleries live (~10,857 images)** — copy-in-place onto the real published posts (frozen slugs/dates/authors verified per post), attachments reparented, drafts retired at status draft + `_vw_retired_after_publish=1` (never trash). Per-post verify gate: HTTP 200, gallery block present, gallery-scoped figcaption match, no dead-JIG markers, featured file on disk. Published count 3,580 unchanged throughout. Runner: `publish_batch.php`. Halt-safe verify caught 1 dirty draft mid-run (75518→65856) — fully reversed from manifest (sha256-verified).
- **6 drafts held from publish:** 5 dirty-body-jig (75518, 80010, 83913, 84851, 84917 — leftover JIG wrapper precedes gallery; 2026-07-08 body-cleanup was byline/chrome-scope only and missed these) + 1 getty-rights-hold (85536, never publish without license). Flags: `_vw_publish_exclude` meta + `db-backups/publish-exclusions.json`.
- **Rollback assets valid:** pre-publish full dump (`db-backups/vancouverweekly_local_2026-07-18_pre-publish.sql`, MD5-verified) + reversal manifest (`vw_publish_reversal_2026-07-18.json`, per-post old content/thumbnail/attachment-parents) + progress file — all in `db-backups/` AND iCloud `vw-rebuild-backups/`.
- Broken-gallery universe (pre-publish analysis): ~687 posts carried dead-gallery markers; ~497 caption-only/thin still need FB album import, not a display fallback. Fallback design preview: `fallback-preview.html`.

**In progress:** gallery quality backlog — remaining NEEDS_REVIEW albums + caption-only/thin posts (see inventory CSV)

**Next:**
1. JIG-strip pass on the 5 dirty-body-jig drafts → re-verify → mini-batch publish
2. Rebuild homepage (page 9) Newspack-native via Claude Design → port to child theme
3. Build local→production deploy tooling (greenfield)

**LAUNCH BLOCKERS (real):**
1. **Homepage** — page 9 is still the old Elementor build (the failed agency restoration this project replaces). Rebuild Newspack-native, switch its page template off `elementor_header_footer`, handle 2 Elementor articles (65340, 65350), then deactivate Elementor Pro / ElementsKit / Essential Addons. **Extraction is homepage-only** — live-render check proved chrome is already native (vw-nav + Newspack); Elementor theme-builder header/footer + ElementsKit mega-menu are DORMANT (do not render). Launching on Elementor is rejected.
2. **Deploy tooling** — greenfield. No CI / Dockerfile / deploy scripts / prod `wp-config` / Namecheap-DNS config in repo (only doc mentions). Full local→production go-live path unbuilt.

Gallery repair/import = quality backlog, NOT a launch gate.

**Blocked:** Nothing hard-blocking dev work. Local MySQL socket this session: run-ID `HKOO9D7DI` (`.../Local/run/HKOO9D7DI/mysql/mysqld.sock`) — the run-ID can change when Local restarts; re-detect the live socket each session before DB work. WP-CLI phar/ini in `/tmp` may be wiped between sessions (rebuild via curl, or use the Local `mysql` client directly against the socket — proven simpler for DB work). Working tree clean, local == origin/main.

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
- Stop for user approval at every phase gate. Never chain phases without a stop.
- Keep responses concise. No trailing summaries ("Here's what I did...") — the diff speaks.
- No comments in code unless the WHY is non-obvious.
- No placeholder/TODO code in committed files.
- Verify completion end-to-end against rendered output or the DB before marking any task done or logging it complete. Never accept "done" on faith.
