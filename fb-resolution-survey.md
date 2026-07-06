# Facebook Album Export — Image Resolution Survey (read-only)

**Date:** 2026-07-06
**Analyst:** Claude Code (desktop, real Mac filesystem)
**Method:** Read-only. Actual pixel dimensions parsed from JPEG SOF headers via a pure-Python
parser fed by `unzip -p` streaming (nothing extracted or written to disk from the archive).
No album files modified, no import, no database access.

## Source

- **Export (zip, not unpacked):** `~/Documents/Vancouver Weekly Facebook backup zip/facebook-VancouverWeekly-2026-06-18-54FRaXvE.zip` (2.34 GB)
- **Album path prefix inside zip:** `this_profile's_activity_across_facebook/posts/media/`
- Only one export candidate found (searched vw-rebuild, Downloads, Desktop, Documents).

## Inventory

| Metric | Expected (est.) | Actual |
|---|---|---|
| Album folders | ~548 | **557** |
| Image files (posts/media) | ~15,500 | **15,883** (15,818 `.jpg` + 65 `.png`) |

Close to expectation; both counts slightly higher.

## Sample

20 albums, taken as every ~28th folder across the alphabetically-sorted set (representative
spread, not the first 20). 2–3 images read per album = **60 images measured**.

| Album | Filename | W×H |
|---|---|---|
| 25amazingphotosofNickCarteratVenue | 1037155913060354.jpg | 1200×568 |
| 25amazingphotosofNickCarteratVenue | 1037155963060349.jpg | 1200×852 |
| 25amazingphotosofNickCarteratVenue | 1037156019727010.jpg | 1200×689 |
| AskingAlexandria | 1458955550880386.jpg | 1200×1218 |
| AskingAlexandria | 1458955610880380.jpg | 1200×800 |
| AskingAlexandria | 1458955574213717.jpg | 1944×1296 |
| BlackMountainatTheVogueTheatre | 1143403922435552.jpg | 1000×728 |
| BlackMountainatTheVogueTheatre | 1143403665768911.jpg | 1000×665 |
| BlackMountainatTheVogueTheatre | 1143403425768935.jpg | 1000×665 |
| ChrisStapleton | 1157328311043113.jpg | 1200×798 |
| ChrisStapleton | 1157328277709783.jpg | 1200×798 |
| ChrisStapleton | 1157328197709791.jpg | 1200×798 |
| DavidNewberryTheCracklingBenjaminJames | 582170295225587.jpg | 2048×1366 |
| DavidNewberryTheCracklingBenjaminJames | 582170525225564.jpg | 2048×2015 |
| DavidNewberryTheCracklingBenjaminJames | 582170411892242.jpg | 1546×2048 |
| FanExpoVancouver2017 | 1358917470884195.jpg | 534×800 |
| FanExpoVancouver2017 | 1358917474217528.jpg | 450×800 |
| FanExpoVancouver2017 | 1358917477550861.jpg | 534×800 |
| HaramandQalandar | 578238088952141.jpg | 960×544 |
| HaramandQalandar | 578238092285474.jpg | 960×683 |
| HaramandQalandar | 578238172285466.jpg | 960×640 |
| JasonBonhamsLedZeppelinExperience | 1206050286170915.jpg | 1200×798 |
| JasonBonhamsLedZeppelinExperience | 1206050312837579.jpg | 798×1200 |
| JasonBonhamsLedZeppelinExperience | 1206050296170914.jpg | 798×1200 |
| KatyPerryWitnessTour2018 | 1436636809778927.jpg | 1200×677 |
| KatyPerryWitnessTour2018 | 1436636779778930.jpg | 954×800 |
| KatyPerryWitnessTour2018 | 1436636706445604.jpg | 1198×800 |
| MatchboxTwenty | 1258050744304202.jpg | 1200×801 |
| MatchboxTwenty | 1258050454304231.jpg | 801×1200 |
| MatchboxTwenty | 1258051180970825.jpg | 1200×801 |
| NeilDiamond | 1262628427179767.jpg | 1200×739 |
| NeilDiamond | 1262628460513097.jpg | 1028×1200 |
| NeilDiamond | 1262628477179762.jpg | 1200×715 |
| PembertonMusicFestivalDayFourHighlights | 751776024931679.jpg | 770×1368 |
| PembertonMusicFestivalDayFourHighlights | 751776028265012.jpg | 1368×912 |
| PembertonMusicFestivalDayFourHighlights | 751776031598345.jpg | 1368×770 |
| PostmodernJukebox | 740142299428385.jpg | 1247×998 |
| PostmodernJukebox | 740142382761710.jpg | 728×1294 |
| PostmodernJukebox | 740142306095051.jpg | 1060×596 |
| RockAmblesidePark2018SaturdayAugust18 | 1656971301078809.jpg | 1200×800 |
| RockAmblesidePark2018SaturdayAugust18 | 1656971467745459.jpg | 800×1200 |
| RockAmblesidePark2018SaturdayAugust18 | 1657800184329254.jpg | 1200×800 |
| SkookumFestivalDay3 | 1685640478211891.jpg | 2048×1365 |
| SkookumFestivalDay3 | 1685641224878483.jpg | 2048×1365 |
| SkookumFestivalDay3 | 1685641034878502.jpg | 2048×1365 |
| TechN9neatTheCommodoreBallroom | 425971714178780.jpg | 2048×1363 |
| TechN9neatTheCommodoreBallroom | 425971677512117.jpg | 2048×1363 |
| TechN9neatTheCommodoreBallroom | 425971664178785.jpg | 2048×1363 |
| TheMelvins | 888868694555744.jpg | 800×1125 |
| TheMelvins | 888868767889070.jpg | 1200×694 |
| TheMelvins | 888869284555685.jpg | 800×958 |
| TiftMerrittGLSWintersleepAndrewBird | 574718959304054.jpg | 2048×1430 |
| TiftMerrittGLSWintersleepAndrewBird | 574717945970822.jpg | 2048×1366 |
| TiftMerrittGLSWintersleepAndrewBird | 574717525970864.jpg | 2048×1366 |
| VancouverFolkMusicFestivalDay1 | 929733010469312.jpg | 1365×2048 |
| VancouverFolkMusicFestivalDay1 | 929734000469213.jpg | 2048×1638 |
| VancouverFolkMusicFestivalDay1 | 929734037135876.jpg | 2048×1638 |
| WestwardMusicFestivalDay3 | 1310291775746765.jpg | 800×1200 |
| WestwardMusicFestivalDay3 | 1310291442413465.jpg | 800×450 |
| WestwardMusicFestivalDay3 | 1310291449080131.jpg | 800×1200 |

