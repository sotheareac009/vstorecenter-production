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
// Charge $2 as a fee when "Delivery" is selected (Pick Up = free). A fee is used
// instead of shipping because the store has no shipping zones/methods configured,
// so WooCommerce would skip shipping calculation entirely.
add_action( 'woocommerce_cart_calculate_fees', 'shopys_delivery_fee' );
function shopys_delivery_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( shopys_get_delivery_option() === 'delivery' ) {
        $cart->add_fee( __( 'Delivery', 'shopys' ), 2 );
    }
}

// Resolve the currently selected delivery option (pickup | delivery). Default: delivery.
function shopys_get_delivery_option() {
    if ( isset( $_POST['delivery_option'] ) ) {
        $v = sanitize_key( wp_unslash( $_POST['delivery_option'] ) );
        if ( in_array( $v, array( 'pickup', 'delivery' ), true ) ) {
            if ( function_exists( 'WC' ) && WC()->session ) WC()->session->set( 'shopys_delivery_option', $v );
            return $v;
        }
    }
    if ( function_exists( 'WC' ) && WC()->session ) {
        $s = WC()->session->get( 'shopys_delivery_option' );
        if ( in_array( $s, array( 'pickup', 'delivery' ), true ) ) return $s;
    }
    return 'delivery';
}

// Capture the option from the checkout AJAX refresh so shipping recalculates correctly.
add_action( 'woocommerce_checkout_update_order_review', 'shopys_capture_delivery_option' );
function shopys_capture_delivery_option( $post_data ) {
    parse_str( (string) $post_data, $data );
    if ( isset( $data['delivery_option'] ) ) {
        $v = sanitize_key( $data['delivery_option'] );
        if ( in_array( $v, array( 'pickup', 'delivery' ), true ) && function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( 'shopys_delivery_option', $v );
        }
    }
}

// Default the radio to "delivery" (and remember the session choice).
add_filter( 'woocommerce_checkout_get_value', 'shopys_delivery_option_default', 10, 2 );
function shopys_delivery_option_default( $value, $input ) {
    if ( $input === 'delivery_option' && empty( $value ) ) {
        $s = ( function_exists( 'WC' ) && WC()->session ) ? WC()->session->get( 'shopys_delivery_option' ) : '';
        return in_array( $s, array( 'pickup', 'delivery' ), true ) ? $s : 'delivery';
    }
    return $value;
}

// Delivery Option styling + recalculate totals when the choice changes.
add_action( 'wp_footer', 'shopys_delivery_option_assets', 100 );
function shopys_delivery_option_assets() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) return;
    ?>
    <style>
    #delivery_option_field > label{ display:block; font-weight:700; color:#0d1117; margin-bottom:7px; }
    .shopys-delivery-option .woocommerce-input-wrapper{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .shopys-delivery-option input[type=radio]{ display:none; }
    .shopys-delivery-option label.radio{
        display:flex; align-items:center; justify-content:center; gap:8px; text-align:center; margin:0;
        padding:13px 14px; border:1.5px solid #e5e7eb; border-radius:10px; background:#f9fafb;
        font-size:13px; font-weight:700; color:#5b6472; cursor:pointer; transition:border-color .15s, background .15s, color .15s;
    }
    .shopys-delivery-option label.radio:hover{ border-color:#bdeccd; }
    .shopys-delivery-option input[type=radio]:checked + label.radio{ border-color:#00c44f; background:#eafff2; color:#0a7d00; box-shadow:0 0 0 3px rgba(0,196,79,.12); }
    @media (max-width:480px){ .shopys-delivery-option .woocommerce-input-wrapper{ grid-template-columns:1fr; } }
    </style>
    <script>
    (function(){ if(!window.jQuery) return; var $=window.jQuery;
        // Hide Delivery Location, Receiver Address & Email for Pick Up (not needed when collecting in store).
        var HIDE = ["#delivery_map_field", "#billing_address_1_field", "#billing_email_field"];
        var REQ  = ["#delivery_map_field", "#billing_address_1_field"]; // required again for Delivery
        function setReq($row, required){
            if(!$row.length) return;
            $row.toggleClass("validate-required", required);
            var $label = $row.children("label").first();
            if(required){
                $row.find("span.optional").remove();
                if($label.length && !$label.find("abbr.required").length){ $label.append(" <abbr class='required' title='required'>*</abbr>"); }
            } else {
                $row.find("abbr.required").remove();
                if($label.length && !$label.find("span.optional").length){ $label.append(" <span class='optional'>(optional)</span>"); }
            }
        }
        function applyPickupFields(){
            var pickup = $("input[name='delivery_option']:checked").val() === "pickup";
            HIDE.forEach(function(sel){ var $r = $(sel); if(pickup){ $r.hide(); } else { $r.show(); } });
            REQ.forEach(function(sel){ setReq($(sel), !pickup); }); // required for Delivery, optional for Pick Up
        }
        $(document.body).on("change", "input[name='delivery_option']", function(){ applyPickupFields(); $(document.body).trigger("update_checkout"); });
        $(applyPickupFields);
        $(document.body).on("updated_checkout", applyPickupFields);

        // Validate the Delivery Location is a Google Maps link (inline feedback).
        function isMapsUrl(v){
            v = (v||"").trim(); if(!v) return true;
            try{
                var u = new URL(v), h = u.hostname.toLowerCase(), p = u.pathname.toLowerCase();
                if(h === "maps.app.goo.gl") return true;
                if(h === "goo.gl" && p.indexOf("/maps") === 0) return true;
                if(/(^|\.)google\.[a-z.]+$/.test(h)){ if(h.indexOf("maps.") === 0) return true; if(p.indexOf("/maps") !== -1) return true; }
                return false;
            }catch(e){ return false; }
        }
        $(document.body).on("blur change input", "#delivery_map", function(){
            var $f = $("#delivery_map_field"), ok = isMapsUrl($(this).val());
            $f.toggleClass("woocommerce-invalid woocommerce-invalid-required-field", !ok);
            $f.toggleClass("woocommerce-validated", ok && ($(this).val()||"").trim() !== "");
        });
        // Block advancing to Step 2 if the Delivery Location isn't a valid Google Maps link (Delivery only).
        // Bound at wp_footer 100 → runs before the stepper's Next handler (120) so it can stop it.
        $(document.body).on("click", ".shopys-next", function(e){
            if($("input[name='delivery_option']:checked").val() === "pickup") return; // map hidden for pickup
            var $inp = $("#delivery_map"), v = ($inp.val()||"").trim();
            if(v !== "" && !isMapsUrl(v)){
                e.preventDefault(); e.stopImmediatePropagation();
                var $f = $("#delivery_map_field");
                $f.addClass("woocommerce-invalid woocommerce-invalid-required-field").removeClass("woocommerce-validated");
                if($f.offset()){ $("html,body").animate({ scrollTop: $f.offset().top - 110 }, 300); }
                $inp.focus();
            }
        });
    })();
    </script>
    <?php
}

// ── Checkout: single "Delivery Address" (no separate shipping address) ────────
// Cambodia uses one delivery address — drop the shipping-address section entirely…
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// …and rename "Billing details" → "Delivery Address" at checkout.
add_filter( 'gettext', 'shopys_billing_to_delivery_label', 20, 3 );
function shopys_billing_to_delivery_label( $translated, $text, $domain ) {
    if ( 'woocommerce' !== $domain ) return $translated;
    if ( ! ( function_exists( 'is_checkout' ) && is_checkout() ) ) return $translated;
    if ( $text === 'Billing details' || $text === 'Billing &amp; Shipping' || $text === 'Billing Details' ) {
        return 'Delivery Address';
    }
    if ( $text === 'Street address' ) {
        return 'Receiver Address';
    }
    if ( $text === 'Phone' ) {
        return 'Phone (Number that have Telegram)';
    }
    return $translated;
}

/* ── Checkout: Delivery Location (map link) — easy for customer + delivery man ── */
add_filter( 'woocommerce_checkout_fields', 'shopys_add_delivery_map_field' );
function shopys_add_delivery_map_field( $fields ) {
    // Remove Company name, "Apartment, suite, unit, etc.", Postcode/ZIP, State/County and Country fields.
    unset( $fields['billing']['billing_company'] );
    unset( $fields['billing']['billing_address_2'] );
    unset( $fields['billing']['billing_postcode'] );
    unset( $fields['billing']['billing_state'] );
    unset( $fields['billing']['billing_country'] );

    // Pick Up orders don't need a delivery address/map — make those fields not required.
    $is_pickup = ( shopys_get_delivery_option() === 'pickup' );

    // Rename "Street address" to "Receiver Address" (required only for Delivery).
    if ( isset( $fields['billing']['billing_address_1'] ) ) {
        $fields['billing']['billing_address_1']['label']       = __( 'Receiver Address', 'shopys' );
        $fields['billing']['billing_address_1']['placeholder'] = __( 'Receiver Address', 'shopys' );
        $fields['billing']['billing_address_1']['required']    = ! $is_pickup;
    }

    // Make Email address optional.
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['required'] = false;
    }

    // Make Town / City optional.
    if ( isset( $fields['billing']['billing_city'] ) ) {
        $fields['billing']['billing_city']['required'] = false;
    }

    // Move Phone above the Delivery Location (Map) field (map = 45) and relabel it.
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['priority'] = 30;
        $fields['billing']['billing_phone']['label']    = __( 'Phone (Number that have Telegram)', 'shopys' );
    }

    // Delivery Option — Pick Up (free) or Delivery (+$2). Shown near the top.
    $fields['billing']['delivery_option'] = array(
        'type'     => 'radio',
        'label'    => __( 'Delivery Option', 'shopys' ),
        'required' => true,
        'class'    => array( 'form-row-wide', 'shopys-delivery-option' ),
        'options'  => array(
            'delivery' => __( 'Delivery (+$2)', 'shopys' ),
            'pickup'   => __( 'Pick Up (Free)', 'shopys' ),
        ),
        'default'  => 'delivery',
        'priority' => 2,
    );

    // Delivery Location map field, placed just above Street address (billing_address_1 = 50).
    $fields['billing']['delivery_map'] = array(
        'type'        => 'text',
        'label'       => __( 'Delivery Location (Map)', 'shopys' ),
        'placeholder' => __( 'Tap "Use my current location" or paste a Google Maps link', 'shopys' ),
        'required'    => ! $is_pickup, // required for Delivery, not for Pick Up
        'class'       => array( 'form-row-wide', 'shopys-map-field' ),
        'priority'    => 45,
    );
    return $fields;
}

