<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds the custom widget to the main WordPress dashboard.
 */
function act_register_dashboard_widget()
{
    // Only add the widget for users who can manage WooCommerce (admins, shop managers)
    if (current_user_can('manage_woocommerce')) {
        wp_add_dashboard_widget(
            'act_main_dashboard_widget',
            __('Advanced Checkout Tracker Summary', 'advanced-checkout-tracker'),
            'act_render_main_dashboard_widget_content'
        );
    }
}
add_action('wp_dashboard_setup', 'act_register_dashboard_widget');

/**
 * Renders the content for the custom dashboard widget.
 * Now includes lockdown logic based on license status.
 */
function act_render_main_dashboard_widget_content()
{
    // The #dashboard-widgets-wrap .postbox .inside selector in WordPress has position:relative,
    // which is perfect for our absolute overlay. We add a class for specific styling.
    echo '<div class="act-widget-container-wrapper">';

    $status = get_option('act_license_status', 'inactive');
    // Check for lockdown status
    if ($status === 'inactive' || $status === 'suspended') {
        $whatsapp_link = 'https://chat.whatsapp.com/JGUTBCNqK7d32zHWOQ4wzR';
        $contact_link_start = '<a href="' . esc_url($whatsapp_link) . '" target="_blank">';
        $contact_link_end = '</a>';

        echo '<div class="act-widget-lockdown-overlay">';
        echo '<p>';

        if ($status === 'inactive') {
            printf(
                /* translators: 1: opening <a> tag, 2: closing </a> tag */
                __('Your site is not active. Please %1$scontact us%2$s to get it activated.', 'advanced-checkout-tracker'),
                $contact_link_start,
                $contact_link_end
            );
        } elseif ($status === 'suspended') {
            printf(
                /* translators: 1: opening <a> tag, 2: closing </a> tag */
                __('Your license is suspended. Please %1$scontact us%2$s to solve the issue.', 'advanced-checkout-tracker'),
                $contact_link_start,
                $contact_link_end
            );
        } else {
            // Fallback message if status is neither inactive nor suspended (should not typically happen here)
            _e('There is an issue with your license status.', 'advanced-checkout-tracker');
        }

        echo '</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=act-settings')) . '" class="button button-primary" style="margin-top: 15px;">' . __('Go to Settings', 'advanced-checkout-tracker') . '</a>';
        echo '</div>'; // Close the wrapper
        return;
    }

    // --- If not locked down, render the normal widget content ---
    act_render_date_filter_controls('dashboard', 'today', 'created_at');

    $incomplete_url = admin_url('admin.php?page=act-incomplete-checkouts');
    $recovered_url = admin_url('admin.php?page=act-recovered-checkouts');
    $followup_url = admin_url('admin.php?page=act-follow-up');
    $cancelled_url = admin_url('admin.php?page=act-cancelled-checkouts');
    ?>
    <div id="act-dashboard-loading" style="display:none; text-align:center; padding:20px;">
        <p><?php _e('Loading dashboard data...', 'advanced-checkout-tracker'); ?></p>
    </div>

    <div class="act-dashboard-chart-container" style="max-width:250px;"><canvas id="actOrderStatusChart"></canvas></div>

    <div class="act-dashboard-counts act-stat-row">
        <a href="<?php echo esc_url($incomplete_url); ?>" class="act-stat-box act-stat-box-link">
            <h3><?php _e('Incomplete', 'advanced-checkout-tracker'); ?></h3>
            <p id="act-stat-incomplete-count">...</p>
        </a>
        <a href="<?php echo esc_url($recovered_url); ?>" class="act-stat-box act-stat-box-link">
            <h3><?php _e('Recovered', 'advanced-checkout-tracker'); ?></h3>
            <p id="act-stat-recovered-count">...</p>
        </a>
        <a href="<?php echo esc_url($followup_url); ?>" class="act-stat-box act-stat-box-link">
            <h3><?php _e('On Hold', 'advanced-checkout-tracker'); ?></h3>
            <p id="act-stat-hold-count">...</p>
        </a>
        <a href="<?php echo esc_url($cancelled_url); ?>" class="act-stat-box act-stat-box-link">
            <h3><?php _e('Cancelled', 'advanced-checkout-tracker'); ?></h3>
            <p id="act-stat-cancelled-count">...</p>
        </a>
    </div>

    <?php
    echo '</div>'; // Close the wrapper
}