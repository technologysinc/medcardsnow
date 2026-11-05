<?php
/**
 * MCN Core module: Authorize.Net settings
 * Admin page for the gateway credentials.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


add_action('admin_menu', function () {
    add_menu_page(
        'Custom Authorization',
        'Custom Authorization',
        'manage_options',
        'custom-authorization',
        'cap_render_settings_page',
        'dashicons-lock',
        58
    );
});

add_action('admin_init', function () {
    register_setting(
        'cap_settings_group',
        CAP_SETTINGS_OPTION,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'cap_sanitize_settings',
            'default'           => array(),
        )
    );

    add_settings_section(
        'cap_authorize_section',
        'Authorize.net Configuration',
        function () {
            echo '<p>Enter your Authorize.net credentials below. Use Sandbox/Test Mode while testing and Production Mode for live payments.</p>';
        },
        'custom-authorization'
    );

    add_settings_field(
        'cap_enabled',
        'Enable Payments',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'enabled', 'label' => 'Enable the Custom Authorize payment gateway.')
    );

    add_settings_field(
        'cap_sandbox',
        'Sandbox / Test Mode',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'sandbox', 'label' => 'Use Authorize.net Sandbox for testing. Uncheck for live Production payments.')
    );

    add_settings_field(
        'cap_api_login_id',
        'API Login ID',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'api_login_id', 'type' => 'text', 'description' => 'Your Authorize.net API Login ID.')
    );

    add_settings_field(
        'cap_transaction_key',
        'Transaction Key',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'transaction_key', 'type' => 'password', 'description' => 'Your Authorize.net Transaction Key. This is stored in WordPress options and never displayed in plain text here.')
    );

    add_settings_field(
        'cap_public_client_key',
        'Public Client Key',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'public_client_key', 'type' => 'text', 'description' => 'Optional. Saved for future client-side Authorize.net integrations. The current server-side payment flow does not use this key.')
    );

    add_settings_field(
        'cap_debug_logging',
        'Debug Logging',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'debug_logging', 'label' => 'Enable WordPress debug logging for payment configuration/response diagnostics. Never logs card numbers or CVV.')
    );
});

function cap_sanitize_settings($input)
{
    $current = cap_get_settings();
    $input = is_array($input) ? $input : array();

    $output = array();
    $output['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $output['sandbox'] = !empty($input['sandbox']) ? 1 : 0;
    $output['debug_logging'] = !empty($input['debug_logging']) ? 1 : 0;
    $output['api_login_id'] = isset($input['api_login_id']) ? sanitize_text_field($input['api_login_id']) : '';
    $output['public_client_key'] = isset($input['public_client_key']) ? sanitize_text_field($input['public_client_key']) : '';

    // Do not erase an existing Transaction Key if the password field is left blank.
    $transaction_key = isset($input['transaction_key']) ? trim((string) $input['transaction_key']) : '';
    $output['transaction_key'] = $transaction_key !== '' ? sanitize_text_field($transaction_key) : $current['transaction_key'];

    add_settings_error('cap_messages', 'cap_saved', 'Custom Authorization settings saved.', 'updated');
    return $output;
}

function cap_text_field($args)
{
    $settings = cap_get_settings();
    $key = $args['key'];
    $type = isset($args['type']) ? $args['type'] : 'text';
    $value = isset($settings[$key]) ? $settings[$key] : '';

    // Never print the stored Transaction Key back into the page.
    if ($key === 'transaction_key') {
        $value = '';
        $placeholder = !empty($settings[$key]) ? 'Saved — leave blank to keep current key' : 'Enter Transaction Key';
    } else {
        $placeholder = '';
    }

    printf(
        '<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" autocomplete="%5$s" placeholder="%6$s">',
        esc_attr($type),
        esc_attr(CAP_SETTINGS_OPTION),
        esc_attr($key),
        esc_attr($value),
        $key === 'transaction_key' ? 'new-password' : 'off',
        esc_attr($placeholder)
    );

    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function cap_checkbox_field($args)
{
    $settings = cap_get_settings();
    $key = $args['key'];
    printf(
        '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
        esc_attr(CAP_SETTINGS_OPTION),
        esc_attr($key),
        checked(!empty($settings[$key]), true, false),
        esc_html($args['label'])
    );
}

function cap_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = cap_get_settings();
?>
    <div class="wrap">
        <h1>Custom Authorization</h1>
        <?php settings_errors('cap_messages'); ?>

        <div style="max-width:900px;">
            <form method="post" action="options.php">
                <?php
                settings_fields('cap_settings_group');
                do_settings_sections('custom-authorization');
                submit_button('Save Authorization Settings');
                ?>
            </form>

            <div class="notice notice-warning inline" style="margin-top:20px;">
                <p><strong>Security:</strong> Never place your API Login ID or Transaction Key in frontend JavaScript, page content, or public repositories. The Transaction Key is used only on the server.</p>
            </div>

            <div class="card" style="max-width:100%; margin-top:20px; padding:15px 20px;">
                <h2 style="margin-top:0;">Current Configuration</h2>
                <p><strong>Environment:</strong> <?php echo !empty($settings['sandbox']) ? 'Sandbox / Test' : 'Production / Live'; ?></p>
                <p><strong>Gateway:</strong> <?php echo !empty($settings['enabled']) ? 'Enabled' : 'Disabled'; ?></p>
                <p><strong>API Login ID:</strong> <?php echo $settings['api_login_id'] ? 'Configured' : 'Not configured'; ?></p>
                <p><strong>Transaction Key:</strong> <?php echo $settings['transaction_key'] ? 'Configured' : 'Not configured'; ?></p>
                <p><strong>Public Client Key:</strong> <?php echo $settings['public_client_key'] ? 'Configured' : 'Not configured'; ?></p>
            </div>
        </div>
    </div>
<?php
}
