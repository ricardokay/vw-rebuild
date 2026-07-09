<?php
/**
 * VW Facebook-album gallery import tool (v5 — CSV/zip driven, batch-gated, multi-album + PER-IMAGE credit).
 * ---------------------------------------------------------------------------
 * WHAT IT DOES
 *   Rebuilds dead Facebook galleries on Wayback-recovered posts. Targets are read
 *   from fb-album-inventory.csv (bucket == REPAIR); each album is mapped to its
 *   matched_post_id and its zip media folder (from the FB export album JSONs).
 *   Images are read straight from the zip (no disk staging). For each album it
 *   creates a REVERSIBLE DRAFT post (never modifies the live published post):
 *   a new draft (post_status=draft, meta `_vw_import_draft_of` = live id) holds
 *   the proposed content for review. Reversal: tools/vw_gallery_import_reverse.php.
 *
 * SAFETY: it will ONLY process post IDs listed in the batch file (VW_BATCH env or
 *   /tmp/vw_batch15.json, a JSON array of matched_post_ids). With no batch file it
 *   refuses to run — so it can never import all REPAIR rows at once by accident.
 *
 * TWO DEAD-MARKUP PATHS (auto-detected from the live post_content)
 *   A) strip-and-replace : real dead gallery markup (jig2 / fbcdn) -> remove, insert gallery.
 *   B) build-into-stub   : only a Facebook "OAuthException" error string -> strip it, build gallery.
 *
 * FIXES BAKED IN (unchanged from v2)
 *   - Fix A: conservative body cleaner (named artifacts only; jig2/fbcdn/OAuth error +
 *     leftover authorpage / author-information / empty jigErrorMessage / empty-nbsp <p>).
 *   - Fix B: credit = WP author-account display_name (via author-box slug) preferred over the
 *     FB album description (typos like "Jashua"->"Joshua"); @handles/boilerplate/dates stripped.
 *   - Fix C: utf8mb4 end to end + mojibake normalization on title + captions.
 *   - Native lightbox attribute emitted per wp:image.
 *   - Per import: images->attachments (caption "Photo by <name>", blank alt, _needs_alt_review=1);
 *     featured image = first attachment; original post_date / slug / author preserved.
 *
 * USAGE (drafts only, review before publishing):
 *   1. echo '[67771,65497,...]' > /tmp/vw_batch15.json   (matched_post_ids to import)
 *   2. php -d mysqli.default_socket=<sock> -d pdo_mysql.default_socket=<sock> \
 *          -d memory_limit=768M tools/vw_gallery_import.php
 *   Reversal data -> /tmp/vw_import_v2_created.json (consumed by the reverse tool).
 *
 * STILL TO WATCH: festival multi-day post mapping (a day/part album can point at the wrong
 *   day's post — verify), non-concert caption conventions, very large albums (100+ imgs) perf.
 */
define('WP_USE_THEMES', false);
require '/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
global $wpdb; $wpdb->query("SET NAMES utf8mb4");

$CSV        = '/Users/ricardokhayatte/Claude-code/vw-rebuild/fb-album-inventory.csv';
$ZIP        = "/Users/ricardokhayatte/Documents/Vancouver Weekly Facebook backup zip/facebook-VancouverWeekly-2026-06-18-54FRaXvE.zip";
$MEDIA      = '/posts/media/';
$BATCH_FILE = getenv('VW_BATCH') ?: '/tmp/vw_batch15.json';
$OUT        = getenv('VW_OUT') ?: '/tmp/vw_import_v2_created.json';

/* ---- SAFETY GATE: explicit batch of target post IDs required ---- */
if ( ! is_file($BATCH_FILE) ) { fwrite(STDERR, "[vw] batch file $BATCH_FILE missing — refusing to run.\n"); exit(1); }
$targets = json_decode(file_get_contents($BATCH_FILE), true);
if ( ! is_array($targets) || ! $targets ) { fwrite(STDERR, "[vw] empty/invalid batch — nothing to do.\n"); exit(1); }
$want = array_flip( array_map('strval', $targets) );

