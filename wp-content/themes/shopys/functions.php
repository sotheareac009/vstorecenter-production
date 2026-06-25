<?php
/**********************/
// child style enqueue
/**********************/
function shopys_styles(){

    // Google Fonts: Play (Latin / English) + Battambang (Khmer)
    wp_enqueue_style(
        'shopys-google-play',
        'https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Play:wght@400;700&display=swap',
        array(),
        null
    );

    // Enqueue our child style.css with our own version for cache busting
    $childVersion = filemtime( get_stylesheet_directory() . '/style.css' );

    wp_enqueue_style('shopys-styles', get_stylesheet_uri(), array( 'shopys-google-play' ), $childVersion);

    wp_add_inline_style('shopys-styles', shopys_custom_styles());

}

add_action('wp_enqueue_scripts', 'shopys_styles', 100);

define('shopys_FOOTER_LAYOUT_TWO', get_theme_file_uri(). "/images/widget-footer-2.png");

/**********************/
//customize setting
/**********************/

function shopys_setting( $wp_customize ){

/******************/
// theme color
/******************/
 $wp_customize->add_setting('open_shop_theme_clr', array(
        'default'        => '#0a0101',
        'capability'     => 'edit_theme_options',
        'sanitize_callback' => 'open_shop_sanitize_color',
        'transport'         => 'postMessage',
    ));
$wp_customize->add_control( 
    new WP_Customize_Color_Control($wp_customize,'open_shop_theme_clr', array(
        'label'      => __('Theme Color', 'shopys' ),
        'section'    => 'open-shop-gloabal-color',
        'settings'   => 'open_shop_theme_clr',
        'priority' => 1,
    ) ) 
 );    
/***********************************/  
// menu alignment
/***********************************/ 
$wp_customize->add_setting('open_shop_menu_alignment', array(
                'default'               => 'right',
                'sanitize_callback'     => 'open_shop_sanitize_select',
            ) );
$wp_customize->add_control( new Open_Shop_Customizer_Buttonset_Control( $wp_customize, 'open_shop_menu_alignment', array(
                'label'                 => esc_html__( 'Menu Alignment', 'shopys' ),
                'section'               => 'open-shop-main-header',
                'settings'              => 'open_shop_menu_alignment',
                'choices'               => array(
                    'left'              => esc_html__( 'Left', 'shopys' ),
                    'center'        => esc_html__( 'center', 'shopys' ),
                    'right'             => esc_html__( 'Right', 'shopys' ),
                ),
        ) ) );
// excerpt length
    $wp_customize->add_setting('open_shop_blog_expt_length', array(
            'default'           =>'15',
            'capability'        => 'edit_theme_options',
            'sanitize_callback' =>'open_shop_sanitize_number',
        )
    );
    $wp_customize->add_control('open_shop_blog_expt_length', array(
            'type'        => 'number',
            'section'     => 'open-shop-section-blog-group',
            'label'       => __( 'Excerpt Length', 'shopys' ),
            'input_attrs' => array(
                'min'  => 0,
                'step' => 1,
                'max'  => 3000,
            ),
             'priority'   =>10,
        )
    );
//Main menu option
$wp_customize->add_setting('open_shop_main_header_option', array(
        'default'        => 'none',
        'capability'     => 'edit_theme_options',
        'sanitize_callback' => 'open_shop_sanitize_select',
    ));
$wp_customize->add_control( 'open_shop_main_header_option', array(
        'settings' => 'open_shop_main_header_option',
        'label'    => __('Column 1','shopys'),
        'section'  => 'open-shop-main-header',
        'type'     => 'select',
        'choices'    => array(
        'none'       => __('None','shopys'),
        'callto'     => __('Call-To','shopys'),
        'button'     => __('Button','shopys'),
        'widget'     => __('Widget','shopys'),     
        ),
    ));


/******************/
//Widegt footer
/******************/
if(class_exists('Open_Shop_WP_Customize_Control_Radio_Image')){
               $wp_customize->add_setting(
               'open_shop_bottom_footer_widget_layout', array(
               'default'           => 'ft-wgt-none',
               'sanitize_callback' => 'sanitize_text_field',
            )
        );
$wp_customize->add_control(
            new Open_Shop_WP_Customize_Control_Radio_Image(
                $wp_customize, 'open_shop_bottom_footer_widget_layout', array(
                    'label'    => esc_html__( 'Layout','shopys'),
                    'section'  => 'open-shop-widget-footer',
                    'choices'  => array(
                       'ft-wgt-none'   => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_NONE,
                        ),
                        'ft-wgt-one'   => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_1,
                        ),
                        'ft-wgt-two' => array(
                            'url' => shopys_FOOTER_LAYOUT_TWO,
                        ),
                        'ft-wgt-three' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_3,
                        ),
                        'ft-wgt-four' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_4,
                        ),
                        'ft-wgt-five' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_5,
                        ),
                        'ft-wgt-six' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_6,
                        ),
                        'ft-wgt-seven' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_7,
                        ),
                        'ft-wgt-eight' => array(
                            'url' => OPEN_SHOP_FOOTER_WIDGET_LAYOUT_8,
                        ),
                    ),
                )
            )
        );
    } 

/******************************/
/* Widget Redirect
/****************************/
if (class_exists('Open_Shop_Widegt_Redirect')){ 
$wp_customize->add_setting(
            'open_shop_bottom_footer_widget_redirect', array(
            'sanitize_callback' => 'sanitize_text_field',
     )
);
$wp_customize->add_control(
            new Open_Shop_Widegt_Redirect(
                $wp_customize, 'open_shop_bottom_footer_widget_redirect', array(
                    'section'      => 'open-shop-widget-footer',
                    'button_text'  => esc_html__( 'Go To Widget', 'shopys' ),
                    'button_class' => 'focus-customizer-widget-redirect',  
                )
            )
        );
} 

}

add_action( 'customize_register', 'shopys_setting', 100 );

/* ── Hero Slider Customizer ───────────────────────────────────── */
function shopys_hero_slider_customizer( $wp_customize ) {

    $wp_customize->add_section( 'shopys_hero_slider', array(
        'title'    => __( 'Hero Slider Images', 'shopys' ),
        'priority' => 30,
    ) );

    $defaults = shopys_hero_slider_defaults();

    for ( $i = 1; $i <= 5; $i++ ) {
        $wp_customize->add_setting( 'shopys_hero_slide_' . $i, array(
            'default'           => $defaults[ $i ],
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'shopys_hero_slide_' . $i, array(
            'label'   => sprintf( __( 'Slide %d Image', 'shopys' ), $i ),
            'section' => 'shopys_hero_slider',
        ) ) );
    }
}
add_action( 'customize_register', 'shopys_hero_slider_customizer', 101 );

// ── SHOPYS ADMIN SUBMENUS (attached to existing shopys-dashboard menu) ────
// Priority 20 — runs AFTER shortcode-guide.php registers the parent menu
add_action( 'admin_menu', function() {
    add_submenu_page(
        'shopys-dashboard',
        'Hero Banner',
        'Hero Banner',
        'edit_posts',
        'shopys-hero-banner',
        'shopys_hero_banner_page'
    );
    add_submenu_page(
        'shopys-dashboard',
        'Announcement Banner',
        'Announcement Banner',
        'edit_posts',
        'shopys-announcement-banner',
        'shopys_announcement_banner_page'
    );
    add_submenu_page(
        'shopys-dashboard',
        'Trust Bar',
        'Trust Bar',
        'edit_posts',
        'shopys-trust-bar',
        'shopys_trust_bar_page'
    );
}, 20 );

// Redirect pretty admin URLs to their real admin.php?page= equivalents
add_action( 'init', function() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $map = array(
        'wp-admin/shopys-hero-banner'  => 'shopys-hero-banner',
        'wp-admin/shopys-hero-slider'  => 'shopys-hero-slider',
        'wp-admin/shopys-dashboard'    => 'shopys-dashboard',
        'wp-admin/shopys-product-details' => 'shopys-product-details',
    );
    foreach ( $map as $pretty => $page ) {
        if ( strpos( $uri, $pretty ) !== false && strpos( $uri, 'admin.php' ) === false ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
            exit;
        }
    }
}, 1 );

function shopys_hero_banner_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    if ( isset( $_POST['shopys_hero_save'] ) && check_admin_referer( 'shopys_hero_save' ) ) {
        $fields = array( 'shopys_hero_tag', 'shopys_hero_title', 'shopys_hero_title_highlight', 'shopys_hero_subtitle', 'shopys_hero_cta_text', 'shopys_hero_cta_url' );
        foreach ( $fields as $key ) {
            $val = isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
            update_option( $key, $val );
        }
        echo '<div class="notice notice-success is-dismissible"><p><strong>Hero banner saved!</strong></p></div>';
    }

    $tag       = get_option( 'shopys_hero_tag',             'New Arrivals 2025' );
    $title     = get_option( 'shopys_hero_title',           'Your Ultimate' );
    $highlight = get_option( 'shopys_hero_title_highlight', 'Tech Store' );
    $subtitle  = get_option( 'shopys_hero_subtitle',        'Laptops · Gaming Gear · PC Hardware · Accessories — all under one roof at the best prices.' );
    $cta_text  = get_option( 'shopys_hero_cta_text',        'Shop Now' );
    $cta_url   = get_option( 'shopys_hero_cta_url',         '/laptop-v2/' );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="background:#13e800;color:#000;padding:4px 14px;border-radius:6px;font-size:13px;font-weight:700;">Shopys</span>
            Hero Banner
        </h1>
        <p style="color:#666;margin-bottom:20px;">Edit the text shown on the homepage hero slider overlay.</p>

        <form method="POST">
            <?php wp_nonce_field( 'shopys_hero_save' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="shopys_hero_tag">Badge Text</label></th>
                    <td><input type="text" id="shopys_hero_tag" name="shopys_hero_tag" value="<?php echo esc_attr( $tag ); ?>" class="regular-text">
                    <p class="description">Small pill above the title e.g. "New Arrivals 2025"</p></td>
                </tr>
                <tr>
                    <th><label for="shopys_hero_title">Title — First Line</label></th>
                    <td><input type="text" id="shopys_hero_title" name="shopys_hero_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text">
                    <p class="description">e.g. "Your Ultimate"</p></td>
                </tr>
                <tr>
                    <th><label for="shopys_hero_title_highlight">Title — Highlighted Word</label></th>
                    <td><input type="text" id="shopys_hero_title_highlight" name="shopys_hero_title_highlight" value="<?php echo esc_attr( $highlight ); ?>" class="regular-text">
                    <p class="description">Shown in green e.g. "Tech Store"</p></td>
                </tr>
                <tr>
                    <th><label for="shopys_hero_subtitle">Subtitle</label></th>
                    <td><textarea id="shopys_hero_subtitle" name="shopys_hero_subtitle" class="large-text" rows="3"><?php echo esc_textarea( $subtitle ); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="shopys_hero_cta_text">Button Text</label></th>
                    <td><input type="text" id="shopys_hero_cta_text" name="shopys_hero_cta_text" value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text">
                    <p class="description">e.g. "Shop Now"</p></td>
                </tr>
                <tr>
                    <th><label for="shopys_hero_cta_url">Button URL</label></th>
                    <td><input type="text" id="shopys_hero_cta_url" name="shopys_hero_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" class="regular-text">
                    <p class="description">e.g. /laptop-v2/ or full URL</p></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="shopys_hero_save" class="button button-primary" style="background:#13e800;border-color:#0fb500;color:#000;font-weight:700;">
                    Save Hero Banner
                </button>
            </p>
        </form>

        <div style="margin-top:30px;padding:20px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;max-width:600px;">
            <strong>Live Preview</strong>
            <div style="margin-top:12px;background:#0d1117;padding:24px;border-radius:8px;color:#fff;">
                <div style="display:inline-block;background:rgba(19,232,0,.15);border:1px solid rgba(19,232,0,.35);color:#13e800;font-size:11px;font-weight:700;padding:4px 12px;border-radius:50px;margin-bottom:10px;"><?php echo esc_html( $tag ); ?></div>
                <div style="font-size:22px;font-weight:800;line-height:1.2;margin-bottom:8px;"><?php echo esc_html( $title ); ?> <span style="color:#13e800;"><?php echo esc_html( $highlight ); ?></span></div>
                <div style="font-size:13px;opacity:.7;margin-bottom:14px;"><?php echo esc_html( $subtitle ); ?></div>
                <div style="display:inline-block;background:#13e800;color:#000;font-weight:700;font-size:13px;padding:8px 18px;border-radius:6px;"><?php echo esc_html( $cta_text ); ?> →</div>
            </div>
        </div>
    </div>
    <?php
}