/** True if $url is a Google Maps link (short link or google.com/maps). */
function shopys_is_google_maps_url( $url ) {
    $url  = trim( (string) $url );
    $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    $path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
    if ( $host === '' ) return false;
    if ( $host === 'maps.app.goo.gl' ) return true;                       // app short link
    if ( $host === 'goo.gl' && strpos( $path, '/maps' ) === 0 ) return true; // legacy short link
    if ( preg_match( '/(^|\.)google\.[a-z.]+$/', $host ) ) {              // any google.* domain
        if ( strpos( $host, 'maps.' ) === 0 ) return true;               // maps.google.com
        if ( strpos( $path, '/maps' ) !== false ) return true;           // www.google.com/maps...
    }
    return false;
}

// Validate the Delivery Location value is a real Google Maps link (Delivery orders only).
add_action( 'woocommerce_checkout_process', 'shopys_validate_delivery_map' );
function shopys_validate_delivery_map() {
    if ( shopys_get_delivery_option() === 'pickup' ) return; // pickup hides the field
    $val = isset( $_POST['delivery_map'] ) ? trim( wp_unslash( $_POST['delivery_map'] ) ) : '';
    if ( $val === '' ) return; // empty is handled by the field's "required" flag
    if ( ! shopys_is_google_maps_url( $val ) ) {
        wc_add_notice( __( 'Delivery Location must be a Google Maps link (e.g. https://maps.app.goo.gl/…). Tap "Use my current location" or paste a Google Maps link.', 'shopys' ), 'error' );
    }
}

add_action( 'woocommerce_checkout_create_order', 'shopys_save_delivery_map', 10, 2 );
function shopys_save_delivery_map( $order, $data ) {
    if ( ! empty( $_POST['delivery_map'] ) ) {
        $order->update_meta_data( '_delivery_map', esc_url_raw( wp_unslash( $_POST['delivery_map'] ) ) );
    }
    // Country & State fields are removed from checkout — default them to Cambodia.
    $order->set_billing_country( 'KH' );
    $order->set_billing_state( 'Cambodia' );

    // Save the chosen delivery option (Pick Up / Delivery).
    $order->update_meta_data( '_delivery_option', shopys_get_delivery_option() === 'pickup' ? 'Pick Up' : 'Delivery' );
}

// Default the customer's billing country to Cambodia (used for shipping/tax calc).
add_filter( 'default_checkout_billing_country', 'shopys_default_country_kh' );
function shopys_default_country_kh() {
    return 'KH';
}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'shopys_admin_show_delivery_map' );
function shopys_admin_show_delivery_map( $order ) {
    $opt = $order->get_meta( '_delivery_option' );
    if ( $opt ) {
        echo '<p><strong>' . esc_html__( 'Delivery Option', 'shopys' ) . ':</strong> ' . esc_html( $opt ) . '</p>';
    }
    $map = $order->get_meta( '_delivery_map' );
    if ( $map ) {
        echo '<p><strong>' . esc_html__( 'Delivery Map', 'shopys' ) . ':</strong> <a href="' . esc_url( $map ) . '" target="_blank" rel="noopener">' . esc_html( $map ) . '</a></p>';
    }
}

