<?php
/**
 * My Account navigation — Shopys override with leading icons.
 *
 * Mirrors the upstream template at WooCommerce 9.3.0 + injects an icon
 * before the label. Icons resolved via shopys_myaccount_get_icons().
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_before_account_navigation' );

$shopys_icons = function_exists( 'shopys_myaccount_get_icons' ) ? shopys_myaccount_get_icons() : array();
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
    <ul>
        <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
            $icon = isset( $shopys_icons[ $endpoint ] )
                ? $shopys_icons[ $endpoint ]
                : ( isset( $shopys_icons['default'] ) ? $shopys_icons['default'] : '' );
        ?>
            <li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
                    <?php if ( $icon ) : ?>
                        <span class="sai-nav-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — trusted SVG markup ?></span>
                    <?php endif; ?>
                    <span class="sai-nav-label"><?php echo esc_html( $label ); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' );
