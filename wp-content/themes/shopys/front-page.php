<?php
/**
 * Front Page Template — Premium Home
 * Hero slider (5 images via Customizer) + stacked product sections.
 *
 * @package Shopys
 */

get_header();

// ── Pull slider images from Customizer (shared defaults from hero-slider-settings.php) ──
$_slider_defaults = shopys_hero_slider_defaults();
$slider_images    = array();
for ( $i = 1; $i <= 5; $i++ ) {
    $url = get_theme_mod( 'shopys_hero_slide_' . $i, $_slider_defaults[ $i ] );
    if ( $url ) {
        $slider_images[] = esc_url( $url );
    }
}
$slide_count = count( $slider_images );
?>

<style>
:root {
    --accent:       #13e800;
    --accent-dim:   #0fb500;
    --dark:         #0d1117;
    --dark-2:       #111820;
    --dark-3:       #1a2233;
    --card-border:  #e8edf3;
    --text-primary: #111827;
    --text-muted:   #6b7280;
    --radius:       12px;
    --shadow:       0 4px 24px rgba(0,0,0,.08);
    --shadow-hover: 0 12px 40px rgba(0,0,0,.16);
    --transition:   .25s ease;
}

/* ── FRONT-PAGE SEARCH BAR OVERRIDE ─────────────────────────── */
.home #aps-wrapper,
.home .aps-wrapper {
    background: #ffffff;
    max-width: 100%;
    margin: 0;
    padding: 16px 6% 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    border-bottom: 1px solid #eaeaea;
}
.home .aps-wrapper .aps-form {
    max-width: 860px;
    margin: 0 auto;
}
.home .aps-wrapper .aps-input-group {
    background: #f8f9fa !important;
    border: 1.5px solid #e2e6ea !important;
    box-shadow: none !important;
    border-radius: 10px;
}
.home .aps-wrapper .aps-input-group:focus-within {
    background: #fff !important;
    border-color: #13e800 !important;
    box-shadow: 0 0 0 3px rgba(19,232,0,.15) !important;
}
.home .aps-wrapper .aps-submit-btn {
    border-radius: 0 9px 9px 0 !important;
}
.home .fp-hero { margin-top: 0; }

/* ── HERO SLIDER ─────────────────────────────────────────────── */
.fp-hero {
    position: relative;
    width: 100%;
    height: 480px;
    overflow: hidden;
    background: var(--dark);
    user-select: none;
}
.fp-slides {
    display: flex;
    height: 100%;
    transition: transform .7s cubic-bezier(.77,0,.18,1);
    will-change: transform;
}
.fp-slide {
    flex: 0 0 100%;
    height: 100%;
    position: relative;
}
.fp-slide__img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    pointer-events: none;
}
.fp-slide__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(13,17,23,.82) 0%, rgba(13,17,23,.28) 55%, transparent 100%);
    display: flex;
    align-items: center;
    padding: 0 7%;
}
.fp-slide__content { max-width: 520px; color: #fff; }

.fp-hero__tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(19,232,0,.15);
    border: 1px solid rgba(19,232,0,.35);
    color: var(--accent);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 50px;
    margin-bottom: 18px;
}
.fp-hero__tag::before {
    content: '';
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--accent);
    animation: fp-pulse 1.6s ease-in-out infinite;
}
@keyframes fp-pulse {
    0%,100%{ opacity:1; transform:scale(1); }
    50%{ opacity:.45; transform:scale(1.5); }
}
.fp-hero__title {
    font-size: clamp(26px, 3.8vw, 50px);
    font-weight: 800;
    line-height: 1.1;
    margin: 0 0 14px;
    letter-spacing: -.5px;
}
.fp-hero__title span { color: var(--accent); }
.fp-hero__sub {
    font-size: 15px;
    opacity: .72;
    margin-bottom: 26px;
    line-height: 1.65;
}
.fp-hero__cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: var(--accent);
    color: #000;
    font-weight: 700;
    font-size: 14px;
    padding: 12px 26px;
    border-radius: 8px;
    text-decoration: none;
    transition: var(--transition);
}
.fp-hero__cta:hover {
    background: #fff; color: #000;
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(19,232,0,.35);
    text-decoration: none;
}
.fp-hero__cta svg { transition: transform .2s; }
.fp-hero__cta:hover svg { transform: translateX(4px); }

/* Arrows */
.fp-arrow {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    z-index: 20;
    background: rgba(0,0,0,.45);
    border: 1px solid rgba(255,255,255,.2);
    color: #fff;
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s, color .2s, border-color .2s;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    padding: 0;
}
.fp-arrow:hover { background: var(--accent); color: #000; border-color: var(--accent); }
.fp-arrow--prev { left: 18px; }
.fp-arrow--next { right: 18px; }

/* Dots */
.fp-dots {
    position: absolute;
    bottom: 16px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 8px; z-index: 20;
    padding: 0; margin: 0; list-style: none;
}
.fp-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,.4);
    cursor: pointer;
    transition: background .25s, transform .25s;
    border: none; padding: 0;
}
.fp-dot.active { background: var(--accent); transform: scale(1.4); }

/* ── CATEGORY QUICK-NAV ──────────────────────────────────────── */
.fp-cats {
    background: #ffffff;
    padding: 32px 5%;
    border-top: 1px solid #eaeaea;
    border-bottom: 1px solid #eaeaea;
}
.fp-cats__inner {
    max-width: 1280px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}
