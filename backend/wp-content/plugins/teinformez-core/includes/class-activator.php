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
            gdpr_consent_policy_version VARCHAR(10) DEFAULT '1.0',
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

            view_count BIGINT(20) UNSIGNED DEFAULT 0,

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

        // Table: Newsletter Subscribers (lightweight, no WP user needed)
        // Double opt-in: confirmed=0 until user clicks confirmation link
        $table_newsletter = $wpdb->prefix . 'teinformez_newsletter';
        $sql_newsletter = "CREATE TABLE IF NOT EXISTS {$table_newsletter} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL,
            confirmed TINYINT(1) DEFAULT 0,
            confirmed_at DATETIME DEFAULT NULL,
            subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY token (token),
            KEY confirmed (confirmed)
        ) {$charset_collate};";

        // Legacy table kept for backwards compatibility during migration
        $table_newsletter_legacy = $wpdb->prefix . 'teinformez_newsletter_subscribers';
        $sql_newsletter_legacy = "CREATE TABLE IF NOT EXISTS {$table_newsletter_legacy} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            gdpr_consent TINYINT(1) DEFAULT 0,
            gdpr_consent_date DATETIME DEFAULT NULL,
            subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME DEFAULT NULL,
            status ENUM('active', 'unsubscribed') DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) {$charset_collate};";

        // Table: Juridic Q&A
        $table_juridic = $wpdb->prefix . 'teinformez_juridic_qa';
        $sql_juridic = "CREATE TABLE IF NOT EXISTS {$table_juridic} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question TEXT NOT NULL,
            question_anonymized TEXT NOT NULL,
            answer LONGTEXT NOT NULL,
            answer_summary TEXT,
            category VARCHAR(100) NOT NULL,
            subcategory VARCHAR(100) DEFAULT NULL,
            tags TEXT,
            is_weekly_column TINYINT(1) DEFAULT 0,
            column_title VARCHAR(255) DEFAULT NULL,
            column_date DATE DEFAULT NULL,
            author_name VARCHAR(100) DEFAULT 'Alina',
            fb_teaser TEXT,
            fb_post_url VARCHAR(500) DEFAULT NULL,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            view_count BIGINT(20) UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            published_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY category (category),
            KEY is_weekly_column (is_weekly_column),
            KEY column_date (column_date),
            KEY published_at (published_at)
        ) {$charset_collate};";

        // Table: News Archive (same schema as news_queue, for articles > 30 days)
        $table_archive = $wpdb->prefix . 'teinformez_news_archive';
        $sql_archive = "CREATE TABLE IF NOT EXISTS {$table_archive} (
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

            view_count BIGINT(20) UNSIGNED DEFAULT 0,

            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY status (status),
            KEY original_url (original_url(191)),
            KEY published_at (published_at),
            KEY archived_at (archived_at)
        ) {$charset_collate};";

        // Table: Reading History
        $table_reading_history = $wpdb->prefix . 'teinformez_reading_history';
        $sql_reading_history = "CREATE TABLE IF NOT EXISTS {$table_reading_history} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            news_id BIGINT(20) UNSIGNED NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            time_spent INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_reading (user_id, news_id),
            KEY user_id (user_id),
            KEY news_id (news_id),
            KEY read_at (read_at)
        ) {$charset_collate};";

        // Table: Bookmarks
        $table_bookmarks = $wpdb->prefix . 'teinformez_bookmarks';
        $sql_bookmarks = "CREATE TABLE IF NOT EXISTS {$table_bookmarks} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            news_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_bookmark (user_id, news_id),
            KEY user_id (user_id),
            KEY news_id (news_id)
        ) {$charset_collate};";

        // Table: Visitor Analytics Events
        $table_visitor_events = $wpdb->prefix . 'teinformez_visitor_events';
        $sql_visitor_events = "CREATE TABLE IF NOT EXISTS {$table_visitor_events} (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_preferences);
        dbDelta($sql_subscriptions);
        dbDelta($sql_news);
        dbDelta($sql_delivery);
        dbDelta($sql_newsletter);
        dbDelta($sql_newsletter_legacy);
        dbDelta($sql_juridic);
        dbDelta($sql_archive);
        dbDelta($sql_reading_history);
        dbDelta($sql_bookmarks);
        dbDelta($sql_visitor_events);

        // Run migrations for existing installations
        self::run_migrations();

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
     * Run database migrations for existing installations
     */
    private static function run_migrations() {
        global $wpdb;

        $table = $wpdb->prefix . 'teinformez_user_preferences';

        // Add gdpr_consent_policy_version column if it does not exist
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table,
            'gdpr_consent_policy_version'
        ));

        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN gdpr_consent_policy_version VARCHAR(10) DEFAULT '1.0' AFTER gdpr_ip_address");
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

        // Check delivery health every 15 minutes
        if (!wp_next_scheduled('teinformez_check_delivery_health')) {
            wp_schedule_event(time(), 'every_15_minutes', 'teinformez_check_delivery_health');
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
