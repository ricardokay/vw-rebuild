<?php
/**
 * Template Name: VW Homepage Preview
 *
 * RETIRED at cutover — this template now redirects to the front page.
 *
 * It existed so the homepage could be reviewed while page 9 was still live.
 * front-page.php now renders the same module at "/", so this surface is a
 * duplicate of the homepage at a second URL: bad for search engines, and a
 * place where a stale preview could drift from the real thing unnoticed.
 *
 * A 301 in the template rather than a redirect plugin or a database change:
 * the project's URL rules forbid a redirect plugin sitting between a reader and
 * any content URL, and no option or post row is touched here. Removing this
 * file restores the old behaviour; page 86013 itself is untouched and is still
 * private.
 *
 * The page can be deleted whenever Ricardo wants, along with page 9 — both are
 * queued for a gated cleanup round, not done here.
 */

defined( 'ABSPATH' ) || exit;

wp_safe_redirect( home_url( '/' ), 301 );
exit;
