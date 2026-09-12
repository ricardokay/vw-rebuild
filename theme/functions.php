<?php

require_once get_stylesheet_directory() . '/inc/dead-media.php';
add_filter( 'the_content', 'vw_dead_media_filter', 20 );

require_once get_stylesheet_directory() . '/inc/article-header.php';
require_once get_stylesheet_directory() . '/inc/masthead.php';

/**
 * Curation: storage, capability, resolver, and the admin screen.
 *
 * Both load unconditionally. Gating the admin file on is_admin() looks tidier
 * and is wrong: is_admin() is false during a REST request, so the search
 * endpoint's rest_api_init registration would never run and the picker's
 * autocomplete would 404. The file registers hooks only — admin_menu,
 * admin_enqueue_scripts and admin_post_* never fire on the front end.
 */
require_once get_stylesheet_directory() . '/inc/curation.php';
require_once get_stylesheet_directory() . '/inc/curation-admin.php';

/**
 * Force every single post to the theme's "One column wide" layout.
 *
 * Filters the meta READ rather than swapping the template file: Newspack keys
 * both its body class (post-template-*) and newspack_is_default_template() off
 * this meta value, and the content column's width comes from that body class —
 * measured, a template_include swap loads the right file and still leaves the
 * wrong class, fixing nothing. Short-circuiting here happens before the meta
 * table is read, so it beats both states in the archive (2,215 posts with no
 * value, 1,158 set to "default"). Nothing is written; the database is
 * untouched. Revert by deleting this filter and its function.
 */
add_filter( 'get_post_metadata', 'vw_force_single_column_template', 10, 4 );
function vw_force_single_column_template( $value, $object_id, $meta_key, $single ) {
	if ( '_wp_page_template' !== $meta_key ) return $value;

	// Never in admin/REST/AJAX: the editor would display the forced value and
	// persist it to the database on the next save.
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return $value;

	if ( ! is_singular( 'post' ) || (int) $object_id !== get_queried_object_id() ) return $value;

	return $single ? 'single-wide.php' : array( 'single-wide.php' );
}

/**
 * Drop the parent theme's "Powered by Newspack" footer credit.
 *
 * It is hard-coded at newspack-theme/footer.php:53-55 with no conditional and
 * no filter of its own, so the translation call is the only seam. Emptying the
 * string leaves an empty <a class="imprint">, which gallery.css hides. Editing
 * footer.php in the child theme would fork a 70-line parent file that drifts on
 * every Newspack update.
 */
add_filter( 'gettext_newspack-theme', 'vw_drop_newspack_credit', 10, 2 );
function vw_drop_newspack_credit( $translated, $text ) {
	return 'Powered by Newspack' === $text ? '' : $translated;
}

add_action( 'wp_enqueue_scripts', 'vw_enqueue_styles' );
function vw_enqueue_styles() {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'newspack-theme-style',
		get_template_directory_uri() . '/style.css'
	);

	wp_enqueue_style(
		'vw-palette',
		$uri . '/palette.css',
		[],
		filemtime( $dir . '/palette.css' )
	);

	wp_enqueue_style(
		'vw-fonts',
		$uri . '/assets/css/fonts.css',
		[],
		filemtime( $dir . '/assets/css/fonts.css' )
	);

	wp_enqueue_style(
		'vw-styles',
		$uri . '/assets/css/section-landing.css',
		[ 'vw-palette', 'vw-fonts' ],
		filemtime( $dir . '/assets/css/section-landing.css' )
	);

	// Gallery grid caption hide + lightbox credit styling.
	wp_enqueue_style(
		'vw-gallery',
		$uri . '/assets/css/gallery.css',
		[ 'vw-palette' ],
		filemtime( $dir . '/assets/css/gallery.css' )
	);

	// Lightbox caption enhancement (mirrors figcaption credit into the native lightbox).
	wp_enqueue_script(
		'vw-lightbox-caption',
		$uri . '/assets/js/vw-lightbox-caption.js',
		[],
		filemtime( $dir . '/assets/js/vw-lightbox-caption.js' ),
		true
	);

	// Head, not footer: a footer-loaded listener would miss image errors that
	// fire while the document is still parsing.
	wp_enqueue_script(
		'vw-dead-media',
		$uri . '/assets/js/vw-dead-media.js',
		[],
		filemtime( $dir . '/assets/js/vw-dead-media.js' ),
		false
	);

	if ( is_front_page() ) {
		wp_enqueue_style(
			'vw-homepage',
			$uri . '/assets/css/homepage.css',
			[ 'vw-styles' ],
			filemtime( $dir . '/assets/css/homepage.css' )
		);
	}

	// Archive inheritance: the Browse-all destination and every non-curated
	// category adopt the design system instead of Newspack's blue defaults.
	if ( is_archive() || is_search() ) {
		wp_enqueue_style(
			'vw-archive',
			$uri . '/assets/css/archive.css',
			[ 'vw-styles' ],
			filemtime( $dir . '/assets/css/archive.css' )
		);
	}

	// Sitewide-masthead preview: reuses homepage-v2.css so the preview shows the
	// real masthead styling rather than a lookalike.
	if ( function_exists( 'vw_masthead_active' ) && vw_masthead_active() ) {
		wp_enqueue_style(
			'vw-homepage-v2',
			$uri . '/assets/css/homepage-v2.css',
			[ 'vw-palette', 'vw-fonts' ],
			filemtime( $dir . '/assets/css/homepage-v2.css' )
		);
		wp_enqueue_style(
			'vw-masthead-preview',
			$uri . '/assets/css/masthead-preview.css',
			[ 'vw-homepage-v2' ],
			filemtime( $dir . '/assets/css/masthead-preview.css' )
		);
	}

	// Article-header preview: only when the flag is set, so live singles are clean.
	if ( function_exists( 'vw_ah_active' ) && vw_ah_active() ) {
		wp_enqueue_style(
			'vw-article-header',
			$uri . '/assets/css/article-header.css',
			[ 'vw-palette', 'vw-fonts' ],
			filemtime( $dir . '/assets/css/article-header.css' )
		);
	}

	if ( is_page_template( 'page-templates/vw-homepage-preview.php' ) ) {
		wp_enqueue_style(
			'vw-homepage-v2',
			$uri . '/assets/css/homepage-v2.css',
			[ 'vw-palette', 'vw-fonts' ],
			filemtime( $dir . '/assets/css/homepage-v2.css' )
		);
	}
}

