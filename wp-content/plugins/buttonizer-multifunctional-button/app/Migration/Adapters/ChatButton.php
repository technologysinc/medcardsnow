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

namespace Buttonizer\Migration\Adapters;

use Buttonizer\Core\PluginConfig;
use Buttonizer\Migration\ConnectionAdopter;
use Buttonizer\Migration\ModuleLoader;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Fits the Chat Button module into Buttonizer without editing it.
 *
 * The module under modules/chat-button/ is a byte-for-byte copy of the plugin
 * it came from, so it still registers a top-level menu and still links to that
 * plugin's own pages. Everything that has to bend lives here instead, using the
 * hooks the module already provides.
 *
 * Keeping the copy untouched is what makes it re-copyable: when the acquired
 * plugin ships a new version, the folder is replaced wholesale and only this
 * file may need attention.
 */
class ChatButton
{
    /**
     * Called right after the module is loaded, before any hook has fired.
     */
    public static function attach(): void
    {
        // The module's own bridge to the "new version" points at the acquired
        // plugin's dashboard, which is deactivated by the time we serve this,
        // and on the way there it turns the module off — taking the user's
        // buttons off the site with no way back. Same button, our handler.
        remove_action('admin_init', 'try_new_version');
        add_action('admin_init', [self::class, 'handleTryNewVersion']);

        add_action('admin_menu', [self::class, 'arrangeMenu'], 999);

        // The plugin this user came from had no admin bar entry, and the one
        // Buttonizer adds only leads to a dashboard they cannot see. Checked
        // by connection and not by hostMenuHidden(), which is admin-only
        // while the bar also renders on the site.
        add_action('admin_bar_menu', [self::class, 'removeAdminBar'], 101);

        if (ModuleLoader::hostMenuHidden()) {
            add_filter('plugin_action_links_' . PluginConfig::baseName(), [self::class, 'pluginActionLinks'], 999);
        }
    }

    /**
     * Drop Buttonizer's admin bar node; its children go with it.
     */
    public static function removeAdminBar(\WP_Admin_Bar $bar): void
    {
        if (ConnectionAdopter::isTargetConnected()) {
            return;
        }

        $bar->remove_node(PluginConfig::name());
    }

    /**
     * Replace the dashboard links on the plugins screen with the one the
     * acquired plugin used to show. The originals all point at Buttonizer's
     * own page, which redirects to the module anyway, losing the route.
     *
     * @param string[] $actions
     * @return string[]
     */
    public static function pluginActionLinks(array $actions): array
    {
        $own = admin_url('admin.php?page=' . PluginConfig::pageSlug() . '#/');

        $actions = array_filter($actions, function ($link) use ($own) {
            return strpos($link, $own) === false;
        });

        $actions[] = '<a href="' . admin_url('admin.php?page=contact_vr') . '">' . esc_html__('Settings', 'buttonizer-multifunctional-button') . '</a>';

        return $actions;
    }

    /**
     * Send "Try now" to Buttonizer instead of to a plugin that is gone.
     *
     * Nothing is switched off on the way: the old buttons keep rendering until
     * the user actually sets Buttonizer up.
     */
    public static function handleTryNewVersion(): void
    {
        if (
            !current_user_can(is_multisite() ? 'manage_options' : 'activate_plugins') ||
            !isset($_GET['page'], $_GET['try_new_version']) ||
            $_GET['page'] !== 'contact_vr_setting' ||
            $_GET['try_new_version'] !== 'yes'
        ) {
            return;
        }

        wp_safe_redirect(add_query_arg(
            ModuleLoader::OWN_DASHBOARD_PARAM,
            1,
            admin_url('admin.php?page=' . PluginConfig::pageSlug())
        ));
        exit;
    }

    /**
     * Decide which of the two menus the user sees.
     *
     * Runs late, after the module registered its own top-level entry:
     *
     * - Buttonizer has nothing of its own to show → it steps out of the
     *   sidebar and hands its name over to the module's menu, which keeps
     *   every screen underneath exactly where it was.
     * - Buttonizer is in use → its menu stays and the module's screens move
     *   under it, so there is one entry instead of two.
     */
    public static function arrangeMenu(): void
    {
        if (ModuleLoader::hostMenuHidden()) {
            self::adoptMenu(remove_menu_page(PluginConfig::pageSlug()));

            return;
        }

        // Re-parent: the page stays registered, so its URL and the tabs the
        // screens navigate with keep working.
        remove_menu_page('contact_vr');

        add_submenu_page(
            PluginConfig::pageSlug(),
            'Button contact VR',
            'Button contact',
            'administrator',
            'contact_vr',
            'pzf_settings_page'
        );
    }

    /**
     * Put Buttonizer's name on the menu the module registered.
     *
     * The user was asked to move to Buttonizer and said yes, so a sidebar
     * still headed "Button contact" reads as if nothing happened. The browser
     * tab goes with it, for the same reason.
     *
     * Only the top-level entry changes: WordPress copied the submenu off it
     * back when the module registered its first child, long before this runs,
     * so the links underneath stay worded exactly as the plugin worded them.
     *
     * @param array|false $own Buttonizer's own menu entry, as removed.
     */
    private static function adoptMenu($own): void
    {
        // Nothing was taken away, so there is nothing to take the name from
        if (!is_array($own)) {
            return;
        }

        foreach ($GLOBALS['menu'] as $position => $item) {
            if (($item[2] ?? null) !== 'contact_vr') {
                continue;
            }

            // Sidebar label, page title, icon
            $GLOBALS['menu'][$position][0] = $own[0];
            $GLOBALS['menu'][$position][3] = $own[0];
            $GLOBALS['menu'][$position][6] = $own[6] ?? '';

            break;
        }

        self::adoptPageTitle($own[0]);
    }

    /**
     * Rename the entry the browser tab actually reads.
     *
     * WordPress treats a top-level page that has children as one of its own
     * children, and takes the title from the copy it made of the parent — not
     * from the parent itself. Renaming the menu alone leaves the old product
     * name in the tab.
     *
     * The label in that same copy is what the sidebar shows underneath, and it
     * stays as the plugin wrote it.
     *
     * @param string $title Name to show in the browser tab.
     */
    private static function adoptPageTitle(string $title): void
    {
        foreach ($GLOBALS['submenu']['contact_vr'] ?? [] as $position => $item) {
            if (($item[2] ?? null) !== 'contact_vr') {
                continue;
            }

            $GLOBALS['submenu']['contact_vr'][$position][3] = $title;

            return;
        }
    }
}
