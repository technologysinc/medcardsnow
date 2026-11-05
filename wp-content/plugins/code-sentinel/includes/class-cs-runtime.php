<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live runtime issue capture.
 *
 * - PHP: registers an error handler + shutdown handler that records errors, warnings,
 *   notices, deprecations and fatal shutdowns with the exact file and line.
 * - WordPress: hooks deprecated_function_run / deprecated_hook_run / doing_it_wrong etc.
 * - JavaScript: a tiny front-end collector posts window.onerror details back to admin-ajax.
 *
 * Events are deduplicated by fingerprint and persisted to a private JSON file in uploads.
 */
class CS_Runtime {

	const MAX_EVENTS = 500;
	private static $buffer = array();
	private static $dirty  = false;
	private static $events = null;

	public static function boot() {
		$s = CS_Util::settings();

		if ( $s['runtime'] ) {
			// Only capture issues originating in wp-content to stay relevant and light.
			set_error_handler( array( __CLASS__, 'on_error' ) );
			register_shutdown_function( array( __CLASS__, 'on_shutdown' ) );

			add_action( 'deprecated_function_run', array( __CLASS__, 'on_dep_function' ), 10, 3 );
			add_action( 'deprecated_hook_run', array( __CLASS__, 'on_dep_hook' ), 10, 4 );
			add_action( 'deprecated_argument_run', array( __CLASS__, 'on_dep_arg' ), 10, 3 );
			add_action( 'deprecated_file_included', array( __CLASS__, 'on_dep_file' ), 10, 4 );
			add_action( 'doing_it_wrong_run', array( __CLASS__, 'on_doing_wrong' ), 10, 3 );

			add_action( 'shutdown', array( __CLASS__, 'flush' ), PHP_INT_MAX );
		}

		// AJAX endpoint for JS error capture (available to logged-in users).
		if ( $s['js_errors'] ) {
			add_action( 'wp_ajax_cs_js_error', array( __CLASS__, 'ajax_js_error' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_js_collector' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_js_collector' ) );
		}
	}

	/* ---------------------------------------------------------------- PHP errors */

	public static function on_error( $errno, $errstr, $errfile, $errline ) {
		self::record_php( $errno, $errstr, $errfile, $errline );
		return false; // let PHP's normal handler run too
	}

