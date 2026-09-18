<?php
/**
 * TOTP two-factor authentication (RFC 6238: SHA-1, 6 digits, 30 s step, ±1 step).
 *
 * Option vw_2fa_enforced:
 *   '0'    (default) off — nobody is challenged. This is the SSH kill switch:
 *          wp option update vw_2fa_enforced 0
 *   'test' enrolled users are challenged; nobody is required to enroll.
 *   '1'    enrolled users are challenged; administrators and editors who have
 *          not enrolled are refused at login.
 */

defined( 'ABSPATH' ) || exit;

const VW_2FA_ISSUER        = 'Vancouver Weekly';
const VW_2FA_SCOPED_ROLES  = [ 'administrator', 'editor' ];
const VW_2FA_BACKUP_COUNT  = 8;
const VW_2FA_LOGIN_TTL     = 10 * MINUTE_IN_SECONDS;
const VW_2FA_TOKEN_TRIES   = 5;
const VW_2FA_META_SECRET   = '_vw_2fa_secret';
const VW_2FA_META_PENDING  = '_vw_2fa_pending_secret';
const VW_2FA_META_BACKUP   = '_vw_2fa_backup_codes';
const VW_2FA_META_LAST     = '_vw_2fa_last_step';
const VW_2FA_META_SINCE    = '_vw_2fa_enrolled_at';
const VW_2FA_META_LOGIN    = '_vw_2fa_login';

// ── Primitives ───────────────────────────────────────────────────────────────

function vw_2fa_base32_encode( string $bin ): string {
	$alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$bits  = '';
	foreach ( str_split( $bin ) as $c ) {
		$bits .= str_pad( decbin( ord( $c ) ), 8, '0', STR_PAD_LEFT );
	}
	$out = '';
	foreach ( str_split( $bits, 5 ) as $chunk ) {
		$out .= $alpha[ bindec( str_pad( $chunk, 5, '0' ) ) ];
	}
	return $out;
}

function vw_2fa_base32_decode( string $b32 ): string {
	$alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$b32   = strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', $b32 ) );
	$bits  = '';
	foreach ( str_split( $b32 ) as $c ) {
		$bits .= str_pad( decbin( strpos( $alpha, $c ) ), 5, '0', STR_PAD_LEFT );
	}
	$out = '';
	foreach ( str_split( $bits, 8 ) as $byte ) {
		if ( 8 === strlen( $byte ) ) {
			$out .= chr( bindec( $byte ) );
		}
	}
	return $out;
}

function vw_2fa_hotp( string $key, int $counter ): string {
	$hash = hash_hmac( 'sha1', pack( 'J', $counter ), $key, true );
	$off  = ord( $hash[19] ) & 0x0F;
	$num  = ( ( ord( $hash[ $off ] ) & 0x7F ) << 24 ) | ( ord( $hash[ $off + 1 ] ) << 16 )
		| ( ord( $hash[ $off + 2 ] ) << 8 ) | ord( $hash[ $off + 3 ] );
	return str_pad( (string) ( $num % 1000000 ), 6, '0', STR_PAD_LEFT );
}

/** Returns the matching time step, or null. */
function vw_2fa_match_step( string $secret_b32, string $code, ?int $now = null ): ?int {
	$code = preg_replace( '/\D/', '', $code );
	if ( 6 !== strlen( $code ) ) {
		return null;
	}
	$key  = vw_2fa_base32_decode( $secret_b32 );
	$step = intdiv( $now ?? time(), 30 );
	foreach ( [ 0, -1, 1 ] as $d ) {
		if ( hash_equals( vw_2fa_hotp( $key, $step + $d ), $code ) ) {
			return $step + $d;
		}
	}
	return null;
}

// ── State ────────────────────────────────────────────────────────────────────

function vw_2fa_mode(): string {
	$v = (string) get_option( 'vw_2fa_enforced', '0' );
	return in_array( $v, [ '1', 'test' ], true ) ? $v : '0';
}

