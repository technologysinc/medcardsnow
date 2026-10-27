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

use Buttonizer\Core\PluginConfig;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Serves an absorbed plugin's own code from inside Buttonizer.
 *
 * Sites still running the acquired plugin's pre-Buttonizer system keep their
 * buttons, their settings and their admin screens exactly as they were: the
 * code is copied under modules/ untouched, and Buttonizer loads it in place of
 * the plugin that used to.
 *
 * Runs at plugin boot, not on admin_init: the module registers hooks on
 * plugins_loaded, wp_footer and admin_menu, so it has to be in place before
 * WordPress gets there.
 */
class ModuleLoader
{
    /**
     * Marks a visit to Buttonizer's own page as the point of the click.
     *
     * Without it there is no way to tell "the user asked for Buttonizer" from
     * "this link was built before the module took over", and the two need
     * opposite answers.
     */
    const OWN_DASHBOARD_PARAM = 'bz_signup';

    /**
     * @var bool Whether a module is being served this request.
     */
    private static $serving = false;

    /**
     * @var SourcePlugin|null The module being served this request, if any.
     */
    private static $servingSource = null;

    /**
     * @var bool Whether Buttonizer keeps itself out of the admin sidebar.
     */
    private static $hostMenuHidden = false;

    /**
     * Load every module this site still needs.
     */
    public static function boot(): void
    {
        foreach (SourcePlugins::all() as $source) {
            if (!self::shouldLoad($source)) {
                continue;
            }

            // Decided before the module registers anything, because its
            // adapter needs the answer while wiring itself up.
            self::decideOwnMenu();

            require_once $source->modulePath();

            self::$serving = true;
            self::$servingSource = $source;

            // Everything the copied code cannot know about Buttonizer lives in
            // the adapter, so the copy itself stays untouched.
            $adapter = $source->moduleAdapter();

            if ($adapter) {
                $adapter::attach();
            }
        }

        // Only when the module took Buttonizer's own place in the sidebar:
        // that is exactly when a link built before this request — a redirect
        // after signup, a bookmark, "Open Buttonizer" — still points at
        // Buttonizer's own page. That page is never unregistered, so it would
        // render its own connect screen right over the module the user is
        // supposed to still be looking at.
        if (self::$hostMenuHidden) {
            add_action('admin_init', [self::class, 'redirectOwnDashboard']);
        }
    }

    /**
     * Send a direct hit on Buttonizer's own page to the module instead.
     *
     * Runs on admin_init, ahead of admin_menu: the menu is irrelevant here,
     * this is about which page callback actually renders.
     */
    public static function redirectOwnDashboard(): void
    {
        if (!self::$servingSource || !isset($_GET['page']) || $_GET['page'] !== PluginConfig::pageSlug()) {
            return;
        }

        // The user asked to be here. "Try now" is the one way to reach the
        // signup screen while the old system is still being served, so sending
        // it back to the module would make signing up impossible.
        if (isset($_GET[self::OWN_DASHBOARD_PARAM])) {
            return;
        }

        $moduleSlug = self::$servingSource->modulePageSlug();

        if (!$moduleSlug) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $moduleSlug));
        exit;
    }

    /**
     * Is Buttonizer serving an absorbed plugin's own system right now?
     */
    public static function isServingModule(): bool
    {
        return self::$serving;
    }

    /**
     * Leave the absorbed plugin's menu as the only one in the sidebar.
     *
     * The point of the module is that nothing changed for this user, and
     * before the migration their sidebar had exactly one entry. Buttonizer's
     * own page stays registered and reachable — by direct URL and from the
     * "Open Buttonizer" button in the migration notice — it just does not add
     * a second entry next to the one they already know.
     *
     * Only when Buttonizer has nothing of its own to show: an install with
     * buttons in the cloud keeps its menu, hiding it would take away something
     * the user actively uses.
     */
    private static function decideOwnMenu(): void
    {
        if (!is_admin() || ConnectionAdopter::isTargetConnected()) {
            return;
        }

        self::$hostMenuHidden = true;
    }

    /**
     * Is Buttonizer keeping itself out of the sidebar?
     *
     * The module asks before registering its screens: hanging them off a menu
     * that is about to be removed would take them off the sidebar too, so in
     * that case it registers itself top-level, exactly as the plugin did.
     */
    public static function hostMenuHidden(): bool
    {
        return self::$hostMenuHidden;
    }

    /**
     * Should this source plugin's module be served by Buttonizer?
     */
    private static function shouldLoad(SourcePlugin $source): bool
    {
        if (!$source->hasModule()) {
            return false;
        }

        // Only a system Buttonizer took over is served from here, and it keeps
        // being served regardless of the source plugin's own flags: going by
        // those means anything that flips one takes the user's buttons off the
        // site. Old options on their own prove nothing either way — whether
        // they are absorbed is MigrationManager's call, and this only follows it.
        if (!self::wasEmbedded($source)) {
            return false;
        }

        // The user has moved on to the cloud
        if (self::hasMovedToCloud($source)) {
            return false;
        }

        // The source plugin already loaded this exact code — it is the same
        // classes and functions in the global namespace, so a second copy is a
        // fatal redeclaration.
        //
        // Deliberately about the code and not about the plugin: a source plugin
        // reactivated after the migration steps aside for the module, and going
        // by its active flag instead would hand that user Buttonizer's signup
        // screen in place of the screens they have been using all along.
        if ($source->isModuleLoaded()) {
            return false;
        }

        return true;
    }

    /**
     * Has this site graduated from the old system to the cloud?
     *
     * The module was taken over while Buttonizer had no site of its own, and
     * Buttonizer got connected: the only way to get there is the user deciding
     * to move. Serving both would put two sets of buttons on their site — the
     * old ones from these options and the new ones from the cloud.
     *
     * The answer is remembered rather than re-read from the connection, so a
     * disconnect does not quietly put the old system back. Someone whose
     * session expired should not find a different plugin rendering on their
     * site; coming back is a decision, and LegacyFallback is where it is made.
     *
     * The options are never touched, so the buttons return exactly as they
     * were left.
     */
    private static function hasMovedToCloud(SourcePlugin $source): bool
    {
        $state = MigrationState::get($source->id());

        if (($state['result'] ?? '') !== MigrationManager::RESULT_LEGACY_MODULE) {
            return false;
        }

        // Already on the cloud before the migration: this site always had both
        if (!empty($state['target_connected'])) {
            return false;
        }

        if (!empty($state['moved_to_cloud'])) {
            return true;
        }

        if (!ConnectionAdopter::isTargetConnected()) {
            return false;
        }

        // First request after the move. Written once, and only ever unwritten
        // by the user asking for the old system back.
        MigrationState::set($source->id(), ['moved_to_cloud' => true]);

        return true;
    }

    /**
     * Did Buttonizer already take this source plugin's old system over?
     */
    private static function wasEmbedded(SourcePlugin $source): bool
    {
        $state = MigrationState::get($source->id());

        return ($state['result'] ?? '') === MigrationManager::RESULT_LEGACY_MODULE;
    }
}
