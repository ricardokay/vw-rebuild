<?php
/**
 * Legacy URL 301s, path to path.
 *
 * Paths only, never a hostname: the target is built with home_url(), so the
 * same map works on staging and on vancouverweekly.com after cutover. Lives in
 * the plugin rather than the theme so a theme change cannot drop it.
 *
 * Scope (DNS prep, 2026-09-18):
 * - Custom Permalinks paths that 404 but whose post is live under its native
 *   slug. The other 40 custom paths are left alone: 29 already resolve (most
 *   are the native slug of the duplicate copy), and 11 belong to B1-retired
 *   gallery posts that must keep 404ing until the FB migration restores them.
 * - Events leftovers (a dead calendar page, two empty pages, a category whose
 *   posts are all drafts) go to the homepage.
 */

defined( 'ABSPATH' ) || exit;

const VW_REDIRECTS = [
	// Custom Permalinks → live post (343, 487).
	'the-best-fitness-studios-for-every-vancouverites-specific-needs-56605-2' => 'the-best-fitness-studios-for-every-vancouverites-specific-needs/',
	'album-review-bute-streets-sunny-days-hazy-nights'                        => 'bute-street-rock-some-familiar-subjects-with-sunny-days-hazy-nights/',
	// Events leftovers (pages 9806, 1779, 1953; category 49).
	'events-calendar'           => '',
	'events'                    => '',
	'events-2'                  => '',
	'category/upcoming-events'  => '',
];

// Priority 1: ahead of redirect_canonical and before a leftover page renders.
add_action( 'template_redirect', function () {
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $base && '/' !== $base && str_starts_with( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	$key = strtolower( trim( rawurldecode( $path ), '/' ) );

	if ( '' !== $key && array_key_exists( $key, VW_REDIRECTS ) ) {
		wp_safe_redirect( home_url( '/' . VW_REDIRECTS[ $key ] ), 301, 'vw-security' );
		exit;
	}
}, 1 );
