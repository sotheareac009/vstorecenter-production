<?php
/**
 * Site View Counter — bulletproof pageview tracking + WP Admin dashboard.
 *
 * Production safety:
 *   - Self-creates its DB table on the very first frontend hit.
 *   - Every DB call is wrapped in try/catch — a failure NEVER reaches the user.
 *   - Static-cached "is table ready" check, so we run the version option lookup
 *     once per request, not per query.
 *   - A global kill-switch option (`shopys_vc_disabled` = '1') instantly
 *     disables tracking without touching code.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/* ═══════════════════════════════════════════════════════════════════
   1. CORE: TABLE NAME, IP HASHING, KILL-SWITCH
   ═══════════════════════════════════════════════════════════════════ */

function shopys_vc_table() {
    global $wpdb;
    return $wpdb->prefix . 'shopys_views';
}

function shopys_vc_is_disabled() {
    return get_option( 'shopys_vc_disabled', '0' ) === '1';
}

function shopys_vc_visitor_salt() {
    $salt = get_option( 'shopys_vc_salt' );
    if ( ! $salt ) {
        $salt = wp_generate_password( 32, true, true );
        add_option( 'shopys_vc_salt', $salt, '', false );
    }
    return $salt;
}

function shopys_vc_hash_ip( $ip ) {
    return hash( 'sha256', $ip . '|' . shopys_vc_visitor_salt() );
}

function shopys_vc_client_ip() {
    foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $h ) {
        if ( ! empty( $_SERVER[ $h ] ) ) {
            $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
        }
    }
    return '';
}


/* ═══════════════════════════════════════════════════════════════════
   2. TABLE: SELF-HEALING CREATION (never throws to caller)
   ═══════════════════════════════════════════════════════════════════ */

/**
 * Returns true once the table is known to exist for THIS request.
 * Result is cached statically so we don't keep checking options/DB.
 */
function shopys_vc_ensure_table() {
    static $ready = null;
    if ( $ready !== null ) return $ready;

    try {
        if ( get_option( 'shopys_vc_table_version' ) === '1.1' ) {
            $ready = true;
            return true;
        }
        shopys_vc_create_table();
        $ready = ( get_option( 'shopys_vc_table_version' ) === '1.0' );
    } catch ( \Throwable $e ) {
        $ready = false;
    }
    return $ready;
}

function shopys_vc_create_table() {
    try {
        global $wpdb;
        $table   = shopys_vc_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED DEFAULT 0,
            post_type VARCHAR(20) DEFAULT '',
            title VARCHAR(255) DEFAULT '',
            url VARCHAR(500) DEFAULT '',
            ip_hash CHAR(64) DEFAULT '',
            user_id BIGINT UNSIGNED DEFAULT 0,
            country_code CHAR(2) DEFAULT '',
            country VARCHAR(100) DEFAULT '',
            region VARCHAR(100) DEFAULT '',
            city VARCHAR(100) DEFAULT '',
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY date_idx (viewed_at),
            KEY post_idx (post_id, viewed_at),
            KEY visitor_idx (ip_hash, viewed_at),
            KEY country_idx (country_code)
        ) {$charset};";

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta( $sql );
        update_option( 'shopys_vc_table_version', '1.1', false );
    } catch ( \Throwable $e ) {
        // Silently swallow — site keeps working, view just isn't recorded yet.
    }
}

/**
 * Migrate existing v1.0 table to v1.1 (add geo columns if missing).
 */
function shopys_vc_maybe_migrate() {
    if ( get_option( 'shopys_vc_table_version' ) === '1.1' ) return;
    try {
        global $wpdb;
        $table = shopys_vc_table();
        $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        if ( ! in_array( 'country_code', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$table}
                ADD COLUMN country_code CHAR(2)   NOT NULL DEFAULT '',
                ADD COLUMN country      VARCHAR(100) NOT NULL DEFAULT '',
                ADD COLUMN region       VARCHAR(100) NOT NULL DEFAULT '',
                ADD COLUMN city         VARCHAR(100) NOT NULL DEFAULT '',
                ADD INDEX  country_idx  (country_code)" );
        }
        update_option( 'shopys_vc_table_version', '1.1', false );
    } catch ( \Throwable $e ) {}
}

