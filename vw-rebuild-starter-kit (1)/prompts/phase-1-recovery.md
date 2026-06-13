# Phase 1: Recovery and audit

Paste this into Claude Code once the source-material folder is populated.

---

Read CLAUDE.md first. This phase touches no live site. Goal: a complete, verified inventory of Vancouver Weekly's content across all sources, plus a recovered media library.

1. Initialize a Git repository in this folder if one does not exist, with a sensible .gitignore (exclude source-material zips, SQL dumps, and recovered media from Git; track scripts, docs, and theme code).

2. Inspect `source-material/`. Tell me what you find in the original backup before doing anything else: is it files, a database, or both, and what date range does it appear to cover?

3. Parse the current-site database export and the original backup. Build `inventory/content-inventory.csv` listing every post with: ID, title, slug, publish date, author, categories, featured image status (present, missing, wrong), and whether the post body contains Elementor markup.

4. Query the Wayback Machine CDX API for all captures of vancouverweekly.com. Build `inventory/wayback-coverage.csv` mapping post URLs to their best available capture dates. Identify posts and images that exist in archives but in no local source.

5. Recover media: for every post with a missing or downgraded image, locate the best version per the source-priority rule in CLAUDE.md and save it to `recovered-media/` organized by year/month matching the original upload structure. Be polite to archive.org (rate-limit requests).

6. Recover author data: build `inventory/authors.csv` with every contributor name, their post count, and any bio or photo found in archives or backups.

7. Write `inventory/RECOVERY-REPORT.md` summarizing: total posts, how many are fully intact, how many were repaired from which source, and anything unrecoverable. Show me 5 sample posts (one per category) comparing before and after so I can verify quality.

Stop after the report and wait for my review before Phase 2.
