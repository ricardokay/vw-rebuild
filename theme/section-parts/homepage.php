<?php
/**
 * Homepage v2 — contained/full-bleed alternation.
 * Included by the VW Homepage Preview template now, front-page.php after cutover.
 *
 * Structure: masthead (contained) → LEAD (bleed 1) → This Week (contained) →
 * section zones: music/food/out-n-about/books+political (contained) →
 * Photography (bleed 2, near-black spine) → Archive closer (bleed 3).
 * $used_ids threaded start to finish: no story repeats.
 */

$eyebrow_cats = [ 7, 17, 13, 6, 15, 30, 18, 9, 8, 11, 20, 10 ];

$zones = [
	[ 'slug' => 'a-la-music',      'title' => 'A La Music',      'cats' => [ 7, 9, 8, 11, 20, 10 ], 'link_cat' => 7 ],
	[ 'slug' => 'food-drink',      'title' => 'Food & Drink',    'cats' => [ 13 ],                  'link_cat' => 13 ],
	[ 'slug' => 'out-n-about',     'title' => 'Out N About',     'cats' => [ 17 ],                  'link_cat' => 17 ],
	[ 'slug' => 'books-political', 'title' => 'Books & Political','cats' => [ 30, 18 ],             'link_cat' => 30 ],
];

$nav_marks = [
	[ 'slug' => 'a-la-music',      'title' => 'A La Music',       'link_cat' => 7 ],
	[ 'slug' => 'photography',     'title' => 'Photography',      'link_cat' => 6 ],
	[ 'slug' => 'food-drink',      'title' => 'Food & Drink',     'link_cat' => 13 ],
	[ 'slug' => 'out-n-about',     'title' => 'Out N About',      'link_cat' => 17 ],
	[ 'slug' => 'books-political', 'title' => 'Books & Political','link_cat' => 30 ],
];

$used_ids = [];

$vw_fetch = static function ( array $args, array $used_ids ): array {
	$posts = [];
	$q = new WP_Query( array_merge( [
		'post__not_in'  => $used_ids,
		'orderby'       => 'date',
		'order'         => 'DESC',
		'no_found_rows' => true,
	], $args ) );
	while ( $q->have_posts() ) {
		$q->the_post();
		$posts[] = get_post();
	}
	wp_reset_postdata();
	return $posts;
};

$vw_read_time = static function ( WP_Post $post ): int {
	return max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $post->post_content ) ) / 200 ) );
};

?>

<!-- ═══ Masthead (contained) ═══ -->
<div class="vw-module vw-masthead">
	<div class="vw-module__inner">
		<div class="vw-masthead__dateline">
			<span><?php echo esc_html( date_i18n( 'l, F j, Y' ) ); ?> · Vancouver, BC</span>
			<span>No Ads · No Clickbait · Independent Since 2006</span>
		</div>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vw-masthead__logo-link">
			<img
				src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW.png' ); ?>"
				alt="Vancouver Weekly"
				class="vw-masthead__logo"
				width="481" height="112"
			>
		</a>
		<p class="vw-masthead__motto">The Record of the City's Culture — Twenty Years and Counting</p>
		<nav class="vw-masthead__nav" aria-label="Sections">
			<?php foreach ( $nav_marks as $nm ) : ?>
				<a href="<?php echo esc_url( get_category_link( $nm['link_cat'] ) ); ?>" class="vw-masthead__nav-item">
					<span class="vw-section-mark vw-section-mark--<?php echo esc_attr( $nm['slug'] ); ?>"></span>
					<span><?php echo esc_html( $nm['title'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</div>

<?php
/* ── LEAD (bleed 1): one story, image flush to the right viewport edge ── */

$anchor     = null;
$sticky_ids = get_option( 'sticky_posts' );
if ( $sticky_ids ) {
	foreach ( $vw_fetch( [ 'post__in' => $sticky_ids, 'posts_per_page' => 10, 'ignore_sticky_posts' => true ], [] ) as $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) { $anchor = $p; break; }
	}
}
if ( ! $anchor ) {
	foreach ( $vw_fetch( [ 'posts_per_page' => 30 ], [] ) as $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) { $anchor = $p; break; }
	}
}
if ( $anchor ) $used_ids[] = $anchor->ID;

$lead_secondaries = $vw_fetch( [ 'posts_per_page' => 2 ], $used_ids );
foreach ( $lead_secondaries as $p ) $used_ids[] = $p->ID;

