#!/usr/bin/env python3
"""
Vancouver Weekly – WordPress import script.

Reads every slug in audit_clean.txt, loads its recovered_posts/slug.json,
and creates a WordPress post via WP-CLI.

Features:
  - Dry-run mode (default) — shows what would be created, touches nothing
  - --run flag to actually import
  - Checkpoint file (import_checkpoint.json) — resume after interruption
  - Authors created automatically if they don't exist
  - Categories created automatically if they don't exist
  - Featured images attached if local file exists in wp-content/uploads/
  - Original publication dates preserved exactly
  - Original URL slugs preserved exactly
  - Rate-limited: 1 post per second to avoid overwhelming PHP/MySQL

Usage:
  python3 import_to_wordpress.py              # dry run — shows first 10
  python3 import_to_wordpress.py --dry-run --batch 50   # dry run, 50 posts
  python3 import_to_wordpress.py --run                  # live import, all posts
  python3 import_to_wordpress.py --run --batch 100      # live import, 100 posts
  python3 import_to_wordpress.py --run --resume         # continue after interruption
"""

import json
import os
import re
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path

# ── Paths & config ─────────────────────────────────────────────────────────────

RECOVERY_DIR = Path(__file__).parent
POSTS_DIR    = RECOVERY_DIR / "recovered_posts"
CLEAN_LIST   = RECOVERY_DIR / "audit_clean.txt"
CHECKPOINT   = RECOVERY_DIR / "import_checkpoint.json"
IMPORT_LOG   = RECOVERY_DIR / "import_run.log"

WP_ROOT  = Path("/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public")
PHP      = Path("/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php")
WP_CLI   = Path("/tmp/wp-cli.phar")
PHP_INI  = Path("/tmp/wp-cli-php.ini")   # contains mysqli.default_socket=...
WP_UPLOADS = WP_ROOT / "wp-content/uploads"

RATE_LIMIT = 1.0   # seconds between WP-CLI calls

# ── WP-CLI wrapper ─────────────────────────────────────────────────────────────

def wp(*args, input_data=None, capture=True):
    """Run a WP-CLI command. Returns (returncode, stdout, stderr)."""
    cmd = [
        str(PHP), f"-c{PHP_INI}", str(WP_CLI),
        *args,
        f"--path={WP_ROOT}",
        "--skip-themes",
        "--skip-plugins=all-in-one-wp-migration",   # skip heavy plugins, keep vw-security
        "--porcelain",   # machine-readable output where supported
    ]
    result = subprocess.run(
        cmd,
        input=input_data,
        capture_output=capture,
        text=True,
    )
    return result.returncode, (result.stdout or "").strip(), (result.stderr or "").strip()

def wp_get_or_create_author(display_name):
    """Return user ID for display_name, creating a subscriber account if needed."""
    if not display_name or display_name.lower() in ('', 'vancouver weekly', 'admin'):
        return 1   # default to admin user

    # Sanitise into a login name
    login = re.sub(r'[^a-z0-9_.-]', '.', display_name.lower()).strip('.')
    login = re.sub(r'\.{2,}', '.', login) or 'contributor'

    # Check if user exists by login
    rc, uid, _ = wp("user", "get", login, "--field=ID")
    if rc == 0 and uid.isdigit():
        return int(uid)

    # Create subscriber account — no password email (--send-user-notification=false)
    email = f"{login}@contributors.vancouverweekly.com"
    rc, uid, err = wp(
        "user", "create", login, email,
        f"--display_name={display_name}",
        "--role=subscriber",
        "--user_pass=PLACEHOLDER_CHANGE_ME",
    )
    if rc == 0 and uid.isdigit():
        return int(uid)

    return 1   # fallback to admin

def wp_get_or_create_category(name):
    """Return term ID for category name, creating it if needed."""
    rc, tid, _ = wp("term", "get", "category", name, "--by=name", "--field=term_id")
    if rc == 0 and tid.isdigit():
        return int(tid)
    rc, tid, _ = wp("term", "create", "category", name)
    if rc == 0 and tid.isdigit():
        return int(tid)
    return None

def wp_attach_image(local_path_relative, post_id, slug):
    """
    Given a path relative to wp-content/uploads/ (e.g. '2016/09/photo.jpg'),
    import it into the media library and set as featured image.
    Returns attachment ID or None.
    """
    full_path = WP_UPLOADS / local_path_relative
    if not full_path.exists():
        return None

    rc, att_id, err = wp(
        "media", "import", str(full_path),
        f"--post_id={post_id}",
        "--featured_image",
        f"--title={slug}",
    )
    if rc == 0 and att_id.isdigit():
        return int(att_id)
    return None

