<?php
/**
 * Base admin class shared by all Buttonizer plugins.
 *
 * Provides common admin functionality: asset loading, admin bar, review notices,
 * caching plugin detection, role listing, and the admin template page.
 * Each plugin extends this class and adds plugin-specific menus and hooks.
 *
 * @package Buttonizer\Core\Admin
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Admin;

use Buttonizer\Core\PluginConfig;
use Buttonizer\Core\Utils\Account;
use Buttonizer\Core\Utils\ManifestParser;
use Buttonizer\Core\Utils\Editor;
use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Core\Utils\Settings;
use Buttonizer\Core\Utils\SiblingPlugins;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

abstract class BaseAdmin
{
    private static $adminStyles = ["dashicons", "common", "admin-menu", "dashboard", "nav-menus", "site-icon", "l10n"];

    /**
     * Register shared admin hooks.
     *
     * Subclasses should call parent::__construct() to register these,
     * then add their own plugin-specific hooks.
     */
    public function __construct()
    {
        // Enqueue admin assets on our page
        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);

        // Enable ES modules for our scripts
        add_filter('script_loader_tag', [$this, 'addModuleToScriptTag'], 10, 3);

        // Review notice on WP admin pages
        if (!defined('DISABLE_NAG_NOTICES') || (defined('DISABLE_NAG_NOTICES') && !DISABLE_NAG_NOTICES)) {
            add_action('admin_notices', [$this, 'generateAdminNotice']);
        }
    }

    /**
     * Enqueue admin scripts and styles for the plugin dashboard page.
     */
    public function adminAssets()
    {
        $pageSlug = PluginConfig::pageSlug();

        // Only add our assets to our own admin page
        if (!isset($_GET['page']) || $_GET['page'] !== $pageSlug) return;

        // Require media manager
        wp_enqueue_media();

        // Get latest files from Vite manifest
        $manifest = new ManifestParser(
            PluginConfig::dir() . "/assets/app/manifest.json",
            plugins_url('assets/app', PluginConfig::pluginFile())
        );

        // Get dashboard scripts
        $script = $manifest->getEntrypoint("index.html", false);

        // Get dashboard style
        $styles = $manifest->getStyles("index.html", false);

        // Get imports
        $imports = $manifest->getImports("index.html", false);

        // Add script
        wp_register_script('buttonizer_admin_js', $script['url'], [], md5(PluginConfig::version()), true);

        // Deregister WP forms style (conflicts with our UI)
        wp_deregister_style('forms');

        // Build localize data
        $data = $this->getLocalizeData();

        // Localize script
        wp_localize_script('buttonizer_admin_js', 'buttonizer_admin', $data);

        wp_enqueue_script('buttonizer_admin_js');

        // Register all script imports
        foreach ($imports as $key => $importScript) {
            wp_register_script('buttonizer_admin_js_' . $key, $importScript['url'], ["buttonizer_admin_js"], md5(PluginConfig::version()), true);
            wp_enqueue_script('buttonizer_admin_js_' . $key);
        }

        // Register all styles
        foreach ($styles as $key => $style) {
            wp_register_style('buttonizer_admin_css_' . $key, $style['url'], self::$adminStyles, md5(PluginConfig::version()));
            wp_enqueue_style('buttonizer_admin_css_' . $key);
        }
    }

    /**
     * Build the data array for wp_localize_script.
     *
     * Subclasses can override this to add/modify data.
     *
     * @return array
     */
    protected function getLocalizeData(): array
    {
        $current_user = wp_get_current_user();
        $current_user = $current_user->data;

        return [
            'admin' => admin_url('admin.php'),
            'isAdmin' => \is_user_logged_in() && current_user_can(is_multisite() ? 'manage_options' : 'activate_plugins'),
            'baseUrl' => get_site_url('/'),
            'adminBase' => substr(admin_url(), 0, -1),
            'assetsPath' => plugins_url('/assets', PluginConfig::pluginFile()),
            'api' => get_rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'isPlain' => get_option('permalink_structure') === "",
            'version' => PluginConfig::version(),
            'locale' => Editor::getEditorLanguage(),
            'actionLock' => $this->getActionLock(),
            'requestReview' => $this->requestForReview(),
            'displayCachingPluginBanner' => $this->cachingPluginDetected(),
            'beforeMigrate' => $this->getBeforeMigrate(),
            'hasMigrated' => Settings::getSetting("has_migrated", false),
            'hasLicense' => Account::getSetting("site_licensed", false),
            'account' => Account::getData(),
            'security' => wp_create_nonce("save_buttonizer"),
            'plugin_slug' => PluginConfig::name(),
            'settings' => [
                'adminTopBarButtonEnabled' => Settings::getSetting("admin_top_bar_show_button", true),
                'canSendErrors' => Settings::getSetting("can_send_errors", false),
                'accessRoles' => Settings::getSetting("additional_permissions", []),
                'googleAnalytics' => Settings::getSetting("google_analytics", null),
                'waitUntilConsent' => Settings::getSetting("wait_until_consent", false)
            ],
            'available_roles' => $this->getRoles(),
            'site' => [
                'domain' => wp_parse_url(get_site_url(), PHP_URL_HOST),
                'name' => get_bloginfo('name'),
                'user' => [
                    "email" => $current_user->user_email,
                    'firstName' => $current_user->first_name ?? $current_user->display_name ?? $current_user->user_nicename ?? "",
                    'lastName' => $current_user->last_name ?? ""
                ]
            ]
        ];
    }

    /**
     * Add type="module" to our script tags for Vite ESM output.
     *
     * @param string $tag    Script HTML tag.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     *
     * @return string Modified script tag.
     */
    public function addModuleToScriptTag($tag, $handle, $src)
    {
        if (strpos($handle, 'buttonizer_admin_js') === 0) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }

        return $tag;
    }

    /**
     * Render the admin dashboard page template.
     */
    public function page()
    {
        AdminTemplate::render();
    }

    /**
     * Lock the screen to a specific action.
     *
     * Subclasses can override to add plugin-specific locks (e.g. legacy migration).
     *
     * @return string "no-lock", "setup", or "migration"
     */
    public function getActionLock(): string
    {
        // Set up Buttonizer (also checks sibling plugins)
        if (!$this->isButtonizerConnected()) {
            return "setup";
        }

        return "no-lock";
    }

    /**
     * Get migration status data.
     *
     * Override in plugins that have migration logic.
     *
     * @return mixed null by default, or migration data array.
     */
    public function getBeforeMigrate()
    {
        return null;
    }

    /**
     * Check if Buttonizer signup has been completed.
     * Tries to copy the connection from a sibling Buttonizer plugin if not.
     *
     * @return bool
     */
    protected function isButtonizerConnected(): bool
    {
        if (Settings::getSetting("finished_setup", false) !== false) {
            return true;
        }

        // Try to copy connection from a sibling Buttonizer plugin
        if (SiblingPlugins::copyConnectionFromSibling()) {
            return true;
        }

        return false;
    }

    /**
     * Add items to the WordPress admin bar.
     *
     * Called statically from init.php via add_action('admin_bar_menu', ...).
     *
     * @param \WP_Admin_Bar $admin_bar
     */
    public static function wordpressAdminBar($admin_bar)
    {
        $pageSlug = PluginConfig::pageSlug();
        $textDomain = PluginConfig::textDomain();

        // Only show to admins and when enabled
        if (
            !PermissionCheck::hasPermission() ||
            filter_var(Settings::getSetting('admin_top_bar_show_button', true), FILTER_VALIDATE_BOOLEAN, ['options' => ['default' => false]]) === false
        ) {
            return;
        }

        $admin_bar->add_menu(array(
            'id' => PluginConfig::name(),
            'title' => '<img src="' . plugins_url('/assets/images/wp-icon.png', PluginConfig::pluginFile()) . '" style="vertical-align: text-bottom; opacity: 0.7; display: inline-block;" />',
            'href' => admin_url() . 'admin.php?page=' . $pageSlug . '#/',
            'meta' => [],
        ));

        $admin_bar->add_menu(array(
            'id' => PluginConfig::name() . '_buttons',
            'parent' => PluginConfig::name(),
            'title' => __('Edit buttons', $textDomain),
            'href' => admin_url() . 'admin.php?page=' . $pageSlug . '#/editor',
            'meta' => array(),
        ));

        $admin_bar->add_menu(array(
            'id' => PluginConfig::name() . '_settings',
            'parent' => PluginConfig::name(),
            'title' => __('Settings', $textDomain),
            'href' => admin_url() . 'admin.php?page=' . $pageSlug . '#/settings',
            'meta' => array(),
        ));

        $admin_bar->add_menu(array(
            'id' => PluginConfig::name() . '_support',
            'parent' => PluginConfig::name(),
            'title' => __('I need support', $textDomain),
            'href' => admin_url() . 'admin.php?page=' . $pageSlug . '#/support',
            'meta' => array(),
        ));

        $admin_bar->add_menu(array(
            'id' => PluginConfig::name() . '_knowledgebase',
            'parent' => PluginConfig::name(),
            'title' => __('Knowledge base', $textDomain),
            'href' => "https://r.buttonizer.io/support/knowledgebase",
            'meta' => [
                "target" => "_blank",
                "title" => __('Find out everything you need to know about Buttonizer', $textDomain)
            ],
        ));
    }

    /**
     * Get available WordPress roles for the permission setting.
     *
     * @return array
     */
    protected function getRoles()
    {
        $roles = [];

        foreach (wp_roles()->get_names() as $id => $role) {
            $roles[] = [
                'id'    => $id,
                'name' => $role
            ];
        }

        return $roles;
    }

    /**
     * Decide if we should ask this user for a review.
     *
     * @return bool
     */
    public function requestForReview()
    {
        try {
            if (Settings::getSetting("review_marked_as_done", false) === true) {
                return false;
            }

            $currentTime = new \DateTime();

            if (Settings::getSetting("review_reminding_since", null) !== null) {
                $remindFrom = Settings::getSetting("review_reminding_since", $currentTime);
                $difference = ($currentTime)->diff($remindFrom);

                if ($difference->days <= 31) {
                    return false;
                }
            }

            /** @var \DateTime */
            $installDate = Settings::getSetting("installed_at", $currentTime);
            $difference = ($currentTime)->diff($installDate);

            return $difference->days >= 9;
        } catch (\Error $e) {
            return false;
        }
    }

    /**
     * Detect if a caching plugin is active.
     *
     * @return bool
     */
    public function cachingPluginDetected()
    {
        try {
            if (Settings::getSetting("dismissed_caching_plugin_banner", false) === true) {
                return false;
            }

            $pluginList = array_map('dirname', get_option('active_plugins'));

            if (
                in_array('litespeed-cache', $pluginList) ||
                in_array('w3-total-cache', $pluginList) ||
                in_array('wp-super-cache', $pluginList) ||
                in_array('wp-fastest-cache', $pluginList) ||
                in_array('wp-optimize', $pluginList)
            ) {
                return true;
            }

            return false;
        } catch (\Error $e) {
            return false;
        }
    }

    /**
     * Show admin notice requesting a review.
     */
    public function generateAdminNotice()
    {
        if (!PermissionCheck::hasPermission() || !$this->requestForReview()) return;

        $currentScreen = get_current_screen();
        $adminPages = ["dashboard", "plugins", "plugin-install", "themes", "edit-page", "edit-post", "options-general"];

        if (!$currentScreen || ($currentScreen && !in_array($currentScreen->id, $adminPages))) return;

        $name = PluginConfig::name();
        $reviewUrl = PluginConfig::reviewUrl();
        $noticeId = PluginConfig::noticeId();
        $jsFunction = PluginConfig::noticeJsFunction();
        $productName = PluginConfig::get('product_name', 'Buttonizer');

        $nonce = wp_create_nonce("wp_rest");
        $endPoint = get_rest_url() . $name . '/settings?nonce=' . $nonce;

        if (get_option('permalink_structure') === "") {
            $endPoint = substr(get_rest_url(), 0, -1) . urlencode('/' . $name . '/settings') . '&nonce=' . $nonce;
        }

        echo '
        <div id="' . esc_attr($noticeId) . '" class="notice notice-info">
            <p>Hey there! You\'re currently using <b>' . esc_html($productName) . '</b> for a while now and we really hope you like it! Would you like to review us on WordPress and share your experience? This way you support us developing new features and spread the love!</p>

            <p><a href="' . esc_url($reviewUrl) . '" target="_blank" onClick="' . esc_attr($jsFunction) . '()" class="button button-primary"><span class="dashicons dashicons-star-filled"></span> Review plugin</a>&nbsp;&nbsp;<a href="javascript:void(0)" onClick="' . esc_attr($jsFunction) . '()" class="button">Dismiss message</a>&nbsp;&nbsp;or&nbsp;&nbsp;<a href="https://r.buttonizer.io/feedback?utm_source=wp-plugin-request-review-btn" target="_blank">send us feedback</a></p>
        </div>

        <script>
            function ' . $jsFunction . '() {
                const notice = document.querySelector("#' . esc_js($noticeId) . '");
                notice.style.height  = (notice.clientHeight - 2) + "px";
                notice.style.transition = "all 150ms ease-in-out";

                setTimeout(() => {
                    notice.style.opacity = 0;
                    notice.style.height = "0px";
                    notice.style.margin = "0px";
                }, 150)
                setTimeout(() => {
                    notice.remove();
                }, 500)

                fetch("' . esc_js($endPoint) . '", {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      "X-WP-Nonce": "' . esc_js($nonce) . '",
                    },
                    body: JSON.stringify({
                        data: {
                            markAsReviewed: true
                        }
                    }),
                  })
            }
        </script>

        ';
    }
}

