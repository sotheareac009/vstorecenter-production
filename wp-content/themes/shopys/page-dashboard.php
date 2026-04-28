<?php
/**
 * Custom Dashboard — served at /dashboard/
 * Requires the user to be logged in (enforced by functions.php route handler).
 *
 * Sidebar menu items:
 *   • Overview        — summary cards
 *   • Site View       — pageview analytics (data from view-counter.php)
 *   • Products        — link to WooCommerce products
 *   • Orders          — link to WooCommerce orders
 *   • WP Admin        — escape hatch to wp-admin
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/vstore-admin/' ) );
    exit;
}

$current_user   = wp_get_current_user();
$is_site_owner  = ( $current_user->user_login === 'reach' || $current_user->user_email === 'blaxkk.stone.68@gmail.com' );
$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
// Block non-owners from accessing owner-only tabs via URL
if ( $active_tab === 'users' && ! $is_site_owner ) $active_tab = 'overview';
if ( $active_tab === 'telegram-users' && ! $is_site_owner ) $active_tab = 'overview';

// ── Collect Site-View data (safe even if view-counter isn't loaded) ───────────
$has_vc = function_exists( 'shopys_vc_count_views' );

if ( $has_vc ) {
    $now_ts          = current_time( 'timestamp' );
    $today_start     = date( 'Y-m-d 00:00:00', $now_ts );
    $yesterday_start = date( 'Y-m-d 00:00:00', $now_ts - DAY_IN_SECONDS );
    $week_start      = date( 'Y-m-d 00:00:00', $now_ts - 7 * DAY_IN_SECONDS );
    $month_start     = date( 'Y-m-d 00:00:00', $now_ts - 30 * DAY_IN_SECONDS );

    $sv_card_country = isset( $_GET['sv_card_country'] ) ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['sv_card_country'] ) ) ), 0, 2 ) : 'KH';

    $views_today     = shopys_vc_count_views( $today_start,     $sv_card_country );
    $views_yesterday = max( 0, shopys_vc_count_views( $yesterday_start, $sv_card_country ) - $views_today );
    $views_7d        = shopys_vc_count_views( $week_start,      $sv_card_country );
    $views_30d       = shopys_vc_count_views( $month_start,     $sv_card_country );
    $uniq_today      = shopys_vc_count_unique( $today_start,    $sv_card_country );
    $uniq_7d         = shopys_vc_count_unique( $week_start,     $sv_card_country );
    $uniq_30d        = shopys_vc_count_unique( $month_start,    $sv_card_country );
    $top_pages       = shopys_vc_top_pages( $week_start, 10 );
    $series          = shopys_vc_daily_series( 14 );
    $top_locations   = function_exists( 'shopys_vc_top_locations' ) ? shopys_vc_top_locations( $week_start, 20 ) : [];
    $max_views       = 1;

    // Custom date card stats
    $sv_card_date = '';
    if ( ! empty( $_GET['sv_card_date'] ) ) {
        $raw = sanitize_text_field( wp_unslash( $_GET['sv_card_date'] ) );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            $sv_card_date = $raw;
        }
    }
    $views_card_date = $sv_card_date && function_exists('shopys_vc_count_views_date')  ? shopys_vc_count_views_date( $sv_card_date,  $sv_card_country ) : 0;
    $uniq_card_date  = $sv_card_date && function_exists('shopys_vc_count_unique_date') ? shopys_vc_count_unique_date( $sv_card_date, $sv_card_country ) : 0;
    foreach ( $series as $row ) {
        if ( $row['views'] > $max_views ) $max_views = $row['views'];
    }
}

// ── All-pages sub-view state ──────────────────────────────────────────────────
$now_ts_ref = isset( $now_ts ) ? $now_ts : current_time( 'timestamp' );
$sv_view    = 'week';
if ( isset( $_GET['sv_view'] ) ) {
    $v = $_GET['sv_view'];
    if ( $v === 'all' ) $sv_view = 'all';
    elseif ( $v === 'locations' ) $sv_view = 'locations';
}
$sv_year    = isset( $_GET['sv_year'] )  ? (int) $_GET['sv_year']  : (int) date( 'Y', $now_ts_ref );
$sv_month   = isset( $_GET['sv_month'] ) ? (int) $_GET['sv_month'] : (int) date( 'n', $now_ts_ref );
$sv_country = isset( $_GET['sv_country'] ) ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['sv_country'] ) ) ), 0, 2 ) : 'KH';
$sv_date    = '';
if ( ! empty( $_GET['sv_date'] ) ) {
    $raw = sanitize_text_field( wp_unslash( $_GET['sv_date'] ) );
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
        $sv_date = $raw; // validated YYYY-MM-DD
    }
}

$all_pages    = [];
$avail_months = [];
$avail_countries = [];
$sv_total     = 0;
$sv_per_page  = 20;
$sv_page      = isset( $_GET['sv_page'] ) ? max( 1, (int) $_GET['sv_page'] ) : 1;
$sv_offset    = ( $sv_page - 1 ) * $sv_per_page;
$sv_total_pages = 1;

if ( $has_vc ) {
    $avail_months = function_exists( 'shopys_vc_available_months' ) ? shopys_vc_available_months() : [];
    $avail_countries = function_exists( 'shopys_vc_available_countries' ) ? shopys_vc_available_countries() : [];
    if ( $sv_view === 'all' && function_exists( 'shopys_vc_pages_by_period' ) ) {
        $sv_total       = function_exists( 'shopys_vc_count_pages_by_period' )
                          ? shopys_vc_count_pages_by_period( $sv_year, $sv_month, $sv_country, $sv_date )
                          : 0;
        $sv_total_pages = $sv_total > 0 ? (int) ceil( $sv_total / $sv_per_page ) : 1;
        $sv_page        = min( $sv_page, $sv_total_pages );
        $sv_offset      = ( $sv_page - 1 ) * $sv_per_page;
        $all_pages      = shopys_vc_pages_by_period( $sv_year, $sv_month, $sv_per_page, $sv_offset, $sv_country, $sv_date );
    } elseif ( $sv_view === 'locations' && function_exists( 'shopys_vc_locations_by_url' ) ) {
        $sv_url         = isset( $_GET['sv_url'] ) ? sanitize_text_field( wp_unslash( $_GET['sv_url'] ) ) : '';
        $sv_total       = function_exists( 'shopys_vc_count_locations_by_url' ) ? shopys_vc_count_locations_by_url( $sv_url ) : 0;
        $sv_total_pages = $sv_total > 0 ? (int) ceil( $sv_total / $sv_per_page ) : 1;
        $sv_page        = min( $sv_page, $sv_total_pages );
        $sv_offset      = ( $sv_page - 1 ) * $sv_per_page;
        $all_pages      = shopys_vc_locations_by_url( $sv_url, $sv_per_page, $sv_offset );
    }
}

// ── WooCommerce quick stats ───────────────────────────────────────────────────
$total_products = 0;
$total_orders   = 0;
if ( class_exists( 'WooCommerce' ) ) {
    $total_products = wp_count_posts( 'product' )->publish ?? 0;
    // Order count: supports both classic post-table and HPOS
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
         && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
        // HPOS — query the orders table directly
        $hpos_counts = wc_get_orders( [ 'limit' => -1, 'return' => 'ids', 'status' => array_keys( wc_get_order_statuses() ) ] );
        $total_orders = count( $hpos_counts );
    } else {
        // Classic post-table
        $order_counts = wp_count_posts( 'shop_order' );
        foreach ( (array) $order_counts as $v ) $total_orders += (int) $v;
    }
}

$logo_id  = get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

// ── Sidebar menu definition ───────────────────────────────────────────────────
$menu_items = [
    'overview'  => [ 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Overview' ],
    'siteview'  => [ 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'label' => 'Site View' ],
    'analytics' => [ 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Analytics' ],
    'users'     => [ 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Users', 'owner_only' => true ],
    'telegram-users' => [ 'icon' => 'M21.5 4.5l-3.1 14.6c-.2 1-1 .9-1.6.6l-4.7-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.4-.1-.6-.6-.3L6.2 12 1.7 10.6c-1-.3-1-1 .2-1.5l17.6-6.8c.8-.3 1.5.2 1.2 1.2z', 'label' => 'Telegram Chatbot Users', 'owner_only' => true ],
    'products'  => [ 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Products', 'href' => admin_url( 'edit.php?post_type=product' ) ],
    'orders'    => [ 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', 'label' => 'Orders', 'href' => admin_url( 'edit.php?post_type=shop_order' ) ],
    'wp-admin'  => [ 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'WP Admin', 'href' => admin_url(), 'owner_only' => true ],
];

// ── Analytics tab — period resolution ────────────────────────────────────────
$an_period = isset( $_GET['an_period'] ) ? sanitize_key( $_GET['an_period'] ) : '7d';
if ( ! in_array( $an_period, [ '7d', '30d', 'day', 'month', 'year' ], true ) ) $an_period = '7d';
$an_now   = current_time( 'timestamp' );
$an_year  = isset( $_GET['an_year'] )  ? (int) $_GET['an_year']  : (int) date( 'Y', $an_now );
$an_month = isset( $_GET['an_month'] ) ? (int) $_GET['an_month'] : (int) date( 'n', $an_now );
$an_day   = isset( $_GET['an_day'] )   ? sanitize_text_field( $_GET['an_day'] ) : date( 'Y-m-d', $an_now );

switch ( $an_period ) {
    case 'day':
        $an_since = $an_day . ' 00:00:00';
        $an_until = $an_day . ' 23:59:59';
        $an_label = 'Day: ' . date( 'j M Y', strtotime( $an_day ) );
        break;
    case 'month':
        $an_since = sprintf( '%04d-%02d-01 00:00:00', $an_year, $an_month );
        $an_until = date( 'Y-m-t 23:59:59', strtotime( $an_since ) );
        $an_label = date( 'F Y', strtotime( $an_since ) );
        break;
    case 'year':
        $an_since = "{$an_year}-01-01 00:00:00";
        $an_until = "{$an_year}-12-31 23:59:59";
        $an_label = "Year: {$an_year}";
        break;
    case '30d':
        $an_since = date( 'Y-m-d 00:00:00', $an_now - 30 * DAY_IN_SECONDS );
        $an_until = date( 'Y-m-d 23:59:59', $an_now );
        $an_label = 'Last 30 Days';
        break;
    default: // 7d
        $an_since = date( 'Y-m-d 00:00:00', $an_now - 7 * DAY_IN_SECONDS );
        $an_until = date( 'Y-m-d 23:59:59', $an_now );
        $an_label = 'Last 7 Days';
        break;
}

$an_country        = isset( $_GET['an_country'] )        ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['an_country'] ) ) ), 0, 2 )        : 'KH';
$an_pr_country     = isset( $_GET['an_pr_country'] )     ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['an_pr_country'] ) ) ), 0, 2 )     : 'KH';
$an_hourly_country = isset( $_GET['an_hourly_country'] ) ? substr( strtoupper( sanitize_text_field( wp_unslash( $_GET['an_hourly_country'] ) ) ), 0, 2 ) : 'KH';
$an_pages    = function_exists( 'shopys_vc_analytics_pages' )    ? shopys_vc_analytics_pages( $an_since, $an_until, 10, $an_country )       : [];
$an_products = function_exists( 'shopys_vc_analytics_products' ) ? shopys_vc_analytics_products( $an_since, $an_until, 10, $an_pr_country ) : [];
$an_hourly   = function_exists( 'shopys_vc_hourly_views' )       ? shopys_vc_hourly_views( $an_since, $an_until, $an_hourly_country )       : array_fill( 0, 24, ['views'=>0,'uniques'=>0] );
$an_sources  = function_exists( 'shopys_vc_traffic_sources' )    ? shopys_vc_traffic_sources( $an_since, $an_until, 15 )    : [];
$an_base_url = add_query_arg( [ 'tab' => 'analytics' ], home_url( '/dashboard/' ) );

// Source icon map
$an_source_icons = [
    'Direct'     => '🔗',
    'Google'     => '🔍',
    'Bing'       => '🔍',
    'Yahoo'      => '🔍',
    'Facebook'   => '👥',
    'Instagram'  => '📸',
    'TikTok'     => '🎵',
    'Twitter / X'=> '🐦',
    'YouTube'    => '▶️',
    'Telegram'   => '✈️',
    'LinkedIn'   => '💼',
    'Pinterest'  => '📌',
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — <?php bloginfo('name'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── DARK MODE (default) ── */
:root {
    --bg:        #0d1117;
    --surface:   #111820;
    --surface2:  #161f2c;
    --border:    #1e2d3d;
    --green:     #13e800;
    --green-dim: rgba(19,232,0,.12);
    --text:      #e6edf3;
    --muted:     #8b949e;
    --sidebar-w: 220px;
    --shadow:    0 1px 4px rgba(0,0,0,.4);
}