/* ---- REPAIR rows from CSV (batch only): matched_post_id -> [ [album, photo_count], ... ]
 *      A post can match SEVERAL sub-albums (co-bill night / festival day) -> gather them all. ---- */
$albums_of = [];
$fh = fopen($CSV, 'r'); if ( ! $fh ) { fwrite(STDERR, "[vw] cannot open CSV\n"); exit(1); }
$H = array_flip( fgetcsv($fh) );
while ( ($row = fgetcsv($fh)) !== false ) {
  if ( ($row[$H['bucket']] ?? '') !== 'REPAIR' ) continue;
  $pid = (string) $row[$H['matched_post_id']];
  if ( isset($want[$pid]) ) $albums_of[$pid][] = [ $row[$H['album']], (int) $row[$H['photo_count']] ];
}
fclose($fh);

/* ---- zip: album name -> [folder,desc]; folder -> [image entry names] ---- */
$za = new ZipArchive;
if ( $za->open($ZIP) !== true ) { fwrite(STDERR, "[vw] cannot open zip\n"); exit(1); }
$meta = []; $folder_entries = [];
for ( $i = 0; $i < $za->numFiles; $i++ ) {
  $e = $za->getNameIndex($i);
  if ( strpos($e, $MEDIA) === false ) continue;
  if ( preg_match('/\.(jpe?g|png|gif)$/i', $e) ) {
    $rest = substr($e, strpos($e, $MEDIA) + strlen($MEDIA));
    if ( strpos($rest, '/') !== false ) $folder_entries[ substr($rest, 0, strpos($rest, '/')) ][] = $e;
  }
}
for ( $i = 0; $i < $za->numFiles; $i++ ) {
  $e = $za->getNameIndex($i);
  if ( strpos($e, '/posts/album/') === false || substr($e, -5) !== '.json' ) continue;
  $d = json_decode($za->getFromIndex($i), true); if ( ! $d ) continue;
  $ph = $d['photos'] ?? []; $fld = ''; $descByBase = [];
  if ( $ph ) {
    $u = $ph[0]['uri'] ?? ''; if ( strpos($u, '/media/') !== false ) $fld = explode('/', explode('/media/', $u)[1])[0];
    // v5: keep each photo's OWN FB description, keyed by image basename, for per-image credit.
    foreach ( $ph as $p ) { $pu = $p['uri'] ?? ''; if ( $pu === '' ) continue; $descByBase[ basename($pu) ] = trim($p['description'] ?? ''); }
  }
  // same-name albums (e.g. two "VFMF - Day 2") -> keep ALL candidate folders, disambiguate later by count
  $meta[ trim($d['name'] ?? '') ][] = ['folder' => $fld, 'desc' => trim($d['description'] ?? ''), 'descByBase' => $descByBase];
}

/* ---- assemble per post: pid -> [ {folder, desc, entries}, ... ] (one OR MORE source albums) ---- */
$POSTS = [];
foreach ( array_keys($want) as $pid ) {
  if ( empty($albums_of[$pid]) ) { echo "[vw] skip $pid: not a REPAIR row.\n"; continue; }
  $sources = [];
  foreach ( $albums_of[$pid] as [$album_name, $pc] ) {
    $cands = $meta[$album_name] ?? [];
    if ( ! $cands ) { echo "[vw] skip $pid: no zip folder for \"$album_name\".\n"; $sources = []; break; }
    // same-name disambiguation: prefer the folder whose on-disk image count == the CSV photo_count
    $pick = null;
    foreach ( $cands as $c ) { if ( count($folder_entries[$c['folder']] ?? []) === $pc ) { $pick = $c; break; } }
    if ( ! $pick ) $pick = $cands[0];
    $entries = $folder_entries[ $pick['folder'] ] ?? []; sort($entries, SORT_STRING);
    if ( ! $entries ) { echo "[vw] skip $pid: no images for folder {$pick['folder']}.\n"; $sources = []; break; }
    $sources[] = ['folder' => $pick['folder'], 'desc' => $pick['desc'], 'entries' => $entries, 'descByBase' => $pick['descByBase'] ?? []];
  }
  if ( $sources ) $POSTS[$pid] = $sources;
}

