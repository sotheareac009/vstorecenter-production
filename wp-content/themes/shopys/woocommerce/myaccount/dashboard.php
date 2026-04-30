<?php
/**
 * My Account Dashboard — Shopys override.
 *
 * Replaces the default Hello/description text with the premium
 * stat-card dashboard. The standard dashboard hooks still fire so
 * other plugins that rely on them keep working.
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'shopys_myaccount_dashboard_premium' ) ) {
    shopys_myaccount_dashboard_premium();
}

/** Standard hooks (kept for compatibility with other plugins). */
do_action( 'woocommerce_account_dashboard' );
do_action( 'woocommerce_before_my_account' );
do_action( 'woocommerce_after_my_account' );
