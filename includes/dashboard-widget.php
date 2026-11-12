<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the dashboard widget.
 *
 * @return void
 */
function headless_ga4_register_dashboard_widget()
{
    if (!current_user_can(headless_ga4_admin_capability())) {
        return;
    }

    wp_add_dashboard_widget(
        'headless_ga4_dashboard_widget',
        __('Headless GA4', 'headless-ga4'),
        'headless_ga4_render_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'headless_ga4_register_dashboard_widget');

/**
 * Formats percentage deltas for display.
 *
 * @param float|null $value Percentage delta.
 *
 * @return string
 */
function headless_ga4_format_delta($value)
{
    if ($value === null) {
        return '—';
    }

    $prefix = $value > 0 ? '+' : '';

    return $prefix . number_format_i18n($value, 1) . '%';
}

/**
 * Returns the dashboard period requested in the URL.
 *
 * @return string
 */
function headless_ga4_get_requested_dashboard_period()
{
    $period = filter_input(INPUT_GET, 'headless_ga4_period', FILTER_SANITIZE_SPECIAL_CHARS);

    return headless_ga4_normalize_dashboard_period(is_string($period) ? $period : 'weekly');
}

/**
 * Returns the dashboard period posted by the refresh form.
 *
 * @return string
 */
function headless_ga4_get_posted_dashboard_period()
{
    $period = filter_input(INPUT_POST, 'headless_ga4_period', FILTER_SANITIZE_SPECIAL_CHARS);

    return headless_ga4_normalize_dashboard_period(is_string($period) ? $period : 'weekly');
}

/**
 * Returns allowed SVG markup for dashboard charts.
 *
 * @return array<string, array<string, bool>>
 */
function headless_ga4_get_allowed_chart_svg_html()
{
    return array(
        'svg' => array(
            'aria-label' => true,
            'class'      => true,
            'role'       => true,
            'viewbox'    => true,
            'viewBox'    => true,
        ),
        'g' => array(
            'class' => true,
        ),
        'polyline' => array(
            'class'  => true,
            'fill'   => true,
            'points' => true,
        ),
        'rect' => array(
            'class'  => true,
            'height' => true,
            'rx'     => true,
            'width'  => true,
            'x'      => true,
            'y'      => true,
        ),
    );
}

/**
 * Builds an SVG bar and line diagram for the sessions trend.
 *
 * @param array<int, array<string, mixed>> $trend      Trend rows.
 * @param string                           $chart_label Accessible chart label.
 *
 * @return string
 */
function headless_ga4_render_trend_svg($trend, $chart_label = '')
{
    if (empty($trend)) {
        return '';
    }

    $width = 520;
    $height = 180;
    $padding_x = 18;
    $padding_y = 16;
    $values = array_map(
        static function ($point) {
            return isset($point['value']) ? (int) $point['value'] : 0;
        },
        $trend
    );
    $max_value = max(1, max($values));
    $range = max(1, $max_value);
    $count = count($trend);
    $inner_width = $width - ($padding_x * 2);
    $inner_height = $height - ($padding_y * 2);
    $step_x = $count > 1 ? $inner_width / ($count - 1) : 0;
    $bar_slot = $count > 0 ? $inner_width / $count : $inner_width;
    $bar_width = max(3, min(22, $bar_slot * 0.55));
    $points = array();
    $bars = array();
    $chart_label = $chart_label !== '' ? $chart_label : __('Sessions trend', 'headless-ga4');

    foreach ($trend as $index => $point) {
        $value = isset($point['value']) ? (int) $point['value'] : 0;
        $x = $padding_x + ($step_x * $index);
        $bar_height = max(1, ($value / $range) * $inner_height);
        $bar_x = $x - ($bar_width / 2);
        $bar_y = $height - $padding_y - $bar_height;
        $y = $height - $padding_y - (($value / $range) * $inner_height);
        $points[] = round($x, 2) . ',' . round($y, 2);
        $bars[] = '<rect x="' . esc_attr((string) round($bar_x, 2)) . '" y="' . esc_attr((string) round($bar_y, 2)) . '" width="' . esc_attr((string) round($bar_width, 2)) . '" height="' . esc_attr((string) round($bar_height, 2)) . '" rx="3" />';
    }

    return '<svg viewBox="0 0 ' . esc_attr((string) $width) . ' ' . esc_attr((string) $height) . '" role="img" aria-label="' . esc_attr($chart_label) . '"><g class="headless-ga4-widget-chart-bars">' . implode('', $bars) . '</g><polyline class="headless-ga4-widget-chart-line" fill="none" points="' . esc_attr(implode(' ', $points)) . '" /></svg>';
}

/**
 * Builds a compact horizontal bar SVG for ranked metric rows.
 *
 * @param int    $value Metric value.
 * @param int    $max_value Largest value in the list.
 * @param string $label Accessible chart label.
 *
 * @return string
 */
function headless_ga4_render_horizontal_bar_svg($value, $max_value, $label)
{
    $value = max(0, (int) $value);
    $max_value = max(1, (int) $max_value);
    $width = 180;
    $height = 10;
    $bar_width = max(1, min($width, ($value / $max_value) * $width));

    return '<svg class="headless-ga4-widget-rank-bar" viewBox="0 0 ' . esc_attr((string) $width) . ' ' . esc_attr((string) $height) . '" role="img" aria-label="' . esc_attr($label) . '"><rect class="headless-ga4-widget-rank-bar-track" x="0" y="0" width="' . esc_attr((string) $width) . '" height="' . esc_attr((string) $height) . '" rx="5" /><rect class="headless-ga4-widget-rank-bar-value" x="0" y="0" width="' . esc_attr((string) round($bar_width, 2)) . '" height="' . esc_attr((string) $height) . '" rx="5" /></svg>';
}

/**
 * Handles the manual cache refresh action from the widget.
 *
 * @return void
 */
function headless_ga4_handle_refresh()
{
    if (!current_user_can(headless_ga4_admin_capability())) {
        wp_die(esc_html__('You do not have permission to access this page.', 'headless-ga4'));
    }

    check_admin_referer('headless_ga4_refresh');
    $period = headless_ga4_get_posted_dashboard_period();
    headless_ga4_clear_cached_data();
    $report = headless_ga4_fetch_dashboard_report(true, $period);

    if (is_wp_error($report)) {
        headless_ga4_set_admin_notice($report->get_error_message(), 'error');
    } else {
        headless_ga4_set_admin_notice(__('Dashboard data refreshed.', 'headless-ga4'));
    }

    wp_safe_redirect(add_query_arg('headless_ga4_period', $period, admin_url('index.php')));
    exit;
}
add_action('admin_post_headless_ga4_refresh', 'headless_ga4_handle_refresh');

/**
 * Renders the dashboard widget.
 *
 * @return void
 */
function headless_ga4_render_dashboard_widget()
{
    $state = 'ready';
    $error_message = '';
    $summary = array();
    $comparison = array();
    $trend = array();
    $top_pages = array();
    $top_sources = array();
    $last_refreshed_at = 0;
    $current_period = headless_ga4_get_requested_dashboard_period();
    $period_choices = headless_ga4_get_dashboard_period_choices();
    $period_label = isset($period_choices[$current_period]) ? $period_choices[$current_period] : __('Weekly', 'headless-ga4');
    $trend_label = __('Daily sessions', 'headless-ga4');
    $headless_ga4_top_pages_max_views = 0;
    $headless_ga4_top_sources_max_sessions = 0;
    $cards = array(
        'users'            => __('Users', 'headless-ga4'),
        'sessions'         => __('Sessions', 'headless-ga4'),
        'views'            => __('Views', 'headless-ga4'),
        'engaged_sessions' => __('Engaged sessions', 'headless-ga4'),
    );

    if (!headless_ga4_is_configured()) {
        $state = 'not_configured';
        require HEADLESS_GA4_PLUGIN_PATH . 'templates/dashboard-widget.php';
        return;
    }

    $report = headless_ga4_fetch_dashboard_report(false, $current_period);

    if (is_wp_error($report)) {
        $state = 'error';
        $error_message = $report->get_error_message();
        require HEADLESS_GA4_PLUGIN_PATH . 'templates/dashboard-widget.php';
        return;
    }

    $summary = isset($report['summary']) && is_array($report['summary']) ? $report['summary'] : array();
    $comparison = isset($report['comparison']) && is_array($report['comparison']) ? $report['comparison'] : array();
    $trend = isset($report['trend']) && is_array($report['trend']) ? $report['trend'] : array();
    $top_pages = isset($report['top_pages']) && is_array($report['top_pages']) ? $report['top_pages'] : array();
    $top_sources = isset($report['top_sources']) && is_array($report['top_sources']) ? $report['top_sources'] : array();
    $last_refreshed_at = isset($report['last_refreshed_at']) ? (int) $report['last_refreshed_at'] : 0;
    $trend_label = isset($report['trend_label']) ? (string) $report['trend_label'] : $trend_label;
    $period_label = isset($report['period_label']) ? (string) $report['period_label'] : $period_label;

    foreach ($top_pages as $headless_ga4_top_page) {
        $headless_ga4_top_pages_max_views = max(
            $headless_ga4_top_pages_max_views,
            isset($headless_ga4_top_page['views']) ? (int) $headless_ga4_top_page['views'] : 0
        );
    }

    foreach ($top_sources as $headless_ga4_top_source) {
        $headless_ga4_top_sources_max_sessions = max(
            $headless_ga4_top_sources_max_sessions,
            isset($headless_ga4_top_source['sessions']) ? (int) $headless_ga4_top_source['sessions'] : 0
        );
    }

    require HEADLESS_GA4_PLUGIN_PATH . 'templates/dashboard-widget.php';
}
