#!/usr/bin/env python3
"""
Vancouver Weekly – Wayback Machine content recovery tool.

Steps:
  1. Query CDX API for every URL ever on vancouverweekly.com
  2. Compare against existing post slugs (exported from agency DB)
  3. For each missing post, fetch the best Wayback capture
  4. Extract title, content, author, date, categories, featured image
  5. Save extracted data as JSON + download images
  6. Write a final report

Rate limit: 1 request/second max to be polite to archive.org.
"""

import json
import os
import re
import sys
import time
import urllib.parse
from datetime import datetime
from pathlib import Path

import requests
from bs4 import BeautifulSoup

# ── Config ────────────────────────────────────────────────────────────────────

DOMAIN = "vancouverweekly.com"
CDX_URL = "https://web.archive.org/cdx/search/cdx"
WB_BASE = "https://web.archive.org/web"

SCRIPT_DIR = Path(__file__).parent
EXISTING_SLUGS_FILE = SCRIPT_DIR / "existing_slugs.txt"
CDX_CACHE_FILE = SCRIPT_DIR / "cdx_all_urls.json"
MISSING_URLS_FILE = SCRIPT_DIR / "missing_urls.json"
RECOVERED_DIR = SCRIPT_DIR / "recovered_posts"
IMAGES_DIR = SCRIPT_DIR / "recovered_images"
REPORT_FILE = SCRIPT_DIR / "recovery_report.md"

# WordPress uploads folder on Local site
WP_UPLOADS = Path("/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-content/uploads")

RATE_LIMIT = 1.1  # seconds between requests
SESSION = requests.Session()
SESSION.headers.update({
    "User-Agent": "Vancouver Weekly content recovery script - admin@vancouverweekly.com"
})

_last_request = 0.0

def get(url, **kwargs):
    """Rate-limited GET."""
    global _last_request
    elapsed = time.time() - _last_request
    if elapsed < RATE_LIMIT:
        time.sleep(RATE_LIMIT - elapsed)
    try:
        r = SESSION.get(url, timeout=30, **kwargs)
        _last_request = time.time()
        return r
    except Exception as e:
        _last_request = time.time()
        raise e

# ── Step 1: CDX enumeration ───────────────────────────────────────────────────

def fetch_cdx_urls(use_cache=True):
    """Return list of dicts: {url, timestamp, statuscode, mimetype}."""
    if use_cache and CDX_CACHE_FILE.exists():
        print(f"[cdx] Using cached CDX data from {CDX_CACHE_FILE}")
        return json.loads(CDX_CACHE_FILE.read_text())

    print("[cdx] Querying Wayback CDX API (this may take a few minutes)…")
    params = {
        "url": f"{DOMAIN}/*",
        "output": "json",
        "collapse": "urlkey",          # one row per unique URL
        "fl": "original,timestamp,statuscode,mimetype",
        "filter": "statuscode:200",
        "limit": "200000",
    }
    r = get(CDX_URL, params=params)
    r.raise_for_status()
    raw = r.json()
    if not raw:
        return []
    # First row is header
    keys = raw[0]
    records = [dict(zip(keys, row)) for row in raw[1:]]
    CDX_CACHE_FILE.write_text(json.dumps(records, indent=2))
    print(f"[cdx] Got {len(records)} unique captured URLs")
    return records

# ── Step 2: Identify missing posts ───────────────────────────────────────────

def load_existing_slugs():
    lines = EXISTING_SLUGS_FILE.read_text().splitlines()
    return set(l.strip() for l in lines if l.strip())

def is_post_url(url):
    """
    Heuristic: vancouverweekly.com post URLs look like:
      https://vancouverweekly.com/some-slug/
      https://www.vancouverweekly.com/some-slug/
    Exclude: admin, feed, wp-*, /?p=, category/, tag/, author/, page/,
             attachments, .jpg/.png etc, query strings with many params.
    """
    try:
        p = urllib.parse.urlparse(url)
    except Exception:
        return False
    path = p.path.rstrip("/")
    if not path or path in ("", "/"):
        return False
    # Exclude WP internals
    exclude_prefixes = (
        "/wp-", "/feed", "/comments", "/?", "/category/",
        "/tag/", "/author/", "/page/", "/search/", "/2002/",
        "/2003/", "/2004/", "/2005/", "/2006/", "/2007/",
        "/xmlrpc", "/sitemap",
    )
    for ex in exclude_prefixes:
        if path.startswith(ex):
            return False
    # Exclude URLs ending in /feed/, /trackback/, /comment-page-N/, /attachment/
    if re.search(r'/(feed|trackback|attachment|embed|comment-page-\d+)/?$', path):
        return False
    # Exclude files
    if re.search(r'\.(jpg|jpeg|png|gif|pdf|zip|css|js|xml|txt|ico)$', path, re.I):
        return False
    # Must have at least one path segment that looks like a slug
    segments = [s for s in path.split("/") if s]
    if not segments:
        return False
    # Skip pure numeric (archive pages like /2024/06/)
    if all(s.isdigit() for s in segments):
        return False
    # Slug = last meaningful segment
    slug = segments[-1]
    # Skip very short or non-slug values
    if len(slug) < 3:
        return False
    return True