.fp-cats__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-decoration: none;
    padding: 22px 16px 18px;
    border-radius: 12px;
    border: 1.5px solid #e2e6ea;
    background: #ffffff;
    color: #2d3748;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .8px;
    text-transform: uppercase;
    transition: var(--transition);
    text-align: center;
    position: relative;
}
.fp-cats__item:hover {
    background: #f7fff6;
    border-color: var(--accent);
    color: var(--accent-dim);
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(19,232,0,.15);
    text-decoration: none;
}
.fp-cats__icon {
    width: 32px;
    height: 32px;
    stroke: currentColor;
    fill: none;
    transition: color var(--transition);
}
.fp-cats__item:hover .fp-cats__icon {
    color: var(--accent);
}

/* ── TRUST BAR ───────────────────────────────────────────────── */
.fp-trust {
    background: #f8f9fa;
    padding: 14px 5%;
    border-bottom: 1px solid #eaeaea;
}
.fp-trust__inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 10px;
}
.fp-trust__item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #555;
    font-size: 13px;
}
.fp-trust__item svg { width: 22px; height: 22px; color: var(--accent-dim); flex-shrink: 0; }
.fp-trust__item strong { display: block; color: #222; font-size: 13px; }

/* ── PRODUCTS AREA ───────────────────────────────────────────── */
.fp-products {
    background: #ffffff;
    padding: 24px 5% 68px;
}
.fp-products__inner { max-width: 1380px; margin: 0 auto; }

.fp-cat-section { margin-bottom: 60px; }
.fp-cat-section:last-child { margin-bottom: 0; }

.fp-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 12px;
}
.fp-section-head__left { display: flex; align-items: center; gap: 14px; }
.fp-section-head__bar {
    width: 4px; height: 34px;
    background: var(--accent);
    border-radius: 4px;
    flex-shrink: 0;
}
.fp-section-head__title {
    font-size: 21px;
    font-weight: 800;
    color: var(--dark);
    margin: 0;
    letter-spacing: -.3px;
}
.fp-section-head__count { font-size: 13px; color: var(--text-muted); }
.fp-section-head__link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: var(--accent-dim);
    text-decoration: none;
    padding: 8px 18px;
    border: 1.5px solid var(--accent);
    border-radius: 6px;
    transition: var(--transition);
}
.fp-section-head__link:hover {
    background: var(--accent); color: #000;
    text-decoration: none; transform: translateY(-1px);
}

.fp-cat-section .ppg-grid { margin-top: 0; }
.fp-cat-section .ppg-card {
    border-radius: var(--radius);
    background: #fff;
    border: 1px solid #eaedf0;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}
.fp-cat-section .ppg-card:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    border-color: #d0d5da;
    transform: translateY(-4px);
}

/* Add to Cart / View button — white background friendly */
.fp-cat-section .ppg-card .button,
.fp-cat-section .ppg-card .add_to_cart_button,
.fp-cat-section .ppg-card .ppg-view-btn {
    background: #13e800 !important;
    color: #000 !important;
    border: none !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    transition: background .2s, transform .2s !important;
}
.fp-cat-section .ppg-card .button:hover,
.fp-cat-section .ppg-card .add_to_cart_button:hover,
.fp-cat-section .ppg-card .ppg-view-btn:hover {
    background: #0fb500 !important;
    color: #000 !important;
    transform: translateY(-1px) !important;
}

.fp-cat-section .ppg-pagination { display: none; }

/* ── RESPONSIVE ──────────────────────────────────────────────── */
@media (max-width: 768px) {
    /* ── Hero banner ── */
    .fp-hero { height: 220px; }
    .fp-slide__overlay {
        padding: 0 5%;
        background: linear-gradient(90deg, rgba(13,17,23,.78) 0%, rgba(13,17,23,.3) 70%, transparent 100%);
        align-items: flex-end;
        padding-bottom: 28px;
    }
    .fp-slide__content { max-width: 100%; }
    .fp-hero__tag { font-size: 9px; letter-spacing: 1.2px; padding: 4px 10px; margin-bottom: 8px; }
    .fp-hero__title { font-size: 22px; margin-bottom: 8px; letter-spacing: -.3px; }
    .fp-hero__sub { display: none; } /* hide long subtitle — too crowded */
    .fp-hero__cta {
        font-size: 12px;
        padding: 9px 18px;
        gap: 7px;
    }
    .fp-arrow { display: none; } /* touch devices swipe; arrows waste space */
    .fp-dots { bottom: 10px; gap: 6px; }
    .fp-dot { width: 6px; height: 6px; }

    /* ── Trust bar (Fastest Delivery, etc.) ── */
    .fp-trust { padding: 12px 4%; }
    .fp-trust__inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 8px;
        justify-items: start;
    }
    .fp-trust__item {
        gap: 8px;
        font-size: 11.5px;
        background: #fff;
        border: 1px solid #eaeaea;
        border-radius: 8px;
        padding: 10px 12px;
        width: 100%;
        align-items: flex-start;
    }
    .fp-trust__item svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; }
    .fp-trust__item strong { font-size: 12px; margin-bottom: 1px; }

    /* ── Other sections ── */
    .fp-products { padding: 24px 4% 40px; }
    .fp-section-head__title { font-size: 17px; }
    .fp-cats { padding: 18px 4%; }
    .fp-cats__inner { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .fp-cats__item { padding: 14px 8px 12px; gap: 7px; font-size: 10px; }
    .fp-cats__icon { width: 24px; height: 24px; }
}

