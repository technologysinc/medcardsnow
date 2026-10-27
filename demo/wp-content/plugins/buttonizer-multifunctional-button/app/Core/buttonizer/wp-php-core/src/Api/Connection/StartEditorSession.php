<?php
/**
 * Start Editor Session API endpoint.
 *
 * Creates an editor session via the Buttonizer cloud API.
 *
 * @package Buttonizer\Core\Api\Connection
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Api\Connection;

use Buttonizer\Core\PluginConfig;
use Buttonizer\Core\Utils\ApiRequest;
use Buttonizer\Core\Utils\PermissionCheck;

/**
 * Start Editor Session API
 * Creates an editor session
 *
 * @methods POST
 */
class StartEditorSession
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/editor_start_session', [
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
                'callback' => [$this, 'startSession'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Start editor session
     */
    public function startSession()
    {
        // Request token
        $result = ApiRequest::post("/request-editor-session");

        // Handle errors
        if (is_a($result, 'WP_Error')) {
            return $result;
        }

        return $result;
    }
}

