# Vancouver Weekly Rebuild: Starter Kit

This folder is the project workspace for rebuilding vancouverweekly.com with Claude Code. You do a short list of setup tasks once. After that, Claude Code does the development work and you review results in a browser.

## What you do (one-time setup, roughly 1 to 2 hours)

### 1. Download everything from Namecheap cPanel

You want three things saved into a folder called `source-material` inside this project:

1. **Database export.** In cPanel, open phpMyAdmin, select the WordPress database, click Export, choose Quick, format SQL, and download. Save as `source-material/current-site.sql`.
2. **The wp-content folder.** In cPanel File Manager, navigate to your WordPress root, right-click `wp-content`, choose Compress (zip), then download the zip. This contains all uploads, themes, and plugins. Save as `source-material/wp-content.zip`.
3. **Your pre-rebuild backup.** Whatever backup you have of the site before the third party touched it (files, database, or both). Save it in `source-material/original-backup/`. This is the most valuable asset in the project because it has the real post formatting and image references.

### 2. Install Local (free local WordPress app)

Download Local from https://localwp.com and install it. Create a new blank site called `vancouverweekly-local`. Write down the admin username and password it gives you (store them in LastPass). This gives you a full WordPress running on your Mac that Claude Code can work on safely. The live site stays untouched until you decide to deploy.

### 3. Open Claude Code in this folder

In Terminal:

```
cd path/to/vw-rebuild
claude
```

Claude Code will read the CLAUDE.md file in this folder automatically and understand the whole project. Then paste in the Phase 1 prompt from `prompts/phase-1-recovery.md` and let it work. Go phase by phase, in order.

## The phases

1. **Recovery and audit** (`prompts/phase-1-recovery.md`): inventory all content from the database, the original backup, and the Wayback Machine. Recover missing images and posts. No changes to any live site.
2. **Rebuild** (`prompts/phase-2-rebuild.md`): set up Newspack on the local site, import the recovered content cleanly, strip Elementor markup, and build a Pitchfork-inspired child theme.
3. **Editorial workflow** (`prompts/phase-3-editorial.md`): contributor, author, and editor roles, submission and approval flow, author pages restored.
4. **Campaign microsites** (`prompts/phase-4-campaigns.md`): a reusable campaign system, with the World Cup Vancouver section as the first one, wired to free APIs.

## Deploying to Namecheap (after Phase 2 or 3)

When the local site looks right, ask Claude Code to prepare deployment. The simple path on Namecheap shared hosting is a migration plugin (WPvivid is free with no practical size limit) or a manual move via cPanel. Deploy first to a subdomain like `staging.vancouverweekly.com` so you can review on real hosting before replacing the live site.

## Ground rules that keep this safe

- The live site is read-only until the final deployment step. All work happens locally.
- Every phase ends with something you can look at in a browser and approve before moving on.
- Keep this folder in a Git repository so nothing is ever lost. Claude Code can set that up for you in Phase 1.
