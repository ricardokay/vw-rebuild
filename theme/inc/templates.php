<?php
/**
 * Template registry and resolver.
 *
 * Three surfaces, one mechanism. Ships with exactly one template each; the
 * point of the registry is that a second costs a file and a row rather than a
 * refactor.
 *
 *   home     option vw_tpl_home     — section-parts/home-{file}
 *   section  term meta _vw_tpl      — section-parts/{file}, per category
 *   archive  option vw_tpl_archive  — the parent theme's archive, for now
 *
 * The section entry also replaces category.php's hardcoded $curated array: a
 * category is curated because it has a template assigned, not because its slug
 * appears in a list someone remembered to update. Assigning one is a dropdown
 * on the normal Edit Category screen.
 *
 * Security: a stored slug is whitelisted against the registry on write AND on
 * read, and an unknown slug resolves to the surface default. No path is ever
 * built from stored or submitted input — the registry supplies the filename.
 */

defined( 'ABSPATH' ) || exit;

const VW_TPL_TERM_META = '_vw_tpl';

function vw_tpl_registry(): array {
	static $registry = null;
	if ( null !== $registry ) {
		return $registry;
	}

	$registry = [
		'home' => [
			'default' => 'v2',
			'templates' => [
				'v2' => [
					'label' => 'Vancouver Weekly v2',
					'file'  => 'homepage-v2.php',
					'note'  => 'Lead, music, photography band, tri-column, archive closer.',
				],
			],
		],

		'section' => [
			'default' => 'lead-3col',
			'templates' => [
				'lead-3col' => [
					'label' => 'Lead block + featured list',
					'file'  => '',   // resolved per slug — see vw_tpl_section_file()
					'note'  => 'Three-column lead block, then a featured story with a compact list.',
				],
			],
		],

		'archive' => [
			'default' => 'default',
			'templates' => [
				'default' => [
					'label' => 'Newspack archive, VW styling',
					'file'  => '',   // the parent theme's archive.php
					'note'  => 'Inherited archive with the design system applied.',
				],
			],
		],
	];

	return $registry;
}

/** Valid template slugs for a surface. */
function vw_tpl_choices( string $surface ): array {
	$reg = vw_tpl_registry();
	return $reg[ $surface ]['templates'] ?? [];
}

function vw_tpl_default( string $surface ): string {
	$reg = vw_tpl_registry();
	return (string) ( $reg[ $surface ]['default'] ?? '' );
}

/** Whitelist a slug against the registry. Unknown resolves to the default. */
function vw_tpl_valid( string $surface, string $slug ): string {
	return isset( vw_tpl_choices( $surface )[ $slug ] ) ? $slug : vw_tpl_default( $surface );
}

function vw_tpl_option_name( string $surface ): string {
	return 'section' === $surface ? '' : 'vw_tpl_' . $surface;
}

/**
 * The template slug in force for a surface.
 * $context is a category term ID for 'section'.
 */
function vw_tpl_current( string $surface, int $context = 0 ): string {
	if ( 'section' === $surface ) {
		$slug = $context ? (string) get_term_meta( $context, VW_TPL_TERM_META, true ) : '';
		return $slug ? vw_tpl_valid( 'section', $slug ) : '';
	}

	$opt = vw_tpl_option_name( $surface );
	return vw_tpl_valid( $surface, (string) get_option( $opt, vw_tpl_default( $surface ) ) );
}

/**
 * Is this category curated? True when a template is assigned to it.
 *
 * This is the replacement for category.php's $curated array. A section front
 * also needs its part file to exist, so a template assigned to a category with
 * no section-parts file falls through to the normal archive rather than fatally
 * including a missing path.
 */
