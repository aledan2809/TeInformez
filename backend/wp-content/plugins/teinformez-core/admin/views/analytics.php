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
    'sessions_per_user' => $stats['unique_visitors'] > 0 ? round($stats['unique_visits'] / $stats['unique_visitors'], 2) : 0,
    'views_per_user' => $stats['unique_visitors'] > 0 ? round($stats['news_page_views'] / $stats['unique_visitors'], 2) : 0,
    'new_user_rate' => $stats['unique_visitors'] > 0 ? round(($stats['new_visitors'] / $stats['unique_visitors']) * 100, 1) : 0,
    'events_per_user' => $stats['unique_visitors'] > 0 ? round($stats['total_events'] / $stats['unique_visitors'], 2) : 0,
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
    'sessions_per_user' => (int) ($ga_summary['active_users'] ?? 0) > 0 ? round(((int) ($ga_summary['sessions'] ?? 0)) / ((int) ($ga_summary['active_users'] ?? 0)), 2) : 0,
    'views_per_user' => (int) ($ga_summary['active_users'] ?? 0) > 0 ? round(((int) ($ga_summary['page_views'] ?? 0)) / ((int) ($ga_summary['active_users'] ?? 0)), 2) : 0,
    'new_user_rate' => (int) ($ga_summary['active_users'] ?? 0) > 0 ? round((((int) ($ga_summary['new_users'] ?? 0)) / ((int) ($ga_summary['active_users'] ?? 0))) * 100, 1) : 0,
    'events_per_user' => (int) ($ga_summary['active_users'] ?? 0) > 0 ? round(((int) ($ga_summary['event_count'] ?? 0)) / ((int) ($ga_summary['active_users'] ?? 0)), 2) : 0,
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
    ['key' => 'sessions', 'label' => 'Sessions', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'unique_visits',
        'info' => 'How many visits your site received. One person opening the site twice counts as 2 sessions.|Count of distinct session IDs|If 6 different browsing sessions happened today, Sessions = 6'],
    ['key' => 'active_users', 'label' => 'Active Users', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'unique_visitors',
        'info' => 'How many different people visited. If one person visits 3 times, they count as 1 active user.|Count of unique visitor fingerprints|6 sessions from 4 different browsers = 4 active users'],
    ['key' => 'new_users', 'label' => 'New Users', 'format' => 'int', 'detail' => null,
        'info' => 'First-time visitors who never visited before the selected period.|Visitors with no tracked activity before the start date|If 4 active users and 1 visited last month, new users = 3'],
    ['key' => 'returning_users', 'label' => 'Returning Users', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'returning_visitors',
        'info' => 'People who visited before and came back during this period.|Active Users - New Users|4 active users - 3 new = 1 returning user'],
    ['key' => 'page_views', 'label' => 'Page Views', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'news_page_views',
        'info' => 'Total number of article pages opened by all visitors.|Count of page_view events|If 6 visitors opened 13 articles total, Page Views = 13'],
    ['key' => 'avg_session_duration', 'label' => 'Avg Session Duration', 'format' => 'seconds', 'detail' => $tab === 'google' ? 'ga_top_pages' : 'avg_time_spent',
        'info' => 'Average time a visitor spends on your site per visit.|Sum of all session durations / number of sessions|3 sessions lasting 60s, 120s, 90s = average 90s'],
    ['key' => 'event_count', 'label' => 'Event Count', 'format' => 'int', 'detail' => $tab === 'google' ? 'ga_top_pages' : null,
        'info' => 'Total number of tracked actions (page views, clicks, scrolls, etc.).|Count of all analytics events|6 page views + 2 clicks + 1 subscribe = 9 events'],
    ['key' => 'pages_per_session', 'label' => 'Pages / Session', 'format' => 'float', 'detail' => null,
        'info' => 'How many pages a visitor typically views per visit. Higher = more engaged.|Page Views / Sessions|13 page views / 6 sessions = 2.17'],
    ['key' => 'bounce_rate', 'label' => 'Bounce Rate', 'format' => 'percent', 'detail' => null,
        'info' => 'Percentage of visitors who left without clicking any article. Lower is better.|((Sessions - Article Clicks) / Sessions) * 100|6 sessions, 2 article clicks = (6-2)/6 = 66.7%'],
    ['key' => 'engagement_rate', 'label' => 'Engagement Rate', 'format' => 'percent', 'detail' => null,
        'info' => 'Percentage of visits where the user actually interacted (spent time, clicked, etc.).|Engaged Sessions / Sessions * 100|2 engaged sessions out of 6 total = 33.3%'],
    ['key' => 'events_per_session', 'label' => 'Events / Session', 'format' => 'float', 'detail' => null,
        'info' => 'Average number of actions per visit. More actions = more interaction.|Event Count / Sessions|32 events / 6 sessions = 5.33'],
    ['key' => 'sessions_per_user', 'label' => 'Sessions / User', 'format' => 'float', 'detail' => null,
        'info' => 'How many times each person visits on average. Higher = loyal audience.|Sessions / Active Users|6 sessions / 6 users = 1.00'],
    ['key' => 'views_per_user', 'label' => 'Views / User', 'format' => 'float', 'detail' => null,
        'info' => 'Average pages viewed per person. Shows content consumption depth.|Page Views / Active Users|13 views / 6 users = 2.17'],
    ['key' => 'new_user_rate', 'label' => 'New User Rate', 'format' => 'percent', 'detail' => null,
        'info' => 'What percentage of your visitors are first-timers. High = good reach, low = loyal base.|(New Users / Active Users) * 100|6 new out of 6 total = 100%'],
    ['key' => 'events_per_user', 'label' => 'Events / User', 'format' => 'float', 'detail' => null,
        'info' => 'Average actions per person. Shows how actively each visitor interacts.|Event Count / Active Users|32 events / 6 users = 5.33'],
    ['key' => 'article_ctr', 'label' => 'Article CTR', 'format' => 'percent', 'detail' => 'article_clicks',
        'info' => 'Click-through rate: how often a viewed article gets clicked for full read.|(Article Clicks / Page Views) * 100|2 clicks on 14 viewed articles = 14.3%'],
    ['key' => 'newsletter_new', 'label' => 'New Subscribers', 'format' => 'int', 'detail' => 'newsletter_new', 'new_tab' => true,
        'info' => 'People who subscribed to your newsletter during this period.|Count from subscriber table where subscribed_at is in date range|12 new signups this week = 12'],
    ['key' => 'newsletter_tracked', 'label' => 'Subscriber Track Events', 'format' => 'int', 'detail' => 'newsletter_tracked',
        'info' => 'Subscribe button clicks tracked. Can be higher than actual subscribers if someone clicks multiple times.|Count of newsletter_subscribe events|1 person clicking subscribe 3 times = 3 events but 1 subscriber'],
    ['key' => 'delivery_sent', 'label' => 'Delivery Sent', 'format' => 'int', 'detail' => 'delivery_sent',
        'info' => 'How many newsletter emails were sent. One campaign to 500 people = 500 sent.|Count of delivery records with status=sent|1 campaign to 567 recipients = 567 sent'],
    ['key' => 'delivery_opened', 'label' => 'Delivery Opened', 'format' => 'int', 'detail' => 'delivery_opened',
        'info' => 'How many recipients opened the email. Tracked via invisible pixel.|Count of delivery records with status=opened|567 sent, 142 opened = 142 (25% open rate)'],
    ['key' => 'delivery_clicked', 'label' => 'Delivery Clicked', 'format' => 'int', 'detail' => 'delivery_clicked',
        'info' => 'How many recipients clicked a link inside the email.|Count of delivery records with status=clicked|142 opened, 35 clicked a link = 35'],
    ['key' => 'active_subscriptions', 'label' => 'Active Personalization Subscriptions', 'format' => 'int', 'detail' => 'active_subscriptions',
        'info' => 'Users who set up personalized topic/category filters for their news feed.|Count of active subscription records|29 users have active topic filters'],
    ['key' => 'newsletter_active_total', 'label' => 'Active Subscribers (Total)', 'format' => 'int', 'detail' => 'newsletter_active_total', 'new_tab' => true,
        'info' => 'Total active newsletter subscribers right now, regardless of date range.|Count where status=active in subscriber table|450 total active subscribers (all-time, not filtered by date)'],
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
        foreach ($metric_definitions as $idx => $metric):
            $detail_key = $metric['detail'];
            $card_url = $detail_key ? $build_url(['detail' => $detail_key]) : '#';
            $new_tab = !empty($metric['new_tab']);
            $has_detail = (bool) $detail_key;
            $card_style = 'text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;display:block;position:relative;';
            if (!$has_detail) $card_style .= 'pointer-events:none;';
            $info_parts = isset($metric['info']) ? explode('|', $metric['info']) : [];
        ?>
            <a href="<?php echo esc_url($card_url); ?>" style="<?php echo esc_attr($card_style); ?>"<?php echo $new_tab ? ' target="_blank"' : ''; ?>>
                <div style="color:#646970;font-size:12px;">
                    <?php echo esc_html($metric['label']); ?>
                    <?php if (!empty($info_parts)): ?>
                        <span class="ti-info-icon" data-idx="<?php echo $idx; ?>" style="font-size:10px;vertical-align:top;cursor:pointer;color:#2271b1;pointer-events:auto;" title="Click for details">&#9432;</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:24px;font-weight:700;"><?php echo esc_html($format_metric_value($metric_values[$metric['key']] ?? 0, $metric['format'])); ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div id="ti-metric-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);position:relative;">
            <button onclick="document.getElementById('ti-metric-modal').style.display='none'" style="position:absolute;top:8px;right:12px;background:none;border:none;font-size:20px;cursor:pointer;color:#646970;">&times;</button>
            <h3 id="ti-modal-title" style="margin:0 0 12px;font-size:16px;"></h3>
            <p id="ti-modal-explain" style="margin:0 0 10px;color:#1d2327;font-size:14px;line-height:1.5;"></p>
            <div style="background:#f0f0f1;border-radius:4px;padding:8px 12px;margin-bottom:10px;">
                <span style="color:#646970;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Formula</span>
                <div id="ti-modal-formula" style="font-family:monospace;font-size:13px;margin-top:2px;color:#1d2327;"></div>
            </div>
            <div style="background:#fcf9e8;border-radius:4px;padding:8px 12px;">
                <span style="color:#646970;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Example</span>
                <div id="ti-modal-example" style="font-size:13px;margin-top:2px;color:#1d2327;"></div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var defs = <?php echo wp_json_encode(array_map(function($m) {
            $parts = isset($m['info']) ? explode('|', $m['info']) : ['', '', ''];
            return ['label' => $m['label'], 'explain' => $parts[0] ?? '', 'formula' => $parts[1] ?? '', 'example' => $parts[2] ?? ''];
        }, $metric_definitions)); ?>;
        document.querySelectorAll('.ti-info-icon').forEach(function(el){
            el.addEventListener('click', function(e){
                e.preventDefault(); e.stopPropagation();
                var d = defs[parseInt(this.getAttribute('data-idx'))];
                if(!d) return;
                document.getElementById('ti-modal-title').textContent = d.label;
                document.getElementById('ti-modal-explain').textContent = d.explain;
                document.getElementById('ti-modal-formula').textContent = d.formula;
                document.getElementById('ti-modal-example').textContent = d.example;
                document.getElementById('ti-metric-modal').style.display = 'flex';
            });
        });
        document.getElementById('ti-metric-modal').addEventListener('click', function(e){
            if(e.target === this) this.style.display = 'none';
        });
    })();
    </script>

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
