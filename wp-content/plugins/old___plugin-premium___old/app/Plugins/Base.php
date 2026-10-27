<?php

namespace PLUGIN\Plugins;

if (!defined('ABSPATH')) {
    exit;
}

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PLUGIN\Plugins\PluginUploader;

class Base
{

    private $plugin_all_plugins;

    public function setup()
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_all_plugins = get_plugins();
        // init
        add_action('init', array($this, 'plugin_plugin_init_action'));
        // plugin menu action
        add_action('admin_menu', array($this, 'plugin_plugin_register_menupage'));
        // enqueue js and css
        add_action('admin_enqueue_scripts', array($this, 'plugin_load_plugin_admin_scripts'));
        if (!current_user_can('install_plugins')) return;

        // add dowload links to all plugins
        $this->addDownloadLinks();
        // add bulk download link
        add_filter('bulk_actions-plugins', array($this, 'add_download_plugin_bulk_actions'));
        // loader handler
        add_action('admin_head', array($this, 'plugin_custom_admin_head_loader'));
        // popup in install handler
        //add_action( 'admin_footer', array( $this, 'plugin_plugin_setting_popup_func' ) );
        // multiple download handler
        add_action('admin_footer', array($this, 'plugin_plugin_multiple_download_action'));
        // ajax multi download handler
        add_action('wp_ajax_plugin_plugin_download_url', array($this, 'plugin_plugin_multiple_download_func'));
        // upload multiple plugin handler
        // add_action('admin_notices', array($this, 'plugin_multiple_upload_admin_func'));

