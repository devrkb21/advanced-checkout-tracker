<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deletes the incomplete checkout entry and decrements the HQ usage count when an order is successfully placed.
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

        // Check if a record exists to be deleted.
        $record_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE session_id = %s",
            $session_id_to_delete
        ));

        if ($record_exists) {
            $deleted = $wpdb->delete(
                $table_name,
                array('session_id' => $session_id_to_delete),
                array('%s')
            );

            // If the local record was successfully deleted, send the decrement call.
            if ($deleted) {
                act_decrement_usage_count('checkouts');
            }
        }
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

function act_display_blocked_order_notice()
{
    // Check if we are on the checkout page and if a session exists.
    if (!function_exists('is_checkout') || !is_checkout() || !function_exists('WC') || !WC()->session) {
        return;
    }

    // Check for our custom session variable.
    $blocked_message = WC()->session->get('act_blocked_order_notice');

    if (!empty($blocked_message)) {
        // Unset the session variable so the notice doesn't show up again on refresh.
        WC()->session->set('act_blocked_order_notice', null);

        // Echo the custom styled notice.
        ?>
        <style>
            .act-checkout-error-notice {
                background: linear-gradient(135deg, #e53935, #b71c1c);
                color: #ffffff;
                text-align: center;
                font-size: 1.2em;
                font-weight: 600;
                border-radius: 8px;
                padding: 20px 25px;
                margin: 0 0 2em 0;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }

            .act-checkout-error-notice::before {
                content: '\\e016';
                /* WooCommerce alert icon */
                font-family: 'WooCommerce';
                font-size: 1.6em;
                line-height: 1;
            }
        </style>
        <div class="act-checkout-error-notice">
            <?php echo wp_kses_post($blocked_message); ?>
        </div>
        <script>
            // Optional: Hide the default WooCommerce error list if it still appears.
            document.addEventListener('DOMContentLoaded', function () {
                var defaultError = document.querySelector('.woocommerce-error, .woocommerce-NoticeGroup-checkout');
                if (defaultError) {
                    defaultError.style.display = 'none';
                }
            });
        </script>
        <?php
    }
}
// Hook high on the checkout form to display our message.
add_action('woocommerce_before_checkout_form', 'act_display_blocked_order_notice', 5);
