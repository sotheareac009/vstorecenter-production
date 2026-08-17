<?php
/**
 * "Order via Telegram" — WooCommerce Payment Gateway
 *
 * Same idea as the out-of-stock "Contact Seller" button: the customer places the
 * order, then taps through to the shop's Telegram with a message already written
 * for them. The customer sends it from their own account, so nothing private is
 * posted to a public channel by us.
 *
 * Flow:
 *   1. Customer picks "Order via Telegram" at checkout -> order created (on-hold).
 *   2. Redirected to the order-received page, which shows a big Telegram button.
 *   3. Tapping it opens t.me/<shop> with a pre-filled message headed by the
 *      customer's name and phone, followed by the order number, items and total.
 *
 * Config (.env / wp-config):
 *   SHOPYS_TG_SALE_VSTORE   Telegram username for V-Store  (e.g. vstorestreet271)
 *   SHOPYS_TG_SALE_VTECH    Telegram username for V-Tech   (e.g. VTechGamingCenter)
 *   SHOPYS_TG_SALE          Fallback when no shop is selected
 *   SHOPYS_TG_ORDER_ENABLED 1 shows the method at checkout, 0 hides it
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Read a constant or environment variable. */
function shopys_tgorder_cfg( $key ) {
    $key = strtoupper( $key );
    if ( defined( $key ) ) return trim( (string) constant( $key ) );
    $v = getenv( $key );
    return ( $v !== false ) ? trim( (string) $v ) : '';
}

/**
 * Telegram username for an order's shop branch, without the leading "@".
 * Falls back to SHOPYS_TG_SALE, then to the out-of-stock Contact Seller account.
 */
function shopys_tgorder_username( $order = null ) {
    $branch = '';
    if ( $order instanceof WC_Abstract_Order ) {
        $branch = strtoupper( (string) $order->get_meta( '_shop_branch' ) ); // VSTORE | VTECH
    }
    $u = $branch !== '' ? shopys_tgorder_cfg( 'shopys_tg_sale_' . $branch ) : '';
    if ( $u === '' ) $u = shopys_tgorder_cfg( 'shopys_tg_sale' );
    if ( $u === '' && function_exists( 'shopys_contact_seller_username' ) ) {
        $u = shopys_contact_seller_username();
    }
    return ltrim( trim( $u ), '@' );
}

/**
 * The message the customer sends. Headed by their name and phone so the seller
 * can identify them immediately, then the order itself.
 */
function shopys_tgorder_message( WC_Order $order ) {
    $name  = trim( $order->get_formatted_billing_full_name() );
    if ( $name === '' ) $name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
    $phone = trim( (string) $order->get_billing_phone() );

    $lines   = array();
    $lines[] = '👤 ' . ( $name !== '' ? $name : '-' );
    $lines[] = '📞 ' . ( $phone !== '' ? $phone : '-' );
    $lines[] = '';
    $lines[] = '🧾 Order #' . $order->get_order_number();

    $branch_names = function_exists( 'shopys_shop_branches' ) ? shopys_shop_branches() : array();
    $branch       = (string) $order->get_meta( '_shop_branch' );
    if ( $branch !== '' && isset( $branch_names[ $branch ] ) ) {
        $lines[] = '🏬 ' . $branch_names[ $branch ];
    }

    $lines[] = '';
    $lines[] = '📦 Items';
    foreach ( $order->get_items() as $item ) {
        $lines[] = '  • ' . $item->get_name() . ' ×' . $item->get_quantity()
                 . ' — ' . html_entity_decode( wp_strip_all_tags( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
    }

    $deliv = (string) $order->get_meta( '_delivery_option' );
    if ( $deliv !== '' ) {
        $lines[] = '';
        $lines[] = '🚚 ' . $deliv;
        if ( strtolower( $deliv ) !== 'pick up' ) {
            $addr = trim( wp_strip_all_tags( str_replace( '<br/>', ', ', $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ) ) );
            if ( $addr !== '' ) $lines[] = '📍 ' . $addr;
        }
    }

    $lines[] = '';
    $lines[] = '💰 Total: ' . html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );

    return implode( "\n", $lines );
}

/** Full t.me deep link for an order (pre-filled message). '' if no username set. */
function shopys_tgorder_link( WC_Order $order ) {
    $user = shopys_tgorder_username( $order );
    if ( $user === '' ) return '';
    // rawurlencode keeps the %0A newlines that Telegram needs for line breaks.
    return 'https://t.me/' . rawurlencode( $user ) . '?text=' . rawurlencode( shopys_tgorder_message( $order ) );
}

/* ───────────────────────── Register the gateway ───────────────────────── */

add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
    $gateways[] = 'WC_Gateway_Shopys_TelegramOrder';
    return $gateways;
} );

