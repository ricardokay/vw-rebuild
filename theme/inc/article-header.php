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

/**
 * The width at or above which a featured image may run full-bleed.
 *
 * 1200, raised from 1140 at rollout, and it is a pure no-upscale rule rather
 * than a taste threshold: the content column tops out at 1200px, so an image
 * narrower than that would be stretched to fill case A. Case B exists to render
 * those at their natural size instead. Nothing is ever upscaled.
 */
const VW_AH_WIDE_MIN = 1200;

/**
 * The adaptive header is the default on articles as of the 2026-09-12 rollout.
 * ?vw_header=1 is a retired no-op, kept so review links do not change meaning.
 */
function vw_ah_active(): bool {
	return is_singular( 'post' );
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

/**
 * Is the featured image also the first image in the post's body?
 *
 * "Opening" is deliberate: only the first gallery or image block is examined,
 * because a photograph reused far down a long article is not the duplication
 * this guards against — two copies of the same frame within one screen is.
 * WordPress writes the attachment id into the class list as wp-image-N, which
 * is the only marker present on both classic and block galleries in this
 * archive.
 */
function vw_ah_thumb_in_opening_gallery( WP_Post $post, int $thumb_id ): bool {
	if ( ! $thumb_id ) {
		return false;
	}

	$html = function_exists( 'vw_dead_media_filter' )
		? vw_dead_media_filter( $post->post_content )
		: $post->post_content;

	// The opening run of markup: everything up to and including the first
	// gallery, or the first ~3 images, whichever comes first.
	if ( preg_match_all( '#wp-image-(\d+)#', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( array_slice( $m[1], 0, 3 ) as $hit ) {
			if ( (int) $hit[0] === $thumb_id ) {
				return true;
			}
		}
	}

	return false;
}

function vw_ah_render( WP_Post $post ): string {
	list( $section, $mark ) = vw_ah_section( $post->ID );

	$thumb_id = get_post_thumbnail_id( $post->ID );
	$src      = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'full' ) : false;
	$path     = $thumb_id ? get_attached_file( $thumb_id ) : '';
	$has_img  = $src && $path && file_exists( $path );
	$width    = $has_img ? (int) $src[1] : 0;

	// Photo-led dedup: a gallery post whose featured image is also the first
	// picture in the body would otherwise show that photograph twice, once
	// full-bleed and once as frame one of the gallery. Case C — the stacked
	// text header — renders instead, and the gallery keeps the image. Only ever
	// when the SAME attachment is in the opening gallery; a featured image that
	// does not appear in the body is still the header's to show.
	if ( $has_img && vw_ah_thumb_in_opening_gallery( $post, (int) $thumb_id ) ) {
		$has_img = false;
		$width   = 0;
	}

	$case = ! $has_img ? 'c' : ( $width >= VW_AH_WIDE_MIN ? 'a' : 'b' );

	$credits = vw_ah_credits( $post );
	$caption = $thumb_id ? trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $thumb_id ) ) ) : '';

	ob_start();
	?>
	<div class="vw-ah vw-ah--<?php echo esc_attr( $case ); ?>" data-vw-ah-case="<?php echo esc_attr( strtoupper( $case ) ); ?>" data-vw-ah-imgw="<?php echo esc_attr( (string) $width ); ?>">

		<?php if ( $section ) : ?>
			<span class="vw-ah__kicker">
				<?php if ( $mark ) : ?><span class="vw-ah__mark vw-ah__mark--<?php echo esc_attr( $mark ); ?>"></span><?php endif; ?>
				<?php echo esc_html( $section ); ?>
			</span>
		<?php endif; ?>

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

/*
 * The header used to be prepended to the_content, which was the only seam a
 * preview flag could reach without touching templates. The rollout replaced it
 * with a real template part (template-parts/header/entry-header.php), so the
 * header now sits in the document where a header belongs rather than inside the
 * article body — which also means it is no longer re-run by anything else that
 * filters the_content.
 */

add_filter( 'body_class', 'vw_ah_body_class' );
function vw_ah_body_class( $classes ) {
	if ( vw_ah_active() ) $classes[] = 'vw-ah-single';
	return $classes;
}
