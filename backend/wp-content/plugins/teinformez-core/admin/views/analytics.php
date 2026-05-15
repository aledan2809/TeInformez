<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
\TeInformez\Visitor_Analytics::create_table_if_missing();

$events_table = \TeInformez\Visitor_Analytics::table_name();
$newsletter_table = $wpdb->prefix . 'teinformez_newsletter_subscribers';
$news_table = $wpdb->prefix . 'teinformez_news_queue';

$exists = static function(string $table) use ($wpdb): bool {
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
};

$has_events = $exists($events_table);
$has_newsletter = $exists($newsletter_table);
$has_news = $exists($news_table);

$tz = wp_timezone();
$now = new DateTimeImmutable('now', $tz);

// Window boundaries (MySQL strings)
$today_start = $now->setTime(0, 0, 0);
$today_end = $now->setTime(23, 59, 59);
$week1_end = $now;
$week1_start = $now->modify('-7 days');
$week2_end = $week1_start;
$week2_start = $now->modify('-14 days');
// 30-day window INCLUSIVE of today: today minus 29 days … today.
// Prior window: today minus 59 days … today minus 30 days (also 30 days, no overlap).
$chart_end = $now;
$chart_start = $now->modify('-29 days')->setTime(0, 0, 0);
$chart_prev_end = $now->modify('-30 days')->setTime(23, 59, 59);
$chart_prev_start = $now->modify('-59 days')->setTime(0, 0, 0);

$fmt = static function(DateTimeImmutable $dt): string { return $dt->format('Y-m-d H:i:s'); };

$advanced_url = esc_url(admin_url('admin.php?page=teinformez-analytics-advanced'));
$detail_base_url = static function(string $detail_key) {
    return esc_url(add_query_arg([
        'page' => 'teinformez-analytics-advanced',
        'detail' => $detail_key,
    ], admin_url('admin.php')));
};

// ---------------------------------------------------------------------------
// Card 1 — Vizitatori unici sapt. (last 7d vs prev 7d)
// ---------------------------------------------------------------------------
$c1_curr = 0; $c1_prev = 0;
if ($has_events) {
    $c1_curr = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT visitor_hash) FROM {$events_table} WHERE created_at BETWEEN %s AND %s",
        $fmt($week1_start), $fmt($week1_end)
    ));
    $c1_prev = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT visitor_hash) FROM {$events_table} WHERE created_at BETWEEN %s AND %s",
        $fmt($week2_start), $fmt($week2_end)
    ));
}

// ---------------------------------------------------------------------------
// Card 2 — WordPress subscribers (total + new today)
// ---------------------------------------------------------------------------
$c2_total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID WHERE m.meta_key = '{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%subscriber%'"
);
$c2_new_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID WHERE m.meta_key = '{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%subscriber%' AND u.user_registered BETWEEN %s AND %s",
    $fmt($today_start), $fmt($today_end)
));

// ---------------------------------------------------------------------------
// Card 3 — Email subscribers active total + new today
// ---------------------------------------------------------------------------
$c3_total = 0; $c3_new_today = 0;
if ($has_newsletter) {
    $c3_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newsletter_table} WHERE status='active'");
    $c3_new_today = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$newsletter_table} WHERE subscribed_at BETWEEN %s AND %s",
        $fmt($today_start), $fmt($today_end)
    ));
}

// ---------------------------------------------------------------------------
// Card 4 — Articole citite sapt. (page views, last 7d vs prev 7d)
// ---------------------------------------------------------------------------
$c4_curr = 0; $c4_prev = 0;
if ($has_events) {
    $c4_curr = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE event_type='page_view' AND page_type='news' AND created_at BETWEEN %s AND %s",
        $fmt($week1_start), $fmt($week1_end)
    ));
    $c4_prev = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE event_type='page_view' AND page_type='news' AND created_at BETWEEN %s AND %s",
        $fmt($week2_start), $fmt($week2_end)
    ));
}

