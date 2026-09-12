<?php

require_once get_stylesheet_directory() . '/inc/dead-media.php';
add_filter( 'the_content', 'vw_dead_media_filter', 20 );

// Credit derivation is shared by the article header and the homepage, so it
// loads first and unconditionally — it is no longer preview-only code.
require_once get_stylesheet_directory() . '/inc/credits.php';

require_once get_stylesheet_directory() . '/inc/article-header.php';
require_once get_stylesheet_directory() . '/inc/masthead.php';

// Primary-category derivation (the active nav's section highlight) plus
// author-link rendering. Loads after masthead.php: it reads VW_MASTHEAD_SECTIONS.
require_once get_stylesheet_directory() . '/inc/context.php';

// Comments off sitewide, display layer only — see inc/comments.php.
require_once get_stylesheet_directory() . '/inc/comments.php';

// /archive/ — the all-posts listing the archive closer points at.
require_once get_stylesheet_directory() . '/inc/all-archive.php';

/**
 * Curation: storage, capability, resolver, and the admin screen.
 *
 * Both load unconditionally. Gating the admin file on is_admin() looks tidier
 * and is wrong: is_admin() is false during a REST request, so the search
 * endpoint's rest_api_init registration would never run and the picker's
 * autocomplete would 404. The file registers hooks only — admin_menu,
 * admin_enqueue_scripts and admin_post_* never fire on the front end.
 */
require_once get_stylesheet_directory() . '/inc/chrome-settings.php';
require_once get_stylesheet_directory() . '/inc/templates.php';
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
/**
 * Stop Newspack rendering the featured image on single posts.
 *
 * The adaptive header renders the image itself, in the shape the image's own
 * dimensions call for. Newspack's single.php branches on
 * newspack_featured_image_position() and, for the 'large'/'behind'/'beside'/
 * 'above' values, loads template-parts/post/large-featured-image.php — which
 * would paint the same photograph above our header, twice on the page.
 *
 * That function has no filter of its own, but it reads a per-post meta value
 * first and returns anything it finds. Short-circuiting the meta READ with a
 * value outside its known set leaves the position unrecognised, so single.php
 * takes its plain branch and the 'small' injection never fires either. Same
 * technique, and the same reasoning, as the single-wide template filter below:
 * nothing is written, and the admin is excluded so the editor still shows and
 * saves the real value.
 */
