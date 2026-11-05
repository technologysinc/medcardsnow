<?php
/**
 * WP-CLI commands: wp webp-optimizer <bulk|stats|remove|status>
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert images to WebP from the command line.
 */
class WIO_CLI {

	/**
	 * Convert images to WebP.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Re-convert images that were already converted.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated attachment IDs to convert.
	 *
	 * [--limit=<n>]
	 * : Stop after this many images.
	 *
	 * ## EXAMPLES
	 *
	 *     wp webp-optimizer bulk
	 *     wp webp-optimizer bulk --force
	 *     wp webp-optimizer bulk --ids=12,15
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function bulk( $args, $assoc_args ) {
		if ( ! WIO_Converter::has_engine() ) {
			WP_CLI::error( 'No WebP-capable image library (Imagick or GD with WebP) on this server.' );
		}
		$force = ! empty( $assoc_args['force'] );
		$limit = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 0;
		$mode  = $force ? 'force' : 'new';

		if ( ! empty( $assoc_args['ids'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', $assoc_args['ids'] ) ) );
			$this->run( $ids, $force );
			return;
		}

		$total = WIO_Optimizer::count_for_mode( $mode );
		if ( $limit ) {
			$total = min( $total, $limit );
		}
		if ( ! $total ) {
			WP_CLI::success( 'Nothing to convert.' );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Converting', $total );
		$cursor   = 0;
		$count    = 0;
		$saved    = 0;
		$failed   = 0;

		while ( true ) {
			$ids = WIO_Optimizer::next_ids( $mode, $cursor, 50 );
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				$r = WIO_Optimizer::process_attachment(
					$id,
					array(
						'force'   => $force,
						'context' => 'bulk',
					)
				);
				$saved += max( 0, $r['orig'] - $r['new'] );
				if ( in_array( $r['status'], array( 'failed', 'crashed' ), true ) ) {
					++$failed;
					WP_CLI::warning( sprintf( '#%d: %s', $id, $r['message'] ) );
				}
				$cursor = $id;
				++$count;
				$progress->tick();
				if ( $limit && $count >= $limit ) {
					break 2;
				}
			}
			$this->free_memory();
		}
		$progress->finish();

		WP_CLI::success( sprintf( '%d images processed, %s saved, %d failed.', $count, size_format( $saved, 1 ), $failed ) );
	}

	/**
	 * Show library statistics.
	 *
	 * @subcommand stats
	 */
	public function stats() {
		$s = WIO_Optimizer::stats( true );
		WP_CLI::log( sprintf( 'Images:     %d', $s['total'] ) );
		WP_CLI::log( sprintf( 'Converted:  %d', $s['status']['optimized'] + $s['status']['partial'] ) );
		WP_CLI::log( sprintf( 'Waiting:    %d', $s['pending'] ) );
		WP_CLI::log( sprintf( 'Skipped:    %d', $s['status']['skipped'] ) );
		WP_CLI::log( sprintf( 'Failed:     %d', $s['status']['failed'] + $s['status']['crashed'] ) );
		WP_CLI::log( sprintf( 'Saved:      %s (%s%%)', size_format( $s['saved'], 1 ) ? size_format( $s['saved'], 1 ) : '0 B', $s['percent'] ) );
		$engines = WIO_Converter::engines();
		WP_CLI::log( 'Engine:     ' . ( $engines ? implode( ' > ', $engines ) : 'none' ) );
	}

	/**
	 * Delete every WebP copy created by the plugin (originals are untouched).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function remove( $args, $assoc_args ) {
		WP_CLI::confirm( 'Delete all WebP copies?', $assoc_args );
		$cursor = 0;
		$files  = 0;
		$items  = 0;
		while ( true ) {
			$ids = WIO_Optimizer::next_ids( 'remove', $cursor, 100 );
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				$files += WIO_Optimizer::remove_attachment( $id );
				$cursor = $id;
				++$items;
			}
			$this->free_memory();
		}
		WP_CLI::success( sprintf( 'Removed %d WebP files from %d images.', $files, $items ) );
	}

	/**
	 * Show the WebP status of one image.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Attachment ID.
	 *
	 * @param array $args Positional args.
	 */
	public function status( $args ) {
		$id   = absint( $args[0] );
		$data = get_post_meta( $id, WIO_Optimizer::META_DATA, true );
		if ( ! is_array( $data ) ) {
			WP_CLI::log( 'Not converted yet.' );
			return;
		}
		WP_CLI::log( sprintf( 'Status: %s %s', $data['status'], $data['message'] ? '(' . $data['message'] . ')' : '' ) );
		$rows = array();
		foreach ( (array) $data['files'] as $size => $row ) {
			$rows[] = array(
				'size'   => $size,
				'file'   => $row['file'],
				'status' => $row['status'],
				'orig'   => size_format( $row['orig'] ),
				'webp'   => $row['webp'] ? size_format( $row['webp'] ) : '-',
				'note'   => $row['message'],
			);
		}
		if ( $rows ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'size', 'file', 'status', 'orig', 'webp', 'note' ) );
		}
	}

	/**
	 * Convert specific IDs.
	 *
	 * @param int[] $ids   IDs.
	 * @param bool  $force Force.
	 */
	private function run( $ids, $force ) {
		foreach ( $ids as $id ) {
			$r = WIO_Optimizer::process_attachment(
				$id,
				array(
					'force'   => $force,
					'context' => 'manual',
				)
			);
			WP_CLI::log( sprintf( '#%d %s — %s → %s %s', $id, $r['status'], size_format( $r['orig'] ), size_format( $r['new'] ), $r['message'] ) );
		}
	}

	/**
	 * Keep memory flat on long runs.
	 */
	private function free_memory() {
		wp_cache_flush_runtime();
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}
}
