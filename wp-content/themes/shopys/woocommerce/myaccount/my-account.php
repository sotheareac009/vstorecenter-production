<?php
/**
 * Premium My Account layout override.
 *
 * @package Shopys
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$greeting     = $current_user && $current_user->exists() ? $current_user->display_name : __( 'Guest', 'shopys' );

// Resolve the user's primary role into a translated display name.
$role_display = '';
if ( $current_user && $current_user->exists() ) {
    $role_keys = (array) $current_user->roles;
    if ( ! empty( $role_keys ) ) {
        $primary    = $role_keys[0];
        $role_names = function_exists( 'wp_roles' ) ? wp_roles()->role_names : array();
        $role_label = isset( $role_names[ $primary ] )
            ? translate_user_role( $role_names[ $primary ] )
            : ucwords( str_replace( '_', ' ', $primary ) );
        $role_display = $role_label;
    }
}
if ( ! $role_display ) {
    $role_display = __( 'Member', 'shopys' );
}
?>

<section class="shopys-account-shell">
    <div class="shopys-account-hero">
        <div class="shopys-account-hero__eyebrow"><?php esc_html_e( 'Member Space', 'shopys' ); ?></div>
        <div class="shopys-account-hero__content">
            <div>
                <h1 class="shopys-account-hero__title"><?php esc_html_e( 'My Account', 'shopys' ); ?></h1>
                <p class="shopys-account-hero__text">
                    <?php
                    printf(
                        /* translators: %s: user display name */
                        esc_html__( 'Welcome back, %s. Track orders, manage addresses, and keep your account details polished in one place.', 'shopys' ),
                        esc_html( $greeting )
                    );
                    ?>
                </p>
            </div>
            <div class="shopys-account-hero__badge">
                <span class="shopys-account-hero__badge-label"><?php esc_html_e( 'Access', 'shopys' ); ?></span>
                <strong><?php echo esc_html( $role_display ); ?></strong>
            </div>
        </div>
    </div>

    <div class="shopys-account-layout">
        <aside class="shopys-account-sidebar">
            <?php do_action( 'woocommerce_account_navigation' ); ?>
        </aside>

        <div class="shopys-account-main">
            <div class="shopys-account-card">
                <div class="woocommerce-MyAccount-content">
                    <?php do_action( 'woocommerce_account_content' ); ?>
                </div>
            </div>
        </div>
    </div>
</section>
