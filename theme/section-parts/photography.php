<?php
/**
 * Photography section front — four-zone custom render.
 * Included by category.php inside .vw-section-blocks.
 *
 * Zone A — Lead block: 3-col symmetric (text list | image anchor | text list).
 * Zone B — Featured + list: 1 image lead + 5 compact text items.
 * Zone C — Headline list: 10 posts, 2-col newspaper layout.
 * Zone D — Card grid: 6 posts, image or text-forward per tier.
 */

$cats     = [ 6 ]; // photography
$used_ids = [];

$vw_get_excerpt = static function ( WP_Post $post ): string {
	$manual = trim( wp_strip_all_tags( $post->post_excerpt ) );
	if ( $manual ) return $manual;
	$content = strip_shortcodes( wp_strip_all_tags( $post->post_content ) );
	return wp_trim_words( $content, 25, '…' );
};


/* ── Zone A: Lead block ───────────────────────────────────────── */

$anchor = null;

$sticky_ids = get_option( 'sticky_posts' );
if ( $sticky_ids ) {
	$q = new WP_Query( [
		'post__in'       => $sticky_ids,
		'category__in'   => $cats,
		'posts_per_page' => 10,
		'no_found_rows'  => true,
	] );
	while ( $q->have_posts() ) {
		$q->the_post();
		if ( vw_image_tier( get_the_ID() ) >= 1 ) {
			$anchor = get_post();
			break;
		}
	}
	wp_reset_postdata();
}

if ( ! $anchor ) {
	$q = new WP_Query( [
		'category__in'   => $cats,
		'posts_per_page' => 30,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	] );
	while ( $q->have_posts() ) {
		$q->the_post();
		if ( vw_image_tier( get_the_ID() ) >= 1 ) {
			$anchor = get_post();
			break;
		}
	}
	wp_reset_postdata();
}

if ( $anchor ) $used_ids[] = $anchor->ID;

$anchor2 = null;
$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 1,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );
if ( $q->have_posts() ) {
	$q->the_post();
	$anchor2 = get_post();
}
wp_reset_postdata();
if ( $anchor2 ) $used_ids[] = $anchor2->ID;

$left_posts = [];
$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 5,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );
while ( $q->have_posts() ) {
	$q->the_post();
	$left_posts[] = get_post();
}
wp_reset_postdata();
foreach ( $left_posts as $p ) $used_ids[] = $p->ID;

$right_posts = [];
$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 5,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );
while ( $q->have_posts() ) {
	$q->the_post();
	$right_posts[] = get_post();
}
wp_reset_postdata();
foreach ( $right_posts as $p ) $used_ids[] = $p->ID;

if ( $anchor ) :
	$anchor_img = wp_get_attachment_image_src( get_post_thumbnail_id( $anchor->ID ), 'large' );
	$anchor_cat = vw_primary_cat_name( $anchor->ID, $cats );
	$anchor_dek = $vw_get_excerpt( $anchor );
	?>
	<div class="vw-module vw-module--lead">
		<div class="vw-module__inner">
			<div class="vw-lead-block">

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
						$a2_cat = vw_primary_cat_name( $anchor2->ID, $cats );
					?>
					<hr class="vw-lead-block__divider">
					<?php if ( $a2_cat ) : ?>
						<span class="vw-kicker vw-kicker--sm"><?php echo esc_html( $a2_cat ); ?></span>
					<?php endif; ?>
					<a class="vw-lead-block__sub-hed" href="<?php echo esc_url( get_permalink( $anchor2 ) ); ?>"><?php echo esc_html( get_the_title( $anchor2 ) ); ?></a>
					<span class="vw-byline vw-byline--sm">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $anchor2->post_author ) ); ?></strong></span>
					<?php endif; ?>
				</div>

				<div class="vw-lead-block__col vw-lead-block__col--left">
					<?php if ( $left_posts ) : ?>
					<ul class="vw-lead-block__list">
						<?php foreach ( $left_posts as $p ) :
							$lcat = vw_primary_cat_name( $p->ID, $cats );
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

				<div class="vw-lead-block__col vw-lead-block__col--right">
					<?php if ( $right_posts ) : ?>
					<ul class="vw-lead-block__list">
						<?php foreach ( $right_posts as $p ) :
							$rcat = vw_primary_cat_name( $p->ID, $cats );
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


/* ── Zone B: Featured + list ──────────────────────────────────── */

$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );

$feat_posts = [];
while ( $q->have_posts() ) {
	$q->the_post();
	$feat_posts[] = get_post();
}
wp_reset_postdata();

if ( $feat_posts ) {
	foreach ( $feat_posts as $p ) $used_ids[] = $p->ID;
	$lead      = array_shift( $feat_posts );
	$lead_tier = vw_image_tier( $lead->ID );
	$lead_cat  = vw_primary_cat_name( $lead->ID, $cats );
	?>
	<div class="vw-module">
		<div class="vw-module__inner">
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
					<?php foreach ( $feat_posts as $p ) :
						$item_cat = vw_primary_cat_name( $p->ID, $cats );
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
	</div>
	<?php
}


/* ── Zone C: Headline list 2-col ──────────────────────────────── */

$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 10,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );

$hl_posts = [];
while ( $q->have_posts() ) {
	$q->the_post();
	$hl_posts[] = get_post();
}
wp_reset_postdata();

if ( $hl_posts ) {
	foreach ( $hl_posts as $p ) $used_ids[] = $p->ID;
	?>
	<div class="vw-module">
		<div class="vw-module__inner">
			<ul class="vw-hl-list vw-hl-list--2col">
				<?php foreach ( $hl_posts as $p ) :
					$hl_cat = vw_primary_cat_name( $p->ID, $cats );
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


/* ── Zone D: Card grid ────────────────────────────────────────── */

$q = new WP_Query( [
	'category__in'   => $cats,
	'post__not_in'   => $used_ids,
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
] );

$grid_posts = [];
while ( $q->have_posts() ) {
	$q->the_post();
	$grid_posts[] = get_post();
}
wp_reset_postdata();

if ( $grid_posts ) {
	?>
	<div class="vw-module vw-module--last">
		<div class="vw-module__inner">
			<div class="vw-grid">
				<?php foreach ( $grid_posts as $p ) :
					$tier     = vw_image_tier( $p->ID );
					$card_cat = vw_primary_cat_name( $p->ID, $cats );
					$is_text  = ( $tier === 0 );
				?>
					<article class="vw-card<?php echo $is_text ? ' vw-card--text' : ''; ?>">
						<?php if ( ! $is_text ) :
							$card_img = wp_get_attachment_image_src( get_post_thumbnail_id( $p->ID ), 'medium_large' );
							if ( $card_img ) :
						?>
							<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true">
								<img
									src="<?php echo esc_url( $card_img[0] ); ?>"
									alt=""
									class="vw-card__img"
									loading="lazy"
								>
							</a>
						<?php endif; endif; ?>
						<div class="vw-card__body">
							<?php if ( $card_cat ) : ?>
								<span class="vw-card__cat"><?php echo esc_html( $card_cat ); ?></span>
							<?php endif; ?>
							<a class="vw-card__hed" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
							<span class="vw-card__byline">By <strong><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></strong></span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}
