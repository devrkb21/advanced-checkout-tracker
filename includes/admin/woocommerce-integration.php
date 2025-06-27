<?php
if (!defined('ABSPATH')) {
    exit;
}

// --- 1. ADD COLUMN TO WOOCOMMERCE ORDERS LIST ---
function act_add_order_success_ratio_column($columns)
{
    $reordered_columns = array();
    foreach ($columns as $key => $column) {
        $reordered_columns[$key] = $column;
        if ($key === 'order_status') {
            $reordered_columns['order_success_ratio'] = __('Success Ratio', 'advanced-checkout-tracker');
        }
    }
    return $reordered_columns;
}
add_filter('manage_edit-shop_order_columns', 'act_add_order_success_ratio_column', 20);
add_filter('manage_woocommerce_page_wc-orders_columns', 'act_add_order_success_ratio_column', 20);

function act_render_order_success_ratio_column_content($column, $order_or_order_id)
{
    if ($column === 'order_success_ratio') {
        $order = ($order_or_order_id instanceof WC_Order) ? $order_or_order_id : wc_get_order($order_or_order_id);
        if (!$order) {
            return;
        }
        $order_id = $order->get_id();
        echo '<div class="act-success-ratio-container" data-order-id="' . esc_attr($order_id) . '">';
        $phone_number = $order->get_billing_phone();
        $saved_ratio_data = get_post_meta($order_id, '_act_success_ratio_data', true);
        if (!empty($saved_ratio_data) && is_array($saved_ratio_data)) {
            echo act_get_compact_ratio_html($saved_ratio_data['grand_total'], $phone_number, $order_id);
        } elseif (!empty($phone_number)) {
            echo '<button type="button" class="button button-small act-check-order-ratio" data-phone="' . esc_attr($phone_number) . '" data-order-id="' . esc_attr($order_id) . '">' . __('Check', 'advanced-checkout-tracker') . '</button>';
        } else {
            echo '<em>' . __('No phone', 'advanced-checkout-tracker') . '</em>';
        }
        echo '</div>';
    }
}
add_action('manage_shop_order_posts_custom_column', 'act_render_order_success_ratio_column_content', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'act_render_order_success_ratio_column_content', 10, 2);


// --- 2. ADD META BOX TO SINGLE ORDER EDIT PAGE ---
function act_add_success_ratio_meta_box()
{
    add_meta_box('act_success_ratio_meta_box_id', __('Customer Success Ratio', 'advanced-checkout-tracker'), 'act_render_success_ratio_meta_box_content', 'shop_order', 'side');
    add_meta_box('act_success_ratio_meta_box_id', __('Customer Success Ratio', 'advanced-checkout-tracker'), 'act_render_success_ratio_meta_box_content', 'woocommerce_page_wc-orders', 'side');
}
add_action('add_meta_boxes', 'act_add_success_ratio_meta_box');

