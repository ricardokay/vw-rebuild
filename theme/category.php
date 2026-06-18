<?php
/**
 * Category archive router.
 *
 * Curated sections: renders section header + do_blocks() on a .html
 * template containing a newspack-blocks/homepage-articles block.
 * Everything else falls through to the Newspack parent archive.php.
 *
 * To add a section: add its slug to $curated AND create section-parts/{slug}.html.
 * No other code changes needed.
 */

$curated = [ 'a-la-music', 'out-n-about', 'must-see-films', 'photography', 'food-drink' ];

$section_display_names = [
	'a-la-music'    => 'A La Music',
	'photography'   => 'Photography',
	'food-drink'    => 'Food & Drink',
	'out-n-about'   => 'Out N About',
	'must-see-films' => 'Must See Films',
];

$slug     = ( get_queried_object() instanceof WP_Term ) ? get_queried_object()->slug : '';
$dir      = get_stylesheet_directory() . '/section-parts/';
$tpl_php  = in_array( $slug, $curated, true ) ? $dir . $slug . '.php'  : '';
$tpl_html = in_array( $slug, $curated, true ) ? $dir . $slug . '.html' : '';

$use_php  = $tpl_php  && file_exists( $tpl_php );
$use_html = ! $use_php && $tpl_html && file_exists( $tpl_html );

if ( $use_php || $use_html ) {
    $cat = get_queried_object();
    get_header();
    ?>

    <div class="vw-section-landing">

        <header class="vw-section-header">
            <div class="vw-section-header__inner">
                <span class="vw-section-mark vw-section-mark--<?php echo esc_attr( $slug ); ?>"></span>
                <h1 class="vw-section-header__title"><?php echo esc_html( $section_display_names[ $slug ] ?? $cat->name ); ?></h1>
                <?php if ( $cat->description ) : ?>
                    <p class="vw-section-header__desc"><?php echo esc_html( $cat->description ); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="vw-section-blocks">
            <?php if ( $use_php ) : ?>
                <?php include $tpl_php; ?>
            <?php else : ?>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo do_blocks( file_get_contents( $tpl_html ) );
                ?>
            <?php endif; ?>
        </div>

    </div><!-- .vw-section-landing -->

    <?php
    get_footer();
    return;
}

// Not a curated section — use Newspack's default archive.
include get_template_directory() . '/archive.php';
