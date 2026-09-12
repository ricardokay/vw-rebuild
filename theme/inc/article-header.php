<?php
/**
 * Article header — adaptive. PREVIEW ONLY.
 *
 * Reachable only with ?vw_header=1 on a single post, so live singles are
 * untouched until Ricardo signs off. Nothing is written to the database.
 *
 * The header shape follows the picture, not the post type:
 *   A  featured image ≥ VW_AH_WIDE_MIN px  → full-width headline, full-width
 *      image, one-line credit strip between hairlines
 *   B  featured image < VW_AH_WIDE_MIN px  → split row: serif headline and
 *      stacked credits left, image at natural size (never upscaled) right
 *   C  no usable featured image            → stacked headline + credit line
 *
 * There is no dek: the headline is the anchor. (Only 1 of 3,373 published posts
 * carries a manual excerpt, and it holds a photo credit rather than a dek.)
 */

const VW_AH_WIDE_MIN = 1140;

function vw_ah_active(): bool {
	return is_singular( 'post' ) && isset( $_GET['vw_header'] ) && '1' === $_GET['vw_header']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Credit derivation (vw_ah_extract_credit, vw_ah_photographer, vw_ah_word_count,
 * vw_ah_photo_count, vw_ah_is_photo_led, vw_ah_read_time, vw_ah_credits) moved
 * to inc/credits.php in Round 1 session B, unchanged, so the homepage can use
 * the same rules. functions.php requires that file before this one.
 */

function vw_ah_byline_html( array $bylines, string $sep ): string {
	$parts = [];
	foreach ( $bylines as $b ) {
		list( $label, $value ) = $b;
		$parts[] = '<span class="vw-ah__label">' . esc_html( $label ) . '</span> '
			. '<span class="vw-ah__name">' . esc_html( $value ) . '</span>';
		// (Author linking happens in vw_ah_render(), which has the post object.)
	}
	return implode( $sep, $parts );
}

function vw_ah_render( WP_Post $post ): string {
	list( $section, $mark ) = vw_ah_section( $post->ID );

	$thumb_id = get_post_thumbnail_id( $post->ID );
	$src      = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'full' ) : false;
	$path     = $thumb_id ? get_attached_file( $thumb_id ) : '';
	$has_img  = $src && $path && file_exists( $path );
	$width    = $has_img ? (int) $src[1] : 0;

	$case = ! $has_img ? 'c' : ( $width >= VW_AH_WIDE_MIN ? 'a' : 'b' );

	$credits = vw_ah_credits( $post );
	$caption = $thumb_id ? trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $thumb_id ) ) ) : '';

	ob_start();
	?>
	<div class="vw-ah vw-ah--<?php echo esc_attr( $case ); ?>" data-vw-ah-case="<?php echo esc_attr( strtoupper( $case ) ); ?>" data-vw-ah-imgw="<?php echo esc_attr( (string) $width ); ?>">

		<?php
		/*
		 * The kicker grew a second level rather than gaining a neighbour: same
		 * element, same mark, same position, now carrying the full trail with
		 * each level linked. A post whose section has no sub-level renders a
		 * one-item trail, which is visually what the kicker already was.
		 * Uncategorized posts produce no trail and nothing prints.
		 */
		$crumbs = vw_breadcrumb_html( 'vw-ah__kicker', 'vw-ah__mark' );
		if ( $crumbs ) :
			echo $crumbs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in vw_breadcrumb_html().
		elseif ( $section ) :
			?>
			<span class="vw-ah__kicker">
				<?php if ( $mark ) : ?><span class="vw-ah__mark vw-ah__mark--<?php echo esc_attr( $mark ); ?>"></span><?php endif; ?>
				<?php echo esc_html( $section ); ?>
			</span>
			<?php
		endif;
		?>

		<?php if ( 'b' === $case ) : ?>

			<div class="vw-ah__split">
				<div class="vw-ah__split-text">
					<h1 class="vw-ah__hed"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
					<hr class="vw-ah__rule">
					<div class="vw-ah__credits">
						<?php foreach ( $credits['bylines'] as $b ) : ?>
							<p class="vw-ah__credit"><?php echo vw_ah_byline_html( [ $b ], '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endforeach; ?>
						<p class="vw-ah__meta"><?php echo esc_html( $credits['meta'] ); ?></p>
					</div>
				</div>
				<div class="vw-ah__split-media">
					<?php echo wp_get_attachment_image( $thumb_id, 'full', false, [ 'class' => 'vw-ah__img' ] ); ?>
					<?php if ( $caption ) : ?>
						<p class="vw-ah__caption"><?php echo esc_html( $caption ); ?></p>
					<?php endif; ?>
				</div>
			</div>

		<?php else : ?>

			<h1 class="vw-ah__hed"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
			<hr class="vw-ah__rule">

			<?php if ( 'a' === $case ) : ?>
				<div class="vw-ah__media">
					<?php echo wp_get_attachment_image( $thumb_id, 'full', false, [ 'class' => 'vw-ah__img' ] ); ?>
				</div>
				<?php if ( $caption ) : ?>
					<p class="vw-ah__caption"><?php echo esc_html( $caption ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<p class="vw-ah__strip">
				<?php echo vw_ah_byline_html( $credits['bylines'], '<span class="vw-ah__dot"> · </span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $credits['bylines'] ) : ?><span class="vw-ah__dot"> · </span><?php endif; ?>
				<span class="vw-ah__meta-inline"><?php echo esc_html( $credits['meta'] ); ?></span>
			</p>

		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

add_filter( 'the_content', 'vw_ah_prepend', 5 );
function vw_ah_prepend( $html ) {
	if ( ! vw_ah_active() || ! in_the_loop() || ! is_main_query() ) return $html;
	$post = get_post();
	if ( ! $post ) return $html;
	return vw_ah_render( $post ) . $html;
}

add_filter( 'body_class', 'vw_ah_body_class' );
function vw_ah_body_class( $classes ) {
	if ( vw_ah_active() ) $classes[] = 'vw-ah-preview';
	return $classes;
}
