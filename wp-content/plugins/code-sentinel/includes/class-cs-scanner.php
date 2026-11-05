<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core scanner: parses files, blanks comments/strings for safe pattern matching, applies rules,
 * records the exact file path and line for every finding.
 */
class CS_Scanner {

	/** @var array findings */
	private $findings = array();
	/** @var array settings */
	private $opts;
	/** @var array global declaration index for duplicate detection: name => list of [rel,line] */
	private $decl = array();
	/** @var array stats */
	private $stats = array( 'files' => 0, 'scanned' => 0, 'skipped' => 0, 'unreadable' => 0 );
	/** @var array ignored fingerprints */
	private $ignored;

	public function __construct( array $opts = array() ) {
		$this->opts    = array_merge( CS_Util::settings(), $opts );
		$this->ignored = CS_Util::ignored();
	}

	/**
	 * Scan a list of targets. Returns the full result structure.
	 */
	public function run( array $targets ) {
		CS_Util::reset_owner_cache();
		$roots = array();
		foreach ( $targets as $t ) {
			$roots = array_merge( $roots, CS_Util::target_roots( $t ) );
		}
		return $this->scan_roots( $roots, $targets );
	}

	/**
	 * Scan explicit absolute paths (used by the daily cron fallback and tests).
	 */
	public function scan_roots( array $roots, array $targets = array() ) {
		$queue = CS_Util::list_files( $roots, $this->opts );
		$this->stats['files'] = count( $queue );

		$limit = $this->opts['max_kb'] * 1024;

		foreach ( $queue as $item ) {
			list( $abs, $flags, $kind ) = $item;

			if ( ( $flags & CS_Util::F_VENDOR ) && ! $this->opts['scan_vendor'] ) {
				// Still name-check vendor files but skip heavy content rules.
				$this->name_check( $abs, $kind );
				$this->stats['skipped']++;
				continue;
			}
			if ( ( $flags & CS_Util::F_MIN ) && ! $this->opts['include_min'] ) {
				$this->name_check( $abs, $kind );
				$this->stats['skipped']++;
				continue;
			}

			$this->name_check( $abs, $kind );
			$this->fs_check( $abs, $kind );

			$size = @filesize( $abs );
			if ( false !== $size && $size > $limit ) {
				$this->add( 'FILE-TOO-BIG', $abs, 0, '', sprintf( '%s KB', round( $size / 1024 ) ) );
				$this->stats['skipped']++;
				continue;
			}

			$src = @file_get_contents( $abs );
			if ( false === $src ) {
				$this->add( 'FILE-UNREADABLE', $abs, 0, '' );
				$this->stats['unreadable']++;
				continue;
			}

			$this->stats['scanned']++;

			if ( 'php' === $kind ) {
				$this->scan_php( $abs, $src );
			} elseif ( 'js' === $kind ) {
				$this->scan_generic( $abs, $src, 'js' );
			} elseif ( 'ht' === $kind ) {
				$this->scan_generic( $abs, $src, 'ht' );
			} elseif ( 'cfg' === $kind ) {
				$this->scan_generic( $abs, $src, 'cfg' );
			}
		}

		$this->detect_duplicates();

		return $this->result( $targets );
	}

	/* ---------------------------------------------------------------- name / filesystem */

	private function name_check( $abs, $kind ) {
		$rule = CS_Rules::suspicious_name( basename( $abs ) );
		if ( $rule ) {
			$this->add( $rule, $abs, 0, basename( $abs ) );
		}
		$owner = CS_Util::owner( $abs );
		if ( 0 === strpos( $owner, 'uploads:' ) && 'php' === $kind ) {
			$this->add( 'FILE-UPLOADS-PHP', $abs, 0, basename( $abs ) );
		}
	}

	private function fs_check( $abs, $kind ) {
		$perms = @fileperms( $abs );
		if ( false !== $perms && ( $perms & 0x0002 ) ) {
			$this->add( 'FILE-WORLD-WRITABLE', $abs, 0, substr( sprintf( '%o', $perms ), -4 ) );
		}
	}

