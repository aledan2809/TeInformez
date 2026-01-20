<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation handler
 * Creates database tables and sets default options
 */
class Activator {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table: User Preferences
        $table_preferences = $wpdb->prefix . 'teinformez_user_preferences';
        $sql_preferences = "CREATE TABLE IF NOT EXISTS {$table_preferences} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            preferred_language VARCHAR(5) DEFAULT 'ro',
            delivery_channels TEXT,
            delivery_schedule TEXT,
            gdpr_consent TINYINT(1) DEFAULT 0,
            gdpr_consent_date DATETIME DEFAULT NULL,
            gdpr_ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY gdpr_consent (gdpr_consent)
        ) {$charset_collate};";

        // Table: Subscriptions
        $table_subscriptions = $wpdb->prefix . 'teinformez_subscriptions';
        $sql_subscriptions = "CREATE TABLE IF NOT EXISTS {$table_subscriptions} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            category_slug VARCHAR(100),
            topic_keyword VARCHAR(255),
            country_filter VARCHAR(100),
            source_filter TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY category_slug (category_slug),
            KEY is_active (is_active)
        ) {$charset_collate};";

        // Table: News Queue
        $table_news = $wpdb->prefix . 'teinformez_news_queue';
        $sql_news = "CREATE TABLE IF NOT EXISTS {$table_news} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            original_url VARCHAR(500),
            original_title TEXT,
            original_content LONGTEXT,
            original_language VARCHAR(5),
            source_name VARCHAR(100),
            source_type ENUM('rss', 'api', 'scraper') DEFAULT 'rss',

            processed_title TEXT,
            processed_summary TEXT,
            processed_content TEXT,
            target_language VARCHAR(5),
            ai_generated_image_url VARCHAR(500),
            youtube_embed VARCHAR(500),

            status ENUM('fetched', 'processing', 'pending_review', 'approved', 'rejected', 'published') DEFAULT 'fetched',
            admin_notes TEXT,

            categories TEXT,
            tags TEXT,

            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,

            PRIMARY KEY (id),
            KEY status (status),
            KEY source_type (source_type),
            KEY original_url (original_url(191)),
            KEY fetched_at (fetched_at)
        ) {$charset_collate};";

        // Table: Delivery Log
        $table_delivery = $wpdb->prefix . 'teinformez_delivery_log';
        $sql_delivery = "CREATE TABLE IF NOT EXISTS {$table_delivery} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            news_id BIGINT(20) UNSIGNED NOT NULL,
            channel ENUM('email', 'facebook_post', 'twitter_post', 'instagram_post') DEFAULT 'email',
            status ENUM('pending', 'sent', 'failed', 'opened', 'clicked') DEFAULT 'pending',
            scheduled_for DATETIME DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            error_message TEXT,
            metadata TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY news_id (news_id),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_preferences);
        dbDelta($sql_subscriptions);
        dbDelta($sql_news);
        dbDelta($sql_delivery);

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Set activation flag
        update_option('teinformez_version', TEINFORMEZ_VERSION);
        update_option('teinformez_activated_at', current_time('mysql'));

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'site_language' => Config::SITE_LANGUAGE,
            'site_country' => Config::SITE_COUNTRY,
            'site_timezone' => Config::SITE_TIMEZONE,
            'available_languages' => Config::AVAILABLE_LANGUAGES,
            'news_fetch_interval' => Config::NEWS_FETCH_INTERVAL,
            'admin_review_period' => Config::ADMIN_REVIEW_PERIOD,
            'max_summary_length' => Config::MAX_SUMMARY_LENGTH,
            'max_social_snippet_length' => Config::MAX_SOCIAL_SNIPPET_LENGTH,
            'email_provider' => Config::EMAIL_PROVIDER,
            'email_from_name' => Config::EMAIL_FROM_NAME,
            'email_from_address' => Config::EMAIL_FROM_ADDRESS,
            'categories' => Config::DEFAULT_CATEGORIES,
        ];

        foreach ($defaults as $key => $value) {
            if (false === get_option('teinformez_' . $key)) {
                add_option('teinformez_' . $key, $value);
            }
        }
    }

    /**
     * Schedule WordPress cron jobs
     */
    private static function schedule_cron_jobs() {
        // Fetch news every 30 minutes
        if (!wp_next_scheduled('teinformez_fetch_news')) {
            wp_schedule_event(time(), 'every_30_minutes', 'teinformez_fetch_news');
        }

        // Process news with AI every 30 minutes
        if (!wp_next_scheduled('teinformez_process_news')) {
            wp_schedule_event(time(), 'every_30_minutes', 'teinformez_process_news');
        }

        // Check delivery queue every 15 minutes
        if (!wp_next_scheduled('teinformez_check_deliveries')) {
            wp_schedule_event(time(), 'every_15_minutes', 'teinformez_check_deliveries');
        }

        // Daily cleanup of old data
        if (!wp_next_scheduled('teinformez_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'teinformez_daily_cleanup');
        }
    }
}

// Add custom cron intervals
add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display' => __('Every 15 minutes', 'teinformez')
    ];
    $schedules['every_30_minutes'] = [
        'interval' => 1800,
        'display' => __('Every 30 minutes', 'teinformez')
    ];
    return $schedules;
});
