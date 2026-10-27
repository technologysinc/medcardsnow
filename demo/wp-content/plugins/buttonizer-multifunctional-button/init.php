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

use Buttonizer\Core\Utils\Settings;
use Buttonizer\Core\InitBootstrap;

// Check Buttonizer Legacy enabled
if (
    // Check if legacy was forced
    Settings::getSetting("force_legacy", false) === true ||

    // Check for older installations which did not yet decide
    (Settings::getSetting("finished_setup", false) === false && get_option("buttonizer_buttons", false) !== false)
) {
    // Init legacy
    require_once BUTTONIZER_DIR . "/legacy.php";
} else {
    // Boot shared hooks (admin, redirect, page data, embed script, shortcode, admin bar, REST API)
    InitBootstrap::boot(
        \Buttonizer\Admin\Admin::class,
        \Buttonizer\Api\Api::class
    );

    // Serve an absorbed plugin's own system, for sites that still run it.
    // Before the hooks below, because the module registers its own.
    \Buttonizer\Migration\ModuleLoader::boot();

    // Absorb acquired plugins (admin only). Deliberately outside the legacy
    // branch: a Buttonizer install that still runs its own legacy system is
    // not in a position to take over another plugin's users.
    \Buttonizer\Migration\MigrationManager::boot();

    // Google Analytics (legacy 2.x setting, buttonizer-specific)
    add_action('wp_enqueue_scripts', function () {
        if (Settings::isset("google_analytics")) {
            wp_register_script('google_analytics', 'https://www.googletagmanager.com/gtag/js?id=' . Settings::getSetting("google_analytics"), array(), false, true);

            wp_enqueue_script('google_analytics');

            wp_add_inline_script('google_analytics', "
 window.dataLayer = window.dataLayer || [];
 function gtag(){dataLayer.push(arguments);}
 gtag('js', new Date());

 gtag('config', '" . Settings::getSetting("google_analytics") . "');");
        }
    });
}
