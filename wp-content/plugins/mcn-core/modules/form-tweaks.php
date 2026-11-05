<?php
/**
 * MCN Core module: Form date rules
 * Form 4 future-date validation and Form 1 date-picker options.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;



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