@media (max-width: 400px) {
    .fp-hero { height: 190px; }
    .fp-hero__title { font-size: 19px; }
    .fp-trust__inner { grid-template-columns: 1fr; }
    .fp-cats__inner { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php
// Trust Bar - check if enabled
$trust_enabled = get_option( 'shopys_trust_enabled', '1' );
if ( $trust_enabled ) {
    $trust_icons = array(
        1 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 4v4h-7V8zM1 16h20M5.5 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM18.5 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/></svg>',
        2 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        3 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        4 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
    );
    
    $trust_defaults = array(
        1 => array( 'title' => 'Fastest Delivery', 'desc' => 'Phnom Penh & nationwide' ),
        2 => array( 'title' => 'Official Warranty', 'desc' => 'All products guaranteed' ),
        3 => array( 'title' => 'After-Sales Support', 'desc' => '7 days a week' ),
        4 => array( 'title' => 'Secure Payment', 'desc' => 'ABA · ACLEDA · Cash' ),
    );
    
    $trust_count = (int) get_option( 'shopys_trust_count', 4 );
    $trust_items = array();
    
    // Collect all trust items from database
    for ( $i = 1; $i <= 50; $i++ ) {
        $title = get_option( "shopys_trust_title_$i", '' );
        if ( ! empty( $title ) ) {
            $desc = get_option( "shopys_trust_desc_$i", '' );
            $trust_items[ $i ] = array( 'title' => $title, 'desc' => $desc );
        }
    }
    
    // If no items found, use defaults
    if ( empty( $trust_items ) ) {
        for ( $i = 1; $i <= 4; $i++ ) {
            $trust_items[ $i ] = $trust_defaults[ $i ];
        }
    }
    ?>
<!-- ═══════════════════════════ TRUST BAR ════════════════════════════ -->
<div class="fp-trust">
    <div class="fp-trust__inner">
    <?php foreach ( $trust_items as $i => $item ) {
        $icon = isset( $trust_icons[ $i ] ) ? $trust_icons[ $i ] : $trust_icons[1];
    ?>
        <div class="fp-trust__item">
            <?php echo $icon; ?>
            <div><strong><?php echo esc_html( $item['title'] ); ?></strong><?php echo esc_html( $item['desc'] ); ?></div>
        </div>
    <?php } ?>
    </div>
</div>
    <?php
}
?>

<!-- ═══════════════════════════ HERO SLIDER ══════════════════════════ -->
<section class="fp-hero" id="fp-hero">

    <div class="fp-slides" id="fp-slides">
        <?php foreach ( $slider_images as $img_url ) : ?>
        <div class="fp-slide">
            <img class="fp-slide__img" src="<?php echo esc_url( $img_url ); ?>" alt="" loading="lazy" />
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Static overlay — stays fixed while images slide -->
    <div class="fp-slide__overlay">
        <div class="fp-slide__content">
            <div class="fp-hero__tag"><?php echo esc_html( get_option( 'shopys_hero_tag', 'New Arrivals 2025' ) ); ?></div>
            <h1 class="fp-hero__title"><?php echo esc_html( get_option( 'shopys_hero_title', 'Your Ultimate' ) ); ?><br><span><?php echo esc_html( get_option( 'shopys_hero_title_highlight', 'Tech Store' ) ); ?></span></h1>
            <p class="fp-hero__sub"><?php echo esc_html( get_option( 'shopys_hero_subtitle', 'Laptops · Gaming Gear · PC Hardware · Accessories — all under one roof at the best prices.' ) ); ?></p>
            <a href="<?php echo esc_url( home_url( get_option( 'shopys_hero_cta_url', '/laptop-v2/' ) ) ); ?>" class="fp-hero__cta">
                <?php echo esc_html( get_option( 'shopys_hero_cta_text', 'Shop Now' ) ); ?>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <!-- Prev arrow -->
    <button class="fp-arrow fp-arrow--prev" id="fp-prev" aria-label="Previous slide">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    </button>

    <!-- Next arrow -->
    <button class="fp-arrow fp-arrow--next" id="fp-next" aria-label="Next slide">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
    </button>

    <!-- Dot indicators -->
    <div class="fp-dots" id="fp-dots">
        <?php for ( $d = 0; $d < $slide_count; $d++ ) : ?>
        <button class="fp-dot <?php echo $d === 0 ? 'active' : ''; ?>" data-index="<?php echo $d; ?>" aria-label="Slide <?php echo $d + 1; ?>"></button>
        <?php endfor; ?>
    </div>

</section>

<!-- ═══════════════════════════ CATEGORY NAV ═════════════════════════ -->
<nav class="fp-cats" aria-label="Product Categories">
    <div class="fp-cats__inner">

        <a href="<?php echo esc_url( get_term_link('laptop','product_cat') ); ?>" class="fp-cats__item">
            <svg class="fp-cats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="13" rx="2"/>
                <path d="M2 19h20"/>
                <path d="M10 19l.5-2h3l.5 2"/>
                <path d="M10 11h4M10 8h2"/>
            </svg>
            Laptops
        </a>
        <a href="<?php echo esc_url( get_term_link('component','product_cat') ); ?>" class="fp-cats__item">
            <svg class="fp-cats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="7" y="7" width="10" height="10" rx="1.5"/>
                <rect x="9.5" y="9.5" width="5" height="5" rx=".5"/>
                <path d="M9 4v3M12 4v3M15 4v3M9 17v3M12 17v3M15 17v3M4 9h3M4 12h3M4 15h3M17 9h3M17 12h3M17 15h3"/>
            </svg>
            PC Hardware
        </a>
        <a href="<?php echo esc_url( get_term_link('accessories','product_cat') ); ?>" class="fp-cats__item">
            <svg class="fp-cats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 11a9 9 0 0 1 18 0"/>
                <path d="M3 11v2a2 2 0 0 0 2 2h1V10H5a2 2 0 0 0-2 1z"/>
                <path d="M21 11v2a2 2 0 0 1-2 2h-1V10h1a2 2 0 0 1 2 1z"/>
                <path d="M19 15v1a3 3 0 0 1-3 3h-2"/>
                <rect x="13" y="19" width="2" height="1" rx=".5"/>
            </svg>
            Accessories
        </a>
        <a href="<?php echo esc_url( get_term_link('gaming-gear','product_cat') ); ?>" class="fp-cats__item">
            <svg class="fp-cats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9H4a2 2 0 0 0-2 2v3a4 4 0 0 0 4 4h2l2 2h4l2-2h2a4 4 0 0 0 4-4v-3a2 2 0 0 0-2-2h-2a8 8 0 0 0-12 0z"/>
                <line x1="9" y1="11" x2="9" y2="15"/>
                <line x1="7" y1="13" x2="11" y2="13"/>
                <circle cx="15.5" cy="11.5" r=".75" fill="currentColor" stroke="none"/>
                <circle cx="17.5" cy="13.5" r=".75" fill="currentColor" stroke="none"/>
                <circle cx="15.5" cy="13.5" r=".75" fill="currentColor" stroke="none"/>
                <circle cx="17.5" cy="11.5" r=".75" fill="currentColor" stroke="none"/>
            </svg>
            Gaming Gear
        </a>
        <a href="<?php echo esc_url( get_term_link('monitor','product_cat') ); ?>" class="fp-cats__item">
            <svg class="fp-cats__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="13" rx="2"/>
                <path d="M12 16v4"/>
                <path d="M8 20h8"/>
                <path d="M7 8h3M7 11h2"/>
                <rect x="13" y="7" width="5" height="4" rx="1"/>
            </svg>
            Monitors
        </a>

    </div>
</nav>

<!-- ═══════════════════════════ MARVO SECTION ════════════════════════ -->
<style>
.fp-marvo {
    background: #fff;
    padding: 24px 5% 48px;
}
.fp-marvo__banner {
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 28px;
    max-height: 220px;
}
.fp-marvo__banner img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    object-position: center;
    display: block;
}
.fp-marvo__banner-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(0,0,0,.65) 0%, rgba(0,0,0,.2) 60%, transparent 100%);
    display: flex;
    align-items: center;
    padding: 0 40px;
}
.fp-marvo__banner-text { color: #fff; }
.fp-marvo__banner-text h2 {
    font-size: clamp(22px, 3vw, 38px);
    font-weight: 800;
    margin: 0 0 6px;
    letter-spacing: -.5px;
}
.fp-marvo__banner-text p {
    font-size: 14px;
    opacity: .8;
    margin: 0 0 16px;
}
.fp-marvo__banner-text a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #13e800;
    color: #000;
    font-weight: 700;
    font-size: 13px;
    padding: 9px 22px;
    border-radius: 7px;
    text-decoration: none;
    transition: background .2s;
}
.fp-marvo__banner-text a:hover { background: #fff; text-decoration: none; }

/* ── WOOCOMMERCE CATEGORIES GRID ─────────────────────────── */
.fp-categories {
    background: #ffffff;
    padding: 48px 5% 60px;
}
.fp-categories__inner { max-width: 1380px; margin: 0 auto; }

.fp-categories__header {
    margin-bottom: 36px;
    text-align: center;
}
.fp-categories__title {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark);
    margin: 0 0 8px;
    letter-spacing: -.4px;
}
.fp-categories__subtitle {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
}

