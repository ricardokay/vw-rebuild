# FB Gallery Migration — Read-Only Assessment of the Meta Export

**Date:** 2026-09-17
**Scope:** Read-only. No DB writes, no imports, no media extracted into `uploads/`.
**Question:** can the Meta "Download Your Information" export restore galleries to the 261 JIG posts retired by B1, before launch?

**Answer: no. Not pre-launch, and not for most of the 261 at any date.** The export is a high-quality asset with near-perfect photographer credits, but it does not contain the era that most of the retired posts come from. Details below.

---

## 1. Export inventory

| Item | Value |
|---|---|
| ZIP | `backups-local/facebook-VancouverWeekly-2026-06-18-54FRaXvE.zip` |
| Size | 2,343,749,826 bytes (2.2 GB) |
| Gitignored | yes — `.gitignore:73` (`backups-local/`) |
| Entries | 16,621 files, 2,338,647,382 bytes uncompressed |
| Album JSONs | **563**, at `this_profile's_activity_across_facebook/posts/album/{0..562}.json` |
| Media files | **15,888** — 15,818 `.jpg`, 65 `.png`, 5 `.mp4` |
| Media bytes | 2,215 MB (2,322,925,192 bytes); avg 145 KB/photo |
| Photo entries in album JSON | **15,871** |
| Album date range | 2012-02-27 → 2021-10-23 |

**What was extracted:** JSON only — `backups-local/fb-export/`, 8.4 MB, 569 files. The 2.2 GB of media was deliberately **not** extracted; every byte figure in this report is read from the ZIP index instead. Disk was 55 GB free before, 53 GB after, so space was not the constraint — there is simply no reason to unpack 2.2 GB for a read-only assessment.

**Album JSON shape** (confirmed against `0.json`):

```
name, description, last_modified_timestamp, cover_photo,
photos[] -> { uri, creation_timestamp, title, description, media_metadata.photo_metadata.exif_data }
```

`last_modified_timestamp` is useless as a date — 558 of 563 albums carry an export-era (2026) value. **Album dates in this report are derived from the earliest `creation_timestamp` across the album's photos.** 8 albums have no photos and therefore no date.

### Encoding: the mojibake gotcha is present, and it is fixable

**Confirmed.** 1,047 strings arrive as UTF-8 bytes escaped into latin-1 codepoints. The `encode('latin-1').decode('utf-8')` round trip repairs all of them:

```
raw: Photo by Timothy Nguyá»n   ->   fixed: Photo by Timothy Nguyễn
```

Every figure in this report was computed **after** that repair. Any future import must apply it, or names like Nguyễn and Paillé land in the DB mangled.

---

## 2. Photographer credits — **not a blocker; this is the export's strongest asset**

| Measure | Albums | % of 563 |
|---|---|---|
| Album-level credit parsed to a clean name | 479 | 85.1% |
| + rescued by photo-level credit | +65 | — |
| **Any credit parsed to a clean name** | **544** | **96.6%** |
| **Any by-line phrase present (album or photo level)** | **553** | **98.2%** |
| No by-line anywhere | 10 | 1.8% |

The 10 with no by-line are **junk, not content**: `Instagram Photos` (1 photo), `Profile pictures` (6 photos), seven empty `Untitled album`/`cut` entries (0 photos). Combined: **7 photos**.

So: **every real content album in this export carries a photographer credit.** The 3.4% gap between "by-line present" and "parsed to a clean name" is parser conservatism, not missing data — those descriptions carry credits with trailing noise my patterns rejected (`Photos by Mariko Margetson May. 5 / 2018`, `All photos by jashjash (justjash.com). All rights reserved`, `Photos by JustJash`). A production importer with slightly looser cleanup reaches effectively 100%.

### Photographer roster — 37 people after consolidation

| Albums | Photos | WP acct | Name |
|---:|---:|---|---|
| 168 | 3,203 | yes | Jennifer McInnis |
| 152 | 6,577 | yes | Ryan Johnson |
| 33 | 600 | yes | Sharon Steele |
| 25 | 771 | yes | Peter Ruttan |
| 19 | 580 | yes | Timothy Nguyễn |
| 18 | 346 | yes | Mariko Margetson |
| 17 | 265 | yes | Quinn Middleton |
| 15 | 259 | yes | Jon Vincent |
| 12 | 296 | yes | Kristina Kimlickova |
| 11 | 660 | yes | Erik Lyon |
| 8 | 171 | yes | Jashua Peter Grafstein (= JustJash) |
| 8 | 403 | yes | Kevin Eisenlord |
| 8 | 140 | yes | Bryce Bladon |
| 4 | 41 | yes | Alix Critchley |
| … | | | (23 more, all with accounts) |

