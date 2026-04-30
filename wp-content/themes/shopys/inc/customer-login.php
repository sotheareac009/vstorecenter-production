<?php
/**
 * Shopys — Customer Shop Login / Register Button
 *
 * Adds a fixed-position floating button that lets visitors log in or register
 * for online shopping (WooCommerce my-account). Sits next to the Telegram
 * login button. Hidden for administrators (they use /vstore-admin/).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── 1. Enqueue CSS ───────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'shopys_customer_login_assets' );
function shopys_customer_login_assets() {
    $css = get_stylesheet_directory() . '/css/customer-login.css';
    if ( ! file_exists( $css ) ) return;
    wp_enqueue_style(
        'shopys-customer-login',
        get_stylesheet_directory_uri() . '/css/customer-login.css',
        array(),
        filemtime( $css )
    );
}

// ── 2a. On my-account page, apply premium styling with tabbed Login/Register UI
//        Default tab = login. ?action=register opens register tab on first paint.
add_action( 'wp_footer', 'shopys_customer_register_focus', 70 );
function shopys_customer_register_focus() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( is_user_logged_in() ) return;

    $raw = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    $action = ( $raw === 'register' ) ? 'register' : 'login';
    ?>
    <style>
    /* ── Premium auth-page background ─────────────────────── */
    body.shopys-auth-mode {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #d1fae5 100%) !important;
        min-height: 100vh;
    }
    body.shopys-auth-mode .woocommerce,
    body.shopys-auth-mode .woocommerce-account .woocommerce {
        max-width: 1100px;
        margin: 0 auto;
        padding: 60px 20px 80px;
    }
    body.shopys-auth-mode .woocommerce-notices-wrapper {
        max-width: 480px;
        margin: 0 auto 16px;
    }
    /* Hide the product search bar on auth pages — it's only for shopping */
    body.shopys-auth-mode .aps-wrapper,
    body.shopys-auth-mode #aps-wrapper {
        display: none !important;
    }

    /* ── Single-card tabbed layout ────────────────────────── */
    body.shopys-auth-mode #customer_login,
    body.shopys-auth-mode .u-columns#customer_login,
    body.shopys-auth-mode .u-columns.col2-set#customer_login {
        display: block !important;
        max-width: 480px !important;
        margin: 0 auto !important;
    }
    body.shopys-auth-mode #customer_login::before,
    body.shopys-auth-mode #customer_login::after {
        display: none !important;
        content: none !important;
    }
    /* Move register column INTO the same visual card as login */
    body.shopys-auth-mode #customer_login .u-column1,
    body.shopys-auth-mode #customer_login .u-column2,
    body.shopys-auth-mode #customer_login .col-1,
    body.shopys-auth-mode #customer_login .col-2 {
        float: none !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    /* Hide the original WooCommerce h2 headings — we render our own */
    body.shopys-auth-mode #customer_login .u-column1 > h2,
    body.shopys-auth-mode #customer_login .u-column2 > h2,
    body.shopys-auth-mode #customer_login .col-1 > h2,
    body.shopys-auth-mode #customer_login .col-2 > h2 {
        display: none !important;
    }
    /* Hide the column ::before icon inside the form columns (we add icon at the card level) */
    body.shopys-auth-mode .u-column1::before,
    body.shopys-auth-mode .u-column2::before,
    body.shopys-auth-mode .col-1::before,
    body.shopys-auth-mode .col-2::before {
        display: none !important;
    }

    /* Tab-state visibility */
    body.shopys-auth-login #customer_login .u-column2,
    body.shopys-auth-login #customer_login .col-2,
    body.shopys-auth-login .woocommerce-form-register,
    body.shopys-auth-login form.register {
        display: none !important;
    }
    body.shopys-auth-register #customer_login .u-column1,
    body.shopys-auth-register #customer_login .col-1,
    body.shopys-auth-register .woocommerce-form-login,
    body.shopys-auth-register form.login {
        display: none !important;
    }

    /* ── Header injected at top of card (icon, title, sub, tabs) ── */
    body.shopys-auth-mode .shopys-auth-head {
        margin-bottom: 4px;
    }
    body.shopys-auth-mode .shopys-auth-card-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 14px;
        border-radius: 16px;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        box-shadow: 0 10px 24px rgba(34, 197, 94, 0.32);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        transition: transform 0.25s ease;
    }
    body.shopys-auth-mode .shopys-auth-card-title {
        text-align: center;
        font-size: 26px;
        font-weight: 800;
        color: #111827;
        margin: 0 0 6px;
        letter-spacing: -0.02em;
    }
    body.shopys-auth-mode .shopys-auth-card-sub {
        text-align: center;
        font-size: 14px;
        color: #6b7280;
        margin: 0 0 22px;
    }

    /* ── Tabs ─────────────────────────────────────────────── */
    body.shopys-auth-mode .shopys-auth-tabs {
        display: flex;
        background: #f3f4f6;
        border-radius: 12px;
        padding: 4px;
        margin: 0 0 24px;
        position: relative;
        gap: 4px;
    }
    body.shopys-auth-mode .shopys-auth-tab {
        flex: 1;
        padding: 10px 12px;
        text-align: center;
        font-size: 14px;
        font-weight: 600;
        color: #6b7280;
        background: transparent;
        border: none;
        border-radius: 9px;
        cursor: pointer;
        transition: color 0.2s, background 0.2s, box-shadow 0.2s;
        position: relative;
        z-index: 1;
        font-family: inherit;
    }
    body.shopys-auth-mode .shopys-auth-tab:hover {
        color: #111827;
    }
    body.shopys-auth-mode .shopys-auth-tab.is-active {
        color: #16a34a;
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    /* ── Wrap #customer_login as the premium card ─────────── */
    body.shopys-auth-mode #customer_login,
    body.shopys-auth-mode .u-columns#customer_login,
    body.shopys-auth-mode .u-columns.col2-set#customer_login {
        background: #ffffff !important;
        border-radius: 18px !important;
        padding: 36px 32px 28px !important;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08), 0 4px 12px rgba(15, 23, 42, 0.04) !important;
        border: 1px solid rgba(0, 0, 0, 0.04) !important;
    }

    body.shopys-auth-mode .woocommerce-form-login,
    body.shopys-auth-mode .woocommerce-form-register,
    body.shopys-auth-mode form.login,
    body.shopys-auth-mode form.register {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    /* ── Form labels & inputs ─────────────────────────────── */
    body.shopys-auth-mode .woocommerce form .form-row label,
    body.shopys-auth-mode form.login label,
    body.shopys-auth-mode form.register label {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #374151 !important;
        margin-bottom: 6px !important;
        display: block !important;
    }
    body.shopys-auth-mode .woocommerce form .form-row {
        margin-bottom: 16px !important;
        padding: 0 !important;
    }
    body.shopys-auth-mode .woocommerce form .form-row input.input-text,
    body.shopys-auth-mode form.login input.input-text,
    body.shopys-auth-mode form.register input.input-text,
    body.shopys-auth-mode .woocommerce-Input {
        width: 100% !important;
        height: 48px !important;
        padding: 12px 14px !important;
        font-size: 15px !important;
        color: #111827 !important;
        background: #f9fafb !important;
        border: 1.5px solid #e5e7eb !important;
        border-radius: 10px !important;
        box-shadow: none !important;
        transition: border-color 0.15s, background 0.15s, box-shadow 0.15s !important;
        box-sizing: border-box !important;
    }
    body.shopys-auth-mode .woocommerce form .form-row input.input-text:focus,
    body.shopys-auth-mode form.login input.input-text:focus,
    body.shopys-auth-mode form.register input.input-text:focus {
        border-color: #16a34a !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.15) !important;
        outline: none !important;
    }

    /* ── Password show/hide eye ───────────────────────────── */
    body.shopys-auth-mode .woocommerce form .password-input {
        position: relative !important;
        display: block !important;
        width: 100% !important;
    }
    body.shopys-auth-mode .woocommerce form .password-input input.input-text,
    body.shopys-auth-mode form.register .password-input input.input-text,
    body.shopys-auth-mode form.login .password-input input.input-text {
        padding-right: 46px !important;
        width: 100% !important;
    }
    body.shopys-auth-mode .woocommerce form .show-password-input {
        position: absolute !important;
        right: 14px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 22px !important;
        height: 22px !important;
        padding: 0 !important;
        margin: 0 !important;
        cursor: pointer !important;
        color: #6b7280 !important;
        background: transparent !important;
        border: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: color 0.15s !important;
    }
    body.shopys-auth-mode .woocommerce form .show-password-input:hover {
        color: #16a34a !important;
    }
    body.shopys-auth-mode .woocommerce form .show-password-input::before {
        font-family: dashicons !important;
        content: "\f177" !important;
        font-size: 18px !important;
        line-height: 1 !important;
    }
    body.shopys-auth-mode .woocommerce form .show-password-input.display-password::before {
        content: "\f530" !important;
    }

    /* Description text under fields */
    body.shopys-auth-mode .woocommerce-form-register em,
    body.shopys-auth-mode form.register em,
    body.shopys-auth-mode .woocommerce-privacy-policy-text,
    body.shopys-auth-mode .woocommerce-privacy-policy-text p {
        font-size: 12px !important;
        color: #6b7280 !important;
        font-style: normal !important;
        line-height: 1.5 !important;
        display: block !important;
        margin-top: 6px !important;
    }
    body.shopys-auth-mode .woocommerce-privacy-policy-text {
        margin: 16px 0 0 !important;
        padding: 12px 14px !important;
        background: #f9fafb !important;
        border-radius: 8px !important;
    }

    /* ── Buttons ──────────────────────────────────────────── */
    body.shopys-auth-mode .woocommerce form .button,
    body.shopys-auth-mode form.login button[type=submit],
    body.shopys-auth-mode form.register button[type=submit],
    body.shopys-auth-mode .woocommerce-form-login__submit,
    body.shopys-auth-mode .woocommerce-form-register__submit {
        width: 100% !important;
        height: 50px !important;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
        color: #fff !important;
        font-size: 15px !important;
        font-weight: 700 !important;
        border: none !important;
        border-radius: 10px !important;
        cursor: pointer !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        margin-top: 8px !important;
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.35) !important;
        transition: transform 0.15s, box-shadow 0.2s, background 0.2s !important;
    }
    body.shopys-auth-mode .woocommerce form .button:hover,
    body.shopys-auth-mode form.login button[type=submit]:hover,
    body.shopys-auth-mode form.register button[type=submit]:hover {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
        box-shadow: 0 10px 28px rgba(34, 197, 94, 0.45) !important;
        transform: translateY(-1px);
    }
    body.shopys-auth-mode .woocommerce form .button:active {
        transform: translateY(0);
    }

    /* ── Remember-me row + lost password ──────────────────── */
    body.shopys-auth-mode .woocommerce-form-login__rememberme,
    body.shopys-auth-mode form.login label.woocommerce-form__label-for-checkbox {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-size: 13px !important;
        color: #374151 !important;
        font-weight: 500 !important;
        margin: 0 !important;
        cursor: pointer;
    }
    body.shopys-auth-mode .woocommerce-form-login__rememberme input[type=checkbox],
    body.shopys-auth-mode form.login input[type=checkbox] {
        width: 16px !important;
        height: 16px !important;
        accent-color: #16a34a;
        margin: 0 !important;
    }
    body.shopys-auth-mode .lost_password {
        margin: 18px 0 0 !important;
        text-align: center;
    }
    body.shopys-auth-mode .lost_password a {
        font-size: 13px;
        color: #16a34a !important;
        font-weight: 600;
        text-decoration: none;
    }
    body.shopys-auth-mode .lost_password a:hover {
        text-decoration: underline;
    }

    /* Mobile */
    @media (max-width: 640px) {
        body.shopys-auth-mode .woocommerce,
        body.shopys-auth-mode .woocommerce-account .woocommerce {
            padding: 30px 14px 60px;
        }
        body.shopys-auth-mode #customer_login {
            padding: 28px 20px 22px !important;
        }
        body.shopys-auth-mode .shopys-auth-card-title {
            font-size: 22px;
        }
    }
    </style>
    <script>
    (function(){
        var initialTab = <?php echo wp_json_encode( $action ); ?>; // 'login' or 'register'

        // Tag <body> immediately so CSS can hide the inactive form before paint
        function tagBody(tab){
            if (!document.body) return;
            document.body.classList.add('shopys-auth-mode');
            document.body.classList.remove('shopys-auth-login', 'shopys-auth-register');
            document.body.classList.add(tab === 'register' ? 'shopys-auth-register' : 'shopys-auth-login');
        }
        if (document.body) { tagBody(initialTab); }
        else { document.addEventListener('DOMContentLoaded', function(){ tagBody(initialTab); }); }

        var TXT = {
            login_h: '<?php echo esc_js( __( 'Welcome Back', 'shopys' ) ); ?>',
            login_s: '<?php echo esc_js( __( 'Log in to continue shopping', 'shopys' ) ); ?>',
            reg_h:   '<?php echo esc_js( __( 'Create Account', 'shopys' ) ); ?>',
            reg_s:   '<?php echo esc_js( __( 'Join us — start shopping today', 'shopys' ) ); ?>',
            tab_login: '<?php echo esc_js( __( 'Login', 'shopys' ) ); ?>',
            tab_reg:   '<?php echo esc_js( __( 'Register', 'shopys' ) ); ?>'
        };

        var ICON_USER = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        var ICON_USER_PLUS = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>';

        document.addEventListener('DOMContentLoaded', function(){
            var card = document.getElementById('customer_login');
            if (!card) return;

            // Build the header (icon + title + subtitle + tabs) and prepend it
            if (!card.querySelector('.shopys-auth-card-icon')) {
                var head = document.createElement('div');
                head.className = 'shopys-auth-head';
                head.innerHTML =
                    '<div class="shopys-auth-card-icon" data-icon-user-plus="0">' + ICON_USER + '</div>' +
                    '<h1 class="shopys-auth-card-title"></h1>' +
                    '<p class="shopys-auth-card-sub"></p>' +
                    '<div class="shopys-auth-tabs" role="tablist">' +
                        '<button type="button" class="shopys-auth-tab" data-tab="login" role="tab">' + TXT.tab_login + '</button>' +
                        '<button type="button" class="shopys-auth-tab" data-tab="register" role="tab">' + TXT.tab_reg + '</button>' +
                    '</div>';
                card.insertBefore(head, card.firstChild);
            }

            var titleEl = card.querySelector('.shopys-auth-card-title');
            var subEl   = card.querySelector('.shopys-auth-card-sub');
            var iconEl  = card.querySelector('.shopys-auth-card-icon');
            var tabs    = card.querySelectorAll('.shopys-auth-tab');

            function setTab(tab) {
                tab = (tab === 'register') ? 'register' : 'login';
                tagBody(tab);
                // Update title/subtitle/icon
                titleEl.textContent = (tab === 'register') ? TXT.reg_h : TXT.login_h;
                subEl.textContent   = (tab === 'register') ? TXT.reg_s : TXT.login_s;
                iconEl.innerHTML    = (tab === 'register') ? ICON_USER_PLUS : ICON_USER;
                // Active tab styling
                tabs.forEach(function(t){
                    t.classList.toggle('is-active', t.getAttribute('data-tab') === tab);
                    t.setAttribute('aria-selected', t.getAttribute('data-tab') === tab ? 'true' : 'false');
                });
                // Update URL without reload (so refresh keeps the tab, sharable links work)
                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('action', tab);
                    window.history.replaceState({}, '', url.toString());
                }
                // Focus first input in the active form
                var activeForm = (tab === 'register')
                    ? card.querySelector('.woocommerce-form-register, form.register')
                    : card.querySelector('.woocommerce-form-login, form.login');
                if (activeForm) {
                    var firstInput = activeForm.querySelector('input[type="text"], input[type="email"]');
                    if (firstInput) setTimeout(function(){ firstInput.focus(); }, 80);
                }
            }

            // Wire tab clicks
            tabs.forEach(function(t){
                t.addEventListener('click', function(){
                    setTab(t.getAttribute('data-tab'));
                });
            });

            // Initial state
            setTab(initialTab);
        });
    })();
    </script>
    <?php
}

