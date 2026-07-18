<?php
/**
 * VW body-chrome cleanup on published lives (_vw_repaired_from posts).
 *
 * Strips scrape chrome from post_content ONLY:
 *   1. <footer class="article-tags entry-footer">…</footer> (+ <p><strong>Tags:</strong>…</p> variant)
 *   2. Any <p> outside gallery/image blocks whose normalized text is >=88% similar to the
 *      post title within a +/-12-char length window (title-duplicate paragraphs).
 *
 * HOLD (hand-fix separately, never auto-stripped): 67540, 67541 — title fused with lineup info.
 *
 * Guards per post: strip must remove ONLY matched chrome — non-chrome prose word count,
 * gallery-block count, wp:image count, figcaption count, and the image-ID set must be
 * IDENTICAL before/after, else the post is skipped (dry) or the run halts (write mode).
 * Reversal entry (old content + sha256) appended to manifest BEFORE each write.
 *
 * Usage: php chrome_strip.php --socket=<sock> --manifest=<path> --progress=<path>
 *          (--dry-run [--samples=ID,ID,...] | --post=<ID> | --batch=<n> [--limit=25])
 */

const BASE_URL  = 'http://vancouverweekly-local.local';
const HAND_FIX  = [67540, 67541];

$opts = getopt('', ['socket:', 'manifest:', 'progress:', 'dry-run', 'samples:', 'post:', 'batch:', 'limit:']);
if (empty($opts['socket'])) { fwrite(STDERR, "missing --socket\n"); exit(1); }
$dry = isset($opts['dry-run']);
if (!$dry && (empty($opts['manifest']) || empty($opts['progress']))) { fwrite(STDERR, "missing --manifest/--progress\n"); exit(1); }

$db = mysqli_connect('localhost', 'root', 'root', 'local', 0, $opts['socket']);
if (!$db) { fwrite(STDERR, "db connect failed\n"); exit(1); }
mysqli_set_charset($db, 'utf8mb4');

function row($db, $sql) { $r = mysqli_query($db, $sql); return $r ? mysqli_fetch_assoc($r) : null; }

function norm($s) {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5);
    $s = strip_tags($s);
    $s = mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
    return trim($s, " .:–-");
}

function content_stats($c) {
    preg_match_all('/wp-image-(\d+)/', $c, $ids);
    $idset = array_unique($ids[1]); sort($idset);
    // prose words outside gallery/image blocks, excluding tags footer
    $body = preg_replace('/<!-- wp:gallery.*?\/wp:gallery -->/s', '', $c);
    $body = preg_replace('/<!-- wp:image.*?\/wp:image -->/s', '', $body);
    $body = preg_replace('/<footer class="article-tags.*?<\/footer>/s', '', $body);
    $body = preg_replace('/<p>\s*<strong>Tags:<\/strong>.*?<\/p>/si', '', $body);
    return [
        'galleries'  => substr_count($c, '<!-- wp:gallery'),
        'images'     => substr_count($c, '<!-- wp:image'),
        'figcaps'    => substr_count($c, '<figcaption'),
        'idset'      => implode(',', $idset),
        'body_nt'    => $body,
    ];
}

function is_title_dup($para_norm, $title_norm) {
    if ($para_norm === '') return false;
    if ($para_norm === $title_norm) return true;
    similar_text($para_norm, $title_norm, $pct);
    return $pct >= 88 && abs(mb_strlen($para_norm) - mb_strlen($title_norm)) <= 12;
}

/** Returns [new_content, removed_tags(bool), removed_dup_count, removed_texts[]] */
function strip_chrome($content, $title) {
    $removed = [];
    $n = 0;
    $c = preg_replace('/\s*<footer class="article-tags.*?<\/footer>/s', '', $content, -1, $n1);
    $c = preg_replace('/\s*<p>\s*<strong>Tags:<\/strong>.*?<\/p>/si', '', $c, -1, $n2);
    $title_n = norm($title);
    // remove title-dup <p> only outside gallery/image blocks: split content on block comments,
    // process only segments outside blocks
    $parts = preg_split('/(<!-- wp:(?:gallery|image).*?\/wp:(?:gallery|image) -->)/s', $c, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $seg) {
        if (str_starts_with($seg, '<!-- wp:')) { $out .= $seg; continue; }
        $out .= preg_replace_callback('/\s*<p\b[^>]*>(.*?)<\/p>/s', function ($m) use ($title_n, &$removed, &$n) {
            if (is_title_dup(norm($m[1]), $title_n)) { $removed[] = trim(strip_tags($m[1])); $n++; return ''; }
            return $m[0];
        }, $seg);
    }
    return [$out, ($n1 + $n2) > 0, $n, $removed];
}

