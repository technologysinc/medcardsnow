<?php
/**
 * Optional server-level delivery (Apache / LiteSpeed .htaccess in the uploads folder).
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite-rule manager.
 */
final class WIO_Rewrite {

	const MARKER = 'WebP Image Optimizer';

	/**
	 * Path of the uploads .htaccess file.
	 *
	 * @return string
	 */
	public static function htaccess_path() {
		$uploads = wp_get_upload_dir();
		return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . '.htaccess';
	}

	/**
	 * Apache-compatible server (Apache or LiteSpeed).
	 *
	 * @return bool
	 */
	public static function is_apache() {
		global $is_apache;
		return ! empty( $is_apache );
	}

	/**
	 * Nginx server.
	 *
	 * @return bool
	 */
	public static function is_nginx() {
		global $is_nginx;
		return ! empty( $is_nginx );
	}

	/**
	 * The rules. Every block is wrapped in <IfModule>, so a server missing a
	 * module simply ignores it instead of returning errors.
	 *
	 * @return string[]
	 */
	public static function rules() {
		return array(
			'<IfModule mod_mime.c>',
			'  AddType image/webp .webp',
			'</IfModule>',
			'<IfModule mod_rewrite.c>',
			'  RewriteEngine On',
			'  RewriteCond %{HTTP_ACCEPT} image/webp',
			'  RewriteCond %{REQUEST_FILENAME} -f',
			'  RewriteCond %{REQUEST_FILENAME}.webp -f',
			'  RewriteRule ^(.+)\.(jpe?g|png|gif)$ $1.$2.webp [NC,T=image/webp,L]',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'  <FilesMatch "\.(jpe?g|png|gif)(\.webp)?$">',
			'    Header append Vary Accept',
			'  </FilesMatch>',
			'</IfModule>',
		);
	}

	/**
	 * Nginx config for people on nginx (shown in the admin, never written).
	 *
	 * @return string
	 */
	public static function nginx_snippet() {
		$uploads = wp_get_upload_dir();
		$path    = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		$path    = $path ? untrailingslashit( $path ) : '/wp-content/uploads';
		return "# WebP Image Optimizer — add inside your server { } block\n"
			. "location ~* ^" . preg_quote( $path, '~' ) . "/.+\\.(jpe?g|png|gif)$ {\n"
			. "    add_header Vary Accept;\n"
			. "    set \$wio_webp \"\";\n"
			. "    if (\$http_accept ~* \"image/webp\") { set \$wio_webp \".webp\"; }\n"
			. "    try_files \$uri\$wio_webp \$uri =404;\n"
			. "}\n";
	}

	/**
	 * Make the .htaccess match the current setting.
	 *
	 * @return true|WP_Error
	 */
	public static function sync() {
		if ( 'rewrite' === WIO_Settings::get( 'delivery' ) ) {
			return self::write();
		}
		self::remove();
		return true;
	}

	/**
	 * Write rules into the uploads .htaccess.
	 *
	 * @return true|WP_Error
	 */
	public static function write() {
		if ( ! self::is_apache() ) {
			return new WP_Error( 'wio_not_apache', __( 'This server is not Apache/LiteSpeed, so no .htaccess was written.', 'webp-image-optimizer' ) );
		}
		$file = self::htaccess_path();
		if ( ( file_exists( $file ) && ! wp_is_writable( $file ) ) || ( ! file_exists( $file ) && ! wp_is_writable( dirname( $file ) ) ) ) {
			return new WP_Error( 'wio_not_writable', __( 'The uploads .htaccess file is not writable. Add the rules manually.', 'webp-image-optimizer' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		if ( ! insert_with_markers( $file, self::MARKER, self::rules() ) ) {
			return new WP_Error( 'wio_write_failed', __( 'Could not write the uploads .htaccess file.', 'webp-image-optimizer' ) );
		}
		return true;
	}

	/**
	 * Remove our block (including the marker lines) from the uploads .htaccess.
	 */
	public static function remove() {
		$file = self::htaccess_path();
		if ( ! @is_file( $file ) || ! wp_is_writable( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return;
		}
		$content = (string) @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === strpos( $content, '# BEGIN ' . self::MARKER ) ) {
			return;
		}
		$pattern = '~\n?# BEGIN ' . preg_quote( self::MARKER, '~' ) . '.*?# END ' . preg_quote( self::MARKER, '~' ) . '[^\n]*\n?~s';
		$new     = preg_replace( $pattern, "\n", $content );
		if ( null === $new ) {
			return;
		}
		$new = trim( $new );
		if ( '' === $new ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- file only ever held our rules.
			return;
		}
		@file_put_contents( $file, $new . "\n", LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}

	/**
	 * Rules are currently present.
	 *
	 * @return bool
	 */
	public static function is_installed() {
		$file = self::htaccess_path();
		if ( ! @is_file( $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		$content = (string) @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return false !== strpos( $content, '# BEGIN ' . self::MARKER ) && false !== strpos( $content, 'RewriteEngine' );
	}
}
