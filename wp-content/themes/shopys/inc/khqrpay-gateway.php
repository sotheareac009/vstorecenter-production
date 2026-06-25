<?php
/**
 * KHQR (Bakong) — WooCommerce Payment Gateway
 *
 * Generates a standards-compliant dynamic KHQR locally (EMVCo TLV) and verifies
 * payment directly against the OFFICIAL Bakong Open API — no reseller, no webhook.
 *
 * Flow:
 *   1. Customer picks KHQR at checkout -> order created -> order-pay page.
 *   2. Server builds a dynamic KHQR for the order (account + amount + unique bill),
 *      computes its md5, renders the QR in the browser.
 *   3. The page polls our server; the server calls Bakong
 *      POST {KH_QR_API_URL}/check_transaction_by_md5 with the Bakong token.
 *   4. The order is marked PAID only when Bakong returns responseCode 0 (found/paid)
 *      AND the amount matches — the frontend is never trusted.
 *
 * Config (read from .env / wp-config, NOT the database):
 *   BAKONG_ID      (or KHQRPAY_BAKONG_ID)      e.g. name@bank  — REQUIRED, the receiving Bakong account
 *   BAKONG_TOKEN   (or KHQRPAY_BAKONG_TOKEN)   developer token from api-bakong.nbc.gov.kh — REQUIRED
 *   MERCHANT_NAME  (or KHQRPAY_MERCHANT_NAME)  KHQR merchant name (<=25 chars; default site name)
 *   KH_QR_API_URL  (or KHQRPAY_API_URL)        default https://api-bakong.nbc.gov.kh/v1
 *   KHQRPAY_CITY                               default "Phnom Penh"
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Read config from a constant or environment variable (prefixed or plain). */
function khqrpay_cfg( $key ) {
    foreach ( array( 'KHQRPAY_' . strtoupper( $key ), strtoupper( $key ) ) as $name ) {
        if ( defined( $name ) ) return trim( (string) constant( $name ) );
        $v = getenv( $name );
        if ( $v !== false && $v !== '' ) return trim( (string) $v );
    }
    return '';
}

function khqrpay_api_base() {
    $u = khqrpay_cfg( 'kh_qr_api_url' );
    if ( $u === '' ) $u = khqrpay_cfg( 'api_url' );
    if ( $u === '' ) $u = 'https://api-bakong.nbc.gov.kh/v1';
    return untrailingslashit( $u );
}

function khqrpay_merchant_name() {
    $n = khqrpay_cfg( 'merchant_name' );
    if ( $n === '' ) $n = get_bloginfo( 'name' );
    return mb_substr( $n, 0, 25 );
}

function khqrpay_city() {
    $c = khqrpay_cfg( 'city' );
    return mb_substr( $c !== '' ? $c : 'Phnom Penh', 0, 15 );
}

/** Rolling debug log (error_log + a capped option for the admin). */
function khqrpay_log( $label, $data = null ) {
    $msg = $label . ( $data === null ? '' : ' ' . ( is_scalar( $data ) ? $data : wp_json_encode( $data ) ) );
    error_log( 'KHQR: ' . $msg );
    $log = get_option( 'khqrpay_debug_log', array() );
    if ( ! is_array( $log ) ) $log = array();
    array_unshift( $log, array( 'at' => current_time( 'mysql' ), 'msg' => $msg ) );
    update_option( 'khqrpay_debug_log', array_slice( $log, 0, 40 ), false );
}

/* ───────────────────────── KHQR (EMVCo) builder ───────────────────────── */

/** One Tag-Length-Value field. Length is 2-digit, byte length of $value. */
function khqrpay_tlv( $id, $value ) {
    return $id . sprintf( '%02d', strlen( $value ) ) . $value;
}

/** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) → 4 upper-hex chars. */
function khqrpay_crc16( $data ) {
    $crc = 0xFFFF;
    $len = strlen( $data );
    for ( $i = 0; $i < $len; $i++ ) {
        $crc ^= ( ord( $data[ $i ] ) << 8 );
        for ( $b = 0; $b < 8; $b++ ) {
            $crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 );
            $crc &= 0xFFFF;
        }
    }
    return sprintf( '%04X', $crc );
}

/** Parse an EMVCo TLV string into an ordered list of array(id, value). */
function khqrpay_tlv_parse( $s ) {
    $out = array();
    $i   = 0;
    $n   = strlen( $s );
    while ( $i + 4 <= $n ) {
        $id  = substr( $s, $i, 2 );
        $len = (int) substr( $s, $i + 2, 2 );
        $out[] = array( $id, substr( $s, $i + 4, $len ) );
        $i += 4 + $len;
    }
    return $out;
}

/** USD → KHR conversion rate (gateway admin setting → .env → default 4100). */
function khqrpay_usd_to_khr_rate() {
    $opt = get_option( 'woocommerce_khqrpay_settings' );
    if ( is_array( $opt ) && isset( $opt['usd_to_khr'] ) && (float) $opt['usd_to_khr'] > 0 ) {
        return (float) $opt['usd_to_khr'];
    }
    $r = (float) khqrpay_cfg( 'khqr_usd_to_khr' ); // reads KHQR_USD_TO_KHR
    if ( $r <= 0 ) $r = (float) khqrpay_cfg( 'usd_to_khr' );
    return $r > 0 ? $r : 4100.0;
}

