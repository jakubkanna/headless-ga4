<?php
/**
 * Plugin settings page template.
 *
 * @var bool $has_credentials
 * @var bool $is_configured
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Headless GA4', 'headless-ga4'); ?></h1>

    <?php settings_errors('headless_ga4_settings'); ?>

    <div class="headless-ga4-layout">
        <div class="headless-ga4-panel">
            <form method="post" action="options.php">
                <?php
                settings_fields('headless_ga4_settings');
                do_settings_sections('headless-ga4');
                submit_button(__('Save settings', 'headless-ga4'));
                ?>
            </form>

            <div class="headless-ga4-actions">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('headless_ga4_test_connection'); ?>
                    <input type="hidden" name="action" value="headless_ga4_test_connection" />
                    <?php submit_button(__('Test connection', 'headless-ga4'), 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <div class="headless-ga4-panel">
            <h2><?php esc_html_e('Current status', 'headless-ga4'); ?></h2>
            <div class="headless-ga4-status-grid">
                <div class="headless-ga4-status-card">
                    <span><?php esc_html_e('Property ID', 'headless-ga4'); ?></span>
                    <strong><?php echo headless_ga4_get_property_id() !== '' ? esc_html(headless_ga4_get_property_id()) : esc_html__('Missing', 'headless-ga4'); ?></strong>
                </div>
                <div class="headless-ga4-status-card">
                    <span><?php esc_html_e('Credentials', 'headless-ga4'); ?></span>
                    <strong><?php echo $has_credentials ? esc_html__('Stored', 'headless-ga4') : esc_html__('Missing', 'headless-ga4'); ?></strong>
                </div>
                <div class="headless-ga4-status-card">
                    <span><?php esc_html_e('Cache TTL', 'headless-ga4'); ?></span>
                    <strong><?php echo esc_html((string) headless_ga4_get_cache_ttl_seconds()); ?>s</strong>
                </div>
                <div class="headless-ga4-status-card">
                    <span><?php esc_html_e('Dashboard widget', 'headless-ga4'); ?></span>
                    <strong><?php echo $is_configured ? esc_html__('Ready to load', 'headless-ga4') : esc_html__('Needs configuration', 'headless-ga4'); ?></strong>
                </div>
            </div>

            <h2 class="headless-ga4-section-heading"><?php esc_html_e('How to set it up', 'headless-ga4'); ?></h2>
            <ol class="headless-ga4-steps">
                <li><?php esc_html_e('Create a Google Cloud project.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('In Google Cloud Console, open APIs & Services, click Enable APIs and Services, search for Google Analytics Data API, open it, and click Enable.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('Create a service account.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('Open that service account, go to Keys, click Add key, choose Create new key, then download the JSON credentials.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('In GA4, open Admin, then Property access management, add the service account email, and grant it Viewer access.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('In GA4, open Admin, then Property settings, and copy the numeric Property ID.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('Paste the property ID and service account JSON into the settings form on this page.', 'headless-ga4'); ?></li>
                <li><?php esc_html_e('Save the settings and use "Test connection" to verify access before opening the dashboard widget.', 'headless-ga4'); ?></li>
            </ol>
        </div>
    </div>
</div>
