<?php
/**
 * KHQR verification relay — UPLOAD THIS FILE TO A SERVER WITH A CAMBODIAN IP.
 * ---------------------------------------------------------------------------
 * Bakong's API (api-bakong.nbc.gov.kh) geo-blocks non-Cambodian server IPs
 * (CloudFront 403). Your store can't call it directly from a foreign host.
 * This tiny relay forwards the store's "check transaction" request to Bakong
 * from a Cambodian IP and returns the JSON unchanged.
 *
 * SETUP
 *   1. Put this file on a Cambodian-IP host (KH cPanel/shared host or KH VPS),
 *      e.g.  https://your-kh-host.com/khqr-relay.php
 *   2. Change RELAY_SECRET below to a long random string.
 *   3. On the STORE (production wp-config.php), add as define() constants:
 *          define( 'KHQR_CHECK_URL',    'https://your-kh-host.com/khqr-relay.php' );
 *          define( 'KHQR_RELAY_SECRET', 'the-same-long-random-string' );
 *   4. Test:  open  https://your-kh-host.com/khqr-relay.php  in a browser
 *             → should show {"relay":"ok"} (health check).
 *      Then in WP admin visit  /?khqr_selftest=1  → should no longer be 403.
 *
 * The store keeps the Bakong token; this relay only lends its Cambodian IP.
 * Keep the URL private and the secret strong.
 */

// ▼▼▼ CHANGE THIS to a long random string (must match KHQR_RELAY_SECRET on the store) ▼▼▼
const RELAY_SECRET    = 'CHANGE_ME_to_a_long_random_string';
// ▲▲▲ ----------------------------------------------------------------------------- ▲▲▲

const BAKONG_ENDPOINT = 'https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5';

header( 'Content-Type: application/json' );
header( 'X-Robots-Tag: noindex, nofollow' );

// Health check: a plain GET (no secret) just confirms the file is reachable.
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'GET' && empty( $_SERVER['HTTP_X_RELAY_SECRET'] ) ) {
    echo json_encode( array( 'relay' => 'ok' ) );
    exit;
}

// Verify the shared secret (constant-time compare).
$sent = isset( $_SERVER['HTTP_X_RELAY_SECRET'] ) ? $_SERVER['HTTP_X_RELAY_SECRET'] : '';
if ( ! hash_equals( RELAY_SECRET, (string) $sent ) ) {
    http_response_code( 403 );
    echo json_encode( array( 'responseCode' => 1, 'responseMessage' => 'relay: forbidden' ) );
    exit;
}

$body = file_get_contents( 'php://input' );
$auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

$ch = curl_init( BAKONG_ENDPOINT );
curl_setopt_array( $ch, array(
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => array(
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: ' . $auth,
    ),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
) );
$resp = curl_exec( $ch );
$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$err  = curl_error( $ch );
curl_close( $ch );

if ( $resp === false ) {
    http_response_code( 502 );
    echo json_encode( array( 'responseCode' => 1, 'responseMessage' => 'relay upstream error: ' . $err ) );
    exit;
}

http_response_code( $code ?: 502 );
echo $resp;