function vw_2fa_is_enrolled( int $user_id ): bool {
	return '' !== (string) get_user_meta( $user_id, VW_2FA_META_SECRET, true );
}

function vw_2fa_is_scoped( WP_User $user ): bool {
	return (bool) array_intersect( VW_2FA_SCOPED_ROLES, (array) $user->roles );
}

/** 'pass' | 'challenge' | 'refuse' */
function vw_2fa_decision( WP_User $user ): string {
	$mode = vw_2fa_mode();
	if ( '0' === $mode ) {
		return 'pass';
	}
	if ( vw_2fa_is_enrolled( $user->ID ) ) {
		return 'challenge';
	}
	return ( '1' === $mode && vw_2fa_is_scoped( $user ) ) ? 'refuse' : 'pass';
}

function vw_2fa_verify_totp( int $user_id, string $code ): bool {
	$secret = (string) get_user_meta( $user_id, VW_2FA_META_SECRET, true );
	if ( '' === $secret ) {
		return false;
	}
	$step = vw_2fa_match_step( $secret, $code );
	if ( null === $step || $step <= (int) get_user_meta( $user_id, VW_2FA_META_LAST, true ) ) {
		return false; // No match, or a replay of an already-used code.
	}
	update_user_meta( $user_id, VW_2FA_META_LAST, $step );
	return true;
}

function vw_2fa_normalize_backup( string $code ): string {
	return strtolower( preg_replace( '/[^A-Za-z0-9]/', '', $code ) );
}

function vw_2fa_use_backup_code( int $user_id, string $code ): bool {
	$code = vw_2fa_normalize_backup( $code );
	if ( 10 !== strlen( $code ) ) {
		return false;
	}
	$hashes = (array) get_user_meta( $user_id, VW_2FA_META_BACKUP, true );
	foreach ( $hashes as $i => $hash ) {
		if ( is_string( $hash ) && wp_check_password( $code, $hash ) ) {
			unset( $hashes[ $i ] );
			update_user_meta( $user_id, VW_2FA_META_BACKUP, array_values( $hashes ) );
			vw_security_log( "2FA_BACKUP_USED user={$user_id} remaining=" . count( $hashes ) );
			return true;
		}
	}
	return false;
}

/** Stores hashes, returns the plaintext codes (shown once). */
function vw_2fa_new_backup_codes( int $user_id ): array {
	$alpha  = 'abcdefghjkmnpqrstuvwxyz23456789';
	$plain  = [];
	$hashes = [];
	for ( $n = 0; $n < VW_2FA_BACKUP_COUNT; $n++ ) {
		$c = '';
		for ( $i = 0; $i < 10; $i++ ) {
			$c .= $alpha[ random_int( 0, strlen( $alpha ) - 1 ) ];
		}
		$plain[]  = substr( $c, 0, 5 ) . '-' . substr( $c, 5 );
		$hashes[] = wp_hash_password( $c );
	}
	update_user_meta( $user_id, VW_2FA_META_BACKUP, $hashes );
	set_transient( 'vw_2fa_fresh_' . $user_id, $plain, 15 * MINUTE_IN_SECONDS );
	return $plain;
}

function vw_2fa_backup_remaining( int $user_id ): int {
	return count( array_filter( (array) get_user_meta( $user_id, VW_2FA_META_BACKUP, true ), 'is_string' ) );
}

function vw_2fa_reset( int $user_id ): void {
	foreach ( [ VW_2FA_META_SECRET, VW_2FA_META_PENDING, VW_2FA_META_BACKUP, VW_2FA_META_LAST, VW_2FA_META_SINCE, VW_2FA_META_LOGIN ] as $k ) {
		delete_user_meta( $user_id, $k );
	}
	delete_transient( 'vw_2fa_fresh_' . $user_id );
}

function vw_2fa_otpauth_uri( WP_User $user, string $secret ): string {
	return 'otpauth://totp/' . rawurlencode( VW_2FA_ISSUER ) . ':' . rawurlencode( $user->user_login )
		. '?secret=' . $secret . '&issuer=' . rawurlencode( VW_2FA_ISSUER );
}

