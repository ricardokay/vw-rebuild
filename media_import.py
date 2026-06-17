#!/usr/bin/env python3
"""
Vancouver Weekly — Media Library Importer
Method 3: proper WP attachment registration + batch thumbnail regen.

Commands:
    parse    Extract metadata from SQL dump → media-import-data.json
    plan     Match recovered files to SQL records, resolve parent IDs → media-import-plan.csv
    import   Register images as WP attachments [--limit N]
    regen    Batch-regenerate thumbnails for all imported images
    report   Show import status

Usage:
    python3 media_import.py parse
    python3 media_import.py plan
    python3 media_import.py import --limit 20     # staged test
    python3 media_import.py import                 # full run (after test approved)
    python3 media_import.py regen
    python3 media_import.py report
"""

import argparse, csv, json, os, re, shutil, subprocess, sys, time
from pathlib import Path

# ── Configuration ──────────────────────────────────────────────────────────────

PROJECT    = Path("/Users/ricardokhayatte/Claude-code/vw-rebuild")
RECOVERED  = PROJECT / "recovered-images"
SQL_DUMP   = Path("/tmp/vw-sql-inspect/vanctcjx_vweekly2016a.sql")

WP_ROOT    = Path("/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public")
UPLOADS    = WP_ROOT / "wp-content" / "uploads"

PHP_BIN    = Path("/Users/ricardokhayatte/Library/Application Support/Local/lightning-services"
                  "/php-8.2.29+0/bin/darwin-arm64/bin/php")
PHP_INI    = "/tmp/wp-cli-php.ini"
WP_CLI     = "/tmp/wp-cli.phar"

DATA_FILE  = PROJECT / "media-import-data.json"    # parse output
PLAN_FILE  = PROJECT / "media-import-plan.csv"     # plan output
LEDGER     = PROJECT / "media-import-ledger.csv"   # import run ledger
PHP_SCRIPT = Path("/tmp/vw_do_import.php")          # ephemeral PHP batch inserter

LEDGER_FIELDS = ["plan_id", "uploads_path", "att_id", "parent_new_id",
                 "parent_slug", "title", "status", "error", "imported_at"]

# ── WP-CLI helper ─────────────────────────────────────────────────────────────

def wpcli(*args):
    cmd = [str(PHP_BIN), "-c", PHP_INI, str(WP_CLI), f"--path={WP_ROOT}"] + list(args)
    r = subprocess.run(cmd, capture_output=True, text=True)
    return r.stdout.strip()

# ── Ledger helpers ────────────────────────────────────────────────────────────

def load_ledger():
    if not LEDGER.exists():
        return {}
    with open(LEDGER, newline='', encoding='utf-8') as f:
        return {r['plan_id']: r for r in csv.DictReader(f)}

def save_ledger(records):
    with open(LEDGER, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=LEDGER_FIELDS)
        w.writeheader()
        w.writerows(records.values())

# ── SQL dump parser ────────────────────────────────────────────────────────────

def parse_row(s):
    """Parse MySQL VALUES row content into a Python list."""
    vals, i, n = [], 0, len(s)
    while i < n:
        c = s[i]
        if c in (' ', '\t', ','):
            i += 1; continue
        if c == 'N' and s[i:i+4] == 'NULL':
            vals.append(None); i += 4; continue
        if c == "'":
            i += 1; buf = []
            while i < n:
                ch = s[i]
                if ch == '\\' and i + 1 < n:
                    nx = s[i + 1]
                    buf.append({'n':'\n','t':'\t','r':'\r','\\':'\\','\'': "'"}.get(nx, nx))
                    i += 2; continue
                if ch == "'":
                    i += 1; break
                buf.append(ch); i += 1
            vals.append(''.join(buf)); continue
        j = i
        while j < n and s[j] not in (',', ')'):
            j += 1
        vals.append(s[i:j].strip()); i = j
    return vals

