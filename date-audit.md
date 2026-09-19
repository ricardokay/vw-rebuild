# Post-date audit (read-only), 2026-09-18

**Scope.** All **3,088** published `post` rows on the server (`bmmmaster@138.197.142.198`, `wptg_posts`), read 2026-09-18. Nothing was written to any database. Working files stayed in the session scratchpad.

## Verdict

**The hypothesis is only partly right.** There are two separate date problems, with different causes:

| Cohort | Posts | What is wrong | Cause | Launch-caused? |
|---|---:|---|---|---|
| **A: June 2024 re-entries** | **46** | Dated 2024-06-14 to 06-27; the true dates run from 2013 to 2019 | The **2024 agency re-typed these posts by hand** into a fresh install (`vancouverweekly4.test`). WordPress stamped each one with the day it was typed. This was **not** a file restore. | **No.** Inherited 2024 damage |
| **B: Wayback-import fallback** | **528** | Dated 0 to 732 days **later** than the truth (median 8 days; 160 are more than 30 days late, 70 are in the wrong year, 38 are more than a year late) | **This rebuild's June 2026 Wayback importer.** `import_to_wordpress.py:164-177` falls back to the Wayback **capture timestamp** when a page has no readable date. 1,005 of 2,986 recovered pages had none. | **No.** It is not a launch or staging problem, but it **is our own 2026 pipeline, not 2024 damage** |
| Two other 2024+ posts | 2 | 65340 (2024-09-29, the Air "Moon Safari" show) and 65350 (2025-11-05, Sigur Rós) | They look like genuine new posts | Probably correct. **Ricardo to confirm** |

**About 574 published posts carry a wrong date (18.6% of 3,088).** The rest checks out. The Wayback page-date imports (1,169 of 1,172 match to the day) and the agency-DB lineage (1,082 of 1,086) agree with the Feb 2019 database.

**Best true-date source: the Feb 2019 production SQL** (`~/Downloads/vanctcjx_vweekly2016a.sql`, dumped 2019-02-15, 2,960 published posts; the same data is in `vanctcjx_vweekly2016a_feb2019.sql.zip` in the repo root). This is the original site's own `wp_posts`, with `post_date` and `post_date_gmt` to the second. **It can fix 550 of the 574 posts:** 33 in A and 517 in B.

- It confirms both known cases:
  - "2017: The Fall of Patriarchy": **2017-12-27 09:07:09** (author said about Dec 2017).
  - "Inside the Incubator": **2015-04-09 19:34:58** (author said 2015).
- It cannot date anything published after 2019-02-14.

**An automated batch repair is feasible for those 550**, using the 2019 SQL as the only source. The remaining 24 (13 in A, 11 in B) need month-level or manual dating (see below).

## 1. The two confirmed posts

| | 1390 `2017-the-fall-of-patriarchy` | 1016 `inside-the-incubator-…` |
|---|---|---|
| post_date / _gmt | 2024-06-25 05:15:59 / same | 2024-06-24 05:41:30 / same |
| post_modified | 2024-08-01 06:27:36 | 2024-08-01 06:07:58 |
| status, author | publish, 159 | publish, 2 |
| guid | `http://vancouverweekly4.test/?p=1390` | `http://vancouverweekly4.test/?p=1016` |
| postmeta (all) | `_eael_post_view_count` 468, `_edit_last` 1, `_edit_lock` 1722493581:1, `_elementor_page_assets` a:0:{}, `_thumbnail_id` 1394, `_wp_page_template` default, `ekit_post_views_count` 256 | `_eael_post_view_count` 456, `_edit_last` 1, `_edit_lock` 1722492501:1, `_elementor_page_assets` a:0:{}, `_thumbnail_id` 1019, `_wp_page_template` default, `ekit_post_views_count` 248 |
| Revisions and attachments | rev 1391 06-25 05:15:59, rev 1393 05:17:02, att 1394 05:17:02, rev 1395 05:17:06, rev 64878 2024-07-31 06:27:42, rev 64980 2024-08-01 06:27:36 | att 1018 06-24 05:41:03, att 1019 05:41:26, rev 1020 05:41:30, rev 64953 2024-08-01 06:07:36, rev 64954 06:07:58 |
| Comments | 5, all pending spam, 2024-12-24 → 2026-05-29 | 4, all pending spam, 2025-09-25 → 2026-03-06 |
| True date (2019 SQL) | **2017-12-27 09:07:09**, original ID 59519, slug `2017-the-fall-of-patriarchy-59519-2` | **2015-04-09 19:34:58**, original ID 44532, slug `a-look-into-incubator-accelerator-programs-in-b-c` |
| Already live as a twin? | **Yes: 65402, dated 2017-12-27 09:07:09 (correct)** | **Yes: 65465, dated 2015-05-18** (cohort B, 39 days late) |