def url_to_slug(url):
    p = urllib.parse.urlparse(url)
    segments = [s for s in p.path.rstrip("/").split("/") if s]
    return segments[-1] if segments else ""

def find_missing(cdx_records, existing_slugs):
    """Return list of {url, timestamp} for posts not already in the DB."""
    seen_slugs = set()
    missing = []
    for rec in cdx_records:
        url = rec["original"]
        if not is_post_url(url):
            continue
        slug = url_to_slug(url)
        if not slug or slug in existing_slugs or slug in seen_slugs:
            continue
        seen_slugs.add(slug)
        missing.append({"url": url, "timestamp": rec["timestamp"], "slug": slug})
    return missing

# ── Step 3: Fetch & extract post from Wayback ─────────────────────────────────

def best_wayback_url(original_url, preferred_timestamp=None):
    """Find the best (closest to original publication) Wayback snapshot URL."""
    # Ask CDX for all snapshots of this specific URL, pick the earliest
    params = {
        "url": original_url,
        "output": "json",
        "fl": "timestamp,statuscode",
        "filter": "statuscode:200",
        "limit": "5",
        "from": "20020101",
        "to": "20240101",   # prefer pre-2024 captures
    }
    try:
        r = get(CDX_URL, params=params)
        rows = r.json()
        if rows and len(rows) > 1:
            # rows[0] is header, pick earliest content snapshot
            ts = rows[1][0]
            return f"{WB_BASE}/{ts}/{original_url}"
    except Exception:
        pass
    # Fall back to the timestamp from CDX collapse
    if preferred_timestamp:
        return f"{WB_BASE}/{preferred_timestamp}/{original_url}"
    return None

def clean_wayback_html(html, wayback_url):
    """Strip Wayback toolbar and return BeautifulSoup of just the page."""
    # Remove Wayback injection scripts/banner
    html = re.sub(r'<!-- BEGIN WAYBACK.*?END WAYBACK[^>]*-->', '', html, flags=re.DOTALL)
    soup = BeautifulSoup(html, "lxml")
    # Remove Wayback toolbar elements
    for el in soup.find_all(id=re.compile(r'^wm-', re.I)):
        el.decompose()
    for el in soup.find_all(class_=re.compile(r'wb-', re.I)):
        el.decompose()
    return soup

def rewrite_wayback_urls(text, base_domain):
    """Convert Wayback-prefixed URLs back to original domain URLs."""
    # /web/20210813180642/https://vancouverweekly.com/path -> /path
    text = re.sub(
        r'https?://web\.archive\.org/web/\d+/https?://' + re.escape(base_domain),
        f'https://{base_domain}',
        text
    )
    return text

