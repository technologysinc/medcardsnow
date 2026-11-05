<?php
/**
 * MCN Core module: Patient ID card
 * The ID card shortcode and the PDF library it uses.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


// Load Dompdf without changing the Authorize.Net SDK.
$dompdf_autoload = get_stylesheet_directory()
    . '/custom-authorize-payment/dompdf-libs/autoload.inc.php';

if (is_readable($dompdf_autoload)) {
    require_once $dompdf_autoload;
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
                        src="https://medcardsnow.com/wp-content/uploads/2026/01/medcardsnow_logo_transparent.png"
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
                    ?>

                </div>
                
               <?php
                // Resolve the saved signature just before rendering it.
                if (empty($signature_url)) {
                    $sig_uid = isset($user_id) && $user_id ? $user_id : get_current_user_id();
                    $sig_attachment_id = get_user_meta($sig_uid, 'patinet_signature', true);
                    if ($sig_attachment_id) {
                        // Stored as an attachment ID -> get its URL.
                        $maybe_url = wp_get_attachment_url($sig_attachment_id);
                        $signature_url = $maybe_url ? $maybe_url : '';
                        // If it was stored as a URL already, fall back to that.
                        if ($signature_url === '' && filter_var($sig_attachment_id, FILTER_VALIDATE_URL)) {
                            $signature_url = esc_url_raw($sig_attachment_id);
                        }
                    }
                }
                ?>
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

                    medcardsnow.com

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