	/* ---------------------------------------------------------------- PHP */

	private function scan_php( $abs, $src ) {
		$lines = explode( "\n", $src );

		// Leading output / BOM.
		if ( 0 === strncmp( $src, "\xEF\xBB\xBF", 3 ) ) {
			$this->add( 'PHP-BOM', $abs, 1, '' );
			$src = substr( $src, 3 );
		}
		$firstTag = strpos( $src, '<?php' );
		if ( false === $firstTag ) {
			$firstTag = strpos( $src, '<?' );
		}
		if ( false !== $firstTag && $firstTag > 0 ) {
			$pre = substr( $src, 0, $firstTag );
			if ( '' !== trim( $pre ) ) {
				$this->add( 'PHP-LEADING-OUTPUT', $abs, 1, trim( substr( $pre, 0, 60 ) ) );
			}
		}
		// Trailing output after the last closing tag.
		$lastClose = strrpos( $src, '?' . '>' );
		if ( false !== $lastClose && preg_match( '/\?>[\t ]*\r?\n\s*\r?\n\s*\S/s', substr( $src, $lastClose ) ) ) {
			$this->add( 'PHP-TRAILING-OUTPUT', $abs, count( $lines ), '' );
		}

		// Syntax check (uses the real PHP binary via lint of a temp copy when possible, else token check).
		$syntax = $this->php_lint( $src );
		if ( is_array( $syntax ) ) {
			$this->add( 'PHP-SYNTAX', $abs, $syntax['line'], $syntax['msg'] );
			// A parse error makes token analysis unreliable; still run text rules on blanked view below.
		}

		// Build comment-blanked and string-blanked views via tokenizer when the file parses.
		$views = $this->php_views( $src, is_array( $syntax ) );
		$codeView  = $views['code'];  // comments blanked, strings kept
		$nostrView = $views['nostr']; // comments + strings blanked

		$this->apply_rules( $abs, $lines, 'php', $codeView, $nostrView );

		// Deprecated WP functions (token-accurate, on nostr view).
		$this->wp_deprecated_check( $abs, $lines, $nostrView );

		// Structural / WordPress API checks.
		$this->php_structure_checks( $abs, $lines, $src, $codeView, $nostrView, ! is_array( $syntax ) );

		// Record declarations for duplicate detection (only when parseable).
		if ( ! is_array( $syntax ) && function_exists( 'token_get_all' ) ) {
			$this->index_declarations( $abs, $src );
			$this->index_includes( $abs, $lines, $src );
		}
	}

	/** Lint via php -l style using the tokenizer + a fallback shell when CLI available. */
	private function php_lint( $src ) {
		// Prefer a real lint using php_check_syntax-like approach: write temp + exec if allowed.
		if ( function_exists( 'exec' ) && ! in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
			$php = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
			$tmp = wp_tempnam( 'cs-lint' );
			if ( $tmp && false !== @file_put_contents( $tmp, $src ) ) {
				$out = array();
				$ret = 0;
				@exec( escapeshellarg( $php ) . ' -d display_errors=1 -l ' . escapeshellarg( $tmp ) . ' 2>&1', $out, $ret );
				@unlink( $tmp );
				if ( 0 !== $ret ) {
					$msg  = implode( ' ', $out );
					$line = 0;
					if ( preg_match( '/on line (\d+)/', $msg, $mm ) ) {
						$line = (int) $mm[1];
					}
					$msg = preg_replace( '/ in \/\S+ on line \d+/', '', $msg );
					$msg = preg_replace( '/ in \S+$/', '', $msg );
					$msg = str_replace( array( 'Errors parsing ' . $tmp, $tmp ), '', $msg );
					$msg = trim( preg_replace( '/^(PHP )?(Parse|Fatal) error:\s*/i', '', $msg ) );
					return array( 'line' => $line, 'msg' => substr( $msg, 0, 200 ) );
				}
				return true; // parsed cleanly
			}
		}
		// Fallback: tokenizer-based brace/paren/bracket + unterminated string heuristic.
		return $this->token_syntax_fallback( $src );
	}

