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

namespace Buttonizer\Api;

use Buttonizer\Core\Api\BaseApi;

class Api extends BaseApi
{
    /**
     * Register API endpoints.
     *
     * Shared routes are registered by BaseApi.
     * Legacy routes are plugin-specific.
     */
    public function __construct()
    {
        // Register shared routes (Connect, Disconnect, Sync, Settings, Editor, Analytics)
        parent::__construct();

        // Legacy (plugin-specific)
        // We can't call the Freemius split conditional here to remove these routes because we don't have the Freemius
        // object available outside Legacy folder.
        (new Utils\DeleteLegacyBackup())->registerRoute();

        // The way back to an absorbed plugin's old system
        (new Utils\UseLegacyModule())->registerRoute();
    }

    /**
     * Return error, need Buttonizer pro
     */
    public static function needButtonizerPremium()
    {
        return new \WP_Error('missing_premium_license', 'You do not have Buttonizer Pro.', [
            'status' => 403
        ]);
    }
}
