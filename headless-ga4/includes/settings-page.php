<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the plugin settings page.
 *
 * @return void
 */
function headless_ga4_register_settings_page()
{
    add_options_page(
        __('Headless GA4', 'headless-ga4'),
        __('Headless GA4', 'headless-ga4'),
        headless_ga4_admin_capability(),
        'headless-ga4',
        'headless_ga4_render_settings_page'
    );
}
add_action('admin_menu', 'headless_ga4_register_settings_page');

/**
 * Registers plugin settings and fields.
 *
 * @return void
 */
function headless_ga4_register_settings()
{
    register_setting(
        'headless_ga4_settings',
        HEADLESS_GA4_PROPERTY_OPTION,
        array(
            'sanitize_callback' => 'headless_ga4_sanitize_property_id',
            'type'              => 'string',
        )
    );

    register_setting(
        'headless_ga4_settings',
        HEADLESS_GA4_SERVICE_ACCOUNT_OPTION,
        array(
            'sanitize_callback' => 'headless_ga4_sanitize_service_account_json',
            'type'              => 'string',
        )
    );

    register_setting(
        'headless_ga4_settings',
        HEADLESS_GA4_CACHE_TTL_OPTION,
        array(
            'sanitize_callback' => 'headless_ga4_sanitize_cache_ttl',
            'type'              => 'integer',
        )
    );

    add_settings_section(
        'headless_ga4_configuration',
        __('Configuration', 'headless-ga4'),
        'headless_ga4_render_settings_section',
        'headless-ga4'
    );

    add_settings_field(
        HEADLESS_GA4_PROPERTY_OPTION,
        __('GA4 Property ID', 'headless-ga4'),
        'headless_ga4_render_property_field',
        'headless-ga4',
        'headless_ga4_configuration'
    );

    add_settings_field(
        HEADLESS_GA4_SERVICE_ACCOUNT_OPTION,
        __('Service account JSON', 'headless-ga4'),
        'headless_ga4_render_service_account_field',
        'headless-ga4',
        'headless_ga4_configuration'
    );

    add_settings_field(
        HEADLESS_GA4_CACHE_TTL_OPTION,
        __('Cache TTL (seconds)', 'headless-ga4'),
        'headless_ga4_render_cache_ttl_field',
        'headless-ga4',
        'headless_ga4_configuration'
    );
}
add_action('admin_init', 'headless_ga4_register_settings');

/**
 * Sanitizes the property ID option.
 *
 * @param mixed $value Raw submitted value.
 *
 * @return string
 */
function headless_ga4_sanitize_property_id($value)
{
    headless_ga4_clear_cached_data();

    return preg_replace('/[^0-9]/', '', is_string($value) ? $value : '');
}

/**
 * Sanitizes the service account JSON option.
 *
 * @param mixed $value Raw submitted value.
 *
 * @return string
 */
