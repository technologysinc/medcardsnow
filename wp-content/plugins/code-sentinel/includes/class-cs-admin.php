<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin dashboard, AJAX scan runner, settings, exports, cron.
 */
class CS_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'code-sentinel';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_cs_scan', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_cs_result', array( __CLASS__, 'ajax_result' ) );
		add_action( 'wp_ajax_cs_ignore', array( __CLASS__, 'ajax_ignore' ) );
		add_action( 'wp_ajax_cs_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_cs_clear_runtime', array( __CLASS__, 'ajax_clear_runtime' ) );
		add_action( 'wp_ajax_cs_export', array( __CLASS__, 'ajax_export' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CS_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function action_link( $links ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Scan</a>' );
		return $links;
	}

	public static function menu() {
		$hook = add_management_page(
			'Code Sentinel',
			'Code Sentinel',
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
		add_action( 'load-' . $hook, array( __CLASS__, 'add_help' ) );
	}

	public static function add_help() {
		$screen = get_current_screen();
		if ( $screen ) {
			$screen->add_help_tab( array(
				'id'      => 'cs-help',
				'title'   => 'About',
				'content' => '<p>Code Sentinel scans plugin, theme, mu-plugin and uploads files for active errors (syntax / fatal / conflicts), passive risks (security, deprecations, performance, malware indicators) and integrity changes, and captures live PHP &amp; JavaScript runtime errors. Every finding shows the exact file path and line.</p>',
			) );
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'cs-admin', CS_URL . 'assets/admin.css', array(), CS_VERSION );
		wp_enqueue_script( 'cs-admin', CS_URL . 'assets/admin.js', array( 'jquery' ), CS_VERSION, true );
		wp_localize_script( 'cs-admin', 'CS', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cs_admin' ),
			'targets' => CS_Util::target_choices(),
		) );
	}

	/* ---------------------------------------------------------------- render */

	public static function render() {
		$s        = CS_Util::settings();
		$choices  = CS_Util::target_choices();
		$last     = get_option( 'cs_last_summary', array() );
		$runtime  = CS_Runtime::as_findings();
		$rtCount  = count( $runtime );
		?>
		<div class="wrap cs-wrap">
			<h1>Code Sentinel <span class="cs-ver">v<?php echo esc_html( CS_VERSION ); ?></span></h1>
			<p class="cs-sub">Deep scanner for active &amp; passive issues in plugins, themes and scripts — with exact file path and line. PHP <?php echo esc_html( PHP_VERSION ); ?> · WordPress <?php echo esc_html( get_bloginfo( 'version' ) ); ?></p>

			<nav class="cs-tabs">
				<a href="#scan" class="cs-tab cs-active" data-tab="scan">Scanner</a>
				<a href="#runtime" class="cs-tab" data-tab="runtime">Live errors <span class="cs-badge"><?php echo (int) $rtCount; ?></span></a>
				<a href="#settings" class="cs-tab" data-tab="settings">Settings</a>
			</nav>

			<section class="cs-panel cs-active" id="cs-scan">
				<div class="cs-controls">
					<label for="cs-target"><strong>Scan target</strong></label>
					<select id="cs-target">
						<?php foreach ( $choices as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( 'active_plugins', $k ); ?>><?php echo esc_html( $v ); ?></option>
						<?php endforeach; ?>
					</select>
					<label class="cs-chk"><input type="checkbox" id="cs-core"> Also verify WordPress core files</label>
					<button class="button button-primary" id="cs-run">Run scan</button>
					<button class="button" id="cs-export" disabled>Export CSV</button>
					<button class="button" id="cs-export-json" disabled>Export JSON</button>
				</div>

				<div id="cs-progress" class="cs-progress" hidden>
					<div class="cs-bar"><div class="cs-bar-fill"></div></div>
					<span class="cs-progress-text">Starting…</span>
				</div>

				<?php if ( $last ) : ?>
					<p class="cs-last">Last scan: <?php echo esc_html( human_time_diff( $last['time'] ) ); ?> ago — <?php echo (int) $last['total']; ?> findings.</p>
				<?php endif; ?>

				<div id="cs-results"></div>
			</section>

			<section class="cs-panel" id="cs-runtime">
				<div class="cs-controls">
					<p class="cs-sub">Errors, warnings and deprecations captured live while WordPress served real requests. <?php echo $s['runtime'] ? '' : '<strong>PHP capture is OFF — enable it in Settings.</strong>'; ?></p>
					<button class="button" id="cs-clear-runtime">Clear log</button>
				</div>
				<div id="cs-runtime-list"><?php self::render_findings_html( $runtime, true ); ?></div>
			</section>

			<section class="cs-panel" id="cs-settings">
				<form id="cs-settings-form">
					<table class="form-table" role="presentation">
						<tr><th>Capture PHP runtime errors</th><td><label><input type="checkbox" name="runtime" <?php checked( $s['runtime'] ); ?>> Record errors, warnings, notices &amp; deprecations from wp-content as they happen</label></td></tr>
						<tr><th>Capture JavaScript errors</th><td><label><input type="checkbox" name="js_errors" <?php checked( $s['js_errors'] ); ?>> Record front-end &amp; admin JS errors (logged-in users)</label></td></tr>
						<tr><th>Scan <code>.min.js</code> files</th><td><label><input type="checkbox" name="include_min" <?php checked( $s['include_min'] ); ?>> Include minified JavaScript</label></td></tr>
						<tr><th>Scan <code>vendor/</code> directories</th><td><label><input type="checkbox" name="scan_vendor" <?php checked( $s['scan_vendor'] ); ?>> Apply rules inside bundled libraries (noisier)</label></td></tr>
						<tr><th>Max file size (KB)</th><td><input type="number" name="max_kb" min="64" max="8192" value="<?php echo (int) $s['max_kb']; ?>"> <span class="description">Files larger than this are listed but not content-scanned.</span></td></tr>
						<tr><th>Daily background scan</th><td><label><input type="checkbox" name="daily" <?php checked( $s['daily'] ); ?>> Scan active plugins daily</label></td></tr>
						<tr><th>Email alerts to</th><td><input type="email" name="email" value="<?php echo esc_attr( $s['email'] ); ?>" class="regular-text"> <span class="description">For critical/high findings from the daily scan.</span></td></tr>
					</table>
					<p><button class="button button-primary" id="cs-save-settings">Save settings</button> <span id="cs-settings-msg"></span></p>
				</form>
				<hr>
				<h3>Ignored findings</h3>
				<?php $ig = CS_Util::ignored(); ?>
				<p class="cs-sub"><?php echo count( $ig ); ?> finding(s) are hidden. <button class="button-link" id="cs-reset-ignores">Reset all</button></p>
			</section>
		</div>
		<?php
	}

	/** Server-side render of findings (used for runtime tab and as AJAX template fallback). */
	public static function render_findings_html( array $findings, $runtime = false ) {
		if ( empty( $findings ) ) {
			echo '<div class="cs-empty">';
			echo $runtime ? '<p>No runtime errors recorded yet. Browse the site while logged out and come back.</p>' : '<p>No findings.</p>';
			echo '</div>';
			return;
		}
		$byOwner = array();
		foreach ( $findings as $f ) {
			$byOwner[ $f['owner'] ][] = $f;
		}
		$meta = CS_Rules::meta_map();
		foreach ( $byOwner as $owner => $list ) {
			$om    = CS_Util::owners();
			$label = isset( $om[ $owner ] ) ? $om[ $owner ]['name'] : $owner;
			echo '<div class="cs-owner">';
			echo '<h3>' . esc_html( $label ) . ' <span class="cs-count">' . count( $list ) . '</span></h3>';
			foreach ( $list as $f ) {
				self::finding_row( $f, $meta );
			}
			echo '</div>';
		}
	}

	private static function finding_row( $f, $meta ) {
		$m   = isset( $meta[ $f['rule'] ] ) ? $meta[ $f['rule'] ] : array( 'sev' => 'info', 'title' => $f['rule'], 'cat' => '', 'desc' => '', 'fix' => '' );
		$sev = $m['sev'];
		$loc = esc_html( $f['rel'] ) . ( $f['line'] ? ':' . (int) $f['line'] : '' );
		echo '<div class="cs-finding cs-sev-' . esc_attr( $sev ) . '" data-fp="' . esc_attr( $f['fp'] ) . '">';
		echo '<div class="cs-f-head">';
		echo '<span class="cs-pill cs-pill-' . esc_attr( $sev ) . '">' . esc_html( ucfirst( $sev ) ) . '</span> ';
		echo '<span class="cs-f-title">' . esc_html( $m['title'] ) . '</span>';
		if ( ! empty( $f['count'] ) && $f['count'] > 1 ) {
			echo ' <span class="cs-x">×' . (int) $f['count'] . '</span>';
		}
		echo '<span class="cs-cat">' . esc_html( $m['cat'] ) . '</span>';
		echo '</div>';
		echo '<div class="cs-f-loc"><code>' . $loc . '</code>';
		echo '<button class="cs-copy" data-path="' . esc_attr( $f['rel'] . ( $f['line'] ? ':' . $f['line'] : '' ) ) . '" title="Copy path">⧉</button></div>';
		if ( ! empty( $f['snippet'] ) ) {
			echo '<pre class="cs-snip">' . esc_html( $f['snippet'] ) . '</pre>';
		}
		if ( ! empty( $m['desc'] ) ) {
			echo '<div class="cs-f-desc">' . esc_html( $m['desc'] ) . '</div>';
		}
		if ( ! empty( $m['fix'] ) ) {
			echo '<div class="cs-f-fix"><strong>Fix:</strong> ' . esc_html( $m['fix'] ) . '</div>';
		}
		if ( empty( $f['runtime'] ) ) {
			echo '<button class="button-link cs-ignore" data-fp="' . esc_attr( $f['fp'] ) . '">Ignore this finding</button>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------- AJAX */

	private static function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'permission', 403 );
		}
		check_ajax_referer( 'cs_admin', 'nonce' );
	}

	public static function ajax_scan() {
		self::guard();
		@set_time_limit( 0 );
		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : 'active_plugins';
		$core   = ! empty( $_POST['core'] );
		$choices = CS_Util::target_choices();
		if ( ! isset( $choices[ $target ] ) ) {
			wp_send_json_error( 'bad target' );
		}

		$scanner = new CS_Scanner();
		$result  = $scanner->run( array( $target ) );

		// Add metadata checks (versions/updates/integrity).
		foreach ( CS_Checks::run( array( $target ) ) as $row ) {
			$result = self::merge_row( $result, $row );
		}
		if ( $core ) {
			foreach ( CS_Checks::core_integrity() as $row ) {
				$result = self::merge_row( $result, $row );
			}
		}
		$result = self::finalize( $result );

		update_option( 'cs_last_summary', array( 'time' => time(), 'total' => count( $result['findings'] ) ), false );
		CS_Util::write_json( CS_Util::data_path( 'lastscan' ), $result );

		wp_send_json_success( $result );
	}

	public static function ajax_result() {
		self::guard();
		$r = CS_Util::read_json( CS_Util::data_path( 'lastscan' ) );
		wp_send_json_success( $r ?: array() );
	}

	public static function ajax_ignore() {
		self::guard();
		$fp  = isset( $_POST['fp'] ) ? sanitize_text_field( wp_unslash( $_POST['fp'] ) ) : '';
		$all = ! empty( $_POST['all'] );
		$ig  = CS_Util::ignored();
		if ( $all ) {
			$ig = array();
		} elseif ( $fp ) {
			$ig[ $fp ] = time();
		}
		update_option( CS_Util::OPT_IGNORED, $ig, false );
		wp_send_json_success( array( 'count' => count( $ig ) ) );
	}

	public static function ajax_save_settings() {
		self::guard();
		$in = array();
		parse_str( isset( $_POST['form'] ) ? wp_unslash( $_POST['form'] ) : '', $in );
		$s = CS_Util::save_settings( $in );
		self::reschedule();
		wp_send_json_success( $s );
	}

	public static function ajax_clear_runtime() {
		self::guard();
		CS_Runtime::clear();
		wp_send_json_success();
	}

	/* ---------------------------------------------------------------- merge helpers */

	private static function merge_row( $result, $row ) {
		$result['_extra'][] = $row;
		return $result;
	}

	private static function finalize( $result ) {
		$meta = CS_Rules::meta_map();
		$extra = isset( $result['_extra'] ) ? $result['_extra'] : array();
		unset( $result['_extra'] );
		$ignored = CS_Util::ignored();
		foreach ( $extra as $f ) {
			if ( isset( $ignored[ $f['fp'] ] ) ) {
				continue;
			}
			$m = isset( $meta[ $f['rule'] ] ) ? $meta[ $f['rule'] ] : null;
			if ( ! $m ) { continue; }
			$f['sev'] = $m['sev']; $f['cat'] = $m['cat']; $f['title'] = $m['title']; $f['desc'] = $m['desc']; $f['fix'] = $m['fix'];
			$result['findings'][] = $f;
			if ( isset( $result['severity'][ $f['sev'] ] ) ) {
				$result['severity'][ $f['sev'] ]++;
			}
			$ok = $f['owner'];
			if ( ! isset( $result['owners'][ $ok ] ) ) {
				$om = CS_Util::owners();
				$result['owners'][ $ok ] = array( 'meta' => isset( $om[ $ok ] ) ? $om[ $ok ] : array( 'name' => $ok, 'type' => 'other' ), 'count' => 0, 'top' => 'info' );
			}
			$result['owners'][ $ok ]['count']++;
			if ( CS_Util::sev_rank( $f['sev'] ) > CS_Util::sev_rank( $result['owners'][ $ok ]['top'] ) ) {
				$result['owners'][ $ok ]['top'] = $f['sev'];
			}
		}
		usort( $result['findings'], function ( $a, $b ) {
			$d = CS_Util::sev_rank( $b['sev'] ) - CS_Util::sev_rank( $a['sev'] );
			if ( $d ) { return $d; }
			$d = strcmp( $a['rel'], $b['rel'] );
			return $d ? $d : $a['line'] - $b['line'];
		} );
		return $result;
	}

	/* ---------------------------------------------------------------- export */

	public static function maybe_export() {
		if ( empty( $_GET['cs_export'] ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		check_admin_referer( 'cs_export' );
		$r = CS_Util::read_json( CS_Util::data_path( 'lastscan' ) );
		$fmt = 'json' === ( $_GET['cs_export'] ) ? 'json' : 'csv';
		if ( ! $r ) {
			wp_die( 'No scan results to export.' );
		}
		if ( 'json' === $fmt ) {
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="code-sentinel-' . gmdate( 'Ymd-His' ) . '.json"' );
			echo wp_json_encode( $r, JSON_PRETTY_PRINT );
			exit;
		}
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="code-sentinel-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Severity', 'Category', 'Issue', 'Owner', 'File path', 'Line', 'Detail', 'Fix' ) );
		$om = array();
		foreach ( $r['findings'] as $f ) {
			fputcsv( $out, array(
				CS_Util::csv_cell( ucfirst( $f['sev'] ) ),
				CS_Util::csv_cell( $f['cat'] ),
				CS_Util::csv_cell( $f['title'] ),
				CS_Util::csv_cell( isset( $f['owner'] ) ? $f['owner'] : '' ),
				CS_Util::csv_cell( $f['rel'] ),
				CS_Util::csv_cell( $f['line'] ),
				CS_Util::csv_cell( $f['snippet'] ),
				CS_Util::csv_cell( isset( $f['fix'] ) ? $f['fix'] : '' ),
			) );
		}
		fclose( $out );
		exit;
	}

	public static function ajax_export() {
		self::guard();
		wp_send_json_success( array( 'url' => wp_nonce_url( admin_url( 'admin.php?page=' . self::SLUG . '&cs_export=csv' ), 'cs_export' ) ) );
	}

	/* ---------------------------------------------------------------- cron */

	public static function reschedule() {
		$s = CS_Util::settings();
		wp_clear_scheduled_hook( 'cs_daily_scan' );
		if ( $s['daily'] ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cs_daily_scan' );
		}
	}

	public static function cron_scan() {
		$scanner = new CS_Scanner();
		$result  = $scanner->run( array( 'active_plugins' ) );
		foreach ( CS_Checks::run( array( 'active_plugins' ) ) as $row ) {
			$result = self::merge_row( $result, $row );
		}
		$result = self::finalize( $result );
		CS_Util::write_json( CS_Util::data_path( 'lastscan' ), $result );
		update_option( 'cs_last_summary', array( 'time' => time(), 'total' => count( $result['findings'] ) ), false );

		$crit = $result['severity']['critical'] + $result['severity']['high'];
		$s    = CS_Util::settings();
		if ( $crit > 0 && $s['email'] ) {
			$lines = array( sprintf( 'Code Sentinel found %d critical/high issue(s) on %s:', $crit, home_url() ), '' );
			foreach ( $result['findings'] as $f ) {
				if ( in_array( $f['sev'], array( 'critical', 'high' ), true ) ) {
					$lines[] = sprintf( '[%s] %s — %s:%d', strtoupper( $f['sev'] ), $f['title'], $f['rel'], $f['line'] );
				}
				if ( count( $lines ) > 60 ) { $lines[] = '…'; break; }
			}
			wp_mail( $s['email'], sprintf( '[Code Sentinel] %d critical/high issue(s) on %s', $crit, wp_parse_url( home_url(), PHP_URL_HOST ) ), implode( "\n", $lines ) );
		}
	}
}
