#!/usr/bin/env python3
"""
Vancouver Weekly – pre-import security audit.

Runs four independent checks across all recovered posts:

  1. SLUG PHARMA     — slug contains pharmaceutical / spam keywords
  2. CONTENT LINKS   — content contains hidden links, suspicious outbound URLs,
                       obfuscated hrefs, or non-VW external domains embedded
  3. BASE64          — content contains base64-encoded payloads (common in
                       injected malware / SEO spam)
  4. PUBLISH BURST   — post published during a mass-publishing burst
                       (>20 posts in any 60-minute window = spam signal)
  5. SPAM AUTHOR     — author name matches known spam patterns or is blank
                       on a post with suspicious other signals

Outputs:
  quarantine.csv       — every flagged post with reason(s), for Ricardo to review
  audit_clean.txt      — slugs that passed all checks (the import-ready list)
  audit_report.md      — human-readable summary
"""

import base64
import csv
import json
import re
import sys
from collections import defaultdict
from datetime import datetime, timedelta
from pathlib import Path
from urllib.parse import urlparse
from bs4 import BeautifulSoup

# ── Paths ─────────────────────────────────────────────────────────────────────

RECOVERY_DIR = Path(__file__).parent
POSTS_DIR = RECOVERY_DIR / "recovered_posts"
QUARANTINE_CSV = RECOVERY_DIR / "quarantine.csv"
CLEAN_LIST = RECOVERY_DIR / "audit_clean.txt"
REPORT_FILE = RECOVERY_DIR / "audit_report.md"
QUARANTINE_DIR = POSTS_DIR / "quarantine"

# ── Check 1: Slug pharmaceutical / spam keywords ──────────────────────────────

PHARMA_SLUG_RE = re.compile(
    r'ivermect|stromectol|mectizan|soolantra|sklice|heartgard'
    r'|viagra|cialis|levitra|sildenafil|tadalafil'
    r'|pharma(?!cy.*vancouver)|pharmacy(?!.*vancouver)'  # standalone pharma/pharmacy is spam
    r'|pubmed|profilaxis|prophylaxis'
    r'|ketamine|adderall|xanax|tramadol|oxycontin|oxycodone|hydrocodone'
    r'|ambien|valium|ritalin|modafinil'
    r'|payday.?loan|essay.?writ|dissertation'
    r'|casino|gambling|poker(?!.*face)'
    r'|bitcoin|crypto(?!graph)|forex|nft\b'
    r'|meds\b|pills\b|rx\b|prescription\b',
    re.I,
)

def check_slug(slug):
    if PHARMA_SLUG_RE.search(slug):
        m = PHARMA_SLUG_RE.search(slug)
        return f"SLUG_PHARMA: slug contains '{m.group()}'"
    return None

# ── Check 2: Suspicious content links ─────────────────────────────────────────

SUSPICIOUS_DOMAINS = re.compile(
    r'(?:^|\.)('
    r'bit\.ly|tinyurl\.com|ow\.ly|goo\.gl|t\.co'
    r'|buy-?ivermectin|ivermectin-?buy|order-?ivermectin'
    r'|canadian-?pharmacy|online-?pharmacy|rx-?online'
    r'|genericpills|cheap-?meds|discount-?drugs'
    r'|paydayloan|casinoslot|freespins'
    r')',
    re.I,
)

OUTBOUND_HREF_RE = re.compile(r'href=["\']https?://([^/"\']+)', re.I)

ALLOWED_DOMAINS = {
    'vancouverweekly.com', 'www.vancouverweekly.com',
    'youtube.com', 'www.youtube.com', 'youtu.be',
    'soundcloud.com', 'open.spotify.com',
    'vimeo.com', 'player.vimeo.com',
    'twitter.com', 'x.com', 'facebook.com', 'instagram.com',
    'web.archive.org',
    'wordpress.org', 'gravatar.com',
    'google.com', 'maps.google.com',
    'bandcamp.com',
    'ticketmaster.ca', 'ticketmaster.com', 'eventbrite.com', 'eventbrite.ca',
    'livenation.com',
}

def domain_root(host):
    parts = host.lower().split('.')
    return '.'.join(parts[-2:]) if len(parts) >= 2 else host

# CSS properties that genuinely hide an element from view
HIDING_CSS_RE = re.compile(
    r'display\s*:\s*none'
    r'|visibility\s*:\s*hidden'
    r'|opacity\s*:\s*0\b'
    r'|font-size\s*:\s*0'
    r'|font-size\s*:\s*1px'
    r'|width\s*:\s*0\s*[;!]'
    r'|height\s*:\s*0\s*[;!]'
    r'|color\s*:\s*(?:#fff{1,3}|white)\s*[;!]',  # white text (invisible on white bg)
    re.I,
)

