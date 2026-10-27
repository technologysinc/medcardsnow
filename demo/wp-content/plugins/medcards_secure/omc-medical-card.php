<?php
/**
 * Plugin Name: Medcardsnow
 * Description: Multi-step medical card flow with Authorize.Net SIM payments
 * Version: 2.8.1
 * Author: M Nasir Rasheed (Marknova)
 * Author URI: https://marknova.io
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

define('OMC_PLUGIN_VERSION', '2.8.1');
define('OMC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OMC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once OMC_PLUGIN_DIR . 'install.php';

class OMC_Plugin {

    /** Base 1-year price by state, mirrored server-side so the client can never set its own price. */
    const STATE_BASE_1Y = [
        "Arizona"=>149.99,"Arkansas"=>179.99,"California"=>39.99,"Connecticut"=>149.99,
        "Delaware"=>149.99,"Georgia"=>149.99,"Illinois"=>179.99,"Iowa"=>199.99,
        "Louisiana"=>179.99,"Maine"=>69.99,"Maryland"=>139.99,"Massachusetts"=>139.99,
        "Michigan"=>129.99,"Minnesota"=>159.99,"Missouri"=>129.99,"Montana"=>139.99,
        "Nevada"=>79.99,"New Jersey"=>149.99,"New York"=>129.99,"Ohio"=>129.99,
        "Oklahoma"=>129.99,"Pennsylvania"=>149.99,"Texas"=>149.99,"Virginia"=>169.99,
        "Washington"=>179.99,"West Virginia"=>179.99,
    ];

    /** California plan catalogue, keyed by the exact plan name used in the UI. */
    const CA_PLANS = [
        'Special Offer'    => ['price' => 64.99,  'hc_eligible' => false],
        'Caregiver'        => ['price' => 99.99,  'hc_eligible' => true],
        'Growers Plan'     => ['price' => 189.99, 'hc_eligible' => true],
        'New Patient PDF'  => ['price' => 39.99,  'hc_eligible' => true],
        'Renewal PDF'      => ['price' => 49.99,  'hc_eligible' => false],
    ];

    /** Multi-year discount, applied to (base * years). */
    const DURATION_DISCOUNT = [1 => 0.0, 2 => 0.09, 3 => 0.15];

    const HARD_COPY_FEE_PER_YEAR = 10.00;
    const ID_CARD_FEE_PER_YEAR   = 15.00;

    /**
     * Coupon codes the server will honor. Kept server-side only — the
     * client can request a coupon by code, but never set its own percent.
     * The original plugin had SAVE10 wired to 100% off, which was almost
     * certainly a leftover test value — fixed to match its name.
     */
    const COUPONS = [
        'SAVE10' => 10,
    ];

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

        // Application data (medical form, plan/duration, coupon) — saved BEFORE payment
        add_action('wp_ajax_omc_save_application',        [$this, 'ajax_save_application']);
        add_action('wp_ajax_nopriv_omc_save_application', [$this, 'ajax_save_application']);

        // SIM Payment
        add_action('wp_ajax_omc_generate_fingerprint',        [$this, 'ajax_generate_fingerprint']);
        add_action('wp_ajax_nopriv_omc_generate_fingerprint', [$this, 'ajax_generate_fingerprint']);
        add_action('wp_ajax_omc_sim_response',        [$this, 'ajax_sim_response']);
        add_action('wp_ajax_nopriv_omc_sim_response', [$this, 'ajax_sim_response']);

        // Admin notification (kept for backward compatibility; application data
        // is now emailed automatically from ajax_sim_response on success).
        add_action('wp_ajax_omc_notify_admin',        [$this, 'ajax_notify_admin']);
        add_action('wp_ajax_nopriv_omc_notify_admin', [$this, 'ajax_notify_admin']);

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
                // NOTE: api_login/client_key are intentionally NOT sent to the browser.
                // The SIM fingerprint is generated entirely server-side (ajax_generate_fingerprint)
                // and only the resulting one-time fingerprint + api_login are returned to the form.
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
        register_setting('omc_group', 'omc_live_mode', ['sanitize_callback' => function ($v) {
            return $v ? '1' : '0';
        }]);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.'));
        }
        $live_mode = get_option('omc_live_mode', '0') === '1';
        ?>
        <div class="wrap">
            <h1>OMC Medical Card – Settings</h1>
            <p><strong>Authorize.Net SIM (Server Integration Method)</strong></p>
            <form method="post" action="options.php">
                <?php settings_fields('omc_group'); ?>
                <table class="form-table">
                    <tr>
                        <th>Payment Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="omc_live_mode" value="1" <?php checked($live_mode); ?>>
                                Go LIVE (uncheck to keep processing against Authorize.Net's TEST gateway)
                            </label>
                            <p class="description">
                                Currently charging against:
                                <code><?php echo esc_html($live_mode ? 'secure.authorize.net (LIVE)' : 'test.authorize.net (TEST — no real funds move)'); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>API Login ID</th>
                        <td><input type="text" name="omc_api_login_id" class="regular-text"
                            value="<?php echo esc_attr(get_option('omc_api_login_id')); ?>"></td>
                    </tr>
                    <tr>
                        <th>Transaction Key</th>
                        <td><input type="password" name="omc_transaction_key" class="regular-text" autocomplete="new-password"
                            value="<?php echo esc_attr(get_option('omc_transaction_key')); ?>">
                            <p class="description">Never exposed to the browser — used only server-side to sign the SIM fingerprint.</p>
                        </td>
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

    /**
     * Log to a location the webserver will not serve directly.
     * Lives under wp-content/uploads/omc-logs/ (not wp-content/ itself),
     * with a deny-all .htaccess and an empty index.php dropped alongside it.
     */
    private function log_payment_error($message, $data = []) {
        error_log('[OMC Payment] ' . $message);

        $upload_dir = wp_upload_dir();
        $log_dir    = trailingslashit($upload_dir['basedir']) . 'omc-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        if (!file_exists($log_dir . '/.htaccess')) {
            @file_put_contents($log_dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!file_exists($log_dir . '/index.php')) {
            @file_put_contents($log_dir . '/index.php', "<?php // Silence is golden.\n");
        }

        $log_entry = '[' . current_time('mysql') . '] ' . $message . "\n";
        if (!empty($data)) {
            // Never log raw card data; scrub anything that looks like a PAN,
            // and drop known-sensitive SIM fields outright.
            $safe = $this->scrub_log_data($data);
            $log_entry .= print_r($safe, true) . "\n";
        }
        @file_put_contents($log_dir . '/omc-payments.log', $log_entry . "\n", FILE_APPEND);
    }

    /** Remove/mask fields we never want sitting in a log file. */
    private function scrub_log_data($data) {
        $deny = ['x_cvv', 'x_card_num', 'card_number', 'cvv'];
        foreach ($deny as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[redacted]';
            }
        }
        if (isset($data['x_account_number'])) {
            $data['x_account_number'] = 'ending ' . substr((string) $data['x_account_number'], -4);
        }
        return $data;
    }

    /* =========================
       AJAX — REGISTER
    ========================== */
    public function ajax_register() {
        check_ajax_referer('omc_nonce', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        if (!$email || !is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address'], 400);
        }
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already registered'], 409);
        }

        // Password is hashed by wp_insert_user and never rendered — do NOT
        // run it through sanitize_text_field(), which trims/collapses
        // whitespace and can silently change what the user typed.
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            wp_send_json_error(['message' => 'Password must be at least 8 characters'], 400);
        }

        $first_name = sanitize_text_field($_POST['firstName'] ?? '');
        $last_name  = sanitize_text_field($_POST['lastName'] ?? '');

        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()], 500);
        }

        $phone = sanitize_text_field($_POST['phone'] ?? '');
        if ($phone !== '') {
            update_user_meta($user_id, 'omc_phone', $phone);
        }

        $this->send_admin_email(
            'New Signup – Medcardsnow',
            '<p><strong>Name:</strong> ' . esc_html(trim($first_name . ' ' . $last_name)) . '</p>' .
            '<p><strong>Email:</strong> ' . esc_html($email) . '</p>'
        );

        wp_set_auth_cookie($user_id, true);

        wp_send_json_success(['message' => 'Registered', 'user_id' => $user_id]);
    }

    /* =========================
       AJAX — LOGIN
    ========================== */
    public function ajax_login() {
        check_ajax_referer('omc_nonce', 'nonce');

        $creds = [
            'user_login'    => sanitize_email($_POST['email'] ?? ''),
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => !empty($_POST['remember']),
        ];

        $user = wp_signon($creds);

        if (is_wp_error($user)) {
            // Generic message — don't reveal whether the email exists.
            wp_send_json_error(['message' => 'Invalid email or password'], 401);
        }

        wp_send_json_success(['message' => 'Logged in', 'user_id' => $user->ID]);
    }

    /* =========================
       SERVER-SIDE PRICING
       (the ONLY place the amount the customer is charged is decided)
    ========================== */
    private function calculate_price(array $app) {
        $state     = sanitize_text_field($app['state'] ?? '');
        $plan_name = sanitize_text_field($app['plan_name'] ?? '');
        $years     = (int) ($app['duration_years'] ?? 1);
        $years     = in_array($years, [1, 2, 3], true) ? $years : 1;

        if ($state === 'California') {
            if (!isset(self::CA_PLANS[$plan_name])) {
                return new WP_Error('invalid_plan', 'Please choose a valid plan.');
            }
            $plan        = self::CA_PLANS[$plan_name];
            $base        = (float) $plan['price'];
            $hc_eligible = (bool) $plan['hc_eligible'];
        } else {
            $base        = self::STATE_BASE_1Y[$state] ?? 149.99;
            $hc_eligible = false; // Hard copy add-on is only ever offered on the CA plan step.
        }

        $discount = self::DURATION_DISCOUNT[$years] ?? 0.0;
        $subtotal = $base * $years * (1 - $discount);

        // Add-ons only apply where the UI actually offers them.
        $add_hard_copy = $state === 'California' && $hc_eligible && !empty($app['add_hard_copy']);
        $add_id_card   = $state === 'California' && !empty($app['add_id_card']);

        if ($add_hard_copy) {
            $subtotal += self::HARD_COPY_FEE_PER_YEAR * $years;
        }
        if ($add_id_card) {
            $subtotal += self::ID_CARD_FEE_PER_YEAR * $years;
        }

        $coupon_code = strtoupper(sanitize_text_field($app['coupon_code'] ?? ''));
        if ($coupon_code !== '' && isset(self::COUPONS[$coupon_code])) {
            $percent   = self::COUPONS[$coupon_code];
            $subtotal -= $subtotal * ($percent / 100);
        } else {
            $coupon_code = '';
        }

        $subtotal = max(0, round($subtotal, 2));

        return [
            'amount'        => $subtotal,
            'state'         => $state,
            'plan_name'     => $plan_name,
            'duration_years'=> $years,
            'add_hard_copy' => $add_hard_copy ? 1 : 0,
            'add_id_card'   => $add_id_card ? 1 : 0,
            'coupon_code'   => $coupon_code,
        ];
    }

    /* =========================
       AJAX — SAVE APPLICATION (medical form + plan/duration, pre-payment)
    ========================== */
    public function ajax_save_application() {
        check_ajax_referer('omc_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'omc_transactions';

        $existing_uid = sanitize_text_field($_POST['application_uid'] ?? '');

        $conditions = [];
        if (!empty($_POST['conditions']) && is_array($_POST['conditions'])) {
            $conditions = array_map('sanitize_text_field', wp_unslash($_POST['conditions']));
        }

        $raw = [
            'first_name'    => sanitize_text_field($_POST['firstName'] ?? ''),
            'last_name'     => sanitize_text_field($_POST['lastName'] ?? ''),
            'email'         => sanitize_email($_POST['email'] ?? ''),
            'phone'         => sanitize_text_field($_POST['phone'] ?? ''),
            'dob'           => sanitize_text_field($_POST['dob'] ?? ''),
            'gender'        => sanitize_text_field($_POST['gender'] ?? ''),
            'state'         => sanitize_text_field($_POST['state'] ?? ''),
            'allergies'     => sanitize_textarea_field($_POST['allergies'] ?? ''),
            'other_condition'=> sanitize_textarea_field($_POST['otherCondition'] ?? ''),
            'telemedicine'  => !empty($_POST['telemedicine']) ? 1 : 0,
            'plan_name'     => sanitize_text_field($_POST['plan_name'] ?? ''),
            'duration_years'=> (int) ($_POST['duration_years'] ?? 1),
            'add_hard_copy' => !empty($_POST['add_hard_copy']),
            'add_id_card'   => !empty($_POST['add_id_card']),
            'coupon_code'   => sanitize_text_field($_POST['coupon_code'] ?? ''),
        ];

        // Signature: must be a data: URL image, capped to something sane (~1.5MB base64).
        $signature = (string) ($_POST['signature'] ?? '');
        if ($signature !== '' && (strlen($signature) > 1_500_000 || strpos($signature, 'data:image/') !== 0)) {
            $signature = '';
        }

        $price = $this->calculate_price($raw);
        if (is_wp_error($price)) {
            wp_send_json_error(['message' => $price->get_error_message()], 400);
        }

        $user    = wp_get_current_user();
        $user_id = $user && $user->ID ? $user->ID : null;

        $row = [
            'user_id'              => $user_id,
            'first_name'           => $raw['first_name'],
            'last_name'            => $raw['last_name'],
            'email'                => $raw['email'],
            'phone'                => $raw['phone'],
            'dob'                  => $raw['dob'],
            'gender'               => $raw['gender'],
            'state'                => $raw['state'],
            'allergies'            => $raw['allergies'],
            'medical_conditions'   => implode(', ', $conditions),
            'other_condition'      => $raw['other_condition'],
            'telemedicine_consent' => $raw['telemedicine'],
            'signature'            => $signature,
            'plan_name'            => $price['plan_name'],
            'duration_years'       => $price['duration_years'],
            'add_hard_copy'        => $price['add_hard_copy'],
            'add_id_card'          => $price['add_id_card'],
            'coupon_code'          => $price['coupon_code'],
            'amount'               => $price['amount'],
            'customer_name'        => trim($raw['first_name'] . ' ' . $raw['last_name']),
            'customer_email'       => $raw['email'],
            'updated_at'           => current_time('mysql'),
        ];

        $application_uid = $existing_uid;

        if ($existing_uid) {
            $owner_ok = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE application_uid = %s AND status = 'draft' LIMIT 1",
                $existing_uid
            ));
            if (!$owner_ok) {
                $existing_uid = ''; // stale/unknown/already-paid — start a fresh draft instead
            }
        }

        if ($existing_uid) {
            $wpdb->update($table, $row, ['application_uid' => $existing_uid]);
        } else {
            $application_uid = wp_generate_uuid4();
            $row['application_uid'] = $application_uid;
            $row['status']          = 'draft';
            $row['created_at']      = current_time('mysql');
            $wpdb->insert($table, $row);
        }

        if (!empty($user_id)) {
            if ($raw['phone'])  update_user_meta($user_id, 'omc_phone', $raw['phone']);
            if ($raw['state'])  update_user_meta($user_id, 'omc_state', $raw['state']);
        }

        wp_send_json_success([
            'application_uid' => $application_uid,
            'amount'          => number_format($price['amount'], 2, '.', ''),
        ]);
    }

    /* =========================
       AJAX — GENERATE SIM FINGERPRINT
       Amount is ALWAYS recomputed server-side from the saved application —
       the client cannot request its own price.
    ========================== */
    public function ajax_generate_fingerprint() {
        check_ajax_referer('omc_nonce', 'nonce');

        $api_login        = get_option('omc_api_login_id');
        $transaction_key  = get_option('omc_transaction_key');

        if (empty($api_login) || empty($transaction_key)) {
            wp_send_json_error(['message' => 'Payment gateway not configured'], 500);
        }

        $application_uid = sanitize_text_field($_POST['application_uid'] ?? '');
        if (!$application_uid) {
            wp_send_json_error(['message' => 'Missing application reference'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'omc_transactions';
        $app   = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE application_uid = %s AND status = 'draft' LIMIT 1",
            $application_uid
        ), ARRAY_A);

        if (!$app) {
            wp_send_json_error(['message' => 'Application not found or already paid. Please review your order again.'], 404);
        }

        // Recompute fresh — never trust the amount column blindly either,
        // in case prices changed between save and checkout.
        $price = $this->calculate_price($app);
        if (is_wp_error($price)) {
            wp_send_json_error(['message' => $price->get_error_message()], 400);
        }
        $amount = $price['amount'];

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid order amount'], 400);
        }

        $wpdb->update($table, [
            'amount'     => $amount,
            'status'     => 'pending_payment',
            'updated_at' => current_time('mysql'),
        ], ['application_uid' => $application_uid]);

        $sequence  = random_int(1, 1000);
        $timestamp = time();

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

        $live_mode   = get_option('omc_live_mode', '0') === '1';
        $gateway_url = $live_mode
            ? 'https://secure.authorize.net/gateway/transact.dll'
            : 'https://test.authorize.net/gateway/transact.dll';

        $this->log_payment_error('Generated fingerprint', [
            'application_uid' => $application_uid,
            'amount'          => $amount,
            'sequence'        => $sequence,
            'timestamp'       => $timestamp,
            'live_mode'       => $live_mode,
        ]);

        wp_send_json_success([
            'fingerprint'      => $fingerprint,
            'api_login'        => $api_login,
            'amount'           => number_format($amount, 2, '.', ''),
            'sequence'         => $sequence,
            'timestamp'        => $timestamp,
            'gateway_url'      => $gateway_url,
            'test_request'     => $live_mode ? 'FALSE' : 'TRUE',
            'application_uid'  => $application_uid,
        ]);
    }

    /* =========================
       AJAX — SIM RESPONSE HANDLER
       (external redirect target from Authorize.Net — cannot carry a WP nonce;
        integrity is instead enforced by the HMAC-safe hash check below)
    ========================== */
    public function ajax_sim_response() {
        $response = wp_unslash($_POST);

        $this->log_payment_error('SIM Response Received', $response);

        $api_login       = get_option('omc_api_login_id');
        $transaction_key = get_option('omc_transaction_key');

        $expected_hash = md5(
            $transaction_key .
            $api_login .
            ($response['x_trans_id'] ?? '') .
            ($response['x_amount'] ?? '')
        );

        $received_hash = strtolower($response['x_MD5_Hash'] ?? '');

        if (!hash_equals($expected_hash, $received_hash)) {
            $this->log_payment_error('SIM Hash Mismatch - Possible Fraud', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            wp_redirect(home_url('/payment-error?reason=security_verification_failed'));
            exit;
        }

        $application_uid = sanitize_text_field($response['x_invoice_num'] ?? '');

        global $wpdb;
        $table = $wpdb->prefix . 'omc_transactions';
        $app   = $application_uid
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE application_uid = %s", $application_uid), ARRAY_A)
            : null;

        if (($response['x_response_code'] ?? '0') === '1') {
            // PAYMENT SUCCESSFUL
            $order_data = [
                'transaction_id' => sanitize_text_field($response['x_trans_id'] ?? ''),
                'amount'         => sanitize_text_field($response['x_amount'] ?? '0'),
                'auth_code'      => sanitize_text_field($response['x_auth_code'] ?? ''),
                'payment_method' => sanitize_text_field($response['x_card_type'] ?? ''),
                'last4'          => substr(sanitize_text_field($response['x_account_number'] ?? ''), -4),
                'customer_name'  => trim(sanitize_text_field(($response['x_first_name'] ?? '') . ' ' . ($response['x_last_name'] ?? ''))),
                'customer_email' => sanitize_email($response['x_email'] ?? ''),
                'customer_phone' => sanitize_text_field($response['x_phone'] ?? ''),
            ];

            $this->log_payment_error('SIM Payment SUCCESS', array_merge($order_data, ['application_uid' => $application_uid]));

            if ($app) {
                $wpdb->update($table, [
                    'transaction_id' => $order_data['transaction_id'],
                    'auth_code'      => $order_data['auth_code'],
                    'payment_method' => $order_data['payment_method'],
                    'last4'          => $order_data['last4'],
                    'status'         => 'completed',
                    'updated_at'     => current_time('mysql'),
                ], ['application_uid' => $application_uid]);
            } else {
                // No matching draft (shouldn't normally happen) — still record the payment.
                $wpdb->insert($table, [
                    'application_uid' => $application_uid ?: wp_generate_uuid4(),
                    'transaction_id'  => $order_data['transaction_id'],
                    'amount'          => $order_data['amount'],
                    'auth_code'       => $order_data['auth_code'],
                    'payment_method'  => $order_data['payment_method'],
                    'last4'           => $order_data['last4'],
                    'customer_name'   => $order_data['customer_name'],
                    'customer_email'  => $order_data['customer_email'],
                    'status'          => 'completed',
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql'),
                ]);
            }

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
            </div>';

            if ($app) {
                $email_html .= '
                <h3>Medical Card Application</h3>
                <div style="background:#f5f5f5; padding:20px; border-radius:5px;">
                    <p><strong>Name:</strong> ' . esc_html($app['first_name'] . ' ' . $app['last_name']) . '</p>
                    <p><strong>DOB:</strong> ' . esc_html($app['dob']) . '</p>
                    <p><strong>Gender:</strong> ' . esc_html($app['gender']) . '</p>
                    <p><strong>State:</strong> ' . esc_html($app['state']) . '</p>
                    <p><strong>Plan:</strong> ' . esc_html($app['plan_name']) . ' (' . esc_html($app['duration_years']) . ' yr)</p>
                    <p><strong>Allergies:</strong> ' . esc_html($app['allergies'] ?: 'None listed') . '</p>
                    <p><strong>Medical Conditions:</strong> ' . esc_html($app['medical_conditions'] ?: 'None listed') . '</p>
                    ' . ($app['other_condition'] ? '<p><strong>Other condition:</strong> ' . esc_html($app['other_condition']) . '</p>' : '') . '
                    <p><strong>Telemedicine consent:</strong> ' . ($app['telemedicine_consent'] ? 'Yes' : 'No') . '</p>
                </div>';
            }

            $this->send_admin_email('Payment Successful: ' . $order_data['transaction_id'], $email_html);

            wp_redirect(home_url('/thank-you?txn=' . rawurlencode($order_data['transaction_id'])));
            exit;

        } else {
            $error_code = sanitize_text_field($response['x_response_reason_code'] ?? '');
            $error_text = sanitize_text_field($response['x_response_reason_text'] ?? 'Payment declined');

            $this->log_payment_error('SIM Payment FAILED', [
                'application_uid' => $application_uid,
                'error_code'      => $error_code,
                'error_text'      => $error_text,
            ]);

            if ($app) {
                $wpdb->update($table, [
                    'status'     => 'failed',
                    'updated_at' => current_time('mysql'),
                ], ['application_uid' => $application_uid]);
            }

            wp_redirect(home_url('/payment-error?code=' . rawurlencode($error_code) . '&reason=' . rawurlencode($error_text)));
            exit;
        }
    }

    /* =========================
       AJAX — Legacy manual notify (kept for compatibility; not used by the live flow)
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
