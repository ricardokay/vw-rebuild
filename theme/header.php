<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">

  <header class="vw-nav" role="banner">
    <div class="vw-nav__inner">

      <a href="<?php echo esc_url( home_url( '/' ) ); ?>"
         class="vw-nav__logo-link"
         aria-label="Vancouver Weekly — Home">
        <img
          src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/logo_VW.png' ); ?>"
          alt="Vancouver Weekly"
          class="vw-nav__logo"
          height="80"
          width="auto"
        >
      </a>

      <nav class="vw-nav__links" aria-label="Main navigation">
        <?php
        // Six sections, matching the homepage-v2 masthead. Links resolve through
        // get_category_link() — the previous hand-built "/a-la-music/" style URLs
        // omitted the /category/ base and every one of them 404'd.
        $vw_nav_sections = [
          'a-la-music'          => 'A La Music',
          'photography'         => 'Photography',
          'food-drink'          => 'Food &amp; Drink',
          'out-n-about'         => 'Out N About',
          'political-megaphone' => 'Political Megaphone',
          'book-reviews'        => 'Book Reviews',
        ];
        foreach ( $vw_nav_sections as $vw_slug => $vw_label ) :
          $vw_term = get_category_by_slug( $vw_slug );
          if ( ! $vw_term ) continue;
          $vw_active = ( vw_nav_active_slug() === $vw_slug ) ? ' vw-nav__link--active' : '';
        ?>
          <a href="<?php echo esc_url( get_category_link( $vw_term->term_id ) ); ?>"
             class="vw-nav__link<?php echo esc_attr( $vw_active ); ?>"
             <?php echo $vw_active ? 'aria-current="page"' : ''; ?>>
            <?php echo wp_kses( $vw_label, [] ); ?>
          </a>
        <?php endforeach; ?>
      </nav>

    </div>
  </header>

  <div id="content" class="site-content">
