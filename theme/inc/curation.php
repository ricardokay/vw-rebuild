<?php
/**
 * Curation storage, capability, sanitizer and resolver.
 *
 * Storage is one autoloaded option. Shape:
 *
 *   [ '_schema' => 1,
 *     'home'    => [ <zone> => [ 'visible' => bool, 'slots' => [ <slot>, … ] ] ],
 *     'section' => [ <slug> => [ <zone> => [ 'visible' => …, 'slots' => … ] ] ] ]
 *
 * A slot is [ 'mode' => 'pin'|'auto'|'hidden', 'post' => int, 'cat' => int ].
 *
 * The option does not exist until someone saves, and never has to: every zone
 * falls back to its registry default and every slot to auto-fill, so an
 * uncurated site renders a complete page. That property is what makes this
 * safe to put in front of a launch gate.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/curation-registry.php';

const VW_CURATION_OPTION = 'vw_curation';
const VW_CURATION_SCHEMA = 1;
const VW_CURATION_MODES  = [ 'pin', 'auto', 'hidden' ];

/** How deep auto-fill scans for a candidate meeting a slot's image requirement. */
const VW_CURATION_SCAN = 150;


/* ── Capability ─────────────────────────────────────────────────────────────
 *
 * vw_curate is granted dynamically off manage_options rather than written onto
 * the role. Same reasoning as the single-wide template filter: no database
 * write, no activation hook to go stale, and reverting is deleting a filter.
 *
 * When non-admin curators exist — there are 0 editors on this site today, and
 * 248 authors — the upgrade is a real add_cap() on a role. Nothing else in the
 * system changes, because every gate asks for vw_curate and not for a role.
 */
add_filter( 'user_has_cap', 'vw_curation_grant_cap', 10, 1 );
function vw_curation_grant_cap( $allcaps ) {
	if ( ! empty( $allcaps['manage_options'] ) ) {
		$allcaps['vw_curate'] = true;
	}
	return $allcaps;
}


/* ── Read ──────────────────────────────────────────────────────────────── */

/**
 * The stored option, schema-checked. A row from a future schema is ignored
 * rather than half-read: an unrecognised shape returns defaults, which renders
 * a correct page, instead of throwing on a key that moved.
 */
function vw_curation_config( bool $refresh = false ): array {
	static $config = null;

	if ( $refresh ) {
		$config = null;
	}
	if ( null !== $config ) {
		return $config;
	}

	$stored = get_option( VW_CURATION_OPTION, [] );

	if ( ! is_array( $stored ) || (int) ( $stored['_schema'] ?? 0 ) !== VW_CURATION_SCHEMA ) {
		$config = [];
		return $config;
	}

	$config = $stored;
	return $config;
}

/**
 * Drop the request-level caches.
 *
 * A normal save redirects immediately, so nothing in the web path needs this.
 * It exists for verification code that exercises several configs in one
 * process, where the statics would otherwise hold the first read forever.
 */
function vw_curation_flush_cache(): void {
	wp_cache_delete( VW_CURATION_OPTION, 'options' );
	vw_curation_config( true );
	vw_curation_candidates( [], '', true );
}

/**
 * A zone's stored config merged over its registry defaults. Always returns a
 * usable shape: one entry per registered slot, in registry order.
 */
function vw_curation_zone_config( string $surface, string $zone, string $context = '' ): array {
	$def = vw_curation_zone_def( $surface, $zone, $context );
	if ( ! $def ) {
		return [ 'visible' => false, 'slots' => [] ];
	}

	$config = vw_curation_config();
	$stored = ( 'section' === $surface )
		? ( $config['section'][ $context ][ $zone ] ?? [] )
		: ( $config[ $surface ][ $zone ] ?? [] );

	$visible = isset( $stored['visible'] )
		? (bool) $stored['visible']
		: (bool) $def['default_visible'];

	// Re-assert the constraint at read time, not only at write time: a zone the
	// registry forbids hiding stays visible even if the option says otherwise.
	if ( empty( $def['can_hide'] ) ) {
		$visible = true;
	}

	$slots = [];
	foreach ( $def['slots'] as $i => $slot_def ) {
		$s    = $stored['slots'][ $i ] ?? [];
		$mode = in_array( $s['mode'] ?? '', VW_CURATION_MODES, true ) ? $s['mode'] : 'auto';

		// Slot 0 of an unhideable zone is the zone. It cannot be hidden either.
		if ( 'hidden' === $mode && empty( $def['can_hide'] ) && 0 === $i ) {
			$mode = 'auto';
		}

		$slots[] = [
			'mode' => $mode,
			'post' => absint( $s['post'] ?? 0 ),
			'cat'  => absint( $s['cat'] ?? 0 ),
		];
	}

	return [ 'visible' => $visible, 'slots' => $slots ];
}