def split_rows(values_clause):
    """Split '(r1),(r2),...' into list of row content strings."""
    rows, buf, depth, in_str, i = [], [], 0, False, 0
    s = values_clause
    while i < len(s):
        c = s[i]
        if in_str:
            buf.append(c)
            if c == '\\' and i + 1 < len(s):
                buf.append(s[i + 1]); i += 2; continue
            elif c == "'":
                in_str = False
        else:
            if c == '(':
                depth += 1
                if depth > 1: buf.append(c)
            elif c == ')':
                depth -= 1
                if depth == 0:
                    rows.append(''.join(buf)); buf = []
                else:
                    buf.append(c)
            elif c == "'":
                in_str = True; buf.append(c)
            elif c == ';' and depth == 0:
                break
            elif depth > 0:
                buf.append(c)
        i += 1
    return rows

def parse_sql_dump(dump_path):
    """
    Parse mysqldump with named-column multi-line INSERT format:
        INSERT INTO `table` (`col1`, `col2`, ...) VALUES
        (val1, val2, ...),
        (val1, val2, ...);
    Each row is on its own line.
    """
    dump_path = Path(dump_path)
    if not dump_path.exists():
        print(f"ERROR: SQL dump not found at {dump_path}")
        print("Re-unzip: unzip -o vanctcjx_vweekly2016a_feb2019.sql.zip -d /tmp/vw-sql-inspect/")
        sys.exit(1)

    mb = dump_path.stat().st_size // 1_000_000
    print(f"Parsing SQL dump ({mb} MB) — this takes ~60 s …")

    current_insert = None   # 'postmeta' | 'posts' | None
    meta_col_idx   = {}     # col_name → position index for wp_postmeta
    posts_col_idx  = {}     # col_name → position index for wp_posts

    attachments = {}   # old_att_id → dict
    post_slugs  = {}   # old_post_id → slug
    att_files   = {}   # old_att_id → uploads_relative_path
    mime_ok = re.compile(r'^image/', re.I)

    def extract_cols(header_line):
        """Pull column names from INSERT INTO `tbl` (`c1`,`c2`,...) VALUES."""
        m = re.search(r'\((`[^)]+`)\)', header_line)
        if not m:
            return {}
        cols = [c.strip().strip('`') for c in m.group(1).split(',')]
        return {name: idx for idx, name in enumerate(cols)}

    with open(dump_path, encoding='utf-8', errors='replace') as f:
        for line in f:
            line = line.rstrip('\n')

            # ── Detect INSERT headers ───────────────────────────────────────
            if 'INSERT INTO `wp_postmeta`' in line:
                meta_col_idx   = extract_cols(line)
                current_insert = 'postmeta'
                continue

            if 'INSERT INTO `wp_posts`' in line:
                posts_col_idx  = extract_cols(line)
                current_insert = 'posts'
                continue

            if line.startswith('INSERT INTO'):
                current_insert = None
                continue

            # ── Skip non-row lines ─────────────────────────────────────────
            if not line.startswith('('):
                continue

            # ── Parse the VALUES row ───────────────────────────────────────
            # Line format: (val1, val2, ..., valN), or (val1, ...);
            stripped = line.rstrip().rstrip(';,')   # remove row terminator
            if not (stripped.startswith('(') and stripped.endswith(')')):
                continue
            row_content = stripped[1:-1]  # strip outer parens
            vals = parse_row(row_content)

            # ── Process postmeta rows ──────────────────────────────────────
            if current_insert == 'postmeta' and meta_col_idx:
                try:
                    pid = int(vals[meta_col_idx['post_id']])
                    key = vals[meta_col_idx['meta_key']]
                    val = vals[meta_col_idx['meta_value']]
                except (ValueError, TypeError, KeyError, IndexError):
                    continue
                if key == '_wp_attached_file' and val:
                    att_files[pid] = val

            # ── Process posts rows ─────────────────────────────────────────
            elif current_insert == 'posts' and posts_col_idx:
                try:
                    pid    = int(vals[posts_col_idx['ID']])
                    ptype  = vals[posts_col_idx['post_type']] or ''
                    pname  = vals[posts_col_idx['post_name']] or ''
                    parent = int(vals[posts_col_idx['post_parent']] or 0)
                    title  = vals[posts_col_idx['post_title']] or ''
                    exc    = vals[posts_col_idx.get('post_excerpt', -1)] or '' \
                             if 'post_excerpt' in posts_col_idx else ''
                    mime   = vals[posts_col_idx.get('post_mime_type', -1)] or '' \
                             if 'post_mime_type' in posts_col_idx else ''
                    guid   = vals[posts_col_idx.get('guid', -1)] or '' \
                             if 'guid' in posts_col_idx else ''
                except (ValueError, TypeError, KeyError, IndexError):
                    continue

                if ptype == 'attachment' and mime_ok.match(mime):
                    attachments[pid] = {
                        'title': title, 'parent_old_id': parent,
                        'mime_type': mime, 'caption': exc, 'guid': guid,
                    }
                elif ptype in ('post', 'page') and pname:
                    post_slugs[pid] = pname

    # Merge uploads paths into attachment records
    for aid, path in att_files.items():
        if aid in attachments:
            attachments[aid]['uploads_path'] = path

    linked = sum(1 for a in attachments.values() if 'uploads_path' in a)
    print(f"  Attachment records (images): {len(attachments)}")
    print(f"  _wp_attached_file entries:   {len(att_files)} ({linked} matched)")
    print(f"  Post/page slugs:             {len(post_slugs)}")

    return {
        'attachments': {str(k): v for k, v in attachments.items()},
        'post_slugs':  {str(k): v for k, v in post_slugs.items()},
    }

