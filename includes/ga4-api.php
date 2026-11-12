<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Executes a GA4 Data API runReport request.
 *
 * @param array<string, mixed> $body                Request body.
 * @param bool                 $refresh_access_token Whether to refresh the token first.
 *
 * @return array<string, mixed>|WP_Error
 */
function headless_ga4_run_report($body, $refresh_access_token = false)
{
    $property_id = headless_ga4_get_property_id();

    if ($property_id === '') {
        return new WP_Error(
            'headless_ga4_missing_property',
            __('GA4 property ID is missing.', 'headless-ga4')
        );
    }

    $access_token = headless_ga4_get_access_token($refresh_access_token);

    if (is_wp_error($access_token)) {
        return $access_token;
    }

    $response = wp_remote_post(
        'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($property_id) . ':runReport',
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
        )
    );

    if (is_wp_error($response)) {
        return new WP_Error(
            'headless_ga4_report_request_failed',
            $response->get_error_message()
        );
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $payload = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($status_code === 401 && !$refresh_access_token) {
        delete_transient(HEADLESS_GA4_ACCESS_TOKEN_TRANSIENT);

        return headless_ga4_run_report($body, true);
    }

    if ($status_code < 200 || $status_code >= 300 || !is_array($payload)) {
        $message = __('The GA4 report request failed.', 'headless-ga4');

        if (is_array($payload) && isset($payload['error']['message'])) {
            $message = (string) $payload['error']['message'];
        }

        return new WP_Error('headless_ga4_report_failed', $message);
    }

    return $payload;
}

/**
 * Converts GA4 metric headers and values into an associative array.
 *
 * @param array<string, mixed> $report GA4 report payload.
 *
 * @return array<string, int|float>
 */
function headless_ga4_map_metric_row($report)
{
    $mapped = array();
    $metric_headers = isset($report['metricHeaders']) && is_array($report['metricHeaders']) ? $report['metricHeaders'] : array();
    $rows = isset($report['rows']) && is_array($report['rows']) ? $report['rows'] : array();
    $row = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
    $metric_values = isset($row['metricValues']) && is_array($row['metricValues']) ? $row['metricValues'] : array();

    foreach ($metric_headers as $index => $header) {
        if (!is_array($header) || empty($header['name'])) {
            continue;
        }

        $raw_value = isset($metric_values[$index]['value']) ? (string) $metric_values[$index]['value'] : '0';
        $numeric_value = strpos($raw_value, '.') !== false ? (float) $raw_value : (int) $raw_value;

        $mapped[(string) $header['name']] = $numeric_value;
    }

    return $mapped;
}

/**
 * Calculates a percentage delta relative to the previous value.
 *
 * @param int|float $current  Current value.
 * @param int|float $previous Previous value.
 *
 * @return float|null
 */
function headless_ga4_calculate_delta_percentage($current, $previous)
{
    if ((float) $previous <= 0) {
        return null;
    }

    return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
}

/**
 * Returns the available dashboard reporting periods.
 *
 * @return array<string, string>
 */
function headless_ga4_get_dashboard_period_choices()
{
    return array(
        'weekly'   => __('Weekly', 'headless-ga4'),
        'monthly'  => __('Monthly', 'headless-ga4'),
        'yearly'   => __('Yearly', 'headless-ga4'),
        'all_time' => __('All time', 'headless-ga4'),
    );
}

/**
 * Normalizes the dashboard period key.
 *
 * @param string $period Raw period key.
 *
 * @return string
 */
function headless_ga4_normalize_dashboard_period($period)
{
    $period = is_string($period) ? sanitize_key($period) : 'weekly';
    $choices = headless_ga4_get_dashboard_period_choices();

    return isset($choices[$period]) ? $period : 'weekly';
}

/**
 * Returns report configuration for a dashboard period.
 *
 * @param string $period Dashboard period key.
 *
 * @return array<string, mixed>
 */