/* ---- Fix C: mojibake normalization ---- */
function vw_normalize($s, &$changed) {
  $o = $s;
  $map = ["\xE2\x80\x99"=>"'","\xE2\x80\x98"=>"'","\xE2\x80\x9C"=>'"',"\xE2\x80\x9D"=>'"',
          "\xE2\x80\x93"=>"-","\xE2\x80\x94"=>"—",
          "\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2"=>"'", "\xC3\xA2\xE2\x82\xAC\xC5\x93"=>'"',
          "\xC3\xA2\xE2\x82\xAC"=>'"', "\xC2\xA0"=>" "];
  $s = strtr($s, $map);
  $s = str_replace("\xEF\xBF\xBD", "", $s);
  $s = preg_replace('/\s+/u',' ', $s);
  $s = trim($s);
  $changed = ($s !== trim(preg_replace('/\s+/u',' ',$o)));
  return $s;
}

/* ---- Fix A: conservative body cleaner, returns [clean, removed[]] ---- */
function vw_clean_body($c, &$removed) {
  $removed = [];
  $patterns = [
    ['jig2 shortcode',       '/\[jig[^\]]*\].*?\[\/jig[^\]]*\]/is'],
    ['gallery shortcode',    '/\[gallery[^\]]*\]/i'],
    ['jig2 div',             '/<div[^>]*id=["\']jig2["\'][^>]*>.*?<\/div>\s*<\/div>/is'],
    ['fbcdn <a><img>',       '/<a[^>]*>\s*<img[^>]*(?:fbcdn|scontent|akamaihd)[^>]*>\s*<\/a>/is'],
    ['fbcdn <img>',          '/<img[^>]*(?:fbcdn|scontent|akamaihd)[^>]*>/is'],
    ['OAuth error text',     '/[^<>\n]*Error:\s*OAuthException[^<\n]*/i'],
    ['FB review boilerplate','/[^<>\n]*To use .?Page Public[^<\n]*/i'],
    ['authorpage section',   '/<section[^>]*class=["\']authorpage["\'][^>]*>.*?<\/section>/is'],
    ['author-info wrapper',  '/<div class="category_container author-information">\s*(?:<div class="inner">\s*)?(?:<\/div>\s*)*<\/div>/is'],
    ['empty jigErr span',    '/<span[^>]*class=["\']jigErrorMessage["\'][^>]*>\s*<\/span>/is'],
    ['empty\/nbsp <p>',      '/<p>(?:\s|&nbsp;|\xC2\xA0|\xEF\xBF\xBD|<br\s*\/?>)*<\/p>/is'],
  ];
  foreach ($patterns as [$label,$re]) {
    $c = preg_replace_callback($re, function($m) use (&$removed,$label){
      $snip = trim(preg_replace('/\s+/',' ', strip_tags($m[0]) ?: $m[0]));
      $removed[] = [$label, mb_substr($snip,0,60)];
      return '';
    }, $c);
  }
  return trim($c);
}

/* ---- Fix B: PER-ALBUM photographer credit ----
 *   Resolves one photographer per SOURCE ALBUM from that album's own FB description,
 *   canonicalized to a WP author account when one exists (fixes source typos), else the
 *   cleaned FB name (for photographers with NO WP account, e.g. "Tanis Lischewski").
 *   Single-photographer posts are unchanged: every source album resolves to the same credit. */
