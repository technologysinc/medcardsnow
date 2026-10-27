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

define('BUTTONIZER_NAME', 'buttonizer');
define('BUTTONIZER_DIR', dirname(__FILE__));
define('BUTTONIZER_APP_DIR', __DIR__ . "/app");
define('BUTTONIZER_SLUG', basename(BUTTONIZER_DIR));
define('BUTTONIZER_PLUGIN_DIR', __FILE__);
define("BUTTONIZER_BASE_NAME", plugin_basename(BUTTONIZER_PLUGIN_FILE));
define('BUTTONIZER_LAST_MIGRATION', 7);
define('BUTTONIZER_LAST_TOUR_UPDATE', 250);
define('BUTTONIZER_LAST_CHANGELOG_UPDATE', 260);

// From CDNJS: https://cdnjs.com/libraries/font-awesome
define('FONTAWESOME_CURRENT_VERSION', '5.15.4');
define('FONTAWESOME_CURRENT_INTEGRITY', 'sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==');

define("FONTAWESOME_6_CURRENT_VERSION", '6.1.1');
define('FONTAWESOME_6_CURRENT_INTEGRITY', 'sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g==');

if (!defined("BUTTONIZER_API_URI")) {
    define("BUTTONIZER_API_URI", "https://api.buttonizer.io");
}

// DEBUG ONLY
if (defined("BUTTONIZER_DEBUG") && BUTTONIZER_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ERROR);
}

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');
