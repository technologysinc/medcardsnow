<?php
/**
 * Front-end delivery: wraps <img> tags in <picture> with a WebP <source>.
 *
 * Why this is safe:
 *  - The original <img> tag is kept byte-for-byte, so browsers without WebP
 *    support (and anything that reads the img) behave exactly as before.
 *  - A <source> is only added when the WebP file really exists on disk.
 *  - Images inside <script>, <style>, <textarea>, <noscript>, <template>,
 *    <svg>, existing <picture> blocks and HTML comments are never touched.
 *  - Any error, regex failure or unexpected output returns the page unchanged.
 *  - The same HTML is sent to every browser, so page caches and CDNs stay
 *    correct (no "Vary: Accept" needed).
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Picture-tag delivery.
 */
final class WIO_Picture {

	/** Ignore mtime differences below this (seconds) — handles site migrations. */
	const MTIME_TOLERANCE = 300;

	/**
	 * Output withheld during intermediate flushes.
	 *
	 * @var string
	 */
	private $stash = '';

	/**
	 * URL -> WebP URL|false lookups for this request.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Hostnames treated as "this site".
	 *
	 * @var string[]
	 */
	private $hosts = array();

	/**
	 * URL path of the uploads folder, with trailing slash.
	 *
	 * @var string
	 */
	private $uploads_path = '';

	/**
	 * Filesystem path of the uploads folder, no trailing slash.
	 *
	 * @var string
	 */
	private $uploads_dir = '';

	/**
	 * Substrings that exclude an <img>.
	 *
	 * @var string[]
	 */
	private $exclude = array();

