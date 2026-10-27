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

namespace Buttonizer\Migration;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Registry of plugins Buttonizer can absorb.
 *
 * Adding a newly acquired plugin means adding one entry here.
 */
class SourcePlugins
{
    /**
     * @var SourcePlugin[]|null
     */
    private static $plugins = null;

    /**
     * All registered source plugins.
     *
     * @return SourcePlugin[]
     */
    public static function all(): array
    {
        if (self::$plugins !== null) {
            return self::$plugins;
        }

        self::$plugins = array_map(function ($config) {
            return new SourcePlugin($config);
        }, self::definitions());

        return self::$plugins;
    }

    /**
     * Get a single source plugin by id.
     *
     * @param string $id Module identifier.
     *
     * @return SourcePlugin|null
     */
    public static function get(string $id)
    {
        foreach (self::all() as $plugin) {
            if ($plugin->id() === $id) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Raw descriptors.
     *
     * @return array[]
     */
    private static function definitions(): array
    {
        return [
            [
                'id'            => 'chat-button',
                'label'         => 'Chat Button',
                // What the same plugin calls itself while its old system is
                // the one running: that menu entry is all these users have
                // ever seen of it.
                'legacy_label'  => 'Button contact',
                'option_prefix' => 'bz_contact_button',
                'page_slug'     => 'bz_button_contact',
                'text_domain'   => 'button-contact-vr',

                // Development checkout first, wordpress.org build second
                'base_name_candidates' => [
                    'button-contact-for-wordpress/button-contact-vr.php',
                    'button-contact-vr/button-contact-vr.php',
                ],

                // The acquired plugin's own pre-Buttonizer system, copied in
                // verbatim under modules/. Loaded only for installs that still
                // run it, and only once the source plugin is out of the way.
                'module_bootstrap'      => 'chat-button/plugin.php',
                'module_page_slug'      => 'contact_vr',
                'module_adapter'        => \Buttonizer\Migration\Adapters\ChatButton::class,
                'module_guard_class'    => 'PZF',
                'module_guard_constant' => 'PZF_FILE',

                // Pre-Buttonizer system of the acquired plugin ("Button contact VR")
                'legacy_flag_option' => 'button_contact_legacy',
                'legacy_flag_value'  => 'yes',
                'legacy_options'     => [
                    'pzf_phone',
                    'pzf_phone2',
                    'pzf_phone3',
                    'pzf_linkfanpage',
                    'pzf_linkmessenger',
                    'pzf_whatsapp',
                    'pzf_zalo',
                    'pzf_telegram',
                    'pzf_instagram',
                    'pzf_youtube',
                    'pzf_tiktok',
                    'pzf_viber',
                    'pzf_linkggmap',
                    'pzf_contact_link',
                ],

                // Settings the user configured in the source plugin that must
                // survive the move. The connection itself (finished_setup,
                // installed_at, last_synced_at, site_id) is always copied.
                'settings_to_carry_over' => [
                    'include_page_data',
                    'wait_until_consent',
                    'additional_permissions',
                    'admin_top_bar_show_button',
                    'can_send_errors',
                    'site_licensed',
                ],
            ],
        ];
    }
}
