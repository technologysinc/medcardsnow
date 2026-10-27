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

namespace Buttonizer\Api\Utils;

use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Migration\LegacyFallback;

/**
 * Go back to an absorbed plugin's old system.
 *
 * The counterpart of the acquired plugin's own "use legacy version" link, kept
 * available to users who moved to Buttonizer so the move never costs them the
 * escape hatch they had before.
 *
 * @endpoint /wp-json/buttonizer/use_legacy_module
 * @methods POST
 */
class UseLegacyModule
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route('buttonizer', '/use_legacy_module', [
            [
                'methods'  => ['POST'],
                'args' => [
                    'nonce' => [
                        'validate_callback' => function ($value) {
                            return wp_verify_nonce($value, 'wp_rest');
                        },
                        'required' => true
                    ],
                ],
                'callback' => [$this, 'useLegacy'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission(true);
                }
            ]
        ]);
    }

    /**
     * Restore the old system.
     */
    public function useLegacy()
    {
        $redirect = LegacyFallback::restore();

        if (!$redirect) {
            return new \WP_Error('no_legacy_system', 'There is no legacy system to go back to on this site.', [
                'status' => 404
            ]);
        }

        return [
            'status'   => 'success',
            'redirect' => $redirect,
        ];
    }
}
