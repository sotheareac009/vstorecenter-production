<?php
/**
 * Custom Admin Login Page — served at /vstore-admin/
 * @package Shopys
 */

// Already logged in → go straight to dashboard
if ( function_exists('is_user_logged_in') && is_user_logged_in() ) {
    wp_redirect( admin_url() );
    exit;
}

$error   = '';
$success = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['log'], $_POST['pwd'] ) ) {
    $creds = array(
        'user_login'    => sanitize_text_field( $_POST['log'] ),
        'user_password' => $_POST['pwd'],
        'remember'      => isset( $_POST['rememberme'] ),
    );
    $user = wp_signon( $creds, false );
    if ( is_wp_error( $user ) ) {
        $error = 'Incorrect username or password. Please try again.';
    } else {
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $creds['remember'] );
        do_action( 'wp_login', $user->user_login, $user );
        wp_safe_redirect( admin_url() );
        exit;
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — <?php bloginfo('name'); ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100vh;
    background: #0d1117;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    padding: 20px;
}

/* Background glow */
body::before {
    content: '';
    position: fixed;
    top: -200px; left: 50%;
    transform: translateX(-50%);
    width: 600px; height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(19,232,0,.12) 0%, transparent 70%);
    pointer-events: none;
}

.login-card {
    background: #111820;
    border: 1px solid #1e2d1e;
    border-radius: 16px;
    padding: 44px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 24px 64px rgba(0,0,0,.5);
    position: relative;
    z-index: 1;
}

.login-logo {
    text-align: center;
    margin-bottom: 32px;
}
.login-logo img {
    height: 56px;
    width: auto;
}
.login-logo h1 {
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    margin-top: 12px;
    letter-spacing: -.3px;
}
.login-logo span { color: #13e800; }
.login-logo p {
    color: #6b7280;
    font-size: 13px;
    margin-top: 6px;
}

.login-error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: #fca5a5;
    font-size: 13px;
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    color: #9ca3af;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .8px;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.form-group input {
    width: 100%;
    background: #0d1117;
    border: 1.5px solid #1e2d1e;
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    padding: 11px 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.form-group input:focus {
    border-color: #13e800;
    box-shadow: 0 0 0 3px rgba(19,232,0,.12);
}
.form-group input::placeholder { color: #374151; }

.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    margin-top: 4px;
}
.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 13px;
    cursor: pointer;
}
.remember input[type="checkbox"] {
    accent-color: #13e800;
    width: 15px; height: 15px;
    cursor: pointer;
}

.btn-login {
    width: 100%;
    background: #13e800;
    color: #000;
    font-weight: 800;
    font-size: 15px;
    padding: 13px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background .2s, transform .15s, box-shadow .2s;
    letter-spacing: .3px;
}
.btn-login:hover {
    background: #0fb500;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(19,232,0,.3);
}
.btn-login:active { transform: translateY(0); }

.login-footer {
    text-align: center;
    margin-top: 28px;
    color: #374151;
    font-size: 12px;
}
.login-footer a {
    color: #4b5563;
    text-decoration: none;
}
.login-footer a:hover { color: #13e800; }
</style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <?php
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        if ( $logo_url ) : ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo('name'); ?>">
        <?php endif; ?>
        <h1><?php bloginfo('name'); ?></h1>
        <p>Admin Access Only</p>
    </div>

    <?php if ( $error ) : ?>
    <div class="login-error"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php wp_nonce_field( 'vstore_admin_login', 'vstore_nonce' ); ?>

        <div class="form-group">
            <label for="log">Username or Email</label>
            <input type="text" id="log" name="log" placeholder="Enter your username" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
            <label for="pwd">Password</label>
            <input type="password" id="pwd" name="pwd" placeholder="Enter your password" autocomplete="current-password" required>
        </div>

        <div class="form-row">
            <label class="remember">
                <input type="checkbox" name="rememberme" value="forever">
                Remember me
            </label>
        </div>

        <button type="submit" class="btn-login">Login to Dashboard</button>
    </form>

    <div class="login-footer">
        <a href="<?php echo esc_url( home_url('/') ); ?>">&larr; Back to store</a>
    </div>
</div>

</body>
</html>