// Try to create/migrate the table eagerly on every request.
add_action( 'init',               'shopys_vc_ensure_table' );
add_action( 'admin_init',         'shopys_vc_ensure_table' );
add_action( 'after_switch_theme', 'shopys_vc_create_table' );
add_action( 'init',               'shopys_vc_maybe_migrate', 5 );


/* ═══════════════════════════════════════════════════════════════════
   3. TRACKING: RECORD A VIEW (never throws to caller)
   ═══════════════════════════════════════════════════════════════════ */

function shopys_vc_should_skip() {
    if ( shopys_vc_is_disabled() ) return true;
    if ( is_admin() )                return true;
    if ( wp_doing_ajax() )           return true;
    if ( wp_doing_cron() )           return true;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return true;
    if ( is_404() || is_feed() || is_robots() || is_search() ) return true;

    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if ( $ua === '' ) return true;
    if ( preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|pingdom|uptimerobot|wget|curl|headless/i', $ua ) ) return true;

    if ( is_user_logged_in()
         && current_user_can( 'manage_options' )
         && get_option( 'shopys_vc_count_admins', '0' ) !== '1' ) {
        return true;
    }
    return false;
}

add_action( 'template_redirect', 'shopys_vc_record_view', 100 );
function shopys_vc_record_view() {
    try {
        if ( shopys_vc_should_skip() )       return;
        if ( ! shopys_vc_ensure_table() )    return;

        $ip = shopys_vc_client_ip();
        if ( $ip === '' ) return;

        global $wpdb;
        $table = shopys_vc_table();

        $post_id   = 0;
        $post_type = '';
        $title     = '';

        if ( is_singular() ) {
            $post_id   = (int)    get_queried_object_id();
            $post_type = (string) get_post_type();
            $title     = (string) get_the_title( $post_id );
        } elseif ( is_front_page() || is_home() ) {
            $title = 'Home';
        } elseif ( is_archive() ) {
            $title = 'Archive: ' . wp_strip_all_tags( get_the_archive_title() );
        }

        $host = isset( $_SERVER['HTTP_HOST'] )    ? $_SERVER['HTTP_HOST']    : parse_url( home_url(), PHP_URL_HOST );
        $uri  = isset( $_SERVER['REQUEST_URI'] )  ? $_SERVER['REQUEST_URI']  : '/';
        $url  = ( is_ssl() ? 'https://' : 'http://' ) . $host . strtok( $uri, '?' );

        $ip_hash = shopys_vc_hash_ip( $ip );

        // Resolve geo location (cached per IP for 7 days).
        $geo = shopys_vc_get_geo( $ip );

        // Throttle dupes from same visitor within 60s.
        $wpdb->suppress_errors( true );
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table}
              WHERE ip_hash = %s
                AND url     = %s
                AND viewed_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
              LIMIT 1",
            $ip_hash, $url
        ) );
        if ( $recent ) { $wpdb->suppress_errors( false ); return; }

        $wpdb->insert( $table, array(
            'post_id'      => $post_id,
            'post_type'    => $post_type,
            'title'        => mb_substr( $title, 0, 255 ),
            'url'          => mb_substr( $url, 0, 500 ),
            'ip_hash'      => $ip_hash,
            'user_id'      => get_current_user_id(),
            'country_code' => $geo['country_code'],
            'country'      => $geo['country'],
            'region'       => $geo['region'],
            'city'         => $geo['city'],
            'viewed_at'    => current_time( 'mysql' ),
        ) );
        $wpdb->suppress_errors( false );

        // Auto-prune ~1% of writes — keep last 180 days only.
        if ( wp_rand( 1, 100 ) === 1 ) {
            $wpdb->query( "DELETE FROM {$table} WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 180 DAY)" );
        }
    } catch ( \Throwable $e ) {
        // Never let tracking break the page.
    }
}


/* ═══════════════════════════════════════════════════════════════════
   4. GEO IP LOOKUP (ip-api.com, cached 7 days per unique IP)
   ═══════════════════════════════════════════════════════════════════ */

/**
 * Returns geo data array for a given IP address.
 * Caches the result for 7 days so the API is only ever hit once per unique IP.
 *
 * @param  string $ip  Raw IP address.
 * @return array { country_code, country, region, city }
 */
