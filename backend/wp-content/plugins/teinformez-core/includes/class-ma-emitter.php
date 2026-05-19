<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emits TeInformez DB events to MarketingAutomation external webhook for analytics attribution.
 *
 * Runs on a 5-min WP cron and batch-POSTs new events since the last cursor
 * to TEINFORMEZ_MA_WEBHOOK_URL with X-Webhook-Secret auth.
 * Cursor stored per source as last-processed row ID in wp_options; advances only on HTTP 2xx.
 * Drains all pending rows per cron run (loop until empty), capped at MAX_DRAIN_SECONDS wall-clock
 * to stay within safe WP-cron execution limits.
 *
 * Sources:
 *   users           → TEINFORMEZ_USER_REGISTERED
 *   newsletter      → TEINFORMEZ_NEWSLETTER_SUBSCRIBED
 *   visitor_events  → TEINFORMEZ_ARTICLE_READ / TEINFORMEZ_ARTICLE_SHARED
 */
class MA_Emitter {

    const CRON_HOOK         = 'teinformez_emit_to_ma';
    const CRON_INTERVAL     = 'every_5min';
    const BATCH_LIMIT       = 200;
    const MAX_DRAIN_SECONDS = 25;

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
        $webhook_url    = defined('TEINFORMEZ_MA_WEBHOOK_URL')    ? TEINFORMEZ_MA_WEBHOOK_URL    : getenv('TEINFORMEZ_MA_WEBHOOK_URL');
        $webhook_secret = defined('TEINFORMEZ_MA_WEBHOOK_SECRET') ? TEINFORMEZ_MA_WEBHOOK_SECRET : getenv('TEINFORMEZ_MA_WEBHOOK_SECRET');

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

        $deadline = microtime(true) + self::MAX_DRAIN_SECONDS;

        do {
            $cursor = self::get_cursor_id('users');

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, user_email, user_registered FROM {$wpdb->users}
                 WHERE ID > %d
                 ORDER BY ID ASC
                 LIMIT %d",
                $cursor,
                self::BATCH_LIMIT
            ), ARRAY_A);

            if (empty($rows)) {
                break;
            }

            $events = [];
            foreach ($rows as $row) {
                $events[] = [
                    'event_type'  => 'TEINFORMEZ_USER_REGISTERED',
                    'occurred_at' => self::to_iso($row['user_registered']),
                    'user_id'     => (int) $row['ID'],
                    'email'       => $row['user_email'],
                ];
            }

            $last = end($rows);
            if (!self::batch_post($url, $secret, $events)) {
                break;
            }
            self::set_cursor_id('users', (int) $last['ID']);

        } while (count($rows) === self::BATCH_LIMIT && microtime(true) < $deadline);
    }

    // -------------------------------------------------------------------------
    // Source: wp_teinformez_newsletter → TEINFORMEZ_NEWSLETTER_SUBSCRIBED
    // -------------------------------------------------------------------------

    private static function emit_newsletter_subscriptions(string $url, string $secret): void {
        global $wpdb;

        $table    = $wpdb->prefix . 'teinformez_newsletter';
        $deadline = microtime(true) + self::MAX_DRAIN_SECONDS;

        do {
            $cursor = self::get_cursor_id('newsletter');

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, email, confirmed_at, utm_source, utm_medium, utm_campaign
                 FROM {$table}
                 WHERE confirmed = 1 AND confirmed_at IS NOT NULL AND id > %d
                 ORDER BY id ASC
                 LIMIT %d",
                $cursor,
                self::BATCH_LIMIT
            ), ARRAY_A);

            if (empty($rows)) {
                break;
            }

            $events = [];
            foreach ($rows as $row) {
                $event = [
                    'event_type'  => 'TEINFORMEZ_NEWSLETTER_SUBSCRIBED',
                    'occurred_at' => self::to_iso($row['confirmed_at']),
                    'email'       => $row['email'],
                ];
                if (!empty($row['utm_source']))   $event['utm_source']   = $row['utm_source'];
                if (!empty($row['utm_medium']))   $event['utm_medium']   = $row['utm_medium'];
                if (!empty($row['utm_campaign'])) $event['utm_campaign'] = $row['utm_campaign'];
                $events[] = $event;
            }

            $last = end($rows);
            if (!self::batch_post($url, $secret, $events)) {
                break;
            }
            self::set_cursor_id('newsletter', (int) $last['id']);

        } while (count($rows) === self::BATCH_LIMIT && microtime(true) < $deadline);
    }

    // -------------------------------------------------------------------------
    // Source: wp_teinformez_visitor_events → TEINFORMEZ_ARTICLE_READ / _SHARED
    // -------------------------------------------------------------------------

    private static function emit_article_events(string $url, string $secret): void {
        global $wpdb;

        $table    = $wpdb->prefix . 'teinformez_visitor_events';
        $deadline = microtime(true) + self::MAX_DRAIN_SECONDS;

        do {
            $cursor = self::get_cursor_id('visitor_events');

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, event_type, metadata, created_at
                 FROM {$table}
                 WHERE event_type IN ('article_read', 'article_shared') AND id > %d
                 ORDER BY id ASC
                 LIMIT %d",
                $cursor,
                self::BATCH_LIMIT
            ), ARRAY_A);

            if (empty($rows)) {
                break;
            }

            $events = [];
            foreach ($rows as $row) {
                $type = $row['event_type'] === 'article_shared'
                    ? 'TEINFORMEZ_ARTICLE_SHARED'
                    : 'TEINFORMEZ_ARTICLE_READ';

                $event = [
                    'event_type'  => $type,
                    'occurred_at' => self::to_iso($row['created_at']),
                ];

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
            if (!self::batch_post($url, $secret, $events)) {
                break;
            }
            self::set_cursor_id('visitor_events', (int) $last['id']);

        } while (count($rows) === self::BATCH_LIMIT && microtime(true) < $deadline);
    }

    // -------------------------------------------------------------------------
    // HTTP transport
    // -------------------------------------------------------------------------

    private static function batch_post(string $url, string $secret, array $events): bool {
        if (empty($events)) {
            return true;
        }

        $response = wp_safe_remote_post($url, [
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[MA_Emitter] Sent ' . count($events) . ' events → HTTP ' . $code);
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Cursor helpers — ID-based (autoload=false)
    // Stored as integers in wp_options key: teinformez_ma_cursor_id_{source}
    // -------------------------------------------------------------------------

    private static function get_cursor_id(string $source): int {
        $stored = get_option('teinformez_ma_cursor_id_' . $source, false);
        return $stored !== false ? (int) $stored : 0;
    }

    private static function set_cursor_id(string $source, int $id): void {
        update_option('teinformez_ma_cursor_id_' . $source, $id, false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Convert MySQL DATETIME (stored as UTC) to ISO-8601 UTC string. */
    private static function to_iso(string $mysql_datetime): string {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $mysql_datetime, new \DateTimeZone('UTC'));
        return $dt ? $dt->format('Y-m-d\TH:i:s\Z') : $mysql_datetime . 'Z';
    }
}
