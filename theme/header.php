<?php
/**
 * Site header — the v2 masthead, sitewide.
 *
 * Rolled out 2026-09-12. This REPLACES the old .vw-nav header rather than
 * hiding it: the preview did the latter, which meant the legacy header and its
 * old-lockup raster logo were still shipped to every reader and only painted
 * over. Nothing renders .vw-nav any more, and assets/images/logo_VW.png — the
 * different, tagline-carrying lockup it used — is no longer referenced.
 *
 * The masthead markup lives in inc/masthead.php so that this template, the
 * homepage part and any future surface all render the same thing from one
 * source. ?vw_masthead=1 survives in old links as a harmless no-op.
 */

defined( 'ABSPATH' ) || exit;
?>
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

  <?php
  /*
   * The homepage part renders its own masthead inline, because the masthead is
   * the first element of that composition rather than chrome sitting above it.
   * Printing a second one here would stack two.
   */
  if ( ! is_front_page() ) {
    vw_masthead_render();
  }
  ?>

  <div id="content" class="site-content">
