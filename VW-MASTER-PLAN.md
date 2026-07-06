# Vancouver Weekly: Master Project Plan
Last updated: June 17, 2026

---

## Project overview

Rebuild vancouverweekly.com from a broken Elementor-based site into a modern, personalized, revenue-generating local media network. Visual and editorial inspiration: Pitchfork. Functional inspiration: a hyper-local, AI-enhanced, community-driven magazine.

---

## Technical foundation

**Current state**
- Live site: vancouverweekly.com, hosted on Namecheap shared hosting (cPanel)
- Local dev: vancouverweekly-local.local via Local (localwp.com)
- Project folder: ~/Claude-code/vw-rebuild
- Recovery folder: ~/Claude-code/vw-rebuild/vw-rebuild-starter-kit (1)/recovery
- Database: vancxabk_wp868 on Namecheap
- Theme: Newspack (active on local), replacing Elementor entirely

**Content recovery (completed June 12-13, 2026)**
- 5,881 post URLs identified from Wayback Machine CDX API
- 5,838 successfully recovered (99.3%)
- 2,896 spam posts quarantined (ivermectin/pharma SEO spam, WordPress query artifacts)
- 2,981 clean posts approved for import
- Import running as of June 13, 2026 (~100 min runtime)
- Security audit completed, audit_report.md in recovery folder

**Architecture decisions**
- Platform: WordPress, self-hosted on Namecheap
- No Elementor, no paid page builders
- Base: Newspack theme and plugin (free, by Automattic)
- Child theme: vancouverweekly (custom, Pitchfork-inspired)
- Build for WordPress Multisite from the start (network expansion)
- Editorial: PublishPress free version for workflow
- Analytics: Plausible Analytics (PIPEDA compliant, privacy-friendly)
- Social automation: Anthropic API + Buffer free tier

---

## Site sections

- A LA MUSIC (music interviews, album reviews, music editorials, music videos)
- PHOTOGRAPHY (self-hosted masonry gallery, no Facebook plugin)
- FOOD & DRINK
- OUT N ABOUT

---

## Design direction

**Primary inspiration:** pitchfork.com
- Near-black and white base with one strong accent color
- Large confident headline typography
- Full-bleed lead images
- Generous whitespace
- Distinctive album review treatment (artist, album, score/verdict block)
- Clean photography galleries

**Additional references to incorporate:**
- To be added by Ricardo before Phase 2

**Mobile:** Mobile-first design. Phone experience designed first, desktop scales up. Bottom navigation bar, swipe-friendly article navigation, fast load on slow connections.

**Progressive Web App:** Users can add Vancouver Weekly to home screen, receive push notifications, offline reading of cached articles.

---

## User system and personalization

**Profile features**
- Sign up with email, Google, or Apple (one-tap mobile)
- Taste profile: select artists, bands, venues, art forms, neighbourhoods
- Saved articles and collections
- Reading history used to surface archive content
- Notification preferences (weekly digest, topic alerts, campaign alerts)
- Supporter badge for paying members
- No login required to browse; prompt after 3-4 articles read

**Personalization**
- Homepage shaped by taste profile
- Archive surfaced by interest (nostalgia feeds)
- Weekly curated experience packages matched to interests
- Content recommendations based on reading history

**Security**
- Two-factor authentication: mandatory for editors/admins, optional for users
- Authenticator app (Google Authenticator, Authy) preferred, not SMS
- Social login via Google and Apple OAuth
- Password strength enforcement
- Rate limiting on login attempts
- Automatic session expiration
- Breach detection alerts
- All passwords hashed, personal data encrypted at rest
- PIPEDA compliance (Canadian privacy law)
- Data export and deletion tool for users

---

## Site security (non-negotiable, baked in from launch)

