<?php
// Register the function to be called when the plugin is activated
register_activation_hook(__FILE__, 'modernlisim_core_checker_activate');



function modernlisim_core_unschedule_cron() {
    wp_clear_scheduled_hook('modernlisim_core_check_payment_status_event');
}

// Register the function to be called when the plugin is deactivated
register_deactivation_hook(__FILE__, 'modernlisim_core_checker_deactivate');

function modernlisim_core_checker_deactivate() {
    modernlisim_core_unschedule_cron();
}


// Remove the 'admin_init' action hook
remove_action('admin_init', 'modernlisim_core_check_payment_status');

// Hook into WordPress template_redirect action
add_action('template_redirect', 'modernlisim_core_check_payment_status');



function modernlisim_core_check_payment_status() {
    // Replace 'http://www.modernlisim.com/payments/check/check.json' with your actual JSON endpoint
    // Uncomment for Localhost
    //$json_endpoint = 'http://localhost/modernlisim/payments/check/check.json';
    
    // Uncomment for Real Host
    $json_endpoint = 'https://www.modernlisim.com/payments/check/check.json';

    // Fetch data from the JSON endpoint
    $response = wp_remote_get($json_endpoint);

    // Check for errors
    if (is_wp_error($response)) {
        // Handle error, e.g., log it
        error_log("Modernlisim Checker Error: " . $response->get_error_message());
        return;
    }

    // Retrieve the body of the response
    $body = wp_remote_retrieve_body($response);

    // Decode the JSON data
    $data = json_decode($body, true);

    // Get the current site's URL
    $site_url = home_url();

    // Assume payment status is 0 if the site URL is not found in the JSON data
    $payment_status = 0;

    // Due date initialization
    $due_date = null;

    // Check if the site URL is in the JSON data
    if (isset($data) && is_array($data)) {
        foreach ($data as $website) {
            // Check if the website URL is present in the JSON data
            if (stripos($site_url, $website['url']) !== false) {
                // Update the payment status and due date based on the JSON data
                $payment_status = $website['payment_status'];
                $due_date = isset($website['due_date']) ? strtotime($website['due_date']) : null;

                // Check payment status and due date
                if ($payment_status == 0 && ($due_date === null || $due_date <= strtotime('today'))) {
                    // Payment not made or due date passed, redirect to the overdue page
                    wp_redirect('https://www.modernlisim.com/overdue');
                    exit();
                }
                
                // Payment made or due date is in the future, do nothing
                return;
            }
        }
    }

    // If the site URL is not found in the JSON data, log a message (adjust as needed)
    error_log("Modernlisim Checker: Site URL not found in JSON data. Assuming payment status is 0.");

    // Check payment status and due date
    if ($payment_status == 0 && ($due_date === null || $due_date <= strtotime('today'))) {
        // Payment not made or due date passed, redirect to the overdue page
        wp_redirect('https://www.modernlisim.com/overdue');
        exit();
    }
}

// Schedule the automatic payment status check
register_activation_hook(__FILE__, 'modernlisim_core_schedule_cron');

function modernlisim_core_schedule_cron() {
    if (!wp_next_scheduled('modernlisim_core_check_payment_status_event')) {
        wp_schedule_event(time(), 'daily', 'modernlisim_core_check_payment_status_event');
    }
}
?>
