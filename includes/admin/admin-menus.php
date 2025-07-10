<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds the main admin menu and submenu pages for the plugin.
 */
function act_register_admin_menu()
{
    add_menu_page(
        __('Adv. Checkout Tracker', 'advanced-checkout-tracker'),
        __('Checkout Tracker', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-main-dashboard',
        'act_render_dashboard_overview_page',
        'dashicons-chart-line',
        56
    );

    add_submenu_page(
        'act-main-dashboard',
        __('Dashboard Overview', 'advanced-checkout-tracker'),
        __('Dashboard', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-main-dashboard',
        'act_render_dashboard_overview_page'
    );

    add_submenu_page(
        'act-main-dashboard',
        __('Follow Up', 'advanced-checkout-tracker'),
        __('Follow Up', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-follow-up',
        'act_render_follow_up_page'
    );

    add_submenu_page(
        'act-main-dashboard',
        __('Incomplete Checkouts', 'advanced-checkout-tracker'),
        __('Incomplete', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-incomplete-checkouts',
        'act_render_incomplete_checkouts_page'
    );

    add_submenu_page(
        'act-main-dashboard',
        __('Recovered Checkouts', 'advanced-checkout-tracker'),
        __('Recovered', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-recovered-checkouts',
        'act_render_recovered_checkouts_page'
    );

    add_submenu_page('act-main-dashboard', 'Blocked Orders', 'Blocked Orders', 'manage_woocommerce', 'act-blocked-orders', 'act_render_blocked_orders_page');

    add_submenu_page(
        'act-main-dashboard',
        __('Cancelled Checkouts', 'advanced-checkout-tracker'),
        __('Cancelled', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-cancelled-checkouts',
        'act_render_cancelled_checkouts_page'
    );

    add_submenu_page(
        'act-main-dashboard',
        __('Fraud Blocker', 'advanced-checkout-tracker'),
        __('Fraud Blocker', 'advanced-checkout-tracker'),
        'manage_options',
        'act-fraud-blocker',
        'act_render_fraud_blocker_page'
    );
    add_submenu_page(
        'act-main-dashboard',
        __('Courier Analytics', 'advanced-checkout-tracker'),
        __('Courier Analytics', 'advanced-checkout-tracker'),
        'manage_woocommerce',
        'act-courier-analytics',
        'act_render_courier_analytics_page'
    );
    add_submenu_page(
        'act-main-dashboard',
        __('Settings', 'advanced-checkout-tracker'),
        __('Settings', 'advanced-checkout-tracker'),
        'manage_options', // Only admins can change settings
        'act-settings',
        'act_render_settings_page' // This function will be in our new file
    );
}
