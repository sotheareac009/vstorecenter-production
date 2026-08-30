<?php
/**
 * Laptop Configurator (RAM + Storage upgrades on the product card)
 *
 * Adds two dropdowns to laptop cards in the premium product grid. Options are
 * pulled from the RAM and Storage product categories; picking one adds that
 * product's price on top of the laptop price. The resulting cart line is
 * flagged "Custom"; leaving both dropdowns on the default keeps it "Standard".
 *
 * Category sources can be re-pointed with the `shopys_lc_option_categories`
 * and `shopys_lc_laptop_categories` filters.
 *
 * @package Shopys
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const SHOPYS_LC_PARTS = array( 'ram', 'storage' );

/**
 * Labels shown for each configurable part.
 */
function shopys_lc_part_label( $part ) {
    $labels = array(
        'ram'     => __( 'RAM', 'shopys' ),
        'storage' => __( 'Storage', 'shopys' ),
    );
    return $labels[ $part ] ?? ucfirst( $part );
}

/**
 * wc_price() as plain text — tags stripped and entities (e.g. the currency
 * symbol) decoded, so it is safe to run through esc_html().
 */
function shopys_lc_price_text( $amount ) {
    return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
}

/**
 * Product category slugs a card must belong to before it gets the configurator.
 */
function shopys_lc_laptop_categories() {
    return (array) apply_filters( 'shopys_lc_laptop_categories', array( 'laptop' ) );
}

/**
 * Product category slugs each dropdown is populated from.
 */
function shopys_lc_option_categories( $part ) {
    $defaults = array(
        'ram'     => array( 'ram' ),
        'storage' => array( 'storage-component', 'storage' ),
    );
    return (array) apply_filters( 'shopys_lc_option_categories', $defaults[ $part ] ?? array(), $part );
}

/**
 * Expand category slugs to their term IDs plus every descendant term ID.
 */
function shopys_lc_term_ids( array $slugs ) {
    $ids = array();
    foreach ( $slugs as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) continue;
        $ids[] = (int) $term->term_id;
        $kids  = get_term_children( (int) $term->term_id, 'product_cat' );
        if ( ! is_wp_error( $kids ) ) $ids = array_merge( $ids, array_map( 'intval', $kids ) );
    }
    return array_values( array_unique( $ids ) );
}

/**
 * Is this product one we should offer RAM/Storage upgrades on?
 * Only simple, purchasable, in-stock laptops — the card's AJAX add-to-cart
 * button is what carries the selection to the server.
 */
function shopys_lc_is_configurable( $product ) {
    if ( ! $product instanceof WC_Product ) return false;
    if ( ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) return false;

    $term_ids = shopys_lc_term_ids( shopys_lc_laptop_categories() );
    if ( empty( $term_ids ) ) return false;

    return (bool) has_term( $term_ids, 'product_cat', $product->get_id() );
}

/**
 * Read a DDR generation ("ddr4" / "ddr5") out of arbitrary text. Empty when absent.
 */
function shopys_lc_detect_ddr( $text ) {
    return preg_match( '/\bDDR\s*([45])\b/i', (string) $text, $m ) ? 'ddr' . $m[1] : '';
}

/**
 * The memory generation a laptop takes, read from its spec sheet.
 *
 * The "RAM:" line of the short description is checked first — a laptop's blurb
 * can mention another generation elsewhere (e.g. a comparison), and that line is
 * the authoritative one. Falls back to the full description, then the title.
 * Returns '' when nothing is detectable, in which case no filtering is applied.
 */
function shopys_lc_product_ddr( $product ) {
    static $memo = array();
    if ( ! $product instanceof WC_Product ) return '';

    $id = $product->get_id();
    if ( isset( $memo[ $id ] ) ) return $memo[ $id ];

    $short = wp_strip_all_tags( $product->get_short_description() );
    $long  = wp_strip_all_tags( $product->get_description() );

    $ram_line = '';
    foreach ( preg_split( '/[\r\n]+/', $short . "\n" . $long ) as $line ) {
        if ( stripos( $line, 'ram' ) !== false ) { $ram_line = $line; break; }
    }

    $gen = shopys_lc_detect_ddr( $ram_line );
    if ( ! $gen ) $gen = shopys_lc_detect_ddr( $short );
    if ( ! $gen ) $gen = shopys_lc_detect_ddr( $long );
    if ( ! $gen ) $gen = shopys_lc_detect_ddr( $product->get_name() );

    $gen = (string) apply_filters( 'shopys_lc_product_ddr', $gen, $product );
    $memo[ $id ] = $gen;
    return $gen;
}

