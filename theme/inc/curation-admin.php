<?php
/**
 * Curation admin screen — "Vancouver Weekly → Homepage & Sections".
 *
 * Everything on this page is rendered from the registry, so a zone cannot
 * appear here without also existing for the sanitizer and the resolver.
 *
 * Security, stated once and applied everywhere below:
 *   - vw_curate is checked on the menu (hides it), on the render callback, and
 *     on the save handler independently. The menu capability protects nothing
 *     on its own — a direct POST to admin-post.php never passes through it.
 *   - Every write is nonced.
 *   - Input is sanitized by walking the registry (see vw_curation_sanitize).
 *   - Output is escaped at the point of echo, including values that were
 *     sanitized on the way in.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/curation.php';


/* ── Menu ──────────────────────────────────────────────────────────────── */

add_action( 'admin_menu', 'vw_curation_admin_menu' );
function vw_curation_admin_menu(): void {
	add_menu_page(
		'Vancouver Weekly',
		'Vancouver Weekly',
		'vw_curate',
		'vw-curation',
		'vw_curation_admin_page',
		'dashicons-layout',
		3
	);
	add_submenu_page(
		'vw-curation',
		'Homepage & Sections',
		'Homepage & Sections',
		'vw_curate',
		'vw-curation',
		'vw_curation_admin_page'
	);
}


/* ── Assets ────────────────────────────────────────────────────────────── */