def extract_post(soup, wayback_url):
    """
    Extract structured post data from a BeautifulSoup page.
    Tries multiple theme/layout patterns used by VW over the years.
    Returns a dict.
    """
    data = {
        "title": "",
        "content": "",
        "author": "",
        "date": "",
        "categories": [],
        "tags": [],
        "featured_image_url": "",
        "wayback_url": wayback_url,
        "extraction_notes": [],
    }

    # ── Title ──────────────────────────────────────────────────────────────
    for sel in [
        "h1.entry-title", "h1.post-title", "h1.article-title",
        ".entry-header h1", "article h1", "h1",
    ]:
        el = soup.select_one(sel)
        if el:
            data["title"] = el.get_text(strip=True)
            break

    if not data["title"]:
        tag = soup.find("title")
        if tag:
            data["title"] = re.sub(r'\s*[|\-–]\s*Vancouver Weekly.*$', '', tag.get_text(strip=True)).strip()

    # ── Date ───────────────────────────────────────────────────────────────
    # Try machine-readable datetime first
    for sel in ["time[datetime]", "[itemprop='datePublished']", ".entry-date", ".post-date", ".published"]:
        el = soup.select_one(sel)
        if el:
            dt = el.get("datetime") or el.get("content") or el.get_text(strip=True)
            if dt:
                data["date"] = dt.strip()
                break

    # ── Author ─────────────────────────────────────────────────────────────
    for sel in [
        "[itemprop='author'] [itemprop='name']", "[itemprop='author']",
        ".author a", ".entry-author a", ".byline a", "a[rel='author']",
        ".author", ".byline",
    ]:
        el = soup.select_one(sel)
        if el:
            data["author"] = el.get_text(strip=True)
            break

    # ── Categories ─────────────────────────────────────────────────────────
    for sel in [".entry-categories a", ".post-categories a", "[rel='category tag']", ".cat-links a"]:
        els = soup.select(sel)
        if els:
            data["categories"] = [e.get_text(strip=True) for e in els]
            break

    # ── Tags ───────────────────────────────────────────────────────────────
    for sel in [".entry-tags a", ".tags-links a", "[rel='tag']"]:
        els = soup.select(sel)
        if els:
            data["tags"] = [e.get_text(strip=True) for e in els]
            break

    # ── Featured image ─────────────────────────────────────────────────────
    # og:image is most reliable
    og_img = soup.find("meta", property="og:image")
    if og_img:
        data["featured_image_url"] = og_img.get("content", "")

    if not data["featured_image_url"]:
        for sel in [".featured-image img", ".post-thumbnail img", ".wp-post-image", "article img"]:
            el = soup.select_one(sel)
            if el:
                src = el.get("src", "")
                if src and not src.endswith("pixel.gif"):
                    data["featured_image_url"] = src
                    break

    # ── Content ────────────────────────────────────────────────────────────
    content_el = None
    for sel in [
        "div.entry-content", "div.post-content", "div.article-content",
        "article .content", ".entry-body", "article",
    ]:
        content_el = soup.select_one(sel)
        if content_el:
            break

    if content_el:
        # Remove share buttons, ads, related posts, nav, comments
        for junk in content_el.select(
            ".sharedaddy, .addtoany, .related-posts, .post-nav, "
            ".comments-area, .navigation, script, style, "
            "[class*='share'], [class*='social'], [class*='ad-']"
        ):
            junk.decompose()
        # Rewrite Wayback URLs in content
        raw_html = str(content_el)
        raw_html = rewrite_wayback_urls(raw_html, DOMAIN)
        data["content"] = raw_html
        data["extraction_notes"].append(f"content selector: {sel}")
    else:
        data["extraction_notes"].append("WARNING: no content element found")

    # Clean featured image URL
    if data["featured_image_url"]:
        data["featured_image_url"] = rewrite_wayback_urls(
            data["featured_image_url"], DOMAIN
        )
        # Strip leftover Wayback prefix if any
        data["featured_image_url"] = re.sub(
            r'^https?://web\.archive\.org/web/\d+/', '', data["featured_image_url"]
        )
        # Prefer full-size image: strip thumbnail suffix like -200x200 or -300x200
        data["featured_image_url"] = re.sub(
            r'-\d+x\d+(\.\w+)$', r'\1', data["featured_image_url"]
        )

    return data

def download_image(url, slug):
    """
    Download an image to the correct year/month uploads folder.
    Returns the local path relative to wp-content/uploads, or None.
    """
    if not url:
        return None
    try:
        # Try to get the original URL if this is still a Wayback URL
        orig_url = re.sub(r'^https?://web\.archive\.org/web/\d+/', '', url)
        # Determine year/month from URL path if possible, default to today
        m = re.search(r'/(\d{4})/(\d{2})/', orig_url)
        if m:
            year, month = m.group(1), m.group(2)
        else:
            year, month = datetime.now().strftime("%Y"), datetime.now().strftime("%m")

        dest_dir = WP_UPLOADS / year / month
        dest_dir.mkdir(parents=True, exist_ok=True)

        filename = os.path.basename(urllib.parse.urlparse(orig_url).path)
        if not filename or '.' not in filename:
            filename = f"{slug}-featured.jpg"

        dest = dest_dir / filename
        if dest.exists():
            return f"{year}/{month}/{filename}"

        # Prefer the Wayback version of the image to avoid hotlinking issues
        fetch_url = url if "web.archive.org" in url else f"{WB_BASE}/20210101000000*/{orig_url}"
        r = get(fetch_url, stream=True)
        if r.status_code == 200:
            with open(dest, "wb") as f:
                for chunk in r.iter_content(8192):
                    f.write(chunk)
            return f"{year}/{month}/{filename}"
    except Exception as e:
        return None
    return None

# ── Main recovery runner ──────────────────────────────────────────────────────

