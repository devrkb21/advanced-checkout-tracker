<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Add these two new functions at the top of includes/admin/admin-pages.php

/**
 * Renders the HTML for the lockdown overlay with a dynamic message.
 */
function act_render_lockdown_overlay($message)
{
    ?>
    <div class="act-lockdown-overlay">
        <div class="act-lockdown-message">
            <h2><?php _e('Advanced Checkout Tracker', 'advanced-checkout-tracker'); ?></h2>
            <p><?php echo wp_kses_post($message); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Renders the admin notice for an expired license.
 */
function act_render_expired_notice()
{
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>Advanced Checkout Tracker:</strong> Your license has expired. Your feature limits have been reverted to
            the Free plan. Please renew your license to restore your premium features.</p>
    </div>
    <?php
}

/**
 * Renders the common date filter controls.
 */
function act_render_date_filter_controls($page_slug_prefix, $default_active_range = 'today', $filter_target_field = 'updated_at', $include_all_button = false, $is_follow_up = false)
{
    $filter_id_prefix = 'act_' . $page_slug_prefix;
    ?>
    <div class="act-dashboard-filters act-list-page-filters <?php echo esc_attr($filter_id_prefix . '-filters'); ?>"
        data-page-prefix="<?php echo esc_attr($page_slug_prefix); ?>">
        <div class="act-filter-buttons">
            <?php if ($is_follow_up): ?>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?> active"
                    data-range="today"><?php _e('Due Today', 'advanced-checkout-tracker'); ?></button>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="next7days"><?php _e('Next 7 Days', 'advanced-checkout-tracker'); ?></button>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="next30days"><?php _e('Next 30 Days', 'advanced-checkout-tracker'); ?></button>
            <?php else: ?>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?> active"
                    data-range="today"><?php _e('Today', 'advanced-checkout-tracker'); ?></button>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="yesterday"><?php _e('Yesterday', 'advanced-checkout-tracker'); ?></button>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="7days"><?php _e('Last 7 Days', 'advanced-checkout-tracker'); ?></button>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="30days"><?php _e('Last 30 Days', 'advanced-checkout-tracker'); ?></button>
            <?php endif; ?>

            <?php if ($include_all_button): ?>
                <button class="button <?php echo esc_attr($filter_id_prefix . '-filter-btn'); ?>"
                    data-range="all"><?php _e('All', 'advanced-checkout-tracker'); ?></button>
            <?php endif; ?>
        </div>
        <div class="act-date-range-filter">
            <label
                for="<?php echo esc_attr($filter_id_prefix . '_start_date'); ?>"><?php _e('From:', 'advanced-checkout-tracker'); ?></label>
            <input type="date" id="<?php echo esc_attr($filter_id_prefix . '_start_date'); ?>"
                class="<?php echo esc_attr($filter_id_prefix . '-date-input'); ?>">
            <label
                for="<?php echo esc_attr($filter_id_prefix . '_end_date'); ?>"><?php _e('To:', 'advanced-checkout-tracker'); ?></label>
            <input type="date" id="<?php echo esc_attr($filter_id_prefix . '_end_date'); ?>"
                class="<?php echo esc_attr($filter_id_prefix . '-date-input'); ?>">
            <button
                class="button button-primary <?php echo esc_attr($filter_id_prefix . '_apply_date_filter'); ?>"><?php _e('Filter', 'advanced-checkout-tracker'); ?></button>
        </div>
    </div>
    <?php
}


/**
 * Renders the main Dashboard Overview page.
 */
function act_render_dashboard_overview_page()
{
    $incomplete_url = admin_url('admin.php?page=act-incomplete-checkouts');
    $recovered_url = admin_url('admin.php?page=act-recovered-checkouts');
    $followup_url = admin_url('admin.php?page=act-follow-up');
    $cancelled_url = admin_url('admin.php?page=act-cancelled-checkouts');
    ?>
    <div class="wrap act-dashboard-wrap">
        <h1><?php _e('Checkout Tracker Dashboard', 'advanced-checkout-tracker'); ?></h1>
        <?php act_render_date_filter_controls('dashboard', 'today', 'created_at'); ?>

        <div id="act-dashboard-loading" style="display:none; text-align:center; padding:20px;">
            <p><?php _e('Loading dashboard data...', 'advanced-checkout-tracker'); ?></p>
        </div>

        <?php // This is the new two-column layout container ?>
        <div class="act-dashboard-layout-container">

            <?php // Left Column: Chart ?>
            <div class="act-dashboard-layout-left">
                <div class="act-dashboard-chart-container"><canvas id="actOrderStatusChart"></canvas></div>
            </div>

            <?php // Right Column: Stat Boxes ?>
            <div class="act-dashboard-layout-right">
                <div class="act-dashboard-counts act-stat-row">
                    <a href="<?php echo esc_url($incomplete_url); ?>" class="act-stat-box act-stat-box-link">
                        <h3><?php _e('Incomplete Orders', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-incomplete-count">0</p>
                    </a>
                    <a href="<?php echo esc_url($recovered_url); ?>" class="act-stat-box act-stat-box-link">
                        <h3><?php _e('Recovered Orders', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-recovered-count">0</p>
                    </a>
                    <a href="<?php echo esc_url($followup_url); ?>" class="act-stat-box act-stat-box-link">
                        <h3><?php _e('Hold Orders', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-hold-count">0</p>
                    </a>
                    <a href="<?php echo esc_url($cancelled_url); ?>" class="act-stat-box act-stat-box-link">
                        <h3><?php _e('Cancelled Orders', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-cancelled-count">0</p>
                    </a>
                </div>
                <div class="act-dashboard-values act-stat-row">
                    <div class="act-stat-box">
                        <h3><?php _e('Incomplete Value', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-incomplete-value"><?php echo wc_price(0); ?></p>
                    </div>
                    <div class="act-stat-box">
                        <h3><?php _e('Recovered Value', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-recovered-value"><?php echo wc_price(0); ?></p>
                    </div>
                    <div class="act-stat-box">
                        <h3><?php _e('Hold Value', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-hold-value"><?php echo wc_price(0); ?></p>
                    </div>
                    <div class="act-stat-box">
                        <h3><?php _e('Cancelled Value', 'advanced-checkout-tracker'); ?></h3>
                        <p id="act-stat-cancelled-value"><?php echo wc_price(0); ?></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php
}

/**
 * Renders the page that lists INCOMPLETE checkouts.
 */
function act_render_incomplete_checkouts_page()
{
    $status = get_option('act_license_status', 'inactive');
    $initial_results = []; // Default to empty

    // Only fetch data if the license is not in a lockdown state
    if ($status !== 'inactive' && $status !== 'suspended') {
        $initial_results = act_fetch_entries_by_status_and_date_range('incomplete', 'today', null, null, 'updated_at');
    }
    ?>
    <div class="wrap act-incomplete-wrap">
        <h1><?php _e('Incomplete Checkouts', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('This page lists checkouts that were started but not completed.', 'advanced-checkout-tracker'); ?></p>
        <?php act_render_date_filter_controls('incomplete', 'today', 'updated_at', true); ?>
        <div id="act-incomplete-loading" class="act-table-loader" style="display:none;">
            <div class="act-spinner"></div>
            <p><?php _e('Loading entries...', 'advanced-checkout-tracker'); ?></p>
        </div>
        <div id="act-incomplete-table-container">
            <?php act_render_checkouts_table($initial_results, 'incomplete'); ?>
        </div>
        <?php act_render_details_modal_html(); ?>
    </div>
    <?php
}

/**
 * Renders the page that lists RECOVERED checkouts.
 */
function act_render_recovered_checkouts_page()
{
    $status = get_option('act_license_status', 'inactive');
    $initial_results = [];

    if ($status !== 'inactive' && $status !== 'suspended') {
        $initial_results = act_fetch_entries_by_status_and_date_range('recovered', 'today', null, null, 'updated_at');
    }
    ?>
    <div class="wrap act-recovered-wrap">
        <h1><?php _e('Recovered Checkouts', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('This page lists incomplete checkouts that have been successfully recovered as WooCommerce orders.', 'advanced-checkout-tracker'); ?>
        </p>
        <?php act_render_date_filter_controls('recovered', 'today', 'updated_at', true); ?>
        <div id="act-recovered-loading" class="act-table-loader" style="display:none; text-align:center; padding:20px;">
            <p><?php _e('Loading entries...', 'advanced-checkout-tracker'); ?></p>
        </div>
        <div id="act-recovered-table-container">
            <?php act_render_checkouts_table($initial_results, 'recovered'); ?>
        </div>
        <?php act_render_details_modal_html(); ?>
    </div>
    <?php
}

/**
 * Renders the page that lists HOLD checkouts. This is also the Follow Up page.
 */
function act_render_hold_checkouts_page()
{
    $status = get_option('act_license_status', 'inactive');
    $initial_results = [];

    if ($status !== 'inactive' && $status !== 'suspended') {
        $initial_results = act_fetch_entries_by_status_and_date_range('hold', 'today', null, null, 'follow_up_date');
    }
    ?>
    <div class="wrap act-hold-wrap">
        <h1><?php _e('Follow Up / Hold Checkouts', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('This page lists incomplete checkouts that have been marked as "Hold" for future follow-up, ordered by the follow-up date.', 'advanced-checkout-tracker'); ?>
        </p>
        <?php act_render_date_filter_controls('hold', 'today', 'follow_up_date', true, true); ?>
        <div id="act-hold-loading" class="act-table-loader" style="display:none; text-align:center; padding:20px;">
            <p><?php _e('Loading entries...', 'advanced-checkout-tracker'); ?></p>
        </div>
        <div id="act-hold-table-container">
            <?php act_render_checkouts_table($initial_results, 'hold'); ?>
        </div>
        <?php act_render_details_modal_html(); ?>
    </div>
    <?php
}

/**
 * Renders the page that lists CANCELLED checkouts.
 */
function act_render_cancelled_checkouts_page()
{
    $status = get_option('act_license_status', 'inactive');
    $initial_results = [];

    if ($status !== 'inactive' && $status !== 'suspended') {
        $initial_results = act_fetch_entries_by_status_and_date_range('cancelled', 'today', null, null, 'updated_at');
    }
    ?>
    <div class="wrap act-cancelled-wrap">
        <h1><?php _e('Cancelled Checkouts', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('This page lists incomplete checkouts that have been marked as "Cancelled".', 'advanced-checkout-tracker'); ?>
        </p>
        <?php act_render_date_filter_controls('cancelled', 'today', 'updated_at', true); ?>
        <div id="act-cancelled-loading" class="act-table-loader" style="display:none; text-align:center; padding:20px;">
            <p><?php _e('Loading entries...', 'advanced-checkout-tracker'); ?></p>
        </div>
        <div id="act-cancelled-table-container">
            <?php act_render_checkouts_table($initial_results, 'cancelled'); ?>
        </div>
        <?php act_render_details_modal_html(); ?>
    </div>
    <?php
}

/**
 * The "Follow Up" menu item points here.
 */
function act_render_follow_up_page()
{
    act_render_hold_checkouts_page();
}


/**
 * Renders the common HTML structure for the details modal.
 */
function act_render_details_modal_html()
{
    ?>
    <div id="act-details-modal" class="act-modal" style="display:none;">
        <div class="act-modal-content">
            <span class="act-modal-close">&times;</span>
            <h2><?php _e('Checkout Details', 'advanced-checkout-tracker'); ?></h2>
            <div id="act-modal-body">
                <p><?php _e('Loading details...', 'advanced-checkout-tracker'); ?></p>
            </div>
        </div>
    </div>
    <?php
}


/**
 * Renders the common table structure for different checkout statuses.
 */
function act_render_checkouts_table($results, $current_status = 'incomplete')
{
    $table_id = 'act-table-' . $current_status;
    $tbody_id = 'act-table-body-' . $current_status;
    $empty_message_id = $tbody_id . '-empty-message';
    $is_empty = empty($results);
    ?>
    <div id="<?php echo esc_attr($empty_message_id); ?>" class="act-table-empty-message" <?php if (!$is_empty)
           echo 'style="display:none;"'; ?>>
        <p><?php printf(__('No checkouts found with status "%s" for the selected criteria.', 'advanced-checkout-tracker'), esc_html($current_status)); ?>
        </p>
    </div>
    <?php // START of the change ?>
    <div class="act-table-responsive-wrapper">
        <table class="wp-list-table widefat fixed striped <?php echo esc_attr($table_id); ?>" <?php if ($is_empty)
               echo 'style="display:none;"'; ?>>
            <thead>
                <tr>
                    <th><?php _e('ID', 'advanced-checkout-tracker'); ?></th>

                    <?php // START of Change: Swapped Name and Email ?>
                    <th><?php _e('Name', 'advanced-checkout-tracker'); ?></th>
                    <th><?php _e('Email', 'advanced-checkout-tracker'); ?></th>
                    <?php // END of Change ?>

                    <th><?php _e('Phone', 'advanced-checkout-tracker'); ?></th>
                    <th><?php _e('IP Address', 'advanced-checkout-tracker'); ?></th>
                    <?php if ($current_status === 'hold'): ?>
                        <th><?php _e('Follow-up Date', 'advanced-checkout-tracker'); ?></th>
                    <?php elseif ($current_status === 'recovered'): ?>
                        <th><?php _e('Recovered Order', 'advanced-checkout-tracker'); ?></th>
                    <?php else: ?>
                        <th><?php _e('Address', 'advanced-checkout-tracker'); ?></th>
                    <?php endif; ?>
                    <th><?php _e('Cart Value', 'advanced-checkout-tracker'); ?></th>
                    <th><?php _e('Cart Items', 'advanced-checkout-tracker'); ?></th>
                    <th><?php _e('Last Updated', 'advanced-checkout-tracker'); ?></th>
                    <th style="width: 280px;"><?php _e('Actions', 'advanced-checkout-tracker'); ?></th>
                </tr>
            </thead>
            <tbody id="<?php echo esc_attr($tbody_id); ?>">
                <?php echo act_get_checkout_table_rows_html($results, $current_status); ?>
            </tbody>
        </table>
    </div>
<?php // END of the change ?>
<?php
}


/**
 * Helper function to generate HTML for table rows (tbody content).
 */
function act_get_checkout_table_rows_html($results, $current_status)
{
    ob_start();
    if (!empty($results)) {
        foreach ($results as $row): ?>
            <tr id="act-entry-row-<?php echo esc_attr($row->id); ?>">
                <?php // START of Change: Reordered cells and made Name clickable ?>
                <td><?php echo esc_html($row->id); ?></td>
                <td>
                    <?php
                    $full_name = trim($row->first_name . ' ' . $row->last_name);
                    // If the name is empty, we show a default placeholder
                    if (empty($full_name)) {
                        $full_name = __('(No Name Provided)', 'advanced-checkout-tracker');
                    }
                    ?>
                    <a href="#" class="act-view-details" data-id="<?php echo esc_attr($row->id); ?>"
                        title="<?php printf(esc_attr__('View details for %s', 'advanced-checkout-tracker'), esc_attr($full_name)); ?>">
                        <strong><?php echo esc_html($full_name); ?></strong>
                    </a>
                </td>
                <td><?php echo esc_html($row->email); ?></td>
                <?php // END of Change ?>

                <td><?php echo esc_html($row->phone); ?></td>
                <td><?php echo esc_html($row->ip_address); ?></td>
                <?php if ($current_status === 'hold'): ?>
                    <td class="act-follow-up-date-cell-<?php echo esc_attr($row->id); ?>">
                        <?php echo esc_html($row->follow_up_date ? date_i18n(get_option('date_format'), strtotime($row->follow_up_date)) : __('N/A', 'advanced-checkout-tracker')); ?>
                    </td>
                <?php elseif ($current_status === 'recovered'): ?>
                    <td>
                        <?php
                        if (!empty($row->recovered_order_id)) {
                            $order_edit_link = get_edit_post_link($row->recovered_order_id);
                            if ($order_edit_link) {
                                printf('<a href="%s" target="_blank">#%s</a>', esc_url($order_edit_link), esc_html($row->recovered_order_id));
                            } else {
                                echo '#' . esc_html($row->recovered_order_id);
                            }
                        } else {
                            _e('N/A', 'advanced-checkout-tracker');
                        }
                        ?>
                    </td>
                <?php else: ?>
                    <td>
                        <?php
                        $address_parts = array_filter([
                            $row->address_1,
                            $row->city,
                            $row->postcode,
                        ]);
                        echo esc_html(implode(', ', $address_parts));
                        ?>
                    </td>
                <?php endif; ?>
                <td><?php echo wc_price($row->cart_value); ?></td>
                <td>
                    <?php
                    $cart_items = json_decode($row->cart_details, true);
                    $item_count = is_array($cart_items) ? count($cart_items) : 0;
                    echo $item_count . ' ' . _n('item', 'items', $item_count, 'advanced-checkout-tracker');
                    ?>
                </td>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->updated_at))); ?>
                </td>
                <td class="act-actions-cell">
                    <?php
                    printf('<a href="#" class="button button-small act-view-details" data-id="%d" title="%s"><span class="dashicons dashicons-visibility"></span></a> ', esc_attr($row->id), __('View Details', 'advanced-checkout-tracker'));

                    if ($current_status === 'incomplete') {
                        printf('<a href="#" class="button button-small button-primary act-recover-order" data-id="%d" title="%s"><span class="dashicons dashicons-yes"></span></a> ', esc_attr($row->id), __('Recover Order', 'advanced-checkout-tracker'));
                        printf('<a href="#" class="button button-small act-mark-hold" data-id="%d" title="%s"><span class="dashicons dashicons-clock"></span></a> ', esc_attr($row->id), __('Mark as Hold', 'advanced-checkout-tracker'));
                        printf('<a href="#" class="button button-small act-mark-cancelled" data-id="%d" title="%s"><span class="dashicons dashicons-no-alt"></span></a>', esc_attr($row->id), __('Mark as Cancelled', 'advanced-checkout-tracker'));
                    } elseif ($current_status === 'hold') {
                        printf('<a href="#" class="button button-small button-primary act-recover-order" data-id="%d" title="%s"><span class="dashicons dashicons-yes"></span></a> ', esc_attr($row->id), __('Recover Order', 'advanced-checkout-tracker'));
                        printf('<a href="#" class="button button-small act-edit-follow-up-date" data-id="%d" data-current-date="%s" title="%s"><span class="dashicons dashicons-edit"></span></a> ', esc_attr($row->id), esc_attr($row->follow_up_date), __('Edit Follow-up Date', 'advanced-checkout-tracker'));
                        printf('<a href="#" class="button button-small act-mark-cancelled" data-id="%d" title="%s"><span class="dashicons dashicons-no-alt"></span></a>', esc_attr($row->id), __('Mark as Cancelled', 'advanced-checkout-tracker'));
                    } elseif ($current_status === 'cancelled') {
                        printf('<a href="#" class="button button-small act-reopen-checkout" data-id="%d" title="%s"><span class="dashicons dashicons-undo"></span></a>', esc_attr($row->id), __('Re-open to Incomplete', 'advanced-checkout-tracker'));
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach;
    }
    return ob_get_clean();
}

/**
 * Renders the Fraud Blocker management page (Simplified for AJAX).
 */
function act_render_fraud_blocker_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'advanced-checkout-tracker'));
    }

    // A single nonce for all AJAX forms on this page.
    $ajax_nonce = wp_create_nonce('act_fraud_blocker_nonce');
    ?>
    <div class="wrap act-fraud-blocker-wrap">
        <h1><?php _e('Fraud Blocker Management', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('Manage blocked IP addresses, email addresses, and phone numbers to prevent unwanted orders.', 'advanced-checkout-tracker'); ?>
        </p>

        <?php // This div is used to show success or error messages from AJAX calls. ?>
        <div id="act-blocker-messages" style="display:none;" class="notice is-dismissible"></div>

        <div class="act-blocker-sections">

            <?php // Section 1: IP Blocker ?>
            <div class="act-blocker-section">
                <h2><?php _e('IP Address Blocker', 'advanced-checkout-tracker'); ?></h2>
                <form class="act-blocker-form" data-block-type="ip">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr($ajax_nonce); ?>">
                    <p><label
                            for="act_block_ip"><?php _e('IP Address to Block:', 'advanced-checkout-tracker'); ?></label><br><input
                            type="text" name="value" class="regular-text" placeholder="e.g., 192.168.1.100"></p>
                    <p><label
                            for="act_block_ip_reason"><?php _e('Reason (Optional):', 'advanced-checkout-tracker'); ?></label><br><textarea
                            name="reason" rows="2" class="large-text"></textarea></p>
                    <p><button type="submit"
                            class="button button-primary"><?php _e('Block IP Address', 'advanced-checkout-tracker'); ?></button><span
                            class="spinner"></span></p>
                </form>
                <h3 style="margin-top:20px;"><?php _e('Blocked IP Addresses', 'advanced-checkout-tracker'); ?></h3>
                <p class="act-search-wrapper">
                    <input type="search" class="act-blocker-search" data-list-id="act-blocked-list-ip"
                        placeholder="<?php esc_attr_e('Search IPs...', 'advanced-checkout-tracker'); ?>">
                </p>
                <?php act_display_blocked_items_list('ip'); ?>
            </div>

            <?php // Section 2: Email Blocker ?>
            <div class="act-blocker-section">
                <h2><?php _e('Email Address Blocker', 'advanced-checkout-tracker'); ?></h2>
                <form class="act-blocker-form" data-block-type="email">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr($ajax_nonce); ?>">
                    <p><label
                            for="act_block_email"><?php _e('Email Address to Block:', 'advanced-checkout-tracker'); ?></label><br><input
                            type="email" name="value" class="regular-text" placeholder="e.g., spammer@example.com"></p>
                    <p><label
                            for="act_block_email_reason"><?php _e('Reason (Optional):', 'advanced-checkout-tracker'); ?></label><br><textarea
                            name="reason" rows="2" class="large-text"></textarea></p>
                    <p><button type="submit"
                            class="button button-primary"><?php _e('Block Email Address', 'advanced-checkout-tracker'); ?></button><span
                            class="spinner"></span></p>
                </form>
                <h3 style="margin-top:20px;"><?php _e('Blocked Email Addresses', 'advanced-checkout-tracker'); ?></h3>
                <p class="act-search-wrapper">
                    <input type="search" class="act-blocker-search" data-list-id="act-blocked-list-email"
                        placeholder="<?php esc_attr_e('Search Emails...', 'advanced-checkout-tracker'); ?>">
                </p>
                <?php act_display_blocked_items_list('email'); ?>
            </div>

            <?php // Section 3: Phone Blocker ?>
            <div class="act-blocker-section">
                <h2><?php _e('Phone Number Blocker', 'advanced-checkout-tracker'); ?></h2>
                <form class="act-blocker-form" data-block-type="phone">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr($ajax_nonce); ?>">
                    <p><label
                            for="act_block_phone"><?php _e('Phone Number to Block:', 'advanced-checkout-tracker'); ?></label><br><input
                            type="text" name="value" class="regular-text" placeholder="e.g., +15551234567"></p>
                    <p><label
                            for="act_block_phone_reason"><?php _e('Reason (Optional):', 'advanced-checkout-tracker'); ?></label><br><textarea
                            name="reason" rows="2" class="large-text"></textarea></p>
                    <p><button type="submit"
                            class="button button-primary"><?php _e('Block Phone Number', 'advanced-checkout-tracker'); ?></button><span
                            class="spinner"></span></p>
                </form>
                <h3 style="margin-top:20px;"><?php _e('Blocked Phone Numbers', 'advanced-checkout-tracker'); ?></h3>
                <p class="act-search-wrapper">
                    <input type="search" class="act-blocker-search" data-list-id="act-blocked-list-phone"
                        placeholder="<?php esc_attr_e('Search Phones...', 'advanced-checkout-tracker'); ?>">
                </p>
                <?php act_display_blocked_items_list('phone'); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Helper function to display a list of blocked items.
 * This function now fetches its own data.
 */
function act_display_blocked_items_list($type)
{
    global $wpdb;
    $table_name = '';
    $value_column = '';

    switch ($type) {
        case 'ip':
            $table_name = $wpdb->prefix . 'act_blocked_ips';
            $value_column = 'ip_address';
            break;
        case 'email':
            $table_name = $wpdb->prefix . 'act_blocked_emails';
            $value_column = 'email_address';
            break;
        case 'phone':
            $table_name = $wpdb->prefix . 'act_blocked_numbers';
            $value_column = 'phone_number';
            break;
        default:
            return;
    }

    $items = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");

    echo '<ul class="act-blocked-list" id="act-blocked-list-' . esc_attr($type) . '">';
    if (empty($items)) {
        echo '<li class="act-no-items">' . __('No items currently blocked.', 'advanced-checkout-tracker') . '</li>';
    } else {
        foreach ($items as $item) {
            echo act_get_blocked_list_item_html($item, $value_column, $type);
        }
    }
    echo '</ul>';
}

/**
 * Helper function to generate HTML for a single blocked list item.
 */
function act_get_blocked_list_item_html($item, $value_column, $type)
{
    ob_start();
    $delete_nonce = wp_create_nonce('act_delete_blocked_item_nonce');
    ?>
    <li id="act-blocked-item-<?php echo esc_attr($item->id); ?>">
        <div>
            <strong><?php echo esc_html($item->$value_column); ?></strong>
            <?php if (!empty($item->reason)): ?>
                <small><em><?php echo esc_html($item->reason); ?></em></small>
            <?php endif; ?>
        </div>
        <a href="#" class="act-delete-item-ajax" title="Delete" data-item-id="<?php echo esc_attr($item->id); ?>"
            data-block-type="<?php echo esc_attr($type); ?>" data-nonce="<?php echo esc_attr($delete_nonce); ?>">
            <span class="dashicons dashicons-trash"></span>
        </a>
    </li>
    <?php
    return ob_get_clean();
}

/**
 * Renders the Courier Analytics page.
 */
function act_render_courier_analytics_page()
{
    ?>
    <div class="wrap act-courier-analytics-wrap">
        <h1><?php _e('Courier Success Ratio Checker', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('Enter a customer phone number to check their delivery success and return ratio across different courier services.', 'advanced-checkout-tracker'); ?>
        </p>

        <div class="act-blocker-section">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label
                            for="act_customer_phone"><?php _e('Customer Phone Number', 'advanced-checkout-tracker'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="act_customer_phone" name="act_customer_phone" class="regular-text"
                            placeholder="e.g., 01*********" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <?php wp_nonce_field('act_check_courier_success_nonce', 'act_courier_nonce'); ?>
                <button id="act_check_ratio_btn"
                    class="button button-primary"><?php _e('Check Success Ratio', 'advanced-checkout-tracker'); ?></button>
                <span class="spinner" style="float: none; margin-left: 10px;"></span>
            </p>
        </div>

        <div id="act-courier-results-container" style="margin-top: 20px;">
        </div>
    </div>
    <?php
}
<<<<<<< HEAD
=======

>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
function act_render_blocked_orders_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'act_blocked_orders';

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$table_name}");
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} ORDER BY blocked_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    ?>
    <div class="wrap act-blocked-orders-wrap">
        <h1><?php _e('Blocked Orders Log', 'advanced-checkout-tracker'); ?></h1>
        <p><?php _e('This page lists all checkout attempts that were automatically blocked due to a low success ratio.', 'advanced-checkout-tracker'); ?>
        </p>

        <div class="act-table-responsive-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
<<<<<<< HEAD
                        <th><?php _e('ID', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Name', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Email', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Phone', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('IP Address', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Cart Value', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Cart Items', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Success Ratio (%)', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Blocked At', 'advanced-checkout-tracker'); ?></th>
                        <th style="width: 120px;"><?php _e('Actions', 'advanced-checkout-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody id="act-blocked-orders-tbody">
                    <?php
                    if (empty($results)) {
                        echo '<tr><td colspan="10">' . __('No orders have been blocked yet.', 'advanced-checkout-tracker') . '</td></tr>'; // Changed colspan to 10
                    } else {
                        echo act_get_blocked_orders_table_rows_html($results);
                    }
                    ?>
=======
                        <th><?php _e('Blocked At', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Phone Number', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Email', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Cart Value', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Success Ratio (%)', 'advanced-checkout-tracker'); ?></th>
                        <th><?php _e('Actions', 'advanced-checkout-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody id="act-blocked-orders-tbody">
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No orders have been blocked yet.', 'advanced-checkout-tracker'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $row): ?>
                            <tr id="log-row-<?php echo esc_attr($row->id); ?>">
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->blocked_at))); ?>
                                </td>
                                <td><?php echo esc_html($row->phone_number); ?></td>
                                <td><?php echo esc_html($row->email_address); ?></td>
                                <td><?php echo wc_price($row->cart_value); ?></td>
                                <td>
                                    <span
                                        style="color: red; font-weight: bold;"><?php echo esc_html($row->success_ratio); ?>%</span>
                                    <small>(Threshold: <?php echo esc_html($row->threshold_at_block); ?>%)</small>
                                </td>
                                <td>
                                    <button class="button button-small button-danger act-delete-blocked-log"
                                        data-log-id="<?php echo esc_attr($row->id); ?>"
                                        data-nonce="<?php echo wp_create_nonce('act_delete_blocked_log_nonce'); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
                </tbody>
            </table>
        </div>

        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_items / $per_page);
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ));
                }
                ?>
            </div>
        </div>
<<<<<<< HEAD
        <?php // Add the details modal to the page
            act_render_details_modal_html(); ?>
    </div>
    <?php
}

/**
 * Helper function to generate HTML for blocked order table rows.
 * This version includes safety checks and correct data attributes.
 */
function act_get_blocked_orders_table_rows_html($results)
{
    ob_start();
    if (!empty($results)) {
        foreach ($results as $row) {
            $cart_items = json_decode($row->cart_details, true);
            $item_count = is_array($cart_items) ? count($cart_items) : 0;
            $full_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            ?>
            <tr id="log-row-<?php echo esc_attr($row->id); ?>">
                <td><?php echo esc_html($row->id); ?></td>
                <td>
                    <a href="#" class="act-view-details" data-type="blocked_order" data-log-id="<?php echo esc_attr($row->id); ?>">
                        <strong><?php echo esc_html($full_name ?: '(No Name)'); ?></strong>
                    </a>
                </td>
                <td><?php echo esc_html($row->email_address); ?></td>
                <td><?php echo esc_html($row->phone_number); ?></td>
                <td><?php echo esc_html($row->ip_address); ?></td>
                <td><?php echo wc_price($row->cart_value); ?></td>
                <td><?php echo esc_html($item_count) . ' ' . _n('item', 'items', $item_count, 'advanced-checkout-tracker'); ?></td>
                <td>
                    <span style="color: red; font-weight: bold;"><?php echo esc_html($row->success_ratio); ?>%</span>
                    <small>(Threshold: <?php echo esc_html($row->threshold_at_block); ?>%)</small>
                </td>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->blocked_at))); ?>
                </td>
                <td class="act-actions-cell">
                    <?php
                    printf('<a href="#" class="button button-small act-view-details" data-type="blocked_order" data-log-id="%d" title="%s"><span class="dashicons dashicons-visibility"></span></a> ', esc_attr($row->id), __('View Details', 'advanced-checkout-tracker'));
                    printf('<button class="button button-small button-danger act-delete-blocked-log" data-log-id="%d" data-nonce="%s"><span class="dashicons dashicons-trash"></span></button>', esc_attr($row->id), wp_create_nonce('act_delete_blocked_log_nonce'));
                    ?>
                </td>
            </tr>
            <?php
        }
    }
    return ob_get_clean();
}
=======
    </div>
    <?php
}
>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
