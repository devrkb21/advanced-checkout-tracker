<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// ====================================================================
// NEW HELPER FUNCTIONS FOR USAGE & LIMITS
// ====================================================================

/**
 * Checks if a user can perform a limited action based on their plan.
 *
 * @param string $action_type 'checkouts', 'fraud_blocks', or 'courier_checks'.
 * @return bool True if the user is within their limits, false otherwise.
 */
function act_can_perform_action($action_type)
{
    $config = get_transient('act_api_cache');
    if (!$config || !isset($config['limits'][$action_type]) || !isset($config['usage'][$action_type])) {
        return false; // Fail safe if config isn't loaded
    }
    $limit = (int) $config['limits'][$action_type];
    $current_usage = (int) $config['usage'][$action_type];
    return $limit === 0 || $current_usage < $limit;
}

function act_increment_usage_count($action_type)
{
    // This function remains for other actions like checkout tracking
    $config = get_transient('act_api_cache');
    if ($config && isset($config['usage'][$action_type])) {
        $config['usage'][$action_type]++;
        set_transient('act_api_cache', $config, DAY_IN_SECONDS);
    }
    act_call_hq_api('/sites/increment-usage', ['action_type' => $action_type]);
}


// For Courier Analytics Page & Single Order Meta Box (returns FULL TABLE)
function act_handle_check_courier_success()
{
    // ** THE FIX: The redundant increment call has been REMOVED from this function. **
    // The main HQ application will now be the only place that increments the count.

    check_ajax_referer('act_check_courier_success_nonce', 'act_courier_nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission Denied.']);
    }

    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    // We still check if the user *can* perform the action based on the last-known data.
    if (!act_can_perform_action('courier_checks')) {
        wp_send_json_error(['message' => 'You have reached your monthly limit for courier API checks. Please upgrade your plan.'], 403);
        return;
    }

    $results = act_perform_all_api_calls($phone_number, $order_id);
    $html_output = act_get_courier_success_html($results, $phone_number);
    wp_send_json_success(['html' => $html_output]);
}

// For Orders List Column (returns COMPACT BAR)
function act_handle_order_list_success_check()
{
    // This function correctly did not have the increment call, so no changes are needed here.
    check_ajax_referer('act_check_order_success_ratio_nonce', 'act_order_nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission Denied.']);
    }
    $phone_number = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $results = act_perform_all_api_calls($phone_number, $order_id);
    $html_output = act_get_compact_ratio_html($results['grand_total'], $phone_number, $order_id);
    wp_send_json_success(['html' => $html_output]);
}


// ====================================================================
// CHECKOUT TRACKING
// ====================================================================