def recover_post(item, download_images=True):
    """Fetch + extract a single post. Returns result dict."""
    result = {
        "slug": item["slug"],
        "url": item["url"],
        "status": "error",
        "recovered_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "snapshot": {
            "cdx_timestamp": item.get("timestamp", ""),
            "wayback_url": None,
        },
        "data": None,
        "local_image": None,
        "error": None,
    }

    wb_url = best_wayback_url(item["url"], item.get("timestamp"))
    if not wb_url:
        result["error"] = "No Wayback snapshot found"
        return result

    result["snapshot"]["wayback_url"] = wb_url

    try:
        r = get(wb_url)
        if r.status_code != 200:
            result["error"] = f"HTTP {r.status_code}"
            return result
    except Exception as e:
        result["error"] = str(e)
        return result

    soup = clean_wayback_html(r.text, wb_url)
    post = extract_post(soup, wb_url)

    if not post["title"]:
        result["error"] = "Could not extract title — page may be a redirect or non-post"
        return result

    result["data"] = post
    result["status"] = "ok"

    if download_images and post["featured_image_url"]:
        local_img = download_image(post["featured_image_url"], item["slug"])
        result["local_image"] = local_img

    return result

# ── Report writer ─────────────────────────────────────────────────────────────

def write_report(results, total_missing):
    ok = [r for r in results if r["status"] == "ok"]
    errors = [r for r in results if r["status"] == "error"]
    lines = [
        "# Vancouver Weekly – Wayback Recovery Report",
        f"\nGenerated: {datetime.now().strftime('%Y-%m-%d %H:%M')}",
        "",
        "## Summary",
        f"- Posts in agency DB: 1,241",
        f"- Unique post URLs found in Wayback CDX: (see cdx_all_urls.json)",
        f"- Posts missing from current DB: {total_missing}",
        f"- Recovered in this run: {len(ok)}",
        f"- Failed: {len(errors)}",
        "",
        "## Recovered posts",
        "",
    ]
    for r in ok:
        d = r["data"]
        lines.append(f"### {d['title'] or r['slug']}")
        lines.append(f"- **Slug:** `{r['slug']}`")
        lines.append(f"- **Date:** {d['date']}")
        lines.append(f"- **Author:** {d['author']}")
        lines.append(f"- **Categories:** {', '.join(d['categories']) if d['categories'] else '—'}")
        lines.append(f"- **Featured image:** {d['featured_image_url'] or '—'}")
        lines.append(f"- **Local image:** {r['local_image'] or '—'}")
        lines.append(f"- **Wayback URL:** {r.get('wayback_url', '—')}")
        lines.append(f"- **Notes:** {'; '.join(d['extraction_notes']) if d['extraction_notes'] else '—'}")
        lines.append("")

    if errors:
        lines += ["## Failed posts", ""]
        for r in errors:
            lines.append(f"- `{r['slug']}` — {r['error']}")
        lines.append("")

    REPORT_FILE.write_text("\n".join(lines))
    print(f"\n[report] Written to {REPORT_FILE}")

# ── Checkpoint helpers ────────────────────────────────────────────────────────

CHECKPOINT_FILE = SCRIPT_DIR / "checkpoint.json"

def load_checkpoint():
    """
    Return (success, failed) as two sets of slugs.
    Handles legacy formats (single "done" list, or old "succeeded" key) gracefully.
    """
    if not CHECKPOINT_FILE.exists():
        return set(), set()
    data = json.loads(CHECKPOINT_FILE.read_text())
    # Oldest format: single "done" list
    if "done" in data and "success" not in data and "succeeded" not in data:
        return set(data["done"]), set()
    # Previous format used "succeeded" — migrate key name
    if "succeeded" in data and "success" not in data:
        return set(data["succeeded"]), set(data.get("failed", []))
    return set(data.get("success", [])), set(data.get("failed", []))

def save_checkpoint(success, failed):
    CHECKPOINT_FILE.write_text(json.dumps(
        {"success": sorted(success), "failed": sorted(failed)},
        indent=2,
    ))

# ── Entry point ───────────────────────────────────────────────────────────────

