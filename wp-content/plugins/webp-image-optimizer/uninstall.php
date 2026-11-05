<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Always removes: settings, per-image status data, the .htaccess block and
 * scheduled jobs. Removes WebP files only if "Also delete all WebP files" was
 * enabled. Original images are never touched.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'wio_uninstall_site' ) ) {
	/**
	 * Clean up the current site.
	 */
	function wio_uninstall_site() {
		global $wpdb;

		$settings = get_option( 'wio_settings', array() );
		$uploads  = wp_get_upload_dir();
		$base     = empty( $uploads['basedir'] ) ? false : realpath( $uploads['basedir'] );
		$base     = $base ? trailingslashit( wp_normalize_path( $base ) ) : '';

		if ( ! empty( $settings['delete_on_uninstall'] ) && '' !== $base ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$last = 0;
			do {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wio_status' AND post_id > %d ORDER BY post_id ASC LIMIT 200", $last ) );
				foreach ( $ids as $id ) {
					$last = (int) $id;
					$file = get_attached_file( $last, true );
					if ( ! $file ) {
						continue;
					}
					$file  = wp_normalize_path( $file );
					$dir   = dirname( $file );
					$real  = realpath( $dir );
					if ( ! $real || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $base ) ) {
						continue;
					}
					$paths = array( $file );
					$meta  = wp_get_attachment_metadata( $last, true );
					if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
						foreach ( $meta['sizes'] as $size ) {
							if ( ! empty( $size['file'] ) ) {
								$paths[] = $dir . '/' . wp_basename( $size['file'] );
							}
						}
					}
					$data = get_post_meta( $last, '_wio_data', true );
					if ( is_array( $data ) && ! empty( $data['files'] ) ) {
						foreach ( $data['files'] as $row ) {
							if ( ! empty( $row['file'] ) ) {
								$paths[] = $dir . '/' . wp_basename( $row['file'] );
							}
						}
					}
					foreach ( array_unique( $paths ) as $path ) {
						if ( preg_match( '/\.(jpe?g|png|gif)$/i', $path ) && @is_file( $path . '.webp' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
							@unlink( $path . '.webp' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						}
					}
				}
				wp_cache_flush_runtime();
			} while ( ! empty( $ids ) );
		}

		// Remove our .htaccess block from the uploads folder.
		if ( '' !== $base ) {
			$htaccess = $base . '.htaccess';
			if ( @is_file( $htaccess ) && wp_is_writable( $htaccess ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$content = (string) @file_get_contents( $htaccess ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
				if ( false !== strpos( $content, '# BEGIN WebP Image Optimizer' ) ) {
					$new = preg_replace( '~\n?# BEGIN WebP Image Optimizer.*?# END WebP Image Optimizer[^\n]*\n?~s', "\n", $content );
					if ( null !== $new ) {
						$new = trim( $new );
						if ( '' === $new ) {
							@unlink( $htaccess ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						} else {
							@file_put_contents( $htaccess, $new . "\n", LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
						}
					}
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wio_status','_wio_data','_wio_bytes_orig','_wio_bytes_new','_wio_processing')" );

		delete_option( 'wio_settings' );
		delete_option( 'wio_version' );
		delete_transient( 'wio_bulk_lock' );
		delete_transient( 'wio_stats' );
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'wio_process_attachment' );
		}
	}
}

if ( is_multisite() ) {
	$wio_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wio_sites as $wio_site ) {
		switch_to_blog( (int) $wio_site );
		wio_uninstall_site();
		restore_current_blog();
	}
} else {
	wio_uninstall_site();
}