        // add_action('network_admin_notices', array($this, 'plugin_multiple_upload_admin_func'));
        // plugin activation handler
        add_action('wp_ajax_plugin_plugin_activate', array($this, 'plugin_plugin_activate_func'));
        // feature select
        //add_action( 'wp_ajax_plugin_feature_select', array( $this, 'plugin_plugin_feature_select_func' ) );
    }

    public function plugin_plugin_init_action()
    {
        global $pagenow;
        if (is_user_logged_in() && current_user_can('activate_plugins')) {
            if (isset($_GET['page']) && sanitize_text_field($_GET['page'] == 'plugin_plugin')) {
                if (isset($_GET['plugin_plugin_download']) && !empty($_GET['plugin_plugin_download']) && isset($_GET['f']) && !empty($_GET['f'])) {
                    $this->plugin_plugin_download_action();
                }
            }
        }
    }

    public function plugin_plugin_register_menupage()
    {
        // download plugin submenu
        add_submenu_page('dp_main_menu_hidden', __('Multiple Upload', ' download-plugin'), __(' Multiple Upload', 'download-plugin'), 'manage_options', 'mul_upload', array($this, 'plugin_plugin_multiple_upload_func'));
        add_submenu_page('dp_main_menu_hidden_activate', __('Dpwap Activate', 'download-plugin'), __(' Dpwap Activate', 'download-plugin'), 'manage_options', 'plugin-activate', array($this, 'plugin_plugin_package_activate_func'));
        add_submenu_page('dp_main_menu_hidden_status', __('Dpwap Status', 'download-plugin'), __(' Dpwap Status', 'download-plugin'), 'manage_options', 'activate-status', array($this, 'plugin_plugin_all_activate_status_func'));
        add_submenu_page('dp_main_menu_hidden_download', __('Dpwap Plugin Download', 'download-plugin'), __(' Dpwap Plugin Download', 'download-plugin'), 'manage_options', 'plugin_plugin_download', array($this, 'plugin_plugin_download_func'));
    }

    // multiple upload function
    public function plugin_plugin_multiple_upload_func()
    {
        $pluginObj = new PluginUploader();
        $plugin_multiple_upload_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'multiple_upload_plugin.php';
        include_once $plugin_multiple_upload_file;
    }

    // single plugin activate function
    public function plugin_plugin_package_activate_func()
    {
        $featureObj = new PluginUploader();
        $plugin_feature_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'feature-package.php';
        include_once $plugin_feature_file;
    }

    // all plugins activate function
    public function plugin_plugin_all_activate_status_func()
    {
        $pluginObj = new PluginUploader();
        $plugin_active_status_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'activate-status.php';
        include_once $plugin_active_status_file;
    }

    public function plugin_load_plugin_admin_scripts()
    {
        if (!current_user_can('install_plugins')) return;

        global $pagenow;
        $isDpPage = 0;
        if (isset($_GET['page']) && ($_GET['page'] == 'mul_upload' || $_GET['page'] == 'activate-status' || $_GET['page'] == 'plugin-activate')) {
            $isDpPage = 1;
        }
        if ($pagenow == 'plugins.php' || $pagenow == 'plugin-install.php' || !empty($isDpPage)) {
            //wp_enqueue_script( 'up_admin_script', PLUGIN_URL.'assets/js/bootstrap.min.js', array('jquery'), PLUGIN_VERSION );
            wp_enqueue_script('up_admin_func', PLUGIN_PREMIUM_URL . 'assets/js/multiple.js', array(), PLUGIN_PREMIUM_VERSION);
            wp_register_style('plugin-admin-style', PLUGIN_PREMIUM_URL . 'assets/css/plugin-admin.css', array(), PLUGIN_PREMIUM_VERSION);
            wp_enqueue_style('plugin-admin-style');
            $plugin_string = array(
                "server_max_upload" => esc_html__('server maximum upload size limit', 'download-plugin'),
                "mb" => esc_html__('MB', 'download-plugin'),
                "valid_zip_message" => esc_html__('Please upload vaild .zip extension file.', 'download-plugin'),
                "max_20_upload" => esc_html__('Max. 20 uploads are supported at a time.', 'download-plugin'),
                "activate_message" => esc_html__('Plugin activated successfully.', 'download-plugin'),
                "activated_message" => esc_html__('Plugin activated', 'download-plugin'),
                "wait_text" => esc_html__('Please wait...', 'download-plugin'),
                "feature_message" => esc_html__('You are all set! Install and activate our following plugins from wordpress.org to add selected features.', 'download-plugin'),
                "select_feature" => esc_html__('Select at least one Feature', 'download-plugin'),
                "no_plugin_message" => esc_html__('Please select a plugin (or multiple plugins) to begin download.', 'download-plugin'),
                "max_30_dpwnload" => esc_html__('Max. 30 downloads are supported at a time', 'download-plugin'),
            );
            wp_localize_script('up_admin_func', 'plugin_string', $plugin_string);

            do_action('plugin_plugin_admin_enqueues');
        }
    }

    public function addDownloadLinks()
    {
        if (!current_user_can('install_plugins')) return;

        foreach ($this->plugin_all_plugins as $key => $value) {
            add_filter('plugin_action_links_' . $key, array($this, 'plugin_plugin_download_link'), 20, 2);
        }
    }

    public function plugin_plugin_download_link($links, $plugin_file)
    {
        if (!current_user_can('install_plugins')) return;

        if (strpos($plugin_file, '/') !== false) {
            $explode = explode('/', $plugin_file);
            $path    = $explode[0];
            $folder  = 1;
        } else {
            $path   = $plugin_file;
            $folder = 2;
        }
        // Add Customize link
        $plugin_name = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file)['Name'];
        // $customize_link = array(
        //     '<span class="plugin_customize-wrap">
        // <a href="#" class="plugin_customize_link" data-plugin="' . esc_attr($plugin_name) . '">' . esc_html__('Customize', 'download-plugin') . '</a></span>',
        // );
        $nonce = wp_create_nonce('bulk-plugins');
        $pluginDownloadLink = admin_url('admin.php?page=plugin_plugin&plugin_plugin_download=' . $path . '&f=' . $folder . '&_wpnonce=' . $nonce);
        $download_link = array(
            '<span class="plugin_download-wrap">
            <a href="' . esc_url($pluginDownloadLink) . '" class="plugin_download_link">' . esc_html__('Download', 'download-plugin') . '</a></span>',
        );

        

        return array_merge($links, $download_link);
    }

    public function add_download_plugin_bulk_actions($bulk_array)
    {
        if (!current_user_can('install_plugins')) return;

        $bulk_array_keys = array_keys($bulk_array);
        if (!empty($bulk_array_keys)) {
            $lastKey = end($bulk_array_keys);
            if ($lastKey == 'delete-selected') {
                unset($bulk_array['delete-selected']);
                $bulk_array['all_download'] = 'Download';
                $bulk_array['delete-selected'] = 'Delete';
            } else {
                $bulk_array['all_download'] = 'Download';
            }
        }
        return $bulk_array;
    }

    public function plugin_custom_admin_head_loader()
    {
        global $pagenow;
        $isDpPage = 0;
        if (isset($_GET['page']) && ($_GET['page'] == 'mul_upload' || $_GET['page'] == 'activate-status' || $_GET['page'] == 'plugin-activate')) {
            $isDpPage = 1;
        }
        if ($pagenow == 'plugins.php' || $pagenow == 'plugin-install.php' || !empty($isDpPage)) {
            $imgUrl = PLUGIN_PREMIUM_URL . '/assets/images/plugin-loader.gif';
            echo "<div id='pluginLoader'>";
            echo  "<img src='" . esc_url($imgUrl) . "'>";
            echo "<p>" . esc_html__('This may take few minutes based on the number and size of the plugins', 'download-plugin') . "</p></div>";
        }
    }

    /*public function plugin_plugin_setting_popup_func() {
        global $pagenow;
        if ( $pagenow == 'plugins.php' ) {
            $plugin_setting_file = PLUGIN_DIR.DS.'app'.DS.'Plugins'.DS.'templates'.DS.'plugin_setting.php';
            if( !get_option( 'plugin_popup_status' ) ) { ?>
                <script language="javascript">
                    jQuery(window).load(function() {
                            //jQuery('#plugin_modal').modal();
                        jQuery('#plugin_modal').toggle();
                        jQuery('#plugin_modal').toggleClass('in');
                        jQuery( "#plugin_modal .plugin-close, #plugin_modal .plugin_modal-ovalay" ).click(function() {     
                        jQuery('#plugin_modal').hide();
                       });
                            //jQuery('#plugin_modal').modal();
                    });
                </script>
                    <?php
                require_once $plugin_setting_file;
                add_option( 'plugin_popup_status', 1 );
           }
            require_once $plugin_setting_file;
        }
    }*/

    private function plugin_plugin_download_action()
    {
        if (!current_user_can('install_plugins')) return;
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'bulk-plugins')) {
            return;
        }
        $all_plugins    = array_keys($this->plugin_all_plugins);
        $plugins_arr    = array();
        $plugin_download = sanitize_text_field($_GET['plugin_plugin_download']);
        foreach ($all_plugins as $key => $value) {
            $explode = explode('/', $value);
            array_push($plugins_arr, $explode[0]);
        }
        if (in_array($plugin_download, $plugins_arr)) {
            $folder = sanitize_text_field($_GET['f']);
            if ($folder == 2) {
                $plugin_download = basename($plugin_download, '.php');
                $folder_path  = WP_PLUGIN_DIR . '/' . $plugin_download;
                if (!file_exists($folder_path)) {
                    mkdir($folder_path, 0777, true);
                }
                $source      = $folder_path . '.php';
                $destination = $folder_path . '/' . $plugin_download . '.php';
                copy($source, $destination);
            } else {
                $folder_path = WP_PLUGIN_DIR . '/' . $plugin_download;
            }
            $root_path = realpath($folder_path);
            $zip = new ZipArchive();
            $zip->open($folder_path . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
            /* $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $root_path ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach( $files as $name => $file ) {
                if ( !$file->isDir() ) {
                    $file_path	   = $file->getRealPath();
                    $relative_path = substr( $file_path, strlen( $root_path ) + 1 );
                    $zip->addFile( $file_path, $relative_path );
                }
            } */

            $plugin_slug = basename($root_path); // Use the folder name as the plugin slug
            $files = new RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root_path, \FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                // Skip hidden files and directories
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($root_path) + 1);
                // Add the base folder to the file paths inside the zip
                $relative_path_with_slug = $plugin_slug . '/' . $relative_path;
                // Skip any file or directory starting with a dot
                $parts = explode(DIRECTORY_SEPARATOR, $relative_path);
                $skip = false;
                foreach ($parts as $part) {
                    if (substr($part, 0, 1) === '.') {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue; // Skip this file or directory
                }

                if (!$file->isDir()) {
                    $zip->addFile($file_path, $relative_path_with_slug);
                }
            }



            $zip->close();
            if ($folder == 2) {
                $this->plugin_delete_temp_folder($folder_path);
            }
            // Download Zip
            $zip_file = $folder_path . '.zip';
            if (file_exists($zip_file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($zip_file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($zip_file));
                header('Set-Cookie:fileLoading=true');
                readfile($zip_file);
                unlink($zip_file);
                exit;
            }
        }
    }

    public function plugin_delete_temp_folder($path)
    {
        if (is_dir($path) === true) {
            $files = array_diff(scandir($path), array('.', '..'));
            foreach ($files as $file) {
                $this->plugin_delete_temp_folder(realpath($path) . '/' . $file);
            }
            return rmdir($path);
        } else if (is_file($path) === true) {
            return unlink($path);
        }
        return false;
    }

    //all plugins activate get ajax response code
    public function plugin_plugin_multiple_download_func()
    {
        if (!current_user_can('install_plugins')) return;
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-plugins')) {
            return;
        }
        $strPluginCount = (isset($_POST['plugin_count'])) ? sanitize_text_field($_POST['plugin_count']) : '0';
        if ($strPluginCount == '1') {
            if (file_exists(PLUGIN_PLUGINS_TEMP)) {
                $folder = PLUGIN_PLUGINS_TEMP;
                $files = glob("$folder/*");
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
        $plugin_download      = sanitize_text_field($_POST['pluginData']);
        $plugin_download_base = basename($plugin_download, '.php');
        $explode             = explode('/', $plugin_download);
        $folderpath          = $explode[0];

        if (!file_exists(PLUGINS_TEMP)) {
            mkdir(PLUGINS_TEMP, 0777, true);
        }

        $folder_path  = WP_PLUGIN_DIR . '/' . $folderpath;
        $zipPath = $folderpath . '.zip';
        if ($geturls = get_option("plugin_downloads_url")) {
            $getDwnurls = maybe_unserialize($geturls);
            array_push($getDwnurls, $zipPath);
            $plugins_arry = maybe_serialize($getDwnurls);
            update_option("plugin_downloads_url", $plugins_arry);
        } else {
            $plugins_arry[] = $zipPath;
            update_option("plugin_downloads_url", $plugins_arry);
        }

        $rlpath = PLUGINS_TEMP . '/' . $folderpath;
        $root_path = realpath($folder_path);
        $zip = new ZipArchive();
        $zip->open($rlpath . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path       = $file->getRealPath();
                $relative_path = substr($file_path, strlen($root_path) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
        $zip->close();
    }

    public function plugin_plugin_multiple_download_action()
    {
        if (!current_user_can('install_plugins')) return;

        global $pagenow;
        if ($pagenow == 'plugins.php' && isset($_GET['action']) && sanitize_text_field($_GET['action']) == 'multiple_download') {
            $plugin_plugins = maybe_unserialize(get_option('plugin_downloads_url'));
            if (!empty($plugin_plugins)) {
                foreach ($plugin_plugins as $pluginUrl) {
                    $downUrl = site_url() . "/wp-content/uploads/plugin_temp/" . $pluginUrl; ?>
                    <script language="javascript" type="text/javascript">
                        jQuery(document).ready(function() {
                            var iframe = document.createElement('iframe');
                            iframe.src = "<?php echo esc_url($downUrl); ?>";
                            iframe.style.display = 'none';
                            document.body.appendChild(iframe);
                        });
                    </script><?php
                            }
                        }
                        delete_option("plugin_downloads_url");
                    }
                }

                public function plugin_multiple_upload_admin_func()
                {
                    if (!current_user_can('install_plugins')) return;
                    global $pagenow;
                    if ($pagenow == 'plugin-install.php') {
                        $plugin_setting_file = PLUGIN_PREMIUM_DIR . DS . 'app' . DS . 'Plugins' . DS . 'templates' . DS . 'plugin_setting.php';
                        require_once $plugin_setting_file;
                        $redirect = admin_url('admin.php?page=mul_upload');
                        echo '<div class="wrap" id="btn_upload">
            <a id="mul_upload" class="page-title-action show" href="' . esc_url($redirect) . '">
            <span class="upload">' . esc_html__('Upload Multiple Plugins', 'download-plugin') . '</span></a>
            </div>';
                    }
                }

                public function plugin_plugin_activate_func()
                {
                    if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'plugin_single_plugin_activate') && current_user_can('install_plugins')) {
                        $waplugin = sanitize_text_field($_POST['plugin_url']);
                        $waplugins = get_option('active_plugins');
                        if ($waplugins) {
                            if (!in_array($waplugin, $waplugins)) {
                                array_push($waplugins, $waplugin);
                                update_option('active_plugins', $waplugins);
                            }
                        }
                    }
                }

                // public function plugin_plugin_feature_select_func() {
                //     $waplugin = sanitize_text_field( $_POST['plugin_feature'] );
                //     foreach ( $waplugin as $value ) {
                //         if( $value == 1 ){ 
                //             $feature1 = 1;
                //         }
                //         elseif ( ( $value >= 2 ) && ( $value <= 8 ) ) {
                //             $feature2 = 1;
                //         }
                //         elseif ( ( $value >= 9 ) && ( $value <= 15 ) ) {
                //             $feature3 = 1;
                //         }
                //         else{
                //             $feature4 = 1;
                //         }
                //     }
                //     $feature_file = PLUGIN_DIR.DS.'app'.DS.'Plugins'.DS.'templates'.DS.'plugin-add-feature.php';
                //     require_once $feature_file;
                //     wp_die();
                // }
            }
