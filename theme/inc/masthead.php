<?php
/**
 * The sitewide masthead — dateline strip, wordmark, motto, section nav — and,
 * below 960px, the mobile bottom bar and its sections/search panel.
 *
 * Rolled out as the default header on 2026-09-12. header.php calls
 * vw_masthead_render() directly; the homepage part renders its own masthead
 * inline because there the masthead is the first element of the composition
 * rather than chrome above it. Both print the nav through
 * vw_masthead_nav_render(), so the two can no longer drift.
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
	'must-see-films'      => 'Must See Films',
];

/*
 * Secondary links in the mobile panel. Page IDs, as in VW_FOOTER_ABOUT, so a
 * page that stops being published simply drops out.
 */
const VW_MOBILE_PANEL_LINKS = [
	[ 'label' => 'Newsletters',     'page' => 78 ],
	[ 'label' => 'Advertise',       'page' => 66 ],
	[ 'label' => 'Contributor Kit', 'page' => 1955 ],
];

/**
 * Retained as a no-op. The masthead is unconditional now; this only exists so
 * that anything still calling it — an old link's query flag, a stale enqueue
 * condition — gets a defined answer instead of a fatal.
 */
function vw_masthead_active(): bool {
	return false;
}

/** Nav sections whose category exists, as [ slug => [ label, url ] ]. */
function vw_masthead_sections(): array {
	$out = [];
	foreach ( VW_MASTHEAD_SECTIONS as $slug => $label ) {
		$term = get_category_by_slug( $slug );
		if ( ! $term ) {
			continue;
		}
		$out[ $slug ] = [ $label, get_category_link( $term->term_id ) ];
	}
	return $out;
}

/**
 * The inline section nav. Above 960px it is the nav; below it, with JavaScript,
 * the bottom bar replaces it; without JavaScript it stays as the fallback.
 */
