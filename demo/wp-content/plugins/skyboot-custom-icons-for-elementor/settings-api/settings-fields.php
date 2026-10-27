<?php
// Exit if accessed directly.
if (!defined('ABSPATH'))
    exit;

class Skb_Cife_Settings_API_Fields
{

    private $settings_api;

    /**
     * Icon pack catalogue.
     * Keys MUST match option names in class-base.php & class-scripts-manager.php.
     */
    private function get_icon_packs()
    {
        return array(
            array('key' => 'elegant_icon', 'name' => 'Elegant Icon', 'desc' => 'Beautiful & elegant icon collection.', 'count' => '150+', 'default' => 'on', 'initials' => 'El', 'pro' => false, 'url' => 'https://www.elegantthemes.com/blog/resources/elegant-icon-font'),
            array('key' => 'linearicons_icon', 'name' => 'Linearicons', 'desc' => 'Clean & minimal line icons.', 'count' => '180+', 'default' => 'on', 'initials' => 'Li', 'pro' => false, 'url' => 'https://linearicons.com'),
            array('key' => 'themify_icon', 'name' => 'Themify Icon', 'desc' => 'The official Themify icon set.', 'count' => '320+', 'default' => 'on', 'initials' => 'Th', 'pro' => false, 'url' => 'https://themify.me/themify-icons'),
            array('key' => 'simpleline_icon', 'name' => 'Simple Line Icon', 'desc' => 'Minimal line icons for a modern look.', 'count' => '120+', 'default' => 'off', 'initials' => 'Si', 'pro' => false, 'url' => 'https://simplelineicons.github.io'),
            array('key' => 'line_icon', 'name' => 'Line Icon', 'desc' => 'Essential line icons for every project.', 'count' => '200+', 'default' => 'off', 'initials' => 'Ln', 'pro' => false, 'url' => 'https://lineicons.com'),
            array('key' => 'ion_icon', 'name' => 'Ionicons', 'desc' => 'Premium quality icons from Ionic.', 'count' => '1,300+', 'default' => 'off', 'initials' => 'Io', 'pro' => false, 'url' => 'https://ionicons.com'),
            array('key' => 'icofont_icon', 'name' => 'Icofont', 'desc' => 'Versatile icon font collection.', 'count' => '2,400+', 'default' => 'off', 'initials' => 'Ic', 'pro' => false, 'url' => 'https://icofont.com'),
            array('key' => 'lineawesome_icon', 'name' => 'Line Awesome', 'desc' => 'Modern icons with line style.', 'count' => '1,000+', 'default' => 'off', 'initials' => 'La', 'pro' => false, 'url' => 'https://icons8.com/line-awesome'),
            array('key' => 'materialdesign_icon', 'name' => 'Material Design Icons', 'desc' => "Google's official material design icons.", 'count' => '7,000+', 'default' => 'off', 'initials' => 'Md', 'pro' => false, 'url' => 'http://materialdesignicons.com'),
            array('key' => 'devicons_icon', 'name' => 'Devicons', 'desc' => 'Developer & technology icons.', 'count' => '300+', 'default' => 'off', 'initials' => 'Dv', 'pro' => false, 'url' => 'http://vorillaz.github.io/devicons/#/dafont'),
            array('key' => 'icomoon_icon', 'name' => 'Icomoon Icons', 'desc' => 'Customizable icon library.', 'count' => '500+', 'default' => 'off', 'initials' => 'Im', 'pro' => false, 'url' => 'https://icomoon.io/#preview-free'),
            array('key' => 'elusive_icon', 'name' => 'Elusive Icons', 'desc' => 'Premium icon set for professionals.', 'count' => '700+', 'default' => 'off', 'initials' => 'El', 'pro' => false, 'url' => 'http://elusiveicons.com'),
            array('key' => 'iconic_icon', 'name' => 'Iconic Icons', 'desc' => 'Iconic and timeless icons.', 'count' => '300+', 'default' => 'off', 'initials' => 'In', 'pro' => false, 'url' => 'https://github.com/somerandomdude/Iconic'),
            array('key' => 'brands_icon', 'name' => 'Brands Icon', 'desc' => 'Popular brand & social icons.', 'count' => '400+', 'default' => 'off', 'initials' => 'Br', 'pro' => false, 'url' => 'https://simpleicons.org'),
            array('key' => 'open_iconic_icon', 'name' => 'Open Iconic', 'desc' => 'Open-source icons for everyone.', 'count' => '250+', 'default' => 'off', 'initials' => 'Op', 'pro' => false, 'url' => 'https://useiconic.com/open'),
        );
    }

    function __construct()
    {
        $this->settings_api = new Skb_Cife_Settings_API;
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }

    function admin_init()
    {
        $this->settings_api->set_sections($this->get_settings_sections());
        $this->settings_api->set_fields($this->get_settings_fields());
        $this->settings_api->admin_init();
    }

    function admin_menu()
    {
        add_menu_page(
            'skb_cife_admin_page',
            esc_html__('Skyboot Icons', 'skyboot-custom-icons-for-elementor'),
            esc_html__('Skyboot Icons', 'skyboot-custom-icons-for-elementor'),
            'skyboot_custom_icons',
            NULL,
            SKB_CIFE_ASSETS . 'images/skb-logo.svg',
            50
        );
        add_submenu_page(
            'skyboot_custom_icons',
            esc_html__('Settings', 'skyboot-custom-icons-for-elementor'),
            esc_html__('Settings', 'skyboot-custom-icons-for-elementor'),
            'manage_options',
            'skyboot_icons',
            array($this, 'plugin_page')
        );
    }

    function get_settings_sections()
    {
        return array(
            array('id' => 'skb_cife_general', 'title' => __('General', 'skyboot-custom-icons-for-elementor')),
            array('id' => 'skb_cife_manage_icon', 'title' => __('Manage Icons', 'skyboot-custom-icons-for-elementor')),
            array('id' => 'skb_cife_update_info', 'title' => __('Update Info', 'skyboot-custom-icons-for-elementor')),
        );
    }