Neither post has a legacy or original-date field: no `_wp_old_date`, no import timestamp, no `dsq_*`.

## 2. Scope: published post dates by year and month (3,088)

| Year | Jan | Feb | Mar | Apr | May | Jun | Jul | Aug | Sep | Oct | Nov | Dec | Total |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 2010 | · | · | · | · | · | · | · | · | · | · | 1 | · | 1 |
| 2011 | · | · | · | · | · | · | · | · | · | · | 1 | 2 | 3 |
| 2012 | 7 | 9 | 30 | 37 | 33 | 38 | 35 | 65 | 82 | 68 | 41 | 36 | 481 |
| 2013 | 32 | 63 | 65 | 50 | 47 | 53 | 65 | 53 | 57 | 58 | 41 | 20 | 604 |
| 2014 | 25 | 6 | 43 | 40 | 41 | 29 | 27 | 21 | 31 | 17 | 6 | 19 | 305 |
| 2015 | 13 | 28 | 23 | 11 | 25 | 16 | 48 | 43 | 31 | 78 | 48 | 31 | 395 |
| 2016 | 24 | 21 | 18 | 26 | 27 | 28 | 27 | 26 | 39 | 16 | 26 | 19 | 297 |
| 2017 | 24 | 30 | 23 | 22 | 16 | 10 | 32 | 29 | 39 | 57 | 43 | 28 | 353 |
| 2018 | 31 | 43 | 33 | 25 | 22 | 27 | 24 | 17 | 39 | 20 | 26 | 27 | 334 |
| 2019 | 15 | 28 | 23 | 9 | 13 | 14 | 11 | 7 | 4 | 23 | 18 | 5 | 170 |
| 2020 | 11 | 15 | 8 | 16 | 7 | 13 | 6 | 5 | 6 | 3 | 1 | 3 | 94 |
| 2021 | · | · | 1 | 1 | · | · | 1 | · | · | · | · | · | 3 |
| 2024 | · | · | · | · | · | **46** | · | · | 1 | · | · | · | 47 |
| 2025 | · | · | · | · | · | · | · | · | · | · | 1 | · | 1 |

**Most common exact `post_date`:** every value occurs once. There are no duplicate timestamps anywhere, so this was **not** a batch that stamped one time on every row.

**Busiest days:** 2015-10-12 (21), 2024-06-25 (17), 2015-11-06 (15), 2014-03-17 (14), 2024-06-24 (12), 2014-09-19 (12), 2014-04-06 (11), 2019-10-24 (11), then ten days with 10 each. The 2015-10 and 2015-11 spikes are cohort B: Wayback crawl days, not publishing days.

**The June 2024 cluster: 46 published posts**

| Day | Posts | Time span | ID range |
|---|---:|---|---|
| 2024-06-14 | 7 | 06:10–08:12 | 629–669 |
| 2024-06-24 | 12 | 04:43–05:41 | 904–1021 |
| 2024-06-25 | 17 | 05:03–11:27 | 1373–1576 |
| 2024-06-26 | 9 | 04:50–05:44 | 1580–1626 |
| 2024-06-27 | 1 | 06:55 | 1813 |

Other 2024 rows are not published: 1 draft on 2024-06-13 and **31 drafts on 2024-07-20**.

## 3. True-date sources, scored against the 2019 SQL as ground truth

The accuracy columns are measured on posts that exist in the 2019 SQL: 33 in A and 513 in B. They cover the full cohorts, not only a sample. "Coverage" is the share of affected posts the source can date at all.

| Source | Coverage in A (46) | Coverage in B (528) | Reliability | Notes |
|---|---|---|---|---|
| **e′. Feb 2019 production SQL** | **33 (72%)** | **517 (98%)** | **Exact to the second.** It is the original database | A matches by title for 25 and by slug for 8, because the agency renamed slugs. B matches by slug for 516 and by title for 1. It covers nothing after 2019-02-14 |
| Live twin already published | 22 of the 33 | n/a | Same as the 2019 SQL | The Wayback import brought the original back with its true date, so these 22 are **duplicate pairs**, part of the ~104 known pairs |
| c. Upload path `wp-content/uploads/YYYY/MM` in the body | 27 (59%) | 520 (98%) | Month level. A: 18/18 correct month. B: 381/511 (74%) correct month, 400 correct year | The month an image was uploaded. It is wrong when an older image is reused. Good as a fallback, not as the main source |
| c. Year in slug or title | 2 | 84 | Year only, and often a topic year ("Best of 2015" was posted 2015-12-31) | A sanity check only |
| a. Postmeta (`_wp_old_date`, import or migration keys) | 0 | 0 | none | 98 published posts have `_wp_old_date`, none of them affected. None carries an original-date key. `_vw_import_path` is a file path, not a date |
| b. Disqus `dsq_thread_id` / earliest comment | 0 / 0 | 0 / 0 | none for these cohorts | Comments on affected posts are all 2024–2026 pending spam. Legacy approved comments and 2,098 `dsq_thread_id`s exist only on agency-DB-lineage posts |
| d. guid / URL date | 0 | 0 | none | A guids are `vancouverweekly4.test/?p=N`. Permalinks have no date segment |
| e. Off-site dump `vw-db-20260918.sql.gz` | same wrong dates | same wrong dates | not a source | **3,088/3,088 `post_date` are identical to live.** The earliest local dump (2026-06-16) is identical too, 3,088/3,088 |
| f. Wayback earliest capture (CDX) | usable only if the slug is original | **circular** | Only a "published on or before" bound | See the sample below. The API is reachable from this Mac and from the server (slow; one timeout on retry) |

