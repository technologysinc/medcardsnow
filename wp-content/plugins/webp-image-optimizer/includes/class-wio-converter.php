<?php
/**
 * Image -> WebP conversion engine (Imagick with GD fallback).
 *
 * Design rules that keep this stable:
 *  - The source file is only ever read, never written.
 *  - Output is written to a temp file in the same folder and renamed into
 *    place only after it has been validated, so a half-written WebP can never
 *    be served.
 *  - A WebP that is not smaller than the original is thrown away.
 *  - Memory is estimated before GD loads an image, so huge images are
 *    skipped instead of crashing PHP.
 *  - Animated GIF/PNG and CMYK JPEG (GD) are skipped instead of producing a
 *    wrong-looking result.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converter.
 */
final class WIO_Converter {

	/** Hard ceiling on image size, in pixels (100 megapixels). */
	const MAX_PIXELS = 100000000;

	/**
	 * Temp file currently being written, removed on shutdown if PHP dies.
	 *
	 * @var string
	 */
	private static $current_tmp = '';

	/**
	 * Whether the shutdown cleaner is registered.
	 *
	 * @var bool
	 */
	private static $cleaner = false;

	/**
	 * Cached upload base dir (real path).
	 *
	 * @var string|null
	 */
	private static $base_real = null;

	/* ------------------------------------------------------------------ */
	/* Engine detection                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Imagick is loaded and can write WebP.
	 *
	 * @return bool
	 */
	public static function imagick_ok() {
		static $ok = null;
		if ( null !== $ok ) {
			return $ok;
		}
		$ok = false;
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			try {
				$formats = Imagick::queryFormats( 'WEBP' );
				$ok      = ! empty( $formats );
			} catch ( Throwable $e ) {
				$ok = false;
			}
		}
		return $ok;
	}

	/**
	 * GD is loaded and can write WebP.
	 *
	 * @return bool
	 */
	public static function gd_ok() {
		static $ok = null;
		if ( null !== $ok ) {
			return $ok;
		}
		$ok = false;
		if ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' ) && function_exists( 'gd_info' ) ) {
			$info = gd_info();
			$ok   = ! empty( $info['WebP Support'] );
		}
		return $ok;
	}

	/**
	 * Engines to try, in order.
	 *
	 * @return string[]
	 */
	public static function engines() {
		$pref  = WIO_Settings::get( 'engine' );
		$order = ( 'gd' === $pref ) ? array( 'gd', 'imagick' ) : array( 'imagick', 'gd' );
		$list  = array();
		foreach ( $order as $engine ) {
			if ( ( 'imagick' === $engine && self::imagick_ok() ) || ( 'gd' === $engine && self::gd_ok() ) ) {
				$list[] = $engine;
			}
		}
		return $list;
	}

	/**
	 * Any engine available.
	 *
	 * @return bool
	 */
	public static function has_engine() {
		return (bool) self::engines();
	}

	/**
	 * Human readable engine summary for the settings page.
	 *
	 * @return array[] Each: name, ok, detail.
	 */
	public static function engine_report() {
		$report = array();

		$imagick_detail = __( 'Not installed', 'webp-image-optimizer' );
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			try {
				$v              = Imagick::getVersion();
				$imagick_detail = isset( $v['versionString'] ) ? $v['versionString'] : 'Imagick';
				if ( ! self::imagick_ok() ) {
					$imagick_detail .= ' — ' . __( 'no WebP support', 'webp-image-optimizer' );
				}
			} catch ( Throwable $e ) {
				$imagick_detail = __( 'Installed but not working', 'webp-image-optimizer' );
			}
		}
		$report[] = array(
			'name'   => 'Imagick',
			'ok'     => self::imagick_ok(),
			'detail' => $imagick_detail,
		);

		$gd_detail = __( 'Not installed', 'webp-image-optimizer' );
		if ( function_exists( 'gd_info' ) ) {
			$info      = gd_info();
			$gd_detail = 'GD ' . ( isset( $info['GD Version'] ) ? $info['GD Version'] : '' );
			if ( ! self::gd_ok() ) {
				$gd_detail .= ' — ' . __( 'no WebP support', 'webp-image-optimizer' );
			}
		}
		$report[] = array(
			'name'   => 'GD',
			'ok'     => self::gd_ok(),
			'detail' => $gd_detail,
		);

		return $report;
	}

	/* ------------------------------------------------------------------ */
	/* Public conversion API                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Path of the WebP copy for a source file.
	 *
	 * @param string $source Source path.
	 * @return string
	 */
	public static function webp_path( $source ) {
		return $source . '.webp';
	}

	/**
	 * Convert one file.
	 *
	 * Result statuses:
	 *   converted – a smaller WebP was written
	 *   exists    – an up-to-date WebP already exists (not forced)
	 *   larger    – WebP would be bigger than the original, so none is kept
	 *   skipped   – image type/feature we deliberately don't convert
	 *   missing   – source file not found
	 *   error     – conversion failed
	 *
	 * @param string $source  Absolute source path.
	 * @param int    $quality 1-100.
	 * @param bool   $force   Re-create even if an up-to-date WebP exists.
	 * @return array{status:string,message:string,src_bytes:int,webp_bytes:int,engine:string}
	 */
	public static function convert_file( $source, $quality, $force = false ) {
		$source = wp_normalize_path( $source );
		$dest   = self::webp_path( $source );

		if ( ! @is_file( $source ) || ! @is_readable( $source ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return self::result( 'missing', __( 'File not found', 'webp-image-optimizer' ) );
		}
		if ( ! self::path_is_safe( $source ) ) {
			return self::result( 'error', __( 'File is outside the uploads folder', 'webp-image-optimizer' ) );
		}

		clearstatcache( true, $source );
		$src_bytes = (int) @filesize( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( ! $force && @is_file( $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			clearstatcache( true, $dest );
			if ( (int) @filemtime( $dest ) >= (int) @filemtime( $source ) && @filesize( $dest ) > 0 ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return self::result( 'exists', '', $src_bytes, (int) filesize( $dest ) );
			}
		}

		$info = @getimagesize( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
			return self::result( 'error', __( 'Not a readable image', 'webp-image-optimizer' ), $src_bytes );
		}
		list( $width, $height, $type ) = $info;

		if ( ! in_array( $type, array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF ), true ) ) {
			return self::result( 'skipped', __( 'Not a JPEG, PNG or GIF', 'webp-image-optimizer' ), $src_bytes );
		}
		if ( $width > 16383 || $height > 16383 ) {
			return self::result( 'skipped', __( 'Dimensions exceed the WebP limit (16383 px)', 'webp-image-optimizer' ), $src_bytes );
		}
		if ( ( $width * $height ) > self::MAX_PIXELS ) {
			return self::result( 'skipped', __( 'Image is too large to convert safely', 'webp-image-optimizer' ), $src_bytes );
		}
		if ( IMAGETYPE_GIF === $type && self::is_animated_gif( $source ) ) {
			return self::result( 'skipped', __( 'Animated GIF (kept as GIF so it still animates)', 'webp-image-optimizer' ), $src_bytes );
		}
		if ( IMAGETYPE_PNG === $type && self::is_animated_png( $source ) ) {
			return self::result( 'skipped', __( 'Animated PNG (kept as PNG so it still animates)', 'webp-image-optimizer' ), $src_bytes );
		}

		$dir = dirname( $dest );
		if ( ! wp_is_writable( $dir ) ) {
			return self::result( 'error', __( 'Folder is not writable', 'webp-image-optimizer' ), $src_bytes );
		}

		$quality = max( 1, min( 100, (int) $quality ) );
		$tmp     = $dir . '/.wio-' . wp_generate_password( 10, false, false ) . '.tmp';
		self::track_tmp( $tmp );

		// Try each available engine; the next one is a fallback if the first fails.
		$errors = array();
		$skips  = 0;
		$used   = '';
		foreach ( self::engines() as $engine ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( 'imagick' === $engine ) {
				$res = self::imagick_convert( $source, $tmp, $quality );
			} else {
				$res = self::gd_convert( $source, $tmp, $type, $width, $height, $quality, isset( $info['channels'] ) ? (int) $info['channels'] : 3 );
			}

			if ( true === $res && self::is_valid_webp( $tmp ) ) {
				$used = $engine;
				break;
			}

			if ( true === $res ) {
				$res = __( 'output failed validation', 'webp-image-optimizer' );
			}
			$res = (string) $res;
			if ( 0 === strpos( $res, 'SKIP:' ) ) {
				++$skips;
				$res = trim( substr( $res, 5 ) );
			}
			$errors[] = ( 'gd' === $engine ? 'GD' : 'Imagick' ) . ': ' . $res;
		}

		if ( '' === $used ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			self::track_tmp( '' );
			if ( ! $errors ) {
				return self::result( 'error', __( 'No WebP-capable image library on this server', 'webp-image-optimizer' ), $src_bytes );
			}
			// Every engine declined on purpose (e.g. not enough memory) -> "skipped", not "error".
			$status = ( count( $errors ) === $skips ) ? 'skipped' : 'error';
			return self::result( $status, implode( '; ', $errors ), $src_bytes );
		}

		clearstatcache( true, $tmp );
		$webp_bytes = (int) filesize( $tmp );

		if ( $webp_bytes >= $src_bytes ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- remove any stale copy.
			self::track_tmp( '' );
			return self::result( 'larger', __( 'WebP was not smaller, original kept', 'webp-image-optimizer' ), $src_bytes, 0, $used );
		}

		if ( ! self::move_into_place( $tmp, $dest ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			self::track_tmp( '' );
			return self::result( 'error', __( 'Could not save the WebP file', 'webp-image-optimizer' ), $src_bytes, 0, $used );
		}
		self::track_tmp( '' );

		// Match the permissions WordPress uses for uploaded files.
		$stat = @stat( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $stat ) {
			@chmod( $dest, $stat['mode'] & 0000666 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		return self::result( 'converted', '', $src_bytes, $webp_bytes, $used );
	}

	/**
	 * Delete the WebP copy of a source file (if it exists and is ours to delete).
	 *
	 * @param string $source Source path.
	 * @return bool True if a file was removed.
	 */
	public static function delete_webp( $source ) {
		$dest = self::webp_path( wp_normalize_path( $source ) );
		if ( @is_file( $dest ) && self::path_is_safe( $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return @unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		return false;
	}

	/**
	 * True if a path sits inside this site's uploads folder.
	 *
	 * @param string $path Path to check.
	 * @return bool
	 */
	public static function path_is_safe( $path ) {
		if ( null === self::$base_real ) {
			$uploads         = wp_get_upload_dir();
			$real            = empty( $uploads['basedir'] ) ? false : realpath( $uploads['basedir'] );
			self::$base_real = $real ? trailingslashit( wp_normalize_path( $real ) ) : '';
		}
		if ( '' === self::$base_real ) {
			return false;
		}
		$dir = realpath( dirname( $path ) );
		if ( ! $dir ) {
			return false;
		}
		$dir = trailingslashit( wp_normalize_path( $dir ) );
		return 0 === strpos( $dir, self::$base_real );
	}

	/**
	 * Reset cached upload dir (multisite switch_to_blog, tests).
	 */
	public static function reset_cache() {
		self::$base_real = null;
	}

	/* ------------------------------------------------------------------ */
	/* Engines                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Convert with GD.
	 *
	 * @param string $source   Source.
	 * @param string $tmp      Temp destination.
	 * @param int    $type     IMAGETYPE_* constant.
	 * @param int    $width    Width.
	 * @param int    $height   Height.
	 * @param int    $quality  Quality.
	 * @param int    $channels Channel count from getimagesize.
	 * @return true|string True or error message ("SKIP:" prefix = deliberate skip).
	 */
	private static function gd_convert( $source, $tmp, $type, $width, $height, $quality, $channels ) {
		if ( IMAGETYPE_JPEG === $type && 4 === $channels ) {
			return 'SKIP: ' . __( 'CMYK JPEG needs Imagick for correct colors', 'webp-image-optimizer' );
		}

		$check = self::gd_memory_check( $width, $height );
		if ( true !== $check ) {
			return 'SKIP: ' . $check;
		}

		$img = false;
		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$img = @imagecreatefromjpeg( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			case IMAGETYPE_PNG:
				$img = @imagecreatefrompng( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
			case IMAGETYPE_GIF:
				$img = @imagecreatefromgif( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				break;
		}
		if ( ! $img ) {
			return __( 'GD could not read the image', 'webp-image-optimizer' );
		}

		try {
			if ( ! imageistruecolor( $img ) ) {
				if ( ! function_exists( 'imagepalettetotruecolor' ) || ! imagepalettetotruecolor( $img ) ) {
					imagedestroy( $img );
					return __( 'GD could not convert the palette image', 'webp-image-optimizer' );
				}
			}

			if ( IMAGETYPE_JPEG !== $type ) {
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
			} else {
				$img = self::gd_apply_orientation( $img, self::jpeg_orientation( $source ) );
				if ( ! $img ) {
					return __( 'GD could not rotate the image', 'webp-image-optimizer' );
				}
			}

			$ok = @imagewebp( $img, $tmp, $quality ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		} catch ( Throwable $e ) {
			$ok = false;
		}

		if ( is_resource( $img ) || ( is_object( $img ) && $img instanceof GdImage ) ) {
			imagedestroy( $img );
		}

		if ( ! $ok ) {
			return __( 'GD failed to write WebP', 'webp-image-optimizer' );
		}

		// Old libgd versions forget the RIFF padding byte on odd-sized output.
		clearstatcache( true, $tmp );
		$size = (int) @filesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size > 0 && 1 === $size % 2 ) {
			file_put_contents( $tmp, "\0", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return true;
	}

	/**
	 * Make sure GD will have enough memory; raise the limit like WP core does.
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 * @return true|string True or reason.
	 */
	private static function gd_memory_check( $width, $height ) {
		wp_raise_memory_limit( 'image' );

		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limit <= 0 ) {
			return true; // -1 = unlimited.
		}

		// ~4 bytes/px for the GD canvas, ~2.5 bytes/px for libwebp work buffers, plus headroom.
		$needed    = (int) ( $width * $height * 6.5 ) + 16 * MB_IN_BYTES;
		$available = $limit - memory_get_usage( true );

		if ( $needed > $available ) {
			/* translators: %s: amount of memory, e.g. "120 MB". */
			return sprintf( __( 'Not enough PHP memory for this image (needs about %s)', 'webp-image-optimizer' ), size_format( $needed ) );
		}
		return true;
	}

	/**
	 * Rotate/flip a GD image according to EXIF orientation (same logic as WP core).
	 *
	 * @param resource|GdImage $img         Image.
	 * @param int              $orientation EXIF orientation 1-8.
	 * @return resource|GdImage|false
	 */
	private static function gd_apply_orientation( $img, $orientation ) {
		if ( $orientation <= 1 || $orientation > 8 ) {
			return $img;
		}

		$rotate = function ( $im, $angle ) {
			$rotated = imagerotate( $im, $angle, 0 );
			if ( false === $rotated ) {
				return false;
			}
			imagedestroy( $im );
			return $rotated;
		};

		switch ( $orientation ) {
			case 2:
				imageflip( $img, IMG_FLIP_HORIZONTAL );
				break;
			case 3:
				imageflip( $img, IMG_FLIP_BOTH );
				break;
			case 4:
				imageflip( $img, IMG_FLIP_VERTICAL );
				break;
			case 5:
				$img = $rotate( $img, 90 );
				if ( $img ) {
					imageflip( $img, IMG_FLIP_VERTICAL );
				}
				break;
			case 6:
				$img = $rotate( $img, 270 );
				break;
			case 7:
				$img = $rotate( $img, 90 );
				if ( $img ) {
					imageflip( $img, IMG_FLIP_HORIZONTAL );
				}
				break;
			case 8:
				$img = $rotate( $img, 90 );
				break;
		}
		return $img;
	}

	/**
	 * Convert with Imagick.
	 *
	 * @param string $source  Source.
	 * @param string $tmp     Temp destination.
	 * @param int    $quality Quality.
	 * @return true|string
	 */
	private static function imagick_convert( $source, $tmp, $quality ) {
		$im = null;
		$fh = null;
		try {
			$im = new Imagick();

			// Read from a stream so unusual filenames (e.g. "photo[1].jpg") are never
			// interpreted as ImageMagick frame/format syntax.
			$fh = fopen( $source, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! $fh ) {
				return __( 'Could not open file', 'webp-image-optimizer' );
			}
			$im->readImageFile( $fh );
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$fh = null;

			if ( $im->getNumberImages() > 1 ) {
				$im->setFirstIterator();
				$first = $im->getImage();
				$im->clear();
				$im = $first;
			}

			self::imagick_apply_orientation( $im );

			$is_cmyk = defined( 'Imagick::COLORSPACE_CMYK' ) && $im->getImageColorspace() === Imagick::COLORSPACE_CMYK;
			$icc     = '';
			if ( ! $is_cmyk ) {
				try {
					$profiles = $im->getImageProfiles( 'icc', true );
					$icc      = isset( $profiles['icc'] ) ? $profiles['icc'] : '';
				} catch ( Throwable $e ) {
					$icc = '';
				}
			}
			if ( $is_cmyk ) {
				$im->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			}

			// Drop EXIF/XMP etc. (saves bytes) but keep the color profile so colors don't shift.
			$im->stripImage();
			if ( '' !== $icc ) {
				try {
					$im->profileImage( 'icc', $icc );
				} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
					// Profile could not be re-attached; the image is still valid.
				}
			}

			$im->setImageFormat( 'WEBP' );
			$im->setImageCompressionQuality( $quality );
			$im->setOption( 'webp:method', '4' );
			$im->setOption( 'webp:lossless', 'false' );
			if ( $im->getImageAlphaChannel() ) {
				$im->setOption( 'webp:alpha-quality', '100' );
			}

			$out = fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! $out ) {
				$im->clear();
				return __( 'Could not create temp file', 'webp-image-optimizer' );
			}
			$written = $im->writeImageFile( $out );
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$im->clear();

			return $written ? true : __( 'Imagick failed to write WebP', 'webp-image-optimizer' );
		} catch ( Throwable $e ) {
			if ( $fh ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
			if ( $im instanceof Imagick ) {
				try {
					$im->clear();
				} catch ( Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
					// Nothing to do.
				}
			}
			return $e->getMessage();
		}
	}

	/**
	 * Apply EXIF orientation with Imagick and reset the tag.
	 *
	 * @param Imagick $im Image.
	 */
	private static function imagick_apply_orientation( $im ) {
		$orientation = (int) $im->getImageOrientation();
		if ( $orientation <= 1 || $orientation > 8 ) {
			return;
		}
		$bg = new ImagickPixel( 'none' );
		switch ( $orientation ) {
			case 2:
				$im->flopImage();
				break;
			case 3:
				$im->rotateImage( $bg, 180 );
				break;
			case 4:
				$im->flipImage();
				break;
			case 5:
				$im->rotateImage( $bg, 90 );
				$im->flopImage();
				break;
			case 6:
				$im->rotateImage( $bg, 90 );
				break;
			case 7:
				$im->rotateImage( $bg, 270 );
				$im->flopImage();
				break;
			case 8:
				$im->rotateImage( $bg, 270 );
				break;
		}
		$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
		$im->setImagePage( 0, 0, 0, 0 );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Validate a WebP file on disk.
	 *
	 * @param string $file File.
	 * @return bool
	 */
	private static function is_valid_webp( $file ) {
		clearstatcache( true, $file );
		if ( ! @is_file( $file ) || @filesize( $file ) < 26 ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		$head = fread( $fh, 16 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 'RIFF' !== substr( $head, 0, 4 ) || 'WEBP' !== substr( $head, 8, 4 ) ) {
			return false;
		}
		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( defined( 'IMAGETYPE_WEBP' ) && $info ) {
			return IMAGETYPE_WEBP === $info[2] && $info[0] > 0 && $info[1] > 0;
		}
		return true;
	}

	/**
	 * Rename temp file over the destination.
	 *
	 * @param string $tmp  Temp.
	 * @param string $dest Destination.
	 * @return bool
	 */
	private static function move_into_place( $tmp, $dest ) {
		if ( @rename( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return true;
		}
		// Windows cannot rename over an existing file.
		@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return @rename( $tmp, $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}

	/**
	 * Remember the temp file so a fatal error doesn't leave it behind.
	 *
	 * @param string $tmp Temp path or ''.
	 */
	private static function track_tmp( $tmp ) {
		self::$current_tmp = $tmp;
		if ( '' !== $tmp && ! self::$cleaner ) {
			self::$cleaner = true;
			register_shutdown_function( array( __CLASS__, 'cleanup_tmp' ) );
		}
	}

	/**
	 * Shutdown handler.
	 */
	public static function cleanup_tmp() {
		if ( '' !== self::$current_tmp && @is_file( self::$current_tmp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			@unlink( self::$current_tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Detect animated GIFs (more than one frame).
	 *
	 * @param string $file File.
	 * @return bool
	 */
	public static function is_animated_gif( $file ) {
		$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		$frames = 0;
		$carry  = '';
		while ( ! feof( $fh ) && $frames < 2 ) {
			$chunk  = $carry . fread( $fh, 1024 * 100 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$frames += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk );
			$carry  = substr( $chunk, -20 );
			if ( strlen( $chunk ) <= 20 ) {
				break;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $frames > 1;
	}

	/**
	 * Detect APNG (acTL chunk before the first IDAT).
	 *
	 * @param string $file File.
	 * @return bool
	 */
	public static function is_animated_png( $file ) {
		$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return false;
		}
		$animated = false;
		if ( "\x89PNG\r\n\x1a\n" === fread( $fh, 8 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			for ( $i = 0; $i < 64; $i++ ) {
				$head = fread( $fh, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( strlen( $head ) < 8 ) {
					break;
				}
				$len   = unpack( 'N', substr( $head, 0, 4 ) );
				$len   = (int) $len[1];
				$ctype = substr( $head, 4, 4 );
				if ( 'acTL' === $ctype ) {
					$animated = true;
					break;
				}
				if ( 'IDAT' === $ctype || 'IEND' === $ctype ) {
					break;
				}
				if ( 0 !== fseek( $fh, $len + 4, SEEK_CUR ) ) {
					break;
				}
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $animated;
	}

	/**
	 * Read the EXIF orientation of a JPEG without needing the exif extension.
	 *
	 * @param string $file File.
	 * @return int 1-8.
	 */
	public static function jpeg_orientation( $file ) {
		$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return 1;
		}
		$data = fread( $fh, 262144 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! is_string( $data ) || "\xFF\xD8" !== substr( $data, 0, 2 ) ) {
			return 1;
		}
		$len = strlen( $data );
		$pos = 2;
		while ( $pos + 4 <= $len ) {
			if ( "\xFF" !== $data[ $pos ] ) {
				return 1;
			}
			$marker = ord( $data[ $pos + 1 ] );
			if ( 0xFF === $marker ) {
				++$pos;
				continue;
			}
			if ( 0xD8 === $marker || 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				$pos += 2;
				continue;
			}
			if ( 0xDA === $marker || 0xD9 === $marker ) {
				return 1;
			}
			$seg = unpack( 'n', substr( $data, $pos + 2, 2 ) );
			$seg = (int) $seg[1];
			if ( $seg < 2 ) {
				return 1;
			}
			if ( 0xE1 === $marker && "Exif\0\0" === substr( $data, $pos + 4, 6 ) ) {
				return self::tiff_orientation( substr( $data, $pos + 10, $seg - 8 ) );
			}
			$pos += 2 + $seg;
		}
		return 1;
	}

	/**
	 * Orientation tag from a TIFF/EXIF block.
	 *
	 * @param string $tiff TIFF data.
	 * @return int
	 */
	private static function tiff_orientation( $tiff ) {
		$n = strlen( $tiff );
		if ( $n < 8 ) {
			return 1;
		}
		$order = substr( $tiff, 0, 2 );
		if ( 'II' === $order ) {
			$s = 'v';
			$l = 'V';
		} elseif ( 'MM' === $order ) {
			$s = 'n';
			$l = 'N';
		} else {
			return 1;
		}
		$ifd = unpack( $l, substr( $tiff, 4, 4 ) );
		$ifd = (int) $ifd[1];
		if ( $ifd + 2 > $n ) {
			return 1;
		}
		$count = unpack( $s, substr( $tiff, $ifd, 2 ) );
		$count = min( 500, (int) $count[1] );
		for ( $i = 0; $i < $count; $i++ ) {
			$entry = $ifd + 2 + $i * 12;
			if ( $entry + 12 > $n ) {
				break;
			}
			$tag = unpack( $s, substr( $tiff, $entry, 2 ) );
			if ( 0x0112 === (int) $tag[1] ) {
				$val = unpack( $s, substr( $tiff, $entry + 8, 2 ) );
				$val = (int) $val[1];
				return ( $val >= 1 && $val <= 8 ) ? $val : 1;
			}
		}
		return 1;
	}

	/**
	 * Build a result array.
	 *
	 * @param string $status     Status.
	 * @param string $message    Message.
	 * @param int    $src_bytes  Source bytes.
	 * @param int    $webp_bytes WebP bytes.
	 * @param string $engine     Engine used.
	 * @return array
	 */
	private static function result( $status, $message = '', $src_bytes = 0, $webp_bytes = 0, $engine = '' ) {
		return array(
			'status'     => $status,
			'message'    => $message,
			'src_bytes'  => (int) $src_bytes,
			'webp_bytes' => (int) $webp_bytes,
			'engine'     => $engine,
		);
	}
}