- Cloudflare free tier (WAF + global CDN speed improvement)
- XML-RPC disabled
- Default admin URL changed to custom path
- Login attempt rate limiting
- Pharmaceutical/spam keyword blocklist on all new content
- File integrity monitoring
- Activity logging for all admin actions
- Weekly automated security scan report emailed to Ricardo
- Uptime monitoring with phone alerts
- Incident response runbook document

**Backup system (three layers)**
1. Namecheap JetBackup (daily, on-server)
2. Google Drive off-site backup (daily, automated)
3. 30-day backup history retained
4. Monthly automated restore test

---

## Campaign microsites

Reusable system for branded campaign sections at vancouverweekly.com/[campaign].

**First campaign: World Cup Vancouver 2026**
- Landing at /worldcup
- Free APIs: football-data.org (fixtures/results), Open-Meteo (weather), City of Vancouver Open Data (venues/events)
- Hero, live data widgets, curated article feeds by tag, events list, editors guide
- Managed via block editor, no code required

**Future campaigns (same template):**
- Whistler ski season
- Vancouver Folk Festival
- Pride Week
- Fringe Theatre
- Dine Out Vancouver
- VIFF

---

## Weekly curated experiences

New content format: editorial package built around a weekly theme.
- Live music this weekend
- Best first dates in East Van
- New art openings
- Visitor guide
- AI-assisted drafting from archive + current event data, editor refines and publishes
- Matched to user taste profiles for personalized delivery
- Companion social series auto-generated

---

## AI features

**AI-powered search**
Natural language search bar powered by Anthropic API querying post database semantically. Example queries: "everything we wrote about the Commodore Ballroom 2005 to 2015", "jazz show reviews in Vancouver."

**SEO and metadata generation**
- Unique meta title and description per post generated via Anthropic API
- Alt text generated for all images based on post context
- Schema.org structured data for articles, reviews, author pages, organization
- robots.txt allowing all major search engines and AI crawlers (GPTBot, ClaudeBot, Google-Extended)

**Social automation**
- On publish: auto-generate platform-optimized posts for Instagram, X, Facebook, Threads
- Editor approve/auto mode per content type
- Weekly experience packages generate matching social series automatically

---

## Social media

### Confirmed accounts (existing)

| Platform | Account | Status |
|---|---|---|
| Facebook | Vancouver Weekly | Existing |
| Instagram | Vancouver Weekly | Existing |
| Twitter / X | Vancouver Weekly | Existing |
| LinkedIn | Vancouver Weekly | Existing |
| YouTube | Vancouver Weekly | Existing |

### Accounts to set up

| Platform | Notes |
|---|---|
| TikTok | Short-form video from photo galleries, event coverage, interview clips |
| Threads | Mirrors Instagram account, connects to Meta ecosystem |
| Spotify podcast feed | For the audio interview series once the pipeline is built (Phase 6+) |

### Security audit — do before connecting any account to automation

The site was hacked during the period when pharma spam was injected. All social accounts active during that window may have been accessed.

**Before connecting any account to Buffer or any automation:**

1. Log in to every existing account (Facebook, Instagram, X, LinkedIn, YouTube).
2. Check connected apps and revoke anything unrecognized.
3. Review recent post history for unauthorized activity.
4. Change the password for every account.
5. Store new passwords in LastPass.
6. Enable 2FA on every account — authenticator app preferred, not SMS.
7. Verify the recovery email on each account is the correct one and is also secured.

Do not connect Buffer or any automation until all five accounts have been audited, passwords changed, and 2FA confirmed.

### Reddit strategy — human only, not automated

Reddit requires genuine participation. Automation is not appropriate and will get the account banned.

Target communities:
- **r/vancouver** — share relevant local coverage, event previews, photography
- **r/indieheads** — share music reviews and interviews when the content fits the community's taste
- **r/CanadianMusic** — share Canadian artist coverage, Vancouver scene stories

Rules for participation:
- Never post only Vancouver Weekly links. Participate in discussions, comment, upvote.
- Follow each subreddit's self-promotion rules (most allow one promotional post per week at most).
- If an article is genuinely useful to a thread, share it in context — not as a standalone post.
- One person manages Reddit, not a rotation. Consistency and recognizable voice matter.
- No posting during the first 30 days on a new account — build karma through genuine participation first.