if ( $anchor ) :
	$lead_img   = wp_get_attachment_image_src( get_post_thumbnail_id( $anchor->ID ), 'full' );
	$lead_cat   = vw_primary_cat_name( $anchor->ID, $eyebrow_cats );
	$lead_dek   = vw_get_excerpt( $anchor, 40 );
	$lead_mins  = $vw_read_time( $anchor );
	$lead_thumb = get_post_thumbnail_id( $anchor->ID );
	$lead_caption = wp_get_attachment_caption( $lead_thumb );
	?>
	<section class="vw-lead2 vw-bleed">
		<div class="vw-lead2__text">
			<?php if ( $lead_cat ) : ?>
				<span class="vw-kicker"><?php echo esc_html( $lead_cat ); ?></span>
			<?php endif; ?>
			<h1 class="vw-lead2__hed">
				<a href="<?php echo esc_url( get_permalink( $anchor ) ); ?>"><?php echo esc_html( get_the_title( $anchor ) ); ?></a>
			</h1>
			<?php if ( $lead_dek ) : ?>
				<p class="vw-lead2__dek"><?php echo esc_html( $lead_dek ); ?></p>
			<?php endif; ?>
			<span class="vw-byline">
				By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $anchor->post_author ) ); ?></strong>
				&nbsp;·&nbsp;
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $anchor ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y', $anchor ) ); ?></time>
				&nbsp;·&nbsp;<?php echo (int) $lead_mins; ?> min read
			</span>
		</div>
		<div class="vw-lead2__img-col">
			<?php if ( $lead_img ) : ?>
				<a href="<?php echo esc_url( get_permalink( $anchor ) ); ?>">
					<img src="<?php echo esc_url( $lead_img[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $anchor ) ); ?>" class="vw-lead2__img" loading="eager">
				</a>
				<?php if ( $lead_caption ) : ?>
					<p class="vw-lead2__caption"><?php echo esc_html( wp_strip_all_tags( $lead_caption ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $lead_secondaries ) : ?>
	<div class="vw-module vw-lead2-sec">
		<div class="vw-module__inner">
			<div class="vw-lead2-sec__grid">
				<?php foreach ( $lead_secondaries as $p ) :
					$sec_cat = vw_primary_cat_name( $p->ID, $eyebrow_cats );
				?>
					<div class="vw-lead2-sec__item">
						<?php if ( $sec_cat ) : ?>
							<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $sec_cat ); ?></span>
						<?php endif; ?>
						<a class="vw-lead2-sec__hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
						<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php endif;
endif;


/* ── This Week (contained): 3 items, rules above/below ── */

$week_posts = $vw_fetch( [ 'posts_per_page' => 3 ], $used_ids );
foreach ( $week_posts as $p ) $used_ids[] = $p->ID;

if ( $week_posts ) : ?>
	<div class="vw-module vw-thisweek">
		<div class="vw-module__inner">
			<span class="vw-thisweek__label">This Week</span>
			<div class="vw-thisweek__grid">
				<?php foreach ( $week_posts as $p ) :
					$tw_cat = vw_primary_cat_name( $p->ID, $eyebrow_cats );
				?>
					<div class="vw-thisweek__item">
						<?php if ( $tw_cat ) : ?>
							<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $tw_cat ); ?></span>
						<?php endif; ?>
						<a class="vw-thisweek__hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
						<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php endif;


/* ── Section zones (contained): featured + 2 compact + All {section} → ── */

foreach ( $zones as $zone ) :
	$zone_posts = $vw_fetch( [ 'category__in' => $zone['cats'], 'posts_per_page' => 6 ], $used_ids );
	if ( ! $zone_posts ) continue;

	$lead_key = 0;
	foreach ( $zone_posts as $k => $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) { $lead_key = $k; break; }
	}
	$lead = $zone_posts[ $lead_key ];
	unset( $zone_posts[ $lead_key ] );
	$items = array_slice( array_values( $zone_posts ), 0, 2 );

	$used_ids[] = $lead->ID;
	foreach ( $items as $p ) $used_ids[] = $p->ID;

	$zone_url  = get_category_link( $zone['link_cat'] );
	$lead_tier = vw_image_tier( $lead->ID );
	$lead_cat  = vw_primary_cat_name( $lead->ID, $zone['cats'] );
	?>
	<section class="vw-module vw-home-zone vw-home-zone--<?php echo esc_attr( $zone['slug'] ); ?>">
		<div class="vw-module__inner">
			<header class="vw-home-zone__header">
				<span class="vw-section-mark vw-section-mark--<?php echo esc_attr( $zone['slug'] ); ?>"></span>
				<h2 class="vw-home-zone__title"><a href="<?php echo esc_url( $zone_url ); ?>"><?php echo esc_html( $zone['title'] ); ?></a></h2>
				<a class="vw-home-zone__more" href="<?php echo esc_url( $zone_url ); ?>">All <?php echo esc_html( $zone['title'] ); ?> →</a>
			</header>
			<div class="vw-feat-list">
				<div class="vw-feat-list__lead">
					<?php if ( $lead_tier >= 1 ) :
						$lead_img = wp_get_attachment_image_src( get_post_thumbnail_id( $lead->ID ), 'large' );
						if ( $lead_img ) :
					?>
						<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="vw-feat-list__lead-img-wrap">
							<img src="<?php echo esc_url( $lead_img[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $lead ) ); ?>" class="vw-feat-list__lead-img" loading="lazy">
						</a>
					<?php endif; endif; ?>
					<?php if ( $lead_cat ) : ?>
						<span class="vw-kicker"><?php echo esc_html( $lead_cat ); ?></span>
					<?php endif; ?>
					<h3 class="vw-feat-list__hed">
						<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>"><?php echo esc_html( get_the_title( $lead ) ); ?></a>
					</h3>
					<span class="vw-byline">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $lead->post_author ) ); ?></strong></span>
				</div>
				<ul class="vw-feat-list__items">
					<?php foreach ( $items as $p ) :
						$item_cat = vw_primary_cat_name( $p->ID, $zone['cats'] );
					?>
						<li class="vw-feat-list__item">
							<?php if ( $item_cat ) : ?>
								<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $item_cat ); ?></span>
							<?php endif; ?>
							<a class="vw-feat-list__item-hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
							<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</section>
	<?php
