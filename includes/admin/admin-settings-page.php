<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetches the latest configuration from the Headquarters API.
 */
function act_fetch_hq_configuration()
{
    $response = act_call_hq_api('/sites/status', [], 'GET');
    if (is_wp_error($response)) {
        return false;
    }
    return $response;
}

/**
 * Fetches data from HQ, updates local settings, and caches the result.
 */
function act_sync_with_hq()
{
    $config = act_fetch_hq_configuration();

    if ($config && is_array($config) && isset($config['status'])) {
        update_option('act_license_status', $config['status']);
        set_transient('act_api_cache', $config, DAY_IN_SECONDS);

        if (isset($config['settings'])) {
            $hq_settings = (array) $config['settings'];
            $options = get_option('act_plugin_options', []);
            $options['enable_checkout_tracking'] = !empty($hq_settings['checkout_tracking_enabled']);
            $options['enable_fraud_blocker'] = !empty($hq_settings['fraud_blocker_enabled']);
            $options['enable_courier_service'] = !empty($hq_settings['courier_service_enabled']);
            $options['data_retention_policy'] = $hq_settings['data_retention_days'] ?? 90;
            update_option('act_plugin_options', $options);
        }
        return true;
    }
    return false;
}

/**
 * Sanitizes settings and sends the settings array to the Headquarters API.
 * This version correctly handles unchecked checkboxes.
 */
function act_sanitize_and_sync_settings($input)
{
    $options = get_option('act_plugin_options', []);
    $new_options = [];

    // Sanitize and save text/select/textarea fields
    $new_options['data_retention_policy'] = isset($input['data_retention_policy']) ? absint($input['data_retention_policy']) : 90;
    $new_options['exclude_user_roles'] = isset($input['exclude_user_roles']) && is_array($input['exclude_user_roles']) ? array_map('sanitize_text_field', $input['exclude_user_roles']) : [];
    $new_options['custom_block_message'] = isset($input['custom_block_message']) ? sanitize_textarea_field($input['custom_block_message']) : '';
    $new_options['ratio_blocker_threshold'] = isset($input['ratio_blocker_threshold']) ? absint($input['ratio_blocker_threshold']) : 20;
    $new_options['ratio_blocker_grace_period'] = isset($input['ratio_blocker_grace_period']) ? absint($input['ratio_blocker_grace_period']) : 5;
    $new_options['ratio_blocker_message'] = isset($input['ratio_blocker_message']) ? sanitize_textarea_field($input['ratio_blocker_message']) : '';

    // **FIX**: Handle checkboxes correctly. They are only in $input if checked.
    $new_options['enable_checkout_tracking'] = !empty($input['enable_checkout_tracking']);
    $new_options['enable_fraud_blocker'] = !empty($input['enable_fraud_blocker']);
    $new_options['enable_courier_service'] = !empty($input['enable_courier_service']);
    $new_options['delete_on_uninstall'] = !empty($input['delete_on_uninstall']);

    // Preserve the API key
    if (isset($options['api_key'])) {
        $new_options['api_key'] = $options['api_key'];
    }

    // Prepare data for HQ sync
    $settings_to_sync = [
        'checkout_tracking_enabled' => $new_options['enable_checkout_tracking'],
        'fraud_blocker_enabled' => $new_options['enable_fraud_blocker'],
        'courier_service_enabled' => $new_options['enable_courier_service'],
        'data_retention_days' => $new_options['data_retention_policy'],
    ];

    // Send the update to the HQ in the background
    act_call_hq_api('/sites/settings', $settings_to_sync, 'POST');

    return $new_options;
}

/**
 * Register all settings under a SINGLE options group.
 */
