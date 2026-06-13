#!/usr/bin/env python3
"""
Vancouver Weekly – pre-import spam filter.

Moves junk posts from recovered_posts/ to recovered_posts/excluded/
before anything is imported into WordPress.

Categories excluded:
  1. Ivermectin / pharma spam (2,023+ posts in multiple languages)
  2. Other drug brand names: stromectol, mectizan, soolantra, sklice, heartgard
  3. WordPress query-string artifacts: tag_ids~N, cat_ids~N, exact_date~, author_ids~
  4. Casino / gambling spam
  5. Posts with no extractable title (empty, failed extractions)

Run this ONCE before the import step:
  python3 filter_spam.py            # dry run — shows what would be moved
  python3 filter_spam.py --apply    # actually moves the files
"""

import argparse
import json
import os
import re
import shutil
from pathlib import Path

RECOVERY_DIR = Path(__file__).parent
POSTS_DIR = RECOVERY_DIR / "recovered_posts"
EXCLUDED_DIR = POSTS_DIR / "excluded"
FILTER_REPORT = RECOVERY_DIR / "filter_report.md"

# ── Spam detection rules ──────────────────────────────────────────────────────

SPAM_SLUG_PATTERNS = re.compile(
    r'ivermect'           # ivermectin (all languages/brands)
    r'|stromectol'        # brand name for ivermectin
    r'|mectizan'          # another ivermectin brand
    r'|soolantra'         # topical ivermectin
    r'|sklice'            # ivermectin lotion
    r'|heartgard'         # ivermectin for pets (spam context)
    r'|casino'
    r'|gambling'
    r'|payday.?loan'
    r'|viagra|cialis'
    r'|pharmacy(?!.*vancouver)'   # "pharmacy" alone is spam; "vancouver pharmacy" may be legit
    r'|bitcoin|crypto(?!graph)'   # crypto = spam; cryptograph = legit music/art word
    r'|forex',
    re.I,
)

ARTIFACT_SLUG_PATTERNS = re.compile(
    r'^(tag_ids|cat_ids|exact_date|author_ids)[~_]',
    re.I,
)

TIMEOUT_ERRORS = re.compile(r'timed out|Max retries|NewConnectionError|Connection reset', re.I)

# Slugs that are clearly non-article technical files/pages regardless of content
JUNK_SLUGS = re.compile(
    r'^index\.php$|^index\.html$|\.svg$|\.png$|\.jpg$'
    r'|^powered_by_|^request_format~|^author_ids~',
    re.I,
)

def classify(slug, post_json):
    """Return ('spam'|'artifact'|'no-title'|'timeout-retry'|'ok', reason)."""
    if ARTIFACT_SLUG_PATTERNS.match(slug):
        return "artifact", "WordPress query-string artifact (tag_ids/cat_ids/exact_date)"
    if JUNK_SLUGS.search(slug):
        return "artifact", "Technical file or non-article URL"
    if SPAM_SLUG_PATTERNS.search(slug):
        return "spam", "Slug matches spam pattern"

    data = post_json.get("data") or {}
    title = (data.get("title") or "").strip()
    error = post_json.get("error") or ""

    if not title:
        # If it failed due to a network timeout, keep it for a retry — don't exclude it
        if TIMEOUT_ERRORS.search(error):
            return "timeout-retry", f"Network timeout — should be retried: {error[:80]}"
        # Failed to extract title from a page that did load — real junk (redirect, etc.)
        return "no-title", f"No title extracted from loaded page: {error[:80]}"

    return "ok", ""

# ── Main ─────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="Filter spam from recovered posts")
    parser.add_argument("--apply", action="store_true",
                        help="Actually move files. Without this flag, just shows what would happen.")
    args = parser.parse_args()

    if args.apply:
        EXCLUDED_DIR.mkdir(exist_ok=True)

    files = sorted(POSTS_DIR.glob("*.json"))
    results = {"spam": [], "artifact": [], "no-title": [], "timeout-retry": [], "ok": []}

    for f in files:
        slug = f.stem
        try:
            post = json.loads(f.read_text())
        except Exception:
            results["artifact"].append((slug, "Unreadable JSON"))
            continue

        category, reason = classify(slug, post)
        results[category].append((slug, reason))

        # Move spam/artifact/no-title to excluded; keep timeout-retry in place for re-fetching
        if args.apply and category in ("spam", "artifact", "no-title"):
            dest = EXCLUDED_DIR / f.name
            shutil.move(str(f), str(dest))

    # ── Print summary ─────────────────────────────────────────────────────
    total = len(files)
    excluded = len(results["spam"]) + len(results["artifact"]) + len(results["no-title"])
    clean = len(results["ok"])
    retries = len(results["timeout-retry"])

    mode = "APPLIED" if args.apply else "DRY RUN"
    print(f"\n=== Spam filter — {mode} ===")
    print(f"Total recovered files:   {total:,}")
    print(f"  Spam posts:            {len(results['spam']):,}")
    print(f"  WP query artifacts:    {len(results['artifact']):,}")
    print(f"  No-title stubs:        {len(results['no-title']):,}")
    print(f"  ─────────────────────")
    print(f"  Total excluded:        {excluded:,}")
    print(f"  Timeout (keep/retry):  {retries:,}")
    print(f"  Clean / ready to import: {clean:,}")

    if not args.apply:
        print(f"\nThis was a dry run. To actually move files, run:")
        print(f"  python3 filter_spam.py --apply")
    else:
        print(f"\nFiles moved to: {EXCLUDED_DIR}")

    # ── Write report ──────────────────────────────────────────────────────
    lines = [
        "# Vancouver Weekly – Spam Filter Report",
        f"\nMode: {mode}",
        f"\n## Summary",
        f"- Total recovered files: {total:,}",
        f"- Spam posts excluded: {len(results['spam']):,}",
        f"- WP query artifacts excluded: {len(results['artifact']):,}",
        f"- No-title stubs excluded: {len(results['no-title']):,}",
        f"- Timeout failures kept for retry: {retries:,}",
        f"- **Clean posts ready to import: {clean:,}**",
        "",
    ]
    for category, label in [
        ("spam", "Spam posts"),
        ("artifact", "WordPress query artifacts"),
        ("no-title", "No-title stubs"),
        ("timeout-retry", "Timeout failures (kept, not excluded)"),
    ]:
        if results[category]:
            lines.append(f"## {label} ({len(results[category])})")
            lines.append("")
            for slug, reason in results[category][:200]:   # cap list length in report
                lines.append(f"- `{slug}` — {reason}")
            if len(results[category]) > 200:
                lines.append(f"- … and {len(results[category]) - 200} more")
            lines.append("")

    FILTER_REPORT.write_text("\n".join(lines))
    print(f"Report: {FILTER_REPORT}")

if __name__ == "__main__":
    main()
