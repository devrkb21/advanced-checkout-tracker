<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Displays an admin notice if WooCommerce is not active.
 * This is the primary version of this function.
 */
function act_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('<strong>Advanced Checkout Tracker</strong> requires WooCommerce to be installed and activated to function properly.', 'advanced-checkout-tracker'); ?>
        </p>
    </div>
    <?php
}

/**
 * Helper function to get an array of formats for $wpdb->insert/update.
 * @param array $data Data array to get formats for.
 * @return array Array of formats.
 */
function act_get_db_formats($data)
{
    $formats = array();
    // Define default formats for your columns based on their types in the DB
    $column_formats = array(
        'session_id' => '%s',
        'user_id' => '%d',
        'email' => '%s',
        'first_name' => '%s',
        'last_name' => '%s',
        'phone' => '%s',
        'address_1' => '%s',
        'address_2' => '%s',
        'city' => '%s',
        'state' => '%s',
        'postcode' => '%s',
        'country' => '%s',
        'ip_address' => '%s',
        'cart_details' => '%s', // JSON string
        'cart_value' => '%f',   // Float
        'status' => '%s',
        'follow_up_date' => '%s', // Date string YYYY-MM-DD
        'admin_notes' => '%s',
        'created_at' => '%s',   // DATETIME string
        'updated_at' => '%s'    // DATETIME string
    );
    foreach ($data as $key => $value) {
        if (isset($column_formats[$key])) {
            $formats[] = $column_formats[$key];
        } else {
            $formats[] = '%s'; // Default to string if not specified
        }
    }
    return $formats;
}
/**
 * Extracts the root domain from a given URL.
 *
 * @param string $url The URL to parse.
 * @return string The root domain.
 */
function act_get_root_domain($url)
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    // Regex to handle standard TLDs and common second-level domains (like .co.uk)
    if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $host, $matches)) {
        return $matches['domain'];
    }
    return $host;
}

/**
 * A centralized function for making API calls to the HQ.
 *
 * @param string $endpoint The API endpoint to call (e.g., '/sites/register').
 * @param array $body The data to send in the request body.
 * @param string $method The HTTP method (e.g., 'POST', 'GET').
 * @param bool $require_api_key Whether this API call requires an API key (set to false for registration).
 * @return array|WP_Error The decoded response body or a WP_Error object on failure.
 */
function act_call_hq_api($endpoint, $body = [], $method = 'POST', $require_api_key = true)
{
    $options = get_option('act_plugin_options', []);
    $api_key = !empty($options['api_key']) ? $options['api_key'] : '';

    // Only return an error if the API key is required but missing.
    // Initial registration call will set $require_api_key to false.
    if ($require_api_key && empty($api_key)) {
        return new WP_Error('api_key_missing', __('API Key is not configured in plugin settings. Please ensure plugin is activated and able to communicate with Headquarters.', 'advanced-checkout-tracker'));
    }

    $api_url = ACT_HEADQUARTERS_URL . '/api/v1' . $endpoint;
    $site_domain = act_get_root_domain(get_site_url());

    $headers = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
        'X-Site-Domain' => $site_domain,
    ];

    // Add X-Api-Key header only if required and available
    if ($require_api_key && !empty($api_key)) {
        $headers['X-Api-Key'] = $api_key;
    }

    $args = [
        'method' => $method,
        'timeout' => 15,
        'headers' => $headers,
    ];

    if (!empty($body)) {
        $args['body'] = json_encode($body);
    }

    $response = wp_remote_request($api_url, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code >= 300) {
        $error_message = isset($response_body['message']) ? $response_body['message'] : 'Unknown API Error';
        return new WP_Error('api_error', $error_message, ['status' => $response_code]);
    }

    return $response_body;
}

/**
 * Normalizes a phone number to the Bangladeshi 01XXXXXXXXX format.
 *
 * This function handles various formats, including those with country codes,
 * spaces, hyphens, and other characters.
 *
 * @param string $phone The phone number to normalize.
 * @return string The normalized 11-digit phone number.
 */
function act_normalize_phone_number($phone)
{
    // Return empty if the input is empty
    if (empty($phone)) {
        return '';
    }

    // 1. Remove all characters except digits.
    $cleaned_phone = preg_replace('/\D/', '', $phone);

    // 2. Check for and handle country code prefixes (880 or 0).
    // If it starts with '880' and is 13 digits long (e.g., 88017...), strip '88'.
    if (strpos($cleaned_phone, '880') === 0 && strlen($cleaned_phone) === 13) {
        return substr($cleaned_phone, 2);
    }

    // If it starts with '0' and is 11 digits long, it's already in the correct format.
    if (strpos($cleaned_phone, '0') === 0 && strlen($cleaned_phone) === 11) {
        return $cleaned_phone;
    }

    // If it's 10 digits long and doesn't start with '0' (e.g., 17...), prepend '0'.
    if (strlen($cleaned_phone) === 10 && strpos($cleaned_phone, '0') !== 0) {
        return '0' . $cleaned_phone;
    }

    // If none of the above rules match, return the cleaned number as a fallback.
    return $cleaned_phone;
}