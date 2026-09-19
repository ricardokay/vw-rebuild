# Date-repair + duplicate review lists (prep, read-only), 2026-09-18

**Nothing was written to any database.** Live data comes from one SELECT of all **3,088** published posts on the server (content, dates, comments, flags, categories). True dates come from the **Feb 2019 production SQL** (`~/Downloads/vanctcjx_vweekly2016a.sql`, `wp_posts`, `post_type = post`). The cohorts are the ones in `date-audit.md`. Machine-readable files are in `backups-local/date-repair-prep/` (gitignored):

| File | Rows | What |
|---|---:|---|
| `list1-date-fix-dryrun.csv` | 550 | Wrong-dated posts with a 2019 match: current and proposed `post_date` / `post_date_gmt`, match, confidence, notes |
| `list1b-no-2019-match.csv` | 24 | Wrong-dated posts with no 2019 row, plus every secondary signal found |
| `list1c-other-2019-mismatches.csv` | 5 | Posts outside the 574 whose date disagrees with the 2019 SQL |
| `list2-duplicate-pairs.csv` | 90 | Content-duplicate pairs (84) + title-only pairs (6) |
| `list3-post-2020-03.csv` | 119 | Every published post dated on or after 2020-03-01 |
| `date-repair-prep.json` | all | Everything above in one file, plus the 264 excluded boilerplate pairs |

## Summary

- **The 574 wrong-dated posts** (46 A = the 42 + the 4 deferred; 528 B; all 574 carry `_vw_front_suppress = 1`, verified in this SELECT):
  - **550 (95.8%) can be dated from the 2019 SQL:** 524 high confidence (exact slug), 26 medium (title matches; 25 of them are cohort A).
  - **24 need secondary or manual dating:** 13 A, 11 B. **All 13 A posts have a content twin already live**, and every one gets a suggested date from it: 12 directly, and 992 through its twin's 2019 date. The 11 B posts are mostly navigation pages imported as posts.
- **Content-duplicate pairs: 84**, covering 168 posts. Every pair is strictly 1:1; no post has two partners.
  - **(a) EXACT: 2.** The same words and the same media; only the markup differs.
  - **(b) NEAR: 82.** In **78** of these, one copy's text is contained word for word in the other. The difference is scrape chrome: author-bio boxes ("Vancouver Weekly As Vancouver's alternative newsweekly…"), bylines, photo credits and tag lines. They are the same article, but not byte-exact, so each needs your eyeball.
  - **(c) TITLE-ONLY: 6.** Probably not duplicates (recurring column titles, different bodies).
  - 40 of the 84 pairs involve a wrong-dated post: 38 contain a cohort A post (in 3 of them the twin is cohort B), and 2 contain only a cohort B post. No pair has an approved comment on either side.
  - 264 more pairs have identical boilerplate text but different photo sets (gallery posts). They were **excluded as not duplicates** and are listed in the JSON only.
- **Posts dated on or after 2020-03-01: 119.**
  - 46 are cohort A (wrong, fixed by List 1).
  - 2 are the allowlist (65340, 65350).
  - **71 are not in any cohort** and cannot be checked against the 2019 SQL (its data ends at 2019-02-14). **Their content reads as genuinely 2020:** COVID livestreams, *Chromatica*, *RTJ4*, *Tiger King*, BLACKPINK "How You Like That". That conflicts with "publishing stopped around March 2020", so please confirm before any of them is treated as wrong.

## Recommended execution order for the write round

1. **Decisions first, no writes:**
   - the List 3 allowlist;
   - survivors for the List 2 pairs;
   - a review of the 26 medium-confidence List 1 rows and the 24 List 1b rows.
2. **Mechanical date fix: 518 posts** (512 B, 6 A). These are the high-confidence exact-slug rows that are **not in any duplicate pair**, so they need no decision. Copy `post_date` and `post_date_gmt` from the 2019 SQL, then delete their `_vw_front_suppress`. Every one moves *earlier*.
3. **De-duplicate** the pairs once you have picked survivors. Retire the loser to draft with a reversal flag, as in B1 (nothing deleted), and 301 its URL to the survivor through the `vw-security` redirect list.
4. **Date-fix the surviving pair members and the rest:** the 30 List 1 rows in pairs (23 of them are cohort A title matches), the other medium rows, and the 24 List 1b rows at your approved dates. Then drop their flags and purge.

**Why de-dup comes before the pair dates, and not after:**
- 40 pairs contain a wrong-dated copy. Redating first would put 38 cohort A posts on the same date as their twin. The archive and search would then show two identical stories side by side.
- Redating first would also spend writes on posts that are then retired.
- Which copy survives decides which ID (and which URL: the agency slug or the original) carries the date.
- Step 2 does not have this problem, because none of those 518 posts is in a pair. It can run as soon as you approve, without waiting on the editorial calls.

## List 1: date-fix dry run (2019 SQL)

**550 rows.** Confidence: exact slug = high; title or slug-suffix match = medium; downgraded to low if the 2019 row is not publish, several 2019 rows match, or the proposed date is later than the current one (none were downgraded). In every row the proposed date is **earlier** than the current one.
How far each post moves back: ≤1 day 35, 2–30 days 322, 31–365 days 122, more than a year 71 (the 33 cohort A posts move back by 1,960+ days each).

Two pairs of wrong-dated posts map to the same 2019 row: 1016 and 65465 (Inside the Incubator), and 1007 and 67344. Each pair is also a content-duplicate pair, so de-dup decides which one keeps the date.

### 1-medium: the 26 rows to review (title or slug-suffix match)

