<?php
/**
 * Sitewide footer — dark-first.
 *
 * The parent footer.php hardcodes <footer id="colophon"> and its copyright
 * block and offers no filter to replace them; its template parts render INSIDE
 * that element, so overriding them would keep Newspack's wrapper. The child
 * footer.php therefore replaces the parent file and calls vw_footer_render().
 *
 * Previewed behind ?vw_footer=1 (2f15107, 29b9a5a) and cut over 2026-09-13.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retained as a no-op, as vw_masthead_active() was at the masthead rollout.
 * The footer is unconditional now; this only exists so that anything still
 * calling it — an old link's query flag, a stale condition — gets a defined
 * answer instead of a fatal.
 */
function vw_footer_preview_active(): bool {
	return false;
}

/*
 * Institutional pages, by ID rather than slug. Most exist twice from the
 * import and the 43-page cull will consolidate them; an ID survives a slug
 * change, and a page that stops being published degrades to plain text.
 *
 * Chosen copies, by stripped content length (2026-09-13):
 *   66   /advertise/            85 chars   (#15893 is an identical stub)
 *   1955 /contributor-kit-2/    99 chars   (#68, the canonical slug, is empty)
 *   78   /newsletter/          349 chars
 *   56   /privacy-policy-2/  3,265 chars
 *   52   /terms-and-conditions/ 21,502 chars
 *   49   /resources/         2,648 chars
 *
 * Jobs is deliberately absent. Both copies (#60, #13407) are empty, and an
 * entry with nowhere to go is worse than no entry. It returns as a row here
 * once a real page exists — the "simple email-us" page is still undecided.
 */
const VW_FOOTER_ABOUT = [
	[ 'label' => 'Advertise With Us', 'page' => 66 ],
	[ 'label' => 'Contributor Kit',   'page' => 1955 ],
	[ 'label' => 'Newsletters',       'page' => 78 ],
	[ 'label' => 'Privacy Policy',    'page' => 56 ],
	[ 'label' => 'Terms',             'page' => 52 ],
	[ 'label' => 'Resources',         'page' => 49 ],
];

const VW_FOOTER_LEGAL = [
	[ 'label' => 'Privacy', 'page' => 56 ],
	[ 'label' => 'Terms',   'page' => 52 ],
];

/**
 * The registry's one rule: a page's URL when it is published, otherwise ''.
 *
 * Every renderer of VW_FOOTER_ABOUT and VW_FOOTER_LEGAL goes through this — the
 * footer columns and the mobile panel's About grid — so a page that stops being
 * published degrades identically everywhere.
 */
function vw_footer_link_url( array $item ): string {
	$id = (int) $item['page'];
	return ( $id && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
}

/** One footer list item: a link when the page is published, otherwise plain text. */
function vw_footer_link_item( array $item ): string {
	$url = vw_footer_link_url( $item );

	$inner = $url
		? '<a href="' . esc_url( $url ) . '">' . esc_html( $item['label'] ) . '</a>'
		: '<span class="vw-footer__unlinked">' . esc_html( $item['label'] ) . '</span>';

	return '<li>' . $inner . '</li>';
}

add_action( 'wp_enqueue_scripts', 'vw_footer_enqueue', 20 );
function vw_footer_enqueue(): void {
	$path = get_stylesheet_directory() . '/assets/css/footer.css';
	wp_enqueue_style(
		'vw-footer',
		get_stylesheet_directory_uri() . '/assets/css/footer.css',
		[],
		file_exists( $path ) ? (string) filemtime( $path ) : null
	);
}

/**
 * The footer palette as custom properties. Colours reach the stylesheets only
 * this way — footer.css and masthead-nav.css hold no colour literal — so the
 * mobile bar and panel follow the chrome panel's footer settings too.
 */
function vw_footer_palette_vars(): string {
	$palette = vw_chrome_footer_palette();
	return sprintf(
		'--vwf-bg:%s;--vwf-text:%s;--vwf-accent:%s;--vwf-rule:%s;',
		$palette['footer_bg'],
		$palette['footer_text'],
		$palette['footer_accent'],
		$palette['footer_rule']
	);
}

function vw_footer_render(): void {
	$motto = vw_chrome_motto_display();
	?>
	<footer id="colophon" class="vw-footer" style="<?php echo esc_attr( vw_footer_palette_vars() ); ?>">
		<div class="vw-footer__inner">

			<div class="vw-footer__top">
				<div class="vw-footer__identity">
					<?php // SVG GUARD: referenced as <img>, never inlined — see inc/masthead.php. ?>
					<a class="vw-footer__nameplate-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<img class="vw-footer__nameplate"
							src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.svg' ); ?>"
							alt="Vancouver Weekly">
					</a>
					<?php if ( '' !== $motto ) : ?>
						<p class="vw-footer__motto"><?php echo esc_html( $motto ); ?></p>
					<?php endif; ?>
					<p class="vw-footer__since">Independent since <?php echo esc_html( (string) vw_chrome_founded() ); ?></p>
				</div>

				<div class="vw-footer__cols">
					<nav class="vw-footer__col" aria-labelledby="vw-footer-sections">
						<h2 class="vw-footer__heading" id="vw-footer-sections">Sections</h2>
						<ul class="vw-footer__list">
							<?php
							foreach ( VW_MASTHEAD_SECTIONS as $slug => $label ) :
								$term = get_category_by_slug( $slug );
								if ( ! $term ) {
									continue;
								}
								?>
								<li><a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>"><?php echo wp_kses( $label, [] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>

					<nav class="vw-footer__col" aria-labelledby="vw-footer-about">
						<h2 class="vw-footer__heading" id="vw-footer-about">About</h2>
						<ul class="vw-footer__list">
							<?php
							foreach ( VW_FOOTER_ABOUT as $item ) {
								echo vw_footer_link_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
							}
							?>
						</ul>
					</nav>
				</div>
			</div>

			<hr class="vw-footer__rule">

			<div class="vw-footer__utility">
				<p class="vw-footer__copyright">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Vancouver Weekly</p>
				<ul class="vw-footer__legal">
					<?php
					foreach ( VW_FOOTER_LEGAL as $item ) {
						echo vw_footer_link_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					}
					?>
				</ul>
			</div>

		</div>
	</footer><!-- #colophon -->
	<?php
}
