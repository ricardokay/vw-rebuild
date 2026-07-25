<?php
/**
 * Homepage — alternative-newsweekly front.
 * Included by the VW Homepage Preview template now, front-page.php after cutover.
 *
 * Zone A — content-agnostic lead: sticky post site-wide → newest tier-1 fallback.
 *          3-col symmetric (text list | image anchor | text list), section eyebrows.
 * Zones B–E — four equal-weight peer section zones: A La Music, Out N About,
 *          Food & Drink, Photography. Featured + compact list per zone.
 * Zone F — cross-section 2-col headline stack.
 * $used_ids threaded A→F: no story appears twice.
 */

// Eyebrow preference: top-level sections first, music children after.
$eyebrow_cats = [ 7, 17, 13, 6, 15, 9, 8, 11, 20, 10 ];

$zones = [
	[ 'slug' => 'a-la-music',  'title' => 'A La Music',  'cats' => [ 7, 9, 8, 11, 20, 10 ], 'link_cat' => 7 ],
	[ 'slug' => 'out-n-about', 'title' => 'Out N About', 'cats' => [ 17 ],                  'link_cat' => 17 ],
	[ 'slug' => 'food-drink',  'title' => 'Food & Drink','cats' => [ 13 ],                  'link_cat' => 13 ],
	[ 'slug' => 'photography', 'title' => 'Photography', 'cats' => [ 6 ],                   'link_cat' => 6 ],
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


/* ── Zone A: content-agnostic lead ────────────────────────────── */

// Anchor: any sticky post with a tier-1 image, site-wide. Fallback: newest tier-1 post.
$anchor     = null;
$sticky_ids = get_option( 'sticky_posts' );
if ( $sticky_ids ) {
	foreach ( $vw_fetch( [ 'post__in' => $sticky_ids, 'posts_per_page' => 10, 'ignore_sticky_posts' => true ], [] ) as $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) {
			$anchor = $p;
			break;
		}
	}
}
if ( ! $anchor ) {
	foreach ( $vw_fetch( [ 'posts_per_page' => 30 ], [] ) as $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) {
			$anchor = $p;
			break;
		}
	}
}
if ( $anchor ) $used_ids[] = $anchor->ID;

$anchor2 = null;
foreach ( $vw_fetch( [ 'posts_per_page' => 1 ], $used_ids ) as $p ) $anchor2 = $p;
if ( $anchor2 ) $used_ids[] = $anchor2->ID;

$left_posts = $vw_fetch( [ 'posts_per_page' => 5 ], $used_ids );
foreach ( $left_posts as $p ) $used_ids[] = $p->ID;

$right_posts = $vw_fetch( [ 'posts_per_page' => 5 ], $used_ids );
foreach ( $right_posts as $p ) $used_ids[] = $p->ID;

