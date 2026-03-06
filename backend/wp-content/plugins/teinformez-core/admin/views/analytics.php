<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
\TeInformez\Visitor_Analytics::create_table_if_missing();

$events_table = \TeInformez\Visitor_Analytics::table_name();
$delivery_table = $wpdb->prefix . 'teinformez_delivery_log';
$subs_table = $wpdb->prefix . 'teinformez_subscriptions';
$newsletter_table = $wpdb->prefix . 'teinformez_newsletter_subscribers';
$news_table = $wpdb->prefix . 'teinformez_news_queue';

$exists = static function(string $table) use ($wpdb): bool {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
};

$has_events = $exists($events_table);
$has_delivery = $exists($delivery_table);
$has_subs = $exists($subs_table);
$has_newsletter = $exists($newsletter_table);
$has_news = $exists($news_table);

$range = isset($_GET['range']) ? sanitize_key((string) $_GET['range']) : 'this_month';
if (!in_array($range, ['today', 'yesterday', 'this_week', 'this_month', 'custom'], true)) {
    $range = 'this_month';
}

$tz = wp_timezone();
$now = new DateTimeImmutable('now', $tz);
$start = $now;
$end = $now;

if ($range === 'today') {
    $start = $now->setTime(0, 0, 0);
} elseif ($range === 'yesterday') {
    $start = $now->modify('-1 day')->setTime(0, 0, 0);
    $end = $now->modify('-1 day')->setTime(23, 59, 59);
} elseif ($range === 'this_week') {
    $start = $now->modify('monday this week')->setTime(0, 0, 0);
} elseif ($range === 'this_month') {
    $start = $now->modify('first day of this month')->setTime(0, 0, 0);
} elseif ($range === 'custom') {
    $start_raw = isset($_GET['start_date']) ? sanitize_text_field((string) $_GET['start_date']) : '';
    $end_raw = isset($_GET['end_date']) ? sanitize_text_field((string) $_GET['end_date']) : '';
    $start_c = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_raw . ' 00:00:00', $tz);
    $end_c = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_raw . ' 23:59:59', $tz);
    if ($start_c instanceof DateTimeImmutable && $end_c instanceof DateTimeImmutable && $start_c <= $end_c) {
        $start = $start_c;
        $end = $end_c;
    } else {
        $range = 'this_month';
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
    }
}

$start_mysql = $start->format('Y-m-d H:i:s');
$end_mysql = $end->format('Y-m-d H:i:s');
$start_value = $start->format('Y-m-d');
$end_value = $end->format('Y-m-d');

$build_url = static function(array $extra = []) use ($range, $start_value, $end_value) {
    $params = ['page' => 'teinformez-analytics', 'range' => $range];
    if ($range === 'custom') {
        $params['start_date'] = $start_value;
        $params['end_date'] = $end_value;
    }
    return esc_url(add_query_arg(array_merge($params, $extra), admin_url('admin.php')));
};

$build_range = static function(string $r) use ($build_url) {
    return $build_url(['range' => $r, 'detail' => null]);
};

$detail = isset($_GET['detail']) ? sanitize_key((string) $_GET['detail']) : '';

$stats = [
    'unique_visits' => 0,
    'unique_visitors' => 0,
    'returning_visitors' => 0,
    'article_clicks' => 0,
    'avg_time_spent' => 0,
    'news_page_views' => 0,
    'delivery_sent' => 0,
    'delivery_opened' => 0,
    'delivery_clicked' => 0,
    'newsletter_active' => 0,
    'newsletter_new' => 0,
    'active_subscriptions' => 0,
];

