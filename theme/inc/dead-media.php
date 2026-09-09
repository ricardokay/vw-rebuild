<?php
/**
 * Dead-media suppression — splice-only.
 *
 * Deletes byte ranges and returns the remainder; it never reserializes, so every
 * retained byte is bit-identical to post_content. DOMDocument was rejected here:
 * a round-trip gate over 300 published posts showed 7% real divergence — it
 * percent-encoded accented filenames, collapsed boolean attributes, and
 * auto-closed </div> on the 149 posts carrying unbalanced wrapper markup.
 * See PROJECT-LOG 2026-09-08.
 *
 * Detection is deliberately narrow. Local files are checked on disk (which is
 * also the self-healing hook — a recovered file simply renders again). Remote
 * images are suppressed only for hosts proven dead. Everything else, including
 * the 2,237 relative-URL images, passes through untouched and is left to the
 * client-side net, which only fires on an actual load failure.
 */

const VW_DEAD_HOSTS         = [ 'web.archive.org' ];
const VW_DEAD_HOST_SUFFIXES = [ '.fbcdn.net' ];
const VW_WRAP_BUDGET        = 4000;

function vw_media_is_dead( string $src ): bool {
	$src = trim( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ) );
	if ( $src === '' ) return false;

	static $baseurl = null, $basedir = null;
	if ( $baseurl === null ) {
		$up      = wp_upload_dir();
		$baseurl = $up['baseurl'];
		$basedir = $up['basedir'];
	}

	if ( strpos( $src, $baseurl ) === 0 ) {
		$rel = preg_replace( '/[?#].*$/', '', substr( $src, strlen( $baseurl ) ) );
		return ! file_exists( $basedir . $rel );
	}

	$host = parse_url( $src, PHP_URL_HOST );
	if ( ! $host ) return false;

	$host = strtolower( $host );
	if ( in_array( $host, VW_DEAD_HOSTS, true ) ) return true;
	foreach ( VW_DEAD_HOST_SUFFIXES as $suffix ) {
		if ( substr( $host, -strlen( $suffix ) ) === $suffix ) return true;
	}
	return false;
}

/**
 * Byte ranges of every <noscript> block. Images inside one never render, so they
 * are skipped entirely — this is what keeps the 230 JIG posts byte-untouched.
 */
