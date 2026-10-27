<?php
/**
 * Plugin settings management.
 *
 * Reads and writes settings from wp_options using the plugin name prefix
 * provided by PluginConfig.
 *
 * @package Buttonizer\Core\Utils
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Utils;

use Buttonizer\Core\PluginConfig;

class Settings
{
    private static $settings = null;
    private static $hasChanges = false;

    /**
     * Initialize settings
     *
     * @return void
     */
    private static function initialize()
    {
        $name = PluginConfig::name();

        // Register options
        register_setting($name, $name . "_settings", [
            'type' => 'array',
            'sanitize_callback' => function ($value) {
                return is_array($value) ? $value : [];
            }
        ]);

        // Load settings
        self::$settings = get_option($name . "_settings", []);
    }

    /**
     * Load settings
     *
     * @return array
     */
    public static function getSettings(): array
    {
        // Initialize
        if (is_null(self::$settings)) {
            self::initialize();
        }

        return self::$settings;
    }

    /**
     * Reset all settings
     *
     * @return void
     */
    public static function empty()
    {
        self::$settings = [];
    }

    /**
     * Get a setting
     *
     * @param string $key Setting key
     * @param mixed $default Default setting value
     *
     * @return mixed
     */
    public static function getSetting(string $key, $default = null)
    {
        // Initialize
        if (is_null(self::$settings)) {
            self::initialize();
        }

        return self::isset($key) ? self::$settings[$key] : $default;
    }

    /**
     * Is a setting set?
     *
     * @param string $key Setting key
     *
     * @return bool Returns if the setting was set
     */
    public static function isset(string $key): bool
    {
        // Initialize
        if (is_null(self::$settings)) {
            self::initialize();
        }

        return isset(self::$settings[$key]);
    }

    /**
     * Set setting
     *
     * @param string $key
     * @param mixed $value
     * @param bool $save
     *
     * @return boolean
     */
    public static function setSetting(string $key, $value, bool $save = false): bool
    {
        // Initialize
        if (is_null(self::$settings)) {
            self::initialize();
        }

        self::$hasChanges = true;
        self::$settings[$key] = $value;

        // Save setting
        if ($save) {
            self::saveUpdatedSettings();
        }

        return true;
    }

    /**
     * Delete setting
     *
     * @param string $key Setting key
     *
     * @return void
     */
    public static function deleteSetting(string $key)
    {
        // Initialize
        if (is_null(self::$settings)) {
            self::initialize();
        }

        unset(self::$settings[$key]);
        self::$hasChanges = true;
    }

    /**
     * Save changes
     *
     * @return bool
     */
    public static function saveUpdatedSettings(): bool
    {
        // No changes
        if (is_null(self::$settings) || !self::$hasChanges) {
            return true;
        }

        // Save setting
        update_option(PluginConfig::name() . '_settings', self::$settings);

        return true;
    }
}

