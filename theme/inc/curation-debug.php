<?php
/**
 * TEMPORARY DIAGNOSTICS for the drag-reorder regression.
 *
 * Added 2026-09-11 because every harness-based test passed while the real
 * wp-admin page failed. The harness is therefore not the truth, and this file
 * exists to instrument the real page instead of simulating it.
 *
 * Off unless explicitly switched on: append ?vwc_debug=1 to the admin screen's
 * URL. The flag is carried through the save POST by a hidden input and back
 * through the redirect, so one page load, one drag and one save produce a
 * complete trace on both sides.
 *
 * DELETE THIS FILE and its require in functions.php once the bug is closed.
 */

defined( 'ABSPATH' ) || exit;

const VW_DEBUG_LOG = 'vwc-debug.log';

/** Debug mode is capability-gated like everything else on this screen. */
function vw_curation_debug(): bool {
	if ( ! current_user_can( 'vw_curate' ) ) {
		return false;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flag; the save handler verifies its own nonce before acting.
	$get  = isset( $_GET['vwc_debug'] ) ? sanitize_text_field( wp_unslash( $_GET['vwc_debug'] ) ) : '';
	$post = isset( $_POST['vwc_debug'] ) ? sanitize_text_field( wp_unslash( $_POST['vwc_debug'] ) ) : '';
	// phpcs:enable
	return '1' === $get || '1' === $post;
}

/** Absolute path of the diagnostic log, and the URL Ricardo can open it at. */
function vw_curation_debug_log_path(): string {
	return trailingslashit( wp_get_upload_dir()['basedir'] ) . VW_DEBUG_LOG;
}
function vw_curation_debug_log_url(): string {
	return trailingslashit( wp_get_upload_dir()['baseurl'] ) . VW_DEBUG_LOG;
}

/**
 * Append a block to the diagnostic log and to PHP's error log.
 *
 * Two destinations on purpose: error_log() is where a developer looks, and the
 * uploads file is something Ricardo can open in a browser and paste.
 */
function vw_curation_debug_log( string $label, $data ): void {
	$block = sprintf(
		"[%s] %s\n%s\n",
		date_i18n( 'Y-m-d H:i:s' ),
		$label,
		is_scalar( $data ) ? (string) $data : print_r( $data, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	);
	error_log( 'VWC-DEBUG ' . $block ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	@file_put_contents( vw_curation_debug_log_path(), $block . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * What PHP ACTUALLY received, logged before the sanitizer touches it.
 *
 * The divergence between this and the browser's own FormData dump is the bug.
 * The counts matter as much as the values: PHP silently truncates a POST that
 * exceeds max_input_vars, which would drop trailing fields with no error and
 * would look exactly like "the order reverted".
 */
add_action( 'vw_curation_before_sanitize', 'vw_curation_debug_capture_post', 10, 1 );
function vw_curation_debug_capture_post( $raw ): void {
	if ( ! vw_curation_debug() ) {
		return;
	}

	$content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : -1;
	$leaves         = 0;
	array_walk_recursive( $_POST, static function () use ( &$leaves ) { $leaves++; } );

	vw_curation_debug_log( 'PHP RECEIVED — envelope', [
		'CONTENT_LENGTH'       => $content_length,
		'post_leaf_values'     => $leaves,
		'max_input_vars'       => ini_get( 'max_input_vars' ),
		'post_max_size'        => ini_get( 'post_max_size' ),
		'suhosin_present'      => extension_loaded( 'suhosin' ) ? 'yes' : 'no',
		'top_level_post_keys'  => array_keys( (array) $_POST ),
	] );

	$zones = [];
	foreach ( (array) ( $raw['home'] ?? [] ) as $zone => $cfg ) {
		$zones[ $zone ] = [
			'present' => $cfg['present'] ?? '(ABSENT)',
			'visible' => $cfg['visible'] ?? '(absent)',
			'slots'   => $cfg['slots'] ?? '(ABSENT)',
		];
	}
	vw_curation_debug_log( 'PHP RECEIVED — home zones, verbatim', $zones );
}

/** What the sanitizer decided, so the two can be compared side by side. */
add_action( 'vw_curation_after_sanitize', 'vw_curation_debug_capture_clean', 10, 1 );
function vw_curation_debug_capture_clean( $clean ): void {
	if ( ! vw_curation_debug() ) {
		return;
	}
	$zones = [];
	foreach ( (array) ( $clean['home'] ?? [] ) as $zone => $cfg ) {
		$zones[ $zone ] = $cfg['slots'];
	}
	vw_curation_debug_log( 'PHP STORED — home zones after sanitize', $zones );
}

/** Hidden input so the flag survives the POST to admin-post.php. */
add_action( 'vw_curation_form_top', 'vw_curation_debug_form_field' );
function vw_curation_debug_form_field(): void {
	if ( ! vw_curation_debug() ) {
		return;
	}
	echo '<input type="hidden" name="vwc_debug" value="1">';
	printf(
		'<div class="notice notice-warning"><p><strong>Diagnostics are ON.</strong> '
		. 'Server-side trace is appended to <a href="%s" target="_blank"><code>%s</code></a>. '
		. 'Remove <code>?vwc_debug=1</code> from the URL to switch it off.</p></div>',
		esc_url( vw_curation_debug_log_url() ),
		esc_html( VW_DEBUG_LOG )
	);
}

/** Carry the flag back through the post-save redirect. */
add_filter( 'vw_curation_redirect_args', 'vw_curation_debug_redirect_args' );
function vw_curation_debug_redirect_args( array $args ): array {
	if ( vw_curation_debug() ) {
		$args['vwc_debug'] = '1';
	}
	return $args;
}