/** QR currency: 'USD' (default) or 'KHR', from gateway setting / .env. */
function khqrpay_qr_currency() {
    $opt = get_option( 'woocommerce_khqrpay_settings' );
    $c   = ( is_array( $opt ) && ! empty( $opt['currency'] ) ) ? $opt['currency'] : khqrpay_cfg( 'khqr_currency' );
    return strtoupper( $c ) === 'KHR' ? 'KHR' : 'USD';
}

/** Minutes a generated QR stays valid (Bakong timestamp expiry). Default 60. */
function khqrpay_qr_ttl_min() {
    $m = (int) khqrpay_cfg( 'khqr_qr_ttl_min' );
    return $m > 0 ? $m : 60;
}

/**
 * Build a dynamic INDIVIDUAL KHQR for an order — byte-identical to the official
 * bakong-khqr SDK (validated). Includes the required timestamp tag (99) so the
 * QR is a valid dynamic KHQR and is verifiable via check_transaction_by_md5.
 * Returns array|WP_Error.
 */
function khqrpay_build_qr( WC_Order $order ) {
    $account = khqrpay_cfg( 'bakong_id' );
    if ( $account === '' || strpos( $account, '@' ) === false ) {
        return new WP_Error( 'khqrpay_no_account', 'Missing/invalid BAKONG_ID (must be name@bank).' );
    }

    $cur  = khqrpay_qr_currency();
    $name = mb_substr( khqrpay_merchant_name(), 0, 25 );
    $city = khqrpay_city();

    // Amount, formatted exactly as the SDK does.
    if ( $cur === 'KHR' ) {
        $currency_code = '116';
        $amount        = (string) (int) round( (float) $order->get_total() * khqrpay_usd_to_khr_rate() );
        $amount_label  = number_format( (float) $amount, 0 ) . ' KHR';
    } else {
        $currency_code = '840';
        $total         = (float) $order->get_total();
        $amount        = ( floor( $total ) == $total ) ? (string) (int) $total : number_format( $total, 2, '.', '' );
        $amount_label  = '$' . number_format( $total, 2 );
    }

    // Unique bill number → each order's QR (and md5) is distinct.
    $bill = 'INV' . $order->get_id() . substr( strtoupper( wp_generate_password( 5, false, false ) ), 0, 5 );

    $creation   = (string) ( time() * 1000 );
    $expiration = (string) ( ( time() + khqrpay_qr_ttl_min() * 60 ) * 1000 );

    // Individual account (tag 29): sub-00 account id, optional sub-01 account info
    // and sub-02 acquiring bank (e.g. ACLEDA uses khqr@aclb + phone + "ACLEDA").
    $acct = khqrpay_tlv( '00', $account );
    $info = khqrpay_cfg( 'khqr_account_info' );
    if ( $info === '' ) $info = khqrpay_cfg( 'account_info' );
    $bank = khqrpay_cfg( 'khqr_acquiring_bank' );
    if ( $bank === '' ) $bank = khqrpay_cfg( 'acquiring_bank' );
    if ( $info !== '' ) $acct .= khqrpay_tlv( '01', $info );
    if ( $bank !== '' ) $acct .= khqrpay_tlv( '02', $bank );

    $p  = khqrpay_tlv( '00', '01' );                              // payload format
    $p .= khqrpay_tlv( '01', '12' );                              // dynamic
    $p .= khqrpay_tlv( '29', $acct );                             // individual account
    $p .= khqrpay_tlv( '52', '5999' );                            // merchant category code
    $p .= khqrpay_tlv( '53', $currency_code );                    // currency
    $p .= khqrpay_tlv( '54', $amount );                           // amount
    $p .= khqrpay_tlv( '58', 'KH' );                              // country
    $p .= khqrpay_tlv( '59', $name );                             // merchant name
    $p .= khqrpay_tlv( '60', $city );                             // merchant city
    $p .= khqrpay_tlv( '62', khqrpay_tlv( '01', $bill ) );        // additional data: bill number
    $p .= khqrpay_tlv( '99', khqrpay_tlv( '00', $creation ) . khqrpay_tlv( '01', $expiration ) ); // timestamp
    $p .= '6304';
    $p .= khqrpay_crc16( $p );

    return array(
        'qr'           => $p,
        'md5'          => md5( $p ),
        'bill'         => $bill,
        'currency'     => $currency_code,
        'amount'       => $amount,
        'amount_label' => $amount_label,
        'expires'      => (int) ( $expiration / 1000 ),
    );
}

/** Call the Bakong Open API to check a transaction by md5. Returns array|WP_Error.
 *  Bakong's CDN geo-blocks non-Cambodian server IPs, so if KHQR_CHECK_URL is set we
 *  POST {md5} to that relay (hosted on a Cambodian IP) which forwards to Bakong. */