function headless_ga4_get_dashboard_period_config($period)
{
    $period = headless_ga4_normalize_dashboard_period($period);
    $choices = headless_ga4_get_dashboard_period_choices();
    $configs = array(
        'weekly' => array(
            'current_range'   => array('startDate' => '6daysAgo', 'endDate' => 'today'),
            'previous_range'  => array('startDate' => '13daysAgo', 'endDate' => '7daysAgo'),
            'trend_dimension' => 'date',
            'trend_label'     => __('Daily sessions', 'headless-ga4'),
            'trend_limit'     => 7,
        ),
        'monthly' => array(
            'current_range'   => array('startDate' => '29daysAgo', 'endDate' => 'today'),
            'previous_range'  => array('startDate' => '59daysAgo', 'endDate' => '30daysAgo'),
            'trend_dimension' => 'date',
            'trend_label'     => __('Daily sessions', 'headless-ga4'),
            'trend_limit'     => 30,
        ),
        'yearly' => array(
            'current_range'   => array('startDate' => '365daysAgo', 'endDate' => 'today'),
            'previous_range'  => array('startDate' => '731daysAgo', 'endDate' => '366daysAgo'),
            'trend_dimension' => 'yearMonth',
            'trend_label'     => __('Monthly sessions', 'headless-ga4'),
            'trend_limit'     => 13,
        ),
        'all_time' => array(
            'current_range'   => array('startDate' => '2020-10-14', 'endDate' => 'today'),
            'previous_range'  => null,
            'trend_dimension' => 'year',
            'trend_label'     => __('Yearly sessions', 'headless-ga4'),
            'trend_limit'     => 100,
        ),
    );

    $config = $configs[$period];
    $config['key'] = $period;
    $config['label'] = $choices[$period];

    return $config;
}

/**
 * Formats a trend dimension value for display.
 *
 * @param string $value     Raw GA4 dimension value.
 * @param string $dimension GA4 dimension name.
 *
 * @return string
 */
function headless_ga4_format_trend_label($value, $dimension)
{
    if ($dimension === 'date') {
        $date = DateTime::createFromFormat('Ymd', $value, wp_timezone());

        return $date instanceof DateTime ? wp_date('M j', $date->getTimestamp()) : $value;
    }

    if ($dimension === 'yearMonth') {
        $date = DateTime::createFromFormat('Ym', $value, wp_timezone());

        return $date instanceof DateTime ? wp_date('M Y', $date->getTimestamp()) : $value;
    }

    return $value;
}

/**
 * Returns an empty metric summary.
 *
 * @return array<string, int>
 */
function headless_ga4_get_empty_metric_summary()
{
    return array(
        'activeUsers'     => 0,
        'sessions'        => 0,
        'screenPageViews' => 0,
        'engagedSessions' => 0,
    );
}

/**
 * Builds normalized trend points and fills empty buckets for the selected period.
 *
 * @param array<string, mixed> $trend_report  Trend report.
 * @param array<string, mixed> $period_config Period configuration.
 *
 * @return array<int, array<string, int|string>>
 */
function headless_ga4_build_trend_points($trend_report, $period_config)
{
    $trend_rows = isset($trend_report['rows']) && is_array($trend_report['rows']) ? $trend_report['rows'] : array();
    $trend_dimension = isset($period_config['trend_dimension']) ? (string) $period_config['trend_dimension'] : 'date';
    $period = isset($period_config['key']) ? (string) $period_config['key'] : 'weekly';
    $values = array();

    foreach ($trend_rows as $row) {
        $dimension_value = isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : '';

        if ($dimension_value === '') {
            continue;
        }

        $values[$dimension_value] = isset($row['metricValues'][0]['value']) ? (int) $row['metricValues'][0]['value'] : 0;
    }

    if ($period === 'weekly' || $period === 'monthly') {
        $days = $period === 'weekly' ? 7 : 30;
        $date = new DateTime('-' . ($days - 1) . ' days', wp_timezone());
        $trend = array();

        for ($index = 0; $index < $days; $index++) {
            $key = $date->format('Ymd');
            $trend[] = array(
                'label' => wp_date('M j', $date->getTimestamp()),
                'value' => isset($values[$key]) ? (int) $values[$key] : 0,
            );
            $date->modify('+1 day');
        }

        return $trend;
    }

    if ($period === 'yearly') {
        $date = new DateTime('first day of this month', wp_timezone());
        $date->modify('-12 months');
        $trend = array();

        for ($index = 0; $index < 13; $index++) {
            $key = $date->format('Ym');
            $trend[] = array(
                'label' => wp_date('M Y', $date->getTimestamp()),
                'value' => isset($values[$key]) ? (int) $values[$key] : 0,
            );
            $date->modify('+1 month');
        }

        return $trend;
    }

    if (empty($values)) {
        return array(
            array(
                'label' => wp_date('Y'),
                'value' => 0,
            ),
        );
    }

    $trend = array();

    foreach ($values as $dimension_value => $session_value) {
        $trend[] = array(
            'label' => headless_ga4_format_trend_label((string) $dimension_value, $trend_dimension),
            'value' => (int) $session_value,
        );
    }

    return $trend;
}