if ( $anchor ) :
	$anchor_img = wp_get_attachment_image_src( get_post_thumbnail_id( $anchor->ID ), 'large' );
	$anchor_cat = vw_primary_cat_name( $anchor->ID, $eyebrow_cats );
	$anchor_dek = vw_get_excerpt( $anchor );
	?>
	<div class="vw-module vw-module--lead">
		<div class="vw-module__inner">
			<div class="vw-lead-block">

				<!-- Center: image anchor (HTML-first for SEO) -->
				<div class="vw-lead-block__col vw-lead-block__col--center">
					<a href="<?php echo esc_url( get_permalink( $anchor ) ); ?>">
						<img
							src="<?php echo esc_url( $anchor_img[0] ); ?>"
							alt="<?php echo esc_attr( get_the_title( $anchor ) ); ?>"
							class="vw-lead-block__main-img"
							width="<?php echo (int) $anchor_img[1]; ?>"
							height="<?php echo (int) $anchor_img[2]; ?>"
							loading="eager"
						>
					</a>
					<?php if ( $anchor_cat ) : ?>
						<span class="vw-kicker"><?php echo esc_html( $anchor_cat ); ?></span>
					<?php endif; ?>
					<a class="vw-lead-block__main-hed" href="<?php echo esc_url( get_permalink( $anchor ) ); ?>"><?php echo esc_html( get_the_title( $anchor ) ); ?></a>
					<?php if ( $anchor_dek ) : ?>
						<p class="vw-lead-block__main-dek"><?php echo esc_html( $anchor_dek ); ?></p>
					<?php endif; ?>
					<span class="vw-byline">
						By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $anchor->post_author ) ); ?></strong>
						&nbsp;·&nbsp;
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $anchor ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y', $anchor ) ); ?></time>
					</span>

					<?php if ( $anchor2 ) :
						$a2_cat = vw_primary_cat_name( $anchor2->ID, $eyebrow_cats );
					?>
					<hr class="vw-lead-block__divider">
					<?php if ( $a2_cat ) : ?>
						<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $a2_cat ); ?></span>
					<?php endif; ?>
					<a class="vw-lead-block__sub-hed" href="<?php echo esc_url( get_permalink( $anchor2 ) ); ?>"><?php echo esc_html( get_the_title( $anchor2 ) ); ?></a>
					<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $anchor2->post_author ) ); ?></strong></span>
					<?php endif; ?>
				</div>

				<!-- Left: text-only list -->
				<div class="vw-lead-block__col vw-lead-block__col--left">
					<?php if ( $left_posts ) : ?>
					<ul class="vw-lead-block__list">
						<?php foreach ( $left_posts as $p ) :
							$lcat = vw_primary_cat_name( $p->ID, $eyebrow_cats );
						?>
							<li>
								<?php if ( $lcat ) : ?>
									<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $lcat ); ?></span>
								<?php endif; ?>
								<a class="vw-lead-block__list-hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>

				<!-- Right: text-only list -->
				<div class="vw-lead-block__col vw-lead-block__col--right">
					<?php if ( $right_posts ) : ?>
					<ul class="vw-lead-block__list">
						<?php foreach ( $right_posts as $p ) :
							$rcat = vw_primary_cat_name( $p->ID, $eyebrow_cats );
						?>
							<li>
								<?php if ( $rcat ) : ?>
									<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $rcat ); ?></span>
								<?php endif; ?>
								<a class="vw-lead-block__list-hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
								<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</div>

			</div><!-- .vw-lead-block -->
		</div>
	</div>
	<?php
endif;


/* ── Zones B–E: peer section zones ────────────────────────────── */

foreach ( $zones as $zone ) :
	$zone_posts = $vw_fetch( [ 'category__in' => $zone['cats'], 'posts_per_page' => 8 ], $used_ids );
	if ( ! $zone_posts ) continue;

	// Lead: first post with a real image; fall back to newest.
	$lead_key = 0;
	foreach ( $zone_posts as $k => $p ) {
		if ( vw_image_tier( $p->ID ) >= 1 ) {
			$lead_key = $k;
			break;
		}
	}
	$lead = $zone_posts[ $lead_key ];
	unset( $zone_posts[ $lead_key ] );
	$items = array_slice( array_values( $zone_posts ), 0, 5 );

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
				<a class="vw-home-zone__more" href="<?php echo esc_url( $zone_url ); ?>">More <?php echo esc_html( $zone['title'] ); ?> →</a>
			</header>

			<div class="vw-feat-list">

				<div class="vw-feat-list__lead">
					<?php if ( $lead_tier >= 1 ) :
						$lead_img = wp_get_attachment_image_src( get_post_thumbnail_id( $lead->ID ), 'large' );
						if ( $lead_img ) :
					?>
						<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>" class="vw-feat-list__lead-img-wrap">
							<img
								src="<?php echo esc_url( $lead_img[0] ); ?>"
								alt="<?php echo esc_attr( get_the_title( $lead ) ); ?>"
								class="vw-feat-list__lead-img"
								loading="lazy"
							>
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


/* ── Zone F: cross-section headline stack ─────────────────────── */

$hl_posts = $vw_fetch( [ 'posts_per_page' => 12 ], $used_ids );

if ( $hl_posts ) {
	foreach ( $hl_posts as $p ) $used_ids[] = $p->ID;
	?>
	<div class="vw-module vw-module--last">
		<div class="vw-module__inner">
			<header class="vw-home-zone__header">
				<h2 class="vw-home-zone__title">More from the Archive</h2>
			</header>
			<ul class="vw-hl-list vw-hl-list--2col">
				<?php foreach ( $hl_posts as $p ) :
					$hl_cat = vw_primary_cat_name( $p->ID, $eyebrow_cats );
				?>
					<li>
						<div class="vw-hl-item">
							<?php if ( $hl_cat ) : ?>
								<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $hl_cat ); ?></span>
							<?php endif; ?>
							<a class="vw-hl-item__hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
							<span class="vw-byline vw-byline--sm">
								By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong>
								&nbsp;·&nbsp;
								<time datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y', $p ) ); ?></time>
							</span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php
}