**Without a WP account — 5 people, 6 albums, 265 photos total:**

| Albums | Photos | Name |
|---:|---:|---|
| 2 | 220 | Tanis Lischewski |
| 1 | 18 | Vikrant Sharma |
| 1 | 13 | Dark Works *(studio name, not in the prior list)* |
| 1 | 8 | Levi Robson |
| 1 | 6 | Jack Mandeley |

**Two corrections to the working assumptions going into this task:**

- **Alix Critchley already has WP accounts** — IDs 111 (`Alix Critchley`) and 386 (`alix.critchley`). The 2013 Squamish album-level credit does not represent a missing contributor.
- **JustJash already has WP accounts** — IDs 132 (`Joshua Peter Grafstein`) and 250 (`joshua.peter.grafstein`). The export spells it `Jashua` (Meta-side typo) and `JustJash`, which is why it read as unknown.

That drops the "photographers without accounts" list from 6 to 5, and one of those 5 (`Dark Works`) is a studio name that was not previously on the list.

**Two name-hygiene items for any future import:**

- Timothy exists under **three spellings**: FB `Nguyễn`, WP display name `Nguyên`, WP login `Nyguyen`. An importer matching on name alone will create a fourth identity.
- Every photographer appears to hold **two WP accounts** (`Ryan Johnson` + `ryan.johnson`, `Jennifer McInnis` + `jennifer.mcinnis`, …). Attribution needs a decision about which account is canonical before, not after, a bulk import.

---

## 3. Per-photo metadata — credits repeat; there are no usable captions

Sampled **31 albums across 2012–2021** (up to 4 per year, every year present).

| Measure | Value |
|---|---|
| Photo entries with a `description` | 13,294 / 15,871 (83.8%) |
| …that are a credit line only | 8,204 (61.7%) |
| …that carry extra text beyond the credit | 5,090 (38.3%) |
| Distinct description strings per album | typically **1–4**, regardless of album size |
| Photos with EXIF payload (sampled) | 650 / 765 (85%) |
| Albums where photo-level credit rescued a missing album-level credit | 65 |

**Do photo entries carry their own captions? Effectively no.** A 62-photo album carries 2 distinct description strings; a 45-photo album carries 1. The per-photo `description` is the album credit stamped onto each image, sometimes with the artist/subject name appended:

```
Photos by @[783055194:2048:Ryan Johnson]
Nick Carter
```

The 38.3% "carries extra text" figure is that trailing subject name, not a caption. **Do not plan on importing per-image captions — they do not exist.** What per-photo descriptions *are* good for is credit: they rescued 65 albums whose album-level description had no parseable by-line, which is most of the gap between 85.1% and 96.6% above.

FB tag markup (`@[id:type:Name]`) appears throughout and must be stripped; it also gives a second, structured route to the photographer's identity.

---

## 4. Album-to-post matching — the core number

Matching used IDF-weighted token similarity plus sequence ratio over normalized titles (strip `Photos:` / `N amazing photos of` leads, trailing `| VANCOUVER WEEKLY`, trailing dates, punctuation, case, and filler/venue words). **IDF weighting matters:** a naive matcher scored `Michael Bublé | Rogers Arena` against `Post Malone @ Rogers Arena` at 0.62 purely on the shared venue. Down-weighting common tokens removes that whole class of false positive.

### Results for the 261 B1-retired posts

| Outcome | Count | % of 261 |
|---|---:|---:|
| Title match **and** date within 45 days — **confident** | **54** | **20.7%** |
| Title match, date within 46–400 days — plausible | 19 | 7.3% |
| Title match, date > 400 days apart — **likely a different show** | 18 | 6.9% |
| **Any title match (ceiling)** | **91** | **34.9%** |
| **No album found** | **170** | **65.1%** |

