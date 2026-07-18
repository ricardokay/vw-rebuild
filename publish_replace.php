<?php
/**
 * VW publish/replace runner — copies a repaired import draft's content onto its
 * live published post (copy-in-place). Frozen fields on the live post are NEVER
 * touched: post_name, post_date, post_date_gmt, post_author, guid, comments, terms.
 *
 * Per pair:
 *   1. Append reversal entry (old content, old _thumbnail_id, old attachment
 *      parents, old post_modified) to the manifest BEFORE any write.
 *   2. UPDATE live post_content = draft post_content (post_modified bumped).
 *   3. Set live _thumbnail_id from draft.
 *   4. Reparent draft's attachments to the live post.
 *   5. Mark live _vw_repaired_from=<draft_id>, copy _vw_import_path.
 *   6. Retire draft: keep post_status='draft', add _vw_retired_after_publish=1.
 *      (NOT trashed — trash auto-purges after 30 days.)
 *
 * Usage: php publish_replace.php --socket=<sock> --manifest=<path> --pair=<draft_id>:<live_id> [--dry-run]
 */

$opts = getopt('', ['socket:', 'manifest:', 'pair:', 'dry-run']);
foreach (['socket', 'manifest', 'pair'] as $req) {
    if (empty($opts[$req])) fwrite(STDERR, "missing --$req\n") && exit(1);
}
$dry = isset($opts['dry-run']);
[$draft_id, $live_id] = array_map('intval', explode(':', $opts['pair']));
if (!$draft_id || !$live_id) { fwrite(STDERR, "bad --pair\n"); exit(1); }

$db = mysqli_connect('localhost', 'root', 'root', 'local', 0, $opts['socket']);
if (!$db) { fwrite(STDERR, "db connect failed\n"); exit(1); }
mysqli_set_charset($db, 'utf8mb4');

function row($db, $sql) { $r = mysqli_query($db, $sql); return $r ? mysqli_fetch_assoc($r) : null; }
function meta($db, $post_id, $key) {
    $r = row($db, sprintf("SELECT meta_value FROM wptg_postmeta WHERE post_id=%d AND meta_key='%s' LIMIT 1", $post_id, mysqli_real_escape_string($db, $key)));
    return $r ? $r['meta_value'] : null;
}

// ---- Pre-flight guards ----
$draft = row($db, "SELECT ID, post_status, post_type, post_content FROM wptg_posts WHERE ID=$draft_id");
$live  = row($db, "SELECT ID, post_status, post_type, post_name, post_date, post_author, post_content, post_modified FROM wptg_posts WHERE ID=$live_id");
if (!$draft || $draft['post_status'] !== 'draft') { fwrite(STDERR, "ABORT: draft $draft_id not found or not status=draft\n"); exit(1); }
if (!$live || $live['post_status'] !== 'publish' || $live['post_type'] !== 'post') { fwrite(STDERR, "ABORT: live $live_id not a published post\n"); exit(1); }
if (meta($db, $draft_id, '_vw_import_draft_of') != $live_id) { fwrite(STDERR, "ABORT: draft $draft_id does not map to live $live_id\n"); exit(1); }
if (meta($db, $draft_id, '_vw_publish_exclude') !== null) { fwrite(STDERR, "ABORT: draft $draft_id carries _vw_publish_exclude\n"); exit(1); }
if (meta($db, $draft_id, '_vw_retired_after_publish') !== null) { fwrite(STDERR, "SKIP: draft $draft_id already retired (published earlier)\n"); exit(1); }
$draft_thumb = meta($db, $draft_id, '_thumbnail_id');
if (!$draft_thumb) { fwrite(STDERR, "ABORT: draft $draft_id has no _thumbnail_id\n"); exit(1); }

// ---- Reversal data (captured before any write) ----
$atts = [];
$r = mysqli_query($db, "SELECT ID, post_parent FROM wptg_posts WHERE post_type='attachment' AND post_parent=$draft_id");
while ($a = mysqli_fetch_assoc($r)) $atts[(int)$a['ID']] = (int)$a['post_parent'];

$entry = [
    'live_id'               => $live_id,
    'draft_id'              => $draft_id,
    'old_post_content'      => $live['post_content'],
    'old_thumbnail_id'      => meta($db, $live_id, '_thumbnail_id'),
    'old_attachment_parents'=> $atts,
    'old_post_modified'     => $live['post_modified'],
    'frozen_before'         => ['post_name' => $live['post_name'], 'post_date' => $live['post_date'], 'post_author' => $live['post_author']],
    'ts'                    => date('c'),
];

