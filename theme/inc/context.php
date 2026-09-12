<?php
/**
 * Where am I? — one derivation, several consumers.
 *
 * The active nav highlight and the breadcrumb trail are the same question asked
 * twice: which section does this view belong to, and how did we get here. They
 * are derived once here so they can never disagree — a red nav item pointing at
 * one section while the breadcrumb names another is worse than neither.
 *
 * The category tree is real and shallow: the five music categories are children
 * of a-la-music, netflix-films is a child of must-see-films, everything else is
 * top level. So a post in "live music reviews" belongs to A La Music, and the
 * nav highlights A La Music even though no post carries that term directly.
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
 * in both "A La Music" and "live music reviews" resolves to the subcategory and
 * the breadcrumb can show both levels. Uncategorized is never primary — it is a
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
 * Breadcrumb levels for the current view, or [] when there is no trail to show.
 *
 * Renders on single posts and on SUBcategory archives only. A top-level section
 * front is already the thing the nav is pointing at; a crumb there would just
 * restate the page's own title back to it.
 */
function vw_breadcrumb_trail(): array {
	$term = null;

	if ( is_singular( 'post' ) ) {
		$term = vw_primary_term( (int) get_queried_object_id() );
	} elseif ( is_category() ) {
		$queried = get_queried_object();
		// Sub-categories only: a term with no parent is a top-level front.
		if ( $queried instanceof WP_Term && $queried->parent ) {
			$term = $queried;
		}
	}

	if ( ! $term instanceof WP_Term ) {
		return [];
	}

	$out = [];
	foreach ( vw_term_trail( $term ) as $level ) {
		$out[] = [
			'name' => $level->name,
			'url'  => (string) get_category_link( $level->term_id ),
			'slug' => $level->slug,
		];
	}
	return $out;
}

/** Section mark modifier for a nav slug, matching the article header's map. */
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

/**
 * The trail as markup.
 *
 * Deliberately the same element the article header's kicker already was — same
 * mark, same size, same position — with the levels linked and separated. It
 * reads as the kicker having grown a second level rather than as a new band of
 * furniture above the headline.
 */
function vw_breadcrumb_html( string $class = 'vw-ah__kicker', string $mark_class = 'vw-ah__mark' ): string {
	$trail = vw_breadcrumb_trail();
	if ( ! $trail ) {
		return '';
	}

	$mark = vw_section_mark( $trail[0]['slug'] );

	$out = '<nav class="' . esc_attr( $class ) . ' vw-crumbs" aria-label="Breadcrumb">';
	if ( $mark ) {
		$out .= '<span class="' . esc_attr( $mark_class ) . ' ' . esc_attr( $mark_class . '--' . $mark ) . '"></span>';
	}

	$last = count( $trail ) - 1;
	foreach ( $trail as $i => $level ) {
		if ( $i > 0 ) {
			$out .= '<span class="vw-crumbs__sep" aria-hidden="true">&rsaquo;</span>';
		}
		$out .= '<a class="vw-crumbs__link" href="' . esc_url( $level['url'] ) . '"'
			. ( $i === $last ? ' aria-current="page"' : '' ) . '>'
			. esc_html( $level['name'] ) . '</a>';
	}

	return $out . '</nav>';
}

/**
 * BreadcrumbList JSON-LD, emitted wherever the trail renders.
 *
 * Seed for the Round 6 metadata work: this is the only structured data on the
 * site so far, because Newspack ships none and delegates to Yoast.
 */
add_action( 'wp_head', 'vw_breadcrumb_jsonld', 20 );
function vw_breadcrumb_jsonld(): void {
	$trail = vw_breadcrumb_trail();
	if ( ! $trail ) {
		return;
	}

	// Singles carry the trail inside the article header, which is still behind
	// its preview flag; emitting the markup's structured data while the markup
	// is not on the page would describe a breadcrumb no reader can see.
	if ( is_singular( 'post' ) && ! ( function_exists( 'vw_ah_active' ) && vw_ah_active() ) ) {
		return;
	}

	$items = [];
	foreach ( $trail as $i => $level ) {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $level['name'],
			'item'     => $level['url'],
		];
	}

	$data = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	];

	printf(
		"<script type=\"application/ld+json\">%s</script>\n",
		wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}

/**
 * Breadcrumb on sub-category archives, which are live.
 *
 * newspack_theme_below_archive_title is the parent theme's own hook, so this
 * needs no template fork and survives Newspack updates.
 */
add_action( 'newspack_theme_below_archive_title', 'vw_breadcrumb_archive' );
function vw_breadcrumb_archive(): void {
	if ( ! is_category() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in vw_breadcrumb_html().
	echo vw_breadcrumb_html( 'vw-archive-crumbs', 'vw-section-mark' );
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
