<?php
/**
 * The sitewide masthead — dateline strip, wordmark, motto, section nav.
 *
 * Rolled out as the default header on 2026-09-12. header.php calls
 * vw_masthead_render() directly; the homepage part renders the same markup
 * inline because there the masthead is the first element of the composition
 * rather than chrome above it.
 *
 * ?vw_masthead=1 is retained as a NO-OP so that preview links shared during the
 * review rounds do not break — the flag used to switch this on, and now there
 * is nothing to switch.
 */

const VW_MASTHEAD_SECTIONS = [
	'a-la-music'          => 'A La Music',
	'photography'         => 'Photography',
	'food-drink'          => 'Food &amp; Drink',
	'out-n-about'         => 'Out N About',
	'political-megaphone' => 'Political Megaphone',
	'book-reviews'        => 'Book Reviews',
];

/**
 * Retained as a no-op. The masthead is unconditional now; this only exists so
 * that anything still calling it — an old link's query flag, a stale enqueue
 * condition — gets a defined answer instead of a fatal.
 */
function vw_masthead_active(): bool {
	return false;
}

function vw_masthead_render(): void {
	?>
	<div class="vwh2-page vw-masthead">
		<div class="vwh2-container">
			<div class="vwh2-masthead">
				<?php if ( vw_chrome_show_dateline() ) : ?>
					<div class="vwh2-masthead__dateline">
						<span><?php echo esc_html( vw_chrome_dateline() ); ?></span>
						<span><?php echo esc_html( vw_chrome_slogan() ); ?></span>
					</div>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vwh2-masthead__logo-link">
<?php
					/*
					 * SVG GUARD — do not inline this file, and do not give it or its
					 * wrapper overflow:visible.
					 *
					 * logo_VW_wordmark.svg carries the "VANCOUVER'S WEEKLY NEWS SOURCE"
					 * tagline glyphs at y 62.9–74.7, outside its viewBox (height 59.4).
					 * 366 of its 516 path coordinates are those glyphs. The viewBox clip
					 * is the ONLY thing hiding them: referenced as <img> they never
					 * render, but inlined into the document — or with overflow opened on
					 * the <svg> — the tagline appears and the masthead becomes the wrong
					 * lockup.
					 */
					?>
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.svg' ); ?>" alt="Vancouver Weekly" class="vwh2-masthead__logo">
				</a>
				<?php if ( vw_chrome_motto() ) : ?>
				<p class="vwh2-masthead__motto"><?php echo esc_html( vw_chrome_motto() ); ?></p>
			<?php endif; ?>
				<nav class="vwh2-masthead__nav">
					<?php foreach ( VW_MASTHEAD_SECTIONS as $slug => $label ) : ?>
						<?php
						$term = get_category_by_slug( $slug );
						$href = $term ? get_category_link( $term->term_id ) : home_url( '/' );
						?>
						<a href="<?php echo esc_url( $href ); ?>" class="vwh2-masthead__nav-item<?php echo ( vw_nav_active_slug() === $slug ) ? ' vwh2-masthead__nav-item--active' : ''; ?>"<?php echo ( vw_nav_active_slug() === $slug ) ? ' aria-current="page"' : ''; ?>><?php echo wp_kses( $label, [] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="vwh2-rule vwh2-rule--heavy">
		</div>
	</div>
	<?php
}