Of the 91 title matches, 21 are near-identical strings (sim ≥ 0.90) and 18 are within 7 days.

**The realistic figure is 54–73 of 261, not 91.** The >400-day bucket is the same artist at a *different* show, which is the worst possible import error — a plausible-looking gallery of the wrong night, credited to the right photographer.

### 10 fuzzy matches, for judgement

Sorted weakest first. The first five are **wrong** and show exactly where the fuzz breaks down:

| sim | days apart | Post | Album |
|---:|---:|---|---|
| 0.551 | 1201 | Photos: August Burns Red with Silverstein \| Vogue Theatre *(2019-07-13)* | August Burns Red *(2016-03-29)* ❌ different show |
| 0.557 | 413 | Photos of Big Sugar at The Commodore Ballroom *(2017-09-11)* | Big Wreck at Commodore Ballroom *(2016-07-25)* ❌ **different band** |
| 0.569 | 1012 | Photos: Norah Jones \| Orpheum Theatre *(2019-07-29)* | Norah Jones at Queen Elizabeth Theatre *(2016-10-21)* ❌ different show + venue |
| 0.590 | 1990 | Photos: Lee Fields & The Expressions \| Rio Theatre *(2019-12-15)* | Lee Fields & the Expressions at the Imperial, 7/3/14 *(2014-07-04)* ❌ different show |
| 0.615 | 768 | Photos: The Tea Party \| Commodore Ballroom *(2019-05-12)* | The Tea Party *(2017-04-05)* ❌ different show |
| 0.582 | 2 | Photos: YES with guest Todd Rundgren at The Queen Elizabeth Theatre *(2017-09-13)* | Yes- with guest Todd Rundgren / Sept.5/2017 *(2017-09-12)* ✅ 84 photos |
| 0.606 | 13 | SonReal at the Imperial *(2014-07-06)* | SonReal at the Imperial, 6/21/14 *(2014-06-23)* ✅ 13 photos |
| 0.611 | 8 | White Ash Falls & Friends at the Biltmore *(2014-07-01)* | White Ash Falls at the Biltmore, 6/21/14 *(2014-06-23)* ✅ 16 photos |
| 0.622 | 1 | Photos: Orchestral Manoeuvres in the Dark @ The Commodore *(2018-03-26)* | OMD - Orchestral Manoeuvres in the Dark *(2018-03-26)* ✅ 28 photos |
| 0.629 | 1 | Metallica WorldWired Tour with Avenged Sevenfold and Gojira *(2017-08-21)* | Metallica - World Wired Tour 2017 w/ Avenged Sevenfold & Gojira *(2017-08-21)* ✅ 254 photos |

**The pattern is clean and it is the basis of the recommendation:** similarity score alone does not separate right from wrong — *date proximity does*. Every ✅ is within 13 days; every ❌ is more than a year out. Any import must gate on date, not on title score.

### Why 170 posts have no album: the export ends in 2019-Q1

Albums per quarter:

```
2014Q2  39   2016Q2  30   2018Q1  40
2014Q3  44   2016Q3   9   2018Q2  30
2014Q4  18   2016Q4  18   2018Q3  36
2015Q1  38   2017Q1  20   2018Q4  27
2015Q2  24   2017Q2  27   2019Q1  13   <- last real quarter
2015Q3  16   2017Q3  51   2019Q2   0
2015Q4  10   2017Q4  35   ...      0
2016Q1   8                2021Q4   1   <- single stray album
```

Album production runs 20–50 per quarter for six straight years, drops to 13 in 2019-Q1, then **stops dead**. Only **14 albums date from 2019-01-01 or later**, and only **1** from after 2019-07-01.

Meanwhile **159 of the 261 retired posts (61%) are dated 2019-01-01 or later.**

Year-by-year, matched vs missed:

| Year | B1 posts | matched | missed |
|---|---:|---:|---:|
| 2013 | 1 | 1 | 0 |
| 2014 | 32 | 30 | 2 |
| 2015 | 35 | 26 | 9 |
| 2016 | 2 | 2 | 0 |
| 2017 | 19 | 7 | 12 |
| 2018 | 13 | 7 | 6 |
| **2019** | **124** | **15** | **109** |
| **2020** | **35** | **3** | **32** |