function shopys_trust_bar_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    if ( isset( $_POST['shopys_trust_save'] ) && check_admin_referer( 'shopys_trust_save' ) ) {
        $enabled = isset( $_POST['shopys_trust_enabled'] ) ? '1' : '0';
        
        // Handle deletions first
        if ( isset( $_POST['shopys_trust_delete'] ) && ! empty( $_POST['shopys_trust_delete'] ) ) {
            $delete_ids = array_map( 'intval', (array) $_POST['shopys_trust_delete'] );
            foreach ( $delete_ids as $id ) {
                delete_option( "shopys_trust_title_$id" );
                delete_option( "shopys_trust_desc_$id" );
            }
        }
        
        // Count and save all trust items — reindex after deletion
        $item_count = 0;
        $new_index = 1;
        for ( $i = 1; $i <= 50; $i++ ) {
            $title = isset( $_POST[ "shopys_trust_title_$i" ] ) ? sanitize_text_field( $_POST[ "shopys_trust_title_$i" ] ) : '';
            $desc  = isset( $_POST[ "shopys_trust_desc_$i" ] ) ? sanitize_text_field( $_POST[ "shopys_trust_desc_$i" ] ) : '';
            
            if ( ! empty( $title ) ) {
                update_option( "shopys_trust_title_$new_index", $title );
                update_option( "shopys_trust_desc_$new_index", $desc );
                $item_count++;
                $new_index++;
            }
        }
        
        // Clean up old indices after reordering
        for ( $i = $new_index; $i <= 50; $i++ ) {
            delete_option( "shopys_trust_title_$i" );
            delete_option( "shopys_trust_desc_$i" );
        }
        
        update_option( 'shopys_trust_enabled', $enabled );
        update_option( 'shopys_trust_count', $item_count );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Trust bar saved with ' . $item_count . ' item(s)!</strong></p></div>';
    }

    $enabled = get_option( 'shopys_trust_enabled', '1' );
    $item_count = (int) get_option( 'shopys_trust_count', 4 );
    
    // Default trust items
    $defaults = array(
        1 => array( 'title' => 'Fastest Delivery', 'desc' => 'Phnom Penh & nationwide' ),
        2 => array( 'title' => 'Official Warranty', 'desc' => 'All products guaranteed' ),
        3 => array( 'title' => 'After-Sales Support', 'desc' => '7 days a week' ),
        4 => array( 'title' => 'Secure Payment', 'desc' => 'ABA · ACLEDA · Cash' ),
    );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="background:#13e800;color:#000;padding:4px 14px;border-radius:6px;font-size:13px;font-weight:700;">Shopys</span>
            Trust Bar
        </h1>
        <p style="color:#666;margin-bottom:20px;">Control the trust bar visibility and customize trust items displayed on homepage.</p>

        <form method="POST" id="shopys_trust_form">
            <?php wp_nonce_field( 'shopys_trust_save' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="shopys_trust_enabled">Enable Trust Bar</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="shopys_trust_enabled" name="shopys_trust_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
                            Show trust bar on homepage
                        </label>
                    </td>
                </tr>
            </table>

            <h2 style="margin-top:30px;margin-bottom:15px;font-size:18px;">Trust Items <span style="color:#999;font-size:14px;font-weight:400;">(<span id="item_count"><?php echo $item_count; ?></span> items)</span></h2>
            <div id="trust_items_container">
            <?php 
            $max_show = max( $item_count + 1, 5 );
            for ( $i = 1; $i <= $max_show; $i++ ) {
                $title = get_option( "shopys_trust_title_$i", isset( $defaults[$i] ) ? $defaults[$i]['title'] : '' );
                $desc  = get_option( "shopys_trust_desc_$i", isset( $defaults[$i] ) ? $defaults[$i]['desc'] : '' );
            ?>
            <table class="form-table trust-item-table" role="presentation" style="border-top:1px solid #e5e5e5;padding-top:20px;margin-bottom:0;">
                <tr>
                    <th colspan="2" style="padding-left:0;"><strong>Item <?php echo $i; ?></strong></th>
                    <td style="text-align:right;padding-right:0;">
                        <button type="button" class="delete-trust-item button" data-item-id="<?php echo $i; ?>" style="color:#d40040;border-color:#d40040;background:#fff;">Delete</button>
                    </td>
                </tr>
                <tr>
                    <th style="width:30%;"><label for="shopys_trust_title_<?php echo $i; ?>">Title</label></th>
                    <td colspan="2">
                        <input type="text" id="shopys_trust_title_<?php echo $i; ?>" name="shopys_trust_title_<?php echo $i; ?>" value="<?php echo esc_attr( $title ); ?>" class="regular-text" placeholder="e.g., Fastest Delivery">
                    </td>
                </tr>
                <tr>
                    <th><label for="shopys_trust_desc_<?php echo $i; ?>">Description</label></th>
                    <td colspan="2">
                        <input type="text" id="shopys_trust_desc_<?php echo $i; ?>" name="shopys_trust_desc_<?php echo $i; ?>" value="<?php echo esc_attr( $desc ); ?>" class="regular-text" placeholder="e.g., Phnom Penh & nationwide">
                        <p class="description">Supporting text or location</p>
                    </td>
                </tr>
            </table>
            <?php } ?>
            </div>

            <p class="submit" style="margin-top:20px;">
                <button type="button" id="add_trust_item" class="button" style="margin-right:10px;">+ Add New Item</button>
                <button type="submit" name="shopys_trust_save" class="button button-primary" style="background:#13e800;border-color:#0fb500;color:#000;font-weight:700;">
                    Save Trust Bar
                </button>
            </p>
        </form>

        <div style="margin-top:30px;padding:20px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;max-width:600px;">
            <strong>Live Preview</strong>
            <div style="margin-top:15px;display:flex;flex-direction:column;gap:12px;" id="preview_container">
                <?php for ( $i = 1; $i <= $item_count; $i++ ) {
                    $title = get_option( "shopys_trust_title_$i", isset( $defaults[$i] ) ? $defaults[$i]['title'] : '' );
                    $desc  = get_option( "shopys_trust_desc_$i", isset( $defaults[$i] ) ? $defaults[$i]['desc'] : '' );
                    if ( ! empty( $title ) ) {
                ?>
                <div style="padding:12px;background:white;border-left:3px solid #13e800;border-radius:4px;">
                    <strong><?php echo esc_html( $title ); ?></strong><br>
                    <span style="color:#666;font-size:13px;"><?php echo esc_html( $desc ); ?></span>
                </div>
                <?php } } ?>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('add_trust_item').addEventListener('click', function(e) {
        e.preventDefault();
        const container = document.getElementById('trust_items_container');
        const tables = container.querySelectorAll('.trust-item-table');
        const nextNum = tables.length + 1;
        
        const newItemHTML = `
            <table class="form-table trust-item-table" role="presentation" style="border-top:1px solid #e5e5e5;padding-top:20px;margin-bottom:0;">
                <tr>
                    <th colspan="2" style="padding-left:0;"><strong>Item ${nextNum}</strong></th>
                    <td style="text-align:right;padding-right:0;">
                        <button type="button" class="delete-trust-item button" data-item-id="${nextNum}" style="color:#d40040;border-color:#d40040;background:#fff;">Delete</button>
                    </td>
                </tr>
                <tr>
                    <th style="width:30%;"><label for="shopys_trust_title_${nextNum}">Title</label></th>
                    <td colspan="2">
                        <input type="text" id="shopys_trust_title_${nextNum}" name="shopys_trust_title_${nextNum}" value="" class="regular-text" placeholder="e.g., Fastest Delivery">
                    </td>
                </tr>
                <tr>
                    <th><label for="shopys_trust_desc_${nextNum}">Description</label></th>
                    <td colspan="2">
                        <input type="text" id="shopys_trust_desc_${nextNum}" name="shopys_trust_desc_${nextNum}" value="" class="regular-text" placeholder="e.g., Phnom Penh & nationwide">
                        <p class="description">Supporting text or location</p>
                    </td>
                </tr>
            </table>
        `;
        
        container.insertAdjacentHTML('beforeend', newItemHTML);
        attachDeleteListener();
    });

    function attachDeleteListener() {
        document.querySelectorAll('.delete-trust-item').forEach(btn => {
            btn.removeEventListener('click', deleteItem);
            btn.addEventListener('click', deleteItem);
        });
    }

    function deleteItem(e) {
        e.preventDefault();
        const btn = e.target;
        const table = btn.closest('.trust-item-table');
        if (confirm('Are you sure you want to delete this item?')) {
            table.remove();
            updatePreview();
        }
    }

    function updatePreview() {
        const preview = document.getElementById('preview_container');
        preview.innerHTML = '';
        let count = 0;
        
        for (let i = 1; i <= 50; i++) {
            const titleEl = document.getElementById(`shopys_trust_title_${i}`);
            const descEl = document.getElementById(`shopys_trust_desc_${i}`);
            
            if (titleEl && titleEl.value.trim()) {
                count++;
                const previewItem = `
                    <div style="padding:12px;background:white;border-left:3px solid #13e800;border-radius:4px;">
                        <strong>${titleEl.value}</strong><br>
                        <span style="color:#666;font-size:13px;">${descEl.value}</span>
                    </div>
                `;
                preview.insertAdjacentHTML('beforeend', previewItem);
            }
        }
        
        document.getElementById('item_count').textContent = count;
    }

    // Update preview in real-time on input changes
    document.getElementById('shopys_trust_form').addEventListener('input', updatePreview);
    
    // Initial attachment of delete listeners
    attachDeleteListener();
    </script>
    <?php
}

