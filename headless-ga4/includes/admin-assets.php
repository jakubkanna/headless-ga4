<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueues the shared admin stylesheet for the plugin UI.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 *
 * @return void
 */
function headless_ga4_enqueue_admin_assets($hook_suffix)
{
    if (!in_array($hook_suffix, array('settings_page_headless-ga4', 'index.php'), true)) {
        return;
    }

    if ($hook_suffix === 'index.php' && !current_user_can(headless_ga4_admin_capability())) {
        return;
    }

    wp_register_style(
        'headless-ga4-admin',
        HEADLESS_GA4_PLUGIN_URL . 'assets/admin.css',
        array(),
        HEADLESS_GA4_VERSION
    );

    wp_enqueue_style('headless-ga4-admin');
}
add_action('admin_enqueue_scripts', 'headless_ga4_enqueue_admin_assets');