### Automation scope (what Buffer handles)

Buffer manages scheduled posts for: Facebook, Instagram, X, Threads.
LinkedIn posts are reviewed and posted manually — tone differs from other platforms.
YouTube uploads are manual.
Reddit is human-only (see above).
TikTok automation evaluated once the account is established and content cadence is understood.

---

## Automation vision

**Goal: one editor managing day-to-day operations in 10–15 hours per week by year two.**

All automation is editor-supervised, not fully autonomous. Every pipeline step produces a draft or suggestion; a human approves before anything goes public.

---

### On-publish content pipeline

Triggered automatically the moment an editor hits Publish:

1. **SEO metadata** — Anthropic API generates a unique meta title, meta description, and Open Graph summary for the post. Saved as post meta; editor can override before or after publish.
2. **Image alt text** — Alt text generated for the featured image and any inline images that lack it, using post title and content as context.
3. **Social posts** — Platform-optimized drafts created for Instagram, X, Facebook, and Threads. Sent to a Buffer queue in draft state; editor approves or edits before posts go live. Option per content type: auto-approve or always require review.
4. **Sitemap ping** — Google Search Console and Bing Webmaster Tools notified via sitemap ping on each publish.
5. **Affiliate link scan** — Post content scanned against an affiliate link map (restaurants, venues, ticket sellers, hotels). Relevant affiliate URLs inserted or flagged for editor approval, disclosed with a standard "contains affiliate links" label.
6. **Newsletter digest queue** — Post added to the weekly newsletter digest queue, tagged by section and interest tags, ready for the weekly send.

---

### Weekly curated experience packages

New content format: an AI-drafted editorial package built around a weekly theme (live music this weekend, best first dates in East Van, new art openings, visitor guide, etc.).

**Workflow:**
1. Monday morning: script pulls upcoming events from City of Vancouver Open Data, venue APIs, and existing VW archive content tagged to matching themes.
2. Anthropic API drafts a complete package: intro copy, article selections with pull quotes, event listings, photo picks.
3. Draft lands in WordPress as a private post assigned to the editor for review.
4. Editor refines, selects final images, approves.
5. On publish: full on-publish pipeline runs, plus a companion social series is auto-generated (one post per day for the week, scheduled in Buffer).

**Distribution:** Package is matched to reader interest tags. Subscribers with matching taste profiles receive it via email; others receive the general weekly digest.

---

### Automated distribution by reader interest

- Every post is tagged with section, topic tags, and named entities (artists, venues, neighbourhoods).
- On publish, the system checks subscriber interest profiles against post tags.
- Topic alert emails sent to matching subscribers (batched, not one email per post — maximum one alert email per reader per day).
- Weekly digest assembled automatically each Sunday night from the week's published posts, grouped by section, personalized by reader profile. Editor reviews and sends Monday morning.

---

### Weekly analytics email

Every Monday morning, an automated email to Ricardo covering the previous week:

- Top 10 posts by pageviews and by time-on-page
- Section breakdown (which sections drove the most traffic)
- New vs. returning readers
- Top referral sources
- Social performance summary (reach, clicks per platform)
- Email open rate and click rate
- Affiliate link clicks and estimated revenue
- Any posts with unusually high or low performance flagged for review

Source: Plausible Analytics API + Buffer API. Delivered via email, no login required to read it.

---

### AAN newswire monitoring

**Note: Rejoin AAN Publishers (aan.org) once the site is live.**

Once rejoined, an automated monitor checks the AAN newswire daily for stories relevant to Vancouver:

- Keywords and topics: Vancouver, BC arts scene, Canadian music, local food, festival announcements, arts funding, local venues.
- Matching stories surfaced in a daily digest in the WordPress dashboard (not emailed — editor checks it as part of morning workflow).
- One-click option to create a new post citing the wire story with a summary draft.
- Cross-post suggestions: if a wire story is relevant to another network site (e.g. a Whistler festival story for Whistler Weekly), flagged automatically.

---

### Cross-network posting suggestions

When a story is published on one network site that is relevant to another city's audience, the system flags it:

- Editor on the originating site sees a notice: "This may be relevant to Whistler Weekly — suggest cross-post?"
- One click creates a draft on the target site with the original post linked and a short localization prompt for the editor there.
- No automatic cross-posting — always editor-approved.
- Shared content pool: evergreen archive pieces (restaurant guides, venue histories, event previews) flagged as eligible for adaptation across network sites.

---

### Automation implementation notes

- All Anthropic API calls use cached system prompts to minimize token cost.
- Social automation runs via Buffer free tier (3 channels); upgrade to Buffer Essentials if network expands beyond 3 sites.
- Affiliate link map maintained as a simple JSON file in the child theme; editor can add/remove entries without code.
- All automated drafts are clearly labelled "AI Draft — Needs Review" in the WordPress dashboard.
- Automation failures (API timeout, missing data) log to a dashboard widget and email Ricardo; they never silently skip.

---

## Connected Article Format

**A signature editorial product that differentiates Vancouver Weekly from every other local outlet. Phase 6 or later — after core site and automation are stable.**

---

### Contextual entity cards

When an interview or feature article is published, an AI pass scans the content for named entities:

- Albums and songs
- Artists, bands, and musicians
- Venues
- Events and festivals
- People (other than the subject — producers, collaborators, referenced figures)

For each recognized entity, the system queries the Vancouver Weekly archive for related content. If relevant matches exist, a contextual card is assembled and attached to the entity mention in the article body.

**Card behaviour:**
- Inline: tapping or hovering an entity mention expands a small card without leaving the page.
- Side panel: on wider screens, related cards surface in a right-side reading panel alongside the article.
- Cards include: article thumbnail, headline, publication date, one-sentence excerpt.
- Maximum three archive links per card to avoid overwhelming the reader.
- Cards only appear when the archive match quality is high — no low-confidence suggestions surfaced to readers.

**Editor control:**
- After AI pass, editor sees a draft of all proposed entity cards before they go live.
- Can accept, reject, or manually add cards to any entity mention.
- Can mark an article as exempt from entity scanning (e.g. opinion pieces where contextual cards would be distracting).

---

### Audio interview pipeline

Vancouver Weekly has conducted hundreds of interviews over the years. Ricardo holds recordings of past interviews. This pipeline processes both the archive and future recordings.

**Workflow for each recording:**

1. **Transcription** — Raw audio file processed locally via open source Whisper (runs on Mac, no data sent to external services, no per-minute cost). Output: full timestamped transcript.
2. **Moment flagging** — Anthropic API reads the transcript and flags the most quotable moments: strong opinions, surprising reveals, vivid descriptions, memorable turns of phrase. Each flag includes the timestamp, the quote, and a one-line reason it was selected.
3. **Editor review** — Editor sees the full transcript with flagged moments highlighted. Selects which clips to use, adjusts start/end timestamps if needed, writes or approves the intro framing for each clip.
4. **Clip extraction** — Approved clips extracted from the source audio as short MP3 segments (FFmpeg, local, no upload required).
5. **Embed** — Clips embedded as playable inline audio within the written piece, positioned at the relevant point in the article. Custom lightweight audio player styled to match the site — no SoundCloud, no Spotify embed dependency.

**Archive processing:**
- Ricardo's existing recordings processed in batches, oldest interviews first.
- Transcripts stored alongside the original post JSON in a dedicated archive folder.
- Posts updated with audio embeds as clips are approved — becomes a rolling enhancement of the existing archive.

**Future interviews:**
- Standard workflow from point of recording: drop file in a watched folder, pipeline runs automatically, transcript and flagged moments appear in the WordPress dashboard within minutes.

