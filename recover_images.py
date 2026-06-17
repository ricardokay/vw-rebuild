#!/usr/bin/env python3
"""
Vancouver Weekly — Wayback Machine Image Recovery
==================================================
Strategy: query the Wayback CDX index for what it actually has, then download
those URLs directly. Far more reliable than the availability API, which misses
most images because Wayback only crawled thumbnail variants embedded in pages.

What Wayback has (as of June 2026):
  - 2,606 full-size originals (no WxH resize suffix)
  -   787 large thumbnails at 1024px width (usable quality fallback)
  - 8,331 thumbnails at smaller sizes (240x150, 150x150, etc.)

This script recovers the full-size set by default.
Pass --include-large-thumbs to also pull the 1024px variants.

Usage:
  python3 recover_images.py extract              # pull CDX list → image-urls.txt
  python3 recover_images.py recover              # download images (resumable)
  python3 recover_images.py recover --limit 20   # test run, 20 URLs then stop
  python3 recover_images.py report               # progress summary, no downloads

Stop at any time with Ctrl-C — progress is saved after every URL.
"""

import re
import os
import csv
import sys
import time
import signal
import argparse
import requests
from pathlib import Path
from urllib.parse import urlparse
from datetime import datetime


# ── Configuration ─────────────────────────────────────────────────────────────

CDX_API       = "http://web.archive.org/cdx/search/cdx"
CDX_SITE      = "vancouverweekly.com/wp-content/uploads/*"

URL_LIST      = "image-urls.txt"          # CDX-derived: url<TAB>timestamp
OUTPUT_DIR    = Path("recovered-images")
LEDGER_FILE   = "image-recovery-ledger.csv"
GAPS_FILE     = "image-recovery-gaps.txt"

# Politeness
BASE_DELAY    = 2.0    # seconds between downloads (Wayback prefers ~1 req/s)
BACKOFF_BASE  = 60     # seconds to wait on first 429; doubles each time
MAX_BACKOFF   = 600    # cap at 10 minutes
TIMEOUT_DL    = 90     # seconds per image download
TIMEOUT_CDX   = 30     # seconds for CDX API calls

USER_AGENT    = (
    "VancouverWeekly-ImageRecovery/1.0 "
    "(ricardokay@gmail.com; recovering archived media for own publication)"
)

LEDGER_FIELDS = ["url", "status", "wb_url", "local_path", "attempts", "updated", "notes"]

THUMB_RE      = re.compile(r'-\d+x\d+\.(jpe?g|png|gif|tiff?|webp)$', re.IGNORECASE)
LARGE_RE      = re.compile(r'-1024x\d+\.(jpe?g|png|gif|tiff?|webp)$', re.IGNORECASE)
IMAGE_EXT_RE  = re.compile(r'\.(jpe?g|png|gif|tiff?|webp)$', re.IGNORECASE)


# ── Graceful shutdown ─────────────────────────────────────────────────────────

_stop = False

def _handle_sig(sig, frame):
    global _stop
    print("\n[!] Interrupt received — finishing current file then stopping cleanly.")
    _stop = True

signal.signal(signal.SIGINT,  _handle_sig)
signal.signal(signal.SIGTERM, _handle_sig)


# ── Step 1: Extract URL list from CDX ─────────────────────────────────────────

def cmd_extract(args):
    """
    Query the Wayback CDX index for all upload URLs that returned HTTP 200.
    Saves a tab-delimited file: original_url<TAB>best_timestamp
    """
    include_large = args.include_large_thumbs
    session = requests.Session()
    session.headers["User-Agent"] = USER_AGENT

    print(f"Querying CDX index for {CDX_SITE} …")
    resp = session.get(
        CDX_API,
        params={
            "url":      CDX_SITE,
            "output":   "text",
            "filter":   "statuscode:200",
            "fl":       "original,timestamp",
            "collapse": "original",        # one row per unique URL
            "limit":    "100000",
        },
        timeout=TIMEOUT_CDX,
    )
    resp.raise_for_status()
    lines = [ln for ln in resp.text.splitlines() if ln.strip()]
    print(f"  CDX returned {len(lines):,} rows")

    entries = []   # list of (url, timestamp)
    for line in lines:
        parts = line.split()
        if len(parts) < 2:
            continue
        url, ts = parts[0], parts[1]

        if not IMAGE_EXT_RE.search(url):
            continue   # skip non-image files

        is_thumb = bool(THUMB_RE.search(url))
        is_large = bool(LARGE_RE.search(url))

        if is_thumb and not is_large:
            continue   # skip small thumbnails

        if is_large and not include_large:
            continue   # skip 1024px thumbs unless requested

        entries.append((url, ts))

    out = args.urls
    with open(out, "w") as f:
        for url, ts in sorted(entries):
            f.write(f"{url}\t{ts}\n")

    label = "full-size originals"
    if include_large:
        label += " + 1024px thumbnails"
    print(f"✓ {len(entries):,} {label} → {out}")


