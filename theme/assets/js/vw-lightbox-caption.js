/**
 * VW lightbox caption enhancement.
 * Shows each image's "Photo by …" credit bottom-center while WordPress 7.0's
 * native block lightbox is open.
 *
 * The overlay is a keyed, Preact-reconciled node (data-wp-key + attachTo:body),
 * so we do NOT inject into it — Preact would wipe a foreign child. Instead the
 * caption is a single element parented to <body> (position:fixed, outside
 * Preact's reconciliation) and toggled from the live `.wp-lightbox-overlay.active`.
 */
( function () {
	var ACTIVE = '.wp-lightbox-overlay.active';

	// filename without the -WxH size / -scaled suffix, so grid ('large') and
	// lightbox ('full'/'scaled') sources of the same image resolve to one key.
	function baseName( src ) {
		if ( ! src ) return '';
		return src.split( '?' )[ 0 ].split( '/' ).pop()
			.replace( /-\d+x\d+(\.\w+)$/, '$1' )
			.replace( /-scaled(\.\w+)$/, '$1' );
	}

	// map: image basename -> caption text, built from on-page galleries.
	function captionMap() {
		var map = {};
		document.querySelectorAll( '.wp-block-gallery figure.wp-block-image' ).forEach( function ( fig ) {
			var img = fig.querySelector( 'img' );
			var cap = fig.querySelector( 'figcaption' );
			if ( img && cap ) {
				var t = cap.textContent.trim();
				if ( t ) {
					map[ baseName( img.getAttribute( 'src' ) ) ] = t;
					if ( img.currentSrc ) map[ baseName( img.currentSrc ) ] = t; // resolved srcset variant
				}
			}
		} );
		return map;
	}

	// distinct caption values — used as a safe fallback for single-photographer galleries.
	function distinctValues( map ) {
		var seen = {};
		Object.keys( map ).forEach( function ( k ) { seen[ map[ k ] ] = 1; } );
		return Object.keys( seen );
	}

	var map = null;
	function getMap() { if ( ! map ) map = captionMap(); return map; }

	// read the enlarged/current image src from several possible sources.
	function readSrc( overlay ) {
		var src = '';
		overlay.querySelectorAll( '.lightbox-image-container img, figure img, img' ).forEach( function ( i ) {
			var s = i.currentSrc || i.getAttribute( 'src' ) || '';
			if ( s ) src = s; // last non-empty wins (enlarged loads after currentSrc)
		} );
		return src;
	}

	// resolve the credit for whatever the lightbox is currently showing.
	function resolveCredit( overlay ) {
		var m = getMap();
		var key = baseName( readSrc( overlay ) );
		if ( Object.prototype.hasOwnProperty.call( m, key ) ) return m[ key ];
		// fallback 1: alt text of the shown image (usually blank in our imports).
		var altImg = overlay.querySelector( '.lightbox-image-container img[alt]' );
		var alt = altImg && ( altImg.getAttribute( 'alt' ) || '' ).trim();
		if ( alt ) return alt;
		// fallback 2: single-photographer gallery -> the one distinct credit.
		var vals = distinctValues( m );
		if ( vals.length === 1 ) return vals[ 0 ];
		return '';
	}

	// the single body-level caption element (outside the Preact overlay).
	var caption = null;
	function ensureCaption() {
		if ( ! caption || ! caption.isConnected ) {
			caption = document.createElement( 'div' );
			caption.className = 'vw-lightbox-caption';
			caption.setAttribute( 'aria-hidden', 'true' );
			document.body.appendChild( caption );
		}
		return caption;
	}

	var OBS_OPTS = { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'src' ] };
	var obs = null;
	var scheduled = false;

	function schedule() {
		if ( scheduled ) return; // debounce: coalesce rapid mutations into one frame
		scheduled = true;
		requestAnimationFrame( function () { scheduled = false; render(); } );
	}

	function render() {
		var overlay = document.querySelector( ACTIVE ); // always the LIVE active overlay
		var el = ensureCaption();
		var text = overlay ? resolveCredit( overlay ) : '';
		var disp = ( overlay && text ) ? 'block' : 'none';
		// loop guard: pause our own body observer across the DOM writes.
		if ( obs ) obs.disconnect();
		if ( el.textContent !== text ) el.textContent = text;
		if ( el.style.display !== disp ) el.style.display = disp;
		if ( obs ) { obs.takeRecords(); obs.observe( document.body, OBS_OPTS ); }
	}

	function boot() {
		ensureCaption();
		obs = new MutationObserver( schedule );
		obs.observe( document.body, OBS_OPTS );
		render();
	}

	if ( document.readyState !== 'loading' ) boot();
	else document.addEventListener( 'DOMContentLoaded', boot );
} )();