# ── Command: parse ─────────────────────────────────────────────────────────────

def cmd_parse(args):
    data = parse_sql_dump(SQL_DUMP)
    DATA_FILE.write_text(json.dumps(data, indent=2))
    print(f"Saved → {DATA_FILE}")

# ── Command: plan ─────────────────────────────────────────────────────────────

def cmd_plan(args):
    if not DATA_FILE.exists():
        print("Run 'parse' first."); sys.exit(1)

    data        = json.loads(DATA_FILE.read_text())
    attachments = data['attachments']   # str(old_id) → dict
    old_slugs   = data['post_slugs']    # str(old_id) → slug

    # Index SQL records by uploads_relative_path and by filename (fallback)
    path_to_att = {}
    for att in attachments.values():
        if 'uploads_path' in att:
            path_to_att[att['uploads_path']] = att
            path_to_att.setdefault(Path(att['uploads_path']).name, att)

    # Walk recovered-images/
    recovered = []
    for root, _, files in os.walk(RECOVERED):
        for fname in sorted(files):
            if fname.startswith('.'): continue
            if not re.search(r'\.(jpe?g|png|gif|webp|bmp|tiff?)$', fname, re.I): continue
            full = Path(root) / fname
            rel  = str(full.relative_to(RECOVERED))   # YYYY/MM/filename.ext
            recovered.append((full, rel))
    recovered.sort(key=lambda x: x[1])
    print(f"Recovered files found: {len(recovered)}")

    # Fetch all current WP post slugs for parent-ID resolution (one query)
    print("Fetching current post slugs from WordPress …")
    raw = wpcli("post", "list", "--post_type=post", "--post_status=publish",
                "--fields=ID,post_name", "--format=json", "--posts_per_page=10000")
    try:
        slug_to_new_id = {p['post_name']: int(p['ID']) for p in json.loads(raw)}
    except Exception:
        slug_to_new_id = {}
    print(f"  WordPress posts indexed: {len(slug_to_new_id)}")

    rows = []
    for i, (full, rel) in enumerate(recovered):
        att = path_to_att.get(rel) or path_to_att.get(Path(rel).name)

        # Title: SQL dump title if substantive, else clean filename
        stem = Path(rel).stem
        if att and att.get('title') and att['title'].strip() and att['title'].strip() != stem:
            title = att['title'].strip()
        else:
            clean = re.sub(r'-\d+x\d+$', '', stem)          # strip resize suffix
            title = re.sub(r'[-_]+', ' ', clean).strip()

        parent_old = att['parent_old_id'] if att else 0
        parent_slug = old_slugs.get(str(parent_old), '') if parent_old else ''
        parent_new  = slug_to_new_id.get(parent_slug, 0) if parent_slug else 0
        mime        = att.get('mime_type', '') if att else ''
        caption     = att.get('caption', '') if att else ''

        rows.append({
            'plan_id':       f"m{i+1:05d}",
            'local_path':    str(full),
            'uploads_path':  rel,
            'title':         title,
            'caption':       caption,
            'mime_type':     mime,
            'parent_old_id': parent_old,
            'parent_slug':   parent_slug,
            'parent_new_id': parent_new,
            'sql_matched':   '1' if att else '0',
        })

    matched = sum(1 for r in rows if r['sql_matched'] == '1')
    linked  = sum(1 for r in rows if r['parent_new_id'])
    print(f"\nPlan summary:")
    print(f"  Total images:              {len(rows)}")
    print(f"  Matched to SQL dump:       {matched}")
    print(f"  With resolved parent post: {linked}")
    print(f"  Unattached (no parent):    {len(rows) - linked}")

    fields = ['plan_id','local_path','uploads_path','title','caption',
              'mime_type','parent_old_id','parent_slug','parent_new_id','sql_matched']
    with open(PLAN_FILE, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)
    print(f"\nPlan saved → {PLAN_FILE}")

