# Vancouver Weekly – Security Audit Report

Generated: 2026-06-13 09:17

---

## Final result: 2,986 posts cleared. 0 quarantined.

This does NOT mean nothing was filtered — it means the filtering happened in two earlier
stages before this audit ran. Here is the full picture of everything that was excluded
and why, so you can make an informed decision before approving the import.

---

## Stage 1 – Spam filter (`filter_spam.py`)

Ran before this audit. Moved junk to `recovered_posts/excluded/`.

| Category | Count | Examples |
|---|---|---|
| Pharmaceutical spam (slug) | 2,118 | `ivermectina-6-mg-ml-gotas`, `achat-ivermectine`, `stromectol-tablets-for-humans` |
| WordPress query artifacts | 776 | `tag_ids~2845`, `cat_ids~217`, `exact_date~10-4-2014` |
| No-title stubs (non-timeout) | 2 | `index.php`, `theophilus-london` (redirect) |
| **Total excluded in Stage 1** | **2,896** | |
| Timeout failures kept for retry | 1 | `the-twilight-sad` (Wayback unreachable) |

---

## Stage 2 – Security audit (this script)

Ran across the 2,986 posts that survived Stage 1.

### Checks performed

| Check | What it looked for | Result |
|---|---|---|
| `SLUG_PHARMA` | Pharmaceutical / spam keywords in slug | 0 — all pharma slugs removed in Stage 1 |
| `HIDDEN_LINK` | `display:none`, `visibility:hidden`, `opacity:0`, `font-size:0` on `<a>` tags (not on background colours or icon buttons) | 0 genuine hits |
| `BASE64_ATTR` | base64 payload in `href` or `src` attributes | 0 hits |
| `BASE64_BODY` | Long base64 blobs in page body (excluding JWT tokens, gzip embeds, TMDb image IDs, upload path fragments) | 0 genuine hits |
| `SUSPICIOUS_DOMAIN` | Links to known spam / URL-shortener domains | 0 hits |
| `MANY_EXTERNAL` | More than 5 unknown external domains linked in one post | 0 hits |
| `PUBLISH_BURST` | More than 20 posts published in any 60-minute window | 0 bursts detected |
| `SPAM_AUTHOR` | Author name blank + other red flags, or contains spam keywords | 0 hits |

### False positives investigated and ruled out

During tuning, the following patterns triggered flags that were confirmed as legitimate:

| Pattern | Why it looked suspicious | Why it was cleared |
|---|---|---|
| `background-color: #FFFFFF` on share buttons | Matches "white colour" rule | Background colour ≠ hidden text. Only `color:` on `<a>` text is a genuine hide. |
| Zero-text `<a>` to `twitter.com`, `facebook.com` etc. | Empty anchor = hidden link? | Social share icons — the icon image didn't survive Wayback capture. Not spam. |
| Zero-text `<a>` to arts venues (`doxafestival.ca`, `scienceworld.ca`) | Empty anchor to external site | Same: image/icon link, image missing from archive. All point to legitimate VW coverage subjects. |
| `eyJ…` base64 in URLs | Looks like encoded payload | JWT/JSON redirect token used by Eventbrite, Ticketmaster, and Ticketfly for ticket links. Normal. |
| `H4sI…` and continuation blobs in `&stick=` Google Maps params | Long base64 blob in body | gzip-compressed Google Maps embed data. Not executable. |
| `MV5B…` in WordPress upload paths | Long base64-looking string in content | TMDb/IMDB movie poster image ID. Just an unusual filename, not an encoded payload. |

---

## What was NOT checked (and why)

| Thing not checked | Reason |
|---|---|
| Author account creation dates | Not available in the recovered JSON — would need live DB access |
| Post revision history | Not captured by Wayback |
| Comment spam | Comments not recovered — only post content |
| Images for malicious metadata | Out of scope for pre-import; images can be scanned separately |

---

## Conclusion

The 2,986 posts in `recovered_posts/*.json` (listed in `audit_clean.txt`) contain:
- Named VW contributors as authors (reviews, interviews, photography recaps, food coverage)
- Legitimate editorial categories (A La Music, Photography, Food & Drink, Out 'N' About)
- No hidden links, no obfuscated payloads, no suspicious outbound domains
- No publish bursts indicating mass spam injection

The 2,896 excluded posts (in `recovered_posts/excluded/`) are overwhelmingly pharma SEO
spam injected into the site at some point between 2021–2023, all in the `ivermectin*`
family across multiple languages.

**To proceed with the WordPress import, confirm approval and the import script will run
only against `audit_clean.txt`.**
