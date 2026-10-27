<?php
// Get the list of plugins that require updates
function get_plugins_that_need_update()
{
    // Force refresh the plugin update data
    delete_site_transient('update_plugins');
    wp_update_plugins(); // Force immediate check for updates
    $update_data = get_site_transient('update_plugins');

    // Check if data is available
    if (empty($update_data)) {
        // If still empty, log error or handle accordingly
        error_log('Unable to retrieve plugin update data.');
        return [];
    }

    // Get the list of installed plugins
    $all_plugins = get_plugins();
    $plugins_to_update = [];

    // Loop through plugins and check if an update is available
    foreach ($all_plugins as $plugin_file => $plugin_data) {
        // Check if an update is available for the plugin
        if (isset($update_data->response[$plugin_file])) {
            $plugin_current_version = $plugin_data['Version'];
            $plugin_new_version = $update_data->response[$plugin_file]->new_version;
            if (version_compare($plugin_current_version, $plugin_new_version, '<')) {
                $plugins_to_update[] = [
                    'name' => $plugin_data['Name'],
                    'slug' => dirname($plugin_file),
                    'current_version' => $plugin_current_version,
                    'new_version' => $plugin_new_version,
                    'update_url' => admin_url('update.php')
                ];
            }
        }
    }

    return $plugins_to_update;
}



// AJAX handler for fetching WordPress and PHP version info
function my_get_version_info()
{
    // --- WordPress Version Info ---
    $current_wp_version = get_bloginfo('version');
    $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
    $latest_wp_version = 'Unknown';
    if (is_array($response) && !is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        $latest_wp_version = $data->offers[0]->current;
    }

    // --- PHP Version Info ---
    $current_php_version = phpversion();
    $response_php = wp_remote_get('https://www.php.net/downloads.php');
    $latest_php_version = 'Unknown';
    if (is_array($response_php) && !is_wp_error($response_php)) {
        $body_php = wp_remote_retrieve_body($response_php);
        preg_match('/PHP ([0-9]+\.[0-9]+\.[0-9]+) \(.*\)/', $body_php, $matches);
        if (!empty($matches)) {
            $latest_php_version = $matches[1];
        }
    }

    wp_send_json_success(array(
        'current_wp_version' => $current_wp_version,
        'latest_wp_version' => $latest_wp_version,
        'current_php_version' => $current_php_version,
        'latest_php_version' => $latest_php_version,
    ));
}
add_action('wp_ajax_get_version_info', 'my_get_version_info');


// Get total users, total admins, and total customers
function get_total_users_and_roles()
{
    // Get total users
    $total_users = count_users();
    $total_users_count = $total_users['total_users'];

    // Get total admins
    $admin_users = get_users(array('role' => 'Administrator'));
    $total_admins_count = count($admin_users);

    // Get total customers
    $customer_users = get_users(array('role' => 'customer'));
    $total_customers_count = count($customer_users);

    return array(
        'total_users' => $total_users_count,
        'total_admins' => $total_admins_count,
        'total_customers' => $total_customers_count
    );
}


// Path to the JSON file where visitor data is stored
$json_file_path = plugin_dir_path(__FILE__) . 'visitors.json';

// Check if visitors.json exists and is not empty
if (file_exists($json_file_path) && filesize($json_file_path) > 0) {
    // Get visitor data from the JSON file
    $visitor_data = json_decode(file_get_contents($json_file_path), true);

    // Ensure the JSON data is valid
    if ($visitor_data === null) {
        $visitor_data = []; // Initialize empty array if JSON is invalid
    }
} else {
    $visitor_data = []; // Initialize empty array if file doesn't exist or is empty
}

// Group visits by page URL
$page_visits = [];
foreach ($visitor_data as $visit) {
    $page_visits[$visit['page_url']][] = $visit;
}

// Sort the pages by the number of visits (in descending order)
arsort($page_visits);

// Get the top 10 pages
$top_pages = array_slice($page_visits, 0, 10);

// Prepare data for chart
$now = time();
$visits_24hrs = array_filter($visitor_data, function ($visit) use ($now) {
    return ($now - $visit['timestamp']) <= 86400; // 24 hours in seconds
});
$visits_7days = array_filter($visitor_data, function ($visit) use ($now) {
    return ($now - $visit['timestamp']) <= 604800; // 7 days in seconds
});
$visits_30days = $visitor_data; // Already filtered to 30 days

