/**
 * Mobile bottom bar + sections/search panel.
 *
 * The html.vw-js class that reveals the bar is set inline in <head> (see
 * vw_js_class in functions.php), so there is no flash of the fallback nav.
 * Without JavaScript neither the bar nor the panel shows and the inline nav
 * stays as it always was.
 */
( function () {
	var panel = document.getElementById( 'vw-mnav-panel' );
	if ( ! panel ) return;

	var root     = document.documentElement;
	var page     = document.getElementById( 'page' );
	var input    = panel.querySelector( '.vw-mnav-search__input' );
	var closeBtn = panel.querySelector( '[data-vw-mnav-close]' );

	// Sections mode focuses the dialog itself rather than its close button: a
	// scripted focus() counts as :focus-visible, so a tap would light a ring.
	var triggers = [].slice.call( document.querySelectorAll( '[data-vw-mnav-open]' ) );
	var wide     = window.matchMedia( '(min-width: 960px)' );
	var mode     = null;
	var returnTo = null;

	function sync() {
		triggers.forEach( function ( t ) {
			var on = t.getAttribute( 'data-vw-mnav-open' ) === mode;
			t.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			t.classList.toggle( 'vw-mbar__item--open', on );
		} );
	}

	function open( next, trigger ) {
		if ( mode === null ) returnTo = trigger || document.activeElement;
		mode = next;
		panel.setAttribute( 'data-mode', next );
		panel.hidden = false;
		root.classList.add( 'vw-mnav-is-open' );
		if ( page ) page.inert = true;
		sync();
		( next === 'search' && input ? input : panel ).focus();
	}

	function close() {
		if ( mode === null ) return;
		mode = null;
		panel.hidden = true;
		root.classList.remove( 'vw-mnav-is-open' );
		if ( page ) page.inert = false;
		sync();
		if ( returnTo && returnTo.focus ) returnTo.focus();
		returnTo = null;
	}

	triggers.forEach( function ( t ) {
		t.addEventListener( 'click', function () {
			var next = t.getAttribute( 'data-vw-mnav-open' );
			if ( mode === next ) {
				close();
			} else {
				open( next, t );
			}
		} );
	} );

	closeBtn.addEventListener( 'click', close );

	document.addEventListener( 'keydown', function ( e ) {
		if ( mode === null ) return;
		if ( e.key === 'Escape' ) {
			close();
			return;
		}
		if ( e.key !== 'Tab' ) return;

		// Keep focus inside the panel while it is open.
		var items = [].slice.call( panel.querySelectorAll( 'a[href], button, input' ) ).filter( function ( el ) {
			return el.offsetParent !== null;
		} );
		if ( ! items.length ) return;
		var first = items[ 0 ];
		var last  = items[ items.length - 1 ];
		if ( e.shiftKey && ( document.activeElement === first || document.activeElement === panel ) ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	// Rotating a tablet past the breakpoint would otherwise leave an invisible
	// open panel holding #page inert.
	var onWide = function ( e ) {
		if ( e.matches ) close();
	};
	if ( wide.addEventListener ) {
		wide.addEventListener( 'change', onWide );
	} else if ( wide.addListener ) {
		wide.addListener( onWide );
	}
} )();