// ── Other authentication paths ───────────────────────────────────────────────

// Application passwords would skip the second step; nobody here uses them.
add_filter( 'wp_is_application_passwords_available_for_user', function ( $available, $user ) {
	return ( $user instanceof WP_User && 'pass' !== vw_2fa_decision( $user ) ) ? false : $available;
}, 10, 2 );

add_filter( 'newspack_can_magic_link', function ( $can, $user ) {
	return ( $user instanceof WP_User && 'pass' !== vw_2fa_decision( $user ) ) ? false : $can;
}, 10, 2 );

// ── Login step 1: password accepted → hold the session, ask for the code ────

$GLOBALS['vw_2fa_hold']  = 0;
$GLOBALS['vw_2fa_token'] = '';
$GLOBALS['vw_2fa_done']  = false;

add_filter( 'authenticate', function ( $user ) {
	if ( ! $user instanceof WP_User ) {
		return $user;
	}
	$decision = vw_2fa_decision( $user );
	if ( 'refuse' === $decision ) {
		vw_security_log( "2FA_REFUSED_UNENROLLED user={$user->ID}" );
		return new WP_Error( 'vw_2fa_required', '<strong>Error:</strong> Two-factor authentication is required for this account and has not been set up. Contact the site administrator.' );
	}
	if ( 'challenge' !== $decision ) {
		return $user;
	}
	$on_login_form = 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
	if ( ! $on_login_form ) {
		return new WP_Error( 'vw_2fa_login_page', '<strong>Error:</strong> This account must log in through the login page.' );
	}
	$GLOBALS['vw_2fa_hold'] = $user->ID;
	return $user;
}, 99 );

// Newspack applies this filter with only the first argument.
add_filter( 'send_auth_cookies', function ( $send, $expire = 0, $expiration = 0, $user_id = 0 ) {
	return ( $GLOBALS['vw_2fa_hold'] && (int) $user_id === $GLOBALS['vw_2fa_hold'] && ! $GLOBALS['vw_2fa_done'] ) ? false : $send;
}, 99, 4 );

add_action( 'set_logged_in_cookie', function ( $cookie, $expire = 0, $expiration = 0, $user_id = 0, $scheme = '', $token = '' ) {
	if ( $GLOBALS['vw_2fa_hold'] && (int) $user_id === $GLOBALS['vw_2fa_hold'] && ! $GLOBALS['vw_2fa_done'] ) {
		$GLOBALS['vw_2fa_token'] = $token;
	}
}, 10, 6 );

add_action( 'wp_login', function ( $login, $user ) {
	if ( $GLOBALS['vw_2fa_done'] || ! $GLOBALS['vw_2fa_hold'] || $user->ID !== $GLOBALS['vw_2fa_hold'] ) {
		return;
	}
	if ( $GLOBALS['vw_2fa_token'] ) {
		WP_Session_Tokens::get_instance( $user->ID )->destroy( $GLOBALS['vw_2fa_token'] );
	}
	$token = wp_generate_password( 40, false );
	update_user_meta( $user->ID, VW_2FA_META_LOGIN, [
		'hash'     => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
		'exp'      => time() + VW_2FA_LOGIN_TTL,
		'remember' => ! empty( $_POST['rememberme'] ),
		'fails'    => 0,
	] );
	vw_2fa_render_form( $user, $token, (string) ( $_REQUEST['redirect_to'] ?? '' ), isset( $_REQUEST['interim-login'] ) );
}, 1, 2 );

