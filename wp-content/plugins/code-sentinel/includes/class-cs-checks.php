<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Environment / metadata checks that don't need per-line file parsing:
 * plugin & theme version headers, available updates, inactive plugins,
 * and wordpress.org checksum integrity verification.
 */
class CS_Checks {

	/**
	 * Returns a list of findings-like rows (same shape the scanner emits, minus line parsing).
	 */
	public static function run( array $targets ) {
		$out       = array();
		$wantAll   = in_array( 'everything', $targets, true );
		$wantPlug  = $wantAll || (bool) array_intersect( $targets, array( 'active_plugins', 'all_plugins' ) ) || self::has_plugin_target( $targets );
		$wantTheme = $wantAll || (bool) array_intersect( $targets, array( 'active_theme', 'themes' ) );

		if ( $wantPlug ) {
			self::plugin_checks( $targets, $out );
		}
		if ( $wantTheme ) {
			self::theme_checks( $out );
		}
		return $out;
	}

	private static function has_plugin_target( $targets ) {
		foreach ( $targets as $t ) {
			if ( 0 === strpos( $t, 'plugin:' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function row( $rule, $abs, $snippet = '', $line = 0 ) {
		return array(
			'rule'    => $rule,
			'fp'      => CS_Util::fingerprint( $rule, CS_Util::rel( $abs ), $snippet ),
			'abs'     => $abs,
			'rel'     => CS_Util::rel( $abs ),
			'line'    => (int) $line,
			'snippet' => $snippet,
			'extra'   => '',
			'owner'   => CS_Util::owner( $abs ),
		);
	}

	private static function plugin_checks( $targets, &$out ) {
		$map     = CS_Util::plugin_map();
		$updates = get_site_transient( 'update_plugins' );
		$php     = PHP_VERSION;
		$wp      = get_bloginfo( 'version' );
		$ignored = CS_Util::ignored();

		$only = array();
		foreach ( $targets as $t ) {
			if ( 0 === strpos( $t, 'plugin:' ) ) {
				$only[ substr( $t, 7 ) ] = 1;
			}
		}
		$activeOnly = in_array( 'active_plugins', $targets, true ) && ! in_array( 'all_plugins', $targets, true ) && ! in_array( 'everything', $targets, true );

		foreach ( $map as $slug => $p ) {
			if ( $only && ! isset( $only[ $slug ] ) ) {
				continue;
			}
			if ( $activeOnly && ! $p['active'] ) {
				continue;
			}
			$abs  = WP_PLUGIN_DIR . '/' . $p['basename'];
			$data = $p['data'];

			if ( ! empty( $data['RequiresPHP'] ) && version_compare( $php, $data['RequiresPHP'], '<' ) ) {
				$r = self::row( 'PLUGIN-REQ-PHP', $abs, sprintf( 'requires PHP %s, server has %s', $data['RequiresPHP'], $php ) );
				self::maybe_add( $r, $ignored, $out );
			}
			if ( ! empty( $data['RequiresWP'] ) && version_compare( $wp, $data['RequiresWP'], '<' ) ) {
				$r = self::row( 'PLUGIN-REQ-WP', $abs, sprintf( 'requires WP %s, site has %s', $data['RequiresWP'], $wp ) );
				self::maybe_add( $r, $ignored, $out );
			}
			if ( $updates && isset( $updates->response[ $p['basename'] ] ) ) {
				$new = $updates->response[ $p['basename'] ]->new_version;
				$r   = self::row( 'PLUGIN-UPDATE', $abs, sprintf( '%s → %s', $p['version'], $new ) );
				self::maybe_add( $r, $ignored, $out );
			}
			if ( ! $p['active'] && ! $activeOnly ) {
				$r = self::row( 'PLUGIN-INACTIVE', $abs, $p['name'] );
				self::maybe_add( $r, $ignored, $out );
			}

			// Integrity check against wordpress.org (only for active target or specific).
			self::integrity_check( $slug, $p, $out, $ignored );
		}
	}

	private static function theme_checks( &$out ) {
		$updates = get_site_transient( 'update_themes' );
		$ignored = CS_Util::ignored();
		foreach ( wp_get_themes() as $slug => $t ) {
			$abs = $t->get_stylesheet_directory();
			if ( $updates && isset( $updates->response[ $slug ] ) ) {
				$new = $updates->response[ $slug ]['new_version'];
				$r   = self::row( 'THEME-UPDATE', $abs, sprintf( '%s → %s', $t->get( 'Version' ), $new ) );
				self::maybe_add( $r, $ignored, $out );
			}
		}
	}

	private static function maybe_add( $r, $ignored, &$out ) {
		if ( ! isset( $ignored[ $r['fp'] ] ) ) {
			$out[] = $r;
		}
	}

	/**
	 * Verify files of a wordpress.org-hosted plugin against the official checksums API.
	 * Skips premium/non-repo plugins silently.
	 */
	private static function integrity_check( $slug, $p, &$out, $ignored ) {
		$version = $p['version'];
		if ( ! $version ) {
			return;
		}
		$cacheKey = 'cs_sums_' . md5( $slug . '|' . $version );
		$sums     = get_transient( $cacheKey );
		if ( false === $sums ) {
			$url  = sprintf( 'https://downloads.wordpress.org/plugin-checksums/%s/%s.json', rawurlencode( $slug ), rawurlencode( $version ) );
			$resp = wp_remote_get( $url, array( 'timeout' => 12 ) );
			if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
				set_transient( $cacheKey, array( 'files' => null ), DAY_IN_SECONDS );
				return; // not on .org or offline
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			$sums = ( isset( $body['files'] ) && is_array( $body['files'] ) ) ? array( 'files' => $body['files'] ) : array( 'files' => null );
			set_transient( $cacheKey, $sums, 7 * DAY_IN_SECONDS );
		}
		if ( empty( $sums['files'] ) ) {
			return;
		}

		$base = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $base ) ) {
			return;
		}

		$official = $sums['files'];
		// Compare official -> disk.
		$checked = 0;
		foreach ( $official as $file => $info ) {
			if ( $checked > 3000 ) {
				break;
			}
			$checked++;
			$abs = $base . '/' . $file;
			if ( ! file_exists( $abs ) ) {
				$r = self::row( 'INTEGRITY-MISSING', $abs, $file );
				self::maybe_add( $r, $ignored, $out );
				continue;
			}
			$expected = isset( $info['md5'] ) ? $info['md5'] : ( isset( $info['sha256'] ) ? $info['sha256'] : null );
			if ( ! $expected ) {
				continue;
			}
			$algo = isset( $info['md5'] ) ? 'md5' : 'sha256';
			$have = @hash_file( $algo, $abs );
			if ( $have && ! hash_equals( (string) ( is_array( $expected ) ? reset( $expected ) : $expected ), $have ) ) {
				$r = self::row( 'INTEGRITY-MODIFIED', $abs, $file );
				self::maybe_add( $r, $ignored, $out );
			}
		}
	}

	/**
	 * WordPress core checksum verification (separate, optional).
	 */
	public static function core_integrity() {
		$out     = array();
		$ignored = CS_Util::ignored();
		$ver     = get_bloginfo( 'version' );
		$locale  = get_locale();
		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$sums = get_core_checksums( $ver, $locale );
		if ( ! is_array( $sums ) ) {
			$sums = get_core_checksums( $ver, 'en_US' );
		}
		if ( ! is_array( $sums ) ) {
			return $out;
		}
		foreach ( $sums as $file => $md5 ) {
			if ( 0 === strpos( $file, 'wp-content/' ) ) {
				continue;
			}
			$abs = ABSPATH . $file;
			if ( ! file_exists( $abs ) ) {
				continue;
			}
			$have = @md5_file( $abs );
			if ( $have && ! hash_equals( $md5, $have ) ) {
				$out[] = self::row( 'CORE-MODIFIED', $abs, $file );
			}
		}
		return $out;
	}
}