function vw_masthead_nav_render(): void {
	$active = vw_nav_active_slug();
	?>
	<nav class="vwh2-masthead__nav" aria-label="Sections">
		<?php foreach ( vw_masthead_sections() as $slug => [ $label, $url ] ) : ?>
			<a href="<?php echo esc_url( $url ); ?>" class="vwh2-masthead__nav-item<?php echo $active === $slug ? ' vwh2-masthead__nav-item--active' : ''; ?>"<?php echo $active === $slug ? ' aria-current="page"' : ''; ?>><?php echo wp_kses( $label, [] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * "You are here" — the active section as an underlined label under the heavy
 * rule. Below 960px the inline nav is collapsed into the panel, and this is the
 * only thing on the page that still says which section you are in.
 */
function vw_masthead_here_render(): void {
	$active   = vw_nav_active_slug();
	$sections = vw_masthead_sections();
	if ( '' === $active || ! isset( $sections[ $active ] ) ) {
		return;
	}
	[ $label, $url ] = $sections[ $active ];
	?>
	<div class="vw-here">
		<a class="vw-here__link" href="<?php echo esc_url( $url ); ?>"><?php echo wp_kses( $label, [] ); ?></a>
	</div>
	<?php
}

function vw_masthead_render(): void {
	?>
	<div class="vwh2-page vw-masthead">
		<div class="vwh2-container">
			<div class="vwh2-masthead">
				<?php if ( vw_chrome_show_dateline() ) : ?>
					<div class="vwh2-masthead__dateline">
						<span><?php echo esc_html( vw_chrome_dateline() ); ?></span>
						<span class="vwh2-masthead__slogan"><?php echo esc_html( vw_chrome_slogan() ); ?></span>
					</div>
				<?php endif; ?>
				<?php vw_masthead_brand_render(); ?>
				<?php if ( vw_chrome_motto_display() ) : ?>
				<p class="vwh2-masthead__motto"><?php echo esc_html( vw_chrome_motto_display() ); ?></p>
			<?php endif; ?>
				<?php vw_masthead_nav_render(); ?>
			</div>
			<hr class="vwh2-rule vwh2-rule--heavy">
			<?php vw_masthead_here_render(); ?>
		</div>
	</div>
	<?php
}

/**
 * Wordmark row. Below 960px it is the compact mobile header — a sections button
 * left, the wordmark centred, a search link right — and both side controls open
 * the same panel the bottom bar opens (masthead-nav.js binds every
 * [data-vw-mnav-open]). At 960px and up the row is display:contents and the
 * controls are hidden, so the desktop masthead is the wordmark alone, as before.
 *
 * Without JavaScript the sections button is hidden (the inline nav is showing
 * directly below it) and the search control is a plain link to the search page.
 */
function vw_masthead_brand_render(): void {
	?>
	<div class="vwh2-masthead__row">
		<button type="button" class="vwh2-masthead__icon vwh2-masthead__icon--sections" data-vw-mnav-open="sections" aria-controls="vw-mnav-panel" aria-expanded="false" aria-label="Sections"><?php echo vw_mobile_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
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
		<a href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" class="vwh2-masthead__icon vwh2-masthead__icon--search" data-vw-mnav-open="search" aria-controls="vw-mnav-panel" aria-label="Search"><?php echo vw_mobile_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></a>
	</div>
	<?php
}

/** Inline icons for the masthead, bottom bar and panel. Decorative; labels carry meaning. */
function vw_mobile_icon( string $name ): string {
	$paths = [
		'menu'     => '<path d="M3 7h10M3 12h18M3 17h14"/>',
		'home'     => '<path d="M3 11l9-7 9 7v9h-6v-6H9v6H3z"/>',
		'sections' => '<rect x="3" y="3" width="7.5" height="7.5"/><rect x="13.5" y="3" width="7.5" height="7.5"/><rect x="3" y="13.5" width="7.5" height="7.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5"/>',
		'search'   => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>',
		'archive'  => '<rect x="3" y="4" width="18" height="5"/><path d="M5 9v11h14V9M10 13h4"/>',
		'close'    => '<path d="M5 5l14 14M19 5L5 19"/>',
	];
	return '<svg class="vw-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Mobile bottom bar + sections/search panel. Printed once, from footer.php.
 *
 * The bar holds modes, not topics — Home, Sections, Search, Archive — so it
 * never grows with the section list. Both elements are hidden without
 * JavaScript and above 960px; see assets/css/masthead-nav.css.
 */
function vw_mobile_nav_render(): void {
	$active   = vw_nav_active_slug();
	$is_arch  = function_exists( 'vw_is_all_archive' ) && vw_is_all_archive();
	$vars     = function_exists( 'vw_footer_palette_vars' ) ? vw_footer_palette_vars() : '';
	?>
	<div class="vw-mnav" style="<?php echo esc_attr( $vars ); ?>">

		<div class="vw-mnav-panel" id="vw-mnav-panel" role="dialog" aria-modal="true" aria-label="Sections and search" data-mode="sections" tabindex="-1" hidden>
			<div class="vw-mnav-panel__top">
				<a class="vw-mnav-panel__logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php // SVG GUARD: referenced as <img>, never inlined — see vw_masthead_render(). ?>
					<img class="vw-mnav-panel__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.svg' ); ?>" alt="Vancouver Weekly">
				</a>
				<button type="button" class="vw-mnav-panel__close" data-vw-mnav-close aria-label="Close"><?php echo vw_mobile_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
			</div>

			<form role="search" method="get" class="vw-mnav-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="vw-mnav-s">Search Vancouver Weekly</label>
				<input type="search" id="vw-mnav-s" class="vw-mnav-search__input" name="s" placeholder="Search 20 years of Vancouver Weekly" value="<?php echo esc_attr( get_search_query() ); ?>">
				<button type="submit" class="vw-mnav-search__submit" aria-label="Search"><?php echo vw_mobile_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></button>
			</form>

			<p class="vw-mnav-panel__label" id="vw-mnav-sections">Sections</p>
			<ul class="vw-mnav-panel__list" aria-labelledby="vw-mnav-sections">
				<?php foreach ( vw_masthead_sections() as $slug => [ $label, $url ] ) : ?>
					<li><a class="vw-mnav-panel__item<?php echo $active === $slug ? ' vw-mnav-panel__item--active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $active === $slug ? ' aria-current="page"' : ''; ?>><?php echo wp_kses( $label, [] ); ?></a></li>
				<?php endforeach; ?>
			</ul>

			<ul class="vw-mnav-panel__secondary">
				<?php
				foreach ( VW_MOBILE_PANEL_LINKS as $item ) :
					$id = (int) $item['page'];
					if ( 'publish' !== get_post_status( $id ) ) {
						continue;
					}
					?>
					<li><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>

			<?php if ( vw_chrome_motto_display() ) : ?>
				<p class="vw-mnav-panel__motto"><?php echo esc_html( vw_chrome_motto_display() ); ?></p>
			<?php endif; ?>
		</div>

		<nav class="vw-mbar" aria-label="Site">
			<a class="vw-mbar__item<?php echo is_front_page() ? ' vw-mbar__item--active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
				<?php echo vw_mobile_icon( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span>Home</span>
			</a>
			<button type="button" class="vw-mbar__item" data-vw-mnav-open="sections" aria-controls="vw-mnav-panel" aria-expanded="false">
				<?php echo vw_mobile_icon( 'sections' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span>Sections</span>
			</button>
			<button type="button" class="vw-mbar__item<?php echo is_search() ? ' vw-mbar__item--active' : ''; ?>" data-vw-mnav-open="search" aria-controls="vw-mnav-panel" aria-expanded="false">
				<?php echo vw_mobile_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span>Search</span>
			</button>
			<a class="vw-mbar__item<?php echo $is_arch ? ' vw-mbar__item--active' : ''; ?>" href="<?php echo esc_url( home_url( '/archive/' ) ); ?>"<?php echo $is_arch ? ' aria-current="page"' : ''; ?>>
				<?php echo vw_mobile_icon( 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span>Archive</span>
			</a>
		</nav>

	</div>
	<?php
}
