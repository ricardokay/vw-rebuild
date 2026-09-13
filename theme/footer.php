<?php
/**
 * Footer passthrough, with the v1 footer behind ?vw_footer=1.
 *
 * Flag off, the parent file is required unchanged, so the default site's
 * output is identical by construction rather than by CSS. Flag on, this
 * replicates the parent's tail around a different #colophon — read from
 * newspack-theme/footer.php, not guessed:
 *
 *   footer-3 sidebar · before_footer · close #content · #colophon · close #page
 *   · wp_footer() · </body></html>
 *
 * The footer-3 sidebar is dropped under the flag. No footer sidebar is active
 * (checked 2026-09-13), and the v1 design replaces the widget areas anyway.
 */

if ( ! function_exists( 'vw_footer_preview_active' ) || ! vw_footer_preview_active() ) {
	require get_template_directory() . '/footer.php';
	return;
}
?>

	<?php do_action( 'before_footer' ); ?>

	</div><!-- #content -->

	<?php vw_footer_render(); ?>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
