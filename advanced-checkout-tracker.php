<?php
/*
Plugin Name: Advanced Checkout Tracker
Plugin URI: https://coderzonebd.com/pricing
Description: Tracks incomplete WooCommerce checkouts, provides recovery tools, a dashboard, and fraud blocking.
Version: 1.0.3
Requires Plugins: woocommerce
Author: Coder Zone BD
Author URI: https://coderzonebd.com/
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die('No direct access allowed');
}

// Define Core Plugin Constants
define('ACT_VERSION', '1.0.3');
define('ACT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACT_PLUGIN_FILE', __FILE__);
define('ACT_INC_DIR', ACT_PLUGIN_DIR . 'includes/');
define('ACT_ADMIN_DIR', ACT_INC_DIR . 'admin/');
define('ACT_HEADQUARTERS_URL', 'https://coderzonebd.com');

// Load essential files needed for status checks and admin pages
require_once ACT_INC_DIR . 'utils.php';
require_once ACT_ADMIN_DIR . 'admin-pages.php';
require_once ACT_ADMIN_DIR . 'admin-settings-page.php';

/**
 * Main plugin initialization function.
 */
function act_init_plugin()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'act_woocommerce_missing_notice');
        return;
    }

    $status = get_option('act_license_status', 'inactive');
    $current_page = $_GET['page'] ?? '';
    $is_act_page = strpos($current_page, 'act-') === 0;

    // --- FINAL LOCKDOWN LOGIC WITH EXCEPTION FOR SETTINGS PAGE ---
    $should_lockdown = ($is_act_page && $current_page !== 'act-settings' && ($status === 'inactive' || $status === 'suspended'));

    if ($should_lockdown) {
        // Load assets needed for the overlay and the menu
        require_once ACT_INC_DIR . 'enqueue.php';
        require_once ACT_ADMIN_DIR . 'admin-menus.php';
        add_action('admin_enqueue_scripts', 'act_enqueue_admin_assets');
        add_action('admin_menu', 'act_register_admin_menu');

        // Define the correct message based on the status
        // Define the correct message based on the status
        $whatsapp_link = 'https://chat.whatsapp.com/JGUTBCNqK7d32zHWOQ4wzR';
        $contact_us_html = sprintf(
            /* translators: 1: opening <a> tag, 2: closing </a> tag */
            __('%1$scontact us%2$s', 'advanced-checkout-tracker'),
            '<a href="' . esc_url($whatsapp_link) . '" target="_blank">',
            '</a>'
        );

        $messages = [
            'inactive' => sprintf(
                /* translators: %s: clickable "contact us" text */
                __('Your site is not active. Please %s to get it activated.', 'advanced-checkout-tracker'),
                $contact_us_html
            ),
            'suspended' => sprintf(
                /* translators: %s: clickable "contact us" text */
                __('Your license is suspended. Please %s to solve the issue.', 'advanced-checkout-tracker'),
                $contact_us_html
            ),
        ];
        $lockdown_message = $messages[$status] ?? __('There is an issue with your license status.', 'advanced-checkout-tracker');

        // Hook the function to show the overlay
        add_action('admin_footer', function () use ($lockdown_message) {
            act_render_lockdown_overlay($lockdown_message);
        });
        // Stop loading all other features.
        return;
    }

    // --- If not locked down, proceed to load everything ---

    // Load all remaining plugin files
    require_once ACT_INC_DIR . 'enqueue.php';
    require_once ACT_INC_DIR . 'ajax-handlers.php';
    require_once ACT_INC_DIR . 'checkout-integration.php';
    require_once ACT_ADMIN_DIR . 'admin-menus.php';
    require_once ACT_ADMIN_DIR . 'dashboard-widget.php';
    require_once ACT_ADMIN_DIR . 'woocommerce-integration.php';

    // Add all WordPress action hooks
    add_action('wp_enqueue_scripts', 'act_enqueue_checkout_assets');
    add_action('admin_enqueue_scripts', 'act_enqueue_admin_assets');
    add_action('admin_menu', 'act_register_admin_menu');

    // Add all AJAX hooks
    add_action('wp_ajax_act_save_checkout_data', 'act_handle_save_checkout_data');
    add_action('wp_ajax_nopriv_act_save_checkout_data', 'act_handle_save_checkout_data');
    add_action('wp_ajax_act_get_checkout_details', 'act_get_incomplete_checkout_details_ajax_handler');
    add_action('wp_ajax_act_recover_order', 'act_recover_order_ajax_handler');
    add_action('wp_ajax_act_mark_hold', 'act_mark_hold_ajax_handler');
    add_action('wp_ajax_act_mark_cancelled', 'act_mark_cancelled_ajax_handler');
    add_action('wp_ajax_act_fetch_dashboard_data', 'act_fetch_dashboard_data_ajax_handler');
    add_action('wp_ajax_act_edit_follow_up_date', 'act_edit_follow_up_date_ajax_handler');
    add_action('wp_ajax_act_reopen_checkout', 'act_reopen_checkout_ajax_handler');
    add_action('wp_ajax_act_fetch_follow_up_entries', 'act_fetch_follow_up_entries_ajax_handler');
    add_action('wp_ajax_act_fetch_incomplete_entries', 'act_fetch_incomplete_entries_ajax_handler');
    add_action('wp_ajax_act_fetch_recovered_entries', 'act_fetch_recovered_entries_ajax_handler');
    add_action('wp_ajax_act_fetch_cancelled_entries', 'act_fetch_cancelled_entries_ajax_handler');
    add_action('wp_ajax_act_check_courier_success', 'act_handle_check_courier_success');
    add_action('wp_ajax_act_check_order_success_ratio', 'act_handle_order_list_success_check');
    add_action('wp_ajax_act_add_blocked_item', 'act_handle_add_blocked_item_ajax');
    add_action('wp_ajax_act_delete_blocked_item', 'act_handle_delete_blocked_item_ajax');
    add_action('wp_ajax_act_delete_blocked_log', 'act_handle_delete_blocked_log_ajax');
    add_action('wp_ajax_act_get_blocked_order_details', 'act_get_blocked_order_details_ajax_handler');


    add_action('wp_ajax_act_live_ratio_check', 'act_live_ratio_check_handler');
    add_action('wp_ajax_nopriv_act_live_ratio_check', 'act_live_ratio_check_handler');

    // WooCommerce hooks
    add_action('woocommerce_thankyou', 'act_delete_incomplete_checkout_on_order_completion', 10, 1);
    add_action('woocommerce_checkout_process', 'act_check_customer_against_blocklists', 20);

    // Case 2: Show the 'expired' notice on all admin pages (but still load features)
    if ($status === 'expired') {
        add_action('admin_notices', 'act_render_expired_notice');
    }

    // Schedule the daily sync cron job
    if (!wp_next_scheduled('act_daily_sync_hook')) {
        wp_schedule_event(time() + rand(0, 3600), 'daily', 'act_daily_sync_hook');
    }
}
add_action('plugins_loaded', 'act_init_plugin', 20);

// Hook for the daily sync
add_action('act_daily_sync_hook', 'act_sync_with_hq');

// Activation & Deactivation Hooks
require_once ACT_INC_DIR . 'activation.php';
register_activation_hook(ACT_PLUGIN_FILE, 'act_plugin_activate');
register_deactivation_hook(ACT_PLUGIN_FILE, 'act_plugin_deactivate');



require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/devrkb21/advanced-checkout-tracker',
    __FILE__,
    'unique-plugin-or-theme-slug'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
