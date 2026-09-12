<?php
/**
 * Site chrome settings — the masthead's editable text.
 *
 * Motto, the top-right slogan line, whether the dateline strip shows at all,
 * and the founding year. One autoloaded option, read through accessors so no
 * template ever touches the raw array.
 *
 * The founding year lives here rather than in a constant because it feeds two
 * different things — the "Independent Since" line and the archive closer's
 * derived age — and Ricardo corrected it once already (2006 → 2012). A value
 * that has been wrong once should be editable without a deploy.
 *
 * The slogan's DEFAULT is derived from the founding year, so changing the year
 * updates the line for anyone who has not overridden it. Once an operator types
 * their own slogan it is theirs and the year no longer touches it — which is
 * the intended trade, but it does mean the two can drift apart by hand.
 *
 * Both the dateline and the motto are uppercased by homepage-v2.css, so values
 * are stored in sentence case and the display casing stays a styling decision.
 */

defined( 'ABSPATH' ) || exit;

const VW_CHROME_OPTION   = 'vw_chrome';
const VW_CHROME_SCHEMA   = 1;
const VW_CHROME_FOUNDED  = 2012;  // Corrected from 2006 by Ricardo, 2026-09-11.
const VW_CHROME_MOTTO    = 'The Record of the City’s Culture';
const VW_CHROME_PLACE    = 'Vancouver, BC';

/** Raw stored settings, schema-checked. */
function vw_chrome_config( bool $refresh = false ): array {
	static $config = null;

	if ( $refresh ) {
		$config = null;
	}
	if ( null !== $config ) {
		return $config;
	}

	$stored = get_option( VW_CHROME_OPTION, [] );
	$config = ( is_array( $stored ) && (int) ( $stored['_schema'] ?? 0 ) === VW_CHROME_SCHEMA )
		? $stored
		: [];

	return $config;
}

function vw_chrome_flush_cache(): void {
	wp_cache_delete( VW_CHROME_OPTION, 'options' );
	vw_chrome_config( true );
}

/** Founding year. */
function vw_chrome_founded(): int {
	$v = (int) ( vw_chrome_config()['founded'] ?? 0 );
	return $v ?: VW_CHROME_FOUNDED;
}

/** Masthead motto. Empty string is a deliberate choice and is respected. */
function vw_chrome_motto(): string {
	$c = vw_chrome_config();
	return array_key_exists( 'motto', $c ) ? (string) $c['motto'] : VW_CHROME_MOTTO;
}

/** Top-right slogan. Defaults derived from the founding year; overridable. */
function vw_chrome_slogan(): string {
	$c = vw_chrome_config();
	if ( array_key_exists( 'slogan', $c ) ) {
		return (string) $c['slogan'];
	}
	return 'No Ads · No Clickbait · Independent Since ' . vw_chrome_founded();
}

/** Whether the dateline strip renders at all. */
function vw_chrome_show_dateline(): bool {
	$c = vw_chrome_config();
	return array_key_exists( 'dateline', $c ) ? (bool) $c['dateline'] : true;
}

/** Left-hand dateline text: today's date plus the place. */
function vw_chrome_dateline(): string {
	return date_i18n( 'l, F j, Y' ) . ' · ' . VW_CHROME_PLACE;
}

/** Years since founding, for the archive closer. Never below 1. */
function vw_chrome_years(): int {
	return max( 1, (int) date_i18n( 'Y' ) - vw_chrome_founded() );
}

/**
 * Sanitize submitted chrome settings.
 *
 * Only keys present in the submission are stored, so an absent field falls back
 * to its derived default rather than being frozen as an empty string — the same
 * present/absent discipline the curation sanitizer uses.
 */
function vw_chrome_sanitize( $raw ): array {
	$raw = is_array( $raw ) ? $raw : [];
	$out = [ '_schema' => VW_CHROME_SCHEMA ];

	if ( empty( $raw['present'] ) ) {
		$current = vw_chrome_config();
		unset( $current['_schema'] );
		return array_merge( $out, $current );
	}

	// A year the publication could not plausibly have been founded in is a typo,
	// not a preference: it would print a negative or absurd age in the closer.
	$year = absint( $raw['founded'] ?? 0 );
	$out['founded'] = ( $year >= 1900 && $year <= (int) date_i18n( 'Y' ) )
		? $year
		: VW_CHROME_FOUNDED;

	$out['motto']  = sanitize_text_field( (string) ( $raw['motto'] ?? '' ) );
	$out['slogan'] = sanitize_text_field( (string) ( $raw['slogan'] ?? '' ) );

	// An emptied slogan means "use the derived default", not "print nothing" —
	// the line is structural. An emptied motto genuinely means no motto.
	if ( '' === $out['slogan'] ) {
		unset( $out['slogan'] );
	}

	$out['dateline'] = ! empty( $raw['dateline'] );

	return $out;
}

/** Settings block, rendered inside the curation form. */
function vw_chrome_render_fields(): void {
	$founded  = vw_chrome_founded();
	$motto    = vw_chrome_motto();
	$slogan   = vw_chrome_slogan();
	$dateline = vw_chrome_show_dateline();
	?>
	<section class="vwc-zone vwc-settings">
		<input type="hidden" name="vw_chrome[present]" value="1">

		<header class="vwc-zone__head">
			<h4 class="vwc-zone__title">Masthead text</h4>
			<span class="vwc-zone__locked">Shown on every page that uses the masthead</span>
		</header>

		<div class="vwc-fields">
			<p class="vwc-field">
				<label for="vwc-motto"><strong>Motto</strong></label>
				<input type="text" id="vwc-motto" name="vw_chrome[motto]" class="regular-text"
					value="<?php echo esc_attr( $motto ); ?>" maxlength="160">
				<span class="vwc-field__help">Under the wordmark. Displayed in caps. Leave empty for no motto.</span>
			</p>

			<p class="vwc-field">
				<label for="vwc-slogan"><strong>Top-right line</strong></label>
				<input type="text" id="vwc-slogan" name="vw_chrome[slogan]" class="regular-text"
					value="<?php echo esc_attr( $slogan ); ?>" maxlength="160">
				<span class="vwc-field__help">
					Right-hand end of the dateline strip. Empty resets it to
					&ldquo;No Ads &middot; No Clickbait &middot; Independent Since <?php echo esc_html( (string) $founded ); ?>&rdquo;.
				</span>
			</p>

			<p class="vwc-field">
				<label for="vwc-founded"><strong>Founding year</strong></label>
				<input type="number" id="vwc-founded" name="vw_chrome[founded]" class="small-text"
					value="<?php echo esc_attr( (string) $founded ); ?>" min="1900" max="<?php echo esc_attr( date_i18n( 'Y' ) ); ?>">
				<span class="vwc-field__help">
					Feeds the archive closer&rsquo;s age (currently
					<strong><?php echo esc_html( (string) vw_chrome_years() ); ?> years</strong>)
					and the default top-right line.
				</span>
			</p>

			<p class="vwc-field">
				<label>
					<input type="checkbox" name="vw_chrome[dateline]" value="1" <?php checked( $dateline ); ?>>
					<strong>Show the dateline strip</strong>
				</label>
				<span class="vwc-field__help">The thin line above the wordmark carrying the date and the top-right line.</span>
			</p>
		</div>
	</section>
	<?php
}
