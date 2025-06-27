jQuery(function($) {
    'use strict';

    if (typeof act_admin_params === 'undefined') {
        console.error('ACT Admin: act_admin_params is not defined.');
        return;
    }

    // --- Modal Handling ---
    const modal = $('#act-details-modal');
    if (modal.length) {
        const modalBody = $('#act-modal-body');
        const closeModalButton = $('.act-modal-close');

        $(document).on('click', '.act-view-details', function(e) {
            e.preventDefault();
            const entryId = $(this).data('id');
            if (!entryId) return;

            modalBody.html('<p>Loading details...</p>');
            modal.show();

            $.post(act_admin_params.ajax_url, {
                action: 'act_get_checkout_details',
                nonce: act_admin_params.view_details_nonce,
                entry_id: entryId
            }).done(function(response) {
                if (response.success) {
                    modalBody.html(response.data.html);
                } else {
                    modalBody.html('<p class="error-message">' + (response.data.message || 'Could not load details.') + '</p>');
                }
            }).fail(function() {
                modalBody.html('<p class="error-message">An error occurred while fetching details.</p>');
            });
        });

        closeModalButton.on('click', () => modal.hide());
        $(window).on('click', (event) => {
            if ($(event.target).is(modal)) {
                modal.hide();
            }
        });
    }
    const modalBody = $('#act-modal-body');
    const closeModalButton = $('.act-modal-close');

    $(document).on('click', '.act-view-details', function(e) {
        e.preventDefault();
        const entryId = $(this).data('id');
        if (!entryId) return;

        modalBody.html('<p>Loading details...</p>');
        modal.show();

        $.post(act_admin_params.ajax_url, {
            action: 'act_get_checkout_details',
            nonce: act_admin_params.view_details_nonce,
            entry_id: entryId
        }).done(function(response) {
            if (response.success) {
                modalBody.html(response.data.html);
            } else {
                modalBody.html('<p class="error-message">' + (response.data.message || 'Could not load details.') + '</p>');
            }
        }).fail(function() {
            modalBody.html('<p class="error-message">An error occurred while fetching details.</p>');
        });
    });

    closeModalButton.on('click', () => modal.hide());
    $(window).on('click', (event) => {
        if (event.target === modal[0]) {
            modal.hide();
        }
    });

    // --- Row Actions Handlers ---
    function handleRowAction(button, confirmation, action, nonce, extraData = {}) {
        const $button = $(button);
        const entryId = $button.data('id');
        const originalButtonHtml = $button.html();

        if (!entryId || (confirmation && !confirm(confirmation))) {
            return;
        }

        $button.prop('disabled', true).html('<span class="spinner is-active" style="float:left;margin:0 5px;"></span>');

        let data = { action, nonce, entry_id: entryId, ...extraData };

        $.post(act_admin_params.ajax_url, data)
            .done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (action === 'act_recover_order' && response.data.edit_order_url) {
                        window.location.href = response.data.edit_order_url;
                    } else {
                        $('#act-entry-row-' + entryId).fadeOut(500, function() {
                            $(this).remove();
                        });
                    }
                } else {
                    alert('Error: ' + (response.data.message || 'An unknown error occurred.'));
                    $button.prop('disabled', false).html(originalButtonHtml);
                }
            }).fail(function() {
                alert('An AJAX error occurred. Please check the browser console.');
                $button.prop('disabled', false).html(originalButtonHtml);
            });
    }

    $(document).on('click', '.act-recover-order', function(e) {
        e.preventDefault();
        handleRowAction(this, 'Are you sure you want to recover this as a new WooCommerce order?', 'act_recover_order', act_admin_params.recover_order_nonce);
    });

    $(document).on('click', '.act-mark-hold', function(e) {
        e.preventDefault();
        const followUpDate = prompt('Enter follow-up date (YYYY-MM-DD), or leave blank:', new Date().toISOString().slice(0, 10));
        if (followUpDate === null) return;
        if (followUpDate !== "" && !/^\d{4}-\d{2}-\d{2}$/.test(followUpDate)) {
            alert('Invalid date format. Please use YYYY-MM-DD or leave it blank.');
            return;
        }
        handleRowAction(this, false, 'act_mark_hold', act_admin_params.mark_hold_nonce, { follow_up_date: followUpDate });
    });

    $(document).on('click', '.act-mark-cancelled', function(e) {
        e.preventDefault();
        handleRowAction(this, 'Are you sure you want to mark this entry as cancelled?', 'act_mark_cancelled', act_admin_params.mark_cancelled_nonce);
    });

    $(document).on('click', '.act-reopen-checkout', function(e) {
        e.preventDefault();
        handleRowAction(this, 'Are you sure you want to re-open this entry to "Incomplete"?', 'act_reopen_checkout', act_admin_params.reopen_checkout_nonce);
    });

    $(document).on('click', '.act-edit-follow-up-date', function(e) {
        e.preventDefault();
        const $button = $(this);
        const entryId = $button.data('id');
        const currentFollowUpDate = $button.data('current-date') || '';

        const newFollowUpDate = prompt('Enter new follow-up date (YYYY-MM-DD) or leave empty to clear:', currentFollowUpDate);
        if (newFollowUpDate === null) return;
        if (newFollowUpDate !== "" && !/^\d{4}-\d{2}-\d{2}$/.test(newFollowUpDate)) {
            alert('Invalid date format. Please use YYYY-MM-DD or leave empty to clear.');
            return;
        }

        const originalButtonHtml = $button.html();
        $button.prop('disabled', true).html('...');

        $.post(act_admin_params.ajax_url, {
            action: 'act_edit_follow_up_date',
            nonce: act_admin_params.edit_follow_up_date_nonce,
            entry_id: entryId,
            new_follow_up_date: newFollowUpDate
        }).done(function(response) {
            if (response.success) {
                alert(response.data.message);
                $('.act-follow-up-date-cell-' + entryId).text(response.data.new_date_formatted);
                $button.data('current-date', newFollowUpDate);
            } else {
                alert('Error: ' + (response.data.message || 'Could not update date.'));
            }
        }).fail(function() {
            alert('An AJAX error occurred while updating the date.');
        }).always(function() {
            $button.prop('disabled', false).html(originalButtonHtml);
        });
    });


    // --- Generic Handler for List Table Filtering ---
    function fetchEntriesForTable(pagePrefix, range, startDate = null, endDate = null) {
        const loadingDiv = $(`#act-${pagePrefix}-loading`);
        const tableContainer = $(`#act-${pagePrefix}-table-container`);
        const tbody = tableContainer.find('tbody');
        const status = pagePrefix.replace('-checkouts', '');

        loadingDiv.show();
        tableContainer.hide();

        const actionMap = {
            'incomplete': 'act_fetch_incomplete_entries',
            'recovered': 'act_fetch_recovered_entries',
            'cancelled': 'act_fetch_cancelled_entries',
            'hold': 'act_fetch_follow_up_entries'
        };

        const nonceMap = {
            'incomplete': act_admin_params.fetch_incomplete_nonce,
            'recovered': act_admin_params.fetch_recovered_nonce,
            'cancelled': act_admin_params.fetch_cancelled_nonce,
            'hold': act_admin_params.fetch_follow_up_nonce
        };

        const ajaxData = {
            action: actionMap[status],
            nonce: nonceMap[status],
            range: range,
            start_date: startDate,
            end_date: endDate
        };

        $.post(act_admin_params.ajax_url, ajaxData)
            .done(function(response) {
                if (response.success) {
                    const table = tableContainer.find('table');
                    const emptyMsg = tableContainer.find('.act-table-empty-message');

                    tbody.html(response.data.html);

                    if (response.data.count > 0) {
                        table.show();
                        emptyMsg.hide();
                    } else {
                        table.hide();
                        emptyMsg.show();
                    }
                } else {
                    tbody.html(`<tr><td colspan="10">Error: ${response.data.message || 'Could not load data.'}</td></tr>`);
                }
            }).fail(function() {
                tbody.html(`<tr><td colspan="10">AJAX Error: Request failed.</td></tr>`);
            }).always(function() {
                loadingDiv.hide();
                tableContainer.show();
            });
    }

    $('.act-list-page-filters .act-filter-buttons button').on('click', function() {
        const $button = $(this);
        const filterGroup = $button.closest('.act-list-page-filters');
        const pagePrefix = filterGroup.data('page-prefix');

        if (!pagePrefix || pagePrefix === 'dashboard') { // Do not run on main dashboard page
            return;
        }

        filterGroup.find('.act-filter-buttons button').removeClass('active');
        $button.addClass('active');
        filterGroup.find('.act-date-input').val('');
        fetchEntriesForTable(pagePrefix, $button.data('range'));
    });

    $('.act-list-page-filters .button-primary').on('click', function() {
        const filterGroup = $(this).closest('.act-list-page-filters');
        const pagePrefix = filterGroup.data('page-prefix');

        if (pagePrefix === 'dashboard') {
            return;
        }

        const startDate = filterGroup.find(`#act_${pagePrefix}_start_date`).val();
        const endDate = filterGroup.find(`#act_${pagePrefix}_end_date`).val();

        if (startDate && endDate) {
            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date cannot be after end date.');
                return;
            }
            filterGroup.find('.act-filter-buttons button').removeClass('active');
            fetchEntriesForTable(pagePrefix, 'custom', startDate, endDate);
        } else {
            alert('Please select both a start and end date.');
        }
    });


    // --- Dashboard Specific Script ---
    if ($('#actOrderStatusChart').length) {
        let actChartInstance = null;

        function updateDashboardStats(stats) {
            $('#act-stat-incomplete-count').text(stats.incomplete.count);
            $('#act-stat-incomplete-value').html(stats.incomplete.value_formatted);
            $('#act-stat-recovered-count').text(stats.recovered.count);
            $('#act-stat-recovered-value').html(stats.recovered.value_formatted);
            $('#act-stat-hold-count').text(stats.hold.count);
            $('#act-stat-hold-value').html(stats.hold.value_formatted);
            $('#act-stat-cancelled-count').text(stats.cancelled.count);
            $('#act-stat-cancelled-value').html(stats.cancelled.value_formatted);

            const chartData = {
                labels: [`Incomplete (${stats.incomplete.count})`, `Recovered (${stats.recovered.count})`, `Hold (${stats.hold.count})`, `Cancelled (${stats.cancelled.count})`],
                datasets: [{
                    label: 'Checkout Statuses',
                    data: [stats.incomplete.count, stats.recovered.count, stats.hold.count, stats.cancelled.count],
                    backgroundColor: ['rgba(153, 102, 255, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(255, 99, 132, 0.7)'],
                    borderColor: ['rgba(153, 102, 255, 1)', 'rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(255, 99, 132, 1)'],
                    borderWidth: 1
                }]
            };

            if (actChartInstance) {
                actChartInstance.data = chartData;
                actChartInstance.update();
            } else {
                const ctx = document.getElementById('actOrderStatusChart').getContext('2d');
                actChartInstance = new Chart(ctx, { type: 'pie', data: chartData, options: { responsive: true } });
            }
        }

        function fetchDashboardData(range, startDate = null, endDate = null) {
            $('#act-dashboard-loading').show();
            $('.act-stat-box p').text('...');
            $('.act-dashboard-chart-container, .act-dashboard-counts, .act-dashboard-values').css('opacity', 0.5);

            $.post(act_admin_params.ajax_url, {
                action: 'act_fetch_dashboard_data',
                nonce: act_admin_params.fetch_dashboard_data_nonce,
                range: range,
                start_date: startDate,
                end_date: endDate
            }).done(function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                } else {
                    alert('Error loading dashboard data: ' + (response.data.message || 'Unknown error.'));
                }
            }).fail(function() {
                alert('AJAX error while loading dashboard data.');
            }).always(function() {
                $('#act-dashboard-loading').hide();
                $('.act-dashboard-chart-container, .act-dashboard-counts, .act-dashboard-values').css('opacity', 1);
            });
        }

        const dashboardFilterGroup = $('.act-dashboard-filters');

        dashboardFilterGroup.find('.act-filter-buttons button').on('click', function() {
            const $button = $(this);
            dashboardFilterGroup.find('.act-filter-buttons button').removeClass('active');
            $button.addClass('active');

            dashboardFilterGroup.find('.act-date-input').val('');

            fetchDashboardData($button.data('range'));
        });

        dashboardFilterGroup.find('.act_dashboard_apply_date_filter').on('click', function() {
            const startDate = dashboardFilterGroup.find('#act_dashboard_start_date').val();
            const endDate = dashboardFilterGroup.find('#act_dashboard_end_date').val();

            if (startDate && endDate) {
                if (new Date(startDate) > new Date(endDate)) {
                    alert('Start date cannot be after end date.');
                    return;
                }
                dashboardFilterGroup.find('.act-filter-buttons button').removeClass('active');
                fetchDashboardData('custom', startDate, endDate);
            } else {
                alert('Please select both a start and end date.');
            }
        });

        fetchDashboardData('today');
    }

    // --- Courier Analytics Page Handler ---
    $(document).on('click', '#act_check_ratio_btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $spinner = $button.siblings('.spinner');
        const phoneNumber = $('#act_customer_phone').val();
        const resultsContainer = $('#act-courier-results-container');
        const nonce = $('#act_courier_nonce').val();

        if (!phoneNumber) { alert('Please enter a phone number.'); return; }
        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        resultsContainer.fadeOut(200);

        $.post(act_admin_params.ajax_url, {
                action: 'act_check_courier_success',
                act_courier_nonce: nonce,
                phone_number: phoneNumber,
                order_id: 0
            })
            .done(function(response) {
                if (response.success) {
                    resultsContainer.html(response.data.html).fadeIn(200);
                    initSuccessRatioChart(); // Call the chart function
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'An unknown error occurred.'));
                }
            })
            .fail(function() { alert('An AJAX error occurred.'); })
            .always(function() {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
            });
    });

    // --- Order List Success Ratio Handler ---
    $(document).on('click', '.act-check-order-ratio, .act-refresh-order-ratio', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $container = $button.closest('.act-success-ratio-container');
        const phoneNumber = $button.data('phone');
        const nonce = act_admin_params.order_list_nonce;

        if (!phoneNumber || !nonce) {
            alert('Could not perform check. Phone number or security token is missing.');
            return;
        }

        $container.html('<span class="spinner is-active" style="float:left;"></span>');

        $.post(act_admin_params.ajax_url, {
                action: 'act_check_order_success_ratio',
                act_order_nonce: nonce,
                phone_number: phoneNumber,
                order_id: $container.data('order-id')
            })
            .done(function(response) {
                if (response.success) {
                    $container.html(response.data.html);
                } else {
                    $container.html('<em>Error</em>');
                }
            })
            .fail(function() {
                $container.html('<em>Request Failed</em>');
            });
    });

    // --- Order List Column Handler ---
    $(document).on('click', '.act-check-order-ratio', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $container = $button.closest('.act-success-ratio-container');
        const phoneNumber = $button.data('phone');
        const orderId = $button.data('order-id');
        const nonce = act_admin_params.order_list_nonce;

        if (!phoneNumber || !orderId) { alert('Missing data for check.'); return; }
        $container.html('<span class="spinner is-active" style="float:left;"></span>');

        $.post(act_admin_params.ajax_url, {
                action: 'act_check_order_success_ratio',
                act_order_nonce: nonce,
                phone_number: phoneNumber,
                order_id: orderId
            })
            .done(function(response) {
                if (response.success) {
                    $container.hide().html(response.data.html).fadeIn(300);
                } else { $container.html('<em>Error</em>'); }
            })
            .fail(function() { $container.html('<em>Request Failed</em>'); });
    });

    // --- Single Order Page Meta Box Handler ---
    $(document).on('click', '.act-meta-box-trigger', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $container = $('#act_order_detail_ratio_container');
        const phoneNumber = $button.data('phone');
        const orderId = $button.data('order-id');
        const nonce = act_admin_params.courier_analytics_nonce;

        if (!phoneNumber || !orderId) { alert('Missing data for check.'); return; }
        $container.html('<span class="spinner is-active" style="display:block; margin: 10px auto;"></span>');

        $.post(act_admin_params.ajax_url, {
                action: 'act_check_courier_success',
                act_courier_nonce: nonce,
                phone_number: phoneNumber,
                order_id: orderId
            })
            .done(function(response) {
                if (response.success) {
                    $container.fadeOut(200, function() {
                        $(this).html(response.data.html).fadeIn(200);
                        initSuccessRatioChart(); // Call the chart function
                    });
                } else { $container.html('<p>Error</p>'); }
            })
            .fail(function() { $container.html('<p>AJAX Error</p>'); });
    });


    /**
     * NEW: Function to initialize the doughnut chart for the success ratio.
     */
    function initSuccessRatioChart() {
        const canvas = $('#act-success-ratio-doughnut-chart');
        if (canvas.length === 0 || typeof Chart === 'undefined') {
            return;
        }

        const rate = parseInt(canvas.data('rate'), 10);
        const color = canvas.data('level-color') || '#4CAF50';
        const remaining = 100 - rate;

        const chartData = {
            datasets: [{
                data: [rate, remaining],
                backgroundColor: [color, '#e9ecef'],
                borderWidth: 0,
                cutout: '80%', // Makes the ring thinner
            }]
        };

        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateScale: true,
                animateRotate: true
            },
            plugins: {
                tooltip: {
                    enabled: false // Disable tooltips on hover
                }
            },
            events: [] // Disable all events on the chart
        };

        new Chart(canvas, {
            type: 'doughnut',
            data: chartData,
            options: chartOptions,
        });
    }

    // --- Fraud Blocker Page AJAX Handlers ---
    if ($('.act-fraud-blocker-wrap').length) {



        // Handle live searching/filtering of the lists
        $('.act-blocker-search').on('keyup input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const listId = $(this).data('list-id');
            const $list = $('#' + listId);
            let itemsFound = 0;

            $list.find('li').each(function() {
                const $item = $(this);
                // Ignore the 'no items' message
                if ($item.hasClass('act-no-items')) {
                    return;
                }

                const itemText = $item.find('strong').text().toLowerCase();

                if (itemText.indexOf(searchTerm) > -1) {
                    $item.show();
                    itemsFound++;
                } else {
                    $item.hide();
                }
            });

            // Show a "no results" message if nothing matches
            $list.find('.act-no-results').remove(); // Remove old message first
            if (itemsFound === 0 && $list.find('.act-no-items').length === 0) {
                $list.append('<li class="act-no-results">No items match your search.</li>');
            }
        });

        // Handle ADDING a new blocked item
        $('.act-blocker-form').on('submit', function(e) {
            e.preventDefault();

            const $form = $(this);
            const $button = $form.find('.button-primary');
            const $spinner = $form.find('.spinner');
            const blockType = $form.data('block-type');
            const list = $('#act-blocked-list-' + blockType);
            const messagesDiv = $('#act-blocker-messages');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            const formData = {
                action: 'act_add_blocked_item',
                nonce: $form.find('input[name="nonce"]').val(),
                block_type: blockType,
                value: $form.find('input[name="value"]').val(),
                reason: $form.find('textarea[name="reason"]').val()
            };

            $.post(act_admin_params.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        // Remove the "no items" message if it exists
                        list.find('.act-no-items').remove();
                        // Add the new item to the top of the list
                        list.prepend(response.data.html);
                        // Clear the form
                        $form[0].reset();
                        messagesDiv.removeClass('notice-error').addClass('notice-success').html('<p>Item added successfully.</p>').slideDown().delay(3000).slideUp();
                    } else {
                        messagesDiv.removeClass('notice-success').addClass('notice-error').html('<p>Error: ' + response.data.message + '</p>').slideDown().delay(4000).slideUp();
                    }
                })
                .fail(function(xhr) {
                    let errorMessage = 'An unexpected error occurred. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    messagesDiv.removeClass('notice-success').addClass('notice-error').html('<p>Error: ' + errorMessage + '</p>').slideDown().delay(4000).slideUp();
                })
                .always(function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                });
        });

        // Handle DELETING a blocked item
        $(document).on('click', '.act-delete-item-ajax', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete this item?')) {
                return;
            }

            const $link = $(this);
            const itemID = $link.data('item-id');
            const blockType = $link.data('block-type');
            const nonce = $link.data('nonce');
            const $listItem = $('#act-blocked-item-' + itemID);
            const list = $('#act-blocked-list-' + blockType);
            const messagesDiv = $('#act-blocker-messages');

            $listItem.css('opacity', '0.5');

            const postData = {
                action: 'act_delete_blocked_item',
                nonce: nonce,
                item_id: itemID,
                block_type: blockType
            };

            // We need to use the delete nonce for this specific action
            postData.nonce = act_admin_params.delete_blocker_item_nonce; // We need to add this nonce

            $.post(act_admin_params.ajax_url, {
                    action: 'act_delete_blocked_item',
                    nonce: $link.data('nonce'),
                    item_id: $link.data('item-id'),
                    block_type: $link.data('block-type')
                })
                .done(function(response) {
                    if (response.success) {
                        $listItem.fadeOut(300, function() {
                            $(this).remove();
                            if (list.children().length === 0) {
                                list.html('<li class="act-no-items">No items currently blocked.</li>');
                            }
                        });
                    } else {
                        $listItem.css('opacity', '1');
                        messagesDiv.removeClass('notice-success').addClass('notice-error').html('<p>Error: ' + response.data.message + '</p>').slideDown().delay(4000).slideUp();
                    }
                })
                .fail(function() {
                    $listItem.css('opacity', '1');
                    messagesDiv.removeClass('notice-success').addClass('notice-error').html('<p>An unexpected error occurred. Please try again.</p>').slideDown().delay(4000).slideUp();
                });
        });
    }

    // --- Single Order Page: Meta Box Button Click Handlers ---
    if ($('body').hasClass('post-type-shop_order')) {

        // Handle the click event for the "Block" button
        $(document).on('click', '.act-block-from-order', function(e) {
            e.preventDefault();
            const $button = $(this),
                blockType = $button.data('block-type'),
                value = $button.data('value'),
                nonce = $button.data('nonce');
            $button.prop('disabled', true).html('<span class="spinner is-active" style="float:left; margin: 2px 5px 0 0;"></span> Blocking...');

            $.post(act_admin_params.ajax_url, {
                action: 'act_add_blocked_item',
                nonce,
                block_type: blockType,
                value,
                reason: `Blocked from Order #${$('#post_ID').val()}`
            }).done(function(response) {
                if (response.success) {
                    // ** THE FIX IS HERE: Use the correct nonce for the new Unblock button **
                    const unblockNonce = act_admin_params.delete_blocker_item_nonce;
                    const newUnblockButton = `<button type="button" class="button act-unblock-from-order" data-block-type="${blockType}" data-value="${value}" data-nonce="${unblockNonce}"><span class="dashicons dashicons-unlock"></span> Unblock</button>`;
                    $button.replaceWith(newUnblockButton);
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).html(`<span class="dashicons dashicons-shield-alt"></span> Block`);
                }
            }).fail(function() {
                alert('An unexpected error occurred.');
                $button.prop('disabled', false).html(`<span class="dashicons dashicons-shield-alt"></span> Block`);
            });
        });

        // Handle the click event for the "Unblock" button
        $(document).on('click', '.act-unblock-from-order', function(e) {
            e.preventDefault();
            const $button = $(this),
                blockType = $button.data('block-type'),
                value = $button.data('value'),
                nonce = $button.data('nonce');
            $button.prop('disabled', true).html('<span class="spinner is-active" style="float:left; margin: 2px 5px 0 0;"></span> Unblocking...');

            $.post(act_admin_params.ajax_url, {
                action: 'act_delete_blocked_item',
                nonce,
                block_type: blockType,
                value
            }).done(function(response) {
                if (response.success) {
                    // ** THE FIX IS HERE: Use the correct nonce for the new Block button **
                    const newNonce = act_admin_params.fraud_blocker_nonce;
                    const newBlockButton = `<button type="button" class="button act-block-from-order" data-block-type="${blockType}" data-value="${value}" data-nonce="${newNonce}"><span class="dashicons dashicons-shield-alt"></span> Block</button>`;
                    $button.replaceWith(newBlockButton);
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-unlock"></span> Unblock');
                }
            }).fail(function() {
                alert('An unexpected error occurred.');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-unlock"></span> Unblock');
            });
        });
    }

    // Handle deleting a blocked order log
    $(document).on('click', '.act-delete-blocked-log', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to permanently delete this log entry?')) {
            return;
        }

        const $button = $(this);
        const logId = $button.data('log-id');
        const nonce = $button.data('nonce');
        const $row = $('#log-row-' + logId);

        $button.prop('disabled', true).text('Deleting...');

        $.post(act_admin_params.ajax_url, {
                action: 'act_delete_blocked_log',
                nonce: nonce,
                log_id: logId
            })
            .done(function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + (response.data.message || 'Could not delete log.'));
                    $button.prop('disabled', false).text('Delete');
                }
            })
            .fail(function() {
                alert('An AJAX error occurred.');
                $button.prop('disabled', false).text('Delete');
            });
    });

});