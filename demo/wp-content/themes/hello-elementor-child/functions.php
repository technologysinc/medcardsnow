<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if (!function_exists('chld_thm_cfg_locale_css')):
    function chld_thm_cfg_locale_css($uri)
    {
        if (empty($uri) && is_rtl() && file_exists(get_template_directory() . '/rtl.css'))
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter('locale_stylesheet_uri', 'chld_thm_cfg_locale_css');

if (!function_exists('child_theme_configurator_css')):
    function child_theme_configurator_css()
    {
        wp_enqueue_style('chld_thm_cfg_child', trailingslashit(get_stylesheet_directory_uri()) . 'style.css', array('hello-elementor', 'hello-elementor-theme-style', 'hello-elementor-header-footer'));
    }
endif;
add_action('wp_enqueue_scripts', 'child_theme_configurator_css', 10);

// END ENQUEUE PARENT ACTION


// Select2 enqueue
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'select2-css',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'
    );

    wp_enqueue_script(
        'select2-js',
        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        array('jquery'),
        null,
        true
    );
});

// Load Dompdf without changing the Authorize.Net SDK.
$dompdf_autoload = get_stylesheet_directory()
    . '/custom-authorize-payment/dompdf-libs/autoload.inc.php';

if (is_readable($dompdf_autoload)) {
    require_once $dompdf_autoload;
}









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
 * 2. Global Email Configuration
 * =========================================================
 *
 * All WordPress and Gravity Forms emails should use:
 *
 * contact@demo.medcardsnow.com
 *
 * WP Mail SMTP Force From Email should remain enabled.
 */


/**
 * Change WordPress email sender name
 */
add_filter('wp_mail_from_name', function ($name) {

    return 'Med Cards Now';

});


/**
 * Change WordPress email sender address
 */
add_filter('wp_mail_from', function ($email) {

    return 'contact@demo.medcardsnow.com';

});


/**
 * Force Gravity Forms notification From email
 *
 * This applies to Gravity Forms notifications.
 */
add_filter('gform_notification', function ($notification, $form, $entry) {

    if (!is_array($notification)) {
        return $notification;
    }

    $notification['from'] = 'contact@demo.medcardsnow.com';

    /**
     * Optional:
     * Set Reply-To only if required.
     *
     * Keep the existing Reply-To configuration
     * unless you specifically need to change it.
     */

    return $notification;

}, 10, 3);


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
        'From: Med Cards Now <contact@demo.medcardsnow.com>',
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


//Populate Conditions by State
add_filter('gform_pre_render_2', 'populate_conditions_by_user_state');
add_filter('gform_pre_validation_2', 'populate_conditions_by_user_state');
add_filter('gform_pre_submission_filter_2', 'populate_conditions_by_user_state');
add_filter('gform_admin_pre_render_2', 'populate_conditions_by_user_state');

function medcards_get_state_conditions()
{
    return array(
        'Arizona' => array(
            'AIDS/HIV',
            'ALS',
            'Crohn’s disease',
            'Agitation of Alzheimer’s disease',
            'Cancer',
            'Glaucoma',
            'Hepatitis C',
            'PTSD',
            'A medical condition that causes cachexia or wasting syndrome',
            'A medical condition that causes severe and chronic pain',
            'A medical condition that causes severe nausea',
            'A medical condition that causes seizures including those characteristic of epilepsy',
            'Severe or persistent muscle spasms including those characteristic of multiple sclerosis',
        ),
        'California' => array(
            'AIDS',
            'Anorexia',
            'Arthritis',
            'Cachexia',
            'Cancer',
            'Chronic pain',
            'Glaucoma',
            'Migraines',
            'Seizures',
            'Severe nausea',
            'Muscle spasms',
            'Or any other chronic or persistent symptom that either substantially limits a person’s ability to conduct one or more of major life activities as defined by the ADA or may cause serious harm to the person’s safety, mental, or physical health if not alleviated.',
        ),
        'Connecticut' => array(
            'Amyotrophic Lateral Sclerosis',
            'Autism Spectrum Disorder',
            'Cachexia',
            'Cancer',
            'Cerebral Palsy',
            'Chronic Neuropathic Pain Associated with Degenerative Spinal Disorders',
            'Chronic Pain of at least 6 months duration',
            'Chronic Pancreatitis',
            'Complex Regional Pain Syndrome, Type 1 and Type 2',
            'Crohn’s Disease',
            'Cystic Fibrosis',
            'Damage to the Nervous Tissue of the Spinal Cord with Objective Neurological Indication of Intractable Spasticity',
            'Ehlers-Danlos Syndrome Associated with Chronic Pain',
            'Epilepsy',
            'Female Orgasmic Difficulty/Disorder',
            'Glaucoma',
            'Hydrocephalus with Intractable Headache',
            'Intractable Headache Syndromes',
            'Intractable Neuropathic Pain that Is Unresponsive to Standard Medical Treatments',
            'Interstitial Cystitis',
            'Irreversible Spinal Cord Injury with Objective Neurological Indication of Intractable Spasticity',
            'MALS Syndrome (Median Arcuate Ligament Syndrome)',
            'Movement Disorders Associated with Huntington Disease',
            'Multiple Sclerosis',
            'Muscular Dystrophy',
            'Neuropathic Facial Pain',
            'Osteogenesis Imperfecta',
            'Parkinson’s Disease',
            'Positive Status for HIV or AIDS',
            'Post Herpetic Neuralgia',
            'Post Laminectomy Syndrome with Chronic Radiculopathy',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Severe Psoriasis and Psoriatic Arthritis',
            'Severe Rheumatoid Arthritis',
            'Sickle Cell Disease',
            'Spasticity or Neuropathic Pain Associated with Fibromyalgia',
            'Terminal Illness Requiring End-Of-Life Care',
            'Tourette Syndrome',
            'Ulcerative Colitis',
            'Uncontrolled Intractable Seizure Disorder',
            'Vulvodynia and Vulvar Burning',
            'Wasting Syndrome',
        ),
        'Florida' => array(
            'AIDS/HIV',
            '(ALS) Amyotrophic Lateral Sclerosis (Lou Gehrig’s Disease)',
            'Cancer',
            'Crohn’s Disease',
            'Epilepsy',
            'Glaucoma',
            'Multiple Sclerosis (MS)',
            'Parkinson’s Disease',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Medical conditions of the same kind or class as or comparable to those listed above',
            'A terminal condition diagnosed by a physician other than the physician issuing the certificatloginion',
            'Chronic nonmalignant pain',
        ),
        'Georgia' => array(
            'AIDS when such syndrome is severe or end stage',
            'Alzheimer’s disease, when such disease is severe or end stage',
            'Amyotrophic lateral sclerosis, when such diagnosis is severe or end stage',
            'Autism spectrum disorder',
            'Cancer, when such diagnosis is end stage or the treatment produces related wasting illness or recalcitrant nausea and vomiting',
            'Crohn’s disease',
            'Epidermolysis bullosa',
            'Intractable pain',
            'Mitochondrial disease',
            'Multiple sclerosis, when such diagnosis is severe or end stage',
            'Parkinson’s disease, when such diagnosis is severe or end stage',
            'Peripheral neuropathy, when symptoms are severe or end stage',
            'Post-traumatic stress disorder (PTSD)',
            'Seizure disorders related to diagnosis of epilepsy or trauma related head injuries',
            'Sickle cell disease, when such diagnosis is severe or end stage',
            'Tourette’s syndrome, when such syndrome is diagnosed as severe',
            'Patient is in hospice program, either as inpatient or outpatient',
        ),
        'Illinois' => array(
            'Autism',
            'Agitation of Alzheimer’s disease',
            'HIV/AIDS',
            'Amyotrophic lateral sclerosis (ALS)',
            'Anorexia',
            'Arnold-Chiari malformation',
            'Cancer',
            'Cachexia/wasting syndrome',
            'Causalgia',
            'Chronic inflammatory demyelinating polyneuropathy',
            'Chronic pain',
            'Crohn’s disease',
            'CRPS (complex regional pain syndrome Type II)',
            'Dystonia',
            'Ehlers-Danlos syndrome',
            'Endometriosis',
            'Female orgasmic disorder',
            'Fibrous Dysplasia',
            'Glaucoma',
            'Hepatitis C',
            'Hydrocephalus',
            'Hydromyelia',
            'Interstitial cystitis',
            'Irritable bowel syndrome',
            'Lupus',
            'Migraines',
            'Multiple Sclerosis',
            'Muscular Dystrophy',
            'Myasthenia Gravis',
            'Myoclonus',
            'Nail-patella syndrome',
            'Neuro-Behcet’s autoimmune disease',
            'Neurofibromatosis',
            'Neuropathy',
            'Osteoarthritis',
            'Ovarian Cysts',
            'Parkinson’s disease',
            'Polycystic kidney disease (PKD)',
            'Post-concussion syndrome',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Reflex sympathetic dystrophy',
            'Residual limb pain',
            'Rheumatoid arthritis',
            'Seizures, including those from Epilepsy',
            'Severe fibromyalgia',
            'Sjogren’s syndrome',
            'Spinal cord disease',
            'Spinal cord injury causing neurological spasticity',
            'Spinocerebellar ataxia',
            'Superior canal dehiscence syndrome',
            'Syringomyelia',
            'Tarlov cysts',
            'Tourette syndrome',
            'Traumatic brain injury',
            'Ulcerative colitis',
            'Uterine fibroids',
        ),
        'Iowa' => array(
            'AIDS or HIV',
            'Amyotrophic lateral sclerosis',
            'Cancer with severe or chronic pain',
            'Cancer with nausea or severe vomiting',
            'Cancer with cachexia or severe wasting',
            'Chronic pain',
            'Corticobasal degeneration',
            'Crohn’s disease',
            'Multiple sclerosis with severe and persistent muscle spasms',
            'Parkinson’s disease',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Seizures, including those characteristic of epilepsy',
            'Severe, intractable autism with self-injurious or aggressive behaviors',
            'Ulcerative colitis',
            'Any terminal illness with severe or chronic pain',
            'Any terminal illness with nausea or severe vomiting',
            'Any terminal illness with cachexia or severe wasting',
        ),
        'Louisiana' => array(
            'Cancer',
            'Glaucoma',
            'HIV/AIDS',
            'Cachexia or wasting syndrome',
            'Seizure disorders',
            'Spasticity',
            'Severe muscle spasms',
            'Intractable pain',
            'Crohn’s disease',
            'Muscular dystrophy',
            'Multiple sclerosis',
            'Post-traumatic stress disorder (PTSD)',
            'Traumatic brain injury',
            'A concussion diagnosed by a physician',
            'Chronic pain',
            'Any condition for which a patient is receiving hospice care or palliative care',
            'Alzheimer’s disease',
        ),
        'Maine' => array(
            'Alzheimer’s disease',
            'Amyotrophic lateral sclerosis (ALS)',
            'Cachexia or wasting syndrome',
            'Cancer',
            'Chronic pain',
            'Crohn’s disease',
            'Epilepsy',
            'Glaucoma',
            'Hepatitis C',
            'HIV/AIDS',
            'Huntington’s disease',
            'Inflammatory bowel disease (IBD)',
            'Multiple sclerosis (MS)',
            'Nausea',
            'Nail-patella syndrome',
            'Parkinson’s disease',
            'Post-traumatic stress disorder (PTSD)',
            'Any other condition the qualifying physician believes may benefit from medical cannabis',
        ),
        'Maryland' => array(
            'Cachexia',
            'Wasting syndrome',
            'Anorexia',
            'Severe or chronic pain',
            'Severe nausea',
            'Seizures',
            'Severe or persistent muscle spasms',
            'Glaucoma',
            'Post-traumatic stress disorder (PTSD)',
            'A chronic medical condition which is severe and for which other treatments have been ineffective',
        ),
        'Massachusetts' => array(
            'Cancer',
            'HIV/AIDS',
            'Amyotrophic Lateral Sclerosis (ALS)',
            'Crohn’s disease',
            'Parkinson’s disease',
            'Multiple sclerosis',
        ),
        'Michigan' => array(
            'Agitation of Alzheimer’s disease',
            'AIDS/HIV',
            'Arthritis',
            'Autism',
            'Cancer',
            'Cerebral palsy',
            'Chronic pain',
            'Colitis',
            'Glaucoma',
            'Hepatitis C',
            'Inflammatory bowel disease (IBD)',
            'Nail patella',
            'Obsessive-compulsive disorder (OCD)',
            'Parkinson’s disease',
            'Post-traumatic stress disorder (PTSD)',
            'Rheumatoid arthritis',
            'Spinal cord injury',
            'Tourette’s disease',
            'Any medical condition that includes or its treatment causes wasting syndrome, cachexia, severe nausea, severe pain, seizures, or muscle spasms.',
        ),
        'Minnesota' => array(
            "Alzheimer's disease",
            'Amyotrophic Lateral Sclerosis (ALS)',
            'Autism',
            'Cancer',
            'Glaucoma',
            'HIV/AIDS',
            'Inflammatory Bowel disease, including Crohn\'s disease',
            'Intractable Pain',
            'Obstructive Sleep Apnea',
            'Other',
            'Post Traumatic Stress Disorder (PTSD)',
            'Seizures, including those characteristic of epilepsy',
            'Severe and persistent muscle spasm, including those characteristic of multiple sclerosis',
            'Terminal illness with a probable life expectancy of less than one year',
            'Tourette syndrome',
        ),
        'Missouri' => array(
            'ALS',
            'Alzheimer’s',
            'Autism',
            'Cachexia',
            'Cancer',
            'Chronic pain',
            'Chronic muscle spasms',
            'Crohn’s',
            'Debilitating psychiatric disorders',
            'Epilepsy',
            'Glaucoma',
            'HIV or AIDS',
            'Hepatitis C',
            'Huntington’s Disease',
            'Inflammatory bowel disease',
            'Intractable migraines',
            'Neuropathy',
            'Sickle cell anemia',
            'Wasting syndrome',
            'Any terminal illness',
            'Any other chronic, debilitating or other medical condition',
            'Any chronic condition that normally requires prescriptions that could lead to dependence',
        ),
        'Nevada' => array(
            'Acquired Immune Deficiency Syndrome (AIDS)',
            'Anxiety disorder',
            'An autism spectrum disorder',
            'Autoimmune disease',
            'Anorexia nervosa',
            'Cancer',
            'Cachexia',
            'Dependence upon or addiction to opioids',
            'Glaucoma',
            'Persistent muscle spasms including spasms caused by multiple sclerosis',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Seizures, including without limitation, seizures caused by epilepsy',
            'Severe pain',
            'Severe nausea',
            'A medical condition related to HIV',
            'A neuropathic condition, whether or not such condition causes seizures',
        ),
        'New Jersey' => array(
            'Amyotrophic lateral sclerosis (ALS)',
            'Anxiety',
            'Cancer',
            'Chronic pain',
            'Dysmenorrhea',
            'Glaucoma',
            'Inflammatory bowel disease (IBD), including Crohn’s disease',
            'Intractable skeletal muscular spasticity',
            'Migraine',
            'Multiple sclerosis (MS)',
            'Muscular dystrophy',
            'Opioid use disorder',
            'Positive Status for HIV or AIDS',
            'Post-traumatic stress disorder (PTSD)',
            'Seizure disorders, including epilepsy',
            'Terminal Illness Requiring End-Of-Life Care',
            'Tourette Syndrome',
        ),
        'New York' => array(
            'AIDS',
            'ALS',
            'Alzheimer’s disease',
            'Anxiety',
            'Cancer',
            'Chronic pain',
            'Crohn’s disease',
            'Epilepsy',
            'HIV',
            "Huntington's disease",
            'Inflammatory bowel disease',
            'Multiple sclerosis',
            'Neuropathy',
            'Other condition',
            "Parkinson's disease",
            'Post-traumatic stress disorder (PTSD)',
            'Spinal cord injury with spasticity',
            'Stress',
        ),
        'Ohio' => array(
            'AIDS',
            'Amyotrophic lateral sclerosis (ALS)',
            'Alzheimer’s disease',
            'Cachexia',
            'Cancer',
            'Chronic traumatic encephalopathy',
            'Crohn’s disease',
            'Epilepsy or another seizure disorder',
            'Fibromyalgia',
            'Glaucoma',
            'Hepatitis C',
            'Huntington’s disease',
            'Inflammatory bowel disease',
            'Multiple sclerosis',
            'Pain that is either chronic and severe or intractable',
            'Parkinson’s disease',
            'Positive status for HIV',
            'Post-traumatic stress disorder (PTSD)',
            'Sickle cell anemia',
            'Spasticity',
            'Spinal cord disease or injury',
            'Terminal illness',
            'Tourette syndrome',
            'Traumatic brain injury',
            'Ulcerative colitis',
        ),
        'Oklahoma' => array(
            'AIDS',
            'ALS',
            'Alzheimer’s disease',
            'Anorexia',
            'Anxiety',
            'Arthritis',
            'Cachexia',
            'Cancer',
            'Chronic pain',
            'Crohn’s disease',
            'Epilepsy',
            'Glaucoma',
            'Hepatitis C',
            'HIV',
            "Huntington's disease",
            'Inflammatory bowel disease',
            'Insomnia',
            'Migraines',
            'Multiple sclerosis',
            'Neuropathy',
            'Opioid use',
            'Other condition',
            "Parkinson's disease",
            'Persistent muscle spasms',
            'PISD',
            'Post-traumatic stress disorder (PTSD)',
            'Seizures',
            'Severe nausea',
            'Stress',
            "Tourette's syndrome",
        ),
        'Pennsylvania' => array(
            'Amyotrophic lateral sclerosis (ALS)',
            'Anxiety Disorders',
            'Autism',
            'Cancer, including remission therapy',
            'Crohn’s disease',
            'Damage to the nervous tissue of the central nervous system',
            'Dyskinetic and spastic movement disorders',
            'Fibromyalgia',
            'Glaucoma',
            'Hepatitis C',
            'Inflammatory bowel disease (IBS)',
            'Multiple sclerosis (MS)',
            'Pain that is either chronic and severe or intractable',
            'Parkinson’s disease',
            'Positive status for HIV',
            'Post-traumatic stress disorder (PTSD)',
            'Sickle cell anemia',
            'Spinal cord disease or injury',
            'Tourette’s syndrome',
            'Traumatic brain injury',
            'Ulcerative colitis',
        ),
        'Texas' => array(
            'Amyotrophic lateral sclerosis',
            'Autism',
            'Cancer',
            'Chronic pain',
            'Crohn’s disease',
            'Epilepsy',
            'Inflammatory bowel disease (IBD)',
            'Multiple sclerosis',
            'Post-traumatic stress disorder (PTSD)',
            'Seizure disorders',
            'Spasticity',
            'Traumatic brain injury (TBI)',
            'An incurable neurodegenerative disease',
            'A medical condition designated by the Health and Human Services Commission',
            'Terminal illnesses',
            'Patients receiving hospice care',
        ),
        'Virginia' => array(
            'AIDS',
            'Anorexia',
            'Anxiety',
            'Arthritis',
            'Cachexia',
            'Cancer',
            'Chronic Pain',
            'Glaucoma',
            'Insomnia',
            'Migraines',
            'Muscle Spasms',
            'Nausea',
            'Other condition',
            'Post-traumatic stress disorder (PTSD)',
            'Seizures',
            'Stress',
        ),
        'Washington' => array(
            'Cancer',
            'Chronic renal failure requiring hemodialysis',
            'Crohn’s disease',
            'Diseases resulting in nausea, vomiting, wasting, appetite loss, cramping, seizures, muscle spasms, or spasticity',
            'Epilepsy',
            'Glaucoma',
            'Hepatitis C with debilitating nausea or intractable pain',
            'Human immunodeficiency virus (HIV)',
            'Intractable pain',
            'Multiple sclerosis, or other seizure disorder, or spasticity disorders',
            'Posttraumatic stress disorder (PTSD)',
            'Traumatic brain injury',
        ),
        'West Virginia' => array(
            'ALS',
            'Cancer',
            'Crohn’s Disease',
            'Damage to the nervous tissue of the spinal cord with objective neurological indication of intractable spasticity',
            'Epilepsy',
            'HIV or AIDS',
            'Huntington’s Disease',
            'Intractable Seizures',
            'Multiple Sclerosis',
            'Neuropathies',
            'Parkinson’s Disease',
            'Post-Traumatic Stress Disorder (PTSD)',
            'Severe chronic or intractable pain of neuropathic origin or severe chronic or intractable pain',
            'Sickle Cell Anemia',
            'Terminal illness with life expectancy of approximately one year or less',
        ),
    );
}

function populate_conditions_by_user_state($form)
{

    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if (!$state) {
        return $form;
    }

    $conditions_by_state = medcards_get_state_conditions();

    if (!isset($conditions_by_state[$state])) {
        return $form;
    }

    $choices = array();

    foreach ($conditions_by_state[$state] as $condition) {
        $choices[] = array(
            'text'  => $condition,
            'value' => $condition,
        );
    }

    foreach ($form['fields'] as &$field) {
        if ((int) $field->id === 1) {
            $field->choices = $choices;
        }
    }

    return $form;
}


add_action('gform_after_submission_2', 'save_conditions_to_acf_user_field', 10, 2);


function save_conditions_to_acf_user_field($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // Gravity Forms field value
    $conditions = rgar($entry, '1');

    if (empty($conditions)) {
        return;
    }

    // Convert to array for ACF multi-select
    if (!is_array($conditions)) {
        $conditions = array_map('trim', explode(',', $conditions));
    }

    // Save to normal user meta too
    update_user_meta($user_id, 'medical_conditions', $conditions);

    // Save to ACF user field
    if (function_exists('update_field')) {
        update_field('medical_conditions', $conditions, 'user_' . $user_id);
    }
}



// Sorting of users 
add_action('pre_get_users', 'medcards_sort_users_by_registration_date');
function medcards_sort_users_by_registration_date($query)
{

    global $pagenow;

    // Only affect the Users admin screen
    if (
        is_admin() &&
        $pagenow === 'users.php' &&
        !isset($_GET['orderby']) // Don't override manual sorting
    ) {
        $query->set('orderby', 'registered');
        $query->set('order', 'DESC');
    }
}


// download button
add_action('acf/render_field/name=patients_id', 'medcards_add_download_link_to_acf_file_field');

function medcards_add_download_link_to_acf_file_field($field)
{

    if (empty($field['value'])) {
        return;
    }

    $attachment_id = $field['value'];

    // Handle ACF returning an array
    if (is_array($attachment_id) && isset($attachment_id['ID'])) {
        $attachment_id = $attachment_id['ID'];
    }

    $file_url = wp_get_attachment_url($attachment_id);

    if ($file_url) {
        echo '<p style="margin-top:10px;">
            <a href="' . esc_url($file_url) . '" class="button button-primary" download target="_blank">
                Download Patient ID
            </a>
        </p>';
    }
}


// Disable Reset password email 

/* ------------------------------------------------
   Disable WordPress Password & User Emails
------------------------------------------------ */

/* Disable password reset emails */
add_filter('send_password_change_email', '__return_false');
add_filter('retrieve_password_message', '__return_empty_string');

function disable_reset_password_email()
{
    return false;
}
add_filter('send_retrieve_password_email', 'disable_reset_password_email');

/* Disable password reset functionality completely */
add_filter('allow_password_reset', '__return_false');

/* Disable new user notification email to user */
remove_action('register_new_user', 'wp_send_new_user_notifications');
remove_action('edit_user_created_user', 'wp_send_new_user_notifications', 10, 2);

add_filter('wp_new_user_notification_email', '__return_false');

/* Disable WooCommerce customer new account email */
add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');

/* Disable admin notification for new users */
add_filter('wp_send_new_user_notification_to_admin', '__return_false');

/* Disable user notification for new users */
add_filter('wp_send_new_user_notification_to_user', '__return_false');


// Phone and Date - Time
/* ==============================
   Med Cards Now - Users Admin Columns
   Phone + Registration Date
   ============================== */

/* Add new columns */
add_filter('manage_users_columns', function ($columns) {

    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'email') {
            $new_columns['phone_number'] = 'Phone #';
            $new_columns['registration_date'] = 'Registration Date';
        }
    }

    return $new_columns;
});


/* Show column values */
add_filter('manage_users_custom_column', function ($value, $column_name, $user_id) {

    if ($column_name === 'phone_number') {

        // Change this meta key if your phone field key is different
        $phone = get_user_meta($user_id, 'phone', true);

        if (!$phone) {
            $phone = get_user_meta($user_id, 'phone_number', true);
        }

        if (!$phone) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
        }

        return $phone ? esc_html($phone) : '—';
    }

    if ($column_name === 'registration_date') {
        $user = get_userdata($user_id);

        if (!empty($user->user_registered)) {
            return date_i18n('M d, Y', strtotime($user->user_registered));
        }

        return '—';
    }

    return $value;
}, 10, 3);


/* Make registration date sortable */
add_filter('manage_users_sortable_columns', function ($columns) {
    $columns['registration_date'] = 'registered';
    return $columns;
});


// Get / renew medical card 
add_action('template_redirect', 'mcn_state_based_redirects');

function mcn_state_based_redirects()
{

    // Current page path
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Allowed redirect pages
    $allowed_pages = [
        'get-medical-card',
        'renew-medical-card'
    ];

    // Stop if not target pages
    if (! in_array($request_path, $allowed_pages)) {
        return;
    }

    // Get user IP
    $ip = $_SERVER['REMOTE_ADDR'];

    // Local testing fallback
    if ($ip === '127.0.0.1' || $ip === '::1') {
        $ip = '8.8.8.8';
    }

    // Get visitor location
    $response = wp_remote_get("http://ip-api.com/json/{$ip}");

    if (is_wp_error($response)) {
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Only allow US visitors
    if (
        empty($body['countryCode']) ||
        strtoupper($body['countryCode']) !== 'US'
    ) {
        return;
    }

    // Get state
    if (empty($body['regionName'])) {
        return;
    }

    $state_slug = sanitize_title($body['regionName']);

    /*
     |--------------------------------------------------------------------------
     | Build URL based on clicked page
     |--------------------------------------------------------------------------
     */

    if ($request_path === 'get-medical-card') {

        $target_url = home_url("/{$state_slug}-medical-marijuana-card/");
    } elseif ($request_path === 'renew-medical-card') {

        $target_url = home_url("/{$state_slug}-medical-marijuana-card/");
    } else {
        return;
    }

    /*
     |--------------------------------------------------------------------------
     | Check if page exists
     |--------------------------------------------------------------------------
     */

    $check = wp_remote_head($target_url);

    if (wp_remote_retrieve_response_code($check) != 200) {

        // Fallback pages
        if ($request_path === 'get-medical-card') {
            $target_url = home_url('/medical-card/');
        }

        if ($request_path === 'renew-medical-card') {
            $target_url = home_url('/renew-medical-card/');
        }
    }

    wp_redirect($target_url);
    exit;
}


// CurrentFuture Date Picker
add_filter('gform_field_validation_4_6', function ($result, $value, $form, $field) {

    if (empty($value)) {
        return $result;
    }

    $selected_date = DateTime::createFromFormat('m/d/Y', $value);
    $today = new DateTime('today');

    if ($selected_date && $selected_date < $today) {
        $result['is_valid'] = false;
        $result['message'] = 'Please select today or a future date.';
    }

    return $result;
}, 10, 4);

add_filter('gform_datepicker_options_pre_init', function ($options, $form_id, $field_id) {

    if ($form_id == 1 && $field_id == 6) {
        $options['minDate'] = 0;
    }

    return $options;
}, 10, 3);


// ID and signature upload to meta
// ID and signature upload to meta
add_action('gform_after_submission_2', 'save_gf_uploaded_file_to_acf_user_field', 10, 2);

function save_gf_uploaded_file_to_acf_user_field($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // GF multiple file upload field value
    $file_value = rgar($entry, '13'); // change field ID

    if (empty($file_value)) {
        return;
    }

    $file_url = '';

    // If multiple files, GF usually stores JSON
    $decoded = json_decode($file_value, true);

    if (is_array($decoded) && !empty($decoded)) {
        $file_url = $decoded[0]; // first uploaded file
    } else {
        $file_url = $file_value; // fallback
    }

    if (empty($file_url)) {
        return;
    }

    // Try to get existing attachment ID first
    $attachment_id = attachment_url_to_postid($file_url);

    // If not already in media library, create attachment
    if (!$attachment_id) {
        $attachment_id = medcards_create_attachment_from_local_url($file_url, $user_id);
    }

    if (!$attachment_id) {
        return;
    }

    // Save normal user meta too
    update_user_meta($user_id, 'patients_id', $attachment_id);

    // Save to ACF file field
    if (function_exists('update_field')) {
        update_field('patients_id', $attachment_id, 'user_' . $user_id);
    }
}



/**
 * Create a WP attachment from a local uploaded file URL
 */
function medcards_create_attachment_from_local_url($file_url, $user_id = 0)
{

    if (empty($file_url)) {
        return 0;
    }

    $upload_dir = wp_upload_dir();

    // Only handle local uploads
    if (strpos($file_url, $upload_dir['baseurl']) !== 0) {
        return 0;
    }

    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

    if (!file_exists($file_path)) {
        return 0;
    }

    $filetype = wp_check_filetype(basename($file_path), null);

    $attachment = array(
        'guid'           => $file_url,
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file_path)),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => $user_id ? $user_id : get_current_user_id(),
    );

    $attachment_id = wp_insert_attachment($attachment, $file_path);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    return $attachment_id;
}


    add_action('gform_after_submission_2', 'save_gf_signature_to_acf_user_field', 20, 2);

function save_gf_signature_to_acf_user_field($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // Raw entry value from the signature field.
    $raw_signature_value = rgar($entry, '14');

    if (empty($raw_signature_value)) {
        return;
    }

    $raw_signature_value = trim($raw_signature_value);

    // Build a usable signature image URL.
    $signature_url = medcards_get_signature_url_from_entry_value($raw_signature_value);

    if (empty($signature_url)) {
        return;
    }

    // Import into Media Library as a real attachment.
    $attachment_id = medcards_import_signature_to_media($signature_url, $user_id);

    if (!$attachment_id) {
        return;
    }

    // Keep your typo field name if that is the real ACF field name.
    update_user_meta($user_id, 'patinet_signature', $attachment_id);

    if (function_exists('update_field')) {
        update_field('patinet_signature', $attachment_id, 'user_' . $user_id);
    }
}

function medcards_get_signature_url_from_entry_value($value)
{

    // Case 1: already a gf-signature URL or normal URL
    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }

    // Case 2: Gravity Forms raw entry value is the filename
    if (function_exists('gf_signature')) {
        $url = gf_signature()->get_signature_url($value);
        if (!empty($url)) {
            return $url;
        }
    }

    return '';
}

function medcards_import_signature_to_media($signature_url, $user_id = 0)
{

    if (empty($signature_url)) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($signature_url);

    if (is_wp_error($tmp)) {
        error_log('GF signature download failed: ' . $tmp->get_error_message());
        return 0;
    }

    $file_array = array(
        'name'     => 'patient-signature-' . time() . '.png',
        'tmp_name' => $tmp,
    );

    $attachment_id = media_handle_sideload($file_array, 0);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        error_log('GF signature sideload failed: ' . $attachment_id->get_error_message());
        return 0;
    }

    if ($user_id) {
        wp_update_post(array(
            'ID'          => $attachment_id,
            'post_author' => $user_id,
        ));
    }

    return $attachment_id;
}


// Secure login link - Login Form page
/* Change WordPress email sender name */
add_filter('wp_mail_from_name', function ($name) {
    return 'Med Cards Now';
});

/* Change sender email address */
add_filter('wp_mail_from', function ($email) {
    return 'contact@demo.medcardsnow.com';
});

// add_action('gform_after_submission_3', 'send_secure_login_link_from_gravity_form', 10, 2);

// function send_secure_login_link_from_gravity_form($entry, $form)
// {

// 	error_log('Secure login function started');
	