function shopys_vc_get_geo( $ip ) {
    $empty = [ 'country_code' => '', 'country' => '', 'region' => '', 'city' => '' ];
    if ( ! $ip || $ip === '127.0.0.1' || $ip === '::1' ) return $empty;

    // Cache key: short hash of the IP (not the salted hash — we need consistency).
    $cache_key = 'shopys_geo_' . substr( md5( $ip ), 0, 16 );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    try {
        $response = wp_remote_get(
            'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,country,countryCode,regionName,city',
            [ 'timeout' => 3, 'sslverify' => false ]
        );

        if ( is_wp_error( $response ) ) {
            set_transient( $cache_key, $empty, HOUR_IN_SECONDS ); // short cache on failure
            return $empty;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ( $body['status'] ?? '' ) !== 'success' ) {
            set_transient( $cache_key, $empty, HOUR_IN_SECONDS );
            return $empty;
        }

        $geo = [
            'country_code' => substr( $body['countryCode'] ?? '', 0, 2 ),
            'country'      => substr( $body['country']     ?? '', 0, 100 ),
            'region'       => substr( $body['regionName']  ?? '', 0, 100 ),
            'city'         => substr( $body['city']        ?? '', 0, 100 ),
        ];

        set_transient( $cache_key, $geo, 7 * DAY_IN_SECONDS );
        return $geo;

    } catch ( \Throwable $e ) {
        return $empty;
    }
}

/**
 * Top visitor locations grouped by country + city, for the dashboard.
 *
 * @param  string $since  MySQL datetime string.
 * @param  int    $limit  Max rows.
 * @return array
 */
function shopys_vc_top_locations( $since, $limit = 20 ) {
    if ( ! shopys_vc_ensure_table() ) return [];
    try {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT country_code, country, region, city,
                    COUNT(*) AS views,
                    COUNT(DISTINCT ip_hash) AS unique_visitors
               FROM " . shopys_vc_table() . "
              WHERE viewed_at >= %s
                AND country_code != ''
              GROUP BY country_code, country, region, city
              ORDER BY views DESC
              LIMIT %d",
            $since, $limit
        ) ) ?: [];
    } catch ( \Throwable $e ) { return []; }
}


/* ═══════════════════════════════════════════════════════════════════
   5. STATS HELPERS (every helper safe to call before table exists)
   ═══════════════════════════════════════════════════════════════════ */

function shopys_vc_count_views( $since ) {
    if ( ! shopys_vc_ensure_table() ) return 0;
    try {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . shopys_vc_table() . " WHERE viewed_at >= %s", $since
        ) );
    } catch ( \Throwable $e ) { return 0; }
}

function shopys_vc_count_unique( $since ) {
    if ( ! shopys_vc_ensure_table() ) return 0;
    try {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_hash) FROM " . shopys_vc_table() . " WHERE viewed_at >= %s", $since
        ) );
    } catch ( \Throwable $e ) { return 0; }
}

function shopys_vc_top_pages( $since, $limit = 15 ) {
    if ( ! shopys_vc_ensure_table() ) return array();
    try {
        global $wpdb;
        $limit = max( 1, min( 50, (int) $limit ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT url, MAX(title) AS title, MAX(post_id) AS post_id, COUNT(*) AS views,
                    MAX(viewed_at) AS last_viewed,
                    COUNT(DISTINCT IF(country_code != '', CONCAT(country_code, city), NULL)) AS location_count,
                        GROUP_CONCAT(DISTINCT IF(country_code != '', CONCAT(country_code, ':', country, ':', city), NULL) ORDER BY viewed_at DESC SEPARATOR '|') AS location_list,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country_code, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country_code,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country,
                    SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', city, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS city
               FROM " . shopys_vc_table() . "
              WHERE viewed_at >= %s
              GROUP BY url
              ORDER BY views DESC
              LIMIT %d",
            $since, $limit
        ) );
        return $rows ?: array();
    } catch ( \Throwable $e ) { return array(); }
}

/**
 * All pages grouped and filtered by an optional year+month (0 = all time).
 */
