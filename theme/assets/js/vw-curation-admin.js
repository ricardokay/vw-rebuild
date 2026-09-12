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

	function renumber( listEl ) {
		listEl.querySelectorAll( '[data-vwc-slot]' ).forEach( function ( slot, index ) {
			slot.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace(
					/\[slots\]\[\d+\]/,
					'[slots][' + index + ']'
				);
			} );
		} );
	}

	$( function () {
		$( '[data-vwc-sortable]' ).sortable( {
			handle: '.vwc-slot__handle',
			items: '> [data-vwc-slot]',
			axis: 'y',
			tolerance: 'pointer',
			start: function ( e, ui ) {
				ui.item.addClass( 'is-dragging' );
			},
			stop: function ( e, ui ) {
				ui.item.removeClass( 'is-dragging' );
				renumber( this );
			}
		} );

		document.querySelectorAll( '[data-vwc-slot]' ).forEach( syncPanes );
	} );

}( window.jQuery ) );
