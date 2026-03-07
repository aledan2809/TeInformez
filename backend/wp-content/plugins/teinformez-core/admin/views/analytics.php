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

$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'custom';
if (!in_array($tab, ['custom', 'google'], true)) {
    $tab = 'custom';
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
$start_ga = $start->format('Y-m-d');
$end_ga = $end->format('Y-m-d');

$build_url = static function(array $extra = []) use ($range, $start_value, $end_value, $tab) {
    $params = [
        'page' => 'teinformez-analytics',
        'tab' => $tab,
        'range' => $range,
    ];
    if ($range === 'custom') {
        $params['start_date'] = $start_value;
        $params['end_date'] = $end_value;
    }

    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    return esc_url(add_query_arg($params, admin_url('admin.php')));
};

$build_range = static function(string $r) use ($build_url) {
    return $build_url(['range' => $r, 'detail' => null]);
};

$build_tab = static function(string $t) use ($build_url) {
    return $build_url(['tab' => $t, 'detail' => null]);
};

$detail = isset($_GET['detail']) ? sanitize_key((string) $_GET['detail']) : '';

$stats = [
    'unique_visits' => 0,
    'unique_visitors' => 0,
    'new_visitors' => 0,
    'unique_visits_all_time' => 0,
    'unique_visitors_all_time' => 0,
    'returning_visitors' => 0,
    'article_clicks' => 0,
    'avg_time_spent' => 0,
    'news_page_views' => 0,
    'total_events' => 0,
    'engaged_sessions' => 0,
    'newsletter_active_total' => 0,
    'newsletter_new' => 0,
    'newsletter_tracked' => 0,
    'delivery_sent' => 0,
    'delivery_opened' => 0,
    'delivery_clicked' => 0,
    'active_subscriptions' => 0,
];

if ($has_events) {
    $stats['unique_visits'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM {$events_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['unique_visitors'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM {$events_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['new_visitors'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT e.visitor_hash) FROM {$events_table} e WHERE e.created_at BETWEEN %s AND %s AND NOT EXISTS (SELECT 1 FROM {$events_table} p WHERE p.visitor_hash = e.visitor_hash AND p.created_at < %s)", $start_mysql, $end_mysql, $start_mysql));
    $stats['unique_visits_all_time'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$events_table}");
    $stats['unique_visitors_all_time'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT visitor_hash) FROM {$events_table}");
    $stats['returning_visitors'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT e.visitor_hash) FROM {$events_table} e WHERE e.created_at BETWEEN %s AND %s AND EXISTS (SELECT 1 FROM {$events_table} p WHERE p.visitor_hash = e.visitor_hash AND p.created_at < %s)", $start_mysql, $end_mysql, $start_mysql));
    $stats['article_clicks'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE event_type='article_click' AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['avg_time_spent'] = (int) round((float) $wpdb->get_var($wpdb->prepare("SELECT AVG(duration_seconds) FROM {$events_table} WHERE event_type='time_spent' AND duration_seconds>0 AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql)));
    $stats['news_page_views'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE event_type='page_view' AND page_type='news' AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['total_events'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
    $stats['engaged_sessions'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM {$events_table} WHERE created_at BETWEEN %s AND %s AND (duration_seconds >= 30 OR event_type IN ('article_click', 'newsletter_subscribe'))", $start_mysql, $end_mysql));
    $stats['newsletter_tracked'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events_table} WHERE event_type='newsletter_subscribe' AND created_at BETWEEN %s AND %s", $start_mysql, $end_mysql));
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
    $stats['newsletter_active_total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newsletter_table} WHERE status='active'");
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
    ['label' => 'Sessions >= active users', 'ok' => $stats['unique_visits'] >= $stats['unique_visitors'], 'values' => $stats['unique_visits'] . ' / ' . $stats['unique_visitors']],
    ['label' => 'Returning users <= active users', 'ok' => $stats['returning_visitors'] <= $stats['unique_visitors'], 'values' => $stats['returning_visitors'] . ' / ' . $stats['unique_visitors']],
    ['label' => 'New subscribers (range) <= sessions', 'ok' => $stats['newsletter_new'] <= $stats['unique_visits'] || $stats['newsletter_new'] === 0, 'values' => $stats['newsletter_new'] . ' / ' . $stats['unique_visits']],
    ['label' => 'Subscriber track events >= new subscribers', 'ok' => $stats['newsletter_tracked'] >= $stats['newsletter_new'] || $stats['newsletter_new'] === 0, 'values' => $stats['newsletter_tracked'] . ' / ' . $stats['newsletter_new']],
    ['label' => 'Active subscribers total <= all-time unique users', 'ok' => $stats['newsletter_active_total'] <= $stats['unique_visitors_all_time'] || $stats['unique_visitors_all_time'] === 0, 'values' => $stats['newsletter_active_total'] . ' / ' . $stats['unique_visitors_all_time']],
    ['label' => 'Opened <= Sent', 'ok' => $stats['delivery_opened'] <= $stats['delivery_sent'], 'values' => $stats['delivery_opened'] . ' / ' . $stats['delivery_sent']],
    ['label' => 'Clicked <= Sent', 'ok' => $stats['delivery_clicked'] <= $stats['delivery_sent'], 'values' => $stats['delivery_clicked'] . ' / ' . $stats['delivery_sent']],
];

$detail_title = '';
$detail_cols = [];
$detail_rows = [];
if ($detail === 'newsletter_active_total' && $has_newsletter) {
    $detail_title = 'Active Newsletter Subscribers Details';
    $detail_cols = ['ID', 'Email', 'Status', 'Subscribed At'];
    $rows = $wpdb->get_results("SELECT id,email,status,subscribed_at FROM {$newsletter_table} WHERE status='active' ORDER BY subscribed_at DESC LIMIT 500");
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (string) $row->email, (string) $row->status, (string) $row->subscribed_at]; }
} elseif ($detail === 'newsletter_new' && $has_newsletter) {
    $detail_title = 'New Newsletter Subscribers Details (Range)';
    $detail_cols = ['ID', 'Email', 'Status', 'Subscribed At'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,email,status,subscribed_at FROM {$newsletter_table} WHERE subscribed_at BETWEEN %s AND %s ORDER BY subscribed_at DESC LIMIT 500", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (string) $row->email, (string) $row->status, (string) $row->subscribed_at]; }
} elseif ($detail === 'unique_visits' && $has_events) {
    $detail_title = 'Sessions Details';
    $detail_cols = ['Session ID', 'First Seen', 'Last Seen', 'Events'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT session_id, MIN(created_at) first_seen, MAX(created_at) last_seen, COUNT(*) events_count FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY session_id ORDER BY last_seen DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->session_id, (string) $row->first_seen, (string) $row->last_seen, (int) $row->events_count]; }
} elseif ($detail === 'unique_visitors' && $has_events) {
    $detail_title = 'Active Users Details';
    $detail_cols = ['Visitor Hash', 'Sessions', 'Events'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT visitor_hash, COUNT(DISTINCT session_id) sessions_count, COUNT(*) events_count FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY visitor_hash ORDER BY events_count DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->visitor_hash, (int) $row->sessions_count, (int) $row->events_count]; }
} elseif ($detail === 'unique_visits_all_time' && $has_events) {
    $detail_title = 'All-time Sessions Details';
    $detail_cols = ['Session ID', 'First Seen', 'Last Seen', 'Events'];
    $rows = $wpdb->get_results("SELECT session_id, MIN(created_at) first_seen, MAX(created_at) last_seen, COUNT(*) events_count FROM {$events_table} GROUP BY session_id ORDER BY last_seen DESC LIMIT 200");
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->session_id, (string) $row->first_seen, (string) $row->last_seen, (int) $row->events_count]; }
} elseif ($detail === 'unique_visitors_all_time' && $has_events) {
    $detail_title = 'All-time Active Users Details';
    $detail_cols = ['Visitor Hash', 'Sessions', 'Events'];
    $rows = $wpdb->get_results("SELECT visitor_hash, COUNT(DISTINCT session_id) sessions_count, COUNT(*) events_count FROM {$events_table} GROUP BY visitor_hash ORDER BY events_count DESC LIMIT 200");
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->visitor_hash, (int) $row->sessions_count, (int) $row->events_count]; }
} elseif ($detail === 'returning_visitors' && $has_events) {
    $detail_title = 'Returning Users Details';
    $detail_cols = ['Visitor Hash', 'First Seen In Range', 'Last Seen In Range'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT e.visitor_hash, MIN(e.created_at) first_seen, MAX(e.created_at) last_seen FROM {$events_table} e WHERE e.created_at BETWEEN %s AND %s AND EXISTS (SELECT 1 FROM {$events_table} p WHERE p.visitor_hash=e.visitor_hash AND p.created_at < %s) GROUP BY e.visitor_hash ORDER BY last_seen DESC LIMIT 200", $start_mysql, $end_mysql, $start_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->visitor_hash, (string) $row->first_seen, (string) $row->last_seen]; }
} elseif ($detail === 'news_page_views' && $has_events) {
    $detail_title = 'Page Views Details';
    $detail_cols = ['Date', 'Page ID', 'Path'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, page_id, page_path FROM {$events_table} WHERE event_type='page_view' AND page_type='news' AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->page_id, (string) $row->page_path]; }
} elseif ($detail === 'article_clicks' && $has_events) {
    $detail_title = 'Article Clicks Details';
    $detail_cols = ['Date', 'Page ID', 'Path'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, page_id, page_path FROM {$events_table} WHERE event_type='article_click' AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->page_id, (string) $row->page_path]; }
} elseif ($detail === 'avg_time_spent' && $has_events) {
    $detail_title = 'Average Time Details';
    $detail_cols = ['Date', 'Page ID', 'Duration(s)', 'Path'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, page_id, duration_seconds, page_path FROM {$events_table} WHERE event_type='time_spent' AND duration_seconds>0 AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->page_id, (int) $row->duration_seconds, (string) $row->page_path]; }
} elseif ($detail === 'newsletter_tracked' && $has_events) {
    $detail_title = 'Subscriber Track Events Details';
    $detail_cols = ['Date', 'Session ID', 'Path', 'Metadata'];
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at, session_id, page_path, metadata FROM {$events_table} WHERE event_type='newsletter_subscribe' AND created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 500", $start_mysql, $end_mysql));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (string) $row->session_id, (string) $row->page_path, (string) $row->metadata]; }
} elseif (in_array($detail, ['delivery_sent', 'delivery_opened', 'delivery_clicked'], true) && $has_delivery) {
    $detail_title = 'Delivery ' . ucfirst(str_replace('delivery_', '', $detail)) . ' Details';
    $detail_cols = ['Date', 'User ID', 'News ID', 'Status', 'Channel'];
    $status_filter = str_replace('delivery_', '', $detail);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT created_at,user_id,news_id,status,channel FROM {$delivery_table} WHERE created_at BETWEEN %s AND %s AND status=%s ORDER BY created_at DESC LIMIT 200", $start_mysql, $end_mysql, $status_filter));
    foreach ($rows as $row) { $detail_rows[] = [(string) $row->created_at, (int) $row->user_id, (int) $row->news_id, (string) $row->status, (string) $row->channel]; }
} elseif ($detail === 'active_subscriptions' && $has_subs) {
    $detail_title = 'Active Personalization Subscriptions Details';
    $detail_cols = ['ID', 'User ID', 'Category', 'Topic', 'Country'];
    $rows = $wpdb->get_results("SELECT id,user_id,category_slug,topic_keyword,country_filter FROM {$subs_table} WHERE is_active=1 ORDER BY created_at DESC LIMIT 500");
    foreach ($rows as $row) { $detail_rows[] = [(int) $row->id, (int) $row->user_id, (string) $row->category_slug, (string) $row->topic_keyword, (string) $row->country_filter]; }
}

$return_rate = $stats['unique_visitors'] > 0 ? round(($stats['returning_visitors'] / $stats['unique_visitors']) * 100, 1) : 0;
$open_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_opened'] / $stats['delivery_sent']) * 100, 1) : 0;
$click_rate = $stats['delivery_sent'] > 0 ? round(($stats['delivery_clicked'] / $stats['delivery_sent']) * 100, 1) : 0;
$pages_per_session = $stats['unique_visits'] > 0 ? round($stats['news_page_views'] / $stats['unique_visits'], 2) : 0;
$bounce_rate = $stats['unique_visits'] > 0 ? round(max(0, ($stats['unique_visits'] - $stats['article_clicks']) / $stats['unique_visits']) * 100, 1) : 0;
$engagement_rate = $stats['unique_visits'] > 0 ? round(($stats['engaged_sessions'] / $stats['unique_visits']) * 100, 1) : 0;
$events_per_session = $stats['unique_visits'] > 0 ? round($stats['total_events'] / $stats['unique_visits'], 2) : 0;
$article_ctr = $stats['news_page_views'] > 0 ? round(($stats['article_clicks'] / $stats['news_page_views']) * 100, 1) : 0;

$ga_summary = [];
$ga_top_pages = [];
$ga_error = '';
if ($tab === 'google') {
    $ga_service = new \TeInformez\Google_Analytics_Service();
    if (!$ga_service->is_configured()) {
        $ga_error = 'Google Analytics is not configured. Complete GA4 settings first.';
    } else {
        $summary = $ga_service->get_summary($start_ga, $end_ga);
        if (is_wp_error($summary)) {
            $ga_error = $summary->get_error_message();
        } else {
            $ga_summary = $summary;
            $pages = $ga_service->get_top_pages($start_ga, $end_ga, 10);
            if (is_wp_error($pages)) {
                $ga_error = $pages->get_error_message();
            } else {
                $ga_top_pages = $pages;
            }
        }
    }
}

$custom_metric_values = [
    'sessions' => $stats['unique_visits'],
    'active_users' => $stats['unique_visitors'],
    'new_users' => $stats['new_visitors'],
    'returning_users' => $stats['returning_visitors'],
    'page_views' => $stats['news_page_views'],
    'avg_session_duration' => $stats['avg_time_spent'],
    'event_count' => $stats['total_events'],
    'pages_per_session' => $pages_per_session,
    'bounce_rate' => $bounce_rate,
    'engagement_rate' => $engagement_rate,
    'events_per_session' => $events_per_session,
    'article_ctr' => $article_ctr,
    'newsletter_new' => $stats['newsletter_new'],
    'newsletter_tracked' => $stats['newsletter_tracked'],
    'delivery_sent' => $stats['delivery_sent'],
    'delivery_opened' => $stats['delivery_opened'],
    'delivery_clicked' => $stats['delivery_clicked'],
    'active_subscriptions' => $stats['active_subscriptions'],
    'newsletter_active_total' => $stats['newsletter_active_total'],
];

$ga_metric_values = [
    'sessions' => (int) ($ga_summary['sessions'] ?? 0),
    'active_users' => (int) ($ga_summary['active_users'] ?? 0),
    'new_users' => (int) ($ga_summary['new_users'] ?? 0),
    'returning_users' => (int) ($ga_summary['returning_users'] ?? 0),
    'page_views' => (int) ($ga_summary['page_views'] ?? 0),
    'avg_session_duration' => (int) ($ga_summary['avg_session_duration'] ?? 0),
    'event_count' => (int) ($ga_summary['event_count'] ?? 0),
    'pages_per_session' => (float) ($ga_summary['pages_per_session'] ?? 0),
    'bounce_rate' => (float) ($ga_summary['bounce_rate'] ?? 0),
    'engagement_rate' => (float) ($ga_summary['engagement_rate'] ?? 0),
    'events_per_session' => (float) ($ga_summary['events_per_session'] ?? 0),
    'article_ctr' => $article_ctr,
    'newsletter_new' => $stats['newsletter_new'],
    'newsletter_tracked' => $stats['newsletter_tracked'],
    'delivery_sent' => $stats['delivery_sent'],
    'delivery_opened' => $stats['delivery_opened'],
    'delivery_clicked' => $stats['delivery_clicked'],
    'active_subscriptions' => $stats['active_subscriptions'],
    'newsletter_active_total' => $stats['newsletter_active_total'],
];

$metric_definitions = [
    ['key' => 'sessions', 'label' => 'Sessions', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'unique_visits', 'tooltip' => 'Total distinct sessions in the selected date range.'],
    ['key' => 'active_users', 'label' => 'Active Users', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'unique_visitors', 'tooltip' => 'Distinct visitors active during the selected period.'],
    ['key' => 'new_users', 'label' => 'New Users', 'format' => 'int', 'detail' => null, 'tooltip' => 'Users with first seen activity in this date range.'],
    ['key' => 'returning_users', 'label' => 'Returning Users', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'returning_visitors', 'tooltip' => 'Users who had activity before the start of this range and returned during it.'],
    ['key' => 'page_views', 'label' => 'Page Views', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'news_page_views', 'tooltip' => 'Total article page views in the selected range.'],
    ['key' => 'avg_session_duration', 'label' => 'Avg Session Duration', 'format' => 'seconds', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'avg_time_spent', 'tooltip' => 'Average engaged time per session, in seconds.'],
    ['key' => 'event_count', 'label' => 'Event Count', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : null, 'tooltip' => 'Total tracked analytics events collected in this range.'],
    ['key' => 'pages_per_session', 'label' => 'Pages / Session', 'format' => 'float', 'detail' => null, 'tooltip' => 'Traffic quality metric: average number of pages seen in one session.'],
    ['key' => 'bounce_rate', 'label' => 'Bounce Rate', 'format' => 'percent', 'detail' => null, 'tooltip' => 'Share of sessions with low engagement or a single-view behavior.'],
    ['key' => 'engagement_rate', 'label' => 'Engagement Rate', 'format' => 'percent', 'detail' => null, 'tooltip' => 'Share of sessions considered engaged by interaction or dwell criteria.'],
    ['key' => 'events_per_session', 'label' => 'Events / Session', 'format' => 'float', 'detail' => null, 'tooltip' => 'Average interaction volume per visit. Useful for engagement trend analysis.'],
    ['key' => 'article_ctr', 'label' => 'Article CTR', 'format' => 'percent', 'detail' => 'article_clicks', 'tooltip' => 'How often viewed articles are clicked: article_clicks / page_views.'],
    ['key' => 'newsletter_new', 'label' => 'New Subscribers', 'format' => 'int', 'detail' => 'newsletter_new', 'tooltip' => 'New newsletter subscribers added in the selected period.'],
    ['key' => 'newsletter_tracked', 'label' => 'Subscriber Track Events', 'format' => 'int', 'detail' => 'newsletter_tracked', 'tooltip' => 'Tracked subscribe events triggered by users; can include retries and repeated attempts.'],
    ['key' => 'delivery_sent', 'label' => 'Delivery Sent', 'format' => 'int', 'detail' => 'delivery_sent', 'tooltip' => 'Newsletter deliveries marked as sent. Example: one campaign to 1,000 recipients generates ~1,000 sent entries.'],
    ['key' => 'delivery_opened', 'label' => 'Delivery Opened', 'format' => 'int', 'detail' => 'delivery_opened', 'tooltip' => 'Deliveries with open signal detected. Used to compute open rate trends.'],
    ['key' => 'delivery_clicked', 'label' => 'Delivery Clicked', 'format' => 'int', 'detail' => 'delivery_clicked', 'tooltip' => 'Deliveries where at least one link was clicked after open.'],
    ['key' => 'active_subscriptions', 'label' => 'Active Personalization Subscriptions', 'format' => 'int', 'detail' => 'active_subscriptions', 'tooltip' => 'Users with active topic/category personalization subscriptions.'],
    ['key' => 'newsletter_active_total', 'label' => 'Active Subscribers (Total)', 'format' => 'int', 'detail' => 'newsletter_active_total', 'tooltip' => 'All active newsletter subscribers regardless of selected date range.'],
];

$format_metric_value = static function($value, string $format): string {
    if ($format === 'seconds') {
        return number_format_i18n((int) round((float) $value)) . 's';
    }
    if ($format === 'percent') {
        return number_format_i18n((float) $value, 1) . '%';
    }
    if ($format === 'float') {
        return number_format_i18n((float) $value, 2);
    }

    return number_format_i18n((int) $value);
};
?>
<div class="wrap">
    <h1>Visitor Analytics</h1>
    <p style="color:#646970;margin-top:2px;">Range: <strong><?php echo esc_html($start->format('d.m.Y')); ?></strong> - <strong><?php echo esc_html($end->format('d.m.Y')); ?></strong>. Auto refresh every 60 seconds.</p>

    <div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?php echo $build_tab('custom'); ?>" class="button <?php echo $tab === 'custom' ? 'button-primary' : ''; ?>">Custom Analytics</a>
        <a href="<?php echo $build_tab('google'); ?>" class="button <?php echo $tab === 'google' ? 'button-primary' : ''; ?>">Google Analytics</a>
    </div>

    <div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?php echo $build_range('today'); ?>" class="button <?php echo $range === 'today' ? 'button-primary' : ''; ?>">Today</a>
        <a href="<?php echo $build_range('yesterday'); ?>" class="button <?php echo $range === 'yesterday' ? 'button-primary' : ''; ?>">Yesterday</a>
        <a href="<?php echo $build_range('this_week'); ?>" class="button <?php echo $range === 'this_week' ? 'button-primary' : ''; ?>">This week</a>
        <a href="<?php echo $build_range('this_month'); ?>" class="button <?php echo $range === 'this_month' ? 'button-primary' : ''; ?>">This month</a>
    </div>

    <form method="get" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px;margin-bottom:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="page" value="teinformez-analytics">
        <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
        <input type="hidden" name="range" value="custom">
        <div><label style="display:block;font-size:12px;color:#646970;">From</label><input type="date" name="start_date" value="<?php echo esc_attr($start_value); ?>"></div>
        <div><label style="display:block;font-size:12px;color:#646970;">To</label><input type="date" name="end_date" value="<?php echo esc_attr($end_value); ?>"></div>
        <button type="submit" class="button button-primary">Apply custom range</button>
    </form>

    <?php if ($tab === 'google' && $ga_error !== ''): ?>
        <div style="background:#fff4f4;border:1px solid #d63638;border-radius:6px;padding:12px;margin-bottom:14px;color:#b32d2e;">
            <?php echo esc_html($ga_error); ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px;">
        <?php
        $metric_values = $tab === 'google' ? $ga_metric_values : $custom_metric_values;
        foreach ($metric_definitions as $metric):
            $detail_key = $metric['detail'];
            $card_url = $detail_key ? $build_url(['detail' => $detail_key]) : '#';
            $card_style = $detail_key ? 'text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;' : 'text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;pointer-events:none;';
        ?>
            <a href="<?php echo esc_url($card_url); ?>" style="<?php echo esc_attr($card_style); ?>">
                <div style="color:#646970;font-size:12px;">
                    <?php echo esc_html($metric['label']); ?>
                    <span style="font-size:10px;vertical-align:top;cursor:help;color:#8c8f94;" title="<?php echo esc_attr($metric['tooltip']); ?>">&#9432;</span>
                </div>
                <div style="font-size:24px;font-weight:700;"><?php echo esc_html($format_metric_value($metric_values[$metric['key']] ?? 0, $metric['format'])); ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'custom'): ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;margin-bottom:14px;">
            <h2 style="margin-top:0;">Data Cross-check</h2>
            <table class="widefat striped">
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?php echo esc_html($check['label']); ?></td>
                        <td><?php echo !empty($check['ok']) ? '<span style="color:#0a7f42;font-weight:600;">OK</span>' : '<span style="color:#b32d2e;font-weight:600;">Mismatch</span>'; ?></td>
                        <td><?php echo esc_html($check['values']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:10px;color:#646970;">Returning rate: <?php echo esc_html($return_rate); ?>% | Open rate: <?php echo esc_html($open_rate); ?>% | Delivery CTR: <?php echo esc_html($click_rate); ?>%</p>
        </div>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Top Accessed Articles (Range)</h2>
            <table class="widefat striped"><thead><tr><th>Title</th><th style="width:90px;">Views</th></tr></thead><tbody><?php if (empty($top_articles)): ?><tr><td colspan="2">No data available.</td></tr><?php else: ?><?php foreach ($top_articles as $article): ?><?php $title = !empty($article->processed_title) ? $article->processed_title : $article->original_title; ?><tr><td><?php echo esc_html(wp_trim_words((string) $title, 14, '...')); ?></td><td><?php echo number_format_i18n((int) $article->views); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table>
        </div>
    <?php else: ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;">
            <h2 style="margin-top:0;">Top GA4 Pages (Range)</h2>
            <table class="widefat striped"><thead><tr><th>Path</th><th>Views</th><th>Sessions</th><th>Users</th></tr></thead><tbody><?php if (empty($ga_top_pages)): ?><tr><td colspan="4">No data available.</td></tr><?php else: ?><?php foreach ($ga_top_pages as $row): ?><tr><td><?php echo esc_html((string) $row['path']); ?></td><td><?php echo number_format_i18n((int) $row['views']); ?></td><td><?php echo number_format_i18n((int) $row['sessions']); ?></td><td><?php echo number_format_i18n((int) $row['users']); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table>
        </div>
    <?php endif; ?>

    <?php if ($detail_title !== '' || $detail === 'ga_top_pages'): ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;margin-top:14px;">
            <?php if ($detail === 'ga_top_pages'): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;"><h2 style="margin:0;">Google Analytics Details - Top Pages</h2><a class="button" href="<?php echo $build_url(['detail' => null]); ?>">Close</a></div>
                <table class="widefat striped" style="margin-top:10px;"><thead><tr><th>Path</th><th>Views</th><th>Sessions</th><th>Users</th></tr></thead><tbody><?php if (empty($ga_top_pages)): ?><tr><td colspan="4">No data available.</td></tr><?php else: ?><?php foreach ($ga_top_pages as $row): ?><tr><td><?php echo esc_html((string) $row['path']); ?></td><td><?php echo number_format_i18n((int) $row['views']); ?></td><td><?php echo number_format_i18n((int) $row['sessions']); ?></td><td><?php echo number_format_i18n((int) $row['users']); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table>
            <?php else: ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;"><h2 style="margin:0;"><?php echo esc_html($detail_title); ?></h2><a class="button" href="<?php echo $build_url(['detail' => null]); ?>">Close</a></div>
                <?php if (empty($detail_rows)): ?><p>No data available for this detail.</p><?php else: ?>
                    <table class="widefat striped" style="margin-top:10px;"><thead><tr><?php foreach ($detail_cols as $col): ?><th><?php echo esc_html((string) $col); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($detail_rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?php echo esc_html((string) $cell); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>(function(){setTimeout(function(){window.location.reload();},60000);})();</script>