// "Use my current location" button + browser geolocation (no API key needed).
add_action( 'wp_footer', 'shopys_delivery_map_button' );
function shopys_delivery_map_button() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) return;
    ?>
    <style>
    .shopys-geo-btn{ margin-top:8px; display:inline-flex; align-items:center; gap:7px; padding:10px 16px; border:1.5px solid #00c44f; background:#fff; color:#0a7d00; font-weight:700; font-size:13px; border-radius:10px; cursor:pointer; font-family:'Play','Battambang',-apple-system,sans-serif; transition:background .2s; }
    .shopys-geo-btn:hover{ background:#f0fff4; }
    .shopys-geo-btn svg{ width:15px; height:15px; }
    .shopys-geo-msg{ font-size:12px; margin-top:7px; font-weight:600; line-height:1.5; }
    .shopys-geo-msg a{ color:#00a341; font-weight:700; }
    </style>
    <script>
    (function(){ if(!window.jQuery) return; var $=window.jQuery;
        function ensureBtn(){
            var $wrap = $('#billing_delivery_map_field');
            if(!$wrap.length || $wrap.find('.shopys-geo-btn').length) return;
            var btn = $('<button type="button" class="shopys-geo-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?php echo esc_js( __( 'Use my current location', 'shopys' ) ); ?></button>');
            var msg = $('<div class="shopys-geo-msg"></div>');
            $wrap.append(btn).append(msg);
            btn.on('click', function(){
                if(!navigator.geolocation){ msg.css('color','#d80019').text('<?php echo esc_js( __( 'Geolocation not supported on this device.', 'shopys' ) ); ?>'); return; }
                msg.css('color','#5b6472').text('<?php echo esc_js( __( 'Getting your location…', 'shopys' ) ); ?>');
                navigator.geolocation.getCurrentPosition(function(p){
                    var lat=p.coords.latitude.toFixed(6), lng=p.coords.longitude.toFixed(6);
                    var link='https://maps.google.com/?q='+lat+','+lng;
                    $('#billing_delivery_map').val(link).trigger('change');
                    msg.css('color','#0a7d00').html('✓ <?php echo esc_js( __( 'Location captured', 'shopys' ) ); ?> — <a href="'+link+'" target="_blank" rel="noopener">'+lat+', '+lng+'</a>');
                }, function(){
                    msg.css('color','#d80019').text('<?php echo esc_js( __( 'Could not get location. Please allow location access or paste a Google Maps link.', 'shopys' ) ); ?>');
                }, { enableHighAccuracy:true, timeout:10000 });
            });
        }
        $(document.body).on('updated_checkout', ensureBtn);
        $(ensureBtn);
    })();
    </script>
    <?php
}

// Remove the checkout "Your personal data will be used…" privacy-policy paragraph.
add_filter( 'woocommerce_get_privacy_policy_text', function ( $text, $type ) {
    return ( $type === 'checkout' ) ? '' : $text;
}, 100, 2 );

// Remove the "Additional information" / "Order notes (optional)" block at checkout.
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// Rename the COD gateway "Walk-In Customer" → "Pay With Cash" + premium description (deploy-safe).
add_filter( 'option_woocommerce_cod_settings', 'shopys_cod_settings_override' );
function shopys_cod_settings_override( $settings ) {
    if ( ! is_array( $settings ) ) return $settings;
    $settings['title']        = __( 'Pay With Cash', 'shopys' );
    $settings['description']  = __( 'Pay in cash when your order is delivered, or when you collect it at our store. No online payment needed — just place your order and our team will contact you to confirm delivery or pickup.', 'shopys' );
    $settings['instructions'] = __( 'Please prepare the exact amount in cash. Our team will reach out shortly to arrange delivery or pickup. Thank you!', 'shopys' );
    return $settings;
}
// Update the label on existing orders (order detail, Telegram, etc.).
add_filter( 'woocommerce_order_get_payment_method_title', 'shopys_cod_order_title', 10, 2 );
function shopys_cod_order_title( $title, $order ) {
    if ( is_a( $order, 'WC_Abstract_Order' ) && $order->get_payment_method() === 'cod' ) {
        return __( 'Pay With Cash', 'shopys' );
    }
    return $title;
}

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
    /* Full-width premium card; fields in a responsive grid (label on top, value below) */
    ul.woocommerce-order-overview.order_details{
        max-width:100% !important; width:100% !important; margin:0 0 26px !important; padding:24px 28px !important;
        list-style:none !important; display:grid !important;
        grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)) !important; gap:20px 30px !important;
        background:#fff; border:1px solid #eef0f4 !important; border-radius:16px;
        box-shadow:0 8px 26px rgba(15,23,42,.05); font-family:'Play','Battambang',-apple-system,sans-serif;
    }
    ul.woocommerce-order-overview.order_details li{
        display:flex !important; flex-direction:column !important; gap:5px; align-items:flex-start;
        padding:0 !important; margin:0 !important; border:0 !important; float:none !important; width:auto !important;
        text-align:left !important; text-transform:uppercase !important; letter-spacing:.6px;
        font-size:11px !important; font-weight:700 !important; color:#9aa3b0 !important; line-height:1.3;
    }
    ul.woocommerce-order-overview.order_details li strong{
        color:#0d1117 !important; font-weight:800 !important; font-size:15px !important;
        text-transform:none !important; letter-spacing:0; word-break:break-word; line-height:1.4;
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

// Force 12px font on all checkout routes (checkout, order-pay, order-received).
add_action( 'wp_head', 'shopys_checkout_font_size', 99 );
function shopys_checkout_font_size() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    echo '<style id="shopys-checkout-12">
    body.woocommerce-checkout .woocommerce,
    body.woocommerce-checkout .woocommerce p,
    body.woocommerce-checkout .woocommerce li,
    body.woocommerce-checkout .woocommerce td,
    body.woocommerce-checkout .woocommerce th,
    body.woocommerce-checkout .woocommerce label,
    body.woocommerce-checkout .woocommerce span,
    body.woocommerce-checkout .woocommerce a,
    body.woocommerce-checkout .woocommerce strong,
    body.woocommerce-checkout .woocommerce small,
    body.woocommerce-checkout .woocommerce select,
    body.woocommerce-checkout .woocommerce textarea,
    body.woocommerce-checkout .woocommerce h1,
    body.woocommerce-checkout .woocommerce h2,
    body.woocommerce-checkout .woocommerce h3,
    body.woocommerce-checkout .woocommerce h4,
    body.woocommerce-checkout .woocommerce input,
    body.woocommerce-checkout .woocommerce button,
    body.woocommerce-checkout .woocommerce form,
    body.woocommerce-checkout form.checkout,
    body.woocommerce-checkout form#order_review,
    body.woocommerce-checkout #payment,
    body.woocommerce-checkout #place_order,
    body.woocommerce-checkout button#place_order,
    body.woocommerce-checkout .woocommerce .button{ font-size:12px !important; }
    </style>';
}

// Premium styling for the "Select Shop" dropdown (matches the checkout theme).
add_action( 'wp_head', 'shopys_shop_field_style' );
function shopys_shop_field_style() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) return;
    echo '<style>
    #shop_branch_field{ margin-bottom:18px !important; }
    #shop_branch_field > label{ display:block !important; font-weight:700 !important; color:#0d1117 !important; margin-bottom:7px !important; }
    #shop_branch_field .required{ color:#e21c25 !important; text-decoration:none; }
    #shop_branch{
        width:100% !important; height:46px !important; line-height:1.4 !important; min-height:0 !important;
        padding:0 40px 0 14px !important; margin:0 !important;
        border:1.5px solid #e5e7eb !important; border-radius:10px !important;
        background-color:#f9fafb !important; color:#111827 !important; font-size:15px !important; font-weight:400 !important;
        font-family:inherit !important;
        -webkit-appearance:none !important; -moz-appearance:none !important; appearance:none !important; cursor:pointer;
        background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2716%27 height=%2716%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%235b6472%27 stroke-width=%272.5%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3E%3Cpolyline points=%276 9 12 15 18 9%27/%3E%3C/svg%3E") !important;
        background-repeat:no-repeat !important; background-position:right 12px center !important; background-size:16px !important;
        box-shadow:none !important; box-sizing:border-box !important; transition:border-color .15s, background .15s, box-shadow .15s !important;
    }
    #shop_branch:focus{ border-color:#00c44f !important; background-color:#fff !important; box-shadow:0 0 0 4px rgba(0,196,79,.15) !important; outline:none !important; }
    </style>';
}

