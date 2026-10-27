<?php
/*
* Plugin Name: Buttonizer - Smart Floating & Sticky Buttons
* Plugin URI:  https://buttonizer.io
* Description: The Buttonizer is a new way to give a boost to your number of interactions, actions and conversions from your website visitor by adding one or multiple Customizable Smart Floating Button in the corner of your website.
* Version:     3.6.1
* Author:      Buttonizer
* Author URI:  https://buttonizer.io
* License:     GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: buttonizer-multifunctional-button
* Domain Path: /languages
*
*
* SOFTWARE LICENSE INFORMATION
*
* Copyright (c) 2017 Buttonizer, all rights reserved.
*
* This file is part of Buttonizer
*
* For detailed information regarding to the licensing of
* this software, please review the license.txt or visit:
* https://buttonizer.io/license/
*/

// Define current Buttonizer version
define('BUTTONIZER_VERSION', '3.6.1');
define('BUTTONIZER_PLUGIN_FILE', __FILE__);

// Get environment vars
require_once __DIR__ . "/EnvVars.php";


# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/* ================================================
 *     WELCOME TO THE BUTTONIZER SOURCE CODE!
 *
 *      We like to see that you are courious
 *        how the code is written. If you
 *       are here to try to resolve problems
 *        you must be careful, anything
 *          can get broken you know...
 *
 *            -- KNOWLEDGE BASE --
 *        Did you know you can use our
 *              knowledge base?
 *               That's free!
 *
 *				     VISIT:
 *  https://r.buttonizer.io/support/knowledgebase
 *
 *             -- BUGS FOUND? --
 *	    Are you here to look for a bug?
 *		 Cool! If you found something
 *         you can report it to us!
 *
 *       Maybe you get a FREE license
 *            for 1 website ;)
 *
 * ================================================
 */

// Autoloader
require_once BUTTONIZER_APP_DIR . "/autoloader.php";

// Initialize shared core configuration
\Buttonizer\Core\PluginConfig::init([
    'name'        => BUTTONIZER_NAME,
    'dir'         => BUTTONIZER_DIR,
    'app_dir'     => BUTTONIZER_APP_DIR,
    'slug'        => BUTTONIZER_SLUG,
    'version'     => BUTTONIZER_VERSION,
    'plugin_file' => BUTTONIZER_PLUGIN_FILE,
    'base_name'   => BUTTONIZER_BASE_NAME,
    'api_uri'     => BUTTONIZER_API_URI,
    'api_timeout' => defined("BUTTONIZER_API_TIMEOUT") ? BUTTONIZER_API_TIMEOUT : 20,
    'page_slug'   => 'Buttonizer',
    'text_domain' => 'buttonizer-multifunctional-button',
    'review_url'  => 'https://r.buttonizer.io/review/wordpress?utm_source=wp-plugin-request-review-btn',
    'product_name' => 'Buttonizer',
    'notice_id'   => 'buttonizer-admin-notice',
    'notice_js_function' => 'buttonizerAdminNotice',
    'referral_slug' => 'buttonizer-plugin-menu',
    'siblings'    => [
        'bz_contact_button' => [
            'token_option'    => 'bz_contact_button_site_connection',
            'settings_option' => 'bz_contact_button_settings',
            'account_option'  => 'bz_contact_button_account',
        ],
        'bz_social_feeds' => [
            'token_option'    => 'bz_social_feeds_site_connection',
            'settings_option' => 'bz_social_feeds_settings',
            'account_option'  => 'bz_social_feeds_account',
        ],
    ],
]);

// Initialize
require_once BUTTONIZER_DIR . "/init.php";

// Uninstall
register_uninstall_hook(__FILE__, 'pluginUninstallEvent');

function pluginUninstallEvent()
{
    // The migration bookkeeping is this plugin's own, and nothing should
    // outlive the plugin that wrote it: a leftover entry makes a later install
    // believe this site was already handed over. The old system's options are
    // left where they are — those are the user's buttons, not bookkeeping.
    if (class_exists('\\Buttonizer\\Migration\\MigrationState')) {
        delete_option(\Buttonizer\Migration\MigrationState::OPTION);
    }

    // Skip if the is no API token being used
    if (\Buttonizer\Core\Utils\ApiRequest::getApiToken() === false) {
        return;
    }

    try {
        // Invalidate access token for security reasons on uninstall
        (new \Buttonizer\Core\Api\Connection\Disconnect)->disconnect(false);
    } catch (\Error $err) {
        // Errored out, nevermind then
    }
}