function khqrpay_bakong_check( $md5 ) {
    $token = khqrpay_cfg( 'bakong_token' );
    if ( $token === '' ) return new WP_Error( 'khqrpay_no_token', 'Missing BAKONG_TOKEN.' );

    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    );
    $relay = khqrpay_cfg( 'khqr_check_url' );
    if ( $relay !== '' ) {
        $url    = $relay;
        $secret = khqrpay_cfg( 'khqr_relay_secret' );
        if ( $secret !== '' ) $headers['X-Relay-Secret'] = $secret;
    } else {
        $url = khqrpay_api_base() . '/check_transaction_by_md5';
    }

    $resp = wp_remote_post( $url, array(
        'timeout' => 20,
        'headers' => $headers,
        'body'    => wp_json_encode( array( 'md5' => $md5 ) ),
    ) );
    if ( is_wp_error( $resp ) ) return $resp;

    $code = (int) wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'khqrpay_badjson', 'Bad Bakong response', 'HTTP ' . $code . ' | ' . substr( trim( wp_strip_all_tags( $body ) ), 0, 300 ) );
    }
    return $data;
}

/**
 * Ask Bakong about every md5 ever generated for the order; on a confirmed match
 * (with amount), mark the order paid (idempotent). Returns true if paid.
 */
function khqrpay_confirm_order_if_paid( WC_Order $order ) {
    if ( $order->is_paid() ) return true;

    $md5s = (array) $order->get_meta( '_khqrpay_md5s' );
    if ( ! $md5s ) {
        $single = $order->get_meta( '_khqrpay_md5' );
        if ( $single ) $md5s = array( $single );
    }
    if ( ! $md5s ) return false;

    $expected = (float) $order->get_meta( '_khqrpay_amount' );

    foreach ( array_unique( $md5s ) as $md5 ) {
        $data = khqrpay_bakong_check( $md5 );
        if ( is_wp_error( $data ) ) { khqrpay_log( 'check error', $data->get_error_message() ); continue; }
        if ( ! isset( $data['responseCode'] ) || (int) $data['responseCode'] !== 0 ) continue;

        $paidAmt = isset( $data['data']['amount'] ) ? (float) $data['data']['amount'] : null;
        $amtOk   = ( $paidAmt === null ) || ( $expected <= 0 ) || ( abs( $paidAmt - $expected ) < 1 );
        if ( ! $amtOk ) { khqrpay_log( 'amount mismatch order ' . $order->get_id(), array( 'paid' => $paidAmt, 'want' => $expected ) ); continue; }

        $order->payment_complete( $md5 );
        $order->add_order_note( 'KHQR payment confirmed by Bakong Open API (md5 ' . $md5 . ', bill ' . $order->get_meta( '_khqrpay_bill' ) . ').' );
        khqrpay_log( 'order ' . $order->get_id() . ' marked paid (md5 ' . $md5 . ')' );
        return true;
    }
    return false;
}

/** A small KHQR logo badge as a data-URI (used as the gateway icon at checkout). */
function khqrpay_logo_data_uri() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="58" height="30" viewBox="0 0 58 30">'
         . '<rect width="58" height="30" rx="7" fill="#e2231a"/>'
         . '<text x="29" y="20" font-family="Arial,Helvetica,sans-serif" font-size="14" font-weight="800" fill="#ffffff" text-anchor="middle" letter-spacing="1.2">KHQR</text>'
         . '</svg>';
    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/* ───────────────────────── Register the gateway ───────────────────────── */

add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
    $gateways[] = 'WC_Gateway_KHQRPay';
    return $gateways;
} );

add_action( 'plugins_loaded', 'khqrpay_load_gateway' );