---

### Why this matters

Most local outlets publish text and images. A small number embed a full podcast episode. No local outlet connects the written piece to its own archive through the content itself, or gives readers playable moments from the interview alongside the prose.

The combination — entity cards surfacing the archive, audio clips embedded at the right moment in the text — creates a reading experience that rewards depth and keeps readers inside Vancouver Weekly longer. It also gives the archive compounding value: every new article links backward, every archive article gains new relevance as new content references it.

This is the format that makes Vancouver Weekly a destination, not a feed.

---

## Interview Archive Project

**A dedicated session after the core site is stable. Not part of the Phase 1–5 build.**

Ricardo holds approximately 1,000 past interview recordings spanning 20+ years of Vancouver arts and culture coverage, stored across external hard drives and Google Drive.

---

### Immediate action (before the dedicated session)

**Priority: no recording should exist on a single external hard drive only.**

- Any drive older than five years: copy its contents to Google Drive as an immediate stopgap, before the drive fails.
- This does not require the full audit — just copy everything, unsorted, to a dated Google Drive folder as insurance.
- Do this before scheduling the dedicated archive session.

---

### Dedicated archive session scope

**1. Audit and inventory**
- Locate all recordings across all drives and cloud storage.
- Note format (MP3, WAV, M4A, voice memo, etc.), approximate duration, condition, and source drive.
- Flag anything that appears corrupted or incomplete.

**2. File naming standardization**
Standard format for every file: `YYYY-MM-DD_ArtistOrSubject_Interviewer.ext`
Example: `2008-03-14_NineInchNails_RicardoKhayatte.mp3`
- Consistent naming enables automated processing downstream.
- Original filenames preserved in the inventory database before renaming.

**3. Searchable database**
Build an inventory in Airtable (or equivalent) with fields:
- File name (standardized)
- Subject / artist
- Interviewer
- Date (recorded)
- Publication (if tied to a specific VW article)
- Duration
- Format
- Storage location(s)
- Transcription status (not started / in progress / complete)
- Notes (condition, gaps, quality issues)
- Rights / licensing flags

**4. Redundant backup**
Minimum two locations for every recording after the session:
- Google Drive (primary cloud)
- Second location: local NAS, second cloud provider (Backblaze B2), or a second external drive stored separately
- No recording exists in only one place after this session

**5. Whisper transcription pipeline**
Process recordings in batches through the local Whisper pipeline (described in Connected Article Format section):
- Prioritize recordings tied to existing published articles — transcripts feed directly into the Connected Article Format.
- Process in chronological order (oldest first) or by subject priority (most historically significant first) — Ricardo decides.
- Each completed transcript stored alongside the recording in the database.

---

### Potential value beyond the website

These recordings may have value in additional contexts. To be explored after inventory is complete:

| Use | Notes |
|---|---|
| **Podcast series** | Curated interview excerpts by theme, artist, era, or section (A La Music retrospectives, etc.) |
| **Documentary licensing** | Interview audio available for film and documentary producers covering Vancouver arts history |
| **Broadcaster licensing** | CBC Radio, Co-op Radio, and similar broadcasters may be interested in archival Vancouver arts content |
| **Academic archive programs** | UBC and SFU both have programs for preserving local cultural heritage — recordings spanning 20+ years of Vancouver arts scene have genuine archival value |

No action needed on these until the inventory is complete and the recordings are properly backed up.

---

## Revenue strategy

**Priority order (experience-first)**

1. Branded content and sponsorships
   - Local brands sponsor relevant sections (brewery sponsors music section, real estate sponsors neighbourhood guides)
   - Campaign microsites have primary sponsor slots
   - Contextual, valuable to readers, not interruptive

2. Display advertising (limited)
   - One standard ad unit per article, positioned after content
   - Google AdSense or local ad network
   - Never: autoplay video, popups, interstitials