.fp-categories__grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 40px;
}

.fp-cat-card {
    background: #fff;
    border: 1.5px solid #e2e6ea;
    border-radius: 12px;
    padding: 22px 16px 18px;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    text-align: center;
}
.fp-cat-card:hover {
    border-color: var(--accent);
    background: #f7fff6;
    box-shadow: 0 6px 18px rgba(19,232,0,.15);
    transform: translateY(-3px);
}

.fp-cat-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    color: #2d3748;
    transition: color var(--transition);
}
.fp-cat-card:hover .fp-cat-card__icon {
    color: var(--accent);
}
.fp-cat-card__icon svg {
    width: 32px;
    height: 32px;
}

.fp-cat-card__name {
    font-size: 12px;
    font-weight: 800;
    color: #2d3748;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    letter-spacing: .8px;
    text-transform: uppercase;
    transition: color var(--transition);
}
.fp-cat-card:hover .fp-cat-card__name {
    color: var(--accent-dim);
}
.fp-cat-card__count {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    margin: 0;
    letter-spacing: .3px;
}

.fp-cat-card__toggle {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 22px;
    height: 22px;
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition);
    border-radius: 4px;
}
.fp-cat-card__toggle:hover {
    background: rgba(19,232,0,.1);
    color: var(--accent);
}
.fp-cat-card:hover .fp-cat-card__toggle {
    color: var(--accent);
}
.fp-cat-card__toggle.active {
    transform: rotate(180deg);
    color: var(--accent);
}

.fp-cat-card__children {
    display: none;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #eaedf3;
    text-align: left;
    animation: slideDown .25s ease;
}
.fp-cat-card__children.active {
    display: block;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Wrappers to force vertical stacking (header + nested children below) */
.fp-cat-child-wrap,
.fp-cat-grandchild-wrap {
    display: block;
    width: 100%;
}

.fp-cat-child {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 11px 12px;
    font-size: 14px;
    font-weight: 600;
    color: #1a202c;
    text-decoration: none;
    transition: all var(--transition);
    border-radius: 8px;
    margin-bottom: 4px;
}
.fp-cat-child:hover {
    color: var(--accent);
    background: rgba(19,232,0,.08);
}

.fp-cat-child a {
    color: inherit;
    text-decoration: none;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color var(--transition);
    font-weight: 600;
}
.fp-cat-child a:hover {
    color: var(--accent);
}

.fp-cat-child.has-grandchild {
    cursor: pointer;
}

.fp-cat-child__toggle {
    width: 20px;
    height: 20px;
    background: rgba(19,232,0,.1);
    border: none;
    cursor: pointer;
    color: var(--accent);
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition);
    flex-shrink: 0;
    border-radius: 4px;
}
.fp-cat-child__toggle:hover {
    background: rgba(19,232,0,.2);
}
.fp-cat-child__toggle.active {
    transform: rotate(90deg);
    background: rgba(19,232,0,.15);
}

