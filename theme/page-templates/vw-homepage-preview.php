<?php
/**
 * Template Name: VW Homepage Preview
 *
 * Renders the homepage module on a private page so the front can be
 * reviewed while page 9 stays live. After cutover approval, a thin
 * front-page.php includes the same module and this template retires.
 */

get_header();
?>
<div class="vw-section-landing vw-homepage">
	<div class="vw-section-blocks">
		<?php include get_stylesheet_directory() . '/section-parts/homepage.php'; ?>
	</div>
</div>
<?php
get_footer();
