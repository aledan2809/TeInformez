<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emits TeInformez DB events to MarketingAutomation external webhook for analytics attribution.
 *
 * Runs on a 5-min WP cron and batch-POSTs new events since the last cursor
 * to TEINFORMEZ_MA_WEBHOOK_URL (env var) with X-Webhook-Secret auth.
 * Cursor stored per source in wp_options; idempotency handled upstream by MA.
 *
 * Sources:
 *   users           → TEINFORMEZ_USER_REGISTERED
 *   newsletter      → TEINFORMEZ_NEWSLETTER_SUBSCRIBED
 *   visitor_events  → TEINFORMEZ_ARTICLE_READ / TEINFORMEZ_ARTICLE_SHARED
 */
class MA_Emitter {

    const CRON_HOOK     = 'teinformez_emit_to_ma';
    const CRON_INTERVAL = 'every_5min';
    const BATCH_LIMIT   = 200;

    // -------------------------------------------------------------------------
    // Cron lifecycle
    // -------------------------------------------------------------------------

    public static function register_cron(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), self::CRON_INTERVAL, self::CRON_HOOK);
        }
    }

    public static function deregister_cron(): void {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    // -------------------------------------------------------------------------
    // Main entry point (cron callback)
    // -------------------------------------------------------------------------

    public static function run(): void {
        $webhook_url    = getenv('TEINFORMEZ_MA_WEBHOOK_URL');
        $webhook_secret = getenv('TEINFORMEZ_MA_WEBHOOK_SECRET');

        if (!$webhook_url || !$webhook_secret) {
            error_log('[MA_Emitter] Missing TEINFORMEZ_MA_WEBHOOK_URL or TEINFORMEZ_MA_WEBHOOK_SECRET — skipping.');
            return;
        }

        self::emit_user_registrations($webhook_url, $webhook_secret);
        self::emit_newsletter_subscriptions($webhook_url, $webhook_secret);
        self::emit_article_events($webhook_url, $webhook_secret);
    }

    // -------------------------------------------------------------------------
    // Source: wp_users → TEINFORMEZ_USER_REGISTERED
    // -------------------------------------------------------------------------

    private static function emit_user_registrations(string $url, string $secret): void {
        global $wpdb;

        $cursor = self::get_cursor('users');
        $rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, user_registered FROM {$wpdb->users}
             WHERE user_registered > %s
             ORDER BY user_registered ASC
             LIMIT %d",
            $cursor,
            self::BATCH_LIMIT
        ), ARRAY_A);

        if (empty($rows)) {
            return;
        }

        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'event_type'  => 'TEINFORMEZ_USER_REGISTERED',
                'occurred_at' => self::to_iso($row['user_registered']),
            ];
        }

        $last = end($rows);
        if (self::batch_post($url, $secret, $events)) {
            self::set_cursor('users', $last['user_registered']);
        }
    }

    // -------------------------------------------------------------------------
    // Source: wp_teinformez_newsletter → TEINFORMEZ_NEWSLETTER_SUBSCRIBED
    // -------------------------------------------------------------------------

    private static function emit_newsletter_subscriptions(string $url, string $secret): void {
        global $wpdb;

        $table  = $wpdb->prefix . 'teinformez_newsletter';
        $cursor = self::get_cursor('newsletter');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, confirmed_at, utm_source, utm_medium, utm_campaign
             FROM {$table}
             WHERE confirmed = 1 AND confirmed_at IS NOT NULL AND confirmed_at > %s
             ORDER BY confirmed_at ASC
             LIMIT %d",
            $cursor,
            self::BATCH_LIMIT
        ), ARRAY_A);

        if (empty($rows)) {
            return;
        }

        $events = [];
        foreach ($rows as $row) {
            $event = [
                'event_type'  => 'TEINFORMEZ_NEWSLETTER_SUBSCRIBED',
                'occurred_at' => self::to_iso($row['confirmed_at']),
            ];
            if (!empty($row['utm_source']))   $event['utm_source']   = $row['utm_source'];
            if (!empty($row['utm_medium']))   $event['utm_medium']   = $row['utm_medium'];
            if (!empty($row['utm_campaign'])) $event['utm_campaign'] = $row['utm_campaign'];
            $events[] = $event;
        }

        $last = end($rows);
        if (self::batch_post($url, $secret, $events)) {
            self::set_cursor('newsletter', $last['confirmed_at']);
        }
    }

    // -------------------------------------------------------------------------
    // Source: wp_teinformez_visitor_events → TEINFORMEZ_ARTICLE_READ / _SHARED
    // -------------------------------------------------------------------------

    private static function emit_article_events(string $url, string $secret): void {
        global $wpdb;

        $table  = $wpdb->prefix . 'teinformez_visitor_events';
        $cursor = self::get_cursor('visitor_events');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, metadata, created_at
             FROM {$table}
             WHERE event_type IN ('article_read', 'article_shared') AND created_at > %s
             ORDER BY created_at ASC
             LIMIT %d",
            $cursor,
            self::BATCH_LIMIT
        ), ARRAY_A);

        if (empty($rows)) {
            return;
        }

        $events = [];
        foreach ($rows as $row) {
            $type  = $row['event_type'] === 'article_shared'
                ? 'TEINFORMEZ_ARTICLE_SHARED'
                : 'TEINFORMEZ_ARTICLE_READ';

            $event = [
                'event_type'  => $type,
                'occurred_at' => self::to_iso($row['created_at']),
            ];

            // Extract UTM fields from metadata JSON for attribution tracking
            if (!empty($row['metadata'])) {
                $meta = json_decode($row['metadata'], true);
                if (is_array($meta)) {
                    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm_key) {
                        if (!empty($meta[$utm_key])) {
                            $event[$utm_key] = (string) $meta[$utm_key];
                        }
                    }
                }
            }

            $events[] = $event;
        }

        $last = end($rows);
        if (self::batch_post($url, $secret, $events)) {
            self::set_cursor('visitor_events', $last['created_at']);
        }
    }

    // -------------------------------------------------------------------------
    // HTTP transport
    // -------------------------------------------------------------------------

    private static function batch_post(string $url, string $secret, array $events): bool {
        if (empty($events)) {
            return true;
        }

        $response = wp_remote_post($url, [
            'timeout'  => 15,
            'blocking' => true,
            'headers'  => [
                'Content-Type'     => 'application/json',
                'X-Webhook-Secret' => $secret,
            ],
            'body'     => wp_json_encode(['events' => $events]),
        ]);

        if (is_wp_error($response)) {
            error_log('[MA_Emitter] wp_remote_post error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[MA_Emitter] HTTP ' . $code . ' — ' . wp_remote_retrieve_body($response));
            return false;
        }

        error_log('[MA_Emitter] Sent ' . count($events) . ' events → HTTP ' . $code);
        return true;
    }

    // -------------------------------------------------------------------------
    // Cursor helpers (wp_options, per-source, autoload=false)
    // -------------------------------------------------------------------------

    private static function get_cursor(string $source): string {
        $stored = get_option('teinformez_ma_cursor_' . $source, '');
        if ($stored) {
            return $stored;
        }
        // First run: start 7 days back to capture recent activity
        return date('Y-m-d H:i:s', strtotime('-7 days'));
    }

    private static function set_cursor(string $source, string $datetime): void {
        update_option('teinformez_ma_cursor_' . $source, $datetime, false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Convert MySQL DATETIME (Y-m-d H:i:s, stored UTC) to ISO-8601 UTC string. */
    private static function to_iso(string $mysql_datetime): string {
        return str_replace(' ', 'T', $mysql_datetime) . 'Z';
    }
}