// ---------------------------------------------------------------------------
// Card 5 — Top category by views (last 7d), parse JSON categories in PHP
// ---------------------------------------------------------------------------
// Top category by share of category-impressions in the last 7 days.
// Each article-view is attributed to ALL its categories (multi-tag = correct
// usage), so the denominator is sum(cat_views), not sum(article_views).
// Result semantics: "X% of category-impressions are in <top>".
// Bounded 0..100 by construction (single key over total).
$c5_label = '—'; $c5_pct = 0; $c5_count = 0; $c5_total_impressions = 0;
if ($has_events && $has_news) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT n.categories, COUNT(*) AS views FROM {$events_table} e INNER JOIN {$news_table} n ON n.id = e.page_id WHERE e.event_type='page_view' AND e.page_type='news' AND e.page_id > 0 AND e.created_at BETWEEN %s AND %s GROUP BY n.id, n.categories",
        $fmt($week1_start), $fmt($week1_end)
    ));
    $cat_views = [];
    foreach ($rows as $row) {
        $views = (int) $row->views;
        $cats_raw = (string) ($row->categories ?? '');
        $cats = [];
        if ($cats_raw !== '') {
            $decoded = json_decode($cats_raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $c) {
                    if (is_string($c) && $c !== '') {
                        $cats[] = $c;
                    } elseif (is_array($c) && isset($c['slug']) && is_string($c['slug'])) {
                        $cats[] = $c['slug'];
                    } elseif (is_array($c) && isset($c['name']) && is_string($c['name'])) {
                        $cats[] = $c['name'];
                    }
                }
            }
        }
        if (empty($cats)) {
            $cats = ['(uncategorized)'];
        }
        foreach (array_unique($cats) as $cat) {
            if (!isset($cat_views[$cat])) $cat_views[$cat] = 0;
            $cat_views[$cat] += $views;
        }
    }
    if (!empty($cat_views)) {
        arsort($cat_views);
        $c5_total_impressions = array_sum($cat_views);
        $c5_label = (string) array_key_first($cat_views);
        $c5_count = (int) $cat_views[$c5_label];
        $c5_pct = $c5_total_impressions > 0 ? round(($c5_count / $c5_total_impressions) * 100, 1) : 0;
    }
}

// ---------------------------------------------------------------------------
// AN-02 — "Ce a funcționat" tables (top 5 articles + sources + categories)
// ---------------------------------------------------------------------------

// Top 5 articles last 7d
$top_articles = [];
if ($has_events && $has_news) {
    $top_articles = $wpdb->get_results($wpdb->prepare(
        "SELECT e.page_id, COUNT(*) views, MAX(n.processed_title) processed_title, MAX(n.original_title) original_title FROM {$events_table} e LEFT JOIN {$news_table} n ON n.id = e.page_id WHERE e.event_type='page_view' AND e.page_type='news' AND e.page_id > 0 AND e.created_at BETWEEN %s AND %s GROUP BY e.page_id ORDER BY views DESC LIMIT 5",
        $fmt($week1_start), $fmt($week1_end)
    ));
}

// Top 5 sources (last 7d, derived from metadata.source_bucket — populated post-AN-02 deploy)
$top_sources = [];
$sources_total = 0;
$sources_unknown = 0;
if ($has_events) {
    // Pull all events with source data + count of those without (legacy/no-tracker events)
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT metadata FROM {$events_table} WHERE event_type='page_view' AND created_at BETWEEN %s AND %s",
        $fmt($week1_start), $fmt($week1_end)
    ));
    $bucket_counts = [];
    foreach ($rows as $row) {
        $sources_total++;
        $bucket = null;
        $meta_raw = (string) ($row->metadata ?? '');
        if ($meta_raw !== '') {
            $meta = json_decode($meta_raw, true);
            if (is_array($meta) && isset($meta['source_bucket']) && is_string($meta['source_bucket'])) {
                $bucket = $meta['source_bucket'];
            }
        }
        if ($bucket === null) {
            $sources_unknown++;
        } else {
            if (!isset($bucket_counts[$bucket])) $bucket_counts[$bucket] = 0;
            $bucket_counts[$bucket]++;
        }
    }
    arsort($bucket_counts);
    $top_sources = array_slice($bucket_counts, 0, 5, true);
}

