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