# FB Gallery Migration — Gap-Check of the 2026-09-17 Export

**Date:** 2026-09-18
**Scope:** Read-only. No DB writes, no imports, no media extracted. Neither zip deleted.
**Question:** does the second Meta export contain the 2019–2020 albums the June export was missing?

**Answer: no.** The September export's albums are the same 563 as June's: same names, same dates, same photo counts, same media. It has **14 albums dated 2019 or later, exactly as June did**, and the confident match rate against the 261 retired posts is unchanged at **54/261 (20.7%)**. The 2019–2020 gap is not closed.

---

## 1. The two exports

| | June (`…2026-06-18-54FRaXvE.zip`) | September (`…2026-09-17-wsUk5azg.zip`) |
|---|---|---|
| Zip bytes | 2,343,749,826 | 2,335,679,825 |
| Zip entries | 16,621 | 16,457 |
| Gitignored | yes (`.gitignore:73`, `backups-local/`) | yes (`.gitignore:73`) |
| Archive root | `this_profile's_activity_across_facebook/` | the same |
| Album JSONs | 563 | 563 |
| Photo entries in albums | 15,871 | 15,871 |
| Media files | 15,818 jpg + 65 png + 5 mp4 | 15,818 jpg + 65 png + 5 mp4 |
| Album photo URIs present in zip | 15,871 / 15,871 | 15,871 / 15,871 |
| Album date range | 2012-02-27 → 2021-10-23 | 2012-02-27 → 2021-10-23 |
| Undated (empty) albums | 8 | 8 |

**Why the zip has 164 fewer entries:** June included the Page's `messages/` folder (inbox, filtered, archived threads); September was exported with Posts only. The posts/media content is the same size.

**Extraction:** JSON only, to `backups-local/fb-export-0917/` (gitignored): 569 files, **8.4 MB**. Media was not extracted. Disk: 36 GB free.

Album dates are the earliest photo `creation_timestamp`, as in the June assessment. All strings were decoded with the latin-1 → utf-8 mojibake repair.

## 2. Albums per year

| Year | June | September |
|---|---:|---:|
| 2012 | 2 | 2 |
| 2013 | 14 | 14 |
| 2014 | 106 | 106 |
| 2015 | 88 | 88 |
| 2016 | 65 | 65 |
| 2017 | 133 | 133 |
| 2018 | 133 | 133 |
| **2019** | **13** | **13** |
| **2020** | **0** | **0** |
| 2021 | 1 | 1 |
| undated | 8 | 8 |
| **Total** | **563** | **563** |

**Album-level diff:** compared on (name, date, photo count), there are **0 albums only in September and 0 only in June.**

9 JSON files differ byte-wise (`33, 34, 303, 304, 499, 503, 508, 509, 510`). Every one belongs to a group of **same-named albums** (Bahamas ×2, Passenger ×2, Untitled album, Vancouver Folk Music Festival – Day 1 ×3), and Meta assigned the file numbers within each group in a different order. No content was added or lost.

`profile_posts_1.json` (309 posts) and `uncategorized_photos.json` are also unchanged. Both exports hold 52 post-attached media items from 2019 and 2 from 2020 (one 2019 post has 5+ photos), plus 9 uncategorized photos from 2019–20. None of that amounts to galleries.

## 3. The 2019+ albums: 14 then, 14 now

| Album | Date | Photos | Credit |
|---|---|---:|---|
| Howlin Rain @ The Astoria | 2019-01-13 | 14 | Mariko Margetson (photo-level) |
| ECCW Ballroom Brawl XI @ The Commodore Ballroom | 2019-01-14 | 45 | Peter Ruttan (photo-level) |
| REEL BIG FISH \| LIFE SUCKS…LET'S DANCE! TOUR | 2019-01-21 | 15 | Mary Matheson (photo-level) |
| Peter Murphy \| 40 years of Bauhaus \| Vogue Theatre | 2019-01-21 | 12 | Sharon Steele (album) |
| Colter Wall \| Commodore Ballroom \| Jan.19/2019 | 2019-01-22 | 12 | Mariko Margetson (album) |
| Photos: THE TREWS W/ guests Altameda and Chase The Bear | 2019-01-28 | 23 | Mariko Margetson (album) |
| Photos: INFECTED MUSHROOM @ The Commodore Ballroom / Jan.26, 2019 | 2019-01-29 | 43 | Ryan Johnson (album) |
| Interpol \| Queen Elizabeth Theatre \| Vancouver | 2019-02-02 | 11 | Jenn McInnis (photo-level) |
| Photos: MØ Forever Neverland Tour w/ guest LPX - Vancouver | 2019-02-02 | 18 | Sharon Steele (album) |
| Photos: Lord Huron \| Pacific Coliseum \| VANCOUVER WEEKLY | 2019-02-04 | 13 | Jenn McInnis (photo-level) |
| Photos: Arkells \| Pacific Colisuem \| VANCOUVER WEEKLY | 2019-02-04 | 26 | Jenn McInnis (album) |
| Photos: Mother Mother \| Orpheum \| Feb. 7, 2019 | 2019-02-09 | 23 | Jenn McInnis (album) |
| Photos: Bob Seger & The Silver Bullet Band \| Rogers Arena | 2019-02-09 | 20 | Ryan Johnson (album) |
| Brothers Osborne @ The Abbotsford Centre \| Oct. 21, 2021 | 2021-10-23 | 34 | Scott Place (photo-level) |