endforeach;


/* ── Photography (bleed 2): near-black spine, essay + thumb strip ── */

$photo_posts = $vw_fetch( [ 'category__in' => [ 6 ], 'posts_per_page' => 8 ], $used_ids );
$photo_essay = null;
foreach ( $photo_posts as $k => $p ) {
	if ( vw_image_tier( $p->ID ) >= 1 ) { $photo_essay = $p; unset( $photo_posts[ $k ] ); break; }
}
$photo_thumbs = array_slice( array_values( $photo_posts ), 0, 5 );

if ( $photo_essay ) :
	$used_ids[] = $photo_essay->ID;
	foreach ( $photo_thumbs as $p ) $used_ids[] = $p->ID;

	$essay_img = wp_get_attachment_image_src( get_post_thumbnail_id( $photo_essay->ID ), 'full' );
	?>
	<section class="vw-photo-band vw-bleed">
		<div class="vw-photo-band__essay">
			<div class="vw-photo-band__img-col">
				<?php if ( $essay_img ) : ?>
					<a href="<?php echo esc_url( get_permalink( $photo_essay ) ); ?>">
						<img src="<?php echo esc_url( $essay_img[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $photo_essay ) ); ?>" class="vw-photo-band__img" loading="lazy">
					</a>
				<?php endif; ?>
			</div>
			<div class="vw-photo-band__text">
				<span class="vw-photo-band__kicker">Photography</span>
				<h2 class="vw-photo-band__hed">
					<a href="<?php echo esc_url( get_permalink( $photo_essay ) ); ?>"><?php echo esc_html( get_the_title( $photo_essay ) ); ?></a>
				</h2>
				<span class="vw-photo-band__byline">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $photo_essay->post_author ) ); ?></strong></span>
				<a class="vw-photo-band__more" href="<?php echo esc_url( get_category_link( 6 ) ); ?>">All Photo Essays →</a>
			</div>
		</div>
		<?php if ( $photo_thumbs ) : ?>
		<div class="vw-photo-band__strip">
			<?php foreach ( $photo_thumbs as $p ) :
				$thumb_img = wp_get_attachment_image_src( get_post_thumbnail_id( $p->ID ), 'medium_large' );
				if ( ! $thumb_img ) continue;
			?>
				<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" class="vw-photo-band__thumb">
					<img src="<?php echo esc_url( $thumb_img[0] ); ?>" alt="<?php echo esc_attr( get_the_title( $p ) ); ?>" loading="lazy">
				</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</section>
<?php endif;


/* ── Archive closer (bleed 3): live numeral + one real archive piece ── */

$archive_count = (int) wp_count_posts( 'post' )->publish;
$oldest = $vw_fetch( [ 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'ASC' ], $used_ids );
$oldest = $oldest ? $oldest[0] : null;
if ( $oldest ) $used_ids[] = $oldest->ID;
?>
<section class="vw-archive-closer vw-bleed">
	<div class="vw-archive-closer__inner">
		<div class="vw-archive-closer__numeral-col">
			<span class="vw-archive-closer__numeral"><?php echo esc_html( number_format_i18n( $archive_count ) ); ?></span>
			<p class="vw-archive-closer__line">Twenty years, and <?php echo esc_html( number_format_i18n( $archive_count ) ); ?> stories. Every review, every photo essay, every band that ever played this city — still here, still findable.</p>
			<a class="vw-archive-closer__cta" href="<?php echo $oldest ? esc_url( get_permalink( $oldest ) ) : esc_url( home_url( '/' ) ); ?>">Browse the Archive →</a>
		</div>
		<?php if ( $oldest ) :
			$oldest_cat = vw_primary_cat_name( $oldest->ID, $eyebrow_cats );
		?>
		<a href="<?php echo esc_url( get_permalink( $oldest ) ); ?>" class="vw-archive-closer__issue">
			<span class="vw-archive-closer__issue-date"><?php echo esc_html( get_the_date( 'M j, Y', $oldest ) ); ?></span>
			<?php if ( $oldest_cat ) : ?>
				<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $oldest_cat ); ?></span>
			<?php endif; ?>
			<span class="vw-archive-closer__issue-hed"><?php echo esc_html( get_the_title( $oldest ) ); ?></span>
			<span class="vw-archive-closer__issue-tag">Where it started</span>
		</a>
		<?php endif; ?>
	</div>
</section>