function vw_noscript_ranges( string $html ): array {
	if ( ! preg_match_all( '#<noscript\b.*?</noscript>#is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return [];
	}
	$out = [];
	foreach ( $m[0] as $hit ) {
		$out[] = [ $hit[1], $hit[1] + strlen( $hit[0] ) ];
	}
	return $out;
}

function vw_offset_in_ranges( int $offset, array $ranges ): bool {
	foreach ( $ranges as $r ) {
		if ( $offset >= $r[0] && $offset < $r[1] ) return true;
	}
	return false;
}

/**
 * Forward balanced scan from an opening tag to its true match.
 * Returns the offset just past the closing tag, or null if it cannot balance.
 */
function vw_match_close( string $html, int $open_at, string $tag ): ?int {
	$open_re  = '#<' . $tag . '\b#i';
	$close_tag = '</' . $tag . '>';
	$depth = 0;
	$pos   = $open_at;
	$len   = strlen( $html );

	while ( $pos < $len ) {
		$next_close = stripos( $html, $close_tag, $pos );
		if ( $next_close === false ) return null;

		$slice = substr( $html, $pos, $next_close - $pos );
		$depth += preg_match_all( $open_re, $slice );
		$depth--;

		$pos = $next_close + strlen( $close_tag );
		if ( $depth === 0 ) return $pos;
		if ( $depth < 0 ) return null;
	}
	return null;
}

/**
 * The range to delete for a dead image: its enclosing <figure> (which takes the
 * figcaption with it), else an <a> that wraps nothing but the image, else the
 * <img> tag alone. The last case is the documented fallback for malformed
 * markup — it never guesses at structure.
 */
function vw_wrapper_range( string $html, int $img_start, int $img_end ): array {
	$window_start = max( 0, $img_start - VW_WRAP_BUDGET );
	$before       = substr( $html, $window_start, $img_start - $window_start );

	$open_rel = strripos( $before, '<figure' );
	if ( $open_rel !== false ) {
		$open_at = $window_start + $open_rel;
		$between = substr( $html, $open_at, $img_start - $open_at );
		if ( stripos( $between, '</figure>' ) === false ) {
			$close_end = vw_match_close( $html, $open_at, 'figure' );
			if ( $close_end !== null && $close_end >= $img_end ) {
				return [ $open_at, $close_end ];
			}
		}
	}

	$a_rel = strripos( $before, '<a ' );
	if ( $a_rel !== false ) {
		$a_at = $window_start + $a_rel;
		$gap  = substr( $html, $a_at, $img_start - $a_at );
		if ( stripos( $gap, '</a>' ) === false && preg_match( '#^<a\b[^>]*>\s*$#is', $gap ) ) {
			$a_end = vw_match_close( $html, $a_at, 'a' );
			if ( $a_end !== null && $a_end >= $img_end ) {
				$tail = substr( $html, $img_end, $a_end - $img_end - 4 );
				if ( trim( $tail ) === '' ) return [ $a_at, $a_end ];
			}
		}
	}

	return [ $img_start, $img_end ];
}

/**
 * A gallery container left holding no images and no text is removed too, so an
 * emptied grid does not collapse to a bare frame.
 */
function vw_strip_empty_galleries( string $html ): string {
	$pattern = '#<(figure|div)\b[^>]*class=["\'][^"\']*(?:wp-block-gallery|gallery)[^"\']*["\'][^>]*>#i';
	$guard   = 0;

	while ( $guard++ < 20 && preg_match( $pattern, $html, $m, PREG_OFFSET_CAPTURE ) ) {
		$open_at = $m[0][1];
		$tag     = strtolower( $m[1][0] );
		$end     = vw_match_close( $html, $open_at, $tag );
		if ( $end === null ) break;

		$inner = substr( $html, $open_at, $end - $open_at );
		if ( stripos( $inner, '<img' ) !== false || trim( wp_strip_all_tags( $inner ) ) !== '' ) {
			break;
		}
		$html = substr_replace( $html, '', $open_at, $end - $open_at );
	}
	return $html;
}

function vw_dead_media_filter( string $html ): string {
	if ( is_admin() || is_feed() || $html === '' ) return $html;
	if ( stripos( $html, '<img' ) === false ) return $html;

	if ( ! preg_match_all( '#<img\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$noscript = vw_noscript_ranges( $html );
	$ranges   = [];

	foreach ( $m[0] as $hit ) {
		$tag   = $hit[0];
		$start = $hit[1];
		if ( vw_offset_in_ranges( $start, $noscript ) ) continue;
		if ( ! preg_match( '#\ssrc=["\']([^"\']*)["\']#i', $tag, $sm ) ) continue;
		if ( ! vw_media_is_dead( $sm[1] ) ) continue;

		$ranges[] = vw_wrapper_range( $html, $start, $start + strlen( $tag ) );
	}

	if ( ! $ranges ) return $html;

	usort( $ranges, fn( $a, $b ) => $a[0] <=> $b[0] );
	$merged = [];
	foreach ( $ranges as $r ) {
		$last = count( $merged ) - 1;
		if ( $last >= 0 && $r[0] <= $merged[ $last ][1] ) {
			$merged[ $last ][1] = max( $merged[ $last ][1], $r[1] );
		} else {
			$merged[] = $r;
		}
	}

	foreach ( array_reverse( $merged ) as $r ) {
		$html = substr_replace( $html, '', $r[0], $r[1] - $r[0] );
	}

	return vw_strip_empty_galleries( $html );
}
