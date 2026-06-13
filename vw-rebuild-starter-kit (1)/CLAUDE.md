# CLAUDE.md: Vancouver Weekly Rebuild

## Project owner

Ricardo. Design background, beginner developer, beginner with AI tooling. Explain what you are doing in plain language, propose before executing anything destructive, and never assume he knows WordPress internals, Git, or the command line. When a step needs his input (passwords, cPanel actions, judgment calls on design), stop and ask with exact instructions.

## What this project is

vancouverweekly.com is a Vancouver arts and culture newspaper with hundreds of past contributors. A third-party agency rebuilt it on free Elementor (currently Elementor 4.1.3 on the live site) and the result is poor: broken page formatting, missing post images (many were dumped into a single 2024/06 uploads folder), degraded navigation and sidebars, and author setup that lost the original structure.

The goal is to restore the full archive to its original quality and rebuild the site as a modern magazine. The visual and structural inspiration is pitchfork.com: bold typography, large imagery, strong section fronts, review-style article treatments. Sections include A LA MUSIC (interviews, album reviews, editorials, music videos), PHOTOGRAPHY, FOOD & DRINK, and OUT 'N' ABOUT.

## Source material

All in `source-material/` (Ricardo downloads these per the README):

- `current-site.sql`: database export of the live (post-rebuild) site
- `wp-content.zip`: full wp-content of the live site
- `original-backup/`: Ricardo's backup from before the agency rebuild (highest quality source)
- Wayback Machine: enumerate all captures with the CDX API, for example
  `https://web.archive.org/cdx/search/cdx?url=vancouverweekly.com*&output=json&collapse=urlkey`
  A known good homepage capture: https://web.archive.org/web/20210813180642/https://vancouverweekly.com/
  The open source `wayback-machine-downloader` tool (Ruby gem) can bulk-download captures.

## Technical decisions (already made, do not relitigate without asking)

- Platform: WordPress, self-hosted on Namecheap shared hosting (cPanel). No Elementor in the rebuild. No paid page builders.
- Local development: Local (localwp.com) site named `vancouverweekly-local` on Ricardo's Mac. Use WP-CLI inside Local's site shell for imports and configuration.
- Base: Newspack plugin suite and Newspack theme (free, by Automattic, built for newsrooms, native block editor).
- Customization: a child theme of Newspack carrying the Pitchfork-inspired design. All custom code lives in the child theme or a small custom plugin, both tracked in this Git repo.
- Editorial: native WordPress roles plus PublishPress (free version) for statuses, editorial comments, and notifications.
- Campaigns: a `campaign` custom post type with its own landing templates, living under paths like /worldcup. Built once as a system, reused per campaign.
- APIs for campaigns must be free or open: football-data.org (free tier, fixtures and results), Open-Meteo (weather, no key), City of Vancouver Open Data (opendata.vancouver.ca). API keys go in environment config, never committed to Git.

## Hard rules

1. Never modify the live site or its database. Live cPanel access is read-only for downloading backups until Ricardo explicitly approves deployment.
2. Commit to Git frequently with plain-language messages.
3. Before any bulk content operation (import, regeneration, deletion), run it on a copy and show Ricardo a sample of results first.
4. Preserve original publication dates, author attributions, categories, and URL slugs. Old URLs must keep working (add redirects for anything that must change).
5. Strip Elementor shortcodes and markup from post content during migration; output clean block editor or plain HTML content.
6. When a post's image exists in multiple sources, prefer original-backup, then Wayback, then the current site, choosing the highest resolution.

## Working style

Work in the numbered phases in `prompts/`. End every working session by updating `PROGRESS.md` with what was done, what is verified, and what is next, so any future session can pick up cleanly.
