<?php
/**
 * Vancouver Weekly — Section Landing Template
 *
 * Handles full-bleed category archive pages for the four main
 * sections: A La Music, Photography, Food & Drink, Out N About.
 *
 * Template hierarchy: category-{slug}.php → category.php → archive.php
 * This file should be named category.php or copied per section slug.
 *
 * Role rules enforced here:
 *   - Wrapper class vw-section--{field} sets --vw-section-field and
 *     --vw-section-ink for the page. CSS consumes these; PHP never
 *     outputs raw hex values.
 *   - Text on the field background always uses --vw-ink via CSS.
 *   - Section-colored text on white card backgrounds always uses
 *     --vw-section-ink via CSS (the deep ink, not the field).
 *   - The Photography section skips the duotone filter; its images
 *     render in full original color (enforced via CSS class only).
 */

// Map category slug → section config.
// slug:   the WordPress category slug for this section
// field:  the CSS modifier class suffix (sets --vw-section-field/ink)
// label:  display name for the section header
// filter: SVG filter id to apply to card images (empty = no filter)
$vw_sections = [
    'a-la-music'  => [
        'field'  => 'music',
        'label'  => 'A La Music',
        'filter' => 'vw-dt-music',
    ],
    'photography' => [
        'field'  => 'photo',
        'label'  => 'Photography',
        'filter' => '',              // Photography: full colour, no duotone
    ],
    'food-drink'  => [
        'field'  => 'food',
        'label'  => 'Food &amp; Drink',
        'filter' => 'vw-dt-food',
    ],
    'out-n-about' => [
        'field'  => 'about',
        'label'  => 'Out N About',
        'filter' => 'vw-dt-about',
    ],
];

$current_cat  = get_queried_object();
$section_slug = $current_cat->slug ?? '';
$section      = $vw_sections[ $section_slug ] ?? null;
$field_class  = $section ? 'vw-section--' . $section['field'] : '';
$section_name = $section ? $section['label'] : single_cat_title( '', false );

get_header();
?>

<?php
// Inline the duotone SVG filters so filter: url(#id) resolves.
// The SVG has width/height 0 and position:absolute — invisible.
// Source of truth is theme/duotone-filters.svg; PHP echoes it directly.
$svg_path = get_stylesheet_directory() . '/duotone-filters.svg';
if ( file_exists( $svg_path ) ) {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo file_get_contents( $svg_path );
}
?>

<div class="vw-section-landing <?php echo esc_attr( $field_class ); ?>">

    <!-- ── Section header band ─────────────────────────────────── -->
    <header class="vw-section-header" aria-label="<?php echo esc_attr( $section_name ); ?> section">
        <div class="vw-section-header__inner">

            <?php
            // "Breaking" badge — uses system accent cyan, not section color.
            // Uncomment and set the label from a category ACF field if needed.
            // echo '<div class="vw-section-header__badge">&#9679; Latest</div>';
            ?>

            <h1 class="vw-section-header__name">
                <?php echo wp_kses_post( $section_name ); ?>
            </h1>

            <?php
            $cat_description = category_description();
            if ( $cat_description ) : ?>
                <p class="vw-section-header__meta">
                    <?php echo wp_kses_post( $cat_description ); ?>
                </p>
            <?php endif; ?>

        </div>
    </header>

    <!-- ── Article grid ────────────────────────────────────────── -->
    <section class="vw-section-grid" aria-label="Articles">
        <div class="vw-section-grid__inner">

            <?php if ( have_posts() ) : ?>

                <?php
                $card_index = 0;
                while ( have_posts() ) :
                    the_post();

                    // Determine card variant via image fallback ladder.
                    // Full logic wired in step 4; placeholder logic here.
                    $thumb_id  = get_post_thumbnail_id();
                    $image_src = wp_get_attachment_image_src( $thumb_id, 'large' );
                    $img_w     = $image_src ? $image_src[1] : 0;

                    if ( $img_w >= 600 ) {
                        $card_variant = $card_index === 0 ? 'hero' : 'standard';
                    } else {
                        $card_variant = 'type-forward';
                    }

                    // Pass section context to the card partial.
                    set_query_var( 'vw_card_variant', $card_variant );
                    set_query_var( 'vw_section', $section );
                    get_template_part( 'template-parts/card' );

                    $card_index++;
                endwhile;
                ?>

            <?php else : ?>
                <p class="vw-section-empty" style="color:var(--vw-ink);padding:60px 0;">
                    <?php esc_html_e( 'No articles found in this section.', 'vancouverweekly' ); ?>
                </p>
            <?php endif; ?>

        </div>
    </section>

    <!-- ── Pagination ──────────────────────────────────────────── -->
    <?php if ( $GLOBALS['wp_query']->max_num_pages > 1 ) : ?>
        <nav class="vw-section-pagination" aria-label="Archive pages">
            <?php
            echo paginate_links( [
                'prev_text' => '&larr;',
                'next_text' => '&rarr;',
                'type'      => 'list',
            ] );
            ?>
        </nav>
    <?php endif; ?>

</div><!-- .vw-section-landing -->

<?php get_footer(); ?>
