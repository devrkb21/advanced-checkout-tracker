<?php
if (!defined('ABSPATH'))
    exit;

function act_plugin_activate()
{
    act_create_database_tables();
    // Set initial status and trigger first sync
    if (!get_option('act_license_status')) {
        update_option('act_license_status', 'active');
    }
    act_register_site_with_hq();
    act_create_blocked_orders_table();
}

function act_create_blocked_orders_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'act_blocked_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        blocked_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
<<<<<<< HEAD
        first_name VARCHAR(100) DEFAULT '' NOT NULL, 
        last_name VARCHAR(100) DEFAULT '' NOT NULL,  
=======
>>>>>>> 14d30c7c49dccb12eb1a04f0f43dab94d7fbd3e2
        phone_number VARCHAR(20) NOT NULL,
        email_address VARCHAR(100) DEFAULT '' NOT NULL,
        cart_details longtext NOT NULL,
        cart_value float NOT NULL,
        success_ratio INT NOT NULL,
        threshold_at_block INT NOT NULL,
        ip_address VARCHAR(100) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function act_register_site_with_hq()
{
    $root_domain = act_get_root_domain(get_site_url());
    $admin_email = get_option('admin_email');

    if (!$root_domain) {
        update_option('act_license_status', 'failed: no domain');
        return;
    }

    // Call the centralized API function without webhook data
    // Call the centralized API function for registration, which does NOT require an API key
    $response = act_call_hq_api('/sites/register', [
        'domain' => $root_domain,
        'admin_email' => $admin_email,
    ], 'POST', false); // The 'false' here means 'require_api_key' is false for this call.

    if (!is_wp_error($response) && isset($response['api_key'])) {
        // Store the unique API key returned by the Headquarters
        $options = get_option('act_plugin_options', []);
        $options['api_key'] = sanitize_text_field($response['api_key']);
        update_option('act_plugin_options', $options);
        // Optionally, you might want to remove the API Key field from settings page
        // if it's only meant to be automatically stored.
    } elseif (is_wp_error($response) && $response->get_error_code() !== 'api_error' || !str_contains($response->get_error_message(), 'already taken')) {
        // Handle registration failure if it's not a "domain already taken" error
        update_option('act_license_status', 'failed: ' . $response->get_error_message());
        return;
    }
    // The rest of the act_register_site_with_hq function remains the same.

    // Case 1: The API call resulted in a definitive error.
    // We will IGNORE the "domain already taken" error because, for our purpose,
    // that means the site is already known to the HQ, which is fine.
    if (is_wp_error($response)) {
        $is_already_taken_error = (
            $response->get_error_code() === 'api_error' &&
            str_contains($response->get_error_message(), 'already taken')
        );

        if (!$is_already_taken_error) {
            update_option('act_license_status', 'failed: ' . $response->get_error_message());
            return;
        }
    }

    // Case 2: The API call was successful OR the site already existed.
    // In either scenario, we must now sync with the HQ to get the latest status.
    if (function_exists('act_sync_with_hq')) {
        act_sync_with_hq();
    }
}
/**
 * Create the necessary database tables.
 * Separated from the main activation function for clarity.
 */
function act_create_database_tables()
{
    if (!class_exists('WooCommerce')) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // 1. Main Incomplete Checkouts Table
    $table_name_incomplete = $wpdb->prefix . 'act_incomplete_checkouts';
    $sql_incomplete = "CREATE TABLE {$table_name_incomplete} (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(100) DEFAULT '' NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        email VARCHAR(100) DEFAULT '',
        first_name VARCHAR(100) DEFAULT '',
        last_name VARCHAR(100) DEFAULT '',
        phone VARCHAR(30) DEFAULT '',
        address_1 VARCHAR(255) DEFAULT '',
        address_2 VARCHAR(255) DEFAULT '',
        city VARCHAR(100) DEFAULT '',
        state VARCHAR(100) DEFAULT '',
        postcode VARCHAR(20) DEFAULT '',
        country VARCHAR(50) DEFAULT '',
        ip_address VARCHAR(45) DEFAULT '',
        cart_details LONGTEXT DEFAULT NULL,
        cart_value DECIMAL(10,2) DEFAULT 0.00,
        status VARCHAR(20) DEFAULT 'incomplete' NOT NULL,
        recovered_order_id BIGINT(20) UNSIGNED DEFAULT NULL, 
        follow_up_date DATE DEFAULT NULL,
        admin_notes TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id(10)),
        KEY user_id (user_id),
        KEY email (email(50)),
        KEY ip_address (ip_address),
        KEY status (status),
        KEY recovered_order_id (recovered_order_id), 
        KEY created_at (created_at),
        KEY follow_up_date (follow_up_date)
    ) {$charset_collate};";
    dbDelta($sql_incomplete);

    // 2. Fraud Blocker Tables
    $table_blocked_numbers = $wpdb->prefix . 'act_blocked_numbers';
    $sql_numbers = "CREATE TABLE {$table_blocked_numbers} (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        phone_number VARCHAR(30) NOT NULL,
        added_by BIGINT(20) UNSIGNED DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY phone_number (phone_number)
    ) {$charset_collate};";
    dbDelta($sql_numbers);

    $table_blocked_emails = $wpdb->prefix . 'act_blocked_emails';
    $sql_emails = "CREATE TABLE {$table_blocked_emails} (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        email_address VARCHAR(100) NOT NULL,
        added_by BIGINT(20) UNSIGNED DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email_address (email_address)
    ) {$charset_collate};";
    dbDelta($sql_emails);

    $table_blocked_ips = $wpdb->prefix . 'act_blocked_ips';
    $sql_ips = "CREATE TABLE {$table_blocked_ips} (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        ip_address VARCHAR(45) NOT NULL,
        added_by BIGINT(20) UNSIGNED DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip_address (ip_address)
    ) {$charset_collate};";
    dbDelta($sql_ips);

    update_option('act_plugin_version', ACT_VERSION);
}


/**
 * Handles tasks upon plugin deactivation.
 * This is a placeholder for now but could be used to clear scheduled cron jobs.
 */
function act_plugin_deactivate()
{
    $options = get_option('act_plugin_options', []);
    if (isset($options['delete_on_uninstall']) && $options['delete_on_uninstall']) {
        global $wpdb;
        $tables_to_drop = [
            $wpdb->prefix . 'act_incomplete_checkouts',
            $wpdb->prefix . 'act_blocked_numbers',
            $wpdb->prefix . 'act_blocked_emails',
            $wpdb->prefix . 'act_blocked_ips',
            $wpdb->prefix . 'act_blocked_orders',
        ];

        foreach ($tables_to_drop as $table_name) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        // Optionally, delete plugin options as well
        delete_option('act_plugin_options');
        delete_option('act_license_status');
        delete_transient('act_api_cache');
        delete_option('act_plugin_version');
    }
}