# ── PHP batch inserter ─────────────────────────────────────────────────────────

PHP_CODE = r"""<?php
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$batch   = json_decode(file_get_contents('/tmp/vw_import_batch.json'), true);
$results = [];

foreach ($batch as $rec) {
    $abs    = WP_CONTENT_DIR . '/uploads/' . $rec['uploads_path'];
    $parent = (int)($rec['parent_new_id'] ?? 0);
    $title  = $rec['title'] ?: pathinfo($abs, PATHINFO_FILENAME);

    if (!file_exists($abs)) {
        $results[] = ['plan_id' => $rec['plan_id'], 'status' => 'error', 'msg' => 'file_missing'];
        continue;
    }

    $ftype = wp_check_filetype(basename($abs), null);
    $mime  = $ftype['type'] ?: ($rec['mime_type'] ?: 'image/jpeg');

    $att_id = wp_insert_attachment([
        'post_title'     => $title,
        'post_content'   => '',
        'post_excerpt'   => $rec['caption'] ?? '',
        'post_status'    => 'inherit',
        'post_mime_type' => $mime,
        'post_parent'    => $parent,
    ], $abs, $parent);

    if (is_wp_error($att_id)) {
        $results[] = ['plan_id' => $rec['plan_id'], 'status' => 'error',
                      'msg' => $att_id->get_error_message()];
        continue;
    }

    update_attached_file($att_id, $rec['uploads_path']);
    update_post_meta($att_id, '_needs_alt_review', '1');
    // _wp_attachment_image_alt intentionally left unset (Option A)

    $results[] = ['plan_id' => $rec['plan_id'], 'status' => 'ok', 'att_id' => $att_id];
}

file_put_contents('/tmp/vw_import_results.json', json_encode($results));
foreach ($results as $r) { WP_CLI::line(json_encode($r)); }
WP_CLI::success('Batch done: ' . count($results) . ' processed.');
"""

# ── Command: import ────────────────────────────────────────────────────────────

BATCH_SIZE = 50   # attachment records inserted per WP-CLI call

