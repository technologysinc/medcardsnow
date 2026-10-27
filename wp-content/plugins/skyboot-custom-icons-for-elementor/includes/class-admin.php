<?php
/**
 * Admin marketing, onboarding & engagement.
 *
 * WordPress.org compliance:
 *  - notices are per-user dismissible and nonce-protected
 *  - onboarding email is optional & opt-in (no data leaves the site by default)
 *  - the activation redirect fires once and never on bulk/AJAX activation
 */
if (!defined('ABSPATH'))
    exit;

if (!class_exists('Skb_Cife_Admin')):

    class Skb_Cife_Admin
    {

        const SETTINGS_SLUG = 'skyboot_icons';
        const WELCOME_SLUG = 'skyboot_icons_welcome';
        const GOPRO_SLUG = 'skyboot_icons_go_pro';
        const NOTICE_META = 'skb_cife_upgrade_notice_dismissed';
        const REDIRECT_TRANSIENT = 'skb_cife_activation_redirect';
        const ONBOARDED_OPTION = 'skb_cife_onboarded';

        /* Review notice constants */
        const REVIEW_START_TIME = 'skb_review_start_time';
        const REVIEW_STATUS = 'skb_review_status';
        const REVIEW_VERSION = 'skb_review_version';
        const REVIEW_REMIND_TIME = 'skb_review_remind_time';

        public function __construct()
        {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_menu', array($this, 'register_pages'), 99);
            add_action('admin_head', array($this, 'menu_styles'));
            add_action('admin_init', array($this, 'maybe_redirect'));
            add_action('admin_init', array($this, 'maybe_dismiss_notice'));
            add_action('admin_notices', array($this, 'upgrade_notice'));
            add_action('admin_notices', array($this, 'review_notice'));
            add_action('wp_dashboard_setup', array($this, 'register_dashboard_widget'));
            add_action('admin_post_skb_cife_onboarding', array($this, 'handle_onboarding_submit'));
            add_action('skb_cife_onboarding_subscribe', array($this, 'subscribe_to_mailmint'));

            add_filter('plugin_action_links_' . SKB_CIFE_PLUGIN_BASE, array($this, 'action_links'));
            add_filter('plugin_row_meta', array($this, 'row_meta'), 10, 2);

            // AJAX handler for auto-update toggle
            add_action('wp_ajax_skb_toggle_auto_update', array($this, 'ajax_toggle_auto_update'));

            // Initialize review tracking and handle review actions on admin_init
            add_action('admin_init', array($this, 'maybe_record_install_time'));
            add_action('admin_init', array($this, 'handle_review_actions'));
        }

        /**
         * AJAX handler to toggle auto-updates for this plugin.
         */
        public function ajax_toggle_auto_update()
        {
            check_ajax_referer('skb_admin_nonce', 'nonce');

            if (!current_user_can('update_plugins')) {
                wp_send_json_error('Unauthorized');
            }

            $enable = isset($_POST['enable']) && $_POST['enable'] === 'true';
            $auto_updates = (array) get_site_option('auto_update' . '_plugins', array());

            if ($enable) {
                if (!in_array(SKB_CIFE_PLUGIN_BASE, $auto_updates)) {
                    $auto_updates[] = SKB_CIFE_PLUGIN_BASE;
                }
            } else {
                $auto_updates = array_diff($auto_updates, array(SKB_CIFE_PLUGIN_BASE));
            }

            update_site_option('auto_update' . '_plugins', $auto_updates);
            wp_send_json_success();
        }

        /* ---------------------------------------------------------------
         * Assets
         * ------------------------------------------------------------- */
        public function enqueue_assets($hook)
        {
            $our_pages = array(self::SETTINGS_SLUG, self::WELCOME_SLUG, self::GOPRO_SLUG);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

            // Load widget styles on WP dashboard (index.php)
            if ('index.php' === $hook) {
                $widget_css = '#skb_cife_dashboard_widget .skb-dw{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px}'
                    . '#skb_cife_dashboard_widget .skb-dw-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}'
                    . '#skb_cife_dashboard_widget .skb-dw-stat{background:#f5f2fd;border-radius:8px;padding:10px 12px;border:1px solid #ece7f7}'
                    . '#skb_cife_dashboard_widget .skb-dw-num{display:block;font-size:18px;font-weight:800;color:#1a1535;line-height:1.2}'
                    . '#skb_cife_dashboard_widget .skb-dw-lbl{font-size:11px;color:#6e6889;margin-top:2px;display:block}'
                    . '#skb_cife_dashboard_widget .skb-dw-actions{margin:10px 0}'
                    . '#skb_cife_dashboard_widget .skb-dw-pro{background:linear-gradient(135deg,#531df7,#7c3aed);color:#fff;border-radius:8px;padding:12px 14px;margin-bottom:12px}'
                    . '#skb_cife_dashboard_widget .skb-dw-pro strong{display:block;font-size:13px;font-weight:800;margin-bottom:2px}'
                    . '#skb_cife_dashboard_widget .skb-dw-pro span{display:block;font-size:13px;margin-bottom:6px}'
                    . '#skb_cife_dashboard_widget .skb-dw-pro a{color:#fff;font-weight:700;text-decoration:none;font-size:11.5px;background:rgba(255,255,255,.22);padding:4px 10px;border-radius:5px;display:inline-block}'
                    . '#skb_cife_dashboard_widget .skb-dw-pro a:hover{background:rgba(255,255,255,.38)}'
                    . '#skb_cife_dashboard_widget .skb-dw-news h4{font-size:11px;font-weight:700;color:#6e6889;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px}'
                    . '#skb_cife_dashboard_widget .skb-dw-news ul{margin:0;padding:0;list-style:none}'
                    . '#skb_cife_dashboard_widget .skb-dw-news li{border-top:1px solid #ece7f7}'
                    . '#skb_cife_dashboard_widget .skb-dw-news li:first-child{border-top:0}'
                    . '#skb_cife_dashboard_widget .skb-dw-news a{display:flex;align-items:center;gap:7px;padding:7px 0;text-decoration:none;color:#1a1535;font-size:12.5px;font-weight:500;transition:color .15s}'
                    . '#skb_cife_dashboard_widget .skb-dw-news a:hover{color:#531df7}'
                    . '#skb_cife_dashboard_widget .skb-dw-dot{width:7px;height:7px;border-radius:50%;background:#531df7;flex-shrink:0;display:inline-block}';
                wp_add_inline_style('wp-admin', $widget_css);
                return;
            }

            if (!in_array($page, $our_pages, true)) {
                return;
            }

            wp_enqueue_style('dashicons');
            wp_enqueue_style(
                'skb-cife-skyboot-admin',
                SKB_CIFE_ASSETS . 'css/skyboot-admin.css',
                array('dashicons'),
                SKB_CIFE_VERSION
            );
            wp_enqueue_script(
                'skb-cife-skyboot-admin',
                SKB_CIFE_ASSETS . 'js/skyboot-admin.js',
                array(),
                SKB_CIFE_VERSION,
                true
            );

            wp_localize_script('skb-cife-skyboot-admin', 'skbCifeAdmin', array(
                'isPro' => Skb_Cife_Pro::is_active() ? 1 : 0,
                'upgradeUrl' => admin_url('admin.php?page=' . self::GOPRO_SLUG),
                'nonce' => wp_create_nonce('skb_admin_nonce'),
                'i18n' => array(
                    'proTitle' => __('Skyboot Pro Coming Soon', 'skyboot-custom-icons-for-elementor'),
                    'proBody' => __('Everything in Free, plus Custom SVG Icon Packs (.zip upload), advanced gradients, and dynamic tags for Elementor.', 'skyboot-custom-icons-for-elementor'),
                    'proCta' => __('Join Waitlist', 'skyboot-custom-icons-for-elementor'),
                    'close' => __('Maybe later', 'skyboot-custom-icons-for-elementor'),
                ),
            ));
        }

        /* ---------------------------------------------------------------
         * Pack defaults (mirrored from class-base.php)
         * ------------------------------------------------------------- */
        public static function pack_defaults()
        {
            return array(
                'elegant_icon' => 'on',
                'linearicons_icon' => 'on',
                'themify_icon' => 'on',
                'simpleline_icon' => 'off',
                'line_icon' => 'off',
                'ion_icon' => 'off',
                'icofont_icon' => 'off',
                'lineawesome_icon' => 'off',
                'materialdesign_icon' => 'off',
                'devicons_icon' => 'off',
                'icomoon_icon' => 'off',
                'elusive_icon' => 'off',
                'iconic_icon' => 'off',
                'brands_icon' => 'off',
                'open_iconic_icon' => 'off',
            );
        }

        public static function active_pack_count()
        {
            $saved = get_option('skb_cife_manage_icon', array());
            $count = 0;
            foreach (self::pack_defaults() as $key => $default) {
                $state = isset($saved[$key]) ? $saved[$key] : $default;
                if ('on' === $state) {
                    $count++;
                }
            }
            return $count;
        }

        /* ---------------------------------------------------------------
         * Menu: hidden welcome + Go Pro
         * ------------------------------------------------------------- */
        public function register_pages()
        {
            add_submenu_page(
                'skb_cife_no_parent_' . wp_generate_password(4, false),
                esc_html__('Welcome to Skyboot Icons', 'skyboot-custom-icons-for-elementor'),
                esc_html__('Welcome', 'skyboot-custom-icons-for-elementor'),
                'manage_options',
                self::WELCOME_SLUG,
                array($this, 'render_welcome')
            );

            if (!Skb_Cife_Pro::is_active()) {
                add_submenu_page(
                    'skyboot_custom_icons',
                    esc_html__('Join Waitlist', 'skyboot-custom-icons-for-elementor'),
                    '<span class="skb-gopro-menu">' . esc_html__('Join Waitlist', 'skyboot-custom-icons-for-elementor') . '</span>',
                    'manage_options',
                    self::GOPRO_SLUG,
                    array($this, 'render_go_pro')
                );
            }
        }

        public function menu_styles()
        {
            ?>
            <style>
                .skb-gopro-menu {
                    color: #ffdd57 !important;
                    font-weight: 700;
                }

                #adminmenu .skb-gopro-menu::before {
                    content: "\f155";
                    font-family: dashicons;
                    vertical-align: middle;
                    margin-right: 4px;
                }

                #toplevel_page_skyboot_custom_icons .wp-menu-image img {
                    width: 20px;
                    padding-top: 7px;
                }
            </style>
            <?php
        }

        /* ---------------------------------------------------------------
         * Activation redirect (fires once)
         * ------------------------------------------------------------- */
        public function maybe_redirect()
        {
            if (!get_transient(self::REDIRECT_TRANSIENT)) {
                return;
            }
            delete_transient(self::REDIRECT_TRANSIENT);

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (wp_doing_ajax() || isset($_GET['activate-multi'])) {
                return;
            }
            if (!current_user_can('manage_options')) {
                return;
            }

            wp_safe_redirect(admin_url('admin.php?page=' . self::WELCOME_SLUG));
            exit;
        }

        /* ---------------------------------------------------------------
         * Dismissible upgrade notice (admin_notices)
         * ------------------------------------------------------------- */
        public function upgrade_notice()
        {
            if (Skb_Cife_Pro::is_active()) {
                return;
            }
            if (!current_user_can('manage_options')) {
                return;
            }
            if (get_user_meta(get_current_user_id(), self::NOTICE_META, true)) {
                return;
            }

            $screen = get_current_screen();
            $allowed = array('plugins', 'dashboard');
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $on_our_page = isset($_GET['page']) && 0 === strpos(sanitize_key(wp_unslash($_GET['page'])), 'skyboot_icons');
            if (!$on_our_page && (!$screen || !in_array($screen->id, $allowed, true))) {
                return;
            }

            $dismiss_url = wp_nonce_url(
                add_query_arg('skb_cife_dismiss_notice', '1'),
                'skb_cife_dismiss_notice',
                'skb_cife_notice_nonce'
            );
            ?>
            <div class="notice" style="position:relative;border-left:4px solid #531df7;padding:0;overflow:hidden;">
                <a href="<?php echo esc_url($dismiss_url); ?>" class="notice-dismiss" style="text-decoration:none;">
                    <span
                        class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'skyboot-custom-icons-for-elementor'); ?></span>
                </a>
                <div style="display:flex;align-items:center;gap:14px;padding:12px 40px 12px 16px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:280px;">
                        <span
                            style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:linear-gradient(135deg,#531df7,#7c3aed);border-radius:8px;flex-shrink:0;">
                            <img src="<?php echo esc_url(SKB_CIFE_ASSETS . 'images/skb-logo.svg'); ?>" alt="Skyboot Logo"
                                style="width: 100%; height: auto; border-radius: inherit;">
                        </span>
                        <div>
                            <strong
                                style="color:#1a1535;font-size:13px;display:block;margin-bottom:2px;"><?php esc_html_e('Skyboot Pro is Coming Soon!', 'skyboot-custom-icons-for-elementor'); ?></strong>
                            <span
                                style="color:#6e6889;font-size:12.5px;"><?php esc_html_e('Get ready for more icon packs, unlimited SVG upload, Gradient, duotone & dynamic icons. Join the waitlist now!', 'skyboot-custom-icons-for-elementor'); ?></span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::GOPRO_SLUG)); ?>"
                            style="background:#531df7;color:#fff;text-decoration:none;padding:8px 14px;border-radius:8px;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
                            <?php esc_html_e('Join Waitlist', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }

        /* ---------------------------------------------------------------
         * Review notice
         * ------------------------------------------------------------- */

        /**
         * Initialize review tracking options on first load (idempotent).
         * Uses add_option so it's safe to call on every admin_init.
         */
        public function maybe_record_install_time()
        {
            // Use add_option — does nothing if option already exists
            // This is safer than get_option + update_option for first-time creation
            $initialized = add_option(self::REVIEW_START_TIME, time(), '', false);

            if ($initialized) {
                // Fresh install — set pending status and version
                add_option(self::REVIEW_STATUS, 'pending', '', false);
                add_option(self::REVIEW_VERSION, SKB_CIFE_VERSION, '', false);
            } else {
                // Already installed — check if we need to handle a plugin update
                $status = get_option(self::REVIEW_STATUS);
                $version = get_option(self::REVIEW_VERSION);

                // If user clicked "No Thanks" on a previous version, reset for this version
                if ('no_thanks' === $status && $version !== SKB_CIFE_VERSION) {
                    update_option(self::REVIEW_STATUS, 'pending');
                    update_option(self::REVIEW_START_TIME, time());
                    update_option(self::REVIEW_VERSION, SKB_CIFE_VERSION);
                } elseif ($version !== SKB_CIFE_VERSION) {
                    // Different version but not no_thanks — just update version record
                    update_option(self::REVIEW_VERSION, SKB_CIFE_VERSION);
                }
            }
        }

        /**
         * Handle review notice actions via standard nonced links.
         */
        public function handle_review_actions()
        {
            if (!isset($_GET['skb_review_action']) || !isset($_GET['_wpnonce'])) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            check_admin_referer('skb_review_action');

            $action = sanitize_key($_GET['skb_review_action']);

            if ('already_rated' === $action) {
                update_option(self::REVIEW_STATUS, 'rated');
            } elseif ('remind' === $action) {
                update_option(self::REVIEW_STATUS, 'remind');
                update_option(self::REVIEW_REMIND_TIME, time());
            } elseif ('no_thanks' === $action) {
                update_option(self::REVIEW_STATUS, 'no_thanks');
            }

            wp_safe_redirect(remove_query_arg(array('skb_review_action', '_wpnonce')));
            exit;
        }

        /**
         * Decide whether to show the review notice for the current user.
         */
        private function should_show_review_notice()
        {
            if (!current_user_can('manage_options')) {
                return false;
            }

            global $pagenow;
            if ('index.php' !== $pagenow) {
                return false;
            }

            $status = get_option(self::REVIEW_STATUS);
            if ('rated' === $status || 'no_thanks' === $status) {
                return false;
            }

            $start_time = (int) get_option(self::REVIEW_START_TIME, 0);
            if (!$start_time) {
                return false;
            }

            $days_elapsed = (time() - $start_time) / DAY_IN_SECONDS;
            if ($days_elapsed < 7) {
                return false;
            }

            if ('remind' === $status) {
                $remind_time = (int) get_option(self::REVIEW_REMIND_TIME, 0);
                if (time() < $remind_time + (3 * DAY_IN_SECONDS)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Render the review nudge notice.
         */
        public function review_notice()
        {
            if (!$this->should_show_review_notice()) {
                return;
            }

            $review_url = 'https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/reviews/#new-post';
            $already_rated_url = wp_nonce_url(add_query_arg('skb_review_action', 'already_rated'), 'skb_review_action');
            $remind_url = wp_nonce_url(add_query_arg('skb_review_action', 'remind'), 'skb_review_action');
            $no_thanks_url = wp_nonce_url(add_query_arg('skb_review_action', 'no_thanks'), 'skb_review_action');
            ?>
            <div class="notice skb-review-notice" id="skb-review-notice"
                style="border-left:4px solid #531df7;padding:0;overflow:hidden;">
                <div style="display:flex;align-items:center;gap:16px;padding:14px 18px;flex-wrap:wrap;">

                    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:260px;">
                        <span
                            style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:linear-gradient(135deg,#531df7,#7c3aed);border-radius:10px;flex-shrink:0;">
                            <img src="<?php echo esc_url(SKB_CIFE_ASSETS . 'images/skb-logo.svg'); ?>" alt="Skyboot Icon"
                                style="width:100%;height:auto;border-radius:inherit;">
                        </span>
                        <div>
                            <strong style="color:#1a1535;font-size:13.5px;display:block;margin-bottom:3px;">
                                <?php esc_html_e('Enjoying Skyboot Custom Icons?', 'skyboot-custom-icons-for-elementor'); ?>
                            </strong>
                            <span style="color:#6e6889;font-size:12.5px;line-height:1.5;">
                                <?php esc_html_e('Thank you for choosing Skyboot Custom Icons for Elementor! If our plugin has been useful and made you smile, please consider giving us a 5-star rating on WordPress.org. It would mean a lot to us.', 'skyboot-custom-icons-for-elementor'); ?>
                            </span>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0;">
                        <a href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener" class="skb-review-btn"
                            style="background:#531df7;color:#fff;text-decoration:none;padding:9px 16px;border-radius:8px;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border:none;cursor:pointer;">
                            👍 <?php esc_html_e('Yes, You Deserve It!', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                        <a href="<?php echo esc_url($already_rated_url); ?>" class="skb-review-btn"
                            style="background:#fff;color:#531df7;text-decoration:none;padding:9px 14px;border-radius:8px;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border:1.5px solid #531df7;cursor:pointer;">
                            🙌 <?php esc_html_e('Already Rated!', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                        <a href="<?php echo esc_url($remind_url); ?>" class="skb-review-btn"
                            style="background:#fff;color:#6e6889;text-decoration:none;padding:9px 14px;border-radius:8px;font-size:12.5px;font-weight:600;display:inline-flex;align-items:center;gap:6px;border:1.5px solid #e0d9f0;cursor:pointer;">
                            🔔 <?php esc_html_e('Remind Me Later', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                        <a href="<?php echo esc_url($no_thanks_url); ?>" class="skb-review-btn"
                            style="background:transparent;color:#6e6889;text-decoration:none;padding:9px 14px;border-radius:8px;font-size:12.5px;font-weight:600;display:inline-flex;align-items:center;gap:6px;border:1.5px solid transparent;cursor:pointer;">
                            💔 <?php esc_html_e('No Thanks', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                    </div>

                </div>
            </div>
            <?php
        }

        public function maybe_dismiss_notice()
        {
            if (!isset($_GET['skb_cife_dismiss_notice'])) {
                return;
            }
            if (!isset($_GET['skb_cife_notice_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['skb_cife_notice_nonce'])), 'skb_cife_dismiss_notice')) {
                return;
            }
            update_user_meta(get_current_user_id(), self::NOTICE_META, 1);
            wp_safe_redirect(remove_query_arg(array('skb_cife_dismiss_notice', 'skb_cife_notice_nonce')));
            exit;
        }

        /* ---------------------------------------------------------------
         * Dashboard widget
         * ------------------------------------------------------------- */
        public function register_dashboard_widget()
        {
            if (!current_user_can('manage_options')) {
                return;
            }
            wp_add_dashboard_widget(
                'skb_cife_dashboard_widget',
                esc_html__('Skyboot Custom Icons', 'skyboot-custom-icons-for-elementor'),
                array($this, 'render_dashboard_widget')
            );
        }

        public function render_dashboard_widget()
        {
            $active = self::active_pack_count();
            $total = count(self::pack_defaults());
            $settings = admin_url('admin.php?page=' . self::SETTINGS_SLUG);
            $news = array(
                array(
                    'text' => __('v1.2.0 released — Redesigned plugin settings UI', 'skyboot-custom-icons-for-elementor'),
                    'url' => 'https://wordpress.org/plugins/skyboot-custom-icons-for-elementor/#developers',
                ),
                array(
                    'text' => __('Fixed IcoFont Duotone CSS rendering conflicts', 'skyboot-custom-icons-for-elementor'),
                    'url' => 'https://wordpress.org/plugins/skyboot-custom-icons-for-elementor/#developers',
                ),
                array(
                    'text' => __('Prevented CSS double-loading for variations', 'skyboot-custom-icons-for-elementor'),
                    'url' => 'https://wordpress.org/plugins/skyboot-custom-icons-for-elementor/#developers',
                ),
            );
            ?>
            <div class="skb-dw">
                <div class="skb-dw-stats">
                    <div class="skb-dw-stat">
                        <span class="skb-dw-num"><?php echo esc_html($active); ?>/<?php echo esc_html($total); ?></span>
                        <span
                            class="skb-dw-lbl"><?php esc_html_e('Icon sets enabled', 'skyboot-custom-icons-for-elementor'); ?></span>
                    </div>
                    <div class="skb-dw-stat">
                        <span class="skb-dw-num">14,300+</span>
                        <span
                            class="skb-dw-lbl"><?php esc_html_e('Icons available', 'skyboot-custom-icons-for-elementor'); ?></span>
                    </div>
                </div>
                <div class="skb-dw-actions">
                    <a href="<?php echo esc_url($settings); ?>"
                        class="button button-primary"><?php esc_html_e('Manage icon sets', 'skyboot-custom-icons-for-elementor'); ?></a>
                </div>
                <div class="skb-dw-news">
                    <h4><?php esc_html_e('News & Updates', 'skyboot-custom-icons-for-elementor'); ?></h4>
                    <ul>
                        <?php foreach ($news as $item): ?>
                            <li>
                                <a href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
                                    <span class="skb-dw-dot" aria-hidden="true"></span>
                                    <?php echo esc_html($item['text']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (!Skb_Cife_Pro::is_active()): ?>
                    <div class="skb-dw-pro" style="margin-top:12px;margin-bottom:0;">
                        <strong><?php esc_html_e('Skyboot Pro Coming Soon', 'skyboot-custom-icons-for-elementor'); ?></strong>
                        <span><?php esc_html_e('Custom SVG upload, performance & animations.', 'skyboot-custom-icons-for-elementor'); ?></span>
                        <a
                            href="<?php echo esc_url(admin_url('admin.php?page=' . self::GOPRO_SLUG)); ?>"><?php esc_html_e('Join Waitlist →', 'skyboot-custom-icons-for-elementor'); ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        /* ---------------------------------------------------------------
         * Plugin row links
         * ------------------------------------------------------------- */
        public function action_links($links)
        {
            $settings = '<a href="' . esc_url(admin_url('admin.php?page=' . self::SETTINGS_SLUG)) . '">' . esc_html__('Settings', 'skyboot-custom-icons-for-elementor') . '</a>';
            array_unshift($links, $settings);
            if (!Skb_Cife_Pro::is_active()) {
                $links['gopro'] = '<a href="' . esc_url(admin_url('admin.php?page=' . self::GOPRO_SLUG)) . '" style="color:#7c3aed;font-weight:700;">' . esc_html__('Join Waitlist', 'skyboot-custom-icons-for-elementor') . '</a>';
            }
            return $links;
        }

        public function row_meta($links, $file)
        {
            if (SKB_CIFE_PLUGIN_BASE !== $file) {
                return $links;
            }
            $links[] = '<a href="https://skybootstrap.com/learning/" target="_blank" rel="noopener">' . esc_html__('Docs', 'skyboot-custom-icons-for-elementor') . '</a>';
            $links[] = '<a href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/" target="_blank" rel="noopener">' . esc_html__('Support', 'skyboot-custom-icons-for-elementor') . '</a>';
            $links[] = '<a href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/reviews/#new-post" target="_blank" rel="noopener" style="color:#ffb900;font-weight:600;">⭐ ' . esc_html__('Rate 5 Stars', 'skyboot-custom-icons-for-elementor') . '</a>';
            return $links;
        }

        /* ---------------------------------------------------------------
         * Onboarding / Welcome screen
         * ------------------------------------------------------------- */
        public function render_welcome()
        {
            $settings = admin_url('admin.php?page=' . self::SETTINGS_SLUG);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $subscribed = isset($_GET['skb_subscribed']) ? sanitize_key(wp_unslash($_GET['skb_subscribed'])) : '';
            $current_user = wp_get_current_user();
            ?>
            <div class="skb-admin">
                <h1 class="screen-reader-text">
                    <?php esc_html_e('Welcome to Skyboot Icons', 'skyboot-custom-icons-for-elementor'); ?>
                </h1>

                <div class="skb-onboard-wrap">

                    <div class="skb-onboard-header">
                        <div class="skb-onboard-logo">
                            <img src="<?php echo esc_url(SKB_CIFE_ASSETS . 'images/skb-logo.svg'); ?>" alt="Skyboot Logo"
                                style="width: 100%; height: auto; border-radius: inherit;">
                        </div>
                        <div class="skb-onboard-title">Skyboot
                            <span><?php esc_html_e('Custom Icons', 'skyboot-custom-icons-for-elementor'); ?></span>
                        </div>
                    </div>

                    <div class="skb-onboard-hero">
                        <h2><?php esc_html_e('Welcome aboard!', 'skyboot-custom-icons-for-elementor'); ?> 🎉</h2>
                        <p><?php esc_html_e("You now have 14,300+ icons ready for Elementor. Let's get you set up in two quick steps.", 'skyboot-custom-icons-for-elementor'); ?>
                        </p>
                        <a href="<?php echo esc_url($settings); ?>#skb-manage" class="skb-btn">
                            <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                            <?php esc_html_e('Choose your icon sets', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                    </div>

                    <div class="skb-onboard-email-card" id="waitlist-section">
                        <h3><?php esc_html_e('Join the Waitlist', 'skyboot-custom-icons-for-elementor'); ?></h3>
                        <?php if ('ok' === $subscribed): ?>
                            <div class="skb-onboard-success" id="skb-welcome-success">
                                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                <?php esc_html_e("Thanks — you're on the list!", 'skyboot-custom-icons-for-elementor'); ?>
                            </div>
                            <script>
                                setTimeout(function () {
                                    var successMsg = document.getElementById('skb-welcome-success');
                                    var formWrap = document.getElementById('skb-welcome-form-wrap');
                                    if (successMsg && formWrap) {
                                        successMsg.style.display = 'none';
                                        formWrap.style.display = 'block';
                                    }
                                }, 5000);
                            </script>
                        <?php endif; ?>

                        <div id="skb-welcome-form-wrap" style="<?php echo ('ok' === $subscribed) ? 'display:none;' : ''; ?>">
                            <p><?php esc_html_e('Be the first to know when Skyboot Icon Pro launches. Get practical WordPress tips, exclusive launch discounts, and updates delivered straight to your inbox.', 'skyboot-custom-icons-for-elementor'); ?>
                            </p>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="skb_cife_onboarding">
                                <input type="hidden" name="skb_redirect"
                                    value="<?php echo esc_url(admin_url('admin.php?page=' . self::WELCOME_SLUG)); ?>">
                                <?php wp_nonce_field('skb_cife_onboarding', 'skb_cife_onboarding_nonce'); ?>
                                <div class="skb-email-form">
                                    <input type="email" name="skb_email" class="skb-email-input" id="skb-onboard-email"
                                        placeholder="<?php echo esc_attr($current_user->user_email); ?>" autocomplete="email">
                                    <label class="skb-email-consent" for="skb-onboard-consent">
                                        <input type="checkbox" id="skb-onboard-consent" name="skb_consent" value="1" required
                                            checked>
                                        <?php esc_html_e('I agree to receive emails.', 'skyboot-custom-icons-for-elementor'); ?>
                                    </label>
                                    <button type="submit"
                                        class="skb-btn"><?php esc_html_e('Subscribe', 'skyboot-custom-icons-for-elementor'); ?></button>
                                    <a href="<?php echo esc_url($settings); ?>"
                                        class="skb-email-skip"><?php esc_html_e('Skip', 'skyboot-custom-icons-for-elementor'); ?></a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="skb-footer">Skyboot Custom Icons for Elementor · v<?php echo esc_html(SKB_CIFE_VERSION); ?></div>
                </div>
            </div>
            <?php
        }

        /**
         * Handle the optional email opt-in.
         * By default NOTHING is transmitted off-site.
         */
        public function handle_onboarding_submit()
        {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Permission denied.', 'skyboot-custom-icons-for-elementor'));
            }
            if (!isset($_POST['skb_cife_onboarding_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['skb_cife_onboarding_nonce'])), 'skb_cife_onboarding')) {
                wp_die(esc_html__('Security check failed.', 'skyboot-custom-icons-for-elementor'));
            }

            $redirect = admin_url('admin.php?page=' . self::WELCOME_SLUG);
            if (!empty($_POST['skb_redirect'])) {
                $redirect = esc_url_raw(wp_unslash($_POST['skb_redirect']));
            }
            $consent = !empty($_POST['skb_consent']);
            $email = isset($_POST['skb_email']) ? sanitize_email(wp_unslash($_POST['skb_email'])) : '';

            if ($consent && is_email($email)) {
                /**
                 * Fires when a user opts in during onboarding.
                 * Hook this to send the address to your email service.
                 * No data leaves the site otherwise.
                 *
                 * @param string $email
                 */
                do_action('skb_cife_onboarding_subscribe', $email);
                update_option(self::ONBOARDED_OPTION, 1);
                $redirect = add_query_arg('skb_subscribed', 'ok', $redirect);
            }

            wp_safe_redirect($redirect);
            exit;
        }

        /**
         * Subscribe email to Mail Mint webhook.
         *
         * @param string $email Subscriber email.
         */
        public function subscribe_to_mailmint($email)
        {

            $response = wp_remote_post(
                'https://skybootstrap.com/?mailmint=1&route=webhook&topic=contact&hash=d429d385-3c0b-4423-a64a-fb2808cc290d',
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => wp_json_encode(
                        array(
                            'email' => $email,
                        )
                    ),
                )
            );

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Mail Mint Error: ' . $response->get_error_message());
                }
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);

            if (200 !== $code) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Mail Mint HTTP Error: ' . $code);
                }
                return false;
            }

            return true;
        }

        /* ---------------------------------------------------------------
         * Go Pro page
         * ------------------------------------------------------------- */
        public function render_go_pro()
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $subscribed = isset($_GET['skb_subscribed']) ? sanitize_key(wp_unslash($_GET['skb_subscribed'])) : '';
            $current_user = wp_get_current_user();
            ?>
            <div class="skb-admin">
                <div class="skb-wrap" style="max-width:700px;">
                    <div class="skb-header">
                        <div class="skb-brand">
                            <span class="skb-brand__logo"><span class="dashicons dashicons-awards" aria-hidden="true"></span></span>
                            <h2 class="skb-brand__title">Skyboot
                                <span><?php esc_html_e('Icons Pro', 'skyboot-custom-icons-for-elementor'); ?></span>
                            </h2>
                        </div>
                    </div>
                    <div class="skb-card" style="padding:24px 28px;">
                        <div class="skb-packs" style="grid-template-columns:repeat(2,1fr);">
                            <?php foreach (Skb_Cife_Pro::features() as $f): ?>
                                <div class="skb-pack">
                                    <span class="skb-pack__ico"><span class="dashicons <?php echo esc_attr($f['icon']); ?>"
                                            aria-hidden="true"></span></span>
                                    <div class="skb-pack__body">
                                        <p class="skb-pack__name"><?php echo esc_html($f['title']); ?> <span class="skb-badge-pro"><span
                                                    class="dashicons dashicons-clock" aria-hidden="true"></span>SOON</span></p>
                                        <p class="skb-pack__desc"><?php echo esc_html($f['desc']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="skb-onboard-email-card"
                            style="margin-top:24px; border-top:1px solid var(--skb-border); padding-top:24px;">
                            <h3><?php esc_html_e('Join the Waitlist', 'skyboot-custom-icons-for-elementor'); ?></h3>
                            <?php if ('ok' === $subscribed): ?>
                                <div class="skb-onboard-success" id="skb-gopro-success">
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                    <?php esc_html_e("Thanks — you're on the list!", 'skyboot-custom-icons-for-elementor'); ?>
                                </div>
                                <script>
                                    setTimeout(function () {
                                        var successMsg = document.getElementById('skb-gopro-success');
                                        var formWrap = document.getElementById('skb-gopro-form-wrap');
                                        if (successMsg && formWrap) {
                                            successMsg.style.display = 'none';
                                            formWrap.style.display = 'block';
                                        }
                                    }, 5000);
                                </script>
                            <?php endif; ?>

                            <div id="skb-gopro-form-wrap" style="<?php echo ('ok' === $subscribed) ? 'display:none;' : ''; ?>">
                                <p><?php esc_html_e('Be the first to know when Skyboot Icon Pro launches. Get practical WordPress tips, exclusive launch discounts, and updates delivered straight to your inbox.', 'skyboot-custom-icons-for-elementor'); ?>
                                </p>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="skb_cife_onboarding">
                                    <input type="hidden" name="skb_redirect"
                                        value="<?php echo esc_url(admin_url('admin.php?page=' . self::GOPRO_SLUG)); ?>">
                                    <?php wp_nonce_field('skb_cife_onboarding', 'skb_cife_onboarding_nonce'); ?>
                                    <div class="skb-email-form">
                                        <input type="email" name="skb_email" class="skb-email-input" id="skb-onboard-email"
                                            placeholder="<?php echo esc_attr($current_user->user_email); ?>" autocomplete="email">
                                        <label class="skb-email-consent" for="skb-onboard-consent">
                                            <input type="checkbox" id="skb-onboard-consent" name="skb_consent" value="1" required
                                                checked>
                                            <?php esc_html_e('I agree to receive emails.', 'skyboot-custom-icons-for-elementor'); ?>
                                        </label>
                                        <button type="submit" class="skb-btn">
                                            <?php esc_html_e('Join Waitlist', 'skyboot-custom-icons-for-elementor'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
        }
    }

endif;