add_filter( 'get_post_metadata', 'vw_ah_suppress_newspack_featured_image', 10, 4 );
function vw_ah_suppress_newspack_featured_image( $value, $object_id, $meta_key, $single ) {
	if ( 'newspack_featured_image_position' !== $meta_key ) {
		return $value;
	}
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $value;
	}
	if ( ! is_singular( 'post' ) || (int) $object_id !== get_queried_object_id() ) {
		return $value;
	}

	return $single ? 'vw-header' : [ 'vw-header' ];
}

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

	// Front page: homepage-v2, the approved design. This replaced the old
	// homepage.css, which styled section-parts/homepage.php — a file nothing had
	// included since the v2 rebuild. Both retired in Round 1 session B. Until
	// front-page.php lands in session C the front page is still Elementor page 9,
	// so this enqueue is currently loading styles for markup that page does not
	// have; harmless, and correct the moment the cutover happens.
	/*
	 * homepage-v2.css is sitewide chrome now, not homepage-only: it carries the
	 * masthead, which header.php renders on every surface. The homepage zone
	 * rules inside it are inert anywhere the zone markup is absent.
	 */
	vw_enqueue_homepage_v2( $dir, $uri );

	// Archive inheritance: the Browse-all destination and every non-curated
	// category adopt the design system instead of Newspack's blue defaults.
	// vw_is_all_archive() is the /archive/ route, which borrows the .archive body
	// class and therefore needs the stylesheet that styles it.
	if ( is_archive() || is_search() || ( function_exists( 'vw_is_all_archive' ) && vw_is_all_archive() ) ) {
		wp_enqueue_style(
			'vw-archive',
			$uri . '/assets/css/archive.css',
			[ 'vw-styles' ],
			filemtime( $dir . '/assets/css/archive.css' )
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

}

/**
 * Body class for every surface that renders the homepage-v2 part.
 *
 * homepage-v2.css carries three rules that must apply wherever that markup
 * renders — hide the old .vw-nav, zero Newspack's #content margin, hide
 * Newspack's #colophon. They used to be scoped to the preview template's own
 * body class, so they would have silently stopped applying the moment
 * front-page.php took over. One class, both surfaces.
 */
/**
 * Drop the dead Elementor page-template class from the front page.
 *
 * Page 9 still carries _wp_page_template = elementor_header_footer from the old
 * build, and it is still the queried object for the front page even though
 * front-page.php renders instead of it — so WordPress prints
 * page-template-elementor_header_footer in the body class. Elementor is
 * deactivated and none of its assets load; this is a leftover label with
 * nothing behind it, and it would otherwise be the last visible trace of the
 * old front page in the markup.
 *
 * Display layer only, no database write — the meta is untouched and goes with
 * page 9 whenever that cleanup is gated.
 */
add_filter( 'body_class', 'vw_drop_dead_elementor_class', 20 );
function vw_drop_dead_elementor_class( $classes ) {
	if ( ! is_front_page() ) {
		return $classes;
	}
	return array_values( array_filter(
		$classes,
		static function ( $c ) {
			return false === strpos( $c, 'elementor' );
		}
	) );
}

add_filter( 'body_class', 'vw_homepage_v2_body_class' );
function vw_homepage_v2_body_class( $classes ) {
	if ( is_front_page() || is_page_template( 'page-templates/vw-homepage-preview.php' ) ) {
		$classes[] = 'vw-home-v2';
	}
	return $classes;
}

/**
 * Homepage v2 stylesheets, in order.
 *
 * homepage-v2-data.css must load after homepage-v2.css and depends on it: it
 * carries the phase-2 additions — tier-0 text variants, the tri columns' second
 * compact slot, and the box fixes that wrapping images in real permalinks made
 * necessary — and several of its rules win only on source order.
 *
 * One function rather than two copies because the preview template and the
 * front page must never diverge; when front-page.php lands in session C it
 * calls the same thing.
 */
function vw_enqueue_homepage_v2( string $dir, string $uri ): void {
	wp_enqueue_style(
		'vw-homepage-v2',
		$uri . '/assets/css/homepage-v2.css',
		[ 'vw-palette', 'vw-fonts' ],
		filemtime( $dir . '/assets/css/homepage-v2.css' )
	);
	wp_enqueue_style(
		'vw-homepage-v2-data',
		$uri . '/assets/css/homepage-v2-data.css',
		[ 'vw-homepage-v2' ],
		filemtime( $dir . '/assets/css/homepage-v2-data.css' )
	);
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
	$cache = vw_image_tier_cache();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$tier = vw_image_tier_compute( $post_id );
	vw_image_tier_seed( [ $post_id => $tier ] );
	return $tier;
}

/**
 * Request-level tier cache.
 *
 * The tier is three uncached meta reads per post and the resolver asks for it
 * repeatedly while walking candidates. vw_curation_prime() fills this in one
 * query for a whole candidate list; single lookups fall through to
 * vw_image_tier_compute() and memoise themselves.
 */
function &vw_image_tier_cache(): array {
	static $cache = [];
	return $cache;
}

function vw_image_tier_seed( array $tiers ): void {
	$cache = &vw_image_tier_cache();
	foreach ( $tiers as $id => $tier ) {
		$cache[ (int) $id ] = (int) $tier;
	}
}

function vw_image_tier_flush(): void {
	$cache = &vw_image_tier_cache();
	$cache = [];
}

function vw_image_tier_compute( int $post_id ): int {
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
function vw_byline_inner( $post, bool $link_author = true ): string {
	$post = get_post( $post );
	if ( ! $post ) return '';

	$name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
	if ( vw_is_junk_author( $name ) ) {
		return '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">'
			. esc_html( get_the_date( 'M j, Y', $post ) ) . '</time>';
	}

	/*
	 * $link_author = false for any byline rendered INSIDE a wrapping <a>.
	 *
	 * HTML forbids nested anchors, and browsers do not merely ignore them: the
	 * parser closes the outer <a> where the inner one opens. Measured on the
	 * live archive-closer card, which is itself a link — the byline's <strong>
	 * was hoisted out of the card entirely and re-parented as a sibling after
	 * it, leaving a bare "By" inside the card and the author's name floating
	 * between the zone and the footer. Real people still link to their archive
	 * everywhere the byline is not already inside a link.
	 */
	if ( ! $link_author ) {
		return 'By <strong>' . esc_html( $name ) . '</strong>';
	}

	return 'By <strong>' . vw_author_html( $post ) . '</strong>';
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
	/*
	 * The trailing \b this pattern used to carry meant it only caught runs whose
	 * "Comment"s were separated by something. Scrapes that produced
	 * "CommentCommentComment…" with no separator at all have no word boundary
	 * between them, so the run survived into the dek verbatim — measured on
	 * posts 225 and 237 among others. One leading boundary, then repeats.
	 */
	$text = preg_replace( '/\bComments?(?:[\s\x{00A0}·|,–—-]*Comments?)+/iu', ' ', $text );
	$text = preg_replace( '/\b(?:Share this|Like this|Loading\.\.\.|Related Posts?|Tweet|Pin It)\b[\s\x{00A0}:·|,-]*/iu', ' ', $text );

	/*
	 * Photo-essay bodies are frequently nothing but one repeated caption credit,
	 * so the generated dek stuttered: "Photo by Jennifer McInnis Photo by
	 * Jennifer McInnis Photo by…" for the whole 26 words. Measured across the
	 * published archive: 359 posts stutter, 336 of those reduce to a credit and
	 * nothing else.
	 *
	 * Two rules. Collapse a repeated identical credit to one occurrence, then
	 * drop a dek that turns out to be only a credit — the credit line already
	 * carries the photographer (vw_ah_photographer() reads post_content
	 * directly, so nothing is lost), and printing it twice under its own byline
	 * was duplication rather than information.
	 *
	 * Single-quoted on purpose: in a double-quoted PHP string "\1" is an OCTAL
	 * escape and becomes byte 0x01, so the backreference never reaches PCRE —
	 * which is exactly how the first two attempts at this silently matched
	 * nothing.
	 */
	$credit = 'Photos?\s*(?:by|:)+\s*:?\s*[\p{Lu}][\p{L}\'’.-]+(?:\s+(?!Photos?\b)[\p{Lu}][\p{L}\'’.-]+){0,3}';
	$text   = preg_replace( '/(' . $credit . ')(?:[\s\x{00A0}·|,–—-]*\1\b)+/ui', '$1', (string) $text );

	/*
	 * A credit glued to the FRONT of real prose — "Photo By: Regina Ip Coffee is
	 * irresistible…" — which the collapse rule above never saw, because it is
	 * neither a repeat nor a credit-only body. Found on the live front page in
	 * the archive-closer card.
	 *
	 * Only ever stripped from the START: a credit that IS the whole body is
	 * handled by the credit-only rule below, and a credit mid-sentence is
	 * somebody's actual sentence.
	 *
	 * The name here is capped at TWO words, unlike the collapse rule above which
	 * allows four. There is no separator between the credit and the prose that
	 * follows it, so the only thing bounding the name is the word count — and a
	 * wider cap eats real copy. Measured: at four words this rule turned
	 * "Photo By: Regina Ip Coffee is irresistible…" into "irresistible…",
	 * swallowing "Coffee is" because "Coffee" is capitalised. Two words covers
	 * the archive's actual credits ("Regina Ip", "Ryan Johnson", "Jennifer
	 * McInnis"); a three-word name leaves one stray word, which is a far cheaper
	 * failure than deleting a sentence's subject. Repetition disambiguates in
	 * the collapse rule, which is why it can afford to be greedier.
	 *
	 * Case insensitive: the archive carries "Photo by", "Photo By:" and "PHOTO:".
	 * Display layer only — post_content is not touched, same as the 92
	 * filename-glued credits still on the backlog.
	 */
	$lead_name = 'Photos?\s*(?:by|:)+\s*:?\s*[\p{Lu}][\p{L}\'’.-]+(?:\s+[\p{Lu}][\p{L}\'’.-]+)?';
	$text      = preg_replace( '/^\s*' . $lead_name . '\s*[:.·|,–—-]*\s*/ui', '', (string) $text );

	$text = preg_replace( '/^[\s\x{00A0}·|,–—-]+/u', '', (string) $text );
	$text = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $text ) );

	if ( preg_match( '/^' . $credit . '\s*[.·|,–—-]*$/u', $text ) ) {
		return '';
	}

	return $text;
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
				<span class="vw-section-more__eyebrow">Keep reading</span>
				<span class="vw-section-more__count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
				<span class="vw-section-more__line">
					<?php printf( '%s stories in the archive', esc_html( $label ) ); ?>
				</span>
				<span class="vw-section-more__cta">Browse all &rarr;</span>
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