if ($dry) {
    echo "DRY RUN pair $draft_id -> $live_id: would replace " . strlen($live['post_content']) . "B with " . strlen($draft['post_content']) . "B, set thumb $draft_thumb, reparent " . count($atts) . " attachments\n";
    exit(0);
}

$manifest = file_exists($opts['manifest']) ? json_decode(file_get_contents($opts['manifest']), true) : [];
foreach ($manifest as $m) {
    if ((int)$m['live_id'] === $live_id) { fwrite(STDERR, "ABORT: live $live_id already in reversal manifest\n"); exit(1); }
}
$manifest[] = $entry;
if (file_put_contents($opts['manifest'], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "ABORT: could not write reversal manifest\n"); exit(1);
}

// ---- Writes (single transaction) ----
mysqli_begin_transaction($db);
try {
    $content = mysqli_real_escape_string($db, $draft['post_content']);
    mysqli_query($db, "UPDATE wptg_posts SET post_content='$content', post_modified=NOW(), post_modified_gmt=UTC_TIMESTAMP() WHERE ID=$live_id") or throw new Exception(mysqli_error($db));

    $thumb = (int)$draft_thumb;
    if (meta($db, $live_id, '_thumbnail_id') !== null) {
        mysqli_query($db, "UPDATE wptg_postmeta SET meta_value='$thumb' WHERE post_id=$live_id AND meta_key='_thumbnail_id'") or throw new Exception(mysqli_error($db));
    } else {
        mysqli_query($db, "INSERT INTO wptg_postmeta (post_id, meta_key, meta_value) VALUES ($live_id, '_thumbnail_id', '$thumb')") or throw new Exception(mysqli_error($db));
    }

    mysqli_query($db, "UPDATE wptg_posts SET post_parent=$live_id WHERE post_type='attachment' AND post_parent=$draft_id") or throw new Exception(mysqli_error($db));

    mysqli_query($db, "INSERT INTO wptg_postmeta (post_id, meta_key, meta_value) VALUES ($live_id, '_vw_repaired_from', '$draft_id')") or throw new Exception(mysqli_error($db));
    $import_path = meta($db, $draft_id, '_vw_import_path');
    if ($import_path !== null && meta($db, $live_id, '_vw_import_path') === null) {
        $ip = mysqli_real_escape_string($db, $import_path);
        mysqli_query($db, "INSERT INTO wptg_postmeta (post_id, meta_key, meta_value) VALUES ($live_id, '_vw_import_path', '$ip')") or throw new Exception(mysqli_error($db));
    }

    mysqli_query($db, "INSERT INTO wptg_postmeta (post_id, meta_key, meta_value) VALUES ($draft_id, '_vw_retired_after_publish', '1')") or throw new Exception(mysqli_error($db));

    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    fwrite(STDERR, "ROLLED BACK pair $draft_id -> $live_id: " . $e->getMessage() . "\n");
    exit(1);
}

// ---- Post-write assertions ----
$after = row($db, "SELECT post_name, post_date, post_author, post_status FROM wptg_posts WHERE ID=$live_id");
$frozen_ok = $after['post_name'] === $live['post_name'] && $after['post_date'] === $live['post_date'] && $after['post_author'] === $live['post_author'] && $after['post_status'] === 'publish';
$reparented = row($db, "SELECT COUNT(*) c FROM wptg_posts WHERE post_type='attachment' AND post_parent=$live_id")['c'];
$draft_after = row($db, "SELECT post_status FROM wptg_posts WHERE ID=$draft_id")['post_status'];

echo "PUBLISHED pair $draft_id -> $live_id\n";
echo "  frozen fields unchanged: " . ($frozen_ok ? 'YES' : 'NO — INVESTIGATE') . "\n";
echo "  attachments now on live: $reparented (was " . count($atts) . " on draft)\n";
echo "  live _thumbnail_id: " . meta($db, $live_id, '_thumbnail_id') . "\n";
echo "  draft status: $draft_after, retired flag: " . meta($db, $draft_id, '_vw_retired_after_publish') . "\n";
exit($frozen_ok ? 0 : 1);
