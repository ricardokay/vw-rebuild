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
