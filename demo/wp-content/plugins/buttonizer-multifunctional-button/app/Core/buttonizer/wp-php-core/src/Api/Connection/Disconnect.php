<?php
/**
 * Disconnect API endpoint.
 *
 * Invalidates the external access token and removes site connection data.
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
use Buttonizer\Core\Utils\SiblingPlugins;

/**
 * Disconnect API
 * Invalidates External Access Token and removes data
 *
 * @methods POST
 */
class Disconnect
{
    private static $continueIfStatus = ["buttonizer_token_expired",  "buttonizer_api_request_failed", "buttonizer_api_server_error", "buttonizer_api_not_reachable"];

    /**
     * Register route
     */
    public function registerRoute()
    {
        register_rest_route(PluginConfig::name(), '/disconnect', [
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
                'callback' => [$this, 'disconnect'],
                'permission_callback' => function () {
                    return PermissionCheck::hasPermission();
                }
            ]
        ]);
    }

    /**
     * Disconnect
     *
     * @param bool $disconnectSiblings Whether to also disconnect sibling Buttonizer plugins.
     *                                  true  = UI "Disconnect" button (explicit user action)
     *                                  false = uninstall hook (only this plugin is being removed)
     */
    public function disconnect($disconnectSiblings = true)
    {
        // Only revoke the token server-side if this is an explicit disconnect
        // or if no sibling plugin still needs the shared token.
        if ($disconnectSiblings || SiblingPlugins::findConnectedSibling() === null) {
            $result = ApiRequest::post("/disconnect");

            if (is_a($result, 'WP_Error') && !in_array($result->get_error_code(), self::$continueIfStatus)) {
                return $result;
            }
        }

        // Set last synced at
        Settings::setSetting("last_synced_at", null);
        Settings::setSetting("finished_setup", false);
        Settings::setSetting("site_id", null);

        // Save synced info
        Settings::saveUpdatedSettings();

        // Remove API token
        ApiRequest::deleteApiToken();

        // Erase account settings
        Account::emptyAccountSettings();

        // Disconnect all sibling Buttonizer plugins
        if ($disconnectSiblings) {
            SiblingPlugins::disconnectAllSiblings();
        }

        return [
            'status' => 'success'
        ];
    }
}