function act_register_settings()
{
    register_setting('act_settings', 'act_plugin_options', [
        'sanitize_callback' => 'act_sanitize_and_sync_settings'
    ]);

    // General Settings Section
    add_settings_section('act_general_section', __('General Settings', 'advanced-checkout-tracker'), null, 'act_settings');
    add_settings_field('data_retention_policy', __('Data Retention Policy', 'advanced-checkout-tracker'), 'act_render_data_retention_field', 'act_settings', 'act_general_section');
    add_settings_field('exclude_user_roles', __('Exclude User Roles', 'advanced-checkout-tracker'), 'act_render_exclude_roles_field', 'act_settings', 'act_general_section');

    // Module Toggles Section
    add_settings_section('act_modules_section', __('Module Toggles', 'advanced-checkout-tracker'), null, 'act_settings');
    add_settings_field('enable_checkout_tracking', __('Enable Checkout Tracking', 'advanced-checkout-tracker'), 'act_render_enable_checkout_tracking_field', 'act_settings', 'act_modules_section');
    add_settings_field('enable_fraud_blocker', __('Enable Fraud Blocker', 'advanced-checkout-tracker'), 'act_render_enable_fraud_blocker_field', 'act_settings', 'act_modules_section');
    add_settings_field('enable_courier_service', __('Enable Courier Service', 'advanced-checkout-tracker'), 'act_render_enable_courier_field', 'act_settings', 'act_modules_section');

    // Fraud Blocker Specifics
    add_settings_section('act_fraud_blocker_specifics', __('Fraud Blocker Settings', 'advanced-checkout-tracker'), null, 'act_settings');
    add_settings_field('custom_block_message', __('Custom Block Message', 'advanced-checkout-tracker'), 'act_render_custom_block_message_field', 'act_settings', 'act_fraud_blocker_specifics');

    // Success Ratio Blocker Section
    add_settings_section('act_ratio_blocker_section', __('Success Ratio Blocker', 'advanced-checkout-tracker'), null, 'act_settings');
    add_settings_field('ratio_blocker_threshold', __('Block Threshold (%)', 'advanced-checkout-tracker'), 'act_render_ratio_threshold_field', 'act_settings', 'act_ratio_blocker_section');
    add_settings_field('ratio_blocker_grace_period', __('New Customer Grace Period', 'advanced-checkout-tracker'), 'act_render_ratio_grace_period_field', 'act_settings', 'act_ratio_blocker_section');
    add_settings_field('ratio_blocker_message', __('Blocked Order Notice', 'advanced-checkout-tracker'), 'act_render_ratio_blocker_message_field', 'act_settings', 'act_ratio_blocker_section');

    // Advanced Section
    add_settings_section('act_advanced_section', __('Advanced Settings', 'advanced-checkout-tracker'), null, 'act_settings');
    add_settings_field('delete_on_uninstall', __('Data Management', 'advanced-checkout-tracker'), 'act_render_delete_on_uninstall_field', 'act_settings', 'act_advanced_section');
}
add_action('admin_init', 'act_register_settings');

/**
 * Main render function for the settings page.
 */
