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
namespace Buttonizer\Legacy\Api;

use Buttonizer\Api\Settings\MigrateToStandalone;
/**
 * Initialize api
 */
class Api {
    private static $authenticated = false;

    /**
     * Register API endpoints
     */
    public function __construct() {
        // Frontend api endpoints
        ( new Buttons\ApiButtons() )->registerRoute();
        // Migration route
        ( new MigrateToStandalone() )->registerRoute();
    }

    /**
     * Return error, need Buttonizer pro
     */
    public static function needButtonizerPremium() {
        return new \WP_Error('missing_premium_license', 'You do not have Buttonizer Pro.', [
            'status' => 403,
        ]);
    }

}