/**
 * Every upgrade option for one part: published, purchasable, in-stock, priced
 * products from the configured categories, cheapest first. Cached for 6h.
 *
 * RAM options carry a `ddr` generation read from the product name, which is
 * where it is recorded reliably — the ddr4/ddr5 categories are not consistent.
 *
 * @return array[] list of array( id, name, price, ddr )
 */
function shopys_lc_all_options( $part ) {
    static $memo = array();
    if ( isset( $memo[ $part ] ) ) return $memo[ $part ];

    $slugs = shopys_lc_option_categories( $part );
    $key   = 'shopys_lc_opts_v2_' . $part . '_' . substr( md5( implode( ',', $slugs ) ), 0, 8 );

    $cached = get_transient( $key );
    if ( is_array( $cached ) ) {
        $memo[ $part ] = $cached;
        return $cached;
    }

    $options = array();
    if ( ! empty( $slugs ) ) {
        $ids = get_posts( array(
            'post_type'        => 'product',
            'post_status'      => 'publish',
            'posts_per_page'   => 100,
            'fields'           => 'ids',
            'orderby'          => 'meta_value_num',
            'meta_key'         => '_price',
            'order'            => 'ASC',
            'no_found_rows'    => true,
            'suppress_filters' => false,
            'tax_query'        => array( array(
                'taxonomy'         => 'product_cat',
                'field'            => 'slug',
                'terms'            => $slugs,
                'include_children' => true,
            ) ),
        ) );

        foreach ( $ids as $id ) {
            $p = wc_get_product( $id );
            if ( ! $p || ! $p->is_purchasable() || ! $p->is_in_stock() ) continue;
            $price = (float) $p->get_price();
            if ( $price <= 0 ) continue;
            $options[] = array(
                'id'    => (int) $id,
                'name'  => $p->get_name(),
                'price' => $price,
                'ddr'   => ( $part === 'ram' ) ? shopys_lc_detect_ddr( $p->get_name() ) : '',
            );
        }
    }

    set_transient( $key, $options, 6 * HOUR_IN_SECONDS );
    $memo[ $part ] = $options;
    return $options;
}

/**
 * The upgrade options offered for a given laptop.
 *
 * RAM is narrowed to the generation the laptop actually takes, so a DDR4
 * machine is never offered DDR5 sticks. If detection fails, or filtering would
 * leave nothing to choose from, the full list is returned unchanged.
 *
 * @return array[]
 */
function shopys_lc_get_options( $part, $for_product = null ) {
    $options = shopys_lc_all_options( $part );

    if ( $part !== 'ram' || ! $for_product instanceof WC_Product ) return $options;

    $gen = shopys_lc_product_ddr( $for_product );
    if ( ! $gen ) return $options;

    $matched = array_values( array_filter( $options, function ( $opt ) use ( $gen ) {
        return ! empty( $opt['ddr'] ) && $opt['ddr'] === $gen;
    } ) );

    return empty( $matched ) ? $options : $matched;
}

/**
 * Look up a single option by product ID, validated against the allowed list.
 * Returns null for anything a customer could have tampered into the request.
 */
function shopys_lc_find_option( $part, $product_id, $for_product = null ) {
    foreach ( shopys_lc_get_options( $part, $for_product ) as $opt ) {
        if ( $opt['id'] === (int) $product_id ) return $opt;
    }
    return null;
}

/**
 * Drop the cached option lists whenever a product changes.
 */
function shopys_lc_flush_options_cache() {
    foreach ( SHOPYS_LC_PARTS as $part ) {
        $slugs = shopys_lc_option_categories( $part );
        delete_transient( 'shopys_lc_opts_v2_' . $part . '_' . substr( md5( implode( ',', $slugs ) ), 0, 8 ) );
    }
}
add_action( 'woocommerce_update_product', 'shopys_lc_flush_options_cache' );
add_action( 'woocommerce_new_product',    'shopys_lc_flush_options_cache' );
add_action( 'deleted_post',               'shopys_lc_flush_options_cache' );

/* ─────────────────────────── Card UI ─────────────────────────── */

/**
 * Render the RAM + Storage dropdowns for a product card. Prints nothing when
 * the product isn't a configurable laptop or no upgrade options exist.
 */
