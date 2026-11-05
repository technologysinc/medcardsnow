<?php
/**
 * MCN Core module: ID card photo
 * Shows the uploaded photo on the ID card preview.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;





function footer_script_id_card(){
    if (!mcn_should_load_profile_assets()) {
        return;
    }
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
