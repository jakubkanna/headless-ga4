<?php
/**
 * Dashboard widget template.
 *
 * @var array<string, string>          $cards
 * @var array<string, mixed>           $comparison
 * @var string                         $current_period
 * @var int                            $last_refreshed_at
 * @var string                         $period_label
 * @var array<string, string>          $period_choices
 * @var int                            $headless_ga4_top_pages_max_views
 * @var int                            $headless_ga4_top_sources_max_sessions
 * @var string                         $state
 * @var array<int, array<string, mixed>> $top_pages
 * @var array<int, array<string, mixed>> $top_sources
 * @var array<string, mixed>           $summary
 * @var array<int, array<string, mixed>> $trend
 * @var string                         $trend_label
 * @var string                         $error_message
 */

if (!defined('ABSPATH')) {
    exit;
}

if ($state === 'not_configured') :
    ?>
    <p><?php esc_html_e('Headless GA4 is not configured yet.', 'headless-ga4'); ?></p>
    <p>
        <a class="button button-secondary" href="<?php echo esc_url(headless_ga4_get_settings_page_url()); ?>">
            <?php esc_html_e('Open settings', 'headless-ga4'); ?>
        </a>
    </p>
    <?php
    return;
endif;

if ($state === 'error') :
    ?>
    <p><?php echo esc_html($error_message); ?></p>
    <p>
        <a class="button button-secondary" href="<?php echo esc_url(headless_ga4_get_settings_page_url()); ?>">
            <?php esc_html_e('Review settings', 'headless-ga4'); ?>
        </a>
    </p>
    <?php
    return;