function vw_2fa_render_form( WP_User $user, string $token, string $redirect_to, bool $interim, ?WP_Error $error = null ): void {
	nocache_headers();
	login_header( 'Two-factor authentication', '', $error );
	?>
	<form name="vw2faform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php?action=vw_2fa', 'login_post' ) ); ?>" method="post" autocomplete="off">
		<input type="hidden" name="vw_2fa_uid" value="<?php echo (int) $user->ID; ?>">
		<input type="hidden" name="vw_2fa_token" value="<?php echo esc_attr( $token ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
		<?php if ( $interim ) : ?><input type="hidden" name="interim-login" value="1"><?php endif; ?>
		<p>
			<label for="vw_2fa_code">Authentication code</label>
			<input type="text" name="vw_2fa_code" id="vw_2fa_code" class="input" value="" size="20" inputmode="numeric" autocomplete="one-time-code" autocapitalize="off" spellcheck="false">
		</p>
		<p style="margin-bottom:16px">Enter the 6-digit code from your authenticator app for <?php echo esc_html( VW_2FA_ISSUER ); ?>. Lost your phone? Enter one of your backup codes instead.</p>
		<p class="submit"><input type="submit" class="button button-primary button-large" value="Verify"></p>
	</form>
	<?php
	login_footer( 'vw_2fa_code' );
	exit;
}

// ── Login step 2: verify the code, then issue the real session ──────────────

add_action( 'login_form_vw_2fa', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_safe_redirect( wp_login_url() );
		exit;
	}
	$user_id  = absint( $_POST['vw_2fa_uid'] ?? 0 );
	$token    = (string) wp_unslash( $_POST['vw_2fa_token'] ?? '' );
	$code     = trim( (string) wp_unslash( $_POST['vw_2fa_code'] ?? '' ) );
	$redirect = (string) wp_unslash( $_POST['redirect_to'] ?? '' );
	$interim  = isset( $_POST['interim-login'] );
	$user     = $user_id ? get_userdata( $user_id ) : false;
	$pending  = $user ? get_user_meta( $user_id, VW_2FA_META_LOGIN, true ) : null;

	$valid = $user && is_array( $pending ) && $token && ( $pending['exp'] ?? 0 ) > time()
		&& hash_equals( (string) $pending['hash'], hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ) );
	if ( ! $valid ) {
		wp_safe_redirect( add_query_arg( 'vw_2fa', 'expired', wp_login_url( $redirect ) ) );
		exit;
	}

	$ip = vw_login_client_ip();
	if ( vw_login_locked_until( $ip, $user->user_login ) > time() ) {
		vw_2fa_render_form( $user, $token, $redirect, $interim, new WP_Error( 'vw_locked', vw_login_locked_message( $ip, $user->user_login ) ) );
	}

	$ok = '' !== $code && ( vw_2fa_verify_totp( $user_id, $code ) || vw_2fa_use_backup_code( $user_id, $code ) );
	if ( ! $ok ) {
		vw_login_record_failure( $ip, $user->user_login );
		vw_security_log( "2FA_FAIL user={$user_id} ip={$ip}" );
		$pending['fails'] = (int) $pending['fails'] + 1;
		if ( $pending['fails'] >= VW_2FA_TOKEN_TRIES ) {
			delete_user_meta( $user_id, VW_2FA_META_LOGIN );
			wp_safe_redirect( add_query_arg( 'vw_2fa', 'expired', wp_login_url( $redirect ) ) );
			exit;
		}
		update_user_meta( $user_id, VW_2FA_META_LOGIN, $pending );
		vw_2fa_render_form( $user, $token, $redirect, $interim, new WP_Error( 'vw_2fa_invalid', '<strong>Error:</strong> That code is not valid. Try the current code from your app.' ) );
	}

	delete_user_meta( $user_id, VW_2FA_META_LOGIN );
	vw_login_clear( $ip, $user->user_login );
	$GLOBALS['vw_2fa_done'] = true;
	wp_set_auth_cookie( $user_id, ! empty( $pending['remember'] ) );
	do_action( 'wp_login', $user->user_login, $user );
	vw_security_log( "2FA_OK user={$user_id} ip={$ip}" );

	if ( $interim ) {
		global $interim_login;
		$interim_login = 'success';
		login_header( '', '<p class="message">You have logged in successfully.</p>' );
		echo '</div>';
		do_action( 'login_footer' );
		echo '</body></html>';
		exit;
	}

	$requested = $redirect;
	$redirect  = apply_filters( 'login_redirect', $redirect ?: admin_url(), $requested, $user );
	if ( ( empty( $redirect ) || 'wp-admin/' === $redirect || admin_url() === $redirect ) && ! $user->has_cap( 'edit_posts' ) ) {
		$redirect = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
	}
	wp_safe_redirect( $redirect );
	exit;
} );

