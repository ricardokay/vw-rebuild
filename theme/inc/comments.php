<?php
/**
 * Comments off, sitewide — display layer only.
 *
 * The archive carries 5,598 comments of unknown provenance on a site that was
 * demonstrably compromised (the pharma-spam import is the reason vw-security
 * exists). Publishing them unvetted is an SEO and liability risk, and vetting
 * 5,598 of them is not a launch task.
 *
 * So: nothing is deleted, nothing is edited, no comment row is touched. Every
 * rule here is a filter, which means the whole decision reverts by removing
 * this file's require. A later human moderation pass can resurface threads
 * selectively — the data is all still there, and the admin Comments screen is
 * deliberately left working so that pass has somewhere to happen.
 *
 * Two layers on purpose:
 *   1. comments_open()/pings_open() report closed everywhere, which is what
 *      well-behaved templates ask before rendering anything.
 *   2. the lists, forms, counts and feeds are suppressed directly, because
 *      "well-behaved" is not a guarantee — the parent theme and any plugin can
 *      call comments_template() or get_comments_number() on their own terms.
 */

defined( 'ABSPATH' ) || exit;

/* ── 1. Closed everywhere, whatever the per-post status says ────────────── */

add_filter( 'comments_open', '__return_false', 100 );
add_filter( 'pings_open', '__return_false', 100 );

/**
 * New posts default to closed.
 *
 * This is the code-level half of the setting. The matching options
 * (default_comment_status / default_ping_status) are a database write and this
 * round is scoped to the theme, so they are left for Ricardo — but with these
 * filters in place the stored option no longer decides anything, in the editor
 * or on the front end.
 */
add_filter( 'option_default_comment_status', 'vw_comments_force_closed', 100 );
add_filter( 'option_default_ping_status', 'vw_comments_force_closed', 100 );
function vw_comments_force_closed(): string {
	return 'closed';
}


/* ── 2. Nothing renders, whatever the template asks for ─────────────────── */

/** No list, ever — this is what hides the 5,598 without touching them. */
add_filter( 'comments_array', 'vw_comments_empty_array', 100 );
add_filter( 'the_comments', 'vw_comments_empty_array', 100 );
function vw_comments_empty_array(): array {
	return [];
}

/**
 * Report zero on the front end only.
 *
 * Scoped away from admin so the Comments screen still shows real numbers for
 * the future moderation pass — the point is to stop publishing them, not to
 * hide them from their owner.
 */
add_filter( 'get_comments_number', 'vw_comments_zero_count', 100 );
function vw_comments_zero_count( $count ) {
	return is_admin() ? $count : 0;
}

/** Kill the form outright, in case a template calls comment_form() directly. */
add_filter( 'comment_form_before', 'vw_comments_buffer_open', 1 );
add_filter( 'comment_form_after', 'vw_comments_buffer_close', 100 );
add_action( 'comment_form_before', 'vw_comments_buffer_open', 1 );
add_action( 'comment_form_after', 'vw_comments_buffer_close', 100 );
function vw_comments_buffer_open(): void {
	if ( ! is_admin() ) {
		ob_start();
	}
}
function vw_comments_buffer_close(): void {
	if ( ! is_admin() && ob_get_level() ) {
		ob_end_clean();
	}
}

/**
 * Serve an empty comments template.
 *
 * comments_template() is called by the parent theme's single.php before any of
 * the filters above get a look at the markup it loads, so it is pointed at a
 * file that outputs nothing rather than trusted to check comments_open().
 */
add_filter( 'comments_template', 'vw_comments_blank_template', 100 );
function vw_comments_blank_template(): string {
	return get_stylesheet_directory() . '/comments.php';
}

/** Reply links have nothing to reply to. */
add_filter( 'comment_reply_link', '__return_empty_string', 100 );

/** No comment feeds advertised or served. */
add_filter( 'feed_links_show_comments_feed', '__return_false', 100 );
add_filter( 'post_comments_feed_link', '__return_empty_string', 100 );

/**
 * Drop the "Leave a comment" / "N Comments" link from entry meta.
 *
 * get_comments_number() already returns 0, which suppresses the count, but
 * Newspack's entry-meta prints a "Leave a comment" affordance when the count is
 * zero AND comments are open. comments_open() is false, so this is belt and
 * braces for any template that checks only one of the two.
 */
add_filter( 'newspack_theme_entry_meta', '__return_false', 1 );

/** Hide the front-end admin-bar comment bubble; the admin screen keeps working. */
add_action( 'admin_bar_menu', 'vw_comments_admin_bar', 999 );
function vw_comments_admin_bar( $bar ): void {
	if ( ! is_admin() ) {
		$bar->remove_node( 'comments' );
	}
}