	private function token_syntax_fallback( $src ) {
		if ( ! function_exists( 'token_get_all' ) ) {
			return true;
		}
		$prev = error_reporting( 0 );
		$toks = @token_get_all( $src );
		error_reporting( $prev );
		$stack = array();
		$pairs = array( ')' => '(', ']' => '[', '}' => '{' );
		$open  = array( '(' => 1, '[' => 1, '{' => 1 );
		$lineNo = 1;
		foreach ( $toks as $t ) {
			if ( is_array( $t ) ) {
				$lineNo = $t[2] + substr_count( $t[1], "\n" );
				continue;
			}
			if ( isset( $open[ $t ] ) ) {
				$stack[] = array( $t, $lineNo );
			} elseif ( isset( $pairs[ $t ] ) ) {
				$last = array_pop( $stack );
				if ( ! $last || $last[0] !== $pairs[ $t ] ) {
					return array( 'line' => $lineNo, 'msg' => "unexpected '{$t}' (unbalanced brackets)" );
				}
			}
		}
		if ( ! empty( $stack ) ) {
			$o = end( $stack );
			return array( 'line' => $o[1], 'msg' => "unclosed '{$o[0]}'" );
		}
		return true;
	}

	/** Returns ['code'=>..,'nostr'=>..] strings same length as source with comments (and strings) blanked. */
	private function php_views( $src, $parseError ) {
		if ( $parseError || ! function_exists( 'token_get_all' ) ) {
			// Regex fallback: blank /* */, //, # comments.
			$code = preg_replace_callback(
				'~/\*.*?\*/|//[^\n]*|#[^\n]*~s',
				function ( $m ) { return preg_replace( '/[^\n]/', ' ', $m[0] ); },
				$src
			);
			$nostr = preg_replace_callback(
				'~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'~s',
				function ( $m ) { return $m[0][0] . str_repeat( ' ', max( 0, strlen( $m[0] ) - 2 ) ) . $m[0][0]; },
				$code
			);
			return array( 'code' => $code, 'nostr' => $nostr );
		}
		$prev = error_reporting( 0 );
		$toks = @token_get_all( $src );
		error_reporting( $prev );
		$code = '';
		$nostr = '';
		foreach ( $toks as $t ) {
			if ( is_array( $t ) ) {
				$id   = $t[0];
				$text = $t[1];
				if ( T_COMMENT === $id || T_DOC_COMMENT === $id ) {
					$blank  = preg_replace( '/[^\n]/', ' ', $text );
					$code  .= $blank;
					$nostr .= $blank;
					continue;
				}
				if ( T_CONSTANT_ENCAPSED_STRING === $id || T_ENCAPSED_AND_WHITESPACE === $id || T_INLINE_HTML === $id ) {
					$code .= $text;
					// Blank string interior but keep newlines & length.
					$nostr .= preg_replace( '/[^\n]/', ' ', $text );
					continue;
				}
				$code  .= $text;
				$nostr .= $text;
			} else {
				$code  .= $t;
				$nostr .= $t;
			}
		}
		return array( 'code' => $code, 'nostr' => $nostr );
	}

	/* ---------------------------------------------------------------- generic (js/ht/cfg) */