	/**
	 * Buffering is active for this request.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Register hooks when picture delivery is on.
	 */
	public static function init() {
		if ( 'picture' !== WIO_Settings::get( 'delivery' ) || is_admin() ) {
			return;
		}
		$instance = new self();
		add_action( 'template_redirect', array( $instance, 'start' ), 9999 );
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 1 );
	}

	/**
	 * Tiny CSS so the <picture> wrapper never changes layout.
	 */
	public static function print_css() {
		if ( ! self::$active || ! WIO_Settings::get( 'display_contents' ) ) {
			return;
		}
		echo "<style id=\"wio-picture-css\">picture.wio-picture{display:contents}</style>\n";
	}

	/**
	 * Begin buffering the page.
	 */
	public function start() {
		if ( ! $this->should_run() ) {
			return;
		}
		if ( ! $this->setup() ) {
			return;
		}
		self::$active = ob_start( array( $this, 'callback' ) );
	}

	/**
	 * Decide whether this request is a normal HTML page we should touch.
	 *
	 * @return bool
	 */
	private function should_run() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false;
		}
		if ( is_feed() || is_robots() || is_trackback() || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return false;
		}
		if ( is_customize_preview() ) {
			return false;
		}

		// Page builders' live editors and a manual "?nowebp" switch for debugging.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$skip_params = array( 'nowebp', 'elementor-preview', 'fl_builder', 'et_fb', 'ct_builder', 'tve', 'vc_editable', 'brizy-edit-iframe', 'bricks', 'breakdance', 'oxygen_iframe' );
		foreach ( $skip_params as $param ) {
			if ( isset( $_GET[ $param ] ) ) {
				return false;
			}
		}
		// phpcs:enable

		return (bool) apply_filters( 'wio_enable_picture', true );
	}

	/**
	 * Prepare URL/host mapping for this request.
	 *
	 * @return bool
	 */
	private function setup() {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) || ! empty( $uploads['error'] ) ) {
			return false;
		}
		$base_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		if ( ! is_string( $base_path ) || '' === $base_path ) {
			return false;
		}
		$this->uploads_path = trailingslashit( $base_path );
		$this->uploads_dir  = untrailingslashit( wp_normalize_path( $uploads['basedir'] ) );

		$hosts = array();
		foreach ( array( home_url(), site_url(), $uploads['baseurl'] ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host ) {
				$host    = strtolower( $host );
				$hosts[] = $host;
				$hosts[] = ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : 'www.' . $host;
			}
		}
		foreach ( WIO_Settings::lines( 'cdn_hosts' ) as $host ) {
			$hosts[] = strtolower( $host );
		}
		$this->hosts   = array_values( array_unique( array_filter( $hosts ) ) );
		$this->exclude = WIO_Settings::lines( 'exclude' );
		return true;
	}

	/**
	 * Output buffer callback.
	 *
	 * @param string $buffer Buffer.
	 * @param int    $phase  PHP_OUTPUT_HANDLER_* bitmask.
	 * @return string
	 */
	public function callback( $buffer, $phase ) {
		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {
			return '';
		}
		// Hold intermediate flushes so a tag is never split between two chunks.
		if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
			$this->stash .= $buffer;
			return '';
		}

		$html        = $this->stash . $buffer;
		$this->stash = '';

		try {
			$out = $this->process( $html );
		} catch ( Throwable $e ) {
			return $html;
		}
		return ( is_string( $out ) && '' !== $out ) ? $out : $html;
	}

	/**
	 * Rewrite a full HTML document.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function process( $html ) {
		if ( '' === $html || false === stripos( $html, '<img' ) ) {
			return $html;
		}

		// Only HTML responses.
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) && false === stripos( $header, 'application/xhtml' ) ) {
				return $html;
			}
		}

		// 1) Hide regions where <img> must never be changed.
		$protected = array();
		$open_tag  = '(?>[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>';
		// Possessive patterns: no catastrophic backtracking even on huge inline scripts.
		$pattern = '~<!--(?:[^-]++|-(?!->))*+-->|<(script|style|textarea|template|noscript|picture|svg|video|audio|math|iframe|object)\b' . $open_tag . '(?:[^<]++|<(?!/\1\s*>))*+</\1\s*>~i';

		$masked = preg_replace_callback(
			$pattern,
			function ( $m ) use ( &$protected ) {
				$key               = "\x1AWIO" . count( $protected ) . "\x1A";
				$protected[ $key ] = $m[0];
				return $key;
			},
			$html
		);
		if ( null === $masked ) {
			return $html; // PCRE limit hit: leave the page alone.
		}

		// 2) Wrap images.
		$replaced = preg_replace_callback( '~<img\b' . $open_tag . '~i', array( $this, 'replace_img' ), $masked );
		if ( null === $replaced ) {
			return $html;
		}

		// 3) Put protected regions back.
		return $protected ? strtr( $replaced, $protected ) : $replaced;
	}

	/**
	 * Wrap one <img> tag.
	 *
	 * @param array $m Regex match.
	 * @return string
	 */
	public function replace_img( $m ) {
		$tag = $m[0];

		if ( false !== stripos( $tag, 'wio-skip' ) || false !== stripos( $tag, 'data-wio-skip' ) ) {
			return $tag;
		}
		foreach ( $this->exclude as $needle ) {
			if ( false !== stripos( $tag, $needle ) ) {
				return $tag;
			}
		}

		$p = new WP_HTML_Tag_Processor( $tag );
		if ( ! $p->next_tag( 'img' ) ) {
			return $tag;
		}

		$attrs = array();
		foreach ( array( 'src', 'srcset', 'sizes', 'data-src', 'data-srcset', 'data-sizes', 'data-lazy-src', 'data-lazy-srcset', 'data-lazy-sizes' ) as $name ) {
			$value = $p->get_attribute( $name );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$attrs[ $name ] = trim( $value );
			}
		}
		if ( empty( $attrs ) ) {
			return $tag;
		}

		$lazy = isset( $attrs['data-src'] ) || isset( $attrs['data-srcset'] ) || isset( $attrs['data-lazy-src'] ) || isset( $attrs['data-lazy-srcset'] );

		// Mirror the attribute the page actually uses: srcset, data-srcset or data-lazy-srcset.
		$pairs  = array(
			'srcset'           => array( 'srcset', 'src', 'sizes' ),
			'data-srcset'      => array( 'data-srcset', 'data-src', 'data-sizes' ),
			'data-lazy-srcset' => array( 'data-lazy-srcset', 'data-lazy-src', 'data-lazy-sizes' ),
		);
		$source = array();
		foreach ( $pairs as $target => $names ) {
			list( $set_attr, $single_attr, $sizes_attr ) = $names;

			$value = null;
			if ( isset( $attrs[ $set_attr ] ) ) {
				$value = $this->convert_srcset( $attrs[ $set_attr ] );
			} elseif ( isset( $attrs[ $single_attr ] ) && ! ( $lazy && 'src' === $single_attr ) ) {
				// With a lazy loader the plain src is usually a placeholder: don't promote it.
				$url   = $this->webp_url( $attrs[ $single_attr ] );
				$value = $url ? $url : null;
			}
			if ( null !== $value ) {
				$source[ $target ] = $value;
				if ( isset( $attrs[ $sizes_attr ] ) ) {
					$source[ $sizes_attr ] = $attrs[ $sizes_attr ];
				}
			}
		}

		if ( ! $source ) {
			return $tag;
		}

		$html = '<source type="image/webp"';
		foreach ( $source as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		$html .= '>';

		$out = '<picture class="wio-picture">' . $html . $tag . '</picture>';
		return (string) apply_filters( 'wio_picture_html', $out, $tag );
	}

	/**
	 * Map a srcset to WebP. Candidates without a WebP copy keep their original
	 * URL, so the browser always has every size available.
	 *
	 * @param string $srcset Srcset.
	 * @return string|null Null when no candidate has a WebP copy.
	 */
	private function convert_srcset( $srcset ) {
		$candidates = self::parse_srcset( $srcset );
		if ( ! $candidates ) {
			return null;
		}
		$any = false;
		$out = array();
		foreach ( $candidates as $c ) {
			$webp = $this->webp_url( $c[0] );
			if ( $webp ) {
				$any = true;
			}
			$out[] = trim( ( $webp ? $webp : $c[0] ) . ' ' . $c[1] );
		}
		return $any ? implode( ', ', $out ) : null;
	}

	/**
	 * Split a srcset into [url, descriptor] pairs (follows the HTML spec rules,
	 * so URLs containing commas are handled).
	 *
	 * @param string $srcset Srcset.
	 * @return array[]
	 */
	public static function parse_srcset( $srcset ) {
		$out = array();
		$n   = strlen( $srcset );
		$i   = 0;
		while ( $i < $n ) {
			while ( $i < $n && ( ctype_space( $srcset[ $i ] ) || ',' === $srcset[ $i ] ) ) {
				++$i;
			}
			if ( $i >= $n ) {
				break;
			}
			$start = $i;
			while ( $i < $n && ! ctype_space( $srcset[ $i ] ) ) {
				++$i;
			}
			$url  = substr( $srcset, $start, $i - $start );
			$desc = '';
			if ( ',' === substr( $url, -1 ) ) {
				$url = rtrim( $url, ',' );
			} else {
				$d = $i;
				while ( $i < $n && ',' !== $srcset[ $i ] ) {
					++$i;
				}
				$desc = trim( substr( $srcset, $d, $i - $d ) );
			}
			if ( '' !== $url ) {
				$out[] = array( $url, $desc );
			}
		}
		return $out;
	}

	/**
	 * WebP URL for an image URL, or false if there is no (fresh) WebP copy.
	 *
	 * @param string $url Image URL.
	 * @return string|false
	 */
	public function webp_url( $url ) {
		$url = trim( $url ); // Attribute values arrive already entity-decoded.
		if ( '' === $url || 0 === strncasecmp( $url, 'data:', 5 ) ) {
			return false;
		}
		if ( isset( $this->cache[ $url ] ) ) {
			return $this->cache[ $url ];
		}
		$this->cache[ $url ] = false;

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
			return false;
		}
		if ( ! empty( $parts['host'] ) ) {
			if ( ! in_array( strtolower( $parts['host'] ), $this->hosts, true ) ) {
				return false;
			}
		} elseif ( '/' !== substr( $parts['path'], 0, 1 ) ) {
			return false; // Relative paths are ambiguous.
		}

		$path = $parts['path'];
		if ( ! preg_match( '~\.(jpe?g|png|gif)$~i', $path ) || 0 !== strpos( $path, $this->uploads_path ) ) {
			return false;
		}

		$relative = rawurldecode( substr( $path, strlen( $this->uploads_path ) ) );
		if ( '' === $relative || false !== strpos( $relative, '..' ) || false !== strpos( $relative, "\0" ) ) {
			return false;
		}

		$file = $this->uploads_dir . '/' . $relative;
		$webp = $file . '.webp';
		if ( ! @is_file( $webp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		// If the original was replaced after conversion, the WebP is out of date: don't use it.
		$src_time = (int) @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $src_time || $src_time > (int) @filemtime( $webp ) + self::MTIME_TOLERANCE ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}

		// Insert ".webp" right after the file name, before any ?query or #hash.
		$cut    = strcspn( $url, '?#' );
		$result = substr( $url, 0, $cut ) . '.webp' . substr( $url, $cut );

		$this->cache[ $url ] = $result;
		return $result;
	}
}
