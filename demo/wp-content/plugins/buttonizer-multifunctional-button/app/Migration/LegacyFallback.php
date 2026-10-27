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
 * The way back to an absorbed plugin's old system.
 *
 * The acquired plugin offers this to anyone who has not signed up yet, and a
 * user who moves to Buttonizer should not lose it. Buttonizer offers the same
 * thing, in the same place — the signup screen — under one condition the
 * original could not check: that there is actually something to go back to.
 *
 * A site with no old buttons would land in an empty screen of a deprecated
 * system with nothing in it. That is not a way back, it is a way nowhere, so
 * those users are never offered it.
 *
 * It is offered even while the old system is already running, because that is
 * the screen someone reaches from the module's own "Try now" — one click away
 * from signing up and taking their buttons off the site. Hiding the way back
 * there, on the grounds that they have not left yet, hides it exactly when it
 * is worth reading. Following it from that state simply returns them to their
 * own screens, which is what "not ready yet" has always meant.
 */
class LegacyFallback
{
    /**
     * The old system this site could return to, if any.
     */
    public static function available(): ?SourcePlugin
    {
        foreach (SourcePlugins::all() as $source) {
            if (self::isAvailableFor($source)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Put the old system back and say where the user should land.
     *
     * Only the decision is reversed. The old options were never touched, so
     * the buttons come back exactly as the user left them.
     *
     * @return string|null Admin URL of the restored system, null when there is
     *                     nothing to restore.
     */
    public static function restore(): ?string
    {
        $source = self::available();

        if (!$source) {
            return null;
        }

        // Forget the move. This is what stops Buttonizer from serving the old
        // system: leaving the cloud is not enough on its own, because leaving
        // it by accident should not change what renders on someone's site.
        //
        // From here on Buttonizer serves that system itself, and says so: a
        // site that came across as a cloud connection was never marked as
        // embedded, and the loader only serves what is.
        MigrationState::set($source->id(), [
            'moved_to_cloud' => false,
            'result'         => MigrationManager::RESULT_LEGACY_MODULE,
        ]);

        // A site that came in through the cloud never had this flag set, and
        // the old system reads it on every load.
        if ($source->legacyFlagOption()) {
            update_option($source->legacyFlagOption(), $source->legacyFlagValue());
        }

        return admin_url('admin.php?page=' . $source->modulePageSlug());
    }

    /**
     * Can this site go back to that plugin's old system?
     */
    private static function isAvailableFor(SourcePlugin $source): bool
    {
        // Buttonizer carries the code and this site actually came from there.
        // A plain Buttonizer install ships the module like every other copy,
        // which is why the module alone proves nothing.
        if (!$source->hasModule() || !MigrationState::isMigrated($source->id())) {
            return false;
        }

        // The plugin itself is back and serving its own screens.
        //
        // Deliberately about the plugin's own active flag, not isModuleLoaded():
        // Buttonizer's own ModuleLoader requires the module on every request
        // while it is embedded, including this one, so that check is true here
        // regardless of whether the source plugin is active. Going by it would
        // hide the way back on the one screen it has to stay reachable from.
        if ($source->isActive()) {
            return false;
        }

        return true;
    }
}
