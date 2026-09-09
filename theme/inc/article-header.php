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

/** Pull "Photo(s) by X" out of caption text. Returns names in order found. */
function vw_ah_extract_credit( string $text ): array {
	$out = [];
	if ( preg_match_all( '/Photos?\s*(?:by|:)\s*:?\s*([\p{Lu}][\p{L}\'’.-]+(?:\s+[\p{Lu}][\p{L}\'’.-]+){0,3})/u', $text, $m ) ) {
		foreach ( $m[1] as $n ) {
			$out[] = trim( $n );
		}
	}
	return $out;
}

/**
 * Photographer from existing caption credits, or '' when not cleanly derivable.
 * One name renders as-is, two join with "and", three or more are ambiguous and
 * the line is omitted rather than guessed.
 */
function vw_ah_photographer( WP_Post $post ): string {
	$names = [];
	if ( preg_match_all( '#<figcaption[^>]*>(.*?)</figcaption>#is', $post->post_content, $m ) ) {
		foreach ( $m[1] as $cap ) {
			$names = array_merge( $names, vw_ah_extract_credit( wp_strip_all_tags( $cap ) ) );
		}
	}
	if ( ! $names && $post->post_excerpt ) {
		$names = vw_ah_extract_credit( wp_strip_all_tags( $post->post_excerpt ) );
	}

	$uniq = [];
	foreach ( $names as $n ) {
		$uniq[ mb_strtolower( $n ) ] = $n;
	}
	$uniq = array_values( $uniq );

	if ( 1 === count( $uniq ) ) return $uniq[0];
	if ( 2 === count( $uniq ) ) return $uniq[0] . ' and ' . $uniq[1];
	return '';
}

function vw_ah_word_count( WP_Post $post ): int {
	return str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
}

/** Images that will actually render, i.e. after dead-media suppression. */
function vw_ah_photo_count( WP_Post $post ): int {
	$html = function_exists( 'vw_dead_media_filter' )
		? vw_dead_media_filter( $post->post_content )
		: $post->post_content;
	return (int) preg_match_all( '#<img\b#i', $html );
}

/** Photo-led when pictures dominate prose, not merely when pictures exist. */
function vw_ah_is_photo_led( WP_Post $post ): bool {
	$imgs = vw_ah_photo_count( $post );
	if ( $imgs < 5 ) return false;
	return vw_ah_word_count( $post ) < $imgs * 50;
}

function vw_ah_read_time( WP_Post $post ): int {
	return max( 1, (int) round( vw_ah_word_count( $post ) / 230 ) );
}

/**
 * Primary category name + mark modifier. Uncategorized carries no editorial
 * meaning, so it yields nothing and the kicker is skipped entirely.
 */
function vw_ah_section( int $post_id ): array {
	$marks = [
		'a-la-music'          => 'music',
		'live-music-reviews'  => 'music',
		'album-reviews'       => 'music',
		'music-interviews'    => 'music',
		'music-videos'        => 'music',
		'music-editorials'    => 'music',
		'photography'         => 'photo',
		'food-drink'          => 'food',
		'out-n-about'         => 'outabout',
		'political-megaphone' => 'political',
		'book-reviews'        => 'books',
	];
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) return [ '', '' ];

	foreach ( $terms as $t ) {
		if ( isset( $marks[ $t->slug ] ) ) return [ $t->name, $marks[ $t->slug ] ];
	}
	foreach ( $terms as $t ) {
		if ( 'uncategorized' !== $t->slug ) return [ $t->name, '' ];
	}
	return [ '', '' ];
}

/**
 * Credits in two groups: the byline lines, then a single quieter meta line.
 * Hierarchy comes from weight and colour, not from a second font.
 */
function vw_ah_credits( WP_Post $post ): array {
	$bylines   = [];
	$author    = get_the_author_meta( 'display_name', (int) $post->post_author );
	$shooter   = vw_ah_photographer( $post );
	$photo_led = vw_ah_is_photo_led( $post );

	if ( $author )  $bylines[] = [ 'By', $author ];
	if ( $shooter ) $bylines[] = [ 'Photos', $shooter ];

	$meta = get_the_date( 'F j, Y', $post ) . ' · ' . ( $photo_led
		? sprintf( '%d photos', vw_ah_photo_count( $post ) )
		: sprintf( '%d min read', vw_ah_read_time( $post ) ) );

	return [ 'bylines' => $bylines, 'meta' => $meta ];
}

function vw_ah_byline_html( array $bylines, string $sep ): string {
	$parts = [];
	foreach ( $bylines as $b ) {
		list( $label, $value ) = $b;
		$parts[] = '<span class="vw-ah__label">' . esc_html( $label ) . '</span> '
			. '<span class="vw-ah__name">' . esc_html( $value ) . '</span>';
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