/**
 * Builds the normalized widget payload from GA4 report responses.
 *
 * @param array<string, int|float> $current_summary  Current summary metrics.
 * @param array<string, int|float> $previous_summary Previous summary metrics.
 * @param array<string, mixed>     $trend_report     Trend report.
 * @param array<string, mixed>     $pages_report     Top pages report.
 * @param array<string, mixed>     $sources_report   Top sources report.
 * @param array<string, mixed>     $period_config    Period configuration.
 *
 * @return array<string, mixed>
 */
function headless_ga4_build_dashboard_payload($current_summary, $previous_summary, $trend_report, $pages_report, $sources_report, $period_config)
{
    $summary = array(
        'users'            => isset($current_summary['activeUsers']) ? (int) $current_summary['activeUsers'] : 0,
        'sessions'         => isset($current_summary['sessions']) ? (int) $current_summary['sessions'] : 0,
        'views'            => isset($current_summary['screenPageViews']) ? (int) $current_summary['screenPageViews'] : 0,
        'engaged_sessions' => isset($current_summary['engagedSessions']) ? (int) $current_summary['engagedSessions'] : 0,
    );

    $comparison = array(
        'users' => array(
            'previous'         => isset($previous_summary['activeUsers']) ? (int) $previous_summary['activeUsers'] : 0,
            'delta_percentage' => headless_ga4_calculate_delta_percentage(
                $summary['users'],
                isset($previous_summary['activeUsers']) ? (int) $previous_summary['activeUsers'] : 0
            ),
        ),
        'sessions' => array(
            'previous'         => isset($previous_summary['sessions']) ? (int) $previous_summary['sessions'] : 0,
            'delta_percentage' => headless_ga4_calculate_delta_percentage(
                $summary['sessions'],
                isset($previous_summary['sessions']) ? (int) $previous_summary['sessions'] : 0
            ),
        ),
        'views' => array(
            'previous'         => isset($previous_summary['screenPageViews']) ? (int) $previous_summary['screenPageViews'] : 0,
            'delta_percentage' => headless_ga4_calculate_delta_percentage(
                $summary['views'],
                isset($previous_summary['screenPageViews']) ? (int) $previous_summary['screenPageViews'] : 0
            ),
        ),
        'engaged_sessions' => array(
            'previous'         => isset($previous_summary['engagedSessions']) ? (int) $previous_summary['engagedSessions'] : 0,
            'delta_percentage' => headless_ga4_calculate_delta_percentage(
                $summary['engaged_sessions'],
                isset($previous_summary['engagedSessions']) ? (int) $previous_summary['engagedSessions'] : 0
            ),
        ),
    );

    $trend = headless_ga4_build_trend_points($trend_report, $period_config);

    $top_pages = array();
    $page_rows = isset($pages_report['rows']) && is_array($pages_report['rows']) ? $pages_report['rows'] : array();

    foreach ($page_rows as $row) {
        $page_path = isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : '/';
        $page_title = isset($row['dimensionValues'][1]['value']) ? (string) $row['dimensionValues'][1]['value'] : '';
        $views = isset($row['metricValues'][0]['value']) ? (int) $row['metricValues'][0]['value'] : 0;

        $top_pages[] = array(
            'path'  => $page_path !== '' ? $page_path : '/',
            'title' => $page_title,
            'views' => $views,
        );
    }

    $top_sources = array();
    $source_rows = isset($sources_report['rows']) && is_array($sources_report['rows']) ? $sources_report['rows'] : array();

    foreach ($source_rows as $row) {
        $source = isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : __('(direct)', 'headless-ga4');
        $medium = isset($row['dimensionValues'][1]['value']) ? (string) $row['dimensionValues'][1]['value'] : __('(none)', 'headless-ga4');
        $sessions = isset($row['metricValues'][0]['value']) ? (int) $row['metricValues'][0]['value'] : 0;

        $top_sources[] = array(
            'medium'   => $medium !== '' ? $medium : __('(none)', 'headless-ga4'),
            'sessions' => $sessions,
            'source'   => $source !== '' ? $source : __('(direct)', 'headless-ga4'),
        );
    }

    return array(
        'summary'           => $summary,
        'comparison'        => $comparison,
        'trend'             => $trend,
        'period'            => isset($period_config['key']) ? (string) $period_config['key'] : 'weekly',
        'period_label'      => isset($period_config['label']) ? (string) $period_config['label'] : __('Weekly', 'headless-ga4'),
        'trend_label'       => isset($period_config['trend_label']) ? (string) $period_config['trend_label'] : __('Daily sessions', 'headless-ga4'),
        'top_pages'         => $top_pages,
        'top_sources'       => $top_sources,
        'last_refreshed_at' => current_time('timestamp'),
    );
}

