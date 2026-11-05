<?php
/**
 * MCN Core module list, in load order. Generated on 2026-10-30.
 * 'requires': modules that must be running first. 'functions': functions the module
 * defines, checked for clashes before it loads.
 */
if (!defined('ABSPATH')) exit;

function mcn_core_manifest()
{
    return array (
      'core' => 
      array (
        'title' => 'Shared helpers',
        'description' => 'State-name cleanup (CA to California), staff-account check, login-token hashing, and the rule for where profile scripts load. Most modules need this.',
        'file' => 'core.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
        ),
        'guarded' => 
        array (
          0 => 'mcn_us_state_abbreviation_map',
          1 => 'mcn_normalize_state_name',
          2 => 'mcn_get_pricing_state',
          3 => 'mcn_user_is_privileged',
          4 => 'mcn_secure_login_token_hash',
          5 => 'mcn_should_load_profile_assets',
        ),
      ),
      'patient-profile' => 
      array (
        'title' => 'Patient profile fields',
        'description' => 'Pre-fills Gravity Forms fields from the patient account and saves the birthdate from Form 2.',
        'file' => 'patient-profile.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'populate_user_meta_first_name',
          1 => 'populate_user_meta_last_name',
          2 => 'populate_user_meta_phone',
          3 => 'populate_user_meta_dob',
          4 => 'populate_user_meta_address1',
          5 => 'populate_user_meta_address2',
          6 => 'populate_user_meta_city',
          7 => 'populate_user_meta_state',
          8 => 'populate_user_meta_zip',
          9 => 'populate_user_meta_country',
          10 => 'populate_user_meta_plan',
          11 => 'populate_user_meta_payment_amount',
        ),
        'guarded' => 
        array (
          0 => 'mcn_save_dob_to_user_meta_after_submit',
        ),
      ),
      'patient-login' => 
      array (
        'title' => 'Registration and magic-link login',
        'description' => 'Form 1 state capture, Form 3 secure login links, the post-registration login email, and link handling. Staff accounts are refused.',
        'file' => 'patient-login.php',
        'requires' => 
        array (
          0 => 'core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'zm_send_magic_login_link_after_registration',
          1 => 'zm_handle_magic_login',
          2 => 'populate_state_from_url',
          3 => 'save_state_to_user_meta_after_registration',
          4 => 'send_secure_login_link_from_gravity_form',
          5 => 'handle_secure_login_link',
        ),
        'guarded' => 
        array (
        ),
      ),
      'account-emails' => 
      array (
        'title' => 'Account email settings',
        'description' => 'Sender name and address, and switches off WordPress password-reset and new-user emails.',
        'file' => 'account-emails.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'disable_reset_password_email',
        ),
        'guarded' => 
        array (
        ),
      ),
      'medical-conditions' => 
      array (
        'title' => 'Medical conditions by state',
        'description' => 'Qualifying-condition choices per state on Form 2, saved to the patient profile.',
        'file' => 'medical-conditions.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_get_state_conditions',
          1 => 'populate_conditions_by_user_state',
          2 => 'save_conditions_to_acf_user_field',
        ),
        'guarded' => 
        array (
        ),
      ),
      'admin-tools' => 
      array (
        'title' => 'Admin user tools',
        'description' => 'Extra columns and sorting on the Users screen, and the patient ID download button.',
        'file' => 'admin-tools.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_sort_users_by_registration_date',
          1 => 'medcards_add_download_link_to_acf_file_field',
        ),
        'guarded' => 
        array (
        ),
      ),
      'geo-redirect' => 
      array (
        'title' => 'State landing redirects',
        'description' => 'Sends US visitors from /get-medical-card/ and /renew-medical-card/ to their state page.',
        'file' => 'geo-redirect.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'mcn_state_based_redirects',
        ),
        'guarded' => 
        array (
        ),
      ),
      'form-tweaks' => 
      array (
        'title' => 'Form date rules',
        'description' => 'Form 4 future-date validation and Form 1 date-picker options.',
        'file' => 'form-tweaks.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
        ),
        'guarded' => 
        array (
        ),
      ),
      'patient-uploads' => 
      array (
        'title' => 'ID and signature uploads',
        'description' => 'Saves the uploaded ID and the signature to the patient profile.',
        'file' => 'patient-uploads.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'save_gf_uploaded_file_to_acf_user_field',
          1 => 'medcards_create_attachment_from_local_url',
          2 => 'save_gf_signature_to_acf_user_field',
          3 => 'medcards_get_signature_url_from_entry_value',
          4 => 'medcards_import_signature_to_media',
        ),
        'guarded' => 
        array (
        ),
      ),
      'pricing' => 
      array (
        'title' => 'Plans and pricing',
        'description' => 'State price table, plan choices on Form 2, legal-letter and hard-copy rules.',
        'file' => 'pricing.php',
        'requires' => 
        array (
          0 => 'core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_get_state_pricing',
          1 => 'medcards_get_california_legal_letters',
          2 => 'medcards_get_california_year_plan_prices',
          3 => 'medcards_calculate_year_plan_price',
          4 => 'medcards_extract_legal_letter_values_from_request_safe',
          5 => 'medcards_get_legal_letter_total_from_values_safe',
          6 => 'medcards_get_legal_letters_total_from_field_58',
          7 => 'medcards_plan_already_has_hard_copy',
          8 => 'medcards_plan_already_has_id_card',
          9 => 'medcards_populate_plan_choices_by_state',
          10 => 'set_hidden_payment_amount_from_selected_plan',
        ),
        'guarded' => 
        array (
        ),
      ),
      'california-addons' => 
      array (
        'title' => 'California add-ons',
        'description' => 'California year-plan and legal-letter cards, and saving those selections.',
        'file' => 'california-addons.php',
        'requires' => 
        array (
          0 => 'pricing',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_save_california_addons_to_user_meta',
          1 => 'medcards_california_legal_letter_cards_shortcode',
          2 => 'medcards_california_year_plan_cards_shortcode',
        ),
        'guarded' => 
        array (
        ),
      ),
      'patient-card' => 
      array (
        'title' => 'Patient ID card',
        'description' => 'The ID card shortcode and the PDF library it uses.',
        'file' => 'patient-card.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_california_id_card_cards_shortcode',
        ),
        'guarded' => 
        array (
          0 => 'mcn_get_patient_email_for_card',
          1 => 'mcn_email_patient_card_pdf_after_payment',
        ),
      ),
      'profile-form-assets' => 
      array (
        'title' => 'Profile form scripts',
        'description' => 'The CSS and JavaScript that drive the multi-step profile form.',
        'file' => 'profile-form-assets.php',
        'requires' => 
        array (
          0 => 'core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_california_addon_assets',
          1 => 'medcards_legal_final_fix',
        ),
        'guarded' => 
        array (
        ),
      ),
      'california-totals' => 
      array (
        'title' => 'California order totals',
        'description' => 'Works out the final California total before and after Form 2 is submitted.',
        'file' => 'california-totals.php',
        'requires' => 
        array (
          0 => 'core',
          1 => 'pricing',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'medcards_final_ca_force_full_payment_total_before_submit',
          1 => 'medcards_final_ca_save_full_payment_total_meta_after_submit',
          2 => 'medcards_debug_gf_entry_fields',
          3 => 'medcards_final_ca_get_full_total_and_plan_summary',
          4 => 'medcards_final_ca_read_value',
          5 => 'medcards_final_ca_read_legal_values',
        ),
        'guarded' => 
        array (
        ),
      ),
      'patient-photo' => 
      array (
        'title' => 'ID card photo',
        'description' => 'Shows the uploaded photo on the ID card preview.',
        'file' => 'patient-photo.php',
        'requires' => 
        array (
          0 => 'core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'footer_script_id_card',
        ),
        'guarded' => 
        array (
        ),
      ),
      'sticky-header' => 
      array (
        'title' => 'Sticky header',
        'description' => 'Header behaviour script.',
        'file' => 'sticky-header.php',
        'requires' => 
        array (
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'func_sticky_header',
        ),
        'guarded' => 
        array (
        ),
      ),
      'patient-address' => 
      array (
        'title' => 'Patient address',
        'description' => 'Saves the Form 2 address and state to the patient profile as the patient moves through the form, and on submission. Includes Tools > Repair Addresses for profiles saved wrongly before 2026-10-30.',
        'file' => 'patient-address.php',
        'requires' => 
        array (
          0 => 'core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'mcn_profile_address_field_ids',
          1 => 'mcn_read_profile_address',
          2 => 'mcn_save_profile_address',
          3 => 'mcn_sync_profile_address_on_page_change',
          4 => 'medcards_save_gf_address_to_user_meta',
          5 => 'mcn_address_repair_value_is_answer',
          6 => 'mcn_address_repair_find_users',
          7 => 'mcn_address_repair_latest_entry',
          8 => 'mcn_address_repair_user',
          9 => 'mcn_address_repair_apply',
          10 => 'mcn_address_repair_run',
          11 => 'mcn_address_repair_render_page',
        ),
        'guarded' => 
        array (
        ),
      ),
      'payments-core' => 
      array (
        'title' => 'Payment engine',
        'description' => 'Server-side price calculation, gateway settings storage, and payment error messages.',
        'file' => 'payments-core.php',
        'requires' => 
        array (
          0 => 'core',
          1 => 'pricing',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'cap_get_pricing_table',
          1 => 'cap_get_plan_labels',
          2 => 'mcn_is_staging_site',
          3 => 'mcn_authorize_vendor_dir',
          4 => 'mcn_load_authorize_sdk',
          5 => 'mcn_authorize_sdk_report',
          6 => 'mcn_authorize_render_diagnostics',
          7 => 'cap_compute_server_total',
          8 => 'cap_get_payment_error_details',
          9 => 'cap_get_settings',
          10 => 'cap_payment_result',
          11 => 'cap_luhn_check',
        ),
        'guarded' => 
        array (
        ),
      ),
      'coupons' => 
      array (
        'title' => 'Coupons',
        'description' => 'Coupon admin page and the checkout coupon field.',
        'file' => 'coupons.php',
        'requires' => 
        array (
          0 => 'payments-core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'mcn_get_coupons',
          1 => 'mcn_get_coupon_discount',
          2 => 'mcn_apply_checkout_coupon_ajax',
          3 => 'mcn_render_coupons_page',
        ),
        'guarded' => 
        array (
        ),
      ),
      'payment-settings' => 
      array (
        'title' => 'Authorize.Net settings',
        'description' => 'Admin page for the gateway credentials.',
        'file' => 'payment-settings.php',
        'requires' => 
        array (
          0 => 'payments-core',
        ),
        'optional' => 
        array (
        ),
        'functions' => 
        array (
          0 => 'cap_sanitize_settings',
          1 => 'cap_text_field',
          2 => 'cap_checkbox_field',
          3 => 'cap_render_settings_page',
        ),
        'guarded' => 
        array (
        ),
      ),
      'checkout' => 
      array (
        'title' => 'Checkout and payment',
        'description' => 'The [custom_authorize_payment] checkout, the Authorize.Net charge, and the ID card PDF email after payment.',
        'file' => 'checkout.php',
        'requires' => 
        array (
          0 => 'core',
          1 => 'pricing',
          2 => 'payments-core',
          3 => 'coupons',
        ),
        'optional' => 
        array (
          0 => 'patient-card',
        ),
        'functions' => 
        array (
          0 => 'cap_process_payment_request',
          1 => 'cap_process_payment_locked',
          2 => 'cap_ajax_process_payment',
        ),
        'guarded' => 
        array (
          0 => 'cap_email_patient_card_pdf_after_payment',
        ),
      ),
    );
}