    function get_settings_fields()
    {
        $fields = array(
            'skb_cife_general' => array(),
            'skb_cife_update_info' => array(),
            'skb_cife_manage_icon' => array(),
        );
        foreach ($this->get_icon_packs() as $pack) {
            $fields['skb_cife_manage_icon'][] = array(
                'name' => $pack['key'],
                'label' => $pack['name'],
                'type' => 'checkbox',
                'default' => $pack['default'],
            );
        }
        return $fields;
    }

    /* =================================================================
     * Main plugin page
     * ================================================================= */
    function plugin_page()
    {
        $packs = $this->get_icon_packs();
        $active_sets = 0;
        foreach ($packs as $pack) {
            if ('on' === skb_cife_get_option($pack['key'], 'skb_cife_manage_icon', $pack['default'])) {
                $active_sets++;
            }
        }
        $total_packs = count($packs);
        $is_pro = class_exists('Skb_Cife_Pro') ? Skb_Cife_Pro::is_active() : false;
        $upgrade_url = class_exists('Skb_Cife_Pro')
            ? Skb_Cife_Pro::upgrade_url('dashboard')
            : 'https://skybootstrap.com/custom-icons-for-elementor/';
            
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $subscribed = isset($_GET['skb_subscribed']) ? sanitize_key(wp_unslash($_GET['skb_subscribed'])) : '';
        $current_user = wp_get_current_user();
        ?>
        <div class="skb-admin">
            <h1 class="screen-reader-text"><?php echo esc_html__('Skyboot Custom Icons for Elementor', 'skyboot-custom-icons-for-elementor'); ?></h1>
            <div class="skb-wrap">

                <?php // ---- Header ---- ?>
                <div class="skb-header">
                    <div class="skb-brand">
                        <span class="skb-brand__logo"><img src="<?php echo esc_url(SKB_CIFE_ASSETS . 'images/skb-logo.svg'); ?>" alt="Skyboot Icon" style="width: 100%; height: auto; border-radius: inherit;"></span>
                        <h2 class="skb-brand__title">Skyboot
                            <span><?php echo esc_html__('Custom Icons for Elementor', 'skyboot-custom-icons-for-elementor'); ?></span></h2>
                    </div>
                    <a class="skb-header__help" href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-sos"></span><?php echo esc_html__('Need Help?', 'skyboot-custom-icons-for-elementor'); ?>
                    </a>
                </div>

                <?php // ---- Tab bar ---- ?>
                <div class="skb-tabs" role="tablist" aria-label="<?php esc_attr_e('Plugin settings tabs', 'skyboot-custom-icons-for-elementor'); ?>">
                    <button type="button" class="skb-tab" role="tab" data-tab="general" id="skb-tab-general"
                        aria-controls="skb-panel-general">
                        <span class="dashicons dashicons-admin-home"
                            aria-hidden="true"></span><?php echo esc_html__('General', 'skyboot-custom-icons-for-elementor'); ?>
                    </button>
                    <button type="button" class="skb-tab" role="tab" data-tab="manage" id="skb-tab-manage"
                        aria-controls="skb-panel-manage">
                        <span class="dashicons dashicons-screenoptions"
                            aria-hidden="true"></span><?php echo esc_html__('Manage Icons', 'skyboot-custom-icons-for-elementor'); ?>
                    </button>
                    <button type="button" class="skb-tab" role="tab" data-tab="svg" id="skb-tab-svg"
                        aria-controls="skb-panel-svg">
                        <span class="dashicons dashicons-upload"
                            aria-hidden="true"></span><?php echo esc_html__('SVG Upload', 'skyboot-custom-icons-for-elementor'); ?>
                        <?php if (!$is_pro): ?><span class="skb-tab__pro">PRO</span><?php endif; ?>
                    </button>
                    <button type="button" class="skb-tab" role="tab" data-tab="modules" id="skb-tab-modules"
                        aria-controls="skb-panel-modules">
                        <span class="dashicons dashicons-admin-plugins"
                            aria-hidden="true"></span><?php echo esc_html__('Modules', 'skyboot-custom-icons-for-elementor'); ?>
                        <?php if (!$is_pro): ?><span class="skb-tab__pro">PRO</span><?php endif; ?>
                    </button>
                    <button type="button" class="skb-tab" role="tab" data-tab="update" id="skb-tab-update"
                        aria-controls="skb-panel-update">
                        <span class="dashicons dashicons-info-outline"
                            aria-hidden="true"></span><?php echo esc_html__('Update Info', 'skyboot-custom-icons-for-elementor'); ?>
                    </button>
                </div>
                <script>
                    (function() {
                        var hash = window.location.hash || '';
                        var initial = '';
                        if (hash.indexOf('#skb-') === 0) {
                            initial = hash.replace('#skb-', '');
                        }
                        if (!initial) {
                            try { initial = window.localStorage.getItem('skb_active_tab') || ''; } catch (e) { }
                        }
                        var validTabs = ['general', 'manage', 'upload', 'modules', 'update'];
                        if (validTabs.indexOf(initial) === -1) {
                            initial = 'general';
                        }
                        document.write('<style id="skb-initial-tab-style">.skb-panel[data-panel="' + initial + '"] { display: block !important; } .skb-tab[data-tab="' + initial + '"] { color: var(--skb-primary-light) !important; background: var(--skb-bg) !important; font-weight: 700 !important; }</style>');
                    })();
                </script>

                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (isset($_GET['settings-updated'])): ?>
                    <div class="skb-notice-saved" style="margin-bottom:18px;" role="alert">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php echo esc_html__('Settings saved successfully.', 'skyboot-custom-icons-for-elementor'); ?>
                    </div>
                <?php endif; ?>

                <?php // ====================================================
                        // GENERAL PANEL
                        // ==================================================== ?>
                <div class="skb-panel" data-panel="general" id="skb-panel-general" role="tabpanel"
                    aria-labelledby="skb-tab-general">

                    <div class="skb-card skb-hero">
                        <div>
                            <h2><?php echo esc_html__('Welcome to Skyboot', 'skyboot-custom-icons-for-elementor'); ?> 👋</h2>
                            <p>
                                <?php
                                printf(
                                    /* translators: 1: icon count, 2: pack count */
                                    wp_kses(__('A library of <strong>%1$s icons</strong> from %2$s packs, ready to drop into Elementor. Enable a set in <strong>Manage Icons</strong> to get started.', 'skyboot-custom-icons-for-elementor'), array('strong' => array())),
                                    esc_html('14,300+'),
                                    esc_html($total_packs)
                                );
                                ?>
                            </p>
                        </div>
                        <a href="#skb-manage" class="skb-btn skb-goto-manage">
                            <span class="dashicons dashicons-screenoptions"
                                aria-hidden="true"></span><?php echo esc_html__('Manage Icons', 'skyboot-custom-icons-for-elementor'); ?>
                        </a>
                    </div>

                    <div class="skb-stats">
                        <div class="skb-card skb-stat">
                            <div class="skb-stat__ico"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                            </div>
                            <div class="skb-stat__num"><?php echo esc_html($total_packs); ?></div>
                            <div class="skb-stat__label"><?php echo esc_html__('Icon packs', 'skyboot-custom-icons-for-elementor'); ?></div>
                        </div>
                        <div class="skb-card skb-stat">
                            <div class="skb-stat__ico"><span class="dashicons dashicons-screenoptions"
                                    aria-hidden="true"></span></div>
                            <div class="skb-stat__num">14,300+</div>
                            <div class="skb-stat__label"><?php echo esc_html__('Total icons', 'skyboot-custom-icons-for-elementor'); ?></div>
                        </div>
                        <div class="skb-card skb-stat">
                            <div class="skb-stat__ico"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            </div>
                            <div class="skb-stat__num" id="skb-active-count"><?php echo esc_html($active_sets); ?></div>
                            <div class="skb-stat__label"><?php echo esc_html__('Active sets', 'skyboot-custom-icons-for-elementor'); ?></div>
                        </div>
                        <div class="skb-card skb-stat">
                            <div class="skb-stat__ico"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
                            <div class="skb-stat__num">200k+</div>
                            <div class="skb-stat__label"><?php echo esc_html__('Active installs', 'skyboot-custom-icons-for-elementor'); ?></div>
                        </div>
                    </div>

                    <div class="skb-grid-2">
                        <div class="skb-card skb-quickstart">
                            <h3><?php echo esc_html__('Quick start', 'skyboot-custom-icons-for-elementor'); ?></h3>
                            <p><?php echo esc_html__('Three steps to using Skyboot icons in Elementor.', 'skyboot-custom-icons-for-elementor'); ?></p>
                            <div class="skb-steps">
                                <div class="skb-step">
                                    <span class="skb-step__n">1</span>
                                    <div>
                                        <div class="skb-step__t"><?php echo esc_html__('Enable icon sets', 'skyboot-custom-icons-for-elementor'); ?>
                                        </div>
                                        <div class="skb-step__d">
                                            <?php echo esc_html__('Toggle the packs you want in the Manage Icons tab.', 'skyboot-custom-icons-for-elementor'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="skb-step">
                                    <span class="skb-step__n">2</span>
                                    <div>
                                        <div class="skb-step__t"><?php echo esc_html__('Open Elementor', 'skyboot-custom-icons-for-elementor'); ?></div>
                                        <div class="skb-step__d">
                                            <?php echo esc_html__('Edit any page and select an icon control.', 'skyboot-custom-icons-for-elementor'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="skb-step">
                                    <span class="skb-step__n">3</span>
                                    <div>
                                        <div class="skb-step__t"><?php echo esc_html__('Insert your icon', 'skyboot-custom-icons-for-elementor'); ?>
                                        </div>
                                        <div class="skb-step__d">
                                            <?php echo esc_html__('Pick any Skyboot icon — color & size are fully editable.', 'skyboot-custom-icons-for-elementor'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--skb-border);">
                                <h3 style="margin-top:0;"><?php echo esc_html__('Resources', 'skyboot-custom-icons-for-elementor'); ?></h3>
                                <ul class="skb-reslist" style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:0;">
                                    <li><a href="https://skybootstrap.com/custom-icons-for-elementor" target="_blank"
                                            rel="noopener">
                                            <span class="skb-res-ico dashicons dashicons-book"
                                                aria-hidden="true"></span><?php echo esc_html__('Documentation', 'skyboot-custom-icons-for-elementor'); ?>
                                        </a></li>
                                    <li><a href="https://youtu.be/zh46PmvLGr0" target="_blank" rel="noopener">
                                            <span class="skb-res-ico dashicons dashicons-video-alt3"
                                                aria-hidden="true"></span><?php echo esc_html__('Video tutorials', 'skyboot-custom-icons-for-elementor'); ?>
                                        </a></li>
                                    <li><a href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/"
                                            target="_blank" rel="noopener">
                                            <span class="skb-res-ico dashicons dashicons-format-chat"
                                                aria-hidden="true"></span><?php echo esc_html__('Support forum', 'skyboot-custom-icons-for-elementor'); ?>
                                        </a></li>
                                    <li><a href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/reviews/#new-post"
                                            target="_blank" rel="noopener" style="color:#ffb900;">
                                            <span class="skb-res-ico dashicons dashicons-star-filled"
                                                aria-hidden="true" style="color:#ffb900;"></span><?php echo esc_html__('Leave a review', 'skyboot-custom-icons-for-elementor'); ?>
                                        </a></li>
                                </ul>
                            </div>
                        </div>

                        <div>
                            <?php if (!$is_pro): ?>
                                <div class="skb-pro">
                                    <span class="skb-pro__badge"><span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                        SOON</span>
                                    <h3><?php echo esc_html__('Skyboot Pro Coming Soon', 'skyboot-custom-icons-for-elementor'); ?></h3>
                                    <p><?php echo esc_html__('SVG upload, more premium icons & priority support.', 'skyboot-custom-icons-for-elementor'); ?>
                                    </p>
                                    <a class="skb-pro__btn" href="#waitlist-section"><?php echo esc_html__('Join Waitlist', 'skyboot-custom-icons-for-elementor'); ?></a>
                                </div>
                            <?php endif; ?>

                            <div class="skb-card skb-side-card skb-mt" id="waitlist-section">
                                <h3><?php esc_html_e('Join the Waitlist', 'skyboot-custom-icons-for-elementor'); ?></h3>
                                <?php if ('ok' === $subscribed): ?>
                                    <div class="skb-onboard-success" id="skb-settings-success">
                                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                        <?php esc_html_e("Thanks — you're on the list!", 'skyboot-custom-icons-for-elementor'); ?>
                                    </div>
                                    <script>
                                        setTimeout(function() {
                                            var successMsg = document.getElementById('skb-settings-success');
                                            var formWrap = document.getElementById('skb-settings-form-wrap');
                                            if (successMsg && formWrap) {
                                                successMsg.style.display = 'none';
                                                formWrap.style.display = 'block';
                                            }
                                        }, 5000);
                                    </script>
                                <?php endif; ?>
                                
                                <div id="skb-settings-form-wrap" style="<?php echo ('ok' === $subscribed) ? 'display:none;' : ''; ?>">
                                    <p style="font-size:13px; color:var(--skb-muted); margin-bottom:16px;">
                                        <?php esc_html_e('Be the first to know when Skyboot Icon Pro launches. Get practical WordPress tips, exclusive launch discounts, and updates delivered straight to your inbox.', 'skyboot-custom-icons-for-elementor'); ?>
                                    </p>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="skb_cife_onboarding">
                                        <input type="hidden" name="skb_redirect" value="<?php echo esc_url(admin_url('admin.php?page=skyboot_icons')); ?>">
                                        <?php wp_nonce_field('skb_cife_onboarding', 'skb_cife_onboarding_nonce'); ?>
                                        <div class="skb-email-form">
                                            <input type="email" name="skb_email" class="skb-email-input" id="skb-onboard-email"
                                                placeholder="<?php echo esc_attr($current_user->user_email); ?>" autocomplete="email" style="width:100%; margin-bottom:10px;">
                                            <label class="skb-email-consent" for="skb-onboard-consent" style="display:block; margin-bottom:12px; font-size:12px; color:var(--skb-muted);">
                                                <input type="checkbox" id="skb-onboard-consent" name="skb_consent" value="1" required checked>
                                                <?php esc_html_e('I agree to receive emails.', 'skyboot-custom-icons-for-elementor'); ?>
                                            </label>
                                            <button type="submit" class="skb-btn" style="width:100%; justify-content:center;">
                                                <?php esc_html_e('Join Waitlist', 'skyboot-custom-icons-for-elementor'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>


                        </div>
                    </div>

                    <div class="skb-card skb-more skb-mt">
                        <div class="skb-more__head">
                            <div>
                                <h3><?php echo esc_html__('More from Skybootstrap', 'skyboot-custom-icons-for-elementor'); ?></h3>
                                <p><?php echo esc_html__('Plugins & tools we build to power your website & business growth.', 'skyboot-custom-icons-for-elementor'); ?>
                                </p>
                            </div>
                            <a class="skb-more__browse" href="https://skybootstrap.com" target="_blank"
                                rel="noopener"><?php echo esc_html__('Browse all', 'skyboot-custom-icons-for-elementor'); ?> ↗</a>
                        </div>
                        <div class="skb-promos">
                            <div class="skb-promo">
                                <div class="skb-promo__top">
                                    <span class="skb-promo__ico" style="background:#14b8a6;"><span
                                            class="dashicons dashicons-admin-links" aria-hidden="true"></span></span>
                                    <span class="skb-tag skb-tag--free"><?php echo esc_html__('FREE', 'skyboot-custom-icons-for-elementor'); ?></span>
                                </div>
                                <h4><?php echo esc_html__('TLinky', 'skyboot-custom-icons-for-elementor'); ?></h4>
                                <p><?php echo esc_html__('URL shortener, QR codes & link-in-bio builder.', 'skyboot-custom-icons-for-elementor'); ?></p>
                                <a class="skb-learn"
                                    href="https://tlinky.com/?utm_source=skb-icon-admin&utm_medium=skb-free-plugin"
                                    target="_blank" rel="noopener"><?php echo esc_html__('Learn more', 'skyboot-custom-icons-for-elementor'); ?> →</a>
                            </div>
                            <div class="skb-promo">
                                <div class="skb-promo__top">
                                    <span class="skb-promo__ico" style="background:#f97316;"><span
                                            class="dashicons dashicons-format-gallery" aria-hidden="true"></span></span>
                                    <span class="skb-tag skb-tag--free"><?php echo esc_html__('FREE', 'skyboot-custom-icons-for-elementor'); ?></span>
                                </div>
                                <h4><?php echo esc_html__('Portfolio Gallery', 'skyboot-custom-icons-for-elementor'); ?></h4>
                                <p><?php echo esc_html__('Create stunning, filterable Elementor galleries in seconds. Install now to showcase your work!', 'skyboot-custom-icons-for-elementor'); ?></p>
                                <a class="skb-learn" href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=skyboot-portfolio-gallery'), 'install-plugin_skyboot-portfolio-gallery')); ?>">
                                    <?php echo esc_html__('Install Now', 'skyboot-custom-icons-for-elementor'); ?> →</a>
                            </div>
                            <div class="skb-promo">
                                <div class="skb-promo__top">
                                    <span class="skb-promo__ico" style="background:#7c3aed;"><span
                                            class="dashicons dashicons-clock" aria-hidden="true"></span></span>
                                    <span class="skb-tag skb-tag--pro">SOON</span>
                                </div>
                                <h4><?php echo esc_html__('Skyboot Icons Pro', 'skyboot-custom-icons-for-elementor'); ?></h4>
                                <p><?php echo esc_html__('SVG upload, performance & premium icon packs.', 'skyboot-custom-icons-for-elementor'); ?></p>
                                <a class="skb-learn" href="#waitlist-section"><?php echo esc_html__('Join Waitlist', 'skyboot-custom-icons-for-elementor'); ?> →</a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php // ====================================================
                        // MANAGE ICONS PANEL
                        // ==================================================== ?>
                <div class="skb-panel" data-panel="manage" id="skb-panel-manage" role="tabpanel"
                    aria-labelledby="skb-tab-manage">
                    <form method="post" action="options.php">
                        <?php settings_fields('skb_cife_manage_icon'); ?>

                        <div class="skb-mi-head">
                            <div>
                                <h2><?php echo esc_html__('Manage Icon Sets', 'skyboot-custom-icons-for-elementor'); ?></h2>
                                <p>
                                    <?php
                                    printf(
                                        /* translators: 1: active count, 2: total count */
                                        wp_kses(__('Enable or disable icon sets to use in Elementor. <strong>%1$s</strong> of <strong>%2$s</strong> active.', 'skyboot-custom-icons-for-elementor'), array('strong' => array())),
                                        '<span id="skb-active-inline">' . esc_html($active_sets) . '</span>',
                                        esc_html($total_packs)
                                    );
                                    ?>
                                </p>
                            </div>
                            <div class="skb-mi-actions">
                                <div class="skb-search">
                                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                                    <input type="text" id="skb-pack-search"
                                        placeholder="<?php echo esc_attr__('Search icon sets…', 'skyboot-custom-icons-for-elementor'); ?>"
                                        autocomplete="off" aria-label="<?php esc_attr_e('Search icon sets', 'skyboot-custom-icons-for-elementor'); ?>">
                                </div>
                                <button type="button" id="skb-enable-all" class="skb-textbtn skb-textbtn--on">
                                    <span class="dashicons dashicons-yes"
                                        aria-hidden="true"></span><?php echo esc_html__('Enable All', 'skyboot-custom-icons-for-elementor'); ?>
                                </button>
                                <button type="button" id="skb-disable-all" class="skb-textbtn">
                                    <span class="dashicons dashicons-no-alt"
                                        aria-hidden="true"></span><?php echo esc_html__('Disable All', 'skyboot-custom-icons-for-elementor'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="skb-chips" role="group" aria-label="<?php esc_attr_e('Filter icon sets', 'skyboot-custom-icons-for-elementor'); ?>">
                            <button type="button" class="skb-chip is-active"
                                data-filter="all"><?php echo esc_html__('All sets', 'skyboot-custom-icons-for-elementor'); ?></button>
                            <button type="button" class="skb-chip"
                                data-filter="enabled"><?php echo esc_html__('Enabled', 'skyboot-custom-icons-for-elementor'); ?></button>
                            <button type="button" class="skb-chip"
                                data-filter="disabled"><?php echo esc_html__('Disabled', 'skyboot-custom-icons-for-elementor'); ?></button>
                        </div>

                        <div class="skb-packs">
                            <?php foreach ($packs as $pack):
                                $value = skb_cife_get_option($pack['key'], 'skb_cife_manage_icon', $pack['default']);
                                $checked = ('on' === $value);
                                $field = 'skb_cife_manage_icon[' . $pack['key'] . ']';
                                $id = 'skb-toggle-' . esc_attr($pack['key']);
                                $is_locked = !empty($pack['pro']) && !$is_pro;
                                ?>
                                <div class="skb-pack<?php echo $is_locked ? ' is-locked' : ''; ?>"
                                    data-name="<?php echo esc_attr($pack['name']); ?>"
                                    data-state="<?php echo $checked ? 'on' : 'off'; ?>"
                                    data-pro="<?php echo $is_locked ? '1' : '0'; ?>" <?php if ($is_locked): ?>data-locked="1"
                                    <?php endif; ?>>
                                    <span class="skb-pack__ico"><?php echo esc_html($pack['initials']); ?></span>
                                    <div class="skb-pack__body">
                                        <p class="skb-pack__name">
                                            <?php echo esc_html($pack['name']); ?>
                                            <?php if (!empty($pack['pro'])): ?>
                                                <span class="skb-badge-pro"><span class="dashicons dashicons-awards"
                                                        aria-hidden="true"></span>PRO</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="skb-pack__desc"><?php echo esc_html($pack['desc']); ?></p>
                                        <div style="display: flex; gap: 12px; align-items: center; margin-top: 6px;">
                                            <span class="skb-pack__count"><?php echo esc_html($pack['count']); ?>
                                                <?php echo esc_html__('icons', 'skyboot-custom-icons-for-elementor'); ?></span>
                                            
                                            <?php if (!empty($pack['url'])): ?>
                                                <a href="<?php echo esc_url($pack['url']); ?>" target="_blank" rel="noopener" style="font-size: 12px; text-decoration: none; display: flex; align-items: center; gap: 4px; color: var(--skb-primary-light);">
                                                    <?php echo esc_html__('Preview', 'skyboot-custom-icons-for-elementor'); ?> <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="skb-pack__ctrl">
                                        <?php if ($is_locked): ?>
                                            <span class="skb-lock"><span class="dashicons dashicons-lock"
                                                    aria-hidden="true"></span></span>
                                        <?php else: ?>
                                            <label class="skb-toggle">
                                                <input type="hidden" name="<?php echo esc_attr($field); ?>" value="off">
                                                <input type="checkbox" id="<?php echo esc_attr($id); ?>"
                                                    name="<?php echo esc_attr($field); ?>" value="on" <?php checked($checked); ?>
                                                    aria-label="<?php echo esc_attr($pack['name']); ?>">
                                                <span class="skb-slider"></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="skb-empty" role="status">
                            <?php echo esc_html__('No icon sets match your search.', 'skyboot-custom-icons-for-elementor'); ?></div>

                        <div class="skb-savebar">
                            <button type="submit" name="submit" id="submit" class="button button-primary skb-btn-save">
                                <span class="skb-spinner"></span>
                                <span class="skb-btn-text"><?php echo esc_html__('Save Changes', 'skyboot-custom-icons-for-elementor'); ?></span>
                            </button>
                            <span class="skb-notice-saved" role="alert" style="<?php echo isset($_GET['settings-updated']) ? '' : 'display:none;'; ?>">
                                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                <?php echo esc_html__('Your changes have been saved.', 'skyboot-custom-icons-for-elementor'); ?>
                            </span>
                        </div>
                    </form>
                </div>

                <?php // ====================================================
                        // SVG UPLOAD PANEL (PRO)
                        // ==================================================== ?>
                <div class="skb-panel" data-panel="svg" id="skb-panel-svg" role="tabpanel" aria-labelledby="skb-tab-svg">
                    <div class="skb-mi-head">
                        <div>
                            <h2>
                                <?php echo esc_html__('SVG Upload', 'skyboot-custom-icons-for-elementor'); ?>
                                <?php if (!$is_pro): ?>
                                    <span class="skb-badge-pro"><span class="dashicons dashicons-awards"
                                            aria-hidden="true"></span>PRO</span>
                                <?php endif; ?>
                            </h2>
                            <p><?php echo esc_html__('Upload SVG icons and use them in Elementor like any other set.', 'skyboot-custom-icons-for-elementor'); ?>
                            </p>
                        </div>
                    </div>

                    <div class="skb-lockwrap<?php echo $is_pro ? '' : ' is-locked'; ?>" <?php if (!$is_pro): ?>
                            data-locked="1" <?php endif; ?>>
                        <?php if (!$is_pro): ?>
                            <div class="skb-lockbadge"><span class="dashicons dashicons-clock"
                                    aria-hidden="true"></span><?php echo esc_html__('PRO feature — Coming Soon', 'skyboot-custom-icons-for-elementor'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="skb-grid-2">
                            <div class="skb-card" style="padding:24px;">
                                <div class="skb-dropzone">
                                    <span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>
                                    <p class="skb-dropzone__t">
                                        <?php echo esc_html__('Drag & drop your SVG files here', 'skyboot-custom-icons-for-elementor'); ?></p>
                                    <p class="skb-dropzone__s"><?php echo esc_html__('or', 'skyboot-custom-icons-for-elementor'); ?> <a
                                            href="#"><?php echo esc_html__('browse files', 'skyboot-custom-icons-for-elementor'); ?></a> —
                                        <?php echo esc_html__('SVG up to 1 MB each', 'skyboot-custom-icons-for-elementor'); ?></p>
                                </div>
                            </div>
                            <div class="skb-card skb-side-card">
                                <h3><?php echo esc_html__('Upload settings', 'skyboot-custom-icons-for-elementor'); ?></h3>
                                <div style="margin-bottom:16px;">
                                    <label
                                        style="font-size:13px;font-weight:700;display:block;margin-bottom:5px;"><?php echo esc_html__('Icon set name', 'skyboot-custom-icons-for-elementor'); ?></label>
                                    <input type="text" value="My Custom Icons" disabled
                                        style="width:100%;padding:9px 12px;border:1.5px solid #e8e4f3;border-radius:9px;font-size:13.5px;background:#faf8fe;color:#6e6889;">
                                    <p style="font-size:12px;color:#6e6889;margin:5px 0 0;">
                                        <?php echo esc_html__('Shown in the Elementor icon picker.', 'skyboot-custom-icons-for-elementor'); ?></p>
                                </div>
                                <?php
                                $svg_opts = array(
                                    array(__('Sanitize on upload', 'skyboot-custom-icons-for-elementor'), __('Strip scripts & unsafe tags for security.', 'skyboot-custom-icons-for-elementor')),
                                    array(__('Optimize SVG', 'skyboot-custom-icons-for-elementor'), __('Minify and clean markup automatically.', 'skyboot-custom-icons-for-elementor')),
                                    array(__('Inherit color', 'skyboot-custom-icons-for-elementor'), __('Let Elementor control the icon color.', 'skyboot-custom-icons-for-elementor')),
                                    array(__('Enable in Elementor', 'skyboot-custom-icons-for-elementor'), __('Show this set in the icon picker.', 'skyboot-custom-icons-for-elementor')),
                                );
                                foreach ($svg_opts as $opt): ?>
                                    <div class="skb-modrow skb-modrow--bordered">
                                        <div class="skb-modrow__body">
                                            <div class="skb-modrow__t"><?php echo esc_html($opt[0]); ?></div>
                                            <div class="skb-modrow__d"><?php echo esc_html($opt[1]); ?></div>
                                        </div>
                                        <div class="skb-pack__ctrl">
                                            <label class="skb-toggle">
                                                <input type="checkbox" checked disabled>
                                                <span class="skb-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div style="margin-top:16px;">
                                    <label
                                        style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;"><?php echo esc_html__('Maximum file size', 'skyboot-custom-icons-for-elementor'); ?></label>
                                    <select disabled
                                        style="width:100%;padding:9px 12px;border:1.5px solid #e8e4f3;border-radius:9px;font-size:13.5px;background:#faf8fe;color:#6e6889;">
                                        <option>1 MB</option>
                                    </select>
                                </div>
                                <div style="margin-top:14px;">
                                    <label
                                        style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;"><?php echo esc_html__('Who can upload', 'skyboot-custom-icons-for-elementor'); ?></label>
                                    <select disabled
                                        style="width:100%;padding:9px 12px;border:1.5px solid #e8e4f3;border-radius:9px;font-size:13.5px;background:#faf8fe;color:#6e6889;">
                                        <option><?php echo esc_html__('Administrators only', 'skyboot-custom-icons-for-elementor'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="skb-savebar" style="margin-top:24px;">
                            <button type="button" class="skb-btn" disabled>
                                <span class="dashicons dashicons-saved"
                                    aria-hidden="true"></span><?php echo esc_html__('Save Changes', 'skyboot-custom-icons-for-elementor'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php // ====================================================
                        // MODULES PANEL (PRO)
                        // ==================================================== ?>
                <div class="skb-panel" data-panel="modules" id="skb-panel-modules" role="tabpanel"
                    aria-labelledby="skb-tab-modules">
                    <div class="skb-mi-head">
                        <div>
                            <h2>
                                <?php echo esc_html__('Modules', 'skyboot-custom-icons-for-elementor'); ?>
                                <?php if (!$is_pro): ?>
                                    <span class="skb-badge-pro"><span class="dashicons dashicons-awards"
                                            aria-hidden="true"></span>PRO</span>
                                <?php endif; ?>
                            </h2>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: active count */
                                    wp_kses(__('Enable the Elementor features you need and configure each one. <strong>%s</strong> of 6 active.', 'skyboot-custom-icons-for-elementor'), array('strong' => array())),
                                    '4'
                                );
                                ?>
                            </p>
                        </div>
                    </div>

                    <div class="skb-lockwrap<?php echo $is_pro ? '' : ' is-locked'; ?>" <?php if (!$is_pro): ?>
                            data-locked="1" <?php endif; ?>>
                        <?php if (!$is_pro): ?>
                            <div class="skb-lockbadge"><span class="dashicons dashicons-clock"
                                    aria-hidden="true"></span><?php echo esc_html__('PRO feature — Coming Soon', 'skyboot-custom-icons-for-elementor'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="skb-card" style="padding:8px 24px;">
                            <?php
                            $modules = array(
                                array('dashicons-admin-appearance', 'Icon Animations', 'Add hover & entrance animations to any Elementor icon.', true, false, false),
                                array('dashicons-editor-code', 'Inline SVG Rendering', 'Output icons as inline SVG for full CSS control.', true, false, false),
                                array('dashicons-performance', 'Selective Loading', 'Load CSS & fonts only for the icon sets you enable.', true, true, false),
                                array('dashicons-universal-access', 'Accessibility', 'ARIA roles & labels for screen-reader-friendly icons.', true, false, false),
                                array('dashicons-layout', 'Custom Icon Box Widget', 'A dedicated Elementor widget for icon + text blocks.', false, false, false),
                                array('dashicons-update', 'Icon Library Sync', 'Keep custom icon sets in sync across a multisite network.', false, false, false),
                                array('dashicons-tag', 'Dynamic Icon Tags', 'Pull icons from custom fields & ACF dynamically.', false, false, true),
                                array('dashicons-art', 'Gradient & Duotone Icons', 'Apply gradients and duotone fills to any icon.', false, false, true),
                            );
                            foreach ($modules as $m):
                                $icon = $m[0];
                                $title = $m[1];
                                $desc = $m[2];
                                $enabled = $m[3];
                                $recommend = $m[4];
                                $is_pro_mod = $m[5];
                                ?>
                                <div class="skb-modrow skb-modrow--bordered">
                                    <div class="skb-modrow__ico">
                                        <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                                    </div>
                                    <div class="skb-modrow__body">
                                        <div class="skb-modrow__t">
                                            <?php echo esc_html($title); ?>
                                            <?php if ($recommend): ?>
                                                <span class="skb-reco"><?php echo esc_html__('Recommended', 'skyboot-custom-icons-for-elementor'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($is_pro_mod): ?>
                                                <span class="skb-badge-pro"><span class="dashicons dashicons-awards"
                                                        aria-hidden="true"></span>PRO</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="skb-modrow__d"><?php echo esc_html($desc); ?></div>
                                    </div>
                                    <div class="skb-modrow__ctrl">
                                        <?php if ($is_pro_mod): ?>
                                            <span class="skb-lock"><span class="dashicons dashicons-lock"
                                                    aria-hidden="true"></span></span>
                                        <?php else: ?>
                                            <button type="button" class="skb-modrow__settings">
                                                <span class="dashicons dashicons-admin-settings"
                                                    aria-hidden="true"></span><?php echo esc_html__('Settings', 'skyboot-custom-icons-for-elementor'); ?>
                                            </button>
                                            <label class="skb-toggle">
                                                <input type="checkbox" <?php checked($enabled); ?> disabled
                                                    aria-label="<?php echo esc_attr($title); ?>">
                                                <span class="skb-slider"></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php // ====================================================
                        // UPDATE INFO PANEL
                        // ==================================================== ?>
                <div class="skb-panel" data-panel="update" id="skb-panel-update" role="tabpanel"
                    aria-labelledby="skb-tab-update">

                    <div class="skb-update-header">
                        <div class="skb-update-header__left">
                            <div class="skb-update-header__check">
                                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            </div>
                            <div>
                                <div class="skb-update-header__title">
                                    <?php echo esc_html__("You're up to date", 'skyboot-custom-icons-for-elementor'); ?></div>
                                <div class="skb-update-header__sub">
                                    <?php
                                    printf(
                                        /* translators: %s: version number */
                                        wp_kses(__('Running <strong>v%s</strong> — the latest version.', 'skyboot-custom-icons-for-elementor'), array('strong' => array())),
                                        esc_html(SKB_CIFE_VERSION)
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="skb-update-header__right">
                            <div class="skb-update-header__auto">
                                <?php echo esc_html__('Auto-update', 'skyboot-custom-icons-for-elementor'); ?>
                                <?php 
                                $auto_updates = (array) get_site_option('auto_update' . '_plugins', array());
                                $is_auto_update = in_array(SKB_CIFE_PLUGIN_BASE, $auto_updates);
                                ?>
                                <label class="skb-toggle">
                                    <input type="checkbox" id="skb-auto-update-toggle" <?php checked($is_auto_update, true); ?>>
                                    <span class="skb-slider"></span>
                                </label>
                            </div>
                            <a href="#" class="skb-update-check-btn">
                                <span class="dashicons dashicons-update"
                                    aria-hidden="true"></span><?php echo esc_html__('Check again', 'skyboot-custom-icons-for-elementor'); ?>
                            </a>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <p class="skb-cl-label" style="margin-bottom: 0;"><?php echo esc_html__('CHANGELOG', 'skyboot-custom-icons-for-elementor'); ?></p>
                        <a href="https://wordpress.org/plugins/skyboot-custom-icons-for-elementor/#developers" target="_blank" rel="noopener" style="font-size: 13px; color: var(--skb-primary); text-decoration: none; font-weight: 500;">
                            <?php echo esc_html__('View all changes', 'skyboot-custom-icons-for-elementor'); ?> &rarr;
                        </a>
                    </div>

                    <div class="skb-log">
                        <?php echo wp_kses_post( $this->get_changelog_html() ); ?>
                    </div>

                </div>

                <div style="text-align:center; margin-top:32px; margin-bottom:16px; color:var(--skb-muted); font-size:14px;">
                    <?php esc_html_e('Enjoying Skyboot Custom Icons? 🙏 Please consider leaving us a', 'skyboot-custom-icons-for-elementor'); ?> 
                    <a href="https://wordpress.org/support/plugin/skyboot-custom-icons-for-elementor/reviews/#new-post" target="_blank" rel="noopener" style="color:#ffb900; font-weight:600; text-decoration:none;">
                        ⭐ <?php esc_html_e('5-star review', 'skyboot-custom-icons-for-elementor'); ?>
                    </a> 
                    <?php esc_html_e('on WordPress.org to help us grow!', 'skyboot-custom-icons-for-elementor'); ?>
                </div>
                <div class="skb-footer">Skyboot Custom Icons for Elementor · v<?php echo esc_html(SKB_CIFE_VERSION); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Change log entries rendered as escaped markup.
     */
    private function get_changelog_html()
    {
        $log = array(
            array(
                'ver' => '1.2.0',
                'latest' => true,
                'date' => 'August 11, 2026',
                'items' => array(
                    array('type' => 'improved', 'text' => 'Redesigned the plugin settings page for a cleaner user experience.'),
                    array('type' => 'fixed', 'text' => 'Resolved an issue where IcoFont Duotone icons were not rendering correctly in the Elementor editor preview.'),
                    array('type' => 'fixed', 'text' => 'Fixed a CSS conflict with default Elementor icon alignment for Duotone icons.'),
                    array('type' => 'fixed', 'text' => 'Prevented CSS file from loading twice when multiple IcoFont variations are used on the same page.'),
                    array('type' => 'improved', 'text' => 'Corrected the icon count description for the IcoFont library.'),
                ),
            ),
            array(
                'ver' => '1.1.1',
                'latest' => false,
                'date' => 'July 2, 2026',
                'items' => array(
                    array('type' => 'improved', 'text' => 'Tested with WordPress 7.0'),
                    array('type' => 'improved', 'text' => 'Compatibility check with the latest version of Elementor'),
                    array('type' => 'new', 'text' => 'Added devshaddam as contributor'),
                ),
            ),
            array(
                'ver' => '1.1.0',
                'latest' => false,
                'date' => 'June 25, 2025',
                'items' => array(
                    array('type' => 'new', 'text' => 'Added: 300+ Icofont Duotone new icons'),
                    array('type' => 'improved', 'text' => 'Compatibility check with the latest version of Elementor'),
                    array('type' => 'improved', 'text' => 'Code structure for maintainability and performance'),
                    array('type' => 'fixed', 'text' => 'Some minor issues fixed'),
                ),
            ),
            array(
                'ver' => '1.0.9',
                'latest' => false,
                'date' => 'April 28, 2025',
                'items' => array(
                    array('type' => 'improved', 'text' => 'Compatible with WordPress 6.8'),
                    array('type' => 'improved', 'text' => 'Compatible with the latest version of Elementor'),
                ),
            ),
            array(
                'ver' => '1.0.8',
                'latest' => false,
                'date' => 'March 2, 2024',
                'items' => array(
                    array('type' => 'improved', 'text' => 'Compatible with WordPress 6.4'),
                    array('type' => 'improved', 'text' => 'Compatible with the latest version of Elementor'),
                ),
            ),
            array(
                'ver' => '1.0.0',
                'latest' => false,
                'date' => 'June 7, 2020',
                'items' => array(
                    array('type' => 'new', 'text' => 'Initial release'),
                    array('type' => 'new', 'text' => '15 icon font packages included'),
                    array('type' => 'new', 'text' => '14,055+ icons included'),
                ),
            ),
        );

        $tag_labels = array(
            'new' => 'NEW',
            'improved' => 'IMPROVED',
            'fixed' => 'FIXED',
            'security' => 'SECURITY',
        );

        $html = '';
        foreach ($log as $entry) {
            $html .= '<div class="skb-log__item">';
            $html .= '<div class="skb-log__dot"></div>';
            $html .= '<div class="skb-log__content">';
            $html .= '<div class="skb-log__head">';
            $html .= '<div class="skb-log__head-left">';
            $html .= '<span class="skb-log__ver">Version ' . esc_html($entry['ver']) . '</span>';
            if ($entry['latest']) {
                $html .= ' <span class="skb-log__badge-latest">LATEST</span>';
            }
            $html .= '</div>';
            $html .= '<span class="skb-log__date">' . esc_html($entry['date']) . '</span>';
            $html .= '</div>';
            $html .= '<div class="skb-log__items">';
            foreach ($entry['items'] as $item) {
                $type = isset($item['type']) ? $item['type'] : 'new';
                $label = isset($tag_labels[$type]) ? $tag_labels[$type] : strtoupper($type);
                $html .= '<div class="skb-log__entry">';
                $html .= '<span class="skb-log__tag skb-log__tag--' . esc_attr($type) . '">' . esc_html($label) . '</span>';
                $html .= '<span>' . esc_html($item['text']) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>'; // .skb-log__items
            $html .= '</div>'; // .skb-log__content
            $html .= '</div>'; // .skb-log__item
        }
        return $html;
    }
}
new Skb_Cife_Settings_API_Fields();
