<?php
/**
 * Front page — the cutover.
 *
 * A thin wrapper, deliberately: every decision about what the homepage contains
 * lives in the template registry and the curation option, not here. This file
 * only answers "which part file, inside which wrapper".
 *
 * It replaces page 9 as the site's front door. WordPress resolves front-page.php
 * ahead of page.php for a static front page, so the existence of this file IS
 * the cutover — no option changed, and deleting it reverts the site to page 9
 * exactly as it was. Page 9's content is untouched and is simply no longer
 * reached.
 *
 * The stylesheet and the .vw-home-v2 body class are wired in functions.php on
 * is_front_page(), so they were already correct before this file existed; that
 * was the point of re-scoping them in Round 1 session C.
 */

defined( 'ABSPATH' ) || exit;

$vw_home = vw_tpl_home_file();

get_header();
?>
<div class="vwh2-page">
	<?php
	if ( $vw_home ) {
		include $vw_home;
	} else {
		// The registry's template slug resolved to a file that is not on disk.
		// Render nothing rather than a fatal: the masthead and footer from
		// get_header()/get_footer() still frame a usable page, and the admin's
		// Templates panel is where the wrong slug gets corrected.
		printf(
			'<!-- vw: no homepage part for template "%s" -->',
			esc_html( vw_tpl_current( 'home' ) )
		);
	}
	?>
</div>
<?php
get_footer();
