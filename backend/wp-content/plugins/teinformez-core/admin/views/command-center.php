<?php
/**
 * Cabina de comandă — operational health at a glance + "De făcut acum".
 *
 * Four health tiles (Publicare / Livrări / Coadă / Venit) fed by live data,
 * plus a derived action list. Designed to surface the failure class that
 * stayed invisible for 16 days in June 2026 (cron hook silently unscheduled
 * → publishing dead): every critical cron hook is checked for existence AND
 * for being overdue (wp-cron not ticking).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'teinformez'));
}

use TeInformez\Config;

global $wpdb;

$news_table     = $wpdb->prefix . 'teinformez_news_queue';
$delivery_table = $wpdb->prefix . 'teinformez_delivery_log';
$stripe_table   = $wpdb->prefix . 'teinformez_stripe_subscriptions';

$now_epoch = time();
$now_local = current_time('mysql');
$ago_24h   = date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);
$today     = date('Y-m-d', current_time('timestamp')) . ' 00:00:00';

/** Human-friendly "acum Xh Ym" for an elapsed-seconds value. */
$fmt_elapsed = static function (int $seconds): string {
    if ($seconds < 60) return 'acum <1 min';
    if ($seconds < HOUR_IN_SECONDS) return 'acum ' . floor($seconds / 60) . ' min';
    if ($seconds < DAY_IN_SECONDS) {
        $h = floor($seconds / HOUR_IN_SECONDS);
        $m = floor(($seconds % HOUR_IN_SECONDS) / 60);
        return 'acum ' . $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
    }
    return 'acum ' . floor($seconds / DAY_IN_SECONDS) . ' zile';
};

// ---------------------------------------------------------------------------
// 1) MOTOR — critical cron hooks: missing OR overdue (>30 min past due) = CRIT.
//    teinformez_ensure_crons() already ran on plugins_loaded for THIS request,
//    so "missing" here means re-scheduling itself is failing — genuinely bad.
// ---------------------------------------------------------------------------
$cron_hooks = [
    'teinformez_fetch_news'            => 'Preluare știri (RSS)',
    'teinformez_process_news'          => 'Procesare + publicare',
    'teinformez_check_deliveries'      => 'Trimitere digest-uri',
    'teinformez_check_delivery_health' => 'Verificare livrări',
];
$cron_problems = [];
$cron_rows     = [];
foreach ($cron_hooks as $hook => $label) {
    $next = wp_next_scheduled($hook);
    if (!$next) {
        $cron_rows[$hook]     = ['label' => $label, 'state' => 'crit', 'text' => 'NEPROGRAMAT'];
        $cron_problems[$hook] = 'missing';
    } elseif ($next < $now_epoch - 30 * MINUTE_IN_SECONDS) {
        $cron_rows[$hook]     = ['label' => $label, 'state' => 'crit', 'text' => 'întârziat ' . $fmt_elapsed($now_epoch - $next)];
        $cron_problems[$hook] = 'overdue';
    } else {
        $mins = max(0, (int) ceil(($next - $now_epoch) / 60));
        $cron_rows[$hook] = ['label' => $label, 'state' => 'ok', 'text' => 'rulează în ~' . $mins . ' min'];
    }
}

// ---------------------------------------------------------------------------
// 2) PUBLICARE — last published story vs. the configured cadence gap.
// ---------------------------------------------------------------------------
$gap = (int) Config::get('publish_min_gap', 25200); // ~7h default
if ($gap <= 0) {
    $gap = 25200;
}
$last_pub_ts = (int) get_option('teinformez_last_publish_ts', 0);
if ($last_pub_ts <= 0) {
    // Fallback for the window before the throttle option existed.
    $max_pub = $wpdb->get_var("SELECT MAX(published_at) FROM {$news_table} WHERE status = 'published'");
    if ($max_pub) {
        // published_at is written with current_time('mysql') (site-local clock);
        // convert via the same site-local offset to epoch.
        $last_pub_ts = strtotime($max_pub) - (current_time('timestamp') - $now_epoch);
    }
}
$published_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$news_table} WHERE status = 'published' AND published_at >= %s",
    $today
));

if ($last_pub_ts <= 0) {
    $pub_state = 'crit';
    $pub_text  = 'Nicio știre publicată (încă).';
} else {
    $elapsed = $now_epoch - $last_pub_ts;
    if ($elapsed > 4 * $gap) {
        $pub_state = 'crit';
    } elseif ($elapsed > 2 * $gap) {
        $pub_state = 'warn';
    } else {
        $pub_state = 'ok';
    }
    $pub_text = 'Ultima știre: ' . $fmt_elapsed($elapsed);
}

