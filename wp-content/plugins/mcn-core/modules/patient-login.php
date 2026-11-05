<?php
/**
 * MCN Core module: Registration and magic-link login
 * Form 1 state capture, Form 3 secure login links, the post-registration login email, and link handling. Staff accounts are refused.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;










// Registration and secure login link process 
/**
 * =========================================================
 * 1. Gravity Forms User Registration - Synchronous Feed
 * =========================================================
 */

add_filter('gform_is_feed_asynchronous', function ($is_async, $feed) {

    if (
        is_array($feed) &&
        rgar($feed, 'addon_slug') === 'gravityformsuserregistration'
    ) {
        return false;
    }

    return $is_async;

}, 10, 2);


/**
 * =========================================================
 * 3. After User Registration - Magic Login Email
 * =========================================================
 */

add_action(
    'gform_user_registered',
    'zm_send_magic_login_link_after_registration',
    10,
    4
);


function zm_send_magic_login_link_after_registration(
    $user_id,
    $feed,
    $entry,
    $user_pass
) {

    if (empty($user_id)) {
        return;
    }

    /**
     * Get registered user
     */
    $user = get_userdata($user_id);

    if (!$user || empty($user->user_email)) {
        return;
    }

    // Never issue passwordless links for staff accounts.
    if (mcn_user_is_privileged($user_id)) {
        return;
    }

    /**
     * Create secure random token
     */
    $token = wp_generate_password(48, false, false);

    $token_hash = wp_hash_password($token);

    $expires_at = time() + (30 * MINUTE_IN_SECONDS);

    /**
     * Save token hash and expiry
     */
    update_user_meta(
        $user_id,
        '_magic_login_token_hash',
        $token_hash
    );

    update_user_meta(
        $user_id,
        '_magic_login_token_expires',
        $expires_at
    );

    /**
     * Build secure login URL
     */
    $profile_url = site_url('/medical-card-profile/');

    $login_url = add_query_arg(
        array(
            'magic_login' => 1,
            'uid'         => $user_id,
            'token'       => $token,
        ),
        $profile_url
    );

    /**
     * Email subject
     */
    $subject = 'Your Secure Login Link for Med Cards Now';

    /**
     * Display name
     */
    $display_name = !empty($user->display_name)
        ? $user->display_name
        : 'there';

    /**
     * Build HTML email
     */
    $message  = '<html><body>';

    $message .= '<p>Hi ' . esc_html($display_name) . ',</p>';

    $message .= '<p>Your account has been created successfully.</p>';

    $message .= '<p>Click the secure link below to log in and complete your profile:</p>';

    $message .= '<p style="margin-top:20px;">';

    $message .= '<a href="' . esc_url($login_url) . '" '
        . 'style="display:inline-block;'
        . 'padding:12px 20px;'
        . 'background:#0073aa;'
        . 'color:#ffffff;'
        . 'text-decoration:none;'
        . 'border-radius:4px;'
        . 'font-weight:bold;">'
        . 'Click here'
        . '</a>';

    $message .= '</p>';

    $message .= '<p>This link expires in 30 minutes and can only be used once.</p>';

    $message .= '<p>If you did not request this, you can safely ignore this email.</p>';

    $message .= '</body></html>';

    /**
     * Email headers
     */
    $headers = array(
        'From: Med Cards Now <contact@medcardsnow.com>',
        'Content-Type: text/html; charset=UTF-8',
    );

    /**
     * Send email
     */
    $mail_sent = wp_mail(
        $user->user_email,
        $subject,
        $message,
        $headers
    );

    /**
     * Error logging
     */
    if (!$mail_sent) {

        error_log(
            'Magic login email failed for user ID: ' . $user_id
        );
    }
}



/**
 * =========================================================
 * 4. Handle One-Time Magic Login
 * =========================================================
 */

add_action(
    'template_redirect',
    'zm_handle_magic_login'
);