if ($has_events) {
    $stats['unique_visits'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM {$events_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['unique_visitors'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM {$events_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['returning_visitors'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT e.visitor_hash) FROM {$events_table} e WHERE e.created_at BETWEEN %s AND %s AND EXISTS (SELECT 1 FROM {$events_table} p WHERE p.visitor_hash = e.visitor_hash AND p.created_at < %s)", $start_mysql, $end_mysql, $start_mysql));
    $stats['article_clicks'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE event_type='article_click' AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['avg_time_spent'] = (int) round((float) $wpdb->get_var($wpdb->prepare("SELECT AVG(duration_seconds) FROM {$events_table} WHERE event_type='time_spent' AND duration_seconds>0 AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql)));
    $stats['news_page_views'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE event_type='page_view' AND page_type='news' AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
}

if ($has_delivery) {
    $delivery = $wpdb->get_row($wpdb->prepare("SELECT SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) sent_count, SUM(CASE WHEN status='opened' THEN 1 ELSE 0 END) opened_count, SUM(CASE WHEN status='clicked' THEN 1 ELSE 0 END) clicked_count FROM {$delivery_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    if ($delivery) {
        $stats['delivery_sent'] = (int) ($delivery->sent_count ?? 0);
        $stats['delivery_opened'] = (int) ($delivery->opened_count ?? 0);
        $stats['delivery_clicked'] = (int) ($delivery->clicked_count ?? 0);
    }
}

if ($has_newsletter) {
    $stats['newsletter_active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newsletter_table} WHERE status='active'");
    $stats['newsletter_new'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$newsletter_table} WHERE subscribed_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
}

if ($has_subs) {
    $stats['active_subscriptions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs_table} WHERE is_active=1");
}

$top_articles = [];
if ($has_events && $has_news) {
    $top_articles = $wpdb->get_results($wpdb->prepare("SELECT e.page_id, COUNT(*) views, MAX(n.processed_title) processed_title, MAX(n.original_title) original_title FROM {$events_table} e LEFT JOIN {$news_table} n ON n.id = e.page_id WHERE e.event_type='page_view' AND e.page_type='news' AND e.page_id > 0 AND e.created_at BETWEEN %s AND %s GROUP BY e.page_id ORDER BY views DESC LIMIT 10", $start_mysql, $end_mysql));
}

$checks = [
    ['label' => 'Vizite unice >= vizitatori unici', 'ok' => $stats['unique_visits'] >= $stats['unique_visitors'], 'values' => $stats['unique_visits'] . ' / ' . $stats['unique_visitors']],
    ['label' => 'Reveniți <= vizitatori unici', 'ok' => $stats['returning_visitors'] <= $stats['unique_visitors'], 'values' => $stats['returning_visitors'] . ' / ' . $stats['unique_visitors']],
    ['label' => 'Opened <= Sent', 'ok' => $stats['delivery_opened'] <= $stats['delivery_sent'], 'values' => $stats['delivery_opened'] . ' / ' . $stats['delivery_sent']],
    ['label' => 'Clicked <= Sent', 'ok' => $stats['delivery_clicked'] <= $stats['delivery_sent'], 'values' => $stats['delivery_clicked'] . ' / ' . $stats['delivery_sent']],
];

$detail_title = '';
$detail_cols = [];
$detail_rows = [];
if ($detail === 'newsletter_active' && $has_newsletter) {
    $detail_title = 'Detalii abonați newsletter activi';
    $detail_cols = ['ID', 'Email', 'Status', 'Subscribed At'];
    $rows = $wpdb->get_results("SELECT id,email,status,subscribed_at FROM {$newsletter_table} WHERE status='active' ORDER BY subscribed_at DESC LIMIT 500");
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (string) $row->email, (string) $row->status, (string) $row->subscribed_at]; }
} elseif ($detail === 'newsletter_new' && $has_newsletter) {
    $detail_title = 'Detalii abonați newsletter noi';
    $detail_cols = ['ID', 'Email', 'Status', 'Subscribed At'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,email,status,subscribed_at FROM {$newsletter_table} WHERE subscribed_at BETWEEN %s AND %s ORDER BY subscribed_at DESC LIMIT 500", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (string) $row->email, (string) $row->status, (string) $row->subscribed_at]; }
} elseif ($detail === 'unique_visits' && $has_events) {
    $detail_title = 'Detalii vizite unice';
    $detail_cols = ['Session ID', 'First Seen', 'Last Seen', 'Evenimente'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT session_id, MIN(created_at) first_seen, MAX(created_at) last_seen, COUNT(*) events_count FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY session_id ORDER BY last_seen DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->session_id, (string) $row->first_seen, (string) $row->last_seen, (int) $row->events_count]; }
} elseif ($detail === 'unique_visitors' && $has_events) {
    $detail_title = 'Detalii vizitatori unici';
    $detail_cols = ['Visitor Hash', 'Sesiuni', 'Evenimente'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT visitor_hash, COUNT(DISTINCT session_id) sessions_count, COUNT(*) events_count FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY visitor_hash ORDER BY events_count DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->visitor_hash, (int) $row->sessions_count, (int) $row->events_count]; }
} elseif ($detail === 'article_clicks' && $has_events) {
    $detail_title = 'Detalii click-uri articole';
    $detail_cols = ['Data', 'Page ID', 'Path'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, page_id, page_path FROM {$events_table} WHERE event_type='article_click' AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->page_id, (string) $row->page_path]; }
} elseif ($detail === 'avg_time_spent' && $has_events) {
    $detail_title = 'Detalii time spent';
    $detail_cols = ['Data', 'Page ID', 'Durata(s)', 'Path'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, page_id, duration_seconds, page_path FROM {$events_table} WHERE event_type='time_spent' AND duration_seconds>0 AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->page_id, (int) $row->duration_seconds, (string) $row->page_path]; }
} elseif ($detail === 'delivery_sent' && $has_delivery) {
    $detail_title = 'Detalii delivery';
    $detail_cols = ['Data', 'User ID', 'News ID', 'Status', 'Canal'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at,user_id,news_id,status,channel FROM {$delivery_table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->user_id, (int) $row->news_id, (string) $row->status, (string) $row->channel]; }
} elseif ($detail === 'active_subscriptions' && $has_subs) {
    $detail_title = 'Detalii abonamente personalizare active';
    $detail_cols = ['ID', 'User ID', 'Categorie', 'Topic', 'Țară'];
    $rows = $wpdb->get_results("SELECT id,user_id,category_slug,topic_keyword,country_filter FROM {$subs_table} WHERE is_active=1 ORDER BY created_at DESC LIMIT 500");
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (int) $row->user_id, (string) $row->category_slug, (string) $row->topic_keyword, (string) $row->country_filter]; }
}

$return_rate = $stats['unique_visitors'] > 0 ? round(($stats['returning_visitors'] / $stats['unique_visitors']) * 100, 1) : 0;
$open_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_opened'] / $stats['delivery_sent']) * 100, 1) : 0;
$click_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_clicked'] / $stats['delivery_sent']) * 100, 1) : 0;
?>
<div class="wrap">
    <h1>Analytics Vizitatori</h1>
    <p style="color:#646970;margin-top:2px;">Interval: <strong><?php echo esc_html($start->format('d.m.Y')); ?></strong> - <strong><?php echo esc_html($end->format('d.m.Y')); ?></strong>. Refresh automat la 60 secunde.</p>
    <div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?php echo $build_range('today'); ?>" class="button <?php echo $range === 'today' ? 'button-primary' : ''; ?>">Azi</a>
        <a href="<?php echo $build_range('yesterday'); ?>" class="button <?php echo $range === 'yesterday' ? 'button-primary' : ''; ?>">Ieri</a>
        <a href="<?php echo $build_range('this_week'); ?>" class="button <?php echo $range === 'this_week' ? 'button-primary' : ''; ?>">Săptămâna curentă</a>
        <a href="<?php echo $build_range('this_month'); ?>" class="button <?php echo $range === 'this_month' ? 'button-primary' : ''; ?>">Luna curentă</a>
    </div>
    <form method="get" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;margin-bottom:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="page" value="teinformez-analytics">
        <input type="hidden" name="range" value="custom">
        <div><label style="display:block;font-size:12px;color:#646970;">De la</label><input type="date" name="start_date" value="<?php echo esc_attr($start_value); ?>"></div>
        <div><label style="display:block;font-size:12px;color:#646970;">Până la</label><input type="date" name="end_date" value="<?php echo esc_attr($end_value); ?>"></div>
        <button type="submit" class="button button-primary">Aplică interval custom</button>
    </form>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px;">
        <a href="<?php echo $build_url(['detail' => 'unique_visits']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Vizite unice</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['unique_visits']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'unique_visitors']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Vizitatori unici</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['unique_visitors']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'article_clicks']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Click-uri articole</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['article_clicks']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'avg_time_spent']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Timp mediu</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['avg_time_spent']); ?>s</div></a>
        <a href="<?php echo $build_url(['detail' => 'newsletter_active']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Subscribers activi</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['newsletter_active']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'newsletter_new']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Subscribers noi (interval)</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['newsletter_new']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'delivery_sent']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Delivery S/O/C</div><div style="font-size:20px;font-weight:700;"><?php echo number_format_i18n($stats['delivery_sent']); ?> / <?php echo number_format_i18n($stats['delivery_opened']); ?> / <?php echo number_format_i18n($stats['delivery_clicked']); ?></div></a>
        <a href="<?php echo $build_url(['detail' => 'active_subscriptions']); ?>" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;"><div style="color:#646970;font-size:12px;">Abonamente personalizare</div><div style="font-size:24px;font-weight:700;"><?php echo number_format_i18n($stats['active_subscriptions']); ?></div></a>
    </div>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;margin-bottom:14px;">
        <h2 style="margin-top:0;">Cross-check între date</h2>
        <table class="widefat striped"><tbody><?php foreach ($checks as $check): ?><tr><td><?php echo esc_html($check['label']); ?></td><td><?php echo !empty($check['ok']) ? '<span style="color:#0a7f42;font-weight:600;">OK</span>' : '<span style="color:#b32d2e;font-weight:600;">Mismatch</span>'; ?></td><td><?php echo esc_html($check['values']); ?></td></tr><?php endforeach; ?></tbody></table>
        <p style="margin-top:10px;color:#646970;">Returning rate: <?php echo esc_html($return_rate); ?>% | Open rate: <?php echo esc_html($open_rate); ?>% | CTR: <?php echo esc_html($click_rate); ?>%</p>
    </div>
    <?php if ($detail_title !== ''): ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;"><h2 style="margin:0;"><?php echo esc_html($detail_title); ?></h2><a class="button" href="<?php echo $build_url(['detail' => null]); ?>">Închide</a></div>
            <?php if (empty($detail_rows)): ?><p>Nu există date pentru detaliu.</p><?php else: ?>
                <table class="widefat striped" style="margin-top:10px;"><thead><tr><?php foreach ($detail_cols as $col): ?><th><?php echo esc_html((string) $col); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($detail_rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?php echo esc_html((string) $cell); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
        <h2 style="margin-top:0;">Top articole accesate (interval)</h2>
        <table class="widefat striped"><thead><tr><th>Titlu</th><th style="width:90px;">Views</th></tr></thead><tbody><?php if (empty($top_articles)): ?><tr><td colspan="2">Nu există date.</td></tr><?php else: ?><?php foreach ($top_articles as $article): ?><?php $title = !empty($article->processed_title) ? $article->processed_title : $article->original_title; ?><tr><td><?php echo esc_html(wp_trim_words((string) $title, 14, '...')); ?></td><td><?php echo number_format_i18n((int) $article->views); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table>
    </div>
</div>
<script>(function(){setTimeout(function(){window.location.reload();},60000);})();</script>