//     $email = rgar($entry, '1'); // email field ID
	
// 	error_log('Entered email: ' . print_r($email, true));

// //     if (!$email || !is_email($email)) {
// //         return;
// //     }
	
// 	if (!$email || !is_email($email)) {
//         error_log('Invalid email: ' . print_r($email, true));
//         return;
//     }

//     $user = get_user_by('email', $email);
	
// 	if (!$user) {
//         error_log('User not found: ' . $email);
//         return;
//     }

//     error_log('User found: ' . $user->user_email);

//     // Do nothing if user not found
// //     if (!$user) {
// //         return;
// //     }

//     $user_id = $user->ID;

//     // Get user name
//     $first_name = get_user_meta($user_id, 'first_name', true);
//     $last_name  = get_user_meta($user_id, 'last_name', true);

//     if ($first_name || $last_name) {
//         $name = trim($first_name . ' ' . $last_name);
//     } else {
//         $name = $user->display_name;
//     }

//     // Create secure token
//     $token = wp_generate_password(32, false, false);

//     // Save token and expiry
//     update_user_meta($user_id, 'secure_login_token', $token);
//     update_user_meta($user_id, 'secure_login_token_expires', time() + (15 * MINUTE_IN_SECONDS));

//     // Create login URL
//     $login_url = add_query_arg(array(
//         'secure_login' => 1,
//         'uid'          => $user_id,
//         'token'        => $token,
//     ), home_url('/medical-card-profile/'));

//     $subject = 'Your secure login link';

//     $headers = array('Content-Type: text/html; charset=UTF-8');

//     $message = '
// <html>
// <body style="margin:0;padding:0;background:#f4f7f9;font-family:Arial,sans-serif;color:#222;line-height:1.6;">

//     <table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:40px 20px;">
//         <tr>
//             <td align="center">

//                 <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);">

//                     <!-- Logo -->
//                     <tr>
//                         <td align="center" style="padding:35px 20px 10px;">
//                             <img 
//                                 src="https://demo.medcardsnow.com/wp-content/uploads/2026/01/medcardsnow_logo_transparent.png" 
//                                 alt="Med Cards Now" 
//                                 style="max-width:220px;height:auto;"
//                             >
//                         </td>
//                     </tr>

//                     <!-- Content -->
//                     <tr>
//                         <td style="padding:10px 40px 40px;">

//                             <p style="font-size:16px;margin-top:0;">
//                                 Hi ' . esc_html($name) . ',
//                             </p>

//                             <p style="font-size:15px;color:#555;">
//                                 We received a request to sign in to your account.
//                             </p>

//                             <p style="text-align:center;margin:35px 0;">
//                                 <a href="' . esc_url($login_url) . '" 
//                                    style="
//                                         display:inline-block;
//                                         padding:14px 28px;
//                                         background:#2bb3b3;
//                                         color:#ffffff;
//                                         text-decoration:none;
//                                         border-radius:8px;
//                                         font-weight:600;
//                                         font-size:15px;
//                                    ">
//                                     Securely Log In to Med Cards Now
//                                 </a>
//                             </p>

//                             <p style="font-size:14px;color:#666;">
//                                 For your security, this link will expire in 15 minutes and can only be used once.
//                             </p>

//                             <p style="font-size:14px;color:#666;">
//                                 If you did not request this sign-in link, you can safely ignore this email.
//                             </p>

//                             <p style="margin-top:30px;font-size:15px;">
//                                 Thank you,<br>
//                                 <strong>' . esc_html(get_bloginfo('name')) . '</strong>
//                             </p>

//                         </td>
//                     </tr>

//                 </table>

//             </td>
//         </tr>
//     </table>

// </body>
// </html>';


// //     wp_mail($email, $subject, $message, $headers);

//     // wp_mail($email, $subject, $message);

// 	$headers = array(
// 		'From: Med Cards Now <contact@demo.medcardsnow.com>',
// 		'Reply-To: contact@demo.medcardsnow.com',
// 		'Content-Type: text/html; charset=UTF-8'
// 	);

// 	$mail_sent = wp_mail(
// 		$email,
// 		$subject,
// 		$message,
// 		$headers
// 	);
	
// 	if (!$mail_sent) {
// 		error_log('Secure Login Email FAILED: ' . $email);
// 	} else {
// 		error_log('Secure Login Email ACCEPTED: ' . $email);
// 	}

	
// }


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

    error_log('Secure login function started');
    error_log('Entered email: ' . $email);

    // Validate email
    if (!$email || !is_email($email)) {
        error_log('Invalid email: ' . $email);
        return;
    }

    // Check whether user already exists
    $user = get_user_by('email', $email);

    if (!$user) {

        // Generate a unique username
        $email_prefix = sanitize_user(
            current(explode('@', $email)),
            true
        );

        $username = $email_prefix;

        if (username_exists($username)) {
            $username .= '_' . wp_rand(1000, 9999);
        }

        // Create a random password
        $password = wp_generate_password(32, true, true);

        // Create new WordPress user
        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'subscriber'
        ));

        if (is_wp_error($user_id)) {
            error_log(
                'User creation failed: ' . $user_id->get_error_message()
            );
            return;
        }

        $user = get_user_by('id', $user_id);

        error_log('New user created: ' . $email);

    } else {

        $user_id = $user->ID;

        error_log('Existing user found: ' . $email);
    }

    /*
     * Generate secure login token
     */
    $token = wp_generate_password(32, false, false);

    update_user_meta(
        $user_id,
        'secure_login_token',
        $token
    );

    update_user_meta(
        $user_id,
        'secure_login_token_expires',
        time() + (15 * MINUTE_IN_SECONDS)
    );

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
        'From: Med Cards Now <contact@demo.medcardsnow.com>',
        'Reply-To: contact@demo.medcardsnow.com',
        'Content-Type: text/html; charset=UTF-8'
    );

    /*
     * Send email to the entered email address
     */
    $mail_sent = wp_mail(
        $email,
        $subject,
        $message,
        $headers
    );

    if (!$mail_sent) {

        error_log(
            'Secure login email FAILED. Recipient: ' . $email
        );

    } else {

        error_log(
            'Secure login email ACCEPTED. Recipient: ' . $email
        );
    }
}



add_action('init', 'handle_secure_login_link');
function handle_secure_login_link()
{

    if (!isset($_GET['secure_login'], $_GET['uid'], $_GET['token'])) {
        return;
    }

    $user_id = absint($_GET['uid']);
    $token   = sanitize_text_field($_GET['token']);

    if (!$user_id || !$token) {
        wp_die('Invalid login link.');
    }

    $saved_token   = get_user_meta($user_id, 'secure_login_token', true);
    $token_expires = get_user_meta($user_id, 'secure_login_token_expires', true);

    if (!$saved_token || !$token_expires) {
        wp_die('Login link is invalid or expired.');
    }

    if (time() > (int) $token_expires) {
        delete_user_meta($user_id, 'secure_login_token');
        delete_user_meta($user_id, 'secure_login_token_expires');
        wp_die('Login link has expired.');
    }

    if (!hash_equals($saved_token, $token)) {
        wp_die('Invalid login token.');
    }

    // Log user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // Remove token after use
    delete_user_meta($user_id, 'secure_login_token');
    delete_user_meta($user_id, 'secure_login_token_expires');

    // Redirect after login
    wp_redirect(home_url('/medical-card-profile/'));
    exit;
}


// Dynamically populate user meta 
add_filter('gform_field_value_first_name', 'populate_user_meta_first_name');
function populate_user_meta_first_name($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'first_name', true) : $value;
}

add_filter('gform_field_value_last_name', 'populate_user_meta_last_name');
function populate_user_meta_last_name($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'last_name', true) : $value;
}

add_filter('gform_field_value_phone', 'populate_user_meta_phone');
function populate_user_meta_phone($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'phone', true) : $value;
}

add_filter('gform_field_value_date_of_birth', 'populate_user_meta_dob');
function populate_user_meta_dob($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'date_of_birth', true) : $value;
}

add_filter('gform_field_value_address_line_1', 'populate_user_meta_address1');
function populate_user_meta_address1($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'address_line_1', true) : $value;
}

add_filter('gform_field_value_address_line_2', 'populate_user_meta_address2');
function populate_user_meta_address2($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'address_line_2', true) : $value;
}

add_filter('gform_field_value_city', 'populate_user_meta_city');
function populate_user_meta_city($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'city', true) : $value;
}

add_filter('gform_field_value_state', 'populate_user_meta_state');
function populate_user_meta_state($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'state', true) : $value;
}

add_filter('gform_field_value_zip_code', 'populate_user_meta_zip');
function populate_user_meta_zip($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'zip_code', true) : $value;
}

add_filter('gform_field_value_country', 'populate_user_meta_country');
function populate_user_meta_country($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'country', true) : $value;
}


add_filter('gform_field_value_payment_plan', 'populate_user_meta_plan');
function populate_user_meta_plan($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'payment_plan_', true) : $value;
}

add_filter('gform_field_value_payment_amount', 'populate_user_meta_payment_amount');
function populate_user_meta_payment_amount($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'payment_amount', true) : $value;
}



// Load prices based on the User state 

/**
 * Med Cards Now - Full California Add-ons Code
 *
 * Form ID: 2
 *
 * Existing fields:
 * Choose Plan field ID: 11
 * Payment Amount hidden field ID: 51
 * Hard Copy radio field ID: 54
 * Legal Support Letter hidden field ID: 58
 *
 * New fields required:
 * ID Card hidden field ID: 61
 * Years Plan hidden field ID: 63
 *
 * Shortcodes to add inside Gravity Forms HTML fields:
 * [medcards_california_year_plan_cards]
 * [medcards_california_id_card_cards]
 * [medcards_california_legal_letter_cards]
 *
 * Saved user meta for checkout breakdown:
 * payment_plan_price
 * california_year_plan
 * california_year_plan_price
 * california_id_card
 * california_id_card_price
 * california_hard_copy
 * california_hard_copy_price
 * california_legal_support_letter
 * california_legal_support_letter_value
 * california_legal_support_letter_price
 * selected_plan_addons
 * payment_amount
 */


/**
 * CALIFORNIA FLOW INCLUDED:
 * 1. Choose basic plan
 * 2. Add / do not add hard copy
 *    - This is hidden automatically when selected base plan already includes hard copy
 * 3. Add legal support letter
 * 4. Add ID card
 * 5. Choose year plan
 *
 * All prices are saved separately in user meta and also added to payment_amount.
 */

/*
 * UPDATED CALIFORNIA 5-STEP LOGIC:
 * 1. Choose basic/base plan.
 * 2. Add hard copy +$10 only if selected base plan does not already include hard copy.
 * 3. Add legal support letters. Multiple letters can be selected. Each letter adds +$79.
 * 4. Add ID card +$16.
 * 5. Choose year plan:
 *    1 year  = base plan price
 *    2 years = base plan price x 2 minus 8%
 *    3 years = base plan price x 3 minus 16%
 *
 * The year plan changes only the base plan price.
 * Hard copy, legal letters, and ID card are added after the year-plan calculation.
 */


/*
 * IMPORTANT PLAN VALUES:
 * new_pdf_id_card = Instant PDF + Patient ID Card. This includes ID card, NOT hard copy.
 * renewal_hard_copy = Existing patient renewal with hard copy. This includes hard copy.
 */

/* =====================================================
   GRAVITY FORMS HOOKS
   ===================================================== */

add_filter('gform_pre_render_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_pre_validation_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_pre_submission_filter_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_admin_pre_render_2', 'medcards_populate_plan_choices_by_state');


/* =====================================================
   STATE PRICING
   ===================================================== */

function medcards_get_state_pricing()
{

    return array(

        'Arizona' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Arkansas' => array(
            'new'        => 159,
            'renewal'    => 139,
            'two_year'   => 293,
            'three_year' => 410,
        ),

        'California' => array(
            'new_pdf'           => 37,
            'new_pdf_id_card'    => 64.99,
            'caregiver_new'     => 99.99,
            'growers_license'   => 189.99,
            'renewal_hard_copy' => 47,
        ),

        'Connecticut' => array(
            'new'        => 159,
            'renewal'    => 139,
            'two_year'   => 293,
            'three_year' => 410,
        ),

        'Florida' => array(
            'new'        => 199,
            'renewal'    => 179,
            'two_year'   => 366,
            'three_year' => 513,
        ),

        'Georgia' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Illinois' => array(
            'new'        => 169,
            'renewal'    => 149,
            'two_year'   => 311,
            'three_year' => 436,
        ),

        'Iowa' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Louisiana' => array(
            'new'        => 180,
            'renewal'    => 160,
            'two_year'   => 331,
            'three_year' => 464,
        ),

        'Maine' => array(
            'new'        => 89,
            'renewal'    => 79,
            'two_year'   => 164,
            'three_year' => 230,
        ),

        'Maryland' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Massachusetts' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Michigan' => array(
            'new'        => 129,
            'renewal'    => 119,
            'two_year'   => 237,
            'three_year' => 333,
        ),

        'Minnesota' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Missouri' => array(
            'new'        => 119,
            'renewal'    => 109,
            'two_year'   => 219,
            'three_year' => 307,
        ),

        'Montana' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Nevada' => array(
            'new'        => 69,
            'renewal'    => 59,
            'two_year'   => 127,
            'three_year' => 178,
        ),

        'New Hampshire' => array(
            'new'        => 180,
            'renewal'    => 160,
            'two_year'   => 331,
            'three_year' => 464,
        ),

        'New Jersey' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'New York' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Ohio' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Oklahoma' => array(
            'new'        => 119,
            'renewal'    => 109,
            'two_year'   => 219,
            'three_year' => 307,
        ),

        'Pennsylvania' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Texas' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Virginia' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Washington' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'West Virginia' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),
    );
}


/* =====================================================
   CALIFORNIA ADD-ON PRICING
   ===================================================== */

function medcards_get_california_legal_letters()
{
    return array(
        'none' => array(
            'label' => 'No Legal Letter',
            'price' => 0.00,
        ),
        'parole' => array(
            'label' => 'Cannabis Medical Letter For Parole',
            'price' => 79.00,
        ),
        'employer' => array(
            'label' => 'Cannabis Letter For Employer',
            'price' => 79.00,
        ),
        'driving' => array(
            'label' => 'Cannabis Medical Letter For Possession While Driving',
            'price' => 79.00,
        ),
        'possession_person' => array(
            'label' => 'Cannabis Medical Letter For Possession On Person',
            'price' => 79.00,
        ),
    );
}


function medcards_get_california_year_plan_prices()
{

    /*
     * Year plan is NOT a separate add-on price.
     * It recalculates the selected base plan price only.
     */
    return array(

        '1_year' => array(
            'title'    => '1 Year',
            'years'    => 1,
            'discount' => 0,
        ),

        '2_year' => array(
            'title'    => '2 Years',
            'years'    => 2,
            'discount' => 8,
        ),

        '3_year' => array(
            'title'    => '3 Years',
            'years'    => 3,
            'discount' => 16,
        ),
    );
}


function medcards_calculate_year_plan_price($base_plan_price, $year_plan)
{

    $year_plans = medcards_get_california_year_plan_prices();

    if (!isset($year_plans[$year_plan])) {
        $year_plan = '1_year';
    }

    $years           = (int) $year_plans[$year_plan]['years'];
    $discount        = (float) $year_plans[$year_plan]['discount'];
    $original_price  = (float) $base_plan_price * $years;
    $discount_amount = $original_price * ($discount / 100);
    $final_price     = $original_price - $discount_amount;

    return array(
        'year_plan'       => $year_plan,
        'title'           => $year_plans[$year_plan]['title'],
        'years'           => $years,
        'discount'        => $discount,
        'original_price'  => $original_price,
        'discount_amount' => $discount_amount,
        'final_price'     => $final_price,
    );
}




function medcards_extract_legal_letter_values_from_request_safe()
{

    $values = array();

    if (isset($_POST['input_58'])) {
        $raw_hidden = $_POST['input_58'];

        if (is_array($raw_hidden)) {
            $values = array_merge($values, $raw_hidden);
        } else {
            $values = array_merge($values, explode(',', (string) $raw_hidden));
        }
    }

    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $raw_ui = $_POST['ca_legal_support_letter_ui'];

        if (is_array($raw_ui)) {
            $values = array_merge($values, $raw_ui);
        } else {
            $values = array_merge($values, explode(',', (string) $raw_ui));
        }
    }

    $clean = array();

    foreach ($values as $value) {
        $value = sanitize_text_field(trim((string) $value));

        if ($value && $value !== 'none') {
            $clean[] = $value;
        }
    }

    $clean = array_values(array_unique($clean));

    $legal_letters = medcards_get_california_legal_letters();
    $valid = array();

    foreach ($clean as $value) {
        if (isset($legal_letters[$value])) {
            $valid[] = $value;
        }
    }

    $_POST['input_58'] = !empty($valid) ? implode(',', $valid) : 'none';

    return $valid;
}

function medcards_get_legal_letter_total_from_values_safe($values)
{

    $legal_letters = medcards_get_california_legal_letters();
    $total = 0;

    if (!is_array($values)) {
        $values = array_filter(array_map('trim', explode(',', (string) $values)));
    }

    foreach ($values as $value) {
        if ($value !== 'none' && isset($legal_letters[$value])) {
            $total += (float) $legal_letters[$value]['price'];
        }
    }

    return $total;
}



function medcards_get_legal_letters_total_from_field_58()
{

    $raw_value = '';

    if (isset($_POST['input_58'])) {
        $raw_value = $_POST['input_58'];
    }

    if (is_array($raw_value)) {
        $values = $raw_value;
    } else {
        $values = array_filter(array_map('trim', explode(',', (string) $raw_value)));
    }

    if (empty($values)) {
        return array(
            'values' => array(),
            'total'  => 0,
        );
    }

    $legal_letters = medcards_get_california_legal_letters();

    $valid_values = array();
    $total = 0;

    foreach ($values as $value) {
        $value = sanitize_text_field($value);

        if ($value !== 'none' && isset($legal_letters[$value])) {
            $valid_values[] = $value;
            $total += (float) $legal_letters[$value]['price'];
        }
    }

    $valid_values = array_values(array_unique($valid_values));

    $_POST['input_58'] = !empty($valid_values) ? implode(',', $valid_values) : 'none';

    return array(
        'values' => $valid_values,
        'total'  => $total,
    );
}


function medcards_plan_already_has_hard_copy($selected_plan)
{

    return in_array($selected_plan, array('renewal_hard_copy'), true);
}



function medcards_plan_already_has_id_card($selected_plan)
{

    return in_array($selected_plan, array('new_pdf_id_card'), true);
}


/* =====================================================
   POPULATE PLAN CHOICES + HIDDEN DEFAULTS
   ===================================================== */

function medcards_populate_plan_choices_by_state($form)
{

    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if (!$state) {
        return $form;
    }

    $pricing = medcards_get_state_pricing();

    if (!isset($pricing[$state])) {
        return $form;
    }

    $prices = $pricing[$state];

    foreach ($form['fields'] as &$field) {

        /**
         * FIELD 11 = CHOOSE PLAN
         */
        if ((int) $field->id === 11) {

            if ($state === 'California') {

                $field->choices = array(

                    array(
                        'text'  => '<span class="badge-save">Save 35%</span><span class="badge-rec">Recommended</span><br><br>$64.99<br><br> Instant PDF + Patient ID Card - With Hard Copy',
                        'value' => 'new_pdf_id_card',
                        'isSelected' => true,
                    ),

                    array(
                        'text'  => '$99.99<br><br> New Patient (Caregiver) — Caregiver + New Patient',
                        'value' => 'caregiver_new',
                    ),

                    array(
                        'text'  => '$189.99<br><br> License to Cultivate up to 99 Plants',
                        'value' => 'growers_license',
                    ),

                    array(
                        'text'  => '$37<br><br> New Patient — PDF Only',
                        'value' => 'new_pdf',
                    ),

                    array(
                        'text'  => '$47<br><br> Existing patient renewal — With Hard Copy',
                        'value' => 'renewal_hard_copy',
                    ),
                );
            } else {

                $field->choices = array(

                    array(
                        'text'  => '$' . number_format($prices['new'], 0) . '<br><br> New Patient',
                        'value' => 'new',
                    ),

                    array(
                        'text'  => '$' . number_format($prices['renewal'], 0) . '<br><br> Renewal',
                        'value' => 'renewal',
                    ),

                    array(
                        'text'  => '$' . number_format($prices['two_year'], 0) . '<br><br> 2 Year Pricing',
                        'value' => 'two_year',
                    ),

                    array(
                        'text'  => '$' . number_format($prices['three_year'], 0) . '<br><br> 3 Year Pricing',
                        'value' => 'three_year',
                    ),
                );
            }
        }


        /**
         * FIELD 54 = ADD A HARD COPY
         */
        if ((int) $field->id === 54) {

            if ($state === 'California') {

                $field->visibility = 'visible';

                $field->choices = array(
                    array(
                        'text'       => 'Yes +$10.00',
                        'value'      => 'yes',
                        'isSelected' => false,
                    ),
                    array(
                        'text'       => 'No',
                        'value'      => 'no',
                        'isSelected' => true,
                    ),
                );
            } else {

                $field->visibility = 'hidden';

                $field->choices = array(
                    array(
                        'text'       => 'No',
                        'value'      => 'no',
                        'isSelected' => true,
                    ),
                );
            }
        }


        /**
         * FIELD 58 = LEGAL SUPPORT LETTER HIDDEN FIELD
         */
        if ((int) $field->id === 58) {
            $field->visibility = 'hidden';
            $field->defaultValue = 'none';
        }


        /**
         * FIELD 61 = ID CARD HIDDEN FIELD
         */
        if ((int) $field->id === 61) {
            $field->visibility = 'hidden';
            $field->defaultValue = 'no';
        }


        /**
         * FIELD 63 = YEAR PLAN HIDDEN FIELD
         */
        if ((int) $field->id === 63) {
            $field->visibility = 'hidden';
            $field->defaultValue = '3_year';
        }
    }

    return $form;
}


/* =====================================================
   CALCULATE FINAL PAYMENT AMOUNT
   ===================================================== */

add_filter('gform_pre_submission_2', 'set_hidden_payment_amount_from_selected_plan');

