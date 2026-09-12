<?php
/**
 * Homepage v2 — PHASE 2: data-driven.
 *
 * The markup and class names of the approved phase-1 mockup are preserved; only
 * the content source changed. Every headline, byline, image, link, date and
 * count below now comes from the curation resolver or from live site data.
 * Gone with phase 1: 7 images.unsplash.com hotlinks, 34 href="#", a frozen
 * dateline, and a hardcoded "16,412 stories" against an actual 3,373.
 *
 * Zones come from inc/curation-registry.php and resolve through
 * vw_curation_resolve(), which threads $used_ids so no story repeats. Nothing
 * needs curating for this page to render: every slot falls back to auto-fill.
 *
 * Missing images are the normal case here, not the exception — 61.3% of the
 * published archive has no usable featured image. Each zone below states its
 * own text-variant behaviour; the rule everywhere is that the image box is
 * REMOVED rather than left empty, so the grid keeps its integrity.
 *
 * New CSS for those variants lives in assets/css/homepage-v2-data.css and is
 * mobile-first. This file's own stylesheet (homepage-v2.css) remains
 * desktop-first until the Round 7 mobile sweep, per decision.
 */

defined( 'ABSPATH' ) || exit;

$vw_used = [];

/** Resolve a zone once, threading $used_ids so no story repeats on the page. */
$vw_zone = static function ( string $zone ) use ( &$vw_used ): array {
	return vw_curation_resolve( 'home', $zone, $vw_used );
};

/** Category permalink by ID, falling back home if the term has gone. */
$vw_cat_url = static function ( int $id ): string {
	$link = get_category_link( $id );
	return $link ? $link : home_url( '/' );
};

/** Kicker markup: section name plus its mark, or nothing for Uncategorized. */
$vw_kicker = static function ( WP_Post $post, string $class = 'vwh2-kicker' ): void {
	list( $name, $mark ) = vw_ah_section( (int) $post->ID );
	if ( ! $name ) {
		return;
	}
	// The section name is the reader's route into the section, so it is a link
	// wherever it appears — kickers included, not just the "All …" affordances.
	$term = null;
	foreach ( (array) get_the_terms( (int) $post->ID, 'category' ) as $t ) {
		if ( $t instanceof WP_Term && $t->name === $name ) { $term = $t; break; }
	}
	$href = $term ? get_category_link( $term->term_id ) : '';

	printf(
		'<span class="%s">%s%s</span>',
		esc_attr( $class ),
		$mark ? '<span class="vwh2-mark vwh2-mark--' . esc_attr( $mark ) . '"></span>' : '',
		$href
			? '<a class="vwh2-kicker__link" href="' . esc_url( $href ) . '">' . esc_html( $name ) . '</a>'
			: esc_html( $name )
	);
};

/** Featured image for a resolved slot, or '' when the slot is text-variant. */
$vw_img = static function ( array $slot, string $size, string $class ): string {
	if ( $slot['text_variant'] ) {
		return '';
	}
	return (string) wp_get_attachment_image(
		get_post_thumbnail_id( $slot['post']->ID ),
		$size,
		false,
		[
			'class'   => $class,
			'alt'     => trim( wp_strip_all_tags( get_the_title( $slot['post'] ) ) ),
			'loading' => 'lazy',
		]
	);
};

$vw_lead      = $vw_zone( 'lead' );
$vw_thisweek  = $vw_zone( 'thisweek' );
$vw_music     = $vw_zone( 'music' );
$vw_photo     = $vw_zone( 'photo' );
$vw_food      = $vw_zone( 'food' );
$vw_political = $vw_zone( 'political' );
$vw_books     = $vw_zone( 'books' );
$vw_archive   = $vw_zone( 'archive' );

$vw_nav = VW_MASTHEAD_SECTIONS;
?>