/* ── LIGHT MODE ── */
:root.light {
    --bg:        #f0f2f5;
    --surface:   #ffffff;
    --surface2:  #f5f7fa;
    --border:    #e2e6ea;
    --green:     #0db800;
    --green-dim: rgba(13,184,0,.10);
    --text:      #1a1f2e;
    --muted:     #6b7280;
    --shadow:    0 1px 4px rgba(0,0,0,.1);
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    overflow: hidden;
}

/* ── SIDEBAR ──────────────────────────────────────────────────── */
.ds-sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    transition: transform .25s;
}

.ds-sidebar-logo {
    padding: 16px 12px 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-align: center;
}
.ds-sidebar-logo img {
    height: 36px;
    width: auto;
    max-width: 80px;
    object-fit: contain;
    border-radius: 8px;
    flex-shrink: 0;
}
.ds-sidebar-logo-text {
    font-weight: 800;
    font-size: 13px;
    color: var(--text);
    line-height: 1.3;
    word-break: break-word;
    width: 100%;
}
.ds-sidebar-logo-text span { color: inherit; }

.ds-nav {
    flex: 1;
    padding: 12px 0;
    overflow-y: auto;
}

.ds-nav-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--muted);
    text-transform: uppercase;
    padding: 16px 20px 6px;
}

.ds-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 0;
    transition: color .15s, background .15s;
    cursor: pointer;
    position: relative;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
}
.ds-nav-item:hover {
    color: var(--text);
    background: var(--surface2);
}
.ds-nav-item.active {
    color: var(--green);
    background: var(--green-dim);
}
.ds-nav-item.active::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--green);
    border-radius: 0 2px 2px 0;
}
.ds-nav-item svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ds-nav-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 8px 16px;
}

.ds-sidebar-user {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.ds-user-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: var(--green-dim);
    border: 1.5px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    color: var(--green);
    flex-shrink: 0;
}
.ds-user-info { min-width: 0; }
.ds-user-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ds-user-role {
    font-size: 11px;
    color: var(--muted);
}
.ds-logout {
    margin-left: auto;
    color: var(--muted);
    text-decoration: none;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: color .15s, background .15s;
    flex-shrink: 0;
}
.ds-logout:hover { color: #ef4444; background: rgba(239,68,68,.1); }

/* ── MAIN AREA ────────────────────────────────────────────────── */
.ds-main {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    height: 100vh;
}

.ds-topbar {
    padding: 16px 28px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.ds-topbar-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
}
.ds-topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ds-store-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: var(--green);
    color: #000;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: opacity .15s;
}
.ds-store-btn:hover { opacity: .85; }

.ds-content {
    padding: 28px;
    flex: 1;
}

/* ── PANELS ───────────────────────────────────────────────────── */
.ds-panel { display: none; }
.ds-panel.active { display: block; }

/* ── STAT CARDS ───────────────────────────────────────────────── */
.ds-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.ds-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    transition: border-color .2s;
}
.ds-card:hover { border-color: rgba(19,232,0,.3); }
.ds-card-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: var(--green-dim);
    border: 1px solid rgba(19,232,0,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    color: var(--green);
}
.ds-card-icon svg {
    width: 18px; height: 18px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.ds-card-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 6px;
}
.ds-card-value {
    font-size: 30px;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
    margin-bottom: 4px;
}
.ds-card-sub {
    font-size: 12px;
    color: var(--muted);
}

/* ── CHART ────────────────────────────────────────────────────── */
.ds-chart-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 24px;
}
.ds-chart-title {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--text);
}
.ds-bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 5px;
    height: 140px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 4px;
}
.ds-bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
}
.ds-bar-num { font-size: 9px; color: var(--muted); margin-bottom: 2px; }
.ds-bar {
    width: 100%;
    border-radius: 3px 3px 0 0;
    min-height: 2px;
    transition: opacity .2s;
}
.ds-bar:hover { opacity: .75; }
.ds-bar-labels {
    display: flex;
    gap: 5px;
    margin-top: 6px;
}
.ds-bar-labels span {
    flex: 1;
    text-align: center;
    font-size: 9.5px;
    color: var(--muted);
}

/* ── TABLE ────────────────────────────────────────────────────── */
.ds-table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 24px;
}
.ds-table-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
}
.ds-table {
    width: 100%;
    border-collapse: collapse;
}
.ds-table th {
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 10px 20px;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.ds-table td {
    font-size: 13px;
    color: var(--text);
    padding: 10px 20px;
    border-bottom: 1px solid rgba(30,45,61,.5);
}
.ds-table tr:last-child td { border-bottom: none; }
.ds-table tr:hover td { background: var(--surface2); }
.ds-table td a {
    color: var(--muted);
    font-size: 12px;
    text-decoration: none;
}
.ds-table td a:hover { color: var(--green); }
.ds-table .views-count { font-weight: 700; color: var(--green); text-align: right; }

/* ── INNER TABS (sv-tab) ──────────────────────────────────────── */
.sv-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0;
}
.sv-tab {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    text-decoration: none;
    border-radius: 6px 6px 0 0;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -1px;
    transition: color .15s, background .15s;
}
.sv-tab:hover { color: var(--text); background: var(--surface2); }
.sv-tab.active {
    color: var(--green);
    background: var(--surface);
    border-color: var(--border);
    border-bottom-color: var(--surface);
}

/* ── FILTER BAR ───────────────────────────────────────────────── */
.sv-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.sv-filter select {
    padding: 7px 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    outline: none;
    transition: border-color .15s;
}
.sv-filter select:focus { border-color: var(--green); }
.sv-filter-btn {
    padding: 7px 18px;
    background: var(--green);
    color: #000;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: opacity .15s;
}
.sv-filter-btn:hover { opacity: .85; }
.sv-filter label {
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
    white-space: nowrap;
}

/* ── PAGINATION ───────────────────────────────────────────────── */
.sv-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 16px 0 4px;
    flex-wrap: wrap;
}
.sv-pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    background: var(--surface2);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: background .15s, color .15s, border-color .15s;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
}
.sv-pag-btn:hover { color: var(--text); border-color: var(--green); background: var(--green-dim); }
.sv-pag-btn.active { background: var(--green); color: #000; border-color: var(--green); cursor: default; font-weight: 800; }
.sv-pag-btn.disabled { opacity: .4; pointer-events: none; }
.sv-pag-ellipsis { color: var(--muted); font-size: 13px; padding: 0 4px; }
.ds-theme-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, border-color .2s, color .2s, box-shadow .2s;
    white-space: nowrap;
    font-family: inherit;
}
.ds-theme-toggle:hover {
    background: var(--border);
    color: var(--text);
    box-shadow: var(--shadow);
}
.ds-theme-toggle svg {
    width: 15px; height: 15px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0;
    transition: transform .4s;
}
.ds-theme-toggle:hover svg { transform: rotate(20deg); }

/* ── MOBILE TOGGLE ────────────────────────────────────────────── */
.ds-mobile-toggle {
    display: none;
    position: fixed;
    bottom: 20px; right: 20px;
    z-index: 200;
    background: var(--green);
    color: #000;
    border: none;
    border-radius: 50%;
    width: 48px; height: 48px;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(19,232,0,.4);
}

@media (max-width: 768px) {
    .ds-sidebar { transform: translateX(-100%); }
    .ds-sidebar.open { transform: translateX(0); }
    .ds-main { margin-left: 0; }
    .ds-mobile-toggle { display: flex; align-items: center; justify-content: center; }
    .ds-content { padding: 16px; }
    .ds-theme-toggle span { display: none; }
}

