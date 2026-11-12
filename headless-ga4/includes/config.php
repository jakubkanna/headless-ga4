<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the capability required to manage the plugin.
 *
 * @return string
 */
function headless_ga4_admin_capability()
{
    return 'manage_options';
}

/**
 * Returns the settings page URL.
 *
 * @param array<string, string> $args Optional query args.
 *
 * @return string
 */
function headless_ga4_get_settings_page_url($args = array())
{
    $url = admin_url('options-general.php?page=headless-ga4');

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

/**
 * Returns true if the current request is the plugin settings page.
 *
 * @return bool
 */
function headless_ga4_is_settings_page()
{
    if (!is_admin()) {
        return false;
    }

    $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
    $page = is_string($page) ? sanitize_key($page) : '';

    return $page === 'headless-ga4';
}

/**
 * Returns true if the current request is the main dashboard page.
 *
 * @return bool
 */
function headless_ga4_is_dashboard_page()
{
    if (!is_admin()) {
        return false;
    }

    global $pagenow;

    return $pagenow === 'index.php';
}

/**
 * Returns the configured GA4 property id.
 *
 * @return string
 */
function headless_ga4_get_property_id()
{
    $value = get_option(HEADLESS_GA4_PROPERTY_OPTION, '');

    return preg_replace('/[^0-9]/', '', is_string($value) ? $value : '');
}

/**
 * Returns the raw service account JSON stored in options.
 *
 * @return string
 */
function headless_ga4_get_service_account_json_raw()
{
    $value = get_option(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION, '');

    return is_string($value) ? trim($value) : '';
}

/**
 * Returns whether service account credentials are currently stored.
 *
 * @return bool
 */
function headless_ga4_has_service_account_json()
{
    return headless_ga4_get_service_account_json_raw() !== '';
}

/**
 * Validates a service account JSON string.
 *
 * @param string $json Raw JSON string.
 *
 * @return array<string, string>|WP_Error
 */
function headless_ga4_validate_service_account_json_string($json)
{
    $json = is_string($json) ? trim($json) : '';

    if ($json === '') {
        return new WP_Error(
            'headless_ga4_missing_credentials',
            __('Service account JSON is missing.', 'headless-ga4')
        );
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return new WP_Error(
            'headless_ga4_invalid_credentials_json',
            __('Service account JSON could not be parsed.', 'headless-ga4')
        );
    }

    $required_keys = array('client_email', 'private_key', 'token_uri');

    foreach ($required_keys as $required_key) {
        if (empty($decoded[$required_key]) || !is_string($decoded[$required_key])) {
            return new WP_Error(
                'headless_ga4_invalid_credentials_fields',
                sprintf(
                    /* translators: %s: JSON field name. */
                    __('Service account JSON is missing the required field: %s', 'headless-ga4'),
                    $required_key
                )
            );
        }
    }

    return array(
        'client_email' => trim((string) $decoded['client_email']),
        'private_key'  => trim((string) $decoded['private_key']),
        'token_uri'    => trim((string) $decoded['token_uri']),
    );
}

/**
 * Returns validated service account credentials from plugin settings.
 *
 * @return array<string, string>|WP_Error
 */
function headless_ga4_get_service_account_credentials()
{
    return headless_ga4_validate_service_account_json_string(
        headless_ga4_get_service_account_json_raw()
    );
}

/**
 * Returns the stored service account email when credentials are valid.
 *
 * @return string
 */
function headless_ga4_get_service_account_email()
{
    $credentials = headless_ga4_get_service_account_credentials();

    if (is_wp_error($credentials) || empty($credentials['client_email'])) {
        return '';
    }

    return (string) $credentials['client_email'];
}

/**
 * Ensures an option is marked as not autoloaded.
 *
 * @param string $option_name Option name.
 *
 * @return void
 */
function headless_ga4_set_option_autoload_no($option_name)
{
    if (!is_string($option_name) || $option_name === '') {
        return;
    }

    $current_value = get_option($option_name, null);

    if ($current_value === null) {
        return;
    }

    update_option($option_name, $current_value, false);
}

/**
 * Returns the cache TTL for report data.
 *
 * @return int
 */
function headless_ga4_get_cache_ttl_seconds()
{
    $value = get_option(HEADLESS_GA4_CACHE_TTL_OPTION, 1800);
    $value = is_numeric($value) ? (int) $value : 1800;

    if ($value < 60) {
        return 60;
    }

    if ($value > DAY_IN_SECONDS) {
        return DAY_IN_SECONDS;
    }

    return $value;
}

/**
 * Returns whether the plugin has the minimum data required to connect.
 *
 * @return bool
 */
function headless_ga4_is_configured()
{
    return headless_ga4_get_property_id() !== '' && headless_ga4_has_service_account_json();
}

/**
 * Stores a short-lived admin notice for the settings page.
 *
 * @param string $message Notice message.
 * @param string $type    Notice type.
 *
 * @return void
 */
function headless_ga4_set_admin_notice($message, $type = 'success')
{
    set_transient(
        HEADLESS_GA4_ADMIN_NOTICE_TRANSIENT,
        array(
            'message' => is_string($message) ? $message : '',
            'type'    => in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'info',
        ),
        MINUTE_IN_SECONDS
    );
}

/**
 * Renders transient admin notices for the settings page.
 *
 * @return void
 */
function headless_ga4_render_admin_notice()
{
    if (!headless_ga4_is_settings_page() && !headless_ga4_is_dashboard_page()) {
        return;
    }

    $notice = get_transient(HEADLESS_GA4_ADMIN_NOTICE_TRANSIENT);

    if (!is_array($notice) || empty($notice['message'])) {
        return;
    }

    delete_transient(HEADLESS_GA4_ADMIN_NOTICE_TRANSIENT);

    $type = isset($notice['type']) ? sanitize_html_class((string) $notice['type']) : 'info';
    ?>
    <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
        <p><?php echo esc_html((string) $notice['message']); ?></p>
    </div>
    <?php
}
add_action('admin_notices', 'headless_ga4_render_admin_notice');