| ID | Cohort | Title (live) | Current | Proposed (2019) | 2019 title / slug | Live content twin |
|---|---|---|---|---|---|---|
| 904 | A | How To Have A Career And Not Incur Any Debt | 2024-06-24 04:43 | **2016-06-15 10:18** | How To Have A Career And Not Incur Any Debt / `how-to-have-a-career-and-not-incur-any-d` | 66446 @ 2016-06-15 10:18:31 (NEAR) |
| 910 | A | How Blockchain Technology Will Revolutionize Global Transact | 2024-06-24 04:48 | **2016-05-02 11:32** | How Blockchain Technology Will Revolutionize  / `how-blockchain-technology-will-revolutio` | 66428 @ 2016-05-02 11:32:56 (NEAR) |
| 921 | A | How Sun Grown Cultivation of Medicinal Marijuana Will Protec | 2024-06-24 04:53 | **2016-04-18 10:19** | How Sun Grown Cultivation of Medicinal Mariju / `how-sun-grown-cultivation-of-medicinal-m` | 66437 @ 2016-04-18 10:19:14 (NEAR) |
| 937 | A | Vancouver’s Looking Glass Foundation Delves Into Eating Diso | 2024-06-24 04:57 | **2016-03-22 19:50** | Vancouver's Looking Glass Foundation Delves I / `vancouvers-looking-glass-foundation-delv` | 68845 @ 2016-03-22 19:50:10 (NEAR) |
| 948 | A | 3 Progressive Companies Are Making Vancouver’s Future Bright | 2024-06-24 05:01 | **2016-02-29 09:08** | 3 Progressive Companies Are Making Vancouver’ / `3-progressive-companies-are-making-vanco` |  |
| 956 | A | 5 Ideas For An Unforgettable Valentine’s Day SuperDate | 2024-06-24 05:09 | **2016-02-03 10:04** | 5 Ideas For An Unforgettable Valentine’s Day  / `5-ideas-for-an-unforgettable-valentines-` | 65428 @ 2016-02-03 10:04:51 (NEAR) |
| 1007 | A | Outlook for B.C. tech looks promising for 2015 | 2024-06-24 05:36 | **2015-01-16 19:37** | Outlook for B.C. tech looks promising for 201 / `outlook-b-c-tech-looks-promising-2015` | 67344 @ 2015-05-20 11:34:41 (NEAR) |
| 1016 | A | Inside the incubator: A look into incubator/accelerator prog | 2024-06-24 05:41 | **2015-04-09 19:34** | Inside the incubator: A look into incubator/a / `a-look-into-incubator-accelerator-progra` | 65465 @ 2015-05-18 01:33:10 (NEAR) |
| 1373 | A | Global Citizen announces its first-ever Vancouver live music | 2024-06-25 05:03 | **2018-03-14 12:20** | Global Citizen announces its first-ever Vanco / `global-citizen-announces-its-first-ever-` | 66298 @ 2018-03-14 12:20:59 (NEAR) |
| 1381 | A | How employers can prepare the workplace for cannabis legaliz | 2024-06-25 05:12 | **2018-06-07 09:02** | How employers can prepare the workplace for c / `how-employers-can-prepare-the-workplace-` | 66430 @ 2018-06-07 09:02:45 (NEAR) |
| 1384 | A | The Science of Wildfires | 2024-06-25 05:12 | **2018-06-05 11:33** | The Science of Wildfires / `the-science-of-wildfires-60817-2` | 68580 @ 2018-06-05 11:33:09 (NEAR) |
| 1390 | A | 2017: The Fall of Patriarchy | 2024-06-25 05:15 | **2017-12-27 09:07** | 2017: The Fall of Patriarchy / `2017-the-fall-of-patriarchy-59519-2` | 65402 @ 2017-12-27 09:07:09 (NEAR) |
| 1398 | A | Deafhood and the importance of learning a first language in  | 2024-06-25 05:17 | **2017-11-24 11:27** | Deafhood and the importance of learning a fir / `deafhood-and-the-importance-of-learning-` | 66055 @ 2017-11-24 11:27:35 (NEAR) |
| 1407 | A | Stiff fines ahead for property owners that don’t comply with | 2024-06-25 05:19 | **2017-11-08 10:14** | Stiff fines ahead for property owners that do / `stiff-fines-ahead-for-property-owners-th` | 68384 @ 2017-11-08 10:14:42 (NEAR) |
| 1496 | A | Science of Cocktails experience brings together bartenders,  | 2024-06-25 06:22 | **2019-02-11 09:30** | Science of Cocktails experience brings togeth / `science-of-cocktails-experience-bring-ba` | 68205 @ 2019-02-11 09:30:54 (NEAR) |
| 1503 | A | Some are born sweet, some achieve sweetness, and some have s | 2024-06-25 06:28 | **2019-01-21 09:30** | Some are born sweet, some achieve sweetness,  / `some-are-born-sweet-some-achieve-sweetne` | 68339 @ 2019-01-21 09:30:35 (NEAR) |
| 1576 | A | Shining Fresh Seafood in the Vibrant Neighborhood of Yaletow | 2024-06-25 11:27 | **2019-01-09 09:30** | Shining Fresh Seafood in the Vibrant Neighbor / `shining-fresh-seafood-in-the-vibrant-nei` | 68269 @ 2019-01-09 09:30:23 (NEAR) |
| 1580 | A | Love is Sharing Food – Top Vancouver Tapas | 2024-06-26 04:50 | **2019-01-07 09:30** | Love is Sharing Food - Top Vancouver Tapas / `love-is-sharing-food-top-vancouver-tapas` |  |
| 1585 | A | 7 Ways to Get Your Holiday Spirit on at the Vancouver Christ | 2024-06-26 05:05 | **2018-12-21 09:30** | 7 Ways to Get Your Holiday Spirit on at the V / `7-ways-to-get-your-holiday-spirit-on-at-` | 65440 @ 2018-12-21 09:30:42 (NEAR) |
| 1591 | A | Get a Real Taste of the Islands at this Jamaican Dining Pop- | 2024-06-26 05:08 | **2018-12-14 09:30** | Get a Real Taste of the Islands at this Jamai / `get-a-real-taste-of-the-islands-at-this-` | 66288 @ 2018-12-14 09:30:25 (NEAR) |
| 1596 | A | How to Find Your Inner German at the Vancouver Christmas Mar | 2024-06-26 05:15 | **2018-12-17 09:30** | How to Find Your Inner German at the Vancouve / `how-to-find-your-inner-german-at-the-van` | 66442 @ 2018-12-17 09:30:50 (NEAR) |
| 1600 | A | Colony Bars Holiday Eggnog Arrives in the City for a Good Ca | 2024-06-26 05:18 | **2018-12-19 22:00** | Colony Bars Holiday Eggnog Arrives in the Cit / `colony-bars-holiday-eggnog-arrives-in-th` | 65933 @ 2018-12-19 22:00:11 (NEAR) |
| 1618 | A | This Chemistry-Themed Cafe is where Art and Chemistry Intert | 2024-06-26 05:36 | **2018-11-30 09:30** | This Chemistry-Themed Cafe is where Art and C / `this-chemistry-themed-cafe-is-where-art-` | 68635 @ 2018-11-30 09:30:32 (NEAR) |
| 1622 | A | A monthly cereal bar pop-up is bringing a heavy dose of nost | 2024-06-26 05:38 | **2018-11-21 10:00** | A monthly cereal bar pop-up is bringing a hea / `a-monthly-cereal-bar-pop-up-is-bringing-` | 65468 @ 2018-11-21 10:00:42 (NEAR) |
| 1626 | A | Break your fast, not your wallet | 2024-06-26 05:44 | **2018-11-28 09:30** | Break your fast, not your wallet / `break-your-fast-not-your-wallet-62368-2` | 65777 @ 2018-11-28 09:30:05 (NEAR) |
| 67280 | B | Ian Vanek and the Art of Motorcycle Maintenance | 2015-02-06 05:28 | **2015-02-02 14:15** | Ian Vanek and the art of motorcycle maintenan / `ian-vanek-and-the-art-of-motorcycle-main` | 66473 @ 2015-02-02 14:15:18 (NEAR) |

### 1-high: 524 exact-slug rows (sample of 15; the full list is in the CSV)

| ID | Cohort | Title | Current | Proposed (2019) | Days moved back |
|---|---|---|---|---|---:|
| 629 | A | Vancouver Brewery Tours Makes You Feel Like You Are In Italy | 2024-06-14 06:10 | 2019-01-28 09:30 | 1963 |
| 65594 | B | ‘Ask Around’: Photography by Steve Louie at the Remington Ga | 2014-06-08 13:01 | 2014-06-06 10:27 | 2 |
| 65800 | B | Burnaby Blues + Roots Festival 2014 | 2014-08-15 04:38 | 2014-08-11 10:00 | 3 |
| 66051 | B | Davidian Knows What We Want on Debut EP | 2014-06-08 13:02 | 2014-06-04 09:41 | 4 |
| 66221 | B | First Nations tourism a cultural ‘snapshot,’ and ‘a new geog | 2015-12-02 08:40 | 2015-09-15 08:20 | 78 |
| 66370 | B | Hashtag, I don’t need feminism because… | 2014-07-29 04:22 | 2014-07-24 17:11 | 4 |
| 66818 | B | Johnny de Courcy announces ‘The Master Manipulator EP’, shar | 2015-08-04 22:43 | 2015-06-14 12:19 | 51 |
| 67099 | B | Man attacked by a group of men outside Metrotown shopping ce | 2015-08-03 02:08 | 2015-08-01 16:58 | 1 |
| 67277 | B | “Nirbhaya”: A must see performance that explores sexual viol | 2015-11-09 12:32 | 2015-11-05 09:33 | 4 |
| 67948 | B | Political activist Joan Baez shares her story | 2014-12-01 07:26 | 2014-11-14 08:41 | 16 |
| 68355 | B | Spoon River “In the Parking Lots of the Swamp Museums” Video | 2014-12-27 13:24 | 2014-11-04 09:58 | 53 |
| 68552 | B | The Melvins dial back the bizarro but rock as hard as ever | 2015-10-25 02:19 | 2015-09-10 07:16 | 44 |
| 68842 | B | Vancouver Whitecaps enjoying life on top, but taking nothing | 2015-08-21 17:12 | 2015-08-07 18:40 | 13 |
| 69016 | B | Win Tickets to Celtic Connections at Chan Centre – March 21, | 2015-03-09 23:20 | 2015-03-06 10:16 | 3 |
| 69083 | B | Win Tickets to Strand of Oaks @ Biltmore Cabaret – August 27 | 2014-07-07 23:52 | 2014-07-01 12:43 | 6 |

### 1b: no 2019 match (24): secondary signals

Wayback: the Internet Archive returned *Temporarily Offline* on 2026-09-18. The captures shown come from the audit's earlier queries. For cohort B the earliest capture **is** the current wrong date, so it tells us nothing.