function set_hidden_payment_amount_from_selected_plan($form)
{

    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id       = get_current_user_id();
    $state         = get_user_meta($user_id, 'state', true);
    $selected_plan = rgpost('input_11');
    $hard_copy     = rgpost('input_54');
    $id_card       = rgpost('input_61');
    $year_plan     = rgpost('input_63');
    $pricing       = medcards_get_state_pricing();

    if (!$state || !$selected_plan) {
        return $form;
    }

    if (isset($pricing[$state][$selected_plan])) {

        $single_year_plan_price = (float) $pricing[$state][$selected_plan];

        $year_plan_data = medcards_calculate_year_plan_price(
            $single_year_plan_price,
            $year_plan ?: '1_year'
        );

        $adjusted_plan_price = (float) $year_plan_data['final_price'];
        $amount = $adjusted_plan_price;

        $hard_copy_price     = 0;
        $legal_letters_total = 0;
        $id_card_price       = 0;

        if ($state === 'California' && !medcards_plan_already_has_hard_copy($selected_plan) && $hard_copy === 'yes') {
            $hard_copy_price = 10;
            $amount += $hard_copy_price;
        }

        if ($state === 'California') {
            $selected_legal_letters = medcards_extract_legal_letter_values_from_request_safe();
            $legal_letters_total = medcards_get_legal_letter_total_from_values_safe($selected_legal_letters);

            if ($legal_letters_total > 0) {
                $amount += $legal_letters_total;
            }
        }

        if ($state === 'California' && medcards_plan_already_has_id_card($selected_plan)) {
            $id_card_price = 0;
            $_POST['input_61'] = 'included';
        } elseif ($state === 'California' && $id_card === 'yes') {
            $id_card_price = 16;
            $amount += $id_card_price;
        }

        update_user_meta($user_id, 'payment_single_year_plan_price', number_format($single_year_plan_price, 2, '.', ''));
        update_user_meta($user_id, 'payment_plan_price', number_format($adjusted_plan_price, 2, '.', ''));

        update_user_meta($user_id, 'california_year_plan', $year_plan_data['year_plan']);
        update_user_meta($user_id, 'california_year_plan_title', $year_plan_data['title']);
        update_user_meta($user_id, 'california_year_plan_years', $year_plan_data['years']);
        update_user_meta($user_id, 'california_year_plan_discount_percent', number_format($year_plan_data['discount'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_original_price', number_format($year_plan_data['original_price'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_discount_amount', number_format($year_plan_data['discount_amount'], 2, '.', ''));

        update_user_meta($user_id, 'california_hard_copy_price', number_format($hard_copy_price, 2, '.', ''));
        update_user_meta($user_id, 'california_legal_support_letter_price', number_format($legal_letters_total, 2, '.', ''));
        update_user_meta($user_id, 'california_id_card_price', number_format($id_card_price, 2, '.', ''));

        update_user_meta($user_id, 'payment_amount', number_format($amount, 2, '.', ''));

        $_POST['input_51'] = number_format($amount, 2, '.', '');
    }

    return $form;
}


/* =====================================================
   SAVE ADD-ON VALUES TO USER META AFTER SUBMISSION
   ===================================================== */

add_action('gform_after_submission_2', 'medcards_save_california_addons_to_user_meta', 20, 2);

function medcards_save_california_addons_to_user_meta($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id       = get_current_user_id();
    $state         = get_user_meta($user_id, 'state', true);
    $selected_plan = rgar($entry, '11');
    $hard_copy     = rgar($entry, '54');
    $legal_letters_selected = rgar($entry, '58'); // comma-separated values

    if (empty($legal_letters_selected) || $legal_letters_selected === 'none') {
        $posted_selected_letters = medcards_extract_legal_letter_values_from_request_safe();
        if (!empty($posted_selected_letters)) {
            $legal_letters_selected = implode(',', $posted_selected_letters);
        }
    }
    $id_card       = rgar($entry, '61');
    $year_plan     = rgar($entry, '63');
    $amount        = rgar($entry, '51');

    $pricing = medcards_get_state_pricing();
    $addons  = array();

    /*
     * Save selected base plan + year plan pricing breakdown.
     */
    if ($state && $selected_plan && isset($pricing[$state][$selected_plan])) {

        $single_year_plan_price = (float) $pricing[$state][$selected_plan];

        $year_plan_data = medcards_calculate_year_plan_price(
            $single_year_plan_price,
            $year_plan ?: '1_year'
        );

        update_user_meta($user_id, 'payment_single_year_plan_price', number_format($single_year_plan_price, 2, '.', ''));
        update_user_meta($user_id, 'payment_plan_price', number_format($year_plan_data['final_price'], 2, '.', ''));

        update_user_meta($user_id, 'california_year_plan', $year_plan_data['year_plan']);
        update_user_meta($user_id, 'california_year_plan_title', $year_plan_data['title']);
        update_user_meta($user_id, 'california_year_plan_years', $year_plan_data['years']);
        update_user_meta($user_id, 'california_year_plan_discount_percent', number_format($year_plan_data['discount'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_original_price', number_format($year_plan_data['original_price'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_discount_amount', number_format($year_plan_data['discount_amount'], 2, '.', ''));

        if ((float) $year_plan_data['discount'] > 0) {
            $addons[] = $year_plan_data['title'] . ' Access - Save ' . number_format($year_plan_data['discount'], 0) . '%';
        }
    }

    /*
     * Hard copy meta.
     */
    if ($state === 'California' && !medcards_plan_already_has_hard_copy($selected_plan) && $hard_copy === 'yes') {

        $addons[] = 'Add A Hard Copy +$10.00';

        update_user_meta($user_id, 'california_hard_copy', 'yes');
        update_user_meta($user_id, 'california_hard_copy_price', '10.00');
    } else {

        update_user_meta($user_id, 'california_hard_copy', 'no');
        update_user_meta($user_id, 'california_hard_copy_price', '0.00');
    }
    /*
     * Legal support letters meta.
     * Multiple selected letters are allowed.
     */
    $legal_letters_total = 0;
    $legal_letter_titles = array();
    $legal_letter_values = array();

    $selected_letters = medcards_extract_legal_letter_values_from_request_safe();

    if (empty($selected_letters) && !empty($legal_letters_selected) && $legal_letters_selected !== 'none') {
        $selected_letters = array_filter(array_map('trim', explode(',', $legal_letters_selected)));
    }

    if ($state === 'California' && !empty($selected_letters)) {

        $legal_letters = medcards_get_california_legal_letters();

        foreach ($selected_letters as $letter_key) {

            if ($letter_key !== 'none' && isset($legal_letters[$letter_key])) {

                $legal_letter_titles[] = $legal_letters[$letter_key]['title'];
                $legal_letter_values[] = $letter_key;
                $legal_letters_total += (float) $legal_letters[$letter_key]['price'];

                $addons[] = $legal_letters[$letter_key]['title'] . ' +$' . number_format((float) $legal_letters[$letter_key]['price'], 2);
            }
        }

        update_user_meta($user_id, 'california_legal_support_letter', implode(' | ', $legal_letter_titles));
        update_user_meta($user_id, 'california_legal_support_letter_value', implode(',', $legal_letter_values));
        update_user_meta($user_id, 'california_legal_support_letter_price', number_format($legal_letters_total, 2, '.', ''));
    } else {

        update_user_meta($user_id, 'california_legal_support_letter', 'none');
        update_user_meta($user_id, 'california_legal_support_letter_value', 'none');
        update_user_meta($user_id, 'california_legal_support_letter_price', '0.00');
    }

    /*
     * ID card meta.
     * If selected base plan already includes ID card, do not charge again.
     */
    if ($state === 'California' && medcards_plan_already_has_id_card($selected_plan)) {

        $addons[] = 'ID Card Included in Selected Plan';

        update_user_meta($user_id, 'california_id_card', 'included');
        update_user_meta($user_id, 'california_id_card_price', '0.00');
    } elseif ($state === 'California' && $id_card === 'yes') {

        $addons[] = 'ID Card +$16.00';

        update_user_meta($user_id, 'california_id_card', 'yes');
        update_user_meta($user_id, 'california_id_card_price', '16.00');
    } else {

        update_user_meta($user_id, 'california_id_card', 'no');
        update_user_meta($user_id, 'california_id_card_price', '0.00');
    }

    /*
     * Readable summary.
     */
    update_user_meta(
        $user_id,
        'selected_plan_addons',
        !empty($addons) ? implode(' | ', $addons) : 'No add-ons selected'
    );

    /*
     * Final total amount.
     */
    if ($amount) {
        update_user_meta($user_id, 'payment_amount', number_format((float) $amount, 2, '.', ''));
    }
}




/**
 * ============================================================
 * SAVE GRAVITY FORMS FORM 2 FIELD 13 PHOTO TO USER META
 * ============================================================
 */





/* =====================================================
   SHORTCODE: LEGAL SUPPORT LETTER CARDS
   Add this in a Gravity Forms HTML field:
   [medcards_california_legal_letter_cards]
   ===================================================== */

add_shortcode('medcards_california_legal_letter_cards', 'medcards_california_legal_letter_cards_shortcode');

function medcards_california_legal_letter_cards_shortcode()
{

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if ($state !== 'California') {
        return '';
    }

    ob_start();
?>

    <div class="ca-legal-addon-wrap">

        <div class="ca-legal-grid">

            <div class="ca-legal-card ca-legal-card-muted" data-value="parole">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="parole">
                <div class="ca-legal-icon">👤</div>
                <h4>Cannabis Medical Letter For Parole</h4>
                <p>A doctor-signed letter for communication with parole or supervising authorities. It confirms cannabis is included in your treatment plan and is intended to support compliance with applicable legal and supervision requirements.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="employer">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="employer">
                <div class="ca-legal-icon">⚖</div>
                <h4>Cannabis Letter For Employer</h4>
                <p>A physician-signed letter to support conversations with your employer or HR. It confirms a doctor has determined cannabis is part of your treatment plan, subject to applicable laws and workplace policies.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="driving">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="driving">
                <div class="ca-legal-icon">🚗</div>
                <h4>Cannabis Medical Letter For Possession While Driving</h4>
                <p>A doctor-signed letter confirming you are authorized to transport medical cannabis in your vehicle. Intended to support lawful possession in accordance with state and local laws.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="possession_person">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="possession_person">
                <div class="ca-legal-icon">i</div>
                <h4>Cannabis Medical Letter For Possession On Person</h4>
                <p>A physician-signed document verifying cannabis is part of your medical care. It may be presented to law enforcement to clarify lawful possession, subject to applicable laws and not a guarantee against enforcement.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

        </div>

        <button type="button" class="ca-legal-clear">Remove Legal Letters</button>

    </div>

<?php
    return ob_get_clean();
}


/* =====================================================
   SHORTCODE: YEAR PLAN CARDS
   Add this in a Gravity Forms HTML field:
   [medcards_california_year_plan_cards]
   ===================================================== */

add_shortcode('medcards_california_year_plan_cards', 'medcards_california_year_plan_cards_shortcode');

function medcards_california_year_plan_cards_shortcode()
{

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if ($state !== 'California') {
        return '';
    }

    ob_start();
?>

    <div class="ca-years-wrap">

        <h3 class="ca-addon-title">How long do you want access to medical cannabis?</h3>
        <p class="ca-addon-subtitle">Your certification must be renewed yearly, but you can save on annual renewal costs by paying ahead.</p>

        <div class="ca-years-grid">

            <div class="ca-years-card" data-value="3_year">
                <span class="ca-years-radio"></span>
                <span class="ca-years-badge">MOST POPULAR</span>
                <h4>3 years</h4>
                <p>Choose 3 years and save 16% on your selected base plan total.</p>
                <div class="ca-years-price">
                    <del>$75</del>
                    <strong>Save 16%</strong>
                    <span>Best Value</span>
                </div>
                <small>221 people chose this today</small>
            </div>

            <div class="ca-years-card" data-value="2_year">
                <span class="ca-years-radio"></span>
                <h4>2 years</h4>
                <p>Choose 2 years and save 8% on your selected base plan total.</p>
                <div class="ca-years-price">
                    <del>$75</del>
                    <strong>Save 8%</strong>
                    <span>Popular</span>
                </div>
                <small>31 people chose this today</small>
            </div>

            <div class="ca-years-card" data-value="1_year">
                <span class="ca-years-radio"></span>
                <h4>1 year</h4>
                <p>Choose 1 year and keep the selected base plan price the same.</p>
                <div class="ca-years-price">
                    <strong>No Discount</strong>
                    <span>Regular Price</span>
                </div>
                <small>65 people chose this today</small>
            </div>

        </div>

    </div>

<?php
    return ob_get_clean();
}








/* =====================================================
   CALIFORNIA MEDICAL CANNABIS PATIENT ID CARD
   Shortcode:
   [medcards_california_id_card_cards]
   ===================================================== */

add_shortcode('medcards_california_id_card_cards','medcards_california_id_card_cards_shortcode');
function medcards_california_id_card_cards_shortcode()
{
    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();

    /*
     * -------------------------------------------------
     * USER DETAILS
     * -------------------------------------------------
     */

    $state = get_user_meta($user_id, 'state', true);

    if (empty($state)) {
        $state = get_user_meta($user_id, 'patient_state', true);
    }

    /*
     * Only show this card for California patients.
     */
    if (strcasecmp(trim($state), 'California') !== 0) {
        return '';
    }

    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name  = get_user_meta($user_id, 'last_name', true);

    /*
     * -------------------------------------------------
     * GRAVITY FORMS SOURCE
     * Form 2:
     *
     * DOB       = Field 32
     * PHOTO     = Field 13
     * SIGNATURE  = Field 14
     * -------------------------------------------------
     */

    $gf_entry = false;

    if (
        class_exists('GFAPI') &&
        function_exists('rgar')
    ) {

        $gf_entries = GFAPI::get_entries(
            2,
            array(
                'status'        => 'active',
                'orderby'       => 'date_created',
                'order'         => 'DESC',
                'field_filters' => array(
                    array(
                        'key'   => 'created_by',
                        'value' => $user_id,
                    ),
                ),
            ),
            null,
            array(
                'offset'    => 0,
                'page_size' => 100,
            )
        );

        if (
            !is_wp_error($gf_entries) &&
            is_array($gf_entries) &&
            !empty($gf_entries)
        ) {
            $gf_entry = $gf_entries[0];
        }
    }

    /*
     * -------------------------------------------------
     * DOB
     * -------------------------------------------------
     *
     * Gravity Forms Date field:
     *
     * 32.1 = Month
     * 32.2 = Day
     * 32.3 = Year
     *
     * US FORMAT:
     * MM/DD/YYYY
     */

    $dob = '';

    if (!empty($gf_entry)) {

        /*
         * First try the complete field value.
         */
        $dob = rgar(
            $gf_entry,
            '32'
        );

        /*
         * If the complete value is empty,
         * use the Date field sub-fields.
         */
        if (empty($dob)) {

            $dob_month = rgar(
                $gf_entry,
                '32.1'
            );

            $dob_day = rgar(
                $gf_entry,
                '32.2'
            );

            $dob_year = rgar(
                $gf_entry,
                '32.3'
            );

            if (
                !empty($dob_month) &&
                !empty($dob_day) &&
                !empty($dob_year)
            ) {

                $dob = sprintf(
                    '%02d/%02d/%04d',
                    (int) $dob_month,
                    (int) $dob_day,
                    (int) $dob_year
                );
            }
        }
    }

    /*
     * Current request fallback.
     *
     * This handles the actual browser fields:
     *
     * input_2_32_1
     * input_2_32_2
     * input_2_32_3
     */

    if (empty($dob)) {

        $posted_dob_month = '';

        $posted_dob_day = '';

        $posted_dob_year = '';

        if (isset($_POST['input_32'])) {

            $posted_dob = wp_unslash(
                $_POST['input_32']
            );

            if (is_array($posted_dob)) {

                $posted_dob_month =
                    isset($posted_dob[0])
                    ? sanitize_text_field(
                        $posted_dob[0]
                    )
                    : '';

                $posted_dob_day =
                    isset($posted_dob[1])
                    ? sanitize_text_field(
                        $posted_dob[1]
                    )
                    : '';

                $posted_dob_year =
                    isset($posted_dob[2])
                    ? sanitize_text_field(
                        $posted_dob[2]
                    )
                    : '';
            }
        }

        if (
            empty($posted_dob_month) &&
            isset($_POST['input_32_1'])
        ) {
            $posted_dob_month = sanitize_text_field(
                wp_unslash(
                    $_POST['input_32_1']
                )
            );
        }

        if (
            empty($posted_dob_day) &&
            isset($_POST['input_32_2'])
        ) {
            $posted_dob_day = sanitize_text_field(
                wp_unslash(
                    $_POST['input_32_2']
                )
            );
        }

        if (
            empty($posted_dob_year) &&
            isset($_POST['input_32_3'])
        ) {
            $posted_dob_year = sanitize_text_field(
                wp_unslash(
                    $_POST['input_32_3']
                )
            );
        }

        if (
            !empty($posted_dob_month) &&
            !empty($posted_dob_day) &&
            !empty($posted_dob_year)
        ) {

            $dob = sprintf(
                '%02d/%02d/%04d',
                (int) $posted_dob_month,
                (int) $posted_dob_day,
                (int) $posted_dob_year
            );
        }
    }

    /*
     * User meta remains a fallback.
     */

    if (empty($dob)) {

        $dob = get_user_meta(
            $user_id,
            'date_of_birth',
            true
        );
    }

    /*
     * -------------------------------------------------
     * PATIENT NAME
     * -------------------------------------------------
     */

    $patient_name = trim(
        sanitize_text_field($first_name) . ' ' .
        sanitize_text_field($last_name)
    );

    if (empty($patient_name)) {

        $user = get_userdata($user_id);

        if ($user) {
            $patient_name = $user->display_name;
        }
    }

    if (empty($patient_name)) {
        $patient_name = 'Patient';
    }

    /*
     * -------------------------------------------------
     * FORMAT DOB
     * -------------------------------------------------
     *
     * US FORMAT = MM/DD/YYYY
     */

    $formatted_dob = '';

    if (!empty($dob)) {

        /*
         * If already in MM/DD/YYYY format,
         * preserve it.
         */
        if (
            preg_match(
                '/^\d{1,2}\/\d{1,2}\/\d{4}$/',
                trim($dob)
            )
        ) {

            $parts = explode(
                '/',
                trim($dob)
            );

            if (count($parts) === 3) {

                $formatted_dob = sprintf(
                    '%02d/%02d/%04d',
                    (int) $parts[0],
                    (int) $parts[1],
                    (int) $parts[2]
                );
            }

        } else {

            $dob_timestamp = strtotime($dob);

            if ($dob_timestamp) {

                $formatted_dob = date_i18n(
                    'm/d/Y',
                    $dob_timestamp
                );

            } else {

                $formatted_dob =
                    sanitize_text_field($dob);
            }
        }
    }

    /*
     * -------------------------------------------------
     * PATIENT ID
     * -------------------------------------------------
     *
     * This is an internal Med Cards Now patient ID.
     * It is NOT a California DMV ID.
     */

    $patient_id = get_user_meta(
        $user_id,
        'medical_card_id',
        true
    );

    /*
     * If no card ID exists, create one.
     */

    if (empty($patient_id)) {

        $patient_id = 'MCN-' . str_pad(
            $user_id,
            7,
            '0',
            STR_PAD_LEFT
        );

        update_user_meta(
            $user_id,
            'medical_card_id',
            $patient_id
        );
    }

    /*
     * -------------------------------------------------
     * ISSUE DATE
     * -------------------------------------------------
     */

    $issue_date = get_user_meta(
        $user_id,
        'card_issue_date',
        true
    );

    if (empty($issue_date)) {

        $user = get_userdata($user_id);

        if (
            $user &&
            !empty($user->user_registered)
        ) {
            $issue_date = $user->user_registered;
        }
    }

    $formatted_issue_date = '';

    if (!empty($issue_date)) {

        $issue_timestamp = strtotime(
            $issue_date
        );

        if ($issue_timestamp) {

            $formatted_issue_date = date_i18n(
                'm/d/Y',
                $issue_timestamp
            );
        }
    }

    /*
     * -------------------------------------------------
     * EXPIRATION DATE
     * -------------------------------------------------
     */

    $expiration_date = get_user_meta(
        $user_id,
        'card_expiration_date',
        true
    );

    /*
     * Get selected California year plan.
     */

    $year_plan = get_user_meta(
        $user_id,
        'california_year_plan',
        true
    );

    $years = (int) get_user_meta(
        $user_id,
        'california_year_plan_years',
        true
    );

    /*
     * Convert values such as:
     *
     * 1_year
     * 2_year
     * 3_year
     */

    if ($years <= 0) {

        if ($year_plan === '3_year') {

            $years = 3;

        } elseif ($year_plan === '2_year') {

            $years = 2;

        } else {

            $years = 1;
        }
    }

    /*
     * Calculate expiration date when no explicit
     * expiration date exists.
     */

    if (
        empty($expiration_date) &&
        !empty($issue_date)
    ) {

        $expiration_timestamp = strtotime(
            '+' . $years . ' years',
            strtotime($issue_date)
        );

        if ($expiration_timestamp) {

            $expiration_date = date(
                'Y-m-d',
                $expiration_timestamp
            );
        }
    }

    $formatted_expiration_date = '';

    if (!empty($expiration_date)) {

        $expiration_timestamp = strtotime(
            $expiration_date
        );

        if ($expiration_timestamp) {

            $formatted_expiration_date = date_i18n(
                'm/d/Y',
                $expiration_timestamp
            );
        }
    }
    
    
    
/*
 * -------------------------------------------------
 * PATIENT GENDER
 * -------------------------------------------------
 */

    $patient_gender = '';
    
    // 1. From Gravity Forms entry
    if (!empty($gf_entry) && is_array($gf_entry)) {
        $patient_gender = rgar($gf_entry, '33');
    }
    
    // 2. Fallback: from current POST (form just submitted)
    if (empty($patient_gender) && isset($_POST['input_33'])) {
        $patient_gender = sanitize_text_field(wp_unslash($_POST['input_33']));
    }
    
    // 3. Fallback: user meta
    if (empty($patient_gender)) {
        $patient_gender = get_user_meta($user_id, 'gender', true);
    }
    
    // Normalize
    $patient_gender = strtolower(trim((string) $patient_gender));
    
    // Map common variations
    if (in_array($patient_gender, array('m', 'man', 'male'), true)) {
        $patient_gender = 'male';
    } elseif (in_array($patient_gender, array('f', 'woman', 'female'), true)) {
        $patient_gender = 'female';
    } else {
        $patient_gender = 'other';
    }
    
    /*
     * Image paths — use get_stylesheet_directory_uri() only if the
     * images really live in your theme. Otherwise use uploads.
     */
    
    $gender_images = array(
        'male'   => get_stylesheet_directory_uri() . '/images/male_image.png',
        'female' => get_stylesheet_directory_uri() . '/images/female_image.png',
        'other'  => get_stylesheet_directory_uri() . '/images/default.png',
    );
    
    $gender_image_url = $gender_images[$patient_gender];
    
    // Debug
    error_log('MCN Gender raw: ' . print_r(rgar($gf_entry, '33'), true));
    error_log('MCN Gender normalized: ' . $patient_gender);
    error_log('MCN Gender Image URL: ' . $gender_image_url);


    $verification_url = add_query_arg(
        array(
            'patient_id' => $patient_id,
        ),
        home_url('/patient-care/')
    );

    /*
     * =================================================
     * CARD HTML
     * =================================================
     */

    ob_start();
    ?>

    <div class="mcn-ca-card-wrapper">
        
        
        <div id="mcn-ca-hard-copy-choice" class="mcn-ca-hard-copy-choice" style="display:none;">

            <h3>Would you like a hard copy?</h3>
        
            <label>
                <input
                    type="radio"
                    name="mcn_ca_hard_copy"
                    value="yes"
                >
                Yes — Add Hard Copy (+$10.00)
            </label>
        
            <label>
                <input
                    type="radio"
                    name="mcn_ca_hard_copy"
                    value="no"
                    checked
                >
                No — No extra charge
            </label>
        
        </div>
        
        

        <div class="mcn-ca-id-card">

            <!-- TOP GREEN BAR -->

            <div class="mcn-ca-topbar">

                <span>
                    US CALIFORNIA MEDICAL CANNABIS PATIENT ID
                </span>

            </div>


            <!-- CARD CONTENT -->

            <div class="mcn-ca-content">

                <!-- LOGO -->

                <div class="mcn-ca-logo-row">

                    <img
                        src="https://demo.medcardsnow.com/wp-content/uploads/2026/01/medcardsnow_logo_transparent.png"
                        alt="Med Cards Now"
                    >

                </div>


                <!-- MAIN CARD AREA -->

                <div class="mcn-ca-main">


                    <!-- PATIENT PHOTO -->
                    
                    
                   
                    
                    

                   <div class="mcn-ca-photo-area">
                    
                    <img
                        decoding="async"
                        id="medical-card-patient-photo"
                        class="mcn-ca-patient-photo"
                        src="<?php echo esc_url($gender_image_url); ?>"
                        alt="<?php echo esc_attr($patient_gender ?: 'Patient'); ?>"
                    >

                </div>


                    <!-- PATIENT INFORMATION -->

                    <div class="mcn-ca-details">

                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                PATIENT ID:
                            </span>

                            <strong>
                                <?php
                                echo esc_html(
                                    $patient_id
                                );
                                ?>
                            </strong>

                        </div>


                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                PATIENT:
                            </span>

                            <strong class="mcn-ca-patient-name">

                                <?php
                                echo esc_html(
                                    $patient_name
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                STATE:
                            </span>

                            <strong>
                                California
                            </strong>

                        </div>


                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                ISS:
                            </span>

                            <strong>

                                <?php
                                echo esc_html(
                                    $formatted_issue_date
                                    ?: '—'
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                EXP:
                            </span>

                            <strong>

                                <?php
                                echo esc_html(
                                    $formatted_expiration_date
                                    ?: '—'
                                );
                                ?>

                            </strong>

                        </div>


                        <div class="mcn-ca-detail-row">

                            <span class="mcn-ca-label">
                                DOB:
                            </span>

                            <strong>

                                <?php
                                echo esc_html(
                                    $formatted_dob
                                    ?: '—'
                                );
                                ?>

                            </strong>

                        </div>

                    </div>


                    <!-- QR CODE -->

                    <!--<div class="mcn-ca-qr-area">-->

                    <!--    <div class="mcn-ca-verify-title">-->
                    <!--        VERIFY ME:-->
                    <!--    </div>-->

                    <!--    <div-->
                    <!--        id="mcn-ca-qr-<?php echo esc_attr($user_id); ?>"-->
                    <!--        class="mcn-ca-qr"-->
                    <!--        data-url="<?php echo esc_attr($verification_url); ?>"-->
                    <!--    ></div>-->

                    <!--    <div class="mcn-ca-verify-text">-->
                    <!--        24 HOUR VERIFICATION-->
                    <!--    </div>-->

                    <!--</div>-->

                </div>


                <!-- CARD NAME -->

                <div class="mcn-ca-patient-name-large">

                    <?php
                    // echo esc_html(
                    //     $patient_name
                    // );
                    ?>

                </div>
                
                <?php if (!empty($signature_url)) : ?>

                    <div class="mcn-ca-signature-area">

                        <!--<div class="mcn-ca-signature-label">-->
                        <!--    PATIENT'S SIGNATURE-->
                        <!--</div>-->

                        <div class="mcn-ca-signature-box">

                            <img
                                class="mcn-ca-signature"
                                src="<?php echo esc_url($signature_url); ?>"
                                alt="Patient Signature"
                            >

                        </div>

                    </div>

                <?php endif; ?>
                


                <!-- MEDICAL USE -->

                <div class="mcn-ca-medical-use">

                    FOR MEDICAL USE ONLY

                </div>


                <!-- SIGNATURE -->

                


                <div class="mcn-ca-site">

                    demo.medcardsnow.com

                </div>


                <div class="mcn-ca-phone">

                    626-323-2358

                </div>

            </div>


            <!-- BOTTOM BAR -->

            <div class="mcn-ca-bottombar">

                MED CARDS NOW • CALIFORNIA PATIENT ID

            </div>

        </div>

    </div>


    <?php

    /*
     * =================================================
     * QR CODE SCRIPT
     * =================================================
     */

    ?>

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js">
    </script>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                var qrElement =
                    document.getElementById(
                        'mcn-ca-qr-<?php echo esc_js($user_id); ?>'
                    );

                if (
                    qrElement &&
                    typeof QRCode !== 'undefined'
                ) {

                    var verificationUrl =
                        qrElement.getAttribute(
                            'data-url'
                        );

                    if (verificationUrl) {

                        new QRCode(
                            qrElement,
                            {
                                text: verificationUrl,
                                width: 110,
                                height: 110,
                                correctLevel:
                                    QRCode.CorrectLevel.M
                            }
                        );

                    }
                }

            }
        );
        
        
        

        const img = document.getElementById('medical-card-patient-photo');
        console.log('Current src:', img?.getAttribute('src'));
        console.log('Resolved URL:', img?.src);
        

    </script>
    
    <!--<script>-->
    <!--    (function () {-->
    <!--        function initHardCopyChoice() {-->
    <!--            var box = document.getElementById('mcn-ca-hard-copy-choice');-->
    <!--            if (!box) return;-->
        
    <!--            var radios = box.querySelectorAll(-->
    <!--                'input[name="mcn_ca_hard_copy"]'-->
    <!--            );-->
        
    <!--            function getPlan() {-->
    <!--                var selected = document.querySelector(-->
    <!--                    '#input_2_11 input[name="input_11"]:checked'-->
    <!--                );-->
        
    <!--                return selected ? selected.value : '';-->
    <!--            }-->
        
    <!--            function saveChoice(value) {-->
    <!--                value = value === 'yes' ? 'yes' : 'no';-->
        
    <!--                try {-->
    <!--                    sessionStorage.setItem(-->
    <!--                        'medcards_selected_hard_copy',-->
    <!--                        value-->
    <!--                    );-->
    <!--                } catch (e) {}-->
        
                     <!--Sync with the existing profile form, if present.-->
    <!--                var form = document.getElementById('gform_2');-->
        
    <!--                if (form) {-->
    <!--                    var hidden = form.querySelector(-->
    <!--                        '[name="medcards_hard_copy_backup"]'-->
    <!--                    );-->
        
    <!--                    if (!hidden) {-->
    <!--                        hidden = document.createElement('input');-->
    <!--                        hidden.type = 'hidden';-->
    <!--                        hidden.name = 'medcards_hard_copy_backup';-->
    <!--                        form.appendChild(hidden);-->
    <!--                    }-->
        
    <!--                    hidden.value = value;-->
    <!--                }-->
    <!--            }-->
        
    <!--            function updateVisibility() {-->
    <!--                var plan = getPlan();-->
    <!--                var show = (-->
    <!--                    plan === 'caregiver_new' ||-->
    <!--                    plan === 'growers_license'-->
    <!--                );-->
        
    <!--                box.style.display = show ? '' : 'none';-->
        
    <!--                var saved = 'no';-->
        
    <!--                if (show) {-->
    <!--                    try {-->
    <!--                        saved = sessionStorage.getItem(-->
    <!--                            'medcards_selected_hard_copy'-->
    <!--                        ) || 'no';-->
    <!--                    } catch (e) {}-->
        
    <!--                    saved = saved === 'yes' ? 'yes' : 'no';-->
    <!--                }-->
        
    <!--                radios.forEach(function (radio) {-->
    <!--                    radio.checked = radio.value === saved;-->
    <!--                });-->
        
    <!--                saveChoice(saved);-->
    <!--            }-->
        
    <!--            radios.forEach(function (radio) {-->
    <!--                radio.addEventListener('change', function () {-->
    <!--                    saveChoice(this.value);-->
    <!--                });-->
    <!--            });-->
        
    <!--            document.addEventListener('change', function (event) {-->
    <!--                if (-->
    <!--                    event.target.matches(-->
    <!--                        '#input_2_11 input[name="input_11"]'-->
    <!--                    )-->
    <!--                ) {-->
    <!--                    updateVisibility();-->
    <!--                }-->
    <!--            });-->
        
    <!--            updateVisibility();-->
    <!--        }-->
        
    <!--        if (document.readyState === 'loading') {-->
    <!--            document.addEventListener(-->
    <!--                'DOMContentLoaded',-->
    <!--                initHardCopyChoice-->
    <!--            );-->
    <!--        } else {-->
    <!--            initHardCopyChoice();-->
    <!--        }-->
    <!--    })();-->
    <!--    </script>-->
    
    
    
<script>
(function () {
    'use strict';

    // Prevent duplicate event handlers on Gravity Forms re-render.
    if (document.documentElement.dataset.mcnHardCopyController === '1') {
        return;
    }
    document.documentElement.dataset.mcnHardCopyController = '1';

    var STORAGE_KEY = 'medcards_selected_hard_copy';
    var ALLOWED_PLANS = ['caregiver_new', 'growers_license'];

    function safeGet(key) {
        try {
            return sessionStorage.getItem(key) || '';
        } catch (e) {
            return '';
        }
    }

    function safeSet(key, value) {
        try {
            sessionStorage.setItem(key, value);
        } catch (e) {}
    }

    function getPlan() {
        var selected = document.querySelector(
            '#input_2_11 input[name="input_11"]:checked'
        );

        if (selected && selected.value) {
            safeSet('medcards_selected_plan', selected.value);
            return selected.value;
        }

        return safeGet('medcards_selected_plan');
    }

    function getChoiceBox() {
        return document.getElementById('mcn-ca-hard-copy-choice');
    }

    function getHardCopyValue() {
        var box = getChoiceBox();

        var selected = box
            ? box.querySelector(
                'input[name="mcn_ca_hard_copy"]:checked'
              )
            : null;

        if (selected) {
            return selected.value === 'yes' ? 'yes' : 'no';
        }

        var saved = safeGet(STORAGE_KEY);
        return saved === 'yes' ? 'yes' : 'no';
    }

    function syncHiddenFields(value) {
        document.querySelectorAll(
            'input[name="medcards_hard_copy_backup"]'
        ).forEach(function (field) {
            field.value = value;
        });

        // Also sync Gravity Forms Field 54 without firing change events.
        var gfField = document.getElementById('field_2_54');

        if (gfField) {
            var gfRadio = gfField.querySelector(
                'input[name="input_54"][value="' + value + '"]'
            );

            if (gfRadio) {
                gfRadio.checked = true;
            }
        }
    }

    function saveChoice(value) {
        var plan = getPlan();
        var allowed = ALLOWED_PLANS.indexOf(plan) !== -1;

        value = allowed && value === 'yes' ? 'yes' : 'no';

        safeSet(STORAGE_KEY, value);
        syncHiddenFields(value);

        // Update visible custom radio buttons without firing events.
        var box = getChoiceBox();

        if (box) {
            box.querySelectorAll(
                'input[name="mcn_ca_hard_copy"]'
            ).forEach(function (radio) {
                radio.checked = radio.value === value;
            });
        }

        console.log('MCN HARD COPY SYNCED:', {
            plan: plan,
            hardCopy: value,
            extraCharge: value === 'yes' ? 10 : 0
        });
    }

    function updateVisibility() {
        var box = getChoiceBox();

        if (!box) {
            return;
        }

        var plan = getPlan();
        var allowed = ALLOWED_PLANS.indexOf(plan) !== -1;

        box.style.display = allowed ? '' : 'none';

        if (!allowed) {
            // Do not allow hard copy for other plans.
            saveChoice('no');
            return;
        }

        // Restore the saved choice instead of resetting it.
        var saved = safeGet(STORAGE_KEY);
        var value = saved === 'yes' ? 'yes' : 'no';

        box.querySelectorAll(
            'input[name="mcn_ca_hard_copy"]'
        ).forEach(function (radio) {
            radio.checked = radio.value === value;
        });

        syncHiddenFields(value);
    }

    // User selects the custom Hard Copy radio.
    document.addEventListener('change', function (event) {
        var target = event.target;

        if (!target || !target.matches) {
            return;
        }

        if (
            target.matches(
                '#mcn-ca-hard-copy-choice input[name="mcn_ca_hard_copy"]'
            )
        ) {
            saveChoice(target.value);
            return;
        }

        // If Gravity Forms Field 54 changes, sync the custom choice.
        if (
            target.matches(
                '#field_2_54 input[name="input_54"]'
            )
        ) {
            saveChoice(target.value);
            return;
        }

        // Plan changed: update visibility and preserve valid selection.
        if (
            target.matches(
                '#input_2_11 input[name="input_11"]'
            )
        ) {
            updateVisibility();
        }
    });

    // Re-sync after Gravity Forms renders or changes pages.
    document.addEventListener('gform_post_render', function (event) {
        if (
            event.detail &&
            event.detail.formId &&
            Number(event.detail.formId) !== 2
        ) {
            return;
        }

        updateVisibility();
    });

    if (window.jQuery) {
        window.jQuery(document).on(
            'gform_post_render gform_page_loaded',
            function (event, formId) {
                if (formId && Number(formId) !== 2) {
                    return;
                }

                updateVisibility();
            }
        );
    }

    function initialize() {
        updateVisibility();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
</script>




    <style>

        /*
         * =================================================
         * CALIFORNIA MEDICAL CARD
         * =================================================
         */

        .mcn-ca-card-wrapper {

            width: 100%;
            max-width: 1100px;

            margin: 25px auto;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            box-sizing: border-box;
        }


        .mcn-ca-id-card {

            width: 100%;

            background: #ffffff;

            border-radius: 18px;

            overflow: hidden;

            border: 1px solid #d8d8d8;

            box-shadow:
                0 8px 25px rgba(
                    0,
                    0,
                    0,
                    0.14
                );

            box-sizing: border-box;
        }


        /*
         * TOP GREEN BAR
         */

        .mcn-ca-topbar {

            background: #176b3a;
            color: #ffffff;
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 25px;
            box-sizing: border-box;
        }


        .mcn-ca-topbar span {

            font-size: 28px;

            font-weight: 800;

            text-align: center;

            letter-spacing: 0.5px;

            line-height: normal;

            text-decoration: underline;

        }


        /*
         * CONTENT
         */

        .mcn-ca-content {
            padding: 15px 30px 15px;

        }


        /*
         * LOGO
         */

        .mcn-ca-logo-row {

            text-align: center;

            margin-bottom: 20px;

        }


        .mcn-ca-logo-row img {
            width: 250px;
            max-width: 100%;
            height: auto;
            display: inline-block;

        }


        /*
         * MAIN AREA
         */

        .mcn-ca-main {

            display: grid;

            grid-template-columns:
                250px
                minmax(300px, 1fr)
                180px;

            gap: 30px;

            align-items: center;

        }


        /*
         * PATIENT PHOTO
         */

        .mcn-ca-photo-area {

            width: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

        }


        .mcn-ca-patient-photo {
            width: 230px;
            height: 180px;
            object-fit: cover;
            display: block;
            border: 1px solid #ddd;
        }


        .mcn-ca-photo-placeholder {

            width: 230px;

            height: 290px;

            background: #eeeeee;

            border: 1px solid #cccccc;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #777;

            font-size: 18px;

            font-weight: 700;

        }


        /*
         * DETAILS
         */

        .mcn-ca-details {

            min-width: 0;

        }


        .mcn-ca-detail-row {

            display: flex;

            align-items: baseline;

            gap: 12px;

            margin-bottom: 12px;

            line-height: 16px;

        }


        .mcn-ca-label {

            min-width: 105px;

            color: #176b3a;

            font-size: 19px;

            font-weight: 700;

        }


        .mcn-ca-detail-row strong {

            color: #111111;

            font-size: 21px;

            font-weight: 800;

            word-break: break-word;

        }


        /*
         * QR
         */

        .mcn-ca-qr-area {

            text-align: center;

        }


        .mcn-ca-verify-title {

            color: #176b3a;

            font-size: 22px;

            font-weight: 800;

            margin-bottom: 8px;

        }


        .mcn-ca-qr {

            width: 110px;

            height: 110px;

            margin: 0 auto 10px;

            background: #ffffff;

        }


        .mcn-ca-qr img {

            display: block;

            width: 110px !important;

            height: 110px !important;

        }


        .mcn-ca-verify-text {

            color: #176b3a;

            font-size: 12px;

            font-weight: 800;

            line-height: 1.25;

        }


        /*
         * LARGE PATIENT NAME
         */

        .mcn-ca-patient-name-large {

            margin-top: 10px;

            color: #111111;

            font-size: 24px;

            font-weight: 900;

            text-transform: uppercase;

            line-height: 1;

            word-break: break-word;

        }


        /*
         * MEDICAL USE
         */

        .mcn-ca-medical-use {

            color: #176b3a;

            text-align: center;

            font-size: 24px;

            font-weight: 900;

            margin-top: 10px;

        }


        /*
         * SIGNATURE
         */

        .mcn-ca-signature-area {

            width: 100%;

            margin-top: 5px;

            text-align: left;

        }


        .mcn-ca-signature-label {

            color: #176b3a;

            font-size: 15px;

            font-weight: 800;

            text-transform: uppercase;

            margin-bottom: 4px;

        }


        .mcn-ca-signature-box {

            width: 260px;

            max-width: 100%;

            min-height: 70px;


            display: flex;

            align-items: flex-start;

            justify-content: left;


        }


        .mcn-ca-signature {

            max-width: 250px;

            width: auto;

            height: auto;

            max-height: 65px;

            object-fit: contain;

            display: block;

        }


        .mcn-ca-site {

            color: #176b3a;

            text-align: center;

            font-size: 18px;

            font-weight: 700;

            margin-top: 5px;

        }


        .mcn-ca-phone {
            color: #176b3a;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin-top: 1px;
        }


        /*
         * BOTTOM BAR
         */

        .mcn-ca-bottombar {

            background: #176b3a;

            color: #ffffff;

            text-align: center;

            padding:
                15px
                20px;

            font-size: 22px;

            font-weight: 800;

        }


        /*
         * TABLET
         */

        @media (max-width: 850px) {

            .mcn-ca-content {

                padding:
                    22px
                    22px
                    25px;

            }


            .mcn-ca-main {

                grid-template-columns:
                    190px
                    minmax(250px, 1fr);

            }


            .mcn-ca-qr-area {

                grid-column: 1 / -1;

                margin-top: 15px;

            }


            .mcn-ca-patient-photo,
            .mcn-ca-photo-placeholder {

                width: 180px;

                height: 230px;

            }

        }


        /*
         * MOBILE
         */

        @media (max-width: 600px) {

            .mcn-ca-card-wrapper {

                margin:
                    15px
                    auto;

                width:
                    calc(
                        100% - 30px
                    );

            }


            .mcn-ca-topbar {

                min-height: 65px;

                padding:
                    12px
                    15px;

            }


            .mcn-ca-topbar span {

                font-size: 18px;

            }


            .mcn-ca-logo-row img {

                width: 220px;

                max-width: 90%;

            }


            .mcn-ca-main {

                grid-template-columns: 1fr;

                gap: 20px;

            }


            .mcn-ca-photo-area {

                order: 1;

            }


            .mcn-ca-details {

                order: 2;

            }


            .mcn-ca-qr-area {

                order: 3;

                grid-column: auto;

            }


            .mcn-ca-patient-photo,
            .mcn-ca-photo-placeholder {

                width: 180px;

                height: 220px;

            }


            .mcn-ca-detail-row {

                display: block;

                margin-bottom: 10px;

            }


            .mcn-ca-label {

                display: block;

                min-width: 0;

                font-size: 14px;

                margin-bottom: 3px;

            }


            .mcn-ca-detail-row strong {

                font-size: 18px;

            }


            .mcn-ca-patient-name-large {

                font-size: 32px;

            }


            .mcn-ca-medical-use {

                font-size: 18px;

            }


            .mcn-ca-signature-area {

                margin-top: 20px;

            }


            .mcn-ca-signature-box {

                width: 230px;

            }


            .mcn-ca-signature {

                max-width: 220px;

                max-height: 60px;

            }


            .mcn-ca-bottombar {

                font-size: 14px;

                padding:
                    12px
                    10px;

            }

        }

    </style>

    <?php

    return ob_get_clean();
}





/**
 * MedCardsNow: Email patient ID-card PDF after successful payment.
 *
 * Uses:
 *   [medcards_california_id_card_cards]
 *   Gravity Forms Form 2
 *
 * Requires Dompdf to be available in the site's vendor folder.
 */

if (!function_exists('mcn_get_patient_email_for_card')) {
    function mcn_get_patient_email_for_card($user_id) {
        $user_id = absint($user_id);

        if (!$user_id) {
            return '';
        }

        // First: email saved in user meta.
        $email = get_user_meta($user_id, 'patient_email', true);

        if (!is_email($email)) {
            $email = get_user_meta($user_id, 'email', true);
        }

        if (is_email($email)) {
            return sanitize_email($email);
        }

        /*
         * Next: look for an Email field in the patient's
         * latest active Gravity Forms Form 2 entry.
         */
        if (class_exists('GFAPI')) {
            $entries = GFAPI::get_entries(
                2,
                array(
                    'status'        => 'active',
                    'field_filters' => array(
                        array(
                            'key'   => 'created_by',
                            'value' => $user_id,
                        ),
                    ),
                ),
                array(
                    'key'       => 'date_created',
                    'direction' => 'DESC',
                ),
                array(
                    'offset'    => 0,
                    'page_size' => 1,
                )
            );

            if (
                !is_wp_error($entries) &&
                is_array($entries) &&
                !empty($entries[0])
            ) {
                $entry = $entries[0];
                $form  = GFAPI::get_form(2);

                if (is_array($form) && !empty($form['fields'])) {
                    foreach ($form['fields'] as $field) {
                        if (
                            isset($field->type) &&
                            $field->type === 'email' &&
                            isset($field->id)
                        ) {
                            $field_id = (string) $field->id;
                            $candidate = isset($entry[$field_id])
                                ? trim((string) $entry[$field_id])
                                : '';

                            if (is_email($candidate)) {
                                return sanitize_email($candidate);
                            }
                        }
                    }
                }
            }
        }

        // Final fallback: WordPress account email.
        $user = get_userdata($user_id);

        if ($user && is_email($user->user_email)) {
            return sanitize_email($user->user_email);
        }

        return '';
    }
}

if (!function_exists('mcn_email_patient_card_pdf_after_payment')) {
    function mcn_email_patient_card_pdf_after_payment(
        $user_id,
        $plan_key,
        $transaction_id
    ) {
        $user_id        = absint($user_id);
        $transaction_id = sanitize_text_field((string) $transaction_id);
        $plan_key       = sanitize_key((string) $plan_key);

        if (!$user_id || $transaction_id === '') {
            error_log('MCN PDF email: Missing user ID or transaction ID.');
            return false;
        }

        // Do not email the same successful transaction twice.
        $sent_transaction = (string) get_user_meta(
            $user_id,
            'payment_pdf_email_transaction_id',
            true
        );

        if ($sent_transaction === $transaction_id) {
            error_log('MCN PDF email: Already sent for this transaction.');
            return true;
        }

        $recipient = mcn_get_patient_email_for_card($user_id);

        if (!is_email($recipient)) {
            error_log(
                'MCN PDF email: No valid patient email for user ID ' .
                $user_id
            );
            return false;
        }

        /*
         * Find the full Composer autoloader.
         * The entire vendor folder must be present.
         */
        $autoload_paths = array(
            get_stylesheet_directory() .
                '/custom-authorize-payment/vendor/autoload.php',
            WP_CONTENT_DIR .
                '/plugins/custom-authorize-payment/vendor/autoload.php',
        );

        $autoload_loaded = false;

        foreach ($autoload_paths as $autoload_path) {
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
                $autoload_loaded = true;
                break;
            }
        }

        if (
            !$autoload_loaded ||
            !class_exists('\Dompdf\Dompdf')
        ) {
            error_log(
                'MCN PDF email: Dompdf is unavailable. ' .
                'Check the vendor folder and Dompdf installation.'
            );
            return false;
        }

        if (!shortcode_exists('medcards_california_id_card_cards')) {
            error_log('MCN PDF email: Patient card shortcode is not registered.');
            return false;
        }

        /*
         * Render the existing card shortcode as the logged-in patient.
         * Restore the original user after rendering.
         */
        $previous_user_id = get_current_user_id();

        try {
            wp_set_current_user($user_id);

            $card_html = do_shortcode(
                '[medcards_california_id_card_cards]'
            );
        } finally {
            wp_set_current_user($previous_user_id);
        }

        if (
            !is_string($card_html) ||
            trim(wp_strip_all_tags($card_html)) === ''
        ) {
            error_log(
                'MCN PDF email: Card shortcode returned empty HTML. ' .
                'Check the patient state, entry, and card data.'
            );
            return false;
        }

        try {
            $dompdf = new \Dompdf\Dompdf();

            $dompdf->set_option('isRemoteEnabled', true);
            $dompdf->set_option('isHtml5ParserEnabled', true);

            $dompdf->loadHtml(
                '<!doctype html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body {
                            font-family: DejaVu Sans, sans-serif;
                            font-size: 12px;
                        }
                        img {
                            max-width: 100%;
                        }
                    </style>
                </head>
                <body>' .
                    $card_html .
                '</body>
                </html>',
                'UTF-8'
            );

            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            $pdf_bytes = $dompdf->output();

        } catch (\Throwable $e) {
            error_log(
                'MCN PDF email: PDF generation failed: ' .
                $e->getMessage()
            );
            return false;
        }

        if (empty($pdf_bytes)) {
            error_log('MCN PDF email: Generated PDF is empty.');
            return false;
        }

        $upload = wp_upload_dir();

        if (!empty($upload['error'])) {
            error_log('MCN PDF email: ' . $upload['error']);
            return false;
        }

        $pdf_dir = trailingslashit($upload['basedir']) .
            'mcn-payment-pdfs';

        if (
            !is_dir($pdf_dir) &&
            !wp_mkdir_p($pdf_dir)
        ) {
            error_log('MCN PDF email: Could not create PDF directory.');
            return false;
        }

        $pdf_path = trailingslashit($pdf_dir) .
            'patient-card-' . $user_id . '-' .
            wp_generate_password(8, false, false) . '.pdf';

        if (file_put_contents($pdf_path, $pdf_bytes) === false) {
            error_log('MCN PDF email: Could not save temporary PDF.');
            return false;
        }

        $user = get_userdata($user_id);
        $patient_name = $user
            ? trim($user->first_name . ' ' . $user->last_name)
            : 'Patient';

        $subject = 'Your MedCardsNow Patient ID Card';

        $message =
            "Hello " . ($patient_name !== '' ? $patient_name : 'Patient') . ",\n\n" .
            "Your payment has been approved. Your patient ID card PDF " .
            "is attached to this email.\n\n" .
            "Thank you,\nMedCardsNow";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail(
            $recipient,
            $subject,
            $message,
            $headers,
            array($pdf_path)
        );

        // Remove the temporary PDF whether sending succeeds or fails.
        if (file_exists($pdf_path)) {
            wp_delete_file($pdf_path);
        }

        if (!$sent) {
            error_log(
                'MCN PDF email: wp_mail failed for transaction ' .
                $transaction_id
            );
            return false;
        }

        // Save the duplicate-prevention marker only after wp_mail succeeds.
        update_user_meta(
            $user_id,
            'payment_pdf_email_transaction_id',
            $transaction_id
        );

        error_log(
            'MCN PDF email: WordPress accepted email for transaction ' .
            $transaction_id
        );

        return true;
    }
}







/**
 * Save patient photo in:
 * wp-content/uploads/patient-photo-copies/
 *
 * Store its URL in the patient's user meta.
 */





/* =====================================================
   FRONTEND CSS + JS
   ===================================================== */

add_action('wp_footer', 'medcards_california_addon_assets');

function medcards_california_addon_assets()
{
?>

    <style>
        /* Legal Support Cards */
        .ca-legal-addon-wrap {
            margin: 20px 0 28px;
            font-family: inherit;
        }

        .ca-legal-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .ca-legal-card {
            background: #25aaa8;
            color: #ffffff;
            border-radius: 14px;
            min-height: 180px;
            padding: 18px 16px 16px;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            overflow: hidden;
        }

        .ca-legal-card-muted {
            background: #6f938d;
        }

        .ca-legal-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .ca-legal-icon {
            width: 36px;
            height: 36px;
            background: #ffffff;
            color: #111111;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .ca-legal-card h4 {
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.25;
            text-align: center;
            margin: 0 0 10px;
        }

        .ca-legal-card p {
            color: #ffffff !important;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.28;
            text-align: center;
            margin: 0 0 15px;
        }

        .ca-legal-bottom {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ca-legal-bottom strong {
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
        }

        .ca-legal-bottom button {
            background: transparent;
            border: 0;
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            padding: 0;
            cursor: pointer;
        }

        .ca-legal-card:hover,
        .ca-legal-card.is-selected {
            transform: translateY(-3px);
            box-shadow: 0 16px 35px rgba(0, 0, 0, 0.13);
            outline: 4px solid rgba(0, 0, 0, 0.12);
        }

        .ca-legal-card.is-selected .ca-legal-bottom button {
            font-size: 0;
        }

        .ca-legal-card.is-selected .ca-legal-bottom button:after {
            content: "Added";
            font-size: 15px;
        }

        .ca-legal-clear {
            margin-top: 14px;
            background: #ffffff;
            border: 1px solid #dce8e6;
            border-radius: 8px;
            color: #52616b;
            padding: 10px 14px;
            font-size: 14px;
            cursor: pointer;
        }

        .ca-legal-clear:hover {
            background: #f7fbfb;
        }


        /* Year Plan Cards */
        .ca-years-wrap {
            margin: 30px 0;
            font-family: inherit;
        }

        .ca-addon-title {
            text-align: center;
            color: #1d4e86 !important;
            font-size: 30px;
            line-height: 1.2;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .ca-addon-subtitle {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 24px;
            font-size: 14px;
            color: #333;
        }

        .ca-years-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .ca-years-card {
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 22px 20px 28px 54px;
            position: relative;
            cursor: pointer;
            min-height: 210px;
            box-shadow: 0 10px 0 rgba(0, 0, 0, 0.08);
            transition: all 0.25s ease;
        }

        .ca-years-card.is-selected {
            border-color: #4f6cff;
            box-shadow: 0 12px 0 rgba(79, 108, 255, 0.18);
        }

        .ca-years-radio {
            width: 18px;
            height: 18px;
            border: 2px solid #d4d4d4;
            border-radius: 50%;
            position: absolute;
            left: 22px;
            top: 48%;
            transform: translateY(-50%);
        }

        .ca-years-card.is-selected .ca-years-radio {
            border-color: #4f6cff;
            background: #4f6cff;
        }

        .ca-years-card.is-selected .ca-years-radio:after {
            content: "✓";
            color: #fff;
            font-size: 11px;
            position: absolute;
            left: 3px;
            top: 0px;
            font-weight: 800;
        }

        .ca-years-badge {
            position: absolute;
            right: 12px;
            top: -12px;
            background: #1d4e86;
            color: #fff;
            border-radius: 999px;
            padding: 8px 9px;
            font-size: 9px;
            line-height: 1;
            font-weight: 800;
            max-width: 70px;
            text-align: center;
        }

        .ca-years-card h4 {
            color: #1d4e86 !important;
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .ca-years-card p {
            color: #333 !important;
            font-size: 13px;
            line-height: 1.35;
            margin: 0 0 14px;
        }

        .ca-years-price {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .ca-years-price del {
            color: #777;
            font-size: 13px;
        }

        .ca-years-price strong {
            color: #365dff;
            font-size: 18px;
            font-weight: 800;
        }

        .ca-years-price span {
            background: #365dff;
            color: #ffffff;
            font-size: 10px;
            padding: 5px 8px;
            border-radius: 2px;
            font-weight: 800;
        }

        .ca-years-card small {
            font-size: 11px;
            color: #555;
        }


        /* ID Card */
        .ca-id-wrap {
            margin: 30px 0;
            display: flex;
            justify-content: center;
        }

        .ca-id-card {
            background: #f2f2f2;
            border-radius: 22px;
            padding: 0 28px 28px;
            max-width: 430px;
            width: 100%;
            overflow: hidden;
            text-align: center;
            transition: 0.25s ease;
            border: 2px solid transparent;
        }


        .ca-id-card.is-disabled {
            opacity: 0.78;
        }

        .ca-id-card.is-disabled .ca-id-add {
            background: #6b7280 !important;
            cursor: not-allowed;
        }

        .ca-id-card.is-selected {
            border-color: #1d4e86;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .ca-id-topbar {
            background: #1d4e86;
            color: #ffffff;
            padding: 9px;
            font-size: 13px;
            font-weight: 800;
            border-radius: 0 0 14px 14px;
            margin-bottom: 24px;
        }

        .ca-id-card h3 {
            color: #1d4e86 !important;
            font-size: 34px;
            font-weight: 800;
            margin: 0 0 22px;
        }

        .ca-id-image {
            background: #fff;
            border-radius: 18px;
            padding: 34px 20px;
            margin-bottom: 22px;
        }

        .mcn-id-card-preview {
            background: linear-gradient(135deg, #f8feff 0%, #d9f5f1 100%);
            border-radius: 18px;
            padding: 18px 18px 14px;
            min-height: 210px;
            box-shadow: 0 18px 35px rgba(13, 53, 85, 0.16);
            border: 1px solid rgba(30, 130, 130, 0.25);
            color: #163b5b;
            text-align: left;
            position: relative;
            overflow: hidden;
        }

        .mcn-id-card-preview:after {
            content: "";
            position: absolute;
            width: 170px;
            height: 170px;
            border-radius: 50%;
            right: -60px;
            top: -50px;
            background: rgba(37, 170, 168, 0.16);
        }

        .mcn-card-top {
            position: relative;
            z-index: 2;
            margin-bottom: 18px;
        }

        .mcn-card-logo {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .mcn-card-logo img {
            width: 230px;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .mcn-card-body {
            display: grid;
            grid-template-columns: 1fr 88px;
            gap: 16px;
            position: relative;
            z-index: 2;
            align-items: center;
        }

        .mcn-card-info span {
            display: block;
            font-size: 12px;
            color: #4e6674;
            margin-bottom: 4px;
        }

        .mcn-card-info strong {
            display: block;
            font-size: 19px;
            color: #111827;
            margin-bottom: 12px;
        }

        .mcn-card-info small {
            display: block;
            font-size: 11px;
            color: #253b46;
            line-height: 1.3;
        }

        .mcn-card-photo {
            width: 82px;
            height: 82px;
            background: #f6bc46;
            border-radius: 50%;
            padding: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 4px rgba(255, 255, 255, 0.55);
        }

        .mcn-avatar {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background:
                radial-gradient(circle at 50% 28%, #63351f 0 10px, transparent 11px),
                radial-gradient(circle at 50% 42%, #8b563b 0 14px, transparent 15px),
                linear-gradient(#1d4e86 0 100%);
            position: relative;
            overflow: hidden;
        }

        .mcn-avatar:after {
            content: "";
            position: absolute;
            width: 46px;
            height: 28px;
            background: #123d7c;
            border-radius: 24px 24px 0 0;
            left: 8px;
            bottom: -2px;
        }

        .mcn-card-bottom {
            border-top: 1px solid rgba(22, 59, 91, 0.16);
            margin-top: 18px;
            padding-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .mcn-card-bottom strong {
            color: #15395d;
            font-size: 15px;
            line-height: 1.2;
        }

        .mcn-card-bottom span {
            color: #4e6674;
            font-size: 11px;
            line-height: 1.25;
            text-align: right;
        }

        .mcn-card-bottom b {
            color: #25aaa8;
            font-size: 13px;
        }

        .ca-id-card p {
            font-size: 16px;
            line-height: 1.45;
            text-align: left;
            color: #111 !important;
        }

        .ca-id-bold {
            font-weight: 800;
            text-align: center !important;
        }

        .ca-id-add {
            background: #1d4e86;
            color: #ffffff;
            width: 100%;
            border: 0;
            border-radius: 4px;
            padding: 16px 18px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            margin: 18px 0;
        }

        .ca-id-card.is-selected .ca-id-add {
            background: #0f766e;
        }

        .ca-id-card.is-selected .ca-id-add:after {
            content: "ed";
        }

        .ca-id-no {
            display: block;
            color: #111 !important;
            font-weight: 800;
            text-decoration: underline;
            margin-bottom: 20px;
        }

        .ca-id-note {
            display: block;
            color: #777;
            font-size: 11px;
            line-height: 1.4;
            text-align: left;
        }


        @media (max-width: 980px) {

            .ca-legal-grid,
            .ca-years-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {

            .ca-legal-grid,
            .ca-years-grid {
                grid-template-columns: 1fr;
            }

            .ca-addon-title {
                font-size: 24px;
            }
        }
    </style>


 

    
    
<script>
(function () {
    'use strict';

    
    function triggerChange(element) {
        if (!element) return;
    
        // Prevent recursive event dispatch.
        if (element.dataset.mcnTriggeringChange === '1') return;
    
        element.dataset.mcnTriggeringChange = '1';
    
        try {
            element.dispatchEvent(new Event('input', {
                bubbles: true
            }));
    
            element.dispatchEvent(new Event('change', {
                bubbles: true
            }));
        } finally {
            delete element.dataset.mcnTriggeringChange;
        }
    }
    



    if (window.MCNProfileScriptInitialized) {
        return;
    }
    window.MCNProfileScriptInitialized = true;

    var FORM_ID = 2;

    var KEYS = {
        plan: 'medcards_selected_plan',
        planLegacy: 'selected_plan',
        year: 'medcards_selected_year_plan',
        duration: 'medcards_selected_duration',
        hardCopy: 'medcards_selected_hard_copy',
        idCard: 'medcards_selected_id_card',
        legal: 'medcards_ca_legal_letters_selected'
    };

    var VALID_YEARS = ['1_year', '2_year', '3_year'];
    var HARD_COPY_PLANS = ['caregiver_new', 'growers_license'];

    function getForm() {
        return document.getElementById('gform_' + FORM_ID);
    }

    function safeGet(key) {
        try {
            return sessionStorage.getItem(key) || '';
        } catch (e) {
            return '';
        }
    }

    function safeSet(key, value) {
        try {
            sessionStorage.setItem(key, String(value));
        } catch (e) {
            console.warn('Session storage unavailable:', key, e);
        }
    }

    function fireChange(element) {
        if (!element) return;

        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function getPlanInput() {
        return document.querySelector(
            '#input_2_11 input[name="input_11"]:checked'
        );
    }

    function getPlan() {
        var input = getPlanInput();
        return input ? input.value : '';
    }

    function getYearField() {
        return document.querySelector(
            '#field_2_63 input[name="input_63"], ' +
            '#gform_2 input[name="input_63"]'
        );
    }

    function getIdCardField() {
        return document.querySelector(
            '#gform_2 input[name="input_61"]'
        );
    }

    function getHardCopyInput() {
        return document.querySelector(
            '#input_2_54 input[name="input_54"]:checked'
        );
    }

    function setHiddenField(name, value) {
        var form = getForm();
        if (!form) return;

        var fields = form.querySelectorAll('[name="' + name + '"]');

        if (!fields.length) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
            return;
        }

        fields.forEach(function (field) {
            if (field.type === 'radio' || field.type === 'checkbox') {
                field.checked = field.value === value;
            } else {
                field.value = value;
            }
        });
    }

    /* --------------------------------------------------
       PAYMENT PLAN
    -------------------------------------------------- */

    function savePlan() {
        var plan = getPlan() ||
            safeGet(KEYS.plan) ||
            safeGet(KEYS.planLegacy);

        if (plan) {
            safeSet(KEYS.plan, plan);
            safeSet(KEYS.planLegacy, plan);
        }

        setHiddenField('medcards_plan_backup', plan);
        syncPaymentFields();

        return plan;
    }

    function restorePlan() {
        var saved = safeGet(KEYS.plan) || safeGet(KEYS.planLegacy);

        if (!saved) return;

        var radio = document.querySelector(
            '#input_2_11 input[name="input_11"][value="' +
            saved.replace(/\\/g, '\\\\').replace(/"/g, '\\"') +
            '"]'
        );

        if (radio && !getPlanInput()) {
            radio.checked = true;
            fireChange(radio);
        }
    }

    /* --------------------------------------------------
       YEAR / DURATION
    -------------------------------------------------- */

    function getValidYear(value) {
        return VALID_YEARS.indexOf(value) !== -1 ? value : '';
    }

    function getSelectedYear() {
        var field = getYearField();
        var card = document.querySelector('.ca-years-card.is-selected');

        return getValidYear(card ? card.getAttribute('data-value') : '') ||
            getValidYear(field ? field.value : '') ||
            getValidYear(safeGet(KEYS.year));
    }

    function setYear(value) {
        value = getValidYear(value) || '3_year';

        document.querySelectorAll('.ca-years-card').forEach(function (card) {
            var selected = card.getAttribute('data-value') === value;

            card.classList.toggle('is-selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        var field = getYearField();

        if (field) {
            field.value = value;
            fireChange(field);
        }

        safeSet(KEYS.year, value);
        safeSet(KEYS.duration, value);

        setHiddenField('medcards_year_plan_backup', value);
        syncPaymentFields();
    }

    /* --------------------------------------------------
       HARD COPY
    -------------------------------------------------- */

    // function updateHardCopy() {
    //     var field = document.getElementById('field_2_54');
    //     if (!field) return;

    //     var show = HARD_COPY_PLANS.indexOf(getPlan()) !== -1;

    //     field.style.display = show ? '' : 'none';
    //     field.setAttribute('aria-hidden', show ? 'false' : 'true');

    //     if (!show) {
    //         var no = field.querySelector(
    //             'input[name="input_54"][value="no"]'
    //         );

    //         if (no && !no.checked) {
    //             no.checked = true;
    //             fireChange(no);
    //         }

    //         safeSet(KEYS.hardCopy, 'no');
    //     }

    //     setHiddenField(
    //         'medcards_hard_copy_backup',
    //         show ? (getHardCopyInput() || {}).value || 'no' : 'no'
    //     );
    // }
    
    function updateHardCopy() {
        var field = document.getElementById('field_2_54');
        if (!field) return;
    
        var selectedPlan = getPlan();
        var show = (
            selectedPlan === 'caregiver_new' ||
            selectedPlan === 'growers_license'
        );
    
        field.style.display = show ? '' : 'none';
        field.setAttribute('aria-hidden', show ? 'false' : 'true');
    
        var radios = field.querySelectorAll(
            'input[name="input_54"]'
        );
    
        var selectedValue = 'no';
    
        if (show) {
            radios.forEach(function (radio) {
                if (radio.checked) {
                    selectedValue = radio.value;
                }
    
                radio.addEventListener('change', function () {
                    saveHardCopyValue(this.value);
                });
            });
        } else {
            var noRadio = field.querySelector(
                'input[name="input_54"][value="no"]'
            );
    
            if (noRadio) {
                noRadio.checked = true;
            }
    
            selectedValue = 'no';
        }
    
        saveHardCopyValue(selectedValue);
    }
    
    function saveHardCopyValue(value) {
        value = value === 'yes' ? 'yes' : 'no';
    
        try {
            sessionStorage.setItem(
                'medcards_selected_hard_copy',
                value
            );
        } catch (e) {}
    
        setHiddenField('medcards_hard_copy_backup', value);
    
        // Keep the existing payment-field synchronization.
        if (typeof syncPaymentFields === 'function') {
            syncPaymentFields();
        }
    }

    /* --------------------------------------------------
       OPTIONAL ID CARD
    -------------------------------------------------- */

    function updateIdCard() {
        var card = document.querySelector('.ca-id-card');
        var add = document.querySelector('.ca-id-add');
        var no = document.querySelector('.ca-id-no');
        var field = getIdCardField();

        if (getPlan() === 'new_pdf_id_card') {
            if (card) card.classList.add('is-selected', 'is-disabled');
            if (add) {
                add.disabled = true;
                add.textContent = 'ID Card Included in Selected Plan';
            }
            if (no) no.style.display = 'none';

            if (field) field.value = 'included';
            safeSet(KEYS.idCard, 'included');
        } else {
            if (card) card.classList.remove('is-disabled');
            if (add) {
                add.disabled = false;
                add.textContent = 'Add ID Card + $16';
            }
            if (no) no.style.display = '';

            var current = field ? field.value : safeGet(KEYS.idCard);

            if (!current || current === 'included') {
                current = safeGet(KEYS.idCard);
                if (!current || current === 'included') current = 'no';
            }

            if (field) field.value = current;

            if (card) {
                card.classList.toggle('is-selected', current === 'yes');
            }

            safeSet(KEYS.idCard, current);
        }

        setHiddenField('medcards_id_card_backup', safeGet(KEYS.idCard) || 'no');

        if (field) fireChange(field);
    }

    function setIdCard(value) {
        if (getPlan() === 'new_pdf_id_card') {
            updateIdCard();
            return;
        }

        value = value === 'yes' ? 'yes' : 'no';

        var field = getIdCardField();
        var card = document.querySelector('.ca-id-card');

        if (field) {
            field.value = value;
            fireChange(field);
        }

        if (card) card.classList.toggle('is-selected', value === 'yes');

        safeSet(KEYS.idCard, value);
        setHiddenField('medcards_id_card_backup', value);
    }

    /* --------------------------------------------------
       LEGAL LETTERS
    -------------------------------------------------- */

    function getLegalSelection() {
        var values = [];

        document.querySelectorAll(
            '.ca-legal-card.is-selected, ' +
            '.ca-legal-letter-card.is-selected'
        ).forEach(function (card) {
            var value = card.getAttribute('data-value');

            if (value && value !== 'none' && values.indexOf(value) === -1) {
                values.push(value);
            }
        });

        document.querySelectorAll(
            'input[name="ca_legal_support_letter_ui"]:checked'
        ).forEach(function (input) {
            if (
                input.value &&
                input.value !== 'none' &&
                values.indexOf(input.value) === -1
            ) {
                values.push(input.value);
            }
        });

        return values;
    }

    function saveLegal(forceCurrentUI) {
        var cardsExist = document.querySelector(
            '.ca-legal-card, .ca-legal-letter-card'
        );

        var value;

        if (forceCurrentUI || cardsExist) {
            var selected = getLegalSelection();
            value = selected.length ? selected.join(',') : 'none';
            safeSet(KEYS.legal, value);
        } else {
            value = safeGet(KEYS.legal) || 'none';
        }

        setHiddenField('input_58', value);
        setHiddenField('medcards_legal_letters_backup', value);

        return value;
    }

    function restoreLegal() {
        var stored = safeGet(KEYS.legal);
        if (!stored) return;

        var values = stored === 'none' ? [] : stored.split(',');

        document.querySelectorAll(
            '.ca-legal-card, .ca-legal-letter-card'
        ).forEach(function (card) {
            var selected = values.indexOf(
                card.getAttribute('data-value')
            ) !== -1;

            card.classList.toggle('is-selected', selected);

            card.querySelectorAll(
                'input[name="ca_legal_support_letter_ui"]'
            ).forEach(function (checkbox) {
                checkbox.checked = selected;
            });

            var button = card.querySelector('.ca-legal-bottom button');
            if (button) button.textContent = selected ? 'Added' : 'Add';
        });

        saveLegal(false);
    }

    function clearLegal() {
        document.querySelectorAll(
            '.ca-legal-card, .ca-legal-letter-card'
        ).forEach(function (card) {
            card.classList.remove('is-selected');

            card.querySelectorAll(
                'input[name="ca_legal_support_letter_ui"]'
            ).forEach(function (checkbox) {
                checkbox.checked = false;
            });

            var button = card.querySelector('.ca-legal-bottom button');
            if (button) button.textContent = 'Add';
        });

        safeSet(KEYS.legal, 'none');
        saveLegal(true);
    }

    /* --------------------------------------------------
       PAYMENT HIDDEN FIELDS
    -------------------------------------------------- */

    function syncPaymentFields() {
        var plan = getPlan() || safeGet(KEYS.plan) || safeGet(KEYS.planLegacy);
        var year = getSelectedYear();

        var planField = document.getElementById('payment_selected_plan');
        var durationField = document.getElementById('selected_duration');

        if (planField && plan) planField.value = plan;
        if (durationField && year) durationField.value = year;

        setHiddenField('medcards_plan_backup', plan || '');
        setHiddenField('medcards_year_plan_backup', year || '3_year');
    }

    function saveAll() {
        savePlan();

        var year = getSelectedYear();
        if (year) setYear(year);

        var hardCopy = getHardCopyInput();
        if (hardCopy) safeSet(KEYS.hardCopy, hardCopy.value);

        var idField = getIdCardField();
        if (idField && idField.value) safeSet(KEYS.idCard, idField.value);

        saveLegal(false);
        updateHardCopy();
        updateIdCard();
        syncPaymentFields();
    }

    /* --------------------------------------------------
       ONE CLICK HANDLER
    -------------------------------------------------- */

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) return;

        var clear = target.closest('.ca-legal-clear');

        if (clear) {
            event.preventDefault();
            clearLegal();
            return;
        }

        var legal = target.closest(
            '.ca-legal-card, .ca-legal-letter-card'
        );

        if (legal) {
            if (target.closest('a, input, label, select, textarea')) return;

            event.preventDefault();

            legal.classList.toggle('is-selected');

            var selected = legal.classList.contains('is-selected');

            legal.querySelectorAll(
                'input[name="ca_legal_support_letter_ui"]'
            ).forEach(function (checkbox) {
                checkbox.checked = selected;
            });

            var button = legal.querySelector('.ca-legal-bottom button');
            if (button) button.textContent = selected ? 'Added' : 'Add';

            saveLegal(true);
            return;
        }

        var yearCard = target.closest('.ca-years-card');

        if (yearCard) {
            event.preventDefault();
            setYear(yearCard.getAttribute('data-value'));
            return;
        }

        if (target.closest('.ca-id-add')) {
            event.preventDefault();
            setIdCard('yes');
            return;
        }

        if (target.closest('.ca-id-no')) {
            event.preventDefault();
            setIdCard('no');
            return;
        }

        // var paymentLink = target.closest('a[href]');

        // if (paymentLink) {
        //     try {
        //         var url = new URL(paymentLink.href);
        
        //         if (url.pathname.indexOf('/secure-payment-gateway/') !== -1) {
        //             var plan = savePlan();
        //             var yearValue = getSelectedYear();
        
        //             if (plan) url.searchParams.set('payment-plan', plan);
        //             if (yearValue) {
        //                 url.searchParams.set('payment-duration', yearValue);
        //             }
        
        //             paymentLink.href = url.toString();
        //         }
        //     } catch (error) {
        //         console.warn('Could not update payment URL:', error);
        //     }
        // }
    }, true);

    /* --------------------------------------------------
       CHANGE AND SUBMIT HANDLERS
    -------------------------------------------------- */

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || !target.matches) return;

        if (target.matches('#input_2_11 input[name="input_11"]')) {
            savePlan();
            updateHardCopy();
            updateIdCard();
        }

        if (target.matches('#input_2_54 input[name="input_54"]')) {
            safeSet(KEYS.hardCopy, target.value);
        }

        if (target.matches('input[name="input_61"]')) {
            safeSet(KEYS.idCard, target.value);
        }

        if (target.matches('input[name="input_63"]')) {
            if (getValidYear(target.value)) setYear(target.value);
        }

        if (target.matches('input[name="ca_legal_support_letter_ui"]')) {
            var card = target.closest(
                '.ca-legal-card, .ca-legal-letter-card'
            );

            if (card) {
                card.classList.toggle('is-selected', target.checked);

                var button = card.querySelector('.ca-legal-bottom button');
                if (button) button.textContent = target.checked ? 'Added' : 'Add';
            }

            saveLegal(true);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        if (event.target !== getForm()) return;

        saveAll();

        // Final values immediately before submission.
        saveLegal(false);
        syncPaymentFields();
    }, true);

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) return;

        var button = target.closest(
            '#gform_2 .gform_next_button, ' +
            '#gform_2 .gform_previous_button, ' +
            '#gform_2 .gform_button, ' +
            '#gform_2 button[type="submit"], ' +
            '#gform_2 input[type="submit"]'
        );

        if (button) saveAll();
    }, true);

    /* --------------------------------------------------
       INITIALIZATION
    -------------------------------------------------- */

    function initialize() {
        restorePlan();
        restoreLegal();

        var savedYear = getValidYear(safeGet(KEYS.year)) ||
            getValidYear(safeGet(KEYS.duration));

        var yearField = getYearField();

        if (!savedYear && yearField) {
            savedYear = getValidYear(yearField.value);
        }

        setYear(savedYear || '3_year');

        updateHardCopy();
        updateIdCard();
        saveLegal(false);
        syncPaymentFields();

        console.log('MCN INITIALIZED:', {
            plan: getPlan() || safeGet(KEYS.plan),
            year: getYearField() ? getYearField().value : 'field not found',
            legal: safeGet(KEYS.legal) || 'none',
            hardCopy: safeGet(KEYS.hardCopy) || 'no',
            idCard: safeGet(KEYS.idCard) || 'no'
        });
    }

    var initTimer;

    function scheduleInitialize() {
        clearTimeout(initTimer);
        initTimer = setTimeout(initialize, 150);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    document.addEventListener('gform_post_render', function (event) {
        if (!event.detail || !event.detail.formId ||
            Number(event.detail.formId) === FORM_ID) {
            scheduleInitialize();
        }
    });

    if (window.jQuery) {
        window.jQuery(document).on(
            'gform_post_render gform_page_loaded',
            function (event, formId) {
                if (Number(formId) === FORM_ID) scheduleInitialize();
            }
        );
    }

    setTimeout(initialize, 500);
    setTimeout(initialize, 1500);

})();
</script>



<?php
}



add_action('wp_footer', 'medcards_legal_final_fix', 99999);
function medcards_legal_final_fix() {
    if (!is_user_logged_in()) return;
    ?>
    


    <?php
}


/* =====================================================
   CHECKOUT BREAKDOWN EXAMPLE

   $user_id = get_current_user_id();

   $single_year_plan_price = get_user_meta($user_id, 'payment_single_year_plan_price', true);
   $plan_price = get_user_meta($user_id, 'payment_plan_price', true); // adjusted base plan after year discount
   $year_plan = get_user_meta($user_id, 'california_year_plan', true);
   $year_discount = get_user_meta($user_id, 'california_year_plan_discount_percent', true);
   $year_discount_amount = get_user_meta($user_id, 'california_year_plan_discount_amount', true);
   $id_card_price = get_user_meta($user_id, 'california_id_card_price', true);
   $hard_copy_price = get_user_meta($user_id, 'california_hard_copy_price', true);
   $legal_letter_price = get_user_meta($user_id, 'california_legal_support_letter_price', true);
   $total = get_user_meta($user_id, 'payment_amount', true);

   ===================================================== */


/* =====================================================
   GRAVITY FORMS FIELD SETUP

   Existing:
   Field 11 = Choose Plan radio field
   Field 51 = Payment Amount hidden field
   Field 54 = Add a Hard Copy radio field
   Field 58 = Legal Support Letter hidden field

   New:
   Field 61 = California ID Card hidden field
       Default Value: no

   Field 63 = California Years Plan hidden field
       Default Value: 1_year

   HTML field after Choose Plan:
       [medcards_california_year_plan_cards]

   HTML field after Hard Copy / before payment:
       [medcards_california_id_card_cards]

   HTML field for legal letters:
       [medcards_california_legal_letter_cards]

   HTML field IDs can be anything. The code only depends on fields:
   11, 51, 54, 58, 61, 63

   ===================================================== */


/* =====================================================

IMPORTANT:
Replace this:

REPLACE_WITH_MEDCARDSNOW_LOGO_URL

with your uploaded Med Cards Now logo image URL.

Example:
https://yourwebsite.com/wp-content/uploads/2026/05/medcardsnow-logo.png

===================================================== */




/* =====================================================
   FINAL CALIFORNIA FULL TOTAL + PLAN META OVERRIDE
   California only.

   payment_amount =
   year-adjusted base plan
   + hard copy
   + legal letters
   + ID card

   payment_plan_ =
   readable full plan string:
   Base plan + Hard Copy + Letters + ID Card + Year Plan
   ===================================================== */

add_filter('gform_pre_submission_2', 'medcards_final_ca_force_full_payment_total_before_submit', 99999);
function medcards_final_ca_force_full_payment_total_before_submit($form)
{

    $result = medcards_final_ca_get_full_total_and_plan_summary(null);

    if (!empty($result) && isset($result['total'])) {
        $_POST['input_51'] = number_format((float) $result['total'], 2, '.', '');
        $_POST['input_58'] = $result['legal_values_string'];
        $_POST['input_61'] = $result['id_card_value'];
    }

    return $form;
}




function footer_script_id_card(){
    ?>
        
        <script>
(function () {
    'use strict';

    const STORAGE_KEY = 'medcards_patient_photo';
    const FORM_ID = '2';
    const FIELD_ID = '13';

    /*
     * 1. FIND THE CARD IMAGE ELEMENT
     */
    function getCardImage() {
        return document.getElementById(
            'medical-card-patient-photo'
        );
    }

    /*
     * 2. DISPLAY IMAGE ON MEDICAL ID CARD
     */
    function displayPatientPhoto(imageData) {
        const image = getCardImage();

        if (!image || !imageData) {
            return;
        }

        image.src = imageData;
        image.style.display = 'block';
        image.style.objectFit = 'cover';

        image.onload = function () {
            console.log('Patient photo displayed on ID card.');
        };

        image.onerror = function () {
            console.error('Unable to display patient photo.');
        };
    }

    /*
     * 3. COMPRESS IMAGE BEFORE SAVING
     * Helps reduce localStorage size.
     */
    function compressImage(file) {
        return new Promise(function (resolve, reject) {
            if (!file || !file.type.startsWith('image/')) {
                reject(new Error('Please select a valid image.'));
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                const img = new Image();

                img.onload = function () {
                    const maxWidth = 1000;
                    const maxHeight = 1000;

                    let width = img.width;
                    let height = img.height;

                    const scale = Math.min(
                        1,
                        maxWidth / width,
                        maxHeight / height
                    );

                    width = Math.round(width * scale);
                    height = Math.round(height * scale);

                    const canvas = document.createElement('canvas');

                    canvas.width = width;
                    canvas.height = height;

                    const context = canvas.getContext('2d');

                    context.drawImage(
                        img,
                        0,
                        0,
                        width,
                        height
                    );

                    const compressedData = canvas.toDataURL(
                        'image/jpeg',
                        0.8
                    );

                    resolve(compressedData);
                };

                img.onerror = function () {
                    reject(new Error('Could not read image.'));
                };

                img.src = event.target.result;
            };

            reader.onerror = function () {
                reject(new Error('Could not load selected file.'));
            };

            reader.readAsDataURL(file);
        });
    }

    /*
     * 4. SAVE NEW IMAGE TO LOCALSTORAGE
     * A new upload replaces the previous image.
     */
    async function savePatientPhoto(file) {
        try {
            const imageData = await compressImage(file);

            localStorage.setItem(
                STORAGE_KEY,
                imageData
            );

            displayPatientPhoto(imageData);

            console.log('Patient photo saved successfully.');

        } catch (error) {
            console.error('Patient photo error:', error);

            alert(
                'Image could not be saved in this browser. ' +
                'Please try a smaller image.'
            );
        }
    }

    /*
     * 5. LISTEN FOR GRAVITY FORMS FIELD 13 UPLOAD
     */
    function bindUploadField() {
        const selectors = [
            '#field_2_13 input[type="file"]',
            '#input_2_13',
            'input[name="input_13"]'
        ];

        const inputs = document.querySelectorAll(
            selectors.join(',')
        );

        inputs.forEach(function (input) {
            if (input.dataset.patientPhotoBound === '1') {
                return;
            }

            input.dataset.patientPhotoBound = '1';

            input.addEventListener('change', function () {
                const files = input.files;

                if (!files || files.length === 0) {
                    return;
                }

                // Use the most recently selected file.
                const file = files[files.length - 1];

                savePatientPhoto(file);
            });
        });
    }

    /*
     * 6. RESTORE IMAGE WHEN PROFILE PAGE LOADS
     */
    function restorePatientPhoto() {
        const savedImage = localStorage.getItem(STORAGE_KEY);

        if (savedImage) {
            displayPatientPhoto(savedImage);
        }
    }

    /*
     * 7. INITIALIZE
     */
    function initializePatientPhoto() {
        bindUploadField();
        restorePatientPhoto();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializePatientPhoto
        );
    } else {
        initializePatientPhoto();
    }

    /*
     * Gravity Forms may recreate its upload field.
     * Watch for dynamically added elements.
     */
    const observer = new MutationObserver(function () {
        bindUploadField();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

})();
</script>
        
        
    <?php
}
add_action('wp_footer','footer_script_id_card');


add_action('gform_after_submission_2', 'medcards_final_ca_save_full_payment_total_meta_after_submit', 99999, 2);
function medcards_final_ca_save_full_payment_total_meta_after_submit($entry, $form)
{

    $result = medcards_final_ca_get_full_total_and_plan_summary($entry);

    if (empty($result) || !isset($result['total'])) {
        return;
    }

    $user_id = get_current_user_id();

    if (!$user_id) {
        return;
    }

    /*
     * Main ACF/user meta fields from your screenshot.
     */
    update_user_meta($user_id, 'payment_amount', number_format((float) $result['total'], 2, '.', '')); // final numeric total only
    update_user_meta($user_id, 'payment_plan_', $result['payment_plan_string']);

    error_log(print_r([
        'payment_plan_'      => get_user_meta($user_id, 'payment_plan_', true),
        'payment_amount'     => get_user_meta($user_id, 'payment_amount', true),
        'selected_plan_addons' => get_user_meta($user_id, 'selected_plan_addons', true),
    ], true));

    /*
     * Breakdown meta.
     */
    update_user_meta($user_id, 'payment_single_year_plan_price', number_format((float) $result['single_year_base_price'], 2, '.', ''));
    update_user_meta($user_id, 'payment_plan_price', number_format((float) $result['year_adjusted_base_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_hard_copy', $result['hard_copy_value']);
    update_user_meta($user_id, 'california_hard_copy_price', number_format((float) $result['hard_copy_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_id_card', $result['id_card_value']);
    update_user_meta($user_id, 'california_id_card_price', number_format((float) $result['id_card_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_legal_support_letter', !empty($result['legal_titles']) ? implode(' | ', $result['legal_titles']) : 'none');
    update_user_meta($user_id, 'california_legal_support_letter_value', $result['legal_values_string']);
    update_user_meta($user_id, 'california_legal_support_letter_price', number_format((float) $result['legal_total'], 2, '.', ''));

    update_user_meta($user_id, 'california_year_plan', $result['year_plan']);
    update_user_meta($user_id, 'california_year_plan_title', $result['year_title']);
    update_user_meta($user_id, 'california_year_plan_years', $result['year_count']);
    update_user_meta($user_id, 'california_year_plan_discount_percent', number_format((float) $result['year_discount_percent'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_original_price', number_format((float) $result['year_original_price'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_discount_amount', number_format((float) $result['year_discount_amount'], 2, '.', ''));

    update_user_meta($user_id, 'selected_plan_addons', $result['addons_string']);

    /*
     * Force GF hidden payment field value too.
     */
    $_POST['input_51'] = number_format((float) $result['total'], 2, '.', '');
    $_POST['input_58'] = $result['legal_values_string'];
    $_POST['input_61'] = $result['id_card_value'];
}


add_action('gform_after_submission_2', 'medcards_debug_gf_entry_fields', 5, 2);
function medcards_debug_gf_entry_fields($entry, $form) {
    error_log('=== GF FORM 2 ENTRY DEBUG ===');
    error_log('Entry ID: ' . rgar($entry, 'id'));
    
    // Log all field values
    foreach ($form['fields'] as $field) {
        $field_id = $field->id;
        $value = rgar($entry, (string) $field_id);
        
        // Handle address field sub-inputs
        if ($field->type === 'address') {
            error_log("Field {$field_id} (address): " . print_r(array(
                $field_id . '.1' => rgar($entry, $field_id . '.1'),
                $field_id . '.2' => rgar($entry, $field_id . '.2'),
                $field_id . '.3' => rgar($entry, $field_id . '.3'),
                $field_id . '.4' => rgar($entry, $field_id . '.4'),
                $field_id . '.5' => rgar($entry, $field_id . '.5'),
                $field_id . '.6' => rgar($entry, $field_id . '.6'),
            ), true));
        } else {
            if (!empty($value)) {
                error_log("Field {$field_id} ({$field->type}, label: {$field->label}): " . print_r($value, true));
            }
        }
    }
    error_log('=== END GF FORM 2 ENTRY DEBUG ===');
}




function medcards_final_ca_get_full_total_and_plan_summary($entry = null)
{

    if (!is_user_logged_in()) {
        return array();
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if ($state !== 'California') {
        return array();
    }

    $selected_plan = medcards_final_ca_read_value($entry, '11', 'input_11');
    $hard_copy     = medcards_final_ca_read_value($entry, '54', 'input_54');
    $id_card       = medcards_final_ca_read_value($entry, '61', 'input_61');
    $year_plan     = medcards_final_ca_read_value($entry, '63', 'input_63');

    if (!$year_plan) {
        $year_plan = '1_year';
    }

    $pricing = medcards_get_state_pricing();

    if (!$selected_plan || empty($pricing['California'][$selected_plan])) {
        return array();
    }

    $plan_titles = array(
        'new_pdf_id_card'   => 'Instant PDF + Patient ID Card', 
        'caregiver_new'     => 'Caregiver + New Patient',
        'growers_license'   => 'License to Cultivate up to 99 Plants',
        'new_pdf'           => 'New Patient — PDF Only',
        'renewal_hard_copy' => 'Existing patient renewal — With Hard Copy',
    );

    $base_title = isset($plan_titles[$selected_plan]) ? $plan_titles[$selected_plan] : $selected_plan;

    /*
     * BASE PLAN WITH YEAR LOGIC
     */
    $single_year_base_price = (float) $pricing['California'][$selected_plan];
    $year_data = medcards_calculate_year_plan_price($single_year_base_price, $year_plan);

    $year_adjusted_base_price = (float) $year_data['final_price'];
    $total = $year_adjusted_base_price;

    $year_title = isset($year_data['title']) ? $year_data['title'] : '1 Year';
    $year_count = isset($year_data['years']) ? (int) $year_data['years'] : 1;
    $year_discount_percent = isset($year_data['discount']) ? (float) $year_data['discount'] : 0;
    $year_original_price = isset($year_data['original_price']) ? (float) $year_data['original_price'] : $single_year_base_price;
    $year_discount_amount = isset($year_data['discount_amount']) ? (float) $year_data['discount_amount'] : 0;

    /*
     * HARD COPY
     */
    $hard_copy_price = 0;
    $hard_copy_value = 'no';

    if (medcards_plan_already_has_hard_copy($selected_plan)) {
        $hard_copy_value = 'included';
    } elseif ($hard_copy === 'yes') {
        $hard_copy_value = 'yes';
        $hard_copy_price = 10;
        $total += $hard_copy_price;
    }

    /*
     * LEGAL LETTERS
     */
    $legal_values = medcards_final_ca_read_legal_values($entry);
    $legal_letters = medcards_get_california_legal_letters();

    $legal_titles = array();
    $legal_total = 0;

    foreach ($legal_values as $value) {
        if ($value !== 'none' && isset($legal_letters[$value])) {
            $legal_titles[] = $legal_letters[$value]['title'];
            $legal_total += (float) $legal_letters[$value]['price'];
        }
    }

    if ($legal_total > 0) {
        $total += $legal_total;
    }

    $legal_values_string = !empty($legal_values) ? implode(',', $legal_values) : 'none';

    /*
     * ID CARD
     */
    $id_card_price = 0;
    $id_card_value = 'no';

    if (medcards_plan_already_has_id_card($selected_plan)) {
        $id_card_value = 'included';
    } elseif ($id_card === 'yes') {
        $id_card_value = 'yes';
        $id_card_price = 16;
        $total += $id_card_price;
    }

    /*
     * READABLE PAYMENT PLAN STRING
     * Saved to ACF/user meta key: payment_plan_
     *
     * Important:
     * Do NOT include "Base Plan:".
     * Do NOT include "Total:" here.
     * Total belongs only in payment_amount.
     */
    $parts = array();

    /*
     * Clean plan/package name.
     */
    $parts[] = $base_title;

    /*
     * Year plan label.
     */
    if ($year_discount_percent > 0) {
        $parts[] = $year_title . ' Plan - Save ' . number_format($year_discount_percent, 0) . '%';
    } else {
        $parts[] = $year_title . ' Plan';
    }

    /*
     * Add-ons.
     */
    if ($hard_copy_value === 'included') {
        $parts[] = 'Hard Copy Included';
    } elseif ($hard_copy_price > 0) {
        $parts[] = 'Hard Copy +$' . number_format($hard_copy_price, 2);
    }

    if (!empty($legal_titles)) {
        $parts[] = 'Letters: ' . implode(', ', $legal_titles) . ' +$' . number_format($legal_total, 2);
    }

    if ($id_card_value === 'included') {
        $parts[] = 'ID Card Included';
    } elseif ($id_card_price > 0) {
        $parts[] = 'ID Card +$' . number_format($id_card_price, 2);
    }

    $payment_plan_string = implode(' + ', $parts);

    $addons = array();

    if ($hard_copy_value === 'included') {
        $addons[] = 'Hard Copy Included';
    } elseif ($hard_copy_price > 0) {
        $addons[] = 'Hard Copy +$' . number_format($hard_copy_price, 2);
    }

    if (!empty($legal_titles)) {
        $addons[] = 'Letters: ' . implode(', ', $legal_titles) . ' +$' . number_format($legal_total, 2);
    }

    if ($id_card_value === 'included') {
        $addons[] = 'ID Card Included';
    } elseif ($id_card_price > 0) {
        $addons[] = 'ID Card +$' . number_format($id_card_price, 2);
    }

    if ($year_discount_percent > 0) {
        $addons[] = $year_title . ' Plan - Save ' . number_format($year_discount_percent, 0) . '%';
    } else {
        $addons[] = $year_title . ' Plan';
    }

    return array(
        'selected_plan'             => $selected_plan,
        'base_title'                => $base_title,
        'single_year_base_price'    => $single_year_base_price,
        'year_adjusted_base_price'  => $year_adjusted_base_price,
        'year_plan'                 => $year_plan,
        'year_title'                => $year_title,
        'year_count'                => $year_count,
        'year_discount_percent'     => $year_discount_percent,
        'year_original_price'       => $year_original_price,
        'year_discount_amount'      => $year_discount_amount,
        'hard_copy_value'           => $hard_copy_value,
        'hard_copy_price'           => $hard_copy_price,
        'legal_values'              => $legal_values,
        'legal_values_string'       => $legal_values_string,
        'legal_titles'              => $legal_titles,
        'legal_total'               => $legal_total,
        'id_card_value'             => $id_card_value,
        'id_card_price'             => $id_card_price,
        'total'                     => $total,
        'payment_plan_string'       => $payment_plan_string,
        'addons_string'             => !empty($addons) ? implode(' | ', $addons) : 'No add-ons selected',
    );
}

function medcards_final_ca_read_value($entry, $entry_key, $post_key)
{

    $value = '';

    if (!empty($entry) && function_exists('rgar')) {
        $value = rgar($entry, $entry_key);
    }

    if (($value === '' || $value === null) && function_exists('rgpost')) {
        $value = rgpost($post_key);
    }

    if (($value === '' || $value === null) && isset($_POST[$post_key])) {
        $value = $_POST[$post_key];
    }

    if (is_array($value)) {
        $value = reset($value);
    }

    return sanitize_text_field((string) $value);
}

function medcards_final_ca_read_legal_values($entry = null)
{

    $raw_values = array();

    /*
     * Entry field 58.
     */
    if (!empty($entry) && function_exists('rgar')) {
        $entry_value = rgar($entry, '58');

        if (!empty($entry_value) && $entry_value !== 'none') {
            $raw_values[] = $entry_value;
        }
    }

    /*
     * POST hidden field 58.
     */
    if (function_exists('rgpost')) {
        $post_value = rgpost('input_58');

        if (!empty($post_value) && $post_value !== 'none') {
            $raw_values[] = $post_value;
        }
    }

    if (isset($_POST['input_58']) && $_POST['input_58'] !== 'none') {
        $raw_values[] = $_POST['input_58'];
    }

    /*
     * UI fallback.
     */
    // if (isset($_POST['ca_legal_support_letter_ui'])) {
    //     $raw_values[] = $_POST['ca_legal_support_letter_ui'];
    // }
    
    $live_legal = '';

    $allowed_legal_letters = array(
        'parole',
        'employer',
        'driving',
        'possession_person',
    );
    
    // Read the payment form's submitted legal-letter values.
    $raw_legal = array();
    
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $posted = wp_unslash($_POST['ca_legal_support_letter_ui']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    
    } elseif (isset($_POST['live_legal'])) {
        $posted = wp_unslash($_POST['live_legal']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    
    } elseif (isset($_POST['input_58'])) {
        $posted = wp_unslash($_POST['input_58']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    }
    
    $valid_legal = array();
    
    foreach ($raw_legal as $value) {
        $value = sanitize_key(trim((string) $value));
    
        if (in_array($value, $allowed_legal_letters, true)) {
            $valid_legal[] = $value;
        }
    }
    
    $valid_legal = array_values(array_unique($valid_legal));
    $live_legal = implode(',', $valid_legal);

    /*
     * Backup hidden field injected by JS on every page.
     * This fixes Gravity Forms multi-page issue where custom HTML selections
     * may not be present on the final submit page.
     */
    if (isset($_POST['medcards_legal_letters_backup']) && $_POST['medcards_legal_letters_backup'] !== 'none') {
        $raw_values[] = $_POST['medcards_legal_letters_backup'];
    }

    $values = array();

    foreach ($raw_values as $raw) {
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $values[] = $item;
            }
        } else {
            foreach (explode(',', (string) $raw) as $item) {
                $values[] = $item;
            }
        }
    }

    $legal_letters = medcards_get_california_legal_letters();
    $clean = array();

    foreach ($values as $value) {
        $value = sanitize_text_field(trim((string) $value));

        if ($value && $value !== 'none' && isset($legal_letters[$value])) {
            $clean[] = $value;
        }
    }

    return array_values(array_unique($clean));
}

/* =====================================================
   END FINAL CALIFORNIA FULL TOTAL + PLAN META OVERRIDE
   ===================================================== */


/*
 * LEGAL LETTERS MULTIPAGE BACKUP FIX:
 * Legal letters are now persisted across Gravity Forms pages through:
 * - sessionStorage key: medcards_ca_legal_letters_selected
 * - hidden input: medcards_legal_letters_backup
 *
 * The final California override reads this backup field and includes
 * legal_total inside payment_amount and payment_plan_.
 */


function func_sticky_header() {
    ?>
    <div class="nav-item phnumber d-none d-md-block" aria-label="Call us">
        <a class="nav-link" href="tel:+16263232358" aria-label="Call (626) 323-2358">
            (626) 323-2358
        </a>
    </div>
	
	<script>
        document.addEventListener('DOMContentLoaded', function () {
            const phoneNumber = document.querySelector('.phnumber');

            if (!phoneNumber) {
                return;
            }

            const scrollOffset = 50;

            function handlePhoneScroll() {
                if (window.scrollY > scrollOffset) {
                    phoneNumber.classList.add('sticky-phone');
                } else {
                    phoneNumber.classList.remove('sticky-phone');
                }
            }

            // Initial state
            handlePhoneScroll();

            // Handle scroll
            window.addEventListener('scroll', handlePhoneScroll, {
                passive: true
            });
        });
    </script>
    <?php
}

add_action('wp_footer', 'func_sticky_header');



/**
 * =========================================================
 * SAVE GRAVITY FORMS FORM 2 ADDRESS FIELDS TO USER META
 * =========================================================
 * This ensures the custom_authorize_payment shortcode can
 * read the address values from user meta.
 *
 * IMPORTANT: Update the field IDs below to match your
 * actual Gravity Forms Form 2 field IDs for address fields.
 */

add_action('gform_after_submission_2', 'medcards_save_gf_address_to_user_meta', 15, 2);

function medcards_save_gf_address_to_user_meta($entry, $form)
{
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // =========================================================
    // REPLACE THESE WITH YOUR ACTUAL FORM 2 FIELD IDs
    // =========================================================
    // If you have a single Address field (type=address), use:
    // $address_field_id = 15; // <-- change to your field ID
    // $address_line_1 = rgar($entry, $address_field_id . '.1');
    // $address_line_2 = rgar($entry, $address_field_id . '.2');
    // $city           = rgar($entry, $address_field_id . '.3');
    // $state          = rgar($entry, $address_field_id . '.4');
    // $zip_code       = rgar($entry, $address_field_id . '.5');
    // $country        = rgar($entry, $address_field_id . '.6');
    //
    // OR if separate fields, use those field IDs:
    // $address_line_1 = rgar($entry, '15');
    // $address_line_2 = rgar($entry, '16');
    // $city           = rgar($entry, '17');
    // $state          = rgar($entry, '18');
    // $zip_code       = rgar($entry, '19');
    // $country        = rgar($entry, '20');

    // ---------------------------------------------------------
    // SMART MAPPING — tries both patterns automatically
    // ---------------------------------------------------------
    $address_line_1 = '';
    $address_line_2 = '';
    $city           = '';
    $state          = '';
    $zip_code       = '';
    $country        = '';

    // 1. Try single Address field pattern (field_id.1 ... field_id.6)
    // Change 15 to your actual address field ID
    $address_field_id = 15;
    $sub_1 = rgar($entry, $address_field_id . '.1');
    if (!empty($sub_1)) {
        $address_line_1 = $sub_1;
        $address_line_2 = rgar($entry, $address_field_id . '.2');
        $city           = rgar($entry, $address_field_id . '.3');
        $state          = rgar($entry, $address_field_id . '.4');
        $zip_code       = rgar($entry, $address_field_id . '.5');
        $country        = rgar($entry, $address_field_id . '.6');
    }

    // 2. Try separate field IDs pattern
    if (empty($address_line_1)) {
        $address_line_1 = rgar($entry, '15');
        $address_line_2 = rgar($entry, '16');
        $city           = rgar($entry, '17');
        $state          = rgar($entry, '18');
        $zip_code       = rgar($entry, '19');
        $country        = rgar($entry, '20');
    }

    // 3. Fallback: keep existing meta if still empty
    if (empty($address_line_1)) $address_line_1 = get_user_meta($user_id, 'address_line_1', true);
    if (empty($address_line_2)) $address_line_2 = get_user_meta($user_id, 'address_line_2', true);
    if (empty($city))           $city           = get_user_meta($user_id, 'city', true);
    if (empty($state))          $state          = get_user_meta($user_id, 'state', true);
    if (empty($zip_code))       $zip_code       = get_user_meta($user_id, 'zip_code', true);
    if (empty($country))        $country        = get_user_meta($user_id, 'country', true) ?: 'United States';

    // ---------------------------------------------------------
    // SAVE TO USER META + ACF
    // ---------------------------------------------------------
    if (!empty($address_line_1)) {
        update_user_meta($user_id, 'address_line_1', sanitize_text_field($address_line_1));
        if (function_exists('update_field')) {
            update_field('address_line_1', sanitize_text_field($address_line_1), 'user_' . $user_id);
        }
    }
    if (!empty($address_line_2)) {
        update_user_meta($user_id, 'address_line_2', sanitize_text_field($address_line_2));
        if (function_exists('update_field')) {
            update_field('address_line_2', sanitize_text_field($address_line_2), 'user_' . $user_id);
        }
    }
    if (!empty($city)) {
        update_user_meta($user_id, 'city', sanitize_text_field($city));
        if (function_exists('update_field')) {
            update_field('city', sanitize_text_field($city), 'user_' . $user_id);
        }
    }
    if (!empty($state)) {
        update_user_meta($user_id, 'state', sanitize_text_field($state));
        if (function_exists('update_field')) {
            update_field('state', sanitize_text_field($state), 'user_' . $user_id);
        }
    }
    if (!empty($zip_code)) {
        update_user_meta($user_id, 'zip_code', sanitize_text_field($zip_code));
        if (function_exists('update_field')) {
            update_field('zip_code', sanitize_text_field($zip_code), 'user_' . $user_id);
        }
    }
    if (!empty($country)) {
        update_user_meta($user_id, 'country', sanitize_text_field($country));
        if (function_exists('update_field')) {
            update_field('country', sanitize_text_field($country), 'user_' . $user_id);
        }
    }

    error_log('MCN Address saved for user ' . $user_id . ': ' . print_r(array(
        'address_line_1' => get_user_meta($user_id, 'address_line_1', true),
        'address_line_2' => get_user_meta($user_id, 'address_line_2', true),
        'city'           => get_user_meta($user_id, 'city', true),
        'state'          => get_user_meta($user_id, 'state', true),
        'zip_code'       => get_user_meta($user_id, 'zip_code', true),
        'country'        => get_user_meta($user_id, 'country', true),
    ), true));
}

















































































































/**
 * Custom Authorize Payment - functions.php version
 * Shortcode: [custom_authorize_payment]
 *
 * IMPORTANT: Deactivate the original plugin before using this code to avoid duplicate functions/hooks.
 *
 * Place the Authorize.Net SDK vendor folder in your active CHILD THEME at:
 * wp-content/themes/YOUR-CHILD-THEME/custom-authorize-payment/vendor/autoload.php
 *
 * Copy the ENTIRE vendor folder (not just autoload.php) from the old plugin.
 */

if (!defined('ABSPATH')) exit;

$GLOBALS['cap_payment_error'] = '';

/* =========================================================
 * PRICE / PLAN WHITELIST (server-side, cannot be tampered)
 * ========================================================= */
function cap_get_pricing_table()
{
    return array(
        'new_pdf_id_card'   => 64.99,
        'caregiver_new'     => 99.99,
        'growers_license'   => 189.99,
        'new_pdf'           => 37.00,
        'renewal_hard_copy' => 47.00,
    );
}

function cap_get_plan_labels()
{
    return array(
        'new'               => 'New Patient',
        'renewal'           => 'Renewal',
        'two_year'          => '2 Year Pricing',
        'three_year'        => '3 Year Pricing',
        'caregiver_new'     => 'Caregiver + New Patient',
        'growers_license'   => 'License to Cultivate up to 99 Plants',
        'new_pdf'           => 'New Patient — PDF Only',
        'new_pdf_id_card'   => 'Instant PDF + Patient ID Card',
        'renewal_hard_copy' => 'Existing Patient Renewal — With Hard Copy',
    );
}

/**
 * Recompute the total server-side from whitelisted values.
 * This is the ONLY trusted source for the charge amount.
 *
 * @return float
 */
function cap_compute_server_total($plan_key, $year_key, $hard_copy, $id_card, $legal_csv)
{
    $pricing = cap_get_pricing_table();
    if (!isset($pricing[$plan_key])) {
        return 0.0;
    }

    $base = (float) $pricing[$plan_key];

    $year_plans = array(
        '1_year' => array('years' => 1, 'discount' => 0),
        '2_year' => array('years' => 2, 'discount' => 8),
        '3_year' => array('years' => 3, 'discount' => 16),
    );
    $year_cfg = isset($year_plans[$year_key]) ? $year_plans[$year_key] : $year_plans['1_year'];

    $original       = $base * $year_cfg['years'];
    $discount_amt   = $original * ($year_cfg['discount'] / 100);
    $total          = $original - $discount_amt;

    // Hard copy add-on
    if ($plan_key !== 'renewal_hard_copy' && $hard_copy === 'yes') {
        $total += 10.00;
    }

    // ID card add-on
    if ($plan_key !== 'new_pdf_id_card' && $id_card === 'yes') {
        $total += 16.00;
    }

    // Legal letters add-on: validate values against the configured California letters.
    // Merge the custom checkout value with the Gravity Forms/UI selection when present.
    $legal_values = array_filter(array_map('trim', explode(',', (string) $legal_csv)));
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $ui_values = $_POST['ca_legal_support_letter_ui'];
        $legal_values = array_merge($legal_values, is_array($ui_values) ? $ui_values : explode(',', (string) $ui_values));
    }
    $legal_values = array_values(array_unique(array_filter(array_map('sanitize_text_field', $legal_values))));
    $total += function_exists('medcards_get_legal_letter_total_from_values_safe')
        ? (float) medcards_get_legal_letter_total_from_values_safe($legal_values)
        : (count(array_filter($legal_values, function ($v) { return $v !== '' && $v !== 'none'; })) * 79.00);

    return round($total, 2);
}


/* =========================================================
 * COUPON MANAGEMENT
 * Admin menu: Coupons. Stored in WordPress option mcn_payment_coupons.
 * ========================================================= */
if (!defined('MCN_COUPONS_OPTION')) define('MCN_COUPONS_OPTION', 'mcn_payment_coupons');

function mcn_get_coupons() {
    $coupons = get_option(MCN_COUPONS_OPTION, array());
    return is_array($coupons) ? $coupons : array();
}

function mcn_get_coupon_discount($code, $subtotal) {
    $code = strtoupper(trim(sanitize_text_field((string) $code)));
    $subtotal = max(0, (float) $subtotal);
    if ($code === '' || $subtotal <= 0) return array('valid' => false, 'discount' => 0, 'message' => '');
    foreach (mcn_get_coupons() as $coupon) {
        if (strtoupper((string) ($coupon['code'] ?? '')) !== $code) continue;
        if (empty($coupon['enabled'])) return array('valid'=>false,'discount'=>0,'message'=>'This coupon is currently disabled.');
        if (!empty($coupon['expiry']) && strtotime($coupon['expiry'] . ' 23:59:59') < current_time('timestamp')) return array('valid'=>false,'discount'=>0,'message'=>'This coupon has expired.');
        $limit = absint($coupon['usage_limit'] ?? 0);
        if ($limit > 0 && absint($coupon['used_count'] ?? 0) >= $limit) return array('valid'=>false,'discount'=>0,'message'=>'This coupon has reached its usage limit.');
        if ($subtotal < (float) ($coupon['minimum'] ?? 0)) return array('valid'=>false,'discount'=>0,'message'=>'The order does not meet this coupon’s minimum amount.');
        $value = max(0, (float) ($coupon['value'] ?? 0));
        $discount = (($coupon['type'] ?? 'percent') === 'fixed') ? $value : ($subtotal * min(100, $value) / 100);
        $discount = min($subtotal, round($discount, 2));
        return array('valid'=>true,'discount'=>$discount,'message'=>'Coupon applied successfully.');
    }
    return array('valid'=>false,'discount'=>0,'message'=>'Invalid coupon code.');
}


/* AJAX coupon validation: applies the coupon immediately without submitting card fields. */
add_action('wp_ajax_mcn_apply_checkout_coupon', 'mcn_apply_checkout_coupon_ajax');
function mcn_apply_checkout_coupon_ajax() {
    if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Please log in again.'), 401);
    check_ajax_referer('mcn_apply_checkout_coupon', 'nonce');

    $plan_key = sanitize_key(wp_unslash($_POST['plan_key'] ?? ''));
    $year_key = sanitize_key(wp_unslash($_POST['year_plan'] ?? '3_year'));
    $hard_copy = sanitize_text_field(wp_unslash($_POST['hard_copy'] ?? 'no'));
    $id_card = sanitize_text_field(wp_unslash($_POST['id_card'] ?? 'no'));
    $legal = sanitize_text_field(wp_unslash($_POST['legal'] ?? ''));
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? '')));

    if (!$plan_key) {
        $plan_key = sanitize_key((string) get_user_meta(get_current_user_id(), 'payment_selected_plan', true));
    }
    if (!$plan_key) wp_send_json_error(array('message' => 'Please select a plan first.'));

    // Reuse the trusted server-side price calculation; never trust a browser-sent amount.
    $subtotal = cap_compute_server_total($plan_key, $year_key, $hard_copy, $id_card, $legal);
    if ($subtotal <= 0) {
        $saved = get_user_meta(get_current_user_id(), 'payment_amount', true);
        $subtotal = is_numeric($saved) ? (float) $saved : 0;
    }
    $result = mcn_get_coupon_discount($code, $subtotal);
    if (empty($result['valid'])) wp_send_json_error(array('message' => $result['message'] ?: 'Please enter a valid coupon code.'));

    $discount = (float) $result['discount'];
    $total = max(0, round($subtotal - $discount, 2));
    wp_send_json_success(array(
        'message' => $result['message'],
        'code' => $code,
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'discount' => number_format($discount, 2, '.', ''),
        'total' => number_format($total, 2, '.', ''),
    ));
}

add_action('admin_menu', function () {
    add_menu_page('Coupons', 'Coupons', 'manage_options', 'mcn-coupons', 'mcn_render_coupons_page', 'dashicons-tickets-alt', 59);
});
add_action('admin_post_mcn_save_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_save_coupon');
    $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['code'] ?? ''))));
    $type = sanitize_key(wp_unslash($_POST['type'] ?? 'percent'));
    $value = (float) ($_POST['value'] ?? 0);
    $expiry = sanitize_text_field(wp_unslash($_POST['expiry'] ?? ''));
    $limit = absint($_POST['usage_limit'] ?? 0);
    $minimum = max(0, (float) ($_POST['minimum'] ?? 0));
    if (!$code || !in_array($type, array('percent','fixed'), true) || $value <= 0 || ($type === 'percent' && $value > 100)) {
        wp_safe_redirect(add_query_arg('coupon_notice', 'invalid', admin_url('admin.php?page=mcn-coupons'))); exit;
    }
    if ($expiry && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) $expiry = '';
    $coupons = mcn_get_coupons();
    $old_used = isset($coupons[$code]['used_count']) ? absint($coupons[$code]['used_count']) : 0;
    $coupons[$code] = array('code'=>$code,'type'=>$type,'value'=>round($value,2),'expiry'=>$expiry,'usage_limit'=>$limit,'minimum'=>round($minimum,2),'enabled'=>isset($_POST['enabled']) ? 1 : 0,'used_count'=>$old_used);
    update_option(MCN_COUPONS_OPTION, $coupons, false);
    wp_safe_redirect(add_query_arg('coupon_notice', 'saved', admin_url('admin.php?page=mcn-coupons'))); exit;
});
add_action('admin_post_mcn_delete_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_delete_coupon');
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    $coupons = mcn_get_coupons(); unset($coupons[$code]); update_option(MCN_COUPONS_OPTION, $coupons, false);
    wp_safe_redirect(admin_url('admin.php?page=mcn-coupons')); exit;
});
add_action('admin_post_mcn_toggle_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_toggle_coupon');
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    $coupons = mcn_get_coupons();
    if (isset($coupons[$code])) { $coupons[$code]['enabled'] = empty($coupons[$code]['enabled']) ? 1 : 0; update_option(MCN_COUPONS_OPTION, $coupons, false); }
    wp_safe_redirect(admin_url('admin.php?page=mcn-coupons')); exit;
});
function mcn_render_coupons_page() {
    if (!current_user_can('manage_options')) return;
    $coupons = mcn_get_coupons();
    echo '<div class="wrap mcn-coupons-admin"><h1>Coupon Management</h1>';
    if (isset($_GET['coupon_notice']) && $_GET['coupon_notice'] === 'saved') echo '<div class="notice notice-success"><p>Coupon saved.</p></div>';
    if (isset($_GET['coupon_notice']) && $_GET['coupon_notice'] === 'invalid') echo '<div class="notice notice-error"><p>Please enter a valid code and discount value (percentage must be 1–100).</p></div>';
    echo '<style>.mcn-coupon-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;max-width:1100px;margin:18px 0}.mcn-coupon-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px}.mcn-coupon-grid label{display:block;font-weight:600;margin-bottom:6px}.mcn-coupon-grid input,.mcn-coupon-grid select{width:100%;max-width:none}.mcn-coupon-table{width:100%;border-collapse:collapse}.mcn-coupon-table th,.mcn-coupon-table td{padding:12px;border-bottom:1px solid #eee;text-align:left}.mcn-coupon-code{font-weight:700;letter-spacing:.5px}@media(max-width:782px){.mcn-coupon-grid{grid-template-columns:1fr}}</style>';
    echo '<div class="mcn-coupon-panel"><h2>Add Coupon</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="mcn_save_coupon">'; wp_nonce_field('mcn_save_coupon');
    echo '<div class="mcn-coupon-grid"><div><label>Coupon Code</label><input required name="code" type="text" placeholder="e.g. WELCOME20" maxlength="40"></div><div><label>Discount Type</label><select name="type"><option value="percent">Percentage (%)</option><option value="fixed">Fixed amount ($)</option></select></div><div><label>Discount Value</label><input required name="value" type="number" min="0.01" step="0.01"></div><div><label>Expiry Date (optional)</label><input name="expiry" type="date"></div><div><label>Usage Limit (0 = unlimited)</label><input name="usage_limit" type="number" min="0" step="1" value="0"></div><div><label>Minimum Order ($)</label><input name="minimum" type="number" min="0" step="0.01" value="0"></div></div><p><label><input type="checkbox" name="enabled" value="1" checked> Enable coupon immediately</label></p><p><button class="button button-primary">Save Coupon</button></p></form></div>';
    echo '<div class="mcn-coupon-panel"><h2>Existing Coupons</h2><table class="mcn-coupon-table"><thead><tr><th>Code</th><th>Discount</th><th>Expiry</th><th>Usage</th><th>Minimum</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    if (!$coupons) echo '<tr><td colspan="7">No coupons created yet.</td></tr>';
    foreach ($coupons as $c) {
        $code = (string) ($c['code'] ?? ''); $limit = absint($c['usage_limit'] ?? 0); $used = absint($c['used_count'] ?? 0);
        echo '<tr><td class="mcn-coupon-code">'.esc_html($code).'</td><td>'.esc_html(($c['type'] ?? '') === 'fixed' ? '$'.number_format((float)$c['value'],2) : number_format((float)$c['value'],2).'%').'</td><td>'.esc_html($c['expiry'] ?: 'Never').'</td><td>'.esc_html($used.' / '.($limit ? $limit : '∞')).'</td><td>$'.esc_html(number_format((float)($c['minimum'] ?? 0),2)).'</td><td>'.(!empty($c['enabled']) ? '<span style="color:#16803c">Enabled</span>' : '<span style="color:#b32d2e">Disabled</span>').'</td><td><form style="display:inline-block" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mcn_toggle_coupon"><input type="hidden" name="code" value="'.esc_attr($code).'">'; wp_nonce_field('mcn_toggle_coupon'); echo '<button class="button">'.(!empty($c['enabled']) ? 'Disable' : 'Enable').'</button></form> <form style="display:inline-block" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Delete this coupon?\')"><input type="hidden" name="action" value="mcn_delete_coupon"><input type="hidden" name="code" value="'.esc_attr($code).'">'; wp_nonce_field('mcn_delete_coupon'); echo '<button class="button button-link-delete">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div></div>';
}


/* =========================================================
 * ERROR MESSAGE MAP
 * ========================================================= */
function cap_get_payment_error_details($payment_error)
{
    $messages = array(
        'declined' => array(
            'title'   => 'Payment Declined',
            'message' => 'We were unable to process your transaction. Please verify your card details or try a different payment method.'
        ),
        'error' => array(
            'title'   => 'Payment Error',
            'message' => 'The payment could not be processed. Please try again or use another card.'
        ),
        'held' => array(
            'title'   => 'Payment Held for Review',
            'message' => 'Your payment was held for review and was not completed. Please contact support if you need assistance.'
        ),
        'no_response' => array(
            'title'   => 'Payment Processing Error',
            'message' => 'No response was received from the payment gateway. Please try again.'
        ),
        'api_exception' => array(
            'title'   => 'Payment Processing Error',
            'message' => 'A payment processing error occurred. Please try again.'
        ),
        'missing_transaction_id' => array(
            'title'   => 'Payment Verification Error',
            'message' => 'The payment could not be verified because the transaction ID was missing. Please try again or contact support.'
        ),
        'not_approved' => array(
            'title'   => 'Payment Not Approved',
            'message' => 'Your payment was not approved. Please try again or use another card.'
        ),
        'invalid_card' => array(
            'title'   => 'Invalid Card Number',
            'message' => 'The card number appears to be invalid. Please check your card details and try again.'
        ),
        'invalid_cvv' => array(
            'title'   => 'Invalid CVV',
            'message' => 'The CVV appears to be invalid. Please check your card security code and try again.'
        ),
        'invalid_expiry' => array(
            'title'   => 'Invalid Expiry Date',
            'message' => 'Please enter the card expiry date in MM/YY format.'
        ),
        'payment_disabled' => array(
            'title'   => 'Payment Unavailable',
            'message' => 'Payment processing is currently disabled. Please contact support.'
        ),
        'credentials_missing' => array(
            'title'   => 'Payment Configuration Error',
            'message' => 'Authorize.net payment credentials are not configured. Please contact support.'
        ),
        'sdk_missing' => array(
            'title'   => 'Payment Configuration Error',
            'message' => 'The payment gateway is temporarily unavailable. Please contact support.'
        ),
        'amount_missing' => array(
            'title'   => 'Payment Error',
            'message' => 'Unable to determine the payment amount. Please go back and select a plan before checking out.'
        ),
        'expired_card' => array(
            'title'   => 'Card Expired',
            'message' => 'This card has expired. Please use a different card.'
        ),
        'duplicate' => array(
            'title'   => 'Duplicate Transaction',
            'message' => 'A similar transaction was just submitted. Please wait a minute before trying again.'
        ),
        'avs_mismatch' => array(
            'title'   => 'Address Mismatch',
            'message' => 'The billing address does not match the card. Please check your billing address and try again.'
        ),
        'invalid_address' => array(
            'title'   => 'Missing Information',
            'message' => 'Please complete your shipping address (address, city, state and ZIP) and try again.'
        ),
        'invalid_coupon' => array(
            'title'   => 'Invalid Coupon',
            'message' => 'The coupon code is invalid or no longer available. Please remove it or enter a valid code.'
        ),
        'security' => array(
            'title'   => 'Session Expired',
            'message' => 'Your session has expired. Please refresh the page and try again.'
        ),
        'not_logged_in' => array(
            'title'   => 'Please Log In',
            'message' => 'You must be logged in to complete this payment. Please log in and try again.'
        ),
        'processing' => array(
            'title'   => 'Payment In Progress',
            'message' => 'Your payment is already being processed. Please wait a moment.'
        ),
    );

    if (!isset($messages[$payment_error])) {
        $messages[$payment_error] = $messages['not_approved'];
    }

    return $messages[$payment_error];
}

/* =========================================================
 * PLUGIN SETTINGS
 * ========================================================= */
define('CAP_SETTINGS_OPTION', 'custom_authorize_payment_settings');

function cap_get_settings()
{
    $defaults = array(
        'enabled'           => 1,
        'sandbox'           => 1,
        'api_login_id'      => '',
        'transaction_key'   => '',
        'public_client_key' => '',
        'debug_logging'     => 0,
    );

    $settings = get_option(CAP_SETTINGS_OPTION, array());
    return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
}

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

/* =========================================================
 * SHORTCODE: [custom_authorize_payment]
 * ========================================================= */
add_shortcode('custom_authorize_payment', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to continue.</p>';
    }

    $user_id = get_current_user_id();

    /* -------------------------------------------------
     * PLAN LABEL
     * ------------------------------------------------- */
    $plan = get_user_meta($user_id, 'payment_plan_', true);

    if (empty($plan) || !is_string($plan)) {
        $plan_key = get_user_meta($user_id, 'selected_plan', true);
        if ($plan_key === '') {
            $plan_key = get_user_meta($user_id, 'payment_plan', true);
        }
        $plan_key = sanitize_key((string) $plan_key);

        $plan_labels = cap_get_plan_labels();
        $plan = isset($plan_labels[$plan_key])
            ? $plan_labels[$plan_key]
            : 'Medical Cannabis Card';
    }

    /* -------------------------------------------------
     * PRICE — computed server-side from live POST selections
     * with fallback to saved meta. NEVER trust POST amount directly.
     * ------------------------------------------------- */
    $live_plan_key  = isset($_POST['live_plan_key'])  ? sanitize_key(wp_unslash($_POST['live_plan_key']))  : '';
    $live_year_plan = isset($_POST['live_year_plan']) ? sanitize_key(wp_unslash($_POST['live_year_plan'])) : '3_year';
    $live_hard_copy = isset($_POST['live_hard_copy']) ? sanitize_text_field(wp_unslash($_POST['live_hard_copy'])) : '';
    $live_id_card   = isset($_POST['live_id_card'])   ? sanitize_text_field(wp_unslash($_POST['live_id_card']))   : '';
    $live_legal     = isset($_POST['live_legal'])     ? sanitize_text_field(wp_unslash($_POST['live_legal']))     : '';
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $posted_legal = wp_unslash($_POST['ca_legal_support_letter_ui']);
        $posted_legal = is_array($posted_legal) ? $posted_legal : explode(',', (string) $posted_legal);
        $live_legal = implode(',', array_values(array_unique(array_filter(array_map('sanitize_key', $posted_legal)))));
    }

    $price = 0.0;

    if ($live_plan_key !== '') {
        $price = cap_compute_server_total($live_plan_key, $live_year_plan ?: '3_year', $live_hard_copy ?: 'no', $live_id_card ?: 'no', $live_legal);
    }

    if ($price <= 0) {
        $raw_price = get_user_meta($user_id, 'payment_amount', true);
        $raw_price = is_scalar($raw_price) ? trim((string) $raw_price) : '';
        if ($raw_price !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $raw_price) && (float) $raw_price > 0) {
            $price = (float) $raw_price;
        }
    }

    $coupon_code = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code']))) : '';
    $coupon_result = $coupon_code !== '' ? mcn_get_coupon_discount($coupon_code, $price) : array('valid'=>false,'discount'=>0,'message'=>'');
    $coupon_discount = !empty($coupon_result['valid']) ? (float) $coupon_result['discount'] : 0;
    $display_price = max(0, round($price - $coupon_discount, 2));
    $price_str = number_format($display_price, 2, '.', '');

    /* -------------------------------------------------
     * PERSIST LIVE SELECTIONS
     * ------------------------------------------------- */
    if ($live_plan_key) {
        update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
    }
    if ($live_year_plan) {
        update_user_meta($user_id, 'california_year_plan', $live_year_plan);
    }
    if ($price > 0) {
        // Keep the original subtotal in user meta. The coupon discount is applied separately
        // for checkout display and again server-side when processing the payment.
        update_user_meta($user_id, 'payment_amount', number_format($price, 2, '.', ''));
    }

    /* -------------------------------------------------
     * ADDRESS FIELDS
     * ------------------------------------------------- */
    $address_line_1 = get_user_meta($user_id, 'address_line_1', true);
    if ($address_line_1 === '') $address_line_1 = get_user_meta($user_id, 'address', true);
    if ($address_line_1 === '') $address_line_1 = get_user_meta($user_id, 'billing_address_line_1', true);

    $address_line_2 = get_user_meta($user_id, 'address_line_2', true);
    if ($address_line_2 === '') $address_line_2 = get_user_meta($user_id, 'billing_address_line_2', true);

    $city = get_user_meta($user_id, 'city', true);
    if ($city === '') $city = get_user_meta($user_id, 'billing_city', true);

    $country = get_user_meta($user_id, 'country', true);
    if ($country === '') $country = get_user_meta($user_id, 'billing_country', true);
    $country = $country ?: 'United States';

    $state = get_user_meta($user_id, 'state', true);
    if ($state === '') $state = get_user_meta($user_id, 'billing_state', true);

    $zip_code = get_user_meta($user_id, 'zip_code', true);
    if ($zip_code === '') $zip_code = get_user_meta($user_id, 'billing_zip', true);

    $same_as_shipping_meta = get_user_meta($user_id, 'billing_same_as_shipping', true);
    $same_as_shipping      = ($same_as_shipping_meta !== 'no'); // bool

    $billing_address_line_1 = get_user_meta($user_id, 'billing_address_line_1', true);
    $billing_address_line_2 = get_user_meta($user_id, 'billing_address_line_2', true);
    $billing_city           = get_user_meta($user_id, 'billing_city', true);
    $billing_state          = get_user_meta($user_id, 'billing_state', true);
    $billing_zip            = get_user_meta($user_id, 'billing_zip', true);
    
    
    
    // Fallback to Gravity Forms entry if user meta is empty
    if (empty($address_line_1) && class_exists('GFAPI')) {
        $entries = GFAPI::get_entries(2, array(
            'status' => 'active',
            'field_filters' => array(
                array('key' => 'created_by', 'value' => $user_id)
            )
        ), null, array('page_size' => 1));
        
        if (!is_wp_error($entries) && !empty($entries)) {
            $gf_entry = $entries[0];
            
            // Try single Address field pattern first (field_id.1 ... field_id.6)
            $address_field_id = 37; // Profile address field ID (input_37)
            $sub_1 = rgar($gf_entry, $address_field_id . '.1');
            if (!empty($sub_1)) {
                $address_line_1 = $sub_1;
                $address_line_2 = rgar($gf_entry, $address_field_id . '.2');
                $city           = rgar($gf_entry, $address_field_id . '.3');
                $state          = rgar($gf_entry, $address_field_id . '.4');
                $zip_code       = rgar($gf_entry, $address_field_id . '.5');
                $country        = rgar($gf_entry, $address_field_id . '.6');
            }
            
            // Fallback: separate field IDs
            if (empty($address_line_1)) {
                // Separate profile fields: address 37, line 2 38, city 39, state 40, ZIP 41, country 42.
                $address_line_1 = rgar($gf_entry, '37');
                $address_line_2 = rgar($gf_entry, '38');
                $city           = rgar($gf_entry, '39');
                $state          = rgar($gf_entry, '40');
                $zip_code       = rgar($gf_entry, '41');
                $country        = rgar($gf_entry, '42');
            }
            
            // Save to user meta for future
            if (!empty($address_line_1)) update_user_meta($user_id, 'address_line_1', $address_line_1);
            if (!empty($address_line_2)) update_user_meta($user_id, 'address_line_2', $address_line_2);
            if (!empty($city))           update_user_meta($user_id, 'city', $city);
            if (!empty($state))          update_user_meta($user_id, 'state', $state);
            if (!empty($zip_code))       update_user_meta($user_id, 'zip_code', $zip_code);
            if (!empty($country))        update_user_meta($user_id, 'country', $country);
        }
    }
    
    
    

    ob_start();

    /* -------------------------------------------------
     * ERROR DISPLAY (from payment handler)
     * ------------------------------------------------- */
    $payment_error = !empty($GLOBALS['cap_payment_error'])
        ? sanitize_key($GLOBALS['cap_payment_error'])
        : '';

    $payment_error_details = $payment_error
        ? cap_get_payment_error_details($payment_error)
        : array();
    ?>

    <?php if (true) : ?>
        <!-- <style id="custom-authorize-payment-error-style">
            .custom-authorize-payment-error {
                position: relative;
                margin: 0 auto 24px;
                padding: 16px 20px;
                max-width: 1100px;
                border: 1px solid #d63638;
                border-radius: 10px;
                background: #fff5f5;
                color: #8a1f1f;
                font-size: 15px;
                line-height: 1.5;
                box-sizing: border-box;
                box-shadow: 0 5px 18px rgba(214, 54, 56, 0.08);
            }
            .custom-authorize-payment-error strong {
                display: block;
                margin-bottom: 4px;
                font-size: 18px;
            }
            .custom-authorize-payment-error-close {
                float: right;
                border: 0;
                background: transparent;
                color: inherit;
                font-size: 22px;
                line-height: 1;
                cursor: pointer;
                padding: 0 4px;
            }
            .custom-authorize-payment-error-modal {
                position: fixed;
                inset: 0;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: rgba(0, 0, 0, 0.55);
                box-sizing: border-box;
            }
            .custom-authorize-payment-error-modal-box {
                width: 100%;
                max-width: 520px;
                background: #fff;
                border-radius: 10px;
                padding: 25px;
                box-sizing: border-box;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0, 0, 0, 0.25);
            }
            .custom-authorize-payment-error-icon {
                width: 72px;
                height: 72px;
                margin: 0 auto 16px;
                border-radius: 50%;
                background: #e53935;
                position: relative;
                box-sizing: border-box;
            }
            .custom-authorize-payment-error-icon::before,
            .custom-authorize-payment-error-icon::after {
                content: '';
                position: absolute;
                width: 38px;
                height: 4px;
                background: #fff;
                border-radius: 3px;
                left: 17px;
                top: 34px;
            }
            .custom-authorize-payment-error-icon::before { transform: rotate(45deg); }
            .custom-authorize-payment-error-icon::after  { transform: rotate(-45deg); }
            .custom-authorize-payment-error-modal-box h3 {
                margin: 0 0 10px;
                font-size: 22px;
            }
            .custom-authorize-payment-error-modal-box p {
                margin: 0 0 20px;
                font-size: 16px;
                line-height: 1.5;
            }
            .custom-authorize-payment-error-modal-close {
                display: inline-block;
                border: 0;
                border-radius: 6px;
                padding: 10px 22px;
                cursor: pointer;
                font-size: 15px;
                background: #20a7a7;
                color: #fff;
            }
        </style> -->

        <style id="cap-payment-modal-style">
            .custom-authorize-payment-error-modal {
                position: fixed; inset: 0; z-index: 999999; display: none;
                align-items: center; justify-content: center; padding: 20px;
                background: rgba(0,0,0,.55); box-sizing: border-box;
            }
            .custom-authorize-payment-error-modal-box {
                width: 100%; max-width: 520px; background: #fff; border-radius: 10px;
                padding: 25px; box-sizing: border-box; text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.25);
            }
            .custom-authorize-payment-error-icon {
                width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 50%;
                background: #e53935; position: relative; box-sizing: border-box;
            }
            .custom-authorize-payment-error-icon::before,
            .custom-authorize-payment-error-icon::after {
                content: ''; position: absolute; width: 38px; height: 4px;
                background: #fff; border-radius: 3px; left: 17px; top: 34px;
            }
            .custom-authorize-payment-error-icon::before { transform: rotate(45deg); }
            .custom-authorize-payment-error-icon::after  { transform: rotate(-45deg); }
            .custom-authorize-payment-error-modal-box h3 { margin: 0 0 10px; font-size: 22px; }
            .custom-authorize-payment-error-modal-box p  { margin: 0 0 20px; font-size: 16px; line-height: 1.5; }
            .custom-authorize-payment-error-modal-close {
                display: inline-block; border: 0; border-radius: 6px; padding: 10px 22px;
                cursor: pointer; font-size: 15px; background: #20a7a7; color: #fff;
            }
            .auth-payment-form input.cap-field-error {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 3px rgba(214,54,56,.12);
            }
            .place-order-btn[disabled] { opacity: .7; cursor: not-allowed; }
        </style>

        <div id="cap-error-modal" class="custom-authorize-payment-error-modal" role="dialog" aria-modal="true" aria-labelledby="cap-error-modal-title">
        <div class="custom-authorize-payment-error-modal-box">
            <div class="custom-authorize-payment-error-icon" aria-hidden="true"></div>
            <h3 id="cap-error-modal-title"></h3>
            <p id="cap-error-modal-message"></p>
            <button type="button" id="cap-error-modal-close" class="custom-authorize-payment-error-modal-close">OK</button>
        </div>
    </div>

    <?php if (!empty($payment_error_details)) : ?>
        <script>
            window.capServerPaymentError = <?php echo wp_json_encode(array(
                'code'    => $payment_error,
                'title'   => $payment_error_details['title'],
                'message' => $payment_error_details['message'],
            )); ?>;
        </script>
    <?php endif; ?>

        <script>
            (function () {
                var modal = document.getElementById('cap-error-modal');
                var title = document.getElementById('cap-error-modal-title');
                var message = document.getElementById('cap-error-modal-message');
                if (!modal || !window.capServerPaymentError) return;
                title.textContent = window.capServerPaymentError.title || 'Payment Error';
                message.textContent = window.capServerPaymentError.message || 'The payment could not be processed. Please try again.';
                modal.style.display = 'flex';
            })();
        </script>
    <?php endif; ?>

    <div class="auth-payment-form" autocomplete="on" id="medcards-payment-form" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <?php wp_nonce_field('custom_authorize_payment_action', 'custom_authorize_payment_nonce'); ?>

        <!-- Hidden fields synced live from Form 2 selections -->
        <input type="hidden" name="live_plan_key"  id="cap_live_plan_key"  value="<?php echo esc_attr($live_plan_key); ?>">
        <input type="hidden" name="live_year_plan" id="cap_live_year_plan" value="<?php echo esc_attr($live_year_plan ?: '3_year'); ?>">
        <input type="hidden" name="live_hard_copy" id="cap_live_hard_copy" value="<?php echo esc_attr($live_hard_copy ?: 'no'); ?>">
        <input type="hidden" name="live_id_card"   id="cap_live_id_card"   value="<?php echo esc_attr($live_id_card ?: 'no'); ?>">
        <input type="hidden" name="live_legal"     id="cap_live_legal"     value="<?php echo esc_attr($live_legal); ?>">
        <input type="hidden" name="live_amount"    id="cap_live_amount"    value="<?php echo esc_attr($price_str); ?>">
        <input type="hidden" id="cap_coupon_discount" value="<?php echo esc_attr(number_format($coupon_discount, 2, '.', '')); ?>">

        <div class="checkout-wrap">
            <div class="checkout-left">
                <div class="checkout-box">
                    <h3>Shipping Address</h3>

                    <div class="field-grid two-col">
                        <input type="text" name="address_line_1" placeholder="Address" value="<?php echo esc_attr($address_line_1); ?>" required>
                        <input type="text" name="address_line_2" placeholder="Apartment, suite, etc." value="<?php echo esc_attr($address_line_2); ?>">
                    </div>

                    <div class="field-grid three-col">
                        <input type="text" name="city" placeholder="City" value="<?php echo esc_attr($city); ?>" required>
                        <input type="text" name="state" placeholder="State" value="<?php echo esc_attr($state); ?>" required>
                        <input type="text" name="zip_code" placeholder="ZIP" value="<?php echo esc_attr($zip_code); ?>" required>
                    </div>

                    <div class="field-grid one-col">
                        <input type="text" name="country" value="United States" readonly required>
                    </div>

                    <label class="billing-check">
                        <input type="checkbox" id="same_as_shipping" name="same_as_shipping" value="yes" <?php checked($same_as_shipping, true); ?>>
                        Billing address same as shipping
                    </label>

                    <div class="billing-fields" style="<?php echo $same_as_shipping ? 'display:none;' : 'display:block;'; ?>">
                        <div class="field-grid two-col">
                            <input type="text" name="billing_address_line_1" placeholder="Billing Address" value="<?php echo esc_attr($billing_address_line_1); ?>">
                            <input type="text" name="billing_address_line_2" placeholder="Apartment, suite, etc." value="<?php echo esc_attr($billing_address_line_2); ?>">
                        </div>

                        <div class="field-grid three-col">
                            <input type="text" name="billing_city" placeholder="Billing City" value="<?php echo esc_attr($billing_city); ?>">
                            <input type="text" name="billing_state" placeholder="Billing State" value="<?php echo esc_attr($billing_state); ?>">
                            <input type="text" name="billing_zip" placeholder="Billing ZIP" value="<?php echo esc_attr($billing_zip); ?>">
                        </div>

                        <div class="field-grid one-col">
                            <input type="text" name="billing_country" value="United States" readonly>
                        </div>
                    </div>
                </div>

                <div class="checkout-box">
                    <h3>Payment Information</h3>

                    <div class="accepted-cards">
                        <img src="https://img.icons8.com/color/96/visa.png" alt="Visa">
                        <img src="https://img.icons8.com/color/96/mastercard-logo.png" alt="Mastercard">
                        <img src="https://img.icons8.com/color/96/amex.png" alt="Amex">
                        <img src="https://img.icons8.com/color/96/discover.png" alt="Discover">
                    </div>

                    <div class="field-grid one-col">
                        <input type="text" name="card_number" placeholder="Card Number" inputmode="numeric" autocomplete="cc-number" maxlength="19" pattern="[0-9 ]{13,19}" aria-label="Card Number" required>
                    </div>

                    <div class="field-grid two-col">
                        <input type="text" name="expiry" placeholder="MM/YY" inputmode="numeric" autocomplete="cc-exp" maxlength="5" aria-label="Card expiry month and year" required>
                        <input type="password" name="cvv" placeholder="CVV" inputmode="numeric" autocomplete="cc-csc" maxlength="4" pattern="[0-9]{3,4}" aria-label="Card security code" required>
                    </div>
                </div>

                <div class="secure-note">
                    Your payment is encrypted and securely processed.
                </div>
            </div>

            <div class="checkout-right">
                <div class="summary-card">
                    <h4>Order Summary</h4>
                    <div class="mcn-coupon-checkout" style="padding:12px 0;border-bottom:1px solid #edf0f2;margin-bottom:8px;">
                        <label for="mcn_coupon_code" style="display:block;font-weight:600;margin-bottom:7px;">Coupon Code</label>
                        <div style="display:flex;gap:8px;">
                            <input id="mcn_coupon_code" name="coupon_code" type="text" value="<?php echo esc_attr($coupon_code); ?>" placeholder="Enter coupon code" style="min-width:0;flex:1;text-transform:uppercase;">
                            <button type="button" id="mcn_apply_coupon_btn" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mcn_apply_checkout_coupon')); ?>" class="button" style="border:1px solid #20a7a7;border-radius:7px;padding:0 12px;cursor:pointer;">Apply</button>
                        </div>
                        <p id="mcn_coupon_message" aria-live="polite" style="margin:8px 0 0;color:<?php echo !empty($coupon_result['valid']) ? '#16803c' : '#b32d2e'; ?>;font-size:13px;" <?php echo $coupon_code === '' ? 'hidden' : ''; ?>><?php echo esc_html($coupon_result['message']); ?></p>
                    </div>

                    <div class="summary-row">
                        <span>Package</span>
                        <strong id="cap_summary_plan"><?php echo esc_html($plan); ?></strong>
                    </div>

                    <div class="summary-row" id="cap_summary_addons_row" style="display:none;">
                        <span>Add-ons</span>
                        <strong id="cap_summary_addons">—</strong>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong id="cap_summary_subtotal">$<?php echo esc_html(number_format($price, 2)); ?></strong>
                    </div>

                    <div class="summary-row" id="mcn_coupon_discount_row" <?php echo $coupon_discount <= 0 ? 'style="display:none;"' : ''; ?>><span>Coupon (<span id="mcn_coupon_applied_code"><?php echo esc_html($coupon_code); ?></span>)</span><strong id="mcn_coupon_discount_amount" style="color:#16803c;">−$<?php echo esc_html(number_format($coupon_discount, 2)); ?></strong></div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <strong>Included</strong>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <strong id="cap_summary_total">$<?php echo esc_html(number_format($display_price, 2)); ?></strong>
                    </div>
                </div>

                <div class="benefits-card">
                    <h4>Order Benefits</h4>
                    <div class="benefit-item"><span>✓</span><p><b>Fast approval process</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Secure checkout</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Licensed physician review</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Money-back guarantee if you are not approved</b></p></div>
                </div>

                <button type="button" name="process_payment" value="1" class="place-order-btn">
                    Submit Application
                </button>
            </div>
        </div>
    </div>

    <style>
        .auth-payment-form {
            max-width: 1100px;
            margin: 35px auto;
            font-family: inherit;
        }
        .checkout-wrap {
            display: grid;
            grid-template-columns: 2fr 0.9fr;
            gap: 26px;
            align-items: start;
        }
        .checkout-box,
        .summary-card,
        .benefits-card {
            background: #fff;
            border: 1px solid #e4e8ec;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
            box-sizing: border-box;
        }
        .checkout-box { margin-bottom: 18px; }
        .checkout-box h3,
        .summary-card h4,
        .benefits-card h4 {
            margin: 0 0 16px;
            font-size: 18px;
            font-weight: 700;
            color: #1d1d1f;
        }
        .field-grid {
            display: grid;
            gap: 12px;
            margin-bottom: 12px;
        }
        .field-grid.one-col   { grid-template-columns: 1fr; }
        .field-grid.two-col   { grid-template-columns: repeat(2, 1fr); }
        .field-grid.three-col { grid-template-columns: 1.2fr 0.8fr 0.8fr; }

        .auth-payment-form input[type="text"] {
            width: 100%;
            height: 44px;
            padding: 10px 13px;
            border: 1px solid #d9dee3;
            border-radius: 8px;
            font-size: 14px;
            color: #111;
            box-sizing: border-box;
            background: #fff;
        }
        .auth-payment-form input:focus {
            border-color: #23b6b6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(35, 182, 182, 0.12);
        }
        .billing-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #333;
            margin-top: 4px;
        }
        .billing-check input { accent-color: #20b6b6; }
        .billing-fields {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e8ecef;
        }
        .accepted-cards {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .accepted-cards img {
            width: 78px;
            height: 52px;
            object-fit: contain;
            background: #fff;
            border: 1px solid #d9eeee;
            border-radius: 10px;
            padding: 8px 12px;
            box-sizing: border-box;
            transition: all 0.25s ease;
        }
        .accepted-cards img:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
            border-color: #2bb3b3;
        }
        .secure-note {
            background: #f6fbfb;
            border: 1px solid #d8eeee;
            color: #286b6b;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            margin-top: 12px;
        }
        .summary-card { margin-bottom: 16px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #edf0f2;
            font-size: 14px;
            color: #505050;
        }
        .summary-row strong {
            text-align: right;
            color: #111;
            font-weight: 700;
            max-width: 200px;
            word-break: break-word;
        }
        .summary-row.total {
            border-bottom: none;
            padding-top: 16px;
            font-size: 16px;
            color: #111;
            font-weight: 700;
        }
        .summary-row.total strong {
            font-size: 18px;
            color: #20a7a7;
        }
        .benefits-card {
            margin-bottom: 16px;
            background: #f9ffff;
        }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }
        .benefit-item:last-child { margin-bottom: 0; }
        .benefit-item span {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #20b6b6;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex: 0 0 20px;
            margin-top: 1px;
        }
        .benefit-item p {
            margin: 0;
            font-size: 13px;
            line-height: 1.4;
            color: #333;
        }
        .place-order-btn {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 9px;
            background: #20a7a7;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s ease;
        }
        .place-order-btn:hover { background: #168b8b; }

        @media (max-width: 900px) {
            .checkout-wrap { grid-template-columns: 1fr; }
            .field-grid.two-col,
            .field-grid.three-col { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .accepted-cards { gap: 10px; }
            .accepted-cards img { width: 64px; height: 44px; padding: 6px 8px; }
        }
    </style>

    <script>
    (function () {
        'use strict';

        var CA_PRICING = {
            new_pdf_id_card:   64.99,
            caregiver_new:     99.99,
            growers_license:  189.99,
            new_pdf:           37.00,
            renewal_hard_copy: 47.00
        };

        var PLAN_LABELS = {
            new_pdf_id_card:   'Instant PDF + Patient ID Card',
            caregiver_new:     'Caregiver + New Patient',
            growers_license:   'License to Cultivate up to 99 Plants',
            new_pdf:           'New Patient — PDF Only',
            renewal_hard_copy: 'Existing Patient Renewal — With Hard Copy'
        };

        var LEGAL_PRICE = 79;

        var YEAR_PLANS = {
            '1_year': { years: 1, discount: 0  },
            '2_year': { years: 2, discount: 8  },
            '3_year': { years: 3, discount: 16 }
        };

        function readRadioOrText(name) {
            var el = document.querySelector('input[name="' + name + '"]:checked');
            if (el) return el.value;
            el = document.querySelector('input[name="' + name + '"]');
            return el ? el.value : '';
        }

        function readLegalLetters() {
            var allowed = [
                'parole',
                'employer',
                'driving',
                'possession_person'
            ];
        
            var selected = [];
        
            // Read checked legal-letter checkboxes.
            document.querySelectorAll(
                'input[name="ca_legal_support_letter_ui"]:checked'
            ).forEach(function (input) {
                if (allowed.includes(input.value)) {
                    selected.push(input.value);
                }
            });
        
            // Use the hidden field only if no checkbox is selected.
            if (selected.length === 0) {
                var hidden = document.querySelector(
                    'input[name="input_58"]'
                );
        
                if (hidden && hidden.value && hidden.value !== 'none') {
                    selected = hidden.value.split(',').filter(function (value) {
                        return allowed.includes(value.trim());
                    });
                }
            }
        
            return Array.from(new Set(selected));
        }

        // Keep legal-letter card styling and checked values synchronized.
        document.addEventListener('change', function (event) {
            if (!event.target.matches('input[name="ca_legal_support_letter_ui"]')) return;
            var card = event.target.closest('.ca-legal-card, .ca-legal-letter-card');
            if (card) card.classList.toggle('is-selected', event.target.checked);
            updateSummary();
        });

        function readYearPlan() {
            // 1. Hidden field on page
            var hidden = document.querySelector('input[name="input_63"]');
            if (hidden && hidden.value) return hidden.value;
        
            // 2. Selected card on page
            var sel = document.querySelector('.ca-years-card.is-selected');
            if (sel) return sel.getAttribute('data-value');
        
            // 3. sessionStorage
            try {
                var stored = sessionStorage.getItem('medcards_selected_year_plan');
                if (stored) return stored;
            } catch (e) {}
        
            return '3_year';
        }
        
        function readHardCopy() {
            var el = document.querySelector('input[name="input_54"]:checked');
            if (el) return el.value;
        
            try {
                var stored = sessionStorage.getItem('medcards_selected_hard_copy');
                if (stored) return stored;
            } catch (e) {}
        
            return 'no';
        }
        
        function readIdCard() {
            var el = document.querySelector('input[name="input_61"]');
            if (el && el.value) return el.value;
        
            try {
                var stored = sessionStorage.getItem('medcards_selected_id_card');
                if (stored) return stored;
            } catch (e) {}
        
            return 'no';
        }
        

        function computeTotal() {
            var planKey = readRadioOrText('input_11');

            // No Gravity Form on page -> do not touch the amount fields.
            if (!planKey || !CA_PRICING[planKey]) {
                return null;
            }

            var base    = CA_PRICING[planKey];
            var yearKey = readYearPlan();
            var yearCfg = YEAR_PLANS[yearKey] || YEAR_PLANS['1_year'];

            var original       = base * yearCfg.years;
            var discountAmount = original * (yearCfg.discount / 100);
            var adjustedBase   = original - discountAmount;

            var total  = adjustedBase;
            var addons = [];

            addons.push(
                yearCfg.years + ' Year' + (yearCfg.years > 1 ? 's' : '') +
                (yearCfg.discount > 0 ? ' (Save ' + yearCfg.discount + '%)' : '')
            );

            var hardCopy = readRadioOrText('input_54') || 'no';
            if (planKey !== 'renewal_hard_copy' && hardCopy === 'yes') {
                total += 10;
                addons.push('Hard Copy +$10.00');
            }

            var legal = readLegalLetters();

            if (legal.length > 0) {
                var legalTotal = legal.length * LEGAL_PRICE;
                total += legalTotal;
            
                addons.push(
                    'Legal Letters (' + legal.length + ') +$' +
                    legalTotal.toFixed(2)
                );
            }

            var idCard = readRadioOrText('input_61') || 'no';
            if (planKey === 'new_pdf_id_card') {
                addons.push('ID Card Included');
            } else if (idCard === 'yes') {
                total += 16;
                addons.push('ID Card +$16.00');
            }

            return {
                total: total,
                planKey: planKey,
                planLabel: PLAN_LABELS[planKey] || planKey,
                addons: addons,
                yearKey: yearKey,
                hardCopy: hardCopy,
                idCard: idCard,
                legal: legal
            };
        }

        // Apply coupon immediately via AJAX; do not submit the payment form or validate card fields.
        (function setupInstantCouponApply() {
            var button = document.getElementById('mcn_apply_coupon_btn');
            var input = document.getElementById('mcn_coupon_code');
            var message = document.getElementById('mcn_coupon_message');
            if (!button || !input) return;

            button.addEventListener('click', function () {
                if (typeof window.medcardsUpdatePaymentSummary === 'function') {
                    window.medcardsUpdatePaymentSummary();
                }
                var data = new URLSearchParams();
                data.append('action', 'mcn_apply_checkout_coupon');
                data.append('nonce', button.getAttribute('data-nonce') || '');
                data.append('coupon_code', input.value.trim().toUpperCase());
                data.append('plan_key', (document.getElementById('cap_live_plan_key') || {}).value || '');
                data.append('year_plan', (document.getElementById('cap_live_year_plan') || {}).value || '3_year');
                data.append('hard_copy', (document.getElementById('cap_live_hard_copy') || {}).value || 'no');
                data.append('id_card', (document.getElementById('cap_live_id_card') || {}).value || 'no');
                data.append('legal', (document.getElementById('cap_live_legal') || {}).value || '');

                button.disabled = true;
                button.textContent = 'Checking…';
                fetch(button.getAttribute('data-ajax-url'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: data.toString()
                }).then(function (response) { return response.json(); })
                  .then(function (response) {
                    var discountEl = document.getElementById('cap_coupon_discount');
                    var row = document.getElementById('mcn_coupon_discount_row');
                    var discountAmount = document.getElementById('mcn_coupon_discount_amount');
                    var appliedCode = document.getElementById('mcn_coupon_applied_code');
                    var totalEl = document.getElementById('cap_summary_total');
                    var subtotalEl = document.getElementById('cap_summary_subtotal');
                    var amountEl = document.getElementById('cap_live_amount');

                    if (response && response.success && response.data) {
                        var discount = parseFloat(response.data.discount) || 0;
                        var total = parseFloat(response.data.total) || 0;
                        if (discountEl) discountEl.value = discount.toFixed(2);
                        if (row) row.style.display = discount > 0 ? '' : 'none';
                        if (discountAmount) discountAmount.textContent = '−$' + discount.toFixed(2);
                        if (appliedCode) appliedCode.textContent = response.data.code;
                        if (subtotalEl) subtotalEl.textContent = '$' + (parseFloat(response.data.subtotal) || 0).toFixed(2);
                        if (totalEl) totalEl.textContent = '$' + total.toFixed(2);
                        if (amountEl) amountEl.value = total.toFixed(2);
                        input.value = response.data.code;
                        if (message) { message.hidden = false; message.style.color = '#16803c'; message.textContent = response.data.message; }
                    } else {
                        if (discountEl) discountEl.value = '0.00';
                        if (row) row.style.display = 'none';
                        if (message) { message.hidden = false; message.style.color = '#b32d2e'; message.textContent = (response && response.data && response.data.message) || 'Could not apply this coupon. Please try again.'; }
                        if (typeof window.medcardsUpdatePaymentSummary === 'function') window.medcardsUpdatePaymentSummary();
                    }
                  }).catch(function () {
                    if (message) { message.hidden = false; message.style.color = '#b32d2e'; message.textContent = 'Could not check coupon. Please try again.'; }
                  }).finally(function () {
                    button.disabled = false;
                    button.textContent = 'Apply';
                  });
            });
        })();

        function updateSummary() {
            var data = computeTotal();

            // Gravity Form not present -> leave hidden fields and summary as-is.
            if (!data) return;

            var planEl     = document.getElementById('cap_summary_plan');
            var addonsEl   = document.getElementById('cap_summary_addons');
            var addonsRow  = document.getElementById('cap_summary_addons_row');
            var subtotalEl = document.getElementById('cap_summary_subtotal');
            var totalEl    = document.getElementById('cap_summary_total');

            if (planEl) planEl.textContent = data.planLabel || 'Medical Cannabis Card';

            if (data.addons && data.addons.length) {
                if (addonsRow) addonsRow.style.display = '';
                if (addonsEl)  addonsEl.textContent = data.addons.join(' · ');
            } else {
                if (addonsRow) addonsRow.style.display = 'none';
            }

            // Keep subtotal at the regular price, but preserve the server-validated
            // coupon discount in the displayed total and hidden checkout amount.
            var couponDiscountEl = document.getElementById('cap_coupon_discount');
            var couponDiscount = couponDiscountEl ? (parseFloat(couponDiscountEl.value) || 0) : 0;
            var baseTotal = Number(data.total || 0);
            var payableTotal = Math.max(0, Math.round((baseTotal - couponDiscount) * 100) / 100);
            var subtotalText = '$' + baseTotal.toFixed(2);
            var totalText = '$' + payableTotal.toFixed(2);
            if (subtotalEl) subtotalEl.textContent = subtotalText;
            if (totalEl)    totalEl.textContent = totalText;

            var f1 = document.getElementById('cap_live_plan_key');  if (f1) f1.value = data.planKey || '';
            var f2 = document.getElementById('cap_live_year_plan'); if (f2) f2.value = data.yearKey || '';
            var f3 = document.getElementById('cap_live_hard_copy'); if (f3) f3.value = data.hardCopy || '';
            var f4 = document.getElementById('cap_live_id_card');   if (f4) f4.value = data.idCard || '';
            var f5 = document.getElementById('cap_live_legal');     if (f5) f5.value = (data.legal || []).join(',');
            var f6 = document.getElementById('cap_live_amount');    if (f6) f6.value = payableTotal.toFixed(2);
        }

        document.addEventListener('change', updateSummary, true);
        document.addEventListener('input',  updateSummary, true);

        document.addEventListener('click', function (e) {
            if (
                e.target.closest('.ca-legal-card') ||
                e.target.closest('.ca-years-card') ||
                e.target.closest('.ca-id-add') ||
                e.target.closest('.ca-id-no') ||
                e.target.closest('.ca-legal-clear')
            ) {
                setTimeout(updateSummary, 50);
                setTimeout(updateSummary, 250);
            }
        }, true);

        document.addEventListener('gform_post_render', function () {
            setTimeout(updateSummary, 100);
            setTimeout(updateSummary, 400);
        });

        function boot() {
            setTimeout(updateSummary, 200);
            setTimeout(updateSummary, 800);
            setTimeout(updateSummary, 1500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }

        var payForm = document.getElementById('medcards-payment-form');
        if (payForm) {
            payForm.addEventListener('submit', function () {
                updateSummary();
            }, true);
        }

        window.medcardsUpdatePaymentSummary = updateSummary;

        document.addEventListener('DOMContentLoaded', function () {
            var checkbox = document.getElementById('same_as_shipping');
            var billingFields = document.querySelector('.billing-fields');

            if (checkbox && billingFields) {
                function toggleBillingFields() {
                    billingFields.style.display = checkbox.checked ? 'none' : 'block';
                }
                checkbox.addEventListener('change', toggleBillingFields);
                toggleBillingFields();
            }

            // var cardInput = document.querySelector('input[name="card_number"]');
            // if (cardInput) {
            //     cardInput.addEventListener('input', function (e) {
            //         var value = e.target.value.replace(/\D/g, '').substring(0, 16);
            //         e.target.value = value.replace(/(.{4})/g, '$1 ').trim();
            //     });
            // }

            // var expiryInput = document.querySelector('input[name="expiry"]');
            // if (expiryInput) {
            //     expiryInput.addEventListener('input', function (e) {
            //         var value = e.target.value.replace(/\D/g, '').substring(0, 4);
            //         var month = value.substring(0, 2);

            //         if (month.length === 2) {
            //             var monthNum = parseInt(month, 10);
            //             if (monthNum > 12) month = '12';
            //             if (monthNum === 0) month = '01';
            //         }

            //         if (value.length > 2) {
            //             value = month + '/' + value.substring(2);
            //         } else {
            //             value = month;
            //         }
            //         e.target.value = value;
            //     });
            // }

            // var cvvInput = document.querySelector('input[name="cvv"]');
            // if (cvvInput) {
            //     cvvInput.addEventListener('input', function (e) {
            //         e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            //     });
            // }
        });

    })();
    </script>

    <script>
        (function () {
            'use strict';

            var form = document.getElementById('medcards-payment-form');
            if (!form) return;

            // This checkout can live inside Gravity Forms, so it must be a DIV, not a nested FORM.
            function getField(name) { return form.querySelector('[name="' + name + '"]'); }
            var cardEl  = getField('card_number');
            var expEl   = getField('expiry');
            var cvvEl   = getField('cvv');
            var btn     = form.querySelector('.place-order-btn');
            var modal   = document.getElementById('cap-error-modal');
            var titleEl = document.getElementById('cap-error-modal-title');
            var msgEl   = document.getElementById('cap-error-modal-message');
            var closeBt = document.getElementById('cap-error-modal-close');
            var btnLabel = btn ? btn.textContent.trim() : 'Submit Application';
            var busy = false;
            var focusField = null;

            // Move modal to <body> so theme containers can't clip it.
            if (modal && modal.parentNode !== document.body) document.body.appendChild(modal);

            var MSG = {
                invalid_card:   ['Invalid Card Number', 'The card number appears to be invalid. Please check your card details and try again.'],
                invalid_expiry: ['Invalid Expiry Date', 'Please enter the card expiry date in MM/YY format.'],
                expired_card:   ['Card Expired', 'This card has expired. Please use a different card.'],
                invalid_cvv:    ['Invalid CVV', 'The CVV appears to be invalid. Please check your card security code and try again.']
            };

            var FIELD_FOR_CODE = {
                invalid_card: cardEl, declined: cardEl, invalid_expiry: expEl,
                expired_card: expEl, invalid_cvv: cvvEl,
                avs_mismatch: getField('address_line_1'),
                invalid_address: getField('address_line_1')
            };

            /* ---------- popup ---------- */
            function showError(title, message, field) {
                title = title || 'Payment Error';
                message = message || 'The payment could not be processed. Please check your details and try again.';
                if (field && field.classList) field.classList.add('cap-field-error');
                focusField = field || null;
                // Modal can be moved/inserted by the theme; resolve it again if needed.
                if (!modal) modal = document.getElementById('cap-error-modal');
                if (!titleEl) titleEl = document.getElementById('cap-error-modal-title');
                if (!msgEl) msgEl = document.getElementById('cap-error-modal-message');
                if (!modal || !titleEl || !msgEl) { window.alert(title + '\n\n' + message); return; }
                titleEl.textContent = title;
                msgEl.textContent = message;
                modal.setAttribute('aria-hidden', 'false');
                modal.style.setProperty('display', 'flex', 'important');
                if (closeBt) closeBt.focus();
            }
            function hideError() {
                if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
                if (focusField) { try { focusField.focus(); } catch (e) {} }
            }
            if (closeBt) closeBt.addEventListener('click', hideError);
            if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) hideError(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.style.display === 'flex') hideError();
            });
            window.capShowPaymentError = showError;

            /* ---------- helpers ---------- */
            function digits(v) { return String(v || '').replace(/\D/g, ''); }
            function isAmex(num) { return /^3[47]/.test(num); }

            function luhn(num) {
                var sum = 0, alt = false;
                for (var i = num.length - 1; i >= 0; i--) {
                    var n = parseInt(num.charAt(i), 10);
                    if (alt) { n *= 2; if (n > 9) n -= 9; }
                    sum += n; alt = !alt;
                }
                return sum % 10 === 0;
            }

            function formatCard(raw) {
                var d = digits(raw);
                if (isAmex(d)) {
                    d = d.substr(0, 15);
                    return [d.substr(0, 4), d.substr(4, 6), d.substr(10, 5)].filter(Boolean).join(' ');
                }
                // Visa test/card numbers beginning with 4 are limited to 16 digits here.
                // Other standard non-Amex cards are also capped at 16 digits for this form.
                d = d.substr(0, 16);
                return d.replace(/(.{4})/g, '$1 ').trim();
            }

            function formatExpiry(raw) {
                var d = digits(raw);
                // Accept MMYY, MMYYYY, or partially typed values; display MM/YY.
                if (d.length > 4) d = d.substr(0, 2) + d.substr(-2);
                d = d.substr(0, 4);
                if (d.length === 1 && d > '1') d = '0' + d;
                if (d.length >= 2) {
                    var m = parseInt(d.substr(0, 2), 10);
                    if (m === 0) d = '01' + d.substr(2);
                    if (m > 12) d = '12' + d.substr(2);
                }
                return d.length > 2 ? d.substr(0, 2) + '/' + d.substr(2) : d;
            }

            function clearErr(el) { if (el) el.classList.remove('cap-field-error'); }

            /* ---------- live formatting ---------- */
            ['input', 'change'].forEach(function (evt) {
                if (cardEl) cardEl.addEventListener(evt, function () { cardEl.value = formatCard(cardEl.value); clearErr(cardEl); });
                if (expEl)  expEl.addEventListener(evt,  function () { expEl.value  = formatExpiry(expEl.value); clearErr(expEl); });
                if (cvvEl)  cvvEl.addEventListener(evt,  function () { cvvEl.value  = digits(cvvEl.value).substr(0, 4); clearErr(cvvEl); });
            });

            /* ---------- sync current Gravity Forms address values ---------- */
            function firstValue(selectors) {
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (el && typeof el.value === 'string' && el.value.trim() !== '') {
                        return el.value.trim();
                    }
                }
                return '';
            }

            function syncAddressFromProfile() {
                var map = [
                    {
                        target: 'address_line_1',
                        selectors: [
                            'input[name="input_37.1"]', '#input_2_37_1', 'input[name="input_2_37_1"]',
                            '#input_2_37', 'input[name="input_37"]', 'input[name="input_2_37"]'
                        ]
                    },
                    {
                        target: 'address_line_2',
                        selectors: [
                            'input[name="input_37.2"]', '#input_2_37_2', 'input[name="input_2_37_2"]',
                            'input[name="input_38"]', 'input[name="input_2_38"]', '#input_2_38'
                        ]
                    },
                    {
                        target: 'city',
                        selectors: [
                            'input[name="input_37.3"]', '#input_2_37_3', 'input[name="input_2_37_3"]',
                            'input[name="input_39"]', 'input[name="input_2_39"]', '#input_2_39'
                        ]
                    },
                    {
                        target: 'state',
                        selectors: [
                            'input[name="input_37.4"]', '#input_2_37_4', 'input[name="input_2_37_4"]',
                            'input[name="input_40"]', 'input[name="input_2_40"]', '#input_2_40',
                            'select[name="input_40"]', 'select[name="input_2_40"]'
                        ]
                    },
                    {
                        target: 'zip_code',
                        selectors: [
                            'input[name="input_37.5"]', '#input_2_37_5', 'input[name="input_2_37_5"]',
                            'input[name="input_41"]', 'input[name="input_2_41"]', '#input_2_41'
                        ]
                    }
                ];

                map.forEach(function (item) {
                    var target = getField(item.target);
                    var value = firstValue(item.selectors);
                    if (target && value) target.value = value;
                });

                var same = getField('same_as_shipping');
                if (same && same.checked) {
                    ['address_line_1', 'address_line_2', 'city', 'state', 'zip_code'].forEach(function (name) {
                        var target = getField(name);
                        if (target) target.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
            }

            /* ---------- validation ---------- */
            function fail(code, field) {
                var m = MSG[code];
                return { code: code, field: field, title: m[0], message: m[1] };
            }

            function validate() {
                var req = [
                    ['address_line_1', 'shipping address'],
                    ['city', 'city'],
                    ['state', 'state'],
                    ['zip_code', 'ZIP code']
                ];
                var same = getField('same_as_shipping');
                if (same && !same.checked) {
                    req.push(['billing_address_line_1', 'billing address'], ['billing_city', 'billing city'],
                            ['billing_state', 'billing state'], ['billing_zip', 'billing ZIP code']);
                }
                for (var i = 0; i < req.length; i++) {
                    var el = getField(req[i][0]);
                    if (el && !String(el.value).trim()) {
                        return { code: 'invalid_address', field: el, title: 'Missing Information',
                                message: 'Please enter your ' + req[i][1] + '.' };
                    }
                }

                var num = digits(cardEl.value);
                var amex = isAmex(num);
                if (num.length < 13 || num.length > 19 || (amex && num.length !== 15) || !luhn(num)) {
                    return fail('invalid_card', cardEl);
                }

                var m = /^(\d{2})\/(\d{2})$/.exec(expEl.value.trim());
                if (!m) return fail('invalid_expiry', expEl);
                var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10);
                if (month < 1 || month > 12) return fail('invalid_expiry', expEl);
                var now = new Date(), ny = now.getFullYear(), nm = now.getMonth() + 1;
                if (year < ny || (year === ny && month < nm)) return fail('expired_card', expEl);
                if (year > ny + 20) return fail('invalid_expiry', expEl);

                var cvv = digits(cvvEl.value);
                if (cvv.length !== (amex ? 4 : 3)) return fail('invalid_cvv', cvvEl);

                return null;
            }

            /* ---------- submit via AJAX ---------- */
            function resetButton() {
                busy = false;
                if (btn) { btn.disabled = false; btn.textContent = btnLabel; }
            }

            if (!btn) return;
            btn.addEventListener('click', function (e) {
                // Payment is AJAX-only. The button is type=button so Gravity Forms cannot submit/reload.
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                if (busy) return false;

                try {
                    syncAddressFromProfile();
                    var problem = validate();
                    if (problem) {
                        showError(problem.title, problem.message, problem.field);
                        return false;
                    }
                    if (typeof window.medcardsUpdatePaymentSummary === 'function') window.medcardsUpdatePaymentSummary();

                    busy = true;
                    if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }
                    var fd = new FormData();
                    Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (control) {
                        if (control.disabled || !control.name) return;
                        if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
                        if (control.type === 'file') {
                            Array.prototype.forEach.call(control.files || [], function (file) { fd.append(control.name, file); });
                        } else {
                            fd.append(control.name, control.value);
                        }
                    });
                    fd.set('action', 'cap_process_payment');
                    var ajaxUrl = form.getAttribute('data-ajax-url');
                    if (!ajaxUrl) throw new Error('Payment endpoint is missing.');

                    fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd, headers: {'Accept': 'application/json'} })
                        .then(function (r) {
                            return r.text().then(function (body) {
                                var parsed;
                                try { parsed = JSON.parse(body); }
                                catch (parseError) { throw new Error('The payment server returned an unexpected response. Please try again.'); }
                                return parsed;
                            });
                        })
                        .then(function (res) {
                            if (res && res.success === true && res.data && res.data.redirect) {
                                if (btn) btn.textContent = 'Payment approved…';
                                window.location.assign(res.data.redirect);
                                return;
                            }
                            var d = (res && res.data) || {};
                            showError(d.title || 'Payment Not Approved',
                                d.message || 'Authorize.net did not approve this payment. Please check your card details or try another card.',
                                FIELD_FOR_CODE[d.code]);
                            resetButton();
                        })
                        .catch(function (err) {
                            showError('Payment Processing Error',
                                (err && err.message) || 'We could not confirm the payment result. Please try again.');
                            resetButton();
                        });
                } catch (err) {
                    showError('Payment Processing Error', 'The payment form could not be submitted safely. Please refresh the page and try again.');
                    resetButton();
                }
                return false;
            });

            // Error returned by a non-JS / fallback form post.
            if (window.capServerPaymentError) {
                var se = window.capServerPaymentError;
                showError(se.title, se.message, FIELD_FOR_CODE[se.code]);
            }
        })();
    </script>


    <script>
    /* Delegated formatter: also works if WordPress/Gravity Forms inserts the payment form dynamically. */
    (function () {
        'use strict';
        function digits(value) { return String(value || '').replace(/\D/g, ''); }
        function formatCard(value) {
            var d = digits(value);
            if (/^3[47]/.test(d)) {
                d = d.slice(0, 15);
                return [d.slice(0, 4), d.slice(4, 10), d.slice(10, 15)].filter(Boolean).join(' ');
            }
            // Prevent a 19-digit value such as 4111 1111 1111 1111 111 on this form.
            d = d.slice(0, 16);
            return d.replace(/(.{4})/g, '$1 ').trim();
        }
        function formatExpiry(value) {
            var d = digits(value);
            if (d.length > 4) d = d.slice(0, 2) + d.slice(-2);
            d = d.slice(0, 4);
            if (d.length === 1 && d > '1') d = '0' + d;
            if (d.length >= 2) {
                var month = parseInt(d.slice(0, 2), 10);
                if (month === 0) d = '01' + d.slice(2);
                if (month > 12) d = '12' + d.slice(2);
            }
            return d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        }
        document.addEventListener('input', function (event) {
            var el = event.target;
            if (!el || !el.name) return;
            if (el.name === 'card_number') {
                el.value = formatCard(el.value);
                el.maxLength = 19;
            } else if (el.name === 'expiry') {
                el.value = formatExpiry(el.value);
                el.maxLength = 5;
                el.placeholder = 'MM/YY';
            } else if (el.name === 'cvv') {
                var form = el.form || el.closest('form');
                var card = form ? form.querySelector('[name="card_number"]') : null;
                var amex = card && /^3[47]/.test(digits(card.value));
                el.value = digits(el.value).slice(0, amex ? 4 : 3);
                el.maxLength = amex ? 4 : 3;
            }
        }, true);
        document.addEventListener('paste', function (event) {
            var el = event.target;
            if (!el || !['card_number', 'expiry', 'cvv'].includes(el.name)) return;
            window.setTimeout(function () {
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        }, true);
    })();
    </script>

    <?php
    return ob_get_clean();
});

/* =========================================================
 * EMAIL PATIENT ID CARD PDF AFTER SUCCESSFUL PAYMENT
 *
 * Requires Dompdf installed in:
 * custom-authorize-payment/vendor/autoload.php
 * Install from the child-theme folder:
 * composer require dompdf/dompdf
 *
 * Only plans 2-5 trigger the PDF email. The default
 * new_pdf_id_card plan (box 1) is intentionally excluded.
 * ========================================================= */
// if (!function_exists('cap_email_patient_card_pdf_after_payment')) {
//     function cap_email_patient_card_pdf_after_payment($user_id, $plan_key, $transaction_id)
//     {
//         $eligible_plans = array(
//             'caregiver_new',
//             'growers_license',
//             'new_pdf',
//             'renewal_hard_copy',
//         );

//         $plan_key = sanitize_key((string) $plan_key);
//         $user_id = absint($user_id);
//         $transaction_id = sanitize_text_field((string) $transaction_id);

//         if (
//             !$user_id ||
//             !$transaction_id ||
//             !in_array($plan_key, $eligible_plans, true)
//         ) {
//             return false;
//         }

//         // Prevent duplicate email for the same successful transaction.
//         $already_sent_for = (string) get_user_meta(
//             $user_id,
//             'payment_pdf_email_transaction_id',
//             true
//         );

//         if ($already_sent_for === $transaction_id) {
//             return true;
//         }

//         $user = get_userdata($user_id);
//         if (!$user || !is_email($user->user_email)) {
//             error_log('MCN PDF email skipped: invalid user/email for user ID ' . $user_id);
//             return false;
//         }

//         $vendor = trailingslashit(get_stylesheet_directory()) .
//             'custom-authorize-payment/vendor/autoload.php';

//         if (file_exists($vendor)) {
//             require_once $vendor;
//         }

//         if (!class_exists('\Dompdf\Dompdf')) {
//             error_log(
//                 'MCN PDF email failed: Dompdf is not installed. Install with composer require dompdf/dompdf in the custom-authorize-payment directory.'
//             );
//             return false;
//         }

//         // The existing shortcode renders the user's California patient ID card.
//         $card_html = do_shortcode('[medcards_california_id_card_cards]');

//         if (!is_string($card_html) || trim($card_html) === '') {
//             error_log(
//                 'MCN PDF email failed: patient ID card shortcode returned empty HTML for user ID ' . $user_id
//             );
//             return false;
//         }

//         $first_name = get_user_meta($user_id, 'first_name', true);
//         $last_name  = get_user_meta($user_id, 'last_name', true);
//         $patient_name = trim($first_name . ' ' . $last_name);

//         if ($patient_name === '') {
//             $patient_name = $user->display_name;
//         }

//         $html = '<!doctype html><html><head><meta charset="utf-8">
//             <style>
//                 @page { margin: 18px; }
//                 body { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
//                 img { max-width: 100%; }
//             </style>
//             </head><body>' . $card_html . '</body></html>';

//         try {
//             $options = new \Dompdf\Options();
//             $options->set('isRemoteEnabled', true);
//             $options->set('isHtml5ParserEnabled', true);
//             $options->set('defaultFont', 'DejaVu Sans');

//             $dompdf = new \Dompdf\Dompdf($options);
//             $dompdf->loadHtml($html, 'UTF-8');
//             $dompdf->setPaper('letter', 'portrait');
//             $dompdf->render();

//             $pdf_bytes = $dompdf->output();

//             if (empty($pdf_bytes) || strpos($pdf_bytes, '%PDF-') !== 0) {
//                 error_log('MCN PDF email failed: Dompdf returned invalid PDF for user ID ' . $user_id);
//                 return false;
//             }

//             $upload = wp_upload_dir();
//             if (!empty($upload['error']) || empty($upload['basedir'])) {
//                 error_log('MCN PDF email failed: WordPress uploads directory is unavailable.');
//                 return false;
//             }

//             $private_dir = trailingslashit($upload['basedir']) . 'mcn-payment-pdfs';
//             if (!file_exists($private_dir) && !wp_mkdir_p($private_dir)) {
//                 error_log('MCN PDF email failed: could not create PDF temporary directory.');
//                 return false;
//             }

//             // Use a temporary filename; delete the PDF after attempting email.
//             $filename = 'patient-id-card-' . $user_id . '-' . wp_generate_password(8, false, false) . '.pdf';
//             $pdf_path = trailingslashit($private_dir) . $filename;

//             if (file_put_contents($pdf_path, $pdf_bytes) === false) {
//                 error_log('MCN PDF email failed: could not write PDF for user ID ' . $user_id);
//                 return false;
//             }

//             $subject = 'Your Med Cards Now Patient ID Card';
//             $message = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
//                 . '<p>Hello ' . esc_html($patient_name) . ',</p>'
//                 . '<p>Your payment has been approved. Your patient ID card PDF is attached to this email.</p>'
//                 . '<p>Transaction ID: ' . esc_html($transaction_id) . '</p>'
//                 . '<p>Thank you,<br>Med Cards Now</p>'
//                 . '</body></html>';

//             $headers = array(
//                 'From: Med Cards Now <contact@demo.medcardsnow.com>',
//                 'Reply-To: contact@demo.medcardsnow.com',
//                 'Content-Type: text/html; charset=UTF-8',
//             );

//             $sent = wp_mail(
//                 $user->user_email,
//                 $subject,
//                 $message,
//                 $headers,
//                 array($pdf_path)
//             );

//             if (file_exists($pdf_path)) {
//                 @unlink($pdf_path);
//             }

//             if (!$sent) {
//                 error_log('MCN PDF email failed for user ID ' . $user_id . ', transaction ' . $transaction_id);
//                 return false;
//             }

//             update_user_meta($user_id, 'payment_pdf_email_transaction_id', $transaction_id);
//             update_user_meta($user_id, 'payment_pdf_email_sent_at', current_time('mysql'));

//             error_log('MCN patient ID PDF email sent to ' . $user->user_email . ' for transaction ' . $transaction_id);
//             return true;

//         } catch (\Throwable $e) {
//             error_log('MCN PDF generation exception for user ID ' . $user_id . ': ' . $e->getMessage());
//             if (isset($pdf_path) && file_exists($pdf_path)) {
//                 @unlink($pdf_path);
//             }
//             return false;
//         }
//     }
// }


/**
 * Email patient ID card PDF after successful payment.
 */
if (!function_exists('cap_email_patient_card_pdf_after_payment')) {
    function cap_email_patient_card_pdf_after_payment($user_id, $plan_key, $transaction_id)
    {
        $user_id        = absint($user_id);
        $transaction_id = sanitize_text_field((string) $transaction_id);
        $plan_key       = sanitize_key((string) $plan_key);

        if (!$user_id || empty($transaction_id)) {
            error_log('MCN PDF email failed: Invalid user ID or transaction ID.');
            return false;
        }

        // Prevent duplicate emails for the same transaction
        $sent_transaction = (string) get_user_meta($user_id, 'payment_pdf_email_transaction_id', true);
        if ($sent_transaction === $transaction_id) {
            return true;
        }

        $user = get_userdata($user_id);
        if (!$user || !is_email($user->user_email)) {
            error_log('MCN PDF email failed: No valid email for user ID ' . $user_id);
            return false;
        }

        // Load Autoloader for Dompdf
        $vendor_paths = array(
            get_stylesheet_directory() . '/custom-authorize-payment/vendor/autoload.php',
            get_stylesheet_directory() . '/custom-authorize-payment/dompdf-libs/autoload.inc.php'
        );

        foreach ($vendor_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            error_log('MCN PDF email failed: Dompdf class not loaded.');
            return false;
        }

        // Switch to the target user context to accurately generate shortcode content
        $current_user = get_current_user_id();
        wp_set_current_user($user_id);
        $card_html = do_shortcode('[medcards_california_id_card_cards]');
        wp_set_current_user($current_user);

        if (empty(trim(wp_strip_all_tags($card_html)))) {
            error_log('MCN PDF email failed: ID card shortcode returned empty content.');
            return false;
        }

        // Full HTML wrapper with inline styling compatibility for Dompdf
        $full_html = '<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                @page { margin: 10px; }
                body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
                .mcn-ca-card-wrapper { width: 100%; max-width: 800px; margin: 0 auto; }
                img { max-width: 100%; height: auto; }
            </style>
        </head>
        <body>' . $card_html . '</body>
        </html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($full_html, 'UTF-8');
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            $pdf_bytes = $dompdf->output();

            if (empty($pdf_bytes)) {
                error_log('MCN PDF email failed: Rendered PDF is empty.');
                return false;
            }

            // Write PDF file temporarily
            $upload_dir = wp_upload_dir();
            $temp_dir   = trailingslashit($upload_dir['basedir']) . 'mcn-temp-cards';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            $pdf_filename = 'medical-card-' . $user_id . '-' . time() . '.pdf';
            $pdf_path     = trailingslashit($temp_dir) . $pdf_filename;

            file_put_contents($pdf_path, $pdf_bytes);

            // Send Email
            $subject = 'Your Med Cards Now Patient ID Card';
            $message = "<p>Hi " . esc_html($user->display_name) . ",</p>"
                     . "<p>Thank you for your payment. Your California Medical Cannabis Patient ID Card is attached to this email.</p>"
                     . "<p><strong>Transaction ID:</strong> " . esc_html($transaction_id) . "</p>"
                     . "<p>Best regards,<br>Med Cards Now Team</p>";

            $headers = array(
                'From: Med Cards Now <contact@demo.medcardsnow.com>',
                'Content-Type: text/html; charset=UTF-8'
            );

            $mail_sent = wp_mail($user->user_email, $subject, $message, $headers, array($pdf_path));

            // Clean up temporary file
            if (file_exists($pdf_path)) {
                @unlink($pdf_path);
            }

            if ($mail_sent) {
                update_user_meta($user_id, 'payment_pdf_email_transaction_id', $transaction_id);
                return true;
            } else {
                error_log('MCN PDF email failed: wp_mail returned false.');
                return false;
            }
        } catch (\Throwable $e) {
            error_log('MCN PDF email exception: ' . $e->getMessage());
            return false;
        }
    }
}





/* =========================================================
 * PAYMENT HANDLER
 * ========================================================= */
/* =========================================================
 * PAYMENT HANDLER
 * ========================================================= */

function cap_payment_result($success, $error = '', $extra = array())
{
    return array_merge(array('success' => (bool) $success, 'error' => $error), $extra);
}

function cap_luhn_check($number)
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int) $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) === 0;
}

