<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers: settings, storage, paths, ownership (plugin / theme / core), file discovery.
 */
class CS_Util {

	const OPT_SETTINGS = 'cs_settings';
	const OPT_IGNORED  = 'cs_ignored';
	const MAX_FILES    = 25000;

	const F_VENDOR = 1;
	const F_MIN    = 2;

	/** @var array|null */
	private static $settings = null;
	/** @var array */
	private static $owners = array();
	/** @var array|null */
	private static $roots = null;
	/** @var array|null */
	private static $plugin_map = null;

	/* ---------------------------------------------------------------- settings */

	public static function defaults() {
		return array(
			'runtime'      => 1,    // Capture PHP errors / deprecations at runtime.
			'js_errors'    => 1,    // Capture JavaScript errors from logged-in users.
			'max_kb'       => 1536, // Skip content scan of files bigger than this.
			'include_min'  => 0,    // Scan *.min.js too.
			'scan_vendor'  => 0,    // Apply rules inside vendor/ directories.
			'daily'        => 0,    // Daily background scan.
			'email'        => '',   // Alert recipient for daily scan.
			'token'        => '',
		);
	}

	public static function settings() {
		if ( null !== self::$settings ) {
			return self::$settings;
		}
		$stored = get_option( self::OPT_SETTINGS, array() );
		$s      = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		if ( empty( $s['token'] ) ) {
			$s['token'] = substr( md5( wp_generate_password( 32, true, true ) . microtime( true ) ), 0, 16 );
			update_option( self::OPT_SETTINGS, $s, false );
		}
		if ( '' === $s['email'] ) {
			$s['email'] = (string) get_option( 'admin_email', '' );
		}
		self::$settings = $s;
		return $s;
	}

	public static function save_settings( array $in ) {
		$cur = self::settings();
		$new = array(
			'runtime'     => empty( $in['runtime'] ) ? 0 : 1,
			'js_errors'   => empty( $in['js_errors'] ) ? 0 : 1,
			'max_kb'      => max( 64, min( 8192, (int) ( $in['max_kb'] ?? 1536 ) ) ),
			'include_min' => empty( $in['include_min'] ) ? 0 : 1,
			'scan_vendor' => empty( $in['scan_vendor'] ) ? 0 : 1,
			'daily'       => empty( $in['daily'] ) ? 0 : 1,
			'email'       => sanitize_email( $in['email'] ?? '' ),
			'token'       => $cur['token'],
		);
		update_option( self::OPT_SETTINGS, $new, false );
		self::$settings = null;
		return self::settings();
	}

	public static function ignored() {
		$i = get_option( self::OPT_IGNORED, array() );
		return is_array( $i ) ? $i : array();
	}

	/* ---------------------------------------------------------------- storage */

	public static function log_dir() {
		$u = wp_upload_dir( null, false );
		return rtrim( wp_normalize_path( $u['basedir'] ), '/' ) . '/code-sentinel';
	}

	public static function ensure_log_dir() {
		$dir = self::log_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( is_dir( $dir ) ) {
			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				@file_put_contents( $dir . '/.htaccess', "# Code Sentinel private data\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
			}
			if ( ! file_exists( $dir . '/index.php' ) ) {
				@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
			}
		}
		return $dir;
	}

	/** Private file path; the token in the name keeps it unguessable on servers that ignore .htaccess. */
	public static function data_path( $name ) {
		$s = self::settings();
		return self::ensure_log_dir() . '/' . $name . '-' . $s['token'] . '.json';
	}

