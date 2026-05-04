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

// ── 1b. Capture WooCommerce notices BEFORE the login form prints them, so we
//        can render them as a toast in the footer instead of an inline list.
//        Runs at priority 5 — before woocommerce_output_all_notices (priority 10).
add_action( 'woocommerce_before_customer_login_form', 'shopys_capture_auth_notices', 5 );
function shopys_capture_auth_notices() {
    if ( is_user_logged_in() || ! function_exists( 'wc_get_notices' ) ) return;
    $errors  = wc_get_notices( 'error' );
    $success = wc_get_notices( 'success' );
    $info    = wc_get_notices( 'notice' );
    if ( empty( $errors ) && empty( $success ) && empty( $info ) ) return;
    $GLOBALS['shopys_auth_notices'] = compact( 'errors', 'success', 'info' );
    wc_clear_notices();
}

// ── 1c. On successful registration, append ?registered=1 so the success
//        dialog can fire on the dashboard after WooCommerce redirects.
add_filter( 'woocommerce_registration_redirect', 'shopys_register_success_redirect' );
function shopys_register_success_redirect( $url ) {
    if ( ! $url ) {
        $url = wc_get_page_permalink( 'myaccount' );
    }
    return add_query_arg( 'registered', '1', $url );
}

// ── 2a. On my-account page, apply premium styling with tabbed Login/Register UI
//        Default tab = login. ?action=register opens register tab on first paint.
add_action( 'wp_footer', 'shopys_customer_register_focus', 70 );
function shopys_customer_register_focus() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( is_user_logged_in() ) return;

    $raw = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    $action = ( $raw === 'register' ) ? 'register' : 'login';

    // Pull captured notices (set by shopys_capture_auth_notices) for toast rendering.
    $captured = isset( $GLOBALS['shopys_auth_notices'] ) ? $GLOBALS['shopys_auth_notices'] : array();
    $toast_payload = array();
    foreach ( array( 'errors' => 'error', 'info' => 'info', 'success' => 'success' ) as $key => $type ) {
        if ( empty( $captured[ $key ] ) ) continue;
        foreach ( $captured[ $key ] as $note ) {
            $msg = is_array( $note ) && isset( $note['notice'] ) ? $note['notice'] : (string) $note;
            $msg = wp_strip_all_tags( $msg );
            if ( $msg !== '' ) $toast_payload[] = array( 'type' => $type, 'msg' => $msg );
        }
    }
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

    /* ── Toast (error / info / success on auth pages) ─────── */
    .shopys-toast-stack {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 100000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: calc(100vw - 48px);
        pointer-events: none;
    }
    .shopys-toast {
        pointer-events: auto;
        min-width: 280px;
        max-width: 380px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.14), 0 4px 10px rgba(15, 23, 42, 0.06);
        border: 1px solid rgba(15, 23, 42, 0.06);
        padding: 14px 14px 14px 14px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 14px;
        line-height: 1.45;
        color: #111827;
        transform: translateX(120%);
        opacity: 0;
        transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease;
    }
    .shopys-toast.is-visible {
        transform: translateX(0);
        opacity: 1;
    }
    .shopys-toast.is-leaving {
        transform: translateX(120%);
        opacity: 0;
    }
    .shopys-toast-icon {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }
    .shopys-toast--error .shopys-toast-icon { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
    .shopys-toast--success .shopys-toast-icon { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
    .shopys-toast--info .shopys-toast-icon { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }
    .shopys-toast--error { border-left: 4px solid #dc2626; }
    .shopys-toast--success { border-left: 4px solid #16a34a; }
    .shopys-toast--info { border-left: 4px solid #4b5563; }
    .shopys-toast-body { flex: 1; padding-top: 4px; }
    .shopys-toast-title {
        font-weight: 700;
        font-size: 14px;
        margin: 0 0 2px;
        color: #111827;
    }
    .shopys-toast-msg {
        margin: 0;
        font-size: 13px;
        color: #374151;
        word-wrap: break-word;
    }
    .shopys-toast-close {
        flex-shrink: 0;
        background: transparent;
        border: 0;
        cursor: pointer;
        color: #9ca3af;
        padding: 4px;
        margin: -4px -4px 0 0;
        border-radius: 6px;
        line-height: 0;
        transition: color 0.15s, background 0.15s;
    }
    .shopys-toast-close:hover { color: #111827; background: #f3f4f6; }

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

        // ── Toast: render any captured WC notices (errors from failed register/login) ──
        var TOASTS = <?php echo wp_json_encode( $toast_payload ); ?>;
        var ICON_ERR  = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        var ICON_OK   = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        var ICON_INFO = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
        var ICON_X    = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        var TITLES = {
            error:   '<?php echo esc_js( __( 'Could not create account', 'shopys' ) ); ?>',
            success: '<?php echo esc_js( __( 'Success', 'shopys' ) ); ?>',
            info:    '<?php echo esc_js( __( 'Notice', 'shopys' ) ); ?>'
        };

        function showToast(type, msg) {
            var stack = document.getElementById('shopys-toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'shopys-toast-stack';
                stack.className = 'shopys-toast-stack';
                document.body.appendChild(stack);
            }
            var icon = type === 'error' ? ICON_ERR : (type === 'success' ? ICON_OK : ICON_INFO);
            var title = TITLES[type] || TITLES.info;
            var t = document.createElement('div');
            t.className = 'shopys-toast shopys-toast--' + type;
            t.setAttribute('role', type === 'error' ? 'alert' : 'status');
            t.innerHTML =
                '<div class="shopys-toast-icon">' + icon + '</div>' +
                '<div class="shopys-toast-body">' +
                    '<p class="shopys-toast-title"></p>' +
                    '<p class="shopys-toast-msg"></p>' +
                '</div>' +
                '<button type="button" class="shopys-toast-close" aria-label="Close">' + ICON_X + '</button>';
            t.querySelector('.shopys-toast-title').textContent = title;
            t.querySelector('.shopys-toast-msg').textContent = msg;
            stack.appendChild(t);
            requestAnimationFrame(function(){ t.classList.add('is-visible'); });
            var dismiss = function(){
                if (t.classList.contains('is-leaving')) return;
                t.classList.add('is-leaving');
                t.classList.remove('is-visible');
                setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 350);
            };
            t.querySelector('.shopys-toast-close').addEventListener('click', dismiss);
            setTimeout(dismiss, type === 'error' ? 7000 : 5000);
        }

        if (TOASTS && TOASTS.length) {
            // Auto-switch to register tab if the error came from the register form
            var hasRegError = TOASTS.some(function(n){
                return n.type === 'error' && /email|user|register|password|account/i.test(n.msg);
            });
            document.addEventListener('DOMContentLoaded', function(){
                if (hasRegError && initialTab !== 'register') {
                    var regTab = document.querySelector('.shopys-auth-tab[data-tab="register"]');
                    if (regTab) regTab.click();
                }
                TOASTS.forEach(function(n){ showToast(n.type, n.msg); });
            });
        }
    })();
    </script>
    <?php
}

// ── 2b. Success dialog: shown on the my-account dashboard right after a
//        registration succeeds. Triggered by ?registered=1 on the URL.
add_action( 'wp_footer', 'shopys_register_success_dialog', 70 );
function shopys_register_success_dialog() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( ! is_user_logged_in() ) return;
    if ( ! isset( $_GET['registered'] ) || $_GET['registered'] !== '1' ) return;

    $user  = wp_get_current_user();
    $first = $user && $user->display_name ? explode( ' ', trim( $user->display_name ) )[0] : '';
    ?>
    <style>
    .shopys-success-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        z-index: 100001;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .shopys-success-overlay.is-visible { opacity: 1; }
    .shopys-success-dialog {
        background: #ffffff;
        border-radius: 20px;
        max-width: 440px;
        width: 100%;
        padding: 36px 32px 28px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.25);
        text-align: center;
        transform: scale(0.92);
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        font-family: inherit;
    }
    .shopys-success-overlay.is-visible .shopys-success-dialog { transform: scale(1); }
    .shopys-success-icon {
        width: 72px;
        height: 72px;
        margin: 0 auto 18px;
        border-radius: 22px;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        box-shadow: 0 14px 30px rgba(34, 197, 94, 0.36);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        animation: shopys-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes shopys-pop {
        0% { transform: scale(0.4); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .shopys-success-title {
        font-size: 22px;
        font-weight: 800;
        color: #111827;
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }
    .shopys-success-sub {
        font-size: 14px;
        color: #6b7280;
        margin: 0 0 24px;
        line-height: 1.55;
    }
    .shopys-success-btn {
        width: 100%;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: #fff;
        border: 0;
        height: 48px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.2s;
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.32);
        font-family: inherit;
    }
    .shopys-success-btn:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(34, 197, 94, 0.42); }
    .shopys-success-btn:active { transform: translateY(0); }
    </style>
    <div class="shopys-success-overlay" id="shopys-success-overlay" role="dialog" aria-modal="true" aria-labelledby="shopys-success-title">
        <div class="shopys-success-dialog">
            <div class="shopys-success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2 class="shopys-success-title" id="shopys-success-title">
                <?php
                if ( $first ) {
                    /* translators: %s: customer first name */
                    printf( esc_html__( 'Welcome, %s!', 'shopys' ), esc_html( $first ) );
                } else {
                    esc_html_e( 'Account Created!', 'shopys' );
                }
                ?>
            </h2>
            <p class="shopys-success-sub">
                <?php esc_html_e( 'Your account is ready. Start exploring products and place your first order whenever you are ready.', 'shopys' ); ?>
            </p>
            <button type="button" class="shopys-success-btn" id="shopys-success-btn">
                <?php esc_html_e( 'Continue Shopping', 'shopys' ); ?>
            </button>
        </div>
    </div>
    <script>
    (function(){
        var overlay = document.getElementById('shopys-success-overlay');
        var btn     = document.getElementById('shopys-success-btn');
        if (!overlay) return;
        // Strip ?registered=1 from URL so reload doesn't reshow.
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('registered');
            window.history.replaceState({}, '', url.toString());
        }
        requestAnimationFrame(function(){ overlay.classList.add('is-visible'); });
        function close() {
            overlay.classList.remove('is-visible');
            setTimeout(function(){ if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 280);
        }
        if (btn) btn.addEventListener('click', close);
        overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
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