**Wayback sample (20 posts)**

A, current slug:
- 663: capture 2018-07-17, truth 2018-07-17. The same day.
- 629: 2019-07-18, truth 2019-01-28.
- 667: 2017-10-08, truth 2013-09-20.
- 1813: 2021-04-22. There is no truth; it is the only bound available.
- 1470, 1484, 657, 660: captures from 2024 or later only.

A, renamed slug (1390, 1016, 1600):
- The current slug gives captures from 2024 or later only.
- The **original** slug gives 2018-03-03, 2015-05-18 and 2019-07-21. These are 2 to 7 months late, and the original slug itself comes from the 2019 SQL.

B (9 posts, 2013–2016):
- The earliest capture **equals the current wrong date in 9/9**. It is the timestamp the importer already used, so it adds nothing.

**Combining sources adds little.** After the 2019 SQL:
- In A, the upload path dates 9 of the remaining 13 to the month. Wayback bounds 1 more (1813). **4 have no signal:** 1465, 992, 657, 1813.
- In B, the 11 left over are mostly navigation pages imported as posts (`advertise`, `contributor-kit`, `events`, `film`, `jobs`, `music`, `past`, `signup-form`, `theatre`, `upcoming`), plus `backstreet-boys-extend-tour`, which the upload path puts in the same month as its current date.

## 4. Recovery fingerprint (cohort A)

- **Hand entry, not a restore.** IDs 629–1813 are low and sequential. Attachments and revisions are interleaved with them, seconds apart (for example, att 1018 → att 1019 → post 1016 → rev 1020 within 27 s). Sessions run from about 04:40 to 08:10 on five days. Featured images were uploaded again at entry time.
- **Agency environment.** The guid host is `vancouverweekly4.test`, `_edit_last = 1` (admin), and every post carries Elementor, EAEL and ElementsKit meta. **Slugs were changed:** 25 of 33 match the original only by title (for example, `…-59519-2` became `2017-the-fall-of-patriarchy`).
- **A batch touch afterwards.** `post_modified` is 2024-08-01 for 45 posts and 2024-07-31 for 1, the same days as extra revisions. That was a bulk re-save by the agency, not a re-insert.
- **It is the 2024 agency work.** This lines up with the Elementor homepage (page 9, created 2024-06-10) and the 31 drafts of 2024-07-20. None of our 2026 dumps changes these rows.

**Cohort B fingerprint:** `post_date` equals `post_date_gmt`, which equals the Wayback CDX timestamp to the second (524 exactly; the other 4 used a different snapshot). The recovery JSON has `data.date == ""`. IDs are in the 65000+ range created on 2026-06-13. Every one is later than the truth; none is earlier.

## 5. Repair feasibility (not done)

- **550 posts:** copy `post_date` and `post_date_gmt` from the 2019 SQL, keyed on the match above.
  - B (517) is keyed on an exact slug and is mechanical.
  - A (33) is keyed on title, so it needs Ricardo to review the list.
  - The dry-run manifest is the 2019 SQL join.
- **Editorial decision first, for 22 A posts:** redating them makes exact duplicate pairs with the correctly dated twin. The alternative is to retire the 2024 copy, the same way as B1. These two options do not have the same URL outcome. The 2024 slugs are the ones Wayback has seen since 2024 (and the ones the author was looking at).
- **24 posts:** set the month from the upload path (9 in A, about 10 in B), leave the 4 with no signal, and have the author or Ricardo confirm. The B navigation pages are probably not articles at all.
- **Minor noise outside A and B:** 7 rows where the 2019 SQL disagrees (for example, 1510 Tegan and Sara 2019-10-30 against 2017-10-30, and 65391 against a rescheduled original). Review them by hand; they are not counted in the 574.
