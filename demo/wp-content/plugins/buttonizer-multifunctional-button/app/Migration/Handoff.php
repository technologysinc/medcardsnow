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
 * Takes the source plugin out of the way once Buttonizer can serve its users.
 *
 * Deactivating is deliberately silent and never deletes anything: the options
 * stay in place, the backup stays in place, and the user can reactivate the
 * plugin if something went wrong.
 */
class Handoff
{
    /** Plugin was deactivated by us. */
    const RESULT_DEACTIVATED = 'deactivated';

    /** Plugin was not active to begin with. */
    const RESULT_NOT_ACTIVE = 'not_active';

    /** Current user may not deactivate plugins. */
    const RESULT_NO_PERMISSION = 'no_permission';

    /**
     * Deactivate the source plugin.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     *
     * @return array{deactivated: bool, result: string}
     */
    public static function deactivateSource(SourcePlugin $source): array
    {
        if (!self::currentUserCanManagePlugins()) {
            return ['deactivated' => false, 'result' => self::RESULT_NO_PERMISSION];
        }

        // Deep scan allowed here: we are committed to this migration, so it is
        // worth finding the plugin even under an unexpected folder name.
        $baseName = $source->resolveBaseName(true);

        if (!$baseName || !$source->isActive()) {
            return ['deactivated' => false, 'result' => self::RESULT_NOT_ACTIVE];
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // Silent: the source plugin's own deactivation hooks must not run, this
        // is a handover and not the user walking away from the product.
        deactivate_plugins($baseName, true);

        return ['deactivated' => true, 'result' => self::RESULT_DEACTIVATED];
    }

    /**
     * Send the source plugin's admin page to Buttonizer.
     *
     * Bookmarks and old menu links keep working after the plugin is gone,
     * the same way the acquired plugin already redirects its own pre-Buttonizer
     * settings page.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     */
    public static function redirectSourcePage(SourcePlugin $source): void
    {
        $pageSlug = $source->pageSlug();

        if (!$pageSlug || !isset($_GET['page']) || $_GET['page'] !== $pageSlug) {
            return;
        }

        // While the source plugin is still active it serves its own page
        if ($source->isActive()) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . \Buttonizer\Core\PluginConfig::pageSlug()));
        exit;
    }

    /**
     * May the current user activate/deactivate plugins?
     */
    public static function currentUserCanManagePlugins(): bool
    {
        return current_user_can(is_multisite() ? 'manage_options' : 'activate_plugins');
    }
}
