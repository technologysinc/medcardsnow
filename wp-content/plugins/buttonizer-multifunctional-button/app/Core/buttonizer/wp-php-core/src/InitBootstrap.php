<?php
/**
 * Shared bootstrap logic for all Buttonizer plugins.
 *
 * Registers common WordPress hooks: language detection, page redirect,
 * page data injection, embed script, widget shortcode, admin bar, and REST API.
 *
 * Each plugin's init.php calls InitBootstrap::boot() with its own Admin and Api
 * class names, then adds any plugin-specific hooks afterwards.
 *
 * @package Core
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core;

use Buttonizer\Core\Utils\Settings;
use Buttonizer\Core\Utils\PermissionCheck;

class InitBootstrap
{
    /**
     * Get the current page language.
     *
     * Supports Polylang, Weglot, WPML, and falls back to WordPress site language.
     *
     * @return string Language code (e.g. "en", "nl").
     */
    public static function getCurrentLanguage(): string
    {
        // Polylang
        if (function_exists("pll_current_language")) {
            return pll_current_language("slug");
        }

        // Weglot
        if (function_exists("weglot_get_current_language")) {
            return weglot_get_current_language();
        }

        // WPML
        $currentLanguage = apply_filters('wpml_current_language', NULL);

        // Try to fall back on current language
        if (!$currentLanguage) return substr(get_bloginfo('language'), 0, 2);

        return $currentLanguage;
    }

    /**
     * Redirect to the translated version of a page.
     *
     * Uses the plugin name from PluginConfig for the redirect query parameter
     * (e.g. `is_buttonizer_redirect`, `is_bz_contact_button_redirect`).
     */
    public static function redirectToPage(): void
    {
        $name = PluginConfig::name();

        // Validate params
        if (!isset($_GET['page_id']) || !is_numeric($_GET['page_id']) || !isset($_GET['is_' . $name . '_redirect'])) {
            return;
        }

        $id = $_GET['page_id'];
        $page = null;

        // Polylang
        if (function_exists("pll_get_post")) {
            $page = pll_get_post($id);
        }

        // Check WPML translated page
        if (!$page && $wpmlObject = apply_filters('wpml_object_id', $id)) {
            $page = $wpmlObject;
        }

        // Redirect if post or page was found
        if ($pageUrl = get_the_permalink($page ?? $id)) {
            // Check if the page was redirected
            if (!wp_redirect($pageUrl, 302, 'Buttonizer')) {
                // Make sure to receive a safe redirect URL
                $redirectUrl = wp_validate_redirect(wp_sanitize_redirect($pageUrl), false);

                // Only redirect if it's a safe and allowed host
                if ($redirectUrl) {
                    header("Location: " . $redirectUrl, true, 302);
                }

                exit("A redirect was cancelled.");
            }
            exit;
        }
    }

    /**
     * Inject page data into wp_head for the Buttonizer embed script.
     *
     * Outputs language, and optionally page ID, categories, front page status,
     * 404 status, and user roles when `include_page_data` setting is enabled.
     */
    public static function injectPageData(): void
    {
        if (!Settings::getSetting("site_id")) return;

        // Get current page language
        $pageData = [
            "language" => self::getCurrentLanguage()
        ];

        // Add Buttonizer page data
        if (Settings::getSetting("include_page_data", false)) {
            // Get page categories
            $pageCategories = array_map(function ($category) {
                return $category->cat_ID;
            }, get_the_category());

            // Collect page data
            $pageData = array_merge([
                "page_id" => get_the_ID(),
                "categories" => $pageCategories,
                "is_frontpage" => is_front_page(),
                "is_404" => is_404(),
                "user_roles" => PermissionCheck::getUserRoles()
            ], $pageData);
        }

        // Define page data
        $buttonizerData = "if(!window._buttonizer) { window._buttonizer = {}; };var _buttonizer_page_data = " . wp_json_encode($pageData) . ";window._buttonizer.data = { ..._buttonizer_page_data, ...window._buttonizer.data };";

        echo '<script type="text/javascript">' . $buttonizerData . '</script>';
    }

    /**
     * Inject the Buttonizer CDN embed script into wp_footer.
     *
     * Respects the `wait_until_consent` GDPR setting.
     */
    public static function injectEmbedScript(): void
    {
        if (!Settings::getSetting("site_id")) return;

        // Buttonizer integration script
        $buttonizerSnippet = "(function(n,t,c,d){if(t.getElementById(d)){return}var o=t.createElement('script');o.id=d;(o.async=!0),(o.src='https://cdn.buttonizer.io/embed.js'),(o.onload=function(){window.Buttonizer?window.Buttonizer.init(c):window.addEventListener('buttonizer_script_loaded',()=>window.Buttonizer.init(c))}),t.head.appendChild(o)})(window,document,'" . Settings::getSetting("site_id") . "','buttonizer_script')";

        // GDPR Compliance check
        if (Settings::getSetting("wait_until_consent", false)) {
            $buttonizerSnippet = "// Buttonizer snippet container
function enableButtonizer() {" . $buttonizerSnippet . "};

// Buttonizer consent given, load content
if(window.buttonizer_consent_given){ enableButtonizer(); }";
        }

        echo '<script type="text/javascript">' . $buttonizerSnippet . '</script>';
    }

    /**
     * Validate a UUID v4 string.
     *
     * @param string $uuid The string to validate.
     *
     * @return bool True if valid UUID v4.
     */
    public static function isValidUUID(string $uuid): bool
    {
        $regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

        return (bool) preg_match($regex, $uuid);
    }

    /**
     * Buttonizer inline widget shortcode handler.
     *
     * Usage: [buttonizer id="uuid-here"]
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string Widget HTML or empty string.
     */
    public static function widgetShortcode($atts): string
    {
        // Get attributes
        $atts = shortcode_atts(
            array(
                'id' => '',
            ),
            $atts
        );

        // Make sure the ID exists and is a valid UUID
        if (!isset($atts['id']) || !is_string($atts['id']) || !self::isValidUUID($atts['id'])) return "";

        return '<div class="buttonizer-inline-widget" data-buttonizer-widget-id="' . esc_attr($atts['id']) . '"></div>';
    }

    /**
     * Boot all shared WordPress hooks for the plugin.
     *
     * Call this from the plugin's init.php after the legacy check (if any).
     *
     * @param string $adminClass Fully qualified class name for the plugin's Admin class.
     * @param string $apiClass   Fully qualified class name for the plugin's Api class.
     */
    public static function boot(string $adminClass, string $apiClass): void
    {
        // Admin dashboard
        if (is_admin()) {
            new $adminClass();
        }

        // Redirect to page in correct language
        add_action('template_redirect', [self::class, 'redirectToPage'], 0);

        // Page data injection (wp_head)
        add_action('wp_head', [self::class, 'injectPageData'], 10);

        // Embed script injection (wp_footer)
        add_action('wp_footer', [self::class, 'injectEmbedScript'], 11);

        // Widget shortcode
        add_action('init', function () {
            if (!shortcode_exists("buttonizer")) {
                add_shortcode('buttonizer', [self::class, 'widgetShortcode']);
            }
        });

        // Admin bar
        add_action('admin_bar_menu', function ($bar) use ($adminClass) {
            $adminClass::wordpressAdminBar($bar);
        }, 100);

        // REST API endpoints
        add_action('rest_api_init', function () use ($apiClass) {
            new $apiClass();
        });
    }
}

