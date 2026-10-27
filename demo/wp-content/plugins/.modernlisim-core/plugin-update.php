<?php
// Plugin Update Logic
add_filter('pre_set_site_transient_update_plugins', 'check_for_plugin_update');

function check_for_plugin_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $update_data_url = 'https://www.modernlisim.com/plugins/update.json';
    $response = wp_remote_get($update_data_url);

    if (!is_wp_error($response)) {
        $update_data = json_decode(wp_remote_retrieve_body($response));

        // Define the version constant if not already defined
        if (!defined('MODERNLISIM_CORE_VERSION')) {
            define('MODERNLISIM_CORE_VERSION', $update_data->version);
        }

        if (version_compare(MODERNLISIM_CORE_VERSION, $update_data->version, '<')) {
            $transient->response['modernlisim-core/modernlisim-core.php'] = (object) array(
                'slug' => 'modernlisim-core',
                'new_version' => $update_data->version,
                'url' => 'https://www.modernlisim.com',
                'package' => $update_data->download_url,
            );
        }
    }

    return $transient;
}
?>