/**
 * Returns the normalized report payload used by the dashboard widget.
 *
 * @param bool $force_refresh Whether to bypass the report cache.
 * @param string $period       Dashboard period key.
 *
 * @return array<string, mixed>|WP_Error
 */
function headless_ga4_fetch_dashboard_report($force_refresh = false, $period = 'weekly')
{
    if (!headless_ga4_is_configured()) {
        return new WP_Error(
            'headless_ga4_not_configured',
            __('Headless GA4 is not configured yet.', 'headless-ga4')
        );
    }

    $period = headless_ga4_normalize_dashboard_period($period);
    $period_config = headless_ga4_get_dashboard_period_config($period);
    $current_range = isset($period_config['current_range']) && is_array($period_config['current_range']) ? $period_config['current_range'] : array('startDate' => '6daysAgo', 'endDate' => 'today');
    $previous_range = isset($period_config['previous_range']) && is_array($period_config['previous_range']) ? $period_config['previous_range'] : null;
    $trend_dimension = isset($period_config['trend_dimension']) ? (string) $period_config['trend_dimension'] : 'date';
    $trend_limit = isset($period_config['trend_limit']) ? (int) $period_config['trend_limit'] : 30;

    if (!$force_refresh) {
        $cached_report = headless_ga4_get_cached_report($period);

        if (is_array($cached_report)) {
            return $cached_report;
        }
    }

    $summary_current = headless_ga4_run_report(
        array(
            'dateRanges' => array(
                $current_range,
            ),
            'metrics' => array(
                array('name' => 'activeUsers'),
                array('name' => 'sessions'),
                array('name' => 'screenPageViews'),
                array('name' => 'engagedSessions'),
            ),
        )
    );

    if (is_wp_error($summary_current)) {
        return $summary_current;
    }

    if (is_array($previous_range)) {
        $summary_previous = headless_ga4_run_report(
            array(
                'dateRanges' => array(
                    $previous_range,
                ),
                'metrics' => array(
                    array('name' => 'activeUsers'),
                    array('name' => 'sessions'),
                    array('name' => 'screenPageViews'),
                    array('name' => 'engagedSessions'),
                ),
            )
        );

        if (is_wp_error($summary_previous)) {
            return $summary_previous;
        }

        $previous_summary = headless_ga4_map_metric_row($summary_previous);
    } else {
        $previous_summary = headless_ga4_get_empty_metric_summary();
    }

    $trend_report = headless_ga4_run_report(
        array(
            'dateRanges' => array(
                $current_range,
            ),
            'dimensions' => array(
                array('name' => $trend_dimension),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
            ),
            'orderBys' => array(
                array(
                    'dimension' => array(
                        'dimensionName' => $trend_dimension,
                    ),
                ),
            ),
            'limit' => $trend_limit,
        )
    );

    if (is_wp_error($trend_report)) {
        return $trend_report;
    }

    $pages_report = headless_ga4_run_report(
        array(
            'dateRanges' => array(
                $current_range,
            ),
            'dimensions' => array(
                array('name' => 'pagePath'),
                array('name' => 'pageTitle'),
            ),
            'metrics' => array(
                array('name' => 'screenPageViews'),
            ),
            'orderBys' => array(
                array(
                    'metric' => array(
                        'metricName' => 'screenPageViews',
                    ),
                    'desc' => true,
                ),
            ),
            'limit' => 5,
        )
    );

    if (is_wp_error($pages_report)) {
        return $pages_report;
    }

    $sources_report = headless_ga4_run_report(
        array(
            'dateRanges' => array(
                $current_range,
            ),
            'dimensions' => array(
                array('name' => 'sessionSource'),
                array('name' => 'sessionMedium'),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
            ),
            'orderBys' => array(
                array(
                    'metric' => array(
                        'metricName' => 'sessions',
                    ),
                    'desc' => true,
                ),
            ),
            'limit' => 5,
        )
    );

    if (is_wp_error($sources_report)) {
        return $sources_report;
    }

    $report = headless_ga4_build_dashboard_payload(
        headless_ga4_map_metric_row($summary_current),
        $previous_summary,
        $trend_report,
        $pages_report,
        $sources_report,
        $period_config
    );

    headless_ga4_set_cached_report($report, $period);

    return $report;
}
