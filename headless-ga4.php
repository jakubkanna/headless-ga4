<?php
/**
 * Plugin Name: Headless GA4
 * Plugin URI: https://studio.jakubkanna.com/
 * Description: Display lightweight GA4 data in the WordPress dashboard for headless frontends.
 * Version: 0.2.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Studio JKN
 * Author URI: https://studio.jakubkanna.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: headless-ga4
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HEADLESS_GA4_VERSION', '0.2.1');
define('HEADLESS_GA4_PLUGIN_FILE', __FILE__);
define('HEADLESS_GA4_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HEADLESS_GA4_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HEADLESS_GA4_PROPERTY_OPTION', 'headless_ga4_property_id');
define('HEADLESS_GA4_SERVICE_ACCOUNT_OPTION', 'headless_ga4_service_account_json');
define('HEADLESS_GA4_CACHE_TTL_OPTION', 'headless_ga4_cache_ttl_seconds');
define('HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT', 'headless_ga4_access_token');
define('HEADLESS_GA4_REPORT_TRANSIENT', 'headless_ga4_report_payload');
define('HEADLESS_GA4_ADMIN_NOTICE_TRANSIENT', 'headless_ga4_admin_notice');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/admin-assets.php';
require_once __DIR__ . '/includes/google-auth.php';
require_once __DIR__ . '/includes/ga4-api.php';
require_once __DIR__ . '/includes/settings-page.php';
require_once __DIR__ . '/includes/dashboard-widget.php';

/**
 * Ensures the plugin starts with safe default options.
 *
 * The service account JSON should never autoload because it is a large secret
 * that is only needed on this plugin's admin/API requests.
 *
 * @return void
 */
function headless_ga4_activate()
{
    if (get_option(HEADLESS_GA4_CACHE_TTL_OPTION, null) === null) {
        add_option(HEADLESS_GA4_CACHE_TTL_OPTION, 1800);
    }

    if (get_option(HEADLESS_GA4_PROPERTY_OPTION, null) === null) {
        add_option(HEADLESS_GA4_PROPERTY_OPTION, '');
    }

    if (get_option(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION, null) === null) {
        add_option(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION, '', '', false);
    }

    headless_ga4_set_option_autoload_no(HEADLESS_GA4_SERVICE_ACCOUNT_OPTION);
}
register_activation_hook(__FILE__, 'headless_ga4_activate');
