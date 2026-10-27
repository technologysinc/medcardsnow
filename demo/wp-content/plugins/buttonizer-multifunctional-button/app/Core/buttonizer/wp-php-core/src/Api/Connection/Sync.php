<?php
/**
 * Sync API endpoint.
 *
 * Synchronizes site data with the Buttonizer cloud API.
 *
 * @package Buttonizer\Core\Api\Connection
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Api\Connection;

use Buttonizer\Core\PluginConfig;
use Buttonizer\Core\Utils\ApiRequest;
use Buttonizer\Core\Utils\Account;
use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Core\Utils\Settings;

/**
 * Sync API
 *
 * @methods POST
 */
class Sync
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/sync', [
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
                'callback' => [$this, 'sync'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Sync data
     */
    public function sync()
    {
        // Sync data
        $result = ApiRequest::post("/sync");

        // Handle errors
        if (is_a($result, 'WP_Error')) {
            return $result;
        }

        // Set last synced at
        Settings::setSetting("last_synced_at", new \DateTime('now'));
        Settings::setSetting("include_page_data", isset($result->site->licensed) && $result->site->licensed);

        // Save synced info
        Settings::saveUpdatedSettings();

        // Sync account settings
        Account::syncToDatabase($result);

        return [
            'status' => 'success',
            'data' => Account::getData()
        ];
    }
}

