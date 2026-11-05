<?php
/**
 * MCN Core module: Profile form scripts
 * The CSS and JavaScript that drive the multi-step profile form.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;








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
    if (!mcn_should_load_profile_assets()) {
        return;
    }
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

    /* Reference only — this is the JS replacement described in FIX 3 above.
   Put the function body into functions.php, not this comment. */
    function fireChange(element) {
        if (!element) return;
     
        // Guard against re-entrancy: a change we dispatch must not retrigger us.
        if (element.dataset.mcnFiring === '1') return;
        element.dataset.mcnFiring = '1';
     
        try {
            element.dispatchEvent(new Event('input',  { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        } finally {
            // Release on the next tick so the listeners finish first.
            setTimeout(function () { delete element.dataset.mcnFiring; }, 0);
        }
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
           if (target.dataset.mcnFiring !== '1' && getValidYear(target.value)) {
               setYear(target.value);
           }
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
