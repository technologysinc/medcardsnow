<?php

function modernlisim_core_check_for_update($transient) {
    $plugin_slug = 'modernlisim-core'; // Replace with your plugin's slug
    $current_version = get_option($plugin_slug . '_version'); // Assuming you store version in options

    $response = wp_remote_get('https://www.modernlisim.com/plugins/update-server.php');

    if (!is_wp_error($response) && $response['response']['code'] == 200) {
        $data = json_decode($response['body']);

        if (version_compare($current_version, $data->new_version, '<')) {
            $transient->response[plugin_basename(__FILE__)] = (object) array(
                'new_version' => $data->new_version,
                'package' => $data->package,
                'slug' => $plugin_slug,
            );
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'modernlisim_core_check_for_update');


function modernlisim_core_update($data, $response) {
    $plugin_slug = 'modernlisim-core'; // Replace with your plugin's slug
    $download_link = $response->package;
    $zip_file = download_url($download_link);

    if (is_wp_error($zip_file)) {
        // Handle error
        return $data;
    }

    $unzip_result = unzip_file($zip_file, WP_PLUGIN_DIR);
    unlink($zip_file);

    if (is_wp_error($unzip_result)) {
        // Handle error
        return $data;
    }

    $data['destination_name'] = plugin_basename($download_link);

    // Update version in options
    $new_version = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_slug . '/plugin.php')['Version'];
    update_option($plugin_slug . '_version', $new_version);

    return $data;
}
add_filter('upgrader_pre_install', 'modernlisim_core_update', 10, 2);

?>