endif;
?>
<div class="headless-ga4-widget-period-header">
    <div>
        <span class="headless-ga4-widget-label"><?php esc_html_e('Report period', 'headless-ga4'); ?></span>
        <strong><?php echo esc_html($period_label); ?></strong>
    </div>
    <div class="headless-ga4-widget-period-filter" aria-label="<?php esc_attr_e('Choose report period', 'headless-ga4'); ?>">
        <?php foreach ($period_choices as $headless_ga4_period_key => $headless_ga4_period_choice_label) : ?>
            <a
                class="headless-ga4-widget-period-link<?php echo $current_period === $headless_ga4_period_key ? ' is-active' : ''; ?>"
                href="<?php echo esc_url(add_query_arg('headless_ga4_period', $headless_ga4_period_key, admin_url('index.php'))); ?>"
                <?php echo $current_period === $headless_ga4_period_key ? 'aria-current="true"' : ''; ?>
            >
                <?php echo esc_html($headless_ga4_period_choice_label); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="headless-ga4-widget-grid">
    <?php foreach ($cards as $headless_ga4_card_key => $headless_ga4_card_label) : ?>
        <div class="headless-ga4-widget-card">
            <span class="headless-ga4-widget-label"><?php echo esc_html($headless_ga4_card_label); ?></span>
            <strong><?php echo esc_html(number_format_i18n(isset($summary[$headless_ga4_card_key]) ? (int) $summary[$headless_ga4_card_key] : 0)); ?></strong>
            <span class="headless-ga4-widget-delta">
                <?php
                $headless_ga4_delta = isset($comparison[$headless_ga4_card_key]['delta_percentage']) ? $comparison[$headless_ga4_card_key]['delta_percentage'] : null;
                echo esc_html(headless_ga4_format_delta(is_null($headless_ga4_delta) ? null : (float) $headless_ga4_delta));
                ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="headless-ga4-widget-layout">
    <div class="headless-ga4-widget-panel">
        <span class="headless-ga4-widget-label"><?php echo esc_html($trend_label); ?></span>
        <?php if (!empty($trend)) : ?>
            <div class="headless-ga4-widget-chart">
                <?php echo wp_kses(headless_ga4_render_trend_svg($trend, $trend_label), headless_ga4_get_allowed_chart_svg_html()); ?>
            </div>
            <div class="headless-ga4-widget-list">
                <?php foreach ($trend as $headless_ga4_point) : ?>
                    <div class="headless-ga4-widget-row">
                        <span><?php echo esc_html((string) $headless_ga4_point['label']); ?></span>
                        <span class="headless-ga4-widget-muted"><?php echo esc_html(number_format_i18n((int) $headless_ga4_point['value'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No trend data is available yet.', 'headless-ga4'); ?></p>
        <?php endif; ?>
    </div>

    <div class="headless-ga4-widget-panel">
        <span class="headless-ga4-widget-label"><?php esc_html_e('Top pages', 'headless-ga4'); ?></span>
        <?php if (!empty($top_pages)) : ?>
            <div class="headless-ga4-widget-list">
                <?php foreach ($top_pages as $headless_ga4_page) : ?>
                    <div class="headless-ga4-widget-row">
                        <div>
                            <strong class="headless-ga4-widget-item-title"><?php echo esc_html($headless_ga4_page['title'] !== '' ? (string) $headless_ga4_page['title'] : (string) $headless_ga4_page['path']); ?></strong>
                            <div class="headless-ga4-widget-muted"><?php echo esc_html((string) $headless_ga4_page['path']); ?></div>
                            <?php
                            echo wp_kses(
                                headless_ga4_render_horizontal_bar_svg(
                                    (int) $headless_ga4_page['views'],
                                    $headless_ga4_top_pages_max_views,
                                    sprintf(
                                        /* translators: %s: number of page views. */
                                        __('%s page views', 'headless-ga4'),
                                        number_format_i18n((int) $headless_ga4_page['views'])
                                    )
                                ),
                                headless_ga4_get_allowed_chart_svg_html()
                            );
                            ?>
                        </div>
                        <span class="headless-ga4-widget-muted"><?php echo esc_html(number_format_i18n((int) $headless_ga4_page['views'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php esc_html_e('No page data is available yet.', 'headless-ga4'); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="headless-ga4-widget-panel headless-ga4-widget-panel-spaced">
    <span class="headless-ga4-widget-label"><?php esc_html_e('Top traffic sources', 'headless-ga4'); ?></span>
    <?php if (!empty($top_sources)) : ?>
        <div class="headless-ga4-widget-list">
            <?php foreach ($top_sources as $headless_ga4_source) : ?>
                <div class="headless-ga4-widget-row">
                    <div>
                        <strong class="headless-ga4-widget-item-title"><?php echo esc_html((string) $headless_ga4_source['source']); ?></strong>
                        <div class="headless-ga4-widget-muted"><?php echo esc_html((string) $headless_ga4_source['medium']); ?></div>
                        <?php
                        echo wp_kses(
                            headless_ga4_render_horizontal_bar_svg(
                                (int) $headless_ga4_source['sessions'],
                                $headless_ga4_top_sources_max_sessions,
                                sprintf(
                                    /* translators: %s: number of sessions. */
                                    __('%s sessions', 'headless-ga4'),
                                    number_format_i18n((int) $headless_ga4_source['sessions'])
                                )
                            ),
                            headless_ga4_get_allowed_chart_svg_html()
                        );
                        ?>
                    </div>
                    <span class="headless-ga4-widget-muted"><?php echo esc_html(number_format_i18n((int) $headless_ga4_source['sessions'])); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No traffic source data is available yet.', 'headless-ga4'); ?></p>
    <?php endif; ?>
</div>

<div class="headless-ga4-widget-footer">
    <span class="headless-ga4-widget-muted">
        <?php
        if ($last_refreshed_at > 0) {
            printf(
                /* translators: %s: formatted date and time. */
                esc_html__('Last refreshed: %s', 'headless-ga4'),
                esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_refreshed_at))
            );
        } else {
            esc_html_e('Last refreshed: just now', 'headless-ga4');
        }
        ?>
    </span>

    <div class="headless-ga4-widget-actions">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('headless_ga4_refresh'); ?>
            <input type="hidden" name="action" value="headless_ga4_refresh" />
            <input type="hidden" name="headless_ga4_period" value="<?php echo esc_attr($current_period); ?>" />
            <?php submit_button(__('Refresh data', 'headless-ga4'), 'secondary', 'submit', false); ?>
        </form>
        <a class="button button-secondary" href="<?php echo esc_url(headless_ga4_get_settings_page_url()); ?>">
            <?php esc_html_e('Settings', 'headless-ga4'); ?>
        </a>
    </div>
</div>