function khqrpay_load_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'WC_Gateway_KHQRPay' ) ) return;

    class WC_Gateway_KHQRPay extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'khqrpay';
            $this->method_title       = 'KHQR (Bakong)';
            $this->method_description = 'Accept KHQR payments verified directly with the official Bakong Open API. Set BAKONG_ID and BAKONG_TOKEN in .env / wp-config.';
            $this->has_fields         = true; // render the premium branded card in payment_fields()
            // No gateway icon — WordPress strips data: URIs (broken img + duplicate title).
            // The branded KHQR logo lives inside payment_fields() instead.

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title', 'Pay with KHQR' );
            $this->description = $this->get_option( 'description', 'Scan the KHQR with Bakong, ABA, or any Cambodian bank app to complete payment.' );
            $this->enabled     = $this->get_option( 'enabled', 'no' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            // NOTE: the poll AJAX is registered at file scope (see bottom) so it works
            // even when WooCommerce hasn't instantiated this gateway (plain admin-ajax).
        }

        public function init_form_fields() {
            $missing = array();
            if ( khqrpay_cfg( 'bakong_id' ) === '' )    $missing[] = 'BAKONG_ID';
            if ( khqrpay_cfg( 'bakong_token' ) === '' ) $missing[] = 'BAKONG_TOKEN';
            $cur = khqrpay_qr_currency();
            $this->form_fields = array(
                'enabled'     => array( 'title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable KHQR (Bakong)', 'default' => 'no' ),
                'title'       => array( 'title' => 'Title', 'type' => 'text', 'default' => 'Pay with KHQR', 'desc_tip' => true, 'description' => 'Shown to the customer at checkout.' ),
                'description' => array( 'title' => 'Description', 'type' => 'textarea', 'default' => 'Scan the KHQR with Bakong, ABA, or any Cambodian bank app to complete payment.' ),
                'currency'    => array( 'title' => 'QR currency', 'type' => 'select', 'default' => 'USD', 'options' => array( 'USD' => 'USD (pay exact USD price)', 'KHR' => 'KHR (convert from USD)' ), 'description' => 'Currency encoded on the KHQR. Use USD if your Bakong account receives USD.' ),
                'usd_to_khr'  => array( 'title' => 'USD → KHR rate', 'type' => 'text', 'default' => '4100', 'desc_tip' => true, 'description' => 'Only used when QR currency is KHR. Overrides KHQR_USD_TO_KHR in .env.' ),
                'creds'       => array(
                    'title'       => 'Bakong account',
                    'type'        => 'title',
                    'description' => $missing
                        ? '<span style="color:#b91c1c;font-weight:600;">Missing in .env / wp-config: ' . esc_html( implode( ', ', $missing ) ) . '</span>'
                        : '<span style="color:#0a7d00;font-weight:600;">✓ Configured.</span> Account: <code>' . esc_html( khqrpay_cfg( 'bakong_id' ) ) . '</code>, currency: <code>' . esc_html( $cur ) . '</code>, API: <code>' . esc_html( khqrpay_api_base() ) . '</code>',
                ),
            );
        }

        public function is_available() {
            // KHQR_ENABLED in .env overrides the admin toggle: 1/true/yes/on shows it,
            // anything else hides it. If unset, fall back to the admin Enable setting.
            $flag = khqrpay_cfg( 'khqr_enabled' );
            $on   = ( $flag !== '' )
                ? in_array( strtolower( $flag ), array( '1', 'true', 'yes', 'on' ), true )
                : ( 'yes' === $this->enabled );
            if ( ! $on ) return false;
            if ( khqrpay_cfg( 'bakong_id' ) === '' || khqrpay_cfg( 'bakong_token' ) === '' ) return false;
            return true;
        }

        /** Premium branded card shown under the payment option at checkout. */
        public function payment_fields() {
            ?>
            <div class="khqr-pf">
                <div class="khqr-pf-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.2"/><path d="M14 14h3M14 18v3M18 14v3M21 14v7M18 21h3"/>
                    </svg>
                    <span>KHQR</span>
                </div>
                <div class="khqr-pf-info">
                    <strong><?php esc_html_e( 'Scan to pay with any banking app', 'shopys' ); ?></strong>
                    <span class="khqr-pf-banks">ABA · ACLEDA · Wing · Bakong · <?php esc_html_e( '& all KHQR banks', 'shopys' ); ?></span>
                    <span class="khqr-pf-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php esc_html_e( 'Secured by Bakong · National Bank of Cambodia', 'shopys' ); ?>
                    </span>
                </div>
            </div>
            <style>
            li.payment_method_khqrpay > label img,
            li.wc-block-components-payment-method-label img{ height:24px !important; width:auto !important; }
            .khqr-pf{ display:flex; align-items:center; gap:14px; margin:10px 0 4px; padding:14px 16px;
                background:linear-gradient(135deg,#fff,#fafbfc); border:1px solid #e7e9ee; border-radius:14px;
                box-shadow:0 6px 20px rgba(15,23,42,.06); font-family:'Play','Battambang',-apple-system,sans-serif; }
            .khqr-pf-badge{ flex-shrink:0; width:62px; height:62px; border-radius:14px; display:flex; flex-direction:column;
                align-items:center; justify-content:center; gap:2px; color:#fff;
                background:linear-gradient(135deg,#ef3b32,#c41a12); box-shadow:0 8px 18px rgba(196,26,18,.34); }
            .khqr-pf-badge svg{ width:24px; height:24px; }
            .khqr-pf-badge span{ font-size:10px; font-weight:800; letter-spacing:1px; }
            .khqr-pf-info{ display:flex; flex-direction:column; gap:3px; }
            .khqr-pf-info strong{ font-size:14.5px; font-weight:800; color:#0d1117; letter-spacing:-.2px; }
            .khqr-pf-banks{ font-size:12px; color:#5b6472; font-weight:600; }
            .khqr-pf-secure{ display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#0a9d4a; font-weight:600; margin-top:2px; }
            .khqr-pf-secure svg{ width:13px; height:13px; }
            @media(max-width:480px){ .khqr-pf{ gap:11px; padding:12px; } .khqr-pf-badge{ width:54px; height:54px; } .khqr-pf-info strong{ font-size:13.5px; } }
            </style>
            <?php
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $order->update_status( 'pending', __( 'Awaiting KHQR payment.', 'shopys' ) );
            if ( WC()->cart ) WC()->cart->empty_cart();
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            );
        }

        public function receipt_page( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) return;
            if ( $order->is_paid() ) {
                echo '<p>' . esc_html__( 'This order is already paid. Thank you!', 'shopys' ) . '</p>';
                return;
            }

            // Keep the QR stable (so its md5 matches what the customer paid), but
            // regenerate once the Bakong timestamp has expired.
            $qr_str  = (string) $order->get_meta( '_khqrpay_qr' );
            $qr_exp  = (int) $order->get_meta( '_khqrpay_expires' );
            if ( $qr_str === '' || $qr_exp <= time() ) {
                $built = khqrpay_build_qr( $order );
                if ( is_wp_error( $built ) ) {
                    khqrpay_log( 'build error', $built->get_error_message() );
                    echo '<div class="woocommerce-error">' . esc_html__( 'Sorry, we could not generate the KHQR. Please contact us to complete your order.', 'shopys' ) . '</div>';
                    return;
                }
                $order->update_meta_data( '_khqrpay_qr', $built['qr'] );
                $order->update_meta_data( '_khqrpay_md5', $built['md5'] );
                $order->update_meta_data( '_khqrpay_bill', $built['bill'] );
                $order->update_meta_data( '_khqrpay_amount', $built['amount'] );
                $order->update_meta_data( '_khqrpay_currency', $built['currency'] );
                $order->update_meta_data( '_khqrpay_expires', $built['expires'] );
                // Keep every md5 so verification still matches if the QR was regenerated.
                $list   = (array) $order->get_meta( '_khqrpay_md5s' );
                $list[] = $built['md5'];
                $order->update_meta_data( '_khqrpay_md5s', array_values( array_unique( array_filter( $list ) ) ) );
                $order->save();
                $qr_str       = $built['qr'];
                $amount_label = $built['amount_label'];
            } else {
                $cur          = $order->get_meta( '_khqrpay_currency' );
                $amt          = $order->get_meta( '_khqrpay_amount' );
                $amount_label = ( $cur === '116' ) ? number_format( (float) $amt, 0 ) . ' KHR' : '$' . number_format( (float) $amt, 2 );
            }

            $poll_url = add_query_arg( array(
                'action'   => 'khqrpay_poll',
                'order_id' => $order->get_id(),
                'key'      => $order->get_order_key(),
            ), admin_url( 'admin-ajax.php' ) );

            $return_url = $this->get_return_url( $order );
            $amount     = esc_html( $amount_label );      // e.g. "205 KHR"
            $amount_sub = wc_price( $order->get_total() ); // USD reference

            wp_enqueue_script( 'khqr-qrgen', 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js', array(), '1.4.4', false );
            ?>
            <style>
            .khqrx-wrap{ max-width:360px; margin:10px auto 28px; font-family:'Play','Battambang',-apple-system,BlinkMacSystemFont,sans-serif; }
            .khqrx-card{ background:#fff; border:1px solid #eef0f4; border-radius:22px; overflow:hidden; box-shadow:0 24px 70px rgba(15,23,42,.13); }
            .khqrx-head{ background:linear-gradient(135deg,#ef3b32,#c0140c); color:#fff; padding:18px 18px 16px; text-align:center; }
            .khqrx-logo{ display:inline-flex; align-items:center; gap:9px; }
            .khqrx-logo svg{ width:26px; height:26px; }
            .khqrx-logo span{ font-size:1.25rem; font-weight:800; letter-spacing:2px; }
            .khqrx-payee{ margin-top:7px; font-size:.9rem; font-weight:600; opacity:.95; }
            .khqrx-sub{ font-size:.72rem; opacity:.8; margin-top:2px; letter-spacing:.3px; }
            .khqrx-body{ padding:18px 18px 6px; text-align:center; }
            .khqrx-ref{ font-size:.7rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#9aa3b0; }
            .khqrx-amount{ font-size:1.65rem; font-weight:800; color:#0d1117; letter-spacing:-.5px; margin-top:3px; line-height:1.1; }
            .khqrx-amount-sub{ font-size:.82rem; color:#9aa3b0; margin-top:2px; }
            .khqrx-qrbox{ position:relative; width:208px; margin:14px auto 4px; padding:13px; background:#fff; border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.08); }
            .khqrx-qr{ width:180px; height:180px; display:flex; align-items:center; justify-content:center; margin:0 auto; }
            .khqrx-qr svg{ width:100% !important; height:100% !important; }
            .khqrx-corner{ position:absolute; width:24px; height:24px; border:3px solid #e21c25; }
            .khqrx-corner.tl{ top:6px; left:6px; border-right:0; border-bottom:0; border-radius:9px 0 0 0; }
            .khqrx-corner.tr{ top:6px; right:6px; border-left:0; border-bottom:0; border-radius:0 9px 0 0; }
            .khqrx-corner.bl{ bottom:6px; left:6px; border-right:0; border-top:0; border-radius:0 0 0 9px; }
            .khqrx-corner.br{ bottom:6px; right:6px; border-left:0; border-top:0; border-radius:0 0 9px 0; }
            .khqrx-status{ display:inline-flex; align-items:center; gap:8px; font-size:.92rem; font-weight:700; color:#5b6472; margin-top:6px; }
            .khqrx-dot{ width:9px; height:9px; border-radius:50%; background:#f59e0b; box-shadow:0 0 0 0 rgba(245,158,11,.5); animation:khqrxpulse 1.4s infinite; }
            @keyframes khqrxpulse{ 0%{ box-shadow:0 0 0 0 rgba(245,158,11,.5);}70%{ box-shadow:0 0 0 8px rgba(245,158,11,0);}100%{ box-shadow:0 0 0 0 rgba(245,158,11,0);} }
            .khqrx-timer{ font-size:.78rem; color:#aab1bd; margin-top:5px; }
            .khqrx-banks{ display:flex; flex-wrap:wrap; justify-content:center; gap:7px; margin:16px 0 4px; }
            .khqrx-banks span{ font-size:.72rem; font-weight:700; color:#5b6472; background:#f3f5f8; border:1px solid #e7e9ee; border-radius:50px; padding:5px 12px; }
            .khqrx-hint{ font-size:.78rem; color:#9aa3b0; line-height:1.55; margin:12px 4px 4px; }
            .khqrx-foot{ display:flex; align-items:center; justify-content:center; gap:7px; padding:13px; background:#f6fffb; border-top:1px solid #e7f5ec; color:#0a9d4a; font-size:.76rem; font-weight:700; }
            .khqrx-foot svg{ width:14px; height:14px; }
            .khqrx-cancel{ display:inline-flex; align-items:center; gap:7px; margin:16px auto 0; padding:11px 22px; border-radius:11px; border:1.5px solid #e7e9ee; background:#fff; color:#8a93a0; font-weight:700; font-size:13px; text-decoration:none; cursor:pointer; transition:border-color .2s,color .2s,background .2s; }
            .khqrx-cancel:hover{ border-color:#ef4444; color:#ef4444; background:#fff5f5; }
            .khqrx-cancel svg{ width:14px; height:14px; }
            .khqrx-modal{ position:fixed; inset:0; z-index:2147483600; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(13,17,23,.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); opacity:0; visibility:hidden; transition:opacity .22s ease,visibility .22s ease; }
            .khqrx-modal.open{ opacity:1; visibility:visible; }
            .khqrx-modal-card{ width:100%; max-width:350px; background:#fff; border-radius:20px; padding:28px 24px 22px; text-align:center; box-shadow:0 30px 70px rgba(0,0,0,.3); transform:translateY(14px) scale(.97); transition:transform .25s cubic-bezier(.2,.7,.3,1); }
            .khqrx-modal.open .khqrx-modal-card{ transform:none; }
            .khqrx-modal-icon{ width:60px; height:60px; margin:0 auto 14px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#fff1f1; color:#ef4444; }
            .khqrx-modal-icon svg{ width:30px; height:30px; }
            .khqrx-modal-card h3{ font-size:19px; font-weight:800; color:#0d1117; margin:0 0 8px; letter-spacing:-.2px; }
            .khqrx-modal-card p{ font-size:13.5px; color:#6b7280; line-height:1.6; margin:0 0 22px; }
            .khqrx-modal-actions{ display:flex; gap:10px; }
            .khqrx-keep, .khqrx-confirm{ flex:1; padding:13px; font-weight:700; font-size:14px; border-radius:11px; cursor:pointer; font-family:inherit; transition:background .2s, border-color .2s; display:flex; align-items:center; justify-content:center; }
            .khqrx-keep{ border:1.5px solid #e7e9ee; background:#fff; color:#374151; }
            .khqrx-keep:hover{ background:#f3f5f8; }
            .khqrx-confirm{ border:none; background:#ef4444; color:#fff; text-decoration:none; }
            .khqrx-confirm:hover{ background:#dc2626; color:#fff; }
            @media(max-width:480px){ .khqrx-amount{ font-size:1.5rem; } .khqrx-qrbox{ width:196px; } .khqrx-qr{ width:170px; height:170px; } }
            </style>
            <div class="khqrx-wrap">
                <div class="khqrx-card">
                    <div class="khqrx-head">
                        <div class="khqrx-logo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><path d="M14 14h3M14 18v3M18 14v3M21 14v7M18 21h3"/></svg>
                            <span>KHQR</span>
                        </div>
                        <div class="khqrx-payee"><?php echo esc_html( khqrpay_merchant_name() ); ?></div>
                        <div class="khqrx-sub"><?php esc_html_e( 'Scan to pay with any banking app', 'shopys' ); ?></div>
                    </div>
                    <div class="khqrx-body">
                        <div class="khqrx-ref"><?php esc_html_e( 'Order', 'shopys' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></div>
                        <div class="khqrx-amount"><?php echo $amount; // KHR label, pre-escaped ?></div>
                        <div class="khqrx-amount-sub">≈ <?php echo wp_kses_post( $amount_sub ); ?></div>
                        <div class="khqrx-qrbox">
                            <span class="khqrx-corner tl"></span><span class="khqrx-corner tr"></span>
                            <span class="khqrx-corner bl"></span><span class="khqrx-corner br"></span>
                            <div id="khqr-img" class="khqrx-qr"></div>
                        </div>
                        <div class="khqrx-status" id="khqr-status"><span class="khqrx-dot"></span><?php esc_html_e( 'Waiting for payment…', 'shopys' ); ?></div>
                        <div class="khqrx-timer" id="khqr-timer"></div>
                        <div class="khqrx-banks"><span>ABA</span><span>ACLEDA</span><span>Wing</span><span>Bakong</span></div>
                        <div class="khqrx-hint"><?php esc_html_e( 'Open your banking app → Scan to Pay. This page confirms automatically once your payment is received.', 'shopys' ); ?></div>
                    </div>
                    <div class="khqrx-foot">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php esc_html_e( 'Secured by Bakong · National Bank of Cambodia', 'shopys' ); ?>
                    </div>
                </div>
                <?php $khqr_cancel_url = esc_url( $order->get_cancel_order_url( wc_get_page_permalink( 'shop' ) ) ); ?>
                <div style="text-align:center;">
                    <a href="<?php echo $khqr_cancel_url; ?>" class="khqrx-cancel" id="khqr-cancel-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                        <?php esc_html_e( 'Cancel order', 'shopys' ); ?>
                    </a>
                </div>
            </div>
            <div class="khqrx-modal" id="khqr-cancel-modal" aria-hidden="true">
                <div class="khqrx-modal-card" role="dialog" aria-modal="true">
                    <div class="khqrx-modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    </div>
                    <h3><?php esc_html_e( 'Cancel this order?', 'shopys' ); ?></h3>
                    <p><?php esc_html_e( 'Your KHQR payment will be cancelled and you’ll need to start over.', 'shopys' ); ?></p>
                    <div class="khqrx-modal-actions">
                        <button type="button" class="khqrx-keep" id="khqr-keep"><?php esc_html_e( 'Keep order', 'shopys' ); ?></button>
                        <a href="<?php echo $khqr_cancel_url; ?>" class="khqrx-confirm"><?php esc_html_e( 'Yes, cancel', 'shopys' ); ?></a>
                    </div>
                </div>
            </div>
            <script>
            (function(){
                var qrData    = <?php echo wp_json_encode( $qr_str ); ?>;
                var pollUrl   = <?php echo wp_json_encode( $poll_url ); ?>;
                var returnUrl = <?php echo wp_json_encode( $return_url ); ?>;
                var statusEl  = document.getElementById('khqr-status');
                var timerEl   = document.getElementById('khqr-timer');
                var done = false, started = Date.now();

                // Premium cancel confirmation modal
                var cancelBtn = document.getElementById('khqr-cancel-btn');
                var cancelModal = document.getElementById('khqr-cancel-modal');
                var keepBtn = document.getElementById('khqr-keep');
                if (cancelBtn && cancelModal) {
                    cancelBtn.addEventListener('click', function(e){ e.preventDefault(); cancelModal.classList.add('open'); });
                    if (keepBtn) keepBtn.addEventListener('click', function(){ cancelModal.classList.remove('open'); });
                    cancelModal.addEventListener('click', function(e){ if (e.target === cancelModal) cancelModal.classList.remove('open'); });
                    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') cancelModal.classList.remove('open'); });
                }

                function draw(){
                    try {
                        var qr = qrcode(0, 'M');
                        qr.addData(qrData);
                        qr.make();
                        document.getElementById('khqr-img').innerHTML = qr.createSvgTag({cellSize:5, margin:2});
                    } catch(e){
                        document.getElementById('khqr-img').textContent = 'QR error';
                    }
                }
                if (window.qrcode) draw(); else window.addEventListener('load', draw);

                // The QR stays valid until paid — just show a "waiting" indicator (no reload).
                function tick(){
                    if (done) return;
                    var s = Math.floor((Date.now()-started)/1000);
                    var m = Math.floor(s/60);
                    timerEl.textContent = 'Auto-checking your payment · ' + m + ':' + ((s%60)<10?'0':'') + (s%60);
                    setTimeout(tick, 1000);
                }
                tick();

                function poll(){
                    if (done) return;
                    fetch(pollUrl + '&_=' + Date.now(), {credentials:'same-origin', cache:'no-store'})
                      .then(function(r){ return r.json(); })
                      .then(function(d){
                          if (done) return;
                          if (d && d.paid){
                              done = true;
                              statusEl.style.color = '#0a7d00';
                              statusEl.textContent = '✓ Payment confirmed! Redirecting…';
                              location.href = d.redirect || returnUrl;
                              return;
                          }
                          setTimeout(poll, 3000);
                      })
                      .catch(function(){ setTimeout(poll, 4000); });
                }
                setTimeout(poll, 3000);
            })();
            </script>
            <?php
        }

    }
}

/** Browser poll endpoint — registered at file scope so it always runs on admin-ajax. */
function khqrpay_ajax_poll() {
    nocache_headers(); // never let a CDN / LiteSpeed cache the poll response
    do_action( 'litespeed_control_set_nocache', 'khqrpay poll' );
    $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    $key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
    $order    = $order_id ? wc_get_order( $order_id ) : false;

    if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
        wp_send_json( array( 'paid' => false, 'error' => 'invalid' ) );
    }
    $redirect = $order->get_checkout_order_received_url();
    if ( $order->is_paid() || khqrpay_confirm_order_if_paid( $order ) ) {
        wp_send_json( array( 'paid' => true, 'redirect' => $redirect ) );
    }
    wp_send_json( array( 'paid' => false ) );
}
add_action( 'wp_ajax_khqrpay_poll',        'khqrpay_ajax_poll' );
add_action( 'wp_ajax_nopriv_khqrpay_poll', 'khqrpay_ajax_poll' );

/**
 * Admin diagnostic: visit  /?khqr_selftest=1  while logged in as admin.
 * Confirms whether THIS server can reach + authenticate with the Bakong API
 * (the #1 cause of "paid but not confirmed" on non-Cambodian hosting).
 */
add_action( 'init', function () {
    if ( empty( $_GET['khqr_selftest'] ) || ! current_user_can( 'manage_options' ) ) return;
    nocache_headers();
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo "KHQR self-test\n==============\n";
    echo 'API base   : ' . khqrpay_api_base() . "\n";
    $relay = khqrpay_cfg( 'khqr_check_url' );
    echo 'Check via  : ' . ( $relay !== '' ? 'RELAY ' . $relay : 'direct to Bakong' ) . "\n";
    echo 'BAKONG_ID  : ' . ( khqrpay_cfg( 'bakong_id' ) ?: '(empty)' ) . "\n";
    echo 'token set  : ' . ( khqrpay_cfg( 'bakong_token' ) ? 'yes' : 'NO' ) . "\n";
    echo 'currency   : ' . khqrpay_qr_currency() . "\n\n";
    $t0 = microtime( true );
    $r  = khqrpay_bakong_check( '0123456789abcdef0123456789abcdef' );
    $ms = round( ( microtime( true ) - $t0 ) * 1000 );
    if ( is_wp_error( $r ) ) {
        echo "REACHABILITY: FAILED ({$ms}ms)\n";
        echo '  error: ' . $r->get_error_message() . "\n";
        echo '  data : ' . ( is_scalar( $r->get_error_data() ) ? $r->get_error_data() : wp_json_encode( $r->get_error_data() ) ) . "\n";
        echo "\n=> If this is a timeout/connection error, the Bakong API is blocking this server's IP (needs a Cambodian IP / relay).\n";
    } else {
        echo "REACHABILITY: OK ({$ms}ms) — server can reach Bakong & token works.\n";
        echo '  response: ' . wp_json_encode( $r ) . "\n";
        echo "\n=> If this says 'transaction could not be found', the API is fine; the issue is elsewhere (check log below).\n";
    }
    echo "\n--- recent KHQR log ---\n";
    foreach ( array_slice( (array) get_option( 'khqrpay_debug_log', array() ), 0, 15 ) as $e ) {
        echo '[' . $e['at'] . '] ' . $e['msg'] . "\n";
    }
    exit;
} );

khqrpay_load_gateway();

/* ── Background fallback: re-check pending KHQR orders even if the buyer closed the page ── */
add_filter( 'cron_schedules', function ( $s ) {
    $s['khqrpay_2min'] = array( 'interval' => 120, 'display' => 'Every 2 minutes (KHQR)' );
    return $s;
} );

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'khqrpay_check_pending' ) ) {
        wp_schedule_event( time() + 120, 'khqrpay_2min', 'khqrpay_check_pending' );
    }
} );

add_action( 'khqrpay_check_pending', function () {
    if ( khqrpay_cfg( 'bakong_token' ) === '' ) return;
    $orders = wc_get_orders( array(
        'payment_method' => 'khqrpay',
        'status'         => 'pending',
        'limit'          => 25,
        'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
    ) );
    foreach ( $orders as $order ) {
        khqrpay_confirm_order_if_paid( $order );
    }
} );

/* ── Show the receiving Bakong account on the payment method (order detail + invoice) ── */
add_filter( 'woocommerce_order_get_payment_method_title', 'khqrpay_payment_method_title_with_account', 10, 2 );
function khqrpay_payment_method_title_with_account( $title, $order ) {
    if ( ! is_a( $order, 'WC_Abstract_Order' ) || $order->get_payment_method() !== 'khqrpay' ) {
        return $title;
    }
    // Prefer a human account number (KHQR_ACCOUNT_NUMBER) if set; fall back to the Bakong ID.
    $acct = khqrpay_cfg( 'khqr_account_number' );
    if ( $acct === '' ) $acct = khqrpay_cfg( 'account_number' );
    if ( $acct === '' ) $acct = khqrpay_cfg( 'bakong_id' );
    $name = khqrpay_merchant_name();
    if ( ( $name === '' && $acct === '' ) || ( $acct !== '' && strpos( (string) $title, $acct ) !== false ) ) {
        return $title; // nothing to add, or already appended
    }
    $extra = trim( $name . ( $acct !== '' ? ' · ' . $acct : '' ) );
    return $extra ? $title . ' — ' . $extra : $title;
}
