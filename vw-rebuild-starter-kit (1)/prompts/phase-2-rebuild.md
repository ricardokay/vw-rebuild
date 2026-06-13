# Phase 2: Rebuild on Newspack with a Pitchfork-inspired design

Paste this into Claude Code after approving the Phase 1 recovery report. The Local site `vancouverweekly-local` must be created first (see README).

---

Read CLAUDE.md and inventory/RECOVERY-REPORT.md. Goal: a clean local rebuild of the full site that I can browse and approve.

1. Connect to the Local site. Walk me through opening Local's site shell so you can run WP-CLI commands, and confirm you can reach the site before proceeding.

2. Install and activate the Newspack theme and the core Newspack plugin. Skip any Newspack setup steps that require external accounts for now.

3. Migrate content: import all posts from the inventory with original dates, slugs, authors, and categories preserved. Strip all Elementor markup, converting bodies to clean block editor content. Attach the recovered featured images and inline images from `recovered-media/`. Do a 20-post test import first, show me the results in the browser, and wait for my approval before the full run.

4. Rebuild the site structure: top navigation with the four main sections and their subcategories (A LA MUSIC with music interviews, album reviews, music editorials, music videos; PHOTOGRAPHY; FOOD & DRINK; OUT 'N' ABOUT), section front pages, and a homepage layout in the spirit of a modern music and culture magazine: one dominant lead story, a strong secondary tier, then section feeds.

5. Create a child theme called `vancouverweekly` in this repo and symlink or copy it into the Local site. Design direction, inspired by pitchfork.com but not copying it: near-black and white base with one strong accent color, large confident headline typography (suggest two or three open-license typeface pairings for me to pick from), full-bleed lead images, generous whitespace, a distinctive treatment for album reviews (reviewed artist, album, and a score or verdict block), and clean photography galleries for the PHOTOGRAPHY section. Mobile-first.

6. Performance and basics: image sizes and lazy loading handled by WordPress defaults, an XML sitemap, and redirects for any slugs that changed (there should be none, but verify against the inventory).

7. Show me the homepage, one section front, one album review, one photo gallery post, and one standard article. Write `PROGRESS.md` and stop for my review.

After I approve, I will ask you separately to prepare deployment to a staging subdomain on Namecheap.
