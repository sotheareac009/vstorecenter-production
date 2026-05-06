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

        // 5 MB cap on the decoded image
        if ( strlen( $bytes ) > 5 * 1024 * 1024 ) {
            wp_send_json_error( array( 'msg' => __( 'Image must be smaller than 5 MB.', 'shopys' ) ) );
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
    </style>
    <script>
    (function(){
        var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var TXT = {
            tooBig:  <?php echo wp_json_encode( __( 'Image must be smaller than 5 MB.', 'shopys' ) ); ?>,
            badType: <?php echo wp_json_encode( __( 'Please choose a JPG, PNG, GIF, or WebP image.', 'shopys' ) ); ?>,
            confirm: <?php echo wp_json_encode( __( 'Remove your profile picture?', 'shopys' ) ); ?>,
            generic: <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'shopys' ) ); ?>,
            reading: <?php echo wp_json_encode( __( 'Could not read the file.', 'shopys' ) ); ?>
        };

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

            input.addEventListener('change', function(){
                var file = input.files && input.files[0];
                if (!file) return;
                if (!/^image\/(jpe?g|png|gif|webp)$/i.test(file.type)) {
                    showToast('error', TXT.badType); input.value = ''; return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast('error', TXT.tooBig); input.value = ''; return;
                }

                uploader.classList.add('is-uploading');

                var reader = new FileReader();
                reader.onerror = function(){
                    uploader.classList.remove('is-uploading');
                    input.value = '';
                    showToast('error', TXT.reading);
                };
                reader.onload = function(ev){
                    var dataUrl = ev.target.result || '';
                    var fd = new URLSearchParams();
                    fd.append('action', 'shopys_upload_avatar');
                    fd.append('nonce', nonce);
                    fd.append('image', dataUrl);

                    postForm('upload', fd)
                        .then(function(res){
                            uploader.classList.remove('is-uploading');
                            input.value = '';
                            var data = res.data;
                            if (!data || !data.success) {
                                var m = (data && data.data && data.data.msg) ? data.data.msg
                                      : (res.raw ? ('HTTP ' + res.status + ': ' + res.raw.substring(0, 200)) : TXT.generic);
                                console.error('[shopys avatar upload]', res);
                                showToast('error', m);
                                return;
                            }
                            refreshAllAvatars(data.data.url);
                            showToast('success', data.data.msg);
                            if (!remove) { window.location.reload(); }
                        })
                        .catch(function(err){
                            uploader.classList.remove('is-uploading');
                            input.value = '';
                            console.error('[shopys avatar upload] network error:', err);
                            showToast('error', TXT.generic);
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