	public static function on_shutdown() {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			self::record_php( $e['type'], $e['message'], $e['file'], $e['line'] );
			self::flush();
		}
	}

	private static function record_php( $type, $msg, $file, $line ) {
		$file = wp_normalize_path( (string) $file );
		// Keep only wp-content-originating issues (plugins, themes, mu-plugins, uploads).
		if ( false === strpos( $file, '/wp-content/' ) ) {
			return;
		}
		$rule = self::map_php_type( $type );
		$msg  = self::clean( $msg );
		self::push( array(
			'kind'  => 'php',
			'rule'  => $rule,
			'msg'   => $msg,
			'file'  => $file,
			'line'  => (int) $line,
			'type'  => (int) $type,
		) );
	}

	private static function map_php_type( $type ) {
		switch ( $type ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return 'RUNTIME-FATAL';
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				return 'RUNTIME-WARNING';
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'RUNTIME-DEPRECATED';
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_STRICT:
				return 'RUNTIME-NOTICE';
			default:
				return 'RUNTIME-ERROR';
		}
	}

	/* ---------------------------------------------------------------- WP deprecations */

	public static function on_dep_function( $function, $replacement, $version ) {
		self::record_wp( sprintf( '%s() is deprecated since %s.%s', $function, $version, $replacement ? ' Use ' . $replacement . '().' : '' ) );
	}
	public static function on_dep_hook( $hook, $replacement, $version, $message ) {
		self::record_wp( sprintf( 'Hook %s is deprecated since %s.%s %s', $hook, $version, $replacement ? ' Use ' . $replacement . '.' : '', (string) $message ) );
	}
	public static function on_dep_arg( $function, $message, $version ) {
		self::record_wp( sprintf( '%s() was called with a deprecated argument since %s. %s', $function, $version, (string) $message ) );
	}
	public static function on_dep_file( $file, $replacement, $version, $message ) {
		self::record_wp( sprintf( 'File %s is deprecated since %s.%s %s', $file, $version, $replacement ? ' Use ' . $replacement . '.' : '', (string) $message ) );
	}
	public static function on_doing_wrong( $function, $message, $version ) {
		self::push( array(
			'kind' => 'php',
			'rule' => 'RUNTIME-DOING-IT-WRONG',
			'msg'  => self::clean( sprintf( '%s: %s (since %s)', $function, $message, $version ) ),
			'file' => self::guess_caller(),
			'line' => 0,
			'type' => 0,
		) );
	}

	private static function record_wp( $msg ) {
		self::push( array(
			'kind' => 'php',
			'rule' => 'RUNTIME-WP-DEPRECATED',
			'msg'  => self::clean( $msg ),
			'file' => self::guess_caller(),
			'line' => 0,
			'type' => 0,
		) );
	}

	/** Best-effort: find the first wp-content frame in the backtrace. */
	private static function guess_caller() {
		$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
		foreach ( $bt as $frame ) {
			if ( ! empty( $frame['file'] ) ) {
				$f = wp_normalize_path( $frame['file'] );
				if ( false !== strpos( $f, '/wp-content/' ) && false === strpos( $f, '/wp-includes/' ) ) {
					return $f . ( isset( $frame['line'] ) ? '' : '' );
				}
			}
		}
		return '';
	}

	/* ---------------------------------------------------------------- JS errors */

	public static function enqueue_js_collector() {
		$s = CS_Util::settings();
		$handle = 'cs-js-collector';
		wp_register_script( $handle, '', array(), CS_VERSION, true );
		wp_enqueue_script( $handle );
		$data = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'cs_js_error' ),
		);
		$js = 'window.CS_JS=' . wp_json_encode( $data ) . ';'
			. '(function(){function s(d){try{var b=new Blob([JSON.stringify(d)],{type:"application/x-www-form-urlencoded"});'
			. 'var p="action=cs_js_error&_n="+encodeURIComponent(CS_JS.nonce)+"&p="+encodeURIComponent(JSON.stringify(d));'
			. 'if(navigator.sendBeacon){navigator.sendBeacon(CS_JS.ajax,p);}else{var x=new XMLHttpRequest();x.open("POST",CS_JS.ajax,true);'
			. 'x.setRequestHeader("Content-type","application/x-www-form-urlencoded");x.send(p);}}catch(e){}}'
			. 'window.addEventListener("error",function(e){if(e.filename){s({m:e.message,f:e.filename,l:e.lineno,c:e.colno,u:location.href});}});'
			. 'window.addEventListener("unhandledrejection",function(e){s({m:"Unhandled promise rejection: "+(e.reason&&e.reason.message?e.reason.message:e.reason),f:(e.reason&&e.reason.stack?String(e.reason.stack).split("\\n")[1]||"":""),l:0,c:0,u:location.href});});})();';
		wp_add_inline_script( $handle, $js );
	}

	public static function ajax_js_error() {
		if ( ! isset( $_POST['_n'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_n'] ) ), 'cs_js_error' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		$raw = isset( $_POST['p'] ) ? wp_unslash( $_POST['p'] ) : '';
		$d   = json_decode( $raw, true );
		if ( is_array( $d ) && ! empty( $d['f'] ) ) {
			self::push( array(
				'kind' => 'js',
				'rule' => 'RUNTIME-JS-ERROR',
				'msg'  => self::clean( (string) ( $d['m'] ?? 'JS error' ) ) . ' [page: ' . esc_url_raw( (string) ( $d['u'] ?? '' ) ) . ']',
				'file' => esc_url_raw( (string) $d['f'] ),
				'line' => (int) ( $d['l'] ?? 0 ),
				'type' => 0,
			) );
			self::flush();
		}
		wp_die( '', '', array( 'response' => 204 ) );
	}

	/* ---------------------------------------------------------------- storage */

	private static function clean( $msg ) {
		$msg = wp_strip_all_tags( (string) $msg );
		$msg = preg_replace( '/ in \/[^ ]+ on line \d+/', '', $msg );
		return trim( mb_substr( $msg, 0, 300 ) );
	}

	private static function push( array $ev ) {
		$ev['file'] = wp_normalize_path( $ev['file'] );
		$fp = substr( md5( $ev['rule'] . '|' . $ev['file'] . '|' . $ev['line'] . '|' . $ev['msg'] ), 0, 14 );
		if ( isset( self::$buffer[ $fp ] ) ) {
			self::$buffer[ $fp ]['count']++;
			self::$buffer[ $fp ]['last'] = time();
			return;
		}
		$ev['fp']    = $fp;
		$ev['count'] = 1;
		$ev['first'] = time();
		$ev['last']  = time();
		self::$buffer[ $fp ] = $ev;
		self::$dirty = true;
	}

	public static function flush() {
		if ( ! self::$dirty || empty( self::$buffer ) ) {
			return;
		}
		$path   = CS_Util::data_path( 'runtime' );
		$events = CS_Util::read_json( $path );
		if ( ! is_array( $events ) ) {
			$events = array();
		}
		foreach ( self::$buffer as $fp => $ev ) {
			if ( isset( $events[ $fp ] ) ) {
				$events[ $fp ]['count'] += $ev['count'];
				$events[ $fp ]['last']   = $ev['last'];
			} else {
				$events[ $fp ] = $ev;
			}
		}
		// Trim to most-recent MAX_EVENTS.
		if ( count( $events ) > self::MAX_EVENTS ) {
			uasort( $events, function ( $a, $b ) { return $b['last'] - $a['last']; } );
			$events = array_slice( $events, 0, self::MAX_EVENTS, true );
		}
		CS_Util::write_json( $path, $events );
		self::$buffer = array();
		self::$dirty  = false;
	}

	/* ---------------------------------------------------------------- read / clear */

	public static function all() {
		if ( null === self::$events ) {
			$e = CS_Util::read_json( CS_Util::data_path( 'runtime' ) );
			self::$events = is_array( $e ) ? $e : array();
		}
		return self::$events;
	}

	public static function clear() {
		CS_Util::write_json( CS_Util::data_path( 'runtime' ), array() );
		self::$events = array();
	}

	/** Convert stored runtime events into finding rows for the dashboard. */
	public static function as_findings() {
		$out = array();
		foreach ( self::all() as $ev ) {
			$abs   = $ev['file'];
			$owner = $abs ? CS_Util::owner( $abs ) : 'other:other';
			$out[] = array(
				'rule'    => $ev['rule'],
				'fp'      => $ev['fp'],
				'abs'     => $abs,
				'rel'     => $abs ? CS_Util::rel( $abs ) : '(unknown)',
				'line'    => (int) $ev['line'],
				'snippet' => $ev['msg'],
				'extra'   => '',
				'owner'   => $owner,
				'count'   => isset( $ev['count'] ) ? (int) $ev['count'] : 1,
				'last'    => isset( $ev['last'] ) ? (int) $ev['last'] : 0,
				'runtime' => true,
			);
		}
		return $out;
	}
}