// Top 5 categories (multi-cat impressions, same logic as Card 5 but list-form)
$top_categories = [];
$top_categories_total = $c5_total_impressions ?? 0;
if (!empty($cat_views ?? [])) {
    $top_categories = array_slice($cat_views, 0, 5, true);
}

// Pretty labels for source buckets
$source_labels = [
    'organic_google'   => 'Google (organic)',
    'organic_bing'     => 'Bing (organic)',
    'organic_other'    => 'Alt search engine',
    'social_facebook'  => 'Facebook',
    'social_instagram' => 'Instagram',
    'social_twitter'   => 'Twitter / X',
    'social_linkedin'  => 'LinkedIn',
    'social_youtube'   => 'YouTube',
    'social_reddit'    => 'Reddit',
    'social_other'     => 'Alt social',
    'email'            => 'Email / Newsletter',
    'rss'              => 'RSS feed',
    'ad'               => 'Reclamă plătită',
    'internal'         => 'Navigare internă',
    'direct'           => 'Direct (typed URL / no referer)',
    'referral_other'   => 'Alt site (referral)',
];

// ---------------------------------------------------------------------------
// Trend helpers + percentage formatting
// ---------------------------------------------------------------------------
$render_trend = static function(int $curr, int $prev): array {
    if ($prev === 0 && $curr === 0) {
        return ['symbol' => '·', 'text' => '—', 'color' => '#646970'];
    }
    if ($prev === 0 && $curr > 0) {
        return ['symbol' => '↑', 'text' => 'NEW', 'color' => '#0a7f42'];
    }
    $delta_pct = (($curr - $prev) / $prev) * 100;
    $rounded = round($delta_pct, 1);
    if ($rounded === 0.0) {
        return ['symbol' => '·', 'text' => '0.0%', 'color' => '#646970'];
    }
    if ($rounded > 0) {
        return ['symbol' => '↑', 'text' => '+' . number_format_i18n($rounded, 1) . '%', 'color' => '#0a7f42'];
    }
    return ['symbol' => '↓', 'text' => number_format_i18n($rounded, 1) . '%', 'color' => '#b32d2e'];
};

// ---------------------------------------------------------------------------
// 30-day daily series for 3 charts (current + previous-30 comparison)
// ---------------------------------------------------------------------------
$build_daily_series = static function(string $sql, array $params, DateTimeImmutable $period_start, int $days) use ($wpdb): array {
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    $by_date = [];
    foreach ($rows as $r) {
        $by_date[(string) $r->day] = (int) $r->n;
    }
    $series = [];
    for ($i = 0; $i < $days; $i++) {
        $d = $period_start->modify("+{$i} days")->format('Y-m-d');
        $series[] = ['date' => $d, 'value' => isset($by_date[$d]) ? $by_date[$d] : 0];
    }
    return $series;
};

$g1_curr = []; $g1_prev = []; $g2_curr = []; $g2_prev = []; $g3_curr = []; $g3_prev = [];

if ($has_events) {
    $g1_curr = $build_daily_series(
        "SELECT DATE(created_at) AS day, COUNT(DISTINCT session_id) AS n FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at)",
        [$fmt($chart_start), $fmt($chart_end)],
        $chart_start,
        30
    );
    $g1_prev = $build_daily_series(
        "SELECT DATE(created_at) AS day, COUNT(DISTINCT session_id) AS n FROM {$events_table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at)",
        [$fmt($chart_prev_start), $fmt($chart_prev_end)],
        $chart_prev_start,
        30
    );
}

$g2_curr = $build_daily_series(
    "SELECT DATE(u.user_registered) AS day, COUNT(*) AS n FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID WHERE m.meta_key = '{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%subscriber%' AND u.user_registered BETWEEN %s AND %s GROUP BY DATE(u.user_registered)",
    [$fmt($chart_start), $fmt($chart_end)],
    $chart_start,
    30
);
$g2_prev = $build_daily_series(
    "SELECT DATE(u.user_registered) AS day, COUNT(*) AS n FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID WHERE m.meta_key = '{$wpdb->prefix}capabilities' AND m.meta_value LIKE '%subscriber%' AND u.user_registered BETWEEN %s AND %s GROUP BY DATE(u.user_registered)",
    [$fmt($chart_prev_start), $fmt($chart_prev_end)],
    $chart_prev_start,
    30
);