def main():
    import argparse
    parser = argparse.ArgumentParser(
        description="Vancouver Weekly Wayback recovery",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python3 wayback_recovery.py --batch 50           # try 50 posts
  python3 wayback_recovery.py --resume             # full run, skip succeeded posts
  python3 wayback_recovery.py --batch 50 --resume  # next 50, skip succeeded posts
  python3 wayback_recovery.py --retry-failed       # retry only previously failed posts
  python3 wayback_recovery.py                      # full run from scratch
""",
    )
    parser.add_argument("--batch", type=int, default=0,
                        help="Stop after N posts (0 = no limit, run everything)")
    parser.add_argument("--resume", action="store_true",
                        help="Skip posts that previously succeeded; re-queue failed ones")
    parser.add_argument("--retry-failed", action="store_true",
                        help="Run only the posts that previously failed, ignore succeeded")
    parser.add_argument("--no-cache", action="store_true",
                        help="Re-fetch CDX data even if cached")
    parser.add_argument("--skip-images", action="store_true",
                        help="Skip image downloads")
    parser.add_argument("--from-year", type=int, default=0,
                        help="Only recover posts captured on or before this year")
    parser.add_argument("--test", type=int, default=0, help=argparse.SUPPRESS)
    args = parser.parse_args()

    if args.test and not args.batch:
        args.batch = args.test

    RECOVERED_DIR.mkdir(exist_ok=True)
    IMAGES_DIR.mkdir(exist_ok=True)

    # Step 1: CDX
    cdx_records = fetch_cdx_urls(use_cache=not args.no_cache)
    print(f"[cdx] Total captured URLs: {len(cdx_records)}")

    # Step 2: Compare against existing DB
    existing = load_existing_slugs()
    print(f"[db]  Existing post slugs in agency DB: {len(existing)}")
    missing = find_missing(cdx_records, existing)
    print(f"[gap] Missing posts (in Wayback but not in DB): {len(missing)}")
    MISSING_URLS_FILE.write_text(json.dumps(missing, indent=2))

    if args.from_year:
        def year_of(item):
            return int(item["timestamp"][:4]) if item["timestamp"] else 9999
        missing = [m for m in missing if year_of(m) <= args.from_year]
        print(f"[filter] After year filter (<= {args.from_year}): {len(missing)}")

    total_missing = len(missing)

    # Step 3: Load checkpoint and decide what to run
    success, failed = load_checkpoint()

    if args.retry_failed:
        run_list = [m for m in missing if m["slug"] in failed]
        print(f"[retry] Re-running {len(run_list)} previously failed posts")
        failed -= {m["slug"] for m in run_list}

    elif args.resume:
        before = len(missing)
        run_list = [m for m in missing if m["slug"] not in success]
        skipped = before - len(run_list)
        retrying = len([m for m in run_list if m["slug"] in failed])
        print(f"[resume] Skipping {skipped} successful posts, "
              f"{retrying} failed posts will be retried, "
              f"{len(run_list)} total remaining")
        failed -= {m["slug"] for m in run_list if m["slug"] in failed}

    else:
        run_list = missing

    # Step 4: Apply batch limit
    if args.batch:
        run_list = run_list[:args.batch]
        print(f"[batch] Capped to {args.batch} posts")

    print(f"\n[run] Recovering {len(run_list)} posts…\n")

    results = []
    try:
        for i, item in enumerate(run_list, 1):
            print(f"  [{i}/{len(run_list)}] {item['slug']} …", end=" ", flush=True)
            result = recover_post(item, download_images=not args.skip_images)
            results.append(result)

            # Save individual JSON immediately
            out_file = RECOVERED_DIR / f"{item['slug']}.json"
            out_file.write_text(json.dumps(result, indent=2, default=str))

            # Update checkpoint — move slug to the right bucket
            slug = item["slug"]
            if result["status"] == "ok":
                success.add(slug)
                failed.discard(slug)
            else:
                failed.add(slug)
                success.discard(slug)
            save_checkpoint(success, failed)

            status = "ok" if result["status"] == "ok" else f"FAIL: {result.get('error','')}"
            print(status)

    except KeyboardInterrupt:
        print(f"\n\n[interrupted] Checkpoint saved "
              f"({len(success)} succeeded, {len(failed)} failed).")
        print("Re-run with --resume to continue, or --retry-failed to retry failures.")

    # Write summary report
    write_report(results, total_missing)

    ok_count = sum(1 for r in results if r["status"] == "ok")
    fail_count = sum(1 for r in results if r["status"] != "ok")
    remaining = total_missing - len(success) - len(failed)
    print(f"\n{'='*60}")
    print(f"This run:  {ok_count} succeeded, {fail_count} failed.")
    print(f"Checkpoint totals:  {len(success)} succeeded, {len(failed)} failed, "
          f"{remaining} not yet attempted.")
    if remaining > 0:
        print("Continue:  python3 wayback_recovery.py --resume")
    if failed:
        print(f"Retry failures ({len(failed)}):  python3 wayback_recovery.py --retry-failed")
    print(f"Results: {RECOVERED_DIR}")
    print(f"Report:  {REPORT_FILE}")

    return results

if __name__ == "__main__":
    main()
