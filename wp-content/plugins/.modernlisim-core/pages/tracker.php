<?php
// Start session to track individual users
session_start();

// Path to the JSON file where visitor data will be stored
$json_file_path = plugin_dir_path(__FILE__) . 'visitors.json';

// Function to record a visit
function record_visit()
{
    global $json_file_path;

    // Check if the current request is for the WordPress admin area
    if (is_admin()) {
        return; // Exit if it's an admin page
    }

    // Exclude WordPress login page
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
        return; // Exit if it's a request to wp-login.php
    }

    // Exclude XML-RPC requests
    if (strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false) {
        return; // Exit if it's a request to xmlrpc.php
    }

    // Exclude REST API requests
    if (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
        return; // Exit if it's a REST API request
    }

    // Exclude Admin AJAX requests
    if (strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php') !== false) {
        return; // Exit if it's an admin AJAX request
    }

    // Exclude Pingbacks
    if (isset($_SERVER['HTTP_X_PINGBACK'])) {
        return; // Exit if it's a pingback request
    }

    // Exclude wp-cron.php from being tracked
    if (strpos($_SERVER['REQUEST_URI'], 'wp-cron.php') !== false) {
        return; // Exit if it's a request to wp-cron.php
    }

    // Exclude RSS feed requests
    if (strpos($_SERVER['REQUEST_URI'], '/feed/') !== false) {
        return; // Exit if it's a request to a feed
    }

    // Exclude search pages
    if (strpos($_SERVER['REQUEST_URI'], '/search/') !== false) {
        return; // Exit if it's a search request
    }

    // Exclude bots and crawlers based on user agent
    $bots = [
        'googlebot',
        'bingbot',
        'slurp',
        'duckduckbot',
        'yandexbot',
        'baiduspider',
        'facebookexternalhit',
        'twitterbot',
        'whatsapp',
        'bot',
        'crawler'
    ];
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    foreach ($bots as $bot) {
        if (strpos($user_agent, $bot) !== false) {
            return; // Exit if it's a bot request
        }
    }

    // Exclude requests with the WordPress admin bar (logged-in users)
    if (is_user_logged_in() && isset($_SERVER['HTTP_X_WP_ADMIN_BAR'])) {
        return; // Exit if it's a request with the admin bar
    }

    // Exclude localization requests (e.g., language-based URL parameters)
    if (strpos($_SERVER['REQUEST_URI'], 'lang=') !== false) {
        return; // Exit if it's a localization request
    }

    // Check if the session already has a visitor ID, if not, generate a new one
    if (!isset($_SESSION['visitor_id'])) {
        $_SESSION['visitor_id'] = uniqid('visitor_', true);
    }

    // Exclude static resource requests (e.g., CSS, JS, images)
    $static_extensions = ['.jpg', '.jpeg', '.png', '.gif', '.css', '.js', '.svg', '.woff', '.woff2', '.ttf'];
    foreach ($static_extensions as $ext) {
        if (strpos($_SERVER['REQUEST_URI'], $ext) !== false) {
            return; // Exit if it's a static resource request
        }
    }

    // Exclude WP-CLI requests
    if (defined('WP_CLI') && WP_CLI) {
        return; // Exit if it's a WP-CLI request
    }


    // Exclude frontend admin-related AJAX requests
    if (strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php?action=') !== false) {
        return; // Exit if it's an admin-related AJAX request
    }

    // Exclude preview pages
    if (isset($_GET['preview']) && $_GET['preview'] == 'true') {
        return; // Exit if it's a preview page
    }

    // Check if WooCommerce is active before calling is_product()
    if (!is_singular() && !(function_exists('is_product') && is_product())) {
    return; // Exit if it's not a page, post, or WooCommerce product
    }


    // Get the current timestamp and the page URL
    $timestamp = time();
    $page_url = $_SERVER['REQUEST_URI'];
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'direct';

    // Prepare visitor data
    $visitor_data = [
        'visitor_id' => $_SESSION['visitor_id'],
        'timestamp' => $timestamp,
        'page_url' => $page_url,
        'referrer' => $referrer // Store the referrer
    ];

    // Check if visitors.json exists and load existing data
    if (file_exists($json_file_path)) {
        $data = json_decode(file_get_contents($json_file_path), true);
    } else {
        $data = [];
    }

    // Add new visitor data
    $data[] = $visitor_data;

    // Filter out visits older than 30 days
    $data = array_filter($data, function ($visit) use ($timestamp) {
        return ($timestamp - $visit['timestamp']) <= 2592000; // 30 days in seconds
    });

    // Save the updated data back to the file
    file_put_contents($json_file_path, json_encode(array_values($data)));
}


// Ensure WordPress environment is loaded before recording visits
function init_tracker() {
    // Make sure the WordPress environment is loaded
    if (function_exists('is_user_logged_in')) {
        record_visit();
    }
}

add_action('wp', 'init_tracker');  // Hook the visit recording function to 'wp' action




?>