function shopys_announcement_banner_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;

    if ( isset( $_POST['shopys_announcement_save'] ) && check_admin_referer( 'shopys_announcement_save' ) ) {
        $enabled = isset( $_POST['shopys_announcement_enabled'] ) ? '1' : '0';
        $text    = isset( $_POST['shopys_announcement_text'] ) ? sanitize_text_field( $_POST['shopys_announcement_text'] ) : '';
        $badge   = isset( $_POST['shopys_announcement_badge'] ) ? sanitize_text_field( $_POST['shopys_announcement_badge'] ) : '';
        
        update_option( 'shopys_announcement_enabled', $enabled );
        update_option( 'shopys_announcement_text', $text );
        update_option( 'shopys_announcement_badge', $badge );
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>Announcement banner saved!</strong></p></div>';
    }

    $enabled = get_option( 'shopys_announcement_enabled', '1' );
    $text    = get_option( 'shopys_announcement_text', 'No Thai Products Here' );
    $badge   = get_option( 'shopys_announcement_badge', 'Notice' );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="background:#13e800;color:#000;padding:4px 14px;border-radius:6px;font-size:13px;font-weight:700;">Shopys</span>
            Announcement Banner
        </h1>
        <p style="color:#666;margin-bottom:20px;">Control the top announcement banner visibility and content.</p>

        <form method="POST">
            <?php wp_nonce_field( 'shopys_announcement_save' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="shopys_announcement_enabled">Enable Banner</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="shopys_announcement_enabled" name="shopys_announcement_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
                            Show announcement banner on homepage
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="shopys_announcement_text">Banner Text</label></th>
                    <td><input type="text" id="shopys_announcement_text" name="shopys_announcement_text" value="<?php echo esc_attr( $text ); ?>" class="regular-text">
                    <p class="description">Main message to display</p></td>
                </tr>
                <tr>
                    <th><label for="shopys_announcement_badge">Badge Label</label></th>
                    <td><input type="text" id="shopys_announcement_badge" name="shopys_announcement_badge" value="<?php echo esc_attr( $badge ); ?>" class="regular-text">
                    <p class="description">Small badge text e.g. "Notice", "Alert", "Info"</p></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="shopys_announcement_save" class="button button-primary" style="background:#13e800;border-color:#0fb500;color:#000;font-weight:700;">
                    Save Announcement Banner
                </button>
            </p>
        </form>

        <div style="margin-top:30px;padding:20px;background:#f8f9fa;border:1px solid #e2e8f0;border-radius:8px;max-width:600px;">
            <strong>Live Preview</strong>
            <div style="margin-top:12px;background:linear-gradient(135deg, #13e800 0%, #0fb500 100%);padding:6px 16px;border-radius:8px;display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" style="flex-shrink:0;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span style="font-size:12px;font-weight:700;color:#fff;"><?php echo esc_html( $text ); ?></span>
                <span style="background:rgb(255,193,212);color:#d40040;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:auto;"><?php echo esc_html( $badge ); ?></span>
            </div>
        </div>
    </div>
    <?php
}

/***************************/
//custom style
/***************************/
function shopys_custom_styles(){
$open_shop_theme_clr = esc_html(get_theme_mod('open_shop_theme_clr','#0a0101'));
$open_shop_color_scheme = esc_html(get_theme_mod('open_shop_color_scheme','opn-light'));

$shopys_custom_style=""; 

$shopys_custom_style.="a:hover, .open-shop-menu li a:hover, .open-shop-menu .current-menu-item a,.woocommerce .thunk-woo-product-list .price,.thunk-product-hover .th-button.add_to_cart_button, .woocommerce ul.products .thunk-product-hover .add_to_cart_button, .woocommerce .thunk-product-hover a.th-butto, .woocommerce ul.products li.product .product_type_variable, .woocommerce ul.products li.product a.button.product_type_grouped,.thunk-compare .compare-button a:hover, .thunk-product-hover .th-button.add_to_cart_button:hover, .woocommerce ul.products .thunk-product-hover .add_to_cart_button :hover, .woocommerce .thunk-product-hover a.th-button:hover,.thunk-product .yith-wcwl-wishlistexistsbrowse.show:before, .thunk-product .yith-wcwl-wishlistaddedbrowse.show:before,.woocommerce ul.products li.product.thunk-woo-product-list .price,.summary .yith-wcwl-add-to-wishlist.show .add_to_wishlist::before, .summary .yith-wcwl-add-to-wishlist .yith-wcwl-wishlistaddedbrowse.show a::before, .summary .yith-wcwl-add-to-wishlist .yith-wcwl-wishlistexistsbrowse.show a::before,.woocommerce .entry-summary a.compare.button.added:before,.header-icon a:hover,.thunk-related-links .nav-links a:hover,.woocommerce .thunk-list-view ul.products li.product.thunk-woo-product-list .price,.woocommerce .woocommerce-error .button, .woocommerce .woocommerce-info .button, .woocommerce .woocommerce-message .button,article.thunk-post-article .thunk-readmore.button,.thunk-wishlist a:hover, .thunk-compare a:hover,.woocommerce .thunk-product-hover a.th-button,.woocommerce ul.cart_list li .woocommerce-Price-amount, .woocommerce ul.product_list_widget li .woocommerce-Price-amount,.open-shop-load-more button, 
.summary .yith-wcwl-add-to-wishlist .yith-wcwl-wishlistaddedbrowse a::before,
 .summary .yith-wcwl-add-to-wishlist .yith-wcwl-wishlistexistsbrowse a::before,.thunk-hglt-icon,.thunk-product .yith-wcwl-wishlistexistsbrowse:before, .thunk-product .yith-wcwl-wishlistaddedbrowse:before,.woocommerce a.button.product_type_simple,.woosw-btn:hover:before,.woosw-added:before,.wooscp-btn:hover:before,.woocommerce #reviews #comments .star-rating span ,.woocommerce p.stars a,.woocommerce .woocommerce-product-rating .star-rating,.woocommerce .star-rating span::before, .woocommerce .entry-summary a.th-product-compare-btn.btn_type:before{color:{$open_shop_theme_clr};} header #thaps-search-button,header #thaps-search-button:hover,.nav-links .page-numbers.current, .nav-links .page-numbers:hover{background:{$open_shop_theme_clr};}";

 if($open_shop_color_scheme=='opn-dark'){
$shopys_custom_style.="body.open-shop-dark a:hover, body.open-shop-dark .open-shop-menu > li > a:hover, body.open-shop-dark .open-shop-menu li ul.sub-menu li a:hover,body.open-shop-dark .thunk-product-cat-list li a:hover,body.open-shop-dark .main-header a:hover, body.open-shop-dark #sidebar-primary .open-shop-widget-content a:hover,.open-shop-dark .thunk-woo-product-list .woocommerce-loop-product__title a:hover{color:{$open_shop_theme_clr}} body.open-shop-dark #searchform [type='submit']{background:{$open_shop_theme_clr};border-color:{$open_shop_theme_clr}}";
}

$shopys_custom_style.=".toggle-cat-wrap,#search-button,.thunk-icon .cart-icon, .single_add_to_cart_button.button.alt, .woocommerce #respond input#submit.alt, .woocommerce a.button.alt, .woocommerce button.button.alt, .woocommerce input.button.alt, .woocommerce #respond input#submit, .woocommerce button.button, .woocommerce input.button,.thunk-woo-product-list .thunk-quickview a,.cat-list a:after,.tagcloud a:hover, .thunk-tags-wrapper a:hover,.btn-main-header,.woocommerce div.product form.cart .button, .thunk-icon .cart-icon .taiowc-cart-item{background:{$open_shop_theme_clr}}
  .open-cart p.buttons a:hover,
  .woocommerce #respond input#submit.alt:hover, .woocommerce a.button.alt:hover, .woocommerce button.button.alt:hover, .woocommerce input.button.alt:hover, .woocommerce #respond input#submit:hover, .woocommerce button.button:hover, .woocommerce input.button:hover,.thunk-slide .owl-nav button.owl-prev:hover, .thunk-slide .owl-nav button.owl-next:hover, .open-shop-slide-post .owl-nav button.owl-prev:hover, .open-shop-slide-post .owl-nav button.owl-next:hover,.thunk-list-grid-switcher a.selected, .thunk-list-grid-switcher a:hover,.woocommerce .woocommerce-error .button:hover, .woocommerce .woocommerce-info .button:hover, .woocommerce .woocommerce-message .button:hover,#searchform [type='submit']:hover,article.thunk-post-article .thunk-readmore.button:hover,.open-shop-load-more button:hover,.woocommerce nav.woocommerce-pagination ul li a:focus, .woocommerce nav.woocommerce-pagination ul li a:hover, .woocommerce nav.woocommerce-pagination ul li span.current{background-color:{$open_shop_theme_clr};} 
  .thunk-product-hover .th-button.add_to_cart_button, .woocommerce ul.products .thunk-product-hover .add_to_cart_button, .woocommerce .thunk-product-hover a.th-butto, .woocommerce ul.products li.product .product_type_variable, .woocommerce ul.products li.product a.button.product_type_grouped,.open-cart p.buttons a:hover,.thunk-slide .owl-nav button.owl-prev:hover, .thunk-slide .owl-nav button.owl-next:hover, .open-shop-slide-post .owl-nav button.owl-prev:hover, .open-shop-slide-post .owl-nav button.owl-next:hover,body .woocommerce-tabs .tabs li a::before,.thunk-list-grid-switcher a.selected, .thunk-list-grid-switcher a:hover,.woocommerce .woocommerce-error .button, .woocommerce .woocommerce-info .button, .woocommerce .woocommerce-message .button,#searchform [type='submit']:hover,article.thunk-post-article .thunk-readmore.button,.woocommerce .thunk-product-hover a.th-button,.open-shop-load-more button,.woocommerce a.button.product_type_simple{border-color:{$open_shop_theme_clr}} .loader {
    border-right: 4px solid {$open_shop_theme_clr};
    border-bottom: 4px solid {$open_shop_theme_clr};
    border-left: 4px solid {$open_shop_theme_clr};}";

    //ribbon  
 $shopys_custom_style.=".openshop-site section.thunk-ribbon-section .content-wrap:before {
    content:'';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background:{$open_shop_theme_clr};}";

return $shopys_custom_style;
}

function shopys_customizer_script_registers(){
wp_enqueue_script( 'shopys_custom_customizer_script', get_theme_file_uri() . '/customizer/js/customizer.js', array("jquery"), '', true  ); 
}
add_action('customize_controls_enqueue_scripts', 'shopys_customizer_script_registers',100 );


// customizer style
function shopys_store_style(){ ?>
<style>
.customize-control-radio-image .ui-state-active img {
    border-color: #00b6ff!important;
    -webkit-box-shadow: 0 0 1px #3ec8ff!important;
    box-shadow: 0 0 5px #3ec8fe!important;
}
</style>
<?php }
add_action('customize_controls_print_styles','shopys_store_style',100 );

/**********************/
// Premium Product Grid
/**********************/
if ( class_exists( 'WooCommerce' ) ) {
    require_once get_stylesheet_directory() . '/template-parts/product-grid.php';
    require_once get_stylesheet_directory() . '/template-parts/featured-product-grid.php';
    require_once get_stylesheet_directory() . '/template-parts/latest-product-grid.php';
    require_once get_stylesheet_directory() . '/template-parts/marvo-product-grid.php';
    require_once get_stylesheet_directory() . '/template-parts/product-by-category.php';
    require_once get_stylesheet_directory() . '/template-parts/cart-summary.php';
    require_once get_stylesheet_directory() . '/inc/cart-invoice.php';
}

