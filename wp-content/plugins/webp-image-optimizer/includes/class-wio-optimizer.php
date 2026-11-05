<?php
/**
 * Attachment-level processing: upload hooks, bulk queries, status, cleanup.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Optimizer.
 */
final class WIO_Optimizer {

	const META_STATUS = '_wio_status';
	const META_DATA   = '_wio_data';
	const META_ORIG   = '_wio_bytes_orig';
	const META_NEW    = '_wio_bytes_new';
	const META_LOCK   = '_wio_processing';

	/** Seconds after which a leftover processing marker means the request died. */
	const LOCK_STALE = 30;

	/**
	 * Attachments to convert once the current request has responded.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 9999, 2 );
		add_filter( 'wp_update_attachment_metadata', array( __CLASS__, 'on_update_metadata' ), 9999, 2 );
		add_filter( 'wp_delete_file', array( __CLASS__, 'on_delete_file' ), 10, 1 );
		add_action( 'wio_process_attachment', array( __CLASS__, 'cron_process' ), 10, 1 );

		add_filter( 'big_image_size_threshold', array( __CLASS__, 'filter_threshold' ), 20 );
		add_filter( 'wp_editor_set_quality', array( __CLASS__, 'filter_jpeg_quality' ), 20, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* WordPress hooks                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * After WordPress finished making thumbnails for a new upload.
	 *
	 * @param array $metadata Metadata.
	 * @param int   $id       Attachment ID.
	 * @return array
	 */
	public static function on_generate_metadata( $metadata, $id ) {
		if ( is_array( $metadata ) && ! empty( $metadata['file'] ) ) {
			self::enqueue( (int) $id );
		}
		return $metadata;
	}

	/**
	 * After an image is edited (crop/rotate/scale) in the media editor.
	 *
	 * @param array $data Metadata.
	 * @param int   $id   Attachment ID.
	 * @return array
	 */
	public static function on_update_metadata( $data, $id ) {
		if ( is_array( $data ) && doing_action( 'wp_ajax_image-editor' ) ) {
			self::enqueue( (int) $id );
		}
		return $data;
	}

	/**
	 * When WordPress deletes an image file, delete its WebP copy too.
	 *
	 * @param string $file Path being deleted.
	 * @return string Unchanged.
	 */
	public static function on_delete_file( $file ) {
		if ( is_string( $file ) && preg_match( '/\.(jpe?g|png|gif)$/i', $file ) ) {
			WIO_Converter::delete_webp( $file );
		}
		return $file;
	}

	/**
	 * Cron callback.
	 *
	 * @param int $id Attachment ID.
	 */
	public static function cron_process( $id ) {
		self::process_attachment( (int) $id, array( 'context' => 'upload' ) );
	}

	/**
	 * Optional: lower WordPress' "big image" threshold.
	 *
	 * @param int|false $threshold Threshold.
	 * @return int|false
	 */
	public static function filter_threshold( $threshold ) {
		$value = (int) WIO_Settings::get( 'max_dimension' );
		if ( 2560 === $value ) {
			return $threshold; // WordPress default: leave other plugins in control.
		}
		return $value > 0 ? $value : false;
	}

	/**
	 * Optional: JPEG quality for thumbnails WordPress generates.
	 *
	 * @param int    $quality   Quality.
	 * @param string $mime_type Mime type.
	 * @return int
	 */
	public static function filter_jpeg_quality( $quality, $mime_type = '' ) {
		$value = (int) WIO_Settings::get( 'jpeg_quality' );
		if ( 'image/jpeg' !== $mime_type || 82 === $value ) {
			return $quality;
		}
		return $value;
	}

	/* ------------------------------------------------------------------ */
	/* Queue                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Schedule conversion of an attachment according to the "on upload" setting.
	 *
	 * @param int $id Attachment ID.
	 */
	public static function enqueue( $id ) {
		$mode = WIO_Settings::get( 'on_upload' );
		if ( 'off' === $mode || ! $id || ! self::is_supported( $id ) || ! WIO_Converter::has_engine() ) {
			return;
		}

		// WP-CLI (e.g. "wp media regenerate"): no visitor is waiting, convert right away.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			self::process_attachment( $id, array( 'context' => 'upload' ) );
			return;
		}

		if ( 'cron' === $mode ) {
			if ( ! wp_next_scheduled( 'wio_process_attachment', array( $id ) ) ) {
				wp_schedule_single_event( time() + 10, 'wio_process_attachment', array( $id ) );
			}
			return;
		}

