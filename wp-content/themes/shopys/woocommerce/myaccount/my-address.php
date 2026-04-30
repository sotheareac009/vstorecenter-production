<?php
/**
 * My Addresses — Shopys override.
 *
 * Mirrors WooCommerce 9.3.0 my-address.php with cleaner button label
 * (just "Edit" / "Add" instead of "Edit Billing address") and tighter
 * markup so the card titles never need to wrap awkwardly.
 *
 * @package Shopys
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing'  => __( 'Billing address', 'woocommerce' ),
            'shipping' => __( 'Shipping address', 'woocommerce' ),
        ),
        $customer_id
    );
} else {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing' => __( 'Billing address', 'woocommerce' ),
        ),
        $customer_id
    );
}

$oldcol = 1;
$col    = 1;
?>

<p class="sai-addresses-intro">
    <?php echo apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</p>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    <div class="u-columns woocommerce-Addresses col2-set addresses">
<?php endif; ?>

<?php foreach ( $get_addresses as $name => $address_title ) :
    $address = wc_get_account_formatted_address( $name );
    $col     = $col * -1;
    $oldcol  = $oldcol * -1;
?>

    <div class="u-column<?php echo $col < 0 ? 1 : 2; ?> col-<?php echo $oldcol < 0 ? 1 : 2; ?> woocommerce-Address">
        <header class="woocommerce-Address-title title">
            <h2><?php echo esc_html( $address_title ); ?></h2>
            <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="edit">
                <?php echo $address ? esc_html__( 'Edit', 'shopys' ) : esc_html__( 'Add', 'shopys' ); ?>
            </a>
        </header>
        <address>
            <?php
            if ( $address ) {
                echo wp_kses_post( $address );
            } else {
                echo '<span class="sai-address-empty">' . esc_html__( 'You have not set up this type of address yet.', 'shopys' ) . '</span>';
            }

            do_action( 'woocommerce_my_account_after_my_address', $name );
            ?>
        </address>
    </div>

<?php endforeach; ?>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
    </div>
<?php endif;
