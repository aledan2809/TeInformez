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
        if ($exists !== $table) {
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
                is_test TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY created_at (created_at),
                KEY event_type (event_type),
                KEY page_type (page_type),
                KEY page_id (page_id),
                KEY visitor_hash (visitor_hash),
                KEY session_id (session_id),
                KEY is_test (is_test)
            ) {$charset_collate};";

            dbDelta($sql);
            return;
        }

        // Table exists — idempotent ALTER for the is_test column (added 2026-05-28).
        $col = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'is_test'",
            $table
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0, ADD KEY is_test (is_test)");
        }
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
        if (!in_array($page_type, ['news', 'news_list', 'home', 'other'], true)) {
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

        // ---- AN-02: enrich metadata with referrer + UTM + derived source bucket ----
        $metadata_obj = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        $referer = isset($payload['referer']) ? sanitize_text_field((string) $payload['referer']) : '';
        if (strlen($referer) > 500) $referer = substr($referer, 0, 500);
        $utm_source   = isset($payload['utm_source'])   ? sanitize_text_field((string) $payload['utm_source'])   : '';
        $utm_medium   = isset($payload['utm_medium'])   ? sanitize_text_field((string) $payload['utm_medium'])   : '';
        $utm_campaign = isset($payload['utm_campaign']) ? sanitize_text_field((string) $payload['utm_campaign']) : '';
        $utm_term     = isset($payload['utm_term'])     ? sanitize_text_field((string) $payload['utm_term'])     : '';
        $utm_content  = isset($payload['utm_content'])  ? sanitize_text_field((string) $payload['utm_content'])  : '';

        // Only attach if any source signal is present (keep payload lean for legacy events).
        if ($referer !== '' || $utm_source !== '') {
            $metadata_obj['referer'] = $referer;
            if ($utm_source   !== '') $metadata_obj['utm_source']   = $utm_source;
            if ($utm_medium   !== '') $metadata_obj['utm_medium']   = $utm_medium;
            if ($utm_campaign !== '') $metadata_obj['utm_campaign'] = $utm_campaign;
            if ($utm_term     !== '') $metadata_obj['utm_term']     = $utm_term;
            if ($utm_content  !== '') $metadata_obj['utm_content']  = $utm_content;
            $metadata_obj['source_bucket'] = self::derive_source_bucket($referer, $utm_source, $utm_medium);
        }

        $metadata = null;
        if (!empty($metadata_obj)) {
            $metadata_json = wp_json_encode($metadata_obj);
            if (is_string($metadata_json)) {
                $metadata = $metadata_json;
            }
        }

        $visitor_hash = hash('sha256', $visitor_id);
        $user_id_int  = get_current_user_id() ?: null;
        $is_test = ($user_id_int && class_exists('TeInformez\\User_Helper') && \TeInformez\User_Helper::is_test_user((int) $user_id_int)) ? 1 : 0;

        $inserted = $wpdb->insert(self::table_name(), [
            'visitor_hash' => $visitor_hash,
            'session_id' => $session_id,
            'user_id' => $user_id_int,
            'event_type' => $event_type,
            'page_type' => $page_type,
            'page_id' => $page_id,
            'page_path' => $page_path,
            'duration_seconds' => $duration_seconds,
            'metadata' => $metadata,
            'is_test' => $is_test,
            'created_at' => current_time('mysql'),
        ], [
            '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s'
        ]);

        return $inserted !== false;
    }

    /**
     * Bucket a (referer, utm_source, utm_medium) tuple into a coarse-grained
     * traffic source label used by the analytics dashboard's "Top sources"
     * table. UTM signals override referer when present (UTM = explicit
     * intent, referer = inferred). Internal navigation is detected via the
     * site host. Buckets are intentionally coarse so a Top-5 list is
     * actionable rather than fragmented.
     *
     * Returns one of:
     *   organic_google | organic_bing | organic_other |
     *   social_facebook | social_instagram | social_twitter | social_linkedin |
     *   social_youtube | social_reddit | social_other |
     *   email | rss | ad | internal | direct | referral_other
     */
    public static function derive_source_bucket(string $referer, string $utm_source = '', string $utm_medium = ''): string {
        $u_source = strtolower(trim($utm_source));
        $u_medium = strtolower(trim($utm_medium));

        // UTM medium overrides everything (Google Analytics convention)
        if ($u_medium === 'cpc' || $u_medium === 'ppc' || $u_medium === 'paid' || $u_medium === 'cpm') return 'ad';
        if ($u_medium === 'email' || $u_source === 'newsletter' || $u_source === 'email')             return 'email';
        if ($u_medium === 'rss' || $u_source === 'rss')                                               return 'rss';

        // UTM source maps when referer is unhelpful
        $social_utm = [
            'facebook' => 'social_facebook', 'fb' => 'social_facebook', 'meta' => 'social_facebook',
            'instagram' => 'social_instagram', 'ig' => 'social_instagram',
            'twitter' => 'social_twitter', 'x' => 'social_twitter',
            'linkedin' => 'social_linkedin',
            'youtube' => 'social_youtube',
            'reddit' => 'social_reddit',
        ];
        if (isset($social_utm[$u_source])) return $social_utm[$u_source];
        if ($u_source === 'google') return 'organic_google';
        if ($u_source === 'bing')   return 'organic_bing';

        // Referer-based detection
        $r = strtolower($referer);
        if ($r === '' || $r === '-') return 'direct';

        $site_host = strtolower((string) (parse_url(home_url(), PHP_URL_HOST) ?: 'teinformez.eu'));
        $r_host = strtolower((string) (parse_url($r, PHP_URL_HOST) ?: ''));
        if ($r_host === '' || $r_host === $site_host || str_ends_with($r_host, '.' . $site_host)) {
            return 'internal';
        }

        if (preg_match('#(^|\.)google\.[a-z.]+#', $r_host))                           return 'organic_google';
        if (str_contains($r_host, 'bing.com'))                                        return 'organic_bing';
        if (str_contains($r_host, 'duckduckgo.com') || str_contains($r_host, 'yandex')) return 'organic_other';

        if (preg_match('#(facebook\.com|fb\.me|m\.facebook|fb\.com)#', $r_host))      return 'social_facebook';
        if (str_contains($r_host, 'instagram.com'))                                   return 'social_instagram';
        if (preg_match('#(twitter\.com|t\.co|x\.com)#', $r_host))                     return 'social_twitter';
        if (preg_match('#(linkedin\.com|lnkd\.in)#', $r_host))                        return 'social_linkedin';
        if (str_contains($r_host, 'youtube.com') || str_contains($r_host, 'youtu.be')) return 'social_youtube';
        if (str_contains($r_host, 'reddit.com'))                                      return 'social_reddit';
        if (preg_match('#(threads\.net|tiktok\.com|telegram|whatsapp\.com|messenger\.com|discord)#', $r_host)) return 'social_other';

        return 'referral_other';
    }
}
