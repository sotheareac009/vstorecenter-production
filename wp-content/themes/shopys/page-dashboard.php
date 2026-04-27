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

$current_user = wp_get_current_user();
$active_tab   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

// ── Collect Site-View data (safe even if view-counter isn't loaded) ───────────
$has_vc = function_exists( 'shopys_vc_count_views' );

if ( $has_vc ) {
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
    $top_pages       = shopys_vc_top_pages( $week_start, 10 );
    $series          = shopys_vc_daily_series( 14 );
    $max_views       = 1;
    foreach ( $series as $row ) {
        if ( $row['views'] > $max_views ) $max_views = $row['views'];
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
    'products'  => [ 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Products', 'href' => admin_url( 'edit.php?post_type=product' ) ],
    'orders'    => [ 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', 'label' => 'Orders', 'href' => admin_url( 'edit.php?post_type=shop_order' ) ],
    'wp-admin'  => [ 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'WP Admin', 'href' => admin_url() ],
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

/* ── THEME TOGGLE BUTTON ──────────────────────────────────────── */
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

            <!-- Top pages table -->
            <div class="ds-table-wrap">
                <div class="ds-table-head">Top Pages — Last 7 days</div>
                <?php if ( $top_pages ) : ?>
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Page</th>
                            <th>URL</th>
                            <th style="text-align:right;">Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $top_pages as $i => $row ) : ?>
                        <tr>
                            <td style="color:var(--muted);font-size:12px;"><?php echo $i + 1; ?></td>
                            <td><strong><?php echo esc_html( $row->title ?: '(untitled)' ); ?></strong></td>
                            <td><a href="<?php echo esc_url( $row->url ); ?>" target="_blank">open ↗</a></td>
                            <td class="views-count"><?php echo number_format_i18n( (int) $row->views ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="padding:24px;color:var(--muted);font-size:13px;">No views recorded yet. Visit your store in incognito mode — admin visits are excluded by default.</p>
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