function vw_clean_fb_name($fb_desc) {
  // SAFETY: never invent attribution from an event title — require an explicit credit marker.
  if ( ! preg_match('/\b(by|credit)\b/i', $fb_desc) ) return '';
  $fb_desc = preg_replace('/\s+/', ' ', $fb_desc); // collapse newlines/tabs so separators match reliably
  $d = preg_replace('/^\s*(all\s+)?(photos?|pics?|photography)\s+by\s+/i', '', $fb_desc);
  $d = preg_split('/\s+-\s+/', $d)[0]; // drop trailing " - venue/promoter boilerplate" (whitespace-dash-whitespace, incl newline; keeps "Hartley-Marjoram")
  $d = preg_split('/\s*[\(\/]|\.\s|,|\s+@|\s+www|\s+VANCOUVER|\s+On\s+|\s+\d/i', $d)[0];
  // strip trailing dates in two safe forms:
  //   (a) Month + day-number ("Sept.16", "Jul 4", "August 12") — the day digit gates it, so surnames
  //       starting with a month (Margetson / Marc / Augustus) are NOT clipped;
  //   (b) a bare, word-bounded trailing month token ("Sept", "September").
  $d = preg_replace('/\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s*\d{1,2}\b.*$/i', '', $d);
  $d = preg_replace('/\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sept|sep|oct|nov|dec)\b\.?\s*$/i', '', $d);
  $d = preg_replace('/@\S+/', '', $d);
  return trim($d, " .-");
}
function vw_surname($name) { $p = preg_split('/\s+/', trim($name)); return strtolower(end($p) ?: ''); }

function vw_album_credit($live_content, $album_desc, &$source) {
  $name = vw_clean_fb_name($album_desc);
  if ($name === '') { $source = '(no credit in FB desc)'; return ''; }
  // 1) WP account by slug derived from the cleaned FB name (canonical spelling)
  $u = get_user_by('slug', sanitize_title($name));
  if ($u && trim($u->display_name) !== '') { $source = "WP account ({$u->user_nicename})"; return $u->display_name; }
  // 2) typo-correction via the POST's author-box account (e.g. FB "Jashua" but author-box "joshua-...-grafstein")
  if (preg_match('/author\/([a-z0-9\-]+)/i', $live_content, $m)) {
    $au = get_user_by('slug', $m[1]);
    if ($au && vw_surname($au->display_name) !== '' && vw_surname($au->display_name) === vw_surname($name)) {
      $source = "WP author-box (typo-corrected: {$m[1]})"; return $au->display_name;
    }
  }
  // 3) no WP account -> the cleaned FB name as-is
  $source = "FB desc (no WP account)"; return $name;
}

/* ---- v5: PER-PHOTO credit. Each FB photo carries its OWN description (the true per-image
 *   credit). Album-level credit is used ONLY as a fallback when a photo has no own credit.
 *   Fixes co-shot albums that were being stamped with one name (or both names) on every image. */

/* extract the raw photographer/studio string from a single photo's FB description.
 *   Handles: FB user tag "@[id:id:Name]", "© YEAR Studio", "[Band -] Photo(s) by X", "credit: X".
 *   Returns '' when the description carries no attribution. */
function vw_extract_photo_credit($desc) {
  $desc = trim( preg_replace('/\s+/u', ' ', (string) $desc) );
  if ($desc === '') return '';
  if ( preg_match('/@\[\d+:\d+:([^\]]+)\]/', $desc, $m) ) return trim($m[1]);              // FB user tag
  if ( preg_match('/(?:©|\(c\))\s*\d{4}\s+(.+)$/iu', $desc, $m) ) return trim($m[1], " .-"); // © YEAR Studio (mojibake "Â©" ok: matches the © byte)
  if ( preg_match('/\b(?:photos?|pics?|photography)\s+by\s+(.+)$/i', $desc, $m) ) return trim($m[1]); // "[Band -] Photo by X"
  if ( preg_match('/\bcredit:?\s+(.+)$/i', $desc, $m) ) return trim($m[1]);
  return '';
}

