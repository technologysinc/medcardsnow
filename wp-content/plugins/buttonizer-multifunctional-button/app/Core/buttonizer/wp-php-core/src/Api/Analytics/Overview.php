<?php
/**
 * Analytics Overview API endpoint.
 *
 * Retrieves analytics graph data from the Buttonizer cloud API.
 *
 * @package Buttonizer\Core\Api\Analytics
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Api\Analytics;

use Buttonizer\Core\PluginConfig;
use Buttonizer\Core\Utils\ApiRequest;
use Buttonizer\Core\Utils\PermissionCheck;

/**
 * Analytics API
 *
 * @methods POST
 */
class Overview
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/analytics/overview', [
            [
                'methods'  => ['POST'],
                'args' => [
                    'type' => [
                        'required' => false,
                        'type' => "string"
                    ],
                    'nonce' => [
                        'validate_callback' => function ($value) {
                            return wp_verify_nonce($value, 'wp_rest');
                        },
                        'required' => true
                    ],
                ],
                'callback' => [$this, 'getGraph'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Sync data
     */
    public function getGraph($request)
    {
        $type = $request->get_param('type', 'weekly');
        $timezone = $request->get_param('timezone', 'site');

        // Sync data
        $result = ApiRequest::post("/analytics/overview?type=" . $type . "&timezone=" . $timezone);

        // Handle errors
        if (is_a($result, 'WP_Error')) {
            return $result;
        }

        return [
            'status' => 'success',
            'data' => $result
        ];
    }
}

