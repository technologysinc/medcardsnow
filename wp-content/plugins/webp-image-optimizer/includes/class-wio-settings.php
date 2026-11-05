<?php
/**
 * Settings storage and sanitizing.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings helper. All settings live in one autoloaded option.
 */
final class WIO_Settings {

	const OPTION = 'wio_settings';

	/**
	 * Request-level cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Register listeners that keep the cache and server rules in sync.
	 */
	public static function init() {
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'on_change' ), 10, 0 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'on_change' ), 10, 0 );
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'quality'             => 80,
			'types'               => array( 'jpeg', 'png', 'gif' ),
			'on_upload'           => 'shutdown', // shutdown | cron | off.
			'delivery'            => 'picture',  // picture | rewrite | off.
			'display_contents'    => 1,
			'exclude'             => '',
			'cdn_hosts'           => '',
			'engine'              => 'auto',     // auto | imagick | gd.
			'max_dimension'       => 2560,
			'jpeg_quality'        => 82,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Clear the request cache.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Fired after the option is saved.
	 */
	public static function on_change() {
		self::flush_cache();
		delete_transient( 'wio_stats' );
		WIO_Rewrite::sync();
	}

	/**
	 * Mime types the user has enabled.
	 *
	 * @return string[]
	 */
	public static function enabled_mimes() {
		$map   = array(
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
		);
		$mimes = array();
		foreach ( (array) self::get( 'types' ) as $type ) {
			if ( isset( $map[ $type ] ) ) {
				$mimes[] = $map[ $type ];
			}
		}
		return $mimes;
	}

	/**
	 * Split a textarea setting into clean lines.
	 *
	 * @param string $key Setting key.
	 * @return string[]
	 */
	public static function lines( $key ) {
		$raw   = (string) self::get( $key );
		$lines = preg_split( '/[\r\n,]+/', $raw );
		$lines = array_map( 'trim', is_array( $lines ) ? $lines : array() );
		return array_values( array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Sanitize callback for register_setting.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$out['quality'] = isset( $input['quality'] ) ? (int) $input['quality'] : $defaults['quality'];
		$out['quality'] = max( 30, min( 100, $out['quality'] ) );

		$types        = isset( $input['types'] ) ? (array) $input['types'] : array();
		$out['types'] = array_values( array_intersect( array( 'jpeg', 'png', 'gif' ), array_map( 'sanitize_key', $types ) ) );

		$out['on_upload'] = isset( $input['on_upload'] ) && in_array( $input['on_upload'], array( 'shutdown', 'cron', 'off' ), true ) ? $input['on_upload'] : $defaults['on_upload'];
		$out['delivery']  = isset( $input['delivery'] ) && in_array( $input['delivery'], array( 'picture', 'rewrite', 'off' ), true ) ? $input['delivery'] : $defaults['delivery'];
		$out['engine']    = isset( $input['engine'] ) && in_array( $input['engine'], array( 'auto', 'imagick', 'gd' ), true ) ? $input['engine'] : $defaults['engine'];

		$out['display_contents']    = empty( $input['display_contents'] ) ? 0 : 1;
		$out['delete_on_uninstall'] = empty( $input['delete_on_uninstall'] ) ? 0 : 1;

		$out['exclude'] = isset( $input['exclude'] ) ? sanitize_textarea_field( (string) $input['exclude'] ) : '';

		$hosts = array();
		if ( isset( $input['cdn_hosts'] ) ) {
			foreach ( preg_split( '/[\s,]+/', (string) $input['cdn_hosts'] ) as $host ) {
				$host = strtolower( trim( $host ) );
				if ( '' === $host ) {
					continue;
				}
				if ( false !== strpos( $host, '/' ) ) {
					$parsed = wp_parse_url( ( 0 === strpos( $host, '//' ) || preg_match( '~^https?://~', $host ) ) ? $host : 'https://' . $host );
					$host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
				}
				$host = preg_replace( '/[^a-z0-9.\-:]/', '', $host );
				if ( '' !== $host ) {
					$hosts[] = $host;
				}
			}
		}
		$out['cdn_hosts'] = implode( "\n", array_unique( $hosts ) );

		$dim                  = isset( $input['max_dimension'] ) ? (int) $input['max_dimension'] : $defaults['max_dimension'];
		$out['max_dimension'] = $dim <= 0 ? 0 : max( 800, min( 10000, $dim ) );

		$jq                  = isset( $input['jpeg_quality'] ) ? (int) $input['jpeg_quality'] : $defaults['jpeg_quality'];
		$out['jpeg_quality'] = max( 40, min( 100, $jq ) );

		return $out;
	}
}