<div class="vwh2-container">

	<!-- Masthead -->
	<div class="vwh2-masthead">
		<?php if ( vw_chrome_show_dateline() ) : ?>
			<div class="vwh2-masthead__dateline">
				<span><?php echo esc_html( vw_chrome_dateline() ); ?></span>
				<span><?php echo esc_html( vw_chrome_slogan() ); ?></span>
			</div>
		<?php endif; ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vwh2-masthead__logo-link">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.svg' ); ?>" alt="Vancouver Weekly" class="vwh2-masthead__logo">
		</a>
		<?php if ( vw_chrome_motto() ) : ?>
				<p class="vwh2-masthead__motto"><?php echo esc_html( vw_chrome_motto() ); ?></p>
			<?php endif; ?>
		<nav class="vwh2-masthead__nav" aria-label="Sections">
			<?php foreach ( $vw_nav as $vw_slug => $vw_label ) :
				$vw_term = get_category_by_slug( $vw_slug );
				if ( ! $vw_term ) continue;
				?>
				<a href="<?php echo esc_url( get_category_link( $vw_term->term_id ) ); ?>" class="vwh2-masthead__nav-item<?php echo ( vw_nav_active_slug() === $vw_slug ) ? ' vwh2-masthead__nav-item--active' : ''; ?>"<?php echo ( vw_nav_active_slug() === $vw_slug ) ? ' aria-current="page"' : ''; ?>><?php echo wp_kses( $vw_label, [] ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
	<hr class="vwh2-rule vwh2-rule--heavy">

	<?php
	/* ── Lead ──────────────────────────────────────────────────────────
	   Text variant: the image column is dropped entirely and the block becomes
	   a single text column carrying the Tier-0 red rule. The headline is
	   already clamp()-sized, so it fills the reclaimed width on its own. */
	$lead = vw_curation_slot_by_role( $vw_lead, 'feat' );
	if ( $lead ) :
		$lp       = $lead['post'];
		$lead_img = $vw_img( $lead, 'large', 'vwh2-lead__img' );
		$lead_dek = vw_get_excerpt( $lp, 40 );
		$lead_cap = $lead_img
			? trim( (string) wp_get_attachment_caption( get_post_thumbnail_id( $lp->ID ) ) )
			: '';
		?>
	<section class="vwh2-lead<?php echo $lead_img ? '' : ' vwh2-lead--text'; ?>">
		<div class="vwh2-lead__text">
			<?php $vw_kicker( $lp ); ?>
			<h1 class="vwh2-lead__hed"><a href="<?php echo esc_url( get_permalink( $lp ) ); ?>"><?php echo esc_html( get_the_title( $lp ) ); ?></a></h1>
			<?php if ( $lead_dek ) : ?>
				<p class="vwh2-lead__dek"><?php echo esc_html( $lead_dek ); ?></p>
			<?php endif; ?>
			<span class="vwh2-byline"><?php echo vw_credits_inline( $lp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<?php if ( $lead_img ) : ?>
			<div class="vwh2-lead__img-col">
				<a href="<?php echo esc_url( get_permalink( $lp ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo $lead_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
		<?php endif; ?>
	</section>
		<?php if ( $lead_cap ) : ?>
	<p class="vwh2-lead__caption"><?php echo esc_html( $lead_cap ); ?></p>
		<?php endif; ?>
	<hr class="vwh2-rule">
	<?php endif; ?>

	<?php
	/* ── This Week ─────────────────────────────────────────────────────
	   Hidden at launch per decision C1-1: the registry ships this zone with
	   default_visible => false, so the resolver returns nothing and this block
	   never prints. Switching it on is an admin checkbox, not a code change. */
	if ( $vw_thisweek ) :
		?>
	<div class="vwh2-thisweek">
		<span class="vwh2-thisweek__label">This Week →</span>
		<?php foreach ( $vw_thisweek as $slot ) :
			$p = $slot['post'];
			?>
			<div class="vwh2-thisweek__item">
				<span class="vwh2-thisweek__day"><?php echo esc_html( get_the_date( 'D', $p ) ); ?></span>
				<a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
			</div>
		<?php endforeach; ?>
	</div>
	<hr class="vwh2-rule">
	<?php endif; ?>

	<?php
	/* ── A La Music ────────────────────────────────────────────────────
	   Text variant: the image column collapses and the featured text takes its
	   place; the ruled stack beside it is unaffected. */
	$music_feat  = vw_curation_slot_by_role( $vw_music, 'feat' );
	$music_stack = vw_curation_slots_by_role( $vw_music, 'compact' );
	if ( $music_feat || $music_stack ) :
		$music_img = $music_feat ? $vw_img( $music_feat, 'medium_large', 'vwh2-music__img' ) : '';
		?>
	<div class="vwh2-zonehead">
		<span class="vwh2-mark vwh2-mark--music"></span>
		<a class="vwh2-zonehead__title" href="<?php echo esc_url( $vw_cat_url( 7 ) ); ?>">A La Music</a>
		<a href="<?php echo esc_url( $vw_cat_url( 7 ) ); ?>" class="vwh2-more">All Music →</a>
	</div>
	<div class="vwh2-music<?php echo $music_img ? '' : ' vwh2-music--text'; ?>">
		<?php if ( $music_img ) : ?>
			<a href="<?php echo esc_url( get_permalink( $music_feat['post'] ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo $music_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php endif; ?>

		<?php
		if ( $music_feat ) :
			$mp      = $music_feat['post'];
			$mus_dek = vw_get_excerpt( $mp, 28 );
			?>
		<div>
			<h3 class="vwh2-music__hed"><a href="<?php echo esc_url( get_permalink( $mp ) ); ?>"><?php echo esc_html( get_the_title( $mp ) ); ?></a></h3>
			<?php if ( $mus_dek ) : ?>
				<p class="vwh2-music__dek"><?php echo esc_html( $mus_dek ); ?></p>
			<?php endif; ?>
			<span class="vwh2-byline"><?php echo vw_byline_inner( $mp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<?php endif; ?>

		<div class="vwh2-music__rule"></div>

		<div class="vwh2-music__stack">
			<?php foreach ( $music_stack as $slot ) :
				$p = $slot['post'];
				?>
				<div>
					<a class="vwh2-music__stack-hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
					<span class="vwh2-byline"><?php echo vw_byline_inner( $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<hr class="vwh2-rule">
	<?php endif; ?>

</div>

<?php
/* ── Photography band (full bleed) ──────────────────────────────────────
   This zone's missing-image rule is stricter than the others, deliberately: a
   photography band without photographs is not a degraded band, it is the wrong
   band. If the essay slot could not meet its image requirement the whole
   section is skipped. The strip renders only the thumbs that qualify and is
   dropped below two, so a half-empty 4-up grid never ships. */
$photo_essay  = vw_curation_slot_by_role( $vw_photo, 'essay' );
$photo_sub    = vw_curation_slot_by_role( $vw_photo, 'compact' );
$photo_thumbs = array_values( array_filter(
	vw_curation_slots_by_role( $vw_photo, 'thumb' ),
	static fn( $s ) => ! $s['text_variant']
) );

if ( $photo_essay && ! $photo_essay['text_variant'] ) :
	$ep     = $photo_essay['post'];
	$ep_dek = vw_get_excerpt( $ep, 26 );
	?>
	<section class="vwh2-photo">
		<div class="vwh2-container">
			<div class="vwh2-photo__head">
				<span class="vwh2-mark vwh2-mark--photo"></span><a class="vwh2-photo__head-link" href="<?php echo esc_url( $vw_cat_url( 6 ) ); ?>">Photography</a>
				<a href="<?php echo esc_url( $vw_cat_url( 6 ) ); ?>" class="vwh2-more">All Photo Essays →</a>
			</div>
			<div class="vwh2-photo__inner">
				<a href="<?php echo esc_url( get_permalink( $ep ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo $vw_img( $photo_essay, 'large', 'vwh2-photo__img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<div class="vwh2-photo__text">
					<span class="vwh2-photo__eyebrow">Photo Essay</span>
					<h2 class="vwh2-photo__hed"><a href="<?php echo esc_url( get_permalink( $ep ) ); ?>"><?php echo esc_html( get_the_title( $ep ) ); ?></a></h2>
					<?php if ( $ep_dek ) : ?>
						<p class="vwh2-photo__dek"><?php echo esc_html( $ep_dek ); ?></p>
					<?php endif; ?>
					<span class="vwh2-photo__byline"><?php echo vw_byline_inner( $ep ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

					<?php if ( $photo_sub ) : $sp = $photo_sub['post']; ?>
						<div class="vwh2-photo__sub">
							<a class="vwh2-photo__sub-hed" href="<?php echo esc_url( get_permalink( $sp ) ); ?>"><?php echo esc_html( get_the_title( $sp ) ); ?></a>
							<span class="vwh2-photo__byline"><?php echo vw_byline_inner( $sp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( count( $photo_thumbs ) >= 2 ) : ?>
				<div class="vwh2-photo__strip">
					<?php foreach ( $photo_thumbs as $slot ) : $tp = $slot['post']; ?>
						<a href="<?php echo esc_url( get_permalink( $tp ) ); ?>" class="vwh2-photo__thumb"><?php echo $vw_img( $slot, 'medium_large', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<div class="vwh2-container">

	<?php
	/* ── Tri-column: Food / Political / Books ──────────────────────────
	   Each column is featured + up to two compact slots (the tri-zone density
	   fix). Food and Books carry an image; Political is image-free BY DESIGN —
	   the pull-quote treatment — and the measurement endorses it: 44 of its 55
	   posts are tier 0. Food is the thin one: 27 posts, 4 usable images, so per
	   decision (a) it runs the text variant most of the time and gets widened
	   by category cleanup after launch. */
	$vw_tri = [
		[ 'label' => 'Food & Drink',        'more' => 'All Food & Drink →', 'cat' => 13, 'mark' => 'food',      'slots' => $vw_food,      'img' => 'vwh2-tri__img' ],
		[ 'label' => 'Political Megaphone', 'more' => 'All Political →',    'cat' => 18, 'mark' => 'political', 'slots' => $vw_political, 'img' => '' ],
		[ 'label' => 'Book Reviews',        'more' => 'All Books →',        'cat' => 30, 'mark' => 'books',     'slots' => $vw_books,     'img' => 'vwh2-tri__img vwh2-tri__img--sm' ],
	];
	$vw_tri = array_values( array_filter( $vw_tri, static fn( $c ) => (bool) $c['slots'] ) );

	if ( $vw_tri ) :
		?>
	<div class="vwh2-tri">
		<?php
		foreach ( $vw_tri as $i => $col ) :
			$feat     = vw_curation_slot_by_role( $col['slots'], 'feat' );
			$compacts = vw_curation_slots_by_role( $col['slots'], 'compact' );
			$is_quote = '' === $col['img'];
			$col_img  = ( $feat && ! $is_quote ) ? $vw_img( $feat, 'medium_large', $col['img'] ) : '';

			$classes = 'vwh2-tri__col';
			if ( $is_quote ) {
				$classes .= ' vwh2-tri__col--quote';
			} elseif ( ! $col_img ) {
				$classes .= ' vwh2-tri__col--text';
			}

			if ( $i > 0 ) {
				echo '<div class="vwh2-tri__rule"></div>';
			}
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<div class="vwh2-tri__col-hed">
					<span class="vwh2-mark vwh2-mark--<?php echo esc_attr( $col['mark'] ); ?>"></span><a class="vwh2-tri__col-link" href="<?php echo esc_url( $vw_cat_url( $col['cat'] ) ); ?>"><?php echo esc_html( $col['label'] ); ?></a>
					<a href="<?php echo esc_url( $vw_cat_url( $col['cat'] ) ); ?>" class="vwh2-more"><?php echo esc_html( $col['more'] ); ?></a>
				</div>

				<?php
				if ( $feat ) :
					$fp     = $feat['post'];
					$fp_dek = vw_get_excerpt( $fp, $is_quote ? 30 : 20 );

					if ( $col_img ) :
						?>
						<a href="<?php echo esc_url( get_permalink( $fp ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo $col_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<?php
					endif;

					if ( $is_quote && $fp_dek ) :
						?>
						<p class="vwh2-tri__quote">&ldquo;<?php echo esc_html( $fp_dek ); ?>&rdquo;</p>
						<?php
					endif;
					?>
					<a class="vwh2-tri__hed" href="<?php echo esc_url( get_permalink( $fp ) ); ?>"><?php echo esc_html( get_the_title( $fp ) ); ?></a>

					<?php if ( ! $is_quote && $fp_dek ) : ?>
						<p class="vwh2-tri__dek"><?php echo esc_html( $fp_dek ); ?></p>
					<?php endif; ?>

					<span class="vwh2-byline"><?php echo vw_byline_inner( $fp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>

				<?php
				foreach ( $compacts as $slot ) :
					$cp   = $slot['post'];
					$ceyb = vw_primary_cat_name( (int) $cp->ID, [ $col['cat'] ] );
					?>
					<div class="vwh2-tri__sub">
						<?php if ( $ceyb ) : ?>
							<span class="vwh2-tri__sub-eyebrow"><?php echo esc_html( $ceyb ); ?></span>
						<?php endif; ?>
						<a class="vwh2-tri__hed vwh2-tri__hed--sub" href="<?php echo esc_url( get_permalink( $cp ) ); ?>"><?php echo esc_html( get_the_title( $cp ) ); ?></a>
						<span class="vwh2-byline"><?php echo vw_byline_inner( $cp ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<hr class="vwh2-rule">
	<?php endif; ?>

	<?php
	/* ── Archive closer ────────────────────────────────────────────────
	   The story count is live: wp_count_posts(), 3,373 against the phase-1
	   mockup's hardcoded "16,412".

	   Both years quote the founding year from Site settings. An earlier version derived the "since" year
	   from the oldest published post, which was defensible while the founding
	   year was thought to be 2006 and the data started in 2010. With the year
	   corrected to 2012 that split stopped being useful: the archive holds 481
	   posts in 2012 and exactly 4 before it, so deriving from the data would
	   advertise "since 2010" on the strength of four outliers. Those four are a
	   data-review item, not the start of the publication. */
	$vw_total = (int) wp_count_posts( 'post' )->publish;
	$vw_years = vw_chrome_years();
	$vw_issue = vw_curation_slot_by_role( $vw_archive, 'issue' );
	?>
	<div class="vwh2-archive">
		<div class="vwh2-archive__inner">
			<div>
				<span class="vwh2-archive__eyebrow">From the Archive</span>
				<h2 class="vwh2-archive__hed">
					<?php echo esc_html( number_format_i18n( $vw_years ) ); ?> years,<br>
					<?php echo esc_html( number_format_i18n( $vw_total ) ); ?> stories.
				</h2>
				<p class="vwh2-archive__line">
					<?php
					printf(
						'Every issue since %s, rebuilt and readable. The record of the city&rsquo;s culture doesn&rsquo;t expire.',
						esc_html( (string) vw_chrome_founded() )
					);
					?>
				</p>
				<a href="<?php echo esc_url( $vw_cat_url( 7 ) ); ?>" class="vwh2-archive__cta">Browse the Archive →</a>
			</div>

			<?php
			if ( $vw_issue ) :
				$ip     = $vw_issue['post'];
				$ip_dek = vw_get_excerpt( $ip, 26 );
				?>
			<a href="<?php echo esc_url( get_permalink( $ip ) ); ?>" class="vwh2-archive__issue">
				<span class="vwh2-archive__issue-top">
					<span>From <strong><?php echo esc_html( get_the_date( 'Y', $ip ) ); ?></strong></span>
					<span><?php echo esc_html( get_the_date( 'F j, Y', $ip ) ); ?></span>
				</span>
				<span class="vwh2-archive__issue-hed"><?php echo esc_html( get_the_title( $ip ) ); ?></span>
				<?php if ( $ip_dek ) : ?>
					<span class="vwh2-archive__issue-quote"><?php echo esc_html( $ip_dek ); ?></span>
				<?php endif; ?>
				<span class="vwh2-archive__issue-byline"><?php echo vw_byline_inner( $ip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</a>
			<?php endif; ?>
		</div>
	</div>

</div>

<!-- Footer -->
<div class="vwh2-container">
	<div class="vwh2-footer">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW_wordmark.svg' ); ?>" alt="Vancouver Weekly" class="vwh2-footer__logo">
		</a>
		<nav class="vwh2-footer__nav" aria-label="Sections">
			<?php foreach ( $vw_nav as $vw_slug => $vw_label ) :
				$vw_term = get_category_by_slug( $vw_slug );
				if ( ! $vw_term ) continue;
				?>
				<a href="<?php echo esc_url( get_category_link( $vw_term->term_id ) ); ?>"><?php echo wp_kses( $vw_label, [] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<span class="vwh2-footer__tag">Independent Since <?php echo esc_html( (string) vw_chrome_founded() ); ?></span>
	</div>
</div>