add_filter( 'wp_login_errors', function ( $errors ) {
	if ( 'expired' === ( $_GET['vw_2fa'] ?? '' ) ) {
		$errors->add( 'vw_2fa_expired', 'Your two-factor step expired or had too many wrong codes. Log in again.' );
	}
	return $errors;
} );

// ── Profile: enrollment, backup codes, reset ────────────────────────────────

add_action( 'show_user_profile', 'vw_2fa_profile_section' );
add_action( 'edit_user_profile', 'vw_2fa_profile_section' );

function vw_2fa_profile_section( WP_User $user ): void {
	$self     = get_current_user_id() === $user->ID;
	$enrolled = vw_2fa_is_enrolled( $user->ID );
	$mode     = vw_2fa_mode();
	$required = '1' === $mode && vw_2fa_is_scoped( $user );
	?>
	<h2 id="vw-2fa">Two-factor authentication</h2>
	<table class="form-table" role="presentation"><tr><th scope="row">Status</th><td>
	<?php
	if ( $enrolled ) {
		$since = (int) get_user_meta( $user->ID, VW_2FA_META_SINCE, true );
		echo '<p><strong style="color:#008a20">On</strong>' . ( $since ? ' since ' . esc_html( wp_date( 'F j, Y', $since ) ) : '' )
			. '. Backup codes left: ' . (int) vw_2fa_backup_remaining( $user->ID ) . ' of ' . VW_2FA_BACKUP_COUNT . '.</p>';
	} else {
		echo '<p><strong>Off</strong>' . ( $required ? ' — <strong style="color:#b32d2e">required for your role; you cannot log in until it is set up.</strong>' : '.' ) . '</p>';
	}
	if ( ! $self ) {
		if ( $enrolled && current_user_can( 'edit_users' ) ) {
			echo '<p><label><input type="checkbox" name="vw_2fa_reset" value="1"> Reset two-factor for this user (they will need to set it up again)</label></p>';
		}
		echo '</td></tr></table>';
		return;
	}

	$fresh = get_transient( 'vw_2fa_fresh_' . $user->ID );
	if ( $enrolled && is_array( $fresh ) ) {
		delete_transient( 'vw_2fa_fresh_' . $user->ID );
		echo '<div style="border:2px solid #b32d2e;padding:12px 16px;max-width:480px;background:#fff">'
			. '<p><strong>Your backup codes — save them in LastPass now.</strong> They are shown only this once. Each works one time, in place of an app code.</p>'
			. '<ol style="font:16px/1.8 monospace">';
		foreach ( $fresh as $c ) {
			echo '<li>' . esc_html( $c ) . '</li>';
		}
		echo '</ol></div>';
	}

	if ( $enrolled ) {
		echo '<p><label><input type="checkbox" name="vw_2fa_regen" value="1"> Replace my backup codes with 8 new ones</label></p>';
		if ( ! $required ) {
			echo '<p><label><input type="checkbox" name="vw_2fa_disable" value="1"> Turn off two-factor</label> — confirm with a current app code: '
				. '<input type="text" name="vw_2fa_disable_code" size="8" inputmode="numeric" autocomplete="one-time-code"></p>';
		}
		echo '</td></tr></table>';
		return;
	}

	$secret = (string) get_user_meta( $user->ID, VW_2FA_META_PENDING, true );
	if ( '' === $secret ) {
		$secret = vw_2fa_base32_encode( random_bytes( 20 ) );
		update_user_meta( $user->ID, VW_2FA_META_PENDING, $secret );
	}
	$uri = vw_2fa_otpauth_uri( $user, $secret );
	?>
	</td></tr>
	<tr><th scope="row">1. Add to your app</th><td>
		<p>In your authenticator app, add an account and scan this code:</p>
		<p><?php echo vw_qr_svg( $uri, 5 ); // phpcs:ignore WordPress.Security.EscapeOutput -- generated SVG, no user input. ?></p>
		<p>Can't scan? Choose “enter a setup key” in the app and type this key (time-based):</p>
		<p><code style="font-size:16px;letter-spacing:1px"><?php echo esc_html( trim( chunk_split( $secret, 4, ' ' ) ) ); ?></code></p>
		<details><summary>Setup link</summary><p><code style="word-break:break-all"><?php echo esc_html( $uri ); ?></code></p></details>
	</td></tr>
	<tr><th scope="row"><label for="vw_2fa_enroll_code">2. Confirm</label></th><td>
		<input type="text" name="vw_2fa_enroll_code" id="vw_2fa_enroll_code" size="8" inputmode="numeric" autocomplete="one-time-code">
		<p class="description">Type the 6-digit code the app now shows, then click <strong>Update Profile</strong> at the bottom of this page. Your backup codes appear here after that.</p>
	</td></tr></table>
	<?php
}