function shopys_vc_pages_by_period( $year = 0, $month = 0, $limit = 25, $offset = 0 ) {
    if ( ! shopys_vc_ensure_table() ) return array();
    try {
        global $wpdb;
        $limit  = max( 1, min( 1000, (int) $limit ) );
        $offset = max( 0, (int) $offset );
        if ( $year && $month ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT url, MAX(title) AS title, MAX(post_id) AS post_id,
                        COUNT(*) AS views, MAX(viewed_at) AS last_viewed,
                        COUNT(DISTINCT IF(country_code != '', CONCAT(country_code, city), NULL)) AS location_count,
                            GROUP_CONCAT(DISTINCT IF(country_code != '', CONCAT(country_code, ':', country, ':', city), NULL) ORDER BY viewed_at DESC SEPARATOR '|') AS location_list,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country_code, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country_code,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', city, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS city
                   FROM " . shopys_vc_table() . "
                  WHERE YEAR(viewed_at) = %d AND MONTH(viewed_at) = %d
                  GROUP BY url
                  ORDER BY last_viewed DESC
                  LIMIT %d OFFSET %d",
                $year, $month, $limit, $offset
            ) );
        } elseif ( $year ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT url, MAX(title) AS title, MAX(post_id) AS post_id,
                        COUNT(*) AS views, MAX(viewed_at) AS last_viewed,
                        COUNT(DISTINCT IF(country_code != '', CONCAT(country_code, city), NULL)) AS location_count,
                            GROUP_CONCAT(DISTINCT IF(country_code != '', CONCAT(country_code, ':', country, ':', city), NULL) ORDER BY viewed_at DESC SEPARATOR '|') AS location_list,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country_code, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country_code,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', city, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS city
                   FROM " . shopys_vc_table() . "
                  WHERE YEAR(viewed_at) = %d
                  GROUP BY url
                  ORDER BY last_viewed DESC
                  LIMIT %d OFFSET %d",
                $year, $limit, $offset
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT url, MAX(title) AS title, MAX(post_id) AS post_id,
                        COUNT(*) AS views, MAX(viewed_at) AS last_viewed,
                        COUNT(DISTINCT IF(country_code != '', CONCAT(country_code, city), NULL)) AS location_count,
                            GROUP_CONCAT(DISTINCT IF(country_code != '', CONCAT(country_code, ':', country, ':', city), NULL) ORDER BY viewed_at DESC SEPARATOR '|') AS location_list,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country_code, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country_code,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', country, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS country,
                        SUBSTRING_INDEX(GROUP_CONCAT(IF(country_code != '', city, NULL) ORDER BY viewed_at DESC SEPARATOR '|'), '|', 1) AS city
                   FROM " . shopys_vc_table() . "
                  GROUP BY url
                  ORDER BY last_viewed DESC
                  LIMIT %d OFFSET %d",
                $limit, $offset
            ) );
        }
        return $rows ?: array();
    } catch ( \Throwable $e ) { return array(); }
}

/**
 * Count of distinct URLs for a given period (used for pagination).
 */
function shopys_vc_count_pages_by_period( $year = 0, $month = 0 ) {
    if ( ! shopys_vc_ensure_table() ) return 0;
    try {
        global $wpdb;
        if ( $year && $month ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT url) FROM " . shopys_vc_table() . " WHERE YEAR(viewed_at) = %d AND MONTH(viewed_at) = %d",
                $year, $month
            ) );
        } elseif ( $year ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT url) FROM " . shopys_vc_table() . " WHERE YEAR(viewed_at) = %d",
                $year
            ) );
        } else {
            return (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT url) FROM " . shopys_vc_table()
            );
        }
    } catch ( \Throwable $e ) { return 0; }
}

/**
 * Returns distinct year+month combos that have at least one recorded view.
 */
function shopys_vc_available_months() {
    if ( ! shopys_vc_ensure_table() ) return array();
    try {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT YEAR(viewed_at) AS yr, MONTH(viewed_at) AS mo
               FROM " . shopys_vc_table() . "
              GROUP BY YEAR(viewed_at), MONTH(viewed_at)
              ORDER BY yr DESC, mo DESC"
        ) ?: array();
    } catch ( \Throwable $e ) { return array(); }
}

function shopys_vc_daily_series( $days = 14 ) {
    $days = max( 1, min( 60, (int) $days ) );
    $series = array();
    $today_ts = current_time( 'timestamp' );
    for ( $i = $days - 1; $i >= 0; $i-- ) {
        $d = date( 'Y-m-d', $today_ts - ( $i * DAY_IN_SECONDS ) );
        $series[ $d ] = array( 'date' => $d, 'views' => 0, 'uniques' => 0 );
    }

    if ( ! shopys_vc_ensure_table() ) return array_values( $series );

    try {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(viewed_at) AS d, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS uniques
               FROM " . shopys_vc_table() . "
              WHERE viewed_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
              GROUP BY DATE(viewed_at)",
            $days
        ) );
        foreach ( $rows as $r ) {
            if ( isset( $series[ $r->d ] ) ) {
                $series[ $r->d ]['views']   = (int) $r->views;
                $series[ $r->d ]['uniques'] = (int) $r->uniques;
            }
        }
    } catch ( \Throwable $e ) {}

    return array_values( $series );
}