/**
 * Excerpt outside the loop: manual excerpt if set, else trimmed content.
 */
function vw_get_excerpt( WP_Post $post, int $words = 25 ): string {
	$manual = trim( wp_strip_all_tags( $post->post_excerpt ) );
	if ( $manual ) return $manual;
	$content = vw_strip_scrape_chrome( strip_shortcodes( wp_strip_all_tags( $post->post_content ) ) );
	return wp_trim_words( $content, $words, '…' );
}

/**
 * Image quality tier for a post's featured image.
 *
 * Tier 1 (≥1024px) — full-bleed / hero treatment
 * Tier 2 (480–1023px) — standard card image
 * Tier 3 (<480px) — small thumbnail, left-aligned
 * Tier 0 — no image, or file missing on disk (dead Facebook import)
 */
function vw_image_tier( int $post_id ): int {
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( ! $thumb_id ) return 0;

	$path = get_attached_file( $thumb_id );
	if ( ! $path || ! file_exists( $path ) ) return 0;

	$src = wp_get_attachment_image_src( $thumb_id, 'full' );
	if ( ! $src ) return 0;

	$width = (int) $src[1];
	if ( $width >= 1024 ) return 1;
	if ( $width >= 480  ) return 2;
	return 3;
}

/**
 * Returns the display name of the first category that matches a preferred list.
 * Falls back to the first assigned category.
 *
 * On a section front the kicker would just repeat the section you are already
 * looking at, so it returns '' there and every call site (all of which already
 * guard with `if ( $cat )`) drops the kicker. Cross-section cards — the
 * homepage, or a card from another section — keep theirs.
 */
function vw_primary_cat_name( int $post_id, array $preferred_cat_ids ): string {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) return '';

	$name = '';
	foreach ( $preferred_cat_ids as $cid ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $cid ) { $name = $t->name; break 2; }
		}
	}
	if ( '' === $name ) $name = $terms[0]->name ?? '';

	if ( is_category() ) {
		$viewed = get_queried_object();
		if ( $viewed instanceof WP_Term && strcasecmp( $viewed->name, $name ) === 0 ) return '';
	}
	return $name;
}

/**
 * Author names that are categories or desk labels rather than people —
 * "Photography", "Contests", "News Feed". Display-layer only: the stored author
 * is untouched, the byline is simply not printed. The underlying data is an
 * editor-backlog item, not a rendering bug.
 */
function vw_is_junk_author( string $name ): bool {
	$name = trim( $name );
	if ( '' === $name ) return true;

	static $cat_names = null;
	if ( null === $cat_names ) {
		$cat_names = [];
		foreach ( get_categories( [ 'hide_empty' => false ] ) as $c ) {
			$cat_names[] = mb_strtolower( $c->name );
		}
	}
	$extra = [ 'news feed', 'music contributing editor', 'energy forum' ];

	$key = mb_strtolower( $name );
	return in_array( $key, $cat_names, true ) || in_array( $key, $extra, true );
}

