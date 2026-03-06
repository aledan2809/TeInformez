<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Visitor analytics helper
 */
class Visitor_Analytics {
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'teinformez_visitor_events';
    }

    public static function create_table_if_missing(): void {
        global $wpdb;

        $table = self::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            visitor_hash CHAR(64) NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            event_type VARCHAR(40) NOT NULL,
            page_type VARCHAR(40) DEFAULT '',
            page_id BIGINT(20) UNSIGNED DEFAULT NULL,
            page_path VARCHAR(255) DEFAULT '',
            duration_seconds INT UNSIGNED DEFAULT 0,
            metadata LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY event_type (event_type),
            KEY page_type (page_type),
            KEY page_id (page_id),
            KEY visitor_hash (visitor_hash),
            KEY session_id (session_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function track_event(array $payload): bool {
        global $wpdb;

        self::create_table_if_missing();

        $visitor_id = sanitize_text_field((string) ($payload['visitor_id'] ?? ''));
        $session_id = sanitize_text_field((string) ($payload['session_id'] ?? ''));
        if ($visitor_id === '' || $session_id === '') {
            return false;
        }

        $event_type = sanitize_key((string) ($payload['event_type'] ?? 'page_view'));
        $allowed_events = ['page_view', 'article_click', 'time_spent', 'newsletter_subscribe'];
        if (!in_array($event_type, $allowed_events, true)) {
            $event_type = 'page_view';
        }

        $page_type = sanitize_key((string) ($payload['page_type'] ?? ''));
        if (!in_array($page_type, ['news', 'juridic', 'news_list', 'juridic_list', 'home', 'other'], true)) {
            $page_type = 'other';
        }

        $page_id = isset($payload['page_id']) ? (int) $payload['page_id'] : null;
        if ($page_id !== null && $page_id <= 0) {
            $page_id = null;
        }

        $duration_seconds = isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : 0;
        $duration_seconds = max(0, min($duration_seconds, 86400));

        $page_path = sanitize_text_field((string) ($payload['page_path'] ?? ''));
        if (strlen($page_path) > 255) {
            $page_path = substr($page_path, 0, 255);
        }

        $metadata = null;
        if (isset($payload['metadata'])) {
            $metadata_json = wp_json_encode($payload['metadata']);
            if (is_string($metadata_json)) {
                $metadata = $metadata_json;
            }
        }

        $visitor_hash = hash('sha256', $visitor_id);

        $inserted = $wpdb->insert(self::table_name(), [
            'visitor_hash' => $visitor_hash,
            'session_id' => $session_id,
            'user_id' => get_current_user_id() ?: null,
            'event_type' => $event_type,
            'page_type' => $page_type,
            'page_id' => $page_id,
            'page_path' => $page_path,
            'duration_seconds' => $duration_seconds,
            'metadata' => $metadata,
            'created_at' => current_time('mysql'),
        ], [
            '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s'
        ]);

        return $inserted !== false;
    }
}
