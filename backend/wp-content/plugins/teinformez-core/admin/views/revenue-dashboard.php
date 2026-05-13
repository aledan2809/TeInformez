<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'teinformez'));
}

global $wpdb;

$users_table       = $wpdb->prefix . 'users';
$newsletter_table  = $wpdb->prefix . 'teinformez_newsletter';
$subs_table        = $wpdb->prefix . 'teinformez_subscriptions';
$news_table        = $wpdb->prefix . 'teinformez_news_queue';
$ads_table         = $wpdb->prefix . 'teinformez_newsletter_ads';
$today             = date('Y-m-d');
$month_start       = date('Y-m-01');

// === USERS ===
$total_users   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$users_table}");
$users_month   = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$users_table} WHERE user_registered >= %s",
    $month_start . ' 00:00:00'
));

// === NEWSLETTER SUBSCRIBERS (confirmed, not unsubscribed) ===
$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $newsletter_table)) === $newsletter_table;
$nl_total = $nl_month = $nl_unconfirmed = 0;
if ($table_exists) {
    $nl_total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newsletter_table} WHERE confirmed = 1 AND unsubscribed_at IS NULL");
    $nl_month       = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$newsletter_table} WHERE confirmed = 1 AND unsubscribed_at IS NULL AND subscribed_at >= %s",
        $month_start . ' 00:00:00'
    ));
    $nl_unconfirmed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newsletter_table} WHERE confirmed = 0 AND unsubscribed_at IS NULL");
}

// === NEWSLETTER ADS ===
$ads_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ads_table)) === $ads_table;
$ads_active     = $ads_upcoming = $ads_ended = [];
$ads_total_imp  = 0;
if ($ads_table_exists) {
    $all_ads = $wpdb->get_results("SELECT * FROM {$ads_table} ORDER BY campaign_start DESC");
    foreach ($all_ads as $ad) {
        $ads_total_imp += (int) $ad->impressions;
        if ($ad->status === 'active' && $ad->campaign_start <= $today && $ad->campaign_end >= $today) {
            $ads_active[] = $ad;
        } elseif ($ad->campaign_start > $today) {
            $ads_upcoming[] = $ad;
        } else {
            $ads_ended[] = $ad;
        }
    }
}

// === ADSENSE ===
$adsense_client = defined('TEINFORMEZ_ADSENSE_CLIENT') ? constant('TEINFORMEZ_ADSENSE_CLIENT') : getenv('NEXT_PUBLIC_ADSENSE_CLIENT');
$adsense_slot   = defined('TEINFORMEZ_ADSENSE_SLOT')   ? constant('TEINFORMEZ_ADSENSE_SLOT')   : getenv('NEXT_PUBLIC_ADSENSE_SLOT');

// === NEWS STATS ===
$news_published_month = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$news_table} WHERE status = 'published' AND published_at >= %s",
    $month_start . ' 00:00:00'
));
$news_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$news_table} WHERE status = 'published'");