// The theme is loaded AFTER plugins_loaded has already fired, so that hook alone
// would never run and the class would never exist — WooCommerce would then report
// "no available payment methods". Hook it for safety, and also call it directly at
// the bottom of this file (same pattern as khqrpay-gateway.php).
add_action( 'plugins_loaded', 'shopys_tgorder_load_gateway' );

function shopys_tgorder_load_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'WC_Gateway_Shopys_TelegramOrder' ) ) return;

    class WC_Gateway_Shopys_TelegramOrder extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'tgorder';
            $this->method_title       = 'Order via Telegram';
            $this->method_description = 'Customer places the order, then sends it to your shop Telegram with one tap. Set SHOPYS_TG_SALE_VSTORE / SHOPYS_TG_SALE_VTECH in .env.';
            $this->has_fields         = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title', 'Order via Telegram' );
            $this->description = $this->get_option( 'description', '' );
            $this->enabled     = $this->get_option( 'enabled', 'no' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array( 'title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable Order via Telegram', 'default' => 'no' ),
                'title'       => array( 'title' => 'Title', 'type' => 'text', 'default' => 'Order via Telegram', 'desc_tip' => true, 'description' => 'Shown to the customer at checkout.' ),
                'description' => array( 'title' => 'Description', 'type' => 'textarea', 'default' => '' ),
                'accounts'    => array(
                    'title'       => 'Telegram accounts',
                    'type'        => 'title',
                    'description' => 'V-Store: <code>' . esc_html( shopys_tgorder_cfg( 'shopys_tg_sale_vstore' ) ?: '(not set)' ) . '</code><br>'
                                   . 'V-Tech: <code>' . esc_html( shopys_tgorder_cfg( 'shopys_tg_sale_vtech' ) ?: '(not set)' ) . '</code>',
                ),
            );
        }

        public function is_available() {
            // SHOPYS_TG_ORDER_ENABLED in .env overrides the admin toggle, exactly like
            // KHQR_ENABLED does for the KHQR gateway.
            $flag = shopys_tgorder_cfg( 'shopys_tg_order_enabled' );
            $on   = ( $flag !== '' )
                ? in_array( strtolower( $flag ), array( '1', 'true', 'yes', 'on' ), true )
                : ( 'yes' === $this->enabled );
            return $on;
        }

        /** Branded card under the payment option, matching the KHQR one. */
        public function payment_fields() {
            ?>
            <div class="tgo-pf">
                <div class="tgo-pf-accent" aria-hidden="true"></div>
                <div class="tgo-pf-badge">
                    <svg viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.1-3.02-1.97 1.91c-.22.22-.4.4-.83.4z"/></svg>
                    <span>TELEGRAM</span>
                </div>
                <div class="tgo-pf-info">
                    <strong><?php esc_html_e( 'Send your order to our Telegram', 'shopys' ); ?></strong>
                    <span class="tgo-pf-sub"><?php esc_html_e( 'We reply fast and confirm payment & delivery with you directly.', 'shopys' ); ?></span>
                    <span class="tgo-pf-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        <?php esc_html_e( 'Your name, phone and order are filled in automatically', 'shopys' ); ?>
                    </span>
                </div>
            </div>
            <style>
            .tgo-pf{ display:flex; align-items:center; gap:14px; margin:10px 0 4px; padding:14px 16px;
                background:linear-gradient(135deg,#fff,#fafbfc); border:1px solid #e7e9ee; border-radius:14px;
                box-shadow:0 6px 20px rgba(15,23,42,.06); font-family:'Play','Battambang',-apple-system,sans-serif; }
            .tgo-pf-badge{ flex-shrink:0; width:62px; height:62px; border-radius:14px; display:flex; flex-direction:column;
                align-items:center; justify-content:center; gap:2px; color:#fff;
                background:linear-gradient(135deg,#3aa8e8,#1c86c6); box-shadow:0 8px 18px rgba(28,134,198,.34); }
            .tgo-pf-badge svg{ width:24px; height:24px; }
            .tgo-pf-badge span{ font-size:8.5px; font-weight:800; letter-spacing:.6px; }
            .tgo-pf-info{ display:flex; flex-direction:column; gap:3px; }
            .tgo-pf-info strong{ font-size:14.5px; font-weight:800; color:#0d1117; letter-spacing:-.2px; }
            .tgo-pf-sub{ font-size:12px; color:#5b6472; font-weight:600; }
            .tgo-pf-secure{ display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#0a9d4a; font-weight:600; margin-top:2px; }
            .tgo-pf-secure svg{ width:13px; height:13px; }
            .tgo-pf-accent{ display:none; }
            @media(max-width:768px){
                .tgo-pf{ flex-direction:column; align-items:stretch; gap:0; padding:0; text-align:center;
                    border-radius:15px; overflow:hidden; background:linear-gradient(180deg,#fff,#fcfcfd); }
                .tgo-pf-accent{ display:block; height:4px; background:linear-gradient(90deg,#3aa8e8,#1c86c6 55%,#14699e); }
                .tgo-pf-badge{ margin:15px auto 0; width:56px; height:56px; border-radius:15px; }
                .tgo-pf-info{ align-items:center; gap:6px; padding:10px 12px 14px; }
                .tgo-pf-info strong{ font-size:13.5px; line-height:1.3; }
                .tgo-pf-secure{ justify-content:center; font-size:10px; font-weight:700;
                    background:#f4fdf8; border:1px solid #e4f6ec; border-radius:50px; padding:6px 13px; }
            }
            </style>
            <?php
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            // On-hold: the order is placed and stock reserved, awaiting the seller's
            // confirmation over Telegram. This also fires the existing Telegram
            // notifier to the private staff group, same as the Walk-In flow.
            $order->update_status( 'on-hold', __( 'Awaiting confirmation via Telegram.', 'shopys' ) );
            if ( WC()->cart ) WC()->cart->empty_cart();
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }
    }
}

/* ── Order-received page: the big "Send my order on Telegram" button ── */

add_action( 'woocommerce_thankyou', 'shopys_tgorder_thankyou_button', 5 );
function shopys_tgorder_thankyou_button( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_payment_method() !== 'tgorder' ) return;

    $link = shopys_tgorder_link( $order );
    if ( $link === '' ) return;
    ?>
    <div class="tgo-cta">
        <div class="tgo-cta-head">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.1-3.02-1.97 1.91c-.22.22-.4.4-.83.4z"/></svg>
            <span><?php esc_html_e( 'One last step', 'shopys' ); ?></span>
        </div>
        <p><?php esc_html_e( 'Tap the button to send your order to us on Telegram. Your name, phone and order details are already filled in — just press send.', 'shopys' ); ?></p>
        <a href="<?php echo esc_attr( $link ); ?>" target="_blank" rel="noopener" class="tgo-cta-btn" id="tgo-send">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.1-3.02-1.97 1.91c-.22.22-.4.4-.83.4z"/></svg>
            <?php esc_html_e( 'Send my order on Telegram', 'shopys' ); ?>
        </a>
    </div>
    <style>
    .tgo-cta{ max-width:520px; margin:0 auto 26px; padding:20px 22px; background:#fff; border:1px solid #e7e9ee;
        border-radius:18px; box-shadow:0 12px 34px rgba(15,23,42,.08); text-align:center;
        font-family:'Play','Battambang',-apple-system,sans-serif; }
    .tgo-cta-head{ display:inline-flex; align-items:center; gap:8px; color:#1c86c6; font-weight:800; font-size:13px;
        text-transform:uppercase; letter-spacing:1px; }
    .tgo-cta-head svg{ width:17px; height:17px; }
    .tgo-cta p{ font-size:13.5px; color:#5b6472; line-height:1.6; margin:8px 0 16px; }
    .tgo-cta-btn{ display:flex; align-items:center; justify-content:center; gap:10px; padding:15px 20px; border-radius:13px;
        background:linear-gradient(135deg,#3aa8e8,#1c86c6); color:#fff !important; font-weight:800; font-size:1rem;
        text-decoration:none !important; box-shadow:0 10px 24px rgba(28,134,198,.32); transition:transform .15s ease; }
    .tgo-cta-btn:active{ transform:scale(.97); }
    .tgo-cta-btn svg{ width:19px; height:19px; }
    </style>
    <?php
}

/* ── Same button on the customer's order detail / thank-you email footer view ── */

add_action( 'woocommerce_order_details_after_order_table', 'shopys_tgorder_order_detail_button', 20 );
function shopys_tgorder_order_detail_button( $order ) {
    if ( ! is_a( $order, 'WC_Abstract_Order' ) ) return;
    if ( $order->get_payment_method() !== 'tgorder' ) return;
    if ( $order->is_paid() || in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) return;
    if ( did_action( 'woocommerce_thankyou' ) ) return; // already shown above
    shopys_tgorder_thankyou_button( $order->get_id() );
}

// Define the gateway class now — see the note above shopys_tgorder_load_gateway().
shopys_tgorder_load_gateway();
