<?php

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
 */
function vw_primary_cat_name( int $post_id, array $preferred_cat_ids ): string {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) return '';
	foreach ( $preferred_cat_ids as $cid ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $cid ) return $t->name;
		}
	}
	return $terms[0]->name ?? '';
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
