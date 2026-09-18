<?php
/**
 * Plugin Name: VW Security
 * Description: Vancouver Weekly hardening — spam blocklist, XML-RPC off, pingbacks off, login throttle, user approval, monthly audit hook.
 * Version: 1.0.0
 * Author: Vancouver Weekly
 */

defined( 'ABSPATH' ) || exit;

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
// Allows 5 attempts per IP per 15 minutes. After that, locked out for 30 minutes.
// Uses WordPress transients (no extra DB tables needed).

define( 'VW_LOGIN_MAX_ATTEMPTS', 5 );
define( 'VW_LOGIN_WINDOW_SECS',  15 * MINUTE_IN_SECONDS );
define( 'VW_LOGIN_LOCKOUT_SECS', 30 * MINUTE_IN_SECONDS );

add_filter( 'authenticate', function ( $user, $username, $password ) {
    if ( empty( $username ) && empty( $password ) ) {
        return $user;
    }

    $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    $key = 'vw_login_' . md5( $ip );

    $record = get_transient( $key );
    if ( ! $record ) {
        $record = [ 'attempts' => 0, 'locked_until' => 0 ];
    }

    // Already locked out?
    if ( $record['locked_until'] > time() ) {
        $wait = ceil( ( $record['locked_until'] - time() ) / 60 );
        return new WP_Error(
            'vw_locked',
            sprintf( 'Too many login attempts. Try again in %d minute(s).', $wait )
        );
    }

    // If this callback is reached after a real auth failure, increment
    // (we hook into wp_login_failed below for the increment; here we just gate)
    return $user;
}, 30, 3 );

add_action( 'wp_login_failed', function ( $username ) {
    $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    $key = 'vw_login_' . md5( $ip );

    $record = get_transient( $key ) ?: [ 'attempts' => 0, 'locked_until' => 0 ];
    $record['attempts']++;

    if ( $record['attempts'] >= VW_LOGIN_MAX_ATTEMPTS ) {
        $record['locked_until'] = time() + VW_LOGIN_LOCKOUT_SECS;
        vw_security_log( "LOGIN_LOCKOUT ip={$ip} username=" . sanitize_user( $username ) );
    }

    set_transient( $key, $record, VW_LOGIN_WINDOW_SECS + VW_LOGIN_LOCKOUT_SECS );
} );

// Clear lockout on successful login
add_action( 'wp_login', function ( $user_login, $user ) {
    $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    delete_transient( 'vw_login_' . md5( $ip ) );
}, 10, 2 );


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

// ── Shared: structured log writer ────────────────────────────────────────────

function vw_security_log( string $message ): void {
    $log_dir  = WP_CONTENT_DIR . '/vw-security-logs';
    $log_file = $log_dir . '/' . date( 'Y-m' ) . '.log';
    if ( ! is_dir( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
        // Prevent direct web access to logs
        file_put_contents( $log_dir . '/.htaccess', "Deny from all\n" );
    }
    $line = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
    file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
}
