# Vancouver Weekly — Post-Launch Register

Everything deliberately deferred past DNS cutover, in one place. Written 2026-09-18 at staging round 2b.

- Items are grouped by kind, not strict order.
- The standing rules still apply to every item: mobile-first, no new third-party plugins, dry run before bulk DB writes, gate per phase.
- **How changes ship after launch** (policy in PROJECT-LOG, 2026-09-18):
  - Content is edited live; WordPress drafts and preview are the safety layer.
  - Small display fixes go local-verify → deploy → purge.
  - Structural changes go to a Cloudways staging clone first.

---

## PRE-DNS DECISIONS

These must be settled before the domain moves.

- **Off-site backup verified.** Cloudways' backup of the migrated app must be full-size: about 18 GB of files plus a 453 MB DB. If it still looks like the ~38 MB fresh install, escalate to Cloudways support before DNS. (Round 2b Part 4.)
- **Production plugin set.** `active_plugins` lists 19 plugins; only newspack-blocks, newspack-plugin and vw-security are on disk. Decide which, if any, of the rest (Classic Editor, Elementor, LiteSpeed, Newsletter, …) exist on the launch host; that choice changes the editor the operator tutorial describes.
- **`admin_email` owner.** It points at the address held by `admin2` (user 245, author). Security mail goes there: registrations pending approval and the monthly audit. Point it at a mailbox Ricardo reads.
- **Launch-day flip list** (PROJECT-LOG, Round 1), in order:
  1. `blog_public` back to 1.
  2. Search-replace staging host → `https://vancouverweekly.com` (backup, dry run, `--all-tables`).
  3. SSL certificate for the real domain.
  4. Cloudways password protection off, if enabled.

  Then purge Varnish from the panel. The purge-on-publish hook sends `URLPURGE` with the site's own host, so it follows the search-replace with no change.
- **301 map** for legacy URLs (launch map step 12).
- **Staging sign-off** triggers the server clean-up under WEEK ONE.

---

## WEEK ONE

- **Plausible analytics.** Privacy-first. Added as a child-theme script tag, not a plugin.
- **User round:**
  - `admin2` (user 245, author) holds the address `admin_email` points at: decide its fate.
  - Full user audit (roles, dormant accounts, the photographer double accounts such as `Ryan Johnson` + `ryan.johnson`).
  - ID 1 `admin`: demote or clean up. It is already refused at login because it has no 2FA, by decision 2026-09-18, and it authors 1,914 posts, so reassign or keep it as a desk byline.
  - **Rename user 200's `user_login`** (the mangled multi-name string; Ricardo's daily account).
    - Propose the safe path at that time: a direct `user_login` update + clearing that user's cache, then a fresh login.
    - It logs that user out everywhere, because auth cookies carry the login.
    - 2FA is keyed by user ID, so it survives the rename; only the authenticator app's label goes stale.
    - Ricardo logs in by email, so his daily routine is unaffected.
- **`/archive/` rewrite.** The current route works but is undesigned; rebuild it properly.
- **Ghost-plugin + stub-table cleanup:**
  - The 16 ghost `active_plugins` entries.
  - The 12 stray `wp_*` stub tables and Cloudways' 4 `wp_actionscheduler_*` tables.
  - Inactive plugin directories (akismet, hello, breeze).
  - **Keep `plugins/object-cache-pro/`.** The Redis drop-in loads its `api.php` even though the plugin is inactive.
  - Each step: backup + dry run first.
- **Delete server dumps at staging sign-off** (all outside the web root, all holding PII or credentials):
  - `/home/master/vw-migration/` (239 MB; raw DB dump with user emails and password hashes).
  - `/home/master/vw-round2a/`, including the moved `wp-config.php.bak-20260918` with DB and Redis credentials.
  - `/home/master/vw-round2b/`, including `wp-config.php.before`, the Breeze drop-in and cache, `readme.html`/`license.txt` and the theme previews.
  - The stray `/home/master/*.sh` scratch scripts.
  - Local copies stay in `backups-local/` + iCloud.
- **SEO pass, early.**
  - Sitemaps return once `blog_public=1`; the users sitemap is disabled on purpose.
  - Titles/descriptions, canonical URLs, Search Console, the 301 map check.
  - Section term names are lowercase for four sections (screen-reader h1 + document title).
