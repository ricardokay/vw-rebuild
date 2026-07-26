# Live-Site HEAD Probe — Results Analysis

Source: `db-backups/live-site-head-probe-results_2026-07-25.csv` (10,217 distinct missing local files, HEAD-probed against `https://vancouverweekly.com/wp-content/uploads/<path>`, exact filename then base/unsized fallback on failure). Read-only analysis — no downloads, no DB writes.

## 1. Totals

10,217 data rows (matches the full distinct missing-file list — probe ran to completion, confirmed separately).

| Exact-attempt status | Count | % of 10,217 |
|---|---:|---:|
| 404 | 10,101 | 98.9% |
| 200 | 114 | 1.1% |
| 000 (connection error/timeout) | 2 | 0.02% |
| 3xx | 0 | 0% |

No 403s and no 3xx redirects were observed on the exact attempt.

**Base-fallback attempts** (only run when the exact filename 404'd and had a `-NNNxNNN` size suffix to strip): 7,797 attempted — 7,746 came back 404, 51 came back 200. 2,306 exact-404 paths had no size suffix to fall back on, so no second attempt was possible for those.

**Combined resolution** (`resolved_via` field): 114 resolved on the exact filename, 51 resolved via the base/unsized fallback, 10,052 resolved via neither.

## 2. Yield

**Headline number: 165 of 10,217 missing files (1.6%) are actually retrievable from the live site right now.**

Cross-referencing those 165 hits against `image-recovery-gaps.txt` (the 6,997-entry list of files never recovered via Wayback/the Feb-2019 SQL dump): **zero direct overlap**. This is a real finding, not a matching bug — I verified the two file universes do overlap substantially overall (1,584 files appear on both lists), just not among these specific 165 hits. Read plainly: the live-site channel and the gaps.txt channel are catching *different* files. The live-site probe isn't confirming known gaps as recoverable — it's finding a distinct set of 165 files that were never on the gaps.txt radar at all, mostly because gaps.txt was sourced from a 2019 SQL dump and these are more recent 2019–2020 uploads that dump predates or never covered.

One more nuance for planning: the 51 base-fallback hits collapse to **36 distinct underlying files** after dedup (multiple thumbnail-size rows — `2-600x450.jpg`, `2-100x75.jpg`, etc. — often resolve to the same one real base file, `2.jpg`). So a harvest job should target roughly **114 + 36 = 150 distinct files** to download, not 165 requests.

Of the 165 hits, 113 belong to the 190-post dead-JIG photo cohort — meaning a meaningful chunk of the photo-gallery backlog specifically is recoverable this way. 0 belong to the 2024 food/lifestyle cohort (those posts' images weren't hit by this probe's outcome at all).

## 3. Redirects

**None observed.** 0 rows returned any 3xx status on either the exact or base attempt. Nothing to flag as junk — there's simply no redirect behavior to evaluate.

## 4. Failures

10,052 files did not resolve (404 on every attempt made). By year (full failures — 404 on exact, and 404 or no-attempt on base):

| Year | Failures |
|---|---:|
| 2019 | 3,456 |
| 2018 | 1,204 |
| 2012 | 1,003 |
| 2016 | 972 |
| 2017 | 897 |
| 2013 | 792 |
| 2014 | 721 |
| 2015 | 591 |
| 2020 | 408 |
| 2011 | 4 |
| 2021 | 1 |
| 2010 | 1 |

2019 dominates by a wide margin — consistent with 2019 being the site's peak-volume content year generally (matches prior session findings on inline-image health by year).

By file type: 9,119 `.jpg`, 690 `.png`, 232 `.jpeg`, 9 `.gif` — roughly proportional to the overall missing-file mix, no single format standing out as disproportionately dead.

2,306 of the 10,052 failures never got a base-fallback attempt at all (their filename had no `-NNNxNNN` size suffix to strip, so exact-only was the only shot). These are guaranteed-dead on this recovery channel unless the site holds a differently-named copy.

Sample paths (10, spread across the failure set): `2012/02/Top_Secret_America-1.jpeg`, `2013/02/Aimee-Payne-200x300.jpg`, `2018/10/RLJ_7640-1-1-300x200.jpg`, `2019/01/RLJ_4581-1-600x400.jpg`, `2016/02/janesaddiction.jpg`, `2018/05/Tom-Paille-1-768x512.jpg`, `2019/06/DSC_2569-1024x684.jpg`, `2019/08/Mitchell-Tenpenny-at-Sunfest-Aug.-3-2019-by-Tom-Paillé-3549-1024x683.jpg`, `2018/03/RLJ4211-1-e1521250576848.jpg`, `2016/04/10990045_10153308750901014_6008714176660406185_o-300x200@2x.jpg`.

## 5. Size sanity

**Not answerable — the probe did not capture Content-Length.** The runner (`curl -sI ... -w "%{http_code}"`) recorded only HTTP status codes, not response size, and I'm not fabricating a distribution. This means the 165 "200" hits have **not** been checked for the classic false-positive failure mode — a misconfigured server returning HTTP 200 with a tiny HTML error page instead of the actual image. Flagging this explicitly as unverified: **before any harvest, a follow-up pass should fetch Content-Length (or Content-Type) for the ~150 distinct hit files** to rule out fake-200s.

## 6. Recommendation

**Investigate anomalies first — do not run a full or partial harvest yet.** Two concrete unknowns block a safe harvest decision:

1. **Content-Type/size not verified** (item 5) — a handful of minutes of follow-up HEAD/GET requests against just the ~150 distinct hit files, checking Content-Type starts with `image/` and Content-Length is non-trivial (e.g. >1KB), would close this gap cheaply before touching anything.
2. **The zero gaps.txt overlap is worth a sanity gut-check** before treating 165 as a stable number — not because the math is wrong (verified), but because it reframes the ask: this yield doesn't validate gaps.txt at all, it's a separate, smaller, previously-unknown-to-us pocket of recoverable files.

Once Content-Type is confirmed clean, a **partial harvest of the deduped ~150 files** is low-risk and clearly worth doing (114 exact + 36 base-fallback originals) — small enough to review by hand before it touches any post content. A full harvest isn't on the table regardless, since 98.4% of the list is confirmed dead on this channel; the real remaining backlog (10,052 files) needs a different recovery avenue entirely (photographer asks, or accepting permanent loss for the pre-2019 tail).
