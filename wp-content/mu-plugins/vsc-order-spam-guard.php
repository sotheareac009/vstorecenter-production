<?php
/**
 * Plugin Name: VSC Order Spam Guard
 * Description: Server-side checkout hardening — Cambodia-only orders, KH phone validation, honeypot + time trap, per-IP order rate limit.
 * Author: vStoreCenter
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/** Countries we actually deliver to. */
function vsc_allowed_countries() {
	return apply_filters( 'vsc_allowed_countries', array( 'KH' ) );
}

/** Hard-limit the country lists Woo will accept, regardless of admin settings. */
add_filter( 'woocommerce_countries_allowed_countries', 'vsc_limit_countries' );
add_filter( 'woocommerce_countries_shipping_countries', 'vsc_limit_countries' );
function vsc_limit_countries( $countries ) {
	$allowed = array_flip( vsc_allowed_countries() );
	return array_intersect_key( $countries, $allowed );
}

/** Honeypot + timestamp trap on the checkout form. */
add_action( 'woocommerce_after_order_notes', 'vsc_checkout_traps' );
function vsc_checkout_traps() {
	echo '<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">';
	echo '<label>Company website</label>';
	echo '<input type="text" name="vsc_hp_url" tabindex="-1" autocomplete="off" value="" />';
	echo '</div>';
	echo '<input type="hidden" name="vsc_form_ts" value="' . esc_attr( time() ) . '" />';
}

/**
 * Server-side validation. Runs on both classic checkout and the store API path
 * (classic hook here; blocks checkout is covered by vsc_validate_store_api below).
 */
add_action( 'woocommerce_after_checkout_validation', 'vsc_validate_checkout', 10, 2 );
function vsc_validate_checkout( $data, $errors ) {

	// 1. Honeypot: only a bot fills a hidden field.
	if ( ! empty( $_POST['vsc_hp_url'] ) ) {
		$errors->add( 'vsc_spam', __( 'Your order could not be processed. Please contact us.', 'vsc' ) );
		vsc_log_block( 'honeypot' );
		return;
	}

	// 2. Time trap: a human needs more than a few seconds to fill checkout.
	$ts = isset( $_POST['vsc_form_ts'] ) ? absint( $_POST['vsc_form_ts'] ) : 0;
	if ( $ts && ( time() - $ts ) < 6 ) {
		$errors->add( 'vsc_spam', __( 'Please take a moment to review your order and try again.', 'vsc' ) );
		vsc_log_block( 'time_trap' );
		return;
	}

	// 3. Country lock.
	$allowed = vsc_allowed_countries();
	$billing = isset( $data['billing_country'] ) ? $data['billing_country'] : '';
	$shipping = ! empty( $data['ship_to_different_address'] ) && isset( $data['shipping_country'] )
		? $data['shipping_country']
		: $billing;

	if ( $billing && ! in_array( $billing, $allowed, true ) ) {
		$errors->add( 'vsc_country', __( 'We currently deliver inside Cambodia only.', 'vsc' ) );
	}
	if ( $shipping && ! in_array( $shipping, $allowed, true ) ) {
		$errors->add( 'vsc_country', __( 'We currently ship inside Cambodia only.', 'vsc' ) );
	}

	// 4. Cambodian phone number.
	$phone = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';
	if ( $phone && ! vsc_is_kh_phone( $phone ) ) {
		$errors->add( 'vsc_phone', __( 'Please enter a valid Cambodian phone number (e.g. 012 345 678).', 'vsc' ) );
	}

	// 5. Per-IP rate limit.
	if ( vsc_ip_over_limit() ) {
		$errors->add( 'vsc_rate', __( 'Too many orders from this connection. Please try again later or contact us.', 'vsc' ) );
		vsc_log_block( 'rate_limit' );
	}
}

/** Accept 0XXXXXXXX / 0XXXXXXXXX and +855 equivalents. */
function vsc_is_kh_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', $phone );
	if ( strpos( $digits, '855' ) === 0 ) {
		$digits = '0' . substr( $digits, 3 );
	}
	return (bool) preg_match( '/^0(1|6|7|8|9)\d{7,8}$/', $digits );
}

function vsc_client_ip() {
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

/** Max successful orders per IP per hour. */
function vsc_order_limit() {
	return (int) apply_filters( 'vsc_orders_per_ip_per_hour', 3 );
}

function vsc_ip_over_limit() {
	$key = 'vsc_ordc_' . md5( vsc_client_ip() );
	return ( (int) get_transient( $key ) ) >= vsc_order_limit();
}

add_action( 'woocommerce_checkout_order_processed', 'vsc_count_order' );
function vsc_count_order() {
	$key   = 'vsc_ordc_' . md5( vsc_client_ip() );
	$count = (int) get_transient( $key );
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
}

/** Record the source IP on every order so you can spot patterns later. */
add_action( 'woocommerce_checkout_create_order', 'vsc_stamp_order_ip' );
function vsc_stamp_order_ip( $order ) {
	$order->update_meta_data( '_vsc_ip', vsc_client_ip() );
}

function vsc_log_block( $reason ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( '[VSC spam guard] blocked (%s) ip=%s ua=%s', $reason, vsc_client_ip(), isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '-' ) );
	}
}

