<?php
/**
 * Google Sign-In (OAuth 2.0) for My Account login/register.
 *
 * ADDITIVE ONLY — the existing email/password login, registration (incl. the
 * required-phone field), and Telegram login all continue working exactly as
 * before. This adds a third, optional "Continue with Google" entry point.
 *
 * SETUP
 *   1. https://console.cloud.google.com/apis/credentials → Create OAuth client ID
 *      (Application type: Web application).
 *   2. Authorized redirect URIs — add one per environment:
 *        http://vstorecenter-local.com/?shopys_google_auth=1
 *        https://vstorecenter.com/?shopys_google_auth=1
 *   3. Put the Client ID / Secret in .env (local) or wp-config.php define()s (prod):
 *        GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
 *        GOOGLE_CLIENT_SECRET=xxxx
 *      Leave both blank to keep the button hidden.
 *
 * Behavior: signing in with a Google account that matches an existing site
 * account (by verified email) logs into that same account. A brand-new Google
 * sign-up creates a normal WooCommerce "customer" account; unlike the regular
 * registration form, the phone number is NOT required (can be added later from
 * My Account).
 *
 * @package Shopys
 */

function shopys_google_cfg( $key ) {
    $const = 'GOOGLE_' . strtoupper( $key );
    if ( defined( $const ) ) return (string) constant( $const );
    $v = getenv( $const );
    return $v !== false ? (string) $v : '';
}

function shopys_google_enabled() {
    return shopys_google_cfg( 'client_id' ) !== '' && shopys_google_cfg( 'client_secret' ) !== '';
}

function shopys_google_redirect_uri() {
    // Google requires an exact string match against the Authorized redirect URI
    // in the Cloud Console. Force https on non-local domains so this stays
    // correct even if WordPress's stored Site Address is "http://" while the
    // site is actually served over https (a common source of redirect_uri_mismatch).
    $url = home_url( '/?shopys_google_auth=1' );
    if ( strpos( home_url(), 'local' ) === false ) {
        $url = set_url_scheme( $url, 'https' );
    }
    return $url;
}

