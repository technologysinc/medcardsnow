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

namespace Buttonizer\Admin;

use Buttonizer\Api\Settings\MigrateToStandalone;
use Buttonizer\Core\Admin\BaseAdmin;
use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Migration\LegacyFallback;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

class Admin extends BaseAdmin
{
    /**
     * Admin constructor.
     */
    public function __construct()
    {
        // Register shared admin hooks (assets, modules, notices)
        parent::__construct();

        // Add to admin menu
        add_action('admin_menu', [$this, 'pluginAdminMenu']);

        // Plugin information, add links
        add_filter("plugin_action_links_" . BUTTONIZER_BASE_NAME, function ($actions) {
            $links = [
                '<a href="' . admin_url('admin.php?page=Buttonizer#/support') . '">' . __('Support', 'buttonizer-multifunctional-button') . '</a>',
                '<a href="' . admin_url('admin.php?page=Buttonizer#/editor') . '">' . __('Edit buttons', 'buttonizer-multifunctional-button') . '</a>',
                '<a href="' . admin_url('admin.php?page=Buttonizer#/settings') . '">' . __('Settings', 'buttonizer-multifunctional-button') . '</a>',
            ];

            return array_merge($actions, $links);
        });
    }

    /**
     * Create Admin menu
     */
    public function pluginAdminMenu()
    {

        if (!PermissionCheck::hasPermission()) return;

        // Admin menu
        add_menu_page('Buttonizer Buttons', 'Buttonizer', 'read', 'Buttonizer', [$this, 'page'], plugins_url('/assets/images/wp-icon.png', BUTTONIZER_PLUGIN_DIR), 81);

        // Add submenu
        add_submenu_page('Buttonizer', 'Edit buttons',  __('Edit buttons', 'buttonizer-multifunctional-button'), 'read', 'admin.php?page=Buttonizer#/editor');

        // Add support link
        add_submenu_page('Buttonizer', __('I need support', 'buttonizer-multifunctional-button'),  __('I need support', 'buttonizer-multifunctional-button'), 'read', 'admin.php?page=Buttonizer#/support');

        // Add community link
        add_submenu_page('Buttonizer', __('Community', 'buttonizer-multifunctional-button'),  __('Community', 'buttonizer-multifunctional-button'), 'read', 'https://r.buttonizer.io/support/community?referral=buttonizer-plugin-menu');

        // Add knowledge base link
        add_submenu_page('Buttonizer', __('Knowledge base', 'buttonizer-multifunctional-button'),  __('Knowledge base', 'buttonizer-multifunctional-button'), 'read', 'https://r.buttonizer.io/support/knowledgebase?referral=buttonizer-plugin-menu');
    }

    /**
     * Lock the screen to a specific action.
     *
     * Adds legacy migration check on top of the base implementation.
     *
     * @return string lock
     */
    public function getActionLock(): string
    {
        // Needs migration (legacy-specific)
        if (defined("BUTTONIZER_LEGACY_REQUESTED_MIGRATION") && !BUTTONIZER_LEGACY_REQUESTED_MIGRATION) {
            return "migration";
        }

        return parent::getActionLock();
    }

    /**
     * Dashboard configuration.
     *
     * Adds whether this site can go back to an absorbed plugin's old system,
     * so the signup screen can offer it the way that plugin used to.
     *
     * @return array
     */
    protected function getLocalizeData(): array
    {
        $data = parent::getLocalizeData();

        $source = LegacyFallback::available();

        // Only ever offered to a site whose old system Buttonizer took over,
        // so the name that means something here is the old one.
        $data['legacyModule'] = $source ? ['label' => $source->legacyLabel()] : null;

        return $data;
    }

    /**
     * Migration status (legacy-specific).
     *
     * @return mixed
     */
    public function getBeforeMigrate()
    {
        if (!defined("BUTTONIZER_LEGACY_REQUESTED_MIGRATION")) {
            return null;
        }

        return MigrateToStandalone::getReadyForMigration();
    }
}
