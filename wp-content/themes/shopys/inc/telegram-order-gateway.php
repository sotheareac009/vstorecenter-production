<?php
/**
 * "Order via Telegram" — WooCommerce Payment Gateway
 *
 * The customer places the order and Telegram opens by itself with the message
 * already written. They send it from their own account, so nothing private is
 * posted to a public channel by us.
 *
 * Flow:
 *   1. Customer picks "Order via Telegram" at checkout -> order created (on-hold).
 *   2. Redirected to the order-received page.
 *   3. That page opens t.me/<shop> automatically (once per order), with a message
 *      headed by the customer's name and phone, then the order number, items and
 *      total. A fallback link is shown in case the browser blocks the redirect.
 *
 * Telegram cannot be made to SEND on the customer's behalf -- a t.me link can only
 * pre-fill the compose box, so the customer still presses send themselves.
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

    $money = function ( $v ) use ( $order ) {
        return html_entity_decode( wp_strip_all_tags( wc_price( $v, array( 'currency' => $order->get_currency() ) ) ) );
    };
    $rule = '━━━━━━━━━━━━━━━';

    // Each block is a group of lines; blocks are joined by a rule, so a block that
    // ends up empty (e.g. no delivery option) never leaves a stray separator.
    $blocks = array();

    // Opening: the customer is writing to the shop, so it reads as their words.
    $blocks[] = array(
        __( 'Hello 👋 I have just placed an order on your website.', 'shopys' ),
        __( 'Could you please check and confirm my order? Thank you!', 'shopys' ),
    );

    $head         = array( '🧾 ' . sprintf( __( 'Order No. %s', 'shopys' ), '#' . $order->get_order_number() ) );
    $branch_names = function_exists( 'shopys_shop_branches' ) ? shopys_shop_branches() : array();
    $branch       = (string) $order->get_meta( '_shop_branch' );
    if ( $branch !== '' && isset( $branch_names[ $branch ] ) ) {
        $head[] = '🏬 ' . $branch_names[ $branch ];
    }
    $blocks[] = $head;

    $blocks[] = array(
        '👤 ' . __( 'Name', 'shopys' ) . ': ' . ( $name !== '' ? $name : '-' ),
        '📞 ' . __( 'Phone', 'shopys' ) . ': ' . ( $phone !== '' ? $phone : '-' ),
    );

    $items = array( '🛍 ' . __( 'My order', 'shopys' ) );
    foreach ( $order->get_items() as $item ) {
        $items[] = '   • ' . $item->get_name() . '  ×' . $item->get_quantity() . '  —  ' . $money( $item->get_total() );
        // Laptop configurator selections (RAM / Storage) stored as line-item meta.
        if ( function_exists( 'shopys_lc_order_item_lines' ) ) {
            foreach ( shopys_lc_order_item_lines( $item ) as $cfg_line ) {
                $items[] = '      ↳ ' . $cfg_line;
            }
        }
    }
    $blocks[] = $items;

    $deliv = (string) $order->get_meta( '_delivery_option' );
    if ( $deliv !== '' ) {
        $is_pickup = ( strtolower( $deliv ) === 'pick up' );
        $recv      = array( ( $is_pickup ? '🏬 ' : '🚚 ' ) . __( 'Receiving', 'shopys' ) . ': ' . $deliv );
        if ( ! $is_pickup ) {
            $addr = trim( wp_strip_all_tags( str_replace( '<br/>', ', ', $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ) ) );
            if ( $addr !== '' ) $recv[] = '📍 ' . __( 'Address', 'shopys' ) . ': ' . $addr;
        }
        $blocks[] = $recv;
    }

    $blocks[] = array( '💰 ' . __( 'Total', 'shopys' ) . ': ' . $money( $order->get_total() ) );
    $blocks[] = array( __( 'Please let me know the next step. 🙏', 'shopys' ) );

    $out = array();
    foreach ( $blocks as $i => $block ) {
        if ( $i > 0 ) $out[] = $rule;
        foreach ( $block as $line ) $out[] = $line;
    }
    return implode( "\n", $out );
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

/* ── Order-received page: open Telegram automatically ── */