function act_render_settings_page()
{
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'account_usage';
    ?>
    <div class="wrap">
        <h1><?php _e('Advanced Checkout Tracker Settings', 'advanced-checkout-tracker'); ?></h1>

        <nav class="nav-tab-wrapper">
            <a href="?page=act-settings&tab=account_usage"
                class="nav-tab <?php echo $active_tab == 'account_usage' ? 'nav-tab-active' : ''; ?>"><?php _e('Account & Usage', 'advanced-checkout-tracker'); ?></a>
            <a href="?page=act-settings&tab=settings"
                class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'advanced-checkout-tracker'); ?></a>
        </nav>

        <div class="act-settings-content">
            <?php if ($active_tab == 'account_usage'): ?>
                <?php
                if (isset($_GET['action']) && $_GET['action'] == 'force_sync') {
                    check_admin_referer('act_force_sync');
                    if (act_sync_with_hq()) {
                        wp_safe_redirect(admin_url('admin.php?page=act-settings&tab=account_usage&synced=true'));
                    } else {
                        wp_safe_redirect(admin_url('admin.php?page=act-settings&tab=account_usage&synced=false'));
                    }
                    exit;
                }

                if (isset($_GET['synced'])) {
                    if ($_GET['synced'] == 'true') {
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully synced with Headquarters.', 'advanced-checkout-tracker') . '</p></div>';
                    } else {
                        update_option('act_license_status', 'inactive');
                        echo '<div class="notice notice-error is-dismissible"><p>' . __('Could not sync with Headquarters. Please check API connectivity. Your license status has been set to inactive.', 'advanced-checkout-tracker') . '</p></div>';
                    }
                }

                $cache = get_transient('act_api_cache');
                $sync_url = wp_nonce_url(admin_url('admin.php?page=act-settings&tab=account_usage&action=force_sync'), 'act_force_sync');
                ?>
                <h2><?php _e('Account & Usage', 'advanced-checkout-tracker'); ?></h2>
                <p><?php _e('This is your current plan and usage status, synced from the Headquarters.', 'advanced-checkout-tracker'); ?>
                </p>
                <p><a href="<?php echo esc_url($sync_url); ?>"
                        class="button-secondary"><?php _e("Refresh Status", "advanced-checkout-tracker"); ?></a></p>

                <?php if (!$cache): ?>
                    <p><em><?php _e('No data found. Click "Refresh Status" to fetch data from the Headquarters.', 'advanced-checkout-tracker'); ?></em>
                    </p>
                <?php else: ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php _e('Registered Domain', 'advanced-checkout-tracker'); ?></th>
                                <td><?php echo esc_html(act_get_root_domain(get_site_url())); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Current Plan', 'advanced-checkout-tracker'); ?></th>
                                <td><strong><?php echo esc_html(ucwords($cache['plan'])); ?></strong></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Account Status', 'advanced-checkout-tracker'); ?></th>
                                <td>
                                    <?php
                                    $displayed_status = get_option('act_license_status', 'inactive');
                                    $status_color = ($displayed_status === 'active') ? 'green' : 'red';
                                    echo '<span style="color: ' . esc_attr($status_color) . '; font-weight: bold;">' . esc_html(ucwords($displayed_status)) . '</span>';
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <h3><?php _e('Plan Usage', 'advanced-checkout-tracker'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Feature', 'advanced-checkout-tracker'); ?></th>
                                <th><?php _e('Used', 'advanced-checkout-tracker'); ?></th>
                                <th><?php _e('Limit', 'advanced-checkout-tracker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php _e('Incomplete Checkouts', 'advanced-checkout-tracker'); ?></td>
                                <td><?php echo esc_html($cache['usage']['checkouts'] ?? 0); ?></td>
                                <td><?php echo esc_html($cache['limits']['checkouts'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Fraud Blocker IPs', 'advanced-checkout-tracker'); ?></td>
                                <td><?php echo esc_html($cache['usage']['fraud_ips'] ?? 0); ?></td>
                                <td><?php echo esc_html($cache['limits']['fraud_ips'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Fraud Blocker Emails', 'advanced-checkout-tracker'); ?></td>
                                <td><?php echo esc_html($cache['usage']['fraud_emails'] ?? 0); ?></td>
                                <td><?php echo esc_html($cache['limits']['fraud_emails'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Fraud Blocker Phones', 'advanced-checkout-tracker'); ?></td>
                                <td><?php echo esc_html($cache['usage']['fraud_phones'] ?? 0); ?></td>
                                <td><?php echo esc_html($cache['limits']['fraud_phones'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e('Courier API Checks', 'advanced-checkout-tracker'); ?></td>
                                <td><?php echo esc_html($cache['usage']['courier_checks'] ?? 0); ?></td>
                                <td><?php echo esc_html($cache['limits']['courier_checks'] ?? 0); ?></td>
                            </tr>
                        </tbody>
                    </table>
<<<<<<< HEAD
                    <div class="act-upgrade-section">
                        <div class="act-upgrade-icon">
                            <span class="dashicons dashicons-star-filled"></span>
                        </div>
                        <div class="act-upgrade-text">
                            <h3>Looking for an Upgrade?</h3>
                            <p>Find the perfect plan that fits your needs and unlock more features.</p>
                        </div>
                        <div class="act-upgrade-actions">
                            <a href="https://coderzonebd.com/pricing" target="_blank" class="button button-primary">View Our
                                Plans</a>
                            <a href="https://coderzonebd.com/contact" target="_blank" class="button button-secondary">Get
                                Support</a>
                        </div>
                    </div>
=======
                    <br>
                    <a href="https://coderzonebd.com/pricing" target="_blank" class="button-primary">
                        <?php _e('Upgrade Your Plan', 'advanced-checkout-tracker'); ?>
                    </a>
>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
                <?php endif; ?>

            <?php elseif ($active_tab == 'settings'): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('act_settings');
                    do_settings_sections('act_settings');
                    submit_button(__('Save Settings', 'advanced-checkout-tracker'));
                    ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/*
 * --- Field Rendering Callbacks ---
 */

function act_render_data_retention_field()
{
    $options = get_option('act_plugin_options', []);
    $value = $options['data_retention_policy'] ?? '90';
    ?>
    <select name="act_plugin_options[data_retention_policy]">
        <option value="30" <?php selected($value, '30'); ?>>30 <?php _e('Days', 'advanced-checkout-tracker'); ?></option>
        <option value="90" <?php selected($value, '90'); ?>>90 <?php _e('Days', 'advanced-checkout-tracker'); ?></option>
        <option value="365" <?php selected($value, '365'); ?>>1 <?php _e('Year', 'advanced-checkout-tracker'); ?></option>
        <option value="0" <?php selected($value, '0'); ?>><?php _e('Never Delete', 'advanced-checkout-tracker'); ?></option>
    </select>
    <p class="description">
<<<<<<< HEAD
        <?php _e('Delete incomplete checkout records after this many days.', 'advanced-checkout-tracker'); ?>
    </p>
=======
        <?php _e('Delete incomplete checkout records after this many days.', 'advanced-checkout-tracker'); ?></p>
>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
    <?php
}

function act_render_exclude_roles_field()
{
    $options = get_option('act_plugin_options', []);
    $excluded_roles = $options['exclude_user_roles'] ?? [];
    $roles = get_editable_roles();
    foreach ($roles as $role_slug => $role_info) {
        $checked = is_array($excluded_roles) && in_array($role_slug, $excluded_roles) ? 'checked' : '';
        echo "<label><input type='checkbox' name='act_plugin_options[exclude_user_roles][]' value='{$role_slug}' {$checked}> {$role_info['name']}</label><br>";
    }
    echo '<p class="description">' . __('Do not track checkouts for these user roles.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_enable_checkout_tracking_field()
{
    $options = get_option('act_plugin_options', []);
    $checked = !empty($options['enable_checkout_tracking']) ? 'checked' : '';
    echo "<label><input type='checkbox' name='act_plugin_options[enable_checkout_tracking]' value='1' {$checked}> " . __('Enable this module.', 'advanced-checkout-tracker') . '</label>';
    echo '<p class="description">' . __('This allows the plugin to track incomplete checkouts.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_enable_fraud_blocker_field()
{
    $options = get_option('act_plugin_options', []);
    $checked = !empty($options['enable_fraud_blocker']) ? 'checked' : '';
    echo "<label><input type='checkbox' name='act_plugin_options[enable_fraud_blocker]' value='1' {$checked}> " . __('Enable this module.', 'advanced-checkout-tracker') . '</label>';
    echo '<p class="description">' . __('This enables the fraud blocker at checkout.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_custom_block_message_field()
{
    $options = get_option('act_plugin_options', []);
    $message = $options['custom_block_message'] ?? __('Your order cannot be processed at this time. Please contact support.', 'advanced-checkout-tracker');
    echo "<textarea name='act_plugin_options[custom_block_message]' rows='4' class='large-text'>" . esc_textarea($message) . "</textarea>";
    echo '<p class="description">' . __('The message shown to customers who are blocked by IP, email, or phone.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_enable_courier_field()
{
    $options = get_option('act_plugin_options', []);
    $checked = !empty($options['enable_courier_service']) ? 'checked' : '';
    echo "<label><input type='checkbox' name='act_plugin_options[enable_courier_service]' value='1' {$checked}> " . __('Enable this module.', 'advanced-checkout-tracker') . '</label>';
    echo '<p class="description">' . __("This enables the courier success ratio check feature.", 'advanced-checkout-tracker') . '</p>';
}

function act_render_delete_on_uninstall_field()
{
    $options = get_option('act_plugin_options', []);
    $checked = !empty($options['delete_on_uninstall']) ? 'checked' : '';
    echo "<label><input type='checkbox' name='act_plugin_options[delete_on_uninstall]' value='1' {$checked}> " . __('Delete all plugin data upon uninstallation.', 'advanced-checkout-tracker') . '</label>';
    echo '<p class="description"><strong>' . __('Warning:', 'advanced-checkout-tracker') . '</strong> ' . __('This will permanently delete all plugin tables and options when you delete the plugin from the Plugins page.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_ratio_threshold_field()
{
    $options = get_option('act_plugin_options', []);
    $value = $options['ratio_blocker_threshold'] ?? '20';
    echo "<input type='number' name='act_plugin_options[ratio_blocker_threshold]' value='" . esc_attr($value) . "' class='small-text' min='0' max='100' /> %";
    echo '<p class="description">' . __('Block orders if the customer\'s success ratio is at or below this percentage.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_ratio_grace_period_field()
{
    $options = get_option('act_plugin_options', []);
    $value = $options['ratio_blocker_grace_period'] ?? '5';
    echo "<input type='number' name='act_plugin_options[ratio_blocker_grace_period]' value='" . esc_attr($value) . "' class='small-text' min='0' /> " . __('orders', 'advanced-checkout-tracker');
    echo '<p class="description">' . __('Do not block new customers until they have more than this many total orders.', 'advanced-checkout-tracker') . '</p>';
}

function act_render_ratio_blocker_message_field()
{
    $options = get_option('act_plugin_options', []);
    $default_message = 'Your courier success ratio is {ratio}%, which is too low. Please contact us to complete your order.';
    $value = $options['ratio_blocker_message'] ?? $default_message;
    echo "<textarea name='act_plugin_options[ratio_blocker_message]' rows='3' class='large-text'>" . esc_textarea($value) . "</textarea>";
    echo '<p class="description">' . __('This message will be shown in a popup to the customer. Use <code>{ratio}</code> as a placeholder for the customer\'s success ratio.', 'advanced-checkout-tracker') . '</p>';
}