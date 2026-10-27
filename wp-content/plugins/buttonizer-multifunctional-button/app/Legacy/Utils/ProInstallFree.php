<?php

namespace Buttonizer\Legacy\Utils;

class ProInstallFree
{
    /**
     * Download plugin/Latest version
     *
     * Since our standalone, we do not need to seperate installs
     * This is why we are downloading/updating our older plugin
     */
    public static function upgrade()
    {
        // Include required libs for installation
        require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php');
        require_once(ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php');

        // Cannot update/install
        if (!current_user_can('install_plugins')) {
            wp_die(__('Sorry, you are not allowed to install plugins on this site. Please ask your administrator to update.', 'buttonizer-multifunctional-button'));
        }

        $upgrader = new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin());

        // Is the free version already installed? Update it to the newest version
        if (array_key_exists('buttonizer-multifunctional-button/buttonizer.php', \get_plugins())) {
            $upgrader->upgrade("buttonizer-multifunctional-button");
        } else {
            $upgrader->install("https://downloads.wordpress.org/plugin/buttonizer-multifunctional-button.latest-stable.zip");
        }

        self::activatePlugin();
    }

    public static function activatePlugin()
    {
        // Activate original plugin
        \activate_plugin("buttonizer-multifunctional-button", "", false, true);

        // Deactivate Buttonizer PRO
        \deactivate_plugins(plugin_basename(BUTTONIZER_PLUGIN_DIR), true);

        exit;
    }
}