// ── 1. Handle the Google OAuth callback (runs early, before any output) ────────
add_action( 'init', 'shopys_handle_google_auth' );
function shopys_handle_google_auth() {
    if ( empty( $_GET['shopys_google_auth'] ) ) return;
    if ( is_user_logged_in() ) {
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) ?: home_url( '/' ) );
        exit;
    }
    if ( ! shopys_google_enabled() ) {
        wp_die( esc_html__( 'Google sign-in is not configured.', 'shopys' ), '', array( 'response' => 500 ) );
    }

    // User cancelled on Google's side, or Google reported an error.
    if ( ! empty( $_GET['error'] ) ) {
        wp_safe_redirect( add_query_arg( 'google_auth_error', '1', wc_get_page_permalink( 'myaccount' ) ) );
        exit;
    }

    $code  = isset( $_GET['code'] )  ? sanitize_text_field( wp_unslash( $_GET['code'] ) )  : '';
    $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
    if ( $code === '' || $state === '' ) {
        wp_die( esc_html__( 'Missing Google sign-in parameters.', 'shopys' ), '', array( 'response' => 400 ) );
    }

    // The state carries a CSRF nonce + where to send the user back — both
    // generated stateless (no server session/cookie needed) when the button rendered.
    list( $nonce, $enc_redirect ) = array_pad( explode( '.', $state, 2 ), 2, '' );
    if ( ! wp_verify_nonce( $nonce, 'shopys_google_login' ) ) {
        wp_die( esc_html__( 'This sign-in link has expired. Please try again.', 'shopys' ), '', array( 'response' => 403 ) );
    }
    $redirect_to = $enc_redirect !== '' ? base64_decode( rawurldecode( $enc_redirect ) ) : '';
    $redirect_to = wp_validate_redirect( $redirect_to, wc_get_page_permalink( 'myaccount' ) );

    // Exchange the authorization code for an access token.
    $token_resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
        'timeout' => 15,
        'body'    => array(
            'code'          => $code,
            'client_id'     => shopys_google_cfg( 'client_id' ),
            'client_secret' => shopys_google_cfg( 'client_secret' ),
            'redirect_uri'  => shopys_google_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ),
    ) );
    if ( is_wp_error( $token_resp ) || (int) wp_remote_retrieve_response_code( $token_resp ) !== 200 ) {
        error_log( 'shopys Google OAuth token exchange failed: ' . ( is_wp_error( $token_resp ) ? $token_resp->get_error_message() : wp_remote_retrieve_body( $token_resp ) ) );
        wp_die( esc_html__( 'Could not verify your Google account. Please try again.', 'shopys' ), '', array( 'response' => 502 ) );
    }
    $token_data   = json_decode( wp_remote_retrieve_body( $token_resp ), true );
    $access_token = is_array( $token_data ) ? ( $token_data['access_token'] ?? '' ) : '';
    if ( $access_token === '' ) {
        wp_die( esc_html__( 'Google did not return an access token.', 'shopys' ), '', array( 'response' => 502 ) );
    }

    // Fetch the signed-in user's Google profile.
    $profile_resp = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
        'timeout' => 15,
        'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
    ) );
    if ( is_wp_error( $profile_resp ) || (int) wp_remote_retrieve_response_code( $profile_resp ) !== 200 ) {
        wp_die( esc_html__( 'Could not read your Google profile. Please try again.', 'shopys' ), '', array( 'response' => 502 ) );
    }
    $profile = json_decode( wp_remote_retrieve_body( $profile_resp ), true );
    if ( ! is_array( $profile ) ) {
        wp_die( esc_html__( 'Unexpected response from Google.', 'shopys' ), '', array( 'response' => 502 ) );
    }

    $google_id      = isset( $profile['sub'] )   ? sanitize_text_field( $profile['sub'] )   : '';
    $email          = isset( $profile['email'] ) ? sanitize_email( $profile['email'] )       : '';
    $email_verified = ! empty( $profile['email_verified'] );
    $name           = isset( $profile['name'] )        ? sanitize_text_field( $profile['name'] )        : '';
    $given          = isset( $profile['given_name'] )  ? sanitize_text_field( $profile['given_name'] )  : '';
    $family         = isset( $profile['family_name'] ) ? sanitize_text_field( $profile['family_name'] ) : '';
    $picture        = isset( $profile['picture'] )      ? esc_url_raw( $profile['picture'] )             : '';

    if ( $google_id === '' || $email === '' || ! $email_verified ) {
        wp_die( esc_html__( 'Your Google account email must be verified to sign in.', 'shopys' ), '', array( 'response' => 403 ) );
    }

    $user = shopys_get_or_create_google_user( $google_id, $email, $name, $given, $family, $picture );
    if ( is_wp_error( $user ) ) {
        wp_die( esc_html( $user->get_error_message() ), '', array( 'response' => 500 ) );
    }

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );
    do_action( 'wp_login', $user->user_login, $user );

    wp_safe_redirect( $redirect_to );
    exit;
}

/**
 * Find the WP user linked to this Google account, auto-link by verified email
 * to an existing account, or create a brand-new WooCommerce customer.
 */
