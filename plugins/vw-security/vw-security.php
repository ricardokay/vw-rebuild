<?php
/**
 * Plugin Name: VW Security
 * Description: Vancouver Weekly hardening — spam blocklist, XML-RPC off, pingbacks off, login throttle, TOTP two-factor, user approval, monthly audit hook.
 * Version: 1.1.0
 * Author: Vancouver Weekly
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/qr.php';
require_once __DIR__ . '/inc/two-factor.php';
require_once __DIR__ . '/inc/redirects.php';

// ── 1. PHARMACEUTICAL / SEO SPAM BLOCKLIST ────────────────────────────────────
//
// Blocks any post whose slug OR content contains a blocked keyword.
// Fires on wp_insert_post_data (before save) so nothing hits the DB.

define( 'VW_SPAM_KEYWORDS', [
    // Ivermectin family (all variants caught by prefix)
    'ivermect', 'stromectol', 'mectizan', 'soolantra', 'sklice',
    // ED / lifestyle drugs
    'viagra', 'cialis', 'levitra', 'sildenafil', 'tadalafil', 'vardenafil',
    // Pain / sedatives
    'tramadol', 'oxycontin', 'oxycodone', 'hydrocodone', 'fentanyl',
    'adderall', 'xanax', 'valium', 'ambien', 'ritalin', 'modafinil', 'ketamine',
    // Generic spam terms
    'cheap-meds', 'buy-pills', 'online-pharmacy', 'canadian-pharmacy',
    'rx-online', 'genericpills', 'discount-drugs',
    // Gambling / financial spam
    'casino-slot', 'freespins', 'payday-loan', 'paydayloan',
    // Crypto spam
    'buy-bitcoin', 'buy-crypto', 'nft-mint',
] );

add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
    // Only check actual posts being published or scheduled
    if ( ! in_array( $data['post_status'], [ 'publish', 'future', 'pending' ], true ) ) {
        return $data;
    }
    if ( ! in_array( $data['post_type'], [ 'post', 'page' ], true ) ) {
        return $data;
    }

    $slug    = strtolower( $data['post_name'] ?? '' );
    $content = strtolower( wp_strip_all_tags( $data['post_content'] ?? '' ) );
    $title   = strtolower( $data['post_title'] ?? '' );
    $haystack = $slug . ' ' . $title . ' ' . $content;

    foreach ( VW_SPAM_KEYWORDS as $kw ) {
        if ( str_contains( $haystack, $kw ) ) {
            // Force to draft and add a note — never silently delete
            $data['post_status'] = 'draft';
            $data['post_content'] = $data['post_content']
                . "\n\n<!-- VW_SECURITY: blocked keyword '{$kw}' — held as draft for review -->";
            // Log it
            vw_security_log( "SPAM_BLOCK slug={$slug} keyword={$kw}" );
            break;
        }
    }
    return $data;
}, 10, 2 );


// ── 2. DISABLE XML-RPC COMPLETELY ────────────────────────────────────────────
//
// XML-RPC is a legacy remote-publishing endpoint that is almost never needed
// and is a common vector for brute-force and spam injection attacks.

add_filter( 'xmlrpc_enabled', '__return_false' );

// Also remove the X-Pingback header so attackers can't discover the endpoint
add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Pingback'] );
    return $headers;
} );

// Block direct HTTP requests to xmlrpc.php at the WordPress level
add_action( 'init', function () {
    if ( isset( $_SERVER['REQUEST_URI'] ) &&
         str_contains( $_SERVER['REQUEST_URI'], 'xmlrpc.php' ) ) {
        http_response_code( 403 );
        exit( 'XML-RPC is disabled on this site.' );
    }
} );


// ── 3. DISABLE TRACKBACKS AND PINGBACKS SITE-WIDE ────────────────────────────

// On new posts, close comments and pings by default — comments are closed sitewide by policy
add_filter( 'pre_option_default_ping_status', function() { return 'closed'; } );
add_filter( 'pre_option_default_comment_status', function() { return 'closed'; } );

// Close pings on all existing published posts (runs once, then stops)
add_action( 'init', function () {
    if ( get_option( 'vw_pingbacks_closed' ) ) {
        return;
    }
    global $wpdb;
    $wpdb->query( "UPDATE {$wpdb->posts} SET ping_status = 'closed' WHERE post_status = 'publish'" );
    update_option( 'vw_pingbacks_closed', true );
} );

// Drop incoming pingback XML-RPC calls even if XML-RPC were somehow re-enabled
add_filter( 'xmlrpc_methods', function ( $methods ) {
    unset( $methods['pingback.ping'] );
    unset( $methods['pingback.extensions.getPingbacks'] );
    return $methods;
} );


// ── 4. LOGIN ATTEMPT THROTTLE ────────────────────────────────────────────────
//
// Two independent counters, both transients (Redis on the server):
//   per IP:       5 failures in 15 min → that IP locked 30 min
//   per username: 10 failures in 15 min (from any IPs) → that account locked 15 min
// The username limit is higher so one attacker IP cannot lock a real editor out
// before its own IP lock trips. Wrong 2FA codes count as failures too.
// Lockouts live in the object cache, so `wp cache flush` clears every lock.

const VW_LOGIN_LIMITS = [
    'ip'   => [ 'max' => 5,  'window' => 15 * MINUTE_IN_SECONDS, 'lock' => 30 * MINUTE_IN_SECONDS ],
    'user' => [ 'max' => 10, 'window' => 15 * MINUTE_IN_SECONDS, 'lock' => 15 * MINUTE_IN_SECONDS ],
];

function vw_login_client_ip(): string {
    $ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

// Login name and email of the same account share one counter.
function vw_login_user_key( string $username ): string {
    $username = trim( $username );
    $user     = get_user_by( 'login', $username ) ?: ( is_email( $username ) ? get_user_by( 'email', $username ) : false );
    return $user ? 'id' . $user->ID : strtolower( $username );
}

function vw_login_transient_keys( string $ip, string $username ): array {
    return [
        'ip'   => 'vw_login_ip_' . md5( $ip ),
        'user' => 'vw_login_user_' . md5( vw_login_user_key( $username ) ),
    ];
}

function vw_login_locked_until( string $ip, string $username ): int {
    $until = 0;
    foreach ( vw_login_transient_keys( $ip, $username ) as $key ) {
        $rec   = get_transient( $key );
        $until = max( $until, (int) ( $rec['locked_until'] ?? 0 ) );
    }
    return $until;
}

function vw_login_locked_message( string $ip, string $username ): string {
    $wait = max( 1, (int) ceil( ( vw_login_locked_until( $ip, $username ) - time() ) / 60 ) );
    return sprintf( '<strong>Error:</strong> Too many failed login attempts. Try again in %d minute(s).', $wait );
}

function vw_login_record_failure( string $ip, string $username ): void {
    $now = time();
    foreach ( vw_login_transient_keys( $ip, $username ) as $type => $key ) {
        $lim = VW_LOGIN_LIMITS[ $type ];
        $rec = get_transient( $key ) ?: [ 'count' => 0, 'first' => $now, 'locked_until' => 0 ];
        if ( $rec['locked_until'] > $now ) {
            continue;
        }
        if ( $now - $rec['first'] > $lim['window'] ) {
            $rec = [ 'count' => 0, 'first' => $now, 'locked_until' => 0 ];
        }
        $rec['count']++;
        if ( $rec['count'] >= $lim['max'] ) {
            $rec = [ 'count' => 0, 'first' => $now, 'locked_until' => $now + $lim['lock'] ];
            vw_security_log( 'LOGIN_LOCKOUT type=' . $type . ' ip=' . $ip . ' username=' . sanitize_user( $username ) );
        }
        set_transient( $key, $rec, max( $lim['window'], $lim['lock'] ) );
    }
}

function vw_login_clear( string $ip, string $username ): void {
    foreach ( vw_login_transient_keys( $ip, $username ) as $key ) {
        delete_transient( $key );
    }
}

// Runs after the password check (priority 20) so its error is the final result.
add_filter( 'authenticate', function ( $user, $username, $password ) {
    if ( empty( $username ) && empty( $password ) ) {
        return $user;
    }
    $ip = vw_login_client_ip();
    if ( vw_login_locked_until( $ip, (string) $username ) > time() ) {
        return new WP_Error( 'vw_locked', vw_login_locked_message( $ip, (string) $username ) );
    }
    return $user;
}, 30, 3 );

add_action( 'wp_login_failed', function ( $username, $error = null ) {
    if ( $error instanceof WP_Error && 'vw_locked' === $error->get_error_code() ) {
        return;
    }
    $ip = vw_login_client_ip();
    vw_login_record_failure( $ip, (string) $username );
    vw_security_log( 'LOGIN_FAIL ip=' . $ip . ' username=' . sanitize_user( (string) $username ) );
}, 10, 2 );

add_action( 'wp_login', function ( $user_login ) {
    vw_login_clear( vw_login_client_ip(), (string) $user_login );
}, 10, 1 );


// ── 5. NEW USER REGISTRATIONS REQUIRE ADMIN APPROVAL ─────────────────────────
//
// All new registrations land as 'subscriber' with a custom meta flag
// `vw_pending_approval = 1`. An admin must explicitly approve them.
// Until approved, login is blocked with a clear message.

add_action( 'user_register', function ( $user_id ) {
    update_user_meta( $user_id, 'vw_pending_approval', 1 );
    // Notify admin
    $user  = get_userdata( $user_id );
    $admin = get_option( 'admin_email' );
    $approve_url = admin_url( "user-edit.php?user_id={$user_id}" );
    wp_mail(
        $admin,
        '[Vancouver Weekly] New user registration pending approval',
        "A new user registered and is awaiting your approval.\n\n"
        . "Username: {$user->user_login}\n"
        . "Email: {$user->user_email}\n\n"
        . "Review and approve: {$approve_url}"
    );
    vw_security_log( "USER_PENDING id={$user_id} login={$user->user_login}" );
} );

// Block login for unapproved accounts
add_filter( 'authenticate', function ( $user, $username, $password ) {
    if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
        return $user;
    }
    if ( get_user_meta( $user->ID, 'vw_pending_approval', true ) ) {
        return new WP_Error(
            'vw_pending',
            'Your account is pending admin approval. You will receive an email when approved.'
        );
    }
    return $user;
}, 40, 3 );

// Admin UI: show pending-approval column on Users list
add_filter( 'manage_users_columns', function ( $cols ) {
    $cols['vw_approval'] = 'Approval';
    return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $val, $col, $user_id ) {
    if ( $col !== 'vw_approval' ) return $val;
    $pending = get_user_meta( $user_id, 'vw_pending_approval', true );
    if ( $pending ) {
        $url = wp_nonce_url(
            admin_url( "users.php?action=vw_approve&user={$user_id}" ),
            'vw_approve_' . $user_id
        );
        return '<a href="' . esc_url( $url ) . '" style="color:green">Approve</a>'
             . ' &nbsp; <span style="color:orange">⚠ Pending</span>';
    }
    return '<span style="color:green">✓ Approved</span>';
}, 10, 3 );

// Handle the approve action
add_action( 'admin_action_vw_approve', function () {
    $user_id = absint( $_GET['user'] ?? 0 );
    check_admin_referer( 'vw_approve_' . $user_id );
    if ( ! current_user_can( 'edit_users' ) || ! $user_id ) wp_die( 'Unauthorized' );
    delete_user_meta( $user_id, 'vw_pending_approval' );
    $user = get_userdata( $user_id );
    wp_mail(
        $user->user_email,
        '[Vancouver Weekly] Your account has been approved',
        "Hi {$user->display_name},\n\nYour Vancouver Weekly account has been approved. You can now log in at "
        . wp_login_url() . "\n\nThanks!"
    );
    vw_security_log( "USER_APPROVED id={$user_id} login={$user->user_login}" );
    wp_redirect( admin_url( 'users.php?vw_approved=1' ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( isset( $_GET['vw_approved'] ) ) {
        echo '<div class="notice notice-success"><p>User approved and notified.</p></div>';
    }
} );


// ── 6. MONTHLY SECURITY AUDIT — WP-CRON HOOK ─────────────────────────────────
//
// Schedules a monthly cron event that scans all published posts and emails
// a report to the admin. Checks for hidden links, base64 payloads, and
// unusual publish bursts. Mirrors the pre-import audit logic.

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'vw_monthly_security_audit' ) ) {
        wp_schedule_event( strtotime( 'first day of next month midnight' ), 'monthly', 'vw_monthly_security_audit' );
    }
} );

// Register the 'monthly' schedule interval
add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => 'Once a month',
    ];
    return $schedules;
} );

add_action( 'vw_monthly_security_audit', 'vw_run_security_audit' );

function vw_run_security_audit() {
    global $wpdb;

    $flagged = [];
    $now     = current_time( 'mysql' );

    // ── a. Hidden links ───────────────────────────────────────────────────
    $hiding_css = "display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0|font-size\s*:\s*0";
    $posts_with_hiding = $wpdb->get_results( $wpdb->prepare(
        "SELECT ID, post_title, post_date FROM {$wpdb->posts}
         WHERE post_status = 'publish'
         AND post_type IN ('post','page')
         AND post_content REGEXP %s",
        $hiding_css
    ) );
    foreach ( $posts_with_hiding as $p ) {
        // Confirm it's on an <a> tag (not just a widget background)
        $content = get_post_field( 'post_content', $p->ID );
        if ( preg_match( '/<a\b[^>]*style=["\'][^"\']*(?:display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0|font-size\s*:\s*0)[^"\']*["\'][^>]*>/i', $content ) ) {
            $flagged[] = "[HIDDEN_LINK] ID={$p->ID} \"{$p->post_title}\" ({$p->post_date})";
        }
    }

    // ── b. Base64 payloads ────────────────────────────────────────────────
    // Look for long base64 strings NOT preceded by eyJ (JWT), H4sI (gzip), or MV5B (IMDB)
    $all_posts = $wpdb->get_results(
        "SELECT ID, post_title, post_date, post_content FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type IN ('post','page')"
    );
    foreach ( $all_posts as $p ) {
        if ( preg_match( '/(?<![eyJH4sMV5B"\'\\/=A-Za-z0-9])([A-Za-z0-9+\\/]{100,}={0,2})(?!["\':A-Za-z0-9\\/])/i', $p->post_content ) ) {
            $flagged[] = "[BASE64] ID={$p->ID} \"{$p->post_title}\" ({$p->post_date})";
        }
    }

    // ── c. Spam keyword in published post ─────────────────────────────────
    foreach ( VW_SPAM_KEYWORDS as $kw ) {
        $hits = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_date FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post','page')
             AND (post_name LIKE %s OR post_content LIKE %s OR post_title LIKE %s)",
            "%{$kw}%", "%{$kw}%", "%{$kw}%"
        ) );
        foreach ( $hits as $p ) {
            $flagged[] = "[SPAM_KW:{$kw}] ID={$p->ID} \"{$p->post_title}\" ({$p->post_date})";
        }
    }

    // ── d. Publish burst — >20 posts in any 60-minute window ─────────────
    $dates = $wpdb->get_col(
        "SELECT post_date FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type = 'post'
         ORDER BY post_date ASC"
    );
    $ts = array_map( 'strtotime', $dates );
    for ( $i = 0; $i < count( $ts ); $i++ ) {
        $window = array_filter( $ts, fn($t) => $t >= $ts[$i] && $t <= $ts[$i] + 3600 );
        if ( count( $window ) >= 20 ) {
            $dt = date( 'Y-m-d H:i', $ts[$i] );
            $flagged[] = "[BURST] " . count( $window ) . " posts published in 60 min starting {$dt}";
            // Skip past this window
            $i += count( $window ) - 1;
        }
    }

    // ── Send report ───────────────────────────────────────────────────────
    $admin = get_option( 'admin_email' );
    $count = count( $flagged );

    if ( $count === 0 ) {
        $body = "Monthly security audit ran on {$now}.\n\nAll clear — no issues found across all published posts.";
    } else {
        $body = "Monthly security audit ran on {$now}.\n\n"
              . "{$count} issue(s) found:\n\n"
              . implode( "\n", $flagged )
              . "\n\nPlease review these posts in wp-admin.";
    }

    wp_mail( $admin, "[Vancouver Weekly] Monthly Security Audit — {$count} issue(s)", $body );
    vw_security_log( "MONTHLY_AUDIT issues={$count}" );
}

// ── 7. USER ENUMERATION ──────────────────────────────────────────────────────
//
// /?author=N would redirect to /author/{login-derived slug}/; answer 404 instead.
// The REST users endpoints need a logged-in user (the block editor still works).

add_filter( 'request', function ( $query_vars ) {
    if ( ! is_admin() && isset( $_GET['author'] ) ) {
        return [ 'error' => '404' ];
    }
    return $query_vars;
} );

add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
    if ( ! is_user_logged_in() && preg_match( '#^/wp/v2/users(/|$)#', $request->get_route() ) ) {
        return new WP_Error( 'rest_forbidden', 'Authentication required.', [ 'status' => 401 ] );
    }
    return $result;
}, 10, 3 );

add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
    return 'users' === $name ? false : $provider;
}, 10, 2 );


// ── 8. WORDPRESS VERSION EXPOSURE ────────────────────────────────────────────
//
// No generator tag in pages or feeds. Asset ?ver= equal to the core version is
// swapped for a site-specific hash, which still changes on every core update.
// readme.html and license.txt are deleted from the server; core updates put
// them back, so they are deleted again after every successful update.

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

function vw_mask_core_version( $src ) {
    if ( ! is_string( $src ) || ! str_contains( $src, 'ver=' ) ) {
        return $src;
    }
    $core = get_bloginfo( 'version' );
    parse_str( (string) wp_parse_url( $src, PHP_URL_QUERY ), $args );
    if ( ( $args['ver'] ?? null ) !== $core ) {
        return $src;
    }
    return add_query_arg( 'ver', substr( wp_hash( 'vw-core-' . $core ), 0, 10 ), $src );
}
add_filter( 'style_loader_src', 'vw_mask_core_version', 9999 );
add_filter( 'script_loader_src', 'vw_mask_core_version', 9999 );
add_filter( 'script_module_loader_src', 'vw_mask_core_version', 9999 );

// The login page's combined load-styles/load-scripts URLs print the core version
// without any filter; loading the files individually routes them through the above.
add_action( 'login_init', function () {
    $GLOBALS['concatenate_scripts'] = false;
} );

add_action( '_core_updated_successfully', function () {
    foreach ( [ 'readme.html', 'license.txt' ] as $file ) {
        if ( file_exists( ABSPATH . $file ) ) {
            unlink( ABSPATH . $file );
        }
    }
} );


// ── 9. VARNISH PURGE ON PUBLISH ──────────────────────────────────────────────
//
// Replaces Breeze's purging. Cloudways Varnish takes `URLPURGE <path>` on
// 127.0.0.1 with the site's Host header and drops that one object (a bare PURGE
// empties the whole domain). Fires when a post or page is published, updated
// while published, or taken out of publish. Purges the post URL (and its old URL
// if the slug changed), the homepage, /feed/, /archive/, every nav section front
// (fronts pull from categories that are not their children, e.g. hungry-social
// on food-drink) and the post's own category archives with their ancestors.
// URLs are collected during the request and sent once, at shutdown, after the
// block editor has saved terms.

function vw_varnish_enabled(): bool {
    $root = realpath( ABSPATH ) ?: ABSPATH;
    return (bool) apply_filters( 'vw_varnish_enabled', str_contains( $root, '.cloudwaysapps.com/' ) );
}

function vw_varnish_purge( array $urls ): array {
    $status = [];
    foreach ( array_unique( array_filter( $urls ) ) as $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            continue;
        }
        $target = 'http://127.0.0.1' . ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
        $res    = wp_remote_request( $target, [
            'method'      => 'URLPURGE',
            'headers'     => [ 'Host' => $parts['host'] ],
            'timeout'     => 2,
            'redirection' => 0,
        ] );
        $status[ $url ] = is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res );
    }
    return $status;
}

function vw_purge_urls_for_post( WP_Post $post, array $term_ids = [] ): array {
    $as_published              = clone $post;
    $as_published->post_status = 'publish';
    $urls = [ get_permalink( $as_published ), home_url( '/' ), home_url( '/feed/' ), home_url( '/archive/' ) ];
    if ( defined( 'VW_MASTHEAD_SECTIONS' ) ) {
        foreach ( array_keys( VW_MASTHEAD_SECTIONS ) as $slug ) {
            $term = get_category_by_slug( $slug );
            if ( $term ) {
                $urls[] = get_category_link( $term->term_id );
            }
        }
    }
    $term_ids = array_merge( $term_ids, wp_get_post_categories( $post->ID ) );
    foreach ( array_unique( $term_ids ) as $tid ) {
        foreach ( array_merge( [ $tid ], get_ancestors( $tid, 'category', 'taxonomy' ) ) as $id ) {
            $link = get_category_link( $id );
            if ( $link ) {
                $urls[] = $link;
            }
        }
    }
    return array_values( array_unique( array_filter( $urls ) ) );
}

$GLOBALS['vw_purge_queue'] = [];

add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( ( 'publish' !== $new && 'publish' !== $old ) || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
        return;
    }
    if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
        return;
    }
    // Categories as they stand before the block editor's follow-up term save.
    $GLOBALS['vw_purge_queue'][ $post->ID ] = array_merge( $GLOBALS['vw_purge_queue'][ $post->ID ] ?? [], wp_get_post_categories( $post->ID ) );
}, 10, 3 );

add_action( 'post_updated', function ( $post_id, $after, $before ) {
    if ( 'publish' === $before->post_status && $before->post_name !== $after->post_name ) {
        $GLOBALS['vw_purge_extra'][] = get_permalink( $before );
    }
}, 10, 3 );

add_action( 'shutdown', function () {
    if ( empty( $GLOBALS['vw_purge_queue'] ) || ! vw_varnish_enabled() ) {
        return;
    }
    $urls = $GLOBALS['vw_purge_extra'] ?? [];
    foreach ( $GLOBALS['vw_purge_queue'] as $post_id => $term_ids ) {
        $post = get_post( $post_id );
        if ( $post ) {
            $urls = array_merge( $urls, vw_purge_urls_for_post( $post, $term_ids ) );
        }
    }
    $GLOBALS['vw_purge_queue'] = [];
    $status = vw_varnish_purge( $urls );
    vw_security_log( 'VARNISH_PURGE urls=' . count( $status ) . ' ' . implode( ' ', array_map(
        fn( $u, $s ) => wp_parse_url( $u, PHP_URL_PATH ) . '=' . $s, array_keys( $status ), $status
    ) ) );
} );


// ── Shared: structured log writer ────────────────────────────────────────────

// Cloudways apps have private_html beside public_html, outside the web root.
function vw_security_log( string $message ): void {
    $private  = dirname( ABSPATH ) . '/private_html';
    $log_dir  = ( is_dir( $private ) && is_writable( $private ) ? $private : WP_CONTENT_DIR ) . '/vw-security-logs';
    $log_file = $log_dir . '/' . date( 'Y-m' ) . '.log';
    if ( ! is_dir( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
        // Prevent direct web access to logs
        file_put_contents( $log_dir . '/.htaccess', "Deny from all\n" );
    }
    $line = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
    file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
}