## Findings

**The export is NOT capped at 800px. Elliott Brood (800px) is on the low end, not the norm.**

Long edge is the true resolution indicator (≈40% of images are portrait, so raw width
understates them).

- **56 of 60 sampled images (93%) exceed 800px on the long edge.**
- **Long-edge min / max:** 800 / 2048.
- **Raw-width min / max:** 450 / 2048.
- **Two dominant tiers on the long edge:**
  - **1200px** — most common (~23/60 images).
  - **2048px** — strong second cluster (~15/60), including several **entire albums** at 2048
    (Skookum, Tech N9ne, Tift Merritt, Vancouver Folk, David Newberry).
- Middle band 954–1000px for a few albums; only FanExpo sat at an 800px long edge in this sample.
- **Resolution is consistent within an album** (all of an album's photos tend to share one
  ceiling), suggesting per-album capture/upload resolution rather than random variation.

## Implication for the high-res-swap idea

Higher-res originals than Elliott Brood's 800px **do exist** for the large majority of albums —
1200px is typical and a meaningful share reaches 2048px. It is worth reading each album's actual
ceiling (long edge) during the inventory/classification pass rather than assuming 800px. Elliott
Brood appears to be a low-resolution outlier; do not generalize its 800px ceiling to the set.

## Caveats

- Sample is 60 images / 20 albums out of 15,883 / 557. Representative by even-spacing but not exhaustive.
- Dimensions read from JPEG SOF markers (and PNG IHDR); no sampled image was a PNG.
- 2048px here is the pixel dimension of the exported file — it does not certify these are
  press-quality originals, only that the export delivered up to 2048px, well above 800px.