function vw_curation_zone_visible( string $surface, string $zone, string $context = '' ): bool {
	return (bool) vw_curation_zone_config( $surface, $zone, $context )['visible'];
}


/* ── Pin health ────────────────────────────────────────────────────────── */

/**
 * Why a pinned post cannot be rendered, or ok.
 *
 * The front end uses this silently — a broken pin falls through to auto-fill
 * and the reader sees a complete page. The admin screen uses the same call to
 * say so out loud, because a pin that quietly stopped working is worse than no
 * pin at all for the person who set it.
 */
function vw_curation_pin_status( int $post_id ): array {
	if ( ! $post_id ) {
		return [ 'ok' => false, 'code' => 'empty', 'label' => '' ];
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return [ 'ok' => false, 'code' => 'missing', 'label' => 'Post no longer exists' ];
	}
	if ( 'post' !== $post->post_type ) {
		return [ 'ok' => false, 'code' => 'type', 'label' => 'Not a post' ];
	}
	if ( 'trash' === $post->post_status ) {
		return [ 'ok' => false, 'code' => 'trashed', 'label' => 'In the trash' ];
	}
	if ( 'publish' !== $post->post_status ) {
		return [
			'ok'    => false,
			'code'  => 'unpublished',
			'label' => 'Not published (' . $post->post_status . ')',
		];
	}
	if ( get_post_meta( $post_id, '_vw_publish_exclude', true ) ) {
		return [ 'ok' => false, 'code' => 'excluded', 'label' => 'Held from publication' ];
	}

	return [ 'ok' => true, 'code' => 'ok', 'label' => '' ];
}


/* ── Auto-fill ─────────────────────────────────────────────────────────── */

/** Does a measured tier satisfy a slot's declared requirement? */
function vw_curation_tier_satisfies( int $tier, string $requirement ): bool {
	if ( 'none' === $requirement ) {
		return true;
	}
	if ( 'tier1' === $requirement ) {
		return 1 === $tier;
	}
	return 1 === $tier || 2 === $tier; // tier2
}

/**
 * Candidate post IDs, newest first, cached per category signature for the
 * request. One query per distinct category set rather than one per slot: the
 * homepage has fifteen slots across six category sets.
 */
