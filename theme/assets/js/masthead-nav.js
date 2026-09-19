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
			if ( t.classList.contains( 'vw-mbar__item' ) ) t.classList.toggle( 'vw-mbar__item--open', on );
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

	// Every [data-vw-mnav-open] is a trigger: the bar's tabs and the masthead's
	// sections/search controls open the one panel. The masthead search control
	// is a link to the search page so it still works without JavaScript; here
	// it becomes a button.
	triggers.forEach( function ( t ) {
		if ( t.tagName === 'A' ) t.setAttribute( 'role', 'button' );
		t.addEventListener( 'click', function ( e ) {
			e.preventDefault();
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

/**
 * Focused search mode (search results, below 960px).
 *
 * Close goes back when the reader arrived from a page on this site, so it
 * returns them to where they searched from; otherwise it stays the plain link
 * home it is without JavaScript. The search header is sticky, and gains its
 * hairline only once it is actually stuck — a sentinel above it leaving the
 * viewport is the signal.
 */
( function () {
	var closeLink = document.querySelector( '[data-vw-search-close]' );
	if ( closeLink ) {
		closeLink.addEventListener( 'click', function ( e ) {
			var ref = document.referrer;
			var sameSite = ref && ref.indexOf( location.origin + '/' ) === 0;
			if ( sameSite && window.history.length > 1 ) {
				e.preventDefault();
				window.history.back();
			}
		} );
	}

	var header = document.querySelector( '.search #primary > .page-header' );
	if ( ! header || ! ( 'IntersectionObserver' in window ) ) return;

	var sentinel = document.createElement( 'div' );
	sentinel.setAttribute( 'aria-hidden', 'true' );
	sentinel.className = 'vw-search-sentinel';
	header.parentNode.insertBefore( sentinel, header );

	new IntersectionObserver( function ( entries ) {
		header.classList.toggle( 'is-stuck', ! entries[ 0 ].isIntersecting );
	} ).observe( sentinel );
} )();

/**
 * Desktop search (960px and up). The icon is a link to the search page
 * without JavaScript; here it toggles the field under the heavy rule. Enter
 * submits the form natively. Escape, a second click, or a click elsewhere
 * closes it.
 */
( function () {
	var wrap = document.querySelector( '[data-vw-dsearch]' );
	if ( ! wrap ) return;

	var toggle = wrap.querySelector( '.vwh2-masthead__search-toggle' );
	var form   = wrap.querySelector( '.vwh2-masthead__search-form' );
	var input  = form.querySelector( 'input[name="s"]' );

	toggle.setAttribute( 'role', 'button' );

	function isOpen() {
		return ! form.hidden;
	}

	function open() {
		form.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		input.focus();
		input.select();
	}

	function close( refocus ) {
		if ( ! isOpen() ) return;
		form.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
		if ( refocus ) toggle.focus();
	}

	toggle.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		if ( isOpen() ) {
			close( true );
		} else {
			open();
		}
	} );

	// A link with role=button: Space activates it, as it would a button.
	toggle.addEventListener( 'keydown', function ( e ) {
		if ( e.key === ' ' ) {
			e.preventDefault();
			toggle.click();
		}
	} );

	wrap.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && isOpen() ) {
			e.preventDefault();
			close( true );
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( isOpen() && ! wrap.contains( e.target ) ) close( false );
	} );

	// Closing when the window narrows past the breakpoint keeps aria-expanded
	// honest; below 960px the whole control is hidden.
	var wide = window.matchMedia( '(min-width: 960px)' );
	var onNarrow = function ( e ) {
		if ( ! e.matches ) close( false );
	};
	if ( wide.addEventListener ) {
		wide.addEventListener( 'change', onNarrow );
	} else if ( wide.addListener ) {
		wide.addListener( onNarrow );
	}
} )();
