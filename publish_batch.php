<?php
/**
 * VW publish/replace BATCH runner — applies the copy-in-place mechanics proven on
 * pair 81982->67740 (publish_replace.php) to the remaining import drafts.
 *
 * Pairs are derived FRESH from the DB (_vw_import_draft_of); drafts carrying
 * _vw_publish_exclude (85536 getty-rights-hold) or _vw_retired_after_publish
 * (already published) are excluded. Idempotent: re-running skips retired drafts.
 *
 * Per post: reversal entry appended to manifest BEFORE any write -> copy-in-place
 * -> set thumbnail -> reparent attachments -> _vw_repaired_from -> retire draft
 * (status stays 'draft' + _vw_retired_after_publish=1, never trash).
 *
 * Per-post verify (halts the entire run on ANY failure): HTTP 200 on real
 * permalink, wp-block-gallery present, gallery-scoped figcaption count == draft
 * count (rendered total minus featured-image caption), no dead-JIG markers
 * ('justified-image-grid' / 'jigErrorMessage'), frozen fields unchanged,
 * featured image file on disk.
 *
 * Usage: php publish_batch.php --socket=<sock> --manifest=<path> --progress=<path>
 *          --batch=<n> [--limit=25] [--dry-run]
 */

const BASE_URL   = 'http://vancouverweekly-local.local';
const UPLOADS    = '/Users/ricardokhayatte/Local Sites/vancouverweekly-local/app/public/wp-content/uploads';
const TARGET_SET = 362; // 364 drafts - 85536 (excluded) - 81982 (test, already published)
const GETTY_HOLD = 85536;

$opts = getopt('', ['socket:', 'manifest:', 'progress:', 'batch:', 'limit:', 'dry-run']);
foreach (['socket', 'manifest', 'progress', 'batch'] as $req) {
    if (!isset($opts[$req])) { fwrite(STDERR, "missing --$req\n"); exit(1); }
}
$dry   = isset($opts['dry-run']);
$limit = isset($opts['limit']) ? (int)$opts['limit'] : 25;
$batch = (int)$opts['batch'];

$db = mysqli_connect('localhost', 'root', 'root', 'local', 0, $opts['socket']);
if (!$db) { fwrite(STDERR, "db connect failed\n"); exit(1); }
mysqli_set_charset($db, 'utf8mb4');

function row($db, $sql) { $r = mysqli_query($db, $sql); return $r ? mysqli_fetch_assoc($r) : null; }
function meta($db, $post_id, $key) {
    $r = row($db, sprintf("SELECT meta_value FROM wptg_postmeta WHERE post_id=%d AND meta_key='%s' LIMIT 1", $post_id, mysqli_real_escape_string($db, $key)));
    return $r ? $r['meta_value'] : null;
}
function fetch_url($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$body];
}

// ---- Exclusion assertion: 85536 must be held ----
$getty_flag   = meta($db, GETTY_HOLD, '_vw_publish_exclude');
$getty_status = row($db, "SELECT post_status FROM wptg_posts WHERE ID=" . GETTY_HOLD)['post_status'] ?? 'MISSING';
if ($getty_flag !== 'getty-rights-hold' || $getty_status !== 'draft') {
    fwrite(STDERR, "ABORT: 85536 exclusion assertion FAILED (flag=" . var_export($getty_flag, true) . ", status=$getty_status)\n");
    exit(1);
}

