<?php
/**
 * Permission check utility.
 *
 * Determines if the current user has permission to access
 * the plugin's admin features.
 *
 * @package Buttonizer\Core\Utils
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Utils;

class PermissionCheck
{
    private static $cachedPermission = null;
    private static $cachedUserPermissions = null;

    /**
     * Does the user have access to the plugin?
     */
    public static function hasPermission($adminOnly = false)
    {
        // Always grand admins
        if (\is_user_logged_in() && current_user_can(is_multisite() ? 'manage_options' : 'activate_plugins')) {
            return true;
        }

        // User was not an admin
        // Deny as we apparently require admin permission
        if ($adminOnly) {
            return false;
        }

        // Use previous cached permission
        if (self::$cachedPermission !== null) {
            return self::$cachedPermission;
        }

        // By default, do not grant any permission
        $grant = false;

        // Check for additional permissions
        if (!$adminOnly && \is_user_logged_in() && Settings::isset("additional_permissions")) {
            // Loop through additional permissions
            foreach (Settings::getSetting("additional_permissions", []) as $permission) {
                if ($grant) continue;

                $grant = current_user_can($permission);
            }
        }

        self::$cachedPermission = $grant;
        return $grant;
    }

    /**
     * Get the current user's roles.
     */
    public static function getUserRoles()
    {
        // Already loaded user permissions
        if (self::$cachedUserPermissions) {
            return self::$cachedUserPermissions;
        }

        // If not logged in, add guest role in roles
        if (!\is_user_logged_in()) return ["guest"];

        self::$cachedUserPermissions = get_userdata(get_current_user_id())->roles;

        return self::$cachedUserPermissions;
    }
}

