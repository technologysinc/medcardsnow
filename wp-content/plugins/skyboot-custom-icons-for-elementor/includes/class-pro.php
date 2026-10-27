<?php
/**
 * PRO gate.
 *
 * The FREE plugin contains all PRO user interface (shown locked). The separate
 * PRO add-on plugin is what turns the gate on: it either defines the constant
 * SKB_CIFE_PRO_VERSION or hooks the "skb_cife_is_pro" filter to return true, and
 * then registers the real feature logic. Nothing here duplicates PRO code.
 */
if (!defined('ABSPATH'))
    exit;

if (!class_exists('Skb_Cife_Pro')):

    class Skb_Cife_Pro
    {

        /**
         * Is the PRO add-on active & licensed?
         *
         * @return bool
         */
        public static function is_active()
        {
            $active = defined('SKB_CIFE_PRO_VERSION');

            /**
             * Allow the PRO add-on (or a license check) to unlock PRO features.
             *
             * @param bool $active
             */
            return (bool) apply_filters('skb_cife_is_pro', $active);
        }

        /**
         * Build a tracked upgrade URL.
         *
         * @param string $medium Where the click came from (for analytics only).
         * @return string
         */
        public static function upgrade_url($medium = 'dashboard')
        {
            $url = 'https://skybootstrap.com/custom-icons-for-elementor/';
            return add_query_arg(
                array(
                    'utm_source' => 'wp-plugin',
                    'utm_medium' => sanitize_key($medium),
                    'utm_campaign' => 'go-pro',
                ),
                $url
            );
        }

        /**
         * Canonical list of PRO features. Used by the locked tabs, the dashboard
         * widget, the upgrade notice and the "Go Pro" page so copy stays in sync.
         *
         * @return array
         */
        public static function features()
        {
            return array(
                array('icon' => 'dashicons-star-filled', 'title' => __('More icon packs', 'skyboot-custom-icons-for-elementor'), 'desc' => __('Get additional icon libraries beyond the Free version.', 'skyboot-custom-icons-for-elementor')),
                array('icon' => 'dashicons-upload', 'title' => __('Custom SVG Icon Packs', 'skyboot-custom-icons-for-elementor'), 'desc' => __('Upload .zip packs securely without cluttering your Media Library.', 'skyboot-custom-icons-for-elementor')),
                array('icon' => 'dashicons-art', 'title' => __('Gradient & dynamic tags', 'skyboot-custom-icons-for-elementor'), 'desc' => __('Advanced gradient styling and dynamic-tag support inside Elementor.', 'skyboot-custom-icons-for-elementor')),
                array('icon' => 'dashicons-sos', 'title' => __('Priority support', 'skyboot-custom-icons-for-elementor'), 'desc' => __('Fast, dedicated help from the Skybootstrap team.', 'skyboot-custom-icons-for-elementor')),
            );
        }
    }

endif;