if ($has_newsletter) {
    $g3_curr = $build_daily_series(
        "SELECT DATE(subscribed_at) AS day, COUNT(*) AS n FROM {$newsletter_table} WHERE status='active' AND subscribed_at BETWEEN %s AND %s GROUP BY DATE(subscribed_at)",
        [$fmt($chart_start), $fmt($chart_end)],
        $chart_start,
        30
    );
    $g3_prev = $build_daily_series(
        "SELECT DATE(subscribed_at) AS day, COUNT(*) AS n FROM {$newsletter_table} WHERE status='active' AND subscribed_at BETWEEN %s AND %s GROUP BY DATE(subscribed_at)",
        [$fmt($chart_prev_start), $fmt($chart_prev_end)],
        $chart_prev_start,
        30
    );
}

// ---------------------------------------------------------------------------
// SVG chart renderer
// ---------------------------------------------------------------------------
$render_chart = static function(array $curr, array $prev, string $color_curr = '#2563eb', string $color_prev = '#94a3b8'): string {
    $w = 300; $h = 120; $pad_x = 12; $pad_y = 14;
    $inner_w = $w - $pad_x * 2;
    $inner_h = $h - $pad_y * 2;
    $n = max(count($curr), 1);
    $max_curr = 0; $max_prev = 0;
    foreach ($curr as $p) { if ($p['value'] > $max_curr) $max_curr = $p['value']; }
    foreach ($prev as $p) { if ($p['value'] > $max_prev) $max_prev = $p['value']; }
    $y_max = max($max_curr, $max_prev, 1);

    $project = static function(int $idx, int $value) use ($n, $y_max, $pad_x, $pad_y, $inner_w, $inner_h): array {
        $x = $pad_x + ($n > 1 ? ($idx / ($n - 1)) * $inner_w : $inner_w / 2);
        $y = $pad_y + $inner_h - (($value / $y_max) * $inner_h);
        return [round($x, 2), round($y, 2)];
    };

    $build_polyline = static function(array $points) use ($project): string {
        $coords = [];
        foreach ($points as $i => $p) {
            [$x, $y] = $project($i, $p['value']);
            $coords[] = $x . ',' . $y;
        }
        return implode(' ', $coords);
    };

    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width:100%;height:auto;display:block;">';
    // Baseline
    $svg .= '<line x1="' . $pad_x . '" y1="' . ($pad_y + $inner_h) . '" x2="' . ($pad_x + $inner_w) . '" y2="' . ($pad_y + $inner_h) . '" stroke="#e5e7eb" stroke-width="1"/>';
    // Previous (dashed)
    if (!empty($prev)) {
        $svg .= '<polyline points="' . esc_attr($build_polyline($prev)) . '" fill="none" stroke="' . esc_attr($color_prev) . '" stroke-width="1.5" stroke-dasharray="3,3"/>';
    }
    // Current (solid)
    if (!empty($curr)) {
        $svg .= '<polyline points="' . esc_attr($build_polyline($curr)) . '" fill="none" stroke="' . esc_attr($color_curr) . '" stroke-width="2"/>';
        foreach ($curr as $i => $p) {
            [$x, $y] = $project($i, $p['value']);
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="2.5" fill="' . esc_attr($color_curr) . '"><title>' . esc_html($p['date']) . ': ' . esc_html(number_format_i18n($p['value'])) . '</title></circle>';
        }
    }
    $svg .= '</svg>';
    return $svg;
};

$sum_series = static function(array $series): int {
    $s = 0; foreach ($series as $p) { $s += (int) $p['value']; } return $s;
};