function act_handle_save_checkout_data()
{
    check_ajax_referer('act_save_checkout_data_nonce', 'nonce');

    if (WC()->session) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        $session_id = WC()->session->get_customer_id();
    } else {
        wp_send_json_error(['message' => 'WooCommerce session not available.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $existing_record_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE session_id = %s",
        $session_id
    ));

    // ** FIX: Only check limits if this is a NEW checkout **
    if (!$existing_record_id && !act_can_perform_action('checkouts')) {
        wp_send_json_error(['message' => 'Usage limit reached.'], 403);
        return;
    }

    $posted_data = array_map('sanitize_text_field', $_POST);
    $data_to_save = [
        'email' => isset($posted_data['billing_email']) ? sanitize_email($posted_data['billing_email']) : '',
        'first_name' => $posted_data['billing_first_name'] ?? '',
        'last_name' => $posted_data['billing_last_name'] ?? '',
        'phone' => $posted_data['billing_phone'] ?? '',
        'address_1' => $posted_data['billing_address_1'] ?? '',
        'address_2' => $posted_data['billing_address_2'] ?? '',
        'city' => $posted_data['billing_city'] ?? '',
        'state' => $posted_data['billing_state'] ?? '',
        'postcode' => $posted_data['billing_postcode'] ?? '',
        'country' => $posted_data['billing_country'] ?? '',
        'ip_address' => WC_Geolocation::get_ip_address(),
        'session_id' => $session_id,
        'updated_at' => current_time('mysql'),
    ];

    $user_id = get_current_user_id();
    if ($user_id) {
        $data_to_save['user_id'] = $user_id;
    }

    $cart_details_array = [];
    $cart_total_value = 0.00;
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $_product = $cart_item['data'];
            if ($_product && is_a($_product, 'WC_Product')) {
                $cart_details_array[] = [
                    'product_id' => $_product->get_id(),
                    'name' => $_product->get_name(),
                    'quantity' => (int) $cart_item['quantity'],
                    'line_total' => (float) $cart_item['line_subtotal'],
                ];
                $cart_total_value += (float) $cart_item['line_subtotal'];
            }
        }
    }
    $data_to_save['cart_details'] = wp_json_encode($cart_details_array);
    $data_to_save['cart_value'] = $cart_total_value;


    if ($existing_record_id) {
        $result = $wpdb->update(
            $table_name,
            $data_to_save,
            ['id' => $existing_record_id],
            act_get_db_formats($data_to_save),
            ['%d']
        );
        if ($result !== false) {
            wp_send_json_success(['message' => 'Checkout data updated.', 'record_id' => $existing_record_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to update checkout data.', 'db_error' => $wpdb->last_error]);
        }
    } else {
        $data_to_save['created_at'] = current_time('mysql');
        $data_to_save['status'] = 'incomplete';
        $result = $wpdb->insert(
            $table_name,
            $data_to_save,
            act_get_db_formats($data_to_save)
        );
        if ($result !== false) {
            // ** FIX: Usage is now only incremented when a NEW record is created. **
            act_increment_usage_count('checkouts');
            wp_send_json_success(['message' => 'Checkout data saved.', 'record_id' => $wpdb->insert_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to save new checkout data.', 'db_error' => $wpdb->last_error]);
        }
    }

    wp_die();
}

/**
 * Handles the AJAX request to get incomplete checkout details.
 */
function act_get_incomplete_checkout_details_ajax_handler()
{
    check_ajax_referer('act_view_details_nonce', 'nonce');

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry) {
        wp_send_json_error(array('message' => __('Entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    $html_output = '<div class="act-entry-details">';
    $html_output .= '<p><strong>' . __('ID:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->id) . '</p>';
    $html_output .= '<p><strong>' . __('Email:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->email) . '</p>';
    $html_output .= '<p><strong>' . __('First Name:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->first_name) . '</p>';
    $html_output .= '<p><strong>' . __('Last Name:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->last_name) . '</p>';
    $html_output .= '<p><strong>' . __('Phone:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->phone) . '</p>';
    $html_output .= '<p><strong>' . __('IP Address:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->ip_address) . '</p>';

    if ($entry->status === 'recovered' && !empty($entry->recovered_order_id)) {
        $order_edit_link = get_edit_post_link($entry->recovered_order_id);
        if ($order_edit_link) {
            $html_output .= '<p><strong>' . __('Recovered WC Order:', 'advanced-checkout-tracker') . '</strong> <a href="' . esc_url($order_edit_link) . '" target="_blank">#' . esc_html($entry->recovered_order_id) . '</a></p>';
        } else {
            $html_output .= '<p><strong>' . __('Recovered WC Order ID:', 'advanced-checkout-tracker') . '</strong> ' . esc_html($entry->recovered_order_id) . '</p>';
        }
    }

    $html_output .= '<h3>' . __('Billing Address', 'advanced-checkout-tracker') . '</h3>';
    $html_output .= '<p>' . esc_html($entry->address_1) . '<br>';
    if ($entry->address_2)
        $html_output .= esc_html($entry->address_2) . '<br>';
    $html_output .= esc_html($entry->city) . ', ' . esc_html($entry->state) . ' ' . esc_html($entry->postcode) . '<br>';
    $html_output .= esc_html($entry->country) . '</p>';

    $html_output .= '<h3>' . __('Cart Details', 'advanced-checkout-tracker') . '</h3>';
    $html_output .= '<p><strong>' . __('Cart Value:', 'advanced-checkout-tracker') . '</strong> ' . wc_price($entry->cart_value) . '</p>';

    $cart_items = json_decode($entry->cart_details, true);
    if (is_array($cart_items) && !empty($cart_items)) {
        $html_output .= '<ul>';
        foreach ($cart_items as $item) {
            $html_output .= '<li>' . esc_html($item['name']) . ' (Qty: ' . esc_html($item['quantity']) . ') - ' . wc_price($item['line_total']) . '</li>';
        }
        $html_output .= '</ul>';
    } else {
        $html_output .= '<p>' . __('No cart items found.', 'advanced-checkout-tracker') . '</p>';
    }

    $html_output .= '<p><strong>' . __('Status:', 'advanced-checkout-tracker') . '</strong> ' . esc_html(ucfirst($entry->status)) . '</p>';
    if ($entry->status === 'hold' && $entry->follow_up_date) {
        $html_output .= '<p><strong>' . __('Follow-up Date:', 'advanced-checkout-tracker') . '</strong> ' . esc_html(date_i18n(get_option('date_format'), strtotime($entry->follow_up_date))) . '</p>';
    }
    $html_output .= '<p><strong>' . __('Captured On:', 'advanced-checkout-tracker') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at))) . '</p>';
    $html_output .= '<p><strong>' . __('Last Updated:', 'advanced-checkout-tracker') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->updated_at))) . '</p>';
    if ($entry->admin_notes) {
        $html_output .= '<h3>' . __('Admin Notes:', 'advanced-checkout-tracker') . '</h3>';
        $html_output .= '<p>' . nl2br(esc_html($entry->admin_notes)) . '</p>';
    }
    $html_output .= '</div>';

    wp_send_json_success(array('html' => $html_output));
    wp_die();
}

/**
 * Handles the AJAX request to recover an incomplete checkout as a WooCommerce order.
 */
function act_recover_order_ajax_handler()
{
    check_ajax_referer('act_recover_order_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'advanced-checkout-tracker')));
        return;
    }

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry) {
        wp_send_json_error(array('message' => __('Incomplete checkout entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    if (!in_array($entry->status, array('incomplete', 'hold'))) {
        wp_send_json_error(array('message' => __('This entry cannot be recovered. Current status: ', 'advanced-checkout-tracker') . $entry->status));
        return;
    }

    try {
        $order_data = array(
            'status' => 'wc-processing',
            'customer_id' => $entry->user_id ? $entry->user_id : 0,
        );
        $order = wc_create_order($order_data);

        if (is_wp_error($order)) {
            throw new Exception(__('Error creating WooCommerce order: ', 'advanced-checkout-tracker') . $order->get_error_message());
        }

        $billing_address = array(
            'first_name' => $entry->first_name,
            'last_name' => $entry->last_name,
            'company' => '',
            'email' => $entry->email,
            'phone' => $entry->phone,
            'address_1' => $entry->address_1,
            'address_2' => $entry->address_2,
            'city' => $entry->city,
            'state' => $entry->state,
            'postcode' => $entry->postcode,
            'country' => $entry->country,
        );
        $order->set_address($billing_address, 'billing');

        $cart_items = json_decode($entry->cart_details, true);
        if (is_array($cart_items) && !empty($cart_items)) {
            foreach ($cart_items as $item_data) {
                $product = wc_get_product($item_data['variation_id'] ? $item_data['variation_id'] : $item_data['product_id']);
                if ($product) {
                    $order->add_product($product, $item_data['quantity']);
                }
            }
        } else {
            throw new Exception(__('No cart items found in the incomplete checkout entry.', 'advanced-checkout-tracker'));
        }

        $order->calculate_totals();
        $order_id = $order->save();

        $order->add_order_note(
            sprintf(__('Order created from Incomplete Checkout ID %d by %s. Related WC Order ID: #%d', 'advanced-checkout-tracker'), $entry_id, wp_get_current_user()->display_name, $order_id),
            false,
            true
        );

        $update_data = array(
            'status' => 'recovered',
            'recovered_order_id' => $order_id,
            'updated_at' => current_time('mysql'),
            'admin_notes' => trim($entry->admin_notes . "\n" . sprintf(__('Recovered as WC Order #%d on %s.', 'advanced-checkout-tracker'), $order_id, current_time('mysql')))
        );

        $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $entry_id),
            act_get_db_formats($update_data),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => sprintf(__('Order #%s created successfully from incomplete checkout ID %d and set to Processing.', 'advanced-checkout-tracker'), $order_id, $entry_id),
            'order_id' => $order_id,
            'edit_order_url' => admin_url('post.php?post=' . $order_id . '&action=edit')
        ));

    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
    wp_die();
}

/**
 * Handles the AJAX request to mark an incomplete checkout as 'hold'.
 */
function act_mark_hold_ajax_handler()
{
    check_ajax_referer('act_mark_hold_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'advanced-checkout-tracker')));
        return;
    }

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    if (!isset($_POST['follow_up_date'])) {
        wp_send_json_error(array('message' => __('Follow-up date not provided.', 'advanced-checkout-tracker')));
        return;
    }

    $follow_up_date_str = sanitize_text_field($_POST['follow_up_date']);
    $follow_up_date = null;

    if (!empty($follow_up_date_str)) {
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $follow_up_date_str)) {
            wp_send_json_error(array('message' => __('Invalid date format. Please use<y_bin_46>-MM-DD.', 'advanced-checkout-tracker')));
            return;
        }
        try {
            $date_obj = new DateTime($follow_up_date_str);
            $follow_up_date = $date_obj->format('Y-m-d');
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Invalid date provided.', 'advanced-checkout-tracker')));
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry_status) {
        wp_send_json_error(array('message' => __('Incomplete checkout entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    if ($entry_status !== 'incomplete') {
        wp_send_json_error(array('message' => __('This entry is not marked as incomplete. Current status: ', 'advanced-checkout-tracker') . $entry_status));
        return;
    }

    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'hold',
            'follow_up_date' => $follow_up_date,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $entry_id),
        array('%s', '%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => sprintf(__('Entry ID %d marked as "Hold" with follow-up date: %s.', 'advanced-checkout-tracker'), $entry_id, ($follow_up_date ? $follow_up_date : 'Not Set'))
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to mark entry as hold.', 'advanced-checkout-tracker'), 'db_error' => $wpdb->last_error));
    }
    wp_die();
}

/**
 * Handles the AJAX request to mark an incomplete checkout as 'cancelled'.
 */
function act_mark_cancelled_ajax_handler()
{
    check_ajax_referer('act_mark_cancelled_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'advanced-checkout-tracker')));
        return;
    }

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry_status) {
        wp_send_json_error(array('message' => __('Incomplete checkout entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    if (!in_array($entry_status, array('incomplete', 'hold'))) {
        wp_send_json_error(array('message' => __('This entry cannot be cancelled. Current status: ', 'advanced-checkout-tracker') . $entry_status));
        return;
    }

    $result = $wpdb->update(
        $table_name,
        array(
            'status' => 'cancelled',
            'updated_at' => current_time('mysql')
        ),
        array('id' => $entry_id),
        array('%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => sprintf(__('Entry ID %d marked as "Cancelled".', 'advanced-checkout-tracker'), $entry_id)
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to mark entry as cancelled.', 'advanced-checkout-tracker'), 'db_error' => $wpdb->last_error));
    }
    wp_die();
}

/**
 * Handles the AJAX request to edit the follow-up date for a 'hold' entry.
 */
function act_edit_follow_up_date_ajax_handler()
{
    check_ajax_referer('act_edit_follow_up_date_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'advanced-checkout-tracker')));
        return;
    }

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    if (!isset($_POST['new_follow_up_date'])) {
        wp_send_json_error(array('message' => __('New follow-up date not provided.', 'advanced-checkout-tracker')));
        return;
    }

    $new_follow_up_date_str = sanitize_text_field(wp_unslash($_POST['new_follow_up_date']));
    $new_follow_up_date_for_db = null;

    if (!empty($new_follow_up_date_str)) {
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $new_follow_up_date_str)) {
            wp_send_json_error(array('message' => __('Invalid date format. Please use<y_bin_46>-MM-DD or leave empty to clear.', 'advanced-checkout-tracker')));
            return;
        }
        try {
            $date_obj = new DateTime($new_follow_up_date_str);
            $new_follow_up_date_for_db = $date_obj->format('Y-m-d');
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Invalid date provided after format check.', 'advanced-checkout-tracker')));
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry) {
        wp_send_json_error(array('message' => __('Entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    if ($entry->status !== 'hold') {
        wp_send_json_error(array('message' => __('This entry is not marked as "Hold". Cannot edit follow-up date.', 'advanced-checkout-tracker')));
        return;
    }

    $result = $wpdb->update(
        $table_name,
        array(
            'follow_up_date' => $new_follow_up_date_for_db,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $entry_id),
        array('%s', '%s'),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => __('Follow-up date updated successfully.', 'advanced-checkout-tracker'),
            'new_date_formatted' => $new_follow_up_date_for_db ? date_i18n(get_option('date_format'), strtotime($new_follow_up_date_for_db)) : __('N/A', 'advanced-checkout-tracker')
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to update follow-up date.', 'advanced-checkout-tracker'), 'db_error' => $wpdb->last_error));
    }
    wp_die();
}

/**
 * Handles the AJAX request to re-open a 'cancelled' checkout back to 'incomplete'.
 */
function act_reopen_checkout_ajax_handler()
{
    check_ajax_referer('act_reopen_checkout_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'advanced-checkout-tracker')));
        return;
    }

    if (!isset($_POST['entry_id']) || !is_numeric($_POST['entry_id'])) {
        wp_send_json_error(array('message' => __('Invalid ID provided.', 'advanced-checkout-tracker')));
        return;
    }
    $entry_id = intval($_POST['entry_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $entry = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$table_name} WHERE id = %d", $entry_id));

    if (!$entry) {
        wp_send_json_error(array('message' => __('Entry not found.', 'advanced-checkout-tracker')));
        return;
    }

    if ($entry->status !== 'cancelled') {
        wp_send_json_error(array('message' => __('This entry is not marked as "Cancelled". Cannot re-open.', 'advanced-checkout-tracker')));
        return;
    }

    $update_data = array(
        'status' => 'incomplete',
        'follow_up_date' => null,
        'recovered_order_id' => null,
        'updated_at' => current_time('mysql')
    );

    $formats = array('%s', null, null, '%s');

    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $entry_id),
        $formats,
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => sprintf(__('Entry ID %d has been re-opened to "Incomplete".', 'advanced-checkout-tracker'), $entry_id)
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to re-open entry.', 'advanced-checkout-tracker'), 'db_error' => $wpdb->last_error));
    }
    wp_die();
}


/**
 * Handles the AJAX request to fetch dashboard data.
 */
function act_fetch_dashboard_data_ajax_handler()
{
    check_ajax_referer('act_fetch_dashboard_data_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'advanced-checkout-tracker')));
        return;
    }

    $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'today';
    $start_date_str = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : null;
    $end_date_str = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;

    $results = act_fetch_entries_by_status_and_date_range(null, $range, $start_date_str, $end_date_str, 'created_at', true);

    $stats = array(
        'incomplete' => array('count' => 0, 'value' => 0.00),
        'recovered' => array('count' => 0, 'value' => 0.00),
        'hold' => array('count' => 0, 'value' => 0.00),
        'cancelled' => array('count' => 0, 'value' => 0.00),
    );

    if ($results) {
        foreach ($results as $row) {
            if (isset($stats[$row->status])) {
                $stats[$row->status]['count'] = (int) $row->count;
                $stats[$row->status]['value'] = (float) $row->total_value;
            }
        }
    }

    foreach ($stats as $status_key => $data) {
        $stats[$status_key]['value_formatted'] = wc_price($data['value']);
    }

    wp_send_json_success($stats);
    wp_die();
}


/**
 * Core function to fetch entries based on status and date range.
 */
function act_fetch_entries_by_status_and_date_range($status, $range, $start_date_str, $end_date_str, $date_field_to_filter = 'updated_at', $group_for_dashboard = false)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'act_incomplete_checkouts';
    $where_clauses = array();

    // Always add status clause if not grouping for the dashboard
    if ($status !== null && !$group_for_dashboard) {
        $where_clauses[] = $wpdb->prepare("status = %s", $status);
    }

    $current_time = current_time('timestamp');
    $today_date = date('Y-m-d', $current_time);

    if ($range === 'custom' && $start_date_str && $end_date_str) {
        if (
            preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $start_date_str) &&
            preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $end_date_str)
        ) {

            $where_clauses[] = $wpdb->prepare("DATE({$date_field_to_filter}) >= %s", $start_date_str);
            $where_clauses[] = $wpdb->prepare("DATE({$date_field_to_filter}) <= %s", $end_date_str);
        } else {
            $range = 'all';
        }
    }

    if ($range !== 'custom' && $range !== 'all') {
        $start_range_date = $today_date;
        $end_range_date = $today_date;

        switch ($range) {
            case 'yesterday':
                $start_range_date = date('Y-m-d', strtotime('-1 day', $current_time));
                $end_range_date = $start_range_date;
                break;
            case '7days':
                $start_range_date = date('Y-m-d', strtotime('-6 days', $current_time));
                break;
            case '30days':
                $start_range_date = date('Y-m-d', strtotime('-29 days', $current_time));
                break;
            case 'next7days':
                $end_range_date = date('Y-m-d', strtotime('+6 days', $current_time));
                break;
            case 'next30days':
                $end_range_date = date('Y-m-d', strtotime('+29 days', $current_time));
                break;
            case 'today':
            default:
                // Dates are already set to today
                break;
        }
        $where_clauses[] = $wpdb->prepare("DATE({$date_field_to_filter}) BETWEEN %s AND %s", $start_range_date, $end_range_date);
    }

    $where_sql_string = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    if ($group_for_dashboard) {
        // For dashboard, we apply date filtering but group by status
        $query = "SELECT status, COUNT(*) as count, SUM(cart_value) as total_value FROM {$table_name} {$where_sql_string} GROUP BY status";
        return $wpdb->get_results($query);
    } else {
        $order_by = ($date_field_to_filter === 'follow_up_date' && $status === 'hold') ? 'follow_up_date ASC, updated_at DESC' : 'updated_at DESC';
        $query = "SELECT * FROM {$table_name} {$where_sql_string} ORDER BY {$order_by}";
        return $wpdb->get_results($query);
    }
}

/**
 * [FIXED] Generic AJAX handler for fetching and returning table rows for a given status.
 */
function act_generic_fetch_entries_ajax_handler($status, $nonce_action, $date_field_to_filter)
{
    check_ajax_referer($nonce_action, 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('Permission denied.', 'advanced-checkout-tracker')]);
        return;
    }

    $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'all';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;

    $results = act_fetch_entries_by_status_and_date_range($status, $range, $start_date, $end_date, $date_field_to_filter);

    $html = act_get_checkout_table_rows_html($results, $status);
    $count = count($results);

    wp_send_json_success(['html' => $html, 'count' => $count]);
    wp_die();
}

// Hook the generic handler to specific actions
function act_fetch_incomplete_entries_ajax_handler()
{
    act_generic_fetch_entries_ajax_handler('incomplete', 'act_fetch_incomplete_nonce', 'updated_at');
}
function act_fetch_recovered_entries_ajax_handler()
{
    act_generic_fetch_entries_ajax_handler('recovered', 'act_fetch_recovered_nonce', 'updated_at');
}
function act_fetch_cancelled_entries_ajax_handler()
{
    act_generic_fetch_entries_ajax_handler('cancelled', 'act_fetch_cancelled_nonce', 'updated_at');
}
function act_fetch_follow_up_entries_ajax_handler()
{
    act_generic_fetch_entries_ajax_handler('hold', 'act_fetch_follow_up_nonce', 'follow_up_date');
}


// --- HELPER FUNCTIONS ---

function act_get_compact_ratio_html($ratio_data, $phone_number, $order_id)
{
    $total_deliveries = (int) ($ratio_data['total'] ?? 0);
    $total_success = (int) ($ratio_data['success'] ?? 0);
    $total_cancelled = $total_deliveries - $total_success;
    $success_rate = (int) ($ratio_data['rate'] ?? 0);
    ob_start(); ?>
    <div class="act-ratio-display">
        <div class="act-ratio-stats">
            All: <?php echo esc_html($total_deliveries); ?>
            <span class="act-ratio-success">Success: <?php echo esc_html($total_success); ?></span>
            <span class="act-ratio-cancel">Cancel: <?php echo esc_html($total_cancelled); ?></span>
        </div>
        <div class="act-ratio-bar-bg">
            <div class="act-ratio-bar-fg" style="width: <?php echo esc_attr($success_rate); ?>%;">
                <?php if ($success_rate > 15) {
                    echo esc_html($success_rate) . '%';
                } ?>
            </div>
        </div>
        <button type="button" class="button-link act-check-order-ratio" data-phone="<?php echo esc_attr($phone_number); ?>"
            data-order-id="<?php echo esc_attr($order_id); ?>">
            <span class="dashicons dashicons-update"></span>
        </button>
    </div>
    <?php return ob_get_clean();
}
/**
 * Calls the secure HQ proxy and returns the REAL, aggregated data.
 */
function act_perform_all_api_calls($phone_number, $order_id)
{
    $results = act_call_hq_api('/proxy/courier-check', ['phone_number' => $phone_number]);

    if (is_wp_error($results)) {
        return [
            'grand_total' => ['rate' => 0, 'total' => 0, 'success' => 0, 'cancelled' => 0],
            'level' => ['name' => 'API Error', 'color' => '#dc3545'],
            'pathao' => ['error' => true, 'total' => 0, 'success' => 0, 'cancelled' => 0],
            'steadfast' => ['error' => true, 'total' => 0, 'success' => 0, 'cancelled' => 0],
            'redx' => ['error' => true, 'total' => 0, 'success' => 0, 'cancelled' => 0],
        ];
    }

    $results['order_id'] = $order_id; // Add order_id for context

    if ($order_id > 0) {
        update_post_meta($order_id, '_act_success_ratio_data', $results);
    }

    return $results;
}

/**
 * Helper function to generate the HTML for the responsive courier success display.
 */
/**
 * Helper function to generate the HTML for the responsive courier success display.
 */
function act_get_courier_success_html($results, $phone_number)
{
    // Courier logos
    $logos = [
        'pathao' => ACT_PLUGIN_URL . 'assets/images/pathao.svg',
        'steadfast' => ACT_PLUGIN_URL . 'assets/images/steadfast.svg',
        'redx' => ACT_PLUGIN_URL . 'assets/images/redx.svg'
    ];

    ob_start();
    ?>
    <div class="act-redesign-container">
        <div class="act-redesign-left">
            <div class="act-ratio-circle-wrap">
                <canvas id="act-success-ratio-doughnut-chart" width="160" height="160"
                    data-rate="<?php echo esc_attr($results['grand_total']['rate']); ?>"
                    data-level-color="<?php echo esc_attr($results['level']['color']); ?>"></canvas>
                <div class="act-ratio-circle-text">
                    <div class="act-ratio-circle-percent"><?php echo esc_html($results['grand_total']['rate']); ?>%</div>
                    <div class="act-ratio-circle-label" style="color: <?php echo esc_attr($results['level']['color']); ?>;">
                        <?php echo esc_html($results['level']['name']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="act-redesign-right">
            <div class="act-redesign-header">
                <h3>Results for: <?php echo esc_html($phone_number); ?></h3>
                <?php if (!empty($results['order_id'])): ?>
                    <button type="button" class="button button-secondary act-meta-box-trigger act-redesign-refresh"
                        data-phone="<?php echo esc_attr($phone_number); ?>"
                        data-order-id="<?php echo esc_attr($results['order_id'] ?? 0); ?>">
                        <span class="dashicons dashicons-update"></span> Refresh
                    </button>
                <?php endif; ?>
            </div>

            <div class="act-summary-boxes">
                <div class="act-summary-box"><span
                        class="act-summary-value"><?php echo esc_html($results['grand_total']['total']); ?></span><span
                        class="act-summary-label">Total Orders</span></div>
                <div class="act-summary-box"><span class="act-summary-value"
                        style="color: #28a745;"><?php echo esc_html($results['grand_total']['success']); ?></span><span
                        class="act-summary-label">Total Delivered</span></div>
                <div class="act-summary-box"><span class="act-summary-value"
                        style="color: #dc3545;"><?php echo esc_html($results['grand_total']['cancelled']); ?></span><span
                        class="act-summary-label">Total Canceled</span></div>
            </div>

            <div class="act-redesigned-table-wrapper">
                <table class="act-redesigned-table">
                    <thead>
                        <tr>
                            <th class="act-col-courier">Courier</th>
                            <th class="act-col-data">Orders</th>
                            <th class="act-col-data">Delivered</th>
                            <th class="act-col-data">Canceled</th>
                            <th class="act-col-rate">Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="act-col-courier"><img src="<?php echo esc_url($logos['pathao']); ?>" alt="Pathao">
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['pathao']['error'] ? 'N/A' : esc_html($results['pathao']['total']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['pathao']['error'] ? 'N/A' : esc_html($results['pathao']['success']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['pathao']['error'] ? '<em>Failed</em>' : esc_html($results['pathao']['cancelled']); ?>
                            </td>
                            <td class="act-col-rate">
                                <?php echo $results['pathao']['total'] > 0 ? esc_html(round(($results['pathao']['success'] / $results['pathao']['total']) * 100)) . '%' : 'N/A'; ?>
                            </td>
                        </tr>
                        <?php // THIS IS THE CORRECTED ROW FOR STEADFAST ?>
                        <tr>
                            <td class="act-col-courier"><img src="<?php echo esc_url($logos['steadfast']); ?>"
                                    alt="Steadfast"></td>
                            <td class="act-col-data">
                                <?php echo $results['steadfast']['error'] ? 'N/A' : esc_html($results['steadfast']['total']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['steadfast']['error'] ? 'N/A' : esc_html($results['steadfast']['success']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['steadfast']['error'] ? '<em>Failed</em>' : esc_html($results['steadfast']['cancelled']); ?>
                            </td> <?php // THIS CELL WAS MISSING ?>
                            <td class="act-col-rate">
                                <?php echo $results['steadfast']['total'] > 0 ? esc_html(round(($results['steadfast']['success'] / $results['steadfast']['total']) * 100)) . '%' : 'N/A'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="act-col-courier"><img src="<?php echo esc_url($logos['redx']); ?>" alt="RedX"></td>
                            <td class="act-col-data">
                                <?php echo $results['redx']['error'] ? 'N/A' : esc_html($results['redx']['total']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['redx']['error'] ? 'N/A' : esc_html($results['redx']['success']); ?>
                            </td>
                            <td class="act-col-data">
                                <?php echo $results['redx']['error'] ? '<em>Failed</em>' : esc_html($results['redx']['cancelled']); ?>
                            </td>
                            <td class="act-col-rate">
                                <?php echo $results['redx']['total'] > 0 ? esc_html(round(($results['redx']['success'] / $results['redx']['total']) * 100)) . '%' : 'N/A'; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Handles adding a blocked item via AJAX.
 */
function act_handle_add_blocked_item_ajax()
{
    $block_type = isset($_POST['block_type']) ? sanitize_key($_POST['block_type']) : '';
    $action_type = 'fraud_' . $block_type . 's';
    // --- LIMIT CHECK ---
    if (!act_can_perform_action($action_type)) {
        wp_send_json_error(['message' => 'You have reached your limit for adding new items to the blocklist. Please upgrade your plan.'], 403);
        return;
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'act_fraud_blocker_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'advanced-checkout-tracker')]);
        return;
    }

    $block_type = isset($_POST['block_type']) ? sanitize_key($_POST['block_type']) : '';
    $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
    $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

    global $wpdb;
    $table_name = '';
    $column_name = '';

    switch ($block_type) {
        case 'ip':
            $table_name = $wpdb->prefix . 'act_blocked_ips';
            $column_name = 'ip_address';
            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                wp_send_json_error(['message' => __('Invalid IP address format.', 'advanced-checkout-tracker')]);
                return;
            }
            break;
        case 'email':
            $table_name = $wpdb->prefix . 'act_blocked_emails';
            $column_name = 'email_address';
            if (!empty($value) && !is_email($value)) {
                wp_send_json_error(['message' => __('Invalid email address format.', 'advanced-checkout-tracker')]);
                return;
            }
            break;
        case 'phone':
            $table_name = $wpdb->prefix . 'act_blocked_numbers';
            $column_name = 'phone_number';
            break;
        default:
            wp_send_json_error(['message' => __('Invalid block type.', 'advanced-checkout-tracker')]);
            return;
    }

    if (empty($value)) {
        wp_send_json_error(['message' => __('Value cannot be empty.', 'advanced-checkout-tracker')]);
        return;
    }

    // Check if already exists
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE {$column_name} = %s", $value));
    if ($existing) {
        wp_send_json_error(['message' => sprintf(__('This %s is already in the blocklist.', 'advanced-checkout-tracker'), $block_type)]);
        return;
    }

    $data_to_insert = [
        $column_name => $value,
        'reason' => $reason,
        'added_by' => get_current_user_id(),
        'created_at' => current_time('mysql')
    ];

    $inserted = $wpdb->insert($table_name, $data_to_insert);

    if ($inserted) {
        act_increment_usage_count($action_type);
        $new_item_id = $wpdb->insert_id;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $new_item_id));
        $html = act_get_blocked_list_item_html($item, $column_name, $block_type);
        wp_send_json_success(['html' => $html]);
    } else {
        wp_send_json_error(['message' => __('Database error.', 'advanced-checkout-tracker')]);
    }
}


/**
 * Handles deleting a blocked item via AJAX by its value.
 */
function act_handle_delete_blocked_item_ajax()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'act_delete_blocked_item_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'advanced-checkout-tracker')]);
        return;
    }

    global $wpdb;
    $block_type = isset($_POST['block_type']) ? sanitize_key($_POST['block_type']) : '';
    $action_type = 'fraud_' . $block_type . 's';
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $value_to_delete = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';

    $table_name = '';
    $column_name = '';
    switch ($block_type) {
        case 'ip':
            $table_name = $wpdb->prefix . 'act_blocked_ips';
            $column_name = 'ip_address';
            break;
        case 'email':
            $table_name = $wpdb->prefix . 'act_blocked_emails';
            $column_name = 'email_address';
            break;
        case 'phone':
            $table_name = $wpdb->prefix . 'act_blocked_numbers';
            $column_name = 'phone_number';
            break;
        default:
            wp_send_json_error(['message' => __('Invalid block type.', 'advanced-checkout-tracker')]);
            return;
    }

    $deleted = false;
    // Prioritize deleting by ID if it's provided (from the main Fraud Blocker page)
    if ($item_id > 0) {
        $deleted = $wpdb->delete($table_name, ['id' => $item_id], ['%d']);
    }
    // Otherwise, delete by value (from the order meta box "Unblock" button)
    elseif (!empty($value_to_delete)) {
        $deleted = $wpdb->delete($table_name, [$column_name => $value_to_delete], ['%s']);
    } else {
        wp_send_json_error(['message' => __('No item ID or value provided for deletion.', 'advanced-checkout-tracker')]);
        return;
    }

    if ($deleted !== false) {
        // --- THIS IS THE NEW LOGIC ---
        // Decrement the fraud_blocks count on the HQ server.
        act_call_hq_api('/sites/decrement-usage', ['action_type' => $action_type]);

        // Also, immediately update the local transient for a faster UI response.
        $config = get_transient('act_api_cache');
        if ($config && isset($config['usage']['fraud_blocks']) && $config['usage']['fraud_blocks'] > 0) {
            $config['usage']['fraud_blocks']--;
            set_transient('act_api_cache', $config, DAY_IN_SECONDS);
        }
        // --- END OF NEW LOGIC ---
        wp_send_json_success(['message' => __('Item removed from blocklist.', 'advanced-checkout-tracker')]);
    } else {
        wp_send_json_error(['message' => __('Could not remove item from the database.', 'advanced-checkout-tracker')]);
    }
}

/**
 * ADD THIS ENTIRE NEW FUNCTION
 * * Handles the live AJAX request from the checkout page to get the success ratio.
 */
function act_live_ratio_check_handler()
{
    check_ajax_referer('act_live_ratio_check_nonce', 'nonce');

    if (empty($_POST['phone_number'])) {
        wp_send_json_error();
        return;
    }

    $phone_number = sanitize_text_field($_POST['phone_number']);

    // IMPORTANT: This call should NOT increment usage. We let the proxy handle that.
    $ratio_data = act_perform_all_api_calls($phone_number, 0);

    if (is_wp_error($ratio_data) || !isset($ratio_data['grand_total']['rate'])) {
        wp_send_json_error();
        return;
    }

    // Only return the rate to keep the response small and fast
    wp_send_json_success(['rate' => $ratio_data['grand_total']['rate']]);
    wp_die();
}

/**
 * Handles deleting a single blocked order log entry via AJAX.
 */
function act_handle_delete_blocked_log_ajax()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'act_delete_blocked_log_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'advanced-checkout-tracker')]);
        return;
    }

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => __('Permission denied.', 'advanced-checkout-tracker')]);
        return;
    }

    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    if (!$log_id) {
        wp_send_json_error(['message' => __('Invalid Log ID.', 'advanced-checkout-tracker')]);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'act_blocked_orders';
    $deleted = $wpdb->delete($table_name, ['id' => $log_id], ['%d']);

    if ($deleted) {
        wp_send_json_success(['message' => __('Log entry deleted successfully.', 'advanced-checkout-tracker')]);
    } else {
        wp_send_json_error(['message' => __('Could not delete the log entry.', 'advanced-checkout-tracker')]);
    }
}