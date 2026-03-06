<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

\TeInformez\Visitor_Analytics::create_table_if_missing();

$events_table = \TeInformez\Visitor_Analytics::table_name();
$news_table = $wpdb->prefix . 'teinformez_news_queue';
$juridic_table = $wpdb->prefix . 'teinformez_juridic_qa';
$delivery_table = $wpdb->prefix . 'teinformez_delivery_log';
$subs_table = $wpdb->prefix . 'teinformez_subscriptions';

$table_exists = static function(string $table_name) use ($wpdb): bool {
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    return $found === $table_name;
};

$has_news = $table_exists($news_table);
$has_juridic = $table_exists($juridic_table);
$has_delivery = $table_exists($delivery_table);
$has_subs = $table_exists($subs_table);
$has_events = $table_exists($events_table);

$range = isset($_GET['range']) ? sanitize_key((string) $_GET['range']) : 'this_month';
$allowed_ranges = ['today', 'yesterday', 'this_week', 'this_month', 'custom'];
if (!in_array($range, $allowed_ranges, true)) {
    $range = 'this_month';
}

$tz = wp_timezone();
$now = new DateTimeImmutable('now', $tz);
$start = $now;
$end = $now;

switch ($range) {
    case 'today':
        $start = $now->setTime(0, 0, 0);
        break;
    case 'yesterday':
        $start = $now->modify('-1 day')->setTime(0, 0, 0);
        $end = $now->modify('-1 day')->setTime(23, 59, 59);
        break;
    case 'this_week':
        $start = $now->modify('monday this week')->setTime(0, 0, 0);
        break;
    case 'this_month':
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        break;
    case 'custom':
        $start_raw = isset($_GET['start_date']) ? sanitize_text_field((string) $_GET['start_date']) : '';
        $end_raw = isset($_GET['end_date']) ? sanitize_text_field((string) $_GET['end_date']) : '';

        $start_candidate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_raw . ' 00:00:00', $tz);
        $end_candidate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_raw . ' 23:59:59', $tz);

        if ($start_candidate instanceof DateTimeImmutable && $end_candidate instanceof DateTimeImmutable && $start_candidate <= $end_candidate) {
            $start = $start_candidate;
            $end = $end_candidate;
        } else {
            $range = 'this_month';
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
            $end = $now;
        }
        break;
}

$start_mysql = $start->format('Y-m-d H:i:s');
$end_mysql = $end->format('Y-m-d H:i:s');
$start_value = $start->format('Y-m-d');
$end_value = $end->format('Y-m-d');

$stats = [
    'unique_visits' => 0,
    'unique_visitors' => 0,
    'returning_visitors' => 0,
    'article_clicks' => 0,
    'avg_time_spent' => 0,
    'news_views_total' => 0,
    'juridic_views_total' => 0,
    'delivery_sent' => 0,
    'delivery_opened' => 0,
    'delivery_clicked' => 0,
    'active_subscriptions' => 0,
];

if ($has_events) {
    $stats['unique_visits'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT session_id)
         FROM {$events_table}
         WHERE event_type = 'page_view' AND created_at BETWEEN %s AND %s",
        $start_mysql,
        $end_mysql
    ));

    $stats['unique_visitors'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT visitor_hash)
         FROM {$events_table}
         WHERE created_at BETWEEN %s AND %s",
        $start_mysql,
        $end_mysql
    ));

    $stats['article_clicks'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$events_table}
         WHERE event_type = 'article_click' AND created_at BETWEEN %s AND %s",
        $start_mysql,
        $end_mysql
    ));

    $stats['avg_time_spent'] = (int) round((float) $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(duration_seconds)
         FROM {$events_table}
         WHERE event_type = 'time_spent'
           AND duration_seconds > 0
           AND created_at BETWEEN %s AND %s",
        $start_mysql,
        $end_mysql
    )));

    $stats['returning_visitors'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT e.visitor_hash)
         FROM {$events_table} e
         WHERE e.created_at BETWEEN %s AND %s
           AND EXISTS (
               SELECT 1 FROM {$events_table} p
               WHERE p.visitor_hash = e.visitor_hash
                 AND p.created_at < %s
           )",
        $start_mysql,
        $end_mysql,
        $start_mysql
    ));
}

if ($has_news) {
    $stats['news_views_total'] = (int) $wpdb->get_var("SELECT COALESCE(SUM(view_count), 0) FROM {$news_table}");
}

if ($has_juridic) {
    $stats['juridic_views_total'] = (int) $wpdb->get_var("SELECT COALESCE(SUM(view_count), 0) FROM {$juridic_table}");
}