3. Reader supporter tier
   - Voluntary $5-10/month
   - Benefits: ad-free experience, early access to weekly guides, supporter profile badge
   - No hard paywall on any content

4. Affiliate revenue
   - Restaurant recommendations, event tickets, hotel bookings (especially Whistler)
   - Disclosed clearly, relevant to content, zero UX cost

5. Network advertising packages (future)
   - Once 3+ city sites are live, sell regional packages to BC/national advertisers

---

## The Weekly Weekly Network

**Owned domains (known)**
- vancouverweekly.com (primary, building now)
- whistlerweekly.com (second launch)
- pembertonweekly.com (third launch)
- saskatoonweekly.com (first non-BC market)
- Additional domains TBC

**Architecture**
- WordPress Multisite: one installation, all city sites
- Shared codebase, theme, plugins, security
- Similar branding across network (same logo family, shared design system, clearly siblings)
- Separate content, community identity, local editorial voice per city
- Adding a new city = configuration task, not a rebuild

**Launch sequence**
1. Vancouver (now)
2. Whistler (after Vancouver stable, high tourism value)
3. Pemberton (Whistler audience spillover, festival connection)
4. Saskatoon (proves national model)
5. Further cities based on domain inventory

---

## Phase roadmap

**Phase 1: Recovery and import** ✓ Complete — June 13, 2026
- 5,881 post URLs identified from Wayback Machine CDX API
- 5,838 successfully recovered (99.3%)
- 2,896 spam posts quarantined (pharma SEO spam, WordPress query artifacts)
- 2,986 clean posts passed security audit
- 2,789 posts imported into local WordPress site
- 197 skipped — no recoverable article body (pages, event listings, Wayback artifacts); all intentional, no data loss
- Import ran 10:47am–12:27pm on June 13, 2026 (~100 minutes)

**Phase 2: Rebuild and design** (in progress)

Completed:
- Child theme `vancouver-weekly` on Newspack parent — live locally
- Navigation (`vw-nav`) with logo + section links
- Section landing system: `category.php` routing to `section-parts/{slug}.php`
- A La Music section front (`a-la-music.php`) — fully styled:
  - Zone A: symmetric 3-col lead block (text list | image anchor | text list), 5 posts per flank, 2 stories in center, post deduplication
  - Zone B–D: Newspack Homepage Posts modules
- Image quality tiers (`vw_image_tier()`): tier 0/1/2/3 by file size
- Section geometric marks (Direction A: Paired Bars) — live, pure CSS pseudo-elements
- Off-white page ground (`#F7F6F4`), design tokens as CSS variables
- Bylines: `By <strong>Name</strong>` pattern, grouped under headline on text cards
- Three design directions prototyped in `text-modules-preview.html` before Direction A was chosen

Remaining:
- Eyebrow tab (solid black label overlapping image cards in Zone D)
- out-n-about, must-see-films section fronts (same pattern as a-la-music)
- Single article template (`single.php`)
- Homepage
- Mobile-first responsive design
- Facebook dead-image cleanup
- Yoast SEO configuration
- Structured data and sitemap

**Phase 3: Editorial workflow**
- PublishPress roles and statuses
- Author pages with photo, bio, archive
- Contributor, author, editor role setup
- Notification system

**Phase 4: User system**
- Profile and taste preferences
- Social login (Google, Apple)
- 2FA implementation
- Personalized homepage feed
- Nostalgia archive surfacing

**Phase 5: Campaign microsites**
- Campaign custom post type
- World Cup Vancouver microsite
- API integrations (football-data.org, Open-Meteo, Vancouver Open Data)
- Reusable campaign template

**Phase 6: AI and automation**
- AI-powered natural language search
- Social post auto-generation on publish
- Weekly curated experience format
- AI-assisted editorial drafting tools
- Connected Article Format: entity scanning, contextual archive cards, inline and side-panel display
- Audio interview pipeline: Whisper transcription, AI moment flagging, editor-approved clip extraction, inline audio embeds
- Process Ricardo's existing interview recording archive

