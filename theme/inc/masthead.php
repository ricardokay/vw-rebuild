<?php
/**
 * Sitewide masthead — PREVIEW ONLY.
 *
 * Renders the homepage-v2 masthead (dateline strip, centred wordmark, motto,
 * six-section nav) on any page with ?vw_masthead=1, so chrome consistency can
 * be judged before rollout. Unflagged pages are untouched.
 *
 * The markup mirrors section-parts/homepage-v2.php and reuses that stylesheet,
 * so the preview shows the real thing rather than a lookalike. Two differences,
 * both deliberate: the dateline shows the real current date instead of the
 * mockup's frozen "Saturday, July 25, 2026", and the nav links point at real
 * category archives via get_category_link() rather than "#".
 *
 * PREVIEW-GRADE: the existing .vw-nav header is hidden with CSS on flagged
 * views. A real rollout replaces header.php properly rather than hiding it.
 */

const VW_MASTHEAD_SECTIONS = [
	'a-la-music'          => 'A La Music',
	'photography'         => 'Photography',
	'food-drink'          => 'Food &amp; Drink',
	'out-n-about'         => 'Out N About',
	'political-megaphone' => 'Political Megaphone',
	'book-reviews'        => 'Book Reviews',
];

function vw_masthead_active(): bool {
	if ( is_admin() || is_feed() ) return false;
	return isset( $_GET['vw_masthead'] ) && '1' === $_GET['vw_masthead']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

add_action( 'wp_body_open', 'vw_masthead_render' );
function vw_masthead_render(): void {
	if ( ! vw_masthead_active() ) return;
	?>
	<div class="vwh2-page vw-masthead-preview__wrap">
		<div class="vwh2-container">
			<div class="vwh2-masthead">
				<?php if ( vw_chrome_show_dateline() ) : ?>
					<div class="vwh2-masthead__dateline">
						<span><?php echo esc_html( vw_chrome_dateline() ); ?></span>
						<span><?php echo esc_html( vw_chrome_slogan() ); ?></span>
					</div>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vwh2-masthead__logo-link">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.png' ); ?>" alt="Vancouver Weekly" class="vwh2-masthead__logo">
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
						<a href="<?php echo esc_url( $href ); ?>" class="vwh2-masthead__nav-item<?php echo is_category( $slug ) ? ' vwh2-masthead__nav-item--active' : ''; ?>"<?php echo is_category( $slug ) ? ' aria-current="page"' : ''; ?>><?php echo wp_kses( $label, [] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="vwh2-rule vwh2-rule--heavy">
		</div>
	</div>
	<?php
}

add_filter( 'body_class', 'vw_masthead_body_class' );
function vw_masthead_body_class( $classes ) {
	if ( vw_masthead_active() ) $classes[] = 'vw-masthead-preview';
	return $classes;
}