# ── Checkpoint helpers ─────────────────────────────────────────────────────────

def load_import_checkpoint():
    if not CHECKPOINT.exists():
        return set(), set()
    d = json.loads(CHECKPOINT.read_text())
    return set(d.get("imported", [])), set(d.get("failed", []))

def save_import_checkpoint(imported, failed):
    CHECKPOINT.write_text(json.dumps(
        {"imported": sorted(imported), "failed": sorted(failed)},
        indent=2,
    ))

# ── Log helper ─────────────────────────────────────────────────────────────────

def log(msg):
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}"
    print(line)
    with open(IMPORT_LOG, "a") as f:
        f.write(line + "\n")

# ── Core: import one post ──────────────────────────────────────────────────────

def import_post(post_data, dry_run=True):
    """
    Import a single post. Returns ('ok', wp_post_id) or ('error', reason).
    If dry_run=True, validates the data but creates nothing.
    """
    slug    = post_data.get("slug", "")
    data    = post_data.get("data") or {}
    title   = data.get("title", "").strip()
    content = data.get("content", "").strip()
    author  = data.get("author", "").strip()
    date    = data.get("date", "").strip()
    # Fallback: derive date from CDX snapshot timestamp if post date is missing
    if not date:
        cdx_ts = (post_data.get("snapshot") or {}).get("cdx_timestamp", "")
        if not cdx_ts:
            # Older format stored wayback_url directly — extract timestamp from it
            wb = post_data.get("wayback_url", "") or data.get("wayback_url", "")
            import re as _re
            m = _re.search(r'/web/(\d{14})/', wb)
            cdx_ts = m.group(1) if m else ""
        if len(cdx_ts) >= 8:
            # CDX format: YYYYMMDDHHmmss → use as approximate publish date
            try:
                dt = datetime.strptime(cdx_ts[:14], "%Y%m%d%H%M%S")
                date = dt.strftime("%Y-%m-%dT%H:%M:%S+00:00")
            except Exception:
                pass
    cats    = data.get("categories", [])
    tags    = data.get("tags", [])
    img     = post_data.get("local_image", "")

    if not title:
        return "error", "no title"
    if not content:
        return "error", "no content"

    # Normalise date to MySQL format (YYYY-MM-DD HH:MM:SS).
    # Handles: 2016-06-20T21:30:24+00:00  |  2016-06-20T21:30:24Z  |  2016-06-20 21:30:24
    date_mysql = ""
    if date:
        m = re.match(r'(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})', date)
        if m:
            date_mysql = f"{m.group(1)} {m.group(2)}"
        else:
            m = re.match(r'(\d{4}-\d{2}-\d{2})', date)
            if m:
                date_mysql = f"{m.group(1)} 00:00:00"

    if dry_run:
        cat_str = ", ".join(cats) if cats else "—"
        return "dry-run", f"title='{title[:50]}' author='{author}' date='{date_mysql}' cats=[{cat_str}]"

    # ── Live import ──────────────────────────────────────────────────────

    # 1. Resolve author ID
    author_id = wp_get_or_create_author(author)

    # 2. Resolve category IDs
    cat_ids = []
    for cat in cats:
        tid = wp_get_or_create_category(cat)
        if tid:
            cat_ids.append(str(tid))

    # 3. Create the post
    wp_args = [
        "post", "create",
        f"--post_title={title}",
        f"--post_content={content}",
        f"--post_status=publish",
        f"--post_name={slug}",
        f"--post_author={author_id}",
        f"--post_type=post",
    ]
    if date_mysql:
        wp_args += [f"--post_date={date_mysql}", f"--post_date_gmt={date_mysql}"]
    if cat_ids:
        wp_args.append(f"--post_category={','.join(cat_ids)}")
    if tags:
        wp_args.append(f"--tags_input={','.join(tags)}")

    rc, post_id, err = wp(*wp_args)
    if rc != 0 or not post_id.isdigit():
        return "error", f"wp post create failed: {err[:120]}"

    post_id = int(post_id)

    # 4. Attach featured image if we have a local file
    if img:
        wp_attach_image(img, post_id, slug)

    return "ok", post_id

