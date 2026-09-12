<?php
/**
 * Single-post header — the adaptive article header, as the default.
 *
 * Overrides newspack-theme/template-parts/header/entry-header.php. WordPress
 * resolves get_template_part() against the child theme first, so replacing the
 * part replaces the header everywhere the parent asks for one — single.php's
 * plain branch and all four of large-featured-image.php's branches — without
 * forking single.php and inheriting its sponsor, sidebar and author-bio logic.
 *
 * Pages keep the parent's header: this design is about articles, and a page has
 * no section, no read time and no photographer.
 */

defined( 'ABSPATH' ) || exit;

if ( is_singular( 'post' ) && function_exists( 'vw_ah_render' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in vw_ah_render().
	echo vw_ah_render( get_post() );
	return;
}

get_template_part( 'template-parts/header/entry-header-newspack' );
