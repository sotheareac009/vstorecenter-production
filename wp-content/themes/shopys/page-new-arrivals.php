<?php
/**
 * Template for New Arrivals page — 50 most recently added products.
 *
 * @package Shopys
 */

get_header();

$query = new WP_Query( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>

<style>
.na-wrap {
    background: #f8f9fa;
    min-height: 60vh;
    padding: 0 0 60px;
}

/* Hero Banner */
.na-hero {
    background: linear-gradient(135deg, #0d1117 0%, #0a1a0a 50%, #0d2010 100%);
    padding: 48px 5% 44px;
    position: relative;
    overflow: hidden;
}
.na-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 320px; height: 320px;
    border-radius: 50%;
    background: rgba(19,232,0,.1);
    pointer-events: none;
}
.na-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: 10%;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(19,232,0,.06);
    pointer-events: none;
}
.na-hero__inner {
    max-width: 1380px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
.na-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(19,232,0,.15);
    border: 1px solid rgba(19,232,0,.35);
    color: #13e800;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 50px;
    margin-bottom: 16px;
}
.na-hero__badge::before {
    content: '';
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #13e800;
    animation: na-pulse 1.6s ease-in-out infinite;
}
@keyframes na-pulse {
    0%,100%{ opacity:1; transform:scale(1); }
    50%{ opacity:.4; transform:scale(1.5); }
}
.na-hero__title {
    font-size: clamp(26px, 3.5vw, 42px);
    font-weight: 800;
    color: #fff;
    margin: 0 0 10px;
    letter-spacing: -.5px;
}
.na-hero__title span { color: #13e800; }
.na-hero__sub {
    font-size: 14px;
    color: rgba(255,255,255,.65);
    margin: 0;
}
.na-hero__count {
    display: inline-block;
    margin-top: 14px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.8);
    font-size: 13px;
    font-weight: 600;
    padding: 5px 16px;
    border-radius: 20px;
}

/* Grid */
.na-content {
    max-width: 1380px;
    margin: 32px auto 0;
    padding: 0 5%;
}

/* Card overrides for white bg */
.na-content .ppg-grid { margin-top: 0; }
.na-content .ppg-card {
    border-radius: 12px;
    background: #fff;
    border: 1px solid #eaedf0;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    transition: box-shadow .25s, transform .25s, border-color .25s;
}
.na-content .ppg-card:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    border-color: #b6f5b0;
    transform: translateY(-4px);
}
.na-content .ppg-card .button,
.na-content .ppg-card .add_to_cart_button,
.na-content .ppg-card .ppg-view-btn {
    background: #13e800 !important;
    color: #000 !important;
    border: none !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    transition: background .2s, transform .2s !important;
}
.na-content .ppg-card .button:hover,
.na-content .ppg-card .add_to_cart_button:hover,
.na-content .ppg-card .ppg-view-btn:hover {
    background: #0fb500 !important;
    color: #000 !important;
    transform: translateY(-1px) !important;
}

/* NEW badge on each card */
.na-new-badge {
    position: absolute;
    top: 10px; left: 10px;
    background: #13e800;
    color: #000;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 20px;
    z-index: 5;
}

.na-empty {
    text-align: center;
    padding: 80px 20px;
    color: #9ca3af;
}
.na-empty svg { margin-bottom: 16px; }
.na-empty p { font-size: 16px; }
</style>

<div class="na-wrap">

    <!-- Hero -->
    <div class="na-hero">
        <div class="na-hero__inner">
            <div class="na-hero__badge">New Arrivals</div>
            <h1 class="na-hero__title">Latest <span>Products</span></h1>
            <p class="na-hero__sub">The 50 most recently added items — fresh stock, updated daily.</p>
            <?php if ( $query->found_posts ) : ?>
            <span class="na-hero__count"><?php echo esc_html( min( $query->found_posts, 50 ) ); ?> products</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid -->
    <div class="na-content">
        <?php if ( $query->have_posts() ) : ?>
        <div class="ppg-grid ppg-cols-4">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $product    = wc_get_product( get_the_ID() );
                if ( ! $product ) continue;

                $thumb_id   = $product->get_image_id();
                $thumb_url  = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
                $regular    = (float) $product->get_regular_price();
                $sale       = (float) $product->get_sale_price();
                $pct        = ( $product->is_on_sale() && $regular > 0 ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
                $sku        = $product->get_sku();
                $short_desc = $product->get_short_description();
                if ( empty( $short_desc ) ) {
                    $short_desc = $product->get_description();
                }
            ?>
            <div class="ppg-card">

                <!-- NEW badge -->
                <span class="na-new-badge">NEW</span>

                <!-- Sale / OOS badges -->
                <?php if ( $product->is_on_sale() || ! $product->is_in_stock() ) : ?>
                <div class="ppg-badges" style="left:auto;right:10px;">
                    <?php if ( $product->is_on_sale() ) : ?>
                        <span class="ppg-badge ppg-badge-sale">-<?php echo esc_html( $pct ); ?>%</span>
                    <?php endif; ?>
                    <?php if ( ! $product->is_in_stock() ) : ?>
                        <span class="ppg-badge ppg-badge-oos"><?php esc_html_e( 'Sold Out', 'shopys' ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Image -->
                <a href="<?php echo esc_url( get_permalink() ); ?>" class="ppg-image-link">
                    <div class="ppg-image-wrapper">
                        <?php if ( $thumb_url ) : ?>
                            <img class="ppg-image" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy" />
                        <?php else : ?>
                            <div class="ppg-no-image"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>
                        <?php endif; ?>
                    </div>
                </a>

                <!-- Info -->
                <div class="ppg-info">
                    <a href="<?php echo esc_url( get_permalink() ); ?>" class="ppg-title-link">
                        <h3 class="ppg-title"><?php echo esc_html( $product->get_name() ); ?></h3>
                    </a>

                    <div class="ppg-price-row">
                        <?php if ( $product->is_on_sale() ) : ?>
                            <span class="ppg-price-regular"><?php echo wc_price( $product->get_regular_price() ); ?></span>
                            <span class="ppg-price-sale"><?php echo wc_price( $product->get_sale_price() ); ?></span>
                        <?php else : ?>
                            <span class="ppg-price-current"><?php echo $product->get_price_html(); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $short_desc ) :
                        $desc_clean = wp_strip_all_tags( $short_desc );
                        $specs = preg_split( '/[.\n]+/', $desc_clean, -1, PREG_SPLIT_NO_EMPTY );
                    ?>
                    <div class="ppg-specs">
                        <?php if ( ! empty( $specs ) ) : ?>
                        <ul class="ppg-specs-list">
                            <?php foreach ( $specs as $spec ) :
                                $spec = trim( $spec );
                                if ( ! empty( $spec ) && strlen( $spec ) > 2 ) : ?>
                                <li><?php echo esc_html( $spec ); ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( $sku ) : ?>
                    <div class="ppg-sku">
                        <span class="ppg-sku-separator"></span>
                        <?php esc_html_e( 'Code', 'shopys' ); ?> : <?php echo esc_html( $sku ); ?>
                    </div>
                    <?php endif; ?>

                    <div class="ppg-actions">
                        <?php woocommerce_template_loop_add_to_cart(); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php else : ?>
        <div class="na-empty">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <p>No products found.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php get_footer(); ?>
