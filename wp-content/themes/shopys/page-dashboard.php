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

// Only administrators and shop managers may access the dashboard.
if ( ! array_intersect( array( 'administrator', 'shop_manager' ), (array) $current_user->roles ) ) {
    wp_safe_redirect( home_url( '/' ) );
    exit;
}

$is_site_owner  = current_user_can( 'manage_options' );
$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
// Block non-owners from accessing owner-only tabs via URL
if ( $active_tab === 'users' && ! $is_site_owner ) $active_tab = 'overview';
if ( $active_tab === 'telegram-users' && ! $is_site_owner ) $active_tab = 'overview';
if ( $active_tab === 'guest-users' && ! $is_site_owner ) $active_tab = 'overview';
if ( $active_tab === 'promotion' && ! $is_site_owner ) $active_tab = 'overview';

// ── Users → Export to Excel (CSV), respects the month filter ──────────────────
if ( $is_site_owner && isset( $_GET['export'] ) && $_GET['export'] === 'users_csv' ) {
    $ex_month = ( ! empty( $_GET['u_month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['u_month'] ) ) ? $_GET['u_month'] : '';
    $ex_day   = ( ! empty( $_GET['u_day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['u_day'] ) ) ? $_GET['u_day'] : '';
    $ex_role  = isset( $_GET['u_role'] ) ? sanitize_key( $_GET['u_role'] ) : '';
    $ex_args  = array( 'orderby' => 'registered', 'order' => 'DESC', 'number' => -1 );
    if ( $ex_day ) { // exact day wins over month
        $ex_args['date_query'] = array( array( 'column' => 'user_registered', 'after' => $ex_day . ' 00:00:00', 'before' => $ex_day . ' 23:59:59', 'inclusive' => true ) );
    } elseif ( $ex_month ) {
        $ex_start = $ex_month . '-01 00:00:00';
        $ex_end   = date( 'Y-m-t 23:59:59', strtotime( $ex_start ) );
        $ex_args['date_query'] = array( array( 'column' => 'user_registered', 'after' => $ex_start, 'before' => $ex_end, 'inclusive' => true ) );
    }
    if ( $ex_role ) $ex_args['role'] = $ex_role;
    $ex_users  = get_users( $ex_args );
    // Search across name / login / email / phone.
    $ex_search = isset( $_GET['u_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['u_search'] ) ) ) : '';
    if ( $ex_search !== '' ) {
        $ex_t = function_exists( 'mb_strtolower' ) ? mb_strtolower( $ex_search ) : strtolower( $ex_search );
        $ex_users = array_filter( $ex_users, function ( $eu ) use ( $ex_t ) {
            $ph  = get_user_meta( $eu->ID, 'billing_phone', true );
            $hay = strtolower( $eu->display_name . ' ' . $eu->user_login . ' ' . $eu->user_email . ' ' . $ph );
            return strpos( $hay, $ex_t ) !== false;
        } );
    }
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="users-' . ( $ex_role ?: 'all' ) . '-' . ( $ex_day ?: ( $ex_month ?: 'all' ) ) . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows Khmer/accents correctly
    fputcsv( $out, array( 'No', 'ID', 'Name', 'Email', 'Phone', 'Role', 'Registered', 'Last Login' ) );
    $ex_no = 0;
    foreach ( $ex_users as $eu ) {
        $ex_no++;
        $ll = get_user_meta( $eu->ID, 'shopys_last_login', true );
        fputcsv( $out, array(
            $ex_no,
            $eu->ID,
            $eu->display_name ?: $eu->user_login,
            $eu->user_email,
            get_user_meta( $eu->ID, 'billing_phone', true ),
            ! empty( $eu->roles ) ? $eu->roles[0] : '',
            $eu->user_registered,
            $ll ?: '',
        ) );
    }
    fclose( $out );
    exit;
}

// ── VIP / VVIP toggle handler ─────────────────────────────────────────────────
if (
    $is_site_owner &&
    isset( $_POST['tg_vip_action'], $_POST['tg_telegram_id'], $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'tg_vip_toggle' )
) {
    global $wpdb;
    $tg_vip_table  = $wpdb->prefix . 'chatbot_telegram_users';
    $tg_vip_id     = sanitize_text_field( wp_unslash( $_POST['tg_telegram_id'] ) );
    $tg_vip_action = sanitize_key( $_POST['tg_vip_action'] );

    $tg_tier_update = match ( $tg_vip_action ) {
        'promote'      => [ 'is_vip' => 1, 'is_vvip' => 0 ],
        'promote_vvip' => [ 'is_vip' => 0, 'is_vvip' => 1 ],
        default        => [ 'is_vip' => 0, 'is_vvip' => 0 ], // demote
    };
    $wpdb->update( $tg_vip_table, $tg_tier_update, [ 'telegram_id' => $tg_vip_id ] );
    wp_safe_redirect( add_query_arg( [ 'tab' => 'telegram-users', 'tg_pg' => max( 1, (int) ( $_POST['tg_pg'] ?? 1 ) ) ], home_url( '/dashboard/' ) ) );
    exit;
}

// ── Guest daily-limit reset handler (admin only) ──────────────────────────────
if (
    $is_site_owner &&
    isset( $_POST['g_reset_ip'], $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'g_reset_limit' )
) {
    global $wpdb;
    $g_reset_table = $wpdb->prefix . 'chatbot_guest_users';
    $g_reset_ip    = sanitize_text_field( wp_unslash( $_POST['g_reset_ip'] ) );
    $wpdb->update( $g_reset_table, [ 'daily_count' => 0 ], [ 'ip' => $g_reset_ip ] );
    wp_safe_redirect( add_query_arg( [ 'tab' => 'guest-users', 'g_pg' => max( 1, (int) ( $_POST['g_pg'] ?? 1 ) ), 'g_reset' => 1 ], home_url( '/dashboard/' ) ) );
    exit;
}

// ── Order status update handler (dashboard Cart tab: single row + bulk) ───────
if (
    isset( $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cart_order_status' ) &&
    function_exists( 'wc_get_order' ) &&
    ( isset( $_POST['co_row_update'] ) || ( isset( $_POST['co_do'] ) && $_POST['co_do'] === 'bulk' ) )
) {
    $co_valid = wc_get_order_statuses(); // 'wc-*' => label
    $co_apply = function ( $oid, $status ) use ( $co_valid ) {
        $status = sanitize_key( $status );
        if ( $status === '' || ! array_key_exists( 'wc-' . $status, $co_valid ) ) return false;
        $o = wc_get_order( (int) $oid );
        if ( ! $o ) return false;
        if ( $o->get_status() !== $status ) {
            $o->update_status( $status, __( 'Status changed from dashboard.', 'shopys' ), true );
        }
        return true;
    };

    $co_done = 0;
    if ( isset( $_POST['co_row_update'] ) ) {
        // Single-row update
        $oid    = (int) $_POST['co_row_update'];
        $rowmap = ( isset( $_POST['co_row_status'] ) && is_array( $_POST['co_row_status'] ) ) ? $_POST['co_row_status'] : [];
        $status = isset( $rowmap[ $oid ] ) ? wp_unslash( $rowmap[ $oid ] ) : '';
        if ( $co_apply( $oid, $status ) ) $co_done = 1;
    } else {
        // Bulk update on the checked orders
        $bulk_status = isset( $_POST['co_bulk_status'] ) ? wp_unslash( $_POST['co_bulk_status'] ) : '';
        $ids         = ( isset( $_POST['co_ids'] ) && is_array( $_POST['co_ids'] ) ) ? array_map( 'intval', $_POST['co_ids'] ) : [];
        foreach ( $ids as $oid ) {
            if ( $co_apply( $oid, $bulk_status ) ) $co_done++;
        }
    }

    $co_ret_month = ( ! empty( $_POST['co_ret_month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_POST['co_ret_month'] ) ) ? $_POST['co_ret_month'] : '';
    $co_ret_day   = ( ! empty( $_POST['co_ret_day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['co_ret_day'] ) ) ? $_POST['co_ret_day'] : '';
    wp_safe_redirect( add_query_arg( array_filter( [
        'tab'        => 'cart',
        'co_pg'      => max( 1, (int) ( $_POST['co_pg'] ?? 1 ) ),
        'co_status'  => sanitize_key( wp_unslash( $_POST['co_ret_status'] ?? '' ) ),
        'co_month'   => $co_ret_month,
        'co_day'     => $co_ret_day,
        'co_search'  => sanitize_text_field( wp_unslash( $_POST['co_ret_search'] ?? '' ) ),
        'co_updated' => $co_done,
    ], 'strlen' ), home_url( '/dashboard/' ) ) );
    exit;
}

// ── Promotion save / delete handlers (owner only, multiple promotions) ────────
if (
    $is_site_owner &&
    isset( $_POST['catpromo_action'], $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'catpromo_save' )
) {
    $cp_action = sanitize_key( $_POST['catpromo_action'] );
    $cp_list   = function_exists( 'shopys_promos_all' ) ? shopys_promos_all() : ( is_array( get_option( 'shopys_cat_promos' ) ) ? get_option( 'shopys_cat_promos' ) : [] );
    $cp_id     = isset( $_POST['cp_id'] ) ? sanitize_key( wp_unslash( $_POST['cp_id'] ) ) : '';
    $cp_flag   = 'promo_saved';

    if ( $cp_action === 'delete' && $cp_id !== '' ) {
        $cp_list = array_values( array_filter( $cp_list, function ( $p ) use ( $cp_id ) {
            return ( $p['id'] ?? '' ) !== $cp_id;
        } ) );
        $cp_flag = 'promo_deleted';
    } elseif ( $cp_action === 'save' ) {
        // Definition only (name / type / value / dates / enabled) — targets are set separately.
        $cp_dtype = ( isset( $_POST['cp_dtype'] ) && $_POST['cp_dtype'] === 'fixed' ) ? 'fixed' : 'percent';
        $cp_val   = isset( $_POST['cp_value'] ) ? (float) $_POST['cp_value'] : 0;
        $cp_val   = ( $cp_dtype === 'percent' ) ? max( 0, min( 95, $cp_val ) ) : max( 0, min( 99999, $cp_val ) );
        $cp_start = ( ! empty( $_POST['cp_start'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['cp_start'] ) ) ? $_POST['cp_start'] : '';
        $cp_end   = ( ! empty( $_POST['cp_end'] )   && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['cp_end'] ) )   ? $_POST['cp_end']   : '';
        $cp_entry = [
            'id'       => $cp_id !== '' ? $cp_id : uniqid( 'p' ),
            'name'     => sanitize_text_field( wp_unslash( $_POST['cp_name'] ?? '' ) ),
            'enabled'  => ! empty( $_POST['cp_enabled'] ),
            'dtype'    => $cp_dtype,
            'value'    => $cp_val,
            'cats'     => [],
            'products' => [],
            'start'    => $cp_start,
            'end'      => $cp_end,
        ];
        if ( $cp_entry['name'] === '' ) $cp_entry['name'] = 'Promotion';
        $cp_replaced = false;
        foreach ( $cp_list as $i => $p ) {
            if ( ( $p['id'] ?? '' ) === $cp_entry['id'] ) {
                // Keep the promotion's existing targets when editing its definition.
                $cp_entry['cats']     = array_map( 'intval', (array) ( $p['cats'] ?? [] ) );
                $cp_entry['products'] = array_map( 'intval', (array) ( $p['products'] ?? [] ) );
                $cp_list[ $i ] = $cp_entry;
                $cp_replaced   = true;
                break;
            }
        }
        if ( ! $cp_replaced ) $cp_list[] = $cp_entry;
    } elseif ( $cp_action === 'targets' && $cp_id !== '' ) {
        // Assign categories / products to an existing promotion.
        $cp_cats  = ( isset( $_POST['cp_cats'] ) && is_array( $_POST['cp_cats'] ) ) ? array_map( 'intval', $_POST['cp_cats'] ) : [];
        $cp_valid = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ] );
        $cp_cats  = array_values( array_intersect( $cp_cats, array_map( 'intval', (array) $cp_valid ) ) );
        $cp_prods = ( isset( $_POST['cp_prods'] ) && is_array( $_POST['cp_prods'] ) ) ? array_map( 'intval', $_POST['cp_prods'] ) : [];
        $cp_prods = array_values( array_filter( array_unique( $cp_prods ), function ( $id ) {
            return $id > 0 && get_post_type( $id ) === 'product';
        } ) );
        foreach ( $cp_list as $i => $p ) {
            if ( ( $p['id'] ?? '' ) === $cp_id ) {
                $cp_list[ $i ]['cats']     = $cp_cats;
                $cp_list[ $i ]['products'] = $cp_prods;
                break;
            }
        }
    }

    update_option( 'shopys_cat_promos', $cp_list, false );
    // Prices changed everywhere — clear WooCommerce + page caches.
    if ( function_exists( 'wc_delete_product_transients' ) ) wc_delete_product_transients();
    delete_transient( 'wc_products_onsale' );
    do_action( 'litespeed_purge_all' );
    wp_safe_redirect( add_query_arg( [ 'tab' => 'promotion', $cp_flag => 1 ], home_url( '/dashboard/' ) ) );
    exit;
}

// ── Home discount banner save handler (owner only) ────────────────────────────
if (
    $is_site_owner &&
    isset( $_POST['promo_banner_save'], $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'promo_banner_save' )
) {
    $db_color   = isset( $_POST['db_color'] ) ? sanitize_key( $_POST['db_color'] ) : 'dark';
    $db_presets = function_exists( 'shopys_disc_banner_presets' ) ? array_keys( shopys_disc_banner_presets() ) : [ 'dark' ];
    if ( ! in_array( $db_color, $db_presets, true ) ) $db_color = 'dark';
    update_option( 'shopys_discount_banner', [
        'enabled' => ! empty( $_POST['db_enabled'] ),
        'text'    => sanitize_text_field( wp_unslash( $_POST['db_text'] ?? '' ) ),
        'link'    => esc_url_raw( wp_unslash( $_POST['db_link'] ?? '' ) ),
        'btn'     => sanitize_text_field( wp_unslash( $_POST['db_btn'] ?? '' ) ),
        'color'   => $db_color,
        'bgc'     => sanitize_hex_color( wp_unslash( $_POST['db_bgc'] ?? '' ) ) ?: '#0b0f14',
        'acc'     => sanitize_hex_color( wp_unslash( $_POST['db_acc'] ?? '' ) ) ?: '#13e800',
    ], false );
    do_action( 'litespeed_purge_all' );
    wp_safe_redirect( add_query_arg( [ 'tab' => 'promotion', 'banner_saved' => 1 ], home_url( '/dashboard/' ) ) );
    exit;
}

// ── Checkout settings save handler (owner only) ────────────────────────────────
if (
    $is_site_owner &&
    isset( $_POST['checkout_settings_save'], $_POST['_wpnonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'checkout_settings_save' )
) {
    update_option( 'shopys_checkout_settings', [
        'hide_cod' => ! empty( $_POST['cs_hide_cod'] ),
    ], false );
    do_action( 'litespeed_purge_all' );
    wp_safe_redirect( add_query_arg( [ 'tab' => 'promotion', 'checkout_settings_saved' => 1 ], home_url( '/dashboard/' ) ) );
    exit;
}

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
$sv_view    = 'all';
if ( isset( $_GET['sv_view'] ) ) {
    $v = $_GET['sv_view'];
    if ( $v === 'week' ) $sv_view = 'week';
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

// ── Top Customers (aggregated from WC Analytics lookup tables) ────────────────
$top_customers = [];
$tc_statuses   = [];
$tc_months     = [];
$top_sort      = ( isset( $_GET['top_sort'] ) && $_GET['top_sort'] === 'orders' ) ? 'orders' : 'spent';
$top_status    = isset( $_GET['top_status'] ) ? sanitize_text_field( wp_unslash( $_GET['top_status'] ) ) : 'wc-completed';
$top_month     = ( ! empty( $_GET['top_month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['top_month'] ) ) ? $_GET['top_month'] : '';
$top_search    = isset( $_GET['top_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['top_search'] ) ) ) : '';
if ( class_exists( 'WooCommerce' ) ) {
    global $wpdb;
    $tc_os       = $wpdb->prefix . 'wc_order_stats';
    $tc_cl       = $wpdb->prefix . 'wc_customer_lookup';
    $tc_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [];
    // Validate the requested status against the known list (allow "all").
    if ( $top_status !== 'all' && ! isset( $tc_statuses[ $top_status ] ) ) $top_status = 'wc-completed';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tc_os}'" ) && $wpdb->get_var( "SHOW TABLES LIKE '{$tc_cl}'" ) ) {
        // Months that actually have orders, for the dropdown.
        $tc_months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(date_created,'%Y-%m') AS ym FROM {$tc_os} ORDER BY ym DESC" );

        // Build the WHERE clause (status + month + search) safely.
        $tc_where = [];
        if ( $top_status !== 'all' ) {
            $tc_where[] = $wpdb->prepare( 'o.status = %s', $top_status );
        }
        if ( $top_month ) {
            $m_start    = $top_month . '-01 00:00:00';
            $m_end      = date( 'Y-m-d H:i:s', strtotime( $m_start . ' +1 month' ) );
            $tc_where[] = $wpdb->prepare( 'o.date_created >= %s AND o.date_created < %s', $m_start, $m_end );
        }
        if ( $top_search !== '' ) {
            $tc_like = '%' . $wpdb->esc_like( $top_search ) . '%';
            $tc_where[] = $wpdb->prepare( '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.username LIKE %s)', $tc_like, $tc_like, $tc_like, $tc_like );
        }
        $tc_where_sql = $tc_where ? ( 'WHERE ' . implode( ' AND ', $tc_where ) ) : '';
        $tc_order = $top_sort === 'orders' ? 'orders DESC, spent DESC' : 'spent DESC, orders DESC';
        $tc_select = "SELECT o.customer_id, c.first_name, c.last_name, c.email, c.username, c.user_id, c.country, c.city,
                    COUNT(*) AS orders, SUM(o.total_sales) AS spent, MAX(o.date_created) AS last_order
             FROM {$tc_os} o LEFT JOIN {$tc_cl} c ON c.customer_id = o.customer_id
             {$tc_where_sql} GROUP BY o.customer_id ORDER BY {$tc_order}";

        // Export to Excel (CSV) — respects status / month / search / sort.
        if ( $is_site_owner && isset( $_GET['export'] ) && $_GET['export'] === 'topcust_csv' ) {
            $rows = $wpdb->get_results( $tc_select . ' LIMIT 5000' );
            $sym  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="top-customers-' . ( $top_status === 'all' ? 'all' : preg_replace( '/^wc-/', '', $top_status ) ) . '-' . ( $top_month ?: 'all' ) . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            fwrite( $out, "\xEF\xBB\xBF" );
            fputcsv( $out, array( 'No', 'Customer', 'Email', 'Orders', 'Total Spent', 'Location', 'Last Order' ) );
            $no = 0;
            foreach ( $rows as $r ) {
                $no++;
                $cname = trim( $r->first_name . ' ' . $r->last_name );
                if ( $cname === '' ) $cname = $r->username ?: ( $r->email ?: 'Customer #' . $r->customer_id );
                $loc = trim( ( $r->city ?: '' ) . ( $r->country ? ( $r->city ? ', ' : '' ) . $r->country : '' ) );
                fputcsv( $out, array( $no, $cname, $r->email, (int) $r->orders, $sym . number_format( (float) $r->spent, 2 ), $loc, ( $r->last_order ? date( 'Y-m-d', strtotime( $r->last_order ) ) : '' ) ) );
            }
            fclose( $out );
            exit;
        }

        $top_customers = $wpdb->get_results( $tc_select . ' LIMIT 50' );
    }
}
$tc_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

$logo_id  = get_theme_mod( 'custom_logo' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

// ── Sidebar menu definition ───────────────────────────────────────────────────
$menu_items = [
    'overview'  => [ 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Overview' ],
    'siteview'  => [ 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'label' => 'Site View' ],
    'analytics' => [ 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Analytics' ],
    'users'     => [ 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Users', 'owner_only' => true ],
    'guest-users'    => [ 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'label' => 'Chatbot Users', 'owner_only' => true ],
    'top-customers'  => [ 'icon' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z', 'label' => 'Top Customers' ],
    'cart'      => [ 'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Orders' ],
    'promotion' => [ 'icon' => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z', 'label' => 'Promotion', 'owner_only' => true ],
    // 'products'  => [ 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Products', 'href' => admin_url( 'edit.php?post_type=product' ) ],
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

    /* Filter/action bars inside table cards: the card clips overflow (rounded
       corners), and flex children don't shrink below their content width by
       default — together that silently cuts off controls with no way to
       scroll to them. Stack everything full-width instead. */
    .ds-table-head { flex-direction: column; align-items: stretch !important; }
    .ds-table-head > div { width: 100%; }
    .ds-table-head form { flex-wrap: wrap; width: 100%; }
    .ds-table-head form > * { min-width: 0; flex: 1 1 auto; }
    .ds-table-head form input[type=search] { flex-basis: 100%; }
    .ds-table-head .ds-store-btn { width: 100%; justify-content: center; }

    /* Same treatment for the standalone filter bars above a table (Analytics,
       Top Customers, etc.) — every filter on the dashboard behaves the same way. */
    .sv-filter { flex-direction: column; align-items: stretch; }
    .sv-filter input[type=search],
    .sv-filter input[type=date],
    .sv-filter select { width: 100%; box-sizing: border-box; }
    .sv-filter label { margin: 4px 0 -4px; }
    .sv-filter-btn { width: 100%; }
    .sv-filter a.an-period-btn { flex: 1; text-align: center; }

    /* Tables (Users, Orders, Top Customers, etc.) are wider than a phone
       screen — let each table scroll horizontally on its own, independent of
       the card/header above it, instead of squeezing or wrapping columns. */
    .ds-table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ds-table th, .ds-table td { white-space: nowrap; }
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

/* ── ANALYTICS: Section title with inline form (desktop) ── */
.an-title-with-form {
    flex-wrap: wrap;
    column-gap: 8px;
    row-gap: 6px;
}
.an-title-text { font-size: 14px; font-weight: 700; color: var(--text); }
.an-title-sub  { color: var(--muted); font-weight: 400; font-size: 12px; }
.an-title-form {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    font-size: 12px;
    font-weight: 400;
}
.an-title-form label { color: var(--muted); }
.an-title-form select {
    background: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 12px;
    cursor: pointer;
}
.an-title-form select:focus { border-color: var(--green); outline: none; }
.an-title-clear { color: var(--muted); font-size: 11px; text-decoration: none; }

/* ── ANALYTICS: Peak Hours bar chart (desktop) ── */
.an-peak-chart {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px 12px;
}
.an-peak-bars {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 200px;
    padding-left: 34px;
    position: relative;
}
.an-peak-yaxis {
    position: absolute;
    inset: 0 0 0 34px;
    pointer-events: none;
}
.an-peak-grid {
    position: absolute;
    left: 0; right: 0;
    border-top: 1px dashed rgba(255,255,255,.06);
}
.an-peak-yval {
    position: absolute;
    left: -32px;
    font-size: 10px;
    color: var(--muted);
    width: 28px;
    text-align: right;
}
.an-peak-bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    cursor: default;
}
.an-peak-rank {
    font-size: 10px;
    font-weight: 700;
    line-height: 1.1;
    text-align: center;
    margin-bottom: 2px;
    white-space: nowrap;
}
.an-peak-rank-views { font-size: 11px; font-weight: 800; }
.an-peak-views {
    font-size: 9px;
    color: var(--muted);
    margin-bottom: 2px;
}
.an-peak-bar {
    width: 100%;
    max-width: 24px;
    border-radius: 4px 4px 0 0;
    transition: height .25s;
}
.an-peak-labels {
    display: flex;
    gap: 4px;
    padding-left: 34px;
    margin-top: 10px;
}
.an-peak-label {
    flex: 1;
    text-align: center;
    font-size: 9px;
    color: var(--muted);
    font-weight: 500;
    line-height: 1.2;
    letter-spacing: -0.3px;
}
.an-peak-label.major { color: var(--text); font-weight: 700; }

/* ── ANALYTICS: Mobile / tablet (≤768px) ───────────────────────── */
@media (max-width: 768px) {
    /* Tighten chart wrapper padding */
    .ds-chart-wrap { padding: 14px 12px; margin-bottom: 16px; }
    .an-section { margin-bottom: 20px; }

    /* Section title: stack form to a new full-width line */
    .an-section-title { flex-wrap: wrap; row-gap: 8px; column-gap: 6px; }
    .an-section-title form,
    .an-title-form {
        margin-left: 0 !important;
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    /* Filter bar: keep period buttons compact and wrappable */
    .an-filter-bar { gap: 6px; }
    .an-period-btn { padding: 6px 10px; font-size: 11px; }
    .an-date-inputs { margin-left: 0; flex-wrap: wrap; width: 100%; }

    /* Most Viewed / Traffic hbar rows: stack label above the track */
    .an-hbar-row { flex-wrap: wrap; row-gap: 4px; column-gap: 8px; }
    .an-hbar-label {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        flex: 1 1 100%;
    }
    .an-hbar-track { flex: 1 1 auto; min-width: 0; height: 18px; }
    .an-hbar-count { min-width: 30px; }
    .an-hbar-uniq  { min-width: 50px; font-size: 10px; }

    /* Peak Hours: shrink chart to fit small screens */
    .an-peak-bars {
        gap: 2px;
        height: 170px;
        padding-left: 28px;
    }
    .an-peak-yaxis  { inset: 0 0 0 28px; }
    .an-peak-yval   { left: -28px; width: 24px; font-size: 9px; }
    .an-peak-bar    { max-width: 16px; border-radius: 3px 3px 0 0; }
    .an-peak-rank   { font-size: 8px; }
    .an-peak-rank-views { font-size: 9px; }
    .an-peak-views  { font-size: 8px; }
    .an-peak-labels { padding-left: 28px; gap: 2px; }
    .an-peak-label  { font-size: 8px; }
    .an-peak-label.minor { display: none; } /* show only major hours */
    /* Re-spread space when minor labels are hidden so majors align with their bars */
    .an-peak-label.major { flex: 0 0 calc((100% - 16px) / 4); }

    /* Period summary cards: 2-up grid on mobile */
    .an-peak-periods {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    .an-peak-leaderboard {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 6px !important;
    }
}

/* ── ANALYTICS: Small phones (≤480px) ──────────────────────────── */
@media (max-width: 480px) {
    .ds-chart-wrap { padding: 12px 10px; }
    .an-peak-bars { height: 150px; padding-left: 24px; gap: 1px; }
    .an-peak-yaxis { inset: 0 0 0 24px; }
    .an-peak-yval  { left: -24px; width: 22px; font-size: 8px; }
    .an-peak-bar   { max-width: 14px; }
    .an-peak-labels { padding-left: 24px; gap: 1px; }
    .an-peak-label.major { font-size: 8px; }
    /* Period cards: still 2-up but tighter */
    .an-peak-periods { gap: 6px !important; }
    .an-peak-leaderboard { grid-template-columns: 1fr 1fr !important; }
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
/* ── Telegram VIP styles ───────────────────────────────────────── */
.tg-vip-badge {
    display: inline-block;
    margin-left: 5px;
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    background: rgba(250,204,21,.18);
    color: #facc15;
    letter-spacing: .3px;
    vertical-align: middle;
}
.tg-vip-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(250,204,21,.15);
    color: #facc15;
}
.tg-row-vip td { background: rgba(250,204,21,.04); }
.tg-row-vip:hover td { background: rgba(250,204,21,.08) !important; }
.tg-vvip-badge {
    display: inline-block;
    margin-left: 5px;
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    background: rgba(167,139,250,.2);
    color: #c4b5fd;
    letter-spacing: .3px;
    vertical-align: middle;
}
.tg-vvip-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(167,139,250,.15);
    color: #c4b5fd;
}
.tg-row-vvip td { background: rgba(167,139,250,.04); }
.tg-row-vvip:hover td { background: rgba(167,139,250,.08) !important; }
.tg-vvip-btn-add {
    background: rgba(167,139,250,.18);
    color: #c4b5fd;
}
.tg-vip-btn {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity .15s;
}
.tg-vip-btn:hover { opacity: .8; }
.tg-vip-btn-add {
    background: rgba(250,204,21,.18);
    color: #facc15;
}
.tg-vip-btn-remove {
    background: rgba(148,163,184,.13);
    color: var(--muted);
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
            <form id="sv-filter-form" class="sv-filter" method="GET" action="<?php echo esc_url(home_url('/dashboard/')); ?>">
                <input type="hidden" name="tab" value="siteview">
                <input type="hidden" name="sv_view" value="all">
                <label>Month</label>
                <select id="sv_month_sel" name="sv_month" data-group="period">
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
                <select id="sv_year_sel" name="sv_year" data-group="period">
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
                <input id="sv_date_inp" type="date" name="sv_date" value="<?php echo esc_attr( $sv_date ); ?>"
                       style="background:var(--card);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:5px 8px;font-size:13px;"
                       title="Filter by exact date — when set, Month and Year are ignored">
                <?php if ( $sv_date ) : ?>
                <button type="button" id="sv_date_clear" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;padding:0 4px;" title="Clear date">✕</button>
                <?php endif; ?>
                <button type="submit" class="sv-filter-btn">Filter</button>
                <span id="sv_filter_hint" style="display:none;font-size:11px;color:var(--muted);margin-left:8px;font-style:italic;">Date is active — Month/Year are ignored</span>
            </form>

            <script>
            (function(){
                var form  = document.getElementById('sv-filter-form');
                if ( ! form ) return;
                var dateI = document.getElementById('sv_date_inp');
                var monS  = document.getElementById('sv_month_sel');
                var yrS   = document.getElementById('sv_year_sel');
                var hint  = document.getElementById('sv_filter_hint');
                var clr   = document.getElementById('sv_date_clear');

                function syncState() {
                    var hasDate = !!dateI.value;
                    [monS, yrS].forEach(function(el){
                        el.disabled = hasDate;
                        el.style.opacity = hasDate ? '0.4' : '';
                        el.style.cursor  = hasDate ? 'not-allowed' : '';
                    });
                    hint.style.display = hasDate ? 'inline' : 'none';
                }

                // Date change → if value entered, reset Month/Year to "all" so URL stays clean on submit
                dateI.addEventListener('change', function(){
                    if ( dateI.value ) {
                        monS.value = '0';
                        yrS.value  = '0';
                    }
                    syncState();
                });

                // Month/Year change → clear Date so it doesn't override
                [monS, yrS].forEach(function(el){
                    el.addEventListener('change', function(){
                        if ( dateI.value ) dateI.value = '';
                        syncState();
                    });
                });

                // Clear button → wipe date and re-enable period selects
                if ( clr ) {
                    clr.addEventListener('click', function(){
                        dateI.value = '';
                        syncState();
                        form.submit();
                    });
                }

                syncState();
            })();
            </script>

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
            <div class="ds-chart-wrap an-section an-peak">
                <div class="an-section-title an-title-with-form">
                    <span class="an-title-text">Peak Hours</span>
                    <span class="an-title-sub">When visitors are most active</span>
                    <form method="get" class="an-title-form">
                        <?php
                        foreach ( $_GET as $k => $v ) {
                            if ( $k === 'an_hourly_country' ) continue;
                            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                        }
                        ?>
                        <label>Country:</label>
                        <select name="an_hourly_country" onchange="this.form.submit()">
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
                        <a href="<?php echo esc_url( add_query_arg('an_hourly_country','',remove_query_arg('an_hourly_country')) ); ?>" class="an-title-clear" title="Clear">✕</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ( $an_hourly_total > 0 ) : ?>

                <!-- Time Period Summary Cards -->
                <div class="an-peak-periods" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:24px;">
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
                <div class="an-peak-chart">
                    <div class="an-peak-bars">

                        <!-- Y-axis grid lines -->
                        <div class="an-peak-yaxis">
                            <?php for ( $i = 0; $i <= 4; $i++ ) :
                                $val = round( $an_hourly_max * ( 4 - $i ) / 4 );
                            ?>
                            <div class="an-peak-grid" style="top:<?php echo $i * 25; ?>%;"></div>
                            <div class="an-peak-yval" style="top:calc(<?php echo $i * 25; ?>% - 6px);"><?php echo number_format($val); ?></div>
                            <?php endfor; ?>
                        </div>

                        <?php foreach ( $an_hourly as $h => $hd ) :
                            $bar_h   = $hd['views'] > 0 ? max( 3, round( ($hd['views'] / $an_hourly_max) * 180 ) ) : 0;
                            $is_top  = isset( $rank_color[$h] );
                            $rank_n  = array_search( $h, $top_hours, true );
                            $color   = $is_top ? $rank_color[$h] : 'rgba(99,179,237,.45)';
                            $title   = $hour_labels[$h] . ': ' . number_format($hd['views']) . ' views, ' . number_format($hd['uniques']) . ' unique';
                        ?>
                        <div class="an-peak-bar-col" title="<?php echo esc_attr($title); ?>">
                            <?php if ( $is_top && $hd['views'] > 0 ) : ?>
                            <div class="an-peak-rank" style="color:<?php echo $color; ?>;">
                                #<?php echo $rank_n + 1; ?>
                                <div class="an-peak-rank-views" style="color:<?php echo $color; ?>;"><?php echo number_format($hd['views']); ?></div>
                            </div>
                            <?php elseif ( $hd['views'] > 0 ) : ?>
                            <div class="an-peak-views"><?php echo number_format($hd['views']); ?></div>
                            <?php endif; ?>
                            <div class="an-peak-bar" style="height:<?php echo $bar_h; ?>px;background:<?php echo $color; ?>;<?php echo $is_top ? 'box-shadow:0 0 8px ' . $color . '88;' : ''; ?>"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hour labels — every hour 12AM to 11PM -->
                    <div class="an-peak-labels">
                        <?php foreach ( $hour_labels as $h => $lbl ) :
                            // Highlight the major hours (every 6h) so the eye can anchor
                            $is_major = in_array( $h, [0,6,12,18], true );
                        ?>
                        <div class="an-peak-label <?php echo $is_major ? 'major' : 'minor'; ?>">
                            <?php echo esc_html( str_replace(' ','',$lbl) ); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top 5 leaderboard -->
                <div class="an-peak-leaderboard" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-top:16px;">
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

        <?php endif; ?>
        </div>

        <!-- ── TELEGRAM CHATBOT USERS TAB ───────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'telegram-users' ? 'active' : ''; ?>" id="panel-telegram-users">
        <?php if ( $active_tab === 'telegram-users' ) :
            global $wpdb;

            // Ensure schema (is_vvip column) exists when reaching this tab from frontend
            if ( function_exists( 'shopys_ai_create_tg_table' ) ) {
                shopys_ai_create_tg_table();
            }

            $tg_table_name = $wpdb->prefix . 'chatbot_telegram_users';
            $tg_has_table  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tg_table_name ) ) === $tg_table_name;
            $tg_users      = [];
            $tg_total      = 0;
            $tg_total_pages = 1;
            $tg_current_pg = isset( $_GET['tg_pg'] ) ? max( 1, (int) $_GET['tg_pg'] ) : 1;
            $tg_per_page   = 20;
            $tg_has_vvip_col = false;

            if ( $tg_has_table ) {
                $tg_has_vvip_col = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SHOW COLUMNS FROM {$tg_table_name} LIKE %s", 'is_vvip'
                ) );
                $tg_total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tg_table_name}" );
                $tg_vip_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tg_table_name} WHERE is_vip = 1" );
                $tg_vvip_count  = $tg_has_vvip_col ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tg_table_name} WHERE is_vvip = 1" ) : 0;
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

            $tg_vip_count  = $tg_vip_count  ?? 0;
            $tg_vvip_count = $tg_vvip_count ?? 0;
            $tg_sessions  = 0;
            $tg_messages  = 0;
            $tg_cost      = 0.0;

            foreach ( $tg_users as $tg_user ) {
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
                <div class="ds-card-sub">30 chats/day</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                </div>
                <div class="ds-card-label">VVIP Users</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $tg_vvip_count ); ?></div>
                <div class="ds-card-sub">Unlimited chats/day</div>
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
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $tg_users as $tg_user ) :
                    $tg_is_vvip = ! empty( $tg_user['is_vvip'] );
                    $tg_is_vip  = ! $tg_is_vvip && ! empty( $tg_user['is_vip'] );
                    $tg_tier    = $tg_is_vvip ? 'vvip' : ( $tg_is_vip ? 'vip' : 'none' );
                ?>
                <tr class="<?php echo $tg_is_vvip ? 'tg-row-vvip' : ( $tg_is_vip ? 'tg-row-vip' : '' ); ?>">
                    <td><code><?php echo esc_html( $tg_user['telegram_id'] ); ?></code></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if ( ! empty( $tg_user['photo_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $tg_user['photo_url'] ); ?>" alt="" width="34" height="34" style="border-radius:50%;object-fit:cover;">
                            <?php else : ?>
                                <div class="user-avatar"><?php echo esc_html( strtoupper( mb_substr( trim( $tg_user['first_name'] . ' ' . $tg_user['last_name'] ) ?: 'T', 0, 1 ) ) ); ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;font-size:13px;">
                                    <?php echo esc_html( trim( $tg_user['first_name'] . ' ' . $tg_user['last_name'] ) ?: 'Unknown User' ); ?>
                                    <?php if ( $tg_is_vvip ) : ?><span class="tg-vvip-badge">★★ VVIP</span>
                                    <?php elseif ( $tg_is_vip ) : ?><span class="tg-vip-badge">★ VIP</span><?php endif; ?>
                                </div>
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
                    <td>
                        <?php if ( $tg_is_vvip ) : ?>
                            <span class="tg-vvip-pill">★★ VVIP</span>
                        <?php elseif ( $tg_is_vip ) : ?>
                            <span class="tg-vip-pill">★ VIP</span>
                        <?php else : ?>
                            <span style="color:var(--muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><strong>$<?php echo esc_html( number_format( (float) ( $tg_user['total_cost'] ?? 0 ), 4 ) ); ?></strong></td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:4px;">
                        <?php if ( $tg_is_vvip ) : ?>
                            <?php /* VVIP → downgrade to VIP or remove */ ?>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="promote">
                                <button type="submit" class="tg-vip-btn tg-vip-btn-add">↓ To VIP</button>
                            </form>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="demote">
                                <button type="submit" class="tg-vip-btn tg-vip-btn-remove">Remove</button>
                            </form>
                        <?php elseif ( $tg_is_vip ) : ?>
                            <?php /* VIP → upgrade to VVIP or remove */ ?>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="promote_vvip">
                                <button type="submit" class="tg-vip-btn tg-vvip-btn-add">↑ VVIP</button>
                            </form>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="demote">
                                <button type="submit" class="tg-vip-btn tg-vip-btn-remove">Remove VIP</button>
                            </form>
                        <?php else : ?>
                            <?php /* Regular → set VIP or VVIP */ ?>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="promote">
                                <button type="submit" class="tg-vip-btn tg-vip-btn-add">★ Set VIP</button>
                            </form>
                            <form method="post" style="margin:0;"><?php wp_nonce_field( 'tg_vip_toggle' ); ?>
                                <input type="hidden" name="tg_telegram_id" value="<?php echo esc_attr( $tg_user['telegram_id'] ); ?>">
                                <input type="hidden" name="tg_pg" value="<?php echo esc_attr( $tg_current_pg ); ?>">
                                <input type="hidden" name="tg_vip_action" value="promote_vvip">
                                <button type="submit" class="tg-vip-btn tg-vvip-btn-add">★★ Set VVIP</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
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

        <!-- ── GUEST CHAT USERS TAB ───────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'guest-users' ? 'active' : ''; ?>" id="panel-guest-users">
        <?php if ( $active_tab === 'guest-users' ) :
            global $wpdb;

            if ( function_exists( 'shopys_ai_create_guest_table' ) ) {
                shopys_ai_create_guest_table();
            }

            $g_table_name  = $wpdb->prefix . 'chatbot_guest_users';
            $g_has_table   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $g_table_name ) ) === $g_table_name;
            $g_users       = [];
            $g_total       = 0;
            $g_messages    = 0;
            $g_cost        = 0.0;
            $g_active_today = 0;
            $g_total_pages = 1;
            $g_current_pg  = isset( $_GET['g_pg'] ) ? max( 1, (int) $_GET['g_pg'] ) : 1;
            $g_per_page    = 20;
            $g_today       = current_time( 'Y-m-d' );

            if ( $g_has_table ) {
                $g_total        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g_table_name}" );
                $g_messages     = (int) $wpdb->get_var( "SELECT COALESCE(SUM(message_count),0) FROM {$g_table_name}" );
                $g_cost         = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_cost),0) FROM {$g_table_name}" );
                $g_active_today = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$g_table_name} WHERE daily_date = %s", $g_today
                ) );
                $g_total_pages  = max( 1, (int) ceil( $g_total / $g_per_page ) );
                $g_current_pg   = min( $g_current_pg, $g_total_pages );
                $g_offset       = ( $g_current_pg - 1 ) * $g_per_page;
                $g_users        = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$g_table_name} ORDER BY last_active DESC LIMIT %d OFFSET %d",
                        $g_per_page, $g_offset
                    ),
                    ARRAY_A
                );
            }

            $g_limit = (int) get_option( 'shopys_ai_daily_limit', 10 );
            $guest_ip = isset( $_GET['guest_ip'] ) ? sanitize_text_field( wp_unslash( $_GET['guest_ip'] ) ) : '';
        ?>

        <?php if ( $guest_ip !== '' ) :
            // ── Single guest: show all the questions they asked ──
            $gm_table = $wpdb->prefix . 'chatbot_guest_messages';
            $gm_has   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gm_table ) ) === $gm_table;
            $gm_rows  = $gm_has ? $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$gm_table} WHERE ip = %s ORDER BY created_at DESC LIMIT 500", $guest_ip
            ), ARRAY_A ) : [];
            $guest_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}chatbot_guest_users WHERE ip = %s", $guest_ip
            ) );
            $guest_label = ! empty( $guest_name ) ? $guest_name . ' (' . $guest_ip . ')' : $guest_ip;
        ?>
        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Questions from <code><?php echo esc_html( $guest_label ); ?></code>
                    <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( count( $gm_rows ) ); ?> messages</span>
                </span>
                <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users' ], home_url( '/dashboard/' ) ) ); ?>">&laquo; Back to guests</a>
            </div>
            <?php if ( ! $gm_has || empty( $gm_rows ) ) : ?>
                <div style="padding:18px;color:var(--muted);">No questions recorded for this guest yet.</div>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr><th style="width:170px;">Time</th><th>Question</th></tr></thead>
                <tbody>
                <?php foreach ( $gm_rows as $gm ) : ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--muted);font-size:12px;"><?php echo esc_html( $gm['created_at'] ); ?></td>
                    <td style="line-height:1.5;"><?php echo esc_html( $gm['question'] ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php else :
            $top_q = get_option( 'shopys_ai_top_questions' );
            $guser = ( isset( $_GET['guser'] ) && $_GET['guser'] === 'member' ) ? 'member' : 'guest';
            $g_base_limit = (int) get_option( 'shopys_ai_daily_limit', 10 );
            $g_member_total = count( get_users( array( 'meta_key' => 'shopys_ai_last_active', 'meta_compare' => 'EXISTS', 'fields' => 'ID', 'number' => -1 ) ) );
        ?>

        <style>
            .cu-filter{display:inline-flex;background:#eef1f5;border:1px solid var(--border,#e6eaf0);border-radius:14px;padding:5px;gap:4px;margin-bottom:22px;}
            .cu-filter__btn{display:inline-flex;align-items:center;gap:9px;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;color:#697586;text-decoration:none;transition:background .2s ease,color .2s ease,box-shadow .2s ease;white-space:nowrap;}
            .cu-filter__btn:hover{color:#0d1117;}
            .cu-filter__btn svg{width:16px;height:16px;color:#9aa6b6;transition:color .2s ease;}
            .cu-filter__btn.active{background:#fff;color:#0d1117;box-shadow:0 3px 10px rgba(16,24,40,.12);}
            .cu-filter__btn.active svg{color:#13e800;}
            .cu-filter__count{font-size:11px;font-weight:800;line-height:1;padding:3px 8px;border-radius:20px;background:#dde3ea;color:#5b6675;}
            .cu-filter__btn.active .cu-filter__count{background:rgba(19,232,0,.14);color:#0a7d00;}
        </style>

        <!-- Filter: Guests vs Members (logged in with website account) -->
        <div class="cu-filter">
            <a class="cu-filter__btn <?php echo $guser === 'guest' ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users' ], home_url( '/dashboard/' ) ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Guests <span class="cu-filter__count"><?php echo number_format_i18n( $g_total ); ?></span>
            </a>
            <a class="cu-filter__btn <?php echo $guser === 'member' ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'guser' => 'member' ], home_url( '/dashboard/' ) ) ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Members <span class="cu-filter__count"><?php echo number_format_i18n( $g_member_total ); ?></span>
            </a>
        </div>

        <?php if ( $guser === 'member' ) :
            $today   = current_time( 'Y-m-d' );
            $m_limit = $g_base_limit * 2;
            $member_id = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
        ?>

        <?php if ( $member_id > 0 ) :
            // ── Single member: show all the questions they asked ──
            $mm_table   = $wpdb->prefix . 'chatbot_member_messages';
            $mm_has     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mm_table ) ) === $mm_table;
            $mm_rows    = $mm_has ? $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$mm_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 500", $member_id
            ), ARRAY_A ) : [];
            $mm_user    = get_userdata( $member_id );
            $mm_label   = $mm_user ? ( $mm_user->display_name . ' (' . $mm_user->user_email . ')' ) : ( 'User #' . $member_id );
        ?>
        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Questions from <code><?php echo esc_html( $mm_label ); ?></code>
                    <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( count( $mm_rows ) ); ?> messages</span>
                </span>
                <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'guser' => 'member' ], home_url( '/dashboard/' ) ) ); ?>">&laquo; Back to members</a>
            </div>
            <?php if ( ! $mm_has || empty( $mm_rows ) ) : ?>
                <div style="padding:18px;color:var(--muted);">No questions recorded for this member yet.</div>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr><th style="width:170px;">Time</th><th>Question</th></tr></thead>
                <tbody>
                <?php foreach ( $mm_rows as $mm ) : ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--muted);font-size:12px;"><?php echo esc_html( $mm['created_at'] ); ?></td>
                    <td style="line-height:1.5;"><?php echo esc_html( $mm['question'] ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php else :
            $members = get_users( array(
                'meta_key'     => 'shopys_ai_last_active',
                'meta_compare' => 'EXISTS',
                'orderby'      => 'meta_value',
                'order'        => 'DESC',
                'number'       => 300,
            ) );
        ?>
        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Members <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( count( $members ) ); ?> users · logged in with website account</span></span>
                <span style="font-size:12px;color:var(--muted);">Daily limit: <?php echo number_format_i18n( $m_limit ); ?>/day</span>
            </div>
            <?php if ( empty( $members ) ) : ?>
                <div style="padding:18px;color:var(--muted);">No logged-in users have used the chatbot yet.</div>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr><th>Name</th><th>Email</th><th>Total Messages</th><th>Today's Usage</th><th>Last Active</th></tr></thead>
                <tbody>
                <?php foreach ( $members as $m ) :
                    $m_total = (int) get_user_meta( $m->ID, 'shopys_ai_total_messages', true );
                    $m_date  = get_user_meta( $m->ID, 'shopys_ai_daily_date', true );
                    $m_today = ( $m_date === $today ) ? (int) get_user_meta( $m->ID, 'shopys_ai_daily_count', true ) : 0;
                    $m_last  = get_user_meta( $m->ID, 'shopys_ai_last_active', true );
                    $m_at    = $m_today >= $m_limit;
                ?>
                <tr>
                    <td style="font-weight:600;">
                        <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'guser' => 'member', 'member_id' => $m->ID ], home_url( '/dashboard/' ) ) ); ?>" style="color:#0fb500;font-weight:700;text-decoration:none;" title="View questions">
                            <?php echo esc_html( $m->display_name ); ?> &rsaquo;
                        </a>
                    </td>
                    <td><?php echo esc_html( $m->user_email ); ?></td>
                    <td><?php echo number_format_i18n( $m_total ); ?></td>
                    <td><span style="font-weight:600;<?php echo $m_at ? 'color:#dc2626;' : ''; ?>"><?php echo number_format_i18n( $m_today ) . ' / ' . number_format_i18n( $m_limit ); ?></span></td>
                    <td style="color:var(--muted);font-size:12px;"><?php echo esc_html( $m_last ?: '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; // member detail vs members list ?>

        <?php else : ?>

        <?php if ( isset( $_GET['g_reset'] ) ) : ?>
        <div style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;">✓ Guest daily limit reset — they can chat again today.</div>
        <?php endif; ?>

        <!-- ── Top Questions (semantic analysis) ── -->
        <div class="ds-table-wrap" style="margin-bottom:24px;">
            <div class="ds-table-head" id="sai-tq-toggle" style="display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none;">
                <span>🔥 Top Questions <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;">grouped by meaning</span></span>
                <svg id="sai-tq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition:transform .2s;flex-shrink:0;"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            <div id="sai-tq-collapse" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:10px 16px;border-bottom:1px solid var(--border,#eee);">
                <select id="sai-tq-top" style="padding:6px 10px;border:1px solid var(--border,#e2e6ea);border-radius:8px;font:inherit;">
                    <option value="20">Top 20</option>
                    <option value="50">Top 50</option>
                </select>
                <button id="sai-tq-run" type="button" style="background:#13e800;color:#000;border:none;border-radius:8px;padding:7px 16px;font-weight:700;cursor:pointer;">Analyze</button>
            </div>
            <div id="sai-tq-meta" style="padding:8px 16px;color:var(--muted);font-size:12px;border-bottom:1px solid var(--border,#eee);">
                <?php if ( is_array( $top_q ) && ! empty( $top_q['generated_at'] ) ) :
                    echo 'Last generated ' . esc_html( $top_q['generated_at'] ) . ' · ' . number_format_i18n( (int) ( $top_q['total_messages'] ?? 0 ) ) . ' questions analyzed';
                else : echo 'Not analyzed yet — click "Analyze" to group guest questions by meaning.'; endif; ?>
            </div>
            <div id="sai-tq-body">
                <?php if ( is_array( $top_q ) && ! empty( $top_q['items'] ) ) : ?>
                <table class="ds-table">
                    <thead><tr><th style="width:48px;">#</th><th>Question (intent)</th><th style="width:90px;">Count</th><th>Example asked</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_q['items'] as $i => $it ) : ?>
                    <tr>
                        <td><?php echo (int) $i + 1; ?></td>
                        <td style="font-weight:600;"><?php echo esc_html( $it['question'] ?? '' ); ?></td>
                        <td><strong><?php echo number_format_i18n( (int) ( $it['count'] ?? 0 ) ); ?></strong></td>
                        <td style="color:var(--muted);font-size:12px;"><?php echo esc_html( $it['example'] ?? '' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            </div>
        </div>
        <script>
        (function () {
            // Collapsible toggle (default collapsed)
            var tg = document.getElementById('sai-tq-toggle');
            var col = document.getElementById('sai-tq-collapse');
            var chev = document.getElementById('sai-tq-chevron');
            if (tg && col) {
                tg.addEventListener('click', function () {
                    var open = col.style.display !== 'none';
                    col.style.display = open ? 'none' : '';
                    if (chev) chev.style.transform = open ? '' : 'rotate(180deg)';
                });
            }

            var btn = document.getElementById('sai-tq-run');
            if (!btn) return;
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce   = '<?php echo esc_js( wp_create_nonce( 'shopys_ai_analyze' ) ); ?>';
            function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }
            btn.addEventListener('click', function () {
                var top = document.getElementById('sai-tq-top').value;
                btn.disabled = true; var old = btn.textContent; btn.textContent = 'Analyzing…';
                var fd = new FormData();
                fd.append('action', 'shopys_ai_analyze_questions');
                fd.append('nonce', nonce);
                fd.append('top', top);
                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false; btn.textContent = old;
                        if (!res || !res.success) { alert((res && res.data && res.data.message) || 'Analysis failed.'); return; }
                        var d = res.data, items = d.items || [];
                        var h = '<table class="ds-table"><thead><tr><th style="width:48px;">#</th><th>Question (intent)</th><th style="width:90px;">Count</th><th>Example asked</th></tr></thead><tbody>';
                        items.forEach(function(it,i){
                            h += '<tr><td>'+(i+1)+'</td><td style="font-weight:600;">'+esc(it.question)+'</td><td><strong>'+(it.count||0)+'</strong></td><td style="color:var(--muted);font-size:12px;">'+esc(it.example)+'</td></tr>';
                        });
                        h += '</tbody></table>';
                        document.getElementById('sai-tq-body').innerHTML = h;
                        document.getElementById('sai-tq-meta').textContent = 'Last generated ' + (d.generated_at||'') + ' · ' + (d.total_messages||0) + ' questions analyzed';
                    })
                    .catch(function(){ btn.disabled = false; btn.textContent = old; alert('Request failed. Please try again.'); });
            });
        })();
        </script>

        <div class="ds-cards" style="margin-bottom:24px;">
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div class="ds-card-label">Guest Users</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $g_total ); ?></div>
                <div class="ds-card-sub">Unique visitors (by IP)</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4 0 .74.2 1.43.55 2.03L7 17h3.11c.57.36 1.25.57 1.94.57 2.21 0 4-1.79 4-4s-1.79-3.57-4-3.57zm-7-4h14a2 2 0 012 2v12a2 2 0 01-2 2h-4l-3 3-3-3H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                </div>
                <div class="ds-card-label">Total Messages</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $g_messages ); ?></div>
                <div class="ds-card-sub">All guest messages</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M13 2L3 14h7l-1 8 10-12h-7z"/></svg>
                </div>
                <div class="ds-card-label">Active Today</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $g_active_today ); ?></div>
                <div class="ds-card-sub">Guests chatting today</div>
            </div>
            <div class="ds-card">
                <div class="ds-card-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 1a9 9 0 100 18 9 9 0 000-18zm1 14.93V17h-2v-1.09A4.002 4.002 0 018 12h2a2 2 0 104 0c0-1.1-.9-2-2-2a4 4 0 110-8V1h2v1.07A4.002 4.002 0 0116 6h-2a2 2 0 10-4 0c0 1.1.9 2 2 2a4 4 0 110 8z"/></svg>
                </div>
                <div class="ds-card-label">API Total Cost</div>
                <div class="ds-card-value">$<?php echo esc_html( number_format( $g_cost, 4 ) ); ?></div>
                <div class="ds-card-sub">All guest messages</div>
            </div>
        </div>

        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;">
                <span>Guest Chat Users <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( $g_total ); ?> guests</span></span>
                <span style="font-size:12px;color:var(--muted);">Daily limit: <?php echo number_format_i18n( $g_limit ); ?>/day</span>
            </div>

            <?php if ( ! $g_has_table ) : ?>
                <div style="padding:18px;color:var(--muted);">The guest users table does not exist yet.</div>
            <?php elseif ( empty( $g_users ) ) : ?>
                <div style="padding:18px;color:var(--muted);">No guest chat users yet.</div>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr>
                    <th>Guest</th>
                    <th>First Seen</th>
                    <th>Last Active</th>
                    <th>Total Messages</th>
                    <th>Today's Usage</th>
                    <th>API Cost</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $g_users as $g_user ) :
                    $g_used_today = ( ( $g_user['daily_date'] ?? '' ) === $g_today ) ? (int) $g_user['daily_count'] : 0;
                    $g_at_limit   = $g_used_today >= $g_limit;
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'guest_ip' => $g_user['ip'] ], home_url( '/dashboard/' ) ) ); ?>" style="color:#0fb500;font-weight:700;text-decoration:none;" title="View questions">
                            <?php if ( ! empty( $g_user['name'] ) ) : ?>
                                <?php echo esc_html( $g_user['name'] ); ?> &rsaquo;
                                <span style="display:block;color:var(--muted);font-weight:400;font-size:11px;"><?php echo esc_html( $g_user['ip'] ); ?></span>
                            <?php else : ?>
                                <code style="color:inherit;"><?php echo esc_html( $g_user['ip'] ); ?></code> &rsaquo;
                            <?php endif; ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( $g_user['first_seen'] ?: '—' ); ?></td>
                    <td><?php echo esc_html( $g_user['last_active'] ?: '—' ); ?></td>
                    <td><?php echo number_format_i18n( (int) ( $g_user['message_count'] ?? 0 ) ); ?></td>
                    <td>
                        <span style="font-weight:600;<?php echo $g_at_limit ? 'color:#dc2626;' : ''; ?>">
                            <?php echo number_format_i18n( $g_used_today ) . ' / ' . number_format_i18n( $g_limit ); ?>
                        </span>
                    </td>
                    <td><strong>$<?php echo esc_html( number_format( (float) ( $g_user['total_cost'] ?? 0 ), 4 ) ); ?></strong></td>
                    <td>
                        <form method="post" style="margin:0;"><?php wp_nonce_field( 'g_reset_limit' ); ?>
                            <input type="hidden" name="g_reset_ip" value="<?php echo esc_attr( $g_user['ip'] ); ?>">
                            <input type="hidden" name="g_pg" value="<?php echo esc_attr( $g_current_pg ); ?>">
                            <button type="submit" class="tg-vip-btn tg-vip-btn-add" <?php echo $g_used_today === 0 ? 'disabled style="opacity:.45;cursor:default;"' : ''; ?> title="Reset today's message limit for this guest">↺ Reset Limit</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $g_total_pages > 1 ) : ?>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 16px;border-top:1px solid var(--border);">
                <?php if ( $g_current_pg > 1 ) : ?>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'g_pg' => 1 ], home_url( '/dashboard/' ) ) ); ?>">&laquo; First</a>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'g_pg' => $g_current_pg - 1 ], home_url( '/dashboard/' ) ) ); ?>">&lsaquo; Prev</a>
                <?php endif; ?>
                <span class="ds-tab active">Page <?php echo esc_html( $g_current_pg ); ?> of <?php echo esc_html( $g_total_pages ); ?></span>
                <?php if ( $g_current_pg < $g_total_pages ) : ?>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'g_pg' => $g_current_pg + 1 ], home_url( '/dashboard/' ) ) ); ?>">Next &rsaquo;</a>
                    <a class="ds-tab" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'guest-users', 'g_pg' => $g_total_pages ], home_url( '/dashboard/' ) ) ); ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; // members vs guests filter ?>
        <?php endif; // guest_ip messages view vs list ?>
        <?php endif; ?>
        </div>

        <!-- ── USERS TAB ──────────────────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'users' ? 'active' : ''; ?>" id="panel-users">
        <?php if ( $active_tab === 'users' ) :
            $u_month = ( ! empty( $_GET['u_month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['u_month'] ) ) ? $_GET['u_month'] : '';
            $u_day   = ( ! empty( $_GET['u_day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['u_day'] ) ) ? $_GET['u_day'] : '';
            $u_role  = isset( $_GET['u_role'] ) ? sanitize_key( $_GET['u_role'] ) : '';
            $u_args  = [ 'orderby' => 'registered', 'order' => 'DESC', 'number' => -1 ];
            if ( $u_day ) { // exact day wins over month
                $u_args['date_query'] = [ [ 'column' => 'user_registered', 'after' => $u_day . ' 00:00:00', 'before' => $u_day . ' 23:59:59', 'inclusive' => true ] ];
            } elseif ( $u_month ) {
                $u_start = $u_month . '-01 00:00:00';
                $u_end   = date( 'Y-m-t 23:59:59', strtotime( $u_start ) );
                $u_args['date_query'] = [ [ 'column' => 'user_registered', 'after' => $u_start, 'before' => $u_end, 'inclusive' => true ] ];
            }
            if ( $u_role ) $u_args['role'] = $u_role;
            $all_users = get_users( $u_args );
            // Search across name / login / email / phone.
            $u_search = isset( $_GET['u_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['u_search'] ) ) ) : '';
            if ( $u_search !== '' ) {
                $u_t = strtolower( $u_search );
                $all_users = array_values( array_filter( $all_users, function ( $u ) use ( $u_t ) {
                    $ph  = get_user_meta( $u->ID, 'billing_phone', true );
                    return strpos( strtolower( $u->display_name . ' ' . $u->user_login . ' ' . $u->user_email . ' ' . $ph ), $u_t ) !== false;
                } ) );
            }
            // Pagination (over the full filtered set; summary cards use the full set too).
            $u_per_page    = 20;
            $u_total       = count( $all_users );
            $u_total_pages = max( 1, (int) ceil( $u_total / $u_per_page ) );
            $u_pg          = isset( $_GET['u_pg'] ) ? max( 1, (int) $_GET['u_pg'] ) : 1;
            $u_pg          = min( $u_pg, $u_total_pages );
            $u_page_users  = array_slice( $all_users, ( $u_pg - 1 ) * $u_per_page, $u_per_page );
            // Months that have registrations (for the dropdown).
            global $wpdb;
            $u_months   = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(user_registered,'%Y-%m') ym FROM {$wpdb->users} ORDER BY ym DESC" );
            $u_all_roles = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : []; // slug => label
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
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>All Users <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo count($all_users); ?> account<?php echo count($all_users)===1?'':'s'; ?><?php echo ( $u_role && isset($u_all_roles[$u_role]) ) ? ' · ' . esc_html($u_all_roles[$u_role]) : ''; ?><?php echo $u_day ? ' · ' . esc_html( date_i18n('j M Y', strtotime($u_day)) ) : ( $u_month ? ' · ' . esc_html( date_i18n('F Y', strtotime($u_month.'-01')) ) : '' ); ?></span></span>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php $u_sel_css = 'padding:7px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;cursor:pointer;'; ?>
                    <form method="get" action="<?php echo esc_url( home_url('/dashboard/') ); ?>" style="display:flex;gap:6px;align-items:center;margin:0;">
                        <input type="hidden" name="tab" value="users">
                        <input type="search" name="u_search" value="<?php echo esc_attr( $u_search ); ?>" placeholder="Search name, email, phone…" style="<?php echo $u_sel_css; ?>min-width:200px;">
                        <select name="u_role" onchange="this.form.submit()" style="<?php echo $u_sel_css; ?>">
                            <option value="">All roles</option>
                            <?php foreach ( (array) $u_all_roles as $rk => $rlabel ) : ?>
                            <option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $u_role, $rk ); ?>><?php echo esc_html( $rlabel ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="u_month" onchange="this.form.submit()" style="<?php echo $u_sel_css; ?>">
                            <option value="">All months</option>
                            <?php foreach ( (array) $u_months as $m ) : if ( ! $m ) continue; ?>
                            <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $u_month, $m ); ?>><?php echo esc_html( date_i18n( 'F Y', strtotime( $m . '-01' ) ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="u_day" value="<?php echo esc_attr( $u_day ); ?>" onchange="this.form.submit()" title="Filter by exact day (overrides month)" style="<?php echo $u_sel_css; ?>">
                        <?php if ( $u_day || $u_month ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'users', 'u_role' => $u_role, 'u_search' => $u_search ], home_url('/dashboard/') ) ); ?>" title="Clear date" style="color:var(--muted);text-decoration:none;font-size:16px;line-height:1;padding:0 2px;">&times;</a><?php endif; ?>
                    </form>
                    <a class="ds-store-btn" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'users', 'export' => 'users_csv', 'u_role' => $u_role, 'u_month' => $u_month, 'u_day' => $u_day, 'u_search' => $u_search ], home_url('/dashboard/') ) ); ?>" title="Download as Excel/CSV">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        Export Excel
                    </a>
                </div>
            </div>
            <?php if ( $all_users ) : ?>
            <table class="ds-table">
                <thead><tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th>Last Login</th>
                    <th>Login IP</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $u_page_users as $i => $u ) :
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
                    <td style="color:var(--muted);font-size:12px;"><?php echo ( $u_pg - 1 ) * $u_per_page + $i + 1; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar"><?php echo esc_html($initial); ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo esc_html( $u->display_name ?: $u->user_login ); ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?php echo esc_html( $u->user_email ); ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12.5px;white-space:nowrap;"><?php $u_phone = get_user_meta( $u->ID, 'billing_phone', true ); echo $u_phone ? esc_html( $u_phone ) : '<span style="color:var(--muted);">—</span>'; ?></td>
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
            <?php if ( $u_total_pages > 1 ) :
                $u_pg_base = add_query_arg( [ 'tab' => 'users', 'u_role' => $u_role, 'u_month' => $u_month, 'u_day' => $u_day, 'u_search' => $u_search ], home_url('/dashboard/') );
            ?>
            <div class="sv-pagination">
                <?php if ( $u_pg > 1 ) : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'u_pg', $u_pg - 1, $u_pg_base ) ); ?>">&lsaquo; Prev</a><?php else : ?><span class="sv-pag-btn disabled">&lsaquo; Prev</span><?php endif; ?>
                <?php for ( $p = 1; $p <= $u_total_pages; $p++ ) :
                    if ( $p == 1 || $p == $u_total_pages || abs( $p - $u_pg ) <= 2 ) : ?>
                        <?php if ( $p == $u_pg ) : ?><span class="sv-pag-btn active"><?php echo $p; ?></span><?php else : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'u_pg', $p, $u_pg_base ) ); ?>"><?php echo $p; ?></a><?php endif; ?>
                    <?php elseif ( $p == 2 || $p == $u_total_pages - 1 ) : ?><span class="sv-pag-ellipsis">&hellip;</span><?php endif;
                endfor; ?>
                <?php if ( $u_pg < $u_total_pages ) : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'u_pg', $u_pg + 1, $u_pg_base ) ); ?>">Next &rsaquo;</a><?php else : ?><span class="sv-pag-btn disabled">Next &rsaquo;</span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else : ?>
            <p style="padding:24px;color:var(--muted);font-size:13px;">No users found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>

        <!-- ── TOP CUSTOMERS PANEL ───────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'top-customers' ? 'active' : ''; ?>" id="panel-top-customers">
        <?php if ( $active_tab === 'top-customers' ) :
            // Base URL preserving the active status + month filters (used by the Rank-by links).
            $tc_base = add_query_arg( [ 'tab' => 'top-customers', 'top_status' => $top_status, 'top_month' => $top_month, 'top_search' => $top_search ], home_url( '/dashboard/' ) );
        ?>
            <form method="get" action="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="sv-filter">
                <input type="hidden" name="tab" value="top-customers">
                <input type="hidden" name="top_sort" value="<?php echo esc_attr( $top_sort ); ?>">
                <input type="search" name="top_search" value="<?php echo esc_attr( $top_search ); ?>" placeholder="Search customer / email…" style="padding:7px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;min-width:200px;">
                <label>Status:</label>
                <select name="top_status">
                    <option value="all" <?php selected( $top_status, 'all' ); ?>>All statuses</option>
                    <?php foreach ( $tc_statuses as $sk => $sl ) : ?>
                    <option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $top_status, $sk ); ?>><?php echo esc_html( $sl ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Month:</label>
                <select name="top_month">
                    <option value="">All time</option>
                    <?php foreach ( $tc_months as $mo ) : if ( ! $mo ) continue; ?>
                    <option value="<?php echo esc_attr( $mo ); ?>" <?php selected( $top_month, $mo ); ?>><?php echo esc_html( date_i18n( 'F Y', strtotime( $mo . '-01' ) ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="sv-filter-btn">Apply</button>
            </form>
            <div class="sv-filter">
                <label>Rank by:</label>
                <a class="an-period-btn <?php echo $top_sort === 'spent' ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'top_sort', 'spent', $tc_base ) ); ?>">Total Spent</a>
                <a class="an-period-btn <?php echo $top_sort === 'orders' ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'top_sort', 'orders', $tc_base ) ); ?>">Order Count</a>
            </div>
            <div class="ds-table-wrap">
                <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <span>Top Customers <span style="color:var(--muted);font-weight:400;font-size:12px;"><?php echo count( (array) $top_customers ); ?> shown<?php echo $top_search !== '' ? ' · “' . esc_html( $top_search ) . '”' : ''; ?></span></span>
                    <a class="ds-store-btn" href="<?php echo esc_url( add_query_arg( [ 'tab' => 'top-customers', 'export' => 'topcust_csv', 'top_status' => $top_status, 'top_month' => $top_month, 'top_search' => $top_search, 'top_sort' => $top_sort ], home_url('/dashboard/') ) ); ?>" title="Download as Excel/CSV">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        Export Excel
                    </a>
                </div>
                <?php if ( $top_customers ) : ?>
                <table class="ds-table">
                    <thead><tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th style="text-align:right;">Orders</th>
                        <th style="text-align:right;">Total Spent</th>
                        <th>Location</th>
                        <th>Last Order</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $top_customers as $i => $tc ) :
                        $tc_name = trim( $tc->first_name . ' ' . $tc->last_name );
                        if ( $tc_name === '' ) $tc_name = $tc->username ?: ( $tc->email ?: 'Customer #' . $tc->customer_id );
                        $tc_init = strtoupper( mb_substr( $tc_name, 0, 1 ) );
                        $tc_loc  = trim( ( $tc->city ?: '' ) . ( $tc->country ? ( $tc->city ? ', ' : '' ) . $tc->country : '' ) );
                        $tc_last = $tc->last_order ? date_i18n( 'j M Y', strtotime( $tc->last_order ) ) : '—';
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-size:12px;"><?php echo $i + 1; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:30px;height:30px;border-radius:50%;background:var(--green-dim);color:var(--green);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;"><?php echo esc_html( $tc_init ); ?></div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?php echo esc_html( $tc_name ); ?></div>
                                    <div style="font-size:11px;color:var(--muted);"><?php echo esc_html( $tc->email ?: '—' ); ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;font-weight:700;"><?php echo number_format_i18n( (int) $tc->orders ); ?></td>
                        <td style="text-align:right;font-weight:700;color:var(--green);"><?php echo esc_html( $tc_symbol . number_format( (float) $tc->spent, 2 ) ); ?></td>
                        <td style="font-size:12px;color:var(--muted);"><?php echo esc_html( $tc_loc ?: '—' ); ?></td>
                        <td style="font-size:12px;white-space:nowrap;"><?php echo esc_html( $tc_last ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p style="padding:24px;color:var(--muted);font-size:13px;">No customer order data yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>

        <!-- ── CART / ORDERS PANEL ───────────────────────────────────── -->
        <div class="ds-panel <?php echo $active_tab === 'cart' ? 'active' : ''; ?>" id="panel-cart">
        <?php if ( $active_tab === 'cart' ) :
            if ( ! function_exists( 'wc_get_orders' ) ) : ?>
            <p style="padding:24px;color:var(--muted);font-size:13px;">WooCommerce is not active.</p>
            <?php else :
            $co_search = isset( $_GET['co_search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['co_search'] ) ) ) : '';
            $co_status = isset( $_GET['co_status'] ) ? sanitize_key( $_GET['co_status'] ) : '';
            $co_month  = ( ! empty( $_GET['co_month'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['co_month'] ) ) ? $_GET['co_month'] : '';
            $co_day    = ( ! empty( $_GET['co_day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['co_day'] ) ) ? $_GET['co_day'] : '';

            $co_sel_css      = 'padding:7px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;cursor:pointer;';
            $co_all_statuses = wc_get_order_statuses(); // 'wc-processing' => 'Processing'
            $co_status_norm  = ( $co_status && array_key_exists( 'wc-' . $co_status, $co_all_statuses ) ) ? $co_status : '';

            // Shared status colour map (used by cards, list badges, and the detail view).
            $co_card_map = [
                'pending'    => [ 'Pending',    '#b45309', 'rgba(245,158,11,.14)' ],
                'processing' => [ 'Processing', '#047857', 'rgba(16,185,129,.14)' ],
                'on-hold'    => [ 'On hold',    '#6b7280', 'rgba(148,163,184,.16)' ],
                'completed'  => [ 'Completed',  '#1d4ed8', 'rgba(59,130,246,.14)' ],
                'cancelled'  => [ 'Cancelled',  '#b91c1c', 'rgba(239,68,68,.14)' ],
                'refunded'   => [ 'Refunded',   '#7c3aed', 'rgba(139,92,246,.14)' ],
                'failed'     => [ 'Failed',     '#b91c1c', 'rgba(239,68,68,.14)' ],
            ];

            // Detail view? (?tab=cart&co_order=<id>)
            $co_view_id = isset( $_GET['co_order'] ) ? (int) $_GET['co_order'] : 0;
            $co_view    = $co_view_id ? wc_get_order( $co_view_id ) : false;
            $co_back    = add_query_arg( array_filter( [ 'tab' => 'cart', 'co_status' => $co_status, 'co_month' => $co_month, 'co_day' => $co_day, 'co_search' => $co_search, 'co_pg' => max( 1, (int) ( $_GET['co_pg'] ?? 1 ) ) ], 'strlen' ), home_url( '/dashboard/' ) );
        ?>

        <?php if ( $co_view ) :
            $v_status  = $co_view->get_status();
            $v_meta    = $co_card_map[ $v_status ] ?? [ ucfirst( str_replace( '-', ' ', $v_status ) ), '#6b7280', 'rgba(148,163,184,.16)' ];
            $v_created = $co_view->get_date_created();
            $v_name    = trim( $co_view->get_billing_first_name() . ' ' . $co_view->get_billing_last_name() );
            if ( $v_name === '' ) $v_name = $co_view->get_billing_company() ?: '—';
            $v_bill    = $co_view->get_formatted_billing_address();
            $v_ship    = $co_view->get_formatted_shipping_address();
            $v_totals  = $co_view->get_order_item_totals();
            $v_delivery= $co_view->get_meta( '_delivery_option' );
            $v_shop    = $co_view->get_meta( 'shop_branch' ) ?: $co_view->get_meta( '_shop_branch' );
            $v_paidby  = $co_view->get_meta( '_khqrpay_from_account' );
            $v_maplink = $co_view->get_meta( '_delivery_map' ) ?: $co_view->get_meta( 'billing_delivery_map' );
        ?>
        <div class="ds-table-wrap" style="margin-bottom:20px;">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    Order #<?php echo esc_html( $co_view->get_order_number() ); ?>
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $v_meta[1] ); ?>;background:<?php echo esc_attr( $v_meta[2] ); ?>;"><?php echo esc_html( shopys_order_status_ui_label( $co_view, $v_meta[0] ) ); ?></span>
                    <span style="color:var(--muted);font-weight:400;font-size:12px;"><?php echo $v_created ? esc_html( $v_created->date_i18n( 'j M Y, H:i' ) ) : ''; ?></span>
                </span>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <a class="ds-store-btn" href="<?php echo esc_url( $co_view->get_edit_order_url() ); ?>" target="_blank">Open in WP Admin</a>
                    <a class="ds-store-btn" href="<?php echo esc_url( $co_back ); ?>" style="background:var(--surface2);color:var(--text);border:1px solid var(--border);">&laquo; Back to orders</a>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:20px;align-items:start;">
            <!-- Items + totals -->
            <div class="ds-table-wrap">
                <div class="ds-table-head">Items <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( $co_view->get_item_count() ); ?></span></div>
                <table class="ds-table">
                    <thead><tr><th>Product</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ( $co_view->get_items() as $item ) :
                        $prod = $item->get_product();
                        $thumb = $prod ? $prod->get_image( [ 40, 40 ] ) : '';
                        $plink = $prod ? get_edit_post_link( $prod->get_id() ) : '';
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php if ( $thumb ) echo wp_kses_post( $thumb ); ?>
                                <div style="font-weight:600;font-size:13px;line-height:1.3;"><?php echo esc_html( $item->get_name() ); ?></div>
                            </div>
                        </td>
                        <td style="text-align:center;font-weight:600;"><?php echo number_format_i18n( $item->get_quantity() ); ?></td>
                        <td style="text-align:right;white-space:nowrap;"><?php echo wp_kses_post( wc_price( $item->get_total(), [ 'currency' => $co_view->get_currency() ] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if ( ! empty( $v_totals ) ) : ?>
                    <tfoot>
                        <?php foreach ( $v_totals as $tk => $trow ) : ?>
                        <tr>
                            <td colspan="2" style="text-align:right;color:var(--muted);<?php echo $tk === 'order_total' ? 'font-weight:800;color:var(--text);' : ''; ?>"><?php echo esc_html( $trow['label'] ); ?></td>
                            <td style="text-align:right;white-space:nowrap;<?php echo $tk === 'order_total' ? 'font-weight:800;color:var(--green);' : ''; ?>"><?php echo wp_kses_post( $trow['value'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Customer + status -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div class="ds-table-wrap" style="padding:0;">
                    <div class="ds-table-head">Customer</div>
                    <div style="padding:16px;font-size:13px;line-height:1.7;">
                        <div style="font-weight:700;margin-bottom:6px;"><?php echo esc_html( $v_name ); ?></div>
                        <?php if ( $co_view->get_billing_email() ) : ?><div>✉️ <a href="mailto:<?php echo esc_attr( $co_view->get_billing_email() ); ?>" style="color:var(--green);text-decoration:none;"><?php echo esc_html( $co_view->get_billing_email() ); ?></a></div><?php endif; ?>
                        <?php if ( $co_view->get_billing_phone() ) : ?><div>📞 <?php echo esc_html( $co_view->get_billing_phone() ); ?></div><?php endif; ?>
                        <?php if ( $v_bill ) : ?><div style="margin-top:10px;color:var(--muted);"><strong style="color:var(--text);">Billing</strong><br><?php echo wp_kses_post( $v_bill ); ?></div><?php endif; ?>
                        <?php if ( $v_ship && $v_ship !== $v_bill ) : ?><div style="margin-top:10px;color:var(--muted);"><strong style="color:var(--text);">Shipping</strong><br><?php echo wp_kses_post( $v_ship ); ?></div><?php endif; ?>
                        <?php if ( $co_view->get_customer_note() ) : ?><div style="margin-top:10px;padding:10px;background:var(--surface2);border-radius:8px;"><strong>Note:</strong> <?php echo esc_html( $co_view->get_customer_note() ); ?></div><?php endif; ?>
                    </div>
                </div>

                <?php if ( $co_view->get_payment_method_title() || $v_delivery || $v_shop || $v_paidby || $v_maplink ) : ?>
                <div class="ds-table-wrap" style="padding:0;">
                    <div class="ds-table-head">Payment &amp; Delivery</div>
                    <div style="padding:16px;font-size:13px;line-height:1.8;">
                        <?php if ( $co_view->get_payment_method_title() ) : ?><div><span style="color:var(--muted);">Payment:</span> <strong><?php echo esc_html( $co_view->get_payment_method_title() ); ?></strong></div><?php endif; ?>
                        <?php if ( $v_paidby ) : ?><div><span style="color:var(--muted);">Paid by:</span> <?php echo esc_html( $v_paidby ); ?></div><?php endif; ?>
                        <?php if ( $v_delivery ) : ?><div><span style="color:var(--muted);">Delivery:</span> <?php echo esc_html( $v_delivery ); ?></div><?php endif; ?>
                        <?php if ( $v_shop ) : ?><div><span style="color:var(--muted);">Shop:</span> <?php echo esc_html( $v_shop ); ?></div><?php endif; ?>
                        <?php if ( $v_maplink ) : ?><div><span style="color:var(--muted);">Map:</span> <a href="<?php echo esc_url( $v_maplink ); ?>" target="_blank" style="color:var(--green);text-decoration:none;">Open location</a></div><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="ds-table-wrap" style="padding:0;">
                    <div class="ds-table-head">Update status</div>
                    <form method="post" style="padding:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <?php wp_nonce_field( 'cart_order_status' ); ?>
                        <input type="hidden" name="co_pg" value="<?php echo esc_attr( max( 1, (int) ( $_GET['co_pg'] ?? 1 ) ) ); ?>">
                        <input type="hidden" name="co_ret_status" value="<?php echo esc_attr( $co_status_norm ); ?>">
                        <input type="hidden" name="co_ret_month" value="<?php echo esc_attr( $co_month ); ?>">
                        <input type="hidden" name="co_ret_day" value="<?php echo esc_attr( $co_day ); ?>">
                        <input type="hidden" name="co_ret_search" value="<?php echo esc_attr( $co_search ); ?>">
                        <select name="co_row_status[<?php echo esc_attr( $co_view_id ); ?>]" style="<?php echo $co_sel_css; ?>flex:1;min-width:150px;">
                            <?php foreach ( $co_all_statuses as $sk => $sl ) : $skn = substr( $sk, 3 ); ?>
                            <option value="<?php echo esc_attr( $skn ); ?>" <?php selected( $v_status, $skn ); ?>><?php echo esc_html( $sl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="co_row_update" value="<?php echo esc_attr( $co_view_id ); ?>" class="ds-store-btn" style="border:0;cursor:pointer;">Update</button>
                    </form>
                </div>
            </div>
        </div>

        <?php else :
            // Date range as timestamps (exact day beats month).
            $co_date = '';
            if ( $co_day ) {
                $co_date = strtotime( $co_day . ' 00:00:00' ) . '...' . strtotime( $co_day . ' 23:59:59' );
            } elseif ( $co_month ) {
                $co_m_start = $co_month . '-01';
                $co_m_end   = date( 'Y-m-t', strtotime( $co_m_start ) );
                $co_date    = strtotime( $co_m_start . ' 00:00:00' ) . '...' . strtotime( $co_m_end . ' 23:59:59' );
            }

            // ── Status breakdown cards (respect the date filter) ──
            $co_counts    = [];
            $co_total_all = 0;
            foreach ( $co_card_map as $cs => $meta ) {
                $args = [ 'status' => $cs, 'limit' => 1, 'paginate' => true, 'return' => 'ids' ];
                if ( $co_date ) $args['date_created'] = $co_date;
                $res            = wc_get_orders( $args );
                $co_counts[$cs] = (int) $res->total;
                $co_total_all  += $co_counts[$cs];
            }

            // ── Full filtered ID list (status + date), newest first ──
            $list_args = [ 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ];
            if ( $co_status_norm ) $list_args['status'] = $co_status_norm;
            if ( $co_date )        $list_args['date_created'] = $co_date;
            $co_ids = wc_get_orders( $list_args );

            // Search (order #, customer, email, phone) via WC search, intersected.
            if ( $co_search !== '' && function_exists( 'wc_order_search' ) ) {
                $co_ids = array_values( array_intersect( $co_ids, (array) wc_order_search( $co_search ) ) );
            }

            // ── Pagination ──
            $co_per_page    = 20;
            $co_total       = count( $co_ids );
            $co_total_pages = max( 1, (int) ceil( $co_total / $co_per_page ) );
            $co_pg          = isset( $_GET['co_pg'] ) ? max( 1, (int) $_GET['co_pg'] ) : 1;
            $co_pg          = min( $co_pg, $co_total_pages );
            $co_page_ids    = array_slice( $co_ids, ( $co_pg - 1 ) * $co_per_page, $co_per_page );

            // Months that have orders (works with or without HPOS).
            global $wpdb;
            $co_hpos_tbl = $wpdb->prefix . 'wc_orders';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $co_hpos_tbl ) ) === $co_hpos_tbl ) {
                $co_months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(date_created_gmt,'%Y-%m') ym FROM {$co_hpos_tbl} WHERE type='shop_order' AND date_created_gmt IS NOT NULL ORDER BY ym DESC" );
            } else {
                $co_months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(post_date,'%Y-%m') ym FROM {$wpdb->posts} WHERE post_type='shop_order' ORDER BY ym DESC" );
            }

            $co_sel_css = 'padding:7px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;cursor:pointer;';
            // Base URL preserving current filters (for cards + pagination).
            $co_base = add_query_arg( [ 'tab' => 'cart', 'co_status' => $co_status_norm, 'co_month' => $co_month, 'co_day' => $co_day, 'co_search' => $co_search ], home_url( '/dashboard/' ) );
        ?>

        <?php if ( isset( $_GET['co_updated'] ) && (int) $_GET['co_updated'] > 0 ) : $co_n = (int) $_GET['co_updated']; ?>
        <div id="co-flash" style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;transition:opacity .4s ease;">✓ <?php echo number_format_i18n( $co_n ); ?> order<?php echo $co_n === 1 ? '' : 's'; ?> updated.</div>
        <script>
        (function(){
            // Strip co_updated from the URL so a refresh doesn't re-show this message.
            if(window.history && history.replaceState){
                var u = new URL(window.location.href);
                u.searchParams.delete('co_updated');
                history.replaceState(null, '', u.toString());
            }
            var f = document.getElementById('co-flash');
            if(f) setTimeout(function(){ f.style.opacity='0'; setTimeout(function(){ f.remove(); }, 450); }, 4000);
        })();
        </script>
        <?php endif; ?>

        <!-- Status cards -->
        <div class="ds-cards" style="margin-bottom:24px;">
            <a class="ds-card" href="<?php echo esc_url( remove_query_arg( 'co_status', $co_base ) ); ?>" style="text-decoration:none;color:inherit;<?php echo $co_status_norm === '' ? 'outline:2px solid var(--green);' : ''; ?>">
                <div class="ds-card-icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
                <div class="ds-card-label">All Orders</div>
                <div class="ds-card-value"><?php echo number_format_i18n( $co_total_all ); ?></div>
                <div class="ds-card-sub"><?php echo $co_day ? esc_html( date_i18n( 'j M Y', strtotime( $co_day ) ) ) : ( $co_month ? esc_html( date_i18n( 'F Y', strtotime( $co_month . '-01' ) ) ) : 'All time' ); ?></div>
            </a>
            <?php foreach ( $co_card_map as $cs => $meta ) : ?>
            <a class="ds-card" href="<?php echo esc_url( add_query_arg( 'co_status', $cs, $co_base ) ); ?>" style="text-decoration:none;color:inherit;<?php echo $co_status_norm === $cs ? 'outline:2px solid ' . esc_attr( $meta[1] ) . ';' : ''; ?>">
                <div class="ds-card-icon" style="background:<?php echo esc_attr( $meta[2] ); ?>;border-color:<?php echo esc_attr( $meta[2] ); ?>;">
                    <svg viewBox="0 0 24 24" style="color:<?php echo esc_attr( $meta[1] ); ?>;"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="ds-card-label"><?php echo esc_html( $meta[0] ); ?></div>
                <div class="ds-card-value"><?php echo number_format_i18n( $co_counts[$cs] ); ?></div>
                <div class="ds-card-sub">Orders <?php echo esc_html( strtolower( $meta[0] ) ); ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>Orders <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( $co_total ); ?> result<?php echo $co_total === 1 ? '' : 's'; ?><?php echo $co_status_norm && isset( $co_all_statuses['wc-' . $co_status_norm] ) ? ' · ' . esc_html( $co_all_statuses['wc-' . $co_status_norm] ) : ''; ?></span></span>
                <form method="get" action="<?php echo esc_url( home_url('/dashboard/') ); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0;">
                    <input type="hidden" name="tab" value="cart">
                    <input type="search" name="co_search" value="<?php echo esc_attr( $co_search ); ?>" placeholder="Search order #, name, email…" style="<?php echo $co_sel_css; ?>min-width:200px;">
                    <select name="co_status" onchange="this.form.submit()" style="<?php echo $co_sel_css; ?>">
                        <option value="">All statuses</option>
                        <?php foreach ( $co_all_statuses as $sk => $sl ) : $skn = substr( $sk, 3 ); ?>
                        <option value="<?php echo esc_attr( $skn ); ?>" <?php selected( $co_status_norm, $skn ); ?>><?php echo esc_html( $sl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="co_month" onchange="this.form.submit()" style="<?php echo $co_sel_css; ?>">
                        <option value="">All months</option>
                        <?php foreach ( (array) $co_months as $mo ) : if ( ! $mo ) continue; ?>
                        <option value="<?php echo esc_attr( $mo ); ?>" <?php selected( $co_month, $mo ); ?>><?php echo esc_html( date_i18n( 'F Y', strtotime( $mo . '-01' ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="co_day" value="<?php echo esc_attr( $co_day ); ?>" onchange="this.form.submit()" title="Filter by exact day (overrides month)" style="<?php echo $co_sel_css; ?>">
                    <button type="submit" class="ds-store-btn" style="border:0;cursor:pointer;">Search</button>
                    <?php if ( $co_search || $co_status_norm || $co_month || $co_day ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'cart', home_url('/dashboard/') ) ); ?>" title="Clear filters" style="color:var(--muted);text-decoration:none;font-size:16px;line-height:1;padding:0 2px;">&times;</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ( empty( $co_page_ids ) ) : ?>
            <p style="padding:24px;color:var(--muted);font-size:13px;">No orders found.</p>
            <?php else : ?>
            <form method="post" id="co-bulk-form">
                <?php wp_nonce_field( 'cart_order_status' ); ?>
                <input type="hidden" name="co_pg" value="<?php echo esc_attr( $co_pg ); ?>">
                <input type="hidden" name="co_ret_status" value="<?php echo esc_attr( $co_status_norm ); ?>">
                <input type="hidden" name="co_ret_month" value="<?php echo esc_attr( $co_month ); ?>">
                <input type="hidden" name="co_ret_day" value="<?php echo esc_attr( $co_day ); ?>">
                <input type="hidden" name="co_ret_search" value="<?php echo esc_attr( $co_search ); ?>">

                <!-- Bulk-action toolbar -->
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--border);">
                    <span id="co-sel-count" style="font-size:12px;color:var(--muted);font-weight:600;min-width:80px;">0 selected</span>
                    <select name="co_bulk_status" style="<?php echo $co_sel_css; ?>">
                        <option value="">Bulk action…</option>
                        <?php foreach ( $co_all_statuses as $sk => $sl ) : $skn = substr( $sk, 3 ); ?>
                        <option value="<?php echo esc_attr( $skn ); ?>">Mark as <?php echo esc_html( $sl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="co_do" value="bulk" class="ds-store-btn" style="border:0;cursor:pointer;">Apply</button>
                </div>

                <table class="ds-table">
                <thead><tr>
                    <th style="width:34px;text-align:center;"><input type="checkbox" class="co-check-all" title="Select all on this page" style="cursor:pointer;"></th>
                    <th>Order</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th style="text-align:right;">Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $co_page_ids as $oid ) :
                    $order = wc_get_order( $oid );
                    if ( ! $order ) continue;
                    $o_status  = $order->get_status(); // no wc- prefix
                    $o_meta    = $co_card_map[ $o_status ] ?? [ ucfirst( $o_status ), '#6b7280', 'rgba(148,163,184,.16)' ];
                    $o_name    = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    if ( $o_name === '' ) $o_name = $order->get_billing_company() ?: '—';
                    $o_email   = $order->get_billing_email();
                    $o_phone   = $order->get_billing_phone();
                    $o_created = $order->get_date_created();
                    $o_items   = $order->get_item_count();
                    $o_href    = add_query_arg( 'co_order', $oid, add_query_arg( 'co_pg', $co_pg, $co_base ) );
                ?>
                <tr class="co-row" data-href="<?php echo esc_url( $o_href ); ?>" style="cursor:pointer;">
                    <td style="text-align:center;"><input type="checkbox" class="co-check" name="co_ids[]" value="<?php echo esc_attr( $oid ); ?>" style="cursor:pointer;"></td>
                    <td>
                        <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank" style="color:var(--green);font-weight:700;text-decoration:none;" title="Open in WP Admin">#<?php echo esc_html( $order->get_order_number() ); ?></a>
                        <div style="font-size:11px;color:var(--muted);"><?php echo number_format_i18n( $o_items ); ?> item<?php echo $o_items === 1 ? '' : 's'; ?></div>
                    </td>
                    <td style="font-size:12px;white-space:nowrap;color:var(--muted);"><?php echo $o_created ? esc_html( $o_created->date_i18n( 'j M Y, H:i' ) ) : '—'; ?></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?php echo esc_html( $o_name ); ?></div>
                        <div style="font-size:11px;color:var(--muted);"><?php echo esc_html( $o_email ?: ( $o_phone ?: '' ) ); ?></div>
                    </td>
                    <td style="text-align:right;font-weight:700;color:var(--green);white-space:nowrap;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                    <td>
                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $o_meta[1] ); ?>;background:<?php echo esc_attr( $o_meta[2] ); ?>;white-space:nowrap;"><?php echo esc_html( shopys_order_status_ui_label( $order, $o_meta[0] ) ); ?></span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <select name="co_row_status[<?php echo esc_attr( $oid ); ?>]" style="<?php echo $co_sel_css; ?>padding:5px 8px;font-size:12px;">
                                <?php foreach ( $co_all_statuses as $sk => $sl ) : $skn = substr( $sk, 3 ); ?>
                                <option value="<?php echo esc_attr( $skn ); ?>" <?php selected( $o_status, $skn ); ?>><?php echo esc_html( $sl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="co_row_update" value="<?php echo esc_attr( $oid ); ?>" class="ds-store-btn" style="border:0;cursor:pointer;padding:6px 12px;font-size:12px;">Update</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </form>

            <!-- Premium confirm modal -->
            <style>
            .co-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;}
            .co-modal.open{opacity:1;visibility:visible;}
            .co-modal__overlay{position:absolute;inset:0;background:rgba(8,12,20,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);}
            .co-modal__card{position:relative;width:100%;max-width:400px;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:28px 26px 22px;box-shadow:0 24px 60px rgba(0,0,0,.4);text-align:center;transform:translateY(10px) scale(.96);opacity:0;transition:transform .24s cubic-bezier(.16,1,.3,1),opacity .24s ease;}
            .co-modal.open .co-modal__card{transform:translateY(0) scale(1);opacity:1;}
            .co-modal__icon{width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;background:rgba(245,158,11,.14);color:#f59e0b;}
            .co-modal__icon svg{width:28px;height:28px;}
            .co-modal__icon.is-danger{background:rgba(239,68,68,.14);color:#ef4444;}
            .co-modal__title{margin:0 0 8px;font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.01em;}
            .co-modal__msg{margin:0 0 22px;font-size:14px;line-height:1.55;color:var(--muted);}
            .co-modal__msg strong{color:var(--text);font-weight:800;}
            .co-modal__actions{display:flex;gap:10px;justify-content:center;}
            .co-modal__btn{flex:1;padding:11px 16px;border-radius:12px;font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;border:1px solid transparent;transition:opacity .15s ease,border-color .15s ease;}
            .co-modal__btn--ghost{background:var(--surface2);border-color:var(--border);color:var(--text);}
            .co-modal__btn--ghost:hover{border-color:var(--muted);}
            .co-modal__btn--primary{background:linear-gradient(135deg,#13e800,#0fb500);color:#04210a;box-shadow:0 6px 16px rgba(19,232,0,.28);}
            .co-modal__btn--primary:hover{opacity:.9;}
            .co-modal__btn--primary.is-danger{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 6px 16px rgba(239,68,68,.3);}
            @media (max-width:480px){ .co-modal__actions{flex-direction:column-reverse;} }
            .co-row:hover{ background:var(--surface2); }
            </style>
            <div class="co-modal" id="co-modal" aria-hidden="true">
                <div class="co-modal__overlay" data-co-close></div>
                <div class="co-modal__card" role="dialog" aria-modal="true" aria-labelledby="co-modal-title">
                    <div class="co-modal__icon" id="co-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    </div>
                    <h3 class="co-modal__title" id="co-modal-title">Apply bulk update</h3>
                    <p class="co-modal__msg" id="co-modal-msg"></p>
                    <div class="co-modal__actions">
                        <button type="button" class="co-modal__btn co-modal__btn--ghost" data-co-close>Cancel</button>
                        <button type="button" class="co-modal__btn co-modal__btn--primary" id="co-modal-confirm">Apply</button>
                    </div>
                </div>
            </div>

            <script>
            (function(){
                var form = document.getElementById('co-bulk-form');
                if(!form) return;
                var all   = form.querySelector('.co-check-all');
                var boxes = [].slice.call(form.querySelectorAll('.co-check'));
                var count = document.getElementById('co-sel-count');
                function refresh(){
                    var n = boxes.filter(function(b){ return b.checked; }).length;
                    if(count) count.textContent = n + ' selected';
                    if(all){ all.checked = n>0 && n===boxes.length; all.indeterminate = n>0 && n<boxes.length; }
                }
                if(all) all.addEventListener('change', function(){ boxes.forEach(function(b){ b.checked = all.checked; }); refresh(); });
                boxes.forEach(function(b){ b.addEventListener('change', refresh); });

                // ── Row click → order detail (ignore clicks on controls/links) ──
                [].slice.call(form.querySelectorAll('.co-row')).forEach(function(row){
                    row.addEventListener('click', function(e){
                        if(e.target.closest('a, button, input, select, label, option')) return;
                        var href = row.getAttribute('data-href');
                        if(href) window.location.href = href;
                    });
                });

                // ── Premium modal ──
                var modal  = document.getElementById('co-modal');
                var mIcon  = document.getElementById('co-modal-icon');
                var mTitle = document.getElementById('co-modal-title');
                var mMsg   = document.getElementById('co-modal-msg');
                var mOk    = document.getElementById('co-modal-confirm');
                var mGhost = modal.querySelector('.co-modal__btn--ghost');
                var DANGER = { failed:1, cancelled:1, refunded:1 };
                var allowSubmit = false;

                function closeModal(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }
                function openModal(o){
                    mTitle.textContent = o.title;
                    mMsg.innerHTML = o.msg;
                    mIcon.className = 'co-modal__icon' + (o.danger ? ' is-danger' : '');
                    if(o.onConfirm){
                        mGhost.style.display = '';
                        mOk.textContent = o.okLabel || 'Confirm';
                        mOk.className = 'co-modal__btn co-modal__btn--primary' + (o.danger ? ' is-danger' : '');
                        mOk.onclick = function(){ closeModal(); o.onConfirm(); };
                    } else {
                        mGhost.style.display = 'none';
                        mOk.textContent = 'Got it';
                        mOk.className = 'co-modal__btn co-modal__btn--primary';
                        mOk.onclick = closeModal;
                    }
                    modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
                }
                modal.querySelectorAll('[data-co-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
                document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

                form.addEventListener('submit', function(e){
                    var btn = e.submitter;
                    if(!(btn && btn.name === 'co_do' && btn.value === 'bulk')) return; // per-row updates pass through
                    if(allowSubmit){ allowSubmit = false; return; }                    // confirmed → let it submit
                    e.preventDefault();
                    var sel = form.querySelector('[name="co_bulk_status"]');
                    var chosen = boxes.filter(function(b){ return b.checked; });
                    if(!chosen.length){ openModal({ title:'No orders selected', msg:'Tick at least one order before applying a bulk action.', danger:true }); return; }
                    if(sel && !sel.value){ openModal({ title:'Choose an action', msg:'Pick a status from the <strong>Bulk action</strong> menu first.', danger:true }); return; }
                    var opt = sel && sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : 'this status';
                    var clean = opt.replace(/^Mark as\s*/i, '');
                    var isDanger = !!DANGER[ sel ? sel.value : '' ];
                    openModal({
                        title: 'Apply bulk update',
                        msg: 'Set <strong>'+ chosen.length +'</strong> selected order'+(chosen.length===1?'':'s')+' to <strong>'+ clean +'</strong>?',
                        okLabel: opt,
                        danger: isDanger,
                        onConfirm: function(){ allowSubmit = true; form.requestSubmit(btn); }
                    });
                });
                refresh();
            })();
            </script>

            <?php if ( $co_total_pages > 1 ) : ?>
            <div class="sv-pagination">
                <?php if ( $co_pg > 1 ) : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'co_pg', $co_pg - 1, $co_base ) ); ?>">&lsaquo; Prev</a><?php else : ?><span class="sv-pag-btn disabled">&lsaquo; Prev</span><?php endif; ?>
                <?php for ( $p = 1; $p <= $co_total_pages; $p++ ) :
                    if ( $p == 1 || $p == $co_total_pages || abs( $p - $co_pg ) <= 2 ) : ?>
                        <?php if ( $p == $co_pg ) : ?><span class="sv-pag-btn active"><?php echo $p; ?></span><?php else : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'co_pg', $p, $co_base ) ); ?>"><?php echo $p; ?></a><?php endif; ?>
                    <?php elseif ( $p == 2 || $p == $co_total_pages - 1 ) : ?><span class="sv-pag-ellipsis">&hellip;</span><?php endif;
                endfor; ?>
                <?php if ( $co_pg < $co_total_pages ) : ?><a class="sv-pag-btn" href="<?php echo esc_url( add_query_arg( 'co_pg', $co_pg + 1, $co_base ) ); ?>">Next &rsaquo;</a><?php else : ?><span class="sv-pag-btn disabled">Next &rsaquo;</span><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; // empty page ?>
        </div>
        <?php endif; // detail vs list ?>
        <?php endif; // wc active ?>
        <?php endif; // active tab cart ?>
        </div>

        <!-- ── PROMOTION PANEL (multiple promotions · dialogs for edit/targets) ── -->
        <div class="ds-panel <?php echo $active_tab === 'promotion' ? 'active' : ''; ?>" id="panel-promotion">
        <?php if ( $active_tab === 'promotion' ) :
            $cp_list  = function_exists( 'shopys_promos_all' ) ? shopys_promos_all() : [];
            $cp_terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
            if ( is_wp_error( $cp_terms ) ) $cp_terms = [];
            $cp_term_names = [];
            foreach ( $cp_terms as $t ) $cp_term_names[ (int) $t->term_id ] = $t->name;

            $cp_now = current_time( 'timestamp' );
            $cp_status_of = function ( $o ) use ( $cp_now ) {
                $has = ! empty( $o['cats'] ) || ! empty( $o['products'] );
                if ( empty( $o['enabled'] ) || ! $has || empty( $o['value'] ) ) return [ 'Off', '#6b7280', 'rgba(148,163,184,.16)' ];
                if ( ! empty( $o['start'] ) && $cp_now < strtotime( $o['start'] . ' 00:00:00' ) ) return [ 'Scheduled', '#b45309', 'rgba(245,158,11,.14)' ];
                if ( ! empty( $o['end'] )   && $cp_now > strtotime( $o['end'] . ' 23:59:59' ) )   return [ 'Expired', '#b91c1c', 'rgba(239,68,68,.14)' ];
                return [ 'Live', '#047857', 'rgba(16,185,129,.14)' ];
            };
            $cp_cur_sym  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
            $cp_fmt_disc = function ( $p ) use ( $cp_cur_sym ) {
                $v = rtrim( rtrim( number_format( (float) ( $p['value'] ?? ( $p['percent'] ?? 0 ) ), 2 ), '0' ), '.' );
                return ( ( $p['dtype'] ?? 'percent' ) === 'fixed' ) ? $cp_cur_sym . $v . ' off' : $v . '% off';
            };
            $cp_in_css = 'padding:9px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:inherit;';

            // Promotion data for the dialogs (definition prefill + current targets)
            $cp_js = [];
            foreach ( $cp_list as $p ) {
                $cp_prods_js = [];
                foreach ( (array) ( $p['products'] ?? [] ) as $ppid ) {
                    $ppid = (int) $ppid;
                    if ( get_post_type( $ppid ) !== 'product' ) continue;
                    $cp_prods_js[] = [ 'id' => $ppid, 'name' => html_entity_decode( get_the_title( $ppid ), ENT_QUOTES ) ];
                }
                $cp_js[] = [
                    'id'       => (string) ( $p['id'] ?? '' ),
                    'name'     => (string) ( $p['name'] ?? 'Promotion' ),
                    'enabled'  => ! empty( $p['enabled'] ),
                    'dtype'    => ( ( $p['dtype'] ?? 'percent' ) === 'fixed' ) ? 'fixed' : 'percent',
                    'value'    => (float) ( $p['value'] ?? 0 ),
                    'start'    => (string) ( $p['start'] ?? '' ),
                    'end'      => (string) ( $p['end'] ?? '' ),
                    'disc'     => $cp_fmt_disc( $p ),
                    'cats'     => array_values( array_map( 'intval', (array) ( $p['cats'] ?? [] ) ) ),
                    'products' => $cp_prods_js,
                ];
            }
        ?>

        <?php if ( isset( $_GET['promo_saved'] ) ) : ?>
        <div style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;">✓ Promotion saved — store prices update immediately.</div>
        <?php elseif ( isset( $_GET['promo_deleted'] ) ) : ?>
        <div style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;">✓ Promotion deleted — its prices are restored.</div>
        <?php elseif ( isset( $_GET['banner_saved'] ) ) : ?>
        <div style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;">✓ Home discount banner saved.</div>
        <?php elseif ( isset( $_GET['checkout_settings_saved'] ) ) : ?>
        <div style="background:#ecfdf3;border:1px solid #abefc6;color:#067647;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;">✓ Checkout settings saved.</div>
        <?php endif; ?>

        <!-- Checkout settings -->
        <?php
            $cs = get_option( 'shopys_checkout_settings' );
            $cs = is_array( $cs ) ? $cs : [];
        ?>
        <div class="ds-table-wrap">
            <div class="ds-table-head">
                <span>Checkout Settings</span>
            </div>
            <form method="post" style="padding:16px;display:flex;flex-wrap:wrap;align-items:center;gap:14px;">
                <?php wp_nonce_field( 'checkout_settings_save' ); ?>
                <input type="hidden" name="checkout_settings_save" value="1">
                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-weight:700;font-size:13px;">
                    <input type="checkbox" name="cs_hide_cod" value="1" <?php checked( ! empty( $cs['hide_cod'] ) ); ?> style="width:17px;height:17px;accent-color:#00c44f;cursor:pointer;">
                    Hide "Pay with Cash" option at checkout
                </label>
                <button type="submit" class="ds-store-btn" style="border:0;cursor:pointer;padding:10px 20px;">Save</button>
            </form>
        </div>

        <!-- Home discount banner config -->
        <?php
            $db = get_option( 'shopys_discount_banner' );
            $db = is_array( $db ) ? $db : [];
        ?>
        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>Home Discount Banner
                    <span style="display:inline-block;margin-left:8px;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;<?php echo ! empty( $db['enabled'] ) ? 'color:#047857;background:rgba(16,185,129,.14);' : 'color:#6b7280;background:rgba(148,163,184,.16);'; ?>"><?php echo ! empty( $db['enabled'] ) ? 'Visible' : 'Hidden'; ?></span>
                </span>
                <span style="font-size:12px;color:var(--muted);">Shown on the home page, under the search bar</span>
            </div>
            <form method="post" style="padding:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <?php wp_nonce_field( 'promo_banner_save' ); ?>
                <input type="hidden" name="promo_banner_save" value="1">
                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-weight:700;font-size:13px;padding-bottom:9px;">
                    <input type="checkbox" name="db_enabled" value="1" <?php checked( ! empty( $db['enabled'] ) ); ?> style="width:17px;height:17px;accent-color:#00c44f;cursor:pointer;">
                    Show banner
                </label>
                <div style="flex:2;min-width:260px;">
                    <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Banner text</div>
                    <input type="text" name="db_text" value="<?php echo esc_attr( $db['text'] ?? '' ); ?>" placeholder="e.g. 🔥 Khmer New Year Sale — up to 20% off!" style="<?php echo $cp_in_css; ?>width:100%;">
                </div>
                <div style="flex:1;min-width:200px;">
                    <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Link <span style="font-weight:400;">(optional)</span></div>
                    <input type="url" name="db_link" value="<?php echo esc_attr( $db['link'] ?? '' ); ?>" placeholder="https://…" style="<?php echo $cp_in_css; ?>width:100%;">
                </div>
                <div style="min-width:130px;">
                    <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Button label</div>
                    <input type="text" name="db_btn" value="<?php echo esc_attr( $db['btn'] ?? '' ); ?>" placeholder="Shop Now" style="<?php echo $cp_in_css; ?>width:100%;">
                </div>
                <div style="min-width:160px;">
                    <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Color theme</div>
                    <select name="db_color" id="db-color-sel" style="<?php echo $cp_in_css; ?>width:100%;cursor:pointer;">
                        <?php $db_color_now = $db['color'] ?? 'dark';
                        foreach ( ( function_exists( 'shopys_disc_banner_presets' ) ? shopys_disc_banner_presets() : [] ) as $ck => $cv ) : ?>
                        <option value="<?php echo esc_attr( $ck ); ?>" <?php selected( $db_color_now, $ck ); ?>><?php echo esc_html( $cv['label'] ?? $ck ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="db-custom-colors" style="display:<?php echo $db_color_now === 'custom' ? 'flex' : 'none'; ?>;gap:10px;align-items:flex-end;">
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Background</div>
                        <input type="color" name="db_bgc" value="<?php echo esc_attr( $db['bgc'] ?? '#0b0f14' ); ?>" style="width:46px;height:37px;padding:2px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                    </div>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;">Accent</div>
                        <input type="color" name="db_acc" value="<?php echo esc_attr( $db['acc'] ?? '#13e800' ); ?>" style="width:46px;height:37px;padding:2px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                    </div>
                </div>
                <script>
                (function(){
                    var sel = document.getElementById('db-color-sel'), cust = document.getElementById('db-custom-colors');
                    if(sel && cust) sel.addEventListener('change', function(){ cust.style.display = sel.value === 'custom' ? 'flex' : 'none'; });
                })();
                </script>
                <button type="submit" class="ds-store-btn" style="border:0;cursor:pointer;padding:10px 20px;">Save Banner</button>
            </form>
        </div>

        <!-- Promotions list -->
        <div class="ds-table-wrap">
            <div class="ds-table-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>Promotions <span style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;"><?php echo number_format_i18n( count( $cp_list ) ); ?> total · best price wins when they overlap</span></span>
                <button type="button" class="ds-store-btn" id="cp-add-btn" style="border:0;cursor:pointer;">+ Add Promotion</button>
            </div>
            <?php if ( empty( $cp_list ) ) : ?>
            <p style="padding:24px;color:var(--muted);font-size:13px;">No promotions yet — click “+ Add Promotion” to create your first one, then use “Set Targets” to pick its categories or products.</p>
            <?php else : ?>
            <table class="ds-table">
                <thead><tr>
                    <th>Name</th>
                    <th>Discount</th>
                    <th>Targets</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $cp_list as $p ) :
                    $p_st    = $cp_status_of( $p );
                    $p_cats  = array_map( 'intval', (array) ( $p['cats'] ?? [] ) );
                    $p_prods = array_map( 'intval', (array) ( $p['products'] ?? [] ) );
                    $p_cat_names = array_filter( array_map( function ( $tid ) use ( $cp_term_names ) { return $cp_term_names[ $tid ] ?? ''; }, $p_cats ) );
                    $p_target_bits = [];
                    if ( $p_cat_names ) {
                        $p_show = array_slice( $p_cat_names, 0, 3 );
                        $p_target_bits[] = implode( ', ', $p_show ) . ( count( $p_cat_names ) > 3 ? ' +' . ( count( $p_cat_names ) - 3 ) : '' );
                    }
                    if ( $p_prods ) $p_target_bits[] = count( $p_prods ) . ' product' . ( count( $p_prods ) === 1 ? '' : 's' );
                    $p_period = ( ! empty( $p['start'] ) || ! empty( $p['end'] ) )
                        ? trim( ( ! empty( $p['start'] ) ? date_i18n( 'j M y', strtotime( $p['start'] ) ) : '…' ) . ' → ' . ( ! empty( $p['end'] ) ? date_i18n( 'j M y', strtotime( $p['end'] ) ) : '…' ) )
                        : 'Always';
                ?>
                <tr>
                    <td style="font-weight:700;">
                        <a href="#" data-cp-edit="<?php echo esc_attr( $p['id'] ?? '' ); ?>" style="color:#0fb500;text-decoration:none;" title="Edit">
                            <?php echo esc_html( $p['name'] ?? 'Promotion' ); ?> &rsaquo;
                        </a>
                    </td>
                    <td style="font-weight:800;color:var(--green);white-space:nowrap;"><?php echo esc_html( $cp_fmt_disc( $p ) ); ?></td>
                    <td style="font-size:12px;color:var(--muted);line-height:1.5;"><?php echo $p_target_bits ? esc_html( implode( ' · ', $p_target_bits ) ) : '— none yet'; ?></td>
                    <td style="font-size:12px;white-space:nowrap;color:var(--muted);"><?php echo esc_html( $p_period ); ?></td>
                    <td><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $p_st[1] ); ?>;background:<?php echo esc_attr( $p_st[2] ); ?>;white-space:nowrap;"><?php echo esc_html( $p_st[0] ); ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <button type="button" data-cp-targets="<?php echo esc_attr( $p['id'] ?? '' ); ?>" style="padding:6px 12px;font-size:12px;font-weight:700;font-family:inherit;color:var(--green);background:rgba(0,196,79,.1);border:1px solid rgba(0,196,79,.3);border-radius:6px;cursor:pointer;white-space:nowrap;">Set Targets</button>
                            <button type="button" class="ds-store-btn" data-cp-edit="<?php echo esc_attr( $p['id'] ?? '' ); ?>" style="border:0;cursor:pointer;padding:6px 12px;font-size:12px;">Edit</button>
                            <form method="post" style="margin:0;" data-cp-del="<?php echo esc_attr( $p['name'] ?? 'Promotion' ); ?>">
                                <?php wp_nonce_field( 'catpromo_save' ); ?>
                                <input type="hidden" name="catpromo_action" value="delete">
                                <input type="hidden" name="cp_id" value="<?php echo esc_attr( $p['id'] ?? '' ); ?>">
                                <button type="submit" style="padding:6px 12px;font-size:12px;font-weight:700;font-family:inherit;color:#ef4444;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:6px;cursor:pointer;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <style>
        /* ── Premium promo dialogs ── */
        .cpm{ position:fixed; inset:0; z-index:100000; display:flex; align-items:center; justify-content:center; padding:20px;
            opacity:0; visibility:hidden; transition:opacity .2s ease, visibility .2s ease; }
        .cpm.open{ opacity:1; visibility:visible; }
        .cpm-overlay{ position:absolute; inset:0; background:rgba(8,12,20,.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
        .cpm-card{ position:relative; width:100%; max-width:560px; max-height:88vh; display:flex; flex-direction:column;
            background:var(--surface); border:1px solid var(--border); border-radius:18px; box-shadow:0 24px 60px rgba(0,0,0,.4);
            transform:translateY(10px) scale(.96); opacity:0; transition:transform .24s cubic-bezier(.16,1,.3,1), opacity .24s ease; }
        .cpm.open .cpm-card{ transform:none; opacity:1; }
        /* Targets dialog: taller so the pickers have room to breathe */
        #cpm-tgt .cpm-card{ min-height:min(600px, 88vh); }
        #cpm-tgt form{ flex:1; min-height:0; }
        #cpm-tgt .cpm-body{ flex:1; }
        .cpm-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:16px 20px; border-bottom:1px solid var(--border); }
        .cpm-head h3{ margin:0; font-size:16px; font-weight:800; letter-spacing:-.01em; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .cpm-x{ width:30px; height:30px; border:0; border-radius:8px; background:var(--surface2); color:var(--muted); font-size:16px; line-height:1; cursor:pointer; flex-shrink:0; }
        .cpm-x:hover{ color:#ef4444; }
        .cpm-body{ padding:18px 20px; overflow-y:auto; display:flex; flex-direction:column; gap:16px; }
        .cpm-foot{ display:flex; gap:10px; justify-content:flex-end; align-items:center; padding:14px 20px; border-top:1px solid var(--border); }
        .cpm-lbl{ font-size:12px; font-weight:700; color:var(--muted); margin-bottom:5px; }
        .cpm-cancel{ padding:9px 16px; font-size:13px; font-weight:700; font-family:inherit; color:var(--text); background:var(--surface2); border:1px solid var(--border); border-radius:8px; cursor:pointer; }
        .cpm-disc-badge{ display:inline-block; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:700; color:var(--green); background:rgba(0,196,79,.12); }
        /* Button loading state (applied on submit) */
        .cp-loading{ pointer-events:none; opacity:.75; }
        .cp-spin{ display:inline-block; width:13px; height:13px; border:2px solid rgba(128,128,128,.35); border-top-color:currentColor; border-radius:50%; animation:cpspin .7s linear infinite; margin-right:7px; vertical-align:-2px; }
        @keyframes cpspin{ to{ transform:rotate(360deg); } }
        /* ── Multi-select pickers (inside the targets dialog) ── */
        .cpms{ position:relative; }
        .cpms-control{ display:flex; align-items:center; flex-wrap:wrap; gap:6px; min-height:44px; padding:7px 38px 7px 9px;
            background:var(--surface2); border:1.5px solid var(--border); border-radius:10px; cursor:pointer; }
        .cpms.open .cpms-control{ border-color:#00c44f; box-shadow:0 0 0 3px rgba(0,196,79,.14); }
        .cpms-chevron{ position:absolute; right:12px; top:14px; width:16px; height:16px; color:var(--muted); pointer-events:none; transition:transform .18s; }
        .cpms.open .cpms-chevron{ transform:rotate(180deg); }
        .cpms-placeholder{ color:var(--muted); font-size:13px; padding-left:4px; }
        .cpms-tag{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px 4px 11px; background:rgba(0,196,79,.12);
            border:1px solid rgba(0,196,79,.35); border-radius:50px; font-size:12px; font-weight:700; color:var(--text); }
        .cpms-tag button{ display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px; border:0; border-radius:50%;
            background:rgba(0,0,0,.12); color:var(--text); font-size:11px; line-height:1; cursor:pointer; padding:0; }
        .cpms-tag button:hover{ background:#ef4444; color:#fff; }
        .cpms-panel{ display:none; position:absolute; z-index:100001; left:0; right:0; top:calc(100% + 6px);
            background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,.18); overflow:hidden; }
        .cpms.open .cpms-panel{ display:block; }
        .cpms-search{ width:100%; padding:11px 14px; border:0; border-bottom:1px solid var(--border); background:transparent;
            color:var(--text); font-size:13px; font-family:inherit; outline:none; }
        .cpms-list{ max-height:200px; overflow-y:auto; padding:6px; }
        .cpms-opt{ display:flex; align-items:center; gap:9px; padding:9px 10px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; color:var(--text); }
        .cpms-opt:hover{ background:var(--surface2); }
        .cpms-opt input{ width:15px; height:15px; accent-color:#00c44f; cursor:pointer; flex-shrink:0; }
        .cpms-opt .cpms-count{ margin-left:auto; color:var(--muted); font-weight:400; font-size:11px; }
        .cpms-opt.hidden{ display:none; }
        .cpms-empty{ padding:12px 14px; color:var(--muted); font-size:12.5px; display:none; }
        </style>

        <!-- Dialog: create / edit promotion -->
        <div class="cpm" id="cpm-def" aria-hidden="true">
            <div class="cpm-overlay" data-cpm-close></div>
            <div class="cpm-card" role="dialog" aria-modal="true">
                <form method="post" style="display:flex;flex-direction:column;min-height:0;">
                    <?php wp_nonce_field( 'catpromo_save' ); ?>
                    <input type="hidden" name="catpromo_action" value="save">
                    <input type="hidden" name="cp_id" id="cp-def-id" value="">
                    <div class="cpm-head">
                        <h3 id="cp-def-title">New Promotion</h3>
                        <button type="button" class="cpm-x" data-cpm-close aria-label="Close">×</button>
                    </div>
                    <div class="cpm-body">
                        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
                            <div style="flex:1;min-width:200px;">
                                <div class="cpm-lbl">Promotion name</div>
                                <input type="text" name="cp_name" id="cp-def-name" placeholder="e.g. Khmer New Year Sale" style="<?php echo $cp_in_css; ?>width:100%;">
                            </div>
                            <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-weight:700;font-size:13px;padding-bottom:9px;">
                                <input type="checkbox" name="cp_enabled" id="cp-def-enabled" value="1" checked style="width:17px;height:17px;accent-color:#00c44f;cursor:pointer;">
                                Enabled
                            </label>
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <div>
                                <div class="cpm-lbl">Discount type</div>
                                <select name="cp_dtype" id="cp-def-dtype" style="<?php echo $cp_in_css; ?>cursor:pointer;">
                                    <option value="percent">Percentage (%)</option>
                                    <option value="fixed">Fixed amount (<?php echo esc_html( $cp_cur_sym ); ?>)</option>
                                </select>
                            </div>
                            <div>
                                <div class="cpm-lbl">Value</div>
                                <input type="number" name="cp_value" id="cp-def-value" value="10" min="0.01" step="0.01" required style="<?php echo $cp_in_css; ?>width:110px;">
                            </div>
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;">
                            <div>
                                <div class="cpm-lbl">Start date <span style="font-weight:400;">(optional)</span></div>
                                <input type="date" name="cp_start" id="cp-def-start" style="<?php echo $cp_in_css; ?>">
                            </div>
                            <div>
                                <div class="cpm-lbl">End date <span style="font-weight:400;">(optional)</span></div>
                                <input type="date" name="cp_end" id="cp-def-end" style="<?php echo $cp_in_css; ?>">
                            </div>
                        </div>
                        <div style="font-size:12px;color:var(--muted);">After saving, use <strong>Set Targets</strong> to choose which categories / products this promotion applies to.</div>
                    </div>
                    <div class="cpm-foot">
                        <button type="button" class="cpm-cancel" data-cpm-close>Cancel</button>
                        <button type="submit" class="ds-store-btn" style="border:0;cursor:pointer;padding:10px 22px;">Save Promotion</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dialog: set targets for ONE promotion (opened from its row) -->
        <div class="cpm" id="cpm-tgt" aria-hidden="true">
            <div class="cpm-overlay" data-cpm-close></div>
            <div class="cpm-card" role="dialog" aria-modal="true">
                <form method="post" style="display:flex;flex-direction:column;min-height:0;">
                    <?php wp_nonce_field( 'catpromo_save' ); ?>
                    <input type="hidden" name="catpromo_action" value="targets">
                    <input type="hidden" name="cp_id" id="cp-tgt-id" value="">
                    <div class="cpm-head">
                        <h3><span id="cp-tgt-name">Set Targets</span> <span class="cpm-disc-badge" id="cp-tgt-disc"></span></h3>
                        <button type="button" class="cpm-x" data-cpm-close aria-label="Close">×</button>
                    </div>
                    <div class="cpm-body">
                        <!-- Separate target types: tabs -->
                        <div style="display:inline-flex;background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:4px;gap:4px;align-self:flex-start;">
                            <button type="button" class="cp-tgt-tab active" data-cp-tab="cats" style="padding:8px 18px;border:0;border-radius:9px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;background:var(--surface);color:var(--text);box-shadow:0 2px 8px rgba(0,0,0,.12);">Categories <span id="cp-tab-cats-n" style="color:var(--green);"></span></button>
                            <button type="button" class="cp-tgt-tab" data-cp-tab="prods" style="padding:8px 18px;border:0;border-radius:9px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;background:transparent;color:var(--muted);">Products <span id="cp-tab-prods-n" style="color:var(--green);"></span></button>
                        </div>
                        <div id="cp-tgt-pane-cats">
                            <div class="cpm-lbl">Categories <span style="font-weight:400;">(select one or more)</span></div>
                            <div class="cpms" id="cpms">
                                <div class="cpms-control" id="cpms-control" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox">
                                    <span class="cpms-placeholder" id="cpms-placeholder">Select categories…</span>
                                    <svg class="cpms-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <div class="cpms-panel">
                                    <input type="text" class="cpms-search" id="cpms-search" placeholder="Search categories…" autocomplete="off">
                                    <div class="cpms-list" id="cpms-list">
                                        <?php foreach ( $cp_terms as $t ) : ?>
                                        <label class="cpms-opt" data-name="<?php echo esc_attr( mb_strtolower( $t->name ) ); ?>">
                                            <input type="checkbox" name="cp_cats[]" value="<?php echo esc_attr( $t->term_id ); ?>" data-label="<?php echo esc_attr( $t->name ); ?>">
                                            <?php echo esc_html( $t->name ); ?>
                                            <span class="cpms-count"><?php echo number_format_i18n( (int) $t->count ); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                        <div class="cpms-empty" id="cpms-empty">No categories match your search.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="cp-tgt-pane-prods" style="display:none;">
                            <div class="cpm-lbl">Specific products <span style="font-weight:400;">(optional — search &amp; add)</span></div>
                            <div class="cpms" id="cpps">
                                <div class="cpms-control" id="cpps-control" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox">
                                    <span class="cpms-placeholder" id="cpps-placeholder">Search &amp; add products…</span>
                                    <svg class="cpms-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <div class="cpms-panel">
                                    <input type="text" class="cpms-search" id="cpps-search" placeholder="Type at least 2 letters to search products…" autocomplete="off">
                                    <div class="cpms-list" id="cpps-list">
                                        <div class="cpms-empty" id="cpps-empty" style="display:block;">Type to search your products.</div>
                                    </div>
                                </div>
                                <span id="cpps-hidden"></span>
                            </div>
                        </div>
                    </div>
                    <div class="cpm-foot">
                        <button type="button" class="cpm-cancel" data-cpm-close>Cancel</button>
                        <button type="submit" class="ds-store-btn" style="border:0;cursor:pointer;padding:10px 22px;">Save Targets</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dialog: confirm promotion delete -->
        <div class="cpm" id="cpm-del" aria-hidden="true">
            <div class="cpm-overlay" data-cpm-close></div>
            <div class="cpm-card" role="dialog" aria-modal="true" style="max-width:400px;text-align:center;">
                <div class="cpm-body" style="align-items:center;padding-top:26px;">
                    <div style="width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(239,68,68,.14);color:#ef4444;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/><path d="M10 11v6M14 11v6"/></svg>
                    </div>
                    <h3 style="margin:4px 0 0;font-size:17px;font-weight:800;letter-spacing:-.01em;">Delete promotion?</h3>
                    <p style="margin:0;font-size:13.5px;line-height:1.55;color:var(--muted);">
                        “<strong id="cp-del-name" style="color:var(--text);"></strong>” will be deleted and its
                        discounts removed from the store immediately.
                    </p>
                </div>
                <div class="cpm-foot" style="justify-content:center;">
                    <button type="button" class="cpm-cancel" data-cpm-close>Cancel</button>
                    <button type="button" id="cp-del-confirm" style="padding:9px 22px;font-size:13px;font-weight:700;font-family:inherit;color:#fff;background:linear-gradient(135deg,#ef4444,#dc2626);border:0;border-radius:8px;cursor:pointer;box-shadow:0 6px 16px rgba(239,68,68,.3);">Yes, delete</button>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var CP_PROMOS = <?php echo wp_json_encode( $cp_js ); ?>;
            function cpFind(id){ for(var i=0;i<CP_PROMOS.length;i++){ if(CP_PROMOS[i].id===id) return CP_PROMOS[i]; } return null; }
            function openM(m){ m.classList.add('open'); m.setAttribute('aria-hidden','false'); }
            function closeM(m){ m.classList.remove('open'); m.setAttribute('aria-hidden','true'); }
            var defM = document.getElementById('cpm-def');
            var tgtM = document.getElementById('cpm-tgt');
            var delM = document.getElementById('cpm-del');
            document.querySelectorAll('[data-cpm-close]').forEach(function(el){
                el.addEventListener('click', function(){ closeM(defM); closeM(tgtM); closeM(delM); });
            });
            document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closeM(defM); closeM(tgtM); closeM(delM); } });

            /* ── Categories picker ── */
            var cpmsRoot = document.getElementById('cpms');
            var cpmsBoxes = [];
            if(cpmsRoot){
                var control = document.getElementById('cpms-control');
                var search  = document.getElementById('cpms-search');
                var list    = document.getElementById('cpms-list');
                var empty   = document.getElementById('cpms-empty');
                var ph      = document.getElementById('cpms-placeholder');
                cpmsBoxes   = [].slice.call(list.querySelectorAll('input[type=checkbox]'));
                var renderTags = function(){
                    cpmsRoot.querySelectorAll('.cpms-tag').forEach(function(t){ t.remove(); });
                    var checked = cpmsBoxes.filter(function(b){ return b.checked; });
                    ph.style.display = checked.length ? 'none' : '';
                    checked.forEach(function(b){
                        var tag = document.createElement('span');
                        tag.className = 'cpms-tag';
                        tag.appendChild(document.createTextNode(b.getAttribute('data-label')));
                        var x = document.createElement('button');
                        x.type='button'; x.textContent='×'; x.setAttribute('aria-label','Remove');
                        x.addEventListener('click', function(e){ e.stopPropagation(); b.checked=false; renderTags(); });
                        tag.appendChild(x);
                        control.insertBefore(tag, ph);
                    });
                    if(window.cpUpdateTabCounts) window.cpUpdateTabCounts();
                };
                var setOpen = function(on){
                    cpmsRoot.classList.toggle('open', on);
                    control.setAttribute('aria-expanded', on?'true':'false');
                    if(on){ search.value=''; filter(); setTimeout(function(){ search.focus(); },10); }
                };
                var filter = function(){
                    var q = search.value.trim().toLowerCase(), hits=0;
                    list.querySelectorAll('.cpms-opt').forEach(function(opt){
                        var hit = !q || opt.getAttribute('data-name').indexOf(q)!==-1;
                        opt.classList.toggle('hidden', !hit);
                        if(hit) hits++;
                    });
                    empty.style.display = hits ? 'none' : 'block';
                };
                control.addEventListener('click', function(){ setOpen(!cpmsRoot.classList.contains('open')); });
                control.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); setOpen(true); } });
                search.addEventListener('input', filter);
                cpmsBoxes.forEach(function(b){ b.addEventListener('change', renderTags); });
                document.addEventListener('click', function(e){ if(!cpmsRoot.contains(e.target)) setOpen(false); });
                window.cpmsSetSelected = function(ids){
                    ids = (ids||[]).map(String);
                    cpmsBoxes.forEach(function(b){ b.checked = ids.indexOf(b.value)!==-1; });
                    renderTags();
                };
                renderTags();
            }

            /* ── Products picker (AJAX search) ── */
            var cppsRoot = document.getElementById('cpps');
            if(cppsRoot){
                var pControl = document.getElementById('cpps-control');
                var pSearch  = document.getElementById('cpps-search');
                var pList    = document.getElementById('cpps-list');
                var pEmpty   = document.getElementById('cpps-empty');
                var pPh      = document.getElementById('cpps-placeholder');
                var pHidden  = document.getElementById('cpps-hidden');
                var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var pNonce   = <?php echo wp_json_encode( wp_create_nonce( 'shopys_promo_search' ) ); ?>;
                var pTimer   = null, pSeq = 0;
                var selectedIds = function(){ return [].slice.call(pHidden.querySelectorAll('input')).map(function(i){ return i.value; }); };
                var renderPTags = function(){
                    cppsRoot.querySelectorAll('.cpms-tag').forEach(function(t){ t.remove(); });
                    var inputs = [].slice.call(pHidden.querySelectorAll('input'));
                    pPh.style.display = inputs.length ? 'none' : '';
                    inputs.forEach(function(inp){
                        var tag = document.createElement('span');
                        tag.className = 'cpms-tag';
                        tag.appendChild(document.createTextNode(inp.getAttribute('data-label') || ('#'+inp.value)));
                        var x = document.createElement('button');
                        x.type='button'; x.textContent='×'; x.setAttribute('aria-label','Remove');
                        x.addEventListener('click', function(e){ e.stopPropagation(); inp.remove(); renderPTags(); });
                        tag.appendChild(x);
                        pControl.insertBefore(tag, pPh);
                    });
                    if(window.cpUpdateTabCounts) window.cpUpdateTabCounts();
                };
                var addProduct = function(id, name){
                    if(selectedIds().indexOf(String(id))!==-1) return;
                    var inp = document.createElement('input');
                    inp.type='hidden'; inp.name='cp_prods[]'; inp.value=id;
                    inp.setAttribute('data-label', name);
                    pHidden.appendChild(inp);
                    renderPTags();
                };
                var showMsg = function(msg){
                    pList.querySelectorAll('.cpms-opt').forEach(function(o){ o.remove(); });
                    pEmpty.textContent = msg; pEmpty.style.display='block';
                };
                var renderResults = function(items){
                    pList.querySelectorAll('.cpms-opt').forEach(function(o){ o.remove(); });
                    if(!items.length){ pEmpty.textContent='No products found.'; pEmpty.style.display='block'; return; }
                    pEmpty.style.display='none';
                    var sel = selectedIds();
                    items.forEach(function(it){
                        var row = document.createElement('div');
                        row.className='cpms-opt';
                        if(sel.indexOf(String(it.id))!==-1) row.style.opacity='.45';
                        row.appendChild(document.createTextNode(it.name));
                        var pr = document.createElement('span');
                        pr.className='cpms-count'; pr.textContent = it.price || '';
                        row.appendChild(pr);
                        row.addEventListener('click', function(){ addProduct(it.id, it.name); row.style.opacity='.45'; });
                        pList.appendChild(row);
                    });
                };
                var doSearch = function(){
                    var q = pSearch.value.trim();
                    if(q.length<2){ showMsg('Type to search your products.'); return; }
                    var mySeq = ++pSeq;
                    showMsg('Searching…');
                    fetch(ajaxUrl + '?action=shopys_promo_search_products&nonce=' + encodeURIComponent(pNonce) + '&q=' + encodeURIComponent(q), {credentials:'same-origin'})
                        .then(function(r){ return r.json(); })
                        .then(function(d){ if(mySeq===pSeq) renderResults((d && d.success && d.data) ? d.data : []); })
                        .catch(function(){ if(mySeq===pSeq) showMsg('Search failed — try again.'); });
                };
                var setPOpen = function(on){
                    cppsRoot.classList.toggle('open', on);
                    pControl.setAttribute('aria-expanded', on?'true':'false');
                    if(on){ setTimeout(function(){ pSearch.focus(); },10); }
                };
                pControl.addEventListener('click', function(){ setPOpen(!cppsRoot.classList.contains('open')); });
                pControl.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); setPOpen(true); } });
                pSearch.addEventListener('input', function(){ clearTimeout(pTimer); pTimer = setTimeout(doSearch, 300); });
                document.addEventListener('click', function(e){ if(!cppsRoot.contains(e.target)) setPOpen(false); });
                window.cppsSetSelected = function(items){
                    [].slice.call(pHidden.querySelectorAll('input')).forEach(function(i){ i.remove(); });
                    (items||[]).forEach(function(it){ addProduct(it.id, it.name); });
                    renderPTags();
                };
                renderPTags();
            }

            /* ── Definition dialog open/prefill ── */
            function openDef(p){
                document.getElementById('cp-def-title').textContent = p ? 'Edit Promotion' : 'New Promotion';
                document.getElementById('cp-def-id').value      = p ? p.id : '';
                document.getElementById('cp-def-name').value    = p ? p.name : '';
                document.getElementById('cp-def-enabled').checked = p ? !!p.enabled : true;
                document.getElementById('cp-def-dtype').value   = p ? p.dtype : 'percent';
                document.getElementById('cp-def-value').value   = p ? p.value : 10;
                document.getElementById('cp-def-start').value   = p ? p.start : '';
                document.getElementById('cp-def-end').value     = p ? p.end : '';
                openM(defM);
            }
            var addBtn = document.getElementById('cp-add-btn');
            if(addBtn) addBtn.addEventListener('click', function(){ openDef(null); });
            document.querySelectorAll('[data-cp-edit]').forEach(function(el){
                el.addEventListener('click', function(e){ e.preventDefault(); openDef(cpFind(el.getAttribute('data-cp-edit'))); });
            });

            /* ── Targets dialog: one promotion, separate Categories / Products tabs ── */
            var tgtId    = document.getElementById('cp-tgt-id');
            var tgtName  = document.getElementById('cp-tgt-name');
            var tgtDisc  = document.getElementById('cp-tgt-disc');
            var tabBtns  = [].slice.call(document.querySelectorAll('.cp-tgt-tab'));
            var paneCats = document.getElementById('cp-tgt-pane-cats');
            var paneProds= document.getElementById('cp-tgt-pane-prods');
            function setTab(which){
                tabBtns.forEach(function(b){
                    var on = b.getAttribute('data-cp-tab') === which;
                    b.classList.toggle('active', on);
                    b.style.background = on ? 'var(--surface)' : 'transparent';
                    b.style.color      = on ? 'var(--text)' : 'var(--muted)';
                    b.style.boxShadow  = on ? '0 2px 8px rgba(0,0,0,.12)' : 'none';
                });
                if(paneCats)  paneCats.style.display  = which === 'cats'  ? '' : 'none';
                if(paneProds) paneProds.style.display = which === 'prods' ? '' : 'none';
            }
            tabBtns.forEach(function(b){ b.addEventListener('click', function(){ setTab(b.getAttribute('data-cp-tab')); }); });
            window.cpUpdateTabCounts = function(){
                var nCats  = document.querySelectorAll('#cpms-list input:checked').length;
                var nProds = document.querySelectorAll('#cpps-hidden input').length;
                var eC = document.getElementById('cp-tab-cats-n'), eP = document.getElementById('cp-tab-prods-n');
                if(eC) eC.textContent = nCats  ? '(' + nCats + ')'  : '';
                if(eP) eP.textContent = nProds ? '(' + nProds + ')' : '';
            };
            function openTgt(id){
                var p = cpFind(id); if(!p) return;
                if(tgtId)   tgtId.value = p.id;
                if(tgtName) tgtName.textContent = 'Set Targets — ' + p.name;
                if(tgtDisc) tgtDisc.textContent = p.disc;
                if(window.cpmsSetSelected) window.cpmsSetSelected(p.cats);
                if(window.cppsSetSelected) window.cppsSetSelected(p.products);
                window.cpUpdateTabCounts();
                setTab('cats');
                openM(tgtM);
            }
            document.querySelectorAll('[data-cp-targets]').forEach(function(el){
                el.addEventListener('click', function(){ openTgt(el.getAttribute('data-cp-targets')); });
            });

            /* ── Delete: premium confirm dialog, then submit with loading ── */
            var delName    = document.getElementById('cp-del-name');
            var delConfirm = document.getElementById('cp-del-confirm');
            var delPending = null, delAllowed = false;
            document.querySelectorAll('#panel-promotion form[data-cp-del]').forEach(function(f){
                f.addEventListener('submit', function(e){
                    if(delAllowed) return; // confirmed — let it through
                    e.preventDefault();
                    delPending = f;
                    if(delName) delName.textContent = f.getAttribute('data-cp-del') || 'Promotion';
                    openM(delM);
                });
            });
            if(delConfirm) delConfirm.addEventListener('click', function(){
                if(!delPending || delConfirm.disabled) return;
                delConfirm.disabled = true;
                delConfirm.classList.add('cp-loading');
                var sp = document.createElement('span');
                sp.className = 'cp-spin';
                delConfirm.insertBefore(sp, delConfirm.firstChild);
                delAllowed = true;
                if(delPending.requestSubmit) delPending.requestSubmit(); else delPending.submit();
            });

            /* ── Loading state on every action button in this panel (save/targets/delete) ── */
            document.querySelectorAll('#panel-promotion form').forEach(function(f){
                f.addEventListener('submit', function(e){
                    var btn = e.submitter || f.querySelector('[type=submit]');
                    if(!btn || btn.disabled) return;
                    setTimeout(function(){
                        if(e.defaultPrevented) return; // e.g. delete intercepted by its dialog
                        btn.disabled = true;
                        btn.classList.add('cp-loading');
                        var sp = document.createElement('span');
                        sp.className = 'cp-spin';
                        btn.insertBefore(sp, btn.firstChild);
                    }, 0);
                });
            });
        })();
        </script>
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