/**
 * "By <strong>Name</strong>" — or the post date when the author is a category
 * or desk label, so a card never renders an empty meta line. Suppressing the
 * name is deliberate; suppressing the whole line was not.
 */
function vw_byline_inner( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) return '';

	$name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
	if ( vw_is_junk_author( $name ) ) {
		return '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">'
			. esc_html( get_the_date( 'M j, Y', $post ) ) . '</time>';
	}
	return 'By <strong>' . esc_html( $name ) . '</strong>';
}

/**
 * Scrape chrome that ended up inside post_content and surfaces through the
 * auto-excerpt — runs of "Comment", Disqus/Facebook widget leftovers, stray
 * "Share this" lines. 3,372 of 3,373 published posts have no manual excerpt, so
 * every dek on the site is generated from this content and inherits the noise.
 * Display-layer only: post_content is not touched.
 */
function vw_strip_scrape_chrome( string $text ): string {
	// wp_strip_all_tags() leaves entities as literal text, so "&nbsp;" arrives as
	// six characters that no whitespace class matches. Decode before cleaning.
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/(?:\bComments?\b[\s\x{00A0}·|,–—-]*){2,}/iu', ' ', $text );
	$text = preg_replace( '/\b(?:Share this|Like this|Loading\.\.\.|Related Posts?|Tweet|Pin It)\b[\s\x{00A0}:·|,-]*/iu', ' ', $text );
	$text = preg_replace( '/^[\s\x{00A0}·|,–—-]+/u', '', (string) $text );
	return trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $text ) );
}

/**
 * Older halves of duplicate-title pairs within a set of categories.
 *
 * The archive holds pairs of distinct posts with identical titles — a clean-slug
 * copy and a Wayback-recovered copy. Deleting one is an editorial decision that
 * has not been made, so the fronts simply never query the older copy: the ids
 * seed $used_ids, and every zone already excludes those.
 *
 * An earlier attempt filtered `the_posts` request-wide, which starved the front
 * to a single story — the fronts run candidate scans (30 posts, one picked), and
 * that filter marked all 30 titles as spent. Excluding at the source avoids it.
 */
function vw_older_duplicate_ids( array $cat_ids ): array {
	if ( ! $cat_ids ) return [];

	$q = new WP_Query( [
		'category__in'           => $cat_ids,
		'posts_per_page'         => -1,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );

	$seen = [];
	$older = [];
	foreach ( $q->posts as $p ) {
		$key = mb_strtolower( trim( $p->post_title ) );
		if ( '' === $key ) continue;
		if ( isset( $seen[ $key ] ) ) { $older[] = (int) $p->ID; continue; }
		$seen[ $key ] = true;
	}
	wp_reset_postdata();
	return $older;
}

/**
 * Byline plus date for the lead card. The date is appended only when the byline
 * is a real name — vw_byline_inner() already falls back to the date for
 * category/desk-label authors, and appending it again printed it twice.
 */
function vw_meta_line( $post ): string {
	$post = get_post( $post );
	if ( ! $post ) return '';

	$inner = vw_byline_inner( $post );
	if ( false === strpos( $inner, '<strong>' ) ) return $inner;

	return $inner . '&nbsp;·&nbsp;<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">'
		. esc_html( get_the_date( 'M j, Y', $post ) ) . '</time>';
}


/**
 * Closing "Browse all N …" link for a section front. Prints nothing when the
 * section holds no more than what the front already showed — small sections
 * just show what they have.
 */
function vw_section_browse_all( array $cat_ids, string $label, int $shown ): void {
	$q = new WP_Query( [
		'category__in'           => $cat_ids,
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );
	$total = (int) $q->found_posts;
	wp_reset_postdata();

	if ( $total <= $shown ) return;

	$term = get_queried_object();
	$base = ( $term instanceof WP_Term ) ? get_category_link( $term->term_id ) : home_url( '/' );
	?>
	<div class="vw-module vw-section-more">
		<div class="vw-module__inner">
			<a class="vw-section-more__link" href="<?php echo esc_url( trailingslashit( $base ) . 'page/2/' ); ?>">
				<?php printf( 'Browse all %s %s stories', esc_html( number_format_i18n( $total ) ), esc_html( $label ) ); ?> &rarr;
			</a>
		</div>
	</div>
	<?php
}

add_action( 'after_setup_theme', 'vw_theme_support' );
function vw_theme_support() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	] );
}
