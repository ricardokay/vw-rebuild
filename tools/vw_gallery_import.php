<?php
/**
 * VW Facebook-album gallery import tool (v2, validated).
 * ---------------------------------------------------------------------------
 * WHAT IT DOES
 *   Rebuilds dead Facebook galleries on Wayback-recovered posts. For each album
 *   it imports the Facebook export images as real WordPress attachments, builds
 *   a native Gutenberg `wp:gallery` block, and creates a REVERSIBLE DRAFT post.
 *   It NEVER modifies the live published post — a new draft (post_status=draft,
 *   meta `_vw_import_draft_of` = live id) holds the proposed content for review.
 *   Reversal: tools/vw_gallery_import_reverse.php.
 *
 * TWO DEAD-MARKUP PATHS (auto-detected from the live post_content)
 *   A) strip-and-replace : post has real dead gallery markup (jig2 / fbcdn) ->
 *      remove it, insert the native gallery.
 *   B) build-into-stub   : post has only a Facebook "OAuthException" error
 *      string, no gallery markup -> strip the error text, build the gallery.
 *
 * FIXES BAKED IN
 *   - Fix A: conservative body cleaner (vw_clean_body) — strips ONLY named
 *     artifacts (jig2/fbcdn/OAuth error + leftover <section.authorpage>,
 *     <div.category_container author-information>, empty jigErrorMessage span,
 *     empty/nbsp/mojibake <p>). Never touches real prose. Reports each removal.
 *   - Fix B: photo credit cleaning + name canonicalization — prefers the
 *     WordPress author-account display_name (looked up via the post's
 *     author-box slug) over the Facebook album description, which carries
 *     typos (e.g. FB "Jashua" -> WP "Joshua"). Strips @handles / boilerplate
 *     tails / mangled dates from the credit. Falls back to a cleaned FB desc
 *     when no author-box slug is present.
 *   - Fix C: utf8mb4 end to end (SET NAMES utf8mb4) + mojibake normalization on
 *     title and captions (curly quotes/dashes, drops U+FFFD).
 *
 * PER IMPORT: images -> attachments (caption = "Photo by <name>", blank alt,
 *   meta `_needs_alt_review`=1); featured image = first attachment; original
 *   post_date / slug / author preserved.
 *
 * USAGE (drafts only, review before publishing):
 *   1. Stage album folders to $STAGE (unzip the FB export media folders).
 *   2. Edit $ALBUMS below: [live_post_id, zip_folder_name, fb_album_description].
 *   3. Run with the Local socket flag (DB_HOST=localhost otherwise forces TCP):
 *      php -d mysqli.default_socket=<sock> -d pdo_mysql.default_socket=<sock> \
 *          -d memory_limit=768M tools/vw_gallery_import.php
 *   Writes reversal data to /tmp/vw_import_v2_created.json.
 *
 * NOT YET HANDLED (add before scaling): non-concert caption conventions,
 *   festival multi-day post mapping, large-batch (100+ image) performance.
 */
define('WP_USE_THEMES', false);
require '/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
global $wpdb; $wpdb->query("SET NAMES utf8mb4");

$STAGE = '/tmp/vw_stage';   // staged FB export album folders (see USAGE)
$ALBUMS = [
  [66353, 'TheGrowlerswiththeGardenTwins_616788415097108',         'All photos by Jennifer McInnis (www.creativecopperimages.com). All rights reserved.'],
  [67771, 'WintersleepEveningHymnsWalrus_866133496829264',         'All photos by Ryan L. Johnson. All rights reserved.'],
  [67909, 'VintageTrouble_1358888147553794',                       'Photos by Sharon Steele  / Nov.12 / 2017'],
  [68931, 'WestwardMusicFestivalDay2ToucheAmore_1310288099080466', 'Photos by Quinn Middleton Sept. 15 / 2017'],
  [65497, 'ACDC_780839368692011',                                  'All photos by Jashua Peter Grafstein (JustJash.com). All rights reserved.'],
];

