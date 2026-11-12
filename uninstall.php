<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('headless_ga4_property_id');
delete_option('headless_ga4_service_account_json');
delete_option('headless_ga4_cache_ttl_seconds');

delete_transient('headless_ga4_access_token');
delete_transient('headless_ga4_report_payload');
delete_transient('headless_ga4_admin_notice');

