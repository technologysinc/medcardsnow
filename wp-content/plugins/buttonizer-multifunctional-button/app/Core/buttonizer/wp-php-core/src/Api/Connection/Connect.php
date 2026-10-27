<?php
/**
 * Connect API endpoint.
 *
 * Connects the site to the Buttonizer cloud API by exchanging an
 * authorization key for an external access token.
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
 * Connect API
 * Invalidates External Access Token and removes data
 *
 * @methods POST
 */
class Connect
{
    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/connect', [
            [
                'methods'  => ['POST'],
                'args' => [
                    'siteId' => [
                        'required' => true,
                        'type' => "string"
                    ],
                    'authorization' => [
                        'required' => true,
                        'type' => "string"
                    ],
                    'nonce' => [
                        'validate_callback' => function ($value) {
                            return wp_verify_nonce($value, 'wp_rest');
                        },
                        'required' => true
                    ],
                ],
                'callback' => [$this, 'connect'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Connect
     */
    public function connect($request)
    {
        // Connect Site ID
        $siteId = $request->get_param('siteId');

        // Connect Authorization key
        $authorization = $request->get_param('authorization');

        // Request token
        $result = ApiRequest::post("/auth/tokens/create-token", [
            "siteId" => $siteId,
            "authorization" => $authorization,
            "platform" => "wordpress",
            "requestToken" => true,
        ], [], false);

        // Handle errors
        if (is_a($result, 'WP_Error')) {
            return $result;
        }

        // Get previous installed at
        $installedAt = Settings::getSetting("installed_at", null);

        // Save site connection token
        ApiRequest::saveApiToken($result->token);

        if ($request->get_param('reconnect') !== true) {
            // Save new settings
            Settings::setSetting("finished_setup", true);
            Settings::setSetting("installed_at", $installedAt ?? new \DateTime('now'));
            Settings::setSetting("last_synced_at", new \DateTime('now'));
            Settings::setSetting("site_id", $siteId);
            Settings::setSetting("include_page_data", isset($result->account->site->licensed) && $result->account->site->licensed);
            Settings::saveUpdatedSettings();
        }

        // Sync account settings
        Account::syncToDatabase($result->account);
        return [
            'data' => Account::getData()
        ];
    }
}