/* ═══════════════════════════════════════════════════════════════════
   5. ADMIN: MENU, SETTINGS, DASHBOARD PAGE
   ═══════════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'shopys_vc_admin_menu' );
function shopys_vc_admin_menu() {
    add_menu_page(
        __( 'Site Views', 'shopys' ),
        __( 'Site Views', 'shopys' ),
        'manage_options',
        'shopys-views',
        'shopys_vc_render_dashboard',
        'dashicons-visibility',
        58
    );
}

add_action( 'admin_init', 'shopys_vc_register_settings' );
function shopys_vc_register_settings() {
    register_setting( 'shopys_vc_settings', 'shopys_vc_count_admins', 'sanitize_text_field' );
    register_setting( 'shopys_vc_settings', 'shopys_vc_disabled',     'sanitize_text_field' );
}

function shopys_vc_render_dashboard() {
    $now_ts          = current_time( 'timestamp' );
    $today_start     = date( 'Y-m-d 00:00:00', $now_ts );
    $yesterday_start = date( 'Y-m-d 00:00:00', $now_ts - DAY_IN_SECONDS );
    $week_start      = date( 'Y-m-d 00:00:00', $now_ts - 7 * DAY_IN_SECONDS );
    $month_start     = date( 'Y-m-d 00:00:00', $now_ts - 30 * DAY_IN_SECONDS );

    $views_today     = shopys_vc_count_views( $today_start );
    $views_yesterday = max( 0, shopys_vc_count_views( $yesterday_start ) - $views_today );
    $views_7d        = shopys_vc_count_views( $week_start );
    $views_30d       = shopys_vc_count_views( $month_start );

    $uniq_today      = shopys_vc_count_unique( $today_start );
    $uniq_7d         = shopys_vc_count_unique( $week_start );
    $uniq_30d        = shopys_vc_count_unique( $month_start );

    $top_pages       = shopys_vc_top_pages( $week_start, 15 );
    $series          = shopys_vc_daily_series( 14 );

    $max_views = 1;
    foreach ( $series as $row ) { if ( $row['views'] > $max_views ) $max_views = $row['views']; }

    $count_admins = get_option( 'shopys_vc_count_admins', '0' );
    $disabled     = get_option( 'shopys_vc_disabled',     '0' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Site Views', 'shopys' ); ?></h1>
        <p style="color:#666;">Real-time pageview tracking for your site. Bots, admins, and crawlers excluded automatically.</p>

        <?php if ( $disabled === '1' ) : ?>
            <div class="notice notice-warning"><p>⚠️ Tracking is currently <strong>disabled</strong> in the settings below.</p></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin:18px 0;">
            <?php
            $cards = array(
                array( 'Today',         $views_today,     $uniq_today  . ' unique visitors' ),
                array( 'Yesterday',     $views_yesterday, '' ),
                array( 'Last 7 days',   $views_7d,        $uniq_7d     . ' unique visitors' ),
                array( 'Last 30 days',  $views_30d,       $uniq_30d    . ' unique visitors' ),
            );
            foreach ( $cards as $c ) : ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:18px;">
                    <div style="font-size:13px;color:#777;text-transform:uppercase;letter-spacing:0.5px;"><?php echo esc_html( $c[0] ); ?></div>
                    <div style="font-size:32px;font-weight:700;color:#1d2327;margin:6px 0 2px;"><?php echo number_format_i18n( $c[1] ); ?></div>
                    <div style="font-size:12px;color:#888;"><?php echo esc_html( $c[2] ?: 'pageviews' ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:18px;margin-bottom:18px;">
            <h2 style="margin:0 0 12px;font-size:15px;">Last 14 days</h2>
            <div style="display:flex;align-items:flex-end;gap:6px;height:160px;border-bottom:1px solid #eee;padding-bottom:4px;">
                <?php foreach ( $series as $row ) :
                    $h = $max_views > 0 ? round( ( $row['views'] / $max_views ) * 140 ) : 0;
                    $is_today = $row['date'] === date( 'Y-m-d', $now_ts );
                ?>
                    <div title="<?php echo esc_attr( $row['date'] . ': ' . $row['views'] . ' views, ' . $row['uniques'] . ' unique' ); ?>"
                         style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
                        <div style="font-size:10px;color:#666;margin-bottom:2px;"><?php echo (int) $row['views']; ?></div>
                        <div style="width:100%;background:<?php echo $is_today ? '#2271b1' : '#72aee6'; ?>;height:<?php echo (int) $h; ?>px;border-radius:3px 3px 0 0;min-height:1px;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;margin-top:6px;">
                <?php foreach ( $series as $row ) : ?>
                    <div style="flex:1;text-align:center;font-size:10px;color:#888;"><?php echo esc_html( date( 'd/m', strtotime( $row['date'] ) ) ); ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:18px;margin-bottom:18px;">
            <h2 style="margin:0 0 12px;font-size:15px;">Top pages — last 7 days</h2>
            <?php if ( $top_pages ) : ?>
            <table class="widefat striped" style="border:0;">
                <thead><tr><th style="width:40px;">#</th><th>Page</th><th style="width:90px;">URL</th><th style="width:80px;text-align:right;">Views</th></tr></thead>
                <tbody>
                <?php foreach ( $top_pages as $i => $row ) : ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo esc_html( $row->title ?: '(untitled)' ); ?></strong></td>
                        <td><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" style="font-size:12px;color:#666;">open ↗</a></td>
                        <td style="text-align:right;font-weight:600;"><?php echo number_format_i18n( (int) $row->views ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p><em>No views recorded yet. Visit your site in an incognito window to test (admin views are excluded).</em></p>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:18px;">
            <h2 style="margin:0 0 12px;font-size:15px;">Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'shopys_vc_settings' ); ?>
                <p>
                    <label>
                        <input type="checkbox" name="shopys_vc_count_admins" value="1" <?php checked( $count_admins, '1' ); ?> />
                        Count administrator visits (default: off)
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="shopys_vc_disabled" value="1" <?php checked( $disabled, '1' ); ?> />
                        <strong style="color:#a00;">Disable tracking entirely</strong> (kill switch — instant pause)
                    </label>
                </p>
                <?php submit_button( 'Save', 'secondary', 'submit', false ); ?>
            </form>
        </div>
    </div>
    <?php
}


/* ═══════════════════════════════════════════════════════════════════
   6. WP DASHBOARD WIDGET (shows at /wp-admin)
   ═══════════════════════════════════════════════════════════════════ */

