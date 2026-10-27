<?php
/*
Plugin Name: Modernlisim Core
Description: Modernlisim Core Plugin to add several functionalities to your website. DO NOT DEACTIVATE.
Version: 3.7.0
Author: Modernlisim
*/

//Add the [mod_credit] to display the modernlisim credit in the footer and [current_year] to display the year e.g. 2024

// Define the version constant
// define('MODERNLISIM_CORE_VERSION', '3.6.9');

$plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);
define('MODERNLISIM_CORE_VERSION', $plugin_data['Version']);


// Include the checker functionality
require_once plugin_dir_path(__FILE__) . 'checker.php';

// Include the widget functionality
require_once plugin_dir_path(__FILE__) . 'widget.php';

// Include the update check functionality
require_once plugin_dir_path(__FILE__) . 'plugin-update.php';

// Include tracker.php to start tracking visitors
include(plugin_dir_path( __FILE__ ) . 'pages/tracker.php');

// Register the action hook for handling AJAX requests
add_action('wp_ajax_update_xmlrpc_status', 'handle_xmlrpc_toggle');

// Add the Ajax URL to the front-end
function add_ajax_url_to_frontend() {
    ?>
    <script type="text/javascript">
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    <?php
}
add_action('wp_head', 'add_ajax_url_to_frontend');

// Schedule update checks every 12 hours
if (!wp_next_scheduled('check_plugin_updates')) {
    wp_schedule_event(time(), 'twicedaily', 'check_plugin_updates');
}
// Enqueue plugin styles and scripts
function modernlisim_enqueue_assets() {
    // Check if the current page is the Modernlisim Core page
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'modernlisim_core') {
        // Get the file modification time to bust the cache
        $css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/modernlisim-admin.css');
        $js_version = filemtime(plugin_dir_path(__FILE__) . 'assets/script.js');
        
        // Enqueue admin styles (modernlisim-admin.css) only on the specific page, with cache busting
        wp_enqueue_style('modernlisim-admin', plugin_dir_url(__FILE__) . 'assets/modernlisim-admin.css', array(), $css_version, 'all');
        
        // Enqueue script (script.js) with cache busting
        wp_enqueue_script('modernlisim-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), $js_version, true); // true for footer load

        // Localize script with correct handle for ajaxurl
        wp_localize_script('modernlisim-script', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    // Always enqueue frontend styles (style.css) with cache busting
    $frontend_css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/style.css');
    wp_enqueue_style('modernlisim-style', plugin_dir_url(__FILE__) . 'assets/style.css', array(), $frontend_css_version, 'all');
}
add_action('wp_enqueue_scripts', 'modernlisim_enqueue_assets'); // For frontend styles/scripts
add_action('admin_enqueue_scripts', 'modernlisim_enqueue_assets'); // For admin styles/scripts



// Hook into the scheduled update check
add_action('check_plugin_updates', 'check_for_plugin_updates');

// Function to check for plugin updates
function check_for_plugin_updates() {
    $update_check_url = admin_url('admin-ajax.php?action=check_plugin_updates');
    wp_remote_get($update_check_url);
}

// Add menu page for the plugin in WordPress Admin
function modernlisim_add_menu() {
    add_menu_page(
        'Modernlisim Core', // Page title
        'Modernlisim', // Menu title
        'manage_options', // Capability required to access
        'modernlisim_core', // Menu slug
        'modernlisim_display_main_page', // Function to display the content
        plugin_dir_url(__FILE__) . 'assets/icons/modernlisim-icon.svg', // Custom icon
        1 // Position
    );
}
add_action('admin_menu', 'modernlisim_add_menu');

// function redirect_admin_to_custom_page() {
//     // Check if the user is an admin and on the dashboard
//     if (is_admin() && current_user_can('administrator') && empty($_GET['page'])) {
//         wp_redirect(admin_url('admin.php?page=modernlisim_core'));
//         exit;
//     }
// }
// add_action('admin_init', 'redirect_admin_to_custom_page', 1); // Priority set to 1


// Function to display the main page content
function modernlisim_display_main_page() {
    // Include the main page content from the pages directory
    include plugin_dir_path(__FILE__) . 'pages/main-page.php';
}   


// Add an icon or image to the plugin row on the Plugins page
add_filter('plugin_row_meta', 'modernlisim_core_plugin_row_meta', 10, 2);

function modernlisim_core_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $icon_url = plugin_dir_url(__FILE__) . './assets/images/favicon-16x16.png';
        $icon = '<img src="' . esc_url($icon_url) . '" alt="Plugin Icon" style="margin-right: 5px; vertical-align: middle;" />';
        $links[] = $icon . '<a href="https://www.modernlisim.com" target="_blank">Plugin Website</a>';
    }
    return $links;
}