| ID | Cohort | Title | Current | Upload months | In-body date | Content twin | Wayback earliest | Suggested |
|---|---|---|---|---|---|---|---|---|
| 657 | A | ScreenTime Junkies | 2024-06-14 | – | – | 68209 @ 2020-03-10 14:36:14 (NEAR; unverified (n | 20241213225049 | **twin date 2020-03-10** |
| 660 | A | Inside every human is a story written in gene | 2024-06-14 | 2019-11 | – | 66504 @ 2019-11-10 11:32:20 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2019-11-10** |
| 992 | A | Domingo copper project on hold, waiting for p | 2024-06-24 | – | – | 65847 @ 2015-09-25 09:06:49 (NEAR; WRONG (cohort | not queried: IA offline 2026-0 | **twin's 2019-SQL date 2015-09-09** |
| 1376 | A | Food Sustainability for the Urban Dwelle | 2024-06-25 | 2018-04 | – | 66243 @ 2018-04-30 10:15:12 (NEAR; correct (=201 | not queried: IA offline 2026-0 | **twin date 2018-04-30** |
| 1465 | A | Eco Eats is Changing the Way You Look at Food | 2024-06-25 | – | – | 68641 @ 2020-01-17 09:11:59 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2020-01-17** |
| 1470 | A | TWG Has A New Mouth Watering Set Menu For The | 2024-06-25 | 2019-12 | – | 68733 @ 2019-12-24 08:00:50 (NEAR; unverified (n | 20240713121054 | **twin date 2019-12-24** |
| 1474 | A | Holiday High Tea at the New Secret Garden Tea | 2024-06-25 | 2019-12 | – | 66403 @ 2019-12-20 12:00:02 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2019-12-20** |
| 1478 | A | A New Bar is Set: Art x Craft is Vancouver’s  | 2024-06-25 | 2019-12 | – | 69202 @ 2019-12-11 08:00:58 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2019-12-11** |
| 1484 | A | The Vancouver Christmas Market is Now Open –  | 2024-06-25 | 2019-11 | – | 68601 @ 2019-11-28 09:00:37 (NEAR; unverified (n | 20250126011615 | **twin date 2019-11-28** |
| 1488 | A | 5 Crazy Desserts in Vancouver You Need to Try | 2024-06-25 | 2019-10 | – | 65427 @ 2019-10-18 10:00:26 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2019-10-18** |
| 1492 | A | Vancouver’s Newest Cannabis Infused Pop-Up Di | 2024-06-25 | 2019-03 | March 10, 2019 | 68847 @ 2019-03-07 09:30:37 (NEAR; unverified (n | not queried: IA offline 2026-0 | **twin date 2019-03-07** |
| 1606 | A | Seasonal Splendour Spews Through the Shaughne | 2024-06-26 | 2018-12 | – | 68211 @ 2018-12-10 22:15:45 (NEAR; correct (=201 | not queried: IA offline 2026-0 | **twin date 2018-12-10** |
| 1813 | A | Cat Cohen Showcases Life as a Gal About Town  | 2024-06-27 | – | – | 65854 @ 2021-04-22 08:15:38 (NEAR; unverified (n | 20210422153719 | **twin date 2021-04-22** |
| 65507 | B | Advertise With Us | 2014-01-01 | 2013-12 | – | – | circular for cohort B (= curre | **month from upload path 2013-12** |
| 65617 | B | Backstreet Boys Extend Tour | 2014-03-17 | 2014-03 | – | – | circular for cohort B (= curre | **month from upload path 2014-03** |
| 66001 | B | Contributor Kit | 2013-10-29 | 2012-07 2013-10 | – | – | circular for cohort B (= curre | **month from upload path 2013-10** |
| 66177 | B | Trampled by Turtles | 2012-05-14 | 2012-05 | – | – | circular for cohort B (= curre | **month from upload path 2012-05** |
| 66216 | B | Upcoming Events    Film | 2012-05-24 | 2012-05 | – | – | circular for cohort B (= curre | **month from upload path 2012-05** |
| 66810 | B | Jobs | 2013-11-08 | – | – | – | circular for cohort B (= curre | **no signal: author/Ricardo** |
| 67229 | B | Trampled by Turtles | 2012-05-24 | 2012-05 | – | – | circular for cohort B (= curre | **month from upload path 2012-05** |
| 67431 | B | Calendar of Events | 2013-09-28 | 2013-07 | – | – | circular for cohort B (= curre | **month from upload path 2013-07** |
| 68277 | B | Signup Form | 2014-01-29 | – | – | – | circular for cohort B (= curre | **no signal: author/Ricardo** |
| 68626 | B | Upcoming Events    Theatre | 2012-05-24 | 2012-05 | June 16, 2012 | – | circular for cohort B (= curre | **month from upload path 2012-05** |
| 68760 | B | Upcoming Events | 2012-08-26 | 2012-08 | – | – | circular for cohort B (= curre | **month from upload path 2012-08** |

Notes:
- Where an A row has both an upload month and a twin date (9 rows), the two agree 9/9.
- 992's twin, 65847, is itself cohort B; its 2019 date is 2015-09-09.
- Of the B rows: 65507 Advertise, 66001 Contributor Kit, 66216/68626/68760/67431 event calendars, 66810 Jobs and 68277 Signup Form are **navigation pages imported as posts**. They may be better retired than dated; that is your call.
- 66177 and 67229 (both "Trampled by Turtles") are a title-only pair in List 2.

### 1c: posts outside the 574 where the 2019 SQL disagrees (5, for awareness; not in any repair)

| ID | Title | Current | 2019 SQL | Match |
|---|---|---|---|---|
| 1510 | Tegan and Sara celebrate 10 years of ‘The Con,’ support | 2019-10-30 06:32 | 2017-10-30 09:08 | title |
| 65391 | 11 Hidden Furniture Store Gems In Vancouver | 2016-09-15 15:30 | 2016-12-27 08:30 | slug |
| 66434 | How science is helping this nerd make better beer | 2017-02-09 15:27 | 2017-03-13 11:21 | slug |
| 67036 | Lifestyle Over Luxury Makes Being Eco-friendly Affordab | 2016-09-30 11:23 | 2017-03-20 03:22 | slug |
| 67300 | Nymphomaniac (I & II): Lars’ Darkest Work Yet | 2014-04-25 22:19 | 2014-03-17 09:16 | slug-suffix |

- 1510: Tegan and Sara's 10-year anniversary. The live date is 2019, the 2019 SQL says 2017; the live date is later than the dump itself, so the post was re-dated after Feb 2019. Which date is right is an editorial call.
- 65391, 66434 and 67036 are the twins of 357, 335 and 327 (List 2), whose dates match the 2019 SQL. The twin copies carry an earlier date than the 2019 SQL, probably a first publication before a reschedule. Resolve this with the de-dup survivor choice.

## List 2: content-duplicate pairs

Method:
- Candidates: same normalized title, identical normalized text, or ≥3 shared sampled 5-word shingles.
- Text comparison: stripped of markup and shortcodes, with entities and quotes normalized. Scored by 5-gram Jaccard and containment (the share of the shorter copy found in the longer), plus the count of words unique to each side.
- Media comparison: image filenames with size suffixes removed, plus video/audio embed IDs.
- **Title is never sufficient.** A gallery pair that shares boilerplate text but has different photos is excluded.
- No deletion is recommended. You pick the survivor.

Columns:
- *Date state*: correct (= 2019 SQL), WRONG (cohort), DIFFERS from the 2019 SQL, or unverified (post-2019 or no 2019 row).
- *Uniq words A/B*: words present only in that copy.
- *Cmts*: approved/all comments.

| # | Class | A: ID · date · state | B: ID · date · state | Title (A) | Words A/B | Uniq A/B | Media A/B | Cmts A · B | Indicator |
|---|---|---|---|---|---|---|---|---|---|
| 1 | EXACT | **1634** · 2013-11-12 · correct (=2019 SQL) | **13779** · 2013-11-12 · correct (=2019 SQL) | “Picture Me”: Profile of a Model is Honest, But Fa | 533/533 | 0/0 | 0/0 | 0/4 · 0/5 | exact: same text + same media (media 0/0 identical); markup differs |
| 2 | EXACT | **1637** · 2013-11-04 · correct (=2019 SQL) | **13204** · 2013-11-04 · correct (=2019 SQL) | “16 Acres”: Bureaucratic Battle Royale at Ground Z | 547/547 | 0/0 | 0/0 | 0/1 · 0/5 | exact: same text + same media (media 0/0 identical); markup differs |
| 3 | NEAR | **96** · 2020-12-11 · unverified (no 2019 row) | **67053** · 2020-12-11 · unverified (no 2019 row) | Local Electronic Duo Recognized For Bringing The F | 810/827 | 0/22 | 0/1 | 0/6 · 0/0 | near: jaccard 0.979, containment 1.0; media 0/1, overlap 0.0 |
| 4 | NEAR | **258** · 2020-02-07 · unverified (no 2019 row) | **68752** · 2020-02-07 · unverified (no 2019 row) | Unikkaaqtuat transports its audience into Inuit st | 565/567 | 0/2 | 0/2 | 0/6 · 0/0 | near: jaccard 0.996, containment 1.0; media 0/2, overlap 0.0 |
| 5 | NEAR | **262** · 2020-02-07 · unverified (no 2019 row) | **66910** · 2020-02-07 · unverified (no 2019 row) | Kismet Asks the Big Questions | 631/658 | 0/27 | 0/2 | 0/2 · 0/0 | near: jaccard 0.959, containment 1.0; media 0/2, overlap 0.0 |
| 6 | NEAR | **274** · 2020-01-24 · unverified (no 2019 row) | **65558** · 2020-01-24 · unverified (no 2019 row) | Andrea Jin Steps Up Canadian Stand-Up | 526/528 | 1/2 | 0/1 | 0/4 · 0/0 | near: jaccard 0.996, containment 1.0; media 0/1, overlap 0.0 |
| 7 | NEAR | **299** · 2019-11-19 · unverified (no 2019 row) | **66084** · 2019-11-19 · unverified (no 2019 row) | Disney on Ice presents Mickey’s Search Party at Pa | 206/226 | 0/18 | 3/5 | 0/4 · 0/0 | near: jaccard 0.91, containment 1.0; media 3/5, overlap 1.0 |
| 8 | NEAR | **305** · 2019-08-29 · unverified (no 2019 row) | **68608** · 2019-08-29 · unverified (no 2019 row) | The Warrior Who Would Rule: Bard on the Beach Unle | 984/986 | 0/2 | 0/2 | 0/6 · 0/0 | near: jaccard 0.998, containment 1.0; media 0/2, overlap 0.0 |
| 9 | NEAR | **307** · 2019-08-22 · unverified (no 2019 row) | **68922** · 2019-08-22 · unverified (no 2019 row) | Weird Al still reigns supreme as king of all thing | 645/647 | 0/2 | 0/1 | 0/2 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/1, overlap 0.0 |
| 10 | NEAR | **315** · 2019-07-01 · unverified (no 2019 row) | **68366** · 2019-07-01 · unverified (no 2019 row) | Star-crossed love wins in Shakespeare in Love | 574/576 | 0/2 | 0/2 | 0/2 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/2, overlap 0.0 |
| 11 | NEAR | **316** · 2019-08-08 · unverified (no 2019 row) | **68782** · 2019-08-08 · unverified (no 2019 row) | Vancouver Chinatown Festival Brings Food Trucks, C | 390/410 | 0/18 | 5/7 | 0/5 · 0/0 | near: jaccard 0.95, containment 1.0; media 5/7, overlap 1.0 |
| 12 | NEAR | **326** · 2017-11-28 · correct (=2019 SQL) | **68372** · 2017-11-28 · correct (=2019 SQL) | Startup Weekend 2017 Brings Together Entrepreneurs | 1164/1166 | 0/2 | 0/2 | 0/5 · 0/0 | near: jaccard 0.998, containment 1.0; media 0/2, overlap 0.0 |
| 13 | NEAR | **327** · 2017-03-20 · correct (=2019 SQL) | **67036** · 2016-09-30 · DIFFERS from 2019 SQL 2017-03-20 0 | Lifestyle Over Luxury Makes Being Eco-friendly Aff | 733/753 | 0/18 | 3/5 | 0/1 · 0/0 | near: jaccard 0.973, containment 1.0; media 3/5, overlap 1.0 |
| 14 | NEAR | **363** · 2016-09-26 · unverified (no 2019 row) | **65611** · 2016-09-26 · correct (=2019 SQL) | Back To The Slopes With Whistler Blackcomb’s Annua | 596/616 | 0/18 | 4/6 | 0/7 · 0/0 | near: jaccard 0.967, containment 1.0; media 4/6, overlap 1.0 |
| 15 | NEAR | **370** · 2016-09-01 · correct (=2019 SQL) | **66447** · 2016-09-01 · correct (=2019 SQL) | How To Have Your Own Rock Star Toast Party | 463/483 | 0/18 | 1/3 | 0/6 · 0/0 | near: jaccard 0.958, containment 1.0; media 1/3, overlap 1.0 |
| 16 | NEAR | **388** · 2016-08-09 · correct (=2019 SQL) | **66451** · 2016-08-09 · correct (=2019 SQL) | How to Make Badass Super Coffee | 701/721 | 0/18 | 2/5 | 0/9 · 0/0 | near: jaccard 0.972, containment 1.0; media 2/5, overlap 0.5 |
| 17 | NEAR | **393** · 2016-08-05 · correct (=2019 SQL) | **66196** · 2016-08-05 · correct (=2019 SQL) | Facebook Tweaks its Algorithm to Fight Clickbait | 439/444 | 0/3 | 0/2 | 0/4 · 0/0 | near: jaccard 0.989, containment 1.0; media 0/2, overlap 0.0 |
| 18 | NEAR | **398** · 2016-08-05 · correct (=2019 SQL) | **67005** · 2016-08-05 · correct (=2019 SQL) | Lessons on Startup Success: Get Comfortable with B | 1682/1698 | 0/16 | 0/2 | 0/5 · 0/0 | near: jaccard 0.991, containment 1.0; media 0/2, overlap 0.0 |
| 19 | NEAR | **529** · 2020-06-20 · unverified (no 2019 row) | **68544** · 2020-06-20 · unverified (no 2019 row) | Premiere – The Long War release music video for “E | 404/422 | 0/23 | 0/2 | 0/4 · 0/0 | near: jaccard 0.957, containment 1.0; media 0/2, overlap 0.0 |
| 20 | NEAR | **586** · 2017-11-26 · correct (=2019 SQL) | **66898** · 2017-11-26 · correct (=2019 SQL) | Kim Gray reveals new video for “Taking It Too Easy | 235/255 | 0/23 | 2/3 | 0/3 · 0/0 | near: jaccard 0.92, containment 1.0; media 2/3, overlap 0.5 |
| 21 | NEAR | **606** · 2015-12-20 · correct (=2019 SQL) | **68528** · 2015-12-24 · WRONG (cohort B); true 2015-12-20  | The Harpoonist and the Axe Murderer reveal new vid | 89/125 | 0/35 | 1/2 | 0/4 · 0/0 | near: jaccard 0.702, containment 1.0; media 1/2, overlap 0.0 |
| 22 | NEAR | **657** · 2024-06-14 · WRONG (cohort A) | **68209** · 2020-03-10 · unverified (no 2019 row) | ScreenTime Junkies | 1843/1852 | 0/9 | 0/1 | 0/4 · 0/0 | near: jaccard 0.995, containment 1.0; media 0/1, overlap 0.0 |
| 23 | NEAR | **660** · 2024-06-14 · WRONG (cohort A) | **66504** · 2019-11-10 · unverified (no 2019 row) | Inside every human is a story written in genetic c | 1442/1445 | 0/3 | 1/1 | 0/3 · 0/0 | near: jaccard 0.998, containment 1.0; media 1/1 identical |
| 24 | NEAR | **667** · 2024-06-14 · WRONG (cohort A); true 2013-09-20  | **9902** · 2013-09-20 · correct (=2019 SQL) | “Serving Life”: The Softer Side of Prison | 613/613 | 0/0 | 1/0 | 0/3 · 0/6 | near: text identical, media differ (media 1/0, overlap 0.0) |
| 25 | NEAR | **669** · 2024-06-14 · WRONG (cohort A); true 2013-11-20  | **14377** · 2013-11-20 · correct (=2019 SQL) | “Somewhere Between”: Four Adopted Girls Search for | 605/605 | 0/0 | 1/0 | 0/5 · 0/1 | near: text identical, media differ (media 1/0, overlap 0.0) |
| 26 | NEAR | **904** · 2024-06-24 · WRONG (cohort A); true 2016-06-15  | **66446** · 2016-06-15 · correct (=2019 SQL) | How To Have A Career And Not Incur Any Debt | 496/516 | 0/18 | 2/4 | 0/7 · 0/0 | near: jaccard 0.961, containment 1.0; media 2/4, overlap 1.0 |
| 27 | NEAR | **910** · 2024-06-24 · WRONG (cohort A); true 2016-05-02  | **66428** · 2016-05-02 · correct (=2019 SQL) | How Blockchain Technology Will Revolutionize Globa | 882/906 | 0/23 | 0/2 | 0/2 · 0/0 | near: jaccard 0.978, containment 1.0; media 0/2, overlap 0.0 |
| 28 | NEAR | **937** · 2024-06-24 · WRONG (cohort A); true 2016-03-22  | **68845** · 2016-03-22 · correct (=2019 SQL) | Vancouver’s Looking Glass Foundation Delves Into E | 1967/1973 | 0/6 | 0/2 | 0/5 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/2, overlap 0.0 |
| 29 | NEAR | **952** · 2020-07-31 · unverified (no 2019 row) | **67979** · 2020-07-31 · unverified (no 2019 row) | Ontario’s Dizzy take introspective approach on sop | 455/775 | 0/323 | 1/4 | 0/4 · 0/0 | near: jaccard 0.587, containment 1.0; media 1/4, overlap 1.0 |
| 30 | NEAR | **956** · 2024-06-24 · WRONG (cohort A); true 2016-02-03  | **65428** · 2016-02-03 · correct (=2019 SQL) | 5 Ideas For An Unforgettable Valentine’s Day Super | 800/802 | 0/2 | 0/1 | 0/4 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/1, overlap 0.0 |
| 31 | NEAR | **992** · 2024-06-24 · WRONG (cohort A) | **65847** · 2015-09-25 · WRONG (cohort B); true 2015-09-09  | Domingo copper project on hold, waiting for price  | 271/325 | 0/51 | 0/0 | 0/7 · 0/0 | near: jaccard 0.832, containment 1.0; media 0/0 identical |
| 32 | NEAR | **1007** · 2024-06-24 · WRONG (cohort A); true 2015-01-16  | **67344** · 2015-05-20 · WRONG (cohort B); true 2015-01-16  | Outlook for B.C. tech looks promising for 2015 | 355/396 | 0/42 | 0/1 | 0/3 · 0/0 | near: jaccard 0.895, containment 1.0; media 0/1, overlap 0.0 |
| 33 | NEAR | **1016** · 2024-06-24 · WRONG (cohort A); true 2015-04-09  | **65465** · 2015-05-18 · WRONG (cohort B); true 2015-04-09  | Inside the incubator: A look into incubator/accele | 963/996 | 0/31 | 4/5 | 0/4 · 0/0 | near: jaccard 0.967, containment 1.0; media 4/5, overlap 0.75 |
| 34 | NEAR | **1054** · 2020-04-02 · unverified (no 2019 row) | **67936** · 2020-04-02 · unverified (no 2019 row) | Play it like it’s your last show – Andrew Seward o | 613/625 | 0/15 | 1/2 | 0/6 · 0/0 | near: jaccard 0.981, containment 1.0; media 1/2, overlap 1.0 |
| 35 | NEAR | **1154** · 2020-01-18 · unverified (no 2019 row) | **69153** · 2020-01-18 · unverified (no 2019 row) | Win Tickets to Vancouver Short Film Festival 10 Ye | 249/249 | 0/0 | 2/3 | 0/3 · 0/0 | near: text identical, media differ (media 2/3, overlap 0.5) |
| 36 | NEAR | **1162** · 2019-06-15 · unverified (no 2019 row) | **69150** · 2019-06-15 · unverified (no 2019 row) | Win Tickets to Vancouver Folk Music Festival 2019 | 142/162 | 0/18 | 1/4 | 0/7 · 0/0 | near: jaccard 0.873, containment 1.0; media 1/4, overlap 0.0 |
| 37 | NEAR | **1172** · 2018-07-05 · correct (=2019 SQL) | **69129** · 2018-07-05 · correct (=2019 SQL) | Win Tickets to Rodgers & Hammerstein’s Cinderella  | 178/185 | 0/7 | 2/3 | 0/4 · 0/0 | near: jaccard 0.961, containment 1.0; media 2/3, overlap 0.5 |
| 38 | NEAR | **1331** · 2018-02-06 · correct (=2019 SQL) | **69125** · 2018-02-06 · correct (=2019 SQL) | Win Tickets to My Funny Valentine at The Dance Cen | 215/215 | 0/0 | 2/3 | 0/2 · 0/0 | near: text identical, media differ (media 2/3, overlap 0.5) |
| 39 | NEAR | **1335** · 2018-04-30 · correct (=2019 SQL) | **69090** · 2018-04-30 · correct (=2019 SQL) | Win Tickets to A Midsummer Night’s Dream at Vancou | 280/280 | 0/0 | 2/3 | 0/6 · 0/0 | near: text identical, media differ (media 2/3, overlap 0.5) |
| 40 | NEAR | **1376** · 2024-06-25 · WRONG (cohort A) | **66243** · 2018-04-30 · correct (=2019 SQL) | Food Sustainability for the Urban Dwelle | 1914/1928 | 0/18 | 4/5 | 0/2 · 0/0 | near: jaccard 0.993, containment 1.0; media 4/5, overlap 1.0 |
| 41 | NEAR | **1381** · 2024-06-25 · WRONG (cohort A); true 2018-06-07  | **66430** · 2018-06-07 · correct (=2019 SQL) | How employers can prepare the workplace for cannab | 865/867 | 0/2 | 0/2 | 0/5 · 0/0 | near: jaccard 0.998, containment 1.0; media 0/2, overlap 0.0 |
| 42 | NEAR | **1384** · 2024-06-25 · WRONG (cohort A); true 2018-06-05  | **68580** · 2018-06-05 · correct (=2019 SQL) | The Science of Wildfires | 1713/1716 | 0/3 | 2/3 | 0/6 · 0/0 | near: jaccard 0.998, containment 1.0; media 2/3, overlap 1.0 |
| 43 | NEAR | **1390** · 2024-06-25 · WRONG (cohort A); true 2017-12-27  | **65402** · 2017-12-27 · correct (=2019 SQL) | 2017: The Fall of Patriarchy | 1179/1181 | 0/2 | 0/2 | 0/5 · 0/0 | near: jaccard 0.998, containment 1.0; media 0/2, overlap 0.0 |
| 44 | NEAR | **1398** · 2024-06-25 · WRONG (cohort A); true 2017-11-24  | **66055** · 2017-11-24 · correct (=2019 SQL) | Deafhood and the importance of learning a first la | 2043/2052 | 0/7 | 0/2 | 0/5 · 0/0 | near: jaccard 0.996, containment 1.0; media 0/2, overlap 0.0 |
| 45 | NEAR | **1407** · 2024-06-25 · WRONG (cohort A); true 2017-11-08  | **68384** · 2017-11-08 · correct (=2019 SQL) | Stiff fines ahead for property owners that don’t c | 388/414 | 0/24 | 0/2 | 0/7 · 0/0 | near: jaccard 0.936, containment 1.0; media 0/2, overlap 0.0 |
| 46 | NEAR | **1461** · 2020-02-12 · unverified (no 2019 row) | **67059** · 2020-02-12 · unverified (no 2019 row) | New Vancouver startup brings together flowers, peo | 200/276 | 0/92 | 0/2 | 0/3 · 0/0 | near: jaccard 0.721, containment 1.0; media 0/2, overlap 0.0 |
| 47 | NEAR | **1465** · 2024-06-25 · WRONG (cohort A) | **68641** · 2020-01-17 · unverified (no 2019 row) | Eco Eats is Changing the Way You Look at Food Wast | 296/364 | 0/81 | 2/3 | 0/3 · 0/0 | near: jaccard 0.811, containment 1.0; media 2/3, overlap 1.0 |
| 48 | NEAR | **1470** · 2024-06-25 · WRONG (cohort A) | **68733** · 2019-12-24 · unverified (no 2019 row) | TWG Has A New Mouth Watering Set Menu For The Holi | 398/475 | 0/93 | 4/5 | 0/4 · 0/0 | near: jaccard 0.833, containment 1.0; media 4/5, overlap 1.0 |
| 49 | NEAR | **1474** · 2024-06-25 · WRONG (cohort A) | **66403** · 2019-12-20 · unverified (no 2019 row) | Holiday High Tea at the New Secret Garden Tea Hous | 287/353 | 0/73 | 3/3 | 0/4 · 0/0 | near: jaccard 0.81, containment 1.0; media 3/3 identical |
| 50 | NEAR | **1478** · 2024-06-25 · WRONG (cohort A) | **69202** · 2019-12-11 · unverified (no 2019 row) | A New Bar is Set: Art x Craft is Vancouver’s New I | 578/691 | 0/122 | 2/4 | 0/4 · 0/0 | near: jaccard 0.838, containment 1.0; media 2/4, overlap 1.0 |
| 51 | NEAR | **1484** · 2024-06-25 · WRONG (cohort A) | **68601** · 2019-11-28 · unverified (no 2019 row) | The Vancouver Christmas Market is Now Open – Here  | 521/596 | 0/81 | 4/5 | 0/3 · 0/0 | near: jaccard 0.873, containment 1.0; media 4/5, overlap 1.0 |
| 52 | NEAR | **1488** · 2024-06-25 · WRONG (cohort A) | **65427** · 2019-10-18 · unverified (no 2019 row) | 5 Crazy Desserts in Vancouver You Need to Try Righ | 366/435 | 0/81 | 5/6 | 0/2 · 0/0 | near: jaccard 0.839, containment 1.0; media 5/6, overlap 1.0 |
| 53 | NEAR | **1492** · 2024-06-25 · WRONG (cohort A) | **68847** · 2019-03-07 · unverified (no 2019 row) | Vancouver’s Newest Cannabis Infused Pop-Up Dinner  | 159/181 | 0/29 | 2/5 | 0/2 · 0/0 | near: jaccard 0.881, containment 1.0; media 2/5, overlap 1.0 |
| 54 | NEAR | **1496** · 2024-06-25 · WRONG (cohort A); true 2019-02-11  | **68205** · 2019-02-11 · correct (=2019 SQL) | Science of Cocktails experience brings together ba | 343/368 | 0/33 | 4/7 | 0/4 · 0/0 | near: jaccard 0.933, containment 1.0; media 4/7, overlap 1.0 |
| 55 | NEAR | **1503** · 2024-06-25 · WRONG (cohort A); true 2019-01-21  | **68339** · 2019-01-21 · correct (=2019 SQL) | Some are born sweet, some achieve sweetness, and s | 685/734 | 0/55 | 3/5 | 0/7 · 0/0 | near: jaccard 0.934, containment 1.0; media 3/5, overlap 1.0 |
| 56 | NEAR | **1530** · 2019-05-24 · unverified (no 2019 row) | **67123** · 2019-05-24 · unverified (no 2019 row) | Matilda the Musical Brings Depth, Joy, and Nasty F | 462/475 | 0/13 | 3/5 | 0/2 · 0/0 | near: jaccard 0.972, containment 1.0; media 3/5, overlap 1.0 |
| 57 | NEAR | **1534** · 2019-03-27 · unverified (no 2019 row) | **67436** · 2019-03-27 · unverified (no 2019 row) | “Pathways” delivers evocative performance with que | 470/472 | 0/2 | 0/1 | 0/6 · 0/0 | near: jaccard 0.996, containment 1.0; media 0/1, overlap 0.0 |
| 58 | NEAR | **1539** · 2019-03-27 · unverified (no 2019 row) | **68611** · 2019-03-27 · unverified (no 2019 row) | The weird and wonderful puppetry of Multiple Organ | 721/723 | 0/2 | 0/2 | 0/4 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/2, overlap 0.0 |
| 59 | NEAR | **1543** · 2019-03-20 · unverified (no 2019 row) | **66419** · 2019-03-20 · unverified (no 2019 row) | Hot Brown Honey delivers anti-patriarchy deprogram | 646/648 | 0/2 | 0/2 | 0/3 · 0/0 | near: jaccard 0.997, containment 1.0; media 0/2, overlap 0.0 |
| 60 | NEAR | **1547** · 2019-02-14 · correct (=2019 SQL) | **66804** · 2019-02-14 · correct (=2019 SQL) | JFL NorthWest Comedians talk Context in Comedy | 826/846 | 190/18 | 3/4 | 0/4 · 0/0 | near: jaccard 0.975, containment 1.0; media 3/4, overlap 0.667 |
| 61 | NEAR | **1551** · 2019-02-11 · correct (=2019 SQL) | **68960** · 2019-02-11 · correct (=2019 SQL) | Who is Clark Rockefeller? In True Crime, Torquil C | 924/930 | 0/6 | 0/2 | 0/3 · 0/0 | near: jaccard 0.994, containment 1.0; media 0/2, overlap 0.0 |
| 62 | NEAR | **1555** · 2019-02-09 · correct (=2019 SQL) | **66417** · 2019-02-09 · correct (=2019 SQL) | Hooray for PJ Masks Live Performance at the Queen  | 364/384 | 0/18 | 0/2 | 0/5 · 0/0 | near: jaccard 0.947, containment 1.0; media 0/2, overlap 0.0 |
| 63 | NEAR | **1559** · 2019-02-06 · correct (=2019 SQL) | **68591** · 2019-02-06 · correct (=2019 SQL) | The Spirit of Revolt in Pancho Villa from a Safe D | 672/684 | 0/12 | 0/2 | 0/5 · 0/0 | near: jaccard 0.982, containment 1.0; media 0/2, overlap 0.0 |
| 64 | NEAR | **1563** · 2019-02-01 · correct (=2019 SQL) | **68551** · 2019-02-01 · correct (=2019 SQL) | The Matchmaker offers a few inspired moments of sl | 575/581 | 0/6 | 0/1 | 0/5 · 0/0 | near: jaccard 0.99, containment 1.0; media 0/1, overlap 0.0 |
| 65 | NEAR | **1567** · 2019-01-12 · correct (=2019 SQL) | **68117** · 2019-01-12 · correct (=2019 SQL) | Ric Flair reveals little in Untold Tales of Pro Wr | 546/580 | 0/38 | 2/5 | 0/0 · 0/0 | near: jaccard 0.944, containment 1.0; media 2/5, overlap 1.0 |
| 66 | NEAR | **1571** · 2018-12-13 · correct (=2019 SQL) | **68153** · 2018-12-13 · correct (=2019 SQL) | Rupi Kaur invites Vancouver to break all emotional | 680/708 | 0/31 | 0/1 | 0/6 · 0/0 | near: jaccard 0.962, containment 1.0; media 0/1, overlap 0.0 |
| 67 | NEAR | **1576** · 2024-06-25 · WRONG (cohort A); true 2019-01-09  | **68269** · 2019-01-09 · correct (=2019 SQL) | Shining Fresh Seafood in the Vibrant Neighborhood  | 577/645 | 0/77 | 7/9 | 0/4 · 0/0 | near: jaccard 0.894, containment 1.0; media 7/9, overlap 1.0 |
| 68 | NEAR | **1585** · 2024-06-26 · WRONG (cohort A); true 2018-12-21  | **65440** · 2018-12-21 · correct (=2019 SQL) | 7 Ways to Get Your Holiday Spirit on at the Vancou | 256/291 | 0/41 | 7/9 | 0/197 · 0/0 | near: jaccard 0.879, containment 1.0; media 7/9, overlap 1.0 |
| 69 | NEAR | **1591** · 2024-06-26 · WRONG (cohort A); true 2018-12-14  | **66288** · 2018-12-14 · correct (=2019 SQL) | Get a Real Taste of the Islands at this Jamaican D | 343/390 | 0/55 | 0/2 | 0/199 · 0/0 | near: jaccard 0.878, containment 1.0; media 0/2, overlap 0.0 |
| 70 | NEAR | **1596** · 2024-06-26 · WRONG (cohort A); true 2018-12-17  | **66442** · 2018-12-17 · correct (=2019 SQL) | How to Find Your Inner German at the Vancouver Chr | 308/356 | 0/55 | 1/4 | 0/199 · 0/0 | near: jaccard 0.866, containment 1.0; media 1/4, overlap 1.0 |
| 71 | NEAR | **1600** · 2024-06-26 · WRONG (cohort A); true 2018-12-19  | **65933** · 2018-12-19 · correct (=2019 SQL) | Colony Bars Holiday Eggnog Arrives in the City for | 238/300 | 0/70 | 2/4 | 0/191 · 0/0 | near: jaccard 0.789, containment 1.0; media 2/4, overlap 1.0 |
| 72 | NEAR | **1606** · 2024-06-26 · WRONG (cohort A) | **68211** · 2018-12-10 · correct (=2019 SQL) | Seasonal Splendour Spews Through the Shaughnessy E | 407/469 | 0/70 | 2/4 | 0/183 · 0/0 | near: jaccard 0.866, containment 1.0; media 2/4, overlap 1.0 |
| 73 | NEAR | **1618** · 2024-06-26 · WRONG (cohort A); true 2018-11-30  | **68635** · 2018-11-30 · correct (=2019 SQL) | This Chemistry-Themed Cafe is where Art and Chemis | 301/365 | 0/74 | 2/4 | 0/182 · 0/0 | near: jaccard 0.822, containment 1.0; media 2/4, overlap 1.0 |
| 74 | NEAR | **1622** · 2024-06-26 · WRONG (cohort A); true 2018-11-21  | **65468** · 2018-11-21 · correct (=2019 SQL) | A monthly cereal bar pop-up is bringing a heavy do | 374/406 | 0/40 | 2/4 | 0/179 · 0/0 | near: jaccard 0.92, containment 1.0; media 2/4, overlap 1.0 |
| 75 | NEAR | **1626** · 2024-06-26 · WRONG (cohort A); true 2018-11-28  | **65777** · 2018-11-28 · correct (=2019 SQL) | Break your fast, not your wallet | 338/387 | 0/62 | 1/3 | 0/159 · 0/0 | near: jaccard 0.872, containment 1.0; media 1/3, overlap 1.0 |
| 76 | NEAR | **1628** · 2020-04-17 · unverified (no 2019 row) | **66007** · 2020-04-17 · unverified (no 2019 row) | “Crash Landing On You”: a tale of forbidden but pr | 564/584 | 0/18 | 0/2 | 0/1 · 0/0 | near: jaccard 0.966, containment 1.0; media 0/2, overlap 0.0 |
| 77 | NEAR | **1813** · 2024-06-27 · WRONG (cohort A) | **65854** · 2021-04-22 · unverified (no 2019 row) | Cat Cohen Showcases Life as a Gal About Town in De | 475/475 | 0/0 | 0/1 | 0/199 · 0/0 | near: text identical, media differ (media 0/1, overlap 0.0) |
| 78 | NEAR | **921** · 2024-06-24 · WRONG (cohort A); true 2016-04-18  | **66437** · 2016-04-18 · correct (=2019 SQL) | How Sun Grown Cultivation of Medicinal Marijuana W | 940/968 | 0/28 | 1/4 | 0/6 · 0/0 | near: jaccard 0.967, containment 0.998; media 1/4, overlap 1.0 |
| 79 | NEAR | **1510** · 2019-10-30 · DIFFERS from 2019 SQL 2017-10-30 0 | **68459** · 2017-10-30 · correct (=2019 SQL) | Tegan and Sara celebrate 10 years of ‘The Con,’ su | 737/745 | 0/6 | 0/2 | 0/6 · 0/0 | near: jaccard 0.978, containment 0.995; media 0/2, overlap 0.0 |
| 80 | NEAR | **1373** · 2024-06-25 · WRONG (cohort A); true 2018-03-14  | **66298** · 2018-03-14 · correct (=2019 SQL) | Global Citizen announces its first-ever Vancouver  | 593/628 | 0/50 | 2/4 | 0/4 · 0/0 | near: jaccard 0.931, containment 0.993; media 2/4, overlap 1.0 |
| 81 | NEAR | **335** · 2017-03-13 · correct (=2019 SQL) | **66434** · 2017-02-09 · DIFFERS from 2019 SQL 2017-03-13 1 | How science is helping this nerd make better beer | 584/602 | 8/24 | 2/4 | 0/0 · 0/0 | near: jaccard 0.918, containment 0.974; media 2/4, overlap 1.0 |
| 82 | NEAR | **357** · 2016-12-27 · correct (=2019 SQL) | **65391** · 2016-09-15 · DIFFERS from 2019 SQL 2016-12-27 0 | 11 Hidden Furniture Store Gems In Vancouver | 1568/1589 | 18/37 | 11/11 | 0/2 · 0/0 | near: jaccard 0.934, containment 0.972; media 11/11 identical |
| 83 | NEAR | **66473** · 2015-02-02 · correct (=2019 SQL) | **67280** · 2015-02-06 · WRONG (cohort B); true 2015-02-02  | Ian Vanek and the art of motorcycle maintenance | 1368/809 | 596/36 | 5/4 | 0/0 · 0/0 | near: jaccard 0.547, containment 0.953; media 5/4, overlap 0.5 |
| 84 | NEAR | **69085** · 2018-01-12 · correct (=2019 SQL) | **69142** · 2017-01-02 · correct (=2019 SQL) | Win Tickets to The Taboo Naughty but Nice Sex Show | 119/159 | 26/64 | 3/4 | 0/0 · 0/0 | partial: jaccard 0.358, containment 0.607; media 3/4, overlap 0.333 |
| 85 | TITLE-ONLY | **353** · 2017-02-16 · correct (=2019 SQL) | **66449** · 2017-02-16 · correct (=2019 SQL) | How to make all your social media posts truly priv | 56/58 | 0/2 | 0/1 | 0/3 · 0/0 | title-only (short body): containment 1.0; media 0/1, overlap 0.0 |
| 86 | TITLE-ONLY | **66177** · 2012-05-14 · WRONG (cohort B) | **67229** · 2012-05-24 · WRONG (cohort B) | Trampled by Turtles | 75/19 | 52/14 | 2/2 | 0/0 · 0/0 | title-only (short body): containment 0.133; media 2/2, overlap 0.0 |
| 87 | TITLE-ONLY | **5066** · 2012-11-26 · correct (=2019 SQL) | **5206** · 2012-12-03 · correct (=2019 SQL) | This Week’s Hot Shows | 430/492 | 363/426 | 8/6 | 0/0 · 0/0 | title-only: different photo sets; text jaccard 0.013; media 8/6, overl |
| 88 | TITLE-ONLY | **5356** · 2012-12-11 · correct (=2019 SQL) | **5433** · 2012-12-17 · correct (=2019 SQL) | Top 5 Hot Shows This Week | 742/822 | 666/747 | 7/6 | 0/0 · 0/0 | title-only: different photo sets; text jaccard 0.005; media 7/6, overl |
| 89 | TITLE-ONLY | **4919** · 2012-11-13 · correct (=2019 SQL) | **5066** · 2012-11-26 · correct (=2019 SQL) | This Week's Hot Shows | 441/430 | 427/408 | 8/8 | 0/0 · 0/0 | title-only: different photo sets; text jaccard 0.0; media 8/8, overlap |
| 90 | TITLE-ONLY | **4919** · 2012-11-13 · correct (=2019 SQL) | **5206** · 2012-12-03 · correct (=2019 SQL) | This Week's Hot Shows | 441/492 | 418/462 | 8/6 | 0/0 · 0/0 | title-only: different photo sets; text jaccard 0.0; media 8/6, overlap |

Reading it:
- The **uniq 0** side is the plain copy; the other copy adds chrome.
- In the 38 A-involved pairs, the A copy is always the 2024 re-entry with the wrong date. Its twin carries the correct (or twin-consistent) date.
- The date cut: if the A copy survives, it takes the twin's date; if the twin survives, no date fix is needed.
- The URL cut: the A slugs are the ones Wayback has seen since 2024 and the author links to, while the twin slugs are the originals.

## List 3: published posts dated on or after 2020-03-01 (119)

- **Allowlist (confirmed genuine):** 65340 AIR "Moon Safari" (2024-09-29) and 65350 Sigur Rós (2025-11-05).
- **Cohort A, 46 posts, all wrong** (2024-06-14 to 06-27). Their proposed dates are in List 1 / 1b. IDs: 629, 632, 657, 660, 663, 667, 669, 904, 910, 921, 937, 948, 956, 977, 986, 992, 1007, 1016, 1021, 1373, 1376, 1381, 1384, 1390, 1398, 1407, 1465, 1470, 1474, 1478, 1484, 1488, 1492, 1496, 1503, 1576, 1580, 1585, 1591, 1596, 1600, 1606, 1618, 1622, 1626, 1813.

**Please scan these 71 for "the few more".** They are not in any cohort. No 2019 source can check them, and the audit found their dates internally consistent (agency-DB and Wayback page-date lineage). Rows marked *twin* have a content-duplicate partner in List 2 with the same date.

| ID | Current date | Title | Author | Twin |
|---|---|---|---|---|
| 1180 | 2020-03-05 | There’s a Pussy Riot Goin’ On | 44 |  |
| 67034 | 2020-03-06 | Lie Exposed doesn’t expose much | 1 |  |
| 68209 | 2020-03-10 | ScreenTime Junkies | 1 | 657 @ 2024-06-14 (NEAR; WRONG (cohor |
| 65937 | 2020-03-12 | Come and See refuses to relent | 1 |  |
| 1175 | 2020-03-16 | An interview with Josh Bogert, the king of melodic dubstep | 174 |  |
| 66424 | 2020-03-18 | Hotel Mira prove their boundaries are limitless with latest album ‘Per | 1 |  |
| 69164 | 2020-03-24 | Wire: punk and post-punk veterans still sharp after 18 albums | 1 |  |
| 68708 | 2020-03-26 | Trapped in Vivarium | 1 |  |
| 1058 | 2020-04-01 | 25 years of big choices | 229 |  |
| 1068 | 2020-04-01 | Big trouble in The Prairies for BC’s Small Town Artillery | 44 |  |
| 999 | 2020-04-02 | Vancouver’s Hello Victim want you to feel uncomfortable with their lat | 225 |  |
| 1054 | 2020-04-02 | Play it like it’s your last show – Andrew Seward of Against Me! | 229 | 67936 @ 2020-04-02 (NEAR; unverified |
| 67936 | 2020-04-02 | Play it like it’s your last show – Andrew Seward of Against Me! | 1 | 1054 @ 2020-04-02 (NEAR; unverified  |
| 68755 | 2020-04-04 | Unplugged and Online: Something for the fans | 1 |  |
| 65859 | 2020-04-06 | Cave Rescue is a timely reminder of human power and compassion | 315 |  |
| 68650 | 2020-04-08 | Throwing rocks against the cave wall | 1 |  |
| 66569 | 2020-04-09 | “Itaewon Class” offers heartfuls of soft tofu soup | 1 |  |
| 65531 | 2020-04-11 | Algiers cooks up a revolution-ready album in There is No Year | 283 |  |
| 1628 | 2020-04-17 | “Crash Landing On You”: a tale of forbidden but prevailing love | 140 | 66007 @ 2020-04-17 (NEAR; unverified |
| 66180 | 2020-04-17 | Everything certainly is possible with Enter Shikari | 1 |  |
| 66007 | 2020-04-17 | “Crash Landing On You”: a tale of forbidden but prevailing love | 1 | 1628 @ 2020-04-17 (NEAR; unverified  |
| 555 | 2020-04-20 | No World for Tomorrow: Looking back and looking ahead with Coheed’s Cl | 229 |  |
| 68653 | 2020-04-21 | Tiger King: A ‘cat-eat-cat’ world | 1 |  |
| 579 | 2020-04-24 | Vancouver’s Hollow Twin share new single “Before I Leave” | 229 |  |
| 551 | 2020-05-01 | Vancouver’s Ludic release new single “Love Me Like” | 229 |  |
| 546 | 2020-05-06 | Local duo Fionn keep busy from home | 1 |  |
| 542 | 2020-05-08 | Fake Shark create new video despite social isolation | 1 |  |
| 66530 | 2020-05-12 | Introducing Tambino and his debut EP | 1 |  |
| 67150 | 2020-05-12 | Melo makes a powerful solo debut with ‘Old Master’ | 361 |  |
| 66057 | 2020-05-16 | Dear Father’s new EP is sad, and for a good cause | 1 |  |
| 487 | 2020-05-30 | Bute Street rock some familiar subjects with ‘Sunny Days Hazy Nights’ | 225 |  |
| 66981 | 2020-06-03 | Lady Gaga’s ‘Chromatica’ is extravagant and personal | 1 |  |
| 67041 | 2020-06-09 | Lil Yachty’s personality still shines with ‘Lil Boat 3’ | 1 |  |
| 68152 | 2020-06-11 | Run The Jewels’ ‘RTJ4’ is the soundtrack to a revolution | 1 |  |
| 1351 | 2020-06-17 | Hollow Twin Get Personal With New Single | 1 |  |
| 68849 | 2020-06-19 | Vancouver’s Noble Son sugarcoats nothing on ‘Life Isn’t Fun’ | 1 |  |
| 529 | 2020-06-20 | Premiere – The Long War release music video for “Endless Summer” | 229 | 68544 @ 2020-06-20 (NEAR; unverified |
| 68544 | 2020-06-20 | Premiere – The Long War release music video for “Endless Summer” | 1 | 529 @ 2020-06-20 (NEAR; unverified ( |
| 966 | 2020-06-22 | Trail of Dead announce upcoming livestream benefiting independent venu | 1 |  |
| 1346 | 2020-06-22 | Elle Wolf takes new direction with upcoming single | 1 |  |
| 1341 | 2020-06-23 | Cassidy Waring looking forward to a big 2020 | 1 |  |
| 1231 | 2020-06-28 | Q&A with Canadian Folk Musician Andrew Collins | 168 |  |
| 524 | 2020-06-29 | Punk rock therapy for the community | 229 |  |
| 572 | 2020-06-30 | BLACKPINK turns up with glowing colors in MV “How You Like That” | 1 |  |
| 521 | 2020-07-16 | Dan Mangan on the new normal of live shows and revisiting a very impor | 229 |  |
| 567 | 2020-07-22 | BTS makes an explosive comeback with “Dynamite | 1 |  |
| 475 | 2020-07-28 | Vancouver’s Riun Garner aims to inspire with EP ‘All We Know and All W | 225 |  |
| 941 | 2020-07-31 | Premiere – Dear Father shares new song “Haunt Me” | 225 |  |
| 952 | 2020-07-31 | Ontario’s Dizzy take introspective approach on sophomore album | 225 | 67979 @ 2020-07-31 (NEAR; unverified |
| 67979 | 2020-07-31 | Ontario’s Dizzy take introspective approach on sophomore album | 1 | 952 @ 2020-07-31 (NEAR; unverified ( |
| 918 | 2020-08-03 | Le Ren delivers heart wrenching debut EP with ‘Morning & Melancholia’ | 229 |  |
| 463 | 2020-08-06 | Vancouver’s Noble Oak frees the mind with label debut | 225 |  |
| 458 | 2020-08-11 | Toronto-based singer-songwriter Alex Southey delivers raw emotion on s | 225 |  |
| 902 | 2020-08-22 | Adapting to Live Streams in the “Now Times” | 214 |  |
| 500 | 2020-08-26 | Vancouver’s Haley Blais shares background on debut full-length album ‘ | 1 |  |
| 561 | 2020-09-11 | Vancouver’s Riun Garner shares live performance of “Wrote Myself Off” | 225 |  |
| 219 | 2020-09-15 | BC’s baby whisperer keeps families entertained during pandemic | 229 |  |
| 213 | 2020-09-25 | Chilliwack’s Dear Father debuts new single | 225 |  |
| 216 | 2020-09-25 | Will Butler brings chaos to light and light to the chaos on ‘Generatio | 233 |  |
| 210 | 2020-09-28 | Vancouver’s IAMTHELIVING and Teon Gibbs release new single | 225 |  |
| 203 | 2020-09-29 | Paul Caldwell’s newest EP is the comfort you need in these dire days | 174 |  |
| 183 | 2020-10-17 | Vancouver’s Jillian Lake releases new single “Walk All Over You” | 225 |  |
| 178 | 2020-10-20 | Vancouver’s Michaela Slinger talks new single and upcoming album | 1 |  |
| 175 | 2020-10-21 | Premiere – Vancouver’s Red Herring release new single “Julia” | 225 |  |
| 195 | 2020-11-25 | The Long War’s valiant battle continues with emotional new single | 229 |  |
| 166 | 2020-12-04 | Waiting in line with 18 to Party | 24 |  |
| 96 | 2020-12-11 | Local Electronic Duo Recognized For Bringing The Funk To Canadians | 229 | 67053 @ 2020-12-11 (NEAR; unverified |
| 67053 | 2020-12-11 | Local electronic duo recognized for bringing the funk to Canadians | 1 | 96 @ 2020-12-11 (NEAR; unverified (n |
| 163 | 2021-03-12 | From the punk rock ashes: Rest Easy releases impressive debut | 229 |  |
| 65854 | 2021-04-22 | Cat Cohen Showcases Life as a Gal About Town in Debut Poetry Collectio | 1 | 1813 @ 2024-06-27 (NEAR; WRONG (coho |
| 187 | 2021-07-29 | Premiere – Vancouver’s Larissa Tandy releases new single “No Fun” | 225 |  |

The upload-path months on a few of these (2014-03, 2015-12, 2018-10) come from reused images such as the logo, not from when the post was written. They are not treated as signals.

## Guards for the execute round

- Input is the CSV/JSON above. Re-check each row's current `post_date` against the file before writing, and skip the row on any drift.
- Write `post_date` and `post_date_gmt` together; the 2019 values are both in the CSV. Never touch the slug: `/%postname%/` is frozen, so date changes do not affect URLs.
- Delete `_vw_front_suppress` only on posts actually fixed. Purge after each batch.
- De-dup retires to draft with a flag and adds a 301. It never deletes. Only pairs you mark go through it.