.ds-badge {
    display: inline-block;
    background: var(--green);
    color: #000;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

/* ── ANALYTICS TAB ───────────────────────────────────────────────── */
.an-filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 24px;
}
.an-period-btn {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
}
.an-period-btn:hover { border-color: var(--green); color: var(--green); }
.an-period-btn.active { background: var(--green); border-color: var(--green); color: #000; }
.an-date-inputs {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}
.an-date-inputs select,
.an-date-inputs input[type=date],
.an-date-inputs input[type=number] {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 12px;
    padding: 5px 8px;
    outline: none;
}
.an-date-inputs select:focus,
.an-date-inputs input:focus { border-color: var(--green); }
.an-apply-btn {
    padding: 5px 12px;
    background: var(--green);
    color: #000;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.an-section { margin-bottom: 32px; }
.an-section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.an-section-title span { color: var(--muted); font-weight: 400; font-size: 12px; }
.an-hbar-wrap { display: flex; flex-direction: column; gap: 10px; }
.an-hbar-row { display: flex; align-items: center; gap: 10px; }
.an-hbar-label {
    width: 180px;
    min-width: 180px;
    font-size: 12px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.an-hbar-track {
    flex: 1;
    background: var(--surface2);
    border-radius: 4px;
    height: 22px;
    position: relative;
    overflow: hidden;
}
.an-hbar-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--green);
    opacity: .85;
    transition: width .4s ease;
    min-width: 2px;
}
.an-hbar-count {
    font-size: 12px;
    font-weight: 700;
    color: var(--green);
    min-width: 36px;
    text-align: right;
}
.an-hbar-uniq {
    font-size: 11px;
    color: var(--muted);
    min-width: 60px;
    text-align: right;
}
.an-empty {
    padding: 32px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
}

/* ── USERS TAB ───────────────────────────────────────────────────── */
.user-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: var(--surface2);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: var(--green);
    flex-shrink: 0;
    border: 1px solid var(--border);
}
.user-role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.role-administrator { background: rgba(239,68,68,.15); color: #f87171; }
.role-editor        { background: rgba(251,146,60,.15); color: #fb923c; }
.role-author        { background: rgba(250,204,21,.15); color: #facc15; }
.role-subscriber    { background: rgba(148,163,184,.15); color: #94a3b8; }
.role-other         { background: rgba(167,139,250,.15); color: #a78bfa; }
.user-online-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--green);
    display: inline-block; margin-right: 5px;
    box-shadow: 0 0 4px var(--green);
}
</style>
<!-- Apply saved theme BEFORE paint to avoid flash -->
<script>
(function(){
    var t = localStorage.getItem('ds_theme');
    if (t === 'light') document.documentElement.classList.add('light');
})();
</script>
</head>
<body>

<!-- ── SIDEBAR ─────────────────────────────────────────────────────────── -->
<aside class="ds-sidebar" id="ds-sidebar">

    <div class="ds-sidebar-logo">
        <?php if ( $logo_url ) : ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo('name'); ?>">
        <?php else : ?>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#13e800" stroke-width="2" style="flex-shrink:0">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        <?php endif; ?>
        <span class="ds-sidebar-logo-text"><?php bloginfo('name'); ?> <span> Dashboard</span></span>
    </div>

    <nav class="ds-nav">
        <div class="ds-nav-label">Main</div>

        <?php foreach ( $menu_items as $key => $item ) :
            if ( ! empty( $item['owner_only'] ) && ! $is_site_owner ) continue;
            if ( isset( $item['href'] ) ) {
                // external link
                $is_active = false;
                $href      = $item['href'];
                $tag       = 'a';
            } else {
                $is_active = ( $active_tab === $key );
                $href      = esc_url( add_query_arg( 'tab', $key, home_url('/dashboard/') ) );
                $tag       = 'a';
            }

            // Divider before WP Admin
            if ( $key === 'wp-admin' ) echo '<hr class="ds-nav-divider">';
        ?>
        <a href="<?php echo $href; ?>"
           class="ds-nav-item <?php echo $is_active ? 'active' : ''; ?>"
           <?php if ( isset( $item['href'] ) ) echo 'target="_blank"'; ?>>
            <svg viewBox="0 0 24 24">
                <path d="<?php echo esc_attr( $item['icon'] ); ?>"/>
            </svg>
            <?php echo esc_html( $item['label'] ); ?>
            <?php if ( $key === 'siteview' && $has_vc && $views_today > 0 ) : ?>
                <span class="ds-badge" style="margin-left:auto;"><?php echo number_format_i18n( $views_today ); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="ds-sidebar-user">
        <div class="ds-user-avatar"><?php echo strtoupper( mb_substr( $current_user->display_name, 0, 1 ) ); ?></div>
        <div class="ds-user-info">
            <div class="ds-user-name"><?php echo esc_html( $current_user->display_name ); ?></div>
            <div class="ds-user-role"><?php echo esc_html( implode( ', ', $current_user->roles ) ); ?></div>
        </div>
        <a href="<?php echo esc_url( wp_logout_url( home_url('/vstore-admin/') ) ); ?>"
           class="ds-logout"
           title="Log out">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
            </svg>
        </a>
    </div>
</aside>

<!-- ── MAIN ────────────────────────────────────────────────────────────── -->
<div class="ds-main">

    <div class="ds-topbar">
        <span class="ds-topbar-title">
            <?php echo esc_html( $menu_items[ $active_tab ]['label'] ?? 'Dashboard' ); ?>
        </span>
        <div class="ds-topbar-right">
            <!-- Light / Dark toggle -->
            <button class="ds-theme-toggle" id="ds-theme-btn" title="Switch theme" aria-label="Toggle light/dark mode">
                <!-- Moon icon (shown in dark mode) -->
                <svg id="ds-icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                <!-- Sun icon (shown in light mode, hidden initially) -->
                <svg id="ds-icon-sun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <span id="ds-theme-label">Light</span>
            </button>

            <a href="<?php echo esc_url( home_url('/') ); ?>" class="ds-store-btn" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                View Store
            </a>
        </div>
    </div>

    <div class="ds-content">

        <!-- ── OVERVIEW PANEL ────────────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" id="panel-overview">
            <div class="ds-cards">
                <!-- Views Today -->
                <div class="ds-card">
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <div class="ds-card-label">Views Today</div>
                    <div class="ds-card-value"><?php echo $has_vc ? number_format_i18n( $views_today ) : '—'; ?></div>
                    <div class="ds-card-sub"><?php echo $has_vc ? number_format_i18n( $uniq_today ) . ' unique visitors' : 'Tracking not active'; ?></div>
                </div>
                <!-- Last 7 Days -->
                <div class="ds-card">
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div class="ds-card-label">Last 7 Days</div>
                    <div class="ds-card-value"><?php echo $has_vc ? number_format_i18n( $views_7d ) : '—'; ?></div>
                    <div class="ds-card-sub"><?php echo $has_vc ? number_format_i18n( $uniq_7d ) . ' unique visitors' : ''; ?></div>
                </div>
                <!-- Products -->
                <?php if ( class_exists('WooCommerce') ) : ?>
                <div class="ds-card">
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="ds-card-label">Products</div>
                    <div class="ds-card-value"><?php echo number_format_i18n( $total_products ); ?></div>
                    <div class="ds-card-sub">Published products</div>
                </div>
                <!-- Orders -->
                <div class="ds-card">
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <div class="ds-card-label">Total Orders</div>
                    <div class="ds-card-value"><?php echo number_format_i18n( $total_orders ); ?></div>
                    <div class="ds-card-sub">All time</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $has_vc ) : ?>
            <!-- 14-day chart -->
            <div class="ds-chart-wrap">
                <div class="ds-chart-title">Pageviews — Last 14 days</div>
                <div class="ds-bar-chart">
                    <?php foreach ( $series as $row ) :
                        $h        = $max_views > 0 ? round( ( $row['views'] / $max_views ) * 130 ) : 0;
                        $is_today = $row['date'] === date( 'Y-m-d', current_time('timestamp') );
                    ?>
                    <div class="ds-bar-col" title="<?php echo esc_attr( $row['date'] . ': ' . $row['views'] . ' views' ); ?>">
                        <div class="ds-bar-num"><?php echo (int) $row['views'] ?: ''; ?></div>
                        <div class="ds-bar" style="height:<?php echo max(2,(int)$h); ?>px;background:<?php echo $is_today ? '#13e800' : 'rgba(19,232,0,.35)'; ?>"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="ds-bar-labels">
                    <?php foreach ( $series as $row ) : ?>
                    <span><?php echo esc_html( date( 'd/m', strtotime( $row['date'] ) ) ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── SITE VIEW PANEL ───────────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'siteview' ? 'active' : ''; ?>" id="panel-siteview">

            <?php if ( ! $has_vc ) : ?>
                <p style="color:var(--muted);padding:40px 0;text-align:center;">View-counter module not loaded.</p>
            <?php else : ?>

            <!-- Date filter for cards -->
            <form method="GET" action="<?php echo esc_url( home_url('/dashboard/') ); ?>" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="siteview">
                <?php if ($sv_view !== 'week') : ?><input type="hidden" name="sv_view" value="<?php echo esc_attr($sv_view); ?>"><?php endif; ?>
                <label style="color:var(--muted);font-size:13px;font-weight:500;">Country:</label>
                <select name="sv_card_country" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:5px 8px;font-size:13px;">
                    <option value="" <?php selected($sv_card_country,''); ?>>All countries</option>
                    <option value="KH" <?php selected($sv_card_country,'KH'); ?>>🇰🇭 Cambodia (KH)</option>
                    <?php foreach ( $avail_countries as $cr ) :
                        if ( $cr->country_code === 'KH' ) continue;
                    ?>
                    <option value="<?php echo esc_attr($cr->country_code); ?>" <?php selected($sv_card_country,$cr->country_code); ?>>
                        <?php echo esc_html( ($cr->country ?: $cr->country_code) . ' (' . $cr->country_code . ')' ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label style="color:var(--muted);font-size:13px;font-weight:500;">Date:</label>
                <input type="date" name="sv_card_date" value="<?php echo esc_attr( $sv_card_date ); ?>"
                       style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:5px 10px;font-size:13px;">
                <button type="submit" style="background:var(--accent);color:#fff;border:none;border-radius:6px;padding:5px 14px;font-size:13px;cursor:pointer;font-weight:600;">Apply</button>
                <?php if ( $sv_card_date ) : ?>
                <a href="<?php echo esc_url( remove_query_arg('sv_card_date') ); ?>" style="color:var(--muted);font-size:12px;text-decoration:none;">✕ Clear date</a>
                <?php endif; ?>
            </form>

            <div class="ds-cards">
                <?php
                $vc_cards = [
                    [ 'Today',        $views_today,     $uniq_today  . ' unique visitors' ],
                    [ 'Yesterday',    $views_yesterday, 'pageviews' ],
                    [ 'Last 7 days',  $views_7d,        $uniq_7d     . ' unique visitors' ],
                    [ 'Last 30 days', $views_30d,       $uniq_30d    . ' unique visitors' ],
                ];
                $icon_paths = [
                    'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
                    'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z',
                    'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                ];
                foreach ( $vc_cards as $idx => $c ) : ?>
                <div class="ds-card">
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="<?php echo esc_attr( $icon_paths[ $idx ] ); ?>"/></svg>
                    </div>
                    <div class="ds-card-label"><?php echo esc_html( $c[0] ); ?></div>
                    <div class="ds-card-value"><?php echo number_format_i18n( $c[1] ); ?></div>
                    <div class="ds-card-sub"><?php echo esc_html( $c[2] ); ?></div>
                </div>
                <?php endforeach; ?>

                <?php if ( $sv_card_date ) : ?>
                <div class="ds-card" style="border:2px solid var(--accent);position:relative;">
                    <div class="ds-card-icon" style="background:var(--accent);opacity:0.15;position:absolute;inset:0;border-radius:inherit;pointer-events:none;"></div>
                    <div class="ds-card-icon">
                        <svg viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="ds-card-label" style="color:var(--accent);"><?php echo esc_html( date('j M Y', strtotime($sv_card_date)) . ( $sv_card_country ? ' · ' . $sv_card_country : '' ) ); ?></div>
                    <div class="ds-card-value"><?php echo number_format_i18n( $views_card_date ); ?></div>
                    <div class="ds-card-sub"><?php echo number_format_i18n( $uniq_card_date ); ?> unique visitors</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- 14-day chart -->
            <div class="ds-chart-wrap">
                <div class="ds-chart-title">Pageviews — Last 14 days <span style="color:var(--muted);font-weight:400;font-size:12px;">(today highlighted in green)</span></div>
                <div class="ds-bar-chart">
                    <?php foreach ( $series as $row ) :
                        $h        = $max_views > 0 ? round( ( $row['views'] / $max_views ) * 130 ) : 0;
                        $is_today = $row['date'] === date( 'Y-m-d', current_time('timestamp') );
                    ?>
                    <div class="ds-bar-col" title="<?php echo esc_attr( $row['date'] . ': ' . $row['views'] . ' views, ' . $row['uniques'] . ' unique' ); ?>">
                        <div class="ds-bar-num"><?php echo (int) $row['views'] ?: ''; ?></div>
                        <div class="ds-bar" style="height:<?php echo max(2,(int)$h); ?>px;background:<?php echo $is_today ? '#13e800' : 'rgba(19,232,0,.4)'; ?>"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="ds-bar-labels">
                    <?php foreach ( $series as $row ) : ?>
                    <span><?php echo esc_html( date( 'd/m', strtotime( $row['date'] ) ) ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Inner tabs ───────────────────────────────── -->
            <?php
            $week_tab_url = esc_url( add_query_arg( [ 'tab' => 'siteview', 'sv_view' => 'week' ], home_url( '/dashboard/' ) ) );
            $all_tab_url  = esc_url( add_query_arg( [ 'tab' => 'siteview', 'sv_view' => 'all', 'sv_year' => $sv_year, 'sv_month' => $sv_month ], home_url( '/dashboard/' ) ) );
            ?>
            <div class="sv-tabs">
                <a href="<?php echo $week_tab_url; ?>" class="sv-tab <?php echo $sv_view === 'week' ? 'active' : ''; ?>">Top Pages — Last 7 days</a>
                <a href="<?php echo $all_tab_url; ?>"  class="sv-tab <?php echo $sv_view === 'all'  ? 'active' : ''; ?>">All Pages</a>
            </div>

            <?php if ( $sv_view === 'week' ) : ?>

            <!-- ── LAST 7 DAYS TABLE ──────────────────────────── -->
            <div class="ds-table-wrap">
                <div class="ds-table-head">Top 10 Pages — Last 7 days</div>
                <?php if ( $top_pages ) : ?>
                <table class="ds-table">
                    <thead><tr>
                        <th>#</th><th>Page</th><th>URL</th>
                        <th>Last Viewed</th><th>Date &amp; Time</th><th>Location</th>
                        <th style="text-align:right;">Views</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $top_pages as $i => $row ) :
                        $lv_raw = $row->last_viewed ?? '';
                        $lv_disp = $lv_abs = '—';
                        if ( $lv_raw ) {
                            $ts = strtotime( $lv_raw );
                            $diff = current_time('timestamp') - $ts;
                            if      ( $diff < 60 )        $lv_disp = 'Just now';
                            elseif  ( $diff < 3600 )      $lv_disp = round($diff/60).' min ago';
                            elseif  ( $diff < 86400 )     $lv_disp = round($diff/3600).' hr ago';
                            elseif  ( $diff < 86400*2 )   $lv_disp = 'Yesterday';
                            else                           $lv_disp = date('d M', $ts);
                            $lv_abs = date('j M Y, H:i', $ts);
                        }
                        $flag = '';
                        $cc = $row->country_code ?? '';
                        if ( $cc && strlen($cc) === 2 ) {
                            list($c1, $c2) = str_split(strtoupper($cc));
                            $flag = mb_convert_encoding('&#' . (127397 + ord($c1)) . ';', 'UTF-8', 'HTML-ENTITIES') . 
                                    mb_convert_encoding('&#' . (127397 + ord($c2)) . ';', 'UTF-8', 'HTML-ENTITIES');
                        }
                        $loc_count = (int)($row->location_count ?? 0);
                        if ( $loc_count > 1 ) {
                            $list_html = '<ul style="margin:4px 0 0 0;padding-left:14px;font-size:11px;color:var(--muted);list-style:disc;">';
                            $ll = array_unique(explode('|', $row->location_list ?? ''));
                            foreach ($ll as $l_item) {
                                $pts = explode(':', $l_item);
                                if (count($pts) < 3) continue;
                                $l_cc = $pts[0]; $l_country = $pts[1]; $l_city = $pts[2];
                                $line = ($l_city ? $l_city . ($l_country ? ', ' : '') : '') . ($l_country ?: $l_cc);
                                // Skip flag inside list to keep it super clean, or add string flag if needed
                                $list_html .= '<li>' . esc_html($line) . '</li>';
                            }
                            $list_html .= '</ul>';
                            $loc_link = esc_url( add_query_arg([ 'tab'=>'siteview', 'sv_view'=>'locations', 'sv_url'=>$row->url ], home_url('/dashboard/')) );
                            $loc_str = '<details style="cursor:pointer;"><summary style="color:var(--accent-dim);font-weight:600;outline:none;">Multiple Locations (' . $loc_count . ')</summary>' . $list_html . '<div style="margin-top:6px;padding-left:14px;"><a href="' . $loc_link . '" style="font-size:11px;color:var(--green);font-weight:600;text-decoration:none;">View all locations →</a></div></details>';
                            $flag = '🌐';
                        } else {
                            $loc_str = ($row->city ?? '') . ($row->city && $row->country ? ', ' : '') . ($row->country ?? $cc);
                            $loc_str = esc_html($loc_str ?: '—');
                        }
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?php echo $i+1; ?></td>
                        <td><strong><?php echo esc_html($row->title ?: '(untitled)'); ?></strong></td>
                        <td><a href="<?php echo esc_url($row->url); ?>" target="_blank">open ↗</a></td>
                        <td style="font-size:12px;color:var(--green);white-space:nowrap;font-weight:600;"><?php echo esc_html($lv_disp); ?></td>
                        <td style="font-size:12px;color:var(--muted);white-space:nowrap;"><?php echo esc_html($lv_abs); ?></td>
                        <td style="font-size:12px;white-space:nowrap;vertical-align:top;">
                            <div style="display:flex;align-items:flex-start;">
                                <?php if ($flag): ?><span style="margin-right:4px;font-size:14px;line-height:1.2;"><?php echo $flag; ?></span><?php endif; ?>
                                <div>
                                    <?php echo $loc_str; ?>
                                    <?php if ( !empty($row->last_ip_hash) ) : ?>
                                    <div style="font-size:10px;color:var(--muted);margin-top:2px;font-family:monospace;word-break:break-all;"><?php echo esc_html($row->last_ip_hash); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="views-count"><?php echo number_format_i18n((int)$row->views); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="padding:24px;color:var(--muted);font-size:13px;">No views recorded yet.</p>
                <?php endif; ?>
            </div>

            <?php elseif ( $sv_view === 'locations' ) : ?>

                <!-- ── PAGE LOCATIONS TABLE ───────────────── -->
                <div class="ds-table-wrap">
                    <div class="ds-table-head" style="display:flex;align-items:center;">
                        <a href="<?php echo esc_url(add_query_arg('tab', 'siteview', remove_query_arg(['sv_view','sv_url']))); ?>" style="margin-right:12px;text-decoration:none;color:var(--green);font-size:18px;line-height:0;">&larr;</a>
                        Visitor Locations for specific URL
                        <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:auto;text-align:right;">
                            URL: <a href="<?php echo esc_url($sv_url); ?>" target="_blank" style="color:var(--text);"><?php echo esc_html($sv_url); ?></a><br>
                            (<?php echo number_format_i18n($sv_total); ?> views)
                        </span>
                    </div>
                    <?php if ( $all_pages ) : ?>
                    <table class="ds-table">
                        <thead><tr>
                            <th style="width:50px;">#</th>
                            <th>Location</th>
                            <th>Viewed At</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $all_pages as $i => $row ) : 
                            $flag = '';
                            $cc = $row->country_code ?? '';
                            if ( $cc && strlen($cc) === 2 ) {
                                list($c1, $c2) = str_split(strtoupper($cc));
                                $flag = mb_convert_encoding('&#' . (127397 + ord($c1)) . ';', 'UTF-8', 'HTML-ENTITIES') . 
                                        mb_convert_encoding('&#' . (127397 + ord($c2)) . ';', 'UTF-8', 'HTML-ENTITIES');
                            }
                            $loc_str = ($row->city ?? '') . ($row->city && $row->country ? ', ' : '') . ($row->country ?? $cc);
                            $loc_str = esc_html($loc_str ?: '—');
                            
                            $ts = strtotime($row->viewed_at);
                            $lv_abs = date('j M Y, H:i', $ts);
                            
                            $diff = current_time('timestamp') - $ts;
                            if      ( $diff < 60 )        $lv_disp = 'Just now';
                            elseif  ( $diff < 3600 )      $lv_disp = round($diff/60).' min ago';
                            elseif  ( $diff < 86400 )     $lv_disp = round($diff/3600).' hr ago';
                            elseif  ( $diff < 86400*2 )   $lv_disp = 'Yesterday';
                            else                           $lv_disp = date('d M', $ts);
                        ?>
                        <tr>
                            <td style="color:var(--muted);font-size:12px;"><?php echo $sv_offset + $i + 1; ?></td>
                            <td style="font-size:13px;vertical-align:top;">
                                <div style="display:flex;align-items:flex-start;">
                                    <?php if ($flag): ?><span style="margin-right:8px;font-size:16px;line-height:1.2;"><?php echo $flag; ?></span><?php endif; ?>
                                    <div>
                                        <?php echo $loc_str; ?>
                                        <?php if ( !empty($row->ip_hash) ) : ?>
                                        <div style="font-size:10px;color:var(--muted);margin-top:2px;font-family:monospace;word-break:break-all;"><?php echo esc_html($row->ip_hash); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                                <span style="color:var(--text);font-weight:600;margin-right:6px;"><?php echo esc_html($lv_disp); ?></span>
                                <?php echo esc_html($lv_abs); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                        <p style="padding:24px;color:var(--muted);font-size:13px;">No views recorded for this URL.</p>
                    <?php endif; ?>

                    <?php if ( $sv_total_pages > 1 ) :
                        $pag_base = add_query_arg( [
                            'tab'      => 'siteview',
                            'sv_view'  => 'locations',
                            'sv_url'   => $sv_url,
                        ], home_url( '/dashboard/' ) );
                        $range_start = ( $sv_page - 1 ) * $sv_per_page + 1;
                        $range_end   = min( $sv_page * $sv_per_page, $sv_total );
                    ?>
                    <div class="sv-pagination">
                        <!-- Prev -->
                        <?php if ( $sv_page > 1 ) : ?>
                        <a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'sv_page', $sv_page - 1, $pag_base ) ); ?>">← Prev</a>
                        <?php else : ?>
                        <span class="sv-pag-btn disabled">← Prev</span>
                        <?php endif; ?>

                        <?php
                        $pages_to_show = [];
                        if ( $sv_total_pages <= 7 ) {
                            $pages_to_show = range( 1, $sv_total_pages );
                        } else {
                            $pages_to_show = [ 1 ];
                            $start = max( 2, $sv_page - 2 );
                            $end   = min( $sv_total_pages - 1, $sv_page + 2 );
                            if ( $start > 2 ) $pages_to_show[] = '…';
                            for ( $p = $start; $p <= $end; $p++ ) $pages_to_show[] = $p;
                            if ( $end < $sv_total_pages - 1 ) $pages_to_show[] = '…';
                            $pages_to_show[] = $sv_total_pages;
                        }
                        foreach ( $pages_to_show as $p ) :
                            if ( $p === '…' ) :
                        ?>
                        <span class="sv-pag-ellipsis">…</span>
                        <?php else : ?>
                        <a class="sv-pag-btn <?php echo $p === $sv_page ? 'active' : ''; ?>"
                           href="<?php echo esc_url( add_query_arg( 'sv_page', $p, $pag_base ) ); ?>">
                            <?php echo $p; ?>
                        </a>
                        <?php endif; endforeach; ?>

                        <!-- Next -->
                        <?php if ( $sv_page < $sv_total_pages ) : ?>
                        <a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'sv_page', $sv_page + 1, $pag_base ) ); ?>">Next →</a>
                        <?php else : ?>
                        <span class="sv-pag-btn disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                    <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:8px;">
                        Showing <?php echo number_format_i18n($range_start); ?>–<?php echo number_format_i18n($range_end); ?> of <?php echo number_format_i18n($sv_total); ?> views
                    </p>
                    <?php endif; ?>
                </div>

            <?php elseif ( $sv_view === 'all' ) : ?>

            <!-- ── ALL PAGES TABLE with filter ───────────────── -->
            <?php
            $month_names = [ 1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                             7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec' ];
            ?>
            <form class="sv-filter" method="GET" action="<?php echo esc_url(home_url('/dashboard/')); ?>">
                <input type="hidden" name="tab" value="siteview">
                <input type="hidden" name="sv_view" value="all">
                <label>Month</label>
                <select name="sv_month">
                    <option value="0" <?php selected($sv_month,0); ?>>All months</option>
                    <?php foreach ( $avail_months as $am ) : ?>
                    <option value="<?php echo (int)$am->mo; ?>"
                            data-yr="<?php echo (int)$am->yr; ?>"
                            <?php echo ($am->yr==$sv_year && $am->mo==$sv_month) ? 'selected' : ''; ?>>
                        <?php echo esc_html( ($month_names[$am->mo]??$am->mo) . ' ' . $am->yr ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label>Year</label>
                <select name="sv_year">
                    <option value="0" <?php selected($sv_year,0); ?>>All years</option>
                    <?php
                    $seen_yr = [];
                    foreach ( $avail_months as $am ) :
                        if ( isset($seen_yr[$am->yr]) ) continue;
                        $seen_yr[$am->yr] = true;
                    ?>
                    <option value="<?php echo (int)$am->yr; ?>" <?php selected($sv_year,(int)$am->yr); ?>>
                        <?php echo (int)$am->yr; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label>Country</label>
                <select name="sv_country">
                    <option value="" <?php selected($sv_country,''); ?>>All countries</option>
                    <?php foreach ( $avail_countries as $country_row ) : ?>
                    <option value="<?php echo esc_attr( $country_row->country_code ); ?>" <?php selected( $sv_country, $country_row->country_code ); ?>>
                        <?php echo esc_html( ( $country_row->country ?: $country_row->country_code ) . ' (' . $country_row->country_code . ')' ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label>Date</label>
                <input type="date" name="sv_date" value="<?php echo esc_attr( $sv_date ); ?>"
                       style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:5px 8px;font-size:13px;"
                       title="Filter by exact date (overrides Month/Year)">
                <?php if ( $sv_date ) : ?>
                <button type="button" onclick="this.previousElementSibling.value='';this.form.submit();" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;padding:0 4px;" title="Clear date">✕</button>
                <?php endif; ?>
                <button type="submit" class="sv-filter-btn">Filter</button>
            </form>

            <div class="ds-table-wrap">
                <div class="ds-table-head">
                    All Pages
                    <?php if ($sv_date) echo '&mdash; ' . esc_html( date('j M Y', strtotime($sv_date)) ); elseif ($sv_month && $sv_year) echo '&mdash; ' . esc_html(($month_names[$sv_month]??'') . ' ' . $sv_year); elseif ($sv_year) echo '&mdash; ' . $sv_year; ?>
                    <?php if ($sv_country) echo ' &mdash; ' . esc_html($sv_country); ?>
                    <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:8px;"><?php echo count($all_pages); ?> pages</span>
                </div>
                <?php if ( $all_pages ) : ?>
                <table class="ds-table">
                    <thead><tr>
                        <th>#</th><th>Page</th><th>URL</th>
                        <th>Last Viewed</th><th>Date &amp; Time</th><th>Location</th>
                        <th style="text-align:right;">Views</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $all_pages as $i => $row ) :
                        $lv_raw = $row->last_viewed ?? '';
                        $lv_disp = $lv_abs = '—';
                        if ( $lv_raw ) {
                            $ts = strtotime( $lv_raw );
                            $diff = current_time('timestamp') - $ts;
                            if      ( $diff < 60 )        $lv_disp = 'Just now';
                            elseif  ( $diff < 3600 )      $lv_disp = round($diff/60).' min ago';
                            elseif  ( $diff < 86400 )     $lv_disp = round($diff/3600).' hr ago';
                            elseif  ( $diff < 86400*2 )   $lv_disp = 'Yesterday';
                            else                           $lv_disp = date('d M', $ts);
                            $lv_abs = date('j M Y, H:i', $ts);
                        }
                        $flag = '';
                        $cc = $row->country_code ?? '';
                        if ( $cc && strlen($cc) === 2 ) {
                            list($c1, $c2) = str_split(strtoupper($cc));
                            $flag = mb_convert_encoding('&#' . (127397 + ord($c1)) . ';', 'UTF-8', 'HTML-ENTITIES') . 
                                    mb_convert_encoding('&#' . (127397 + ord($c2)) . ';', 'UTF-8', 'HTML-ENTITIES');
                        }
                        $loc_count = (int)($row->location_count ?? 0);
                        if ( $loc_count > 1 ) {
                            $list_html = '<ul style="margin:4px 0 0 0;padding-left:14px;font-size:11px;color:var(--muted);list-style:disc;">';
                            $ll = array_unique(explode('|', $row->location_list ?? ''));
                            foreach ($ll as $l_item) {
                                $pts = explode(':', $l_item);
                                if (count($pts) < 3) continue;
                                $l_cc = $pts[0]; $l_country = $pts[1]; $l_city = $pts[2];
                                $line = ($l_city ? $l_city . ($l_country ? ', ' : '') : '') . ($l_country ?: $l_cc);
                                // Skip flag inside list to keep it super clean, or add string flag if needed
                                $list_html .= '<li>' . esc_html($line) . '</li>';
                            }
                            $list_html .= '</ul>';
                            $loc_link = esc_url( add_query_arg([ 'tab'=>'siteview', 'sv_view'=>'locations', 'sv_url'=>$row->url ], home_url('/dashboard/')) );
                            $loc_str = '<details style="cursor:pointer;"><summary style="color:var(--accent-dim);font-weight:600;outline:none;">Multiple Locations (' . $loc_count . ')</summary>' . $list_html . '<div style="margin-top:6px;padding-left:14px;"><a href="' . $loc_link . '" style="font-size:11px;color:var(--green);font-weight:600;text-decoration:none;">View all locations →</a></div></details>';
                            $flag = '🌐';
                        } else {
                            $loc_str = ($row->city ?? '') . ($row->city && $row->country ? ', ' : '') . ($row->country ?? $cc);
                            $loc_str = esc_html($loc_str ?: '—');
                        }
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?php echo $i+1; ?></td>
                        <td><strong><?php echo esc_html($row->title ?: '(untitled)'); ?></strong></td>
                        <td><a href="<?php echo esc_url($row->url); ?>" target="_blank">open ↗</a></td>
                        <td style="font-size:12px;color:var(--green);white-space:nowrap;font-weight:600;"><?php echo esc_html($lv_disp); ?></td>
                        <td style="font-size:12px;color:var(--muted);white-space:nowrap;"><?php echo esc_html($lv_abs); ?></td>
                        <td style="font-size:12px;white-space:nowrap;vertical-align:top;">
                            <div style="display:flex;align-items:flex-start;">
                                <?php if ($flag): ?><span style="margin-right:4px;font-size:14px;line-height:1.2;"><?php echo $flag; ?></span><?php endif; ?>
                                <div>
                                    <?php echo $loc_str; ?>
                                    <?php if ( !empty($row->last_ip_hash) ) : ?>
                                    <div style="font-size:10px;color:var(--muted);margin-top:2px;font-family:monospace;word-break:break-all;"><?php echo esc_html($row->last_ip_hash); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="views-count"><?php echo number_format_i18n((int)$row->views); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="padding:24px;color:var(--muted);font-size:13px;">No pages found for this period.</p>
                <?php endif; ?>

                <?php if ( $sv_total_pages > 1 ) :
                    // Build base URL preserving all filters except sv_page
                    $pag_base = add_query_arg( [
                        'tab'      => 'siteview',
                        'sv_view'  => 'all',
                        'sv_year'  => $sv_year,
                        'sv_month' => $sv_month,
                        'sv_country' => $sv_country,
                        'sv_date'  => $sv_date,
                    ], home_url( '/dashboard/' ) );

                    $range_start = ( $sv_page - 1 ) * $sv_per_page + 1;
                    $range_end   = min( $sv_page * $sv_per_page, $sv_total );
                ?>
                <div class="sv-pagination">
                    <!-- Prev -->
                    <?php if ( $sv_page > 1 ) : ?>
                    <a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'sv_page', $sv_page - 1, $pag_base ) ); ?>">← Prev</a>
                    <?php else : ?>
                    <span class="sv-pag-btn disabled">← Prev</span>
                    <?php endif; ?>

                    <?php
                    // Show up to 7 page buttons with ellipsis
                    $pages_to_show = [];
                    if ( $sv_total_pages <= 7 ) {
                        $pages_to_show = range( 1, $sv_total_pages );
                    } else {
                        $pages_to_show = [ 1 ];
                        $start = max( 2, $sv_page - 2 );
                        $end   = min( $sv_total_pages - 1, $sv_page + 2 );
                        if ( $start > 2 ) $pages_to_show[] = '…';
                        for ( $p = $start; $p <= $end; $p++ ) $pages_to_show[] = $p;
                        if ( $end < $sv_total_pages - 1 ) $pages_to_show[] = '…';
                        $pages_to_show[] = $sv_total_pages;
                    }
                    foreach ( $pages_to_show as $p ) :
                        if ( $p === '…' ) :
                    ?>
                    <span class="sv-pag-ellipsis">…</span>
                    <?php else : ?>
                    <a class="sv-pag-btn <?php echo $p === $sv_page ? 'active' : ''; ?>"
                       href="<?php echo esc_url( add_query_arg( 'sv_page', $p, $pag_base ) ); ?>">
                        <?php echo $p; ?>
                    </a>
                    <?php endif; endforeach; ?>

                    <!-- Next -->
                    <?php if ( $sv_page < $sv_total_pages ) : ?>
                    <a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'sv_page', $sv_page + 1, $pag_base ) ); ?>">Next →</a>
                    <?php else : ?>
                    <span class="sv-pag-btn disabled">Next →</span>
                    <?php endif; ?>
                </div>
                <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:8px;">
                    Showing <?php echo number_format_i18n($range_start); ?>–<?php echo number_format_i18n($range_end); ?> of <?php echo number_format_i18n($sv_total); ?> pages
                </p>
                <?php endif; ?>
            </div>

            <?php endif; // sv_view ?>

            <!-- ── VISITOR LOCATIONS ─────────────────────────────── -->
            <?php if ( ! empty( $top_locations ) ) : ?>
            <div class="ds-table-wrap" style="margin-top:28px;">
                <div class="ds-table-head">
                    Visitor Locations — Last 7 days
                    <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:8px;"><?php echo count($top_locations); ?> cities</span>
                </div>
                <table class="ds-table">
                    <thead><tr>
                        <th>#</th>
                        <th>Country</th>
                        <th>Region</th>
                        <th>City</th>
                        <th style="text-align:right;">Visitors</th>
                        <th style="text-align:right;">Views</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $top_locations as $i => $loc ) :
                        // Convert country code to flag emoji
                        $flag = '';
                        if ( $loc->country_code && strlen($loc->country_code) === 2 ) {
                            $chars = str_split( strtoupper( $loc->country_code ) );
                            $flag = mb_convert_encoding('&#' . (127397 + ord($chars[0])) . ';', 'UTF-8', 'HTML-ENTITIES') . 
                                    mb_convert_encoding('&#' . (127397 + ord($chars[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
                        }
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?php echo $i + 1; ?></td>
                        <td>
                            <?php if ( $flag ) : ?><span style="font-size:18px;line-height:1;margin-right:6px;"><?php echo $flag; ?></span><?php endif; ?>
                            <strong><?php echo esc_html( $loc->country ?: $loc->country_code ?: '—' ); ?></strong>
                        </td>
                        <td style="color:var(--muted);font-size:13px;"><?php echo esc_html( $loc->region ?: '—' ); ?></td>
                        <td style="font-size:13px;"><?php echo esc_html( $loc->city ?: '—' ); ?></td>
                        <td style="text-align:right;font-size:13px;color:var(--green);font-weight:700;"><?php echo number_format_i18n( (int)$loc->unique_visitors ); ?></td>
                        <td class="views-count"><?php echo number_format_i18n( (int)$loc->views ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <!-- ── ANALYTICS TAB ──────────────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'analytics' ? 'active' : ''; ?>" id="panel-analytics">
        <?php if ( $active_tab === 'analytics' ) : ?>

            <!-- Filter bar -->
            <form method="get" action="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" style="margin-bottom:0;">
                <input type="hidden" name="tab" value="analytics">
                <div class="an-filter-bar">
                    <?php
                    $periods = [ '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'day' => 'Day', 'month' => 'Month', 'year' => 'Year' ];
                    foreach ( $periods as $pk => $pl ) :
                        $href = esc_url( add_query_arg( [ 'tab' => 'analytics', 'an_period' => $pk ], home_url( '/dashboard/' ) ) );
                    ?>
                    <a href="<?php echo $href; ?>" class="an-period-btn <?php echo $an_period === $pk ? 'active' : ''; ?>"><?php echo $pl; ?></a>
                    <?php endforeach; ?>

                    <?php if ( $an_period === 'day' ) : ?>
                    <div class="an-date-inputs">
                        <input type="hidden" name="an_period" value="day">
                        <input type="date" name="an_day" value="<?php echo esc_attr( $an_day ); ?>" max="<?php echo date('Y-m-d'); ?>">
                        <button type="submit" class="an-apply-btn">Go</button>
                    </div>
                    <?php elseif ( $an_period === 'month' ) : ?>
                    <div class="an-date-inputs">
                        <input type="hidden" name="an_period" value="month">
                        <select name="an_month">
                            <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                            <option value="<?php echo $m; ?>" <?php selected( $an_month, $m ); ?>><?php echo date( 'F', mktime( 0,0,0,$m,1 ) ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="an_year">
                            <?php for ( $y = (int) date('Y'); $y >= 2024; $y-- ) : ?>
                            <option value="<?php echo $y; ?>" <?php selected( $an_year, $y ); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="an-apply-btn">Go</button>
                    </div>
                    <?php elseif ( $an_period === 'year' ) : ?>
                    <div class="an-date-inputs">
                        <input type="hidden" name="an_period" value="year">
                        <select name="an_year">
                            <?php for ( $y = (int) date('Y'); $y >= 2024; $y-- ) : ?>
                            <option value="<?php echo $y; ?>" <?php selected( $an_year, $y ); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="an-apply-btn">Go</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>

            <div style="font-size:12px;color:var(--muted);margin-bottom:20px;">
                Showing data for: <strong style="color:var(--text);"><?php echo esc_html( $an_label ); ?></strong>
            </div>

            <!-- Traffic Sources -->
            <div class="ds-chart-wrap an-section">
                <div class="an-section-title">
                    Traffic Sources
                    <span><?php echo count( $an_sources ); ?> sources</span>
                </div>
                <?php if ( $an_sources ) :
                    $an_max_src = max( array_column( (array) $an_sources, 'views' ) );
                ?>
                <div class="an-hbar-wrap">
                    <div class="an-hbar-row" style="margin-bottom:4px;">
                        <div class="an-hbar-label" style="color:var(--muted);font-size:11px;">Source</div>
                        <div style="flex:1;"></div>
                        <div class="an-hbar-count" style="color:var(--muted);font-size:11px;">Views</div>
                        <div class="an-hbar-uniq" style="color:var(--muted);font-size:11px;">Uniques</div>
                    </div>
                    <?php foreach ( $an_sources as $src ) :
                        $pct     = $an_max_src > 0 ? round( ($src->views / $an_max_src) * 100 ) : 0;
                        $icon    = $an_source_icons[ $src->source ] ?? '🌐';
                        $is_direct = $src->source === 'Direct';
                        $bar_color = $is_direct ? 'var(--green)' : '#a78bfa';
                    ?>
                    <div class="an-hbar-row">
                        <div class="an-hbar-label">
                            <span style="margin-right:6px;"><?php echo $icon; ?></span><?php echo esc_html( $src->source ); ?>
                        </div>
                        <div class="an-hbar-track">
                            <div class="an-hbar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $bar_color; ?>;"></div>
                        </div>
                        <div class="an-hbar-count"><?php echo number_format_i18n( (int) $src->views ); ?></div>
                        <div class="an-hbar-uniq"><?php echo number_format_i18n( (int) $src->uniques ); ?> uniq</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ( empty( $an_sources ) && ! function_exists('shopys_vc_traffic_sources') ) : ?>
                <div class="an-empty">Traffic source tracking is not active yet.</div>
                <?php else : ?>
                <div class="an-empty">No traffic recorded for this period. Sources appear as visitors arrive.</div>
                <?php endif; ?>
            </div>

            <!-- Most Viewed Pages -->
            <div class="ds-chart-wrap an-section">
                <div class="an-section-title">
                    Most Viewed Pages
                    <span><?php echo count( $an_pages ); ?> pages</span>
                    <form method="get" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto;font-size:12px;font-weight:400;">
                        <?php
                        // Preserve all current GET params except an_country
                        foreach ( $_GET as $k => $v ) {
                            if ( $k === 'an_country' ) continue;
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                        }
                        $page_countries = [];
                        if ( function_exists('shopys_vc_ensure_table') && shopys_vc_ensure_table() ) {
                            global $wpdb;
                            $page_countries = $wpdb->get_results(
                                "SELECT DISTINCT country_code, country FROM " . shopys_vc_table() . "
                                  WHERE country_code != '' ORDER BY country ASC"
                            ) ?: [];
                        }
                        ?>
                        <label for="an_country_select" style="color:var(--muted);">Country:</label>
                        <select id="an_country_select" name="an_country" onchange="this.form.submit()" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:2px 6px;font-size:12px;cursor:pointer;">
                            <option value="" <?php selected($an_country,''); ?>>All</option>
                            <option value="KH" <?php selected($an_country,'KH'); ?>>🇰🇭 Cambodia (KH)</option>
                            <?php foreach ( $page_countries as $cr ) :
                                if ( $cr->country_code === 'KH' ) continue; // already listed above
                            ?>
                            <option value="<?php echo esc_attr($cr->country_code); ?>" <?php selected($an_country,$cr->country_code); ?>>
                                <?php echo esc_html( ($cr->country ?: $cr->country_code) . ' (' . $cr->country_code . ')' ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $an_country ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'an_country', '', remove_query_arg('an_country') ) ); ?>" style="color:var(--muted);font-size:11px;text-decoration:none;" title="Clear country filter">✕</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if ( $an_pages ) :
                    $an_max_p = max( array_column( (array) $an_pages, 'views' ) );
                ?>
                <div class="an-hbar-wrap">
                    <div class="an-hbar-row" style="margin-bottom:4px;">
                        <div class="an-hbar-label" style="color:var(--muted);font-size:11px;">Page</div>
                        <div style="flex:1;"></div>
                        <div class="an-hbar-count" style="color:var(--muted);font-size:11px;">Views</div>
                        <div class="an-hbar-uniq" style="color:var(--muted);font-size:11px;">Uniques</div>
                    </div>
                    <?php foreach ( $an_pages as $row ) :
                        $pct = $an_max_p > 0 ? round( ($row->views / $an_max_p) * 100 ) : 0;
                        $title = $row->title ?: basename( rtrim( $row->url, '/' ) ) ?: 'Home';
                    ?>
                    <div class="an-hbar-row">
                        <div class="an-hbar-label" title="<?php echo esc_attr( $row->url ); ?>">
                            <a href="<?php echo esc_url( $row->url ); ?>" target="_blank" style="color:var(--text);text-decoration:none;" title="<?php echo esc_attr($row->url); ?>"><?php echo esc_html( $title ); ?></a>
                        </div>
                        <div class="an-hbar-track">
                            <div class="an-hbar-fill" style="width:<?php echo $pct; ?>%;"></div>
                        </div>
                        <div class="an-hbar-count"><?php echo number_format_i18n( (int) $row->views ); ?></div>
                        <div class="an-hbar-uniq"><?php echo number_format_i18n( (int) $row->uniques ); ?> uniq</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="an-empty">No page views recorded for this period.</div>
                <?php endif; ?>
            </div>

            <!-- Most Viewed Products -->
            <div class="ds-chart-wrap an-section">
                <div class="an-section-title">
                    Most Viewed Products
                    <span><?php echo count( $an_products ); ?> products</span>
                    <form method="get" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto;font-size:12px;font-weight:400;">
                        <?php
                        foreach ( $_GET as $k => $v ) {
                            if ( $k === 'an_pr_country' ) continue;
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                        }
                        $pr_countries = [];
                        if ( function_exists('shopys_vc_ensure_table') && shopys_vc_ensure_table() ) {
                            global $wpdb;
                            $pr_countries = $wpdb->get_results(
                                "SELECT DISTINCT country_code, country FROM " . shopys_vc_table() . "
                                  WHERE country_code != '' AND post_type = 'product' ORDER BY country ASC"
                            ) ?: [];
                        }
                        ?>
                        <label for="an_pr_country_select" style="color:var(--muted);">Country:</label>
                        <select id="an_pr_country_select" name="an_pr_country" onchange="this.form.submit()" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:2px 6px;font-size:12px;cursor:pointer;">
                            <option value="" <?php selected($an_pr_country,''); ?>>All</option>
                            <option value="KH" <?php selected($an_pr_country,'KH'); ?>>🇰🇭 Cambodia (KH)</option>
                            <?php foreach ( $pr_countries as $cr ) :
                                if ( $cr->country_code === 'KH' ) continue;
                            ?>
                            <option value="<?php echo esc_attr($cr->country_code); ?>" <?php selected($an_pr_country,$cr->country_code); ?>>
                                <?php echo esc_html( ($cr->country ?: $cr->country_code) . ' (' . $cr->country_code . ')' ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $an_pr_country ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'an_pr_country', '', remove_query_arg('an_pr_country') ) ); ?>" style="color:var(--muted);font-size:11px;text-decoration:none;" title="Clear filter">✕</a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php if ( $an_products ) :
                    $an_max_pr = max( array_column( (array) $an_products, 'views' ) );
                ?>
                <div class="an-hbar-wrap">
                    <div class="an-hbar-row" style="margin-bottom:4px;">
                        <div class="an-hbar-label" style="color:var(--muted);font-size:11px;">Product</div>
                        <div style="flex:1;"></div>
                        <div class="an-hbar-count" style="color:var(--muted);font-size:11px;">Views</div>
                        <div class="an-hbar-uniq" style="color:var(--muted);font-size:11px;">Uniques</div>
                    </div>
                    <?php foreach ( $an_products as $row ) :
                        $pct       = $an_max_pr > 0 ? round( ($row->views / $an_max_pr) * 100 ) : 0;
                        $loc_count = (int) ($row->location_count ?? 0);
                        $cc        = $row->country_code ?? '';

                        // Build flag emoji
                        $pr_flag = '';
                        if ( $cc && strlen($cc) === 2 ) {
                            list($c1,$c2) = str_split( strtoupper($cc) );
                            $pr_flag = mb_convert_encoding('&#'.(127397+ord($c1)).';','UTF-8','HTML-ENTITIES')
                                     . mb_convert_encoding('&#'.(127397+ord($c2)).';','UTF-8','HTML-ENTITIES');
                        }

                        // Build location string(s)
                        if ( $loc_count > 1 ) {
                            $ll = array_unique( explode( '|', $row->location_list ?? '' ) );
                            $loc_parts = [];
                            foreach ( $ll as $l_item ) {
                                $pts = explode( ':', $l_item );
                                if ( count($pts) < 3 ) continue;
                                $l_cc = $pts[0]; $l_country = $pts[1]; $l_city = $pts[2];
                                $l_flag = '';
                                if ( $l_cc && strlen($l_cc) === 2 ) {
                                    list($lc1,$lc2) = str_split(strtoupper($l_cc));
                                    $l_flag = mb_convert_encoding('&#'.(127397+ord($lc1)).';','UTF-8','HTML-ENTITIES')
                                            . mb_convert_encoding('&#'.(127397+ord($lc2)).';','UTF-8','HTML-ENTITIES');
                                }
                                $loc_parts[] = $l_flag . ' ' . ($l_city ? $l_city.', ' : '') . ($l_country ?: $l_cc);
                            }
                            $loc_html = '<span style="color:var(--muted);font-size:11px;">' . esc_html( implode( ' · ', array_slice($loc_parts, 0, 5) ) ) . ( count($loc_parts) > 5 ? ' …' : '' ) . '</span>';
                        } elseif ( $cc ) {
                            $loc_str  = ($row->city ?? '') . ($row->city && $row->country ? ', ' : '') . ($row->country ?? $cc);
                            $loc_html = '<span style="color:var(--muted);font-size:11px;">' . $pr_flag . ' ' . esc_html($loc_str) . '</span>';
                        } else {
                            $loc_html = '';
                        }
                    ?>
                    <div class="an-hbar-row" style="align-items:flex-start;padding:4px 0;">
                        <div class="an-hbar-label" style="padding-top:2px;" title="<?php echo esc_attr( $row->url ); ?>">
                            <a href="<?php echo esc_url( $row->url ); ?>" target="_blank" style="color:var(--text);text-decoration:none;display:block;"><?php echo esc_html( $row->title ?: 'Product' ); ?></a>
                            <?php if ( $loc_html ) : ?><div style="margin-top:3px;"><?php echo $loc_html; ?></div><?php endif; ?>
                        </div>
                        <div class="an-hbar-track" style="margin-top:4px;">
                            <div class="an-hbar-fill" style="width:<?php echo $pct; ?>%;background:#0af;"></div>
                        </div>
                        <div class="an-hbar-count" style="padding-top:2px;"><?php echo number_format_i18n( (int) $row->views ); ?></div>
                        <div class="an-hbar-uniq" style="padding-top:2px;"><?php echo number_format_i18n( (int) $row->uniques ); ?> uniq</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="an-empty">No product views recorded for this period.</div>
                <?php endif; ?>
            </div>

            <!-- Peak Hours Chart -->
            <?php
            $hour_labels = [
                '12 AM','1 AM','2 AM','3 AM','4 AM','5 AM','6 AM','7 AM','8 AM','9 AM','10 AM','11 AM',
                '12 PM','1 PM','2 PM','3 PM','4 PM','5 PM','6 PM','7 PM','8 PM','9 PM','10 PM','11 PM'
            ];
            $views_only = array_column( $an_hourly, 'views' );
            $an_hourly_max = max( 1, max( $views_only ?: [0] ) );
            $an_hourly_total = array_sum( $views_only );

            // Period totals: Night (0-5), Morning (6-11), Afternoon (12-17), Evening (18-23)
            $periods = [
                'Night'     => [ 'range' => [0,5],   'icon' => '🌙', 'views' => 0, 'uniques' => 0 ],
                'Morning'   => [ 'range' => [6,11],  'icon' => '☀️', 'views' => 0, 'uniques' => 0 ],
                'Afternoon' => [ 'range' => [12,17], 'icon' => '🌤️', 'views' => 0, 'uniques' => 0 ],
                'Evening'   => [ 'range' => [18,23], 'icon' => '🌆', 'views' => 0, 'uniques' => 0 ],
            ];
            foreach ( $periods as $pn => &$p ) {
                for ( $h = $p['range'][0]; $h <= $p['range'][1]; $h++ ) {
                    $p['views']   += $an_hourly[$h]['views'];
                    $p['uniques'] += $an_hourly[$h]['uniques'];
                }
            }
            unset($p);

            // Find top 3 hours by views for ranking
            $ranked = $an_hourly;
            uasort( $ranked, function($a,$b){ return $b['views'] - $a['views']; } );
            $top_hours = array_slice( array_keys( $ranked ), 0, 3, true );
            $rank_color = [ $top_hours[0] ?? -1 => '#13e800', $top_hours[1] ?? -1 => '#f6c343', $top_hours[2] ?? -1 => '#ff8a3d' ];

            $hourly_countries = [];
            if ( function_exists('shopys_vc_ensure_table') && shopys_vc_ensure_table() ) {
                global $wpdb;
                $hourly_countries = $wpdb->get_results(
                    "SELECT DISTINCT country_code, country FROM " . shopys_vc_table() . " WHERE country_code != '' ORDER BY country ASC"
                ) ?: [];
            }
            ?>
            <div class="ds-chart-wrap an-section">
                <div class="an-section-title">
                    Peak Hours
                    <span>When visitors are most active</span>
                    <form method="get" style="display:inline-flex;align-items:center;gap:6px;margin-left:auto;font-size:12px;font-weight:400;">
                        <?php
                        foreach ( $_GET as $k => $v ) {
                            if ( $k === 'an_hourly_country' ) continue;
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                        }
                        ?>
                        <label style="color:var(--muted);">Country:</label>
                        <select name="an_hourly_country" onchange="this.form.submit()" style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:2px 6px;font-size:12px;cursor:pointer;">
                            <option value="" <?php selected($an_hourly_country,''); ?>>All</option>
                            <option value="KH" <?php selected($an_hourly_country,'KH'); ?>>🇰🇭 Cambodia (KH)</option>
                            <?php foreach ( $hourly_countries as $cr ) :
                                if ( $cr->country_code === 'KH' ) continue;
                            ?>
                            <option value="<?php echo esc_attr($cr->country_code); ?>" <?php selected($an_hourly_country,$cr->country_code); ?>>
                                <?php echo esc_html( ($cr->country ?: $cr->country_code) . ' (' . $cr->country_code . ')' ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $an_hourly_country ) : ?>
                        <a href="<?php echo esc_url( add_query_arg('an_hourly_country','',remove_query_arg('an_hourly_country')) ); ?>" style="color:var(--muted);font-size:11px;text-decoration:none;" title="Clear">✕</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ( $an_hourly_total > 0 ) : ?>

                <!-- Time Period Summary Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:24px;">
                    <?php foreach ( $periods as $pname => $pd ) :
                        $pct = $an_hourly_total > 0 ? round( ($pd['views'] / $an_hourly_total) * 100 ) : 0;
                        $is_busiest = ( $pd['views'] === max( array_column($periods,'views') ) && $pd['views'] > 0 );
                    ?>
                    <div style="background:var(--card);border:1px solid <?php echo $is_busiest ? '#13e800' : 'var(--border)'; ?>;border-radius:8px;padding:12px;<?php echo $is_busiest ? 'box-shadow:0 0 0 2px rgba(19,232,0,.1);' : ''; ?>">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <span style="font-size:13px;color:var(--muted);font-weight:500;">
                                <span style="font-size:16px;margin-right:4px;"><?php echo $pd['icon']; ?></span><?php echo esc_html($pname); ?>
                            </span>
                            <?php if ( $is_busiest ) : ?>
                            <span style="font-size:9px;background:#13e800;color:#000;padding:2px 6px;border-radius:10px;font-weight:700;">BUSIEST</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:22px;font-weight:700;color:var(--text);"><?php echo number_format($pd['views']); ?></div>
                        <div style="font-size:11px;color:var(--muted);">
                            <?php echo $pct; ?>% &middot; <?php echo number_format($pd['uniques']); ?> unique
                        </div>
                        <div style="font-size:10px;color:var(--muted);margin-top:4px;">
                            <?php echo $hour_labels[$pd['range'][0]] . ' – ' . $hour_labels[$pd['range'][1]]; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hourly bar chart -->
                <div style="background:var(--card);border:1px solid var(--border);border-radius:8px;padding:16px 12px;">
                    <div style="display:flex;align-items:flex-end;gap:4px;height:200px;padding-left:34px;position:relative;">

                        <!-- Y-axis grid lines -->
                        <div style="position:absolute;inset:0 0 0 34px;pointer-events:none;">
                            <?php for ( $i = 0; $i <= 4; $i++ ) :
                                $val = round( $an_hourly_max * ( 4 - $i ) / 4 );
                            ?>
                            <div style="position:absolute;left:0;right:0;top:<?php echo $i * 25; ?>%;border-top:1px dashed rgba(255,255,255,.06);"></div>
                            <div style="position:absolute;left:-32px;top:calc(<?php echo $i * 25; ?>% - 6px);font-size:10px;color:var(--muted);width:28px;text-align:right;"><?php echo number_format($val); ?></div>
                            <?php endfor; ?>
                        </div>

                        <?php foreach ( $an_hourly as $h => $hd ) :
                            $bar_h   = $hd['views'] > 0 ? max( 3, round( ($hd['views'] / $an_hourly_max) * 180 ) ) : 0;
                            $is_top  = isset( $rank_color[$h] );
                            $rank_n  = array_search( $h, $top_hours, true );
                            $color   = $is_top ? $rank_color[$h] : 'rgba(99,179,237,.45)';
                            $title   = $hour_labels[$h] . ': ' . number_format($hd['views']) . ' views, ' . number_format($hd['uniques']) . ' unique';
                        ?>
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;cursor:default;" title="<?php echo esc_attr($title); ?>">
                            <?php if ( $is_top && $hd['views'] > 0 ) : ?>
                            <div style="font-size:10px;color:<?php echo $color; ?>;font-weight:700;line-height:1.1;text-align:center;margin-bottom:2px;white-space:nowrap;">
                                #<?php echo $rank_n + 1; ?>
                                <div style="font-size:11px;color:<?php echo $color; ?>;font-weight:800;"><?php echo number_format($hd['views']); ?></div>
                            </div>
                            <?php elseif ( $hd['views'] > 0 ) : ?>
                            <div style="font-size:9px;color:var(--muted);margin-bottom:2px;"><?php echo number_format($hd['views']); ?></div>
                            <?php endif; ?>
                            <div style="width:100%;max-width:24px;height:<?php echo $bar_h; ?>px;background:<?php echo $color; ?>;border-radius:4px 4px 0 0;<?php echo $is_top ? 'box-shadow:0 0 8px ' . $color . '88;' : ''; ?>"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hour labels — every hour 12AM to 11PM -->
                    <div style="display:flex;gap:4px;padding-left:34px;margin-top:10px;">
                        <?php foreach ( $hour_labels as $h => $lbl ) :
                            // Highlight the major hours (every 6h) so the eye can anchor
                            $is_major = in_array( $h, [0,6,12,18], true );
                        ?>
                        <div style="flex:1;text-align:center;font-size:9px;color:<?php echo $is_major ? 'var(--text)' : 'var(--muted)'; ?>;font-weight:<?php echo $is_major ? '700' : '500'; ?>;line-height:1.2;letter-spacing:-0.3px;">
                            <?php echo esc_html( str_replace(' ','',$lbl) ); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top 5 leaderboard -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-top:16px;">
                    <?php
                    $i = 0;
                    foreach ( $ranked as $h => $hd ) :
                        if ( $i >= 5 ) break;
                        if ( $hd['views'] === 0 ) break;
                        $medals = ['🥇','🥈','🥉','4.','5.'];
                        $i++;
                    ?>
                    <div style="background:var(--card);border:1px solid var(--border);border-radius:6px;padding:8px 10px;">
                        <div style="font-size:11px;color:var(--muted);margin-bottom:2px;"><?php echo $medals[$i-1]; ?> <?php echo esc_html($hour_labels[$h]); ?></div>
                        <div style="font-size:16px;font-weight:700;"><?php echo number_format($hd['views']); ?> <span style="font-size:11px;color:var(--muted);font-weight:400;">views</span></div>
                        <div style="font-size:10px;color:var(--muted);"><?php echo number_format($hd['uniques']); ?> unique</div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else : ?>
                <div class="an-empty">No data for this period<?php echo $an_hourly_country ? ' in ' . esc_html($an_hourly_country) : ''; ?>.</div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        </div>

        <!-- ── TELEGRAM CHATBOT USERS TAB ───────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'telegram-users' ? 'active' : ''; ?>" id="panel-telegram-users">
        <?php if ( $active_tab === 'telegram-users' ) :
            global $wpdb;

            $tg_table_name = $wpdb->prefix . 'chatbot_telegram_users';
            $tg_has_table  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tg_table_name ) ) === $tg_table_name;
            $tg_users      = [];
            $tg_total      = 0;
            $tg_total_pages = 1;
            $tg_current_pg = isset( $_GET['tg_pg'] ) ? max( 1, (int) $_GET['tg_pg'] ) : 1;
            $tg_per_page   = 20;

            if ( $tg_has_table ) {
                $tg_total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tg_table_name}" );
                $tg_total_pages = max( 1, (int) ceil( $tg_total / $tg_per_page ) );
                $tg_current_pg  = min( $tg_current_pg, $tg_total_pages );
                $tg_offset      = ( $tg_current_pg - 1 ) * $tg_per_page;
                $tg_users       = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$tg_table_name} ORDER BY last_active DESC LIMIT %d OFFSET %d",
                        $tg_per_page,
                        $tg_offset
                    ),
                    ARRAY_A
                );
            }

            $tg_vip_count = 0;
            $tg_sessions  = 0;
            $tg_messages  = 0;
            $tg_cost      = 0.0;

            foreach ( $tg_users as $tg_user ) {
                $tg_vip_count += ! empty( $tg_user['is_vip'] ) ? 1 : 0;
                $tg_sessions  += (int) ( $tg_user['session_count'] ?? 0 );
                $tg_messages  += (int) ( $tg_user['message_count'] ?? 0 );
                $tg_cost      += (float) ( $tg_user['total_cost'] ?? 0 );
            }
        ?>

        <div class="ds-cards" style="margin-bottom:24px;">
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="ds-card-label">Telegram Users</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $tg_total ); ?></div>
                <div class="ds-card-sub">Tracked chatbot users</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4 0 .74.2 1.43.55 2.03L7 17h3.11c.57.36 1.25.57 1.94.57 2.21 0 4-1.79 4-4s-1.79-3.57-4-3.57zm-7-4h14a2 2 0 012 2v12a2 2 0 01-2 2h-4l-3 3-3-3H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                </div>
                <div class="ds-card-label">Messages</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $tg_messages ); ?></div>
                <div class="ds-card-sub">Messages on this page</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 8c-4.42 0-8 1.79-8 4s3.58 4 8 4 8-1.79 8-4-3.58-4-8-4zm0 6c-2.21 0-4-.45-4-1s1.79-1 4-1 4 .45 4 1-1.79 1-4 1zm0-8c3.31 0 6 1.34 6 3H6c0-1.66 2.69-3 6-3zm-6 8v3c0 1.66 2.69 3 6 3s6-1.34 6-3v-3c-1.32 1.01-3.49 1.5-6 1.5S7.32 15.01 6 14z"/></svg>
                </div>
                <div class="ds-card-label">Sessions</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $tg_sessions ); ?></div>
                <div class="ds-card-sub">Sessions on this page</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5 3.84 9.74 9 11 5.16-1.26 9-6 9-11V5l-9-4zm1 17.93V20h-2v-1.07A8.001 8.001 0 014 11V6.3l8-3.56 8 3.56V11a8.001 8.001 0 01-7 7.93zM11 7h2v5h-2zm0 6h2v2h-2z"/></svg>
                </div>
                <div class="ds-card-label">VIP Users</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $tg_vip_count ); ?></div>
                <div class="ds-card-sub">On this page</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 1a9 9 0 100 18 9 9 0 000-18zm1 14.93V17h-2v-1.09A4.002 4.002 0 018 12h2a2 2 0 104 0c0-1.1-.9-2-2-2a4 4 0 110-8V1h2v1.07A4.002 4.002 0 0116 6h-2a2 2 0 10-4 0c0 1.1.9 2 2 2a4 4 0 110 8z"/></svg>
                </div>
                <div class="ds-card-label">API Total Cost</div>
                <div class="ds-card-value">$<?php echo esc_html( number_format( $tg_cost, 4 ) ); ?></div>
                <div class="ds-card-sub">Users on this page</div>
            </div>
        </div>

        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Telegram Chatbot Users <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( $tg_total ); ?> users</span></span>
                <span style="font-size:12px;color:var(--muted);">Page cost: $<?php echo esc_html( number_format( $tg_cost, 4 ) ); ?></span>
            </div>

            <?php if ( ! $tg_has_table ) : ?>
                <div style="padding:18px;color:var(--muted);">The Telegram chatbot users table does not exist yet.</div>
            <?php elseif ( empty( $tg_users ) ) : ?>
                <div style="padding:18px;color:var(--muted);">No Telegram chatbot users found yet.</div>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr>
                    <th>Telegram ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>First Login</th>
                    <th>Last Active</th>
                    <th>Sessions</th>
                    <th>Messages</th>
                    <th>VIP</th>
                    <th>API Cost</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $tg_users as $tg_user ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $tg_user['telegram_id'] ); ?></code></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if ( ! empty( $tg_user['photo_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $tg_user['photo_url'] ); ?>" alt="" width="34" height="34" style="border-radius:50%;object-fit:cover;">
                            <?php else : ?>
                                <div class="user-avatar"><?php echo esc_html( strtoupper( mb_substr( trim( $tg_user['first_name'] . ' ' . $tg_user['last_name'] ) ?: 'T', 0, 1 ) ) ); ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo esc_html( trim( $tg_user['first_name'] . ' ' . $tg_user['last_name'] ) ?: 'Unknown User' ); ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?php echo ! empty( $tg_user['username'] ) ? '@' . esc_html( $tg_user['username'] ) : 'No username'; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ( ! empty( $tg_user['username'] ) ) : ?>
                            <a href="<?php echo esc_url( 'https://t.me/' . rawurlencode( $tg_user['username'] ) ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $tg_user['username'] ); ?></a>
                        <?php else : ?>
                            <span style="color:var(--muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $tg_user['logged_in_at'] ?: '—' ); ?></td>
                    <td><?php echo esc_html( $tg_user['last_active'] ?: '—' ); ?></td>
                    <td><?php echo number_format_i18n( (int) ( $tg_user['session_count'] ?? 0 ) ); ?></td>
                    <td><?php echo number_format_i18n( (int) ( $tg_user['message_count'] ?? 0 ) ); ?></td>
                    <td><?php echo ! empty( $tg_user['is_vip'] ) ? 'Yes' : 'No'; ?></td>
                    <td><strong>$<?php echo esc_html( number_format( (float) ( $tg_user['total_cost'] ?? 0 ), 4 ) ); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $tg_total_pages > 1 ) : ?>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 16px;border-top:1px solid var(--border);">
                <?php if ( $tg_current_pg > 1 ) : ?>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'telegram-users', 'tg_pg' => 1 ], home_url( '/dashboard/' ) ) ); ?>">&laquo; First</a>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'telegram-users', 'tg_pg' => $tg_current_pg - 1 ], home_url( '/dashboard/' ) ) ); ?>">&lsaquo; Prev</a>
                <?php endif; ?>
                <span class="ds-tab active">Page <?php echo esc_html( $tg_current_pg ); ?> of <?php echo esc_html( $tg_total_pages ); ?></span>
                <?php if ( $tg_current_pg < $tg_total_pages ) : ?>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'telegram-users', 'tg_pg' => $tg_current_pg + 1 ], home_url( '/dashboard/' ) ) ); ?>">Next &rsaquo;</a>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'telegram-users', 'tg_pg' => $tg_total_pages ], home_url( '/dashboard/' ) ) ); ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>

        <!-- ── USERS TAB ──────────────────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="panel-users">
        <?php if ( $active_tab === 'users' ) :
            $all_users = get_users( [ 'orderby' => 'registered', 'order' => 'DESC', 'number' => 200 ] );
            $role_labels = [
                'administrator' => 'Administrator',
                'editor'        => 'Editor',
                'author'        => 'Author',
                'contributor'   => 'Contributor',
                'subscriber'    => 'Subscriber',
                'customer'      => 'Customer',
                'shop_manager'  => 'Shop Manager',
            ];

            // Summary counts
            $u_total      = count( $all_users );
            $u_admins     = 0;
            $u_active_24h = 0;
            $u_never      = 0;
            $cutoff_24h   = current_time('timestamp') - DAY_IN_SECONDS;
            foreach ( $all_users as $u ) {
                if ( in_array( 'administrator', (array) $u->roles, true ) ) $u_admins++;
                $ll = get_user_meta( $u->ID, 'shopys_last_login', true );
                if ( $ll && strtotime($ll) >= $cutoff_24h ) $u_active_24h++;
                if ( ! $ll ) $u_never++;
            }
        ?>

        <!-- Summary cards -->
        <div class="ds-cards" style="margin-bottom:24px;">
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="ds-card-label">Total Users</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $u_total ); ?></div>
                <div class="ds-card-sub">Registered accounts</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2);">
                    <svg viewBox="0 0 24 24" style="color:#f87171;"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div class="ds-card-label">Administrators</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $u_admins ); ?></div>
                <div class="ds-card-sub">Admin accounts</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="ds-card-label">Active (24h)</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $u_active_24h ); ?></div>
                <div class="ds-card-sub">Logged in last 24 hours</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon" style="background:rgba(148,163,184,.1);border-color:rgba(148,163,184,.2);">
                    <svg viewBox="0 0 24 24" style="color:#94a3b8;"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <div class="ds-card-label">Never Logged In</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $u_never ); ?></div>
                <div class="ds-card-sub">No login recorded yet</div>
            </div>
        </div>

        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>All Users <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo count($all_users); ?> accounts</span></span>
            </div>
            <?php if ( $all_users ) : ?>
            <table class="ds-table">
                <thead><tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th>Last Login</th>
                    <th>Login IP</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $all_users as $i => $u ) :
                    $last_login    = get_user_meta( $u->ID, 'shopys_last_login',    true );
                    $last_login_ip = get_user_meta( $u->ID, 'shopys_last_login_ip', true );
                    $role_key      = ! empty( $u->roles ) ? $u->roles[0] : 'other';
                    $role_label    = $role_labels[ $role_key ] ?? ucfirst( $role_key );
                    $role_class    = isset( $role_labels[ $role_key ] ) ? 'role-' . $role_key : 'role-other';

                    // Registered date
                    $reg_ts   = strtotime( $u->user_registered );
                    $reg_disp = date( 'j M Y', $reg_ts );
                    $reg_time = date( 'H:i', $reg_ts );

                    // Last login
                    if ( $last_login ) {
                        $ll_ts    = strtotime( $last_login );
                        $ll_diff  = current_time('timestamp') - $ll_ts;
                        if      ( $ll_diff < 300 )        $ll_disp = '<span class="user-online-dot"></span>Online now';
                        elseif  ( $ll_diff < 3600 )       $ll_disp = round($ll_diff/60).' min ago';
                        elseif  ( $ll_diff < 86400 )      $ll_disp = round($ll_diff/3600).' hr ago';
                        elseif  ( $ll_diff < 86400*2 )    $ll_disp = 'Yesterday';
                        else                               $ll_disp = date( 'j M Y', $ll_ts );
                        $ll_abs  = date( 'j M Y, H:i', $ll_ts );
                    } else {
                        $ll_disp = '—';
                        $ll_abs  = '';
                    }

                    // Avatar initial
                    $initial = strtoupper( mb_substr( $u->display_name ?: $u->user_login, 0, 1 ) );
                ?>
                <tr>
                    <td style="color:var(--muted);font-size:12px;"><?php echo $i + 1; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar"><?php echo esc_html($initial); ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo esc_html( $u->display_name ?: $u->user_login ); ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?php echo esc_html( $u->user_email ); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="user-role-badge <?php echo $role_class; ?>"><?php echo esc_html($role_label); ?></span></td>
                    <td style="font-size:12px;white-space:nowrap;">
                        <div><?php echo esc_html($reg_disp); ?></div>
                        <div style="color:var(--muted);font-size:11px;"><?php echo esc_html($reg_time); ?></div>
                    </td>
                    <td style="font-size:12px;white-space:nowrap;">
                        <?php if ( $last_login ) : ?>
                        <div style="color:var(--green);font-weight:600;"><?php echo $ll_disp; ?></div>
                        <div style="color:var(--muted);font-size:11px;"><?php echo esc_html($ll_abs); ?></div>
                        <?php else : ?>
                        <span style="color:var(--muted);">Never logged in</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:11px;font-family:monospace;color:var(--muted);"><?php echo esc_html( $last_login_ip ?: '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p style="padding:24px;color:var(--muted);font-size:13px;">No users found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>

    </div><!-- .ds-content -->