add_action( 'wp_dashboard_setup', 'shopys_vc_register_widget' );
function shopys_vc_register_widget() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    wp_add_dashboard_widget(
        'shopys_vc_widget',
        __( 'Site Views', 'shopys' ),
        'shopys_vc_render_widget'
    );
}

function shopys_vc_render_widget() {
    $now_ts      = current_time( 'timestamp' );
    $today_start = date( 'Y-m-d 00:00:00', $now_ts );
    $week_start  = date( 'Y-m-d 00:00:00', $now_ts - 7 * DAY_IN_SECONDS );

    $views_today = shopys_vc_count_views( $today_start );
    $uniq_today  = shopys_vc_count_unique( $today_start );
    $views_7d    = shopys_vc_count_views( $week_start );
    $uniq_7d     = shopys_vc_count_unique( $week_start );
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="background:#f6f7f7;padding:12px;border-radius:6px;">
            <div style="font-size:11px;color:#666;text-transform:uppercase;">Today</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n( $views_today ); ?></div>
            <div style="font-size:11px;color:#888;"><?php echo number_format_i18n( $uniq_today ); ?> visitors</div>
        </div>
        <div style="background:#f6f7f7;padding:12px;border-radius:6px;">
            <div style="font-size:11px;color:#666;text-transform:uppercase;">Last 7 days</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n( $views_7d ); ?></div>
            <div style="font-size:11px;color:#888;"><?php echo number_format_i18n( $uniq_7d ); ?> visitors</div>
        </div>
    </div>
    <p style="margin:10px 0 0;text-align:right;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=shopys-views' ) ); ?>">View full dashboard →</a>
    </p>
    <?php
}