# ── Ledger helpers ────────────────────────────────────────────────────────────

def load_ledger(path):
    ledger = {}
    if not os.path.exists(path):
        return ledger
    with open(path, "r", newline="", encoding="utf-8") as f:
        for row in csv.DictReader(f):
            ledger[row["url"]] = row
    return ledger

def save_ledger(ledger, path):
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=LEDGER_FIELDS)
        w.writeheader()
        w.writerows(ledger.values())

def record(ledger, path, url, **kwargs):
    entry = ledger.get(url, {
        "url": url, "status": "pending", "wb_url": "",
        "local_path": "", "attempts": "0", "updated": "", "notes": ""
    })
    entry.update(kwargs)
    entry["attempts"] = str(int(entry.get("attempts", 0)))
    entry["updated"]  = datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
    ledger[url] = entry
    save_ledger(ledger, path)


# ── Download helper ───────────────────────────────────────────────────────────

def build_wb_url(original_url, timestamp):
    """Construct a raw-content Wayback URL. The 'if_' modifier skips toolbar."""
    return f"https://web.archive.org/web/{timestamp}if_/{original_url}"


def dest_for_url(url, output_dir):
    """
    https://vancouverweekly.com/wp-content/uploads/2015/04/foo.jpg
    → output_dir/2015/04/foo.jpg
    """
    path = urlparse(url).path    # /wp-content/uploads/2015/04/foo.jpg
    rel  = re.sub(r"^/wp-content/uploads/", "", path)
    return Path(output_dir) / rel


def download_file(session, wb_url, dest_path):
    """
    Download wb_url → dest_path.
    Returns True on success, 'rate_limited', or 'error:<msg>' string.
    """
    try:
        resp = session.get(wb_url, timeout=TIMEOUT_DL, stream=True)
        if resp.status_code == 429:
            return "rate_limited"
        resp.raise_for_status()
        dest_path.parent.mkdir(parents=True, exist_ok=True)
        with open(dest_path, "wb") as f:
            for chunk in resp.iter_content(65536):
                f.write(chunk)
        return True
    except Exception as e:
        return f"error:{e}"


# ── Step 2: Recover ───────────────────────────────────────────────────────────

