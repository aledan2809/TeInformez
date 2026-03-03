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

        // Get matching news grouped by category
        $by_category = $this->get_news_for_user($user_id, $frequency);

        if (empty($by_category)) {
            return false;
        }

        // Flatten for counting, logging, and plain-text fallback
        $all_news = [];
        foreach ($by_category as $items) {
            foreach ($items as $item) {
                $all_news[] = $item;
            }
        }

        if (empty($all_news)) {
            return false;
        }

        // Build and send email
        $subject = $this->build_subject($frequency, count($all_news));
        $html = $this->build_digest_html($user, $by_category, $frequency);
        $text = $this->build_digest_text($user, $all_news);

        // Log as pending
        $log_ids = $this->log_delivery($user_id, $all_news, 'pending');

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
                // Store which subscribed category matched (for correct grouping)
                $item->_matched_cat = '';
                $item_cats_list = json_decode($item->categories, true) ?? [];
                foreach ($item_cats_list as $ic) {
                    if (in_array($ic, $categories)) {
                        $item->_matched_cat = $ic;
                        break;
                    }
                }
                if (empty($item->_matched_cat)) {
                    $item->_matched_cat = $item_cats_list[0] ?? 'other';
                }
                $matched[] = $item;
            }
        }

        // Group matched articles by the subscribed category that matched
        $by_category = [];

        foreach ($matched as $item) {
            $cat = $item->_matched_cat;
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = [];
            }
            // Limit to 10 per category
            if (count($by_category[$cat]) < 10) {
                $by_category[$cat][] = $item;
            }
        }

        return $by_category;
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
     * Build HTML email digest — "Axios meets Morning Brew" single-column layout.
     * $by_category = ['politics' => [$item1, $item2, ...], 'tech' => [...], ...]
     */
    private function build_digest_html($user, $by_category, $frequency) {
        $frontend_url = Config::get('frontend_url', 'https://teinformez.eu');
        $user_name = $user->display_name ?: $user->user_email;
        $greeting = $this->get_greeting($frequency);

        // Category label mapping
        $cat_labels = [];
        $cat_colors = [
            'tech' => '#7c3aed', 'auto' => '#0891b2', 'finance' => '#059669',
            'entertainment' => '#d946ef', 'sports' => '#dc2626', 'science' => '#0d9488',
            'politics' => '#4338ca', 'business' => '#ea580c',
        ];
        foreach (Config::DEFAULT_CATEGORIES as $slug => $cat) {
            $cat_labels[$slug] = $cat['icon'] . ' ' . $cat['label'];
        }

        // Count total articles + flatten for hero selection
        $total_count = 0;
        $all_articles = [];
        foreach ($by_category as $cat_slug => $items) {
            $total_count += count($items);
            foreach ($items as $item) {
                $item->_cat_slug = $cat_slug;
                $all_articles[] = $item;
            }
        }

        // Pick hero article: first article with an image, or first article overall
        $hero = null;
        $hero_cat = '';
        foreach ($all_articles as $article) {
            if (!empty($article->ai_generated_image_url)) {
                $hero = $article;
                $hero_cat = $article->_cat_slug;
                break;
            }
        }
        if (!$hero && !empty($all_articles)) {
            $hero = $all_articles[0];
            $hero_cat = $all_articles[0]->_cat_slug;
        }

        // Track used image IDs to avoid duplicates
        $used_image_ids = [];
        $thumbnail_budget = 4; // thumbnails in category sections (hero image is separate, total ~5)

        // === HERO SECTION ===
        $hero_html = '';
        if ($hero) {
            $h_title = esc_html($hero->processed_title ?? 'Fără titlu');
            $h_summary = esc_html($hero->processed_summary ?? '');
            if (mb_strlen($h_summary) > 200) {
                $h_summary = mb_substr($h_summary, 0, 197) . '...';
            }
            $h_link = esc_url($frontend_url . '/news/' . $hero->id);
            $h_cat_label = $cat_labels[$hero_cat] ?? '';
            $h_cat_color = $cat_colors[$hero_cat] ?? '#4338ca';
            $h_image = $hero->ai_generated_image_url ?? '';

            $hero_image_html = '';
            if (!empty($h_image)) {
                $used_image_ids[] = $hero->id;
                $hero_image_html = '
                    <a href="' . $h_link . '" style="display:block;">
                        <img src="' . esc_url($h_image) . '" alt="" width="560" style="display:block;width:100%;border-radius:0;" />
                    </a>';
            }

            $hero_html = '
            <div style="background:#ffffff;overflow:hidden;">
                ' . $hero_image_html . '
                <div style="padding:20px 24px 24px;">
                    <p style="margin:0 0 8px;font-size:11px;font-weight:bold;color:' . $h_cat_color . ';text-transform:uppercase;letter-spacing:0.5px;">' . $h_cat_label . '</p>
                    <h2 style="margin:0 0 10px;font-size:20px;line-height:1.3;color:#111827;">
                        <a href="' . $h_link . '" style="color:#111827;text-decoration:none;">' . $h_title . '</a>
                    </h2>
                    <p style="margin:0 0 14px;font-size:14px;color:#4b5563;line-height:1.5;">' . $h_summary . '</p>
                    <a href="' . $h_link . '" style="font-size:13px;font-weight:bold;color:#2563eb;text-decoration:none;">Citește mai mult &rarr;</a>
                </div>
            </div>';
        }

        // === CATEGORY SECTIONS (2-column: lead+image left, headlines right) ===
        $sections_html = '';
        $cat_index = 0;
        foreach ($by_category as $cat_slug => $items) {
            if (empty($items)) continue;

            $cat_label = $cat_labels[$cat_slug] ?? ucfirst($cat_slug);
            $cat_color = $cat_colors[$cat_slug] ?? '#4338ca';

            // Filter out hero article from this category
            $filtered_items = [];
            foreach ($items as $item) {
                if ($hero && $item->id === $hero->id) continue;
                $filtered_items[] = $item;
            }
            if (empty($filtered_items)) continue;

            // Smart lead selection: if budget allows, pick an article WITH image as lead
            // This ensures images are distributed across categories, not just first ones
            $lead_idx = 0;
            if ($thumbnail_budget > 0) {
                foreach ($filtered_items as $fi => $fitem) {
                    if (!empty($fitem->ai_generated_image_url) && !in_array($fitem->id, $used_image_ids)) {
                        $lead_idx = $fi;
                        break;
                    }
                }
            }

            // Split: lead article (left) + rest as headlines (right)
            $lead = $filtered_items[$lead_idx];
            $sidebar_items = [];
            foreach ($filtered_items as $fi => $fitem) {
                if ($fi !== $lead_idx) $sidebar_items[] = $fitem;
            }

            $lead_title = esc_html($lead->processed_title ?? 'Fără titlu');
            $lead_summary = esc_html($lead->processed_summary ?? '');
            if (mb_strlen($lead_summary) > 140) {
                $lead_summary = mb_substr($lead_summary, 0, 137) . '...';
            }
            $lead_link = esc_url($frontend_url . '/news/' . $lead->id);
            $lead_image = $lead->ai_generated_image_url ?? '';

            // Show image if available, not used, within budget
            $show_img = !empty($lead_image) && !in_array($lead->id, $used_image_ids) && $thumbnail_budget > 0;
            if ($show_img) {
                $used_image_ids[] = $lead->id;
                $thumbnail_budget--;
            }

            // Category header
            $sections_html .= '
            <div style="background:#ffffff;border-top:3px solid ' . $cat_color . ';margin-top:16px;">
                <div style="padding:14px 24px 8px;">
                    <span style="font-size:13px;font-weight:bold;color:' . $cat_color . ';text-transform:uppercase;letter-spacing:0.5px;">' . $cat_label . '</span>
                </div>';

            // Build LEFT column: image + lead article
            $left_html = '';
            if ($show_img) {
                $left_html .= '
                    <a href="' . $lead_link . '" style="display:block;margin-bottom:8px;">
                        <img src="' . esc_url($lead_image) . '" alt="" width="100%" style="display:block;border-radius:4px;" />
                    </a>';
            }
            $left_html .= '
                <h3 style="margin:0 0 5px;font-size:14px;line-height:1.3;color:#111827;">
                    <a href="' . $lead_link . '" style="color:#111827;text-decoration:none;">' . $lead_title . '</a>
                </h3>
                <p style="margin:0 0 6px;font-size:12px;color:#4b5563;line-height:1.4;">' . $lead_summary . '</p>
                <a href="' . $lead_link . '" style="font-size:11px;font-weight:bold;color:#2563eb;text-decoration:none;">Citește &rarr;</a>';

            // Build RIGHT column: headline links
            $right_html = '';
            if (!empty($sidebar_items)) {
                $right_html .= '<p style="margin:0 0 8px;font-size:10px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Mai multe:</p>';
                foreach ($sidebar_items as $si => $side) {
                    $s_title = esc_html($side->processed_title ?? 'Fără titlu');
                    if (mb_strlen($s_title) > 75) {
                        $s_title = mb_substr($s_title, 0, 72) . '...';
                    }
                    $s_link = esc_url($frontend_url . '/news/' . $side->id);
                    $s_border = $si > 0 ? 'border-top:1px solid #f3f4f6;padding-top:6px;' : '';
                    $right_html .= '
                    <div style="margin-bottom:6px;' . $s_border . '">
                        <a href="' . $s_link . '" style="color:#2563eb;text-decoration:none;font-size:11px;line-height:1.3;display:block;max-height:2.6em;overflow:hidden;">' . $s_title . '</a>
                    </div>';
                }
            }

            // 2-column table layout
            $sections_html .= '
                <div style="padding:0 16px 16px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                        <tr>
                            <td width="58%" valign="top" style="padding:8px;">
                                ' . $left_html . '
                            </td>
                            <td width="42%" valign="top" style="padding:8px 8px 8px 12px;border-left:1px solid #e5e7eb;">
                                ' . $right_html . '
                            </td>
                        </tr>
                    </table>
                </div>';

            $sections_html .= '</div>'; // close category card
            $cat_index++;
        }

        // Inject mid-email WhatsApp share CTA after 2nd category
        $share_text = urlencode("Citește știrile zilei pe TeInformez — gratuit și personalizat! 📰 https://teinformez.eu");
        $wa_share_url = 'whatsapp://send?text=' . $share_text;
        $tg_share_url = 'https://t.me/share/url?url=' . urlencode('https://teinformez.eu') . '&text=' . urlencode('Citește știrile zilei pe TeInformez — gratuit și personalizat! 📰');

        // Split sections_html to inject mid-email CTA after 2nd category card
        // Count </div> category closings to find insertion point
        $mid_cta_html = '
        <div style="background:#dcfce7;padding:14px 24px;margin-top:16px;text-align:center;border-left:4px solid #22c55e;">
            <p style="margin:0 0 8px;font-size:13px;color:#166534;font-weight:bold;">Cunoști pe cineva interesat de știri? Trimite-i pe WhatsApp!</p>
            <a href="' . $wa_share_url . '" style="display:inline-block;background:#25d366;color:#ffffff;padding:8px 20px;text-decoration:none;border-radius:6px;font-size:13px;font-weight:bold;">Trimite pe WhatsApp</a>
        </div>';

        // Insert mid-CTA after 2nd category section
        if ($cat_index >= 2) {
            $pos = 0;
            $card_closings = 0;
            $search_pos = 0;
            while ($card_closings < 2 && ($pos = strpos($sections_html, 'close category card', $search_pos)) !== false) {
                $card_closings++;
                $search_pos = $pos + 20;
            }
            if ($card_closings === 2) {
                // Find the </div> after the comment
                $insert_pos = strpos($sections_html, '</div>', $pos);
                if ($insert_pos !== false) {
                    $insert_pos += 6;
                    $sections_html = substr($sections_html, 0, $insert_pos) . $mid_cta_html . substr($sections_html, $insert_pos);
                }
            }
        }

        $unsubscribe_link = esc_url($frontend_url . '/dashboard/settings');
        $today_date = date_i18n('j F Y'); // e.g. "3 martie 2026"

        return '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($this->build_subject($frequency, $total_count)) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f1f3;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:600px;margin:0 auto;">

        <!-- Header -->
        <div style="background:#1e293b;padding:20px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td>
                        <h1 style="margin:0;font-size:22px;font-weight:bold;color:#ffffff;">TeInformez</h1>
                    </td>
                    <td align="right">
                        <span style="font-size:12px;color:#94a3b8;">' . esc_html($today_date) . '</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Greeting bar -->
        <div style="background:#ffffff;padding:16px 24px;border-bottom:1px solid #e5e7eb;">
            <p style="margin:0;font-size:15px;color:#374151;">' . $greeting . ', <strong>' . esc_html($user_name) . '</strong>! Iată ce e nou astăzi.</p>
        </div>

        <!-- Hero article -->
        ' . $hero_html . '

        <!-- Category sections -->
        ' . $sections_html . '

        <!-- YouTube Videos -->
        ' . $this->build_youtube_section($by_category) . '

        <!-- CTA -->
        <div style="background:#ffffff;padding:24px;text-align:center;margin-top:16px;">
            <a href="' . esc_url($frontend_url . '/news') . '" style="display:inline-block;background:#2563eb;color:#ffffff;padding:12px 32px;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;">Explorează toate știrile</a>
        </div>

        <!-- Share -->
        <div style="background:#f8fafc;padding:16px 24px;text-align:center;">
            <p style="margin:0 0 12px;font-size:13px;color:#64748b;">Ti-a plăcut acest digest? Trimite-l unui prieten!</p>
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:collapse;">
                <tr>
                    <td style="padding:0 4px;">
                        <a href="' . $wa_share_url . '" style="display:inline-block;background:#25d366;color:#ffffff;padding:8px 14px;text-decoration:none;border-radius:6px;font-size:12px;font-weight:bold;">WhatsApp</a>
                    </td>
                    <td style="padding:0 4px;">
                        <a href="' . $tg_share_url . '" style="display:inline-block;background:#0088cc;color:#ffffff;padding:8px 14px;text-decoration:none;border-radius:6px;font-size:12px;font-weight:bold;">Telegram</a>
                    </td>
                    <td style="padding:0 4px;">
                        <a href="mailto:?subject=TeInformez%20-%20%C8%98tiri%20personalizate&body=Aboneaz%C4%83-te%20gratuit%3A%20https%3A%2F%2Fteinformez.eu" style="display:inline-block;border:1px solid #cbd5e1;color:#475569;padding:7px 14px;text-decoration:none;border-radius:6px;font-size:12px;font-weight:bold;">Email</a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div style="padding:20px 24px;text-align:center;">
            <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;">
                Primești acest email pentru că ești abonat pe <a href="' . esc_url($frontend_url) . '" style="color:#6b7280;">TeInformez</a>.
            </p>
            <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;">
                <a href="' . $unsubscribe_link . '" style="color:#6b7280;text-decoration:underline;">Modifică preferințele</a> &nbsp;&middot;&nbsp;
                <a href="' . $unsubscribe_link . '" style="color:#6b7280;text-decoration:underline;">Dezabonare</a>
            </p>
            <p style="margin:0;font-size:11px;color:#d1d5db;">&copy; ' . date('Y') . ' TeInformez. Toate drepturile rezervate.</p>
        </div>

    </div>
</body>
</html>';
    }

    /**
     * Build YouTube video section for email.
     * Searches for relevant videos based on top article topics.
     */
    private function build_youtube_section($by_category) {
        $api_key = Config::get_api_key('youtube');
        if (empty($api_key)) {
            return '';
        }

        $max_videos = Config::YOUTUBE_MAX_PER_EMAIL;
        $frontend_url = Config::get('frontend_url', 'https://teinformez.eu');

        // Pick search queries from top categories' first article titles
        $search_queries = [];
        foreach ($by_category as $cat_slug => $items) {
            if (empty($items)) continue;
            $title = $items[0]->processed_title ?? '';
            if (!empty($title)) {
                // Extract key phrase (first 6 words for focused search)
                $words = explode(' ', $title);
                $search_queries[] = implode(' ', array_slice($words, 0, 6));
            }
            if (count($search_queries) >= $max_videos) break;
        }

        if (empty($search_queries)) {
            return '';
        }

        $videos = [];
        foreach ($search_queries as $query) {
            $video = $this->search_youtube($query, $api_key);
            if ($video) {
                $videos[] = $video;
            }
            if (count($videos) >= $max_videos) break;
        }

        if (empty($videos)) {
            return '';
        }

        // Build HTML for video cards
        $html = '
        <div style="padding:0 20px 20px;background:#f9fafb;">
            <div style="margin:8px 0 12px;padding:8px 16px;background:#fee2e2;border-radius:6px;">
                <span style="font-size:14px;font-weight:bold;color:#dc2626;">▶ Video recomandate</span>
            </div>';

        foreach ($videos as $v) {
            $thumb = esc_url($v['thumbnail']);
            $link = esc_url($v['url']);
            $title = esc_html($v['title']);
            if (mb_strlen($title) > 80) {
                $title = mb_substr($title, 0, 77) . '...';
            }
            $channel = esc_html($v['channel']);

            $html .= '
            <div style="margin-bottom:12px;background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                    <tr>
                        <td width="40%" valign="top" style="padding:0;">
                            <a href="' . $link . '" style="display:block;position:relative;">
                                <img src="' . $thumb . '" alt="" width="100%" style="display:block;min-height:90px;object-fit:cover;" />
                            </a>
                        </td>
                        <td width="60%" valign="middle" style="padding:12px 16px;">
                            <h3 style="margin:0 0 4px;font-size:13px;line-height:1.3;color:#111827;">
                                <a href="' . $link . '" style="color:#dc2626;text-decoration:none;">' . $title . '</a>
                            </h3>
                            <p style="margin:0;font-size:10px;color:#9ca3af;">▶ ' . $channel . ' · YouTube</p>
                        </td>
                    </tr>
                </table>
            </div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Search YouTube for a relevant video.
     */
    private function search_youtube($query, $api_key) {
        $url = Config::YOUTUBE_API . '/search?' . http_build_query([
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'maxResults' => 1,
            'order' => 'relevance',
            'relevanceLanguage' => 'ro',
            'publishedAfter' => date('Y-m-d\TH:i:s\Z', strtotime('-7 days')),
            'key' => $api_key,
        ]);

        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            error_log('TeInformez YouTube: Search failed: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['items'][0])) {
            return null;
        }

        $item = $body['items'][0];
        $video_id = $item['id']['videoId'] ?? '';
        if (empty($video_id)) {
            return null;
        }

        return [
            'id' => $video_id,
            'title' => $item['snippet']['title'] ?? '',
            'channel' => $item['snippet']['channelTitle'] ?? '',
            'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? '',
            'url' => 'https://www.youtube.com/watch?v=' . $video_id,
        ];
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
