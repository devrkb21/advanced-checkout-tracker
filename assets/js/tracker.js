jQuery(function ($) {
    'use strict';

    // Ensure our localized parameters object exists before running any logic.
    if (typeof act_checkout_params === 'undefined') {
        return;
    }

    // --- SESSION TRACKING LOGIC (COMBINED) ---
    // This section captures user input across checkout fields and sends it for potential cart abandonment recovery.
    // It sends all fields from the old file, using the debounced method from the new file.
    let debounceTimeout;
    const debounceDelay = 800; // Time in ms to wait after user stops typing.

    function collectAndSendData() {
        // Collect data from all relevant checkout fields.
        const checkoutData = {
            action: 'act_save_checkout_data',
            nonce: act_checkout_params.save_data_nonce,
            billing_first_name: $('#billing_first_name').val() || '',
            billing_last_name: $('#billing_last_name').val() || '',
            billing_company: $('#billing_company').val() || '',
            billing_country: $('#billing_country').val() || '',
            billing_address_1: $('#billing_address_1').val() || '',
            billing_address_2: $('#billing_address_2').val() || '',
            billing_city: $('#billing_city').val() || '',
            billing_state: $('#billing_state').val() || '',
            billing_postcode: $('#billing_postcode').val() || '',
            billing_phone: $('#billing_phone').val() || '',
            billing_email: $('#billing_email').val() || '',
            order_comments: $('#order_comments').val() || ''
        };

        // Send data only if at least one key field has been filled.
        if (checkoutData.billing_email || checkoutData.billing_phone || checkoutData.billing_first_name || checkoutData.billing_address_1) {
            $.post(act_checkout_params.ajax_url, checkoutData, function (response) {
                // Optional: handle success or failure response.
                // The one-time tracking flag 'act_session_tracked' has been removed
                // to allow for continuous updates, capturing the most complete data.
            });
        }
    }

    // A debounced version of the function to prevent excessive AJAX requests.
    function debouncedCollectAndSendData() {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(collectAndSendData, debounceDelay);
    }

    // Selectors for all fields we want to track.
    const fieldSelectors = [
        '#billing_first_name', '#billing_last_name', '#billing_company',
        '#billing_country', '#billing_address_1', '#billing_address_2',
        '#billing_city', '#billing_state', '#billing_postcode',
        '#billing_phone', '#billing_email', '#order_comments'
    ].join(',');

    // Event listeners to trigger data collection.
    $(document.body).on('input change blur', fieldSelectors, debouncedCollectAndSendData);
    $(document.body).on('update_checkout', () => setTimeout(debouncedCollectAndSendData, 100));

    // Initial data capture on page load for pre-filled fields.
    setTimeout(collectAndSendData, 1500);


    // --- LIVE RATIO CHECK LOGIC ---
    // This section provides real-time feedback on the success ratio of a phone number.
    let ratioDebounce;
    const phoneField = $('#billing_phone');

    // Only add the feedback div if the phone field exists.
    if (phoneField.length) {
        // **FIX:** Append the feedback div INSIDE the phone field's container for better positioning.
        phoneField.closest('p.form-row').append('<div id="act-ratio-feedback" class="act-ratio-feedback" style="display:none;"></div>');
        const feedbackDiv = $('#act-ratio-feedback');

        phoneField.on('input', function () {
            clearTimeout(ratioDebounce);
            const phoneNumber = $(this).val().replace(/\s/g, ''); // Sanitize phone number
            feedbackDiv.html('').hide();

            // Check for a specific length, adjust if needed for your country.
            if (phoneNumber.length >= 10) {
                feedbackDiv.html('<i>Checking success ratio...</i>').show();
                ratioDebounce = setTimeout(function () {
                    $.post(act_checkout_params.ajax_url, {
                        action: 'act_live_ratio_check',
                        nonce: act_checkout_params.live_ratio_check_nonce,
                        phone_number: phoneNumber
                    })
                        .done(function (response) {
                            if (response.success && response.data.rate !== undefined) {
                                feedbackDiv.html(`<strong>Success Ratio: ${response.data.rate}%</strong>`);
                            } else {
                                feedbackDiv.html(''); // Clear on failure or no rate
                            }
                        }).fail(function () {
                            feedbackDiv.html(''); // Clear on AJAX error
                        });
                }, 600); // Debounce delay for the ratio check
            }
        });
    }
    
});

