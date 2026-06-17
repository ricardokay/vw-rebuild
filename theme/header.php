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
        <a href="<?php echo esc_url( home_url( '/a-la-music/' ) ); ?>"
           class="vw-nav__link<?php echo is_category( 'a-la-music' ) ? ' vw-nav__link--active' : ''; ?>">
          A La Music
        </a>
        <a href="<?php echo esc_url( home_url( '/photography/' ) ); ?>"
           class="vw-nav__link<?php echo is_category( 'photography' ) ? ' vw-nav__link--active' : ''; ?>">
          Photography
        </a>
        <a href="<?php echo esc_url( home_url( '/food-drink/' ) ); ?>"
           class="vw-nav__link<?php echo is_category( 'food-drink' ) ? ' vw-nav__link--active' : ''; ?>">
          Food &amp; Drink
        </a>
        <a href="<?php echo esc_url( home_url( '/out-n-about/' ) ); ?>"
           class="vw-nav__link<?php echo is_category( 'out-n-about' ) ? ' vw-nav__link--active' : ''; ?>">
          Out N About
        </a>
      </nav>

    </div>
  </header>

  <div id="content" class="site-content">