/**
 * Entry point. Verifies nonce, prevents double-submits, then processes the payment.
 * Returns array('success' => bool, 'error' => code, ...)
 */
function cap_process_payment_request()
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return cap_payment_result(false, 'not_logged_in');
    }

    if (
        !isset($_POST['custom_authorize_payment_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['custom_authorize_payment_nonce'])), 'custom_authorize_payment_action')
    ) {
        return cap_payment_result(false, 'security');
    }

    // Block double-clicks / parallel submits (prevents double charging).
    $lock_key = 'cap_pay_lock_' . $user_id;
    if (get_transient($lock_key)) {
        return cap_payment_result(false, 'processing');
    }
    set_transient($lock_key, 1, 60);

    $result = cap_process_payment_locked($user_id);

    delete_transient($lock_key);
    return $result;
}

function cap_process_payment_locked($user_id)
{
    /* ---------- PRICE (server-side only) ---------- */
    $live_plan_key  = isset($_POST['live_plan_key'])  ? sanitize_key(wp_unslash($_POST['live_plan_key']))  : '';
    $live_year_plan = isset($_POST['live_year_plan']) ? sanitize_key(wp_unslash($_POST['live_year_plan'])) : '1_year';
    $live_hard_copy = isset($_POST['live_hard_copy']) ? sanitize_text_field(wp_unslash($_POST['live_hard_copy'])) : 'no';
    $live_id_card   = isset($_POST['live_id_card'])   ? sanitize_text_field(wp_unslash($_POST['live_id_card']))   : 'no';
    $live_legal     = isset($_POST['live_legal'])     ? sanitize_text_field(wp_unslash($_POST['live_legal']))     : '';

    $price = 0.0;

    if ($live_plan_key !== '') {
        $price = cap_compute_server_total($live_plan_key, $live_year_plan, $live_hard_copy, $live_id_card, $live_legal);
    }

    if ($price <= 0) {
        $raw_price = get_user_meta($user_id, 'payment_amount', true);
        $raw_price = is_scalar($raw_price) ? trim((string) $raw_price) : '';
        if ($raw_price !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $raw_price) && (float) $raw_price > 0) {
            $price = (float) $raw_price;
        }
    }

    if ($price <= 0) {
        return cap_payment_result(false, 'amount_missing');
    }

    /* ---------- COUPON ---------- */
    $subtotal      = $price;
    $coupon_code   = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code']))) : '';
    $coupon_result = $coupon_code !== ''
        ? mcn_get_coupon_discount($coupon_code, $subtotal)
        : array('valid' => false, 'discount' => 0, 'message' => '');

    if ($coupon_code !== '' && empty($coupon_result['valid'])) {
        return cap_payment_result(false, 'invalid_coupon');
    }

    $coupon_discount = !empty($coupon_result['valid']) ? (float) $coupon_result['discount'] : 0;
    $charge          = round($subtotal - $coupon_discount, 2);

    if ($charge <= 0) {
        return cap_payment_result(false, 'amount_missing');
    }
    $price = number_format($charge, 2, '.', '');

    /* ---------- CARD VALIDATION (server-side) ---------- */
    $card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', wp_unslash($_POST['card_number'])) : '';
    $expiry      = isset($_POST['expiry']) ? sanitize_text_field(wp_unslash($_POST['expiry'])) : '';
    $cvv         = isset($_POST['cvv']) ? preg_replace('/\D/', '', wp_unslash($_POST['cvv'])) : '';
    $is_amex     = (bool) preg_match('/^3[47]/', $card_number);

    if (
        strlen($card_number) < 13 || strlen($card_number) > 19 ||
        ($is_amex && strlen($card_number) !== 15) ||
        !cap_luhn_check($card_number)
    ) {
        return cap_payment_result(false, 'invalid_card');
    }

    if (!preg_match('/^(\d{1,2})\s*\/\s*(\d{2}|\d{4})$/', $expiry, $m)) {
        return cap_payment_result(false, 'invalid_expiry');
    }
    $exp_month = (int) $m[1];
    $exp_year  = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
    $now_year  = (int) current_time('Y');
    $now_month = (int) current_time('n');

    if ($exp_month < 1 || $exp_month > 12 || $exp_year > $now_year + 20) {
        return cap_payment_result(false, 'invalid_expiry');
    }
    if ($exp_year < $now_year || ($exp_year === $now_year && $exp_month < $now_month)) {
        return cap_payment_result(false, 'expired_card');
    }
    $formatted_expiry = sprintf('%04d-%02d', $exp_year, $exp_month);

    if (strlen($cvv) !== ($is_amex ? 4 : 3)) {
        return cap_payment_result(false, 'invalid_cvv');
    }

    /* ---------- ADDRESSES ---------- */
    $address_line_1 = isset($_POST['address_line_1']) ? sanitize_text_field(wp_unslash($_POST['address_line_1'])) : '';
    $address_line_2 = isset($_POST['address_line_2']) ? sanitize_text_field(wp_unslash($_POST['address_line_2'])) : '';
    $city           = isset($_POST['city'])           ? sanitize_text_field(wp_unslash($_POST['city']))           : '';
    $state          = isset($_POST['state'])          ? sanitize_text_field(wp_unslash($_POST['state']))          : '';
    $zip_code       = isset($_POST['zip_code'])       ? sanitize_text_field(wp_unslash($_POST['zip_code']))       : '';
    $country        = 'United States';

    if ($address_line_1 === '' || $city === '' || $state === '' || $zip_code === '') {
        return cap_payment_result(false, 'invalid_address');
    }

    update_user_meta($user_id, 'address_line_1', $address_line_1);
    update_user_meta($user_id, 'address_line_2', $address_line_2);
    update_user_meta($user_id, 'city', $city);
    update_user_meta($user_id, 'country', $country);
    update_user_meta($user_id, 'state', $state);
    update_user_meta($user_id, 'zip_code', $zip_code);

    $same_as_shipping = isset($_POST['same_as_shipping']) ? 'yes' : 'no';
    update_user_meta($user_id, 'billing_same_as_shipping', $same_as_shipping);

    if ($same_as_shipping === 'no') {
        $b1 = isset($_POST['billing_address_line_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_line_1'])) : '';
        $b2 = isset($_POST['billing_address_line_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_line_2'])) : '';
        $bc = isset($_POST['billing_city'])           ? sanitize_text_field(wp_unslash($_POST['billing_city']))           : '';
        $bs = isset($_POST['billing_state'])          ? sanitize_text_field(wp_unslash($_POST['billing_state']))          : '';
        $bz = isset($_POST['billing_zip'])            ? sanitize_text_field(wp_unslash($_POST['billing_zip']))            : '';
    } else {
        $b1 = $address_line_1; $b2 = $address_line_2; $bc = $city; $bs = $state; $bz = $zip_code;
    }
    update_user_meta($user_id, 'billing_address_line_1', $b1);
    update_user_meta($user_id, 'billing_address_line_2', $b2);
    update_user_meta($user_id, 'billing_city', $bc);
    update_user_meta($user_id, 'billing_state', $bs);
    update_user_meta($user_id, 'billing_zip', $bz);
    update_user_meta($user_id, 'billing_country', 'United States');

    if (function_exists('update_field')) {
        update_field('address_line_1', $address_line_1, 'user_' . $user_id);
        update_field('address_line_2', $address_line_2, 'user_' . $user_id);
        update_field('city', $city, 'user_' . $user_id);
        update_field('country', $country, 'user_' . $user_id);
        update_field('state', $state, 'user_' . $user_id);
        update_field('zip_code', $zip_code, 'user_' . $user_id);
    }

    if ($live_plan_key) {
        update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
    }
    if ($live_year_plan) {
        update_user_meta($user_id, 'california_year_plan', $live_year_plan);
    }
    // Keep the ORIGINAL subtotal (before coupon) so the coupon is never applied twice.
    update_user_meta($user_id, 'payment_amount', number_format($subtotal, 2, '.', ''));

    /* ---------- SETTINGS + SDK ---------- */
    $settings = cap_get_settings();

    if (empty($settings['enabled'])) {
        return cap_payment_result(false, 'payment_disabled');
    }
    if (empty($settings['api_login_id']) || empty($settings['transaction_key'])) {
        return cap_payment_result(false, 'credentials_missing');
    }

    $vendor = trailingslashit(get_stylesheet_directory()) . 'custom-authorize-payment/vendor/autoload.php';
    if (!file_exists($vendor)) {
        return cap_payment_result(false, 'sdk_missing');
    }
    require_once $vendor;

    /* ---------- GATEWAY REQUEST ---------- */
    $merchantAuthentication = new net\authorize\api\contract\v1\MerchantAuthenticationType();
    $merchantAuthentication->setName($settings['api_login_id']);
    $merchantAuthentication->setTransactionKey($settings['transaction_key']);

    $creditCard = new net\authorize\api\contract\v1\CreditCardType();
    $creditCard->setCardNumber($card_number);
    $creditCard->setExpirationDate($formatted_expiry);
    $creditCard->setCardCode($cvv);

    $payment = new net\authorize\api\contract\v1\PaymentType();
    $payment->setCreditCard($creditCard);

    $transactionRequest = new net\authorize\api\contract\v1\TransactionRequestType();
    $transactionRequest->setTransactionType('authCaptureTransaction');
    $transactionRequest->setAmount($price);
    $transactionRequest->setPayment($payment);

    $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequest);

    $controller  = new net\authorize\api\controller\CreateTransactionController($request);
    $environment = !empty($settings['sandbox'])
        ? net\authorize\api\constants\ANetEnvironment::SANDBOX
        : net\authorize\api\constants\ANetEnvironment::PRODUCTION;

    if (!empty($settings['debug_logging'])) {
        error_log('Custom Authorize Payment: sending transaction to ' . (!empty($settings['sandbox']) ? 'SANDBOX' : 'PRODUCTION') . ' environment.');
    }

    $response                = null;
    $transactionResponse     = null;
    $transactionResponseCode = null;
    $transactionId           = '';
    $apiResultCode           = '';
    $responseReasonCode      = '';
    $responseReasonText      = '';
    $gatewayErrorCode        = '';
    $gatewayErrorText        = '';
    $gatewayMsgCode          = '';
    $api_exception           = false;

    try {
        $response = $controller->executeWithApiResponse($environment);

        if ($response && $response->getMessages()) {
            $apiResultCode = (string) $response->getMessages()->getResultCode();
            $messages = $response->getMessages()->getMessage();
            if (!empty($messages)) {
                $responseReasonCode = (string) $messages[0]->getCode();
                $responseReasonText = (string) $messages[0]->getText();
            }
        }

        if ($response) {
            $transactionResponse = $response->getTransactionResponse();
        }

        if ($transactionResponse) {
            $transactionResponseCode = (string) $transactionResponse->getResponseCode();
            $transactionId           = (string) $transactionResponse->getTransId();

            $errors = $transactionResponse->getErrors();
            if (!empty($errors)) {
                $gatewayErrorCode = (string) $errors[0]->getErrorCode();
                $gatewayErrorText = (string) $errors[0]->getErrorText();
            }

            $tmsgs = $transactionResponse->getMessages();
            if (!empty($tmsgs)) {
                $gatewayMsgCode = (string) $tmsgs[0]->getCode();
            }
        }

        if (!empty($settings['debug_logging'])) {
            error_log('=== AUTHORIZE.NET TRANSACTION RESPONSE ===');
            error_log('API Result Code: ' . ($apiResultCode !== '' ? $apiResultCode : 'N/A'));
            error_log('Transaction Response Code: ' . ($transactionResponseCode !== null ? $transactionResponseCode : 'N/A'));
            error_log('Response Reason Code: ' . ($responseReasonCode !== '' ? $responseReasonCode : 'N/A'));
            error_log('Response Reason Text: ' . ($responseReasonText !== '' ? $responseReasonText : 'N/A'));
            error_log('Gateway Error Code/Text: ' . $gatewayErrorCode . ' / ' . $gatewayErrorText);
            error_log('Gateway Message Code: ' . $gatewayMsgCode);
            error_log('Transaction ID: ' . ($transactionId !== '' ? $transactionId : 'N/A'));
            error_log('=== END AUTHORIZE.NET TRANSACTION RESPONSE ===');
        }
    } catch (\Throwable $e) {
        if (!empty($settings['debug_logging'])) {
            error_log('Authorize.net Exception: ' . $e->getMessage());
        }
        $response            = null;
        $transactionResponse = null;
        $transactionId       = '';
        $api_exception       = true;
    }

    /* ---------- APPROVED ---------- */
    $payment_approved = (
        $transactionResponse !== null &&
        $transactionResponseCode === '1' &&
        $transactionId !== ''
    );

    if ($payment_approved) {
        if ($coupon_code !== '' && !empty($coupon_result['valid'])) {
            $all_coupons = mcn_get_coupons();
            if (isset($all_coupons[$coupon_code])) {
                $all_coupons[$coupon_code]['used_count'] = absint($all_coupons[$coupon_code]['used_count'] ?? 0) + 1;
                update_option(MCN_COUPONS_OPTION, $all_coupons, false);
            }
            update_user_meta($user_id, 'payment_coupon_code', $coupon_code);
            update_user_meta($user_id, 'payment_coupon_discount', number_format($coupon_discount, 2, '.', ''));
        }

        update_user_meta($user_id, 'payment_status', 'paid');
        update_user_meta($user_id, 'transaction_id', $transactionId);

        if ($live_plan_key !== '') {
            update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
        }

        // Email failures must never block the customer from reaching the thank-you page.
        try {
            cap_email_patient_card_pdf_after_payment($user_id, $live_plan_key, $transactionId);
        } catch (\Throwable $e) {
            error_log('MCN PDF email exception after payment: ' . $e->getMessage());
        }

        return cap_payment_result(true, '', array('transaction_id' => $transactionId));
    }

    /* ---------- NOT APPROVED ---------- */
    if (get_user_meta($user_id, 'payment_status', true) !== 'paid') {
        update_user_meta($user_id, 'payment_status', 'failed');
        delete_user_meta($user_id, 'transaction_id');
    }

    // Map Authorize.Net codes to friendly errors.
    $code_map = array(
        '6'      => 'invalid_card',     // card number invalid
        '37'     => 'invalid_card',
        '7'      => 'invalid_expiry',   // expiry invalid
        '8'      => 'expired_card',     // card expired
        '44'     => 'invalid_cvv',      // card code mismatch
        '45'     => 'invalid_cvv',
        '65'     => 'invalid_cvv',
        '78'     => 'invalid_cvv',
        '27'     => 'avs_mismatch',     // address mismatch
        '11'     => 'duplicate',        // duplicate transaction
        'E00007' => 'credentials_missing',
        'E00008' => 'credentials_missing',
    );

    $error_type = 'not_approved';

    if ($api_exception) {
        $error_type = 'api_exception';
    } else {
        foreach (array($gatewayErrorCode, $gatewayMsgCode, $responseReasonCode) as $c) {
            if ($c !== '' && isset($code_map[$c])) {
                $error_type = $code_map[$c];
                break;
            }
        }

        if ($error_type === 'not_approved') {
            if (!$response) {
                $error_type = 'no_response';
            } elseif ($transactionResponseCode === '2') {
                $error_type = 'declined';
            } elseif ($transactionResponseCode === '3') {
                $error_type = 'error';
            } elseif ($transactionResponseCode === '4') {
                $error_type = 'held';
            } elseif ($transactionResponseCode === '1' && $transactionId === '') {
                $error_type = 'missing_transaction_id';
            }
        }
    }

    return cap_payment_result(false, $error_type);
}