// ── Register TikTok and Telegram in Customizer > Social Icons panel ──
add_action( 'customize_register', function( $wp_customize ) {
    foreach ( [
        'social_shop_link_tiktok'   => __( 'TikTok URL', 'shopys' ),
        'social_shop_link_telegram' => __( 'Telegram URL', 'shopys' ),
    ] as $id => $label ) {
        $wp_customize->add_setting( $id, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
        $wp_customize->add_control( $id, [
            'label'   => $label,
            'section' => 'open-shop-social-icon',
            'type'    => 'url',
        ] );
    }
}, 20 );

// Telegram Login — defines bot constants needed by AI Chatbot
require_once get_stylesheet_directory() . '/inc/telegram-login.php';

// Customer Shop Login / Register — floating button for WooCommerce customers
require_once get_stylesheet_directory() . '/inc/customer-login.php';

// My Account premium enhancements (sidebar icons, dashboard stat cards)
require_once get_stylesheet_directory() . '/inc/myaccount-enhance.php';
require_once get_stylesheet_directory() . '/inc/profile-picture.php';

// Premium footer polish — layered CSS over the Elementor footer.
add_action( 'wp_enqueue_scripts', function() {
    $css = get_stylesheet_directory() . '/css/footer-premium.css';
    if ( ! file_exists( $css ) ) return;
    wp_enqueue_style(
        'shopys-footer-premium',
        get_stylesheet_directory_uri() . '/css/footer-premium.css',
        array(),
        filemtime( $css )
    );
}, 60 );

// Premium checkout styling — only loads on the WC checkout / order-received pages.
add_action( 'wp_enqueue_scripts', function() {
    if ( ! function_exists( 'is_checkout' ) ) return;
    if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) return;

    $css = get_stylesheet_directory() . '/css/checkout-premium.css';
    if ( ! file_exists( $css ) ) return;
    wp_enqueue_style(
        'shopys-checkout-premium',
        get_stylesheet_directory_uri() . '/css/checkout-premium.css',
        array( 'woocommerce-general' ),
        filemtime( $css )
    );
}, 70 );

// Premium cart styling — only loads on the WC cart page.
add_action( 'wp_enqueue_scripts', function() {
    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) return;

    $css = get_stylesheet_directory() . '/css/cart-premium.css';
    if ( ! file_exists( $css ) ) return;
    wp_enqueue_style(
        'shopys-cart-premium',
        get_stylesheet_directory_uri() . '/css/cart-premium.css',
        array( 'woocommerce-general' ),
        filemtime( $css )
    );
}, 70 );

// AI Chatbot — always loads so the Settings page is always available
require_once get_stylesheet_directory() . '/inc/ai-chatbot.php';
require_once get_stylesheet_directory() . '/inc/khqrpay-gateway.php';

// Site view counter — pageviews dashboard inside WP Admin.
// Wrapped in file_exists so a half-finished FTP deploy can't crash the site.
$shopys_vc = get_stylesheet_directory() . '/inc/view-counter.php';
if ( file_exists( $shopys_vc ) ) require_once $shopys_vc;

// Shortcode Guide — admin sidebar reference page
require_once get_stylesheet_directory() . '/inc/shortcode-guide.php';
require_once get_stylesheet_directory() . '/inc/hero-slider-settings.php';

// ── Fixed $2 shipping — no zone setup required ────────────────────────────────
add_filter( 'woocommerce_package_rates', 'shopys_force_flat_2_shipping', 99, 2 );
function shopys_force_flat_2_shipping( $rates, $package ) {
    $fixed_rate = new WC_Shipping_Rate(
        'shopys_flat_2',              // rate ID
        __( 'Shipping', 'shopys' ),   // label shown on cart/checkout
        2,                            // cost — always $2
        array(),                      // no taxes
        'shopys_flat'                 // method ID
    );
    return array( 'shopys_flat_2' => $fixed_rate );
}

// ── Checkout: show a Shipping address form below Billing ──────────────────────
// Keep "Billing details", and always display a "Shipping Address" form below it.
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );
add_filter( 'woocommerce_ship_to_different_address_checked', '__return_true' );

// Relabel the shipping toggle heading to "Shipping Address".
add_filter( 'gettext', 'shopys_shipping_heading_label', 20, 3 );
function shopys_shipping_heading_label( $translated, $text, $domain ) {
    if ( 'woocommerce' === $domain && 'Ship to a different address?' === $text ) {
        return 'Shipping Address';
    }
    return $translated;
}

// Keep the shipping form permanently visible (hide the collapse checkbox).
add_action( 'wp_head', 'shopys_force_shipping_form_visible' );
function shopys_force_shipping_form_visible() {
    if ( ! ( function_exists( 'is_checkout' ) && is_checkout() ) ) return;
    echo '<style>#ship-to-different-address-checkbox{display:none !important;}#ship-to-different-address label{cursor:default;font-weight:700;}.woocommerce-shipping-fields .shipping_address{display:block !important;}</style>';
}

// Remove the checkout "Your personal data will be used…" privacy-policy paragraph.
add_filter( 'woocommerce_get_privacy_policy_text', function ( $text, $type ) {
    return ( $type === 'checkout' ) ? '' : $text;
}, 100, 2 );

// Remove the "Additional information" / "Order notes (optional)" block at checkout.
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// Clean, full-width, centered layout for the "Pay for order" page only (not /checkout/).
add_action( 'wp_head', 'shopys_order_pay_layout' );
function shopys_order_pay_layout() {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) return;
    ?>
    <style>
    /* Full-width content, no sidebar, single centered column */
    body.woocommerce-order-pay .sidebar-content-area,
    body.woocommerce-order-pay #secondary,
    body.woocommerce-order-pay .widget-area{ display:none !important; }
    body.woocommerce-order-pay .primary-content-area,
    body.woocommerce-order-pay .primary-content-wrap,
    body.woocommerce-order-pay .content-wrap,
    body.woocommerce-order-pay .entry-content,
    body.woocommerce-order-pay #primary{ width:100% !important; max-width:100% !important; float:none !important; flex:0 0 100% !important; }
    /* Hide empty customer/billing column that's not used when paying an existing order */
    body.woocommerce-order-pay #customer_details,
    body.woocommerce-order-pay .col2-set,
    body.woocommerce-order-pay form.checkout > .col2-set{ display:none !important; }
    /* The order/payment form: a clean centered card */
    body.woocommerce-order-pay #order_review{
        width:100% !important; max-width:840px !important; margin:24px auto 40px !important;
        position:static !important; float:none !important; box-sizing:border-box;
        background:#fff !important; border:1px solid #eef0f4 !important; border-radius:18px !important;
        box-shadow:0 16px 44px rgba(15,23,42,.07) !important; padding:26px 28px !important;
    }
    body.woocommerce-order-pay #order_review_heading{ max-width:840px; margin:24px auto 0 !important; padding:0 28px; }
    /* Restore a real TABLE layout (the checkout flex styling breaks 3-column alignment) */
    body.woocommerce-order-pay #order_review table.shop_table{ display:table !important; width:100% !important; table-layout:auto !important; border-collapse:collapse !important; margin:6px 0 0 !important; }
    body.woocommerce-order-pay #order_review table.shop_table thead{ display:table-header-group !important; }
    body.woocommerce-order-pay #order_review table.shop_table tbody{ display:table-row-group !important; max-height:none !important; overflow:visible !important; }
    body.woocommerce-order-pay #order_review table.shop_table tfoot{ display:table-footer-group !important; }
    body.woocommerce-order-pay #order_review table.shop_table tr{ display:table-row !important; }
    body.woocommerce-order-pay #order_review table.shop_table th,
    body.woocommerce-order-pay #order_review table.shop_table td{ display:table-cell !important; border:0 !important; padding:14px 8px !important; vertical-align:middle; }
    /* Continuous separators via collapsed cell borders */
    body.woocommerce-order-pay #order_review table.shop_table thead th{ border-bottom:2px solid #eef0f4 !important; color:#9aa3b0 !important; font-size:12px !important; font-weight:700 !important; letter-spacing:.6px !important; text-transform:uppercase; padding-bottom:11px !important; }
    body.woocommerce-order-pay #order_review table.shop_table tbody td{ border-bottom:1px solid #f1f3f6 !important; }
    body.woocommerce-order-pay #order_review table.shop_table tfoot th,
    body.woocommerce-order-pay #order_review table.shop_table tfoot td{ border-bottom:1px solid #f1f3f6 !important; }
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr:last-child th,
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr:last-child td{ border-bottom:0 !important; }
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr.order-total th,
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr.order-total td{ border-top:2px solid #eef0f4 !important; }
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr.order-total th,
    body.woocommerce-order-pay #order_review table.shop_table tfoot tr.order-total .amount{ font-size:16px !important; font-weight:800 !important; color:#0d1117 !important; }
    body.woocommerce-order-pay #order_review table.shop_table tbody td.product-name{ font-weight:700; color:#0d1117 !important; }
    body.woocommerce-order-pay #order_review table.shop_table .amount{ color:#0d1117 !important; font-weight:700; }
    /* Column alignment: Qty centered, Totals right */
    body.woocommerce-order-pay #order_review table.shop_table th.product-quantity,
    body.woocommerce-order-pay #order_review table.shop_table td.product-quantity,
    body.woocommerce-order-pay #order_review table.shop_table thead th:nth-child(2),
    body.woocommerce-order-pay #order_review table.shop_table tbody td:nth-child(2){ text-align:center !important; }
    body.woocommerce-order-pay #order_review table.shop_table th:last-child,
    body.woocommerce-order-pay #order_review table.shop_table td:last-child{ text-align:right !important; }
    /* Keep the payment area + "Pay for order" button inside the card padding */
    body.woocommerce-order-pay #order_review #payment{ background:transparent !important; border:0 !important; margin:0 !important; padding:0 !important; width:100% !important; max-width:100% !important; box-sizing:border-box; }
    body.woocommerce-order-pay #order_review .form-row.place-order,
    body.woocommerce-order-pay #order_review #payment .form-row{ margin:0 !important; padding:0 !important; width:100% !important; max-width:100% !important; box-sizing:border-box; }
    body.woocommerce-order-pay #order_review{ overflow:visible !important; padding-bottom:26px !important; }
    /* Keep #payment + the "Pay for order" button INSIDE the card padding.
       Uses 2–3 IDs to out-specify checkout-premium.css's '#payment #place_order'. */
    body.woocommerce-order-pay #order_review #payment{ width:100% !important; max-width:100% !important; margin:8px 0 0 !important; padding:0 !important; background:transparent !important; border:0 !important; box-sizing:border-box !important; }
    body.woocommerce-order-pay #order_review #payment .form-row,
    body.woocommerce-order-pay #order_review #payment .form-row.place-order{ width:100% !important; max-width:100% !important; margin:0 !important; padding:0 !important; box-sizing:border-box !important; }
    body.woocommerce-order-pay #order_review #payment #place_order,
    body.woocommerce-order-pay #order_review #payment button#place_order,
    body.woocommerce-order-pay #order_review #payment input#place_order{
        display:block !important; width:100% !important; max-width:100% !important;
        margin:18px 0 0 !important; box-sizing:border-box !important;
        position:static !important; float:none !important;
    }
    </style>
    <?php
}