// ---------------------------------------------------------------------------
// 3) COADĂ — pipeline counts + intake freshness.
// ---------------------------------------------------------------------------
$status_counts = ['fetched' => 0, 'processing' => 0, 'pending_review' => 0, 'approved' => 0, 'rejected' => 0, 'published' => 0];
$rows = $wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$news_table} GROUP BY status");
foreach ((array) $rows as $r) {
    if (isset($status_counts[$r->status])) {
        $status_counts[$r->status] = (int) $r->n;
    }
}
$fetched_24h = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$news_table} WHERE fetched_at >= %s",
    $ago_24h
));

$queue_state = 'ok';
if ($fetched_24h === 0) {
    $queue_state = 'warn'; // RSS intake dead — the upstream half of the June outage.
}
if ($status_counts['processing'] > 20) {
    $queue_state = 'warn'; // items stuck mid-processing → processor likely dying.
}

// ---------------------------------------------------------------------------
// 4) LIVRĂRI — last-24h digest sends.
// ---------------------------------------------------------------------------
$sent_24h = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$delivery_table} WHERE created_at >= %s AND status = 'sent'",
    $ago_24h
));
$failed_24h = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$delivery_table} WHERE created_at >= %s AND status = 'failed'",
    $ago_24h
));
$deliv_total = $sent_24h + $failed_24h;
if ($deliv_total > 0 && $sent_24h === 0) {
    $deliv_state = 'crit';
} elseif ($deliv_total > 0 && ($failed_24h / $deliv_total) > 0.2) {
    $deliv_state = 'warn';
} else {
    $deliv_state = 'ok';
}

// ---------------------------------------------------------------------------
// 5) VENIT — active premium subscriptions (tile links to Revenue Dashboard).
// ---------------------------------------------------------------------------
$premium_active = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$stripe_table} WHERE tier = 'premium' AND status IN ('active', 'trialing')"
);

// ---------------------------------------------------------------------------
// „De făcut acum" — derived, severity-ordered.
// ---------------------------------------------------------------------------
$actions = [];
foreach ($cron_problems as $hook => $why) {
    $actions[] = [
        'sev'  => 'crit',
        'text' => sprintf(
            '%s (%s) e %s. Auto-repararea rulează la fiecare încărcare de pagină — reîncarcă pagina; dacă persistă, verifică wp-cron pe server (crontab / DISABLE_WP_CRON).',
            esc_html($cron_hooks[$hook]),
            esc_html($hook),
            $why === 'missing' ? 'NEPROGRAMAT' : 'ÎNTÂRZIAT — wp-cron nu bate'
        ),
    ];
}
if ($pub_state === 'crit' && empty($cron_problems)) {
    $actions[] = [
        'sev'  => 'crit',
        'text' => 'Publicarea pare oprită deși cron-urile sunt programate. Verifică News Queue (există articole approved?) și logurile de procesare.',
    ];
} elseif ($pub_state === 'warn') {
    $actions[] = [
        'sev'  => 'warn',
        'text' => 'Ultima publicare e mai veche decât dublul cadenței setate (~' . round($gap / 3600, 1) . 'h). Ține sub observație — următorul tick ar trebui să publice.',
    ];
}
if ($fetched_24h === 0) {
    $actions[] = [
        'sev'  => 'warn',
        'text' => 'Nicio știre preluată în ultimele 24h — sursele RSS nu aduc nimic. Verifică News Sources / conexiunea la feed-uri.',
    ];
}
if ($status_counts['processing'] > 20) {
    $actions[] = [
        'sev'  => 'warn',
        'text' => $status_counts['processing'] . ' articole blocate în „processing" — procesorul AI pare să moară în mijlocul lucrului. Verifică logurile.',
    ];
}
if ($deliv_state !== 'ok') {
    $actions[] = [
        'sev'  => $deliv_state,
        'text' => $failed_24h . ' livrări eșuate în 24h (din ' . $deliv_total . '). Verifică providerul de email (Brevo/SendGrid) și cheile API.',
    ];
}

