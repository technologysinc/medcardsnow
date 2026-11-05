<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'O30s8$hgqt!7!Q:zIv(c#q7TLI_NvDQnXA1=!ff:Sb`K#+[d`[)42%:XRjwkso{D' );
define( 'SECURE_AUTH_KEY',  'PR;`B %yra)=Tqb0)`}ife+J~w77]~75yOB9N?)[vw.S:h XY9h4:ej$$F]L`C=&' );
define( 'LOGGED_IN_KEY',    '$vY21L%;v)7YmgL[jh]2Wv/Wy>r5+;s]1>{)<8oGoE55KiDKlEj1JH)bY>7*}Rp?' );
define( 'NONCE_KEY',        'u*vIa`fzdukUF---y_X[wI:[D[nOGI8U=X_+UV(!2i<_s0|pXg,IuXyCb2b4hcU]' );
define( 'AUTH_SALT',        'q{Q-p}AsPA<P[XUKf7qybpZ|5}W+_J&}HIEY=IQhof}>%ehUG13[s.FI O)-aFOv' );
define( 'SECURE_AUTH_SALT', 'I-qO+<z8,;_=_|7)Pj?uh!0;eAoNlCyt9^VzWB57;,:a?i^+J2CN#&|7yD[H $H2' );
define( 'LOGGED_IN_SALT',   '{*$;S<l_JR{wq1|D,]p8Xq wiPY&%gItCpR+cgl%{=mQluEzNTWw>gN|W>-Gz i ' );
define( 'NONCE_SALT',       '~;gD+9y9Ra`RlBTI);W!ul=c^.B,,LeZQ|V;5(<F53}Ekk]J^qgkJK#sRVSBo-o~' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
