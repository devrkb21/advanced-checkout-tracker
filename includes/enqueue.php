<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues scripts and styles for the FRONT-END checkout page.
 */
function act_enqueue_checkout_assets()
{
    if (function_exists('is_checkout') && is_checkout() && !is_order_received_page() && !is_checkout_pay_page()) {
        wp_enqueue_script(
            'act-checkout-tracker',
            ACT_PLUGIN_URL . 'assets/js/tracker.js',
            array('jquery'),
            ACT_VERSION,
            true
        );
        wp_enqueue_style('act-frontend-styles', ACT_PLUGIN_URL . 'assets/css/frontend-styles.css', [], ACT_VERSION);


        wp_localize_script(
            'act-checkout-tracker',
            'act_checkout_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'save_data_nonce' => wp_create_nonce('act_save_checkout_data_nonce'),
                'live_ratio_check_nonce' => wp_create_nonce('act_live_ratio_check_nonce'),
                'tracked_fields' => apply_filters('act_tracked_checkout_fields', array(
                    '#billing_first_name',
                    '#billing_last_name',
                    '#billing_company',
                    '#billing_country',
                    '#billing_address_1',
                    '#billing_address_2',
                    '#billing_city',
                    '#billing_state',
                    '#billing_postcode',
                    '#billing_phone',
                    '#billing_email',
                    '#shipping_first_name',
                    '#shipping_last_name',
                    '#shipping_company',
                    '#shipping_country',
                    '#shipping_address_1',
                    '#shipping_address_2',
                    '#shipping_city',
                    '#shipping_state',
                    '#shipping_postcode',
                    '#order_comments'
                ))
            )
        );
    }
}

/**
 * Enqueues scripts and styles for the plugin's ADMIN pages.
 */
function act_enqueue_admin_assets($hook_suffix)
{
    global $post; // Make the global $post object available

    $screen = get_current_screen();
    if (!$screen) {
        return;
    }

    $is_act_page = (strpos($screen->id, 'act-main-dashboard') !== false || strpos($screen->id, '_page_act-') !== false);
    $is_main_dashboard = ($screen->id === 'dashboard');
    $is_shop_order_list_page = ($screen->id === 'edit-shop_order' || ($screen->id === 'woocommerce_page_wc-orders' && ($_GET['action'] ?? '') !== 'edit'));
    $is_single_order_page = ($screen->id === 'shop_order' || ($screen->id === 'woocommerce_page_wc-orders' && ($_GET['action'] ?? '') === 'edit'));
    $is_courier_analytics_page = ($screen->id === 'checkout-tracker_page_act-courier-analytics');

    if ($is_act_page || $is_main_dashboard || $is_shop_order_list_page || $is_single_order_page) {

        wp_enqueue_style(
            'act-admin-styles',
            ACT_PLUGIN_URL . 'assets/css/admin-styles.css',
            array(),
            time()
        );

        $dependencies = array('jquery');

        if ($screen->id === 'toplevel_page_act-main-dashboard' || $screen->id === 'dashboard' || $is_single_order_page || $is_courier_analytics_page) {
            // Corrected: Use ACT_PLUGIN_URL to ensure full path for assets
            wp_enqueue_script('chart-js', ACT_PLUGIN_URL . 'assets/js/chart.min.js', array(), '3.9.1', true);
            $dependencies[] = 'chart-js';
        }

        wp_enqueue_script('act-admin-tracker', ACT_PLUGIN_URL . 'assets/js/admin-tracker.js', $dependencies, time(), true);

        $params_array = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'fraud_blocker_nonce' => wp_create_nonce('act_fraud_blocker_nonce'),
            'delete_blocker_item_nonce' => wp_create_nonce('act_delete_blocked_item_nonce'),
            'order_list_nonce' => wp_create_nonce('act_check_order_success_ratio_nonce'),
            'courier_analytics_nonce' => wp_create_nonce('act_check_courier_success_nonce'),
            'view_details_nonce' => wp_create_nonce('act_view_details_nonce'),
            'recover_order_nonce' => wp_create_nonce('act_recover_order_nonce'),
            'mark_hold_nonce' => wp_create_nonce('act_mark_hold_nonce'),
            'mark_cancelled_nonce' => wp_create_nonce('act_mark_cancelled_nonce'),
            'fetch_dashboard_data_nonce' => wp_create_nonce('act_fetch_dashboard_data_nonce'),
            'edit_follow_up_date_nonce' => wp_create_nonce('act_edit_follow_up_date_nonce'),
            'reopen_checkout_nonce' => wp_create_nonce('act_reopen_checkout_nonce'),
            'fetch_incomplete_nonce' => wp_create_nonce('act_fetch_incomplete_nonce'),
            'fetch_recovered_nonce' => wp_create_nonce('act_fetch_recovered_nonce'),
            'fetch_cancelled_nonce' => wp_create_nonce('act_fetch_cancelled_nonce'),
            'fetch_follow_up_nonce' => wp_create_nonce('act_fetch_follow_up_nonce'),
            'live_ratio_check_nonce' => wp_create_nonce('act_live_ratio_check_nonce'),

        );

        if ($is_single_order_page) {
            global $wpdb;

            // --- THIS IS THE CORRECTED, ROBUST WAY TO GET THE ORDER ID ---
            $order_id = 0;
            if (isset($post->ID)) {
                $order_id = $post->ID;
            } elseif (isset($_GET['post'])) {
                $order_id = intval($_GET['post']);
            } elseif (isset($_GET['id'])) { // For HPOS compatibility
                $order_id = intval($_GET['id']);
            }
            // --- END OF CORRECTION ---

            $is_blocked = false;
            if ($order_id > 0) {
                $ip_address = get_post_meta($order_id, '_customer_ip_address', true);
                if ($ip_address) {
                    $result = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}act_blocked_ips WHERE ip_address = %s",
                        $ip_address
                    ));
                    if ($result) {
                        $is_blocked = true;
                    }
                }
            }
            $params_array['is_ip_blocked'] = $is_blocked;
        }

        wp_localize_script(
            'act-admin-tracker',
            'act_admin_params',
            $params_array
        );
    }
}














