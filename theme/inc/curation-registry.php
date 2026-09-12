<?php
/**
 * Curation zone registry — the single whitelist.
 *
 * This array is simultaneously three things, and that is deliberate: the admin
 * page renders from it, the sanitizer validates against it, and the resolver
 * reads its contract. Nothing about a zone is expressed anywhere else, so a
 * zone cannot drift between the three.
 *
 * Zone keys:
 *   label            Admin-facing name.
 *   can_hide         false forbids hiding the whole zone (the lead). Enforced
 *                    in the sanitizer AND re-asserted in the resolver, so a
 *                    hand-edited option row cannot blank it.
 *   default_visible  Visibility when nothing is stored.
 *   cats             Category IDs the zone draws from. Empty = sitewide.
 *   prefer_meta      Optional meta key. Posts carrying it are offered first,
 *                    then the wider pool. A preference, never a filter —
 *                    measured 2026-09-11: cat 6 + _vw_repaired_from yields 17
 *                    posts, only 5 of them tier 1, which is exactly the photo
 *                    band's image-slot count with zero slack. The wider cat-6
 *                    tier-1 pool is 47.
 *
 * Slot keys:
 *   role   Templates group by this, not by index, so hidden slots never
 *          misalign a composition.
 *   image  'tier1' | 'tier2' | 'none' — the requirement, not a guarantee. The
 *          resolver relaxes one step and then falls back to a text variant.
 *   label  Admin-facing name.
 */

defined( 'ABSPATH' ) || exit;

const VW_CURATION_MUSIC_CATS = [ 7, 9, 8, 11, 20, 10 ];

function vw_curation_registry(): array {
	static $registry = null;
	if ( null !== $registry ) {
		return $registry;
	}

	$compact = static function ( string $label ): array {
		return [ 'role' => 'compact', 'image' => 'none', 'label' => $label ];
	};

	// Every curated section front takes the same shape: an image anchor and one
	// stacked text story beneath it — $anchor and $anchor2 in the existing
	// section-parts. The surrounding ten-item lists stay query-driven; curating
	// twelve slots per section is a tool nobody would use.
	$section_zones = static function ( array $cats ) use ( $compact ): array {
		return [
			'lead' => [
				'label'           => 'Lead block',
				'can_hide'        => false,
				'default_visible' => true,
				'cats'            => $cats,
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'tier1', 'label' => 'Anchor story' ],
					$compact( 'Second story' ),
				],
			],
		];
	};

	$registry = [
		'home' => [

			'lead' => [
				'label'           => 'Lead story',
				'can_hide'        => false,
				'default_visible' => true,
				'cats'            => [],
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'tier1', 'label' => 'Lead' ],
				],
			],

			// Hidden at launch per decision C1-1. Wired so it can be switched on
			// without a code change.
			'thisweek' => [
				'label'           => 'This Week strip',
				'can_hide'        => true,
				'default_visible' => false,
				'cats'            => [],
				'prefer_meta'     => '',
				'slots'           => [
					$compact( 'Item 1' ),
					$compact( 'Item 2' ),
					$compact( 'Item 3' ),
				],
			],

			'music' => [
				'label'           => 'A La Music',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => VW_CURATION_MUSIC_CATS,
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'tier2', 'label' => 'Featured' ],
					$compact( 'Stack 1' ),
					$compact( 'Stack 2' ),
				],
			],

			'photo' => [
				'label'           => 'Photography band',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => [ 6 ],
				'prefer_meta'     => '_vw_repaired_from',
				'slots'           => [
					[ 'role' => 'essay', 'image' => 'tier1', 'label' => 'Photo essay' ],
					$compact( 'Secondary' ),
					[ 'role' => 'thumb', 'image' => 'tier2', 'label' => 'Strip 1' ],
					[ 'role' => 'thumb', 'image' => 'tier2', 'label' => 'Strip 2' ],
					[ 'role' => 'thumb', 'image' => 'tier2', 'label' => 'Strip 3' ],
					[ 'role' => 'thumb', 'image' => 'tier2', 'label' => 'Strip 4' ],
				],
			],

			// Measured 2026-09-11: 27 posts, 4 with a usable image. Decision (a) —
			// accept, and let the column run its text variant most of the time.
			// Widening via category cleanup is logged for post-launch.
			'food' => [
				'label'           => 'Food & Drink',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => [ 13 ],
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'tier2', 'label' => 'Featured' ],
					$compact( 'Compact 1' ),
					$compact( 'Compact 2' ),
				],
			],

			// image => 'none' is the design, not a shortfall: this column is the
			// pull-quote treatment. Measured 44 of 55 posts at tier 0, so the
			// approved design put the image-free treatment on the right section.
			'political' => [
				'label'           => 'Political Megaphone',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => [ 18 ],
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'none', 'label' => 'Quote story' ],
					$compact( 'Compact 1' ),
					$compact( 'Compact 2' ),
				],
			],

			// tier2, not tier1: measured 0 tier-1 posts against 21 tier-2, and the
			// slot is a 1:1 crop in a ~450px column, which tier 2 fills.
			'books' => [
				'label'           => 'Book Reviews',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => [ 30 ],
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'feat', 'image' => 'tier2', 'label' => 'Featured' ],
					$compact( 'Compact 1' ),
					$compact( 'Compact 2' ),
				],
			],

			'archive' => [
				'label'           => 'Archive closer',
				'can_hide'        => true,
				'default_visible' => true,
				'cats'            => [],
				'prefer_meta'     => '',
				'slots'           => [
					[ 'role' => 'issue', 'image' => 'none', 'label' => 'From the archive' ],
				],
			],
		],

		'section' => [
			'a-la-music'     => $section_zones( VW_CURATION_MUSIC_CATS ),
			'photography'    => $section_zones( [ 6 ] ),
			'food-drink'     => $section_zones( [ 13, 14 ] ),   // food-drink + hungry-social, matching the section part
			'out-n-about'    => $section_zones( [ 17 ] ),
			'must-see-films' => $section_zones( [ 15 ] ),
		],
	];

	return $registry;
}

/**
 * Zone definitions for a surface. $context is the category slug for 'section'.
 */
function vw_curation_zones( string $surface, string $context = '' ): array {
	$registry = vw_curation_registry();

	if ( 'section' === $surface ) {
		return $registry['section'][ $context ] ?? [];
	}
	return $registry[ $surface ] ?? [];
}

/**
 * One zone definition, or null when the surface/zone pair is not registered.
 * Every lookup that could come from stored or submitted data goes through here.
 */
function vw_curation_zone_def( string $surface, string $zone, string $context = '' ): ?array {
	$zones = vw_curation_zones( $surface, $context );
	return $zones[ $zone ] ?? null;
}

/**
 * Curated section slugs. category.php will consult this in session C; until
 * then its own $curated array remains the routing authority.
 */
function vw_curation_section_slugs(): array {
	return array_keys( vw_curation_registry()['section'] );
}
