/**
 * Curation admin: mode panes, post-search autocomplete, drag ordering.
 *
 * Ordering renumbers the slot field indices on drop. The alternative — unindexed
 * name[] arrays submitted in DOM order — desynchronises the moment a radio group
 * has no checked member, because unchecked radios do not submit and the parallel
 * arrays stop lining up. Renumbering keeps every slot's fields addressed as one
 * group, and a save with JavaScript unavailable still works, just without
 * reordering.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.vwCuration || {};
	var strings = cfg.strings || {};

	/* ── TEMPORARY DIAGNOSTICS ──────────────────────────────────────
	   Added 2026-09-11: every harness test passed while the real admin page
	   failed, so the real page has to report on itself. Off unless the screen
	   was opened with ?vwc_debug=1. Delete this block, its call sites, and
	   inc/curation-debug.php once the drag bug is closed. */

	var DEBUG = !! cfg.debug;
	var STASH = 'vwcDebugTrace';

	function dbg( label, data ) {
		if ( ! DEBUG ) {
			return;
		}
		/* eslint-disable no-console */
		if ( typeof data === 'undefined' ) {
			console.log( '%c[VWC] ' + label, 'color:#C41230;font-weight:bold' );
		} else {
			console.log( '%c[VWC] ' + label, 'color:#C41230;font-weight:bold', data );
		}
		/* eslint-enable no-console */
	}

	/** Everything about one zone's slots, as the DOM currently holds it. */
	function zoneState( zoneEl ) {
		return [].slice.call( zoneEl.querySelectorAll( '[data-vwc-slot]' ) ).map( function ( slot, i ) {
			var checked = slot.querySelector( '[data-vwc-mode]:checked' );
			var pin = slot.querySelector( '[data-vwc-pin-id]' );
			var label = slot.querySelector( '.vwc-slot__role strong' );
			return {
				domIndex: i,
				label: label ? label.textContent.trim() : '?',
				modeFieldName: ( slot.querySelector( '[data-vwc-mode]' ) || {} ).name,
				checkedMode: checked ? checked.value : '*** NONE CHECKED ***',
				pinFieldName: pin ? pin.name : '?',
				pinValue: pin ? pin.value : '?'
			};
		} );
	}

	function zoneOf( el ) {
		return el.closest( '.vwc-zone' );
	}

	function zoneKey( zoneEl ) {
		var f = zoneEl.querySelector( '[name*="[present]"]' );
		return f ? f.name.replace( '[present]', '' ) : '(unknown zone)';
	}

	/** Duplicate field names are the signature of a renumber collision. */
	function duplicateNames( form ) {
		var seen = {};
		var dupes = {};
		[].slice.call( form.querySelectorAll( '[name]' ) ).forEach( function ( f ) {
			if ( f.type === 'radio' ) {
				return; // radios legitimately share a name within one group
			}
			seen[ f.name ] = ( seen[ f.name ] || 0 ) + 1;
			if ( seen[ f.name ] > 1 ) {
				dupes[ f.name ] = seen[ f.name ];
			}
		} );
		return dupes;
	}

	/** Radio groups with no checked member — the failure mode we are hunting. */
	function uncheckedGroups( form ) {
		var out = [];
		[].slice.call( form.querySelectorAll( '[data-vwc-slot]' ) ).forEach( function ( slot ) {
			var radios = slot.querySelectorAll( '[data-vwc-mode]' );
			if ( radios.length && ! slot.querySelector( '[data-vwc-mode]:checked' ) ) {
				out.push( radios[ 0 ].name );
			}
		} );
		return out;
	}

	/* ── Mode panes ─────────────────────────────────────────────── */

	function syncPanes( slot ) {
		var checked = slot.querySelector( '[data-vwc-mode]:checked' );
		var mode = checked ? checked.value : 'auto';

		slot.querySelectorAll( '[data-vwc-pane]' ).forEach( function ( pane ) {
			pane.hidden = pane.getAttribute( 'data-vwc-pane' ) !== mode;
		} );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '[data-vwc-mode]' ) ) {
			return;
		}
		var slot = e.target.closest( '[data-vwc-slot]' );
		if ( slot ) {
			syncPanes( slot );
		}
	} );

	/* ── Clear a pin ────────────────────────────────────────────── */

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.matches( '[data-vwc-clear]' ) ) {
			return;
		}
		e.preventDefault();

		var slot = e.target.closest( '[data-vwc-slot]' );
		if ( ! slot ) {
			return;
		}
		slot.querySelector( '[data-vwc-pin-id]' ).value = '';
		slot.querySelector( '[data-vwc-pin-current]' ).hidden = true;
		slot.classList.remove( 'vwc-slot--broken' );

		var broken = slot.querySelector( '.vwc-broken' );
		if ( broken ) {
			broken.remove();
		}
	} );

	/* ── Search ─────────────────────────────────────────────────── */

	function badgeClass( tier ) {
		return 'vwc-badge vwc-badge--t' + ( [ 0, 1, 2, 3 ].indexOf( tier ) === -1 ? 0 : tier );
	}

	function renderResults( list, items ) {
		list.textContent = '';

		if ( ! items.length ) {
			var empty = document.createElement( 'li' );
			empty.className = 'vwc-results__msg';
			empty.textContent = strings.none || 'No matching stories.';
			list.appendChild( empty );
			list.hidden = false;
			return;
		}

		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'vwc-result';
			btn.setAttribute( 'data-vwc-pick', '' );
			btn.setAttribute( 'data-id', item.id );
			btn.setAttribute( 'data-title', item.title );
			btn.setAttribute( 'data-tier', item.tier );
			btn.setAttribute( 'data-tier-label', item.tierLabel );
			btn.setAttribute( 'data-date', item.date );

			// textContent throughout: titles are author-entered and never parsed
			// as markup here, regardless of what the REST layer returned.
			var title = document.createElement( 'span' );
			title.className = 'vwc-result__title';
			title.textContent = item.title;
			btn.appendChild( title );

			var meta = document.createElement( 'span' );
			meta.className = 'vwc-result__meta';

			var badge = document.createElement( 'span' );
			badge.className = badgeClass( item.tier );
			badge.textContent = item.tierLabel;
			meta.appendChild( badge );

			if ( item.section ) {
				var sec = document.createElement( 'span' );
				sec.textContent = item.section;
				meta.appendChild( sec );
			}

			var date = document.createElement( 'span' );
			date.textContent = item.date;
			meta.appendChild( date );

			if ( item.status && item.status !== 'publish' ) {
				var st = document.createElement( 'span' );
				st.className = 'vwc-badge vwc-badge--warn';
				st.textContent = item.status;
				meta.appendChild( st );
			}

			btn.appendChild( meta );
			li.appendChild( btn );
			list.appendChild( li );
		} );

		list.hidden = false;
	}

	function message( list, text ) {
		list.textContent = '';
		var li = document.createElement( 'li' );
		li.className = 'vwc-results__msg';
		li.textContent = text;
		list.appendChild( li );
		list.hidden = false;
	}

	var timers = new WeakMap();

	document.addEventListener( 'input', function ( e ) {
		if ( ! e.target.matches( '[data-vwc-search]' ) ) {
			return;
		}

		var input = e.target;
		var slot = input.closest( '[data-vwc-slot]' );
		var list = slot.querySelector( '[data-vwc-results]' );
		var term = input.value.trim();

		clearTimeout( timers.get( input ) );

		if ( term.length < 2 ) {
			list.hidden = true;
			list.textContent = '';
			return;
		}

		timers.set( input, setTimeout( function () {
			message( list, strings.searching || 'Searching…' );

			var url = cfg.searchUrl
				+ ( cfg.searchUrl.indexOf( '?' ) === -1 ? '?' : '&' )
				+ 'search=' + encodeURIComponent( term )
				+ '&cats=' + encodeURIComponent( input.getAttribute( 'data-vwc-cats' ) || '' );

			window.fetch( url, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce }
			} )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( r.status );
					}
					return r.json();
				} )
				.then( function ( items ) {
					renderResults( list, Array.isArray( items ) ? items : [] );
				} )
				.catch( function () {
					message( list, strings.error || 'Search failed.' );
				} );
		}, 300 ) );
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-vwc-pick]' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();

		var slot = btn.closest( '[data-vwc-slot]' );
		var current = slot.querySelector( '[data-vwc-pin-current]' );
		var tier = btn.getAttribute( 'data-tier' );

		slot.querySelector( '[data-vwc-pin-id]' ).value = btn.getAttribute( 'data-id' );

		current.textContent = '';

		var title = document.createElement( 'span' );
		title.className = 'vwc-pin__title';
		title.textContent = btn.getAttribute( 'data-title' );
		current.appendChild( title );

		var badge = document.createElement( 'span' );
		badge.className = badgeClass( parseInt( tier, 10 ) );
		badge.textContent = btn.getAttribute( 'data-tier-label' );
		current.appendChild( badge );

		var date = document.createElement( 'span' );
		date.className = 'vwc-pin__date';
		date.textContent = btn.getAttribute( 'data-date' );
		current.appendChild( date );

		var clear = document.createElement( 'button' );
		clear.type = 'button';
		clear.className = 'button-link vwc-pin__clear';
		clear.setAttribute( 'data-vwc-clear', '' );
		clear.textContent = strings.clear || 'Clear';
		current.appendChild( clear );

		current.hidden = false;

		// A freshly picked post was just returned by a search that only offers
		// pinnable posts, so any standing broken-pin warning no longer applies.
		slot.classList.remove( 'vwc-slot--broken' );
		var broken = slot.querySelector( '.vwc-broken' );
		if ( broken ) {
			broken.remove();
		}

		var results = slot.querySelector( '[data-vwc-results]' );
		results.hidden = true;
		results.textContent = '';
		slot.querySelector( '[data-vwc-search]' ).value = '';
	} );

	/* ── Ordering ───────────────────────────────────────────────── */

	/**
	 * Renumber slot field indices after a drag.
	 *
	 * The naive one-pass rename silently corrupted every reorder. Radios are
	 * grouped by name, so while slot N was being renamed it briefly shared a
	 * name with an already-renamed slot; the browser merged the two into one
	 * group and unchecked the earlier member. The moved slot then submitted no
	 * mode at all, the sanitizer defaulted it to auto, and its pin was dropped —
	 * which is why a reorder appeared not to survive a save.
	 *
	 * Three passes, in this order, and all three are load-bearing:
	 *   1. capture the checked mode per slot BEFORE any renaming
	 *   2. rename to a unique placeholder, then to the final index, so no two
	 *      radio groups ever hold the same name even for an instant
	 *   3. restore the captured state and re-sync the panes
	 *
	 * Session B's drag test passed over this because every slot in it was set to
	 * auto — the default the lost radio fell back to — so the corruption was
	 * invisible. Any test of this function must use non-default modes.
	 */
	function renumber( listEl ) {
		var slots = [].slice.call( listEl.querySelectorAll( '[data-vwc-slot]' ) );

		dbg( 'renumber PASS 0 — state on entry', zoneState( zoneOf( listEl ) ) );

		var checkedModes = slots.map( function ( slot ) {
			var c = slot.querySelector( '[data-vwc-mode]:checked' );
			return c ? c.value : null;
		} );

		dbg( 'renumber PASS 1 — captured checked modes', checkedModes.slice() );

		slots.forEach( function ( slot, index ) {
			slot.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[slots\]\[\d+\]/, '[slots][__vw' + index + '__]' );
			} );
		} );

		slots.forEach( function ( slot, index ) {
			slot.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[slots\]\[__vw\d+__\]/, '[slots][' + index + ']' );
			} );
		} );

		dbg( 'renumber PASS 2 — after placeholder + final rename', zoneState( zoneOf( listEl ) ) );

		slots.forEach( function ( slot, index ) {
			if ( checkedModes[ index ] ) {
				var radio = slot.querySelector(
					'[data-vwc-mode][value="' + checkedModes[ index ] + '"]'
				);
				if ( radio ) {
					radio.checked = true;
				}
			}
			syncPanes( slot );
		} );

		dbg( 'renumber PASS 3 — after restore + syncPanes', zoneState( zoneOf( listEl ) ) );
	}

	$( function () {
		var lists = $( '[data-vwc-sortable]' );

		dbg( 'init — sortable lists found', lists.length );
		dbg( 'init — jQuery ' + ( $ && $.fn && $.fn.jquery ) + ', UI sortable ' + ( $.ui && $.ui.sortable ? $.ui.sortable.version : 'MISSING' ) );

		lists.sortable( {
			handle: '.vwc-slot__handle',
			items: '> [data-vwc-slot]',
			axis: 'y',
			tolerance: 'pointer',
			start: function ( e, ui ) {
				ui.item.addClass( 'is-dragging' );
				dbg( 'sortable START — ' + zoneKey( zoneOf( this ) ), zoneState( zoneOf( this ) ) );
			},
			update: function () {
				dbg( 'sortable UPDATE — DOM reordered, before renumber', zoneState( zoneOf( this ) ) );
			},
			stop: function ( e, ui ) {
				ui.item.removeClass( 'is-dragging' );
				dbg( 'sortable STOP — calling renumber' );
				renumber( this );
				dbg( 'sortable STOP — renumber returned' );
			}
		} );

		document.querySelectorAll( '[data-vwc-slot]' ).forEach( syncPanes );

		if ( ! DEBUG ) {
			return;
		}

		/* ── Save-click payload dump ─────────────────────────────────
		   The form does a normal POST + redirect, so the console is wiped
		   before the result is visible. The trace is stashed in
		   sessionStorage and re-printed after the reload, so one copy/paste
		   carries both the payload that was sent and the state that came
		   back. */

		var form = document.querySelector( 'form' );

		form.addEventListener( 'submit', function () {
			var fd = new FormData( form );
			var all = [];
			var entries = 0;
			for ( var pair of fd.entries() ) {
				entries++;
				all.push( encodeURIComponent( pair[ 0 ] ) + '=' + encodeURIComponent( pair[ 1 ] ) );
			}
			var serialized = all.join( '&' );

			var zones = {};
			document.querySelectorAll( '.vwc-zone' ).forEach( function ( z ) {
				if ( z.querySelector( '[data-vwc-slot]' ) ) {
					zones[ zoneKey( z ) ] = zoneState( z );
				}
			} );

			var trace = {
				when: new Date().toISOString(),
				formDataEntryCount: entries,
				serializedByteLength: serialized.length,
				duplicateFieldNames: duplicateNames( form ),
				radioGroupsWithNothingChecked: uncheckedGroups( form ),
				zonesAtSubmit: zones,
				serializedPayload: serialized
			};

			dbg( '=== SUBMIT — payload being sent ===', trace );

			try {
				sessionStorage.setItem( STASH, JSON.stringify( trace ) );
			} catch ( err ) {
				dbg( 'could not stash trace', String( err ) );
			}
		} );

		// Re-print the pre-save trace next to what the server sent back.
		var stashed = null;
		try {
			stashed = sessionStorage.getItem( STASH );
		} catch ( err ) {
			stashed = null;
		}

		if ( stashed ) {
			dbg( '=== PREVIOUS SUBMIT (recovered after reload) ===', JSON.parse( stashed ) );
			try {
				sessionStorage.removeItem( STASH );
			} catch ( err ) {}
		}

		var after = {};
		document.querySelectorAll( '.vwc-zone' ).forEach( function ( z ) {
			if ( z.querySelector( '[data-vwc-slot]' ) ) {
				after[ zoneKey( z ) ] = zoneState( z );
			}
		} );
		dbg( '=== PAGE LOAD — what the server just rendered ===', {
			url: window.location.href,
			savedFlagPresent: window.location.search.indexOf( 'vw_saved=1' ) !== -1,
			zonesAsRendered: after
		} );
	} );

}( window.jQuery ) );