.fp-cat-grandchildren {
    display: none;
    margin: 4px 0 10px 12px;
    padding: 4px 0 4px 14px;
    border-left: 2px solid rgba(19,232,0,.25);
    animation: slideDown .25s ease;
}
.fp-cat-grandchildren.active {
    display: block;
}

.fp-cat-grandchild {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 10px;
    font-size: 13px;
    font-weight: 600;
    color: #2d3748;
    text-decoration: none;
    transition: all var(--transition);
    border-radius: 6px;
    margin-bottom: 3px;
}
.fp-cat-grandchild:hover {
    color: var(--accent);
    background: rgba(19,232,0,.06);
}

.fp-cat-grandchild a {
    color: inherit;
    text-decoration: none;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: color var(--transition);
}
.fp-cat-grandchild a::before {
    content: '•';
    font-size: 14px;
    color: var(--accent);
    opacity: 0.8;
    line-height: 1;
}
.fp-cat-grandchild a:hover {
    color: var(--accent);
}

.fp-cat-grandchild__toggle {
    width: 18px;
    height: 18px;
    background: rgba(19,232,0,.08);
    border: none;
    cursor: pointer;
    color: var(--accent);
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition);
    flex-shrink: 0;
    border-radius: 3px;
}
.fp-cat-grandchild__toggle:hover {
    background: rgba(19,232,0,.18);
}
.fp-cat-grandchild__toggle.active {
    transform: rotate(90deg);
    background: rgba(19,232,0,.13);
}

.fp-cat-grandchild.has-grandchild {
    cursor: pointer;
}

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 1200px) {
    .fp-categories__grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 992px) {
    .fp-categories__grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .fp-categories { padding: 36px 4% 48px; }
    .fp-categories__title { font-size: 22px; }
    .fp-categories__grid { grid-template-columns: repeat(2, 1fr); }
    .fp-cat-card { padding: 18px 14px; }
    .fp-cat-child { padding: 10px 8px; font-size: 12px; }
    .fp-cat-grandchild { padding: 9px 6px; font-size: 12px; }
}
@media (max-width: 480px) {
    .fp-categories__grid { grid-template-columns: 1fr; }
    .fp-cat-card { padding: 16px 12px; }
}
</style>