function shopys_get_or_create_google_user( $google_id, $email, $name, $given, $family, $picture ) {
    // 1) Already linked to this exact Google account.
    $existing = get_users( array( 'meta_key' => 'shopys_google_id', 'meta_value' => $google_id, 'number' => 1 ) );
    if ( ! empty( $existing ) ) {
        if ( $picture ) update_user_meta( $existing[0]->ID, 'shopys_google_picture', $picture );
        return $existing[0];
    }

    // 2) Auto-link to an existing account with the same email — safe because
    //    Google has already verified the requester owns that email address.
    $by_email = get_user_by( 'email', $email );
    if ( $by_email ) {
        update_user_meta( $by_email->ID, 'shopys_google_id', $google_id );
        if ( $picture ) update_user_meta( $by_email->ID, 'shopys_google_picture', $picture );
        return $by_email;
    }

    // 3) Create a new customer account via WooCommerce's own helper (same one
    //    the normal registration form uses — sets role, fires the usual hooks,
    //    sends the standard "new account" email). The theme's phone-required
    //    check is bypassed for just this call since Google sign-ups don't
    //    collect a phone number.
    remove_action( 'woocommerce_register_post', 'shopys_register_validate_phone', 10 );
    $customer_id = wc_create_new_customer( $email, '', wp_generate_password( 32, true, true ), array(
        'first_name' => $given,
        'last_name'  => $family,
        'source'     => 'google-oauth',
    ) );
    add_action( 'woocommerce_register_post', 'shopys_register_validate_phone', 10, 3 );

    if ( is_wp_error( $customer_id ) ) return $customer_id;

    update_user_meta( $customer_id, 'shopys_google_id', $google_id );
    if ( $picture ) update_user_meta( $customer_id, 'shopys_google_picture', $picture );
    if ( $email )   update_user_meta( $customer_id, 'billing_email', $email );
    if ( $name !== '' ) {
        wp_update_user( array( 'ID' => $customer_id, 'display_name' => $name ) );
    }

    return get_user_by( 'ID', $customer_id );
}

// ── 2. "Continue with Google" button — top of both the login and register forms ──
add_action( 'woocommerce_login_form_start', 'shopys_google_button' );
add_action( 'woocommerce_register_form_start', 'shopys_google_button' );
function shopys_google_button() {
    if ( ! shopys_google_enabled() ) return;

    $redirect_to = wp_get_referer();
    if ( ! $redirect_to ) $redirect_to = wc_get_page_permalink( 'myaccount' );

    // Stateless CSRF protection: a WP nonce plus the return URL, both carried
    // through Google's redirect round-trip in the `state` param.
    $state = wp_create_nonce( 'shopys_google_login' ) . '.' . rawurlencode( base64_encode( $redirect_to ) );

    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
        'client_id'     => shopys_google_cfg( 'client_id' ),
        'redirect_uri'  => shopys_google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ) );
    ?>
    <a href="<?php echo esc_url( $auth_url ); ?>" class="shopys-google-btn">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
            <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
            <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
        </svg>
        <span><?php esc_html_e( 'Continue with Google', 'shopys' ); ?></span>
    </a>
    <div class="shopys-google-divider"><span><?php esc_html_e( 'or', 'shopys' ); ?></span></div>
    <style>
    .shopys-google-btn{
        display:flex; align-items:center; justify-content:center; gap:10px; width:100%;
        margin:0 0 6px; padding:12px 16px; border-radius:10px; border:1.5px solid #dfe3ea;
        background:#fff; color:#3c4043 !important; font-weight:700; font-size:14px;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
        text-decoration:none !important; box-shadow:0 1px 3px rgba(0,0,0,.08);
        transition:box-shadow .18s ease, border-color .18s ease;
    }
    .shopys-google-btn:hover{ box-shadow:0 3px 10px rgba(0,0,0,.14); border-color:#c7cdd6; }
    .shopys-google-divider{ display:flex; align-items:center; gap:12px; margin:14px 0 18px; color:#9aa3b0; font-size:12px; font-weight:600; }
    .shopys-google-divider::before, .shopys-google-divider::after{ content:''; flex:1; height:1px; background:#e7e9ee; }
    </style>
    <?php
}

// ── 3. Friendly error notice if Google sign-in failed / was cancelled ──────────
add_action( 'woocommerce_before_customer_login_form', 'shopys_google_auth_error_notice', 4 );
function shopys_google_auth_error_notice() {
    if ( empty( $_GET['google_auth_error'] ) ) return;
    wc_print_notice( esc_html__( 'Google sign-in was cancelled or failed. Please try again, or use the form below.', 'shopys' ), 'error' );
}
