# Vancouver Weekly – Wayback Recovery Guide

This document explains how the content recovery system works and how to use it safely.

---

## What the script does

`wayback_recovery.py` recovers Vancouver Weekly articles that existed before the 2024 agency rebuild but are no longer in the current database.

It works in four steps:
1. Downloads a list of every URL ever captured on vancouverweekly.com from the Wayback Machine's CDX index.
2. Compares that list against the slugs already in the agency database export.
3. For each missing post, fetches the best available Wayback snapshot and extracts the title, content, author, date, categories, and featured image.
4. Saves the result as a JSON file and downloads the featured image to the correct WordPress uploads folder.

---

## Files and folders

| Path | What it is |
|---|---|
| `wayback_recovery.py` | The main script |
| `checkpoint.json` | Tracks which posts have succeeded or failed |
| `cdx_all_urls.json` | Cached list of all URLs ever on the site (28,726 URLs) |
| `existing_slugs.txt` | Post slugs already in the agency database |
| `missing_urls.json` | The gap list: posts in Wayback but not in the DB |
| `recovered_posts/` | One JSON file per recovered post |
| `recovery_report.md` | Human-readable summary of the most recent run |

Images are saved directly into the Local WordPress site:
```
~/Local Sites/vancouverweekly-local/app/public/wp-content/uploads/YYYY/MM/filename.jpg
```

---

## How checkpointing works

After every single post (success or failure), `checkpoint.json` is updated. It stores two separate lists:

```json
{
  "success": ["slug-a", "slug-b"],
  "failed":  ["slug-c"]
}
```

- **`success`** — posts that were fully recovered. `--resume` skips these.
- **`failed`** — posts that encountered an error. `--resume` retries these automatically. `--retry-failed` runs only these.

If a previously failed post succeeds on retry, it moves from `failed` to `success`. The opposite is also true.

Because the checkpoint is written after every post, an interruption loses at most one post's work.

---

## Individual post files

Each recovered post is saved to `recovered_posts/slug-name.json`. The file contains:

```json
{
  "slug": "post-slug-here",
  "url": "https://vancouverweekly.com/post-slug-here/",
  "status": "ok",
  "recovered_at": "2026-06-12T18:00:00Z",
  "snapshot": {
    "cdx_timestamp": "20161112123100",
    "wayback_url": "https://web.archive.org/web/20161112123100/..."
  },
  "data": {
    "title": "Post title",
    "content": "<div class=\"entry-content\">...</div>",
    "author": "Author Name",
    "date": "2016-09-15T15:30:12+00:00",
    "categories": ["Business"],
    "tags": [],
    "featured_image_url": "https://vancouverweekly.com/wp-content/uploads/...",
    "extraction_notes": ["content selector: div.entry-content"]
  },
  "local_image": "2016/09/image-filename.jpg",
  "error": null
}
```

For failed posts, `status` is `"error"`, `data` is `null`, and `error` contains the reason.

---

## Running the script

### Prerequisites

The script must be run from its own folder:
```bash
cd ~/Claude-code/vw-rebuild/vw-rebuild-starter-kit\ \(1\)/recovery
```

### Test batch (try a small number first)

```bash
python3 wayback_recovery.py --batch 50
```

Stops after 50 posts. Safe to run multiple times — each run adds to the checkpoint.

### Continue after a test batch

```bash
python3 wayback_recovery.py --resume
```

Skips all posts in the `success` list. Automatically retries anything in the `failed` list. Runs until all 5,881 missing posts have been attempted (or until you stop it).

### Run a specific number and then stop

```bash
python3 wayback_recovery.py --batch 200 --resume
```

Useful for running in stages throughout the day.

### Full overnight run (recommended command)

```bash
python3 wayback_recovery.py --resume
```

This is the safe command for the full recovery. It will:
- Skip the ~43 posts already successfully recovered
- Retry any failures from previous runs
- Process the remaining ~5,838 posts at 1 per second (~1.6 hours)
- Save a checkpoint after every single post
- Print a summary when finished

### Resume after any interruption

Same command — `--resume` always picks up exactly where you left off:

```bash
python3 wayback_recovery.py --resume
```

### Retry only failed posts

After a full run, some posts may have failed due to temporary Wayback timeouts. Retry them separately:

```bash
python3 wayback_recovery.py --retry-failed
```

This runs only the slugs in the `failed` list and ignores everything else.

---

## Checking progress mid-run

Open a second Terminal window and run:

```bash
python3 -c "
import json
d = json.load(open('checkpoint.json'))
print('Succeeded:', len(d['success']))
print('Failed:   ', len(d['failed']))
print('Total done:', len(d['success']) + len(d['failed']))
"
```

---

## After recovery is complete

The JSON files in `recovered_posts/` are the input for the next step: importing posts into the Local WordPress site using WP-CLI. That step is handled by a separate import script.

---

## Flags reference

| Flag | What it does |
|---|---|
| `--batch N` | Stop after N posts |
| `--resume` | Skip succeeded posts; retry failed posts |
| `--retry-failed` | Run only previously failed posts |
| `--skip-images` | Skip image downloads (faster, for testing) |
| `--no-cache` | Re-fetch the CDX URL list from archive.org |
| `--from-year YYYY` | Only recover posts captured in or before that year |