add_action( 'admin_enqueue_scripts', 'vw_curation_admin_assets' );
function vw_curation_admin_assets( string $hook ): void {
	if ( 'toplevel_page_vw-curation' !== $hook ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'vw-curation-admin',
		$uri . '/assets/css/curation-admin.css',
		[],
		filemtime( $dir . '/assets/css/curation-admin.css' )
	);

	wp_enqueue_script(
		'vw-curation-admin',
		$uri . '/assets/js/vw-curation-admin.js',
		[ 'jquery-ui-sortable' ],
		filemtime( $dir . '/assets/js/vw-curation-admin.js' ),
		true
	);

	wp_localize_script( 'vw-curation-admin', 'vwCuration', [
		'searchUrl' => esc_url_raw( rest_url( 'vw/v1/post-search' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'strings'   => [
			'searching' => 'Searching…',
			'none'      => 'No matching stories.',
			'error'     => 'Search failed. Reload and try again.',
			'clear'     => 'Clear',
		],
	] );
}


/* ── REST: post search ─────────────────────────────────────────────────── */

add_action( 'rest_api_init', 'vw_curation_register_rest' );
function vw_curation_register_rest(): void {
	register_rest_route( 'vw/v1', '/post-search', [
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'vw_curation_rest_search',
		'permission_callback' => static function () {
			return current_user_can( 'vw_curate' );
		},
		'args'                => [
			'search' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'cats'   => [
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	] );
}

/**
 * Search results carrying an image-tier badge.
 *
 * The badge is the reason this is a custom route rather than core's
 * /wp/v2/search: 61.3% of the published archive has no usable featured image,
 * so a curator choosing a story for an image slot needs to see that before
 * pinning it, not after the homepage renders a text variant.
 */
function vw_curation_rest_search( WP_REST_Request $request ) {
	$term = trim( (string) $request->get_param( 'search' ) );
	if ( mb_strlen( $term ) < 2 ) {
		return rest_ensure_response( [] );
	}

	$cats = array_values( array_filter( array_map(
		'absint',
		explode( ',', (string) $request->get_param( 'cats' ) )
	) ) );

	$args = [
		's'                      => $term,
		'post_type'              => 'post',
		'post_status'            => [ 'publish', 'future', 'draft', 'pending' ],
		'posts_per_page'         => 12,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	];
	if ( $cats ) {
		$args['category__in'] = $cats;
	}

	$out = [];
	foreach ( get_posts( $args ) as $post ) {
		$out[] = vw_curation_post_summary( $post );
	}

	return rest_ensure_response( $out );
}

/** Shared shape for a post in the picker, server-rendered or via REST. */
function vw_curation_post_summary( WP_Post $post ): array {
	$tier   = vw_image_tier( (int) $post->ID );
	$status = vw_curation_pin_status( (int) $post->ID );

	return [
		'id'         => (int) $post->ID,
		'title'      => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		'date'       => get_the_date( 'M j, Y', $post ),
		'status'     => $post->post_status,
		'section'    => vw_curation_section_label( (int) $post->ID ),
		'tier'       => $tier,
		'tierLabel'  => vw_curation_tier_label( $tier ),
		'warning'    => $status['ok'] ? '' : $status['label'],
		'editUrl'    => (string) get_edit_post_link( $post->ID, 'raw' ),
	];
}

function vw_curation_tier_label( int $tier ): string {
	switch ( $tier ) {
		case 1:
			return 'Large image';
		case 2:
			return 'Medium image';
		case 3:
			return 'Small image';
		default:
			return 'No image';
	}
}

/** First non-Uncategorized category name, for orientation in search results. */
function vw_curation_section_label( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	foreach ( $terms as $t ) {
		if ( 'uncategorized' !== $t->slug ) {
			return $t->name;
		}
	}
	return $terms[0]->name ?? '';
}


/* ── Save ──────────────────────────────────────────────────────────────── */

add_action( 'admin_post_vw_curation_save', 'vw_curation_handle_save' );
function vw_curation_handle_save(): void {
	if ( ! current_user_can( 'vw_curate' ) ) {
		wp_die(
			esc_html( 'You do not have permission to curate this site.' ),
			esc_html( 'Forbidden' ),
			[ 'response' => 403 ]
		);
	}

	check_admin_referer( 'vw_curation_save', 'vw_curation_nonce' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by vw_curation_sanitize() against the registry.
	$raw = isset( $_POST['vw_curation'] ) ? wp_unslash( $_POST['vw_curation'] ) : [];

	$before = vw_curation_config();
	$clean  = vw_curation_sanitize( $raw );

	update_option( VW_CURATION_OPTION, $clean, true );

	/*
	 * Tell the operator what the save actually did.
	 *
	 * A flat "Curation saved." was itself the cause of a false bug report: three
	 * identical auto-fill slots were dragged into a new order, which is a genuine
	 * no-op, and the unchanged screen read as the save having reverted. A transient
	 * rather than query args because the summary is a list of sentences.
	 */
	set_transient(
		'vw_curation_notice_' . get_current_user_id(),
		vw_curation_describe_changes( $before, $clean ),
		60
	);

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by vw_chrome_sanitize().
	$chrome = isset( $_POST['vw_chrome'] ) ? wp_unslash( $_POST['vw_chrome'] ) : [];
	update_option( VW_CHROME_OPTION, vw_chrome_sanitize( $chrome ), true );

	// Template choices. Each is whitelisted against the registry before it is
	// stored; an unregistered slug is simply not written.
	foreach ( [ 'home', 'archive' ] as $surface ) {
		$key = 'vw_tpl_' . $surface;
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$slug = sanitize_key( wp_unslash( $_POST[ $key ] ) );
		if ( isset( vw_tpl_choices( $surface )[ $slug ] ) ) {
			update_option( vw_tpl_option_name( $surface ), $slug );
		}
	}

	vw_curation_flush_cache();
	vw_chrome_flush_cache();

	wp_safe_redirect( add_query_arg(
		[ 'page' => 'vw-curation', 'vw_saved' => '1' ],
		admin_url( 'admin.php' )
	) );
	exit;
}


/* ── Render ────────────────────────────────────────────────────────────── */

function vw_curation_admin_page(): void {
	if ( ! current_user_can( 'vw_curate' ) ) {
		wp_die(
			esc_html( 'You do not have permission to curate this site.' ),
			esc_html( 'Forbidden' ),
			[ 'response' => 403 ]
		);
	}

	$registry = vw_curation_registry();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag.
	$saved = isset( $_GET['vw_saved'] );
	?>
	<div class="wrap vwc">
		<h1>Homepage &amp; Sections</h1>

		<?php
		if ( $saved ) :
			$changes = get_transient( 'vw_curation_notice_' . get_current_user_id() );
			delete_transient( 'vw_curation_notice_' . get_current_user_id() );
			?>
			<?php if ( is_array( $changes ) && $changes ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong>Saved.</strong></p>
					<ul class="vwc-changes">
						<?php foreach ( $changes as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<div class="notice notice-info is-dismissible">
					<p><strong>Saved — but nothing changed.</strong>
					Reordering slots that hold the same setting has no effect: two auto-fill
					slots drawing from the same section are interchangeable. Pin a story, or
					point a slot at a different category, and the order will hold.</p>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<p class="vwc__intro">
			Every slot is <strong>Pin</strong> a chosen story, <strong>Auto</strong> the newest
			story from a category, or <strong>Hidden</strong>. Nothing here has to be set:
			anything left on Auto fills itself, so the site always renders a complete page.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vw_curation_save">
			<?php wp_nonce_field( 'vw_curation_save', 'vw_curation_nonce' ); ?>

			<h2 class="vwc__surface-head">Site settings</h2>
			<?php vw_chrome_render_fields(); ?>
			<?php vw_curation_render_templates(); ?>

			<h2 class="vwc__surface-head">Homepage</h2>
			<?php
			foreach ( $registry['home'] as $zone => $def ) {
				vw_curation_render_zone( 'home', $zone, $def, '' );
			}
			?>

			<h2 class="vwc__surface-head">Section fronts</h2>
			<?php
			foreach ( $registry['section'] as $slug => $zones ) {
				$term = get_category_by_slug( $slug );
				?>
				<h3 class="vwc__section-head"><?php echo esc_html( $term ? $term->name : $slug ); ?></h3>
				<?php
				foreach ( $zones as $zone => $def ) {
					vw_curation_render_zone( 'section', $zone, $def, $slug );
				}
			}
			?>

			<?php submit_button( 'Save curation' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Template pickers for the two option-backed surfaces.
 *
 * Section fronts are deliberately absent: their template is assigned per
 * category on the normal Edit Category screen, which is where a section is
 * configured. This block links there rather than duplicating the control.
 */
function vw_curation_render_templates(): void {
	$assigned = vw_tpl_assigned_sections();
	?>
	<section class="vwc-zone vwc-settings">
		<header class="vwc-zone__head">
			<h4 class="vwc-zone__title">Templates</h4>
			<span class="vwc-zone__locked">One per surface today; variants are added in code</span>
		</header>

		<div class="vwc-fields">
			<?php foreach ( [ 'home' => 'Homepage', 'archive' => 'Archive pages' ] as $surface => $label ) :
				$current = vw_tpl_current( $surface );
				?>
				<p class="vwc-field">
					<label for="<?php echo esc_attr( 'vwc-tpl-' . $surface ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
					<select id="<?php echo esc_attr( 'vwc-tpl-' . $surface ); ?>" name="<?php echo esc_attr( 'vw_tpl_' . $surface ); ?>">
						<?php foreach ( vw_tpl_choices( $surface ) as $slug => $tpl ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
								<?php echo esc_html( $tpl['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="vwc-field__help">
						<?php echo esc_html( vw_tpl_choices( $surface )[ $current ]['note'] ?? '' ); ?>
					</span>
				</p>
			<?php endforeach; ?>

			<p class="vwc-field">
				<strong>Section fronts</strong>
				<span class="vwc-field__help">
					Assigned per category, on the category&rsquo;s own edit screen.
					<?php if ( $assigned ) : ?>
						Currently curated:
						<?php
						$links = [];
						foreach ( $assigned as $row ) {
							$links[] = sprintf(
								'<a href="%s">%s</a>',
								esc_url( get_edit_term_link( (int) $row['term']->term_id, 'category' ) ),
								esc_html( $row['term']->name )
							);
						}
						echo wp_kses( implode( ', ', $links ), [ 'a' => [ 'href' => [] ] ] );
						?>.
					<?php else : ?>
						<strong>No category has a template assigned</strong> — every category is
						currently using the standard archive.
					<?php endif; ?>
				</span>
			</p>
		</div>
	</section>
	<?php
}

/**
 * What each slot is CURRENTLY showing on the site, keyed "surface:context:zone".
 *
 * Resolved once per request, per surface, in the same order the templates
 * resolve — home zones share one $used_ids chain exactly as homepage-v2.php
 * does, and each section front starts a fresh chain exactly as its part does.
 * Anything else would print a title the reader never sees.
 *
 * This is what makes an auto-fill slot legible. Without it every auto slot
 * looked identical on screen, so reordering two of them appeared to do nothing
 * — which is precisely the false bug report this screen produced.
 */
function vw_curation_admin_resolved(): array {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$map      = [];
	$registry = vw_curation_registry();

	$used = [];
	foreach ( array_keys( $registry['home'] ) as $zone ) {
		foreach ( vw_curation_resolve( 'home', $zone, $used ) as $slot ) {
			$map[ 'home::' . $zone ][ $slot['slot'] ] = $slot;
		}
	}

	foreach ( $registry['section'] as $slug => $zones ) {
		$section_used = [];
		foreach ( array_keys( $zones ) as $zone ) {
			foreach ( vw_curation_resolve( 'section', $zone, $section_used, $slug ) as $slot ) {
				$map[ 'section:' . $slug . ':' . $zone ][ $slot['slot'] ] = $slot;
			}
		}
	}

	return $map;
}

/** The resolved slot for one position, or null when the slot renders nothing. */
function vw_curation_admin_now_showing( string $surface, string $zone, string $context, int $index ): ?array {
	$key = $surface . ':' . ( 'section' === $surface ? $context : '' ) . ':' . $zone;
	return vw_curation_admin_resolved()[ $key ][ $index ] ?? null;
}

function vw_curation_render_zone( string $surface, string $zone, array $def, string $context ): void {
	$config = vw_curation_zone_config( $surface, $zone, $context );
	$prefix = 'section' === $surface
		? sprintf( 'vw_curation[section][%s][%s]', $context, $zone )
		: sprintf( 'vw_curation[%s][%s]', $surface, $zone );
	?>
	<section class="vwc-zone">
		<?php // Marks this zone as rendered by the form — see vw_curation_sanitize_zones(). ?>
		<input type="hidden" name="<?php echo esc_attr( $prefix . '[present]' ); ?>" value="1">

		<header class="vwc-zone__head">
			<h4 class="vwc-zone__title"><?php echo esc_html( $def['label'] ); ?></h4>

			<?php if ( ! empty( $def['can_hide'] ) ) : ?>
				<label class="vwc-zone__vis">
					<input type="checkbox"
						name="<?php echo esc_attr( $prefix . '[visible]' ); ?>"
						value="1" <?php checked( $config['visible'] ); ?>>
					Show this zone
				</label>
			<?php else : ?>
				<span class="vwc-zone__locked" title="This zone cannot be hidden.">Always shown</span>
			<?php endif; ?>
		</header>

		<ul class="vwc-slots" data-vwc-sortable="1">
			<?php
			foreach ( $def['slots'] as $i => $slot_def ) {
				vw_curation_render_slot( $prefix, $i, $slot_def, $def, $config['slots'][ $i ] ?? [], $surface, $zone, $context );
			}
			?>
		</ul>
	</section>
	<?php
}

function vw_curation_render_slot( string $prefix, int $index, array $slot_def, array $zone_def, array $slot, string $surface = 'home', string $zone = '', string $context = '' ): void {
	$mode = $slot['mode'] ?? 'auto';
	$post = (int) ( $slot['post'] ?? 0 );
	$cat  = (int) ( $slot['cat'] ?? 0 );
	$name = sprintf( '%s[slots][%d]', $prefix, $index );

	// Slot 0 of an unhideable zone is the zone itself: no Hidden option exists,
	// matching the sanitizer and the resolver rather than relying on either.
	$can_hide_slot = ! ( empty( $zone_def['can_hide'] ) && 0 === $index );

	$pinned  = $post ? get_post( $post ) : null;
	$summary = $pinned instanceof WP_Post ? vw_curation_post_summary( $pinned ) : null;
	$status  = $post ? vw_curation_pin_status( $post ) : [ 'ok' => true, 'label' => '' ];
	$broken  = $post && ! $status['ok'];

	$req_note = [
		'tier1' => 'wants a large image',
		'tier2' => 'wants an image',
		'none'  => 'text only',
	][ $slot_def['image'] ] ?? '';
	?>
	<li class="vwc-slot<?php echo $broken ? ' vwc-slot--broken' : ''; ?>" data-vwc-slot>
		<span class="vwc-slot__grip">
			<span class="vwc-slot__handle" aria-hidden="true">⋮⋮</span>
			<span class="vwc-slot__move">
				<button type="button" class="vwc-move" data-vwc-move="up"
					aria-label="<?php echo esc_attr( 'Move ' . $slot_def['label'] . ' up' ); ?>">&uarr;</button>
				<button type="button" class="vwc-move" data-vwc-move="down"
					aria-label="<?php echo esc_attr( 'Move ' . $slot_def['label'] . ' down' ); ?>">&darr;</button>
			</span>
		</span>

		<div class="vwc-slot__body">
			<p class="vwc-slot__role">
				<strong><?php echo esc_html( $slot_def['label'] ); ?></strong>
				<?php if ( $req_note ) : ?>
					<span class="vwc-slot__req"><?php echo esc_html( $req_note ); ?></span>
				<?php endif; ?>
			</p>

			<?php
			/*
			 * The live answer to "what is in this slot right now", shown for every
			 * mode. On an auto-fill slot it is the only thing distinguishing one
			 * slot from another on screen.
			 */
			$showing = vw_curation_admin_now_showing( $surface, $zone, $context, $index );
			?>
			<p class="vwc-showing">
				<?php if ( $showing ) : ?>
					<span class="vwc-showing__label">Now showing</span>
					<span class="vwc-showing__title"><?php echo esc_html( html_entity_decode( wp_strip_all_tags( get_the_title( $showing['post'] ) ), ENT_QUOTES, 'UTF-8' ) ); ?></span>
					<?php if ( 'auto' === $showing['mode'] ) : ?>
						<span class="vwc-showing__how">auto-filled</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="vwc-showing__label vwc-showing__label--empty">Not rendering &mdash; this slot is hidden</span>
				<?php endif; ?>
			</p>

			<div class="vwc-slot__modes" role="group">
				<?php
				$modes = [ 'pin' => 'Pin a story', 'auto' => 'Auto-fill' ];
				if ( $can_hide_slot ) {
					$modes['hidden'] = 'Hidden';
				}
				foreach ( $modes as $value => $label ) :
					?>
					<label class="vwc-mode">
						<input type="radio"
							name="<?php echo esc_attr( $name . '[mode]' ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							<?php checked( $mode, $value ); ?>
							data-vwc-mode>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="vwc-pane vwc-pane--pin" data-vwc-pane="pin" <?php echo 'pin' === $mode ? '' : 'hidden'; ?>>
				<input type="hidden"
					name="<?php echo esc_attr( $name . '[post]' ); ?>"
					value="<?php echo esc_attr( (string) $post ); ?>"
					data-vwc-pin-id>

				<div class="vwc-pin" data-vwc-pin-current <?php echo $post ? '' : 'hidden'; ?>>
					<?php if ( $summary ) : ?>
						<span class="vwc-pin__title"><?php echo esc_html( $summary['title'] ); ?></span>
						<span class="vwc-badge vwc-badge--t<?php echo esc_attr( (string) $summary['tier'] ); ?>">
							<?php echo esc_html( $summary['tierLabel'] ); ?>
						</span>
						<span class="vwc-pin__date"><?php echo esc_html( $summary['date'] ); ?></span>
					<?php elseif ( $post ) : ?>
						<span class="vwc-pin__title">Post #<?php echo esc_html( (string) $post ); ?></span>
					<?php endif; ?>
					<button type="button" class="button-link vwc-pin__clear" data-vwc-clear>Clear</button>
				</div>

				<?php if ( $broken ) : ?>
					<p class="vwc-broken" role="alert">
						<strong>Pin not working:</strong>
						<?php echo esc_html( $status['label'] ); ?>.
						This slot is auto-filling instead.
					</p>
				<?php endif; ?>

				<label class="screen-reader-text" for="<?php echo esc_attr( 'vwc-s-' . md5( $name ) ); ?>">
					Search stories
				</label>
				<input type="search"
					id="<?php echo esc_attr( 'vwc-s-' . md5( $name ) ); ?>"
					class="vwc-search"
					placeholder="Search stories by title…"
					autocomplete="off"
					data-vwc-search
					data-vwc-cats="<?php echo esc_attr( implode( ',', array_map( 'intval', $zone_def['cats'] ) ) ); ?>">
				<ul class="vwc-results" data-vwc-results hidden></ul>
			</div>

			<div class="vwc-pane vwc-pane--auto" data-vwc-pane="auto" <?php echo 'auto' === $mode ? '' : 'hidden'; ?>>
				<label>
					Fill from
					<select name="<?php echo esc_attr( $name . '[cat]' ); ?>">
						<option value="0"><?php echo esc_html( vw_curation_default_cat_label( $zone_def ) ); ?></option>
						<?php foreach ( vw_curation_cat_choices( $zone_def ) as $id => $label ) : ?>
							<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $cat, $id ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>
	</li>
	<?php
}

function vw_curation_default_cat_label( array $zone_def ): string {
	return $zone_def['cats'] ? 'This zone’s sections' : 'Anywhere on the site';
}

/**
 * Categories offered for auto-fill. A zone with declared cats offers exactly
 * those; a sitewide zone offers the editorial sections rather than all 400-odd
 * categories, most of which are empty spam terms awaiting cleanup.
 */
function vw_curation_cat_choices( array $zone_def ): array {
	$ids = $zone_def['cats'] ?: vw_curation_sitewide_cats();

	$out = [];
	foreach ( $ids as $id ) {
		$term = get_term( (int) $id, 'category' );
		if ( $term instanceof WP_Term ) {
			$out[ (int) $id ] = $term->name;
		}
	}
	asort( $out );
	return $out;
}

/** The editorial sections, used as the choice list for sitewide zones. */
function vw_curation_sitewide_cats(): array {
	$ids = [];
	foreach ( vw_curation_registry()['home'] as $def ) {
		$ids = array_merge( $ids, $def['cats'] );
	}
	$ids = array_merge( $ids, [ 15, 17 ] ); // must-see-films, out-n-about
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}
