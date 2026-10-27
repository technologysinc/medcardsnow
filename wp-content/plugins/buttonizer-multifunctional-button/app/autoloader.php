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

// Load Strauss classmap autoloader for Buttonizer\Core\* classes
require_once __DIR__ . '/Core/autoload.php';

spl_autoload_register(function ($class_name) {
    try {
        if (substr($class_name, 0, 10) === 'Buttonizer') {
            // Skip Core\ classes — handled by Strauss classmap autoloader above
            if (substr($class_name, 0, 16) === 'Buttonizer\Core\\') {
                return;
            }

            $class_name = substr($class_name, 10);

            require BUTTONIZER_APP_DIR . str_replace("\\", "/", $class_name) . '.php';
        }
    } catch (\Exception $e) {
        exit("Error: " . $e->getMessage());
    }
});