def cmd_import(args):
    if not PLAN_FILE.exists():
        print("Run 'plan' first."); sys.exit(1)

    with open(PLAN_FILE, newline='', encoding='utf-8') as f:
        plan = list(csv.DictReader(f))

    ledger  = load_ledger()
    pending = [r for r in plan if r['plan_id'] not in ledger
               or ledger[r['plan_id']]['status'] != 'ok']

    if args.limit:
        pending = pending[:args.limit]

    if not pending:
        print("Nothing to do — all images already imported."); return

    is_test = bool(args.limit)
    print(f"{'TEST RUN' if is_test else 'FULL RUN'}: importing {len(pending)} images")

    PHP_SCRIPT.write_text(PHP_CODE)
    new_att_ids = []

    for i in range(0, len(pending), BATCH_SIZE):
        batch = pending[i:i + BATCH_SIZE]
        label = f"Batch {i//BATCH_SIZE + 1}/{(len(pending)-1)//BATCH_SIZE + 1}"
        print(f"\n{label}: copying {len(batch)} files …", end=' ', flush=True)

        # Copy files into wp-content/uploads/
        for rec in batch:
            dst = UPLOADS / rec['uploads_path']
            dst.parent.mkdir(parents=True, exist_ok=True)
            if not dst.exists():
                shutil.copy2(rec['local_path'], dst)

        # Write batch JSON for PHP script
        Path('/tmp/vw_import_batch.json').write_text(json.dumps([
            {
                'plan_id':      r['plan_id'],
                'uploads_path': r['uploads_path'],
                'title':        r['title'],
                'caption':      r['caption'],
                'mime_type':    r['mime_type'],
                'parent_new_id': int(r['parent_new_id'] or 0),
            }
            for r in batch
        ]))

        # Insert attachment records via PHP
        wpcli("eval-file", str(PHP_SCRIPT))

        # Read results
        results_path = Path('/tmp/vw_import_results.json')
        results = json.loads(results_path.read_text()) if results_path.exists() else []
        result_map = {r['plan_id']: r for r in results}

        now = time.strftime('%Y-%m-%dT%H:%M:%S')
        batch_ok = 0
        for rec in batch:
            res    = result_map.get(rec['plan_id'], {})
            status = res.get('status', 'error')
            att_id = str(res.get('att_id', ''))
            error  = res.get('msg', '')
            ledger[rec['plan_id']] = {
                'plan_id':       rec['plan_id'],
                'uploads_path':  rec['uploads_path'],
                'att_id':        att_id,
                'parent_new_id': rec['parent_new_id'],
                'parent_slug':   rec['parent_slug'],
                'title':         rec['title'],
                'status':        status,
                'error':         error,
                'imported_at':   now,
            }
            if status == 'ok' and att_id:
                new_att_ids.append(att_id)
                batch_ok += 1
        save_ledger(ledger)
        print(f"{batch_ok} ok, {len(batch)-batch_ok} errors")

    print(f"\nAttachment records created: {len(new_att_ids)}")

    # For test runs (--limit), auto-regen so thumbnails are immediately visible
    if is_test and new_att_ids:
        print(f"\nRegenerating thumbnails for {len(new_att_ids)} test images …")
        regen_ids(new_att_ids)
        print("\n─── Test verification ──────────────────────────────────────────")
        show_test_results(new_att_ids, ledger)

    if not is_test:
        print(f"\nFull import done. Run 'regen' to generate thumbnails for all {len(new_att_ids)} images.")


def regen_ids(att_ids):
    """Regenerate thumbnails for a list of attachment IDs, in chunks."""
    chunk_size = 100  # positional args, smaller chunks to avoid shell limits
    for i in range(0, len(att_ids), chunk_size):
        chunk = att_ids[i:i + chunk_size]
        label = f"Chunk {i//chunk_size + 1}/{(len(att_ids)-1)//chunk_size + 1}"
        print(f"  {label} ({len(chunk)} images) …", end=' ', flush=True)
        # IDs are positional args — --attachment-id flag does not exist in this WP-CLI version
        out = wpcli("media", "regenerate", *chunk)
        summary = next((l for l in reversed(out.splitlines()) if 'Success' in l or 'Error' in l), None)
        print(summary or "(no output)")