def cmd_recover(args):
    url_file    = args.urls
    ledger_path = args.ledger
    output_dir  = Path(args.out)
    limit       = args.limit

    if not os.path.exists(url_file):
        print(f"[!] URL list not found: {url_file}")
        print("    Run 'extract' first.")
        sys.exit(1)

    # Load url+timestamp pairs
    entries = {}   # url → timestamp
    with open(url_file) as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            parts = line.split("\t")
            if len(parts) == 2:
                entries[parts[0]] = parts[1]
            else:
                entries[parts[0]] = "19700101000000"   # fallback

    ledger = load_ledger(ledger_path)

    done   = {"recovered", "not-found"}
    todo   = [(url, ts) for url, ts in entries.items()
              if ledger.get(url, {}).get("status") not in done]

    total   = len(entries)
    n_done  = total - len(todo)
    print(f"  {total:,} total | {n_done:,} already done | {len(todo):,} to process")

    if limit:
        todo = todo[:limit]
        print(f"  Session capped at {limit} URLs (--limit)")

    if not todo:
        print("  Nothing to do.")
        cmd_report(args)
        return

    output_dir.mkdir(parents=True, exist_ok=True)
    session = requests.Session()
    session.headers["User-Agent"] = USER_AGENT

    recovered = failed = 0
    backoff   = 0   # extra delay when rate-limited

    for i, (url, timestamp) in enumerate(todo, 1):
        if _stop:
            print("[!] Stopped cleanly.")
            break

        entry    = ledger.get(url, {})
        attempts = int(entry.get("attempts", 0))
        label    = f"[{i}/{len(todo)}]"

        wb_url = build_wb_url(url, timestamp)
        dest   = dest_for_url(url, output_dir)

        print(f"{label} {url}")

        # Already on disk (re-run after partial completion)
        if dest.exists() and dest.stat().st_size > 0:
            size = dest.stat().st_size
            print(f"  ✓ already on disk ({size:,} B)")
            record(ledger, ledger_path, url,
                   status="recovered", wb_url=wb_url,
                   local_path=str(dest), attempts=str(attempts + 1),
                   notes="already on disk")
            recovered += 1
            continue

        # Backoff pause if previous request was rate-limited
        if backoff:
            print(f"  ↳ rate-limit backoff: waiting {backoff}s …")
            time.sleep(backoff)
            backoff = 0

        time.sleep(BASE_DELAY)
        result = download_file(session, wb_url, dest)

        if result == "rate_limited":
            wait = min(BACKOFF_BASE * (2 ** min(attempts, 4)), MAX_BACKOFF)
            print(f"  ↳ 429 — retrying after {wait}s")
            time.sleep(wait)
            result = download_file(session, wb_url, dest)

        if result is True:
            size = dest.stat().st_size
            print(f"  ✓ {size:,} B → {dest}")
            record(ledger, ledger_path, url,
                   status="recovered", wb_url=wb_url,
                   local_path=str(dest), attempts=str(attempts + 1),
                   notes=f"{size} bytes")
            recovered += 1

        elif result == "rate_limited":
            print(f"  ✗ still rate-limited — marking failed for next run")
            record(ledger, ledger_path, url,
                   status="failed", attempts=str(attempts + 1),
                   wb_url=wb_url, notes="rate-limited on download (2 tries)")
            failed += 1
            backoff = BACKOFF_BASE

        else:
            print(f"  ✗ {result}")
            record(ledger, ledger_path, url,
                   status="failed", attempts=str(attempts + 1),
                   wb_url=wb_url, notes=str(result))
            failed += 1

    print(f"\nSession done — recovered {recovered}, failed {failed}")
    cmd_report(args)


# ── Step 3: Report ────────────────────────────────────────────────────────────

def cmd_report(args):
    ledger = load_ledger(args.ledger)
    counts = {}
    not_found_urls = []

    for entry in ledger.values():
        s = entry.get("status", "pending")
        counts[s] = counts.get(s, 0) + 1
        if s == "not-found":
            not_found_urls.append(entry["url"])

    total = sum(counts.values())
    print()
    print("═" * 62)
    print("  RECOVERY PROGRESS REPORT")
    print("═" * 62)
    print(f"  Total URLs tracked :  {total:>6,}")
    print(f"  Recovered ✓         :  {counts.get('recovered', 0):>6,}")
    print(f"  Failed (will retry) :  {counts.get('failed', 0):>6,}")
    print(f"  Not yet attempted   :  {counts.get('pending', 0):>6,}")
    print("═" * 62)

    if not_found_urls:
        with open(GAPS_FILE, "w") as f:
            f.write("\n".join(sorted(not_found_urls)) + "\n")
        print(f"\n  Gaps written to: {GAPS_FILE} ({len(not_found_urls):,} URLs)")

    if not ledger:
        print("  (No ledger found — run 'recover' first)")


# ── CLI ───────────────────────────────────────────────────────────────────────

def main():
    p = argparse.ArgumentParser(
        description="VW Wayback Machine image recovery",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__
    )
    sub = p.add_subparsers(dest="cmd", required=True)

    common = argparse.ArgumentParser(add_help=False)
    common.add_argument("--urls",   default=URL_LIST,        help="URL+timestamp list file")
    common.add_argument("--ledger", default=LEDGER_FILE,     help="Progress ledger CSV")
    common.add_argument("--out",    default=str(OUTPUT_DIR), help="Output directory")

    ext = sub.add_parser("extract", parents=[common],
                         help="Pull CDX index → image-urls.txt")
    ext.add_argument("--include-large-thumbs", action="store_true",
                     help="Also include 1024px-wide thumbnail variants (~787 more images)")

    rec = sub.add_parser("recover", parents=[common],
                         help="Download images (resumable)")
    rec.add_argument("--limit", type=int, default=None, metavar="N",
                     help="Stop after N URLs this session")

    rep = sub.add_parser("report", parents=[common],
                         help="Print progress summary without downloading")

    args = p.parse_args()

    if args.cmd == "extract":
        cmd_extract(args)
    elif args.cmd == "recover":
        cmd_recover(args)
    elif args.cmd == "report":
        cmd_report(args)


if __name__ == "__main__":
    main()
