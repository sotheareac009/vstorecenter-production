<?php
/**
 * KHQR verification relay — host this single file on a server with a CAMBODIAN IP
 * (e.g. a KH VPS, KH shared host, or your existing Cambodian-side infra).
 *
 * Bakong's API (api-bakong.nbc.gov.kh) geo-blocks non-Cambodian server IPs with a
 * CloudFront 403. This relay simply forwards the WooCommerce gateway's
 * check-transaction request to Bakong from a Cambodian IP and returns the JSON.
 *
 * Deploy:
 *   1. Put this file on a Cambodian-IP host, e.g. https://your-kh-host.com/khqr-relay.php
 *   2. Set a shared secret below (RELAY_SECRET) — must match KHQR_RELAY_SECRET on the store.
 *   3. On the store (.env or wp-config), set:
 *        KHQR_CHECK_URL    = https://your-kh-host.com/khqr-relay.php
 *        KHQR_RELAY_SECRET = <the same secret>
 *
 * The store still holds the Bakong token and passes it through; this relay only
 * provides the Cambodian IP. Keep the URL private and the secret strong.
 */

const RELAY_SECRET   = 'CHANGE_ME_to_a_long_random_string';
const BAKONG_ENDPOINT = 'https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5';

header( 'Content-Type: application/json' );

// Verify the shared secret.
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
    CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $auth ),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
) );
$resp = curl_exec( $ch );
$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$err  = curl_error( $ch );
curl_close( $ch );

if ( $resp === false ) {
    http_response_code( 502 );
    echo json_encode( array( 'responseCode' => 1, 'responseMessage' => 'relay: ' . $err ) );
    exit;
}

http_response_code( $code ?: 502 );
echo $resp;
