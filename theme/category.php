<?php
/**
 * Category archive router.
 *
 * A category renders a curated front when it has a template assigned (term meta
 * _vw_tpl, set from a dropdown on the Edit Category screen) AND a matching
 * section-parts/{slug}.php exists. Everything else falls through to the
 * Newspack parent archive.
 *
 * This replaced a hardcoded $curated array plus a parallel display-name map.
 * Adding a section used to mean editing this file in two places and remembering
 * to; it is now a dropdown, and the section's name comes from the term itself.
 *
 * The section header block (mark + title + description) was removed in Round 1
 * session C: the active item in the masthead nav already says which section you
 * are in, in accent red, and the block repeated it directly underneath. An h1
 * is still emitted for search engines and screen readers — visually hidden,
 * never absent, because a page with no h1 is an accessibility defect regardless
 * of how the design reads.
 */

defined( 'ABSPATH' ) || exit;

$vw_term = get_queried_object();
$vw_part = ( $vw_term instanceof WP_Term ) ? vw_tpl_section_part( $vw_term ) : null;

// Page 2+ falls through to the standard archive: the curated front is the
// section's front page, not its whole index. Without this the router served the
// same front at /page/2/, so curated sections had no working pagination at all.
if ( $vw_part && ! is_paged() ) :
	get_header();
	?>

	<div class="vw-section-landing vw-section-landing--noheader">

		<h1 class="screen-reader-text"><?php echo esc_html( $vw_term->name ); ?></h1>

		<div class="vw-section-blocks">
			<?php
			if ( 'php' === $vw_part['type'] ) {
				include $vw_part['path'];
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup from a theme file.
				echo do_blocks( (string) file_get_contents( $vw_part['path'] ) );
			}
			?>
		</div>

	</div><!-- .vw-section-landing -->

	<?php
	get_footer();
	return;
endif;

// Not a curated section — use Newspack's default archive.
include get_template_directory() . '/archive.php';