**141 of the 170 misses (83%) are 2019–2020 posts.** For 2014–2016 the export covers the archive well (59 of 70 matched, 84%). For 2019–2020 it covers almost nothing (18 of 159, 11%).

This is a **structural gap, not a matching-quality problem.** No amount of matcher tuning recovers galleries that are not in the file. The misses are real, current-era events — The Strokes, Brad Paisley, WWE SmackDown, Sinéad O'Connor, Old Dominion, The Beaches — with no nearest album above 0.40 similarity.

### Albums that match no post at all

| Outcome | Albums | Photos | Bytes |
|---|---:|---:|---:|
| Matched to a B1 post | 91 | 2,572 | 394 MB |
| Matched to some other post (mostly published) | 309 | — | — |
| **Matched no post at all** | **163** | **4,226** | 627 MB |

163 orphan albums hold 4,226 photos that appear to have never become an article — festival days, red-carpet sets, a 320-photo 2013 `Photos` album, `Vancouver Women's March` (73), `Tom Lee Music New Store Media Event` (125). This is unpublished archive material, not a migration gap, but it is worth knowing it exists before anyone deletes the ZIP.

### The export's value is wider than the 261

Cross-matched against `recovery-inventory.csv` (the 1,764-post dead-image worklist):

- **137 of 1,764 inventory posts (7.8%)** have an album in the export — all 137 published
- those posts carry **2,421 dead images**; the matching albums hold **3,714 photos**
- **median ratio of album photos to dead images: 1.08**, with 64 of 137 within ±10%

That ratio is the strongest single validation in this assessment. Post 67156 *Metallica WorldWired Tour* has **255 dead images**; the export's Metallica album has **254 photos**. Rifflandia: 110 dead / 109 available. YES: 85 / 84. Gogol Bordello: 78 / 77. Testament: 65 / 64. These are not coincidences — **the FB album is demonstrably the original source of the dead images**, and where an album exists the recovery is essentially complete.

---

## 5. Disk and transfer impact

Current local uploads:

| Measure | Value |
|---|---|
| Total | **16.46 GB**, 263,106 files |
| Originals | 4.40 GB, 15,427 files, avg 299 KB |
| Derivatives | 12.06 GB, 247,476 files, avg 51 KB |
| Registered image subsizes | **30** |
| Observed derivatives per original | **16** |
| Observed byte multiplier | **2.74×** |

FB photos average 145 KB — about half the size of existing originals — so they will generate somewhat fewer/smaller subsizes. Projections below apply the observed 2.74× multiplier, which is therefore conservative (high).

**Scenario A — import only what matches the 261 (2,572 photos):**

| | |
|---|---|
| Originals added | 394 MB |
| Derivatives generated | ~1.05 GB |
| **Total added** | **~1.4 GB** |
| Files added | ~43,600 (2,572 + ~41,000) |
| Uploads after | ~17.9 GB, ~307k files |

**Scenario B — import the whole export (15,871 photos):**

| | |
|---|---|
| Originals added | 2.16 GB |
| Derivatives generated | ~5.9 GB |
| **Total added** | **~8.1 GB** |
| Files added | ~270,000 |
| Uploads after | **~24.6 GB, ~533k files — the uploads tree roughly doubles** |

**On the DigitalOcean 2 GB droplet (Cloudways `bmm-server-1`):** the standard Cloudways DO 2 GB plan ships 50 GB of storage — *confirm this on the actual server before relying on it.* At 50 GB, Scenario A lands the site near 20 GB and Scenario B near 27 GB; both fit, with B leaving materially less headroom for backups and the 388 MB DB.

**Transfer is not the bottleneck; regeneration is.** Derivatives are reproducible, so the staging rsync should carry originals only — +394 MB (A) or +2.16 GB (B) on top of the existing 4.4 GB payload. The real cost is generating **~41,000 (A) or ~254,000 (B) derivative files at 30 registered sizes on a 1-vCPU droplet**. Scenario B is a multi-hour, CPU-saturating job that should never run during or near cutover. Note also that the 30 registered subsizes are themselves worth auditing — 16 derivatives per original is a lot of disk for sizes that may not all be in use.

---

## 6. Recommendation

### Pre-launch: **not feasible. Do not attempt it.**

This is not a schedule judgement — the data does not support the operation at any speed.

**Blocking gaps, in order of severity:**