// ── 2. Render the Login / Account button in the footer ───────────────────────
add_action( 'wp_footer', 'shopys_render_customer_login_button', 60 );

function shopys_render_customer_login_button() {
    if ( ! class_exists( 'WooCommerce' ) ) return;

    // Allow disabling via option
    if ( get_option( 'shopys_show_customer_login_btn', '1' ) === '0' ) return;

    $myaccount_id = get_option( 'woocommerce_myaccount_page_id' );
    if ( ! $myaccount_id ) return;
    $myaccount_url = get_permalink( $myaccount_id );

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();

        // Hide for admin / shop manager — they have other entry points
        if ( array_intersect( array( 'administrator', 'shop_manager' ), (array) $user->roles ) ) {
            return;
        }

        $name        = $user->display_name ?: $user->user_login;
        $first       = explode( ' ', trim( $name ) )[0];
        $avatar_url  = get_avatar_url( $user->ID, array( 'size' => 64 ) );
        $tg_photo    = get_user_meta( $user->ID, 'telegram_photo', true );
        if ( $tg_photo ) $avatar_url = $tg_photo;

        $orders_url   = wc_get_account_endpoint_url( 'orders' );
        $address_url  = wc_get_account_endpoint_url( 'edit-address' );
        $logout_url   = wp_logout_url( home_url( '/' ) );
        $wishlist_url = '';
        if ( function_exists( 'YITH_WCWL' ) && method_exists( YITH_WCWL(), 'get_wishlist_url' ) ) {
            $wishlist_url = YITH_WCWL()->get_wishlist_url();
        }
        ?>
        <div class="shopys-cust-wrap" id="shopys-cust-wrap">
            <button class="shopys-cust-btn shopys-cust-btn-user" id="shopys-cust-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="shopys-cust-avatar" />
                <span class="shopys-cust-name"><?php echo esc_html( $first ); ?></span>
                <svg class="shopys-cust-chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="shopys-cust-dd" id="shopys-cust-dd" role="menu">
                <div class="shopys-cust-dd-head">
                    <img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="shopys-cust-dd-avatar" />
                    <div class="shopys-cust-dd-meta">
                        <div class="shopys-cust-dd-name"><?php echo esc_html( $name ); ?></div>
                        <div class="shopys-cust-dd-email"><?php echo esc_html( $user->user_email ); ?></div>
                    </div>
                </div>
                <a href="<?php echo esc_url( $myaccount_url ); ?>" class="shopys-cust-dd-item" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    <?php esc_html_e( 'My Account', 'shopys' ); ?>
                </a>
                <a href="<?php echo esc_url( $orders_url ); ?>" class="shopys-cust-dd-item" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
                    <?php esc_html_e( 'My Orders', 'shopys' ); ?>
                </a>
                <a href="<?php echo esc_url( $address_url ); ?>" class="shopys-cust-dd-item" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?php esc_html_e( 'Addresses', 'shopys' ); ?>
                </a>
                <?php if ( $wishlist_url ) : ?>
                <a href="<?php echo esc_url( $wishlist_url ); ?>" class="shopys-cust-dd-item" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <?php esc_html_e( 'Wishlist', 'shopys' ); ?>
                </a>
                <?php endif; ?>
                <div class="shopys-cust-dd-sep"></div>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="shopys-cust-dd-item shopys-cust-dd-logout" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <?php esc_html_e( 'Logout', 'shopys' ); ?>
                </a>
            </div>
        </div>
        <?php
    } else {
        // Build register URL — #register anchor scrolls to the WooCommerce register form
        $login_url    = add_query_arg( 'action', 'login', $myaccount_url ) . '#customer_login';
        $register_url = add_query_arg( 'action', 'register', $myaccount_url ) . '#register';
        ?>
        <div class="shopys-cust-wrap" id="shopys-cust-wrap">
            <button class="shopys-cust-btn shopys-cust-btn-login" id="shopys-cust-trigger" type="button" aria-haspopup="true" aria-expanded="false" title="<?php esc_attr_e( 'Login or create an account to shop', 'shopys' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="shopys-cust-btn-text"><?php esc_html_e( 'Login / Register', 'shopys' ); ?></span>
                <svg class="shopys-cust-chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="shopys-cust-dd" id="shopys-cust-dd" role="menu">
                <div class="shopys-cust-dd-title"><?php esc_html_e( 'Shop with your account', 'shopys' ); ?></div>
                <a href="<?php echo esc_url( $login_url ); ?>" class="shopys-cust-dd-item" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <div class="shopys-cust-dd-text">
                        <strong><?php esc_html_e( 'Login', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'Already a customer', 'shopys' ); ?></small>
                    </div>
                </a>
                <a href="<?php echo esc_url( $register_url ); ?>" class="shopys-cust-dd-item shopys-cust-dd-register" role="menuitem">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <div class="shopys-cust-dd-text">
                        <strong><?php esc_html_e( 'Create Account', 'shopys' ); ?></strong>
                        <small><?php esc_html_e( 'New customer', 'shopys' ); ?></small>
                    </div>
                </a>
            </div>
        </div>
        <?php
    }
    ?>
    <script>
    (function(){
        var btn = document.getElementById('shopys-cust-trigger');
        var dd  = document.getElementById('shopys-cust-dd');
        if (!btn || !dd) return;
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            var open = dd.classList.toggle('shopys-cust-dd--open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function(e){
            if (!dd.contains(e.target) && !btn.contains(e.target)) {
                dd.classList.remove('shopys-cust-dd--open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                dd.classList.remove('shopys-cust-dd--open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
    <?php
}