// ---- Derive publish set fresh from DB ----
$pairs = [];
$r = mysqli_query($db, "
    SELECT pm.post_id AS draft_id, CAST(pm.meta_value AS UNSIGNED) AS live_id
    FROM wptg_postmeta pm
    JOIN wptg_posts d ON d.ID = pm.post_id AND d.post_status = 'draft'
    WHERE pm.meta_key = '_vw_import_draft_of'
      AND NOT EXISTS (SELECT 1 FROM wptg_postmeta e WHERE e.post_id = pm.post_id AND e.meta_key = '_vw_publish_exclude')
      AND NOT EXISTS (SELECT 1 FROM wptg_postmeta t WHERE t.post_id = pm.post_id AND t.meta_key = '_vw_retired_after_publish')
    ORDER BY pm.post_id");
while ($p = mysqli_fetch_assoc($r)) $pairs[] = ['draft' => (int)$p['draft_id'], 'live' => (int)$p['live_id']];

foreach ($pairs as $p) {
    if ($p['draft'] === GETTY_HOLD) { fwrite(STDERR, "ABORT: 85536 leaked into publish set\n"); exit(1); }
}

$retired = (int)row($db, "SELECT COUNT(*) c FROM wptg_postmeta WHERE meta_key='_vw_retired_after_publish'")['c'];
$done_so_far = $retired - 1; // minus the pre-batch test post 81982
if (count($pairs) + $done_so_far !== TARGET_SET) {
    fwrite(STDERR, "ABORT: set assertion FAILED — remaining " . count($pairs) . " + done $done_so_far != " . TARGET_SET . "\n");
    exit(1);
}
echo "ASSERT OK: 85536 held (flag=getty-rights-hold, status=draft, not in set); remaining " . count($pairs) . " + done $done_so_far == " . TARGET_SET . "\n";

$todo = array_slice($pairs, 0, $limit);
if ($dry) {
    echo "DRY RUN batch $batch: would process " . count($todo) . " pairs:\n";
    foreach ($todo as $p) echo "  {$p['draft']} -> {$p['live']}\n";
    exit(0);
}

// ---- Process the batch ----
$verified = 0;
foreach ($todo as $p) {
    $draft_id = $p['draft'];
    $live_id  = $p['live'];

    $draft = row($db, "SELECT post_status, post_content FROM wptg_posts WHERE ID=$draft_id");
    $live  = row($db, "SELECT post_status, post_type, post_name, post_date, post_author, post_content, post_modified FROM wptg_posts WHERE ID=$live_id");
    if (!$draft || $draft['post_status'] !== 'draft') { fwrite(STDERR, "HALT at $draft_id->$live_id: draft missing/not draft\n"); exit(1); }
    if (!$live || $live['post_status'] !== 'publish' || $live['post_type'] !== 'post') { fwrite(STDERR, "HALT at $draft_id->$live_id: live not a published post\n"); exit(1); }
    $draft_thumb = meta($db, $draft_id, '_thumbnail_id');
    if (!$draft_thumb) { fwrite(STDERR, "HALT at $draft_id->$live_id: draft has no _thumbnail_id\n"); exit(1); }
    $thumb_file = meta($db, (int)$draft_thumb, '_wp_attached_file');
    if (!$thumb_file || !file_exists(UPLOADS . '/' . $thumb_file)) { fwrite(STDERR, "HALT at $draft_id->$live_id: featured file missing on disk ($thumb_file)\n"); exit(1); }

    // Reversal entry BEFORE any write
    $atts = [];
    $ar = mysqli_query($db, "SELECT ID, post_parent FROM wptg_posts WHERE post_type='attachment' AND post_parent=$draft_id");
    while ($a = mysqli_fetch_assoc($ar)) $atts[(int)$a['ID']] = (int)$a['post_parent'];

    $manifest = json_decode(file_get_contents($opts['manifest']), true);
    foreach ($manifest as $m) {
        if ((int)$m['live_id'] === $live_id) { fwrite(STDERR, "HALT at $draft_id->$live_id: already in reversal manifest but not retired\n"); exit(1); }
    }
    $manifest[] = [
        'live_id'                => $live_id,
        'draft_id'               => $draft_id,
        'old_post_content'       => $live['post_content'],
        'old_thumbnail_id'       => meta($db, $live_id, '_thumbnail_id'),
        'old_attachment_parents' => $atts,
        'old_post_modified'      => $live['post_modified'],
        'frozen_before'          => ['post_name' => $live['post_name'], 'post_date' => $live['post_date'], 'post_author' => $live['post_author']],
        'ts'                     => date('c'),
    ];
    if (file_put_contents($opts['manifest'], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        fwrite(STDERR, "HALT at $draft_id->$live_id: manifest write failed\n"); exit(1);
    }

    // Writes (single transaction, same as proven single-post runner)
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
        fwrite(STDERR, "HALT at $draft_id->$live_id: ROLLED BACK — " . $e->getMessage() . "\n");
        exit(1);
    }

    // ---- Per-post verify ----
    $fail = null;
    $after = row($db, "SELECT post_name, post_date, post_author, post_status FROM wptg_posts WHERE ID=$live_id");
    if ($after['post_name'] !== $live['post_name'] || $after['post_date'] !== $live['post_date']
        || $after['post_author'] !== $live['post_author'] || $after['post_status'] !== 'publish') {
        $fail = 'frozen fields changed: ' . json_encode($after);
    }

    if (!$fail) {
        [$code, $html] = fetch_url(BASE_URL . '/' . $live['post_name'] . '/');
        if ($code !== 200) $fail = "HTTP $code";
        elseif (strpos($html, 'wp-block-gallery') === false) $fail = 'no wp-block-gallery in rendered output';
        elseif (strpos($html, 'justified-image-grid') !== false) $fail = "dead-JIG marker 'justified-image-grid' still rendered";
        elseif (strpos($html, 'jigErrorMessage') !== false) $fail = "dead-JIG marker 'jigErrorMessage' still rendered";
        else {
            $draft_caps    = substr_count($draft['post_content'], '<figcaption');
            $rendered_caps = substr_count($html, '<figcaption');
            $thumb_caption = row($db, "SELECT post_excerpt FROM wptg_posts WHERE ID=$thumb");
            $featured_caps = ($thumb_caption && trim($thumb_caption['post_excerpt']) !== '') ? 1 : 0;
            $gallery_caps  = $rendered_caps - $featured_caps;
            if ($gallery_caps !== $draft_caps) $fail = "gallery figcaption mismatch: rendered $rendered_caps - featured $featured_caps = $gallery_caps != draft $draft_caps";
        }
    }

    // Checkpoint append (recorded even on failure, with status)
    $progress = file_exists($opts['progress']) ? json_decode(file_get_contents($opts['progress']), true) : ['done' => []];
    $progress['done'][] = [
        'live_id' => $live_id, 'draft_id' => $draft_id, 'batch' => $batch,
        'imgs' => substr_count($draft['post_content'], '<!-- wp:image'),
        'status' => $fail ? 'FAILED_VERIFY' : 'done', 'fail' => $fail, 'ts' => date('c'),
    ];
    file_put_contents($opts['progress'], json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if ($fail) {
        fwrite(STDERR, "HALT: pair $draft_id -> $live_id FAILED VERIFY: $fail\n");
        fwrite(STDERR, "Reversal data for this post is entry #" . (count($manifest) - 1) . " in the manifest.\n");
        exit(1);
    }
    $verified++;
}

$pubcount = (int)row($db, "SELECT COUNT(*) c FROM wptg_posts WHERE post_type='post' AND post_status='publish'")['c'];
$total_done = (int)row($db, "SELECT COUNT(*) c FROM wptg_postmeta WHERE meta_key='_vw_retired_after_publish'")['c'] - 1;
echo "Batch $batch: $verified/" . count($todo) . " verified, running total $total_done/" . TARGET_SET . ", published count $pubcount\n";
exit(0);
