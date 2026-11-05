<?php
/**
 * Admin screens: settings, bulk optimizer, Media Library column, AJAX.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
final class WIO_Admin {

	const PAGE  = 'wio-webp-optimizer';
	const NONCE = 'wio_admin';

	/** Lock lifetime for bulk runs (refreshed by every step). */
	const LOCK_TTL = 90;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WIO_FILE ), array( __CLASS__, 'action_links' ) );

		add_filter( 'manage_media_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'attachment_field' ), 10, 2 );

		add_action( 'wp_ajax_wio_bulk_start', array( __CLASS__, 'ajax_bulk_start' ) );
		add_action( 'wp_ajax_wio_bulk_step', array( __CLASS__, 'ajax_bulk_step' ) );
		add_action( 'wp_ajax_wio_bulk_stop', array( __CLASS__, 'ajax_bulk_stop' ) );
		add_action( 'wp_ajax_wio_single', array( __CLASS__, 'ajax_single' ) );
		add_action( 'wp_ajax_wio_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_wio_stats', array( __CLASS__, 'ajax_stats' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Registration                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Media > WebP Optimizer.
	 */
	public static function menu() {
		add_media_page(
			__( 'WebP Image Optimizer', 'webp-image-optimizer' ),
			__( 'WebP Optimizer', 'webp-image-optimizer' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings() {
		register_setting(
			'wio_settings_group',
			WIO_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WIO_Settings', 'sanitize' ),
				'default'           => WIO_Settings::defaults(),
			)
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'upload.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'webp-image-optimizer' ) . '</a>' );
		return $links;
	}

	/**
	 * Scripts and styles on our page and the Media Library.
	 *
	 * @param string $hook Current screen hook.
	 */
	public static function assets( $hook ) {
		$ours = ( 'media_page_' . self::PAGE === $hook );
		if ( ! $ours && 'upload.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wio-admin', WIO_URL . 'assets/admin.css', array(), WIO_VERSION );
		wp_enqueue_script( 'wio-admin', WIO_URL . 'assets/admin.js', array(), WIO_VERSION, true );
		wp_localize_script(
			'wio-admin',
			'wioAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'working'     => __( 'Working…', 'webp-image-optimizer' ),
					'starting'    => __( 'Starting…', 'webp-image-optimizer' ),
					'paused'      => __( 'Paused', 'webp-image-optimizer' ),
					'stopped'     => __( 'Stopped', 'webp-image-optimizer' ),
					'done'        => __( 'Finished', 'webp-image-optimizer' ),
					'nothing'     => __( 'Nothing to do — every image is already handled.', 'webp-image-optimizer' ),
					'retrying'    => __( 'Server hiccup, retrying…', 'webp-image-optimizer' ),
					'gaveUp'      => __( 'The server stopped responding. Paused — press Resume to continue from where it stopped.', 'webp-image-optimizer' ),
					'leave'       => __( 'Bulk conversion is still running. Leave anyway?', 'webp-image-optimizer' ),
					'takeover'    => __( 'Another bulk run seems to be active (another tab or user). Take over and continue here?', 'webp-image-optimizer' ),
					'confirmRm'   => __( 'Delete every WebP copy made by this plugin? Your original images are not touched.', 'webp-image-optimizer' ),
					'saved'       => __( 'saved', 'webp-image-optimizer' ),
					'requestFail' => __( 'Request failed', 'webp-image-optimizer' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Notices                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Admin notices.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$where  = $screen ? $screen->id : '';
		if ( ! in_array( $where, array( 'plugins', 'media_page_' . self::PAGE ), true ) ) {
			return;
		}
		if ( ! WIO_Converter::has_engine() ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'WebP Image Optimizer:', 'webp-image-optimizer' ) . '</strong> ';
			echo esc_html__( 'this server has neither Imagick nor GD with WebP support, so no images can be converted. Ask your host to enable the Imagick or GD (with WebP) PHP extension.', 'webp-image-optimizer' );
			echo '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings page                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Render the main page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = WIO_Settings::all();
		$stats = WIO_Optimizer::stats( true );
		$name  = WIO_Settings::OPTION;
		?>
		<div class="wrap wio-wrap">
			<h1><?php esc_html_e( 'WebP Image Optimizer', 'webp-image-optimizer' ); ?></h1>
			<p class="wio-lead"><?php esc_html_e( 'Creates smaller WebP copies of your images and serves them to browsers that support WebP. Your original files are never changed, so you can switch this off at any time.', 'webp-image-optimizer' ); ?></p>

			<?php settings_errors(); ?>
			<?php self::render_delivery_warnings( $s ); ?>

			<div class="wio-grid">
				<div class="wio-card wio-stat">
					<span class="wio-stat-label"><?php esc_html_e( 'Converted images', 'webp-image-optimizer' ); ?></span>
					<span class="wio-stat-value" data-wio-stat="converted"><?php echo esc_html( number_format_i18n( $stats['status']['optimized'] + $stats['status']['partial'] ) ); ?></span>
					<span class="wio-stat-sub">
						<?php
						/* translators: %s: number of images in the library. */
						printf( esc_html__( 'of %s images', 'webp-image-optimizer' ), '<span data-wio-stat="total">' . esc_html( number_format_i18n( $stats['total'] ) ) . '</span>' );
						?>
					</span>
				</div>
				<div class="wio-card wio-stat">
					<span class="wio-stat-label"><?php esc_html_e( 'Space saved', 'webp-image-optimizer' ); ?></span>
					<span class="wio-stat-value" data-wio-stat="saved"><?php echo esc_html( size_format( $stats['saved'], 1 ) ? size_format( $stats['saved'], 1 ) : '0 B' ); ?></span>
					<span class="wio-stat-sub"><span data-wio-stat="percent"><?php echo esc_html( number_format_i18n( $stats['percent'], 1 ) ); ?></span>% <?php esc_html_e( 'smaller than the originals', 'webp-image-optimizer' ); ?></span>
				</div>
				<div class="wio-card wio-stat">
					<span class="wio-stat-label"><?php esc_html_e( 'Waiting', 'webp-image-optimizer' ); ?></span>
					<span class="wio-stat-value" data-wio-stat="pending"><?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?></span>
					<span class="wio-stat-sub">
						<?php
						$issues = $stats['status']['failed'] + $stats['status']['crashed'];
						/* translators: 1: skipped count, 2: failed count. */
						printf( esc_html__( '%1$s skipped · %2$s failed', 'webp-image-optimizer' ), '<span data-wio-stat="skipped">' . esc_html( number_format_i18n( $stats['status']['skipped'] ) ) . '</span>', '<span data-wio-stat="failed">' . esc_html( number_format_i18n( $issues ) ) . '</span>' );
						?>
					</span>
				</div>
				<div class="wio-card wio-stat">
					<span class="wio-stat-label"><?php esc_html_e( 'Image library', 'webp-image-optimizer' ); ?></span>
					<?php
					$engines = WIO_Converter::engines();
					if ( $engines ) {
						echo '<span class="wio-stat-value wio-ok">' . esc_html( 'imagick' === $engines[0] ? 'Imagick' : 'GD' ) . '</span>';
					} else {
						echo '<span class="wio-stat-value wio-bad">' . esc_html__( 'None', 'webp-image-optimizer' ) . '</span>';
					}
					?>
					<span class="wio-stat-sub">
						<?php
						$fallback = count( $engines ) > 1 ? ( 'imagick' === $engines[1] ? 'Imagick' : 'GD' ) : '';
						if ( $fallback ) {
							/* translators: %s: fallback library name. */
							printf( esc_html__( 'with %s as fallback', 'webp-image-optimizer' ), esc_html( $fallback ) );
						} else {
							esc_html_e( 'no fallback available', 'webp-image-optimizer' );
						}
						?>
					</span>
				</div>
			</div>

			<div class="wio-card" id="wio-bulk">
				<h2><?php esc_html_e( 'Bulk convert', 'webp-image-optimizer' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Converts your existing images in small batches. Keep this page open while it runs; you can pause and resume at any time. New uploads are converted automatically.', 'webp-image-optimizer' ); ?></p>

				<fieldset class="wio-modes">
					<label><input type="radio" name="wio_mode" value="new" checked> <?php esc_html_e( 'Convert images that have not been converted yet', 'webp-image-optimizer' ); ?> <span class="wio-count" data-wio-count="new">(<?php echo esc_html( number_format_i18n( $stats['pending'] ) ); ?>)</span></label>
					<label><input type="radio" name="wio_mode" value="force"> <?php esc_html_e( 'Re-convert all images (applies the current quality setting)', 'webp-image-optimizer' ); ?> <span class="wio-count" data-wio-count="force">(<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>)</span></label>
					<label><input type="radio" name="wio_mode" value="remove"> <?php esc_html_e( 'Delete all WebP copies (originals stay untouched)', 'webp-image-optimizer' ); ?> <span class="wio-count" data-wio-count="remove">(<?php echo esc_html( number_format_i18n( $stats['processed'] ) ); ?>)</span></label>
				</fieldset>

				<div class="wio-actions">
					<button type="button" class="button button-primary" id="wio-start" <?php disabled( ! WIO_Converter::has_engine() ); ?>><?php esc_html_e( 'Start', 'webp-image-optimizer' ); ?></button>
					<button type="button" class="button" id="wio-pause" hidden><?php esc_html_e( 'Pause', 'webp-image-optimizer' ); ?></button>
					<button type="button" class="button" id="wio-resume" hidden><?php esc_html_e( 'Resume', 'webp-image-optimizer' ); ?></button>
					<button type="button" class="button-link wio-danger" id="wio-stop" hidden><?php esc_html_e( 'Stop', 'webp-image-optimizer' ); ?></button>
					<span class="wio-state" id="wio-state" aria-live="polite"></span>
				</div>

				<div class="wio-progress" id="wio-progress" hidden>
					<div class="wio-bar"><span id="wio-bar-fill"></span></div>
					<div class="wio-progress-text"><span id="wio-done">0</span> / <span id="wio-total">0</span> · <span id="wio-run-saved">0 B</span> <?php esc_html_e( 'saved this run', 'webp-image-optimizer' ); ?></div>
				</div>
				<ol class="wio-log" id="wio-log" hidden></ol>
			</div>

			<form method="post" action="options.php" class="wio-card">
				<?php settings_fields( 'wio_settings_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'webp-image-optimizer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wio-quality"><?php esc_html_e( 'WebP quality', 'webp-image-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="30" max="100" step="1" id="wio-quality" name="<?php echo esc_attr( $name ); ?>[quality]" value="<?php echo esc_attr( $s['quality'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( '80 is a good balance: usually 25–35% smaller than JPEG with no visible difference. Lower = smaller files. Changes apply to new conversions; use "Re-convert all" for existing images.', 'webp-image-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Convert these types', 'webp-image-optimizer' ); ?></th>
						<td>
							<?php
							$types = array(
								'jpeg' => 'JPEG',
								'png'  => 'PNG',
								'gif'  => __( 'GIF (animated GIFs are always skipped)', 'webp-image-optimizer' ),
							);
							foreach ( $types as $key => $label ) {
								printf(
									'<label class="wio-inline"><input type="checkbox" name="%1$s[types][]" value="%2$s" %3$s> %4$s</label>',
									esc_attr( $name ),
									esc_attr( $key ),
									checked( in_array( $key, (array) $s['types'], true ), true, false ),
									esc_html( $label )
								);
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'New uploads', 'webp-image-optimizer' ); ?></th>
						<td>
							<?php
							$opts = array(
								'shutdown' => __( 'Convert right after upload (recommended — runs after the upload finishes, so it never slows the upload or breaks it)', 'webp-image-optimizer' ),
								'cron'     => __( 'Convert in the background a few seconds later (WP-Cron)', 'webp-image-optimizer' ),
								'off'      => __( 'Don\'t convert automatically (use Bulk convert)', 'webp-image-optimizer' ),
							);
							foreach ( $opts as $key => $label ) {
								printf(
									'<label class="wio-block"><input type="radio" name="%1$s[on_upload]" value="%2$s" %3$s> %4$s</label>',
									esc_attr( $name ),
									esc_attr( $key ),
									checked( $s['on_upload'], $key, false ),
									esc_html( $label )
								);
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'How WebP is served', 'webp-image-optimizer' ); ?></th>
						<td>
							<?php
							$opts = array(
								'picture' => __( '<picture> tags (recommended) — works on every host, with page caching and with CDNs such as Cloudflare. Old browsers automatically get the original image.', 'webp-image-optimizer' ),
								'rewrite' => __( 'Server rewrite (.htaccess, Apache/LiteSpeed only) — same image URLs, server swaps in WebP. Also covers CSS background images. Your CDN must respect "Vary: Accept".', 'webp-image-optimizer' ),
								'off'     => __( 'Don\'t serve — only create WebP files', 'webp-image-optimizer' ),
							);
							foreach ( $opts as $key => $label ) {
								printf(
									'<label class="wio-block"><input type="radio" name="%1$s[delivery]" value="%2$s" %3$s> %4$s</label>',
									esc_attr( $name ),
									esc_attr( $key ),
									checked( $s['delivery'], $key, false ),
									esc_html( $label )
								);
							}
							?>
							<label class="wio-block wio-sub"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[display_contents]" value="1" <?php checked( $s['display_contents'], 1 ); ?>> <?php esc_html_e( 'Keep layouts identical: make the <picture> wrapper invisible to CSS layout (recommended)', 'webp-image-optimizer' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wio-exclude"><?php esc_html_e( 'Exclude images', 'webp-image-optimizer' ); ?></label></th>
						<td>
							<textarea id="wio-exclude" name="<?php echo esc_attr( $name ); ?>[exclude]" rows="3" class="large-text code" placeholder="logo.png&#10;class=&quot;no-webp&quot;"><?php echo esc_textarea( $s['exclude'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Any <img> tag containing this text is left alone (file name, class, etc.). You can also add the class "wio-skip" to an image.', 'webp-image-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wio-cdn"><?php esc_html_e( 'CDN hostnames', 'webp-image-optimizer' ); ?></label></th>
						<td>
							<textarea id="wio-cdn" name="<?php echo esc_attr( $name ); ?>[cdn_hosts]" rows="2" class="large-text code" placeholder="cdn.example.com"><?php echo esc_textarea( $s['cdn_hosts'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Only needed if your image URLs point at a CDN hostname that pulls files from this site. Leave empty otherwise.', 'webp-image-optimizer' ); ?></p>
						</td>
					</tr>
				</table>

				<h3 class="wio-h3"><?php esc_html_e( 'Make future uploads smaller', 'webp-image-optimizer' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wio-maxdim"><?php esc_html_e( 'Maximum image size', 'webp-image-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="0" max="10000" step="1" id="wio-maxdim" name="<?php echo esc_attr( $name ); ?>[max_dimension]" value="<?php echo esc_attr( $s['max_dimension'] ); ?>" class="small-text"> px
							<p class="description"><?php esc_html_e( 'Uploads wider or taller than this are scaled down by WordPress (the untouched original is kept). WordPress default is 2560. 1920 suits most sites. 0 = never scale.', 'webp-image-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wio-jq"><?php esc_html_e( 'JPEG thumbnail quality', 'webp-image-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" step="1" id="wio-jq" name="<?php echo esc_attr( $name ); ?>[jpeg_quality]" value="<?php echo esc_attr( $s['jpeg_quality'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Quality of the JPEG thumbnail sizes WordPress creates (the fallback for old browsers). WordPress default is 82.', 'webp-image-optimizer' ); ?></p>
						</td>
					</tr>
				</table>

				<details class="wio-advanced">
					<summary><?php esc_html_e( 'Advanced', 'webp-image-optimizer' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wio-engine"><?php esc_html_e( 'Image library', 'webp-image-optimizer' ); ?></label></th>
							<td>
								<select id="wio-engine" name="<?php echo esc_attr( $name ); ?>[engine]">
									<option value="auto" <?php selected( $s['engine'], 'auto' ); ?>><?php esc_html_e( 'Automatic (Imagick first, GD as fallback)', 'webp-image-optimizer' ); ?></option>
									<option value="imagick" <?php selected( $s['engine'], 'imagick' ); ?>><?php esc_html_e( 'Prefer Imagick', 'webp-image-optimizer' ); ?></option>
									<option value="gd" <?php selected( $s['engine'], 'gd' ); ?>><?php esc_html_e( 'Prefer GD', 'webp-image-optimizer' ); ?></option>
								</select>
								<ul class="wio-engines">
									<?php foreach ( WIO_Converter::engine_report() as $row ) : ?>
										<li><span class="dashicons <?php echo esc_attr( $row['ok'] ? 'dashicons-yes-alt wio-ok' : 'dashicons-dismiss wio-bad' ); ?>"></span> <strong><?php echo esc_html( $row['name'] ); ?></strong> — <?php echo esc_html( $row['detail'] ); ?></li>
									<?php endforeach; ?>
								</ul>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'When deleting the plugin', 'webp-image-optimizer' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'], 1 ); ?>> <?php esc_html_e( 'Also delete all WebP files it created', 'webp-image-optimizer' ); ?></label>
								<p class="description"><?php esc_html_e( 'Settings and status data are always removed. For very large libraries, use "Delete all WebP copies" above first instead.', 'webp-image-optimizer' ); ?></p>
							</td>
						</tr>
					</table>
				</details>

				<?php submit_button(); ?>
			</form>

			<div class="wio-card">
				<h2><?php esc_html_e( 'Check delivery', 'webp-image-optimizer' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Picks one converted image and checks that the server sends its WebP copy correctly.', 'webp-image-optimizer' ); ?></p>
				<p><button type="button" class="button" id="wio-test"><?php esc_html_e( 'Run check', 'webp-image-optimizer' ); ?></button></p>
				<div id="wio-test-result" class="wio-test-result" aria-live="polite"></div>
				<?php if ( WIO_Rewrite::is_nginx() ) : ?>
					<details class="wio-advanced">
						<summary><?php esc_html_e( 'Nginx configuration (only for "Server rewrite" mode)', 'webp-image-optimizer' ); ?></summary>
						<p class="description"><?php esc_html_e( 'Nginx does not read .htaccess files. To use server rewrite mode, add this to your site configuration and reload nginx. Picture-tag mode needs no server changes.', 'webp-image-optimizer' ); ?></p>
						<pre class="wio-code"><?php echo esc_html( WIO_Rewrite::nginx_snippet() ); ?></pre>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Warnings related to the delivery mode.
	 *
	 * @param array $s Settings.
	 */
	private static function render_delivery_warnings( $s ) {
		if ( 'rewrite' !== $s['delivery'] ) {
			return;
		}
		if ( WIO_Rewrite::is_apache() ) {
			if ( ! WIO_Rewrite::is_installed() ) {
				$result = WIO_Rewrite::write();
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-warning"><p>' . esc_html( $result->get_error_message() ) . '</p>';
					echo '<p>' . esc_html__( 'Add these lines to', 'webp-image-optimizer' ) . ' <code>' . esc_html( WIO_Rewrite::htaccess_path() ) . '</code>:</p>';
					echo '<pre class="wio-code">' . esc_html( "# BEGIN WebP Image Optimizer\n" . implode( "\n", WIO_Rewrite::rules() ) . "\n# END WebP Image Optimizer" ) . '</pre></div>';
				}
			}
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		if ( WIO_Rewrite::is_nginx() ) {
			esc_html_e( 'Your server runs nginx, which ignores .htaccess. Add the nginx configuration shown under "Check delivery", or switch to <picture> tags (no server changes needed).', 'webp-image-optimizer' );
		} else {
			esc_html_e( 'Server rewrite mode needs Apache or LiteSpeed. Switch to <picture> tags, which work on any server.', 'webp-image-optimizer' );
		}
		echo '</p></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Media Library                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Add the WebP column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public static function add_column( $cols ) {
		$cols['wio'] = __( 'WebP', 'webp-image-optimizer' );
		return $cols;
	}

	/**
	 * Column content.
	 *
	 * @param string $col Column.
	 * @param int    $id  Attachment ID.
	 */
	public static function render_column( $col, $id ) {
		if ( 'wio' === $col ) {
			echo self::status_html( (int) $id, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
	}

	/**
	 * Read-only status in the attachment details panel.
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public static function attachment_field( $fields, $post ) {
		if ( $post && WIO_Optimizer::is_supported( $post->ID ) ) {
			$fields['wio'] = array(
				'label' => __( 'WebP', 'webp-image-optimizer' ),
				'input' => 'html',
				'html'  => self::status_html( (int) $post->ID, false ),
			);
		}
		return $fields;
	}

	/**
	 * Status markup for one attachment.
	 *
	 * @param int  $id      Attachment ID.
	 * @param bool $buttons Include action buttons.
	 * @return string
	 */
	public static function status_html( $id, $buttons = true ) {
		if ( ! WIO_Optimizer::is_supported( $id ) ) {
			return '<span class="wio-muted">—</span>';
		}
		$status = (string) get_post_meta( $id, WIO_Optimizer::META_STATUS, true );
		$data   = get_post_meta( $id, WIO_Optimizer::META_DATA, true );
		$data   = is_array( $data ) ? $data : array();
		$msg    = isset( $data['message'] ) ? (string) $data['message'] : '';

		$out     = '';
		$actions = array();

		switch ( $status ) {
			case 'optimized':
			case 'partial':
				$orig  = isset( $data['orig'] ) ? (int) $data['orig'] : 0;
				$new   = isset( $data['new'] ) ? (int) $data['new'] : 0;
				$pct   = $orig > 0 ? round( ( $orig - $new ) / $orig * 100 ) : 0;
				$label = sprintf(
					/* translators: 1: percentage, 2: bytes saved. */
					__( '%1$s%% smaller (%2$s saved)', 'webp-image-optimizer' ),
					number_format_i18n( $pct ),
					size_format( max( 0, $orig - $new ), 1 )
				);
				$out .= '<span class="wio-badge wio-badge-ok">' . esc_html( $label ) . '</span>';
				$out .= '<span class="wio-muted">' . esc_html(
					sprintf(
						/* translators: 1: number of WebP files, 2: quality. */
						_n( '%1$s WebP file · quality %2$s', '%1$s WebP files · quality %2$s', (int) ( $data['webps'] ?? 0 ), 'webp-image-optimizer' ),
						number_format_i18n( (int) ( $data['webps'] ?? 0 ) ),
						(int) ( $data['quality'] ?? 0 )
					)
				) . '</span>';
				if ( 'partial' === $status && $msg ) {
					$out .= '<span class="wio-warn">' . esc_html( $msg ) . '</span>';
				}
				$actions = array(
					'reconvert' => __( 'Re-convert', 'webp-image-optimizer' ),
					'remove'    => __( 'Remove WebP', 'webp-image-optimizer' ),
				);
				break;
			case 'skipped':
				$out    .= '<span class="wio-badge">' . esc_html__( 'Kept original', 'webp-image-optimizer' ) . '</span>';
				$out    .= '<span class="wio-muted">' . esc_html( $msg ) . '</span>';
				$actions = array( 'reconvert' => __( 'Try again', 'webp-image-optimizer' ) );
				break;
			case 'failed':
			case 'crashed':
				$out    .= '<span class="wio-badge wio-badge-bad">' . esc_html__( 'Failed', 'webp-image-optimizer' ) . '</span>';
				$out    .= '<span class="wio-warn">' . esc_html( $msg ) . '</span>';
				$actions = array( 'reconvert' => __( 'Retry', 'webp-image-optimizer' ) );
				break;
			default:
				$out    .= '<span class="wio-muted">' . esc_html__( 'Not converted yet', 'webp-image-optimizer' ) . '</span>';
				$actions = array( 'convert' => __( 'Convert now', 'webp-image-optimizer' ) );
		}

		if ( $buttons && current_user_can( 'edit_post', $id ) && WIO_Converter::has_engine() ) {
			$out .= '<span class="wio-row-actions">';
			foreach ( $actions as $op => $label ) {
				$out .= '<button type="button" class="button-link wio-op" data-op="' . esc_attr( $op ) . '" data-id="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</button>';
			}
			$out .= '</span>';
		}

		return '<div class="wio-status" data-wio-id="' . esc_attr( $id ) . '">' . $out . '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Verify nonce + capability, or end the request.
	 *
	 * @param string $cap Capability.
	 */
	private static function guard( $cap = 'manage_options' ) {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page.', 'webp-image-optimizer' ) ), 403 );
		}
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'webp-image-optimizer' ) ), 403 );
		}
	}

	/**
	 * Read a POST field.
	 *
	 * @param string $key     Key.
	 * @param string $default Default.
	 * @return string
	 */
	private static function post( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Mode from request.
	 *
	 * @return string
	 */
	private static function mode() {
		$mode = self::post( 'mode', 'new' );
		return in_array( $mode, array( 'new', 'force', 'remove' ), true ) ? $mode : 'new';
	}

	/**
	 * Start a bulk run.
	 */
	public static function ajax_bulk_start() {
		self::guard();
		$mode     = self::mode();
		$takeover = '1' === self::post( 'takeover' );
		$lock     = get_transient( 'wio_bulk_lock' );

		if ( $lock && ! $takeover ) {
			wp_send_json_error(
				array(
					'code'    => 'locked',
					'message' => __( 'Another bulk run is active.', 'webp-image-optimizer' ),
				),
				409
			);
		}

		$run = wp_generate_password( 12, false, false );
		set_transient( 'wio_bulk_lock', $run, self::LOCK_TTL );

		wp_send_json_success(
			array(
				'run'   => $run,
				'total' => WIO_Optimizer::count_for_mode( $mode ),
			)
		);
	}

	/**
	 * Process one batch.
	 */
	public static function ajax_bulk_step() {
		self::guard();
		$mode   = self::mode();
		$run    = self::post( 'run' );
		$cursor = absint( self::post( 'cursor', '0' ) );
		$lock   = get_transient( 'wio_bulk_lock' );

		if ( $lock && $lock !== $run ) {
			wp_send_json_error(
				array(
					'code'    => 'locked',
					'message' => __( 'Another bulk run took over. This one has stopped.', 'webp-image-optimizer' ),
				),
				409
			);
		}
		set_transient( 'wio_bulk_lock', $run, self::LOCK_TTL );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		// Spend at most ~1/3 of PHP's time limit (max 8 s) per request, so progress stays lively.
		$max_exec = (int) ini_get( 'max_execution_time' );
		$budget   = $max_exec > 0 ? min( 8, max( 3, $max_exec / 3 ) ) : 8;
		$started  = microtime( true );
		$items    = array();
		$done     = false;

		while ( ! $done ) {
			$ids = WIO_Optimizer::next_ids( $mode, $cursor, 10 );
			if ( ! $ids ) {
				$done = true;
				break;
			}
			foreach ( $ids as $id ) {
				if ( 'remove' === $mode ) {
					$removed = WIO_Optimizer::remove_attachment( $id );
					$items[] = array(
						'id'      => $id,
						'name'    => self::item_name( $id ),
						'status'  => 'removed',
						'saved'   => 0,
						/* translators: %d: number of files. */
						'message' => sprintf( _n( '%d file deleted', '%d files deleted', $removed, 'webp-image-optimizer' ), $removed ),
					);
				} else {
					$r       = WIO_Optimizer::process_attachment(
						$id,
						array(
							'force'   => 'force' === $mode,
							'context' => 'bulk',
						)
					);
					$items[] = array(
						'id'      => $id,
						'name'    => self::item_name( $id ),
						'status'  => $r['status'],
						'saved'   => max( 0, (int) $r['orig'] - (int) $r['new'] ),
						'message' => self::item_message( $r ),
					);
				}
				$cursor = $id;
				if ( ( microtime( true ) - $started ) > $budget ) {
					break 2;
				}
			}
		}

		if ( $done ) {
			delete_transient( 'wio_bulk_lock' );
		}

		wp_send_json_success(
			array(
				'cursor' => $cursor,
				'done'   => $done,
				'items'  => $items,
				'stats'  => $done ? self::stats_payload() : null,
			)
		);
	}

	/**
	 * Stop / release the lock.
	 */
	public static function ajax_bulk_stop() {
		self::guard();
		$run  = self::post( 'run' );
		$lock = get_transient( 'wio_bulk_lock' );
		if ( ! $lock || $lock === $run ) {
			delete_transient( 'wio_bulk_lock' );
		}
		wp_send_json_success( array( 'stats' => self::stats_payload() ) );
	}

	/**
	 * Fresh stats for the cards.
	 */
	public static function ajax_stats() {
		self::guard();
		wp_send_json_success( array( 'stats' => self::stats_payload() ) );
	}

	/**
	 * Convert / re-convert / remove a single attachment.
	 */
	public static function ajax_single() {
		self::guard( 'upload_files' );
		$id = absint( self::post( 'id', '0' ) );
		$op = self::post( 'op' );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this item.', 'webp-image-optimizer' ) ), 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( 'remove' === $op ) {
			WIO_Optimizer::remove_attachment( $id );
		} else {
			WIO_Optimizer::process_attachment(
				$id,
				array(
					'force'   => 'reconvert' === $op,
					'context' => 'manual',
				)
			);
		}
		wp_send_json_success( array( 'html' => self::status_html( $id, true ) ) );
	}

	/**
	 * Check that a WebP copy is actually served correctly.
	 */
	public static function ajax_test() {
		self::guard();
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ('optimized','partial') ORDER BY post_id DESC LIMIT 20", WIO_Optimizer::META_STATUS ) );

		$picked = null;
		foreach ( (array) $ids as $id ) {
			$file = get_attached_file( (int) $id, true );
			if ( $file && @is_file( $file . '.webp' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$picked = (int) $id;
				break;
			}
		}
		if ( ! $picked ) {
			wp_send_json_error( array( 'message' => __( 'No converted image found yet. Run Bulk convert first.', 'webp-image-optimizer' ) ) );
		}

		$url    = wp_get_attachment_url( $picked );
		$mode   = WIO_Settings::get( 'delivery' );
		$checks = array();

		// 1) The .webp file itself must be reachable with an image type.
		$webp_url = $url . '.webp';
		$resp     = wp_remote_get(
			$webp_url,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'sslverify'   => false,
				'headers'     => array( 'Accept' => 'image/webp,image/*,*/*;q=0.8' ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			$checks[] = array( 'warn', sprintf( /* translators: %s: error. */ __( 'Could not test over HTTP (%s). Many hosts block requests from the server to itself; open the link below in your browser instead.', 'webp-image-optimizer' ), $resp->get_error_message() ) );
		} else {
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$type = (string) wp_remote_retrieve_header( $resp, 'content-type' );
			if ( 200 === $code && false !== stripos( $type, 'image/webp' ) ) {
				$checks[] = array( 'ok', __( 'WebP files are served correctly (HTTP 200, image/webp).', 'webp-image-optimizer' ) );
			} elseif ( 200 === $code ) {
				/* translators: %s: content type. */
				$checks[] = array( 'warn', sprintf( __( 'WebP files load but are sent as "%s". Browsers still show them, but ask your host to add the image/webp MIME type.', 'webp-image-optimizer' ), $type ? $type : '?' ) );
			} else {
				/* translators: %d: HTTP code. */
				$checks[] = array( 'bad', sprintf( __( 'WebP file returned HTTP %d. Something on the server (security plugin, hotlink protection, CDN rule) blocks .webp files.', 'webp-image-optimizer' ), $code ) );
			}
		}

		// 2) Mode-specific check.
		if ( 'rewrite' === $mode ) {
			$resp = wp_remote_get(
				$url,
				array(
					'timeout'   => 10,
					'sslverify' => false,
					'headers'   => array( 'Accept' => 'image/webp,image/*,*/*;q=0.8' ),
				)
			);
			if ( ! is_wp_error( $resp ) ) {
				$type = (string) wp_remote_retrieve_header( $resp, 'content-type' );
				$vary = (string) wp_remote_retrieve_header( $resp, 'vary' );
				if ( false !== stripos( $type, 'image/webp' ) ) {
					$checks[] = array( 'ok', __( 'Server rewrite works: the normal image URL returns WebP to WebP browsers.', 'webp-image-optimizer' ) );
					if ( false === stripos( $vary, 'accept' ) ) {
						$checks[] = array( 'warn', __( 'The "Vary: Accept" header is missing (mod_headers may be off). Caches could send WebP to old browsers.', 'webp-image-optimizer' ) );
					}
				} else {
					$checks[] = array( 'bad', __( 'Server rewrite is not active: the image URL still returns the original. Your host may not allow .htaccess rules (or uses nginx). Switch to <picture> tags.', 'webp-image-optimizer' ) );
				}
			}
		} elseif ( 'picture' === $mode ) {
			$checks[] = array( 'ok', __( '<picture> mode is on: pages list the WebP version first and keep the original as fallback. If you use a page cache, clear it once so cached pages pick this up.', 'webp-image-optimizer' ) );
		} else {
			$checks[] = array( 'warn', __( 'Serving is switched off, so visitors still get the original images.', 'webp-image-optimizer' ) );
		}

		wp_send_json_success(
			array(
				'checks'  => $checks,
				'url'     => $webp_url,
				'webp'    => __( 'Open the WebP copy', 'webp-image-optimizer' ),
				'imageId' => $picked,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Stats with formatted values for the UI.
	 *
	 * @return array
	 */
	private static function stats_payload() {
		$s = WIO_Optimizer::stats( true );
		return array(
			'converted' => number_format_i18n( $s['status']['optimized'] + $s['status']['partial'] ),
			'total'     => number_format_i18n( $s['total'] ),
			'pending'   => number_format_i18n( $s['pending'] ),
			'skipped'   => number_format_i18n( $s['status']['skipped'] ),
			'failed'    => number_format_i18n( $s['status']['failed'] + $s['status']['crashed'] ),
			'saved'     => $s['saved'] > 0 ? size_format( $s['saved'], 1 ) : '0 B',
			'percent'   => number_format_i18n( $s['percent'], 1 ),
			'counts'    => array(
				'new'    => number_format_i18n( $s['pending'] ),
				'force'  => number_format_i18n( $s['total'] ),
				'remove' => number_format_i18n( $s['processed'] ),
			),
		);
	}

	/**
	 * File name for the log.
	 *
	 * @param int $id Attachment ID.
	 * @return string
	 */
	private static function item_name( $id ) {
		$file = get_attached_file( $id, true );
		return $file ? wp_basename( $file ) : '#' . $id;
	}

	/**
	 * One-line message for the log.
	 *
	 * @param array $r Summary.
	 * @return string
	 */
	private static function item_message( $r ) {
		if ( in_array( $r['status'], array( 'optimized', 'partial' ), true ) && $r['orig'] > 0 ) {
			$pct = round( ( $r['orig'] - $r['new'] ) / $r['orig'] * 100 );
			$msg = sprintf(
				/* translators: 1: size before, 2: size after, 3: percent. */
				__( '%1$s → %2$s (−%3$s%%)', 'webp-image-optimizer' ),
				size_format( $r['orig'], 1 ),
				size_format( $r['new'], 1 ),
				number_format_i18n( $pct )
			);
			return 'partial' === $r['status'] && $r['message'] ? $msg . ' · ' . $r['message'] : $msg;
		}
		return (string) $r['message'];
	}
}
