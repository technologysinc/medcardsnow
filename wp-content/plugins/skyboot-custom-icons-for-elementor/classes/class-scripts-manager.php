<?php
namespace Skb_Cife;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*--------------------------
* Class Scripts Manager
* -------------------------*/
class Skb_Cife_Scripts{

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(){
        $this->init();
    }

    public function init() {
        // Admin dashboard assets are enqueued by Skb_Cife_Admin (always loaded
        // in admin) so they also cover the welcome & Go-Pro pages.
    }

    /*----------------
    * Enqueue frontend scripts
    * ----------------*/
    public function enqueue_frontend_scripts() {

        /**
         * Configuration array for all icon pack stylesheets.
         * Each item in the array contains:
         * 'option'  => The name of the setting in the 'skb_cife_manage_icon' options group.
         * 'handle'  => The unique handle for the stylesheet.
         * 'file'    => The CSS file name in the assets/css/ folder.
         * 'default' => The default state of the option ('on' or 'off').
         */
        $icon_packs = [
            [ 'option' => 'brands_icon',         'handle' => 'skb-cife-brands_icon',         'file' => 'icomoon_brands.css',          'default' => 'off' ],
            [ 'option' => 'devicons_icon',       'handle' => 'skb-cife-devicons_icon',       'file' => 'devicons.min.css',          'default' => 'off' ],
            [ 'option' => 'elegant_icon',        'handle' => 'skb-cife-elegant_icon',        'file' => 'elegant.css',               'default' => 'on'  ],
            [ 'option' => 'elusive_icon',        'handle' => 'skb-cife-elusive_icon',        'file' => 'elusive-icons.min.css',     'default' => 'off' ],
            [ 'option' => 'icofont_icon',        'handle' => 'skb-cife-icofont_icon',        'file' => 'icofont.min.css',           'default' => 'off' ],
            [ 'option' => 'icomoon_icon',        'handle' => 'skb-cife-icomoon_icon',        'file' => 'icomoon.css',               'default' => 'off' ],
            [ 'option' => 'iconic_icon',         'handle' => 'skb-cife-iconic_icon',         'file' => 'iconic.css',                'default' => 'off' ],
            [ 'option' => 'ion_icon',            'handle' => 'skb-cife-ion_icon',            'file' => 'ionicons.min.css',          'default' => 'off' ],
            [ 'option' => 'linearicons_icon',    'handle' => 'skb-cife-linearicons_icon',    'file' => 'linearicons.css',           'default' => 'on'  ],
            [ 'option' => 'lineawesome_icon',    'handle' => 'skb-cife-lineawesome_icon',    'file' => 'line-awesome.min.css',      'default' => 'off' ],
            [ 'option' => 'line_icon',           'handle' => 'skb-cife-line_icon',           'file' => 'lineicons.css',             'default' => 'off' ],
            [ 'option' => 'materialdesign_icon', 'handle' => 'skb-cife-materialdesign_icon', 'file' => 'materialdesignicons.min.css', 'default' => 'off' ],
            [ 'option' => 'open_iconic_icon',    'handle' => 'skb-cife-open_iconic',         'file' => 'open-iconic.css',           'default' => 'off' ],
            [ 'option' => 'simpleline_icon',     'handle' => 'skb-cife-simpleline_icon',     'file' => 'simple-line-icons.css',     'default' => 'off' ],
            [ 'option' => 'themify_icon',        'handle' => 'skb-cife-themify_icon',        'file' => 'themify.css',               'default' => 'on'  ],
        ];

        // Loop through the configuration array and enqueue scripts that are turned on.
        foreach ( $icon_packs as $pack ) {
            if ( 'on' === skb_cife_get_option( $pack['option'], 'skb_cife_manage_icon', $pack['default'] ) ) {
                wp_enqueue_style(
                    $pack['handle'],
                    SKB_CIFE_ASSETS . 'css/' . $pack['file'],
                    [], // No dependencies
                    SKB_CIFE_VERSION
                );
            }
        }
    }
}

Skb_Cife_Scripts::instance();