$chart_data = [
    '24hrs' => count($visits_24hrs),
    '7days' => count($visits_7days),
    '30days' => count($visits_30days),
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="wrap">
    <header class="modernlisim-core-header">
        <!-- Logo on the left -->


        <!-- Welcome text and user role on the left -->
        <div class="left-content">
            <img class="icon"
                src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/modernlisim-logo-dark.svg'; ?>"
                alt="Modernlisim" onclick="window.open('https://www.modernlisim.com', '_blank');"
                onmouseover="this.src='<?php echo plugin_dir_url(__FILE__) . '../assets/images/modernlisim-logo-red.svg'; ?>';"
                onmouseout="this.src='<?php echo plugin_dir_url(__FILE__) . '../assets/images/modernlisim-logo-dark.svg'; ?>';"
                style="cursor: pointer; transition: opacity 0.3s ease;">
            <div class="welcome-container">
                <p class="modernlisim-welcome">Welcome <?php echo wp_get_current_user()->first_name; ?></p>
                <?php
                // Get the current user object
                $current_user = wp_get_current_user();

                // Get the user's roles
                $user_roles = $current_user->roles;

                // Display the first role (typically the highest priority role, like 'administrator')
                if (!empty($user_roles)) {
                    echo '<p class="user-role">(' . ucfirst($user_roles[0]) . ')</p>';
                }
                ?>
            </div>
        </div>

        <!-- Buttons on the right -->
        <div class="right-content">
            <div class="button-container">
                <button onclick="window.open(window.location.origin, '_blank');" class="preview-button">Preview
                    Website</button>

                <button onclick="window.open('https://www.modernlisim.com', '_blank');" class="visit-button">Go to
                    Modernlisim <svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M2.5 5.24609C2.5 3.72731 3.73122 2.49609 5.25 2.49609H6C6.41421 2.49609 6.75 2.83188 6.75 3.24609C6.75 3.66031 6.41421 3.99609 6 3.99609H5.25C4.55964 3.99609 4 4.55574 4 5.24609V10.6842C4 11.3745 4.55964 11.9342 5.25 11.9342H10.7508C11.4411 11.9342 12.0008 11.3745 12.0008 10.6842V9.99829C12.0008 9.58408 12.3366 9.24829 12.7508 9.24829C13.165 9.24829 13.5008 9.58408 13.5008 9.99829V10.6842C13.5008 12.203 12.2696 13.4342 10.7508 13.4342H5.25C3.73122 13.4342 2.5 12.203 2.5 10.6842V5.24609ZM12 5.05906L8.03033 9.02873C7.73744 9.32162 7.26256 9.32162 6.96967 9.02873C6.67678 8.73583 6.67678 8.26096 6.96967 7.96807L10.9393 3.9984L9 3.9984C8.58579 3.9984 8.25 3.66261 8.25 3.2484C8.25 2.83418 8.58579 2.4984 9 2.4984L12.25 2.4984C12.9404 2.4984 13.5 3.05804 13.5 3.7484V6.9984C13.5 7.41261 13.1642 7.7484 12.75 7.7484C12.3358 7.7484 12 7.41261 12 6.9984V5.05906Z"
                            fill="#3857E9"></path>
                    </svg></button>

            </div>
        </div>
    </header>

    <!-- <h1>Modernlisim</h1> -->
    <div class="text-title">
        <h1>Modernlisim</h1>
    </div>
    <div class="main-page-container">

        <div class="modernlisim-cards">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">

                        <img class="icon"
                            src="<?php echo plugin_dir_url(__FILE__) . '../assets/icons/wordpress.svg'; ?>"
                            alt="WordPress">

                        <h3>WordPress Version</h3>
                    </div>
                    <div class="card-label">
                        <?php
                        // Get the current WordPress version
                        $current_wp_version = get_bloginfo('version');

                        // Fetch the latest WordPress version from WordPress.org API
                        $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
                        $latest_wp_version = 'Unknown';

                        if (is_array($response) && !is_wp_error($response)) {
                            $body = wp_remote_retrieve_body($response);
                            $data = json_decode($body);
                            $latest_wp_version = $data->offers[0]->current;
                        }

                        // Compare versions and output badge
                        if (version_compare($current_wp_version, $latest_wp_version, '>=')) {
                            echo '<span class="badge badge-success">' . esc_html($current_wp_version) . '</span>';
                        } else {
                            echo '<span class="badge badge-danger">' . esc_html($current_wp_version) . ' (Outdated)</span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="card-body">
                    <p>
                        <?php
                        if (version_compare($current_wp_version, $latest_wp_version, '>=')) {
                            echo 'Running the latest version';
                        } else {
                            echo 'Outdated version, please update!';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <img class="icon" src="<?php echo plugin_dir_url(__FILE__) . '../assets/icons/php.svg'; ?>"
                            alt="PHP">
                        <h3>PHP Version</h3>
                    </div>
                    <div class="card-label">
                        <?php
                        // Get the current PHP version
                        $current_php_version = phpversion();

                        // Fetch the latest PHP version from PHP.net (scrape the downloads page)
                        $response_php = wp_remote_get('https://www.php.net/downloads.php');
                        $latest_php_version = 'Unknown';

                        if (is_array($response_php) && !is_wp_error($response_php)) {
                            $body_php = wp_remote_retrieve_body($response_php);

                            // Regular expression to extract the latest PHP version from the downloads page
                            preg_match('/PHP ([0-9]+\.[0-9]+\.[0-9]+) \(.*\)/', $body_php, $matches);
                            if (!empty($matches)) {
                                $latest_php_version = $matches[1];
                            }
                        }

                        // Compare versions and output badge
                        if (version_compare($current_php_version, $latest_php_version, '>=')) {
                            echo '<span class="badge badge-success">' . esc_html($current_php_version) . '</span>';
                        } else {
                            echo '<span class="badge badge-danger">' . esc_html($current_php_version) . ' (Outdated)</span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="card-body">
                    <p>
                        <?php
                        if (version_compare($current_php_version, $latest_php_version, '>=')) {
                            echo 'Running the latest version';
                        } else {
                            echo 'Outdated version, please update!';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="card" style="max-width:75% !important;">
                <div class="card-title">
                    <h3>Plugins Needing Update</h3>
                </div>
                <div class="card__body">
                    <?php
                    // Fetch plugins requiring update
                    $plugins_to_update = get_plugins_that_need_update();

                    if (empty($plugins_to_update)) {
                        echo '<p>No plugins require updates.</p>';
                    } else {
                        echo '<table class="modernlisim-plugin-table wp-list-table widefat fixed striped" style="border-collapse: collapse; width: 100%;">';
                        echo '<thead><tr><th style="width: 85%;">Plugin</th><th style="width: 15%;">Action</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($plugins_to_update as $plugin) {
                            echo '<tr>';
                            echo '<td>';
                            echo '<strong>' . esc_html($plugin['name']) . '</strong>';
                            // Add the ? mark button with a tooltip
                            echo '<span class="tooltip" style="cursor: pointer; color: #0073aa; margin-left: 5px;" title="Make sure to backup your website before updating. This may break your website functionality. Or contact Modernlisim.com team.">?</span><br>';
                            echo '<span class="current-version" style="color: red;">Outdated Version: ' . esc_html($plugin['current_version']) . '</span><br>';
                            echo '<span class="new-version" style="color: green;">Latest Version: ' . esc_html($plugin['new_version']) . '</span>';
                            echo '</td>';
                            echo '<td><a href="' . esc_url($plugin['update_url']) . '" class="button button-primary">Go to Update Page</a></td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                    }
                    ?>


                </div>
            </div>

            <div class="card" style="max-width: 338px;">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Total Users</h3>
                    </div>
                </div>
                <div class="card-body">
                    <p><?php
                    $user_data = get_total_users_and_roles();
                    echo 'Total Users: ' . $user_data['total_users'];
                    ?></p>
                </div>
            </div>

            <div class="card" style="max-width: 338px;">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Total Admins</h3>
                    </div>
                </div>
                <div class="card-body">
                    <p><?php
                    echo 'Total Admins: ' . $user_data['total_admins'];
                    ?></p>
                </div>
            </div>

            <div class="card" style="max-width: 338px;">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Total Customers</h3>
                    </div>
                </div>
                <div class="card-body">
                    <p><?php
                    echo 'Total Customers: ' . $user_data['total_customers'];
                    ?></p>
                </div>
            </div>


            <div class="card-container"
                style="display: flex; justify-content: space-between; max-width: 75% !important; gap:1rem;">
                <!-- First Card for Visitor Chart -->
                <div class="card"
                    style="flex: 1; min-height: 300px; min-width:520px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(155, 155, 155, 0.1); padding: 20px;">
                    <h3 style="color: #333;">Visitor Statistics</h3>
                    <?php if (empty($chart_data)): ?>
                        <p>Please check back later.</p>
                    <?php else: ?>
                        <canvas id="visitorChart"></canvas>
                        <script>
                            var ctx = document.getElementById('visitorChart').getContext('2d');
                            var visitorChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['Last 24 Hours', 'Last 7 Days', 'Last 30 Days'],
                                    datasets: [{
                                        label: 'Visitors',
                                        data: <?php echo json_encode(array_values($chart_data)); ?>,
                                        backgroundColor: ['#4CAF50', '#2196F3', '#FF9800'],
                                        borderColor: ['#388E3C', '#1976D2', '#F57C00'],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        </script>
                    <?php endif; ?>
                </div>

                <!-- Second Card for Top 10 Trending Pages -->
                <div class="card"
                    style="flex: 1; min-height: 300px; min-width:520px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(155, 155, 155, 0.1); padding: 20px;">
                    <h3 style="color: #333;">Top 10 Trending Pages</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 8px; background-color: #2196F3; color: white;">
                                    Page</th>
                                <th style="text-align: left; padding: 8px; background-color: #2196F3; color: white;">
                                    Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_pages)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; padding: 8px; border: 1px solid #ddd;">No
                                        data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_pages as $page => $visits): ?>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo $page; ?></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo count($visits); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>






        </div>
    </div>
</div>