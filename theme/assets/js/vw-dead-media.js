/**
 * Client-side net for dead images the server-side filter cannot judge:
 * relative URLs, unknown remote hosts, and files that exist but fail to decode.
 * Hides the whole enclosing element so no orphaned caption is left behind.
 */
( function () {
	function hide( img ) {
		var wrap = img.closest( 'figure, .wp-block-image, .gallery-item' ) || img;
		wrap.classList.add( 'vw-media-dead' );
	}

	// Capture phase: image error events do not bubble.
	document.addEventListener( 'error', function ( e ) {
		if ( e.target && e.target.tagName === 'IMG' ) hide( e.target );
	}, true );

	// Catches failures that resolved before the listener attached.
	function sweep() {
		[].forEach.call( document.images, function ( img ) {
			if ( img.complete && img.naturalWidth === 0 ) hide( img );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', sweep );
	window.addEventListener( 'load', sweep );
} )();