def check_content(slug, content):
    reasons = []
    if not content:
        return reasons

    # Parse HTML so we only inspect actual <a> tags, not widget backgrounds
    try:
        soup = BeautifulSoup(content, 'lxml')
    except Exception:
        soup = None

    # Domains whose zero-text links are legitimate (social icons, share buttons)
    SOCIAL_DOMAINS = {
        'twitter.com', 'x.com', 'facebook.com', 'instagram.com',
        'linkedin.com', 'pinterest.com', 'youtube.com', 'youtu.be',
        'plus.google.com', 'reddit.com', 'tumblr.com',
        'web.archive.org',  # Wayback-wrapped versions of the above
    }

    # CSS properties that are genuinely suspicious ONLY when applied to <a> tags —
    # i.e. properties that make the link text/element invisible to a human reader
    # but still crawlable. We do NOT flag white backgrounds (common on buttons).
    HIDING_ON_LINK_RE = re.compile(
        r'display\s*:\s*none'
        r'|visibility\s*:\s*hidden'
        r'|opacity\s*:\s*0\b'
        r'|font-size\s*:\s*(?:0|0px|1px)\b'
        r'|color\s*:\s*(?:transparent|rgba\(0,0,0,0\))'
        r'|position\s*:\s*absolute.*?(?:left|top)\s*:\s*-\d{3,}',  # off-screen positioning
        re.I,
    )

    if soup:
        for a in soup.find_all('a', href=True):
            style = a.get('style', '')
            if HIDING_ON_LINK_RE.search(style):
                # One last sanity check: ignore if it's clearly a share/icon button
                # (line-height:0 + padding:0 is a common icon-button reset, not spam)
                if re.search(r'line-height\s*:\s*0', style, re.I) and not re.search(
                    r'display\s*:\s*none|visibility\s*:\s*hidden', style, re.I
                ):
                    continue
                reasons.append(
                    f"HIDDEN_LINK: <a> tag has hiding CSS style='{style[:100]}'"
                )
                break
    else:
        for m in re.finditer(r'<a\b[^>]*style=["\']([^"\']*)["\'][^>]*>', content, re.I):
            if HIDING_ON_LINK_RE.search(m.group(1)):
                reasons.append(f"HIDDEN_LINK: <a> tag has hiding CSS (regex fallback)")
                break
    # Note: zero-text <a> tags are NOT flagged — they're nearly always icon/image
    # links where the image didn't survive the Wayback capture, not hidden spam.

    # base64 in href/src attributes
    b64_attr = re.compile(
        r'(?:href|src)\s*=\s*["\'](?:data:[^;]+;base64,)?'
        r'([A-Za-z0-9+/]{60,}={0,2})["\']',
        re.I,
    )
    if b64_attr.search(content):
        reasons.append("BASE64_ATTR: base64-encoded value found in href/src attribute")

    # Suspicious domains
    for m in OUTBOUND_HREF_RE.finditer(content):
        host = m.group(1).split('/')[0].lower()
        if SUSPICIOUS_DOMAINS.search(host):
            reasons.append(f"SUSPICIOUS_DOMAIN: link to '{host}'")
            break

    # Many unknown external domains
    external = set()
    for m in OUTBOUND_HREF_RE.finditer(content):
        host = m.group(1).split('/')[0].lower()
        root = domain_root(host)
        if root not in ALLOWED_DOMAINS and not root.endswith('vancouverweekly.com'):
            external.add(root)
    if len(external) > 5:
        sample = ', '.join(sorted(external)[:5])
        reasons.append(
            f"MANY_EXTERNAL: {len(external)} unknown external domains "
            f"(sample: {sample})"
        )

    return reasons

# ── Check 3: Standalone base64 blobs in content body ─────────────────────────

# Long base64 strings in page body that are NOT part of a URL path.
# eyJ = base64 of '{"' — this is a JWT/JSON token used by ticket platforms
# (Eventbrite, Ticketmaster, Ticketfly, etc.) in redirect URLs. Not spam.
B64_BODY_RE = re.compile(
    r'(?<!["\'/=A-Za-z0-9])([A-Za-z0-9+/]{100,}={0,2})(?!["\':A-Za-z0-9/])',
)
JWT_OR_TICKET_RE = re.compile(
    r'^eyJ'             # JWT/JSON token (Eventbrite, ticket platforms)
    r'|^redirect/'      # ticket-platform redirect path
    r'|^H4sI'           # gzip-base64 (Google Maps / API embeds)
    r'|^MV5B'           # TMDb/IMDB image ID (movie poster filenames)
    r'|^content/uploads/',  # WordPress upload path fragment
    re.I,
)

