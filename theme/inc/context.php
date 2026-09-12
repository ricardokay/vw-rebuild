<?php
/**
 * Where am I? — section derivation for the current view.
 *
 * The category tree is real and shallow: the five music categories are children
 * of a-la-music, netflix-films is a child of must-see-films, everything else is
 * top level. So a post in "live music reviews" belongs to A La Music, and the
 * nav highlights A La Music even though no post carries that term directly.
 *
 * This also fed a breadcrumb trail, removed in the cutover round — Ricardo's
 * call, the trail restated what the red nav item already says. The derivation
 * stays because the nav highlight is its remaining consumer, and because it is
 * the right place for any later feature that needs to ask the same question.
 */

defined( 'ABSPATH' ) || exit;

/** Slugs that appear in the masthead / nav, in display order. */
function vw_nav_slugs(): array {
	return array_keys( VW_MASTHEAD_SECTIONS );
}

/**
 * A term plus its ancestors, outermost first: [ grandparent, parent, term ].
 */
function vw_term_trail( WP_Term $term ): array {
	$trail = [ $term ];
	$seen  = [ (int) $term->term_id => true ];

	$parent = (int) $term->parent;
	while ( $parent && ! isset( $seen[ $parent ] ) ) {
		$seen[ $parent ] = true;
		$up = get_term( $parent, 'category' );
		if ( ! $up instanceof WP_Term ) {
			break;
		}
		array_unshift( $trail, $up );
		$parent = (int) $up->parent;
	}

	return $trail;
}

/**
 * The category a post most belongs to.
 *
 * Prefers the DEEPEST term whose ancestry reaches a nav section, so a post filed
 * in both "A La Music" and "live music reviews" resolves to the subcategory.
 * Uncategorized is never primary — it is a
 * filing accident on 1,835 posts, not an editorial statement.
 */
function vw_primary_term( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}

	$nav      = vw_nav_slugs();
	$best     = null;
	$best_len = -1;
	$fallback = null;

	foreach ( $terms as $term ) {
		if ( 'uncategorized' === $term->slug ) {
			continue;
		}
		if ( null === $fallback ) {
			$fallback = $term;
		}

		$trail = vw_term_trail( $term );
		if ( ! in_array( $trail[0]->slug, $nav, true ) ) {
			continue;
		}
		if ( count( $trail ) > $best_len ) {
			$best     = $term;
			$best_len = count( $trail );
		}
	}

	return $best ?: $fallback;
}

/**
 * The nav slug to highlight on the current view, or '' for none.
 *
 * Category archives resolve through the ancestor chain too, so
 * /category/live-music-reviews/ highlights A La Music rather than nothing.
 */
function vw_nav_active_slug(): string {
	$term = null;

	if ( is_category() ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			$term = $queried;
		}
	} elseif ( is_singular( 'post' ) ) {
		$term = vw_primary_term( (int) get_queried_object_id() );
	}

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$root = vw_term_trail( $term )[0]->slug;
	return in_array( $root, vw_nav_slugs(), true ) ? $root : '';
}

/**
 * Section mark modifier for a nav slug, matching the article header's map.
 */
function vw_section_mark( string $slug ): string {
	$marks = [
		'a-la-music'          => 'music',
		'photography'         => 'photo',
		'food-drink'          => 'food',
		'out-n-about'         => 'outabout',
		'political-megaphone' => 'political',
		'book-reviews'        => 'books',
	];
	return $marks[ $slug ] ?? '';
}


/* ── Author links ──────────────────────────────────────────────────────── */

/**
 * A post's author as a link, or plain text when the "author" is a desk label.
 *
 * 64 posts are filed under names like "Photography", "Contests" and "News Feed".
 * Those are categories that ended up in the author column during the import, not
 * people, and linking them would send a reader to an author archive for a person
 * who does not exist. vw_is_junk_author() is the same test the cards already use
 * to suppress the name.
 */
function vw_author_html( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	$id   = (int) $post->post_author;
	$name = (string) get_the_author_meta( 'display_name', $id );

	if ( '' === $name || vw_is_junk_author( $name ) ) {
		return esc_html( $name );
	}

	$url = get_author_posts_url( $id );
	if ( ! $url ) {
		return esc_html( $name );
	}

	return '<a class="vw-author-link" href="' . esc_url( $url ) . '" rel="author">' . esc_html( $name ) . '</a>';
}
