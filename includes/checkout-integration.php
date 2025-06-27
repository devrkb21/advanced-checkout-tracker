<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deletes the incomplete checkout entry when an order is successfully placed.
 */
function act_delete_incomplete_checkout_on_order_completion($order_id)
{
    if (!$order_id) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $session_id_to_delete = null;
    if (WC()->session && WC()->session->get_customer_id()) {
        $session_id_to_delete = WC()->session->get_customer_id();
    }

    if ($session_id_to_delete) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'act_incomplete_checkouts';

        $wpdb->delete(
            $table_name,
            array('session_id' => $session_id_to_delete),
            array('%s')
        );
    }
}

/**
 * Checks customer details against blocklists during checkout processing.
 */
function act_check_customer_against_blocklists()
{
    global $wpdb;

    // 1. Get Customer's IP Address
    $customer_ip = WC_Geolocation::get_ip_address();
    if ($customer_ip) {
        $table_blocked_ips = $wpdb->prefix . 'act_blocked_ips';
        $is_ip_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_blocked_ips} WHERE ip_address = %s LIMIT 1",
            $customer_ip
        ));
        if ($is_ip_blocked) {
            wc_add_notice(__('Your order cannot be processed at this time. Please contact support if you believe this is an error (Ref: IP).', 'advanced-checkout-tracker'), 'error');
            return;
        }
    }

    // 2. Get Customer's Email from POST data
    if (isset($_POST['billing_email'])) {
        $customer_email = sanitize_email(wp_unslash($_POST['billing_email']));
        if (!empty($customer_email) && is_email($customer_email)) {
            $table_blocked_emails = $wpdb->prefix . 'act_blocked_emails';
            $is_email_blocked = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_blocked_emails} WHERE email_address = %s LIMIT 1",
                $customer_email
            ));
            if ($is_email_blocked) {
                wc_add_notice(__('Your order cannot be processed at this time. Please contact support if you believe this is an error (Ref: Email).', 'advanced-checkout-tracker'), 'error');
                return;
            }
        }
    }

    // 3. Get Customer's Phone from POST data
    if (isset($_POST['billing_phone'])) {
        $customer_phone = sanitize_text_field(wp_unslash($_POST['billing_phone']));
        if (!empty($customer_phone)) {
            $table_blocked_numbers = $wpdb->prefix . 'act_blocked_numbers';
            $is_phone_blocked = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_blocked_numbers} WHERE phone_number = %s LIMIT 1",
                $customer_phone
            ));
            if ($is_phone_blocked) {
                wc_add_notice(__('Your order cannot be processed at this time. Please contact support if you believe this is an error (Ref: Phone).', 'advanced-checkout-tracker'), 'error');
                return;
            }
        }
    }
}

/**
 * Adds the modal HTML to the page footer on the checkout page.
 */
function act_add_checkout_modal_html()
{
    if (is_checkout() && !is_order_received_page()) {
        echo '<div id="act-checkout-notice-modal" class="act-checkout-modal" style="display:none;"><div class="act-checkout-modal-content"><span class="act-checkout-modal-close">&times;</span><div id="act-checkout-modal-body"></div></div></div>';
    }
}
add_action('wp_footer', 'act_add_checkout_modal_html');