def check_base64(slug, content):
    if not content:
        return None
    for m in B64_BODY_RE.finditer(content):
        blob = m.group(1)
        # Skip JWT tokens and ticket-platform redirect paths (false positives)
        if JWT_OR_TICKET_RE.search(blob):
            continue
        # Skip if this blob appears inside a URL or is a continuation of a
        # known-safe blob (e.g. gzip H4sI value split across a URL-encoded hyphen)
        start = m.start()
        preceding_short = content[max(0, start-5):start]
        preceding_wide  = content[max(0, start-120):start]
        if re.search(r'[A-Za-z0-9/]$', preceding_short):
            continue
        # Continuation of a gzip blob (H4sI) split by a hyphen/encoded char
        if re.search(r'H4sI', preceding_wide, re.I):
            continue
        # Try to decode — only flag if it yields readable binary payload
        try:
            decoded = base64.b64decode(blob + '==')
            if len(decoded) > 50:
                snippet = blob[:40]
                return (f"BASE64_BODY: non-URL base64 blob in content body "
                        f"('{snippet}…', {len(decoded)} decoded bytes)")
        except Exception:
            pass
    return None

# ── Check 4: Publish burst detection ─────────────────────────────────────────

def parse_date(date_str):
    if not date_str:
        return None
    for fmt in ('%Y-%m-%dT%H:%M:%S+00:00', '%Y-%m-%dT%H:%M:%SZ',
                '%Y-%m-%d %H:%M:%S', '%Y-%m-%d'):
        try:
            return datetime.strptime(date_str[:19], fmt[:len(date_str[:19])])
        except Exception:
            continue
    return None

def find_burst_slugs(posts, window_minutes=60, threshold=20):
    """
    Return dict of slug -> burst_reason for posts published in a
    burst of >threshold posts within window_minutes.
    """
    dated = []
    for p in posts:
        dt = parse_date(p.get('date', ''))
        if dt:
            dated.append((dt, p['slug']))
    dated.sort()

    flagged = set()
    n = len(dated)
    for i, (dt_i, slug_i) in enumerate(dated):
        window_end = dt_i + timedelta(minutes=window_minutes)
        burst = [slug for dt_j, slug in dated[i:] if dt_j <= window_end]
        if len(burst) >= threshold:
            for slug in burst:
                flagged.add(slug)

    result = {}
    for slug in flagged:
        result[slug] = (f"PUBLISH_BURST: published in a window with "
                        f">={threshold} posts in {window_minutes} min")
    return result

# ── Check 5: Spam author heuristics ──────────────────────────────────────────

SPAM_AUTHOR_RE = re.compile(
    r'^\s*$'                          # blank
    r'|^(admin|administrator|root|test|user\d*)$'
    r'|buy|cheap|order|price|discount|pharmacy|pills|meds|casino|loan',
    re.I,
)

def check_author(author, other_reasons):
    if SPAM_AUTHOR_RE.search(author or ''):
        label = f"SPAM_AUTHOR: author '{author}' matches spam/generic pattern"
        # Only flag standalone if author is blank AND there are other signals,
        # or if author literally contains a spam keyword
        if re.search(r'buy|cheap|order|price|discount|pharmacy|pills|meds|casino|loan',
                     author or '', re.I):
            return label
        if not (author or '').strip() and other_reasons:
            return label
    return None

# ── Load posts ────────────────────────────────────────────────────────────────

def load_posts():
    posts = []
    for f in sorted(POSTS_DIR.glob('*.json')):
        try:
            raw = json.loads(f.read_text())
            data = raw.get('data') or {}
            posts.append({
                'slug': raw.get('slug', f.stem),
                'file': f,
                'title': data.get('title', ''),
                'content': data.get('content', ''),
                'author': data.get('author', ''),
                'date': data.get('date', ''),
                'categories': data.get('categories', []),
                'wayback_url': raw.get('wayback_url') or data.get('wayback_url', ''),
            })
        except Exception as e:
            posts.append({
                'slug': f.stem, 'file': f,
                'title': '', 'content': '', 'author': '',
                'date': '', 'categories': [], 'wayback_url': '',
                '_load_error': str(e),
            })
    return posts

# ── Main ─────────────────────────────────────────────────────────────────────