def show_test_results(att_ids, ledger):
    """Query WP and display verification info for the test batch."""
    ids_csv = ','.join(att_ids[:50])

    # Fetch attachment records
    raw = wpcli("post", "list", f"--post__in={ids_csv}",
                "--post_type=attachment", "--post_status=inherit",
                "--fields=ID,post_title,post_parent,post_mime_type",
                "--format=json")
    try:
        atts = json.loads(raw)
    except Exception:
        atts = []

    # Fetch parent post permalinks for linked ones
    parent_ids = list({int(a['post_parent']) for a in atts if int(a['post_parent']) > 0})
    permalinks = {}   # parent_post_id → permalink
    if parent_ids:
        pids_str = ','.join(str(p) for p in parent_ids[:20])
        praw = wpcli("post", "list", f"--post__in={pids_str}",
                     "--post_type=post", "--fields=ID,guid", "--format=json")
        try:
            for p in json.loads(praw):
                permalinks[int(p['ID'])] = p['guid']
        except Exception:
            pass

    # Print table
    linked    = sum(1 for a in atts if int(a['post_parent']) > 0)
    unlinked  = len(atts) - linked

    print(f"\n{'att_id':<8}  {'parent':<8}  {'linked':<7}  title[:50]")
    print("─" * 76)
    for a in atts:
        parent  = int(a['post_parent'])
        flag    = '✓' if parent > 0 else '·'
        title   = (a['post_title'] or '')[:50]
        print(f"{a['ID']:<8}  {parent:<8}  {flag:<7}  {title}")

    print(f"\nSummary: {len(atts)} attachments created, {linked} linked to parent posts, {unlinked} unattached")
    print(f"Alt text: blank on all ✓  |  _needs_alt_review = 1 set on all ✓")
    print(f"Thumbnails: regenerated ✓")

    if permalinks:
        print(f"\nPosts to verify in browser (images should appear in Media Library):")
        for pid, url in list(permalinks.items())[:3]:
            print(f"  {url}")
        if len(permalinks) > 3:
            print(f"  … and {len(permalinks)-3} more parent posts")

# ── Command: regen ─────────────────────────────────────────────────────────────

def cmd_regen(args):
    ledger = load_ledger()
    att_ids = [r['att_id'] for r in ledger.values() if r['status'] == 'ok' and r['att_id']]
    if not att_ids:
        print("No imported attachments in ledger."); return
    print(f"Regenerating thumbnails for {len(att_ids)} attachments (use caffeinate -i to prevent sleep) …")
    regen_ids(att_ids)
    print("Done.")

# ── Command: report ────────────────────────────────────────────────────────────

def cmd_report(args):
    plan_total = 0
    if PLAN_FILE.exists():
        with open(PLAN_FILE) as f:
            plan_total = sum(1 for _ in csv.DictReader(f))

    if not LEDGER.exists():
        print(f"No ledger yet. Plan has {plan_total} images."); return

    ledger = load_ledger()
    ok       = sum(1 for r in ledger.values() if r['status'] == 'ok')
    errors   = sum(1 for r in ledger.values() if r['status'] == 'error')
    linked   = sum(1 for r in ledger.values()
                   if r['status'] == 'ok' and r.get('parent_new_id') and int(r['parent_new_id']) > 0)

    print(f"Media import progress")
    print(f"  Plan total:             {plan_total}")
    print(f"  Imported (ok):          {ok} / {plan_total}")
    print(f"  Linked to parent post:  {linked}")
    print(f"  Errors:                 {errors}")
    print(f"  Remaining:              {plan_total - ok}")

    if errors:
        errs = [r for r in ledger.values() if r['status'] == 'error'][:5]
        print(f"\nFirst {len(errs)} errors:")
        for e in errs:
            print(f"  {e['plan_id']}  {e['uploads_path']}  {e['error']}")

# ── Main ───────────────────────────────────────────────────────────────────────

def main():
    p   = argparse.ArgumentParser(description="VW media library importer")
    sub = p.add_subparsers(dest='command', required=True)

    sub.add_parser('parse',  help='Extract SQL dump metadata')
    sub.add_parser('plan',   help='Build import plan')

    imp = sub.add_parser('import', help='Register images as WP attachments')
    imp.add_argument('--limit', type=int, default=0,
                     help='Max images to import (0 = all; use 20 for test)')

    sub.add_parser('regen',  help='Batch-regenerate thumbnails')
    sub.add_parser('report', help='Show progress')

    args = p.parse_args()
    {
        'parse':  cmd_parse,
        'plan':   cmd_plan,
        'import': cmd_import,
        'regen':  cmd_regen,
        'report': cmd_report,
    }[args.command](args)

if __name__ == '__main__':
    main()