/* trailing-junk cleanup shared by the per-photo path (dates / venue-promoter / @handles),
 *   WITHOUT the leading "by" strip (vw_extract_photo_credit already dropped the marker). */
function vw_tidy_name($d) {
  $d = preg_replace('/\s+/', ' ', trim($d));
  $d = preg_split('/\s+-\s+/', $d)[0];
  $d = preg_split('/\s*[\(\/]|\.\s|,|\s+@|\s+www|\s+VANCOUVER|\s+On\s+|\s+\d/i', $d)[0];
  $d = preg_replace('/\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s*\d{1,2}\b.*$/i', '', $d);
  $d = preg_replace('/\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sept|sep|oct|nov|dec)\b\.?\s*$/i', '', $d);
  $d = preg_replace('/@\S+/', '', $d);
  return trim($d, " .-");
}

/* canonicalize a raw per-photo credit -> final caption name.
 *   studio/handle alias (no slug relation to an account) -> WP account by slug (canonical spelling)
 *   -> author-box typo-correction (surname match only) -> cleaned name as-is. */
function vw_canonical_credit($raw, $live_content, &$source) {
  $raw = trim($raw, " .-");
  if ($raw === '') { $source = '(none)'; return ''; }
  static $ALIAS = [ 'creative copper images' => 'Jennifer McInnis / Creative Copper Images' ];
  $key = strtolower( trim($raw) );
  if ( isset($ALIAS[$key]) ) { $source = 'studio alias'; return $ALIAS[$key]; }
  $name = vw_tidy_name($raw);
  if ($name === '') { $source = '(none)'; return ''; }
  $u = get_user_by('slug', sanitize_title($name));
  if ($u && trim($u->display_name) !== '') { $source = "WP account ({$u->user_nicename})"; return $u->display_name; }
  if ( preg_match('/author\/([a-z0-9\-]+)/i', $live_content, $m) ) {
    $au = get_user_by('slug', $m[1]);
    if ($au && vw_surname($au->display_name) !== '' && vw_surname($au->display_name) === vw_surname($name)) {
      $source = "WP author-box (typo-corrected: {$m[1]})"; return $au->display_name;
    }
  }
  $source = "cleaned name (no WP account)"; return $name;
}

function vw_gallery_block($items) {   // each item carries its OWN caption (per-album credit)
  $inner='';
  foreach ($items as $it) {
    $id=(int)$it['id']; $url=esc_url($it['url']); $cap=esc_html($it['caption']);
    $fig = $cap !== '' ? "<figcaption class=\"wp-element-caption\">$cap</figcaption>" : ''; // omit when credit is blank
    $inner .= "\n<!-- wp:image {\"id\":$id,\"sizeSlug\":\"large\",\"linkDestination\":\"none\",\"lightbox\":{\"enabled\":true}} -->\n"
      ."<figure class=\"wp-block-image size-large\"><img src=\"$url\" alt=\"\" class=\"wp-image-$id\"/>$fig</figure>\n<!-- /wp:image -->\n";
  }
  return "<!-- wp:gallery {\"columns\":3,\"linkTo\":\"none\",\"className\":\"vw-fb-gallery\"} -->\n"
    ."<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped vw-fb-gallery\">$inner</figure>\n"
    ."<!-- /wp:gallery -->";
}