def main():
    print("Loading posts…")
    posts = load_posts()
    print(f"  {len(posts)} posts loaded from {POSTS_DIR}")

    # Run burst detection across all posts first (needs full date list)
    print("Running burst detection…")
    burst_flags = find_burst_slugs(posts)
    print(f"  {len(burst_flags)} posts in publish bursts")

    print("Running per-post checks…")
    flagged = []   # list of dicts
    clean = []     # slugs

    for p in posts:
        reasons = []
        slug = p['slug']

        # Load error
        if p.get('_load_error'):
            reasons.append(f"LOAD_ERROR: {p['_load_error']}")

        # Check 1: slug
        r = check_slug(slug)
        if r:
            reasons.append(r)

        # Check 2: content links
        reasons.extend(check_content(slug, p['content']))

        # Check 3: base64
        r = check_base64(slug, p['content'])
        if r:
            reasons.append(r)

        # Check 4: burst
        if slug in burst_flags:
            reasons.append(burst_flags[slug])

        # Check 5: author
        r = check_author(p['author'], reasons)
        if r:
            reasons.append(r)

        if reasons:
            flagged.append({
                'slug': slug,
                'title': p['title'],
                'author': p['author'],
                'date': p['date'],
                'wayback_url': p['wayback_url'],
                'reasons': ' | '.join(reasons),
                'reason_codes': ','.join(
                    r.split(':')[0] for r in reasons
                ),
            })
        else:
            clean.append(slug)

    # ── Outputs ───────────────────────────────────────────────────────────

    # quarantine.csv
    with open(QUARANTINE_CSV, 'w', newline='', encoding='utf-8') as f:
        w = csv.DictWriter(f, fieldnames=[
            'slug', 'title', 'author', 'date', 'reason_codes', 'reasons', 'wayback_url'
        ])
        w.writeheader()
        for row in flagged:
            w.writerow(row)
    print(f"  quarantine.csv written ({len(flagged)} rows)")

    # audit_clean.txt
    CLEAN_LIST.write_text('\n'.join(clean) + '\n')
    print(f"  audit_clean.txt written ({len(clean)} slugs)")

    # Count by reason code
    code_counts = defaultdict(int)
    for row in flagged:
        for code in row['reason_codes'].split(','):
            code_counts[code.strip()] += 1

    # audit_report.md
    lines = [
        "# Vancouver Weekly – Security Audit Report",
        f"\nGenerated: {datetime.now().strftime('%Y-%m-%d %H:%M')}",
        "",
        "## Summary",
        f"- Posts audited: {len(posts):,}",
        f"- **Posts quarantined: {len(flagged):,}**",
        f"- **Posts cleared for import: {len(clean):,}**",
        "",
        "## Quarantine breakdown by flag type",
        "",
    ]
    for code, count in sorted(code_counts.items(), key=lambda x: -x[1]):
        lines.append(f"| `{code}` | {count:,} |")

    lines += [
        "",
        "## What each flag means",
        "",
        "| Code | Meaning |",
        "|---|---|",
        "| `SLUG_PHARMA` | Slug contains pharmaceutical/spam keyword |",
        "| `HIDDEN_LINK` | Content uses CSS to hide links (display:none, opacity:0, etc.) |",
        "| `BASE64_ATTR` | base64-encoded value in an href or src attribute |",
        "| `BASE64_BODY` | Standalone base64 blob in page body |",
        "| `SUSPICIOUS_DOMAIN` | Link to a known spam/shortener domain |",
        "| `MANY_EXTERNAL` | Unusual number of outbound links to unknown domains |",
        "| `PUBLISH_BURST` | Published during a mass-publish burst (>20 posts/hour) |",
        "| `SPAM_AUTHOR` | Author name is blank or contains spam keywords |",
        "| `LOAD_ERROR` | JSON file could not be read |",
        "",
        "## Next steps",
        "",
        "1. Open `quarantine.csv` and review the flagged posts.",
        "2. Move any false positives back to `recovered_posts/` manually.",
        "3. When satisfied, confirm to proceed with the WordPress import.",
        "4. Only posts listed in `audit_clean.txt` will be imported.",
        "",
        "## Quarantine sample (first 50 rows)",
        "",
        "| Slug | Author | Date | Flags |",
        "|---|---|---|---|",
    ]
    for row in flagged[:50]:
        slug = row['slug'][:50]
        author = (row['author'] or '—')[:25]
        date = (row['date'] or '—')[:10]
        codes = row['reason_codes']
        lines.append(f"| `{slug}` | {author} | {date} | {codes} |")
    if len(flagged) > 50:
        lines.append(f"| … | … | … | ({len(flagged) - 50} more in quarantine.csv) |")

    REPORT_FILE.write_text('\n'.join(lines))
    print(f"  audit_report.md written")

    # ── Console summary ───────────────────────────────────────────────────
    print(f"\n{'='*60}")
    print(f"Posts audited:           {len(posts):,}")
    print(f"Quarantined:             {len(flagged):,}")
    print(f"Cleared for import:      {len(clean):,}")
    print()
    print("Flag breakdown:")
    for code, count in sorted(code_counts.items(), key=lambda x: -x[1]):
        print(f"  {code:<22} {count:,}")
    print()
    print(f"quarantine.csv  → {QUARANTINE_CSV}")
    print(f"audit_clean.txt → {CLEAN_LIST}")
    print(f"audit_report.md → {REPORT_FILE}")
    print()
    print("Review quarantine.csv, then confirm to proceed with import.")

if __name__ == "__main__":
    main()