		self::$queue[ $id ] = $id;
		if ( ! has_action( 'shutdown', array( __CLASS__, 'process_queue' ) ) ) {
			// Priority 100: after WordPress has flushed the response (priority 1).
			add_action( 'shutdown', array( __CLASS__, 'process_queue' ), 100 );
		}
	}

	/**
	 * Convert queued attachments at the very end of the request.
	 *
	 * The upload response has already been sent, so the person uploading isn't
	 * kept waiting, and if anything goes wrong here the upload itself is safe.
	 */
	public static function process_queue() {
		if ( ! self::$queue ) {
			return;
		}
		$ids         = array_values( self::$queue );
		self::$queue = array();

		if ( apply_filters( 'wio_finish_request', true ) ) {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} elseif ( function_exists( 'litespeed_finish_request' ) ) {
				litespeed_finish_request();
			}
		}
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		foreach ( $ids as $id ) {
			self::process_attachment( $id, array( 'context' => 'upload' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Processing                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Mime type of an attachment is enabled.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	public static function is_supported( $id ) {
		$mime = get_post_mime_type( $id );
		return $mime && in_array( $mime, WIO_Settings::enabled_mimes(), true );
	}

	/**
	 * All local files of an attachment: the main file plus every thumbnail size.
	 *
	 * The untouched "original_image" (kept by WP when it creates "-scaled"
	 * versions) is not included: WordPress never displays it on the site.
	 *
	 * @param int $id Attachment ID.
	 * @return string[] size name => absolute path.
	 */
	public static function get_files( $id ) {
		$file = get_attached_file( $id, true );
		if ( ! $file ) {
			return array();
		}
		$file  = wp_normalize_path( $file );
		$dir   = dirname( $file );
		$files = array( 'full' => $file );

		$meta = wp_get_attachment_metadata( $id, true );
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $size ) {
				if ( empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
					continue;
				}
				if ( ! empty( $size['mime-type'] ) && ! in_array( $size['mime-type'], array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
					continue;
				}
				$path = $dir . '/' . wp_basename( $size['file'] );
				if ( ! in_array( $path, $files, true ) ) {
					$files[ (string) $name ] = $path;
				}
			}
		}
		return $files;
	}

	/**
	 * Convert every file of one attachment.
	 *
	 * @param int   $id   Attachment ID.
	 * @param array $args {
	 *     @type bool   $force   Re-create existing WebP files.
	 *     @type string $context upload | bulk | manual | cli.
	 * }
	 * @return array Summary (status, message, orig, new, files).
	 */
	public static function process_attachment( $id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'force'   => false,
				'context' => 'manual',
			)
		);
		$id   = (int) $id;
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return self::summary( 'failed', __( 'Not a media item', 'webp-image-optimizer' ) );
		}
		if ( ! self::is_supported( $id ) ) {
			return self::summary( 'unsupported', __( 'File type is not enabled for conversion', 'webp-image-optimizer' ) );
		}
		if ( apply_filters( 'wio_skip_attachment', false, $id ) ) {
			return self::summary( 'unsupported', __( 'Skipped by filter', 'webp-image-optimizer' ) );
		}
		if ( ! WIO_Converter::has_engine() ) {
			return self::summary( 'failed', __( 'No WebP-capable image library on this server', 'webp-image-optimizer' ) );
		}

		/*
		 * Crash protection. A marker is set before converting and removed after.
		 * If a marker is still there, a previous attempt died (memory/time limit).
		 * We record that and skip the image so it can never take the site or a
		 * bulk run down twice.
		 */
		$lock = (int) get_post_meta( $id, self::META_LOCK, true );
		if ( $lock ) {
			$stale = ( time() - $lock ) > self::LOCK_STALE;
			if ( 'upload' === $args['context'] && ! $stale ) {
				return self::summary( 'busy', __( 'Already being processed', 'webp-image-optimizer' ) );
			}
			delete_post_meta( $id, self::META_LOCK );
			$summary = self::summary( 'crashed', __( 'The server stopped while converting this image last time (usually a PHP memory or time limit). It was skipped to keep things stable. Try "Re-convert" after raising the memory limit.', 'webp-image-optimizer' ) );
			self::save( $id, $summary );
			// Only an explicit single "Convert now" click retries; bulk/CLI/upload move on.
			if ( 'manual' !== $args['context'] ) {
				return $summary;
			}
		}

		update_post_meta( $id, self::META_LOCK, time() );

		$quality = (int) apply_filters( 'wio_quality', (int) WIO_Settings::get( 'quality' ), $id );
		$files   = self::get_files( $id );
		$results = array();
		$engine  = '';

		foreach ( $files as $size => $path ) {
			$r                = WIO_Converter::convert_file( $path, $quality, (bool) $args['force'] );
			$results[ $size ] = array(
				'file'    => wp_basename( $path ),
				'status'  => $r['status'],
				'message' => $r['message'],
				'orig'    => $r['src_bytes'],
				'webp'    => $r['webp_bytes'],
			);
			if ( '' !== $r['engine'] ) {
				$engine = $r['engine'];
			}
		}

		delete_post_meta( $id, self::META_LOCK );

		$summary = self::summarize( $results, $quality, $engine );
		self::save( $id, $summary );

		return $summary;
	}

	/**
	 * Remove every WebP copy of an attachment and its status.
	 *
	 * @param int $id Attachment ID.
	 * @return int Number of files removed.
	 */
	public static function remove_attachment( $id ) {
		$removed = 0;
		foreach ( self::get_files( $id ) as $path ) {
			if ( WIO_Converter::delete_webp( $path ) ) {
				++$removed;
			}
		}
		// Files from older edits that may still have WebP copies.
		$data = get_post_meta( $id, self::META_DATA, true );
		if ( is_array( $data ) && ! empty( $data['files'] ) ) {
			$main = get_attached_file( $id, true );
			if ( $main ) {
				$dir = dirname( wp_normalize_path( $main ) );
				foreach ( $data['files'] as $row ) {
					if ( ! empty( $row['file'] ) && WIO_Converter::delete_webp( $dir . '/' . wp_basename( $row['file'] ) ) ) {
						++$removed;
					}
				}
			}
		}
		foreach ( array( self::META_STATUS, self::META_DATA, self::META_ORIG, self::META_NEW, self::META_LOCK ) as $key ) {
			delete_post_meta( $id, $key );
		}
		delete_transient( 'wio_stats' );
		return $removed;
	}

	/**
	 * Turn per-file results into one attachment summary.
	 *
	 * @param array  $results Per-file rows.
	 * @param int    $quality Quality used.
	 * @param string $engine  Engine used.
	 * @return array
	 */
	private static function summarize( $results, $quality, $engine ) {
		$orig     = 0;
		$new      = 0;
		$webps    = 0;
		$errors   = array();
		$notes    = array();
		$present  = 0;
		$problems = 0;

		foreach ( $results as $size => $row ) {
			if ( 'missing' === $row['status'] ) {
				// A missing thumbnail is common (deleted sizes) and harmless.
				$notes[] = $row['file'] . ': ' . $row['message'];
				continue;
			}
			++$present;
			$orig += $row['orig'];
			if ( in_array( $row['status'], array( 'converted', 'exists' ), true ) && $row['webp'] > 0 ) {
				$new += $row['webp'];
				++$webps;
			} else {
				$new += $row['orig'];
			}
			if ( 'error' === $row['status'] ) {
				++$problems;
				$errors[] = $row['file'] . ': ' . $row['message'];
			}
		}

		if ( 0 === $present ) {
			$status  = 'failed';
			$message = __( 'Image files not found on the server', 'webp-image-optimizer' );
		} elseif ( $problems > 0 && 0 === $webps ) {
			$status  = 'failed';
			$message = implode( '; ', array_slice( $errors, 0, 3 ) );
		} elseif ( $problems > 0 ) {
			$status  = 'partial';
			$message = implode( '; ', array_slice( $errors, 0, 3 ) );
		} elseif ( 0 === $webps ) {
			$status  = 'skipped';
			$message = __( 'WebP would not be smaller', 'webp-image-optimizer' );
			foreach ( $results as $row ) {
				if ( 'skipped' === $row['status'] && '' !== $row['message'] ) {
					$message = $row['message'];
					break;
				}
			}
		} else {
			$status  = 'optimized';
			$message = $notes ? implode( '; ', array_slice( $notes, 0, 3 ) ) : '';
		}

		return array(
			'status'  => $status,
			'message' => $message,
			'orig'    => $orig,
			'new'     => $new,
			'webps'   => $webps,
			'count'   => count( $results ),
			'quality' => $quality,
			'engine'  => $engine,
			'time'    => time(),
			'files'   => $results,
		);
	}

	/**
	 * Empty summary.
	 *
	 * @param string $status  Status.
	 * @param string $message Message.
	 * @return array
	 */
	private static function summary( $status, $message ) {
		return array(
			'status'  => $status,
			'message' => $message,
			'orig'    => 0,
			'new'     => 0,
			'webps'   => 0,
			'count'   => 0,
			'quality' => 0,
			'engine'  => '',
			'time'    => time(),
			'files'   => array(),
		);
	}

	/**
	 * Persist a summary.
	 *
	 * @param int   $id      Attachment ID.
	 * @param array $summary Summary.
	 */
	private static function save( $id, $summary ) {
		update_post_meta( $id, self::META_STATUS, $summary['status'] );
		update_post_meta( $id, self::META_DATA, $summary );
		update_post_meta( $id, self::META_ORIG, (int) $summary['orig'] );
		update_post_meta( $id, self::META_NEW, (int) $summary['new'] );
		delete_transient( 'wio_stats' );
	}

	/* ------------------------------------------------------------------ */
	/* Queries & stats                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * SQL IN() list of enabled mime types.
	 *
	 * @return string
	 */
	private static function mime_in_sql() {
		global $wpdb;
		$mimes = WIO_Settings::enabled_mimes();
		if ( ! $mimes ) {
			return "('')";
		}
		$parts = array();
		foreach ( $mimes as $mime ) {
			$parts[] = $wpdb->prepare( '%s', $mime );
		}
		return '(' . implode( ',', $parts ) . ')';
	}

	/**
	 * Next attachment IDs for a bulk run.
	 *
	 * @param string $mode   new | force | remove.
	 * @param int    $after  Cursor (last processed ID).
	 * @param int    $limit  Max IDs.
	 * @return int[]
	 */
	public static function next_ids( $mode, $after, $limit = 10 ) {
		global $wpdb;
		$after = (int) $after;
		$limit = max( 1, (int) $limit );

		if ( 'remove' === $mode ) {
			$sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.ID > %d
				ORDER BY p.ID ASC LIMIT %d",
				self::META_STATUS,
				$after,
				$limit
			);
		} elseif ( 'force' === $mode ) {
			$sql = $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				WHERE p.post_type = 'attachment' AND p.post_status <> 'trash'
				AND p.post_mime_type IN " . self::mime_in_sql() . ' AND p.ID > %d
				ORDER BY p.ID ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- mime list is prepared above.
				$after,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_status <> 'trash'
				AND p.post_mime_type IN " . self::mime_in_sql() . ' AND p.ID > %d AND m.post_id IS NULL
				ORDER BY p.ID ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::META_STATUS,
				$after,
				$limit
			);
		}

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * How many attachments a bulk run in this mode will touch.
	 *
	 * @param string $mode new | force | remove.
	 * @return int
	 */
	public static function count_for_mode( $mode ) {
		$stats = self::stats( true );
		if ( 'remove' === $mode ) {
			return $stats['processed'];
		}
		if ( 'force' === $mode ) {
			return $stats['total'];
		}
		return $stats['pending'];
	}

	/**
	 * Library statistics (cached for a minute).
	 *
	 * @param bool $fresh Skip the cache.
	 * @return array
	 */
	public static function stats( $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( 'wio_stats' );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		global $wpdb;
		$mime_in = self::mime_in_sql();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status <> 'trash' AND post_mime_type IN {$mime_in}"
		);

		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_status <> 'trash' AND p.post_mime_type IN {$mime_in} AND m.post_id IS NULL",
				self::META_STATUS
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS status, COUNT(*) AS n FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = %s AND p.post_type = 'attachment'
				GROUP BY m.meta_value",
				self::META_STATUS
			),
			ARRAY_A
		);

		$orig = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value + 0) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_ORIG ) );
		$new  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(meta_value + 0) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_NEW ) );
		// phpcs:enable

		$by_status = array(
			'optimized' => 0,
			'partial'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'crashed'   => 0,
		);
		$processed = 0;
		foreach ( (array) $rows as $row ) {
			$by_status[ $row['status'] ] = (int) $row['n'];
			$processed                  += (int) $row['n'];
		}

		$stats = array(
			'total'     => $total,
			'pending'   => $pending,
			'processed' => $processed,
			'status'    => $by_status,
			'orig'      => $orig,
			'new'       => $new,
			'saved'     => max( 0, $orig - $new ),
			'percent'   => $orig > 0 ? round( ( $orig - $new ) / $orig * 100, 1 ) : 0,
		);
		set_transient( 'wio_stats', $stats, MINUTE_IN_SECONDS );
		return $stats;
	}
}