</div><!-- .ds-main -->

<button class="ds-mobile-toggle" onclick="document.getElementById('ds-sidebar').classList.toggle('open')">☰</button>

<script>
(function() {
    // ── Theme toggle ────────────────────────────────────────────
    var root    = document.documentElement;
    var btn     = document.getElementById('ds-theme-btn');
    var iconMoon= document.getElementById('ds-icon-moon');
    var iconSun = document.getElementById('ds-icon-sun');
    var label   = document.getElementById('ds-theme-label');

    function applyTheme(theme) {
        if (theme === 'light') {
            root.classList.add('light');
            iconMoon.style.display = 'none';
            iconSun.style.display  = 'block';
            label.textContent = 'Dark';
        } else {
            root.classList.remove('light');
            iconMoon.style.display = 'block';
            iconSun.style.display  = 'none';
            label.textContent = 'Light';
        }
    }

    // Sync button state with current theme on load
    applyTheme(localStorage.getItem('ds_theme') || 'dark');

    btn.addEventListener('click', function() {
        var isLight = root.classList.contains('light');
        var next    = isLight ? 'dark' : 'light';
        localStorage.setItem('ds_theme', next);
        applyTheme(next);
    });

    // ── Close sidebar on mobile when clicking a nav link ────────
    document.querySelectorAll('.ds-nav-item').forEach(function(el) {
        el.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                document.getElementById('ds-sidebar').classList.remove('open');
            }
        });
    });
})();
</script>

</body>
</html>