$sev_badge = static function (string $state): string {
    $map = ['ok' => ['#00a32a', '● OK'], 'warn' => ['#dba617', '● Atenție'], 'crit' => ['#d63638', '● Problemă']];
    [$color, $label] = $map[$state] ?? $map['warn'];
    return '<span style="color:' . $color . ';font-weight:600;">' . $label . '</span>';
};
?>
<div class="wrap">
    <h1>🎛️ <?php _e('Cabina de comandă', 'teinformez'); ?></h1>
    <p style="color:#646970;margin-top:2px;">
        <?php _e('Starea operațională la zi. Dacă e ceva de făcut, apare mai jos — nu trebuie să cauți prin meniuri.', 'teinformez'); ?>
    </p>

    <?php // „De făcut acum" — first, because it's the point of the page. ?>
    <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo empty($actions) ? '#00a32a' : (in_array('crit', array_column($actions, 'sev'), true) ? '#d63638' : '#dba617'); ?>;border-radius:4px;padding:14px 18px;margin:16px 0;">
        <h2 style="margin:0 0 8px;font-size:15px;">📌 <?php _e('De făcut acum', 'teinformez'); ?></h2>
        <?php if (empty($actions)) : ?>
            <p style="margin:0;color:#00a32a;font-weight:600;"><?php _e('Totul funcționează. Nimic de făcut. ✅', 'teinformez'); ?></p>
        <?php else : ?>
            <ol style="margin:0 0 0 18px;">
                <?php foreach ($actions as $a) : ?>
                    <li style="margin-bottom:6px;">
                        <?php echo $sev_badge($a['sev']); ?>
                        <?php echo wp_kses_post($a['text']); ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:6px;">

        <!-- Publicare -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;">
            <h2 style="margin:0 0 6px;font-size:14px;">📰 <?php _e('Publicare', 'teinformez'); ?> <?php echo $sev_badge($pub_state); ?></h2>
            <p style="margin:0 0 4px;font-size:13px;"><?php echo esc_html($pub_text); ?></p>
            <p style="margin:0 0 8px;font-size:13px;color:#646970;">
                <?php printf(__('Azi: %d publicate · cadență ~%sh', 'teinformez'), $published_today, esc_html(round($gap / 3600, 1))); ?>
            </p>
            <table style="width:100%;font-size:12px;border-collapse:collapse;">
                <?php foreach ($cron_rows as $row) : ?>
                    <tr>
                        <td style="padding:1px 0;color:#646970;"><?php echo esc_html($row['label']); ?></td>
                        <td style="padding:1px 0;text-align:right;<?php echo $row['state'] === 'crit' ? 'color:#d63638;font-weight:600;' : 'color:#00a32a;'; ?>">
                            <?php echo esc_html($row['text']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p style="margin:8px 0 0;"><a href="<?php echo esc_url(admin_url('admin.php?page=teinformez-news-queue')); ?>"><?php _e('News Queue →', 'teinformez'); ?></a></p>
        </div>

        <!-- Livrări -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;">
            <h2 style="margin:0 0 6px;font-size:14px;">📬 <?php _e('Livrări (24h)', 'teinformez'); ?> <?php echo $sev_badge($deliv_state); ?></h2>
            <p style="margin:0;font-size:22px;font-weight:600;"><?php echo esc_html($sent_24h); ?> <span style="font-size:12px;font-weight:400;color:#646970;"><?php _e('trimise', 'teinformez'); ?></span></p>
            <p style="margin:2px 0 0;font-size:13px;color:<?php echo $failed_24h > 0 ? '#d63638' : '#646970'; ?>;">
                <?php printf(__('%d eșuate', 'teinformez'), $failed_24h); ?>
            </p>
        </div>

        <!-- Coadă -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;">
            <h2 style="margin:0 0 6px;font-size:14px;">📥 <?php _e('Coadă', 'teinformez'); ?> <?php echo $sev_badge($queue_state); ?></h2>
            <table style="width:100%;font-size:12px;border-collapse:collapse;">
                <tr><td style="color:#646970;"><?php _e('Preluate (24h)', 'teinformez'); ?></td><td style="text-align:right;font-weight:600;"><?php echo esc_html($fetched_24h); ?></td></tr>
                <tr><td style="color:#646970;"><?php _e('În procesare', 'teinformez'); ?></td><td style="text-align:right;"><?php echo esc_html($status_counts['processing']); ?></td></tr>
                <tr><td style="color:#646970;"><?php _e('Aprobate (așteaptă cadența)', 'teinformez'); ?></td><td style="text-align:right;"><?php echo esc_html($status_counts['approved']); ?></td></tr>
                <tr><td style="color:#646970;"><?php _e('Pending review', 'teinformez'); ?></td><td style="text-align:right;"><?php echo esc_html($status_counts['pending_review']); ?></td></tr>
                <tr><td style="color:#646970;"><?php _e('Publicate (total)', 'teinformez'); ?></td><td style="text-align:right;"><?php echo esc_html($status_counts['published']); ?></td></tr>
            </table>
        </div>

        <!-- Venit -->
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:14px 16px;">
            <h2 style="margin:0 0 6px;font-size:14px;">💰 <?php _e('Venit', 'teinformez'); ?></h2>
            <p style="margin:0;font-size:22px;font-weight:600;"><?php echo esc_html($premium_active); ?> <span style="font-size:12px;font-weight:400;color:#646970;"><?php _e('abonamente premium active', 'teinformez'); ?></span></p>
            <p style="margin:8px 0 0;"><a href="<?php echo esc_url(admin_url('admin.php?page=teinformez-revenue')); ?>"><?php _e('Revenue Dashboard →', 'teinformez'); ?></a></p>
        </div>

    </div>
</div>