function vw_tpl_section_part( WP_Term $term ): ?array {
	if ( ! vw_tpl_current( 'section', (int) $term->term_id ) ) {
		return null;
	}

	$dir = get_stylesheet_directory() . '/section-parts/';

	$php = $dir . $term->slug . '.php';
	if ( file_exists( $php ) ) {
		return [ 'type' => 'php', 'path' => $php ];
	}

	// Legacy .html parts hold a newspack-blocks/homepage-articles block and are
	// rendered through do_blocks(). Only must-see-films still uses one. It is
	// kept working rather than silently dropped, but it cannot be curated —
	// nothing in a block template reads the curation option — so it stays on
	// the known-dirt list until it is ported to PHP.
	$html = $dir . $term->slug . '.html';
	if ( file_exists( $html ) ) {
		return [ 'type' => 'html', 'path' => $html ];
	}

	return null;
}

/** Absolute path to the homepage part currently in force. */
function vw_tpl_home_file(): string {
	$slug = vw_tpl_current( 'home' );
	$file = vw_tpl_choices( 'home' )[ $slug ]['file'] ?? '';
	$path = get_stylesheet_directory() . '/section-parts/' . $file;
	return ( $file && file_exists( $path ) ) ? $path : '';
}

/** Categories with a template assigned, for the admin screen. */
function vw_tpl_assigned_sections(): array {
	$out = [];
	foreach ( get_categories( [ 'hide_empty' => false ] ) as $cat ) {
		$slug = vw_tpl_current( 'section', (int) $cat->term_id );
		if ( $slug ) {
			$out[ $cat->slug ] = [ 'term' => $cat, 'template' => $slug ];
		}
	}
	return $out;
}


/* ── Option registration ───────────────────────────────────────────────── */

add_action( 'admin_init', 'vw_tpl_register_settings' );
function vw_tpl_register_settings(): void {
	foreach ( [ 'home', 'archive' ] as $surface ) {
		register_setting( 'vw_templates', vw_tpl_option_name( $surface ), [
			'type'              => 'string',
			'show_in_rest'      => false,
			'default'           => vw_tpl_default( $surface ),
			'sanitize_callback' => static function ( $value ) use ( $surface ) {
				return vw_tpl_valid( $surface, (string) $value );
			},
		] );
	}
}


/* ── Term-meta dropdown on the Edit Category screen ────────────────────── */

add_action( 'category_edit_form_fields', 'vw_tpl_term_field' );
function vw_tpl_term_field( WP_Term $term ): void {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$current = (string) get_term_meta( $term->term_id, VW_TPL_TERM_META, true );
	$has_part = file_exists( get_stylesheet_directory() . '/section-parts/' . $term->slug . '.php' );
	wp_nonce_field( 'vw_tpl_term_' . $term->term_id, 'vw_tpl_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><label for="vw_tpl">Section front template</label></th>
		<td>
			<select name="vw_tpl" id="vw_tpl">
				<option value="">Not a curated section — use the normal archive</option>
				<?php foreach ( vw_tpl_choices( 'section' ) as $slug => $tpl ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>>
						<?php echo esc_html( $tpl['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				Assigning a template makes <code>/category/<?php echo esc_html( $term->slug ); ?>/</code>
				render a curated front instead of the standard archive.
				<?php if ( ! $has_part ) : ?>
					<br><strong>No <code>section-parts/<?php echo esc_html( $term->slug ); ?>.php</code> exists yet</strong> —
					until one does, this category keeps using the normal archive.
				<?php endif; ?>
			</p>
		</td>
	</tr>
	<?php
}

add_action( 'edited_category', 'vw_tpl_save_term' );
function vw_tpl_save_term( int $term_id ): void {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( ! isset( $_POST['vw_tpl_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vw_tpl_nonce'] ) ), 'vw_tpl_term_' . $term_id ) ) {
		return;
	}
	if ( ! isset( $_POST['vw_tpl'] ) ) {
		return;
	}

	$slug = sanitize_key( wp_unslash( $_POST['vw_tpl'] ) );

	if ( '' === $slug ) {
		delete_term_meta( $term_id, VW_TPL_TERM_META );
		return;
	}

	// Whitelisted on write as well as on read — a slug that is not registered
	// never reaches the database.
	if ( ! isset( vw_tpl_choices( 'section' )[ $slug ] ) ) {
		return;
	}

	update_term_meta( $term_id, VW_TPL_TERM_META, $slug );
}