<!-- ═══════════════════════════ CATEGORIES SECTION ═════════════════════ -->
<section class="fp-categories">
    <div class="fp-categories__inner">
        <div class="fp-categories__header">
            <h2 class="fp-categories__title">Shop by Category</h2>
            <p class="fp-categories__subtitle">Browse our premium collection of products</p>
        </div>

        <div class="fp-categories__grid" id="fp-categories-grid">
            <?php
            if ( ! function_exists( 'shopys_get_category_icon' ) ) {
                function shopys_get_category_icon( $slug, $name = '' ) {
                    $slug = strtolower( $slug );
                    $name = strtolower( $name );
                    $combined = $slug . ' ' . $name;

                    $icons = array(
                        'laptop'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M1 20h22"/></svg>',
                        'desktop'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
                        'computer'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
                        'pc'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 7h6M9 11h6M9 15h2"/></svg>',
                        'component'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/></svg>',
                        'monitor'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
                        'accessor'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 7.28l-7.21 7.21a2 2 0 0 1-2.83 0L8 11.59V7.5l3.5-3.5h4l4 4v-.72z"/><circle cx="14" cy="8" r="1"/></svg>',
                        'gaming'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h4M8 10v4"/><circle cx="15" cy="13" r="1"/><circle cx="18" cy="11" r="1"/><rect x="2" y="6" width="20" height="12" rx="6"/></svg>',
                        'keyboard'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/></svg>',
                        'mouse'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="6"/><path d="M12 7v4"/></svg>',
                        'headset'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3v4zM3 19a2 2 0 0 0 2 2h1v-6H3v4z"/></svg>',
                        'headphone'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3v4zM3 19a2 2 0 0 0 2 2h1v-6H3v4z"/></svg>',
                        'speaker'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><circle cx="12" cy="6" r="1"/></svg>',
                        'audio'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3v4zM3 19a2 2 0 0 0 2 2h1v-6H3v4z"/></svg>',
                        'mousepad'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="1"/></svg>',
                        'phone'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>',
                        'mobile'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>',
                        'camera'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
                        'printer'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
                        'network'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/></svg>',
                        'router'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6.01 18H6M10.01 18H10M15 10V5M12.5 2.5 15 5l2.5-2.5M20 10V5M17.5 2.5 20 5l2.5-2.5"/></svg>',
                        'cable'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v6a6 6 0 0 0 12 0V2M6 22v-4M18 22v-4"/></svg>',
                        'storage'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="4" rx="1"/><rect x="2" y="11" width="20" height="4" rx="1"/><rect x="2" y="17" width="20" height="4" rx="1"/><path d="M6 7h.01M6 13h.01M6 19h.01"/></svg>',
                        'ssd'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="4" rx="1"/><rect x="2" y="11" width="20" height="4" rx="1"/><rect x="2" y="17" width="20" height="4" rx="1"/></svg>',
                        'ram'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 19V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14M6 19l-2 2M18 19l2 2M10 7v6M14 7v6"/></svg>',
                        'cpu'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/></svg>',
                        'hardware'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3"/></svg>',
                        'office'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
                        'software'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
                        'used'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><polyline points="21 3 21 8 16 8"/></svg>',
                        'tablet'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M10 18h4"/></svg>',
                        'watch'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><path d="M12 9v3l2 2M9 3h6l1 4M9 21h6l1-4"/></svg>',
                        'battery'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="6" width="18" height="12" rx="2"/><path d="M23 13v-2M6 10v4M10 10v4M14 10v4"/></svg>',
                        'charger'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
                        'marvo'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h4M8 10v4"/><circle cx="15" cy="13" r="1"/><circle cx="18" cy="11" r="1"/><rect x="2" y="6" width="20" height="12" rx="6"/></svg>',
                    );

                    foreach ( $icons as $keyword => $svg ) {
                        if ( strpos( $combined, $keyword ) !== false ) {
                            return $svg;
                        }
                    }

                    // Default icon (box/product)
                    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
                }
            }

            function shopys_render_category_tree( $parent_id = 0, $depth = 0 ) {
                $categories = get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'parent'     => $parent_id,
                    'hide_empty' => false,
                    'number'     => 100,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ) );

                if ( empty( $categories ) || is_wp_error( $categories ) ) {
                    return;
                }

                foreach ( $categories as $cat ) :
                    $cat_link = get_term_link( $cat, 'product_cat' );
                    $product_count = $cat->count;

                    $grandchildren = get_terms( array(
                        'taxonomy'   => 'product_cat',
                        'parent'     => $cat->term_id,
                        'hide_empty' => false,
                        'number'     => 100,
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                    ) );

                    $has_children = ! empty( $grandchildren ) && ! is_wp_error( $grandchildren );

                    if ( $depth === 0 ) :
            ?>
            <div class="fp-cat-card" onclick="window.location.href='<?php echo esc_js( esc_url( $cat_link ) ); ?>';">
                <div class="fp-cat-card__icon">
                    <?php echo shopys_get_category_icon( $cat->slug, $cat->name ); ?>
                </div>
                <h3 class="fp-cat-card__name">
                    <?php echo esc_html( $cat->name ); ?>
                </h3>
                <p class="fp-cat-card__count">
                    <?php echo esc_html( sprintf( _n( '%d product', '%d products', $product_count, 'woocommerce' ), $product_count ) ); ?>
                    <?php if ( $has_children ) : ?>
                        <span style="display:inline-block;margin-left:4px;font-size:10px;background:rgba(19,232,0,.12);color:var(--accent-dim);padding:1px 6px;border-radius:10px;font-weight:700;">
                            <?php echo count( $grandchildren ); ?> sub
                        </span>
                    <?php endif; ?>
                </p>

                <?php if ( $has_children ) : ?>
                    <button class="fp-cat-card__toggle" onclick="event.stopPropagation(); this.classList.toggle('active'); this.nextElementSibling.classList.toggle('active');">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div class="fp-cat-card__children" style="margin: 0; padding: 0; border: none;">
                        <?php shopys_render_category_tree( $cat->term_id, 1 ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
                    else :
                        $great_grandchildren = get_terms( array(
                            'taxonomy'   => 'product_cat',
                            'parent'     => $cat->term_id,
                            'hide_empty' => false,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ) );
                        $has_more_children = ! empty( $great_grandchildren ) && ! is_wp_error( $great_grandchildren );
                        $wrapper_class = $depth === 1 ? 'fp-cat-child-wrap' : 'fp-cat-grandchild-wrap';
                        $row_class = $depth === 1 ? 'fp-cat-child' : 'fp-cat-grandchild';
                        $toggle_class = $depth === 1 ? 'fp-cat-child__toggle' : 'fp-cat-grandchild__toggle';
            ?>
            <div class="<?php echo $wrapper_class; ?>">
                <div class="<?php echo $row_class; ?> <?php echo $has_more_children ? 'has-grandchild' : ''; ?>">
                    <a href="<?php echo esc_url( $cat_link ); ?>" onclick="event.stopPropagation();">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                    <?php if ( $has_more_children ) : ?>
                        <button class="<?php echo $toggle_class; ?>" onclick="event.stopPropagation(); this.classList.toggle('active'); this.closest('.<?php echo $wrapper_class; ?>').querySelector('.fp-cat-grandchildren').classList.toggle('active');">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="9 6 15 12 9 18"></polyline>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( $has_more_children ) : ?>
                    <div class="fp-cat-grandchildren">
                        <?php shopys_render_category_tree( $cat->term_id, $depth + 1 ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
                    endif;
                endforeach;
            }

            shopys_render_category_tree( 0, 0 );
            ?>
        </div>
    </div>
</section>

<section class="fp-marvo">
    <div class="fp-marvo__banner">
        <img src="<?php echo esc_url( get_theme_mod( 'shopys_marvo_banner', shopys_marvo_banner_default() ) ); ?>" alt="Marvo Gaming Gear" />
        <div class="fp-marvo__banner-overlay">
            <div class="fp-marvo__banner-text">
                <h2>MARVO Gaming Gear</h2>
                <p>Keyboards · Mice · Headsets · Mousepads · Speakers</p>
                <a href="<?php echo esc_url( get_term_link('marvo','product_cat') ); ?>">
                    Shop Marvo
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>
    <?php echo do_shortcode( '[premium_products category="marvo" limit="12" columns="6" filter="false" cart="true" show_description="false" pagination_type="normal"]' ); ?>
</section>

<!-- ═══════════════════════════ USED PRODUCTS ════════════════════════ -->
<section class="fp-products fp-used">
    <div class="fp-products__inner">
        <div class="fp-cat-section">
            <div class="fp-section-head">
                <div class="fp-section-head__left">
                    <div class="fp-section-head__bar" style="background:#f59e0b;"></div>
                    <div>
                        <h2 class="fp-section-head__title">Used Products <span style="background:#f59e0b;color:#000;font-size:12px;font-weight:700;padding:2px 8px;border-radius:20px;vertical-align:middle;margin-left:6px;">Second Hand</span></h2>
                        <span class="fp-section-head__count">Quality checked · Best price</span>
                    </div>
                </div>
                <?php
                $used_cat = get_term_by( 'slug', 'used-product', 'product_cat' );
                if ( ! $used_cat ) {
                    $result = wp_insert_term( 'Used Products', 'product_cat', array( 'slug' => 'used-product' ) );
                    $used_cat = is_wp_error( $result ) ? false : get_term( $result['term_id'], 'product_cat' );
                }
                $used_link = home_url( '/used-product/' );
                ?>
                <a href="<?php echo esc_url( $used_link ); ?>" class="fp-section-head__link">
                    View All Used Products
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <?php echo do_shortcode( '[premium_products category="used-product" limit="12" columns="6" filter="false" cart="true" show_description="false" pagination_type="normal"]' ); ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════ PRODUCT SECTIONS ══════════════════════ -->
<section class="fp-products">
    <div class="fp-products__inner">

        <!-- ── NEW RECENT ADDED ── -->
        <div class="fp-cat-section">
            <div class="fp-section-head">
                <div class="fp-section-head__left">
                    <div class="fp-section-head__bar"></div>
                    <div>
                        <h2 class="fp-section-head__title">New Recent Added <span style="background:#13e800;color:#000;font-size:12px;font-weight:700;padding:2px 10px;border-radius:20px;vertical-align:middle;margin-left:6px;">NEW</span></h2>
                        <span class="fp-section-head__count">Latest arrivals · Just in stock</span>
                    </div>
                </div>
                <a href="<?php echo esc_url( home_url( '/new-arrivals/' ) ); ?>" class="fp-section-head__link">
                    View All New Arrivals
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <?php echo do_shortcode( '[premium_products limit="12" columns="6" filter="false" cart="true" show_description="false" pagination_type="normal" orderby="date" order="DESC"]' ); ?>
        </div>

        <?php
        $fp_categories = array(
            array( 'slug' => 'laptop',           'label' => 'Laptops',      'count' => 93,  'custom_url' => 'laptop-v2' ),
            array( 'slug' => 'component,desktop', 'label' => 'PC Hardware',  'count' => 249, 'custom_url' => 'pc-harware-v2' ),
            array( 'slug' => 'accessories',       'label' => 'Accessories',  'count' => 69,  'custom_url' => 'accessories' ),
            array( 'slug' => 'gaming-gear',       'label' => 'Gaming Gear',  'count' => 80,  'custom_url' => 'gaming-gear' ),
            array( 'slug' => 'monitor',           'label' => 'Monitors',     'count' => 37,  'custom_url' => '' ),
        );

        foreach ( $fp_categories as $cat ) :
            $first_slug = trim( explode( ',', $cat['slug'] )[0] );
            $cat_obj    = get_term_by( 'slug', $first_slug, 'product_cat' );
            $cat_link   = ! empty( $cat['custom_url'] ) ? home_url( '/' . $cat['custom_url'] . '/' ) : ( $cat_obj ? get_term_link( $cat_obj ) : wc_get_page_permalink( 'shop' ) );
        ?>
        <div class="fp-cat-section">
            <div class="fp-section-head">
                <div class="fp-section-head__left">
                    <div class="fp-section-head__bar"></div>
                    <div>
                        <h2 class="fp-section-head__title"><?php echo esc_html( $cat['label'] ); ?></h2>
                        <span class="fp-section-head__count"><?php echo esc_html( $cat['count'] ); ?>+ products available</span>
                    </div>
                </div>
                <a href="<?php echo esc_url( $cat_link ); ?>" class="fp-section-head__link">
                    View All <?php echo esc_html( $cat['label'] ); ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            <?php echo do_shortcode( '[premium_products category="' . esc_attr( $cat['slug'] ) . '" limit="12" columns="6" filter="false" cart="true" show_description="false" pagination_type="normal"]' ); ?>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ═══════════════════════════ SLIDER SCRIPT ════════════════════════ -->
<script>
(function () {
    var track  = document.getElementById('fp-slides');
    var dots   = document.querySelectorAll('.fp-dot');
    var total  = <?php echo (int) $slide_count; ?>;
    var cur    = 0;
    var timer;

    function goTo(n) {
        cur = (n + total) % total;
        track.style.transform = 'translateX(-' + (cur * 100) + '%)';
        dots.forEach(function (d, i) { d.classList.toggle('active', i === cur); });
    }

    function next() { goTo(cur + 1); }
    function prev() { goTo(cur - 1); }

    function startAuto() { timer = setInterval(next, 5000); }
    function stopAuto()  { clearInterval(timer); }

    document.getElementById('fp-next').addEventListener('click', function () { stopAuto(); next(); startAuto(); });
    document.getElementById('fp-prev').addEventListener('click', function () { stopAuto(); prev(); startAuto(); });

    dots.forEach(function (d) {
        d.addEventListener('click', function () {
            stopAuto();
            goTo(parseInt(d.dataset.index, 10));
            startAuto();
        });
    });

    startAuto();
})();
</script>

<!-- ═══════════════════════════ BRAND SLIDER ═════════════════════════ -->
<style>
.fp-brands {
    background: #fff;
    padding: 40px 5% 44px;
    border-top: 1px solid #eaeaea;
}
.fp-brands__title {
    text-align: center;
    font-size: 20px;
    font-weight: 800;
    color: #111827;
    margin: 0 0 28px;
    letter-spacing: -.2px;
}
.fp-brands__slider {
    position: relative;
    overflow: hidden;
}
.fp-brands__track {
    display: flex;
    align-items: center;
    transition: transform .55s cubic-bezier(.77,0,.18,1);
    will-change: transform;
}
.fp-brands__item {
    flex: 0 0 calc(100% / 7);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
}
.fp-brands__item img {
    max-height: 52px;
    max-width: 120px;
    width: auto;
    object-fit: contain;
    transition: transform .25s;
}
.fp-brands__item img:hover {
    transform: scale(1.08);
}
.fp-brands__arrow {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    z-index: 5;
    background: #fff;
    border: 1.5px solid #e2e6ea;
    color: #444;
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all .2s;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.fp-brands__arrow:hover { background: #13e800; border-color: #13e800; color: #000; }
.fp-brands__arrow--prev { left: 0; }
.fp-brands__arrow--next { right: 0; }
.fp-brands__dots {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 20px;
}
.fp-brands__dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #d1d5db;
    border: none; cursor: pointer;
    transition: background .2s, transform .2s;
    padding: 0;
}
.fp-brands__dot.active { background: #13e800; transform: scale(1.35); }
@media (max-width: 768px) {
    .fp-brands__item { flex: 0 0 calc(100% / 3); }
}
</style>

<section class="fp-brands">
    <h2 class="fp-brands__title">Brand We Distribute</h2>
    <div class="fp-brands__slider" id="fp-brands-slider">
        <div class="fp-brands__track" id="fp-brands-track">
            <?php
            $_b = untrailingslashit( home_url() );
            $fp_brands = array(
                array( 'name' => 'Dell',      'url' => $_b . '/wp-content/uploads/2023/01/Dell_logo_2016.svg.png' ),
                array( 'name' => 'MSI',       'url' => $_b . '/wp-content/uploads/2023/01/MSI-Logo.png' ),
                array( 'name' => 'Predator',  'url' => $_b . '/wp-content/uploads/2023/01/Acer-Predator-logo.jpeg' ),
                array( 'name' => 'Acer',      'url' => $_b . '/wp-content/uploads/2023/01/acer-2011.svg' ),
                array( 'name' => 'Alienware', 'url' => $_b . '/wp-content/uploads/2023/01/Alienware-Logo.png' ),
                array( 'name' => 'Apple',     'url' => $_b . '/wp-content/uploads/2023/01/Apple-Logo.png' ),
                array( 'name' => 'Huawei',    'url' => $_b . '/wp-content/uploads/2023/01/Huawei-Logo.png' ),
                array( 'name' => 'HP',        'url' => $_b . '/wp-content/uploads/2023/01/2048px-HP_logo_2012.svg.png' ),
                array( 'name' => 'Lenovo',    'url' => $_b . '/wp-content/uploads/2023/01/Lenovo-Logo.jpg' ),
                array( 'name' => 'Logitech',  'url' => $_b . '/wp-content/uploads/2023/01/Logitech-Logo.png' ),
                array( 'name' => 'Samsung',   'url' => $_b . '/wp-content/uploads/2023/01/Samsung-Logo-2.png' ),
                array( 'name' => 'Razer',     'url' => $_b . '/wp-content/uploads/2023/01/Razer_snake_logo.svg.png' ),
                array( 'name' => 'ASUS ROG',  'url' => $_b . '/wp-content/uploads/2025/12/asus-r-o-g-logo-mo786jhtsvw0537d-mo786jhtsvw0537d.jpg' ),
                array( 'name' => 'DXRacer',   'url' => $_b . '/wp-content/uploads/2025/12/1687852557206-1676343536644-DXRacer-Logo.png' ),
            );
            foreach ( $fp_brands as $brand ) : ?>
            <div class="fp-brands__item">
                <img src="<?php echo esc_url( $brand['url'] ); ?>" alt="<?php echo esc_attr( $brand['name'] ); ?>" loading="lazy" />
            </div>
            <?php endforeach; ?>
        </div>

        <button class="fp-brands__arrow fp-brands__arrow--prev" id="fp-brands-prev" aria-label="Previous">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="fp-brands__arrow fp-brands__arrow--next" id="fp-brands-next" aria-label="Next">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>

    <div class="fp-brands__dots" id="fp-brands-dots"></div>
</section>

<script>
(function () {
    var track     = document.getElementById('fp-brands-track');
    var dotsWrap  = document.getElementById('fp-brands-dots');
    var perView   = window.innerWidth <= 768 ? 3 : 7;
    var items     = track.querySelectorAll('.fp-brands__item');
    var total     = items.length;
    var pages     = Math.ceil(total / perView);
    var cur       = 0;
    var timer;

    // Build dots
    for (var i = 0; i < pages; i++) {
        var d = document.createElement('button');
        d.className = 'fp-brands__dot' + (i === 0 ? ' active' : '');
        d.dataset.i = i;
        d.setAttribute('aria-label', 'Page ' + (i + 1));
        dotsWrap.appendChild(d);
    }

    function goTo(n) {
        cur = (n + pages) % pages;
        var pct = cur * (perView / total) * 100;
        track.style.transform = 'translateX(-' + pct + '%)';
        dotsWrap.querySelectorAll('.fp-brands__dot').forEach(function (d, i) {
            d.classList.toggle('active', i === cur);
        });
    }

    document.getElementById('fp-brands-next').addEventListener('click', function () { clearInterval(timer); goTo(cur + 1); startAuto(); });
    document.getElementById('fp-brands-prev').addEventListener('click', function () { clearInterval(timer); goTo(cur - 1); startAuto(); });
    dotsWrap.addEventListener('click', function (e) {
        if (e.target.classList.contains('fp-brands__dot')) {
            clearInterval(timer); goTo(parseInt(e.target.dataset.i)); startAuto();
        }
    });

    function startAuto() { timer = setInterval(function () { goTo(cur + 1); }, 3000); }
    startAuto();
})();
</script>

<?php get_footer(); ?>