function zm_handle_magic_login()
{

    /**
     * Check required parameters
     */
    if (
        empty($_GET['magic_login']) ||
        empty($_GET['uid']) ||
        empty($_GET['token'])
    ) {
        return;
    }


    /**
     * Validate login request
     */
    $user_id = absint($_GET['uid']);

    $token = sanitize_text_field(
        wp_unslash($_GET['token'])
    );


    if (
        !$user_id ||
        empty($token)
    ) {
        wp_die('Invalid login link.');
    }


    /**
     * Get user
     */
    $user = get_userdata($user_id);

    if (!$user) {
        wp_die('Invalid login link.');
    }

    // Staff accounts must sign in with a password.
    if (mcn_user_is_privileged($user_id)) {
        wp_die('This account must sign in with a password.');
    }


    /**
     * Retrieve stored token data
     */
    $stored_hash = get_user_meta(
        $user_id,
        '_magic_login_token_hash',
        true
    );

    $expires_at = (int) get_user_meta(
        $user_id,
        '_magic_login_token_expires',
        true
    );


    /**
     * Check token existence
     */
    if (
        empty($stored_hash) ||
        empty($expires_at)
    ) {
        wp_die(
            'This login link is invalid or has already been used.'
        );
    }


    /**
     * Check token expiry
     */
    if (time() > $expires_at) {

        delete_user_meta(
            $user_id,
            '_magic_login_token_hash'
        );

        delete_user_meta(
            $user_id,
            '_magic_login_token_expires'
        );

        wp_die(
            'This login link has expired.'
        );
    }


    /**
     * Verify token
     */
    if (
        !wp_check_password(
            $token,
            $stored_hash,
            $user_id
        )
    ) {
        wp_die('Invalid login link.');
    }


    /**
     * Single-use token:
     * Delete before login
     */
    delete_user_meta(
        $user_id,
        '_magic_login_token_hash'
    );

    delete_user_meta(
        $user_id,
        '_magic_login_token_expires'
    );


    /**
     * Log user in
     */
    wp_set_current_user($user_id);

    wp_set_auth_cookie($user_id, true);


    /**
     * Redirect to profile page
     *
     * Fixed internal destination.
     */
    $redirect_to = site_url(
        '/medical-card-profile/'
    );


    wp_safe_redirect($redirect_to);

    exit;

}


/**
 * =========================================================
 * 5. Populate Patient State From URL
 * =========================================================
 */

add_filter(
    'gform_field_value_patient_state',
    'populate_state_from_url'
);


function populate_state_from_url($value)
{

    if (
        empty($_SERVER['REQUEST_URI'])
    ) {
        return $value;
    }


    /**
     * Get request path
     */
    $request_path = trim(
        parse_url(
            wp_unslash($_SERVER['REQUEST_URI']),
            PHP_URL_PATH
        ),
        '/'
    );


    if (!$request_path) {
        return $value;
    }


    /**
     * Get URL slug
     */
    $slug = basename($request_path);


    /**
     * Remove known suffix
     */
    $slug = str_replace(
        '-medical-marijuana-card',
        '',
        $slug
    );


    /**
     * Convert slug into state name
     */
    $state = ucwords(
        str_replace(
            '-',
            ' ',
            $slug
        )
    );


    return $state;

}


/**
 * =========================================================
 * 6. Save State to User Meta After Registration
 * =========================================================
 */

add_action(
    'gform_after_submission_1',
    'save_state_to_user_meta_after_registration',
    10,
    2
);


function save_state_to_user_meta_after_registration(
    $entry,
    $form
)
{

    /**
     * Gravity Forms field IDs
     */
    $email = sanitize_email(
        rgar($entry, '10')
    );

    $state = sanitize_text_field(
        rgar($entry, '12')
    );


    if (
        empty($email) ||
        empty($state)
    ) {
        return;
    }


    /**
     * Find user by email
     */
    $user = get_user_by(
        'email',
        $email
    );


    if (!$user) {
        return;
    }


    /**
     * Save state in WordPress user meta
     */
    update_user_meta(
        $user->ID,
        'patient_state',
        $state
    );


    /**
     * Save state in ACF user field
     */
    if (
        function_exists('update_field')
    ) {

        update_field(
            'patient_state',
            $state,
            'user_' . $user->ID
        );

    }

}


// Secure login link - Login Form page
// (Sender name/address filters are registered once, near the top of this file.)



	
	

	

	






























	

	


add_action(
    'gform_after_submission_3',
    'send_secure_login_link_from_gravity_form',
    10,
    2
);

