<?php
/**
 * Shopys — Profile picture upload
 *
 * Lets logged-in customers upload a profile picture from the my-account
 * dashboard. To avoid plugin/WAF interference with multipart uploads on some
 * shared hosts (notably the Hostinger nginx layer), the file is sent as a
 * base64 data URL in a regular POST and written directly to
 * wp-content/uploads/shopys-avatars/. No WordPress attachment record is
 * created — the resolved URL lives in user meta (_shopys_avatar_url).
 *
 * Priority: custom upload > telegram_photo > default Gravatar.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Helper: resolve the avatar URL for a user ────────────────────────────────
function shopys_get_user_avatar_url( $user_id, $size = 96 ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return '';

    $custom = (string) get_user_meta( $user_id, '_shopys_avatar_url', true );
    if ( $custom ) return $custom;

    $tg = get_user_meta( $user_id, 'telegram_photo', true );
    if ( $tg ) return $tg;

    // Bypass our own filter to avoid recursion when computing the default.
    remove_filter( 'get_avatar_url', 'shopys_filter_avatar_url', 99 );
    $url = get_avatar_url( $user_id, array( 'size' => (int) $size ) );
    add_filter( 'get_avatar_url', 'shopys_filter_avatar_url', 99, 3 );
    return $url;
}

// ── Filter: redirect every get_avatar_url() call to the resolved URL ─────────
add_filter( 'get_avatar_url', 'shopys_filter_avatar_url', 99, 3 );
function shopys_filter_avatar_url( $url, $id_or_email, $args ) {
    $user_id = 0;
    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $u = get_user_by( 'email', $id_or_email );
        if ( $u ) $user_id = $u->ID;
    } elseif ( is_object( $id_or_email ) ) {
        if ( ! empty( $id_or_email->user_id ) )      $user_id = (int) $id_or_email->user_id;
        elseif ( ! empty( $id_or_email->ID ) )       $user_id = (int) $id_or_email->ID;
        elseif ( ! empty( $id_or_email->user_email ) ) {
            $u = get_user_by( 'email', $id_or_email->user_email );
            if ( $u ) $user_id = $u->ID;
        }
    }
    if ( ! $user_id ) return $url;

    $custom = (string) get_user_meta( $user_id, '_shopys_avatar_url', true );
    if ( $custom ) return $custom;

    $tg = get_user_meta( $user_id, 'telegram_photo', true );
    if ( $tg ) return $tg;

    return $url;
}

// ── Internal: where avatars live ─────────────────────────────────────────────
function shopys_avatar_dir() {
    $uploads = wp_upload_dir();
    return array(
        'path' => trailingslashit( $uploads['basedir'] ) . 'shopys-avatars',
        'url'  => trailingslashit( $uploads['baseurl'] ) . 'shopys-avatars',
        'err'  => ! empty( $uploads['error'] ) ? $uploads['error'] : '',
    );
}

// ── Internal: delete a previously uploaded avatar file (if it lives in our dir)
function shopys_delete_old_avatar( $url ) {
    if ( ! $url ) return;
    $dir = shopys_avatar_dir();
    if ( strpos( $url, $dir['url'] . '/' ) !== 0 ) return; // only delete files we own
    $rel = substr( $url, strlen( $dir['url'] ) + 1 );
    $path = $dir['path'] . '/' . $rel;
    if ( file_exists( $path ) ) @unlink( $path );
}

// ── AJAX: upload avatar (base64 in POST body) ────────────────────────────────
add_action( 'wp_ajax_shopys_upload_avatar', 'shopys_handle_avatar_upload' );
function shopys_handle_avatar_upload() {
    // Catch any escaped fatal so the client gets a JSON error instead of nginx 500.
    register_shutdown_function( function() {
        $err = error_get_last();
        $fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
        if ( $err && in_array( $err['type'], $fatal, true ) && ! headers_sent() ) {
            @header( 'Content-Type: application/json; charset=utf-8' );
            @http_response_code( 200 );
            @ob_end_clean();
            echo wp_json_encode( array(
                'success' => false,
                'data'    => array( 'msg' => 'PHP fatal: ' . $err['message'] . ' @ ' . basename( $err['file'] ) . ':' . $err['line'] ),
            ) );
        }
    } );

    try {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'msg' => __( 'You must be logged in.', 'shopys' ) ) );
        }
        if ( ! check_ajax_referer( 'shopys_avatar', 'nonce', false ) ) {
            wp_send_json_error( array( 'msg' => __( 'Security check failed. Please refresh and try again.', 'shopys' ) ) );
        }

        $image = isset( $_POST['image'] ) ? (string) wp_unslash( $_POST['image'] ) : '';
        if ( ! $image ) {
            wp_send_json_error( array( 'msg' => __( 'No image data received.', 'shopys' ) ) );
        }

        // Expect: data:image/<png|jpeg|gif|webp>;base64,<payload>
        if ( ! preg_match( '#^data:image/(jpeg|png|gif|webp);base64,(.+)$#i', $image, $m ) ) {
            wp_send_json_error( array( 'msg' => __( 'Please upload a JPG, PNG, GIF, or WebP image.', 'shopys' ) ) );
        }
        $mime  = 'image/' . strtolower( $m[1] );
        $ext   = strtolower( $m[1] === 'jpeg' ? 'jpg' : $m[1] );
        $bytes = base64_decode( $m[2], true );
        if ( $bytes === false || strlen( $bytes ) < 16 ) {
            wp_send_json_error( array( 'msg' => __( 'Invalid image data.', 'shopys' ) ) );
        }

        // 10 MB cap on the decoded image
        if ( strlen( $bytes ) > 10 * 1024 * 1024 ) {
            wp_send_json_error( array( 'msg' => __( 'Image must be smaller than 10 MB.', 'shopys' ) ) );
        }

        // Sanity-check magic bytes match the claimed type.
        $head = substr( $bytes, 0, 12 );
        $ok = ( $ext === 'jpg'  && substr( $head, 0, 3 ) === "\xFF\xD8\xFF" )
           || ( $ext === 'png'  && substr( $head, 0, 8 ) === "\x89PNG\r\n\x1A\n" )
           || ( $ext === 'gif'  && ( substr( $head, 0, 6 ) === 'GIF87a' || substr( $head, 0, 6 ) === 'GIF89a' ) )
           || ( $ext === 'webp' && substr( $head, 0, 4 ) === 'RIFF' && substr( $head, 8, 4 ) === 'WEBP' );
        if ( ! $ok ) {
            wp_send_json_error( array( 'msg' => __( 'File does not look like a valid image.', 'shopys' ) ) );
        }

        $user_id = get_current_user_id();
        $dir     = shopys_avatar_dir();
        if ( $dir['err'] ) {
            wp_send_json_error( array( 'msg' => $dir['err'] ) );
        }

        if ( ! is_dir( $dir['path'] ) ) {
            if ( ! wp_mkdir_p( $dir['path'] ) ) {
                wp_send_json_error( array( 'msg' => __( 'Could not create upload directory.', 'shopys' ) ) );
            }
        }
        if ( ! is_writable( $dir['path'] ) ) {
            wp_send_json_error( array( 'msg' => __( 'Upload directory is not writable.', 'shopys' ) ) );
        }

        $filename = sprintf( 'user-%d-%s.%s', $user_id, wp_generate_password( 8, false ), $ext );
        $filepath = $dir['path'] . '/' . $filename;
        $fileurl  = $dir['url']  . '/' . $filename;

        if ( file_put_contents( $filepath, $bytes ) === false ) {
            wp_send_json_error( array( 'msg' => __( 'Failed to write image to disk.', 'shopys' ) ) );
        }

        // Best-effort: delete the old avatar file if it lives in our directory.
        $previous = (string) get_user_meta( $user_id, '_shopys_avatar_url', true );
        if ( $previous ) shopys_delete_old_avatar( $previous );

        update_user_meta( $user_id, '_shopys_avatar_url', $fileurl );
        // Tidy up: make sure the legacy attachment-id meta isn't left around.
        delete_user_meta( $user_id, '_shopys_avatar_id' );

        wp_send_json_success( array(
            'url' => $fileurl,
            'msg' => __( 'Profile picture updated.', 'shopys' ),
        ) );
    } catch ( \Throwable $e ) {
        error_log( '[shopys avatar upload] exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
        wp_send_json_error( array(
            'msg' => 'Exception: ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine(),
        ) );
    }
}

// ── AJAX: remove avatar ──────────────────────────────────────────────────────
add_action( 'wp_ajax_shopys_remove_avatar', 'shopys_handle_avatar_remove' );
function shopys_handle_avatar_remove() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'msg' => __( 'You must be logged in.', 'shopys' ) ) );
    }
    if ( ! check_ajax_referer( 'shopys_avatar', 'nonce', false ) ) {
        wp_send_json_error( array( 'msg' => __( 'Security check failed.', 'shopys' ) ) );
    }

    $user_id  = get_current_user_id();
    $previous = (string) get_user_meta( $user_id, '_shopys_avatar_url', true );
    if ( $previous ) shopys_delete_old_avatar( $previous );
    delete_user_meta( $user_id, '_shopys_avatar_url' );
    delete_user_meta( $user_id, '_shopys_avatar_id' ); // legacy

    wp_send_json_success( array(
        'url' => shopys_get_user_avatar_url( $user_id, 96 ),
        'msg' => __( 'Profile picture removed.', 'shopys' ),
    ) );
}

// ── UI: render the avatar block (image + edit button + hidden file input) ────
function shopys_render_avatar_uploader( $user_id, $name = '', $size = 96 ) {
    $url   = shopys_get_user_avatar_url( $user_id, $size );
    $nonce = wp_create_nonce( 'shopys_avatar' );
    $has_custom = (bool) get_user_meta( $user_id, '_shopys_avatar_url', true );
    ?>
    <div class="shopys-avatar-uploader" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-has-custom="<?php echo $has_custom ? '1' : '0'; ?>">
        <img class="sai-dash-avatar shopys-avatar-img" src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $name ); ?>" />
        <button type="button" class="shopys-avatar-edit" aria-label="<?php esc_attr_e( 'Change profile picture', 'shopys' ); ?>" title="<?php esc_attr_e( 'Change profile picture', 'shopys' ); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
        </button>
        <div class="shopys-avatar-spinner" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
        </div>
        <input type="file" class="shopys-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden />
        <?php if ( $has_custom ) : ?>
        <button type="button" class="shopys-avatar-remove" aria-label="<?php esc_attr_e( 'Remove profile picture', 'shopys' ); ?>" title="<?php esc_attr_e( 'Remove profile picture', 'shopys' ); ?>">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <?php endif; ?>
    </div>
    <?php
}

// ── Inline assets (CSS + JS) on the my-account page ──────────────────────────
add_action( 'wp_footer', 'shopys_avatar_uploader_assets', 60 );
function shopys_avatar_uploader_assets() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    if ( ! is_user_logged_in() ) return;
    ?>
    <style>
    .shopys-avatar-uploader {
        position: relative;
        display: inline-block;
        line-height: 0;
    }
    .shopys-avatar-uploader .shopys-avatar-img {
        display: block;
        border-radius: 50%;
        object-fit: cover;
        transition: filter 0.2s ease, opacity 0.2s ease;
    }
    .shopys-avatar-uploader.is-uploading .shopys-avatar-img {
        filter: blur(2px) brightness(0.85);
        opacity: 0.85;
    }
    .shopys-avatar-edit {
        position: absolute;
        right: -2px;
        bottom: -2px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: #fff;
        border: 3px solid #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 6px 14px rgba(34, 197, 94, 0.36);
        transition: transform 0.15s ease, box-shadow 0.2s ease;
        padding: 0;
    }
    .shopys-avatar-edit:hover {
        transform: scale(1.06);
        box-shadow: 0 8px 18px rgba(34, 197, 94, 0.5);
    }
    .shopys-avatar-edit:active { transform: scale(0.98); }
    .shopys-avatar-remove {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #ef4444;
        color: #fff;
        border: 2px solid #ffffff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.36);
        opacity: 0;
        transform: scale(0.7);
        transition: opacity 0.15s, transform 0.15s;
        padding: 0;
    }
    .shopys-avatar-uploader:hover .shopys-avatar-remove,
    .shopys-avatar-uploader:focus-within .shopys-avatar-remove {
        opacity: 1;
        transform: scale(1);
    }
    .shopys-avatar-spinner {
        position: absolute;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        color: #16a34a;
        pointer-events: none;
    }
    .shopys-avatar-uploader.is-uploading .shopys-avatar-spinner {
        display: flex;
    }
    .shopys-avatar-spinner svg {
        animation: shopys-spin 0.9s linear infinite;
    }
    @keyframes shopys-spin {
        to { transform: rotate(360deg); }
    }

    /* ── Preview modal (before upload) ─────────────────────── */
    .shopys-preview-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 100002;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .shopys-preview-overlay.is-visible { opacity: 1; }
    .shopys-preview-card {
        background: #fff;
        border-radius: 20px;
        max-width: 460px;
        width: 100%;
        padding: 28px 28px 24px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
        text-align: center;
        transform: scale(0.94);
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        font-family: inherit;
    }
    .shopys-preview-overlay.is-visible .shopys-preview-card { transform: scale(1); }
    .shopys-preview-title {
        font-size: 18px;
        font-weight: 800;
        color: #111827;
        margin: 0 0 4px;
        letter-spacing: -0.01em;
    }
    .shopys-preview-sub {
        font-size: 13px;
        color: #6b7280;
        margin: 0 0 22px;
    }
    /* Cropper viewport — circular mask, drag to pan */
    .shopys-cropper {
        position: relative;
        width: 280px;
        height: 280px;
        margin: 0 auto 14px;
        border-radius: 50%;
        overflow: hidden;
        background: repeating-conic-gradient(#f3f4f6 0% 25%, #e5e7eb 0% 50%) 50% / 16px 16px;
        cursor: grab;
        user-select: none;
        -webkit-user-select: none;
        touch-action: none;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(15, 23, 42, 0.05), inset 0 0 0 2px rgba(255, 255, 255, 0.85);
    }
    .shopys-cropper.is-dragging { cursor: grabbing; }
    .shopys-cropper-img {
        position: absolute;
        top: 0;
        left: 0;
        display: block;
        max-width: none;
        max-height: none;
        pointer-events: none;
        user-select: none;
        -webkit-user-drag: none;
        image-orientation: from-image;
    }
    .shopys-cropper-zoom {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 auto 18px;
        max-width: 280px;
    }
    .shopys-cropper-zoom svg {
        flex-shrink: 0;
        color: #6b7280;
    }
    .shopys-cropper-zoom input[type=range] {
        flex: 1;
        height: 4px;
        -webkit-appearance: none;
        appearance: none;
        background: #e5e7eb;
        border-radius: 999px;
        outline: none;
    }
    .shopys-cropper-zoom input[type=range]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(34, 197, 94, 0.36);
        cursor: pointer;
    }
    .shopys-cropper-zoom input[type=range]::-moz-range-thumb {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(34, 197, 94, 0.36);
        cursor: pointer;
    }
    .shopys-cropper-hint {
        text-align: center;
        font-size: 12px;
        color: #6b7280;
        margin: 0 0 14px;
    }

    /* Avatar lightbox (click avatar to enlarge) */
    .shopys-lightbox-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.78);
        backdrop-filter: blur(6px);
        z-index: 100003;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .shopys-lightbox-overlay.is-visible { opacity: 1; }
    .shopys-lightbox-img {
        max-width: min(90vw, 480px);
        max-height: 80vh;
        width: auto;
        height: auto;
        border-radius: 50%;
        object-fit: cover;
        aspect-ratio: 1 / 1;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.5), 0 0 0 6px rgba(255, 255, 255, 0.08);
        transform: scale(0.92);
        transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .shopys-lightbox-overlay.is-visible .shopys-lightbox-img { transform: scale(1); }
    .shopys-lightbox-close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        border: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s, transform 0.15s;
        backdrop-filter: blur(8px);
    }
    .shopys-lightbox-close:hover { background: rgba(255, 255, 255, 0.22); transform: scale(1.05); }
    .shopys-avatar-uploader .shopys-avatar-img { cursor: zoom-in; }
    .shopys-preview-meta {
        font-size: 12px;
        color: #6b7280;
        margin: 0 0 22px;
    }
    .shopys-preview-actions {
        display: flex;
        gap: 10px;
    }
    .shopys-preview-btn {
        flex: 1;
        height: 46px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        border: 0;
        font-family: inherit;
        transition: transform 0.15s, box-shadow 0.2s, background 0.15s, color 0.15s;
    }
    .shopys-preview-btn--cancel {
        background: #f3f4f6;
        color: #374151;
    }
    .shopys-preview-btn--cancel:hover { background: #e5e7eb; color: #111827; }
    .shopys-preview-btn--save {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: #fff;
        box-shadow: 0 8px 20px rgba(34, 197, 94, 0.32);
    }
    .shopys-preview-btn--save:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 26px rgba(34, 197, 94, 0.42);
    }
    .shopys-preview-btn--save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    </style>
    <script>
    (function(){
        var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var TXT = {
            tooBig:    <?php echo wp_json_encode( __( 'Image must be smaller than 10 MB.', 'shopys' ) ); ?>,
            badType:   <?php echo wp_json_encode( __( 'Please choose a JPG, PNG, GIF, or WebP image.', 'shopys' ) ); ?>,
            confirm:   <?php echo wp_json_encode( __( 'Remove your profile picture?', 'shopys' ) ); ?>,
            generic:   <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'shopys' ) ); ?>,
            reading:   <?php echo wp_json_encode( __( 'Could not read the file.', 'shopys' ) ); ?>,
            prevTitle: <?php echo wp_json_encode( __( 'Adjust your photo', 'shopys' ) ); ?>,
            prevSub:   <?php echo wp_json_encode( __( 'Drag to reposition, use the slider to zoom.', 'shopys' ) ); ?>,
            cropHint:  <?php echo wp_json_encode( __( 'Anything inside the circle will be saved as your profile picture.', 'shopys' ) ); ?>,
            cancel:    <?php echo wp_json_encode( __( 'Cancel', 'shopys' ) ); ?>,
            save:      <?php echo wp_json_encode( __( 'Save photo', 'shopys' ) ); ?>,
            saving:    <?php echo wp_json_encode( __( 'Saving…', 'shopys' ) ); ?>
        };

        function formatBytes(n) {
            if (n < 1024) return n + ' B';
            if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
            return (n / (1024 * 1024)).toFixed(2) + ' MB';
        }

        // Cropper: drag-to-pan + zoom slider over a circular viewport.
        // The displayed <img> may apply EXIF rotation (modern browsers do via
        // image-orientation: from-image), but canvas.drawImage from a raw <img>
        // can render the un-rotated pixels, producing a sideways/upside-down
        // crop. To avoid that, we keep an EXIF-corrected source for the canvas:
        //   - prefer createImageBitmap(blob, {imageOrientation: 'from-image'})
        //   - fall back to the displayed <img> when the API isn't available
        function setupCropper(stage, img, zoom, srcFile) {
            var V = stage.offsetWidth || 280;
            var natW = 0, natH = 0, minScale = 1, scale = 1;
            var ox = 0, oy = 0;
            var dragging = false, lastX = 0, lastY = 0;
            var ready = false;
            var canvasSource = null; // ImageBitmap if available, else null (use img)

            function apply() {
                img.style.width  = (natW * scale) + 'px';
                img.style.height = (natH * scale) + 'px';
                img.style.transform = 'translate(' + ox + 'px, ' + oy + 'px)';
            }
            function clamp() {
                var iw = natW * scale, ih = natH * scale;
                if (ox > 0) ox = 0;
                if (oy > 0) oy = 0;
                if (ox < V - iw) ox = V - iw;
                if (oy < V - ih) oy = V - ih;
            }
            function finishInit(w, h) {
                natW = w; natH = h;
                if (!natW || !natH) return;
                minScale = V / Math.min(natW, natH);
                scale = minScale;
                ox = (V - natW * scale) / 2;
                oy = (V - natH * scale) / 2;
                clamp();
                apply();
                ready = true;
            }
            var initRan = false;
            function init() {
                // Idempotent: only the first load event should kick off the
                // EXIF-baking pipeline. Otherwise the second load (after we
                // swap img.src to the baked URL) re-runs init(), which calls
                // finishInit() again and wipes any zoom/pan the user has done.
                if (initRan) return;
                initRan = true;

                if (srcFile && window.createImageBitmap) {
                    window.createImageBitmap(srcFile, { imageOrientation: 'from-image' })
                        .then(function(bmp){
                            canvasSource = bmp;
                            // Bake the rotation into a fresh JPEG and swap the
                            // displayed <img> over to it. This guarantees the
                            // browser's display dimensions match bmp.width/height
                            // exactly — otherwise EXIF handling can disagree
                            // between the <img> tag and createImageBitmap, and
                            // the cropper math runs against numbers that don't
                            // match what the user sees.
                            var c = document.createElement('canvas');
                            c.width = bmp.width;
                            c.height = bmp.height;
                            c.getContext('2d').drawImage(bmp, 0, 0);
                            var bakedUrl = c.toDataURL('image/jpeg', 0.95);
                            var onLoad = function() {
                                img.removeEventListener('load', onLoad);
                                finishInit(bmp.width, bmp.height);
                            };
                            img.addEventListener('load', onLoad);
                            img.src = bakedUrl;
                        })
                        .catch(function(){
                            // Fallback to img dimensions
                            finishInit(img.naturalWidth, img.naturalHeight);
                        });
                } else {
                    finishInit(img.naturalWidth, img.naturalHeight);
                }
            }
            function pt(e) {
                if (e.touches && e.touches[0]) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
                return { x: e.clientX, y: e.clientY };
            }
            function onDown(e) {
                if (!ready) return;
                dragging = true;
                var p = pt(e);
                lastX = p.x; lastY = p.y;
                stage.classList.add('is-dragging');
                e.preventDefault();
            }
            function onMove(e) {
                if (!dragging) return;
                var p = pt(e);
                ox += p.x - lastX;
                oy += p.y - lastY;
                lastX = p.x; lastY = p.y;
                clamp();
                apply();
                e.preventDefault();
            }
            function onUp() {
                dragging = false;
                stage.classList.remove('is-dragging');
            }

            if (img.complete && img.naturalWidth) init();
            else img.addEventListener('load', init, { once: true });

            stage.addEventListener('mousedown', onDown);
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            stage.addEventListener('touchstart', onDown, { passive: false });
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onUp);

            zoom.addEventListener('input', function(){
                if (!ready) return;
                var mult = parseFloat(zoom.value);
                // Zoom centered on viewport center: keep the pixel under (V/2, V/2) fixed.
                var cx = V / 2, cy = V / 2;
                var imgCx = (cx - ox) / scale;
                var imgCy = (cy - oy) / scale;
                scale = minScale * mult;
                ox = cx - imgCx * scale;
                oy = cy - imgCy * scale;
                clamp();
                apply();
            });

            return {
                isReady: function(){ return ready; },
                crop: function(outputSize) {
                    if (!ready) return null;
                    var canvas = document.createElement('canvas');
                    canvas.width = outputSize;
                    canvas.height = outputSize;
                    var ctx = canvas.getContext('2d');
                    // Source rect in image-pixel coords. natW/natH match whichever
                    // source we're drawing from (bitmap if EXIF-corrected, else img).
                    var sx = -ox / scale;
                    var sy = -oy / scale;
                    var sw = V / scale;
                    var sh = V / scale;
                    // Clamp source to image bounds (defensive against rounding).
                    if (sx < 0) sx = 0;
                    if (sy < 0) sy = 0;
                    if (sx + sw > natW) sw = natW - sx;
                    if (sy + sh > natH) sh = natH - sy;
                    if (sw <= 0 || sh <= 0) return null;

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, outputSize, outputSize);
                    try {
                        ctx.drawImage(canvasSource || img, sx, sy, sw, sh, 0, 0, outputSize, outputSize);
                    } catch (e) {
                        return null;
                    }
                    var url = canvas.toDataURL('image/jpeg', 0.92);
                    // toDataURL returns "data:," when the canvas is blank/tainted.
                    if (!url || url.length < 100 || url === 'data:,') return null;
                    return url;
                },
                destroy: function() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.removeEventListener('touchmove', onMove);
                    document.removeEventListener('touchend', onUp);
                    if (canvasSource && canvasSource.close) canvasSource.close();
                }
            };
        }

        // Preview modal with cropper. onConfirm is called with (setSaving, closeModal, croppedDataUrl).
        function showPreview(dataUrl, file, onConfirm, onCancel) {
            var overlay = document.createElement('div');
            overlay.className = 'shopys-preview-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML =
                '<div class="shopys-preview-card">' +
                    '<h2 class="shopys-preview-title"></h2>' +
                    '<p class="shopys-preview-sub"></p>' +
                    '<div class="shopys-cropper">' +
                        '<img class="shopys-cropper-img" alt="" />' +
                    '</div>' +
                    '<div class="shopys-cropper-zoom">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>' +
                        '<input type="range" min="1" max="3" step="0.01" value="1" />' +
                    '</div>' +
                    '<p class="shopys-cropper-hint"></p>' +
                    '<p class="shopys-preview-meta"></p>' +
                    '<div class="shopys-preview-actions">' +
                        '<button type="button" class="shopys-preview-btn shopys-preview-btn--cancel"></button>' +
                        '<button type="button" class="shopys-preview-btn shopys-preview-btn--save"></button>' +
                    '</div>' +
                '</div>';

            overlay.querySelector('.shopys-preview-title').textContent = TXT.prevTitle;
            overlay.querySelector('.shopys-preview-sub').textContent   = TXT.prevSub;
            overlay.querySelector('.shopys-cropper-hint').textContent  = TXT.cropHint;
            overlay.querySelector('.shopys-preview-meta').textContent  =
                (file.name || 'image') + ' · ' + formatBytes(file.size);

            var stage  = overlay.querySelector('.shopys-cropper');
            var imgEl  = overlay.querySelector('.shopys-cropper-img');
            var zoom   = overlay.querySelector('.shopys-cropper-zoom input[type=range]');
            var cancel = overlay.querySelector('.shopys-preview-btn--cancel');
            var save   = overlay.querySelector('.shopys-preview-btn--save');
            cancel.textContent = TXT.cancel;
            save.textContent   = TXT.save;
            imgEl.src = dataUrl;

            document.body.appendChild(overlay);
            requestAnimationFrame(function(){ overlay.classList.add('is-visible'); });

            var cropper = setupCropper(stage, imgEl, zoom, file);

            function close() {
                overlay.classList.remove('is-visible');
                cropper.destroy();
                setTimeout(function(){ if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 280);
            }
            function setSaving(on) {
                save.disabled = on;
                cancel.disabled = on;
                zoom.disabled = on;
                save.textContent = on ? TXT.saving : TXT.save;
            }

            cancel.addEventListener('click', function(){ close(); if (onCancel) onCancel(); });
            overlay.addEventListener('click', function(e){
                if (e.target === overlay && !save.disabled) { close(); if (onCancel) onCancel(); }
            });
            document.addEventListener('keydown', function escClose(e){
                if (e.key === 'Escape' && overlay.parentNode && !save.disabled) {
                    document.removeEventListener('keydown', escClose);
                    close(); if (onCancel) onCancel();
                }
            });
            save.addEventListener('click', function(){
                if (!cropper.isReady()) return;
                var cropped = cropper.crop(512);
                if (!cropped) {
                    showToast('error', TXT.generic);
                    return;
                }
                onConfirm(setSaving, close, cropped);
            });
        }

        // Lightbox: click avatar to enlarge.
        function showLightbox(url) {
            if (!url) return;
            var overlay = document.createElement('div');
            overlay.className = 'shopys-lightbox-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML =
                '<button type="button" class="shopys-lightbox-close" aria-label="Close">' +
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                '</button>' +
                '<img class="shopys-lightbox-img" alt="" />';
            overlay.querySelector('.shopys-lightbox-img').src = url;
            document.body.appendChild(overlay);
            requestAnimationFrame(function(){ overlay.classList.add('is-visible'); });
            function close() {
                overlay.classList.remove('is-visible');
                setTimeout(function(){ if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 250);
            }
            overlay.querySelector('.shopys-lightbox-close').addEventListener('click', close);
            overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
            document.addEventListener('keydown', function esc(e){
                if (e.key === 'Escape' && overlay.parentNode) {
                    document.removeEventListener('keydown', esc);
                    close();
                }
            });
        }

        function showToast(type, msg) {
            var stack = document.getElementById('shopys-toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'shopys-toast-stack';
                stack.style.cssText = 'position:fixed;top:24px;right:24px;z-index:100000;display:flex;flex-direction:column;gap:10px;max-width:calc(100vw - 48px);';
                document.body.appendChild(stack);
            }
            var bg = type === 'error' ? '#ef4444' : '#16a34a';
            var t = document.createElement('div');
            t.style.cssText = 'background:#fff;border-radius:12px;box-shadow:0 14px 36px rgba(15,23,42,0.14);border:1px solid rgba(15,23,42,0.06);border-left:4px solid ' + bg + ';padding:12px 16px;font-size:14px;color:#111827;min-width:240px;max-width:380px;transform:translateX(120%);opacity:0;transition:transform .32s cubic-bezier(.22,1,.36,1),opacity .2s;word-break:break-word;';
            t.textContent = msg;
            stack.appendChild(t);
            requestAnimationFrame(function(){ t.style.transform = 'translateX(0)'; t.style.opacity = '1'; });
            setTimeout(function(){
                t.style.transform = 'translateX(120%)'; t.style.opacity = '0';
                setTimeout(function(){ if (t.parentNode) t.parentNode.removeChild(t); }, 350);
            }, type === 'error' ? 8000 : 4000);
        }

        function refreshAllAvatars(newUrl) {
            var selectors = ['.shopys-avatar-img', '.sai-dash-avatar', '.shopys-cust-avatar', '.shopys-cust-dd-avatar'];
            selectors.forEach(function(sel){
                document.querySelectorAll(sel).forEach(function(img){
                    img.src = newUrl + (newUrl.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();
                });
            });
        }

        function postForm(action, body) {
            return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function(r){
                    return r.text().then(function(text){
                        var data = null;
                        try { data = JSON.parse(text); } catch(_) {}
                        return { status: r.status, data: data, raw: text };
                    });
                });
        }

        function bind(uploader) {
            if (uploader.dataset.bound === '1') return;
            uploader.dataset.bound = '1';

            var nonce  = uploader.dataset.nonce;
            var img    = uploader.querySelector('.shopys-avatar-img');
            var edit   = uploader.querySelector('.shopys-avatar-edit');
            var remove = uploader.querySelector('.shopys-avatar-remove');
            var input  = uploader.querySelector('.shopys-avatar-input');
            if (!img || !edit || !input) return;

            edit.addEventListener('click', function(){ input.click(); });

            // Click the avatar image itself to view a larger version.
            img.addEventListener('click', function(e){
                if (uploader.classList.contains('is-uploading')) return;
                showLightbox(img.src);
            });

            input.addEventListener('change', function(){
                var file = input.files && input.files[0];
                if (!file) return;
                if (!/^image\/(jpe?g|png|gif|webp)$/i.test(file.type)) {
                    showToast('error', TXT.badType); input.value = ''; return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    showToast('error', TXT.tooBig); input.value = ''; return;
                }

                var reader = new FileReader();
                reader.onerror = function(){
                    input.value = '';
                    showToast('error', TXT.reading);
                };
                reader.onload = function(ev){
                    var dataUrl = ev.target.result || '';

                    showPreview(dataUrl, file, function onConfirm(setSaving, closeModal, croppedDataUrl){
                        setSaving(true);
                        uploader.classList.add('is-uploading');

                        var fd = new URLSearchParams();
                        fd.append('action', 'shopys_upload_avatar');
                        fd.append('nonce', nonce);
                        fd.append('image', croppedDataUrl || dataUrl);

                        postForm('upload', fd)
                            .then(function(res){
                                uploader.classList.remove('is-uploading');
                                input.value = '';
                                var data = res.data;
                                if (!data || !data.success) {
                                    setSaving(false);
                                    var m = (data && data.data && data.data.msg) ? data.data.msg
                                          : (res.raw ? ('HTTP ' + res.status + ': ' + res.raw.substring(0, 200)) : TXT.generic);
                                    console.error('[shopys avatar upload]', res);
                                    showToast('error', m);
                                    return;
                                }
                                refreshAllAvatars(data.data.url);
                                showToast('success', data.data.msg);
                                closeModal();
                                if (!remove) { window.location.reload(); }
                            })
                            .catch(function(err){
                                uploader.classList.remove('is-uploading');
                                input.value = '';
                                setSaving(false);
                                console.error('[shopys avatar upload] network error:', err);
                                showToast('error', TXT.generic);
                            });
                    }, function onCancel(){
                        input.value = '';
                    });
                };
                reader.readAsDataURL(file);
            });

            if (remove) {
                remove.addEventListener('click', function(e){
                    e.stopPropagation();
                    if (!window.confirm(TXT.confirm)) return;

                    var fd = new URLSearchParams();
                    fd.append('action', 'shopys_remove_avatar');
                    fd.append('nonce', nonce);

                    uploader.classList.add('is-uploading');
                    postForm('remove', fd)
                        .then(function(res){
                            uploader.classList.remove('is-uploading');
                            var data = res.data;
                            if (!data || !data.success) {
                                var m = (data && data.data && data.data.msg) ? data.data.msg : TXT.generic;
                                showToast('error', m);
                                return;
                            }
                            refreshAllAvatars(data.data.url);
                            showToast('success', data.data.msg);
                            window.location.reload();
                        })
                        .catch(function(){
                            uploader.classList.remove('is-uploading');
                            showToast('error', TXT.generic);
                        });
                });
            }
        }

        document.querySelectorAll('.shopys-avatar-uploader').forEach(bind);
    })();
    </script>
    <?php
}