function vw_curation_candidates( array $cats, string $prefer_meta = '', bool $refresh = false ): array {
	static $cache = [];

	if ( $refresh ) {
		$cache = [];
		return [];
	}

	sort( $cats );
	$key = md5( wp_json_encode( [ $cats, $prefer_meta ] ) );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$base = [
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => VW_CURATION_SCAN,
		'fields'                 => 'ids',
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	];
	if ( $cats ) {
		$base['category__in'] = $cats;
	}

	$preferred = [];
	if ( $prefer_meta ) {
		$preferred = get_posts( array_merge( $base, [
			'meta_query' => [ [ 'key' => $prefer_meta, 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		] ) );
	}

	$wider = get_posts( $base );

	// Preferred pool first, then everything else in date order. Never a filter:
	// the photo band's preferred pool holds five usable images against six slots.
	$cache[ $key ] = array_values( array_unique( array_merge( $preferred, $wider ) ) );
	return $cache[ $key ];
}

/**
 * Newest unused post meeting the image requirement, relaxing one step and then
 * accepting anything. Returning a text-variant post beats returning nothing:
 * 61.3% of the published archive has no usable featured image, so a strict
 * requirement would empty most zones rather than restyle them.
 */
function vw_curation_autofill( array $cats, string $requirement, array $used_ids, string $prefer_meta = '' ): ?WP_Post {
	$candidates = vw_curation_candidates( $cats, $prefer_meta );

	$ladder = [ $requirement ];
	if ( 'tier1' === $requirement ) {
		$ladder[] = 'tier2';
	}
	$ladder[] = 'none';

	foreach ( $ladder as $step ) {
		foreach ( $candidates as $id ) {
			if ( in_array( (int) $id, $used_ids, true ) ) {
				continue;
			}
			if ( ! vw_curation_tier_satisfies( vw_image_tier( (int) $id ), $step ) ) {
				continue;
			}
			return get_post( (int) $id );
		}
	}

	return null;
}


/* ── Resolver ──────────────────────────────────────────────────────────── */

/**
 * Resolve one zone to renderable slots.
 *
 * $used_ids is threaded by reference exactly as the existing section-parts
 * thread theirs, so no story repeats across zones on a surface.
 *
 * Each returned entry:
 *   role          registry role — templates group by this, never by index, so
 *                 a hidden slot cannot misalign a composition
 *   post          WP_Post
 *   tier          vw_image_tier() result
 *   mode          'pin' or 'auto' — what actually happened, not what was asked
 *   text_variant  true when the slot wanted an image the post cannot supply;
 *                 the template drops the image box rather than leaving it empty
 *   pin_failed    pin status code when a pin fell through to auto-fill
 */
function vw_curation_resolve( string $surface, string $zone, array &$used_ids, string $context = '' ): array {
	$def = vw_curation_zone_def( $surface, $zone, $context );
	if ( ! $def ) {
		return [];
	}

	$config = vw_curation_zone_config( $surface, $zone, $context );
	if ( ! $config['visible'] ) {
		return [];
	}

	$out = [];

	foreach ( $def['slots'] as $i => $slot_def ) {
		$slot = $config['slots'][ $i ] ?? [ 'mode' => 'auto', 'post' => 0, 'cat' => 0 ];

		if ( 'hidden' === $slot['mode'] ) {
			continue;
		}

		$post       = null;
		$mode       = 'auto';
		$pin_failed = '';

		if ( 'pin' === $slot['mode'] && $slot['post'] ) {
			$status = vw_curation_pin_status( $slot['post'] );
			if ( $status['ok'] && ! in_array( $slot['post'], $used_ids, true ) ) {
				$post = get_post( $slot['post'] );
				$mode = 'pin';
			} else {
				$pin_failed = $status['ok'] ? 'duplicate' : $status['code'];
			}
		}

		if ( ! $post ) {
			$cats = $slot['cat'] ? [ $slot['cat'] ] : $def['cats'];
			$post = vw_curation_autofill( $cats, $slot_def['image'], $used_ids, $def['prefer_meta'] );
		}

		if ( ! $post ) {
			continue;
		}

		$used_ids[] = (int) $post->ID;
		$tier       = vw_image_tier( (int) $post->ID );

		$out[] = [
			'role'         => $slot_def['role'],
			'post'         => $post,
			'tier'         => $tier,
			'mode'         => $mode,
			'text_variant' => ! vw_curation_tier_satisfies( $tier, $slot_def['image'] ),
			'pin_failed'   => $pin_failed,
		];
	}

	return $out;
}

/** All resolved slots carrying a role. */
function vw_curation_slots_by_role( array $resolved, string $role ): array {
	return array_values( array_filter(
		$resolved,
		static fn( $s ) => $s['role'] === $role
	) );
}

/** First resolved slot carrying a role, or null. */
function vw_curation_slot_by_role( array $resolved, string $role ): ?array {
	$all = vw_curation_slots_by_role( $resolved, $role );
	return $all[0] ?? null;
}


/* ── Sanitize ──────────────────────────────────────────────────────────── */

/**
 * Build a storable config from submitted input.
 *
 * Walks the REGISTRY and reads from the input, never the reverse. An unknown
 * surface, zone, or slot index in the submission has nowhere to land, so
 * whitelisting is structural rather than a list of rejections to maintain.
 */
function vw_curation_sanitize( $raw ): array {
	$raw = is_array( $raw ) ? $raw : [];
	$out = [ '_schema' => VW_CURATION_SCHEMA ];
	$reg = vw_curation_registry();
	$cur = vw_curation_config();

	foreach ( $reg as $surface => $surface_def ) {
		if ( 'section' === $surface ) {
			$out['section'] = [];
			foreach ( $surface_def as $slug => $zones ) {
				$clean = vw_curation_sanitize_zones(
					$zones,
					$raw['section'][ $slug ] ?? [],
					is_array( $cur['section'][ $slug ] ?? null ) ? $cur['section'][ $slug ] : []
				);
				if ( $clean ) {
					$out['section'][ $slug ] = $clean;
				}
			}
			continue;
		}

		$out[ $surface ] = vw_curation_sanitize_zones(
			$surface_def,
			$raw[ $surface ] ?? [],
			is_array( $cur[ $surface ] ?? null ) ? $cur[ $surface ] : []
		);
	}

	return $out;
}

/**
 * @param array $zone_defs Registry zone definitions for one surface.
 * @param mixed $raw       Submitted input for that surface.
 * @param array $existing  Currently stored config for that surface, preserved
 *                         for any zone the submission did not include.
 */
function vw_curation_sanitize_zones( array $zone_defs, $raw, array $existing = [] ): array {
	$raw = is_array( $raw ) ? $raw : [];
	$out = [];

	foreach ( $zone_defs as $zone => $def ) {
		$in = is_array( $raw[ $zone ] ?? null ) ? $raw[ $zone ] : [];

		/*
		 * A zone the form did not render must not be rewritten by this save.
		 *
		 * An unchecked checkbox submits nothing, so "no visible key" and "zone
		 * absent from the POST entirely" look identical — which meant a partial
		 * submission silently hid every zone it omitted. Harmless while the whole
		 * form always posts, and a live landmine for a future per-zone save or
		 * any programmatic write. Each rendered zone now carries a hidden
		 * [present] marker: with it, an absent checkbox means hidden; without
		 * it, the stored value is carried through untouched.
		 */
		if ( empty( $in['present'] ) ) {
			$stored = $existing[ $zone ] ?? null;
			if ( is_array( $stored ) && isset( $stored['visible'], $stored['slots'] ) ) {
				$out[ $zone ] = $stored;
				continue;
			}
			$out[ $zone ] = [
				'visible' => ! empty( $def['can_hide'] ) ? (bool) $def['default_visible'] : true,
				'slots'   => array_fill( 0, count( $def['slots'] ), [ 'mode' => 'auto', 'post' => 0, 'cat' => 0 ] ),
			];
			continue;
		}

		$visible = ! empty( $def['can_hide'] )
			? ! empty( $in['visible'] )
			: true;

		$slots_in = is_array( $in['slots'] ?? null ) ? $in['slots'] : [];
		ksort( $slots_in, SORT_NUMERIC );
		$slots_in = array_values( $slots_in );

		$slots = [];
		foreach ( $def['slots'] as $i => $slot_def ) {
			$s = is_array( $slots_in[ $i ] ?? null ) ? $slots_in[ $i ] : [];

			/*
			 * Defence in depth for a missing mode.
			 *
			 * An unchecked radio group submits nothing, so one client-side slip —
			 * the renumber collision fixed in vw-curation-admin.js — silently
			 * turned pins into auto-fill with no error anywhere. When no mode
			 * arrives, a submitted post id is the one unambiguous signal of
			 * intent: only 'pin' uses one, and an auto or hidden slot always
			 * renders its post field as 0 because this sanitizer zeroes it.
			 *
			 * Deliberately NOT read from the stored slot at this index: under a
			 * reorder, index i refers to a different slot than it did when the
			 * option was written, so that lookup would restore the wrong mode.
			 * Inferring from the payload is order-independent.
			 *
			 * A mode that IS present but unrecognised is still coerced to 'auto'.
			 */
			if ( ! array_key_exists( 'mode', $s ) ) {
				$mode = absint( $s['post'] ?? 0 ) ? 'pin' : 'auto';
			} else {
				$mode = in_array( $s['mode'], VW_CURATION_MODES, true ) ? $s['mode'] : 'auto';
			}

			if ( 'hidden' === $mode && empty( $def['can_hide'] ) && 0 === $i ) {
				$mode = 'auto';
			}

			$post = absint( $s['post'] ?? 0 );
			if ( $post ) {
				$p = get_post( $post );
				// Stored only if it is a post at all. Status is deliberately NOT
				// required here: an editor pinning a scheduled or draft story is
				// doing something reasonable, and the resolver falls through
				// until it publishes while the admin screen flags it.
				if ( ! $p || 'post' !== $p->post_type ) {
					$post = 0;
				}
			}
			if ( 'pin' !== $mode ) {
				$post = 0;
			}

			$cat = absint( $s['cat'] ?? 0 );
			if ( $cat && $def['cats'] && ! in_array( $cat, array_map( 'intval', $def['cats'] ), true ) ) {
				$cat = 0;
			}
			if ( $cat && ! $def['cats'] && ! get_term( $cat, 'category' ) instanceof WP_Term ) {
				$cat = 0;
			}
			if ( 'auto' !== $mode ) {
				$cat = 0;
			}

			$slots[] = [ 'mode' => $mode, 'post' => $post, 'cat' => $cat ];
		}

		$out[ $zone ] = [ 'visible' => $visible, 'slots' => $slots ];
	}

	return $out;
}