Album production in the export still stops at **2019-02-09**. There is nothing from 2019-03 through 2021-09.

## 4. Album-to-post matching, re-run

The matcher and thresholds are the June assessment's code, copied verbatim: IDF-weighted Jaccard 0.75 + sequence ratio 0.25, threshold 0.55, the same 4,233-title IDF corpus. It was **re-run against the June export first as a calibration and reproduced 54/19/18/170 exactly.**

| Bucket | June, 261 B1 | Sept, 261 B1 | Sept, 283 (B1 + B1b) |
|---|---:|---:|---:|
| Confident (title match, ≤45 days) | **54** (20.7%) | **53** (20.3%)* | 74 (26.1%) |
| Plausible (46–400 days) | 19 | 19 | 19 |
| Far (>400 days, likely a different show) | 18 | 19 | 19 |
| No album | 170 | 170 | 171 |

\* **53 vs 54 is a tie-break artifact, not a loss.** Post 67767 (2016 Folk Fest Day One) and B1b post 67902 (2018 Folk Fest Day 1) both score against three identically named "Vancouver Folk Music Festival - Day 1" albums. The matcher keeps the first top-scoring album by file order, and that order changed between exports:
- June paired 67767 with the 2016 album (0 days apart), so it counted as confident.
- September pairs it with the 2018 album (729 days apart), so it counts as far.

The 2016 album is present in both exports. **The effective figure is 54/261 in both.** Any importer must choose among same-named albums by date, not by name alone.

**The 2019–2020 posts (159 of the 261):**

| | June | September |
|---|---:|---:|
| Confident | 3 | 3 |
| Plausible | 3 | 3 |
| Far | 12 | 12 |
| No album | 141 | 141 |
| **Confident rate** | **1.9%** | **1.9%** |

Per year in September, confident / posts: 2014 22/32, 2015 14/35, 2016 1/2 (2/2 with the tie resolved), 2017 6/19, 2018 6/13, **2019 3/124, 2020 0/35.**

**B1b (22 posts):** 21 are confident (67902 included); 1 has no album. The B1b set is almost entirely pre-2019, which is why 283 lifts the overall rate to 26%.

## 5. Credit coverage on new albums

**There are no new albums, so there is nothing new to credit.** Coverage is identical to June: 544/563 albums carry a by-line (479 at album level, 65 recovered from photo captions). All 14 albums from 2019 onward are credited (8 at album level, 6 from photo captions).

## 6. Verdict

**NOT closed.** The September export is the same album set as June. The confident rate stays at ~21% (54/261), and 2019–2020 is still 3/159. Recovering the 2019–2020 galleries needs a different source:
- **Business Suite / Graph API:** read the Page's albums directly (`/{page-id}/albums` with dates), first confirming that the 2019–2020 albums still exist on Facebook.
- **An export from another admin's profile or the Page's own login:** this export is again `this_profile's_activity_across_facebook`, meaning the uploading profile's activity. A likely (unverified) explanation for the 2019-02 cliff is that later albums were posted by a different profile or tool.

## Method notes

- Script: session scratchpad `gapcheck.py`, read-only. Results: `backups-local/fb-export-0917/gapcheck-results.json` (per-post rows included).
- Retired set:
  - the 261 posts carrying `_vw_retired_jig_b1 = 1` in the local DB;
  - plus the 22 IDs in `backups-local/b1b-retirement-manifest-20260918.csv`, with dates and titles read from the local DB.
  - Locally the `_vw_retired_jig_b1b` meta is **not present**; B1b was executed on the server only.
- Nothing was written to the database. Media was not extracted.