function send_secure_login_link_from_gravity_form($entry, $form)
{
    // Email Field ID = 1
    $email = sanitize_email(rgar($entry, '1'));

    // Validate email. The address itself is never written to the log (personal data).
    if (!$email || !is_email($email)) {
        return;
    }

    /*
     * Rate limiting. Without it, anyone could use this form to create unlimited
     * accounts and make the site send unlimited emails to any address, which
     * damages the domain's email reputation.
     *   - at most 3 links per email address per 15 minutes
     *   - at most 10 links per IP address per hour
     */
    $email_key  = 'mcn_sl_rl_e_' . md5(strtolower($email));
    $ip         = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $ip_key     = 'mcn_sl_rl_i_' . md5($ip);
    $email_hits = (int) get_transient($email_key);
    $ip_hits    = (int) get_transient($ip_key);

    if ($email_hits >= 3 || ($ip !== '' && $ip_hits >= 10)) {
        error_log('Secure login link request rate-limited.');
        return;
    }

    set_transient($email_key, $email_hits + 1, 15 * MINUTE_IN_SECONDS);
    if ($ip !== '') {
        set_transient($ip_key, $ip_hits + 1, HOUR_IN_SECONDS);
    }

    // Check whether user already exists
    $user = get_user_by('email', $email);

    if (!$user) {

        // Generate a unique username
        $email_prefix = sanitize_user(current(explode('@', $email)), true);
        if ($email_prefix === '') {
            $email_prefix = 'patient';
        }

        $username = $email_prefix;
        while (username_exists($username)) {
            $username = $email_prefix . '_' . wp_rand(1000, 99999);
        }

        // Create new WordPress user with a random password
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass'  => wp_generate_password(32, true, true),
            'user_email' => $email,
            'role'       => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            error_log('Secure login: user creation failed: ' . $user_id->get_error_message());
            return;
        }

        $user = get_user_by('id', $user_id);

    } else {

        $user_id = $user->ID;
    }

    /*
     * Passwordless links must never work for staff accounts. Administrators,
     * editors and anyone else who can edit content must sign in with a password.
     */
    if (mcn_user_is_privileged($user_id)) {
        error_log('Secure login: link refused for privileged user ID ' . (int) $user_id);
        return;
    }

    /*
     * Generate the login token. Only an HMAC of it is stored, so a database or
     * backup leak cannot be turned into a working login link.
     */
    $token = wp_generate_password(32, false, false);

    update_user_meta($user_id, 'secure_login_token_hash', mcn_secure_login_token_hash($token));
    update_user_meta($user_id, 'secure_login_token_expires', time() + (15 * MINUTE_IN_SECONDS));
    delete_user_meta($user_id, 'secure_login_token'); // legacy plaintext token

    /*
     * Secure login URL
     */
    $login_url = add_query_arg(
        array(
            'secure_login' => 1,
            'uid'          => $user_id,
            'token'        => $token,
        ),
        home_url('/medical-card-profile/')
    );

    /*
     * Email content
     */
    $subject = 'Your Secure Login Link - Med Cards Now';

    $message = '
        <html>
        <body>
            <p>Hello,</p>

            <p>Click the button below to securely access your profile:</p>

            <p>
                <a href="' . esc_url($login_url) . '"
                   style="
                       display:inline-block;
                       padding:12px 20px;
                       background:#0073aa;
                       color:#ffffff;
                       text-decoration:none;
                       border-radius:5px;
                   ">
                    Secure Login
                </a>
            </p>

            <p>This link will expire in 15 minutes.</p>

            <p>If you did not request this link, you can ignore this email.</p>
        </body>
        </html>
    ';

    $headers = array(
        'From: Med Cards Now <contact@medcardsnow.com>',
        'Reply-To: contact@medcardsnow.com',
        'Content-Type: text/html; charset=UTF-8'
    );

    $mail_sent = wp_mail($email, $subject, $message, $headers);

    if (!$mail_sent) {
        error_log('Secure login email FAILED for user ID ' . (int) $user_id);
    }
}



add_action('init', 'handle_secure_login_link');
function handle_secure_login_link()
{

    if (!isset($_GET['secure_login'], $_GET['uid'], $_GET['token'])) {
        return;
    }

    $user_id = absint($_GET['uid']);
    $token   = sanitize_text_field(wp_unslash($_GET['token']));

    if (!$user_id || $token === '') {
        wp_die('Invalid login link.');
    }

    $user = get_userdata($user_id);

    if (!$user) {
        wp_die('Login link is invalid or expired.');
    }

    $saved_hash    = (string) get_user_meta($user_id, 'secure_login_token_hash', true);
    $legacy_token  = (string) get_user_meta($user_id, 'secure_login_token', true);
    $token_expires = (int) get_user_meta($user_id, 'secure_login_token_expires', true);

    if (($saved_hash === '' && $legacy_token === '') || !$token_expires) {
        wp_die('Login link is invalid or expired.');
    }

    if (time() > $token_expires) {
        delete_user_meta($user_id, 'secure_login_token_hash');
        delete_user_meta($user_id, 'secure_login_token');
        delete_user_meta($user_id, 'secure_login_token_expires');
        wp_die('Login link has expired.');
    }

    // Links issued before this update stored the token in plain text; they
    // stay valid until they expire (15 minutes at most).
    $is_valid = ($saved_hash !== '')
        ? hash_equals($saved_hash, mcn_secure_login_token_hash($token))
        : hash_equals($legacy_token, $token);

    if (!$is_valid) {
        wp_die('Invalid login token.');
    }

    // Single use: remove the token before logging in.
    delete_user_meta($user_id, 'secure_login_token_hash');
    delete_user_meta($user_id, 'secure_login_token');
    delete_user_meta($user_id, 'secure_login_token_expires');

    // Staff accounts must sign in with a password.
    if (mcn_user_is_privileged($user_id)) {
        wp_die('This account must sign in with a password.');
    }

    // Log user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    do_action('wp_login', $user->user_login, $user);

    // Redirect after login
    wp_safe_redirect(home_url('/medical-card-profile/'));
    exit;
}
