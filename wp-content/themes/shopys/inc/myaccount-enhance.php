<?php
/**
 * Shopys — My Account premium enhancements
 *
 * Adds icons to the WooCommerce account sidebar, replaces the dashboard
 * content with stat cards, and enriches the hero badge with member-since
 * info. Hooks only fire on the my-account page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── 1. Enqueue premium CSS on my-account ─────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'shopys_myaccount_premium_assets', 30 );
function shopys_myaccount_premium_assets() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;

    $css = get_stylesheet_directory() . '/css/myaccount-premium.css';
    if ( ! file_exists( $css ) ) return;

    wp_enqueue_style(
        'shopys-myaccount-premium',
        get_stylesheet_directory_uri() . '/css/myaccount-premium.css',
        array(),
        filemtime( $css )
    );
}

// ── 2. Add an extra body/li class so we can target by endpoint reliably ──────
add_filter( 'woocommerce_account_menu_item_classes', 'shopys_myaccount_nav_classes', 10, 2 );
function shopys_myaccount_nav_classes( $classes, $endpoint ) {
    $classes[] = 'sai-nav-' . sanitize_html_class( $endpoint );
    return $classes;
}

// ── 3. SVG icons map for the sidebar (used by navigation.php template) ──────
function shopys_myaccount_get_icons() {
    return array(
        'dashboard'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'orders'          => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>',
        'downloads'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'edit-address'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'payment-methods' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
        'edit-account'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>',
        'customer-logout' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'default'         => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    );
}

// ── 4. Premium dashboard renderer (called from dashboard.php template override) ─
function shopys_myaccount_dashboard_premium() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return;

    $user      = wp_get_current_user();
    $name      = $user->display_name ?: $user->user_login;
    $first     = explode( ' ', trim( $name ) )[0];
    $avatar    = get_avatar_url( $user_id, array( 'size' => 96 ) );
    $tg_photo  = get_user_meta( $user_id, 'telegram_photo', true );
    if ( $tg_photo ) $avatar = $tg_photo;

    // Stats
    $orders_count   = wc_get_customer_order_count( $user_id );
    $downloads      = function_exists( 'wc_get_customer_available_downloads' )
        ? wc_get_customer_available_downloads( $user_id )
        : array();
    $download_count = count( $downloads );

    $address_count = 0;
    if ( get_user_meta( $user_id, 'billing_address_1', true ) )  $address_count++;
    if ( get_user_meta( $user_id, 'shipping_address_1', true ) ) $address_count++;

    // Member since
    $registered = $user->user_registered ? date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) : '';

    // Total spent (last 100 orders)
    $total_spent = 0;
    if ( function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'status'      => array( 'completed', 'processing' ),
            'limit'       => 100,
            'return'      => 'objects',
        ) );
        foreach ( $orders as $o ) {
            $total_spent += (float) $o->get_total();
        }
    }

    $orders_url    = wc_get_account_endpoint_url( 'orders' );
    $downloads_url = wc_get_account_endpoint_url( 'downloads' );
    $address_url   = wc_get_account_endpoint_url( 'edit-address' );
    $account_url   = wc_get_account_endpoint_url( 'edit-account' );
    $shop_url      = home_url( '/' );
    ?>

    <div class="sai-dash">
        <div class="sai-dash-welcome">
            <img class="sai-dash-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
            <div class="sai-dash-welcome-text">
                <h2 class="sai-dash-hello"><?php echo esc_html( sprintf( __( 'Hello, %s 👋', 'shopys' ), $first ) ); ?></h2>
                <p class="sai-dash-sub"><?php esc_html_e( 'Here\'s a snapshot of your account activity.', 'shopys' ); ?></p>
                <?php if ( $registered ) : ?>
                <div class="sai-dash-since"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php printf( esc_html__( 'Member since %s', 'shopys' ), esc_html( $registered ) ); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sai-dash-stats">
            <a href="<?php echo esc_url( $orders_url ); ?>" class="sai-stat sai-stat-1">
                <div class="sai-stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg></div>
                <div class="sai-stat-num"><?php echo intval( $orders_count ); ?></div>
                <div class="sai-stat-label"><?php esc_html_e( 'Orders', 'shopys' ); ?></div>
            </a>

            <a href="<?php echo esc_url( $downloads_url ); ?>" class="sai-stat sai-stat-2">
                <div class="sai-stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
                <div class="sai-stat-num"><?php echo intval( $download_count ); ?></div>
                <div class="sai-stat-label"><?php esc_html_e( 'Downloads', 'shopys' ); ?></div>
            </a>

            <a href="<?php echo esc_url( $address_url ); ?>" class="sai-stat sai-stat-3">
                <div class="sai-stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                <div class="sai-stat-num"><?php echo intval( $address_count ); ?></div>
                <div class="sai-stat-label"><?php esc_html_e( 'Saved Addresses', 'shopys' ); ?></div>
            </a>

            <div class="sai-stat sai-stat-4 sai-stat--static">
                <div class="sai-stat-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="sai-stat-num"><?php echo wp_kses_post( wc_price( $total_spent ) ); ?></div>
                <div class="sai-stat-label"><?php esc_html_e( 'Total Spent', 'shopys' ); ?></div>
            </div>
        </div>

        <div class="sai-dash-actions">
            <h3 class="sai-dash-section-title"><?php esc_html_e( 'Quick Actions', 'shopys' ); ?></h3>
            <div class="sai-dash-actions-grid">
                <a href="<?php echo esc_url( $shop_url ); ?>" class="sai-action">
                    <div class="sai-action-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg></div>
                    <div>
                        <strong><?php esc_html_e( 'Continue Shopping', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'Browse the latest products', 'shopys' ); ?></small>
                    </div>
                </a>
                <a href="<?php echo esc_url( $orders_url ); ?>" class="sai-action">
                    <div class="sai-action-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
                    <div>
                        <strong><?php esc_html_e( 'Track an Order', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'View status and history', 'shopys' ); ?></small>
                    </div>
                </a>
                <a href="<?php echo esc_url( $address_url ); ?>" class="sai-action">
                    <div class="sai-action-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                    <div>
                        <strong><?php esc_html_e( 'Update Address', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'Billing & shipping details', 'shopys' ); ?></small>
                    </div>
                </a>
                <a href="<?php echo esc_url( $account_url ); ?>" class="sai-action">
                    <div class="sai-action-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.85 1.18 1.42 2.05 1.51H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
                    <div>
                        <strong><?php esc_html_e( 'Account Details', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'Name, email, password', 'shopys' ); ?></small>
                    </div>
                </a>
            </div>
        </div>

        <?php
        // Recent orders snapshot
        if ( $orders_count > 0 && function_exists( 'wc_get_orders' ) ) :
            $recent = wc_get_orders( array(
                'customer_id' => $user_id,
                'limit'       => 3,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ) );
            if ( ! empty( $recent ) ) :
        ?>
        <div class="sai-dash-recent">
            <div class="sai-dash-recent-head">
                <h3 class="sai-dash-section-title"><?php esc_html_e( 'Recent Orders', 'shopys' ); ?></h3>
                <a class="sai-dash-recent-all" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'View all', 'shopys' ); ?> →</a>
            </div>
            <div class="sai-dash-recent-list">
                <?php foreach ( $recent as $o ) :
                    $order_url = $o->get_view_order_url();
                    $status    = wc_get_order_status_name( $o->get_status() );
                    $items     = $o->get_item_count();
                    $date      = wc_format_datetime( $o->get_date_created() );
                ?>
                <a href="<?php echo esc_url( $order_url ); ?>" class="sai-recent-item">
                    <div class="sai-recent-info">
                        <div class="sai-recent-id">#<?php echo esc_html( $o->get_order_number() ); ?></div>
                        <div class="sai-recent-meta"><?php echo esc_html( $date ); ?> · <?php echo intval( $items ); ?> <?php esc_html_e( 'items', 'shopys' ); ?></div>
                    </div>
                    <div class="sai-recent-side">
                        <span class="sai-recent-status sai-status-<?php echo esc_attr( $o->get_status() ); ?>"><?php echo esc_html( $status ); ?></span>
                        <span class="sai-recent-total"><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; endif; ?>
    </div>
    <?php
}

// ── 5. Replace the my-account hero badge with member-since info ──────────────
add_filter( 'shopys_account_hero_badge', '__return_null' ); // optional hook for future use