// Premium, centered order overview (Order number / Date / Total / Payment) on pay/received pages.
add_action( 'wp_head', 'shopys_order_overview_style' );
function shopys_order_overview_style() {
    if ( ! function_exists( 'is_wc_endpoint_url' ) ) return;
    if ( ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) return;
    ?>
    <style>
    ul.woocommerce-order-overview.order_details{
        max-width:430px; margin:18px auto 26px !important; padding:8px 20px !important; list-style:none !important;
        display:block !important; background:#fff; border:1px solid #eef0f4 !important; border-radius:18px;
        box-shadow:0 16px 44px rgba(15,23,42,.07); font-family:'Play','Battambang',-apple-system,sans-serif;
    }
    ul.woocommerce-order-overview.order_details li{
        display:flex !important; justify-content:space-between; align-items:flex-start; gap:16px;
        padding:13px 0 !important; margin:0 !important; border:0 !important; border-bottom:1px solid #f1f3f6 !important;
        width:auto !important; float:none !important; text-transform:none !important; text-align:left !important;
        font-size:13px !important; font-weight:600 !important; color:#9aa3b0 !important; line-height:1.5;
    }
    ul.woocommerce-order-overview.order_details li:last-child{ border-bottom:0 !important; }
    ul.woocommerce-order-overview.order_details li strong{
        color:#0d1117 !important; font-weight:800 !important; font-size:13.5px !important;
        text-align:right; max-width:64%; word-break:break-word; line-height:1.45;
    }
    </style>
    <?php
}

// Cap the order-review item list height so long carts scroll (totals/payment stay visible).
add_action( 'wp_head', 'shopys_order_review_scroll' );
function shopys_order_review_scroll() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    // Skip the pay-for-order page (3-column table) and the received page — flex rows
    // there break column alignment; only the main /checkout/ review needs scrolling.
    if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) return;
    ?>
    <style>
    body.woocommerce-checkout #order_review table.shop_table thead,
    body.woocommerce-checkout #order_review table.shop_table tbody,
    body.woocommerce-checkout #order_review table.shop_table tfoot{ display:block !important; width:100% !important; }
    body.woocommerce-checkout #order_review table.shop_table thead tr,
    body.woocommerce-checkout #order_review table.shop_table tbody tr,
    body.woocommerce-checkout #order_review table.shop_table tfoot tr{ display:flex !important; justify-content:space-between; align-items:flex-start; gap:12px; width:100%; }
    body.woocommerce-checkout #order_review table.shop_table th:last-child,
    body.woocommerce-checkout #order_review table.shop_table td:last-child{ text-align:right !important; flex-shrink:0; white-space:nowrap; }
    /* The product list scrolls; everything below (totals, coupon, payment) stays put */
    body.woocommerce-checkout #order_review table.shop_table tbody{ max-height:300px; overflow-y:auto; overscroll-behavior:contain; }
    body.woocommerce-checkout #order_review table.shop_table tbody::-webkit-scrollbar{ width:6px; }
    body.woocommerce-checkout #order_review table.shop_table tbody::-webkit-scrollbar-thumb{ background:#cdd3db; border-radius:3px; }
    body.woocommerce-checkout #order_review table.shop_table tbody::-webkit-scrollbar-track{ background:transparent; }
    </style>
    <?php
}

/* ── Move the coupon entry into the order summary, below the total ──────────── */
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

add_action( 'woocommerce_review_order_before_payment', 'shopys_checkout_coupon_field' );
function shopys_checkout_coupon_field() {
    if ( ! function_exists( 'wc_coupons_enabled' ) || ! wc_coupons_enabled() ) return;
    ?>
    <div class="shopys-coupon">
        <button type="button" class="shopys-coupon-toggle">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a2 2 0 0 1 .703 3.872l-1.71.62a2 2 0 0 0-1.193 1.193l-.62 1.71A2 2 0 0 1 12 20a2 2 0 0 1-3.872.703l-.62-1.71a2 2 0 0 0-1.193-1.193l-1.71-.62A2 2 0 0 1 4 12"/><path d="M9 10h.01M15 10h.01"/></svg>
            <?php esc_html_e( 'Have a coupon?', 'shopys' ); ?> <span><?php esc_html_e( 'Click here to enter your code', 'shopys' ); ?></span>
        </button>
        <div class="shopys-coupon-box">
            <div class="shopys-coupon-row">
                <input type="text" class="shopys-coupon-input" placeholder="<?php esc_attr_e( 'Coupon code', 'shopys' ); ?>" autocomplete="off">
                <button type="button" class="shopys-coupon-apply"><?php esc_html_e( 'Apply', 'shopys' ); ?></button>
            </div>
            <div class="shopys-coupon-msg" aria-live="polite"></div>
        </div>
    </div>
    <?php
}

