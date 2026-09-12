<?php
/**
 * The parent theme's own entry header, reachable after the child override.
 *
 * entry-header.php now answers for articles; anything else — pages above all —
 * still needs Newspack's version, and a child template part cannot call the
 * parent file it shadows. Loading it directly is the escape hatch.
 */

defined( 'ABSPATH' ) || exit;

require get_template_directory() . '/template-parts/header/entry-header.php';