if ($has_delivery) {
    $delivery_rows = $wpdb->get_row($wpdb->prepare(
        "SELECT
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) AS opened_count,
            SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) AS clicked_count
         FROM {$delivery_table}
         WHERE created_at BETWEEN %s AND %s",
        $start_mysql,
        $end_mysql
    ));

    if ($delivery_rows) {
        $stats['delivery_sent'] = (int) ($delivery_rows->sent_count ?? 0);
        $stats['delivery_opened'] = (int) ($delivery_rows->opened_count ?? 0);
        $stats['delivery_clicked'] = (int) ($delivery_rows->clicked_count ?? 0);
    }
}

if ($has_subs) {
    $stats['active_subscriptions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs_table} WHERE is_active = 1");
}

$open_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_opened'] / $stats['delivery_sent']) * 100, 1) : 0;
$click_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_clicked'] / $stats['delivery_sent']) * 100, 1) : 0;
$return_rate = $stats['unique_visitors'] > 0 ? round(($stats['returning_visitors'] / $stats['unique_visitors']) * 100, 1) : 0;

$top_articles = [];
if ($has_events && $has_news) {
    $top_articles = $wpdb->get_results($wpdb->prepare(
        "SELECT
            e.page_id,
            COUNT(*) AS views,
            MAX(n.processed_title) AS processed_title,
            MAX(n.original_title) AS original_title
         FROM {$events_table} e
         LEFT JOIN {$news_table} n ON n.id = e.page_id
         WHERE e.event_type = 'page_view'
           AND e.page_type = 'news'
           AND e.page_id > 0
           AND e.created_at BETWEEN %s AND %s
         GROUP BY e.page_id
         ORDER BY views DESC
         LIMIT 10",
        $start_mysql,
        $end_mysql
    ));
}

$top_juridic = [];
if ($has_events && $has_juridic) {
    $top_juridic = $wpdb->get_results($wpdb->prepare(
        "SELECT
            e.page_id,
            COUNT(*) AS views,
            MAX(j.question_anonymized) AS question
         FROM {$events_table} e
         LEFT JOIN {$juridic_table} j ON j.id = e.page_id
         WHERE e.event_type = 'page_view'
           AND e.page_type = 'juridic'
           AND e.page_id > 0
           AND e.created_at BETWEEN %s AND %s
         GROUP BY e.page_id
         ORDER BY views DESC
         LIMIT 10",
        $start_mysql,
        $end_mysql
    ));
}

$visitors_trend = [];
if ($has_events) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            DATE(created_at) AS day,
            COUNT(DISTINCT session_id) AS visits,
            COUNT(DISTINCT visitor_hash) AS visitors,
            SUM(CASE WHEN event_type = 'article_click' THEN 1 ELSE 0 END) AS clicks
         FROM {$events_table}
         WHERE created_at BETWEEN %s AND %s
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at) ASC",
        $start_mysql,
        $end_mysql
    ));

    foreach ($rows as $row) {
        $visitors_trend[] = [
            'day' => $row->day,
            'visits' => (int) $row->visits,
            'visitors' => (int) $row->visitors,
            'clicks' => (int) $row->clicks,
        ];
    }
}

$max_visits = 1;
foreach ($visitors_trend as $point) {
    if ($point['visits'] > $max_visits) {
        $max_visits = $point['visits'];
    }
}

$make_url = static function(string $value, array $extra = []) {
    $params = array_merge([
        'page' => 'teinformez-analytics',
        'range' => $value,
    ], $extra);

    return esc_url(add_query_arg($params, admin_url('admin.php')));
};
?>