// Add your custom shortcode function
function mod_credit_shortcode() {
    return 'Designed with ❤️ by <a href="https://www.modernlisim.com">Modernlisim</a>.';
}
add_shortcode('mod_credit', 'mod_credit_shortcode');



function modernlisim_year_shortcode() {
    // Get the current year
    $current_year = date('Y');
    return $current_year;
}
add_shortcode('current_year', 'modernlisim_year_shortcode');



//Random Quote

// Function to display success message
function display_dashboard_success_message() {
    $quotes = array(
        "The only way to do great work is to love what you do. - Steve Jobs",
        "Success is not final, failure is not fatal: It is the courage to continue that counts. - Winston Churchill",
        "Believe you can and you're halfway there. - Theodore Roosevelt",
        "Success is not the key to happiness. Happiness is the key to success. If you love what you are doing, you will be successful. - Albert Schweitzer",
        "The road to success and the road to failure are almost exactly the same. - Colin R. Davis",
        "I find that the harder I work, the more luck I seem to have. - Thomas Jefferson",
        "Don't be afraid to give up the good to go for the great. - John D. Rockefeller",
        "The secret of success is to do the common thing uncommonly well. - John D. Rockefeller Jr.",
        "Success usually comes to those who are too busy to be looking for it. - Henry David Thoreau",
        "Success is walking from failure to failure with no loss of enthusiasm. - Winston Churchill"
    );

    $random_quote = $quotes[array_rand($quotes)]; // Select a random quote

    $message = "<strong>Modernlisim wish you success. Stay Motivated: $random_quote</strong>";

    ?>
    <div class="notice notice-success is-dismissible">
        <p style="text-transform:capitalize;"><?php echo $message; ?></p>
    </div>
    <?php
}
add_action('admin_notices', 'display_dashboard_success_message');


//digital service message
// Function to display warning message on WooCommerce orders page

function display_order_page_warning_message() {
    // Check if we're on the WooCommerce orders page or the shop orders page
    if ((isset($_GET['page']) && $_GET['page'] === 'woocommerce-orders') ||
        (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order')) {
        // Get the referring URL if available
        $referring_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Extract the domain name from the referring URL
        $domain_name = parse_url($referring_url, PHP_URL_HOST);

        // Add tracking parameters to the URL
        $tracking_params = http_build_query(array(
            'utm_source' => $domain_name,
            'utm_medium' => 'admin_notice'
        ));

        // Append tracking parameters to the URL
        $url_with_tracking = 'https://www.modernlisim.com/digital-marketing-service/?' . $tracking_params;

        // Generate the message with the hyperlink
        $message = 'If you want to get more orders and generate more revenue, consider Modernlisim\'s digital marketing service. <a href="' . esc_url($url_with_tracking) . '" target="_blank">Click here</a> for more information.';

        // Display the notice
        echo '<div class="notice notice-warning">';
        echo '<p>' . $message . '</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'display_order_page_warning_message');




// Function to display the address
function mod_address_shortcode() {
    $address = "1234 Innovation Drive,<br>Suite 567, Creativity City, CC 12345, United States";
    return $address;
}

// Function to display the support phone number
function mod_support_shortcode() {
    $support_phone = "+447432363660";
    return $support_phone;
}

// Function to display the WhatsApp number
function mod_whatsapp_shortcode() {
    $whatsapp_number = "+923003969068";
    return $whatsapp_number;
}

// Register shortcodes
add_shortcode('mod_address', 'mod_address_shortcode');
add_shortcode('mod_support', 'mod_support_shortcode');
add_shortcode('mod_whatsapp', 'mod_whatsapp_shortcode');

//Usage: [mod_address], [mod_support], and [mod_whatsapp]


function modernlisim_discount_notification() {
    // Get the current host domain
    $host_domain = $_SERVER['HTTP_HOST'];
    
    // Construct the notification message
    $message = sprintf(
        'Want to generate more orders, and visitors to your website? Claim your Exclusive 10%% discount for Digital Marketing Service for your brand %s. <a href="https://www.modernlisim.com/digital-marketing-service/" target="_blank">Click here to learn more</a>.',
        $host_domain
    );

    // Display the message as a success notification
    echo '<div class="notice notice-success is-dismissible">
            <p>' . $message . '</p>
          </div>';
}

// Hook the function to the admin_notices action
add_action('admin_notices', 'modernlisim_discount_notification');

// Orders Statistics








?>
