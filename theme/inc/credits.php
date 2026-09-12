<?php
/**
 * Credit derivation — shared by the article header and the homepage.
 *
 * Hoisted out of inc/article-header.php in Round 1 session B. Nothing about the
 * logic changed; it simply stopped being reachable only behind ?vw_header=1.
 * The function names keep their vw_ah_ prefix so the article header's call
 * sites are untouched and the preview keeps behaving identically.
 *
 * Everything here derives credits from existing content. The Round 5 credits
 * panel will add stored per-post credits; when it does, these become the
 * fallback for the ~2,800 archive posts nobody is going to re-enter by hand,
 * and the only change needed is a lookup in front of vw_ah_credits().
 *
 * Newspack's own _newspack_byline is neither read nor written here. Its feature
 * is enabled and its the_author / pre_newspack_posted_by filters are live; two
 * systems writing one byline would fight.
 */

defined( 'ABSPATH' ) || exit;

/** Pull "Photo(s) by X" out of caption text. Returns names in order found. */
function vw_ah_extract_credit( string $text ): array {
	$out = [];
	if ( preg_match_all( '/Photos?\s*(?:by|:)\s*:?\s*([\p{Lu}][\p{L}\'’.-]+(?:\s+[\p{Lu}][\p{L}\'’.-]+){0,3})/u', $text, $m ) ) {
		foreach ( $m[1] as $n ) {
			$out[] = trim( $n );
		}
	}
	return $out;
}

/**
 * Photographer from existing caption credits, or '' when not cleanly derivable.
 * One name renders as-is, two join with "and", three or more are ambiguous and
 * the line is omitted rather than guessed.
 */
function vw_ah_photographer( WP_Post $post ): string {
	$names = [];
	if ( preg_match_all( '#<figcaption[^>]*>(.*?)</figcaption>#is', $post->post_content, $m ) ) {
		foreach ( $m[1] as $cap ) {
			$names = array_merge( $names, vw_ah_extract_credit( wp_strip_all_tags( $cap ) ) );
		}
	}
	if ( ! $names && $post->post_excerpt ) {
		$names = vw_ah_extract_credit( wp_strip_all_tags( $post->post_excerpt ) );
	}

	$uniq = [];
	foreach ( $names as $n ) {
		$uniq[ mb_strtolower( $n ) ] = $n;
	}
	$uniq = array_values( $uniq );

	if ( 1 === count( $uniq ) ) return $uniq[0];
	if ( 2 === count( $uniq ) ) return $uniq[0] . ' and ' . $uniq[1];
	return '';
}

function vw_ah_word_count( WP_Post $post ): int {
	return str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
}

/** Images that will actually render, i.e. after dead-media suppression. */
function vw_ah_photo_count( WP_Post $post ): int {
	$html = function_exists( 'vw_dead_media_filter' )
		? vw_dead_media_filter( $post->post_content )
		: $post->post_content;
	return (int) preg_match_all( '#<img\b#i', $html );
}

/** Photo-led when pictures dominate prose, not merely when pictures exist. */
function vw_ah_is_photo_led( WP_Post $post ): bool {
	$imgs = vw_ah_photo_count( $post );
	if ( $imgs < 5 ) return false;
	return vw_ah_word_count( $post ) < $imgs * 50;
}

function vw_ah_read_time( WP_Post $post ): int {
	return max( 1, (int) round( vw_ah_word_count( $post ) / 230 ) );
}

/**
 * Primary category name + mark modifier. Uncategorized carries no editorial
 * meaning, so it yields nothing and the kicker is skipped entirely.
 *
 * Hoisted with the rest: the homepage kickers need the same slug-to-mark map
 * the article header uses, and two copies would drift.
 */
function vw_ah_section( int $post_id ): array {
	$marks = [
		'a-la-music'          => 'music',
		'live-music-reviews'  => 'music',
		'album-reviews'       => 'music',
		'music-interviews'    => 'music',
		'music-videos'        => 'music',
		'music-editorials'    => 'music',
		'photography'         => 'photo',
		'food-drink'          => 'food',
		'out-n-about'         => 'outabout',
		'political-megaphone' => 'political',
		'book-reviews'        => 'books',
	];
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) return [ '', '' ];

	foreach ( $terms as $t ) {
		if ( isset( $marks[ $t->slug ] ) ) return [ $t->name, $marks[ $t->slug ] ];
	}
	foreach ( $terms as $t ) {
		if ( 'uncategorized' !== $t->slug ) return [ $t->name, '' ];
	}
	return [ '', '' ];
}

/**
 * Credits in two groups: the byline lines, then a single quieter meta line.
 * Hierarchy comes from weight and colour, not from a second font.
 */
function vw_ah_credits( WP_Post $post ): array {
	$bylines   = [];
	$author    = get_the_author_meta( 'display_name', (int) $post->post_author );
	$shooter   = vw_ah_photographer( $post );
	$photo_led = vw_ah_is_photo_led( $post );

	if ( $author )  $bylines[] = [ 'By', $author ];
	if ( $shooter ) $bylines[] = [ 'Photos', $shooter ];

	$meta = get_the_date( 'F j, Y', $post ) . ' · ' . ( $photo_led
		? sprintf( '%d photos', vw_ah_photo_count( $post ) )
		: sprintf( '%d min read', vw_ah_read_time( $post ) ) );

	return [ 'bylines' => $bylines, 'meta' => $meta ];
}

/**
 * Credits as one inline string: "By Name · Photos Shooter · Jan 1, 2020 · 6 min read".
 * Used by homepage cards, which have no room for the header's stacked treatment.
 *
 * The junk-author suppression that every card already applies is filtered in
 * HERE rather than inside vw_ah_credits(), deliberately: vw_ah_credits() is
 * hoisted verbatim so the approved article-header preview stays byte-identical.
 * The header does still print "By Photography" on the 64 desk-label posts —
 * that is a pre-existing inconsistency, logged for the Round 8 rollout, not
 * something to fix silently inside an approved design.
 */
function vw_credits_inline( WP_Post $post ): string {
	$credits = vw_ah_credits( $post );

	$parts = [];
	foreach ( $credits['bylines'] as list( $label, $value ) ) {
		if ( 'By' === $label && function_exists( 'vw_is_junk_author' ) && vw_is_junk_author( $value ) ) {
			continue;
		}
		$parts[] = esc_html( $label ) . ' <strong>'
			. ( 'By' === $label ? vw_author_html( $post ) : esc_html( $value ) )
			. '</strong>';
	}
	$parts[] = esc_html( $credits['meta'] );

	return implode( ' · ', $parts );
}