/* ---- Fix C: mojibake normalization ---- */
function vw_normalize($s, &$changed) {
  $o = $s;
  $map = ["\xE2\x80\x99"=>"'","\xE2\x80\x98"=>"'","\xE2\x80\x9C"=>'"',"\xE2\x80\x9D"=>'"',
          "\xE2\x80\x93"=>"-","\xE2\x80\x94"=>"—",
          "\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2"=>"'", "\xC3\xA2\xE2\x82\xAC\xC5\x93"=>'"',
          "\xC3\xA2\xE2\x82\xAC"=>'"', "\xC2\xA0"=>" "];
  $s = strtr($s, $map);
  $s = str_replace("\xEF\xBF\xBD", "", $s);        // drop U+FFFD replacement chars
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

/* ---- Fix B: credit resolution ---- */
function vw_credit($live_content, $fb_desc, &$source) {
  if (preg_match('/author\/([a-z0-9\-]+)/i', $live_content, $m)) {
    $u = get_user_by('slug', $m[1]);
    if ($u && trim($u->display_name)!=='') { $source = "WP author account ({$m[1]})"; return $u->display_name; }
  }
  // fallback: clean the FB desc
  $d = preg_replace('/^\s*(all\s+)?(photos?|pics?|photography)\s+by\s+/i', '', $fb_desc);
  $d = preg_split('/\s*[\(\/]|\.\s|,|\s+@|\s+www|\s+VANCOUVER|\s+On\s+|\s+\d/i', $d)[0];
  $d = preg_replace('/@\S+/', '', $d);
  $source = "FB album description (cleaned)";
  return trim($d, " .-");
}

function vw_gallery_block($items, $caption) {
  $inner='';
  foreach ($items as $it) {
    $id=(int)$it['id']; $url=esc_url($it['url']); $cap=esc_html($caption);
    $inner .= "\n<!-- wp:image {\"id\":$id,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n"
      ."<figure class=\"wp-block-image size-large\"><img src=\"$url\" alt=\"\" class=\"wp-image-$id\"/>"
      ."<figcaption class=\"wp-element-caption\">$cap</figcaption></figure>\n<!-- /wp:image -->\n";
  }
  return "<!-- wp:gallery {\"columns\":3,\"linkTo\":\"none\",\"className\":\"vw-fb-gallery\"} -->\n"
    ."<figure class=\"wp-block-gallery has-nested-images columns-3 is-cropped vw-fb-gallery\">$inner</figure>\n"
    ."<!-- /wp:gallery -->";
}

$created=[];
foreach ($ALBUMS as [$live_id,$folder,$fb_desc]) {
  $live = get_post($live_id);
  $path = preg_match('/id=["\']jig2["\']|\[jig|fbcdn|scontent|akamaihd/i',$live->post_content) ? 'A'
        : (stripos($live->post_content,'OAuthException')!==false ? 'B' : 'A');

  // Fix C: title
  $title = vw_normalize($live->post_title, $title_changed);
  // Fix B: credit
  $name = vw_credit($live->post_content, $fb_desc, $src);
  $name = vw_normalize($name, $cap_changed);
  $caption = "Photo by $name";

  // draft shell
  $draft_id = wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>$title,
    'post_author'=>$live->post_author,'post_date'=>$live->post_date,'post_date_gmt'=>$live->post_date_gmt,
    'edit_date'=>true,'post_content'=>'','meta_input'=>['_vw_import_draft_of'=>$live_id,'_vw_import_path'=>$path]], true);

  // attachments
  $files = glob("$STAGE/$folder/*"); sort($files, SORT_STRING);
  $items=[]; $atts=[];
  foreach ($files as $f) {
    if (!preg_match('/\.(jpe?g|png|gif)$/i',$f)) continue;
    $bits = wp_upload_bits(basename($f), null, file_get_contents($f));
    if (!empty($bits['error'])) continue;
    $ft = wp_check_filetype($bits['file']);
    $aid = wp_insert_attachment(['guid'=>$bits['url'],'post_mime_type'=>$ft['type'],
      'post_title'=>preg_replace('/\.[^.]+$/','',basename($f)),'post_excerpt'=>$caption,'post_status'=>'inherit'],
      $bits['file'], $draft_id);
    wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid,$bits['file']));
    update_post_meta($aid,'_wp_attachment_image_alt','');
    update_post_meta($aid,'_needs_alt_review',1);
    $atts[]=$aid; $items[]=['id'=>$aid,'url'=>$bits['url']];
  }

  // Fix A: clean body + gallery
  $body = vw_clean_body($live->post_content, $removed);
  $body = vw_normalize($body, $body_changed);
  $gallery = vw_gallery_block($items,$caption);
  $content = trim($body)==='' ? $gallery : "$body\n\n$gallery";
  wp_update_post(['ID'=>$draft_id,'post_content'=>$content]);
  if ($atts) set_post_thumbnail($draft_id,$atts[0]);

  $edit = admin_url("post.php?post=$draft_id&action=edit");
  $created[] = ['live'=>$live_id,'draft'=>$draft_id,'path'=>$path,'imgs'=>count($atts),'atts'=>$atts,
    'credit'=>$caption,'source'=>$src,'removed'=>$removed,
    'title_before'=>$live->post_title,'title_after'=>$title,'title_changed'=>(bool)$title_changed,
    'edit'=>$edit,'preview'=>home_url("/?p=$draft_id&preview=true")];

  echo "=== live $live_id -> DRAFT $draft_id | path $path | {$created[count($created)-1]['imgs']} images ===\n";
  echo "  credit: '$caption'  <- $src\n";
  echo "  title: ".($title_changed?"NORMALIZED '{$live->post_title}' -> '$title'":"unchanged (no mojibake)")."\n";
  echo "  cleaner removed (".count($removed)."):\n";
  foreach ($removed as $r) echo "     - {$r[0]}: \"{$r[1]}\"\n";
  echo "  edit: $edit\n";
}
file_put_contents('/tmp/vw_import_v2_created.json', json_encode($created, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "\nDONE. reversal data -> /tmp/vw_import_v2_created.json\n";
