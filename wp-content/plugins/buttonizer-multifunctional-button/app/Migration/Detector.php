<?php
/*
 * SOFTWARE LICENSE INFORMATION
 *
 * Copyright (c) 2017 Buttonizer, all rights reserved.
 *
 * This file is part of Buttonizer
 *
 * For detailed information regarding to the licensing of
 * this software, please review the license.txt or visit:
 * https://buttonizer.pro/license/
 */

namespace Buttonizer\Migration;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Classifies what a source plugin looks like on this site.
 *
 * Every state needs a different action, and applying the wrong one breaks the
 * site — deactivating a legacy install without carrying its module over takes
 * the user's buttons off the frontend. So detection happens once, explicitly,
 * and the manager acts on the result.
 */
class Detector
{
    /** Source plugin is not installed and left no data behind. */
    const STATE_NOT_PRESENT = 'not_present';

    /** Installed, still running its own pre-Buttonizer system. */
    const STATE_LEGACY = 'legacy';

    /** Installed and connected to the Buttonizer cloud — migratable today. */
    const STATE_CLOUD_CONNECTED = 'cloud_connected';

    /** Installed on the cloud codebase but never connected. */
    const STATE_CLOUD_DISCONNECTED = 'cloud_disconnected';

    /** Already handed over to Buttonizer. */
    const STATE_ALREADY_MIGRATED = 'already_migrated';

    /**
     * Detect the state of a source plugin.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     *
     * @return string One of the STATE_* constants.
     */
    public static function detect(SourcePlugin $source): string
    {
        if (MigrationState::isMigrated($source->id())) {
            return self::STATE_ALREADY_MIGRATED;
        }

        if (!self::hasFootprint($source)) {
            return self::STATE_NOT_PRESENT;
        }

        if (self::isLegacy($source)) {
            return self::STATE_LEGACY;
        }

        if (self::isCloudConnected($source)) {
            return self::STATE_CLOUD_CONNECTED;
        }

        return self::STATE_CLOUD_DISCONNECTED;
    }

    /**
     * Does the source plugin exist on this site in any form?
     *
     * Checks for the plugin files as well as leftover options, so a site that
     * deleted the plugin but kept its data is still recognised.
     */
    public static function hasFootprint(SourcePlugin $source): bool
    {
        // Options first: they are autoloaded, while looking for the plugin
        // files costs a filesystem check on every admin request.
        if (get_option($source->settingsOption(), null) !== null) {
            return true;
        }

        if (!empty(get_option($source->tokenOption(), ''))) {
            return true;
        }

        if (self::hasLegacyOptions($source)) {
            return true;
        }

        // Installed but never opened, so it has not written anything yet
        return $source->isInstalled();
    }

    /**
     * Is the source plugin running its own legacy system?
     *
     * Trusts the plugin's own flag when it has decided, and falls back to
     * sniffing the legacy options for installs that never ran the detector.
     */
    public static function isLegacy(SourcePlugin $source): bool
    {
        $flagOption = $source->legacyFlagOption();

        if ($flagOption) {
            $flag = get_option($flagOption, null);

            if ($flag !== null) {
                return $flag === $source->legacyFlagValue();
            }
        }

        return self::hasLegacyOptions($source);
    }

    /**
     * Does the site carry any of the source plugin's legacy options?
     */
    public static function hasLegacyOptions(SourcePlugin $source): bool
    {
        foreach ($source->legacyOptions() as $option) {
            $value = get_option($option, '');

            if ($value !== '' && $value !== false && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the source plugin connected to the Buttonizer cloud?
     *
     * Requires both a finished setup and a usable token — a half-finished
     * signup is not something worth adopting.
     */
    public static function isCloudConnected(SourcePlugin $source): bool
    {
        $settings = get_option($source->settingsOption(), []);

        if (!is_array($settings) || empty($settings['finished_setup']) || empty($settings['site_id'])) {
            return false;
        }

        return !empty(self::readToken($source));
    }

    /**
     * Read the source plugin's API token.
     *
     * Mirrors ApiRequest::getApiToken(): the token normally lives in an
     * option, but can sit in a transient after a refresh.
     *
     * @return string Empty string when there is no token.
     */
    public static function readToken(SourcePlugin $source): string
    {
        $token = get_option($source->tokenOption(), '');

        if (empty($token)) {
            $token = get_transient($source->tokenOption());
        }

        return is_string($token) ? $token : '';
    }
}