<div class="wrap">
    <h1>Analytics Vizitatori</h1>

    <p style="color:#646970;margin-top:2px;">Interval: <strong><?php echo esc_html($start->format('d.m.Y')); ?></strong> - <strong><?php echo esc_html($end->format('d.m.Y')); ?></strong>. Refresh automat la 60 secunde.</p>

    <div style="margin: 12px 0 12px;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?php echo $make_url('today'); ?>" class="button <?php echo $range === 'today' ? 'button-primary' : ''; ?>">Azi</a>
        <a href="<?php echo $make_url('yesterday'); ?>" class="button <?php echo $range === 'yesterday' ? 'button-primary' : ''; ?>">Ieri</a>
        <a href="<?php echo $make_url('this_week'); ?>" class="button <?php echo $range === 'this_week' ? 'button-primary' : ''; ?>">Săptămâna curentă</a>
        <a href="<?php echo $make_url('this_month'); ?>" class="button <?php echo $range === 'this_month' ? 'button-primary' : ''; ?>">Luna curentă</a>
    </div>

    <form method="get" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;margin-bottom:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="page" value="teinformez-analytics">
        <input type="hidden" name="range" value="custom">
        <div>
            <label for="start_date" style="display:block;font-size:12px;color:#646970;">De la</label>
            <input id="start_date" type="date" name="start_date" value="<?php echo esc_attr($start_value); ?>">
        </div>
        <div>
            <label for="end_date" style="display:block;font-size:12px;color:#646970;">Până la</label>
            <input id="end_date" type="date" name="end_date" value="<?php echo esc_attr($end_value); ?>">
        </div>
        <button type="submit" class="button button-primary">Aplică interval custom</button>
    </form>

    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;margin-bottom:14px;">
        <h2 style="margin:0 0 8px;">Brainstorming rapid (best practices, simplu)</h2>
        <ol style="margin:0 0 0 18px;">
            <li>Colectează doar date anonime (visitor id hash), fără PII.</li>
            <li>Separă evenimentele cheie: page_view, article_click, time_spent.</li>
            <li>Folosește intervale predefinite + custom, fără dashboard complex.</li>
            <li>Păstrează KPI-urile principale în carduri și topuri concise.</li>
            <li>Refresh periodic, fără polling agresiv.</li>
        </ol>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-bottom:18px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Vizite unice (sesiuni)</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['unique_visits']); ?></div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Vizitatori unici</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['unique_visitors']); ?></div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Vizitatori reveniți</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['returning_visitors']); ?></div>
            <div style="color:#646970;font-size:12px;margin-top:4px;">Rată revenire: <?php echo esc_html($return_rate); ?>%</div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Click-uri pe articole</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['article_clicks']); ?></div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Timp mediu pe pagină</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['avg_time_spent']); ?>s</div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Views total știri / juridic</div>
            <div style="font-size:20px;font-weight:700;">
                <?php echo number_format_i18n($stats['news_views_total']); ?> /
                <?php echo number_format_i18n($stats['juridic_views_total']); ?>
            </div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Delivery sent / opened / clicked</div>
            <div style="font-size:20px;font-weight:700;">
                <?php echo number_format_i18n($stats['delivery_sent']); ?> /
                <?php echo number_format_i18n($stats['delivery_opened']); ?> /
                <?php echo number_format_i18n($stats['delivery_clicked']); ?>
            </div>
            <div style="color:#646970;font-size:12px;margin-top:4px;">Open rate: <?php echo esc_html($open_rate); ?>% | CTR: <?php echo esc_html($click_rate); ?>%</div>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <div style="color:#646970;font-size:12px;">Abonări active</div>
            <div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['active_subscriptions']); ?></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;align-items:start;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Trend vizite/zi</h2>
            <?php if (empty($visitors_trend)): ?>
                <p>Nu există date în intervalul selectat.</p>
            <?php else: ?>
                <div style="display:flex;gap:8px;align-items:flex-end;min-height:180px;border-bottom:1px solid #dcdcde;padding:8px 0;">
                    <?php foreach ($visitors_trend as $point): ?>
                        <?php $height = max(6, (int) round(($point['visits'] / $max_visits) * 150)); ?>
                        <div title="<?php echo esc_attr($point['day'] . ' | vizite ' . $point['visits'] . ' | vizitatori ' . $point['visitors'] . ' | clickuri ' . $point['clicks']); ?>" style="flex:1;min-width:8px;">
                            <div style="height:<?php echo (int) $height; ?>px;background:linear-gradient(180deg,#2271b1,#72aee6);border-radius:3px 3px 0 0;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Returning visitors</h2>
            <table class="widefat striped">
                <tbody>
                    <tr><td>Vizitatori unici</td><td><?php echo number_format_i18n($stats['unique_visitors']); ?></td></tr>
                    <tr><td>Reveniți</td><td><?php echo number_format_i18n($stats['returning_visitors']); ?></td></tr>
                    <tr><td>Rată revenire</td><td><?php echo esc_html($return_rate); ?>%</td></tr>
                    <tr><td>Timp mediu/pagină</td><td><?php echo number_format_i18n($stats['avg_time_spent']); ?>s</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Top articole accesate (interval)</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Titlu</th>
                        <th style="width:90px;">Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_articles)): ?>
                        <tr><td colspan="2">Nu există încă date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_articles as $article): ?>
                            <?php $title = !empty($article->processed_title) ? $article->processed_title : $article->original_title; ?>
                            <tr>
                                <td><?php echo esc_html(wp_trim_words((string) $title, 14, '...')); ?></td>
                                <td><?php echo number_format_i18n((int) $article->views); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Top Q&A juridic (interval)</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Întrebare</th>
                        <th style="width:90px;">Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_juridic)): ?>
                        <tr><td colspan="2">Nu există încă date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_juridic as $item): ?>
                            <tr>
                                <td><?php echo esc_html(wp_trim_words((string) $item->question, 14, '...')); ?></td>
                                <td><?php echo number_format_i18n((int) $item->views); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    setTimeout(function () {
        window.location.reload();
    }, 60000);
})();
</script>
