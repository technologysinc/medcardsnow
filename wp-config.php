<?php
define( 'WP_CACHE', true );

 // Added by WP Rocket
 // Added by WP Rocket

 // Added by WP Rocket




 // Added by WP Rocket




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
define( 'DB_NAME', 'u440320247_3yUwO' );

/** Database username */
define( 'DB_USER', 'u440320247_HtFh2' );

/** Database password */
define( 'DB_PASSWORD', '0chPWIgWiE' );

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
define( 'AUTH_KEY',          '-V@Gh-1m4GIU%b^Bnjn-r;k%v/>F4ifZ$+AI=~k@[1+~1~ndS~VD@e8am%;|d~)+' );
define( 'SECURE_AUTH_KEY',   '1?V$`4BWv/9W-,w)X?Ix;O~Wz6]U}34dIs}$zqsRo]+86v1uUnC:Do6q<vRL!?2F' );
define( 'LOGGED_IN_KEY',     'H)[Rv*[Ag8n5f0CDRF:LVn~f#C:N=9S +c2!G~U&~R{<4UWo:Eo|!n6dh3aXcQ.F' );
define( 'NONCE_KEY',         'C64l+yNR|[,LSFr8As~37V.x*RuiCxkYnd|8Y4]3E$HJuvxO//j<x1<^2bcQn,1,' );
define( 'AUTH_SALT',         '6]R!k.O$e@@OOk 6w/BLX.]@|oo=|u5u.x):1!*)gyTf(*K|ouWLU=R~Ky<9*=P ' );
define( 'SECURE_AUTH_SALT',  '^yGa?>)rhSAMrB<R27OLAN1_fa`)#v0n^<ayin1W$GVK Q[|EAOz,=1Sz9,F?$u6' );
define( 'LOGGED_IN_SALT',    'asm*-S(H9(ra_$[|{aM>Yahoo$V_a?|_f[Csj!#D>n#Y$@}ROW6wcxfwEJ)^xv~[' );
define( 'NONCE_SALT',        '#L2^s>+HACJa>N=C11r#:UxjEe{6E4jY8Tri6bpG, .[MFVk*2{.(fy99iqW/K+U' );
define( 'WP_CACHE_KEY_SALT', 'bt:&;pOAl|dO)-DL(dh{nO^G|M)kXX=iG;EtL?[ME9t;-*0ez2iEYG4q}>;AY$(8' );


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
}

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);


define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', 'ad39001f838486799f36f4db72d08dd1' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';