	private function scan_generic( $abs, $src, $kind ) {
		$lines = explode( "\n", $src );
		if ( 'js' === $kind ) {
			$code = preg_replace_callback(
				'~/\*.*?\*/|//[^\n]*~s',
				function ( $m ) { return preg_replace( '/[^\n]/', ' ', $m[0] ); },
				$src
			);
			$nostr = preg_replace_callback(
				'~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`(?:\\\\.|[^`\\\\])*`~s',
				function ( $m ) { return $m[0][0] . preg_replace( '/[^\n]/', ' ', substr( $m[0], 1, -1 ) ) . substr( $m[0], -1 ); },
				$code
			);
			$this->apply_rules( $abs, $lines, 'js', $code, $nostr );
			// Secrets (lang any) run on js code view too.
			$this->apply_rules( $abs, $lines, 'any', $code, $nostr );
		} elseif ( 'ht' === $kind ) {
			$code = preg_replace( '/^\s*#.*$/m', '', $src );
			$this->apply_rules( $abs, $lines, 'ht', $code, $code );
		} elseif ( 'cfg' === $kind ) {
			// Config: secrets only, raw view.
			$this->apply_rules( $abs, $lines, 'any', $src, $src );
		}
	}

	/* ---------------------------------------------------------------- rule application */