$c1_trend = $render_trend($c1_curr, $c1_prev);
$c4_trend = $render_trend($c4_curr, $c4_prev);
?>
<div class="wrap">
    <h1>Visitor Analytics</h1>
    <p style="color:#646970;margin-top:2px;margin-bottom:14px;">Snapshot rapid &mdash; ultimele 7 zile vs. săptămâna anterioară. Auto refresh la 60 secunde.</p>

    <div style="margin-bottom:14px;">
        <a href="<?php echo $advanced_url; ?>" class="button button-secondary" style="font-weight:600;">Show advanced view &rarr;</a>
    </div>

    <!-- Headline cards -->
    <div class="ti-headline-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:24px;">

        <a href="<?php echo $detail_base_url('unique_visitors'); ?>" class="ti-card" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;display:block;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;">Vizitatori unici (săpt.)</div>
            <div style="font-size:36px;font-weight:700;line-height:1.1;margin-top:8px;color:#1d2327;"><?php echo esc_html(number_format_i18n($c1_curr)); ?></div>
            <div style="font-size:13px;font-weight:600;margin-top:6px;color:<?php echo esc_attr($c1_trend['color']); ?>;">
                <?php echo esc_html($c1_trend['symbol']); ?> <?php echo esc_html($c1_trend['text']); ?>
                <span style="color:#646970;font-weight:400;">vs <?php echo esc_html(number_format_i18n($c1_prev)); ?></span>
            </div>
        </a>

        <a href="<?php echo $detail_base_url('wp_subscribers'); ?>" class="ti-card" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;display:block;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;">Useri înregistrați</div>
            <div style="font-size:36px;font-weight:700;line-height:1.1;margin-top:8px;color:#1d2327;"><?php echo esc_html(number_format_i18n($c2_total)); ?></div>
            <div style="font-size:13px;font-weight:600;margin-top:6px;color:<?php echo $c2_new_today > 0 ? '#0a7f42' : '#646970'; ?>;">
                <?php echo $c2_new_today > 0 ? '+' . esc_html(number_format_i18n($c2_new_today)) . ' azi' : '· 0 azi'; ?>
            </div>
        </a>

        <a href="<?php echo $detail_base_url('newsletter_active_total'); ?>" class="ti-card" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;display:block;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;">Email subscribers (activi)</div>
            <div style="font-size:36px;font-weight:700;line-height:1.1;margin-top:8px;color:#1d2327;"><?php echo esc_html(number_format_i18n($c3_total)); ?></div>
            <div style="font-size:13px;font-weight:600;margin-top:6px;color:<?php echo $c3_new_today > 0 ? '#0a7f42' : '#646970'; ?>;">
                <?php echo $c3_new_today > 0 ? '+' . esc_html(number_format_i18n($c3_new_today)) . ' azi' : '· 0 azi'; ?>
            </div>
        </a>

        <a href="<?php echo $detail_base_url('news_page_views'); ?>" class="ti-card" style="text-decoration:none;color:inherit;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;display:block;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;">Articole citite (săpt.)</div>
            <div style="font-size:36px;font-weight:700;line-height:1.1;margin-top:8px;color:#1d2327;"><?php echo esc_html(number_format_i18n($c4_curr)); ?></div>
            <div style="font-size:13px;font-weight:600;margin-top:6px;color:<?php echo esc_attr($c4_trend['color']); ?>;">
                <?php echo esc_html($c4_trend['symbol']); ?> <?php echo esc_html($c4_trend['text']); ?>
                <span style="color:#646970;font-weight:400;">vs <?php echo esc_html(number_format_i18n($c4_prev)); ?></span>
            </div>
        </a>

        <div class="ti-card" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;" title="Procent calculat din total impresii pe categorii (un articol cu 2 categorii contribuie la ambele).">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;">Top categorie audiență (săpt.)</div>
            <div style="font-size:24px;font-weight:700;line-height:1.2;margin-top:8px;color:#1d2327;word-break:break-word;"><?php echo esc_html($c5_label); ?></div>
            <div style="font-size:13px;font-weight:600;margin-top:6px;color:#646970;">
                <?php echo esc_html(number_format_i18n($c5_count)); ?> views &middot; <?php echo esc_html(number_format_i18n($c5_pct, 1)); ?>% din audiență
            </div>
        </div>

    </div>

    <!-- Trend charts -->
    <h2 style="font-size:15px;color:#1d2327;margin:0 0 10px;">Trend &mdash; ultimele 30 zile <span style="font-weight:400;color:#646970;font-size:13px;">(comparat cu acum 30 zile, linie punctată)</span></h2>
    <div class="ti-charts-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-bottom:24px;">

        <div class="ti-chart" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:13px;font-weight:600;color:#1d2327;margin-bottom:4px;">Trafic zilnic</div>
            <div style="font-size:11px;color:#646970;margin-bottom:8px;">Sesiuni unice / zi &middot; total 30d: <strong><?php echo esc_html(number_format_i18n($sum_series($g1_curr))); ?></strong></div>
            <?php echo $render_chart($g1_curr, $g1_prev); ?>
        </div>

        <div class="ti-chart" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:13px;font-weight:600;color:#1d2327;margin-bottom:4px;">Înregistrări noi / zi</div>
            <div style="font-size:11px;color:#646970;margin-bottom:8px;">WP subscribers / zi &middot; total 30d: <strong><?php echo esc_html(number_format_i18n($sum_series($g2_curr))); ?></strong></div>
            <?php echo $render_chart($g2_curr, $g2_prev); ?>
        </div>

        <div class="ti-chart" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:13px;font-weight:600;color:#1d2327;margin-bottom:4px;">Newsletter signups / zi</div>
            <div style="font-size:11px;color:#646970;margin-bottom:8px;">Active subscribers / zi &middot; total 30d: <strong><?php echo esc_html(number_format_i18n($sum_series($g3_curr))); ?></strong></div>
            <?php echo $render_chart($g3_curr, $g3_prev); ?>
        </div>

    </div>

    <p style="color:#646970;font-size:12px;margin-top:0;margin-bottom:24px;">Hover pe punctele graficului pentru data + valoarea exactă. Click pe orice card &rarr; detail rows în Advanced view.</p>

    <!-- AN-02: "Ce a funcționat" — 3 tables (top articles + sources + categories) -->
    <h2 style="font-size:15px;color:#1d2327;margin:0 0 10px;">Ce a funcționat săptămâna asta</h2>
    <div class="ti-tables-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin-bottom:18px;">

        <!-- Top 5 articles -->
        <div class="ti-table-card" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">📰 Top 5 articole (săpt.)</div>
            <?php if (empty($top_articles)): ?>
                <p style="color:#646970;font-size:13px;margin:8px 0 0;">Niciun articol citit săptămâna asta.</p>
            <?php else: ?>
                <table class="widefat" style="border:none;">
                    <tbody>
                    <?php foreach ($top_articles as $article): ?>
                        <?php $title = !empty($article->processed_title) ? $article->processed_title : $article->original_title; ?>
                        <tr>
                            <td style="padding:6px 8px;border:none;font-size:13px;"><?php echo esc_html(wp_trim_words((string) $title, 12, '…')); ?></td>
                            <td style="padding:6px 8px;border:none;font-size:13px;font-weight:600;text-align:right;width:60px;"><?php echo esc_html(number_format_i18n((int) $article->views)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="color:#646970;font-size:12px;margin-top:10px;font-style:italic;">➡️ Promovează articolul #1 pe rețelele sociale luna asta.</p>
            <?php endif; ?>
        </div>

        <!-- Top 5 sources -->
        <div class="ti-table-card" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">🔗 Top 5 surse trafic (săpt.)</div>
            <?php if (empty($top_sources) && $sources_unknown === 0): ?>
                <p style="color:#646970;font-size:13px;margin:8px 0 0;">Nicio vizită înregistrată.</p>
            <?php else: ?>
                <table class="widefat" style="border:none;">
                    <tbody>
                    <?php foreach ($top_sources as $bucket => $count):
                        $pct = $sources_total > 0 ? round(($count / $sources_total) * 100, 1) : 0;
                        $label = $source_labels[$bucket] ?? $bucket;
                    ?>
                        <tr>
                            <td style="padding:6px 8px;border:none;font-size:13px;"><?php echo esc_html($label); ?></td>
                            <td style="padding:6px 8px;border:none;font-size:13px;font-weight:600;text-align:right;width:90px;color:#1d2327;">
                                <?php echo esc_html(number_format_i18n((int) $count)); ?>
                                <span style="color:#646970;font-weight:400;">(<?php echo esc_html(number_format_i18n($pct, 1)); ?>%)</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sources_unknown > 0):
                        $unknown_pct = $sources_total > 0 ? round(($sources_unknown / $sources_total) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td style="padding:6px 8px;border:none;font-size:13px;color:#9ca3af;font-style:italic;">(neînregistrat)*</td>
                            <td style="padding:6px 8px;border:none;font-size:13px;text-align:right;width:90px;color:#9ca3af;">
                                <?php echo esc_html(number_format_i18n((int) $sources_unknown)); ?>
                                <span style="font-weight:400;">(<?php echo esc_html(number_format_i18n($unknown_pct, 1)); ?>%)</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($sources_unknown > 0): ?>
                    <p style="color:#9ca3af;font-size:11px;margin-top:8px;font-style:italic;">* Capture de referer/UTM activat 2026-05-15. Evenimentele anterioare apar ca "neînregistrat". Ratio real apare pe măsură ce se acumulează date noi (~3-7 zile).</p>
                <?php endif; ?>
                <p style="color:#646970;font-size:12px;margin-top:10px;font-style:italic;">
                    <?php
                    $top_bucket = !empty($top_sources) ? array_key_first($top_sources) : null;
                    if ($top_bucket === 'direct') echo '➡️ Mult trafic direct = brand awareness bun. Profită cu newsletter recurring.';
                    elseif ($top_bucket === 'organic_google') echo '➡️ Continui SEO articole; categoria de top atrage trafic organic.';
                    elseif (str_starts_with((string) $top_bucket, 'social_')) echo '➡️ Funcționează social — postează 2-3 articole/săpt. pe canalul ăsta.';
                    elseif ($top_bucket === 'email') echo '➡️ Newsletter aduce trafic — testează frecvență mai mare.';
                    elseif ($top_bucket === 'internal') echo '➡️ Useri navighează în site — verifică cross-links + recommended articles.';
                    else echo '➡️ Diversifică surse: încearcă SEO + social + newsletter în paralel.';
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Top 5 categories -->
        <div class="ti-table-card" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">🏷️ Top 5 categorii audiență (săpt.)</div>
            <?php if (empty($top_categories)): ?>
                <p style="color:#646970;font-size:13px;margin:8px 0 0;">Nicio categorie urmărită.</p>
            <?php else: ?>
                <table class="widefat" style="border:none;">
                    <tbody>
                    <?php foreach ($top_categories as $cat => $views):
                        $pct = $top_categories_total > 0 ? round(($views / $top_categories_total) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td style="padding:6px 8px;border:none;font-size:13px;"><?php echo esc_html($cat); ?></td>
                            <td style="padding:6px 8px;border:none;font-size:13px;font-weight:600;text-align:right;width:90px;color:#1d2327;">
                                <?php echo esc_html(number_format_i18n((int) $views)); ?>
                                <span style="color:#646970;font-weight:400;">(<?php echo esc_html(number_format_i18n($pct, 1)); ?>%)</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="color:#646970;font-size:12px;margin-top:10px;font-style:italic;">➡️ Scrie 2-3 articole noi în categoria #1 săptămâna asta.</p>
            <?php endif; ?>
        </div>

    </div>

    <style>
    @media (max-width: 768px) {
        .ti-headline-grid { grid-template-columns: 1fr !important; }
        .ti-charts-grid { grid-template-columns: 1fr !important; }
        .ti-tables-grid { grid-template-columns: 1fr !important; }
    }
    .ti-card:hover, .ti-chart:hover, .ti-table-card:hover { box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    </style>
</div>
<script>(function(){setTimeout(function(){window.location.reload();},60000);})();</script>
