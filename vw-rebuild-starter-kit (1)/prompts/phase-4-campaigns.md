# Phase 4: Campaign microsites (World Cup Vancouver first)

Paste this into Claude Code after Phase 3 is approved.

---

Read CLAUDE.md. Goal: a reusable campaign system so that launching a themed microsite is a short conversation, not a build. The first campaign is the FIFA World Cup in Vancouver.

1. Build a small custom plugin in this repo (`vw-campaigns`) registering a `campaign` post type and a `campaign_section` taxonomy. Each campaign gets a clean URL like vancouverweekly.com/worldcup and its own landing template in the child theme, with a distinct but on-brand visual identity (accent color, header treatment) configurable per campaign.

2. The campaign landing template should support modular blocks: hero, live data widgets, curated article feeds pulled by tag, an events list, and an editor's guide section. Editors manage campaign content with the normal block editor, no code.

3. API integrations, all free, keys kept out of Git:
   - football-data.org (free tier): World Cup fixtures, results, and group standings, cached server-side so we respect rate limits.
   - Open-Meteo (no key): Vancouver weather for matchday widgets.
   - City of Vancouver Open Data (opendata.vancouver.ca): relevant event and venue data where useful.
   Build each as a small, separate module so future campaigns can mix and match.

4. Create the World Cup campaign itself: landing page at /worldcup with matches at BC Place, a fixtures and results widget, weather, and feeds for tagged articles (things to do, food and drink near the stadium, fan guides). Seed it with three draft article stubs our writers can take over.

5. Document the repeatable process in `docs/launching-a-campaign.md`: the exact prompt template I paste into Claude Code to spin up a new campaign (name, URL, accent color, tags, which API modules to enable), plus what editors do afterward in WordPress.

6. Show me /worldcup in the browser, update `PROGRESS.md`, and stop for review.
