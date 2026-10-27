<?php
/**
 * Central configuration object for shared Buttonizer plugin code.
 *
 * Each plugin initializes this with its own constants early in the boot process.
 * All shared code reads from PluginConfig instead of plugin-specific constants.
 *
 * @package Core
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core;

class PluginConfig
{
    private static ?array $config = null;

    /**
     * Initialize the plugin configuration.
     *
     * Must be called once during plugin bootstrap, before any shared code is used.
     *
     * @param array $config Configuration array with plugin-specific values.
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get a configuration value by key.
     *
     * @param string $key     Configuration key.
     * @param mixed  $default Default value if key is not set.
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Plugin name / option prefix (e.g. "buttonizer", "bz_contact_button").
     */
    public static function name(): string
    {
        return self::$config['name'];
    }

    /**
     * Plugin root directory path.
     */
    public static function dir(): string
    {
        return self::$config['dir'];
    }

    /**
     * Plugin app/ directory path.
     */
    public static function appDir(): string
    {
        return self::$config['app_dir'];
    }

    /**
     * Plugin slug (directory basename).
     */
    public static function slug(): string
    {
        return self::$config['slug'];
    }

    /**
     * Plugin version string.
     */
    public static function version(): string
    {
        return self::$config['version'];
    }

    /**
     * Main plugin file path.
     */
    public static function pluginFile(): string
    {
        return self::$config['plugin_file'];
    }

    /**
     * Plugin basename (e.g. "buttonizer-for-wordpress/buttonizer.php").
     */
    public static function baseName(): string
    {
        return self::$config['base_name'];
    }

    /**
     * Buttonizer API base URI (e.g. "https://api.buttonizer.io").
     */
    public static function apiUri(): string
    {
        return self::$config['api_uri'];
    }

    /**
     * Sibling plugins configuration for connection sharing.
     *
     * Each entry maps a sibling plugin name to its option keys:
     *   [
     *       'plugin_name' => [
     *           'token_option'    => 'plugin_name_site_connection',
     *           'settings_option' => 'plugin_name_settings',
     *           'account_option'  => 'plugin_name_account',
     *       ],
     *   ]
     *
     * @return array
     */
    public static function siblings(): array
    {
        return self::$config['siblings'] ?? [];
    }

    /**
     * WordPress admin page slug (e.g. "Buttonizer", "bz_button_contact").
     */
    public static function pageSlug(): string
    {
        return self::$config['page_slug'];
    }

    /**
     * WordPress text domain for translations.
     */
    public static function textDomain(): string
    {
        return self::$config['text_domain'];
    }

    /**
     * WordPress.org review URL for the plugin.
     */
    public static function reviewUrl(): string
    {
        return self::$config['review_url'] ?? '';
    }

    /**
     * HTML notice element ID for the admin review notice.
     */
    public static function noticeId(): string
    {
        return self::$config['notice_id'] ?? 'buttonizer-admin-notice';
    }

    /**
     * JS function name for the admin notice dismiss handler.
     */
    public static function noticeJsFunction(): string
    {
        return self::$config['notice_js_function'] ?? 'buttonizerAdminNotice';
    }

    /**
     * Referral slug for community/knowledge base links.
     */
    public static function referralSlug(): string
    {
        return self::$config['referral_slug'] ?? '';
    }
}