add_action( 'wp_ajax_shopys_apply_coupon', 'shopys_apply_coupon' );
add_action( 'wp_ajax_nopriv_shopys_apply_coupon', 'shopys_apply_coupon' );
function shopys_apply_coupon() {
    $code = isset( $_POST['code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['code'] ) ) : '';
    if ( $code === '' || ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json( array( 'ok' => false, 'msg' => __( 'Please enter a coupon code.', 'shopys' ) ) );
    }
    if ( WC()->cart->has_discount( $code ) ) {
        wp_send_json( array( 'ok' => false, 'msg' => __( 'Coupon already applied.', 'shopys' ) ) );
    }
    wc_clear_notices();
    $applied = WC()->cart->apply_coupon( $code );
    $errors  = wc_get_notices( 'error' );
    wc_clear_notices();
    if ( $applied ) {
        wp_send_json( array( 'ok' => true, 'msg' => __( 'Coupon applied ✓', 'shopys' ) ) );
    }
    $msg = $errors ? wp_strip_all_tags( $errors[0]['notice'] ) : __( 'Invalid coupon.', 'shopys' );
    wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
}

add_action( 'wp_footer', 'shopys_checkout_coupon_assets' );
function shopys_checkout_coupon_assets() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) return;
    $ajax = esc_url( admin_url( 'admin-ajax.php' ) );
    ?>
    <style>
    .shopys-coupon{ margin:14px 0 4px; padding-top:14px; border-top:1px dashed #e7e9ee; }
    .shopys-coupon-toggle{ display:inline-flex; align-items:center; gap:7px; background:none; border:none; cursor:pointer; padding:0;
        font-family:'Play','Battambang',-apple-system,sans-serif; font-size:13.5px; font-weight:700; color:#00a341; }
    .shopys-coupon-toggle span{ font-weight:600; color:#5b6472; text-decoration:underline; }
    .shopys-coupon-box{ display:none; margin-top:11px; }
    .shopys-coupon-box.open{ display:block; }
    .shopys-coupon-row{ display:flex; gap:8px; }
    .shopys-coupon-input{ flex:1; padding:11px 14px; border:1.5px solid #e7e9ee; border-radius:10px; font-size:14px; font-family:inherit; outline:none; background:#fff; }
    .shopys-coupon-input:focus{ border-color:#00c44f; box-shadow:0 0 0 3px rgba(0,196,79,.12); }
    .shopys-coupon-apply{ padding:11px 22px; border:none; border-radius:10px; background:#00c44f; color:#fff; font-weight:700; font-size:13.5px; cursor:pointer; font-family:inherit; transition:background .2s; white-space:nowrap; }
    .shopys-coupon-apply:hover{ background:#00a341; }
    .shopys-coupon-apply:disabled{ opacity:.6; cursor:default; }
    .shopys-coupon-msg{ font-size:12.5px; margin-top:8px; font-weight:600; }
    </style>
    <script>
    (function(){ if(!window.jQuery) return; var $=window.jQuery;
        $(document.body).on('click','.shopys-coupon-toggle',function(e){ e.preventDefault();
            $(this).siblings('.shopys-coupon-box').toggleClass('open'); });
        $(document.body).on('click','.shopys-coupon-apply',function(){
            var $b=$(this), $box=$b.closest('.shopys-coupon-box'), $msg=$box.find('.shopys-coupon-msg');
            var code=$.trim($box.find('.shopys-coupon-input').val());
            if(!code){ $msg.css('color','#d80019').text('Please enter a code.'); return; }
            $b.prop('disabled',true).text('…');
            $.post('<?php echo $ajax; ?>',{action:'shopys_apply_coupon',code:code},function(r){
                $msg.css('color', (r&&r.ok)?'#0a7d00':'#d80019').text(r?r.msg:'Error');
                if(r&&r.ok){ $(document.body).trigger('update_checkout'); }
            },'json').fail(function(){ $msg.css('color','#d80019').text('Error, please try again.'); })
            .always(function(){ $b.prop('disabled',false).text('Apply'); });
        });
    })();
    </script>
    <?php
}

// Make "Pay with KHQR" the default-selected method (first available gateway = default).
add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
    if ( isset( $gateways['khqrpay'] ) ) {
        $khqr = $gateways['khqrpay'];
        unset( $gateways['khqrpay'] );
        $gateways = array( 'khqrpay' => $khqr ) + $gateways;
    }
    return $gateways;
} );

// Premium card-style payment selector at checkout (no radio circles; click the whole card).
add_action( 'wp_footer', 'shopys_premium_payment_methods' );
function shopys_premium_payment_methods() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) return;
    ?>
    <style>
    /* High specificity to override the theme's checkout-premium.css */
    body.woocommerce-checkout #payment ul.wc_payment_methods{ background:transparent !important; border:0 !important; padding:0 !important; margin:0 0 18px !important; list-style:none !important; display:flex; flex-direction:column; gap:12px; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method{ position:relative; list-style:none !important; margin:0 !important; padding:16px 18px !important;
        background:#fff !important; border:1.6px solid #e7e9ee !important; border-radius:14px !important; cursor:pointer;
        transition:border-color .2s ease, box-shadow .2s ease, background .2s ease; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method:hover{ border-color:#bdeccd !important; background:#fff !important; box-shadow:0 6px 18px rgba(15,23,42,.06); }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method input[type=radio]{ position:absolute !important; opacity:0 !important; pointer-events:none !important; width:0; height:0; margin:0; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method > label{ display:block; margin:0 !important; padding:0 !important; cursor:pointer;
        font-family:'Play','Battambang',-apple-system,sans-serif; font-weight:800 !important; font-size:15px !important; color:#0d1117 !important; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method > label img{ display:none !important; }
    /* Selected — strong, unmistakable highlight (full border on all sides, incl. last-child) */
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method.pm-selected{ border:2.5px solid #00c44f !important; background:#eafff2 !important;
        box-shadow:0 0 0 3px rgba(0,196,79,.18), 0 12px 26px rgba(0,196,79,.2); padding-right:54px !important; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method.pm-selected > label{ color:#067a3b !important; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method.pm-selected::after{ content:''; position:absolute; top:15px; right:15px; width:28px; height:28px; border-radius:50%;
        background:#00c44f url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='17' height='17' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E") center/17px no-repeat;
        box-shadow:0 4px 10px rgba(0,196,79,.5); }
    /* Dim the non-selected options so the choice is obvious */
    body.woocommerce-checkout #payment ul.wc_payment_methods:has(.pm-selected) li.wc_payment_method:not(.pm-selected){ opacity:.62; }
    body.woocommerce-checkout #payment ul.wc_payment_methods li.wc_payment_method:not(.pm-selected):hover{ opacity:1; }
    /* Strip the inner grey description box (the KHQR card supplies its own styling) */
    body.woocommerce-checkout #payment .payment_box{ background:transparent !important; border:0 !important; margin:10px 0 0 !important; padding:0 !important; }
    body.woocommerce-checkout #payment .payment_box::before{ display:none !important; }
    /* Remove the now-empty privacy text + its leftover card */
    body.woocommerce-checkout .woocommerce-privacy-policy-text{ display:none !important; }
    body.woocommerce-checkout .woocommerce-terms-and-conditions-wrapper{ background:transparent !important; border:0 !important; padding:0 !important; margin:0 !important; }
    </style>
    <script>
    (function(){
        if(!window.jQuery) return; var $=window.jQuery;
        function mark(){
            $("ul.wc_payment_methods li.wc_payment_method").removeClass("pm-selected");
            $("ul.wc_payment_methods input[name=payment_method]:checked").closest("li.wc_payment_method").addClass("pm-selected");
        }
        $(document.body).on("click","ul.wc_payment_methods li.wc_payment_method",function(e){
            if($(e.target).closest("a, input").length) return;
            var $r=$(this).find("input[type=radio]");
            if(!$r.prop("checked")){ $r.prop("checked",true).trigger("click"); }
        });
        $(document.body).on("change","input[name=payment_method]",mark);
        $(document.body).on("updated_checkout payment_method_selected",mark);
        $(function(){ setTimeout(mark,300); });
    })();
    </script>
    <?php
}

// Align the Print / Download Invoice buttons into a premium row on the order page.
add_action( 'wp_head', 'shopys_invoice_buttons_style' );
function shopys_invoice_buttons_style() {
    if ( ! function_exists( 'is_account_page' ) ) return;
    if ( ! is_account_page() && ! ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) ) return;
    ?>
    <style>
    /* Print / Download Invoice buttons (print-invoices plugin) → one premium row */
    a[class*="wt_pklist_"]{
        display:inline-flex !important; align-items:center; gap:8px; vertical-align:middle;
        width:auto !important; min-height:0 !important;
        margin:16px 10px 0 0 !important; padding:11px 22px !important;
        border-radius:10px !important; font-weight:700 !important; font-size:13px !important;
        line-height:1.2 !important; text-decoration:none !important;
        background:#00c44f !important; color:#fff !important; border:1.5px solid #00c44f !important;
        box-shadow:0 6px 18px rgba(0,196,79,.28); transition:transform .2s ease,box-shadow .2s ease,background .2s ease;
    }
    a[class*="wt_pklist_"]:hover{ background:#00a341 !important; border-color:#00a341 !important; transform:translateY(-2px); box-shadow:0 10px 26px rgba(0,196,79,.38); color:#fff !important; }
    /* Remove the <br><br> the plugin prints after each button so they stay on one row */
    a[class*="wt_pklist_"] + br,
    a[class*="wt_pklist_"] + br + br{ display:none !important; }
    /* Download = outline pill at rest, but fills green on hover just like Print */
    a[class*="_download"]{ background:#fff !important; color:#0a7d00 !important; box-shadow:none !important; }
    a[class*="_download"]:hover{ background:#00a341 !important; border-color:#00a341 !important; color:#fff !important; transform:translateY(-2px); box-shadow:0 10px 26px rgba(0,196,79,.38) !important; }
    </style>
    <?php
}

/* ── Telegram: push order details to the shop channel when an order is paid ──── */
function shopys_tg_order_chat_id() {
    if ( defined( 'SHOPYS_TG_ORDER_CHAT_ID' ) ) return (string) SHOPYS_TG_ORDER_CHAT_ID;
    $v = getenv( 'SHOPYS_TG_ORDER_CHAT_ID' );
    return $v !== false ? (string) $v : '';
}

/** Separate channel for Walk-In Customer (COD) orders; falls back to the main one. */
function shopys_tg_walkin_chat_id() {
    if ( defined( 'SHOPYS_TG_WALKIN_CHAT_ID' ) ) return (string) SHOPYS_TG_WALKIN_CHAT_ID;
    $v = getenv( 'SHOPYS_TG_WALKIN_CHAT_ID' );
    return $v !== false ? (string) $v : '';
}

/** Route an order to the right Telegram channel by payment method. */
function shopys_tg_chat_for_order( $order ) {
    if ( $order && $order->get_payment_method() !== 'khqrpay' ) {
        $walkin = shopys_tg_walkin_chat_id();
        if ( $walkin !== '' ) return $walkin;
    }
    return shopys_tg_order_chat_id();
}

add_action( 'woocommerce_payment_complete', 'shopys_notify_telegram_paid_order', 20 );
// Walk-In Customer (COD) and other gateways don't fire payment_complete — notify on
// the status transition instead. The _shopys_tg_notified guard prevents duplicates.
add_action( 'woocommerce_order_status_processing', 'shopys_notify_telegram_paid_order', 20 );
add_action( 'woocommerce_order_status_completed', 'shopys_notify_telegram_paid_order', 20 );
add_action( 'woocommerce_order_status_on-hold', 'shopys_notify_telegram_paid_order', 20 );
function shopys_notify_telegram_paid_order( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_shopys_tg_notified' ) === 'yes' ) return;

    $token = defined( 'SHOPYS_TG_BOT_TOKEN' ) ? SHOPYS_TG_BOT_TOKEN : '';
    $chat  = shopys_tg_chat_for_order( $order ); // KHQR → purchase channel, Walk-In → walk-in channel
    if ( ! $token || ! $chat ) return;

    $name  = trim( $order->get_formatted_billing_full_name() );
    if ( $name === '' ) $name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
    $phone = $order->get_billing_phone();
    $addr  = $order->get_shipping_address_1() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();
    $addr  = trim( html_entity_decode( wp_strip_all_tags( str_replace( array( '<br/>', '<br>', '<br />' ), ', ', (string) $addr ) ) ) );

    // Clean, locale-proof money formatter ($ for USD, ៛ for KHR).
    $cur   = $order->get_currency();
    $money = function ( $v ) use ( $cur ) {
        $v = (float) $v;
        return $cur === 'KHR' ? number_format( $v, 0 ) . ' ៛' : '$' . number_format( $v, 2 );
    };

    $lines = array();
    foreach ( $order->get_items() as $item ) {
        $lines[] = '   • ' . esc_html( $item->get_name() ) . '  <b>×' . $item->get_quantity() . '</b>  —  ' . esc_html( $money( $item->get_total() ) );
    }

    // Split payment method + receiver cleanly for KHQR; generic title otherwise.
    if ( $order->get_payment_method() === 'khqrpay' ) {
        $pay_method = 'KHQR (Bakong)';
        $recv_name  = function_exists( 'khqrpay_merchant_name' ) ? khqrpay_merchant_name() : '';
        $recv_acct  = function_exists( 'khqrpay_cfg' ) ? ( khqrpay_cfg( 'khqr_account_number' ) ?: khqrpay_cfg( 'bakong_id' ) ) : '';
    } else {
        $pay_method = $order->get_payment_method_title();
        $recv_name  = '';
        $recv_acct  = '';
    }
    $when = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y · g:i A' ) : date_i18n( 'd M Y · g:i A' );
    $div  = "━━━━━━━━━━━━━━━";

    $header = ( $order->get_payment_method() === 'khqrpay' ) ? '🛍️  <b>NEW PAID ORDER</b>' : '🛒  <b>NEW ORDER</b>';
    $msg  = $header . "\n";
    $msg .= "<i>" . esc_html( get_bloginfo( 'name' ) ) . "</i>\n";
    $msg .= $div . "\n";
    $msg .= "🧾  <b>Invoice</b>\n      <code>#" . esc_html( $order->get_order_number() ) . "</code>\n\n";
    $msg .= "👤  <b>Customer</b>\n      " . esc_html( $name !== '' ? $name : '-' ) . "\n";
    $msg .= "📞  " . esc_html( $phone !== '' ? $phone : '-' ) . "\n";
    $msg .= "📍  " . esc_html( $addr !== '' ? $addr : '-' ) . "\n";
    $wp_user = $order->get_user();
    if ( $wp_user ) {
        $msg .= "🧑‍💻  <b>Website Account:</b>\n";
        $msg .= "      Name : " . esc_html( $wp_user->display_name ) . "\n";
        if ( $wp_user->user_email ) {
            $msg .= "      Email : " . esc_html( $wp_user->user_email ) . "\n";
        }
    } else {
        $msg .= "🧑‍💻  <b>Website Account:</b> " . esc_html__( 'Guest (not logged in)', 'shopys' ) . "\n";
    }
    $msg .= $div . "\n";
    $msg .= "📦  <b>Order Items</b>\n" . implode( "\n", $lines ) . "\n";
    $msg .= $div . "\n";
    $msg .= "💳  <b>Payment:</b> " . esc_html( $pay_method ) . "\n";
    if ( $recv_name !== '' || $recv_acct !== '' ) {
        $msg .= "🏦  <b>Received by:</b> " . esc_html( trim( $recv_name . ( $recv_acct !== '' ? ' · ' . $recv_acct : '' ) ) ) . "\n";
    }
    $msg .= "💰  <b>TOTAL:</b>  <b>" . esc_html( $money( $order->get_total() ) ) . "</b>\n";
    $msg .= $div . "\n";
    $msg .= "🕒  <i>" . esc_html( $when ) . "</i>";

    $resp = wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", array(
        'timeout' => 15,
        'body'    => array(
            'chat_id'                  => $chat,
            'text'                     => $msg,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ),
    ) );
    if ( ! is_wp_error( $resp ) && (int) wp_remote_retrieve_response_code( $resp ) === 200 ) {
        $order->update_meta_data( '_shopys_tg_notified', 'yes' );
        $order->save();
    } else {
        error_log( 'shopys TG order notify failed: ' . ( is_wp_error( $resp ) ? $resp->get_error_message() : wp_remote_retrieve_body( $resp ) ) );
    }
}

function shopys_product_grid_assets() {
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style(
            'shopys-product-grid',
            get_stylesheet_directory_uri() . '/css/product-grid.css',
            array(),
            filemtime( get_stylesheet_directory() . '/css/product-grid.css' )
        );
        wp_enqueue_script(
            'shopys-product-grid-js',
            get_stylesheet_directory_uri() . '/js/product-grid.js',
            array(),
            filemtime( get_stylesheet_directory() . '/js/product-grid.js' ),
            true
        );
        wp_localize_script( 'shopys-product-grid-js', 'ppgParams', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ppg_ajax_nonce' ),
        ) );
        // Products-by-category CSS
        wp_enqueue_style(
            'shopys-product-by-category',
            get_stylesheet_directory_uri() . '/css/product-by-category.css',
            array( 'shopys-product-grid' ),
            filemtime( get_stylesheet_directory() . '/css/product-by-category.css' )
        );
        // Single product premium CSS
        if ( function_exists( 'is_product' ) && is_product() ) {
            wp_enqueue_style(
                'shopys-single-product',
                get_stylesheet_directory_uri() . '/css/single-product.css',
                array(),
                filemtime( get_stylesheet_directory() . '/css/single-product.css' )
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'shopys_product_grid_assets' );

/**********************/
// Force custom single product template
/**********************/
function shopys_force_single_product_template( $template ) {
    if ( function_exists( 'is_product' ) && is_product() ) {
        $custom = get_stylesheet_directory() . '/single-product.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
add_filter( 'template_include', 'shopys_force_single_product_template', 99 );

/**********************/
// Advanced Product Search
/**********************/
require_once get_stylesheet_directory() . '/inc/advanced-search.php';

function shopys_advanced_search_assets() {
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style(
            'shopys-advanced-search',
            get_stylesheet_directory_uri() . '/css/advanced-search.css',
            array(),
            filemtime( get_stylesheet_directory() . '/css/advanced-search.css' )
        );
        wp_enqueue_script(
            'shopys-advanced-search-js',
            get_stylesheet_directory_uri() . '/js/advanced-search.js',
            array(),
            filemtime( get_stylesheet_directory() . '/js/advanced-search.js' ),
            true
        );
        wp_localize_script( 'shopys-advanced-search-js', 'shopys_search_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'shopys_advanced_search_assets' );

/**********************/
// Force custom search template for product search
/**********************/
function shopys_force_product_search_template( $template ) {
    if ( is_search() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'product' ) {
        $custom = get_stylesheet_directory() . '/search.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
add_filter( 'template_include', 'shopys_force_product_search_template', 999 );

/**********************/
// Force custom taxonomy template for WooCommerce product categories
/**********************/
function shopys_force_product_cat_template( $template ) {
    if ( is_tax( 'product_cat' ) ) {
        $custom = get_stylesheet_directory() . '/taxonomy-product_cat.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
add_filter( 'template_include', 'shopys_force_product_cat_template', 999 );

/**********************/
// Force custom taxonomy template for WooCommerce product brands
/**********************/
function shopys_force_product_brand_template( $template ) {
    if ( is_tax( 'product_brand' ) ) {
        $custom = get_stylesheet_directory() . '/taxonomy-product_brand.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
add_filter( 'template_include', 'shopys_force_product_brand_template', 999 );

/**********************/
// Premium Announcement Banner (above search area)
/**********************/
function shopys_announcement_banner() {
    $enabled = get_option( 'shopys_announcement_enabled', '1' );
    if ( ! $enabled ) {
        return;
    }

    $text = get_option( 'shopys_announcement_text', 'No Thai Products Here' );
    $badge = get_option( 'shopys_announcement_badge', 'Notice' );
    ?>
    <div class="shopys-announcement-bar">
        <div class="shopys-ann-inner">
            <span class="shopys-ann-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <span class="shopys-ann-text"><?php echo esc_html( $text ); ?></span>
            <span class="shopys-ann-badge"><?php echo esc_html( $badge ); ?></span>
        </div>
    </div>
    <style>
    .shopys-announcement-bar {
        background: linear-gradient(135deg, #13e800 0%, #0fb500 100%);
        padding: 0;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(19,232,0,0.2);
    }
    .shopys-announcement-bar::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -5%;
        width: 140px;
        height: 140px;
        background: rgba(255,255,255,0.07);
        border-radius: 50%;
        pointer-events: none;
    }
    .shopys-ann-inner {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 6px 16px;
        max-width: 1400px;
        margin: 0 auto;
    }
    .shopys-ann-icon {
        display: flex;
        align-items: center;
        color: #fff;
        opacity: 0.9;
        flex-shrink: 0;
    }
    .shopys-ann-text {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.3px;
    }
    .shopys-ann-badge {
        background: rgb(255,193,212);
        color: #d40040;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        border: 1px solid rgb(255,173,198);
    }
    </style>
    <?php
}
add_action( 'open_shop_below_header', 'shopys_announcement_banner', 1 );

// Disable fallback to "show all pages" when no menu is assigned to a location
add_filter( 'wp_nav_menu_args', function( $args ) {
    $args['fallback_cb'] = false;
    return $args;
} );

// Include Product Details admin page
require_once get_stylesheet_directory() . '/inc/product-details.php';

// Hide New Arrivals from all nav menus
add_filter( 'wp_nav_menu_objects', function( $items ) {
    foreach ( $items as $key => $item ) {
        $slug = $item->object === 'page'
            ? get_post_field( 'post_name', $item->object_id )
            : $item->post_name;
        if ( $slug === 'new-arrivals' ) {
            unset( $items[ $key ] );
        }
    }
    return $items;
} );

// ── NEW ARRIVALS virtual route ────────────────────────────────────────────
// Registers /new-arrivals/ as a virtual URL — no WordPress page required.
// Works on any environment (dev, staging, production) without DB setup.

add_action( 'init', 'shopys_new_arrivals_rewrite' );
function shopys_new_arrivals_rewrite() {
    add_rewrite_rule( '^new-arrivals/?$', 'index.php?shopys_new_arrivals=1', 'top' );
}

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'shopys_new_arrivals';
    return $vars;
} );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'shopys_new_arrivals' ) ) {
        $template = get_stylesheet_directory() . '/page-new-arrivals.php';
        if ( file_exists( $template ) ) {
            include $template;
            exit;
        }
    }
} );

// Flush rewrite rules once after theme switch so the route is registered
add_action( 'after_switch_theme', function() {
    shopys_new_arrivals_rewrite();
    flush_rewrite_rules();
} );

// ── CUSTOM DASHBOARD ROUTE /dashboard/ ───────────────────────────────────────
add_action( 'init', function() {
    add_rewrite_rule( '^dashboard/?$', 'index.php?shopys_dashboard=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'shopys_dashboard';
    return $vars;
} );

add_action( 'template_redirect', function() {
    if ( ! get_query_var( 'shopys_dashboard' ) ) return;

    // Must be logged in to view the dashboard
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/vstore-admin/' ) );
        exit;
    }

    $template = get_stylesheet_directory() . '/page-dashboard.php';
    if ( file_exists( $template ) ) {
        include $template;
        exit;
    }
}, 1 );

// ── SECRET ADMIN LOGIN ROUTE /vstore-admin/ ──────────────────────────────
add_action( 'init', function() {
    add_rewrite_rule( '^vstore-admin/?$', 'index.php?shopys_admin_login=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'shopys_admin_login';
    return $vars;
} );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'shopys_admin_login' ) ) {
        $template = get_stylesheet_directory() . '/page-vstore-admin.php';
        if ( file_exists( $template ) ) {
            include $template;
            exit;
        }
    }
}, 1 );

// Redirect all wp-login.php and wp-admin unauthenticated access to /vstore-admin/
add_filter( 'login_url', function( $login_url, $redirect ) {
    // Allow logout to work normally
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'logout', 'lostpassword', 'rp', 'resetpass' ) ) ) {
        return $login_url;
    }
    return home_url( '/vstore-admin/' );
}, 10, 2 );

add_action( 'auth_redirect', function() {
    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url( '/vstore-admin/' ) );
        exit;
    }
} );

// Intercept direct wp-login.php access and redirect to /vstore-admin/
add_action( 'init', function() {
    if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
        if ( ! in_array( $action, array( 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass' ) ) && ! isset( $_POST['log'] ) ) {
            wp_redirect( home_url( '/vstore-admin/' ) );
            exit;
        }
    }
} );

// ── FLUSH REWRITE RULES ───────────────────────────────────────────────────
// Flush rewrite rules whenever the registered rules don't include our route
add_action( 'init', function() {
    $rules = get_option( 'rewrite_rules' );
    if ( empty( $rules ) || ! isset( $rules['^new-arrivals/?$'] ) || ! isset( $rules['^vstore-admin/?$'] ) || ! isset( $rules['^dashboard/?$'] ) ) {
        flush_rewrite_rules();
    }
}, 99 );

// ── OPEN GRAPH / DEEP LINK PREVIEWS ──────────────────────────────────────
// Injects OG + Twitter Card meta into <head> on product pages so that
// pasting a product URL in Telegram, Messenger, Facebook etc. shows a
// rich preview with product image, name, price and description.
add_action( 'wp_head', function() {
    if ( ! is_singular( 'product' ) ) {
        return;
    }

    $product = wc_get_product( get_the_ID() );
    if ( ! $product ) {
        return;
    }

    // --- collect values ---
    $title       = wp_strip_all_tags( $product->get_name() );
    $url         = get_permalink();
    $site_name   = get_bloginfo( 'name' );
    $currency    = get_woocommerce_currency();

    // Description: short desc → full desc → product name fallback
    $desc = wp_strip_all_tags( $product->get_short_description() );
    if ( empty( $desc ) ) {
        $desc = wp_strip_all_tags( $product->get_description() );
    }
    if ( empty( $desc ) ) {
        $desc = $title;
    }
    $desc = wp_trim_words( $desc, 30 );

    // Price
    $price = $product->get_price();
    $price_html = $price ? wc_format_decimal( $price, 2 ) : '';

    // Image — full size for best quality, fallback to placeholder
    $image_id   = $product->get_image_id();
    $image_data = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : null;
    $image_url  = $image_data ? $image_data[0] : wc_placeholder_img_src( 'full' );
    $image_w    = $image_data ? $image_data[1] : 800;
    $image_h    = $image_data ? $image_data[2] : 800;
    $image_alt  = $image_id ? trim( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) : $title;
    if ( empty( $image_alt ) ) $image_alt = $title;

    // --- output ---
    ?>
<!-- Open Graph / Deep Link Preview -->
<meta property="og:type"        content="product" />
<meta property="og:locale"      content="en_US" />
<meta property="og:site_name"   content="<?php echo esc_attr( $site_name ); ?>" />
<meta property="og:url"         content="<?php echo esc_url( $url ); ?>" />
<meta property="og:title"       content="<?php echo esc_attr( $title ); ?>" />
<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>" />
<?php if ( $image_url ) : ?>
<meta property="og:image"             content="<?php echo esc_url( $image_url ); ?>" />
<meta property="og:image:secure_url"  content="<?php echo esc_url( $image_url ); ?>" />
<meta property="og:image:width"       content="<?php echo esc_attr( $image_w ); ?>" />
<meta property="og:image:height"      content="<?php echo esc_attr( $image_h ); ?>" />
<meta property="og:image:alt"         content="<?php echo esc_attr( $image_alt ); ?>" />
<meta property="og:image:type"        content="image/jpeg" />
<?php endif; ?>
<?php if ( $price_html ) : ?>
<meta property="product:price:amount"   content="<?php echo esc_attr( $price_html ); ?>" />
<meta property="product:price:currency" content="<?php echo esc_attr( $currency ); ?>" />
<?php endif; ?>
<!-- Twitter / Telegram card -->
<meta name="twitter:card"        content="summary_large_image" />
<meta name="twitter:title"       content="<?php echo esc_attr( $title ); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr( $desc ); ?>" />
<?php if ( $image_url ) : ?>
<meta name="twitter:image"       content="<?php echo esc_url( $image_url ); ?>" />
<meta name="twitter:image:alt"   content="<?php echo esc_attr( $image_alt ); ?>" />
<?php endif; ?>
    <?php
}, 1 );


/* ═══════════════════════════════════════════════════════════════════
   USER LOGIN TRACKING — records last login time + IP to user meta
   ═══════════════════════════════════════════════════════════════════ */

add_action( 'wp_login', 'shopys_track_user_login', 10, 2 );
function shopys_track_user_login( $user_login, $user ) {
    $now = current_time( 'mysql' );
    $ip  = '';
    foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $h ) {
        if ( ! empty( $_SERVER[ $h ] ) ) {
            $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) break;
            $ip = '';
        }
    }
    update_user_meta( $user->ID, 'shopys_last_login',    $now );
    update_user_meta( $user->ID, 'shopys_last_login_ip', $ip  );
}

/* ═══════════════════════════════════════════════════════════════════
   ADD-TO-CART LOGIN GATE
   Guests must log in before adding to cart. Logged-in users add normally.
   ═══════════════════════════════════════════════════════════════════ */

// Server-side safety net — blocks guests even if JS is bypassed.
add_filter( 'woocommerce_add_to_cart_validation', 'shopys_require_login_to_add_cart', 10, 2 );
function shopys_require_login_to_add_cart( $passed, $product_id ) {
    if ( ! is_user_logged_in() ) {
        wc_add_notice( __( 'Please log in or create an account to add products to your cart.', 'shopys' ), 'error' );
        return false;
    }
    return $passed;
}

// Premium login dialog + click interception (only output for guests).
/* ── Premium floating cart button (links to the [cart_summary] page) ─────── */
// Ensure the dedicated Cart page exists (auto-creates on any environment, e.g. prod
// where the DB isn't deployed). Idempotent + keeps it out of nav menus.
add_action( 'init', 'shopys_ensure_cart_page' );
function shopys_ensure_cart_page() {
    $pid = (int) get_option( 'shopys_cart_page_id' );
    if ( $pid && get_post_status( $pid ) === 'publish' ) return;

    // Reuse an existing cart_summary page if one already exists.
    foreach ( array( 'my-cart', 'cart' ) as $slug ) {
        $p = get_page_by_path( $slug );
        if ( $p && has_shortcode( (string) $p->post_content, 'cart_summary' ) ) {
            update_option( 'shopys_cart_page_id', (int) $p->ID );
            delete_transient( 'shopys_cart_page_url' );
            return;
        }
    }

    $slug = get_page_by_path( 'cart' ) ? 'my-cart' : 'cart';
    $new  = wp_insert_post( array(
        'post_title'     => 'Cart',
        'post_name'      => $slug,
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'post_content'   => '[cart_summary title="Your Cart"]',
        'comment_status' => 'closed',
    ) );
    if ( $new && ! is_wp_error( $new ) ) {
        update_option( 'shopys_cart_page_id', (int) $new );
        delete_transient( 'shopys_cart_page_url' );
        // Keep it out of any auto-add nav menus.
        foreach ( wp_get_nav_menus() as $m ) {
            foreach ( (array) wp_get_nav_menu_items( $m->term_id ) as $it ) {
                if ( (int) $it->object_id === (int) $new && $it->object === 'page' ) {
                    wp_delete_post( $it->ID, true );
                }
            }
        }
    }
}

function shopys_cart_summary_page_url() {
    $cached = get_transient( 'shopys_cart_page_url' );
    if ( $cached ) return $cached;

    // Prefer the dedicated cart page created for the floating button.
    $pid = (int) get_option( 'shopys_cart_page_id' );
    if ( $pid && get_post_status( $pid ) === 'publish' ) {
        $url = get_permalink( $pid );
    } else {
        $url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
    }
    set_transient( 'shopys_cart_page_url', $url, DAY_IN_SECONDS );
    return $url;
}

add_action( 'wp_ajax_shopys_cart_count', 'shopys_cart_count_ajax' );
add_action( 'wp_ajax_nopriv_shopys_cart_count', 'shopys_cart_count_ajax' );
function shopys_cart_count_ajax() {
    $count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
    wp_send_json( array( 'count' => (int) $count ) );
}

add_action( 'wp_footer', 'shopys_floating_cart_button', 55 );
function shopys_floating_cart_button() {
    if ( is_admin() || ! function_exists( 'WC' ) ) return;
    $url   = esc_url( shopys_cart_summary_page_url() );
    $count = ( WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
    $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
    ?>
    <a id="shopys-cart-fab" href="<?php echo $url; ?>" aria-label="<?php esc_attr_e( 'View cart', 'shopys' ); ?>">
        <span class="scf-ic">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6 7h12l-1 12.2a2 2 0 0 1-2 1.8H9a2 2 0 0 1-2-1.8L6 7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M9 7V6a3 3 0 0 1 6 0v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="scf-badge"<?php echo $count > 0 ? '' : ' hidden'; ?>><?php echo $count; ?></span>
        <span class="scf-tip"><?php esc_html_e( 'View Cart', 'shopys' ); ?></span>
    </a>
    <style>
    #shopys-cart-fab{position:fixed;left:22px;bottom:24px;z-index:99990;width:58px;height:58px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#00c44f;color:#fff;text-decoration:none;box-shadow:0 4px 20px rgba(0,196,79,.4);border:none;transition:transform .25s ease,box-shadow .25s ease,background .25s ease;}
    #shopys-cart-fab:hover{transform:scale(1.08);background:#00a341;box-shadow:0 6px 28px rgba(0,196,79,.5);}
    #shopys-cart-fab:active{transform:translateY(-1px);}
    #shopys-cart-fab .scf-ic{display:flex;}
    #shopys-cart-fab svg{width:26px;height:26px;display:block;}
    #shopys-cart-fab .scf-badge{position:absolute;top:-5px;right:-5px;min-width:22px;height:22px;padding:0 6px;border-radius:11px;background:linear-gradient(135deg,#ff4d4d,#d80019);color:#fff;font:700 12px/22px 'Play','Battambang',sans-serif;text-align:center;border:2px solid #fff;box-shadow:0 4px 10px rgba(216,0,25,.5);}
    #shopys-cart-fab .scf-badge[hidden]{display:none;}
    #shopys-cart-fab.scf-has .scf-ic{animation:scfpop .35s ease;}
    @keyframes scfpop{0%{transform:scale(1)}40%{transform:scale(1.22)}100%{transform:scale(1)}}
    #shopys-cart-fab .scf-tip{position:absolute;left:70px;white-space:nowrap;background:#0d1117;color:#fff;font:600 12.5px/1 'Play','Battambang',sans-serif;padding:9px 13px;border-radius:9px;opacity:0;pointer-events:none;transform:translateX(-6px);transition:opacity .2s ease,transform .2s ease;}
    #shopys-cart-fab:hover .scf-tip{opacity:1;transform:none;}
    #shopys-cart-fab .scf-tip:before{content:'';position:absolute;left:-5px;top:50%;transform:translateY(-50%);border:5px solid transparent;border-right-color:#0d1117;}
    @media(max-width:600px){#shopys-cart-fab{width:52px;height:52px;left:16px;bottom:20px;}#shopys-cart-fab svg{width:24px;height:24px;}#shopys-cart-fab .scf-tip{display:none;}}
    </style>
    <script>
    (function(){
        var fab = document.getElementById('shopys-cart-fab'); if(!fab) return;
        var badge = fab.querySelector('.scf-badge');
        function set(n){ n=parseInt(n||0,10);
            if(n>0){ badge.textContent=n; badge.hidden=false; fab.classList.add('scf-has'); setTimeout(function(){fab.classList.remove('scf-has');},400); }
            else { badge.hidden=true; }
        }
        function refresh(){ fetch('<?php echo $ajax; ?>?action=shopys_cart_count',{credentials:'same-origin'})
            .then(function(r){return r.json();}).then(function(d){ set(d.count); }).catch(function(){}); }
        refresh();
        if(window.jQuery){ jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals wc_fragments_refreshed', refresh); }
        document.body.addEventListener('added_to_cart', refresh);
        window.addEventListener('pageshow', function(e){ if(e.persisted) refresh(); });
    })();
    </script>
    <?php
}

add_action( 'wp_footer', 'shopys_add_to_cart_login_dialog', 60 );
function shopys_add_to_cart_login_dialog() {
    if ( is_user_logged_in() || ! function_exists( 'WC' ) ) return;

    $account_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
    if ( ! $account_url ) $account_url = home_url( '/my-account/' );
    $login_url    = add_query_arg( 'action', 'login', $account_url ) . '#customer_login';
    $register_url = add_query_arg( 'action', 'register', $account_url ) . '#register';
    ?>
    <style>
    .satc-overlay{position:fixed;inset:0;z-index:2147483600;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(13,17,23,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);opacity:0;visibility:hidden;transition:opacity .22s ease,visibility .22s ease;}
    .satc-overlay.satc-open{opacity:1;visibility:visible;}
    .satc-modal{position:relative;width:100%;max-width:380px;background:#fff;border-radius:20px;padding:30px 26px 26px;box-shadow:0 30px 70px rgba(0,0,0,.3);text-align:center;transform:translateY(14px) scale(.97);transition:transform .25s cubic-bezier(.2,.7,.3,1);font-family:'Play','Battambang',-apple-system,sans-serif;}
    .satc-overlay.satc-open .satc-modal{transform:none;}
    .satc-close{position:absolute;top:14px;right:14px;width:30px;height:30px;border:none;background:#f1f5f9;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:background .2s;}
    .satc-close:hover{background:#e2e8f0;color:#1a1a2e;}
    .satc-badge{width:64px;height:64px;margin:0 auto 16px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#13e800,#0fb500);box-shadow:0 10px 26px rgba(19,232,0,.4);}
    .satc-title{font-size:20px;font-weight:800;color:#0d1117;margin:0 0 8px;letter-spacing:-.3px;}
    .satc-text{font-size:14px;color:#64748b;line-height:1.6;margin:0 0 22px;}
    .satc-actions{display:flex;flex-direction:column;gap:10px;}
    .satc-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 20px;border-radius:11px;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer;transition:transform .15s ease,box-shadow .2s ease,background .2s ease;}
    .satc-btn-primary{background:linear-gradient(135deg,#13e800,#0fb500);color:#000;box-shadow:0 8px 22px rgba(19,232,0,.35);}
    .satc-btn-primary:hover{transform:translateY(-2px);color:#000;box-shadow:0 12px 28px rgba(19,232,0,.45);}
    .satc-btn-ghost{background:#fff;color:#0d1117;border:1.5px solid #e2e8f0;}
    .satc-btn-ghost:hover{border-color:#13e800;color:#0a7d00;}
    </style>

    <div class="satc-overlay" id="satc-login-gate" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Login required', 'shopys' ); ?>" hidden>
        <div class="satc-modal">
            <button class="satc-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'shopys' ); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="satc-badge">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <h3 class="satc-title"><?php esc_html_e( 'Log in to add to cart', 'shopys' ); ?></h3>
            <p class="satc-text"><?php esc_html_e( 'Please log in or create an account to add products to your cart and build your PC.', 'shopys' ); ?></p>
            <div class="satc-actions">
                <a class="satc-btn satc-btn-primary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log In', 'shopys' ); ?></a>
                <a class="satc-btn satc-btn-ghost" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create Account', 'shopys' ); ?></a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var gate = document.getElementById('satc-login-gate');
        if (!gate) return;
        var SEL = '.add_to_cart_button, .single_add_to_cart_button, .ajax_add_to_cart, .ppg-qv-add, button[name="add-to-cart"]';
        function openGate() { gate.hidden = false; requestAnimationFrame(function(){ gate.classList.add('satc-open'); }); document.body.style.overflow = 'hidden'; }
        function closeGate() { gate.classList.remove('satc-open'); document.body.style.overflow = ''; setTimeout(function(){ gate.hidden = true; }, 220); }
        // Intercept add-to-cart clicks in the capture phase so WooCommerce's own handler never runs.
        document.addEventListener('click', function (e) {
            if (e.target.closest(SEL)) { e.preventDefault(); e.stopImmediatePropagation(); openGate(); return; }
            if (e.target.closest('.satc-close') || e.target === gate) { closeGate(); }
        }, true);
        // Block add-to-cart form submits (single product page, Enter key)
        document.addEventListener('submit', function (e) {
            if (e.target.closest && e.target.closest('form.cart')) { e.preventDefault(); e.stopImmediatePropagation(); openGate(); }
        }, true);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && gate.classList.contains('satc-open')) closeGate(); });
    })();
    </script>
    <?php
}