// ── Stepper checkout: Step 1 Delivery Address → Step 2 Your Order (Next/Back) ──
add_action( 'wp_head', 'shopys_checkout_stepper_css', 100 );
function shopys_checkout_stepper_css() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) return;
    ?>
    <style>
    /* Hide search blocks/widgets on the checkout page */
    body.woocommerce-checkout .widget_search,
    body.woocommerce-checkout .widget_product_search,
    body.woocommerce-checkout .wp-block-search,
    body.woocommerce-checkout .woocommerce-product-search,
    body.woocommerce-checkout .search-form,
    body.woocommerce-checkout #searchform,
    body.woocommerce-checkout form[role="search"]{ display:none !important; }
    /* Anti-flash: render single-column with Step 2 hidden on first paint (before JS) */
    body.woocommerce-checkout form.checkout{ display:block !important; max-width:760px !important; margin-left:auto !important; margin-right:auto !important; }
    body.woocommerce-checkout form.checkout > #order_review{ display:none; }
    .shopys-steps{ display:flex; align-items:center; gap:12px; margin:0 auto 22px; max-width:760px; font-family:"Play","Battambang",sans-serif; }
    .shopys-step{ display:flex; align-items:center; gap:8px; font-weight:800; color:#9aa3b0; font-size:12px; cursor:pointer; white-space:nowrap; }
    .shopys-step span{ width:26px; height:26px; border-radius:50%; background:#e7e9ee; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; transition:background .2s; }
    .shopys-step.active{ color:#0d1117; } .shopys-step.active span{ background:#00c44f; }
    .shopys-step.done span{ background:#00a341; }
    .shopys-step-line{ flex:1; height:2px; background:#e7e9ee; border-radius:2px; }
    /* Top action bar on each step */
    .shopys-actions{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin:0 0 18px; flex-wrap:wrap; }
    .shopys-actions.shopys-actions-end{ justify-content:flex-end; }
    .shopys-next, .shopys-place{ display:inline-flex; align-items:center; gap:7px; padding:13px 26px; background:#00c44f; color:#fff; font-weight:800; font-size:13px; border:none; border-radius:11px; cursor:pointer; font-family:"Play","Battambang",sans-serif; box-shadow:0 8px 20px rgba(0,196,79,.28); transition:background .2s, transform .15s; }
    .shopys-next:hover, .shopys-place:hover{ background:#00a341; transform:translateY(-1px); }
    .shopys-back{ display:inline-flex; align-items:center; gap:6px; padding:11px 18px; background:#fff; border:1.5px solid #e7e9ee; border-radius:11px; color:#5b6472; font-weight:700; font-size:12px; cursor:pointer; font-family:"Play","Battambang",sans-serif; }
    .shopys-back:hover{ border-color:#00c44f; color:#0a7d00; }
    /* Hide the original bottom Place Order — we expose it in the top action bar */
    body.woocommerce-checkout #order_review #payment .form-row.place-order{ display:none !important; }
    body.woocommerce-checkout .woocommerce-billing-fields > h3{ display:none !important; }
    body.woocommerce-checkout .woocommerce-billing-fields::before{ content:"STEP 1  \00b7  DELIVERY ADDRESS"; display:block; font-size:12px; font-weight:800; letter-spacing:1px; color:#00a341; margin:0 0 16px; padding-bottom:12px; border-bottom:1px solid #eef0f4; font-family:"Play","Battambang",sans-serif; }
    body.woocommerce-checkout #order_review::before{ content:"STEP 2  \00b7  YOUR ORDER" !important; color:#00a341 !important; letter-spacing:1px !important; font-size:12px !important; font-weight:800 !important; }
    </style>
    <noscript><style>
    /* No-JS fallback: show the full single-page checkout so an order can still be placed */
    body.woocommerce-checkout form.checkout > #order_review{ display:block !important; }
    body.woocommerce-checkout #order_review #payment .form-row.place-order{ display:block !important; }
    body.woocommerce-checkout .shopys-steps{ display:none !important; }
    </style></noscript>
    <?php
}

add_action( 'wp_footer', 'shopys_checkout_stepper_js', 120 );
function shopys_checkout_stepper_js() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) return;
    ?>
    <script>
    (function(){
        if(!window.jQuery) return;
        var $ = window.jQuery, step = 1;
        function imp(el, prop, val){ if(el && el.style){ el.style.setProperty(prop, val, "important"); } }
        function applyLayout(){
            var $f = $("form.checkout"); if(!$f.length) return;
            imp($f[0], "display", "block");
            imp($f[0], "max-width", "760px");
            imp($f[0], "margin-left", "auto");
            imp($f[0], "margin-right", "auto");
            var cust = document.getElementById("customer_details");
            var rev  = document.getElementById("order_review");
            if(cust) imp(cust, "display", step === 1 ? "block" : "none");
            if(rev)  imp(rev,  "display", step === 2 ? "block" : "none");
            $(".shopys-step").removeClass("active done");
            $(".shopys-step[data-go='" + step + "']").addClass("active");
            if(step === 2){ $(".shopys-step[data-go='1']").addClass("done"); }
        }
        function buildChrome(){
            var $f = $("form.checkout"); if(!$f.length) return;
            if(!$(".shopys-steps").length){
                $f.prepend("<div class='shopys-steps'><div class='shopys-step' data-go='1'><span>1</span> Delivery Address</div><div class='shopys-step-line'></div><div class='shopys-step' data-go='2'><span>2</span> Your Order</div></div>");
            }
            if($("#customer_details").length && !$("#customer_details > .shopys-actions").length){
                $("#customer_details").prepend("<div class='shopys-actions shopys-actions-end'><button type='button' class='shopys-next'>Next — Review Order →</button></div>");
            }
            if($("#order_review").length && !$("#order_review > .shopys-actions").length){
                var placeTxt = ($.trim($("#place_order").text()) || "Place Order");
                $("#order_review").prepend("<div class='shopys-actions'><button type='button' class='shopys-back'>← Back to Delivery</button><button type='button' class='shopys-place'>" + placeTxt + "</button></div>");
            }
            applyLayout();
        }
        function toForm(){ var $f = $("form.checkout"); if($f.length){ $("html,body").animate({ scrollTop: $f.offset().top - 90 }, 300); } }
        $(document.body).on("click", ".shopys-next", function(e){
            e.preventDefault();
            var ok = true, bad = null;
            $("#customer_details .validate-required:visible").each(function(){
                var $r = $(this), v = ($r.find("input,select,textarea").first().val() || "").toString().trim();
                if(v === ""){ ok = false; $r.addClass("woocommerce-invalid woocommerce-invalid-required-field"); if(!bad){ bad = $r; } }
                else { $r.removeClass("woocommerce-invalid woocommerce-invalid-required-field"); }
            });
            if(!ok){ if(bad){ $("html,body").animate({ scrollTop: bad.offset().top - 110 }, 300); } return; }
            step = 2; applyLayout(); toForm();
        });
        $(document.body).on("click", ".shopys-back", function(e){ e.preventDefault(); step = 1; applyLayout(); toForm(); });
        $(document.body).on("click", ".shopys-place", function(e){ e.preventDefault(); var $p = $("#place_order"); if($p.length){ $p.trigger("click"); } else { $("form.checkout").trigger("submit"); } });
        $(document.body).on("click", ".shopys-steps .shopys-step", function(){ if(parseInt($(this).attr("data-go"), 10) === 1){ step = 1; applyLayout(); toForm(); } });
        $(buildChrome);
        $(document.body).on("updated_checkout", buildChrome);
    })();
    </script>
    <?php
}

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

/** Telegram username for the "Contact Seller" button (out-of-stock products). Configurable via env / wp-config. */
function shopys_contact_seller_username() {
    $u = '';
    if ( defined( 'SHOPYS_CONTACT_SELLER' ) ) {
        $u = (string) SHOPYS_CONTACT_SELLER;
    } else {
        $v = getenv( 'SHOPYS_CONTACT_SELLER' );
        if ( $v !== false ) $u = (string) $v;
    }
    if ( $u === '' ) $u = 'unicorn_vvipcplus'; // fallback default
    return ltrim( trim( $u ), '@' ); // store/accept with or without leading "@"
}

// Never serve a cached (logged-out) page to a logged-in user — fixes "appears logged out
// after navigating / asked to log in again on add-to-cart" caused by full-page caching on production.
add_action( 'template_redirect', 'shopys_no_cache_for_logged_in', 0 );
function shopys_no_cache_for_logged_in() {
    if ( ! is_user_logged_in() ) return;
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true ); // WP Super Cache / W3TC / etc.
    do_action( 'litespeed_control_set_nocache', 'shopys: logged-in user must not be cached' ); // LiteSpeed
    nocache_headers(); // CDN / browser
}

// Premium mobile overlay menu — targets the theme .sider.overcenter panel (mobile-only; hidden on desktop).
add_action( 'wp_head', 'shopys_premium_mobile_menu', 130 );
function shopys_premium_mobile_menu() {
    echo '<style>
    /* Mobile only (theme mobile menu activates at <=1024px) — desktop header untouched */
    @media screen and (max-width:1024px){
    /* Backdrop + solid premium panel */
    .open-shop-mobile-menu-wrapper{ background:rgba(5,8,12,.55) !important; -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px); }
    .mobile-menu-active .sider.overcenter,
    .sticky-mobile-menu-active .sider.overcenter,
    .mobile-bottom-menu-active .sider.overcenter{
        background:linear-gradient(180deg,#0d1117 0%,#080b10 100%) !important;
    }
    /* Centered menu column */
    .mobile-menu-active .sider.overcenter .open-shop-menu,
    .sticky-mobile-menu-active .sider.overcenter .open-shop-menu,
    .mobile-bottom-menu-active .sider.overcenter .open-shop-menu{
        max-width:520px !important; width:auto !important; margin:0 auto !important; padding:0 22px !important; display:block !important; list-style:none !important;
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu > li,
    .sticky-mobile-menu-active .sider.overcenter .open-shop-menu > li,
    .mobile-bottom-menu-active .sider.overcenter .open-shop-menu > li{
        display:block !important; float:none !important; width:100% !important; margin:0 0 11px !important; padding:0 !important; border:none !important; text-align:left !important; line-height:1.3 !important;
    }
    /* Each item = premium card row */
    .mobile-menu-active .sider.overcenter .open-shop-menu > li > a,
    .sticky-mobile-menu-active .sider.overcenter .open-shop-menu > li > a,
    .mobile-bottom-menu-active .sider.overcenter .open-shop-menu > li > a{
        display:flex !important; align-items:center !important; justify-content:space-between; gap:10px;
        padding:18px 26px !important; border-radius:14px !important; line-height:1.25 !important; min-height:0 !important; height:auto !important; text-align:left !important;
        background:rgba(255,255,255,.045) !important; border:1px solid rgba(255,255,255,.08) !important;
        color:#e6edf3 !important; font-size:15.5px !important; font-weight:600 !important; letter-spacing:.2px; text-transform:none !important;
        transition:background .18s, border-color .18s, box-shadow .18s, transform .12s !important;
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu > li > a *{ color:inherit !important; }
    .mobile-menu-active .sider.overcenter .open-shop-menu > li > a .open-shop-menu-link,
    .sticky-mobile-menu-active .sider.overcenter .open-shop-menu > li > a .open-shop-menu-link,
    .mobile-bottom-menu-active .sider.overcenter .open-shop-menu > li > a .open-shop-menu-link{
        display:inline-block !important; line-height:1.25 !important; padding:0 !important; margin:0 0 0 10px !important; color:inherit !important; font-weight:600 !important;
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu > li > a:hover{ transform:translateY(-1px); border-color:rgba(0,196,79,.55) !important; background:rgba(0,196,79,.08) !important; }
    .mobile-menu-active .sider.overcenter .open-shop-menu > li.current-menu-item > a,
    .mobile-menu-active .sider.overcenter .open-shop-menu > li.current_page_item > a,
    .mobile-menu-active .sider.overcenter .open-shop-menu > li.menu-active > a,
    .sticky-mobile-menu-active .sider.overcenter .open-shop-menu > li.current-menu-item > a,
    .mobile-bottom-menu-active .sider.overcenter .open-shop-menu > li.current-menu-item > a{
        background:linear-gradient(135deg,#00c44f,#00a341) !important; border-color:transparent !important; color:#fff !important;
        box-shadow:0 10px 26px rgba(0,196,79,.34) !important;
    }
    /* Sub-menu (accordion) */
    .mobile-menu-active .sider.overcenter .open-shop-menu ul.sub-menu{
        margin:8px 0 4px !important; padding:6px !important; background:rgba(255,255,255,.03) !important; border-radius:12px !important; list-style:none !important;
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu ul.sub-menu li{ border:none !important; margin:0 !important; width:100% !important; }
    .mobile-menu-active .sider.overcenter .open-shop-menu ul.sub-menu li a{
        color:#aeb8c4 !important; padding:12px 16px !important; border-radius:10px !important; font-size:14px !important; display:block !important; background:transparent !important;
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu ul.sub-menu li a:hover{ background:rgba(0,196,79,.12) !important; color:#fff !important; }
    /* Dropdown arrow chip */
    .mobile-menu-active .sider.overcenter .open-shop-menu .arrow{
        width:24px; height:24px; flex:0 0 auto; display:inline-flex !important; align-items:center; justify-content:center;
        border-radius:7px; background:rgba(255,255,255,.07);
    }
    .mobile-menu-active .sider.overcenter .open-shop-menu .arrow:before{ color:#cfd6df !important; }
    /* Close button */
    .mobile-menu-active .sider.overcenter .menu-close-btn{
        width:42px !important; height:42px !important; border-radius:50% !important; display:inline-flex !important; align-items:center; justify-content:center;
        background:rgba(255,255,255,.08) !important; border:1px solid rgba(255,255,255,.12) !important; color:#fff !important; margin:0 auto 6px !important;
    }
    .mobile-menu-active .sider.overcenter .menu-close-btn:hover{ background:#ef4444 !important; border-color:#ef4444 !important; }
    }
    </style>';
}

/** Separate channel for Walk-In Customer (COD) orders; falls back to the main one. */
function shopys_tg_walkin_chat_id() {
    if ( defined( 'SHOPYS_TG_WALKIN_CHAT_ID' ) ) return (string) SHOPYS_TG_WALKIN_CHAT_ID;
    $v = getenv( 'SHOPYS_TG_WALKIN_CHAT_ID' );
    return $v !== false ? (string) $v : '';
}

/** Route an order to the right Telegram channel by payment method. */
/** The two shops the customer chooses between at checkout. */
function shopys_shop_branches() {
    return array(
        'vstore' => 'V-Store Center',
        'vtech'  => 'V-Tech Gaming Center',
    );
}

/**
 * Shop pickup location info for a branch (name, address, coords, phone) — env/constant configurable.
 * Keys: SHOPYS_SHOP_<BRANCH>_ADDRESS | _COORDS | _PHONE   (BRANCH = VSTORE | VTECH)
 */
function shopys_shop_info( $branch ) {
    $branch = strtolower( (string) $branch );
    $b      = strtoupper( $branch );
    $names  = shopys_shop_branches();
    $get = function ( $suffix ) use ( $b ) {
        $key = "SHOPYS_SHOP_{$b}_{$suffix}";
        if ( defined( $key ) ) return (string) constant( $key );
        $v = getenv( $key );
        return $v !== false ? (string) $v : '';
    };
    return array(
        'name'      => isset( $names[ $branch ] ) ? $names[ $branch ] : '',
        'address'   => trim( $get( 'ADDRESS' ) ),
        'coords'    => trim( $get( 'COORDS' ) ),
        'phone'     => trim( $get( 'PHONE' ) ),
        'embed_url' => trim( $get( 'MAP_EMBED' ) ), // full Google Maps "embed?pb=..." src (optional)
        'link'      => trim( $get( 'MAP_LINK' ) ),  // shareable Google Maps link (e.g. maps.app.goo.gl/...)
    );
}

/** Show the selected shop's location + map on the order-received / view-order page. */
add_action( 'woocommerce_order_details_after_order_table', 'shopys_order_shop_map_section', 5 );
function shopys_order_shop_map_section( $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) return;
    if ( $order->get_meta( '_delivery_option' ) !== 'Pick Up' ) return; // only for pickup orders
    $branch = $order->get_meta( '_shop_branch' );
    if ( ! $branch ) return;
    $info  = shopys_shop_info( $branch );
    $embed = shopys_shop_map_embed( $info );
    $link  = shopys_shop_map_link( $info );
    if ( $info['address'] === '' && $embed === '' ) return; // nothing configured yet

    echo '<section class="shopys-shopmap">';
    echo '<h2 class="shopys-shopmap-title">🏬 ' . esc_html( $info['name'] ) . '</h2>';
    echo '<p class="shopys-shopmap-note">' . esc_html__( 'Shop location — for pickup or to visit us.', 'shopys' ) . '</p>';
    if ( $info['address'] !== '' ) {
        echo '<p class="shopys-shopmap-addr">📍 ' . esc_html( $info['address'] ) . '</p>';
    }
    if ( $info['phone'] !== '' ) {
        echo '<p class="shopys-shopmap-phone">📞 ' . esc_html( $info['phone'] ) . '</p>';
    }
    if ( $embed !== '' ) {
        echo '<div class="shopys-shopmap-frame"><iframe src="' . esc_url( $embed ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
    }
    if ( $link !== '' ) {
        echo '<a class="shopys-shopmap-open" href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open in Google Maps', 'shopys' ) . ' →</a>';
    }
    echo '</section>';
    echo '<style>
    .shopys-shopmap{ margin:22px 0; padding:18px; border:1.5px solid #e5e7eb; border-radius:14px; background:#f9fafb; }
    .shopys-shopmap-title{ font-size:16px !important; margin:0 0 4px !important; color:#0d1117; }
    .shopys-shopmap-note{ font-size:12px; color:#6b7280; margin:0 0 10px; }
    .shopys-shopmap-addr, .shopys-shopmap-phone{ font-size:13px; color:#111827; margin:2px 0; }
    .shopys-shopmap-frame{ margin:12px 0; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb; }
    .shopys-shopmap-frame iframe{ width:100%; height:260px; border:0; display:block; }
    .shopys-shopmap-open{ display:inline-flex; align-items:center; gap:6px; padding:10px 16px; background:#00c44f; color:#fff !important; font-weight:700; font-size:13px; border-radius:10px; text-decoration:none !important; }
    .shopys-shopmap-open:hover{ background:#00a341; }
    </style>';
}

/** Google Maps embed URL (no API key) for a shop info array; '' if no location set. */
function shopys_shop_map_embed( $info ) {
    if ( ! empty( $info['embed_url'] ) ) return $info['embed_url']; // explicit embed src wins
    $q = $info['coords'] !== '' ? $info['coords'] : $info['address'];
    return $q !== '' ? 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=16&output=embed' : '';
}

/** "Open in Google Maps" link for a shop info array; '' if no location set. */
function shopys_shop_map_link( $info ) {
    // Explicit shareable link wins (e.g. maps.app.goo.gl/...).
    if ( ! empty( $info['link'] ) ) return $info['link'];
    // Otherwise prefer coords pulled from the embed URL (!3d<lat>!2d<lng>) so the link matches the embedded map.
    if ( ! empty( $info['embed_url'] )
        && preg_match( '/!3d(-?[0-9.]+)/', $info['embed_url'], $lat )
        && preg_match( '/!2d(-?[0-9.]+)/', $info['embed_url'], $lng ) ) {
        return 'https://www.google.com/maps?q=' . rawurlencode( $lat[1] . ',' . $lng[1] );
    }
    $q = $info['coords'] !== '' ? $info['coords'] : $info['address'];
    return $q !== '' ? 'https://www.google.com/maps?q=' . rawurlencode( $q ) : '';
}

// Checkout: confirm dialog (customer info + selected shop map) before placing a PICK-UP order.
// Bound at priority 115 so it runs before the stepper's Place Order proxy (priority 120).
add_action( 'wp_footer', 'shopys_checkout_confirm_dialog', 115 );
function shopys_checkout_confirm_dialog() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
    if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) return;

    $shops = array();
    foreach ( array_keys( shopys_shop_branches() ) as $b ) {
        $info        = shopys_shop_info( $b );
        $shops[ $b ] = array(
            'name'    => $info['name'],
            'address' => $info['address'],
            'phone'   => $info['phone'],
            'embed'   => shopys_shop_map_embed( $info ),
            'link'    => shopys_shop_map_link( $info ),
        );
    }
    ?>
    <style>
    .shopys-cd-overlay{ position:fixed; inset:0; background:rgba(13,17,23,.55); z-index:100000; display:flex; align-items:center; justify-content:center; padding:16px; }
    .shopys-cd-box{ background:#fff; width:100%; max-width:440px; max-height:90vh; overflow:auto; border-radius:16px; padding:22px; position:relative; font-family:"Play","Battambang",sans-serif; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .shopys-cd-x{ position:absolute; top:12px; right:14px; background:none; border:none; font-size:24px; line-height:1; color:#9aa3b0; cursor:pointer; }
    .shopys-cd-title{ font-size:17px !important; font-weight:800; margin:0 0 4px !important; color:#0d1117; }
    .shopys-cd-sub{ font-size:12px; color:#6b7280; margin:0 0 14px; }
    .shopys-cd-info{ display:grid; gap:8px; margin-bottom:16px; }
    .shopys-cd-info > div{ display:flex; justify-content:space-between; gap:12px; font-size:13px; border-bottom:1px dashed #eef0f4; padding-bottom:7px; }
    .shopys-cd-info span{ color:#6b7280; } .shopys-cd-info b{ color:#0d1117; text-align:right; }
    .shopys-cd-shop{ background:#f9fafb; border:1.5px solid #e5e7eb; border-radius:12px; padding:14px; margin-bottom:16px; }
    .shopys-cd-shopname{ font-weight:800; font-size:14px; color:#0d1117; margin-bottom:6px; }
    .shopys-cd-addr{ font-size:12.5px; color:#374151; margin:2px 0; }
    .shopys-cd-map{ margin:10px 0; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; }
    .shopys-cd-map iframe{ width:100%; height:200px; border:0; display:block; }
    .shopys-cd-maprow{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .shopys-cd-open{ display:inline-block; font-size:12.5px; font-weight:700; color:#00a341 !important; text-decoration:none !important; }
    .shopys-cd-copy{ display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border:1.5px solid #e5e7eb; background:#fff; border-radius:9px; color:#5b6472; font-weight:700; font-size:12px; cursor:pointer; font-family:inherit; }
    .shopys-cd-copy:hover{ border-color:#00c44f; color:#0a7d00; }
    .shopys-cd-copy svg{ width:14px; height:14px; }
    .shopys-cd-actions{ display:flex; gap:10px; }
    .shopys-cd-cancel{ flex:0 0 auto; padding:13px 18px; border:1.5px solid #e5e7eb; background:#fff; color:#5b6472; font-weight:700; font-size:13px; border-radius:11px; cursor:pointer; font-family:inherit; }
    .shopys-cd-confirm{ flex:1; padding:13px 18px; border:none; background:#00c44f; color:#fff; font-weight:800; font-size:13px; border-radius:11px; cursor:pointer; font-family:inherit; }
    .shopys-cd-confirm:hover{ background:#00a341; }
    </style>
    <script>
    (function(){
        if(!window.jQuery) return; var $=window.jQuery;
        var SHOPS = <?php echo wp_json_encode( $shops ); ?>;
        function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){ return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]; }); }
        function legacyCopy(text, cb){
            try{
                var ta=document.createElement("textarea");
                ta.value=text; ta.setAttribute("readonly",""); ta.style.position="fixed"; ta.style.top="-9999px"; ta.style.opacity="0";
                document.body.appendChild(ta); ta.focus(); ta.select(); ta.setSelectionRange(0, ta.value.length);
                var ok=document.execCommand("copy"); document.body.removeChild(ta);
                if(ok && cb) cb();
            }catch(e){}
        }
        function openDialog(branch, onConfirm){
            var shop = SHOPS[branch] || {};
            var name = (($("#billing_first_name").val()||"")+" "+($("#billing_last_name").val()||"")).trim();
            var phone = $("#billing_phone").val()||"";
            var addr = (($("#billing_address_1").val()||"")+", "+($("#billing_city").val()||"")).replace(/^,\s*|,\s*$/g,"");
            var h = "<div class='shopys-cd-box'>"
                + "<button type='button' class='shopys-cd-x' aria-label='Close'>&times;</button>"
                + "<h3 class='shopys-cd-title'>Pick Up — Confirm</h3>"
                + "<p class='shopys-cd-sub'>Please confirm your details and the shop location to pick up your order.</p>"
                + "<div class='shopys-cd-info'>"
                +   "<div><span>Name</span><b>"+esc(name||"-")+"</b></div>"
                +   "<div><span>Phone</span><b>"+esc(phone||"-")+"</b></div>"
                +   "<div><span>Address</span><b>"+esc(addr||"-")+"</b></div>"
                +   "<div><span>Shop</span><b>"+esc(shop.name||"")+"</b></div>"
                + "</div>";
            h += "<div class='shopys-cd-shop'>";
            h += "<div class='shopys-cd-shopname'>🏬 "+esc(shop.name||"")+"</div>";
            if(shop.address) h += "<div class='shopys-cd-addr'>📍 "+esc(shop.address)+"</div>";
            if(shop.phone)   h += "<div class='shopys-cd-addr'>📞 "+esc(shop.phone)+"</div>";
            if(shop.embed)   h += "<div class='shopys-cd-map'><iframe src='"+esc(shop.embed)+"' loading='lazy' allowfullscreen></iframe></div>";
            if(shop.link){
                h += "<div class='shopys-cd-maprow'>";
                h += "<a class='shopys-cd-open' href='"+esc(shop.link)+"' target='_blank' rel='noopener'>Open in Google Maps &rarr;</a>";
                h += "<button type='button' class='shopys-cd-copy' data-link='"+esc(shop.link)+"' title='Copy Google Maps link'>"
                   + "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='9' y='9' width='13' height='13' rx='2' ry='2'></rect><path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'></path></svg>"
                   + "<span class='shopys-cd-copy-txt'>Copy</span></button>";
                h += "</div>";
            }
            h += "</div>";
            h += "<div class='shopys-cd-actions'>"
                + "<button type='button' class='shopys-cd-cancel'>Cancel</button>"
                + "<button type='button' class='shopys-cd-confirm'>Confirm &amp; Place Order</button>"
                + "</div></div>";
            var $ov = $("<div class='shopys-cd-overlay'></div>").html(h);
            $("body").append($ov);
            function close(){ $ov.remove(); }
            $ov.on("click", function(e){ if(e.target===$ov[0]) close(); });
            $ov.find(".shopys-cd-x, .shopys-cd-cancel").on("click", close);
            $ov.on("click", ".shopys-cd-copy", function(){
                var link = $(this).attr("data-link"), $txt = $(this).find(".shopys-cd-copy-txt");
                function done(){ var old = $txt.data("orig") || $txt.text(); $txt.data("orig", old); $txt.text("Copied!"); setTimeout(function(){ $txt.text(old); }, 1500); }
                if(navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(link).then(done).catch(function(){ legacyCopy(link, done); });
                } else {
                    legacyCopy(link, done);
                }
            });
            $ov.find(".shopys-cd-confirm").on("click", function(){ close(); onConfirm(); });
        }
        $(document.body).on("click", ".shopys-place", function(e){
            var deliv  = $("input[name='delivery_option']:checked").val() || "";
            var branch = $("#shop_branch").val() || "";
            // Only for PICK UP orders, with a shop that has a configured location.
            if(deliv !== "pickup" || !branch || !SHOPS[branch] || !SHOPS[branch].embed){ return; }
            e.preventDefault(); e.stopImmediatePropagation();
            openDialog(branch, function(){ $("#place_order").trigger("click"); });
        });
    })();
    </script>
    <?php
}

/** Telegram channel for branch (vstore|vtech) + type (khqr|walkin) — env/constant overridable. */
function shopys_tg_branch_chat( $branch, $type ) {
    $branch = strtolower( (string) $branch );
    $type   = strtolower( (string) $type );
    $key    = 'SHOPYS_TG_' . strtoupper( $branch ) . '_' . strtoupper( $type ); // e.g. SHOPYS_TG_VSTORE_KHQR
    if ( defined( $key ) ) return (string) constant( $key );
    $v = getenv( $key );
    return ( $v !== false ) ? (string) $v : ''; // not set → caller falls back to the generic channel
}

function shopys_tg_chat_for_order( $order ) {
    $type   = ( $order && $order->get_payment_method() === 'khqrpay' ) ? 'khqr' : 'walkin';
    $branch = $order ? $order->get_meta( '_shop_branch' ) : '';
    if ( $branch ) {
        $chat = shopys_tg_branch_chat( $branch, $type );
        if ( $chat !== '' ) return $chat;
    }
    // Fallback to the single-channel config if no shop was selected.
    return ( $type === 'khqr' ) ? shopys_tg_order_chat_id() : ( shopys_tg_walkin_chat_id() ?: shopys_tg_order_chat_id() );
}

/* ── Required "Select Shop" field at checkout ──────────────────────────────── */
add_filter( 'woocommerce_checkout_fields', 'shopys_add_shop_field' );
function shopys_add_shop_field( $fields ) {
    $fields['billing']['shop_branch'] = array(
        'type'     => 'select',
        'label'    => __( 'Select Shop', 'shopys' ),
        'required' => true,
        'class'    => array( 'form-row-wide', 'shopys-shop-field' ),
        'priority' => 1,
        'options'  => array( '' => __( 'Choose your shop…', 'shopys' ) ) + shopys_shop_branches(),
    );
    return $fields;
}

add_action( 'woocommerce_checkout_create_order', 'shopys_save_shop_field', 10, 2 );
function shopys_save_shop_field( $order, $data ) {
    $branch = isset( $_POST['shop_branch'] ) ? wc_clean( wp_unslash( $_POST['shop_branch'] ) ) : '';
    if ( $branch !== '' ) $order->update_meta_data( '_shop_branch', $branch );
}

// Show the chosen shop on the admin order screen.
add_action( 'woocommerce_admin_order_data_after_billing_address', 'shopys_admin_show_shop' );
function shopys_admin_show_shop( $order ) {
    $branch = $order->get_meta( '_shop_branch' );
    if ( ! $branch ) return;
    $names = shopys_shop_branches();
    echo '<p><strong>' . esc_html__( 'Shop', 'shopys' ) . ':</strong> ' . esc_html( isset( $names[ $branch ] ) ? $names[ $branch ] : $branch ) . '</p>';
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
    // Build the receiver address from the simplified checkout fields (Receiver Address, City, Cambodia).
    $addr_parts = array_filter( array(
        trim( (string) $order->get_billing_address_1() ),
        trim( (string) $order->get_billing_city() ),
        'Cambodia',
    ) );
    $addr = implode( ', ', $addr_parts );

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
    $div  = "━━━━━━━━━━━━━━━━━━━━━━━━━━";

    $branch_meta  = $order->get_meta( '_shop_branch' );
    $branch_names = shopys_shop_branches();
    $shop_title   = ( $branch_meta && isset( $branch_names[ $branch_meta ] ) ) ? $branch_names[ $branch_meta ] : get_bloginfo( 'name' );
    $header = ( $order->get_payment_method() === 'khqrpay' ) ? '🛍️  <b>NEW PAID ORDER</b>' : '🛒  <b>NEW ORDER</b>';
    $msg  = $header . "\n";
    $msg .= "<i>" . esc_html( $shop_title ) . "</i>\n";
    $msg .= $div . "\n";
    $msg .= "🧾  <b>Invoice :</b> #" . esc_html( $order->get_order_number() ) . "\n";
    if ( $branch_meta && isset( $branch_names[ $branch_meta ] ) ) {
        $msg .= "🏬  <b>Shop :</b> " . esc_html( $branch_names[ $branch_meta ] ) . "\n";
    }
    $msg .= "👤  <b>Customer Name :</b> " . esc_html( $name !== '' ? $name : '-' ) . "\n";
    $msg .= "📞  <b>Customer Phone :</b> " . esc_html( $phone !== '' ? $phone : '-' ) . "\n";
    $msg .= "📍  <b>Receiver Address :</b> " . esc_html( $addr !== '' ? $addr : '-' ) . "\n";
    $deliv_opt = $order->get_meta( '_delivery_option' );
    if ( $deliv_opt ) {
        $deliv_label = ( $deliv_opt === 'Pick Up' ) ? 'Pick Up (Free)' : 'Delivery (+$2)';
        $msg .= "🚚  <b>Delivery Option :</b> " . esc_html( $deliv_label ) . "\n";
    }
    $map = $order->get_meta( '_delivery_map' );
    if ( $map ) {
        $msg .= "🗺️  <b>Delivery Map :</b> <a href=\"" . esc_url( $map ) . "\">" . esc_html__( 'Open in Google Maps', 'shopys' ) . "</a>\n";
    }
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
    $st_slug   = $order->get_status();
    $st_badges = array(
        'completed'  => '🟢', 'processing' => '🟢',
        'on-hold'    => '🟡', 'pending'    => '🟠',
        'cancelled'  => '🔴', 'failed'     => '🔴',
        'refunded'   => '⚪',
    );
    $st_badge = isset( $st_badges[ $st_slug ] ) ? $st_badges[ $st_slug ] : '⚫';
    $msg .= "📌  <b>Status:</b> " . $st_badge . " " . esc_html( wc_get_order_status_name( $st_slug ) ) . "\n";
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
        $order->update_meta_data( '_shopys_tg_last_status', $order->get_status() );
        $order->save();
    } else {
        error_log( 'shopys TG order notify failed: ' . ( is_wp_error( $resp ) ? $resp->get_error_message() : wp_remote_retrieve_body( $resp ) ) );
    }
}

// ── Separate "payment received" notification for KHQR payments, per shop (payment details only) ──
// Sends to SHOPYS_TG_<BRANCH>_PAYMENT (e.g. SHOPYS_TG_VSTORE_PAYMENT). No products — just the money + order id.
add_action( 'woocommerce_payment_complete', 'shopys_notify_telegram_payment', 25 );
add_action( 'woocommerce_order_status_processing', 'shopys_notify_telegram_payment', 25 );
add_action( 'woocommerce_order_status_completed', 'shopys_notify_telegram_payment', 25 );
function shopys_notify_telegram_payment( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    if ( $order->get_payment_method() !== 'khqrpay' ) return;          // QR payments only
    if ( $order->get_meta( '_shopys_tg_pay_notified' ) === 'yes' ) return; // once per order

    $branch = $order->get_meta( '_shop_branch' );
    $chat   = $branch ? shopys_tg_branch_chat( $branch, 'payment' ) : ''; // SHOPYS_TG_<BRANCH>_PAYMENT
    $token  = defined( 'SHOPYS_TG_BOT_TOKEN' ) ? SHOPYS_TG_BOT_TOKEN : '';
    if ( ! $token || ! $chat ) return; // no payment group configured for this shop → skip

    $branch_names = shopys_shop_branches();
    $shop  = ( $branch && isset( $branch_names[ $branch ] ) ) ? $branch_names[ $branch ] : get_bloginfo( 'name' );
    $cur   = $order->get_currency();
    $total = ( $cur === 'KHR' ) ? number_format( (float) $order->get_total(), 0 ) . ' ៛' : '$' . number_format( (float) $order->get_total(), 2 );

    // Per-shop receiver account (from the KHQR gateway).
    $recv = function_exists( 'khqrpay_order_account' ) ? khqrpay_order_account( $order ) : array();
    $recv_name = $recv['merchant_name'] ?? '';
    $recv_acct = $recv['account_number'] ?? ( $recv['account_info'] ?? '' );

    $name  = trim( $order->get_formatted_billing_full_name() );
    $phone = $order->get_billing_phone();
    $when  = $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'd M Y · g:i A' )
           : ( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y · g:i A' ) : date_i18n( 'd M Y · g:i A' ) );
    $div   = "━━━━━━━━━━━━━━━━━━━━━━━━━━";

    $msg  = "✅  <b>PAYMENT RECEIVED — KHQR</b>\n";
    $msg .= "<i>" . esc_html( $shop ) . "</i>\n";
    $msg .= $div . "\n";
    $msg .= "🧾  <b>Order ID :</b> #" . esc_html( $order->get_order_number() ) . "\n";
    $msg .= "💰  <b>Amount :</b> <b>" . esc_html( $total ) . "</b>\n";
    $msg .= "💳  <b>Method :</b> KHQR (Bakong)\n";
    if ( $recv_name !== '' || $recv_acct !== '' ) {
        $msg .= "🏦  <b>Received by :</b> " . esc_html( trim( $recv_name . ( $recv_acct !== '' ? ' · ' . $recv_acct : '' ) ) ) . "\n";
    }
    if ( $name !== '' )  $msg .= "👤  <b>Customer :</b> " . esc_html( $name ) . "\n";
    if ( $phone !== '' ) $msg .= "📞  <b>Phone :</b> " . esc_html( $phone ) . "\n";
    $msg .= $div . "\n";
    $msg .= "🕒  <i>" . esc_html( $when ) . "</i>";

    $resp = wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", array(
        'timeout' => 15,
        'body'    => array( 'chat_id' => $chat, 'text' => $msg, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true ),
    ) );
    if ( ! is_wp_error( $resp ) && (int) wp_remote_retrieve_response_code( $resp ) === 200 ) {
        $order->update_meta_data( '_shopys_tg_pay_notified', 'yes' );
        $order->save();
    } else {
        error_log( 'shopys TG payment notify failed: ' . ( is_wp_error( $resp ) ? $resp->get_error_message() : wp_remote_retrieve_body( $resp ) ) );
    }
}

/** Colored badge emoji for an order status (used in both the new-order + update messages). */
function shopys_order_status_badge( $slug ) {
    $b = array(
        'completed' => '🟢', 'processing' => '🟢',
        'on-hold'   => '🟡', 'pending'    => '🟠',
        'cancelled' => '🔴', 'failed'     => '🔴',
        'refunded'  => '⚪',
    );
    return isset( $b[ $slug ] ) ? $b[ $slug ] : '⚫';
}

/* ── Telegram: notify when an order's status changes (e.g. admin edits it) ──── */
add_action( 'woocommerce_order_status_changed', 'shopys_notify_telegram_status_changed', 30, 4 );
function shopys_notify_telegram_status_changed( $order_id, $from, $to, $order = null ) {
    if ( ! $order ) $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    // Only for orders we've already announced; skip the initial transition (same status just notified).
    if ( $order->get_meta( '_shopys_tg_notified' ) !== 'yes' ) return;
    $last = $order->get_meta( '_shopys_tg_last_status' );
    if ( $last === '' ) $last = $from;
    if ( $to === $last ) return;

    $token = defined( 'SHOPYS_TG_BOT_TOKEN' ) ? SHOPYS_TG_BOT_TOKEN : '';
    $chat  = shopys_tg_chat_for_order( $order );
    if ( ! $token || ! $chat ) return;

    $branch_names = shopys_shop_branches();
    $branch       = $order->get_meta( '_shop_branch' );
    $shop         = ( $branch && isset( $branch_names[ $branch ] ) ) ? $branch_names[ $branch ] : get_bloginfo( 'name' );
    $cur          = $order->get_currency();
    $total        = ( $cur === 'KHR' ) ? number_format( (float) $order->get_total(), 0 ) . ' ៛' : '$' . number_format( (float) $order->get_total(), 2 );
    $div          = "━━━━━━━━━━━━━━━━━━━━━━━━━━";

    $msg  = "🔄  <b>ORDER STATUS UPDATED</b>\n";
    $msg .= "<i>" . esc_html( $shop ) . "</i>\n";
    $msg .= $div . "\n";
    $msg .= "🧾  <b>Invoice :</b> #" . esc_html( $order->get_order_number() ) . "\n";
    if ( $branch && isset( $branch_names[ $branch ] ) ) {
        $msg .= "🏬  <b>Shop :</b> " . esc_html( $shop ) . "\n";
    }
    $msg .= "📌  <b>Status:</b> " . shopys_order_status_badge( $last ) . " " . esc_html( wc_get_order_status_name( $last ) )
          . "  →  " . shopys_order_status_badge( $to ) . " <b>" . esc_html( wc_get_order_status_name( $to ) ) . "</b>\n";
    $msg .= "💰  <b>TOTAL:</b> " . esc_html( $total ) . "\n";
    $msg .= $div . "\n";
    $msg .= "🕒  <i>" . esc_html( date_i18n( 'd M Y · g:i A' ) ) . "</i>";

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
        $order->update_meta_data( '_shopys_tg_last_status', $to );
        $order->save();
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
    // Only administrators and shop managers may access the dashboard.
    if ( ! array_intersect( array( 'administrator', 'shop_manager' ), (array) wp_get_current_user()->roles ) ) {
        wp_safe_redirect( home_url( '/' ) );
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