	private function apply_rules( $abs, array $lines, $lang, $codeView, $nostrView ) {
		foreach ( CS_Rules::for_kind( $lang ) as $r ) {
			$hay = ( 'nostr' === $r['view'] ) ? $nostrView : $codeView;
			if ( false === @preg_match( $r['re'], '' ) ) {
				continue; // guard against a broken pattern
			}
			if ( ! preg_match_all( $r['re'], $hay, $m, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$hits = 0;
			foreach ( $m[0] as $match ) {
				$offset = $match[1];
				$line   = substr_count( $hay, "\n", 0, $offset ) + 1;
				$col    = $offset - ( strrpos( substr( $hay, 0, $offset ), "\n" ) ?: -1 ) - 1;
				$snip   = CS_Util::snippet( $lines, $line, $col );
				$this->add( $r['id'], $abs, $line, $snip );
				if ( ++$hits >= 50 ) {
					break; // cap noisy files
				}
			}
		}
	}

	private function wp_deprecated_check( $abs, array $lines, $nostrView ) {
		$map = CS_Rules::wp_deprecated();
		if ( ! preg_match_all( '/(?<![\w>:$\\\\])([a-z_]\w+)\s*\(/i', $nostrView, $m, PREG_OFFSET_CAPTURE ) ) {
			return;
		}
		foreach ( $m[1] as $fn ) {
			$name = strtolower( $fn[0] );
			if ( isset( $map[ $name ] ) ) {
				$line = substr_count( $nostrView, "\n", 0, $fn[1] ) + 1;
				$this->add( 'PHP-DEPRECATED-WP', $abs, $line, $fn[0] . '()', 'Use ' . $map[ $name ] );
			}
		}
	}

	/* ---------------------------------------------------------------- PHP structure / WP API */

	private function php_structure_checks( $abs, array $lines, $src, $codeView, $nostrView, $parsed ) {
		// Direct-access guard: file executes top-level code without ABSPATH/defined guard.
		$owner = CS_Util::owner( $abs );
		if ( in_array( explode( ':', $owner )[0], array( 'plugin', 'mu-plugin', 'theme' ), true ) ) {
			if ( ! preg_match( '/defined\s*\(\s*[\'"]ABSPATH[\'"]|ABSPATH|WPINC|defined\s*\(\s*[\'"]WPINC/', $codeView )
				&& preg_match( '/<\?php/', $src )
				&& $this->has_toplevel_code( $nostrView ) ) {
				$this->add( 'PHP-NO-GUARD', $abs, 1, '' );
			}
		}

		// AJAX handlers.
		$hasAjaxPriv   = preg_match( '/add_action\s*\(\s*[\'"]wp_ajax_(?!nopriv_)/', $codeView );
		$hasAjaxNopriv = preg_match_all( '/add_action\s*\(\s*[\'"]wp_ajax_nopriv_([\w-]+)/', $codeView, $nm, PREG_OFFSET_CAPTURE );
		if ( $hasAjaxNopriv ) {
			foreach ( $nm[0] as $i => $match ) {
				$line = substr_count( $codeView, "\n", 0, $match[1] ) + 1;
				$this->add( 'WP-AJAX-NOPRIV', $abs, $line, 'wp_ajax_nopriv_' . $nm[1][ $i ][0] );
			}
		}
		if ( $hasAjaxPriv || $hasAjaxNopriv ) {
			$pos = strpos( $codeView, 'wp_ajax' );
			$line = false !== $pos ? substr_count( $codeView, "\n", 0, $pos ) + 1 : 0;
			if ( ! preg_match( '/current_user_can\s*\(/', $codeView ) ) {
				$this->add( 'WP-AJAX-NOCAP', $abs, $line, '' );
			}
			if ( ! preg_match( '/check_ajax_referer\s*\(|wp_verify_nonce\s*\(|check_admin_referer\s*\(/', $codeView ) ) {
				$this->add( 'WP-AJAX-NONONCE', $abs, $line, '' );
			}
		}

		// REST routes.
		if ( preg_match_all( '/register_rest_route\s*\(/', $codeView, $rm, PREG_OFFSET_CAPTURE ) ) {
			// Look for permission_callback presence in the file.
			if ( ! preg_match( '/permission_callback/', $codeView ) ) {
				$line = substr_count( $codeView, "\n", 0, $rm[0][0][1] ) + 1;
				$this->add( 'WP-REST-NOPERM', $abs, $line, '' );
			} elseif ( preg_match( '/permission_callback[\'"]?\s*=>\s*[\'"]__return_true[\'"]/', $codeView, $pm, PREG_OFFSET_CAPTURE ) ) {
				$line = substr_count( $codeView, "\n", 0, $pm[0][1] ) + 1;
				$this->add( 'WP-REST-PUBLIC', $abs, $line, '' );
			}
		}

		// Nonce absence on request processing (admin-ish files only, reduce noise).
		if ( preg_match( '/\$_(POST|REQUEST)\b/', $nostrView )
			&& ! preg_match( '/wp_verify_nonce\s*\(|check_admin_referer\s*\(|check_ajax_referer\s*\(/', $codeView )
			&& ! $hasAjaxPriv && ! $hasAjaxNopriv
			&& preg_match( '/update_option\s*\(|update_user_meta\s*\(|update_post_meta\s*\(|wp_insert_|wp_update_|delete_option\s*\(/', $codeView ) ) {
			$pos  = strpos( $nostrView, '$_POST' );
			$pos  = false === $pos ? strpos( $nostrView, '$_REQUEST' ) : $pos;
			$line = false !== $pos ? substr_count( $nostrView, "\n", 0, $pos ) + 1 : 0;
			$this->add( 'WP-NONCE-MISSING', $abs, $line, '' );
		}
	}

	private function has_toplevel_code( $nostrView ) {
		// crude: look for an echo/require/call at column 0-ish outside function/class bodies is hard;
		// treat presence of top-level statements by checking for code after the first guard-less tag.
		return (bool) preg_match( '/^\s*(?:echo|print|include|require|\$\w+\s*=|wp_|add_action|add_filter|if\s*\()/m', $nostrView );
	}

	/* ---------------------------------------------------------------- declarations & includes */

	private function index_declarations( $abs, $src ) {
		$rel  = CS_Util::rel( $abs );
		$prev = error_reporting( 0 );
		$toks = @token_get_all( $src );
		error_reporting( $prev );
		$ns   = '';
		$depth = 0;
		$fnDepth = array();
		$count = count( $toks );
		for ( $i = 0; $i < $count; $i++ ) {
			$t = $toks[ $i ];
			if ( is_array( $t ) && T_NAMESPACE === $t[0] ) {
				$ns = '';
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $toks[ $j ] ) && in_array( $toks[ $j ][0], array( T_STRING, T_NS_SEPARATOR ), true ) ) {
						$ns .= $toks[ $j ][1];
					} elseif ( ';' === $toks[ $j ] || '{' === $toks[ $j ] ) {
						break;
					}
				}
				continue;
			}
			if ( is_array( $t ) && in_array( $t[0], array( T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT ), true ) ) {
				// find name
				for ( $j = $i + 1; $j < $count; $j++ ) {
					$tj = $toks[ $j ];
					if ( is_array( $tj ) && T_STRING === $tj[0] ) {
						$kind = ( T_FUNCTION === $t[0] ) ? 'function' : 'type';
						// skip anonymous functions / methods inside classes: only index top-level fns and any class/type
						$name = $tj[1];
						$full = ( $ns ? $ns . '\\' : '' ) . $name;
						$key  = $kind . ':' . strtolower( $full );
						// Heuristic: ignore methods (function preceded by visibility inside class) — detect '(' ok, but methods are fine to skip:
						if ( T_FUNCTION === $t[0] && $this->prev_is_visibility( $toks, $i ) ) {
							break;
						}
						$this->decl[ $key ][] = array( $rel, $abs, $t[2] );
						break;
					}
					if ( '(' === $tj || ';' === $tj ) {
						break; // anonymous
					}
				}
			}
		}
	}

	private function prev_is_visibility( $toks, $i ) {
		for ( $j = $i - 1; $j >= 0 && $j > $i - 4; $j-- ) {
			$t = $toks[ $j ];
			if ( is_array( $t ) ) {
				if ( in_array( $t[0], array( T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL ), true ) ) {
					return true;
				}
				if ( T_WHITESPACE === $t[0] ) {
					continue;
				}
			}
			return false;
		}
		return false;
	}

	private function index_includes( $abs, array $lines, $src ) {
		$dir = dirname( $abs );
		if ( ! preg_match_all( '/\b(include|require)(_once)?\s*(?:\(\s*)?([\'"])([^\'"$]+)\3/i', $src, $m, PREG_OFFSET_CAPTURE ) ) {
			return;
		}
		foreach ( $m[4] as $i => $path ) {
			$p = $path[0];
			if ( preg_match( '#^https?://#i', $p ) ) {
				continue; // handled by remote-include rule
			}
			// Resolve relative to file dir and a couple of common bases.
			$cand = $p;
			$cand = str_replace( array( '__DIR__', '__FILE__' ), '', $cand );
			$resolved = $this->resolve_include( $dir, $m[0][ $i ][0] );
			if ( null === $resolved ) {
				continue; // dynamic, skip
			}
			if ( ! file_exists( $resolved ) ) {
				$line = substr_count( $src, "\n", 0, $m[0][ $i ][1] ) + 1;
				$this->add( 'PHP-MISSING-INCLUDE', $abs, $line, $p );
			}
		}
	}

	private function resolve_include( $dir, $stmt ) {
		// Only resolve clearly static forms: dirname(__FILE__).'/x', __DIR__.'/x', plugin_dir_path(__FILE__).'x', or bare 'x'.
		if ( preg_match( '/(?:__DIR__|dirname\s*\(\s*__FILE__\s*\)|plugin_dir_path\s*\(\s*__FILE__\s*\))\s*\.\s*([\'"])([^\'"]+)\1/', $stmt, $mm ) ) {
			return CS_Util::norm( $dir . '/' . ltrim( $mm[2], '/' ) );
		}
		if ( preg_match( '/(?:include|require)(?:_once)?\s*(?:\(\s*)?([\'"])([^\'"$.]+)\1\s*\)?\s*;/', $stmt, $mm ) ) {
			$p = $mm[2];
			if ( preg_match( '#^(/|[A-Za-z]:)#', $p ) ) {
				return CS_Util::norm( $p );
			}
			return CS_Util::norm( $dir . '/' . $p );
		}
		return null;
	}

	private function detect_duplicates() {
		foreach ( $this->decl as $key => $places ) {
			if ( count( $places ) < 2 ) {
				continue;
			}
			// Group owners: a conflict matters most across different plugins/files.
			list( $kind, $name ) = explode( ':', $key, 2 );
			$label = ( 'function' === $kind ? 'function ' : 'class/type ' ) . $name;
			foreach ( $places as $p ) {
				$others = array();
				foreach ( $places as $q ) {
					if ( $q[0] !== $p[0] || $q[2] !== $p[2] ) {
						$others[] = $q[0] . ':' . $q[2];
					}
				}
				$this->add( 'PHP-DUP-DECL', $p[1], $p[2], $label . ' — also in ' . implode( ', ', array_slice( array_unique( $others ), 0, 4 ) ) );
			}
		}
	}

	/* ---------------------------------------------------------------- finding sink */

	private function add( $rule, $abs, $line, $snippet = '', $extra = '' ) {
		$rel   = CS_Util::rel( $abs );
		$fp    = CS_Util::fingerprint( $rule, $rel, $line . '|' . $snippet );
		if ( isset( $this->ignored[ $fp ] ) ) {
			return;
		}
		$owner = CS_Util::owner( $abs );
		$this->findings[] = array(
			'rule'    => $rule,
			'fp'      => $fp,
			'abs'     => $abs,
			'rel'     => $rel,
			'line'    => (int) $line,
			'snippet' => $snippet,
			'extra'   => $extra,
			'owner'   => $owner,
		);
	}

	public function add_external( array $f ) {
		$this->findings[] = $f;
	}

	/* ---------------------------------------------------------------- result */

	private function result( $targets ) {
		$meta  = CS_Rules::meta_map();
		$sev   = array( 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0 );
		$byOwner = array();
		$owners  = CS_Util::owners();

		foreach ( $this->findings as &$f ) {
			$m         = isset( $meta[ $f['rule'] ] ) ? $meta[ $f['rule'] ] : array( 'sev' => 'info', 'cat' => 'Other', 'title' => $f['rule'], 'desc' => '', 'fix' => '' );
			$f['sev']  = $m['sev'];
			$f['cat']  = $m['cat'];
			$f['title'] = $m['title'];
			$f['desc'] = $m['desc'];
			$f['fix']  = $f['extra'] ? ( $m['fix'] . ( $m['fix'] ? ' ' : '' ) . ( 0 === strpos( $f['extra'], 'Use ' ) ? '(' . $f['extra'] . ')' : '' ) ) : $m['fix'];
			if ( isset( $sev[ $f['sev'] ] ) ) {
				$sev[ $f['sev'] ]++;
			}
			$ok = $f['owner'];
			if ( ! isset( $byOwner[ $ok ] ) ) {
				$om = isset( $owners[ $ok ] ) ? $owners[ $ok ] : array( 'type' => explode( ':', $ok )[0], 'slug' => $ok, 'name' => $ok, 'active' => true, 'version' => '' );
				$byOwner[ $ok ] = array( 'meta' => $om, 'count' => 0, 'top' => 'info' );
			}
			$byOwner[ $ok ]['count']++;
			if ( CS_Util::sev_rank( $f['sev'] ) > CS_Util::sev_rank( $byOwner[ $ok ]['top'] ) ) {
				$byOwner[ $ok ]['top'] = $f['sev'];
			}
		}
		unset( $f );

		// Sort findings: severity desc, then owner, then file, then line.
		usort( $this->findings, function ( $a, $b ) {
			$d = CS_Util::sev_rank( $b['sev'] ) - CS_Util::sev_rank( $a['sev'] );
			if ( $d ) { return $d; }
			$d = strcmp( $a['rel'], $b['rel'] );
			if ( $d ) { return $d; }
			return $a['line'] - $b['line'];
		} );

		uasort( $byOwner, function ( $a, $b ) {
			$d = CS_Util::sev_rank( $b['top'] ) - CS_Util::sev_rank( $a['top'] );
			return $d ? $d : $b['count'] - $a['count'];
		} );

		return array(
			'time'     => time(),
			'targets'  => $targets,
			'stats'    => $this->stats,
			'severity' => $sev,
			'owners'   => $byOwner,
			'findings' => $this->findings,
			'php'      => PHP_VERSION,
			'wp'       => get_bloginfo( 'version' ),
		);
	}
}
