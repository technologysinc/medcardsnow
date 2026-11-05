<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$upload = wp_upload_dir( null, false );
$dir    = trailingslashit( $upload['basedir'] ) . 'code-sentinel';

if ( is_dir( $dir ) ) {
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
	}
	@rmdir( $dir );
}

$loader = WPMU_PLUGIN_DIR . '/code-sentinel-loader.php';
if ( file_exists( $loader ) ) {
	@unlink( $loader );
}

delete_option( 'cs_settings' );
delete_option( 'cs_ignored' );
delete_option( 'cs_last_summary' );
wp_clear_scheduled_hook( 'cs_daily_scan' );
