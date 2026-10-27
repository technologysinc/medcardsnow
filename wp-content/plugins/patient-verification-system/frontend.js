jQuery(document).ready(function ($) {
    $('#patient-verification-form').on('submit', function (e) {
        e.preventDefault(); // Prevent default form submission behavior

        var code = $('.ver-code').val();
        var submitButton = $(this).find('button[type="submit"]'); // Target the submit button
        var originalButtonText = submitButton.text(); // Save the original button text

        // Change the button text to spinner
        submitButton.html('<span class="spinner"></span> Fetching...').prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: mpv_ajax.ajax_url,
            data: {
                action: 'mpv_verify_code',
                code: code
            },
            success: function (response) {
                if (response.success) {
                    $('.ver-output-error').hide();
                    $('.ver-out-success').css('display', 'flex');

                    // Update table values
                    $('#patient-name').text(response.data.patient_name);
                    $('#doctor-name').text(response.data.doctor_name);
                    $('#verification-code').text(response.data.verification_code);
                    $('#expiry-date').text(response.data.expiry_date);

                    // Check validity
                    if (response.data.is_expired) {
                        $('#valid-status').text('Expired').css('color', 'red');
                    } else {
                        $('#valid-status').text('Valid').css('color', 'green');
                    }
                } else {
                    // No record found, show error
                    $('.ver-out-success').hide();
                    $('.ver-output-error').css('display', 'flex');
                }
            },
            error: function () {
                $('.ver-out-success').hide();
                $('.ver-output-error').css('display', 'flex');
            },
            complete: function () {
                // Restore the original button text and re-enable the button
                submitButton.text(originalButtonText).prop('disabled', false);
            }
        });
    });
});
