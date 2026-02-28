<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Handler — Phase C
 * Sends personalized news digests to subscribers based on their schedule.
 */
class Delivery_Handler {

    private $email_sender;
    private $user_manager;
    private $subscription_manager;

    public function __construct() {
        $this->email_sender = new Email_Sender();
        $this->user_manager = new User_Manager();
        $this->subscription_manager = new Subscription_Manager();
    }

    /**
     * Main cron handler — called every 15 minutes.
     * Checks which users are due for delivery and sends their digests.
     */
    public function process_deliveries() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $sent = 0;
        $failed = 0;

        // Process each frequency type
        foreach (['realtime', 'hourly', 'daily', 'weekly', 'monthly'] as $frequency) {
            $users = $this->get_users_due_for_delivery($frequency, $now);

            foreach ($users as $user_row) {
                $user_id = (int) $user_row['user_id'];
                $channels = json_decode($user_row['delivery_channels'], true) ?: ['email'];
                $schedule = json_decode($user_row['delivery_schedule'], true);

                if (!in_array('email', $channels)) {
                    continue;
                }

                $result = $this->send_digest($user_id, $frequency, $schedule);
                if ($result) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        if ($sent > 0 || $failed > 0) {
            error_log("TeInformez Delivery: sent={$sent}, failed={$failed}");
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Get users who should receive a delivery right now.
     */
    private function get_users_due_for_delivery($frequency, \DateTime $now) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_user_preferences';
        $delivery_table = $wpdb->prefix . 'teinformez_delivery_log';

        // Get all users with this frequency
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, delivery_channels, delivery_schedule
             FROM {$table}
             WHERE JSON_EXTRACT(delivery_schedule, '$.frequency') = %s",
            $frequency
        ), ARRAY_A);

        if (empty($users)) {
            return [];
        }

        $due_users = [];

        foreach ($users as $user) {
            $schedule = json_decode($user['delivery_schedule'], true);
            $timezone = $schedule['timezone'] ?? 'Europe/Bucharest';
            $preferred_time = $schedule['time'] ?? '14:00';

            // Convert current UTC time to user's timezone
            $user_now = clone $now;
            $user_now->setTimezone(new \DateTimeZone($timezone));
            $current_time = $user_now->format('H:i');
            $current_hour = (int) $user_now->format('H');
            $current_minute = (int) $user_now->format('i');

            $pref_parts = explode(':', $preferred_time);
            $pref_hour = (int) ($pref_parts[0] ?? 14);
            $pref_minute = (int) ($pref_parts[1] ?? 0);

            $is_due = false;

            switch ($frequency) {
                case 'realtime':
                    // Every 15 minutes — always due
                    $is_due = true;
                    break;

                case 'hourly':
                    // At the preferred minute of each hour (within 15-min window)
                    $is_due = abs($current_minute - $pref_minute) < 15;
                    break;

                case 'daily':
                    // At the preferred time (within 15-min window)
                    $is_due = ($current_hour === $pref_hour && abs($current_minute - $pref_minute) < 15);
                    break;

                case 'weekly':
                    // On Monday at preferred time
                    $is_due = ($user_now->format('N') === '1' && $current_hour === $pref_hour && abs($current_minute - $pref_minute) < 15);
                    break;

                case 'monthly':
                    // On 1st of month at preferred time
                    $is_due = ($user_now->format('j') === '1' && $current_hour === $pref_hour && abs($current_minute - $pref_minute) < 15);
                    break;
            }

            if (!$is_due) {
                continue;
            }

            // Check if we already sent in this window (prevent duplicates)
            $lookback = $this->get_lookback_interval($frequency);
            $already_sent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$delivery_table}
                 WHERE user_id = %d AND channel = 'email' AND status IN ('sent', 'pending')
                 AND created_at > DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $user['user_id'],
                $lookback
            ));

            if ((int) $already_sent === 0) {
                $due_users[] = $user;
            }
        }

        return $due_users;
    }

    /**
     * Get lookback interval in minutes to prevent duplicate sends.
     */
    private function get_lookback_interval($frequency) {
        switch ($frequency) {
            case 'realtime': return 14;  // 14 min (cron runs every 15)
            case 'hourly':   return 50;  // 50 min
            case 'daily':    return 720; // 12 hours
            case 'weekly':   return 4320; // 3 days
            case 'monthly':  return 10080; // 7 days
            default:         return 720;
        }
    }

    /**
     * Send a personalized digest to a user.
     */
    private function send_digest($user_id, $frequency, $schedule) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Get matching news for this user
        $news = $this->get_news_for_user($user_id, $frequency);

        if (empty($news)) {
            // No news to send — skip silently
            return false;
        }

        // Build and send email
        $subject = $this->build_subject($frequency, count($news));
        $html = $this->build_digest_html($user, $news, $frequency);
        $text = $this->build_digest_text($user, $news);

        // Log as pending
        $log_ids = $this->log_delivery($user_id, $news, 'pending');

        $result = $this->email_sender->send($user->user_email, $subject, $html, $text);

        // Update log status
        $status = $result ? 'sent' : 'failed';
        $error = $result ? null : 'Email send failed';
        $this->update_delivery_log($log_ids, $status, $error);

        return $result;
    }

    /**
     * Get published news matching user's subscriptions since last delivery.
     */
    private function get_news_for_user($user_id, $frequency) {
        global $wpdb;
        $news_table = $wpdb->prefix . 'teinformez_news_queue';
        $delivery_table = $wpdb->prefix . 'teinformez_delivery_log';

        // Get user's active subscriptions
        $subscriptions = $this->subscription_manager->get_user_subscriptions($user_id, true);

        if (empty($subscriptions)) {
            return [];
        }

        // Build category list from subscriptions
        $categories = [];
        $topics = [];
        foreach ($subscriptions as $sub) {
            if (!empty($sub['category_slug'])) {
                $categories[] = $sub['category_slug'];
            }
            if (!empty($sub['topic_keyword'])) {
                $topics[] = $sub['topic_keyword'];
            }
        }

        // Time window based on frequency
        $hours = $this->get_news_window_hours($frequency);

        // Get published news within the time window
        $news = $wpdb->get_results($wpdb->prepare(
            "SELECT id, processed_title, processed_summary, processed_content,
                    categories, tags, original_url, ai_generated_image_url,
                    source_name, published_at
             FROM {$news_table}
             WHERE status = 'published'
             AND published_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY published_at DESC
             LIMIT 50",
            $hours
        ));

        if (empty($news)) {
            return [];
        }

        // Filter by user's subscribed categories/topics
        $matched = [];

        foreach ($news as $item) {
            $item_categories = json_decode($item->categories, true) ?? [];
            $item_tags = json_decode($item->tags, true) ?? [];

            // Check if already delivered to this user
            $already_delivered = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$delivery_table}
                 WHERE user_id = %d AND news_id = %d AND status = 'sent'",
                $user_id, $item->id
            ));

            if ((int) $already_delivered > 0) {
                continue;
            }

            // Match by category
            $category_match = !empty(array_intersect($categories, $item_categories));

            // Match by topic keyword (check in tags and title)
            $topic_match = false;
            if (!empty($topics)) {
                $title_lower = mb_strtolower($item->processed_title ?? '');
                foreach ($topics as $topic) {
                    $topic_lower = mb_strtolower($topic);
                    if (in_array($topic_lower, array_map('mb_strtolower', $item_tags))) {
                        $topic_match = true;
                        break;
                    }
                    if (mb_strpos($title_lower, $topic_lower) !== false) {
                        $topic_match = true;
                        break;
                    }
                }
            }

            if ($category_match || $topic_match) {
                $matched[] = $item;
            }
        }

        // Limit digest size
        $max_items = ($frequency === 'realtime') ? 5 : 10;
        return array_slice($matched, 0, $max_items);
    }

    /**
     * Get how far back to look for news based on frequency.
     */
    private function get_news_window_hours($frequency) {
        switch ($frequency) {
            case 'realtime': return 1;
            case 'hourly':   return 2;
            case 'daily':    return 24;
            case 'weekly':   return 168;
            case 'monthly':  return 720;
            default:         return 24;
        }
    }

    /**
     * Build email subject line.
     */
    private function build_subject($frequency, $count) {
        $labels = [
            'realtime' => 'Știri noi',
            'hourly'   => 'Rezumatul orar',
            'daily'    => 'Digestul zilnic',
            'weekly'   => 'Digestul săptămânal',
            'monthly'  => 'Digestul lunar',
        ];

        $label = $labels[$frequency] ?? 'Știri';
        return "{$label} TeInformez — {$count} " . ($count === 1 ? 'articol' : 'articole');
    }

    /**
     * Build HTML email digest.
     */
    private function build_digest_html($user, $news, $frequency) {
        $frontend_url = Config::get('frontend_url', 'https://teinformez.eu');
        $user_name = $user->display_name ?: $user->user_email;
        $greeting = $this->get_greeting($frequency);

        $news_html = '';
        foreach ($news as $item) {
            $title = esc_html($item->processed_title ?? 'Fără titlu');
            $summary = esc_html($item->processed_summary ?? '');
            $source = esc_html($item->source_name ?? '');
            $link = esc_url($frontend_url . '/news/' . $item->id);
            $image = '';

            if (!empty($item->ai_generated_image_url)) {
                $image = '<img src="' . esc_url($item->ai_generated_image_url) . '" alt="" style="width:100%;max-width:560px;height:auto;border-radius:8px;margin-bottom:12px;" />';
            }

            $news_html .= '
            <div style="margin-bottom:24px;padding:20px;background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;">
                ' . $image . '
                <h2 style="margin:0 0 8px;font-size:18px;color:#111827;">
                    <a href="' . $link . '" style="color:#2563eb;text-decoration:none;">' . $title . '</a>
                </h2>
                <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.5;">' . $summary . '</p>
                <p style="margin:0;font-size:12px;color:#9ca3af;">Sursă: ' . $source . '</p>
            </div>';
        }

        $unsubscribe_link = esc_url($frontend_url . '/dashboard/settings');

        return '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($this->build_subject($frequency, count($news))) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:600px;margin:0 auto;padding:20px;">
        <!-- Header -->
        <div style="background:#2563eb;color:#ffffff;padding:24px;text-align:center;border-radius:8px 8px 0 0;">
            <h1 style="margin:0;font-size:24px;font-weight:bold;">TeInformez</h1>
            <p style="margin:8px 0 0;font-size:14px;opacity:0.9;">Știri personalizate, livrate când vrei tu</p>
        </div>

        <!-- Greeting -->
        <div style="background:#ffffff;padding:24px;border-bottom:1px solid #e5e7eb;">
            <p style="margin:0;font-size:16px;color:#374151;">' . $greeting . ', <strong>' . esc_html($user_name) . '</strong>!</p>
            <p style="margin:8px 0 0;font-size:14px;color:#6b7280;">Iată ' . count($news) . ' ' . (count($news) === 1 ? 'articol' : 'articole') . ' selectate pentru tine:</p>
        </div>

        <!-- News items -->
        <div style="padding:20px;background:#f9fafb;">
            ' . $news_html . '
        </div>

        <!-- CTA -->
        <div style="background:#ffffff;padding:24px;text-align:center;border-top:1px solid #e5e7eb;">
            <a href="' . esc_url($frontend_url . '/news') . '" style="display:inline-block;background:#2563eb;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;">Vezi toate știrile</a>
        </div>

        <!-- Footer -->
        <div style="padding:20px;text-align:center;border-radius:0 0 8px 8px;">
            <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;">
                Primești acest email pentru că ești abonat pe TeInformez.
            </p>
            <p style="margin:0;font-size:12px;color:#9ca3af;">
                <a href="' . $unsubscribe_link . '" style="color:#6b7280;">Modifică preferințele</a> &middot;
                <a href="' . $unsubscribe_link . '" style="color:#6b7280;">Dezabonare</a>
            </p>
            <p style="margin:8px 0 0;font-size:11px;color:#d1d5db;">&copy; ' . date('Y') . ' TeInformez. Toate drepturile rezervate.</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Build plain-text fallback.
     */
    private function build_digest_text($user, $news) {
        $frontend_url = Config::get('frontend_url', 'https://teinformez.eu');
        $user_name = $user->display_name ?: $user->user_email;
        $lines = ["Salut, {$user_name}!", "", "Iată știrile tale de pe TeInformez:", ""];

        foreach ($news as $i => $item) {
            $title = $item->processed_title ?? 'Fără titlu';
            $summary = $item->processed_summary ?? '';
            $link = $frontend_url . '/news/' . $item->id;
            $lines[] = ($i + 1) . ". {$title}";
            if ($summary) {
                $lines[] = "   {$summary}";
            }
            $lines[] = "   {$link}";
            $lines[] = "";
        }

        $lines[] = "---";
        $lines[] = "Modifică preferințele: {$frontend_url}/dashboard/settings";
        $lines[] = "TeInformez — Știri personalizate, livrate când vrei tu.";

        return implode("\n", $lines);
    }

    /**
     * Get greeting based on time of day.
     */
    private function get_greeting($frequency) {
        $hour = (int) current_time('H');

        if ($hour < 12) {
            return 'Bună dimineața';
        } elseif ($hour < 18) {
            return 'Bună ziua';
        } else {
            return 'Bună seara';
        }
    }

    /**
     * Log delivery attempts in delivery_log table.
     */
    private function log_delivery($user_id, $news, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';
        $log_ids = [];

        foreach ($news as $item) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'news_id' => $item->id,
                'channel' => 'email',
                'status' => $status,
                'scheduled_for' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ]);
            $log_ids[] = $wpdb->insert_id;
        }

        return $log_ids;
    }

    /**
     * Update delivery log entries after send.
     */
    private function update_delivery_log($log_ids, $status, $error = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';

        foreach ($log_ids as $id) {
            $data = [
                'status' => $status,
                'sent_at' => ($status === 'sent') ? current_time('mysql') : null,
            ];

            if ($error) {
                $data['error_message'] = $error;
            }

            $wpdb->update($table, $data, ['id' => $id]);
        }
    }

    /**
     * Get delivery stats for a user (used by API).
     */
    public function get_user_delivery_stats($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        $sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'sent'",
            $user_id
        ));

        $last_delivery = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sent_at) FROM {$table} WHERE user_id = %d AND status = 'sent'",
            $user_id
        ));

        return [
            'total_delivered' => $total,
            'sent' => $sent,
            'failed' => $total - $sent,
            'last_delivery' => $last_delivery,
        ];
    }

    /**
     * Get recent deliveries for a user (used by API).
     */
    public function get_user_deliveries($user_id, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';
        $news_table = $wpdb->prefix . 'teinformez_news_queue';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.channel, d.status, d.sent_at, d.created_at,
                    n.processed_title as news_title, n.id as news_id
             FROM {$table} d
             LEFT JOIN {$news_table} n ON d.news_id = n.id
             WHERE d.user_id = %d
             ORDER BY d.created_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
    }
}