add_action( 'woocommerce_thankyou', 'shopys_tgorder_thankyou_button', 5 );
function shopys_tgorder_thankyou_button( $order_id, $auto = true ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_payment_method() !== 'tgorder' ) return;

    $link = shopys_tgorder_link( $order );
    if ( $link === '' ) return;

    ?>
    <div class="tgo-cta" id="tgo-cta" data-auto="<?php echo $auto ? '1' : '0'; ?>" data-order="<?php echo esc_attr( $order->get_id() ); ?>"
         data-blocked-title="<?php esc_attr_e( 'Send your order', 'shopys' ); ?>"
         data-blocked-note="<?php esc_attr_e( 'Your browser blocked the Telegram tab. Tap below to open it — your order is already written out.', 'shopys' ); ?>"
         data-blocked-cta="<?php esc_attr_e( 'Open Telegram', 'shopys' ); ?>">
        <div class="tgo-cta-head">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.1-3.02-1.97 1.91c-.22.22-.4.4-.83.4z"/></svg>
            <span id="tgo-title"><?php echo $auto ? esc_html__( 'Opening Telegram…', 'shopys' ) : esc_html__( 'Send your order', 'shopys' ); ?></span>
        </div>
        <p id="tgo-note">
            <?php
            if ( $auto ) {
                esc_html_e( 'Your order is already written out — just press send in Telegram to confirm it with us.', 'shopys' );
            } elseif ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
                // The tab opened from the "Place order" click is already showing Telegram.
                esc_html_e( 'Telegram has opened in a new tab with your order ready — just press send there.', 'shopys' );
            } else {
                esc_html_e( 'Your name, phone and order details are already filled in — just press send.', 'shopys' );
            }
            ?>
        </p>
        <a href="<?php echo esc_attr( $link ); ?>" target="_blank" rel="noopener" class="tgo-cta-link" id="tgo-send">
            <?php esc_html_e( 'Telegram did not open? Tap here', 'shopys' ); ?>
        </a>
    </div>
    <style>
    .tgo-cta{ max-width:520px; margin:0 auto 26px; padding:20px 22px; background:#fff; border:1px solid #e7e9ee;
        border-radius:18px; box-shadow:0 12px 34px rgba(15,23,42,.08); text-align:center;
        font-family:'Play','Battambang',-apple-system,sans-serif; }
    .tgo-cta-head{ display:inline-flex; align-items:center; gap:8px; color:#1c86c6; font-weight:800; font-size:13px;
        text-transform:uppercase; letter-spacing:1px; }
    .tgo-cta-head svg{ width:17px; height:17px; }
    .tgo-cta p{ font-size:13.5px; color:#5b6472; line-height:1.6; margin:8px 0 14px; }
    .tgo-cta-link{ display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 20px;
        border-radius:12px; background:linear-gradient(135deg,#3aa8e8,#1c86c6); color:#fff !important; font-weight:800;
        font-size:.92rem; text-decoration:none !important; box-shadow:0 8px 20px rgba(28,134,198,.28); }
    .tgo-cta-link:active{ transform:scale(.97); }
    .tgo-cta-link.is-primary{ display:flex; width:100%; padding:15px 20px; font-size:1rem;
        box-shadow:0 10px 24px rgba(28,134,198,.32); }
    </style>
    <script>
    (function(){
        var box = document.getElementById('tgo-cta');
        if (!box || box.getAttribute('data-auto') !== '1') return;
        var link = document.getElementById('tgo-send');
        if (!link) return;

        // Signal once per order. A refresh must not re-open Telegram.
        var key = 'tgo-signalled-' + box.getAttribute('data-order');
        try { if (sessionStorage.getItem(key)) return; sessionStorage.setItem(key, '1'); } catch(e){}

        // This page cannot open a tab itself (no click), so it hands the link to the
        // tab that the "Place order" click already parked on the waiting page. One
        // second lets the order detail settle on screen first.
        setTimeout(function(){
            var payload = JSON.stringify({ url: link.href, t: Date.now() });
            try { localStorage.setItem('shopys_tgo_url', payload); } catch(e){}
            try { var bc = new BroadcastChannel('shopys_tgo'); bc.postMessage(payload); bc.close(); } catch(e){}
        }, 1000);
    })();
    </script>
    <?php
}

/* ── Customer's order detail page: link only, never auto-open ── */

add_action( 'woocommerce_order_details_after_order_table', 'shopys_tgorder_order_detail_button', 20 );
function shopys_tgorder_order_detail_button( $order ) {
    if ( ! is_a( $order, 'WC_Abstract_Order' ) ) return;
    if ( $order->get_payment_method() !== 'tgorder' ) return;
    if ( $order->is_paid() || in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) return;
    if ( did_action( 'woocommerce_thankyou' ) ) return; // already shown above
    // Revisiting an old order should not relaunch Telegram — show the link only.
    shopys_tgorder_thankyou_button( $order->get_id(), false );
}

/* ── Open Telegram in a new tab, 1s after the order page appears ───────────────
 * A browser only grants a new tab from a real click, and the order-received page
 * has none — so the tab is CREATED on the "Place order" click and parked on a
 * waiting page. It does nothing until the order-received page has rendered, then
 * that page hands it the Telegram link and the parked tab navigates to it.
 *
 * Result: exactly one Telegram tab, opened a second after the order detail shows.
 */

add_action( 'template_redirect', 'shopys_tgorder_go_handler' );
function shopys_tgorder_go_handler() {
    if ( empty( $_GET['shopys_tgo_go'] ) ) return;
    nocache_headers();
    ?><!doctype html>
    <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php esc_html_e( 'Preparing your order…', 'shopys' ); ?></title>
    <style>
     body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f7f9;
        font-family:'Play','Battambang',-apple-system,BlinkMacSystemFont,sans-serif;color:#0d1117;text-align:center;padding:24px;}
     .b{max-width:340px}
     .s{width:38px;height:38px;margin:0 auto 18px;border:3px solid #dbe3ea;border-top-color:#1c86c6;border-radius:50%;animation:sp .8s linear infinite}
     @keyframes sp{to{transform:rotate(360deg)}}
     h1{font-size:17px;font-weight:800;margin:0 0 8px}
     p{font-size:13.5px;color:#5b6472;line-height:1.6;margin:0}
    </style></head><body><div class="b" id="w">
      <div class="s"></div>
      <h1><?php esc_html_e( 'Preparing your order…', 'shopys' ); ?></h1>
      <p><?php esc_html_e( 'Telegram will open here in a moment with your order ready to send.', 'shopys' ); ?></p>
    </div>
    <script>
    (function(){
        var done = false;
        function go(raw){
            if (done || !raw) return;
            var d; try { d = JSON.parse(raw); } catch(e){ return; }
            // Ignore a leftover link from an earlier order.
            if (!d || !d.url || !d.t || (Date.now() - d.t) > 120000) return;
            done = true;
            try { localStorage.removeItem('shopys_tgo_url'); } catch(e){}
            window.location.href = d.url;
        }
        // The order page may signal before or after this tab finishes loading, so
        // listen for the event AND read whatever is already there.
        window.addEventListener('storage', function(e){ if (e.key === 'shopys_tgo_url') go(e.newValue); });
        try { var bc = new BroadcastChannel('shopys_tgo'); bc.onmessage = function(e){ go(e.data); }; } catch(e){}
        try { go(localStorage.getItem('shopys_tgo_url')); } catch(e){}

        setTimeout(function(){
            if (done) return;
            document.getElementById('w').innerHTML =
                '<h1><?php echo esc_js( __( 'Nothing to send yet', 'shopys' ) ); ?></h1>' +
                '<p><?php echo esc_js( __( 'You can close this tab — your order page has a link to send it on Telegram.', 'shopys' ) ); ?></p>';
        }, 45000);
    })();
    </script>
    </body></html>
    <?php
    exit;
}

/* ── Checkout: create the parked tab on the "Place order" click ── */

add_action( 'wp_footer', 'shopys_tgorder_checkout_js', 99 );
function shopys_tgorder_checkout_js() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) return;
    ?>
    <script>
    (function(){
        // Opening inside the click handler keeps the browser's "user activation",
        // which is the only state in which a new tab is allowed. Opening later —
        // after checkout's AJAX returns, or from the order page — is blocked.
        function selectedIsTgorder(){
            var el = document.querySelector('input[name="payment_method"]:checked');
            return !!el && el.value === 'tgorder';
        }
        document.addEventListener('click', function(e){
            var btn = e.target.closest && e.target.closest('#place_order');
            if (!btn || !selectedIsTgorder()) return;
            try {
                // Clear any stale link so the parked tab cannot act on an old order.
                localStorage.removeItem('shopys_tgo_url');
            } catch(err){}
            try { window.open('<?php echo esc_url_raw( add_query_arg( 'shopys_tgo_go', 1, home_url( '/' ) ) ); ?>', '_blank'); } catch(err){}
        }, true);
    })();
    </script>
    <?php
}

// Define the gateway class now — see the note above shopys_tgorder_load_gateway().
shopys_tgorder_load_gateway();
