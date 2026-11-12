<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns a cached GA4 access token if one is still valid.
 *
 * @return string
 */
function headless_ga4_get_cached_access_token()
{
    $cached = get_transient(HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT);

    if (!is_array($cached) || empty($cached['access_token']) || empty($cached['expires_at'])) {
        return '';
    }

    if ((int) $cached['expires_at'] <= time()) {
        delete_transient(HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT);

        return '';
    }

    return (string) $cached['access_token'];
}

/**
 * Stores an access token until shortly before expiry.
 *
 * @param string $token      OAuth access token.
 * @param int    $expires_in Token lifetime in seconds.
 *
 * @return void
 */
function headless_ga4_set_cached_access_token($token, $expires_in)
{
    $token = is_string($token) ? trim($token) : '';
    $expires_in = max(60, (int) $expires_in);

    if ($token === '') {
        return;
    }

    set_transient(
        HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT,
        array(
            'access_token' => $token,
            'expires_at'   => time() + max(60, $expires_in - 60),
        ),
        max(60, $expires_in - 60)
    );
}

/**
 * Returns the transient name for a period-specific report cache.
 *
 * @param string $period Dashboard period key.
 *
 * @return string
 */
function headless_ga4_get_report_transient_name($period = 'weekly')
{
    $period = is_string($period) ? sanitize_key($period) : 'weekly';

    if (!in_array($period, array('weekly', 'monthly', 'yearly', 'all_time'), true)) {
        $period = 'weekly';
    }

    return HEADLESS_GA4_REPORT_TRANSIENT . '_' . $period;
}

/**
 * Returns the cached report payload.
 *
 * @param string $period Dashboard period key.
 *
 * @return array<string, mixed>|null
 */
function headless_ga4_get_cached_report($period = 'weekly')
{
    $cached = get_transient(headless_ga4_get_report_transient_name($period));

    return is_array($cached) ? $cached : null;
}

/**
 * Stores the normalized report payload.
 *
 * @param array<string, mixed> $report Report payload.
 * @param string               $period Dashboard period key.
 *
 * @return void
 */
function headless_ga4_set_cached_report($report, $period = 'weekly')
{
    set_transient(
        headless_ga4_get_report_transient_name($period),
        $report,
        headless_ga4_get_cache_ttl_seconds()
    );
}

/**
 * Clears all plugin caches.
 *
 * @return void
 */
function headless_ga4_clear_cached_data()
{
    $periods = array('weekly', 'monthly', 'yearly', 'all_time');

    delete_transient(HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT);
    delete_transient(HEADLESS_GA4_REPORT_TRANSIENT);

    foreach ($periods as $period) {
        delete_transient(headless_ga4_get_report_transient_name($period));
    }
}