- **Weekly off-Cloudways DB dump cadence.** A dump to local + iCloud every week, independent of Cloudways' own backups.
- **Scale the server on evidence.** It is a 2 GB DigitalOcean box. Watch memory, PHP workers and Varnish hit rate after launch before resizing.
- **Operator tutorial + dashboard widgets.**
  - Publishing round Phase 2 (`PUBLISHING.md`), written only when Ricardo calls for it; Phase 1 findings 1–9 in PROJECT-LOG are its input.
  - Dashboard widgets for the operator.

---

## CONTENT-ARCHIVE

- **FB gallery migration chain** (assessment: `fb-gallery-migration-assessment.md`):
  1. Gap-check the 2026-09-17 Meta export.
  2. Matching.
  3. Import.
  4. **Un-retire the 261 B1 posts:** flip every post with `_vw_retired_jig_b1 = 1` back to publish.
  5. Adjudicate the 19 *plausible* matches (title match, date 46–400 days) by hand; do not automate that bucket.
  6. The 163 orphan albums (4,226 photos, 627 MB): an editorial decision, not an import.
  7. Fallbacks if the export has gaps: Business Suite, then the Graph API.
- **`recovery-inventory.csv` review.** 1,764 posts with suppressed dead images; about 1,200 photo credits return automatically when their image is recovered.
- **Ryan caption review.**
- **Wayback reversal lead.**
- **16 duplicate pairs.** Editorial review; this sits alongside the ~104 archive-wide duplicate-title pairs in CLAUDE.md known dirt, and the quote-variant dedupe fix on fronts.
- **Categorization pass: 1,797 no-nav posts** (posts not reachable from any nav section; `uncategorized` alone holds 1,637). Photography is P1-additive: add the category, never remove existing ones.
- **Known-dirt editorial pass:**
  - 48 posts with scraped comment chrome in `post_content`.
  - 391 empty spam categories.
  - Two duplicate pairs with conflicting dates.
- **Institutional pages.** Launch map step 4. Includes resolving the duplicate Terms (IDs 52 / 1951) and Privacy (IDs 56 / 1950) pages, Jobs as a simple email-us page, and the Contributor Kit (DB page 68 is empty; the only copy is in `source-material/contributor-kit/`).
- **Spam-comment cleanup (optional).** 5,598 pending comments, all hidden since comments closed in 2a; backup + dry-run count first.
- **Interview archiving.** About 1,000 recordings over 20+ years: audit, naming, database, redundant backup, batch transcription.

---

## PRODUCT-DESIGN

- **Bylines / credits round** (credits panel, launch map step 6).
- **Gallery workflow** for new photo stories.
- **End-of-post discovery** (launch map step 5).
- **Imageless-card structure** (tier-0 cards, missing-image variants).
- **Typography pass.** Also clean up the known leftovers: the second hairline under the article credit strip, and the oversized author-bio name.
- **Photography / film section refinement + accent reconciliation.** Film section mark deferred; Must See Films has only 8 of 169 posts with a usable image.
- **Dark mode.**
- **Author pride pages.**
- **Banner slot** (sponsorship/ad slot, served dynamically, never hard-coded).
- **Template-switcher variants.**
- **Drawer v2.** Blocked on the social-account inventory.
- **Events calendar decision** (207 event listings were retired 2026-07-25).
- **CATEGORY / LIST PAGES REDESIGN.**
  - Subcategory archives + pagination (page 2+ is the plain Newspack archive today).
  - A Photography dense-thumbnail switcher variant, **sequenced after the FB migration**.
- **THIS WEEK strip.** Hidden at launch; revisit.
- **Light footer variant.** Not built.

---

## BUSINESS-LEGAL

- **Trademark transfer + entity wording, after legal advice.** The site says "Vancouver Weekly" everywhere since 2a; revisit once counsel answers.
- **Photographer license document.** Also governs 85536 (getty-rights-hold), the only held draft.
- **Newsletter provider + re-consent.** 19 legacy subscribers with signup dates lost; re-consent, never import silently.
- **Namecheap cancellation** (Stellar Plus cPanel), only after DNS is stable and a final off-host backup exists.
- **AAN.** Rejoin AAN Publishers once live; then set up newswire monitoring.
- **Copycat monitoring.**
- **Slack.**
- **Portfolio case study + site.**
- **Cloudways promo check.**