add_action( 'user_profile_update_errors', function ( $errors, $update, $user ) {
	if ( ! $update || empty( $user->ID ) ) {
		return;
	}
	$uid  = (int) $user->ID;
	$self = get_current_user_id() === $uid;

	if ( ! $self ) {
		if ( ! empty( $_POST['vw_2fa_reset'] ) && current_user_can( 'edit_users' ) ) {
			vw_2fa_reset( $uid );
			vw_security_log( "2FA_RESET user={$uid} by=" . get_current_user_id() );
		}
		return;
	}

	$enroll = trim( (string) ( $_POST['vw_2fa_enroll_code'] ?? '' ) );
	if ( '' !== $enroll && ! vw_2fa_is_enrolled( $uid ) ) {
		$pending = (string) get_user_meta( $uid, VW_2FA_META_PENDING, true );
		$step    = $pending ? vw_2fa_match_step( $pending, $enroll ) : null;
		if ( null === $step ) {
			$errors->add( 'vw_2fa_enroll', '<strong>Two-factor:</strong> that code did not match. Check the app shows the Vancouver Weekly entry and try the current code.' );
			return;
		}
		update_user_meta( $uid, VW_2FA_META_SECRET, $pending );
		update_user_meta( $uid, VW_2FA_META_LAST, $step );
		update_user_meta( $uid, VW_2FA_META_SINCE, time() );
		delete_user_meta( $uid, VW_2FA_META_PENDING );
		vw_2fa_new_backup_codes( $uid );
		vw_security_log( "2FA_ENROLLED user={$uid}" );
		return;
	}

	if ( ! vw_2fa_is_enrolled( $uid ) ) {
		return;
	}
	if ( ! empty( $_POST['vw_2fa_disable'] ) ) {
		$wp_user = get_userdata( $uid );
		if ( '1' === vw_2fa_mode() && vw_2fa_is_scoped( $wp_user ) ) {
			$errors->add( 'vw_2fa_disable', '<strong>Two-factor:</strong> it is required for your role and cannot be turned off.' );
		} elseif ( ! vw_2fa_verify_totp( $uid, (string) ( $_POST['vw_2fa_disable_code'] ?? '' ) ) ) {
			$errors->add( 'vw_2fa_disable', '<strong>Two-factor:</strong> enter a current app code to turn it off.' );
		} else {
			vw_2fa_reset( $uid );
			vw_security_log( "2FA_DISABLED user={$uid}" );
		}
		return;
	}
	if ( ! empty( $_POST['vw_2fa_regen'] ) ) {
		vw_2fa_new_backup_codes( $uid );
		vw_security_log( "2FA_BACKUP_REGEN user={$uid}" );
	}
}, 10, 3 );