	public static function read_json( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}
		$d = json_decode( $raw, true );
		return is_array( $d ) ? $d : null;
	}

	public static function write_json( $path, $data ) {
		$tmp = $path . '.tmp' . wp_rand( 1000, 9999 );
		$ok  = file_put_contents( $tmp, wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ), LOCK_EX );
		if ( false === $ok ) {
			return false;
		}
		return @rename( $tmp, $path );
	}

	/* ---------------------------------------------------------------- paths */

	public static function norm( $p ) {
		return rtrim( wp_normalize_path( $p ), '/' );
	}

	/** Path relative to the WordPress root, or the normalised absolute path when outside. */
	public static function rel( $abs ) {
		$abs  = wp_normalize_path( $abs );
		$root = trailingslashit( wp_normalize_path( ABSPATH ) );
		return 0 === strpos( $abs, $root ) ? substr( $abs, strlen( $root ) ) : $abs;
	}

	public static function abs_from_rel( $rel ) {
		if ( preg_match( '#^(/|[A-Za-z]:/)#', $rel ) ) {
			return $rel;
		}
		return trailingslashit( wp_normalize_path( ABSPATH ) ) . $rel;
	}

	/* ---------------------------------------------------------------- owners */

	private static function roots() {
		if ( null !== self::$roots ) {
			return self::$roots;
		}
		$u           = wp_upload_dir( null, false );
		self::$roots = array(
			array( self::norm( WP_PLUGIN_DIR ), 'plugin' ),
			array( self::norm( WPMU_PLUGIN_DIR ), 'mu-plugin' ),
			array( self::norm( get_theme_root() ), 'theme' ),
			array( self::norm( $u['basedir'] ), 'uploads' ),
			array( self::norm( ABSPATH . 'wp-admin' ), 'core' ),
			array( self::norm( ABSPATH . WPINC ), 'core' ),
			array( self::norm( WP_CONTENT_DIR ), 'content' ),
			array( self::norm( ABSPATH ), 'other' ),
		);
		return self::$roots;
	}

	public static function plugin_map() {
		if ( null !== self::$plugin_map ) {
			return self::$plugin_map;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = array_flip( (array) get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active = array_merge( $active, (array) get_site_option( 'active_sitewide_plugins', array() ) );
		}
		$map = array();
		foreach ( get_plugins() as $basename => $data ) {
			$slug = false !== strpos( $basename, '/' ) ? strstr( $basename, '/', true ) : $basename;
			$act  = isset( $active[ $basename ] );
			if ( isset( $map[ $slug ] ) ) {
				$map[ $slug ]['active'] = $map[ $slug ]['active'] || $act;
				continue;
			}
			$map[ $slug ] = array(
				'basename' => $basename,
				'name'     => $data['Name'] ? $data['Name'] : $slug,
				'version'  => $data['Version'],
				'data'     => $data,
				'active'   => $act,
			);
		}
		self::$plugin_map = $map;
		return $map;
	}

	/** Returns the owner key ("plugin:slug") for a path and registers its metadata. */
	public static function owner( $abs ) {
		$abs = self::norm( $abs );
		foreach ( self::roots() as $r ) {
			if ( 0 !== strpos( $abs, $r[0] . '/' ) ) {
				continue;
			}
			$type = $r[1];
			$rest = substr( $abs, strlen( $r[0] ) + 1 );
			$slug = in_array( $type, array( 'plugin', 'mu-plugin', 'theme', 'uploads' ), true ) ? explode( '/', $rest )[0] : $type;
			if ( 'uploads' === $type ) {
				$slug = 'uploads';
			}
			$key = $type . ':' . $slug;
			if ( ! isset( self::$owners[ $key ] ) ) {
				self::$owners[ $key ] = self::owner_meta( $type, $slug, $r[0] );
			}
			return $key;
		}
		self::$owners['other:other'] = array( 'type' => 'other', 'slug' => 'other', 'name' => 'Other', 'active' => true, 'version' => '' );
		return 'other:other';
	}

	private static function owner_meta( $type, $slug, $root ) {
		$m = array( 'type' => $type, 'slug' => $slug, 'name' => $slug, 'active' => true, 'version' => '' );
		if ( 'plugin' === $type ) {
			$map = self::plugin_map();
			if ( isset( $map[ $slug ] ) ) {
				$m['name']    = $map[ $slug ]['name'];
				$m['version'] = $map[ $slug ]['version'];
				$m['active']  = (bool) $map[ $slug ]['active'];
			} else {
				$m['active'] = false;
			}
		} elseif ( 'theme' === $type ) {
			$m['active'] = in_array( $slug, array( get_stylesheet(), get_template() ), true );
			$t           = wp_get_theme( $slug );
			if ( $t->exists() ) {
				$m['name']    = $t->get( 'Name' );
				$m['version'] = $t->get( 'Version' );
			}
		} elseif ( 'uploads' === $type ) {
			$m['name'] = 'Uploads folder';
		} elseif ( 'core' === $type ) {
			$m['name'] = 'WordPress core';
		} elseif ( 'mu-plugin' === $type ) {
			$m['name'] = $slug . ' (must-use)';
		}
		return $m;
	}

	public static function owners() {
		return self::$owners;
	}

	public static function reset_owner_cache() {
		self::$owners     = array();
		self::$plugin_map = null;
		self::$roots      = null;
	}

	/* ---------------------------------------------------------------- targets */

	/** Options shown in the UI target selector. */
	public static function target_choices() {
		$out = array(
			'active_plugins' => 'Active plugins',
			'all_plugins'    => 'All plugins (active and inactive)',
			'active_theme'   => 'Active theme (and parent)',
			'themes'         => 'All themes',
			'mu'             => 'Must-use plugins',
			'uploads'        => 'Uploads folder (stray PHP / backups)',
			'everything'     => 'Everything above',
		);
		foreach ( self::plugin_map() as $slug => $p ) {
			$out[ 'plugin:' . $slug ] = 'Plugin: ' . $p['name'] . ( $p['active'] ? '' : ' (inactive)' );
		}
		return $out;
	}

	/** Resolve a target keyword to a list of absolute paths. Never trusts a path from the client. */
	public static function target_roots( $target ) {
		$roots = array();
		$map   = self::plugin_map();
		$add_plugins = function ( $only_active ) use ( &$roots, $map ) {
			foreach ( $map as $slug => $p ) {
				if ( $only_active && ! $p['active'] ) {
					continue;
				}
				$path = WP_PLUGIN_DIR . '/' . $slug;
				if ( file_exists( $path ) ) {
					$roots[] = $path;
				}
			}
		};
		$add_themes = function ( $only_active ) use ( &$roots ) {
			$active = array_unique( array( get_stylesheet(), get_template() ) );
			foreach ( wp_get_themes() as $slug => $t ) {
				if ( $only_active && ! in_array( $slug, $active, true ) ) {
					continue;
				}
				$roots[] = $t->get_stylesheet_directory();
			}
		};
		$add_mu = function () use ( &$roots ) {
			if ( is_dir( WPMU_PLUGIN_DIR ) ) {
				$roots[] = WPMU_PLUGIN_DIR;
			}
		};
		$add_uploads = function () use ( &$roots ) {
			$u = wp_upload_dir( null, false );
			if ( is_dir( $u['basedir'] ) ) {
				$roots[] = $u['basedir'];
			}
		};

		if ( 0 === strpos( $target, 'plugin:' ) ) {
			$slug = substr( $target, 7 );
			if ( isset( $map[ $slug ] ) ) {
				$roots[] = WP_PLUGIN_DIR . '/' . $slug;
			}
			return $roots;
		}
		switch ( $target ) {
			case 'active_plugins':
				$add_plugins( true );
				break;
			case 'all_plugins':
				$add_plugins( false );
				break;
			case 'active_theme':
				$add_themes( true );
				break;
			case 'themes':
				$add_themes( false );
				break;
			case 'mu':
				$add_mu();
				break;
			case 'uploads':
				$add_uploads();
				break;
			case 'everything':
				$add_plugins( false );
				$add_themes( false );
				$add_mu();
				$add_uploads();
				break;
		}
		return $roots;
	}

	/* ---------------------------------------------------------------- file discovery */

	private static $skip_dirs = array( 'node_modules', '.git', '.svn', '.hg', '.idea', '.vscode' );

	/** Classify a file. Returns 'php' | 'js' | 'cfg' | 'ht' | 'meta' | '' (ignore). */
	public static function classify( $name ) {
		$l = strtolower( $name );
		if ( '.htaccess' === $l || '.user.ini' === $l ) {
			return 'ht';
		}
		$ext = strtolower( pathinfo( $l, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'php', 'phtml', 'inc', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht', 'phar' ), true ) ) {
			return 'php';
		}
		if ( in_array( $ext, array( 'js', 'mjs' ), true ) ) {
			return 'js';
		}
		if ( in_array( $ext, array( 'json', 'ini', 'yml', 'yaml', 'env' ), true ) || 0 === strpos( $l, '.env' ) ) {
			return 'cfg';
		}
		if ( CS_Rules::suspicious_name( $name ) ) {
			return 'meta';
		}
		return '';
	}

	/**
	 * Walk roots and return a queue of [absPath, flags, kind].
	 */
	public static function list_files( array $roots, array $opts ) {
		$queue = array();
		$seen  = array();
		foreach ( $roots as $root ) {
			$root = self::norm( $root );
			if ( is_file( $root ) ) {
				$k = self::classify( basename( $root ) );
				if ( $k && ! isset( $seen[ $root ] ) ) {
					$seen[ $root ] = 1;
					$queue[]       = array( $root, self::flags_for( $root, $opts ), $k );
				}
				continue;
			}
			if ( ! is_dir( $root ) ) {
				continue;
			}
			try {
				$dirIt = new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS );
				$filter = new RecursiveCallbackFilterIterator(
					$dirIt,
					function ( $cur ) {
						if ( $cur->isDir() && ! $cur->isLink() ) {
							return ! in_array( $cur->getFilename(), CS_Util::skip_dirs(), true );
						}
						return $cur->isFile();
					}
				);
				$it = new RecursiveIteratorIterator( $filter, RecursiveIteratorIterator::LEAVES_ONLY );
				foreach ( $it as $f ) {
					$abs = self::norm( $f->getPathname() );
					if ( isset( $seen[ $abs ] ) ) {
						continue;
					}
					$k = self::classify( $f->getFilename() );
					if ( '' === $k ) {
						continue;
					}
					$seen[ $abs ] = 1;
					$queue[]      = array( $abs, self::flags_for( $abs, $opts ), $k );
					if ( count( $queue ) >= self::MAX_FILES ) {
						break 2;
					}
				}
			} catch ( Exception $e ) {
				continue;
			}
		}
		return $queue;
	}

	public static function skip_dirs() {
		return self::$skip_dirs;
	}

	private static function flags_for( $abs, $opts ) {
		$f = 0;
		if ( preg_match( '#/(vendor|vendor_prefixed|vendors|third[-_]?party|libs?/vendor)/#i', $abs ) ) {
			$f |= self::F_VENDOR;
		}
		if ( preg_match( '#[.\-]min\.m?js$#i', $abs ) ) {
			$f |= self::F_MIN;
		}
		return $f;
	}

	/* ---------------------------------------------------------------- misc */

	/** One line of code for display, windowed around the match on very long (minified) lines. */
	public static function snippet( array $lines, $line, $col = 0 ) {
		$t = isset( $lines[ $line - 1 ] ) ? $lines[ $line - 1 ] : '';
		$t = rtrim( $t, "\r\n" );
		if ( strlen( $t ) > 240 ) {
			$start = max( 0, $col - 90 );
			$t     = ( $start > 0 ? '…' : '' ) . substr( $t, $start, 220 ) . '…';
		}
		return trim( preg_replace( '/\s+/', ' ', $t ) );
	}

	public static function fingerprint( $rule, $rel, $snippet ) {
		return substr( md5( $rule . '|' . $rel . '|' . trim( (string) $snippet ) ), 0, 14 );
	}

	/** Prevent spreadsheet formula injection in CSV exports. */
	public static function csv_cell( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$v = "'" . $v;
		}
		return $v;
	}

	public static function sev_rank( $s ) {
		$r = array( 'critical' => 5, 'high' => 4, 'medium' => 3, 'low' => 2, 'info' => 1 );
		return isset( $r[ $s ] ) ? $r[ $s ] : 0;
	}
}