/* AJAX endpoint used by the checkout form (no page reload). */
add_action('wp_ajax_cap_process_payment', 'cap_ajax_process_payment');
add_action('wp_ajax_nopriv_cap_process_payment', 'cap_ajax_process_payment');
function cap_ajax_process_payment()
{
    $result = cap_process_payment_request();

    if (!empty($result['success'])) {
        wp_send_json_success(array('redirect' => home_url('/thank-you/')));
    }

    $error   = !empty($result['error']) ? $result['error'] : 'not_approved';
    // Log only the normalized result when debug is enabled; never log card data or CVV.
    $cap_settings_for_log = cap_get_settings();
    if (!empty($cap_settings_for_log['debug_logging'])) {
        error_log('Custom Authorize Payment AJAX outcome: ' . sanitize_key($error));
    }
    $details = cap_get_payment_error_details($error);

    wp_send_json_error(array(
        'code'    => $error,
        'title'   => $details['title'],
        'message' => $details['message'],
    ));
}

/* Fallback for a normal (non-AJAX) form post, e.g. JavaScript disabled. */
add_action('init', function () {
    if (wp_doing_ajax() || !isset($_POST['process_payment']) || !is_user_logged_in()) {
        return;
    }

    $result = cap_process_payment_request();

    if (!empty($result['success'])) {
        wp_safe_redirect(home_url('/thank-you/'));
        exit;
    }

    $GLOBALS['cap_payment_error'] = !empty($result['error']) ? $result['error'] : 'not_approved';
});





