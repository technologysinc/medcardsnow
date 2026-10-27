<?php
/**
 * Plugin Name: Medcardsnow
 * Description: Multi-step medical card flow with Authorize.Net SIM payments
 * Version: 2.7.0
 * Author: M Nasir Rasheed (Marknova)
 * Author URI: https://marknova.io
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('OMC_PLUGIN_VERSION', '1.3.0');
define('OMC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OMC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once OMC_PLUGIN_DIR . 'install.php';

class OMC_Plugin {

    public function __construct() {
        add_action('init',                  [$this, 'register_shortcodes']);
        add_action('admin_menu',            [$this, 'admin_menu']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('wp_enqueue_scripts',    [$this, 'enqueue_assets']);

        // User registration/login
        add_action('wp_ajax_omc_register',        [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_omc_register', [$this, 'ajax_register']);
        add_action('wp_ajax_omc_login',        [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_omc_login', [$this, 'ajax_login']);

        // SIM Payment
        add_action('wp_ajax_omc_generate_fingerprint',        [$this, 'ajax_generate_fingerprint']);
        add_action('wp_ajax_nopriv_omc_generate_fingerprint', [$this, 'ajax_generate_fingerprint']);
        add_action('wp_ajax_omc_sim_response',        [$this, 'ajax_sim_response']);
        add_action('wp_ajax_nopriv_omc_sim_response', [$this, 'ajax_sim_response']);

        // Admin notification
        add_action('wp_ajax_omc_notify_admin',        [$this, 'ajax_notify_admin']);
        add_action('wp_ajax_nopriv_omc_notify_admin', [$this, 'ajax_notify_admin']);

        //new shortcodes
    add_shortcode('omc_thank_you', [$this, 'render_thank_you']);
    add_shortcode('omc_payment_error', [$this, 'render_payment_error']);
    }

    /* =========================
       FRONTEND ASSETS
    ========================== */
    public function enqueue_assets() {
        wp_enqueue_style('bootstrap',  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
        wp_enqueue_style('bs-icons',   'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css');
        wp_enqueue_style('omc-css',    OMC_PLUGIN_URL . 'assets/css/omc.css', [], OMC_PLUGIN_VERSION);

        wp_enqueue_script('jquery');
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', ['jquery'], null, true);
        wp_enqueue_script('omc-js',    OMC_PLUGIN_URL . 'assets/js/omc.js', ['jquery'], OMC_PLUGIN_VERSION, true);

        $user = wp_get_current_user();

        wp_localize_script('omc-js', 'OMC', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('omc_nonce'),
            'lost_password_url' => wp_lostpassword_url(),
            'images' => [
                'signup_hero' => OMC_PLUGIN_URL . 'assets/Hero.jpg',
                'login_hero' => OMC_PLUGIN_URL . 'assets/Hero.jpg',
            ],
            'current_user' => [
                'is_logged_in' => is_user_logged_in(),
                'email'  => $user ? $user->user_email : '',
                'name'   => $user ? $user->display_name : '',
                'phone'  => ($user && $user->ID) ? get_user_meta($user->ID, 'omc_phone', true) : '',
            ],
            'settings' => [
                'admin_email' => get_option('omc_admin_email', get_option('admin_email')),
                'api_login'   => get_option('omc_api_login_id'),
                'client_key'  => get_option('omc_client_key'),
            ],
        ]);
    }

    /* =========================
       ADMIN SETTINGS
    ========================== */
    public function admin_menu() {
        add_menu_page(
            'OMC Medical Card',
            'OMC Medical Card',
            'manage_options',
            'omc-settings',
            [$this, 'settings_page'],
            'dashicons-id',
            56
        );
    }

    public function register_settings() {
        register_setting('omc_group', 'omc_api_login_id');
        register_setting('omc_group', 'omc_transaction_key');
        register_setting('omc_group', 'omc_client_key');
        register_setting('omc_group', 'omc_admin_email');
    }

    public function settings_page() { ?>
        <div class="wrap">
            <h1>OMC Medical Card – Settings</h1>
            <p><strong>Now using Authorize.Net SIM (Server Integration Method)</strong></p>
            <form method="post" action="options.php">
                <?php settings_fields('omc_group'); ?>
                <table class="form-table">
                    <tr>
                        <th>API Login ID</th>
                        <td><input type="text" name="omc_api_login_id" class="regular-text"
                            value="<?php echo esc_attr(get_option('omc_api_login_id')); ?>"></td>
                    </tr>
                    <tr>
                        <th>Transaction Key</th>
                        <td><input type="text" name="omc_transaction_key" class="regular-text"
                            value="<?php echo esc_attr(get_option('omc_transaction_key')); ?>"></td>
                    </tr>
                    <tr>
                        <th>Admin Notification Email</th>
                        <td><input type="email" name="omc_admin_email" class="regular-text"
                            value="<?php echo esc_attr(get_option('omc_admin_email', get_option('admin_email'))); ?>"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php }

    private function send_admin_email($subject, $message) {
        return wp_mail(
            get_option('omc_admin_email', get_option('admin_email')),
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    private function log_payment_error($message, $data = []) {
        error_log('[OMC Payment] ' . $message);
        if (!empty($data)) {
            error_log('[OMC Data] ' . print_r($data, true));
        }
        
        $log_file = WP_CONTENT_DIR . '/omc-payments.log';
        $log_entry = '[' . current_time('mysql') . '] ' . $message . "\n";
        if (!empty($data)) {
            $log_entry .= print_r($data, true) . "\n";
        }
        @file_put_contents($log_file, $log_entry . "\n", FILE_APPEND);
    }

    /* =========================
       AJAX — REGISTER
    ========================== */
    public function ajax_register() {
        check_ajax_referer('omc_nonce', 'nonce');

        $email = sanitize_email($_POST['email']);
        if (!$email || email_exists($email)) {
            wp_send_json_error(['message' => 'Email already registered'], 409);
        }

        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => sanitize_text_field($_POST['password']),
            'first_name' => sanitize_text_field($_POST['firstName']),
            'last_name'  => sanitize_text_field($_POST['lastName']),
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()], 500);
        }

        update_user_meta($user_id, 'omc_phone',   sanitize_text_field($_POST['phone']));
        update_user_meta($user_id, 'omc_state',   sanitize_text_field($_POST['state']));
        update_user_meta($user_id, 'omc_address', sanitize_text_field($_POST['address']));

        $this->send_admin_email(
            'New Signup – Medcardsnow',
            '<p><strong>Email:</strong> ' . esc_html($email) . '</p>'
        );

        wp_send_json_success(['message' => 'Registered', 'user_id' => $user_id]);
    }

    /* =========================
       AJAX — LOGIN
    ========================== */
    public function ajax_login() {
        check_ajax_referer('omc_nonce', 'nonce');

        $creds = [
            'user_login'    => sanitize_email($_POST['email']),
            'user_password' => $_POST['password'],
            'remember'      => !empty($_POST['remember']),
        ];

        $user = wp_signon($creds);

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message()], 401);
        }

        wp_send_json_success(['message' => 'Logged in', 'user_id' => $user->ID]);
    }

    /* =========================
       AJAX — GENERATE SIM FINGERPRINT
    ========================== */
    public function ajax_generate_fingerprint() {
        check_ajax_referer('omc_nonce', 'nonce');

        $api_login = get_option('omc_api_login_id');
        $transaction_key = get_option('omc_transaction_key');
        
        if (empty($api_login) || empty($transaction_key)) {
            wp_send_json_error(['message' => 'Payment gateway not configured'], 500);
        }

        $amount = floatval($_POST['amount']);
        $sequence = intval($_POST['sequence']);
        $timestamp = intval($_POST['timestamp']);

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount'], 400);
        }

        // Generate fingerprint hash for Authorize.Net SIM
        $fingerprint = hash_hmac(
            'sha512',
            sprintf(
                "%s^%s^%s^%s^",
                $api_login,
                $sequence,
                $timestamp,
                number_format($amount, 2, '.', '')
            ),
            $transaction_key
        );

        $this->log_payment_error('Generated fingerprint', [
            'amount' => $amount,
            'sequence' => $sequence,
            'timestamp' => $timestamp,
            'fingerprint' => $fingerprint
        ]);

        wp_send_json_success([
            'fingerprint' => $fingerprint,
            'api_login' => $api_login,
            'amount' => number_format($amount, 2, '.', ''),
            'sequence' => $sequence,
            'timestamp' => $timestamp
        ]);
    }

    /* =========================
       AJAX — SIM RESPONSE HANDLER
    ========================== */
    public function ajax_sim_response() {
        // Authorize.Net SIM response handler
        $response = $_POST;
        
        $this->log_payment_error('SIM Response Received', $response);

        // Get credentials
        $api_login = get_option('omc_api_login_id');
        $transaction_key = get_option('omc_transaction_key');

        // Verify the MD5 Hash (security check)
        $expected_hash = md5(
            $transaction_key . 
            $api_login . 
            ($response['x_trans_id'] ?? '') . 
            ($response['x_amount'] ?? '')
        );

        $received_hash = strtolower($response['x_MD5_Hash'] ?? '');
        
        if ($received_hash !== $expected_hash) {
            $this->log_payment_error('SIM Hash Mismatch - Possible Fraud', [
                'expected' => $expected_hash,
                'received' => $received_hash,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            // Redirect to error page
            wp_redirect(home_url('/payment-error?reason=security_verification_failed'));
            exit;
        }

        // Check payment status
        if (($response['x_response_code'] ?? '0') === '1') {
            // PAYMENT SUCCESSFUL
            
            // Prepare order data
            $order_data = [
                'transaction_id' => $response['x_trans_id'] ?? '',
                'amount' => $response['x_amount'] ?? '0',
                'auth_code' => $response['x_auth_code'] ?? '',
                'payment_method' => $response['x_card_type'] ?? '',
                'last4' => substr($response['x_account_number'] ?? '', -4),
                'customer_name' => $response['x_first_name'] . ' ' . $response['x_last_name'],
                'customer_email' => $response['x_email'] ?? '',
                'customer_phone' => $response['x_phone'] ?? '',
                'timestamp' => current_time('mysql'),
                'response' => $response
            ];

            $this->log_payment_error('SIM Payment SUCCESS', $order_data);

            // Send admin notification
            $email_html = '
            <h2>Payment Successful – Medcardsnow</h2>
            <div style="background:#f5f5f5; padding:20px; border-radius:5px;">
                <h3>Transaction Details</h3>
                <p><strong>Transaction ID:</strong> ' . esc_html($order_data['transaction_id']) . '</p>
                <p><strong>Amount:</strong> $' . esc_html($order_data['amount']) . '</p>
                <p><strong>Customer:</strong> ' . esc_html($order_data['customer_name']) . '</p>
                <p><strong>Email:</strong> ' . esc_html($order_data['customer_email']) . '</p>
                <p><strong>Phone:</strong> ' . esc_html($order_data['customer_phone']) . '</p>
                <p><strong>Payment Method:</strong> ' . esc_html($order_data['payment_method']) . ' ending in ' . esc_html($order_data['last4']) . '</p>
                <p><strong>Auth Code:</strong> ' . esc_html($order_data['auth_code']) . '</p>
                <p><strong>Date:</strong> ' . esc_html($order_data['timestamp']) . '</p>
            </div>';

            $this->send_admin_email(
                'Payment Successful: ' . $order_data['transaction_id'],
                $email_html
            );

            // Store transaction in database
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'omc_transactions',
                [
                    'transaction_id' => $order_data['transaction_id'],
                    'amount' => $order_data['amount'],
                    'customer_name' => $order_data['customer_name'],
                    'customer_email' => $order_data['customer_email'],
                    'status' => 'completed',
                    'created_at' => current_time('mysql'),
                ]
            );

            // Redirect to thank you page
            wp_redirect(home_url('/thank-you?txn=' . $order_data['transaction_id']));
            exit;

        } else {
            // PAYMENT FAILED
            $error_code = $response['x_response_reason_code'] ?? '';
            $error_text = $response['x_response_reason_text'] ?? 'Payment declined';
            
            $this->log_payment_error('SIM Payment FAILED', [
                'error_code' => $error_code,
                'error_text' => $error_text,
                'response' => $response
            ]);

            // Redirect to error page with reason
            wp_redirect(home_url('/payment-error?code=' . $error_code . '&reason=' . urlencode($error_text)));
            exit;
        }
    }

    /* =========================
       AJAX — ORDER EMAIL
    ========================== */
    public function ajax_notify_admin() {
        check_ajax_referer('omc_nonce', 'nonce');

        $payload = json_decode(stripslashes($_POST['payload'] ?? ''), true);

        $html =
            '<h2>New Order – Medcardsnow</h2>' .
            '<pre style="white-space:pre-wrap;background:#fafafa;padding:10px;">' .
            esc_html(print_r($payload, true)) .
            '</pre>';

        $this->send_admin_email('New Medcardsnow Order', $html);

        wp_send_json_success(['message' => 'Admin notified']);
    }

    /* =========================
       SHORTCODE
    ========================== */
    public function register_shortcodes() {
        add_shortcode('omc_medical_card', [$this, 'render_shortcode']);
    }

    public function render_shortcode() {
        ob_start();
        include OMC_PLUGIN_DIR . 'templates/frontend.php';
        return ob_get_clean();
    }
    public function render_thank_you() {
    ob_start();
    include OMC_PLUGIN_DIR . 'templates/thank-you.php';
    return ob_get_clean();
}
public function render_payment_error() {
    ob_start();
    include OMC_PLUGIN_DIR . 'templates/payment-error.php';
    return ob_get_clean();
}
}

new OMC_Plugin();