<?php
/** REVERSAL for the v2 DRAFT gallery imports (73250/73266/73293/73318/73335).
 *  Deletes the 94 imported attachments (files + thumbnails + DB rows) and the 5 draft posts.
 *  Live published posts are never referenced -> untouched.
 *  Run: PHP -d mysqli.default_socket=$SOCK -d pdo_mysql.default_socket=$SOCK /tmp/vw_reverse_v2.php
 */
define('WP_USE_THEMES', false);
require '/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-load.php';
$c = json_decode(file_get_contents('/tmp/vw_import_v2_created.json'), true);
$att=0; $dr=0;
foreach ($c as $x) {
  foreach ($x['atts'] as $aid) { if (wp_delete_attachment((int)$aid, true)) $att++; }
  if (wp_delete_post((int)$x['draft'], true)) $dr++;
  echo "reversed draft {$x['draft']} (live {$x['live']}), {$x['imgs']} attachments\n";
}
echo "TOTAL removed: $att attachments, $dr draft posts. Live posts untouched.\n";
