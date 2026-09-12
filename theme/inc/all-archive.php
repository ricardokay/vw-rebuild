<?php
/**
 * /archive/ — a paginated listing of every post, across every section.
 *
 * The archive closer's "Browse the Archive" call to action had nowhere honest to
 * point: it linked to /category/a-la-music/, which is one section out of six, on
 * a card whose whole claim is "20 years, 3,373 stories". WordPress offers no
 * all-posts route on this site — show_on_front is a page and page_for_posts is
 * 0, so there is no blog index — and the only multi-section archives that exist
 * are the per-year date archives.
 *
 * Implemented on `parse_request` rather than with a rewrite rule, deliberately:
 * a rewrite rule only takes effect once the rewrite_rules option is flushed, and
 * this round is scoped to the theme with no database writes. Matching the path
 * here needs no stored rules, works the moment the file exists, and disappears
 * the moment it is removed. The trade is that the match happens in PHP on every
 * request instead of in the rules table; it is one preg_match against a path
 * that has already been parsed.
 *
 * The listing deliberately reuses the parent theme's archive.php and the
 * existing .archive styling — no new template, no new CSS. This is the honest
 * functional version; the designed "browse the archive" experience is a
 * post-launch round.
 */

defined( 'ABSPATH' ) || exit;

const VW_ALL_ARCHIVE_SLUG = 'archive';

/** True once the request has been recognised as the all-posts archive. */
function vw_is_all_archive(): bool {
	return ! empty( $GLOBALS['vw_all_archive'] );
}

/**
 * Claim /archive/ and /archive/page/N/ before WordPress resolves anything else.
 *
 * A page or category that ever takes the slug "archive" would collide; nothing
 * does today, and the check below keeps it that way by refusing to claim the
 * path if a real post or page already lives there.
 */
add_action( 'parse_request', 'vw_all_archive_parse_request' );
function vw_all_archive_parse_request( $wp ): void {
	$path = trim( (string) $wp->request, '/' );

	if ( ! preg_match( '#^' . VW_ALL_ARCHIVE_SLUG . '(?:/page/([0-9]+))?$#i', $path, $m ) ) {
		return;
	}

	// Never shadow real content that happens to use this slug.
	if ( get_page_by_path( VW_ALL_ARCHIVE_SLUG ) ) {
		return;
	}

	$paged = isset( $m[1] ) ? (int) $m[1] : 0;
	if ( ! $paged && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = absint( wp_unslash( $_GET['paged'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$wp->query_vars = [
		'post_type'   => 'post',
		'post_status' => 'publish',
	];
	if ( $paged > 1 ) {
		$wp->query_vars['paged'] = $paged;
	}

	$GLOBALS['vw_all_archive'] = true;
}

/** Render through the parent theme's archive template. */
add_filter( 'template_include', 'vw_all_archive_template', 20 );
function vw_all_archive_template( $template ) {
	return vw_is_all_archive() ? get_template_directory() . '/archive.php' : $template;
}

/**
 * Borrow the archive body class so every existing .archive rule applies —
 * the restructured row grid, the pagination, the palette, the serif title.
 */
add_filter( 'body_class', 'vw_all_archive_body_class' );
function vw_all_archive_body_class( $classes ) {
	if ( vw_is_all_archive() ) {
		$classes[] = 'archive';
		$classes[] = 'vw-all-archive';
	}
	return $classes;
}

/** A title, since there is no queried object to derive one from. */
// Priority 99: Newspack filters this too, and re-adds its own prefix at 10.
add_filter( 'get_the_archive_title', 'vw_all_archive_title', 99 );
function vw_all_archive_title( $title ) {
	return vw_is_all_archive() ? 'The Archive' : $title;
}

add_filter( 'get_the_archive_description', 'vw_all_archive_description' );
function vw_all_archive_description( $desc ) {
	if ( ! vw_is_all_archive() ) {
		return $desc;
	}
	return sprintf(
		'<p>Every published story, newest first — %s in all.</p>',
		esc_html( number_format_i18n( (int) wp_count_posts( 'post' )->publish ) )
	);
}

add_filter( 'document_title_parts', 'vw_all_archive_document_title' );
function vw_all_archive_document_title( $parts ) {
	if ( vw_is_all_archive() ) {
		$parts['title'] = 'The Archive';
	}
	return $parts;
}

/**
 * Pagination links as /archive/page/N/ rather than /archive/?paged=N.
 *
 * the_posts_pagination() builds its links with add_query_arg() because there is
 * no rewrite rule telling it a prettier shape exists. Both forms are accepted by
 * the parser above; this only decides which one a reader sees and shares.
 */
add_filter( 'paginate_links', 'vw_all_archive_paginate_links' );
function vw_all_archive_paginate_links( $link ) {
	if ( ! vw_is_all_archive() ) {
		return $link;
	}

	$base = trailingslashit( home_url( VW_ALL_ARCHIVE_SLUG ) );

	if ( preg_match( '#[?&]paged=([0-9]+)#', $link, $m ) ) {
		$n = (int) $m[1];
		return esc_url( $n > 1 ? $base . 'page/' . $n . '/' : $base );
	}

	return $link;
}

/** The canonical URL for the all-posts archive. */
function vw_all_archive_url(): string {
	return trailingslashit( home_url( VW_ALL_ARCHIVE_SLUG ) );
}