$card = function(string $label, string $value, string $sub = '', string $color = '#2563eb') {
    echo '<div style="background:#fff;border-radius:8px;padding:20px 24px;border-left:4px solid ' . esc_attr($color) . ';box-shadow:0 1px 3px rgba(0,0,0,.08);">'
        . '<p style="margin:0 0 4px;font-size:12px;color:#6b7280;text-transform:uppercase;font-weight:600;">' . esc_html($label) . '</p>'
        . '<p style="margin:0;font-size:28px;font-weight:700;color:#111827;">' . esc_html($value) . '</p>'
        . ($sub ? '<p style="margin:4px 0 0;font-size:12px;color:#9ca3af;">' . esc_html($sub) . '</p>' : '')
        . '</div>';
};
?>
<div class="wrap">
    <h1><?php esc_html_e('Revenue Dashboard', 'teinformez'); ?></h1>
    <p style="color:#6b7280"><?php echo esc_html(sprintf(__('Data: %s — toate cifrele sunt cumulate/luna curentă', 'teinformez'), date_i18n('j F Y'))); ?></p>

    <!-- Grid cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin:24px 0;">
        <?php
        $card(__('Utilizatori înregistrați', 'teinformez'), number_format($total_users), sprintf(__('%d noi luna asta', 'teinformez'), $users_month), '#2563eb');
        $card(__('Abonați newsletter', 'teinformez'), number_format($nl_total), sprintf(__('%d noi + %d neconfirmați', 'teinformez'), $nl_month, $nl_unconfirmed), '#16a34a');
        $card(__('Articole publicate', 'teinformez'), number_format($news_total), sprintf(__('%d luna asta', 'teinformez'), $news_published_month), '#7c3aed');
        $card(__('Campanii ads active azi', 'teinformez'), (string) count($ads_active), count($ads_upcoming) . ' ' . __('viitoare', 'teinformez'), count($ads_active) > 0 ? '#16a34a' : '#9ca3af');
        $card(__('Total impressii newsletter', 'teinformez'), number_format($ads_total_imp), __('din toate campaniile', 'teinformez'), '#ea580c');
        ?>
    </div>

    <hr>

    <!-- Newsletter Ads section -->
    <h2><?php esc_html_e('Newsletter Ads — Campanii', 'teinformez'); ?></h2>

    <?php if (!$ads_table_exists): ?>
        <p><?php esc_html_e('Tabelul newsletter_ads nu există. Accesează o pagină de admin pentru a rula migrarea.', 'teinformez'); ?></p>
    <?php elseif (empty($ads_active) && empty($ads_upcoming) && empty($ads_ended)): ?>
        <p><?php esc_html_e('Nicio campanie. ', 'teinformez'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=teinformez-newsletter-ads')); ?>"><?php esc_html_e('Adaugă prima campanie →', 'teinformez'); ?></a>
        </p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Sponsor', 'teinformez'); ?></th>
                    <th style="width:110px"><?php esc_html_e('Start', 'teinformez'); ?></th>
                    <th style="width:110px"><?php esc_html_e('End', 'teinformez'); ?></th>
                    <th style="width:90px"><?php esc_html_e('Status', 'teinformez'); ?></th>
                    <th style="width:100px"><?php esc_html_e('Impressii', 'teinformez'); ?></th>
                    <th style="width:80px"><?php esc_html_e('Azi', 'teinformez'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_merge($ads_active, $ads_upcoming, $ads_ended) as $ad): ?>
                <?php $is_today_ad = ($ad->status === 'active' && $ad->campaign_start <= $today && $ad->campaign_end >= $today); ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($ad->sponsor_name); ?></strong>
                        &nbsp; <a href="<?php echo esc_url(admin_url('admin.php?page=teinformez-newsletter-ads&action=edit&id=' . (int) $ad->id)); ?>" style="font-size:11px"><?php esc_html_e('editează', 'teinformez'); ?></a>
                    </td>
                    <td><?php echo esc_html($ad->campaign_start); ?></td>
                    <td><?php echo esc_html($ad->campaign_end); ?></td>
                    <td><?php echo $ad->status === 'active' ? '<span style="color:#16a34a">&#9679; Activ</span>' : '<span style="color:#9ca3af">&#9679; Pausat</span>'; ?></td>
                    <td><?php echo number_format((int) $ad->impressions); ?></td>
                    <td><?php echo $is_today_ad ? '<span style="color:#2563eb;font-weight:bold">&#10003;</span>' : '&mdash;'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=teinformez-newsletter-ads')); ?>" class="button"><?php esc_html_e('Gestionează campanii →', 'teinformez'); ?></a>
        </p>
    <?php endif; ?>

    <hr>

    <!-- InFeed Ads (AdSense) section -->
    <h2><?php esc_html_e('InFeed Ads — AdSense', 'teinformez'); ?></h2>
    <?php if ($adsense_client && $adsense_slot): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px 20px;">
            <p style="margin:0;color:#15803d;font-weight:600;">&#10003; <?php esc_html_e('AdSense configurat', 'teinformez'); ?></p>
            <p style="margin:4px 0 0;font-size:13px;color:#166534;"><?php echo esc_html('Client: ' . $adsense_client . ' | Slot: ' . $adsense_slot); ?></p>
        </div>
    <?php else: ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:16px 20px;">
            <p style="margin:0;color:#b45309;font-weight:600;">&#9888; <?php esc_html_e('AdSense neconfigurat — InFeedAd afișează caruselul intern', 'teinformez'); ?></p>
            <p style="margin:8px 0 0;font-size:13px;color:#92400e;"><?php esc_html_e('Pentru a activa AdSense, adaugă în .env pe VPS:', 'teinformez'); ?></p>
            <pre style="background:#fff;border:1px solid #fde68a;border-radius:4px;padding:8px 12px;font-size:12px;margin:8px 0 0;">NEXT_PUBLIC_ADSENSE_CLIENT=ca-pub-XXXXXXXXXXXXXXXX
NEXT_PUBLIC_ADSENSE_SLOT=XXXXXXXXXX</pre>
            <p style="margin:8px 0 0;font-size:12px;color:#92400e;"><?php esc_html_e('Apoi: pm2 restart teinformez', 'teinformez'); ?></p>
        </div>
    <?php endif; ?>

    <hr>

    <h2><?php esc_html_e('Strategia de monetizare curentă', 'teinformez'); ?></h2>
    <div style="background:#f8fafc;border-radius:8px;padding:16px 20px;font-size:13px;color:#374151;line-height:1.7;">
        <p style="margin:0 0 8px"><strong><?php esc_html_e('Model: 100% Free + Ads', 'teinformez'); ?></strong></p>
        <ul style="margin:0;padding-left:18px;">
            <li><?php esc_html_e('InFeed Ads în news feed (AdSense sau carusel intern)', 'teinformez'); ?></li>
            <li><?php esc_html_e('Newsletter Ads — bloc „Partener" în fiecare email digest (campanie sponsorizată sau rotație internă)', 'teinformez'); ?></li>
            <li><?php esc_html_e('Premium tier: DEFER — se evaluează când baza de useri crește', 'teinformez'); ?></li>
        </ul>
    </div>
</div>
