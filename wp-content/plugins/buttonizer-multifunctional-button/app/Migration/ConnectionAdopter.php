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

use Buttonizer\Core\Utils\ApiRequest;
use Buttonizer\Core\Utils\Settings;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Moves a source plugin's cloud connection into Buttonizer.
 *
 * This is what makes the handover invisible: the user was already signed in
 * through the source plugin, and Buttonizer picks up the same token, the same
 * site and the same account instead of asking them to connect again.
 *
 * Core's SiblingPlugins::copyConnectionFromSibling() does roughly the same,
 * but it targets whichever sibling happens to answer first and copies only
 * five settings — everything else the user configured (GDPR consent, dashboard
 * permissions, admin bar) is silently dropped. This adopts one specific source
 * and carries those settings over too.
 */
class ConnectionAdopter
{
    /** Connection was copied from the source plugin. */
    const RESULT_ADOPTED = 'connection_adopted';

    /** Buttonizer was already connected; the target's connection wins. */
    const RESULT_TARGET_ALREADY_CONNECTED = 'target_already_connected';

    /** Nothing usable to adopt. */
    const RESULT_SOURCE_NOT_CONNECTED = 'source_not_connected';

    /** The source was never connected, so there was nothing to move. */
    const RESULT_NOTHING_TO_ADOPT = 'nothing_to_adopt';

    /** The copy did not produce a usable connection. */
    const RESULT_FAILED = 'adoption_failed';

    /**
     * Adopt the source plugin's connection.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     *
     * @return array{adopted: bool, result: string}
     */
    public static function adopt(SourcePlugin $source): array
    {
        $token          = Detector::readToken($source);
        $sourceSettings = get_option($source->settingsOption(), []);

        // A site id is as required as the token: without it nothing renders on
        // the frontend, and writing finished_setup anyway would leave
        // Buttonizer believing it is set up when it is not.
        if (
            empty($token) ||
            !is_array($sourceSettings) ||
            empty($sourceSettings['finished_setup']) ||
            empty($sourceSettings['site_id'])
        ) {
            return self::result(false, self::RESULT_SOURCE_NOT_CONNECTED);
        }

        // Buttonizer already has its own connection. Two sites on one install is
        // rare (a site is a domain), and the agreed rule is that the target
        // keeps its account — so leave it alone and continue the handover.
        if (self::isTargetConnected()) {
            return self::result(true, self::RESULT_TARGET_ALREADY_CONNECTED);
        }

        ApiRequest::saveApiToken($token);

        Settings::setSetting('finished_setup', true);
        Settings::setSetting('installed_at', $sourceSettings['installed_at'] ?? new \DateTime('now'));
        Settings::setSetting('last_synced_at', new \DateTime('now'));
        Settings::setSetting('site_id', $sourceSettings['site_id']);

        // Carry over what the user configured in the source plugin. Skipping
        // this is how wait_until_consent gets lost, which silently turns a
        // GDPR-compliant install into a non-compliant one.
        foreach ($source->settingsToCarryOver() as $key) {
            if (array_key_exists($key, $sourceSettings)) {
                Settings::setSetting($key, $sourceSettings[$key]);
            }
        }

        Settings::saveUpdatedSettings();

        // Account data, so the dashboard opens signed in instead of empty
        $account = get_option($source->accountOption(), []);

        if (!empty($account)) {
            update_option(BUTTONIZER_NAME . '_account', $account);
        }

        if (!self::isTargetConnected()) {
            return self::result(false, self::RESULT_FAILED);
        }

        return self::result(true, self::RESULT_ADOPTED);
    }

    /**
     * Is Buttonizer itself connected and usable?
     *
     * Deliberately strict: this is the gate that decides whether the source
     * plugin may be deactivated, so a missing token or site id must read as
     * "not connected".
     */
    public static function isTargetConnected(): bool
    {
        if (Settings::getSetting('finished_setup', false) === false) {
            return false;
        }

        if (empty(Settings::getSetting('site_id', null))) {
            return false;
        }

        return ApiRequest::getApiToken() !== false;
    }

    /**
     * @return array{adopted: bool, result: string}
     */
    private static function result(bool $adopted, string $result): array
    {
        return [
            'adopted' => $adopted,
            'result'  => $result,
        ];
    }
}