function act_render_success_ratio_meta_box_content($post_or_order_object)
{
    if ($post_or_order_object instanceof WP_Post) {
        $order = wc_get_order($post_or_order_object->ID);
    } elseif ($post_or_order_object instanceof WC_Order) {
        $order = $post_or_order_object;
    } else {
        $order = isset($_GET['id']) ? wc_get_order((int) $_GET['id']) : null;
    }
    if (!$order) {
        echo '<p>' . __('Could not load order data.', 'advanced-checkout-tracker') . '</p>';
        return;
    }

    $phone_number = $order->get_billing_phone();
    $order_id = $order->get_id();
    echo '<div id="act_order_detail_ratio_container">';
    if (empty($phone_number)) {
        echo '<p>' . __('No billing phone number found.', 'advanced-checkout-tracker') . '</p>';
    } else {
        $saved_results = get_post_meta($order_id, '_act_success_ratio_data', true);
        if (!empty($saved_results) && is_array($saved_results)) {
            echo act_get_courier_success_html($saved_results, $phone_number);
        } else {
            echo '<p style="margin-top:0;">Check the full delivery history.</p>';
            echo '<button type="button" class="button button-primary act-meta-box-trigger" data-phone="' . esc_attr($phone_number) . '" data-order-id="' . esc_attr($order_id) . '">' . __('Check Full Ratio', 'advanced-checkout-tracker') . '</button>';
        }
    }
    echo '</div>';
}
// This first function registers our new meta box with WooCommerce.
function act_add_fraud_blocker_meta_box()
{
    add_meta_box(
        'act_fraud_blocker_meta_box',
        __('Fraud Blocker Actions', 'advanced-checkout-tracker'),
        'act_render_fraud_blocker_meta_box_content',
        ['shop_order', 'woocommerce_page_wc-orders'], // Show on classic and HPOS order screens
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'act_add_fraud_blocker_meta_box');

/**
 * Renders the content inside our Fraud Blocker meta box.
 * Now shows "Unblock" buttons.
 */
function act_render_fraud_blocker_meta_box_content($post_or_order_object)
{
    if ($post_or_order_object instanceof WP_Post) {
        $order = wc_get_order($post_or_order_object->ID);
    } else {
        $order = $post_or_order_object;
    }

    if (!$order || !current_user_can('manage_woocommerce')) {
        return;
    }

    global $wpdb;
    $ip_address = $order->get_customer_ip_address();
    $email = $order->get_billing_email();
    $phone = $order->get_billing_phone();

    // Create nonces for our AJAX actions
    $block_nonce = wp_create_nonce('act_fraud_blocker_nonce');
    $unblock_nonce = wp_create_nonce('act_delete_blocked_item_nonce');
    ?>
    <div class="act-order-blocker">
        <?php // --- IP Address Row --- ?>
        <div class="act-order-blocker-row">
            <div class="act-order-blocker-label"><strong>IP:</strong> <?php echo esc_html($ip_address ?: 'N/A'); ?></div>
            <div class="act-order-blocker-action">
                <?php
                $is_blocked = $ip_address ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}act_blocked_ips WHERE ip_address = %s", $ip_address)) : false;
                if ($is_blocked) {
                    echo '<button type="button" class="button act-unblock-from-order" data-block-type="ip" data-value="' . esc_attr($ip_address) . '" data-nonce="' . esc_attr($unblock_nonce) . '"><span class="dashicons dashicons-unlock"></span> Unblock</button>';
                } elseif ($ip_address) {
                    echo '<button type="button" class="button act-block-from-order" data-block-type="ip" data-value="' . esc_attr($ip_address) . '" data-nonce="' . esc_attr($block_nonce) . '"><span class="dashicons dashicons-shield-alt"></span> Block</button>';
                }
                ?>
            </div>
        </div>

        <?php // --- Email Address Row --- ?>
        <div class="act-order-blocker-row">
            <div class="act-order-blocker-label"><strong>Email:</strong> <?php echo esc_html($email ?: 'N/A'); ?></div>
            <div class="act-order-blocker-action">
                <?php
                $is_blocked = $email ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}act_blocked_emails WHERE email_address = %s", $email)) : false;
                if ($is_blocked) {
                    echo '<button type="button" class="button act-unblock-from-order" data-block-type="email" data-value="' . esc_attr($email) . '" data-nonce="' . esc_attr($unblock_nonce) . '"><span class="dashicons dashicons-unlock"></span> Unblock</button>';
                } elseif ($email) {
                    echo '<button type="button" class="button act-block-from-order" data-block-type="email" data-value="' . esc_attr($email) . '" data-nonce="' . esc_attr($block_nonce) . '"><span class="dashicons dashicons-shield-alt"></span> Block</button>';
                }
                ?>
            </div>
        </div>

        <?php // --- Phone Number Row --- ?>
        <div class="act-order-blocker-row">
            <div class="act-order-blocker-label"><strong>Phone:</strong> <?php echo esc_html($phone ?: 'N/A'); ?></div>
            <div class="act-order-blocker-action">
                <?php
                $is_blocked = $phone ? $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}act_blocked_numbers WHERE phone_number = %s", $phone)) : false;
                if ($is_blocked) {
                    echo '<button type="button" class="button act-unblock-from-order" data-block-type="phone" data-value="' . esc_attr($phone) . '" data-nonce="' . esc_attr($unblock_nonce) . '"><span class="dashicons dashicons-unlock"></span> Unblock</button>';
                } elseif ($phone) {
                    echo '<button type="button" class="button act-block-from-order" data-block-type="phone" data-value="' . esc_attr($phone) . '" data-nonce="' . esc_attr($block_nonce) . '"><span class="dashicons dashicons-shield-alt"></span> Block</button>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * ADD THIS ENTIRE NEW FUNCTION
 *
 * Checks customer's success ratio during checkout and blocks if below the threshold.
 */
function act_block_order_by_success_ratio()
{
    $options = get_option('act_plugin_options', []);
    $is_enabled = isset($options['enable_courier_service']) && $options['enable_courier_service'];
    $threshold = isset($options['ratio_blocker_threshold']) ? (int) $options['ratio_blocker_threshold'] : 20;
    $grace_period = isset($options['ratio_blocker_grace_period']) ? (int) $options['ratio_blocker_grace_period'] : 5;

    if (!$is_enabled || empty($_POST['billing_phone'])) {
        return;
    }

    $phone_number = sanitize_text_field(wp_unslash($_POST['billing_phone']));
    $ratio_data = act_perform_all_api_calls($phone_number, 0);

    if (is_wp_error($ratio_data)) {
        return;
    }

    $total_orders = (int) ($ratio_data['grand_total']['total'] ?? 0);
    $success_rate = (int) ($ratio_data['grand_total']['rate'] ?? 100);

    if ($total_orders > $grace_period && $success_rate <= $threshold) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'act_blocked_orders';
        $cart = WC()->cart;
        $cart_details_array = [];
        if ($cart && !$cart->is_empty()) {
            foreach ($cart->get_cart() as $cart_item) {
                $_product = $cart_item['data'];
                if ($_product) {
                    $cart_details_array[] = ['name' => $_product->get_name(), 'quantity' => (int) $cart_item['quantity']];
                }
            }
        }

        $wpdb->insert($table_name, ['blocked_at' => current_time('mysql'), 'phone_number' => $phone_number, 'email_address' => sanitize_email($_POST['billing_email'] ?? ''), 'cart_details' => wp_json_encode($cart_details_array), 'cart_value' => (float) $cart->get_total('edit'), 'success_ratio' => $success_rate, 'threshold_at_block' => $threshold, 'ip_address' => WC_Geolocation::get_ip_address()]);

        // **THE FIX**: Use the custom message from settings
        $default_message = 'Your courier success ratio is {ratio}%, which is too low. Please contact us to complete your order.';
        $custom_message = $options['ratio_blocker_message'] ?? $default_message;
        $final_message = str_replace('{ratio}', $success_rate, $custom_message);

        // This notice will now be caught by our JavaScript and shown in a popup
        wc_add_notice($final_message, 'error');
    }
}
// Add the new check with a later priority to run after other validation
add_action('woocommerce_checkout_process', 'act_block_order_by_success_ratio', 30);