$created=[];
foreach ($POSTS as $live_id => $sources) {
  $live = get_post((int)$live_id);
  if ( ! $live ) { echo "[vw] skip $live_id: post not found\n"; continue; }
  $path = preg_match('/id=["\']jig2["\']|\[jig|fbcdn|scontent|akamaihd/i',$live->post_content) ? 'A'
        : (stripos($live->post_content,'OAuthException')!==false ? 'B' : 'A');

  $title = vw_normalize($live->post_title, $title_changed);

  $draft_id = wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>$title,
    'post_author'=>$live->post_author,'post_date'=>$live->post_date,'post_date_gmt'=>$live->post_date_gmt,
    'edit_date'=>true,'post_content'=>'','meta_input'=>['_vw_import_draft_of'=>$live_id,'_vw_import_path'=>$path]], true);

  // import each SOURCE ALBUM; credit is resolved PER PHOTO (album credit is fallback only),
  // then all images combine into one gallery. Image bytes read straight from the zip.
  $items=[]; $atts=[]; $credit_dist=[];
  foreach ($sources as $srcAlbum) {
    // album-level fallback credit (used only when a photo carries no own attribution)
    $album_name = vw_normalize( vw_album_credit($live->post_content, $srcAlbum['desc'], $album_src), $cc );
    $album_caption = $album_name !== '' ? "Photo by $album_name" : '';
    $descByBase = $srcAlbum['descByBase'] ?? [];
    foreach ($srcAlbum['entries'] as $e) {
      $bytes = $za->getFromName($e);
      if ($bytes === false) continue;
      // per-photo credit from THIS image's FB description; album credit as fallback.
      $praw    = vw_extract_photo_credit( $descByBase[ basename($e) ] ?? '' );
      $pname   = $praw !== '' ? vw_normalize( vw_canonical_credit($praw, $live->post_content, $psrc), $cc ) : '';
      $caption = $pname !== '' ? "Photo by $pname" : $album_caption;
      $bits = wp_upload_bits(basename($e), null, $bytes);
      if (!empty($bits['error'])) continue;
      $ft = wp_check_filetype($bits['file']);
      $aid = wp_insert_attachment(['guid'=>$bits['url'],'post_mime_type'=>$ft['type'],
        'post_title'=>preg_replace('/\.[^.]+$/','',basename($e)),'post_excerpt'=>$caption,'post_status'=>'inherit'],
        $bits['file'], $draft_id);
      wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid,$bits['file']));
      update_post_meta($aid,'_wp_attachment_image_alt','');
      update_post_meta($aid,'_needs_alt_review',1);
      $atts[]=$aid; $items[]=['id'=>$aid,'url'=>$bits['url'],'caption'=>$caption]; // per-image caption
      $k = $caption !== '' ? $caption : '(no credit)';
      $credit_dist[$k] = ($credit_dist[$k] ?? 0) + 1;
    }
  }
  $credits = []; arsort($credit_dist);
  foreach ($credit_dist as $cap=>$n) $credits[] = "$cap x$n";

  $body = vw_clean_body($live->post_content, $removed);
  $body = vw_normalize($body, $body_changed);
  $gallery = vw_gallery_block($items);
  $content = trim($body)==='' ? $gallery : "$body\n\n$gallery";
  wp_update_post(['ID'=>$draft_id,'post_content'=>$content]);
  if ($atts) set_post_thumbnail($draft_id,$atts[0]);

  $edit = admin_url("post.php?post=$draft_id&action=edit");
  $created[] = ['live'=>$live_id,'draft'=>$draft_id,'path'=>$path,'albums'=>count($sources),
    'imgs'=>count($atts),'atts'=>$atts,'credits'=>$credits,'removed'=>$removed,
    'title_before'=>$live->post_title,'title_after'=>$title,'title_changed'=>(bool)$title_changed,
    'edit'=>$edit,'preview'=>home_url("/?p=$draft_id&preview=true")];

  echo "=== live $live_id -> DRAFT $draft_id | path $path | ".count($sources)." album(s) | ".count($atts)." images ===\n";
  foreach ($credits as $c) echo "  credit: $c\n";
  echo "  title: ".($title_changed?"NORMALIZED '{$live->post_title}' -> '$title'":"unchanged")."\n";
  echo "  cleaner removed: ".count($removed)."\n  edit: $edit\n";
}
$za->close();
file_put_contents($OUT, json_encode($created, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "\nDONE. ".count($created)." drafts. reversal data -> $OUT\n";