// ---- Build target set: repaired lives with chrome, excluding hand-fix holds ----
$targets = [];
$res = mysqli_query($db, "SELECT p.ID, p.post_title, p.post_name, p.post_date, p.post_author, p.post_content, p.post_modified
    FROM wptg_posts p JOIN wptg_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_vw_repaired_from'
    WHERE p.post_status='publish' ORDER BY p.ID");
while ($r = mysqli_fetch_assoc($res)) {
    if (in_array((int)$r['ID'], HAND_FIX, true)) continue;
    [$new, $tags_rm, $dups, $rm_texts] = strip_chrome($r['post_content'], $r['post_title']);
    if (!$tags_rm && $dups === 0) continue;

    $before = content_stats($r['post_content']);
    $after  = content_stats($new);
    $guard_fail = null;
    if ($before['galleries'] !== $after['galleries']) $guard_fail = 'gallery count changed';
    elseif ($before['images'] !== $after['images']) $guard_fail = 'wp:image count changed';
    elseif ($before['figcaps'] !== $after['figcaps']) $guard_fail = 'figcaption count changed';
    elseif ($before['idset'] !== $after['idset']) $guard_fail = 'image ID set changed';
    else {
        // non-chrome prose: words in body_nt minus the removed dup paragraphs' words
        $prose_before = str_word_count(norm($before['body_nt']));
        $dup_words = 0;
        foreach ($rm_texts as $t) $dup_words += str_word_count(norm($t));
        $prose_after = str_word_count(norm($after['body_nt']));
        if ($prose_after !== $prose_before - $dup_words) $guard_fail = "prose word mismatch: before $prose_before - dup $dup_words != after $prose_after";
    }
    $targets[] = ['row' => $r, 'new' => $new, 'tags_rm' => $tags_rm, 'dups' => $dups,
                  'rm_texts' => $rm_texts, 'guard_fail' => $guard_fail];
}

if ($dry) {
    $ok = array_filter($targets, fn($t) => !$t['guard_fail']);
    $blocked = array_filter($targets, fn($t) => $t['guard_fail']);
    echo "TARGETS: " . count($targets) . " chrome-affected (holds 67540/67541 excluded) | guard-pass: " . count($ok) . " | guard-blocked: " . count($blocked) . "\n";
    foreach ($blocked as $t) echo "  BLOCKED {$t['row']['ID']}: {$t['guard_fail']}\n";
    $samples = isset($opts['samples']) ? array_map('intval', explode(',', $opts['samples'])) : [];
    foreach ($targets as $t) {
        if (!in_array((int)$t['row']['ID'], $samples, true)) continue;
        $b = content_stats($t['row']['post_content']);
        $a = content_stats($t['new']);
        echo "\n========== SAMPLE {$t['row']['ID']} — {$t['row']['post_title']} ==========\n";
        echo "-- BEFORE (non-gallery body) --\n" . trim($b['body_nt']) . "\n";
        echo "-- REMOVED: tags-footer=" . ($t['tags_rm'] ? 'yes' : 'no') . ", title-dup paras={$t['dups']}" . ($t['rm_texts'] ? " (\"" . implode('" | "', $t['rm_texts']) . "\")" : "") . " --\n";
        echo "-- AFTER (non-gallery body) --\n" . trim($a['body_nt']) . "\n";
        echo "-- integrity: galleries {$b['galleries']}=={$a['galleries']}, images {$b['images']}=={$a['images']}, figcaps {$b['figcaps']}=={$a['figcaps']}, idset " . ($b['idset'] === $a['idset'] ? 'IDENTICAL' : 'CHANGED') . " --\n";
    }
    exit(0);
}

// ---- Write modes: --post=ID (single) or --batch=N (next --limit targets) ----
$progress = file_exists($opts['progress']) ? json_decode(file_get_contents($opts['progress']), true) : ['done' => []];
$done_ids = array_column($progress['done'], 'live_id');
$todo = array_values(array_filter($targets, fn($t) => !in_array((int)$t['row']['ID'], $done_ids, true)));
if (isset($opts['post'])) {
    $todo = array_values(array_filter($todo, fn($t) => (int)$t['row']['ID'] === (int)$opts['post']));
    if (!$todo) { fwrite(STDERR, "post {$opts['post']} not in pending target set\n"); exit(1); }
} else {
    $todo = array_slice($todo, 0, isset($opts['limit']) ? (int)$opts['limit'] : 25);
}
$batch = isset($opts['batch']) ? (int)$opts['batch'] : 0;

$verified = 0;
foreach ($todo as $t) {
    $r = $t['row'];
    $live_id = (int)$r['ID'];
    if ($t['guard_fail']) { fwrite(STDERR, "HALT $live_id: guard — {$t['guard_fail']}\n"); exit(1); }

    $manifest = file_exists($opts['manifest']) ? json_decode(file_get_contents($opts['manifest']), true) : [];
    foreach ($manifest as $m) if ((int)$m['live_id'] === $live_id) { fwrite(STDERR, "HALT $live_id: already in manifest\n"); exit(1); }
    $manifest[] = [
        'live_id' => $live_id, 'pass' => 'chrome-strip',
        'old_post_content' => $r['post_content'], 'old_sha256' => hash('sha256', $r['post_content']),
        'old_post_modified' => $r['post_modified'],
        'frozen_before' => ['post_name' => $r['post_name'], 'post_date' => $r['post_date'], 'post_author' => $r['post_author']],
        'ts' => date('c'),
    ];
    if (file_put_contents($opts['manifest'], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        fwrite(STDERR, "HALT $live_id: manifest write failed\n"); exit(1);
    }

    $esc = mysqli_real_escape_string($db, $t['new']);
    if (!mysqli_query($db, "UPDATE wptg_posts SET post_content='$esc', post_modified=NOW(), post_modified_gmt=UTC_TIMESTAMP() WHERE ID=$live_id")) {
        fwrite(STDERR, "HALT $live_id: UPDATE failed — " . mysqli_error($db) . "\n"); exit(1);
    }

    // ---- Verify ----
    $fail = null;
    $now = row($db, "SELECT post_name, post_date, post_author, post_status, post_content FROM wptg_posts WHERE ID=$live_id");
    if ($now['post_name'] !== $r['post_name'] || $now['post_date'] !== $r['post_date'] || $now['post_author'] !== $r['post_author'] || $now['post_status'] !== 'publish') $fail = 'frozen fields changed';
    elseif (strpos($now['post_content'], 'article-tags') !== false) $fail = 'article-tags still in content';
    else {
        [, $tags_left, $dups_left] = strip_chrome($now['post_content'], $r['post_title']);
        if ($tags_left || $dups_left > 0) $fail = "chrome still detected (tags=" . var_export($tags_left, true) . ", dups=$dups_left)";
    }
    if (!$fail) {
        $ch = curl_init(BASE_URL . '/' . $r['post_name'] . '/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $html = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) $fail = "HTTP $code";
        elseif (strpos($html, 'wp-block-gallery') === false) $fail = 'gallery missing from rendered output';
        elseif (stripos($html, '<strong>Tags:</strong>') !== false) $fail = 'Tags: chrome still rendered';
    }

    $progress = file_exists($opts['progress']) ? json_decode(file_get_contents($opts['progress']), true) : ['done' => []];
    $progress['done'][] = ['live_id' => $live_id, 'batch' => $batch, 'tags_rm' => $t['tags_rm'], 'dups_rm' => $t['dups'],
                          'status' => $fail ? 'FAILED_VERIFY' : 'done', 'fail' => $fail, 'ts' => date('c')];
    file_put_contents($opts['progress'], json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if ($fail) { fwrite(STDERR, "HALT: $live_id FAILED VERIFY: $fail (reversal in manifest)\n"); exit(1); }
    $verified++;
}

$pub = (int)row($db, "SELECT COUNT(*) c FROM wptg_posts WHERE post_type='post' AND post_status='publish'")['c'];
$total_done = count(json_decode(file_get_contents($opts['progress']), true)['done']);
echo "Chrome batch $batch: $verified/" . count($todo) . " verified, running total $total_done, published count $pub\n";