**Phase 7: Revenue**
- Sponsorship display system
- Google AdSense integration
- Reader supporter tier and payments
- Affiliate link management

**Phase 8: Network**
- WordPress Multisite conversion
- Whistler Weekly launch
- Network analytics dashboard
- Cross-site content syndication

---

## Key contacts and collaborators

- Ricardo: owner, Design Lead, primary decision maker
- GitHub: ricardokay
- Portfolio: ricardokhayatte.com
- Credentials: stored in LastPass

---

## Notes and decisions log

**June 12, 2026**
- Discovered two .wpress backups (770MB each) on server dated July 12, 2024
- All-in-One WP Migration free version blocked restore; manual database import used instead
- Database prefix is wptg_ not wp_
- wp-content extracted and merged from three folders (only current uploads had content)
- Newspack theme installed from GitHub release zip
- Photography section note: original used Facebook-connected masonry plugin; rebuild uses self-hosted masonry, no social platform dependency

**June 13, 2026**
- Recovery complete: 5,838 succeeded, 43 failed (mostly spam slugs)
- Security audit: 2,896 posts quarantined, 2,981 approved
- Upgraded from Pro to Max plan on Claude
- Import completed 12:27pm June 13, 2026: 2,789 imported, 197 skipped (no content — pages/event stubs/Wayback artifacts, not data loss)
- Full product vision documented (personalization, network, revenue, security)
- Decision: Multisite-ready build from start
- Decision: Similar branding across city sites, not fully independent
- Decision: Authenticator app 2FA, not SMS
- Decision: Plausible Analytics for PIPEDA compliance
- Decision: No hard paywall, voluntary supporter tier instead
- Added Automation Vision section: on-publish pipeline (SEO, social, sitemap, affiliate, newsletter queue), AI-drafted weekly curated experience packages, interest-tagged distribution, Monday analytics email, AAN newswire monitoring, cross-network post suggestions
- Decision: All automation is editor-supervised — every step produces a draft or suggestion, nothing publishes automatically without approval
- Decision: Rejoin AAN Publishers (aan.org) once site is live
- Decision: Affiliate link map maintained as a JSON file, no code required to update
- Decision: Automation failures always alert Ricardo — never silently skip
- Added Connected Article Format section: entity cards linking archive content inline and in side panels, audio interview pipeline (Whisper transcription → AI moment flagging → editor clip approval → inline embed)
- Decision: Whisper runs locally on Mac — no audio data sent externally, no per-minute cost
- Decision: Audio player is custom lightweight embed, no SoundCloud or Spotify dependency
- Decision: Entity cards only surface when archive match quality is high — no low-confidence suggestions shown to readers
- Decision: Connected Article Format is Phase 6 or later, after core site and automation stable
- Note: Ricardo holds existing interview recordings — archive processing is part of Phase 6 scope
- Added Interview Archive Project section: ~1,000 recordings across 20+ years, dedicated post-launch session for audit, file naming, Airtable database, redundant backup, and batch Whisper transcription
- Decision: Immediate action before the session — copy all drives older than five years to Google Drive as stopgap; no recording should exist on a single drive only
- Note: Recordings have potential value beyond the site — podcast series, documentary licensing, broadcaster licensing, UBC/SFU academic archive programs — to be explored after inventory
- Added Social Media section: confirmed accounts (Facebook, Instagram, X, LinkedIn, YouTube); accounts to set up (TikTok, Threads, Spotify podcast feed); Reddit strategy is human-only (r/vancouver, r/indieheads, r/CanadianMusic)
- Decision: Full security audit of all social accounts required before connecting anything to Buffer — change passwords, revoke unknown connected apps, check for unauthorized posts, enable 2FA on every account
- Decision: LinkedIn posted manually, not via Buffer — tone differs from other platforms
- Decision: Reddit is never automated; genuine participation only
