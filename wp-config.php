<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u554116939_uat' );

/** Database username */
define( 'DB_USER', 'u554116939_uat' );

/** Database password */
define( 'DB_PASSWORD', '0@zG&Xi0:Le' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'w-bOXsn/sh-;R3,vUR6f!FZWlM0?<3A$BQO/OrU5*Zuv=%[63qQBCzyWv.KT_7LV' );
define( 'SECURE_AUTH_KEY',   'hazzaWA#,3w:dN&r>j!hMJ|. 99.vDCg>UL{Wp/R=t<S,W9=VjiOf2QQ|hRWGOr!' );
define( 'LOGGED_IN_KEY',     'W&/vHl*y}{m$49X_5KE{e>jCfMNV%MhCq1`<n=[122>B0x1U]e:J,?8[*^.w`7v7' );
define( 'NONCE_KEY',         'X*T{Y7H_&S95KUz(_+l?ih}Ab-%w#1Y(kO,o6c_%>.lvFEEjijKzN7m|8zTh*pc/' );
define( 'AUTH_SALT',         'TJFe` ~OyRQ}]L;CS7`JTPQ&ctBTY7VTJjpeh*Ub;!}CyTD_hJ1$Fs8=m5Z:FpA=' );
define( 'SECURE_AUTH_SALT',  '=QNcl/L#Ssv`~_s/wc*a_R}_8:)Sm3hTUNl!Po>yi^hYo,Wjb)b3C3Pqh82nWrpP' );
define( 'LOGGED_IN_SALT',    '? aEP,:$K$N5Kt7uU8qRBZN#$UR82d1>474miX>EDz=Hr|oV]aM(^l-jD^)?::g}' );
define( 'NONCE_SALT',        '+^n`@FS90VY3Ye_<;L}Bg#j!H8cEhDc o|!^>:<[;Uxzj*7mxWCs^Bg.|D).*/@X' );
define( 'WP_CACHE_KEY_SALT', '`ib^PWM[2Er1C4aG%]Ry]]~;K>t|U4vDNQ>i!1}6Lv~bb@,o3Aa|a60:FCQ-Tp2}' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', 'e3eb2e4db9d32daad61b4df8a6248cce' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
