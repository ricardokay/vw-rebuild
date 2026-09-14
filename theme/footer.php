<?php
/**
 * Sitewide footer.
 *
 * Replaces the parent footer.php outright rather than wrapping it: Newspack
 * hardcodes <footer id="colophon"> and its copyright block with no filter to
 * swap them. The tail around #colophon is replicated from
 * newspack-theme/footer.php, not guessed:
 *
 *   before_footer · close #content · #colophon · close #page · wp_footer()
 *   · </body></html>
 *
 * The parent's footer-3 widget sidebar is not reproduced. No footer sidebar
 * was active at cutover (2026-09-13), and the design replaces the widget areas.
 */
?>

	<?php do_action( 'before_footer' ); ?>

	</div><!-- #content -->

	<?php vw_footer_render(); ?>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