# ── Main ─────────────────────────────────────────────────────────────────────

def main():
    import argparse
    parser = argparse.ArgumentParser(
        description="Vancouver Weekly – WordPress post importer",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python3 import_to_wordpress.py                    # dry run, first 10 posts
  python3 import_to_wordpress.py --dry-run --batch 50  # dry run, 50 posts
  python3 import_to_wordpress.py --run --batch 100  # live import, 100 posts
  python3 import_to_wordpress.py --run --resume     # live import, skip already imported
  python3 import_to_wordpress.py --run              # full live import (all 2,985 posts)
""",
    )
    parser.add_argument("--run",      action="store_true", help="Actually import (default is dry run)")
    parser.add_argument("--dry-run",  action="store_true", help="Explicit dry run (default if --run not given)")
    parser.add_argument("--batch",    type=int, default=10, help="Max posts to process (default 10 for dry run, 0=all for --run)")
    parser.add_argument("--resume",   action="store_true", help="Skip posts already imported")
    args = parser.parse_args()

    dry_run = not args.run
    batch   = args.batch if (dry_run or args.batch != 10) else 0   # no limit on full live run

    if dry_run:
        print("\n⚠️  DRY RUN — nothing will be written to WordPress.")
        print("   Pass --run to perform the actual import.\n")
    else:
        print("\n🚀 LIVE IMPORT — writing to WordPress.\n")

    # Validate tools exist
    for p in [PHP, WP_CLI, PHP_INI]:
        if not p.exists():
            print(f"ERROR: required file not found: {p}")
            sys.exit(1)

    # Load approved slug list
    if not CLEAN_LIST.exists():
        print(f"ERROR: {CLEAN_LIST} not found — run security_audit.py first")
        sys.exit(1)

    approved = [s.strip() for s in CLEAN_LIST.read_text().splitlines() if s.strip()]
    log(f"Approved slugs in audit_clean.txt: {len(approved)}")

    # Load checkpoint
    imported, failed = load_import_checkpoint() if args.resume else (set(), set())
    if args.resume:
        before = len(approved)
        approved = [s for s in approved if s not in imported]
        log(f"[resume] Skipping {before - len(approved)} already imported, {len(approved)} remaining")

    if batch:
        approved = approved[:batch]

    log(f"Processing {len(approved)} posts (dry_run={dry_run})\n")

    ok_count   = 0
    fail_count = 0

    try:
        for i, slug in enumerate(approved, 1):
            post_file = POSTS_DIR / f"{slug}.json"
            if not post_file.exists():
                log(f"  [{i}/{len(approved)}] {slug} — SKIP (JSON not found)")
                failed.add(slug)
                save_import_checkpoint(imported, failed)
                continue

            post_data = json.loads(post_file.read_text())
            status, detail = import_post(post_data, dry_run=dry_run)

            if status in ("ok", "dry-run"):
                ok_count += 1
                imported.add(slug)
                icon = "✓" if status == "ok" else "~"
                log(f"  [{i}/{len(approved)}] {icon} {slug} — {detail}")
            else:
                fail_count += 1
                failed.add(slug)
                log(f"  [{i}/{len(approved)}] ✗ {slug} — {detail}")

            if not dry_run:
                save_import_checkpoint(imported, failed)
                time.sleep(RATE_LIMIT)

    except KeyboardInterrupt:
        log(f"\n[interrupted] Checkpoint saved ({len(imported)} imported, {len(failed)} failed)")
        log("Re-run with --run --resume to continue")

    log(f"\n{'='*60}")
    log(f"{'Dry-run preview' if dry_run else 'Import'} complete.")
    log(f"  OK:     {ok_count}")
    log(f"  Failed: {fail_count}")
    if not dry_run:
        remaining = len([s for s in CLEAN_LIST.read_text().splitlines() if s.strip() and s not in imported])
        log(f"  Remaining (not yet imported): {remaining}")
        if failed:
            log(f"\nFailed slugs saved in {CHECKPOINT}")
    if dry_run and len(approved) < sum(1 for _ in CLEAN_LIST.read_text().splitlines() if _.strip()):
        total = sum(1 for _ in CLEAN_LIST.read_text().splitlines() if _.strip())
        log(f"\nThis was a preview of {len(approved)}/{total} posts.")
        log("To import all: python3 import_to_wordpress.py --run")

if __name__ == "__main__":
    main()