function headless_ga4_sanitize_service_account_json($value)
{
    $value = is_string($value) ? trim((string) $value) : '';
    $current = headless_ga4_get_service_account_json_raw();

    if ($value === '') {
        return $current;
    }

    $validated = headless_ga4_validate_service_account_json_string($value);

    if (is_wp_error($validated)) {
        add_settings_error(
            'headless_ga4_settings',
            'headless_ga4_invalid_service_account_json',
            $validated->get_error_message(),
            'error'
        );

        return $current;
    }

    headless_ga4_clear_cached_data();

    return (string) wp_json_encode(
        json_decode($value, true),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Sanitizes the cache TTL option.
 *
 * @param mixed $value Raw submitted value.
 *
 * @return int
 */
function headless_ga4_sanitize_cache_ttl($value)
{
    headless_ga4_clear_cached_data();

    $value = is_numeric($value) ? (int) $value : 1800;

    if ($value < 60) {
        $value = 60;
    }

    if ($value > DAY_IN_SECONDS) {
        $value = DAY_IN_SECONDS;
    }

    return $value;
}

/**
 * Renders the configuration section intro.
 *
 * @return void
 */
function headless_ga4_render_settings_section()
{
    echo '<p>' . esc_html__('Connect a GA4 property to your WordPress dashboard widget. This plugin is designed for headless sites where the frontend sends GA4 data and WordPress only reads the reports.', 'headless-ga4') . '</p>';
}

/**
 * Renders the property ID field.
 *
 * @return void
 */
function headless_ga4_render_property_field()
{
    ?>
    <input
        type="text"
        name="<?php echo esc_attr(HEADLESS_GA4_PROPERTY_OPTION); ?>"
        value="<?php echo esc_attr(headless_ga4_get_property_id()); ?>"
        class="regular-text"
        inputmode="numeric"
        placeholder="<?php esc_attr_e('e.g. 123456789', 'headless-ga4'); ?>"
    />
    <p class="description">
        <?php esc_html_e('Use the numeric GA4 property ID, not the G- measurement ID from your frontend tracking snippet.', 'headless-ga4'); ?>
    </p>
    <?php
}

/**
 * Renders the service account field.
 *
 * @return void
 */
function headless_ga4_render_service_account_field()
{
    $has_credentials = headless_ga4_has_service_account_json();
    $service_account_email = headless_ga4_get_service_account_email();
    ?>
    <textarea
        name="<?php echo esc_attr(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION); ?>"
        rows="12"
        class="large-text code"
        placeholder="<?php esc_attr_e('Paste the full service account JSON here to add or replace stored credentials.', 'headless-ga4'); ?>"
    ></textarea>
    <?php if ($has_credentials) : ?>
        <div class="headless-ga4-credentials-box">
            <div>
                <?php if ($service_account_email !== '') : ?>
                    <?php
                    printf(
                        /* translators: %s: service account email. */
                        esc_html__('Service account: %s', 'headless-ga4'),
                        esc_html($service_account_email)
                    );
                    ?>
                <?php else : ?>
                    <?php esc_html_e('Credentials are stored, but the email could not be read from the saved JSON.', 'headless-ga4'); ?>
                <?php endif; ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="headless-ga4-inline-form">
                <?php wp_nonce_field('headless_ga4_clear_credentials'); ?>
                <input type="hidden" name="action" value="headless_ga4_clear_credentials" />
                <?php submit_button(__('Clear stored credentials', 'headless-ga4'), 'secondary', 'submit', false); ?>
            </form>
        </div>
    <?php else : ?>
        <p class="description">
            <?php esc_html_e('Paste the full JSON credentials from a Google Cloud service account. In GA4, open Admin, then Property access management, and give that service account email Viewer access before testing the connection.', 'headless-ga4'); ?>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * Renders the cache TTL field.
 *
 * @return void
 */
function headless_ga4_render_cache_ttl_field()
{
    ?>
    <input
        type="number"
        min="60"
        max="<?php echo esc_attr((string) DAY_IN_SECONDS); ?>"
        name="<?php echo esc_attr(HEADLESS_GA4_CACHE_TTL_OPTION); ?>"
        value="<?php echo esc_attr((string) headless_ga4_get_cache_ttl_seconds()); ?>"
        class="small-text"
    />
    <p class="description">
        <?php esc_html_e('Controls how long the dashboard widget keeps GA4 report data before refreshing it from Google.', 'headless-ga4'); ?>
    </p>
    <?php
}

/**
 * Handles the "test connection" admin action.
 *
 * @return void
 */
function headless_ga4_handle_test_connection()
{
    if (!current_user_can(headless_ga4_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'headless-ga4'));
    }

    check_admin_referer('headless_ga4_test_connection');
    headless_ga4_clear_cached_data();

    $report = headless_ga4_fetch_dashboard_report(true);

    if (is_wp_error($report)) {
        headless_ga4_set_admin_notice($report->get_error_message(), 'error');
    } else {
        headless_ga4_set_admin_notice(__('Connection successful. GA4 data is available to the dashboard widget.', 'headless-ga4'));
    }

    wp_safe_redirect(headless_ga4_get_settings_page_url());
    exit;
}
add_action('admin_post_headless_ga4_test_connection', 'headless_ga4_handle_test_connection');

/**
 * Handles the "clear credentials" admin action.
 *
 * @return void
 */
function headless_ga4_handle_clear_credentials()
{
    if (!current_user_can(headless_ga4_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'headless-ga4'));
    }

    check_admin_referer('headless_ga4_clear_credentials');
    delete_option(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION);
    add_option(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION, '', '', false);
    headless_ga4_clear_cached_data();
    headless_ga4_set_admin_notice(__('Stored service account credentials were cleared.', 'headless-ga4'));

    wp_safe_redirect(headless_ga4_get_settings_page_url());
    exit;
}
add_action('admin_post_headless_ga4_clear_credentials', 'headless_ga4_handle_clear_credentials');

/**
 * Renders the plugin settings page.
 *
 * @return void
 */
function headless_ga4_render_settings_page()
{
    if (!current_user_can(headless_ga4_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'headless-ga4'));
    }

    $has_credentials = headless_ga4_has_service_account_json();
    $is_configured = headless_ga4_is_configured();

    require HEADLESS_GA4_PLUGIN_PATH . 'templates/settings-page.php';
}