/* -------------------------------------------------------------------------
 * Cash on Delivery hardening — COD is the spam vector on this store.
 * ---------------------------------------------------------------------- */

/** Max order value a GUEST (not logged in) may place on COD. */
function vsc_cod_guest_max() {
	return (float) apply_filters( 'vsc_cod_guest_max_total', 150 );
}

/** Max COD orders per phone number per day. */
function vsc_cod_phone_limit() {
	return (int) apply_filters( 'vsc_cod_orders_per_phone_per_day', 2 );
}

/**
 * Hide COD entirely when the cart/customer already looks wrong.
 * Country + cart total are reliable in the session; phone is not, so the
 * phone rules live in validation below.
 */
add_filter( 'woocommerce_available_payment_gateways', 'vsc_filter_cod_gateway' );
function vsc_filter_cod_gateway( $gateways ) {
	if ( is_admin() || ! isset( $gateways['cod'] ) || ! WC()->customer ) {
		return $gateways;
	}

	$country = WC()->customer->get_billing_country();
	if ( $country && ! in_array( $country, vsc_allowed_countries(), true ) ) {
		unset( $gateways['cod'] );
		return $gateways;
	}

	// High-value COD from a guest is the classic fake-order pattern.
	if ( ! is_user_logged_in() && WC()->cart && (float) WC()->cart->get_total( 'edit' ) > vsc_cod_guest_max() ) {
		unset( $gateways['cod'] );
	}

	return $gateways;
}

/** COD-specific checkout validation. */
add_action( 'woocommerce_after_checkout_validation', 'vsc_validate_cod', 20, 2 );
function vsc_validate_cod( $data, $errors ) {
	if ( empty( $data['payment_method'] ) || 'cod' !== $data['payment_method'] ) {
		return;
	}

	// COD must carry a real Cambodian address, not a blank country.
	if ( empty( $data['billing_country'] ) || ! in_array( $data['billing_country'], vsc_allowed_countries(), true ) ) {
		$errors->add( 'vsc_cod', __( 'Cash on Delivery is available for deliveries inside Cambodia only.', 'vsc' ) );
		vsc_log_block( 'cod_country' );
		return;
	}

	// COD needs a usable address — the driver has to find it.
	if ( empty( trim( (string) ( $data['billing_address_1'] ?? '' ) ) ) ) {
		$errors->add( 'vsc_cod', __( 'Please enter a delivery address for Cash on Delivery orders.', 'vsc' ) );
	}

	// COD needs a reachable KH phone.
	$phone = $data['billing_phone'] ?? '';
	if ( ! vsc_is_kh_phone( $phone ) ) {
		$errors->add( 'vsc_cod', __( 'Cash on Delivery requires a valid Cambodian phone number.', 'vsc' ) );
		vsc_log_block( 'cod_phone' );
		return;
	}

	// Per-phone daily COD cap.
	if ( vsc_cod_phone_over_limit( $phone ) ) {
		$errors->add( 'vsc_cod', __( 'This phone number has reached today\'s Cash on Delivery limit. Please contact us to order more.', 'vsc' ) );
		vsc_log_block( 'cod_phone_limit' );
	}
}

function vsc_normalize_kh_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( strpos( $digits, '855' ) === 0 ) {
		$digits = '0' . substr( $digits, 3 );
	}
	return $digits;
}

function vsc_cod_phone_key( $phone ) {
	return 'vsc_codp_' . md5( vsc_normalize_kh_phone( $phone ) );
}

function vsc_cod_phone_over_limit( $phone ) {
	return ( (int) get_transient( vsc_cod_phone_key( $phone ) ) ) >= vsc_cod_phone_limit();
}

/** Count COD orders per phone, and flag guest COD orders for manual review. */
add_action( 'woocommerce_checkout_order_processed', 'vsc_after_cod_order', 20, 3 );
function vsc_after_cod_order( $order_id, $posted_data, $order ) {
	if ( ! $order || 'cod' !== $order->get_payment_method() ) {
		return;
	}

	$phone = $order->get_billing_phone();
	if ( $phone ) {
		$key   = vsc_cod_phone_key( $phone );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	// First-time guest COD: hold it so a human confirms by phone before dispatch.
	if ( ! $order->get_customer_id() ) {
		$order->add_order_note( __( 'Guest COD order — confirm by phone before dispatch.', 'vsc' ) );
		if ( apply_filters( 'vsc_hold_guest_cod', true, $order ) ) {
			$order->update_status( 'on-hold', __( 'Awaiting phone confirmation (guest COD).', 'vsc' ) );
		}
	}
}