function shopys_lc_render_card_config( $product ) {
    if ( ! shopys_lc_is_configurable( $product ) ) return;

    $sets = array();
    foreach ( SHOPYS_LC_PARTS as $part ) {
        $opts = shopys_lc_get_options( $part, $product );
        if ( ! empty( $opts ) ) $sets[ $part ] = $opts;
    }
    if ( empty( $sets ) ) return;

    // Tell the customer which memory generation the machine takes.
    $ddr = shopys_lc_product_ddr( $product );

    $base      = (float) $product->get_price();
    $collapsed = (bool) apply_filters( 'shopys_lc_default_collapsed', true, $product );
    $panel_id  = 'ppg-lc-panel-' . $product->get_id() . '-' . wp_rand( 1000, 9999 );
    ?>
    <div class="ppg-lc<?php echo $collapsed ? ' ppg-lc--collapsed' : ''; ?>" data-base="<?php echo esc_attr( $base ); ?>">
        <button type="button" class="ppg-lc-toggle" aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>"
                aria-controls="<?php echo esc_attr( $panel_id ); ?>">
            <span class="ppg-lc-toggle-text"><?php esc_html_e( 'Customize', 'shopys' ); ?></span>
            <svg class="ppg-lc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
        <div class="ppg-lc-body" id="<?php echo esc_attr( $panel_id ); ?>">
        <?php foreach ( $sets as $part => $opts ) : ?>
        <div class="ppg-lc-field">
            <label class="ppg-lc-label">
                <?php echo esc_html( shopys_lc_part_label( $part ) ); ?>
                <?php if ( $part === 'ram' && $ddr ) : ?>
                    <span class="ppg-lc-hint"><?php echo esc_html( strtoupper( $ddr ) ); ?></span>
                <?php endif; ?>
            </label>
            <select class="ppg-lc-select" data-part="<?php echo esc_attr( $part ); ?>"
                    aria-label="<?php echo esc_attr( sprintf( __( '%s upgrade', 'shopys' ), shopys_lc_part_label( $part ) ) ); ?>">
                <option value="" data-price="0"><?php esc_html_e( 'Standard (as listed)', 'shopys' ); ?></option>
                <?php foreach ( $opts as $opt ) : ?>
                <option value="<?php echo esc_attr( $opt['id'] ); ?>" data-price="<?php echo esc_attr( $opt['price'] ); ?>">
                    <?php echo esc_html( $opt['name'] ); ?> (+<?php echo esc_html( shopys_lc_price_text( $opt['price'] ) ); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
        </div><!-- /.ppg-lc-body -->
        <div class="ppg-lc-summary">
            <span class="ppg-lc-badge ppg-lc-badge--standard"><?php esc_html_e( 'Standard', 'shopys' ); ?></span>
            <span class="ppg-lc-total"><?php echo wp_kses_post( wc_price( $base ) ); ?></span>
        </div>
    </div>
    <?php
}

/**
 * Front-end assets. Piggybacks on the product-grid stylesheet/script handles.
 */
function shopys_lc_assets() {
    if ( ! class_exists( 'WooCommerce' ) ) return;

    wp_enqueue_style(
        'shopys-laptop-config',
        get_stylesheet_directory_uri() . '/css/laptop-config.css',
        array( 'shopys-product-grid' ),
        filemtime( get_stylesheet_directory() . '/css/laptop-config.css' )
    );
    wp_enqueue_script(
        'shopys-laptop-config',
        get_stylesheet_directory_uri() . '/js/laptop-config.js',
        array(),
        filemtime( get_stylesheet_directory() . '/js/laptop-config.js' ),
        true
    );
    wp_localize_script( 'shopys-laptop-config', 'shopysLcParams', array(
        'symbol'      => html_entity_decode( get_woocommerce_currency_symbol() ),
        'format'      => get_woocommerce_price_format(),
        'decimals'    => wc_get_price_decimals(),
        'decimalSep'  => wc_get_price_decimal_separator(),
        'thousandSep' => wc_get_price_thousand_separator(),
        'iStandard'   => __( 'Standard', 'shopys' ),
        'iCustom'     => __( 'Custom', 'shopys' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'shopys_lc_assets', 20 );

/* ────────────────────────── Cart plumbing ────────────────────────── */

/**
 * Capture the selected upgrades when the card's add-to-cart button fires.
 * Distinct configurations hash to distinct cart keys, so they stay separate lines.
 */
function shopys_lc_add_cart_item_data( $data, $product_id, $variation_id ) {
    $product = wc_get_product( $product_id );
    if ( ! shopys_lc_is_configurable( $product ) ) return $data;

    $parts = array();
    $extra = 0.0;
    foreach ( SHOPYS_LC_PARTS as $part ) {
        $raw = isset( $_REQUEST[ 'shopys_' . $part ] ) ? absint( wp_unslash( $_REQUEST[ 'shopys_' . $part ] ) ) : 0;
        if ( ! $raw ) continue;
        $opt = shopys_lc_find_option( $part, $raw, $product );
        if ( ! $opt ) continue; // unknown/ineligible product id — ignore rather than trust it
        $parts[ $part ] = $opt;
        $extra         += $opt['price'];
    }

    // Nothing chosen → plain "standard" cart line, merges with other standard lines.
    if ( empty( $parts ) ) return $data;

    $data['shopys_config'] = array(
        'parts' => $parts,
        'extra' => $extra,
        'base'  => (float) $product->get_price(),
    );
    return $data;
}
add_filter( 'woocommerce_add_cart_item_data', 'shopys_lc_add_cart_item_data', 10, 3 );

/**
 * Price a configured line as laptop price + upgrades.
 * The base is the price captured at add time, so repeated recalculation
 * can never compound the upgrade cost.
 */
function shopys_lc_apply_prices( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) return;
    foreach ( $cart->get_cart() as $item ) {
        if ( empty( $item['shopys_config'] ) || ! isset( $item['data'] ) ) continue;
        $cfg = $item['shopys_config'];
        $item['data']->set_price( (float) $cfg['base'] + (float) $cfg['extra'] );
    }
}
add_action( 'woocommerce_before_calculate_totals', 'shopys_lc_apply_prices', 20 );

/**
 * Build the display lines for one cart item: Configuration + each chosen part.
 * Laptops with no selection report as "Standard".
 *
 * @return array[] list of array( key, value )
 */
function shopys_lc_item_lines( $cart_item ) {
    $lines = array();
    $cfg   = $cart_item['shopys_config'] ?? null;

    if ( empty( $cfg['parts'] ) ) {
        $product = $cart_item['data'] ?? null;
        if ( $product && shopys_lc_is_configurable( $product ) ) {
            $lines[] = array( 'key' => __( 'Configuration', 'shopys' ), 'value' => __( 'Standard', 'shopys' ) );
        }
        return $lines;
    }

    $lines[] = array( 'key' => __( 'Configuration', 'shopys' ), 'value' => __( 'Custom', 'shopys' ) );
    foreach ( $cfg['parts'] as $part => $opt ) {
        $lines[] = array(
            'key'   => shopys_lc_part_label( $part ),
            'value' => $opt['name'] . ' (+' . shopys_lc_price_text( $opt['price'] ) . ')',
        );
    }
    return $lines;
}

/**
 * Show the configuration under the item name on the WooCommerce cart/checkout.
 */
function shopys_lc_get_item_data( $item_data, $cart_item ) {
    foreach ( shopys_lc_item_lines( $cart_item ) as $line ) {
        $item_data[] = array(
            'key'     => $line['key'],
            'value'   => $line['value'],
            'display' => '',
        );
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'shopys_lc_get_item_data', 10, 2 );

/**
 * Visible line-item meta for an *order* item, as plain "Key: Value" strings.
 * This is the same meta WooCommerce shows on the order screen and in emails —
 * for a configured laptop it is Configuration / RAM / Storage.
 *
 * Callers are responsible for escaping (the Telegram builders send HTML).
 *
 * @return string[]
 */
function shopys_lc_order_item_lines( $item ) {
    $out = array();
    if ( ! $item instanceof WC_Order_Item ) return $out;

    foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
        $key = trim( wp_strip_all_tags( $meta->display_key ) );
        $val = trim( wp_strip_all_tags( $meta->display_value ) );
        if ( $key === '' || $val === '' ) continue;
        $out[] = $key . ': ' . $val;
    }
    return $out;
}

/**
 * Persist the configuration onto the order line item so it shows in the admin,
 * order emails, invoices and the Telegram order message.
 */
function shopys_lc_order_line_item( $item, $cart_item_key, $values, $order ) {
    foreach ( shopys_lc_item_lines( $values ) as $line ) {
        $item->add_meta_data( $line['key'], $line['value'], true );
    }
    if ( ! empty( $values['shopys_config'] ) ) {
        $item->add_meta_data( '_shopys_config', $values['shopys_config'], true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'shopys_lc_order_line_item', 10, 4 );