1. **Coverage — decisive.** The export cannot restore 170 of the 261 retired posts (65%), and the shortfall is structural: album production stops at 2019-Q1 while 61% of the retired posts are 2019 or later. Best case is **54 posts confidently restored (20.7%)**, ceiling 91 (34.9%). A migration that fixes one post in five does not change the launch decision for the other four, and B1 already handled all 261 correctly and reversibly.

2. **Wrong-show risk — decisive on its own.** Title similarity does not distinguish the right night from the same artist two years earlier. 18 of 91 title matches are more than 400 days out, and one pair is a *different band* (`Big Sugar` / `Big Wreck`). Publishing a wrong-night gallery under a real photographer's byline is a worse outcome than the current retired-to-draft state, and it is not self-correcting — nobody reviewing the site will catch it.

3. **Disk and regeneration — manageable but not free.** Scenario A is genuinely cheap (~1.4 GB, ~43k files). Scenario B doubles the uploads tree and needs a multi-hour thumbnail regeneration on a 1-vCPU droplet. Neither belongs anywhere near the cutover window, and the droplet's actual disk size still needs confirming.

**Explicitly not blockers:**

4. **Credit coverage is solved.** 98.2% of albums carry a by-line; the 10 that do not are empty or profile-junk totalling 7 photos. On the subset that matches the 261, credit coverage is **98.9%**. Rights attribution is the one thing this export does near-perfectly.

5. **Unknown photographers are a small, finite problem.** 5 people, 6 albums, 265 photos — and two names previously believed missing (Alix Critchley, JustJash) already have WP accounts. This is a short afternoon of account creation, not a project.

### What to do instead

**Now, pre-launch:** nothing. Leave the 261 retired. B1 is reversible and correct, the galleries never rendered anyway, and the ZIP is safely archived and gitignored.

**Post-launch, in this order:**

1. **Fix the identity layer first** — resolve the duplicate WP accounts (every photographer has two), settle the Timothy Nguyễn / Nguyên / Nyguyen spelling, and create the 5 missing accounts. Attribution must be correct *before* 2,500 images arrive carrying it, not after.
2. **Run Scenario A against the 54 date-confirmed matches only**, gated on date proximity ≤45 days — never on title score alone. Dry run, manifest, eyeball the 54, then write. This restores about 1,500 photos to 54 posts for ~1.4 GB.
3. **Review the 19 plausible (46–400 day) matches by hand.** Roughly 20 posts; a human who knows the publication settles each in seconds. Do not automate this bucket.
4. **Discard the 18 >400-day matches outright.** They are wrong.
5. **Separately, mine the export against `recovery-inventory.csv`** — 137 already-published posts with dead images have a matching album, covering 2,421 dead images with 3,714 available photos at a median 1.08 ratio. **This is a better return than the B1 work** and is where the export's real value sits: it repairs live, public pages rather than draft ones.
6. **Leave the 163 orphan albums alone** until someone decides editorially whether 4,226 never-published photos are worth surfacing.

**One flag.** [FLAG - reply 'noted' to dismiss] The export's album coverage stopping in 2019-Q1 has a cause worth knowing before anyone plans a second export: the archive path is `this_profile's_activity_across_facebook`, and a Page-level export (or a second admin's export) may hold the 2019–2020 albums that are missing here. If those albums still exist on Facebook, a fresh Page export would lift the 261 recovery rate from ~21% toward the ~84% the export achieves for 2014–2016 — which would genuinely change this recommendation. Worth ten minutes checking whether the Page's 2019–2020 albums are still live before accepting the 65% loss as permanent.

---

## Method notes

- All figures computed after the latin-1→utf-8 mojibake repair.
- Album dates derived from the minimum photo `creation_timestamp`; `last_modified_timestamp` is export-era for 558/563 albums and was not used.
- Matching: IDF-weighted Jaccard (0.75) + `difflib` sequence ratio (0.25) over normalized titles, threshold 0.55, IDF computed over 4,233 post titles + 563 album names.
- Post dates read from `wptg_posts.post_date` for posts carrying `_vw_retired_jig_b1 = 1` (261 rows, matching the manifest).
- Byte figures read from the ZIP index, not from extracted files.
- Nothing was written to the database. Media was not extracted.
