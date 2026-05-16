<?php
/**
 * Plugin Name: TeInformez Core
 * Plugin URI: https://teinformez.eu
 * Description: Headless WordPress backend for AI-powered personalized news platform
 * Version: 1.0.0
 * Author: TeInformez Team
 * Author URI: https://teinformez.eu
 * License: GPL v2 or later
 * Text Domain: teinformez
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('TEINFORMEZ_VERSION', '1.1.0');
define('TEINFORMEZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEINFORMEZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TEINFORMEZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'TeInformez\\';
    $base_dir = TEINFORMEZ_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('\\', '/', strtolower(str_replace('_', '-', $relative_class))) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-activator.php';
    TeInformez\Activator::activate();
});

// Run DB migrations when plugin version changes (for already-activated installs)
add_action('admin_init', function() {
    if (get_option('teinformez_db_version') !== TEINFORMEZ_VERSION) {
        require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-activator.php';
        TeInformez\Activator::run_migrations();
        update_option('teinformez_db_version', TEINFORMEZ_VERSION);
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-deactivator.php';
    TeInformez\Deactivator::deactivate();
});

// Initialize plugin
function teinformez_init() {
    // Load configuration
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-config.php';

    // Load core classes
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-database.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-user-manager.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-subscription-manager.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-gdpr-handler.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-email-sender.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-ma-client.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-visitor-analytics.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-cas-telemetry.php';

    // Load news processing classes (Phase B)
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-source-manager.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-fetcher.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-ai-processor.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-publisher.php';

    // Load Chief Editor AI Agent
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-chief-editor.php';

    // Load delivery system (Phase C)
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-delivery-handler.php';

    // Load social media posting (Phase E)
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-social-poster.php';

    // Load API endpoints
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-rest-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-auth-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-user-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-news-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-juridic-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-telegram-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-settings-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-analytics-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-cas-api.php';
    // Initialize REST API
    new TeInformez\API\REST_API();
    new TeInformez\API\Auth_API();
    new TeInformez\API\User_API();
    new TeInformez\API\News_API();
    new TeInformez\API\Juridic_API();
    new TeInformez\API\Telegram_API();
    new TeInformez\API\Settings_API();
    new TeInformez\API\Analytics_API();
    new TeInformez\API\CAS_API();

    // Auto-merge new categories into stored option
    $current_cats = get_option('teinformez_categories', []);
    $default_cats = TeInformez\Config::DEFAULT_CATEGORIES;
    $merged = array_merge($default_cats, $current_cats);
    if (count($merged) !== count($current_cats)) {
        update_option('teinformez_categories', $merged);
    }

    // Load admin if in admin area
    if (is_admin()) {
        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/class-admin.php';
        new TeInformez\Admin\Admin();
    }
}
add_action('plugins_loaded', 'teinformez_init');

// Cron job handlers (Phase B - News Aggregation)
add_action('teinformez_fetch_news', function() {
    $fetcher = new TeInformez\News_Fetcher();
    $fetcher->fetch_all();
});

add_action('teinformez_process_news', function() {
    $processor = new TeInformez\AI_Processor();
    $processor->process_queue();

    // Also check for auto-publish
    $publisher = new TeInformez\News_Publisher();
    $publisher->auto_publish_expired();
    $publisher->publish_approved();
});

add_action('teinformez_check_deliveries', function() {
    $handler = new TeInformez\Delivery_Handler();
    $handler->process_deliveries();

    // Retry failed social media posts
    $social = new TeInformez\Social_Poster();
    $social->retry_failed_posts();
});

// Chief Editor AI Agent: review article when it reaches pending_review
add_action('teinformez_article_pending_review', function($article_id) {
    if (TeInformez\Chief_Editor::is_enabled()) {
        $editor = new TeInformez\Chief_Editor();
        $editor->review_and_publish($article_id);
    }
});

// Social media posting: auto-post when news is published (Phase E)
add_action('teinformez_news_published', function($item) {
    $social = new TeInformez\Social_Poster();
    $social->post_on_publish($item);
});

// Cache invalidation: bust news + homepage transients when a new article is published
add_action('teinformez_news_published', function() {
    delete_transient('teinformez_homepage_data');
    // Invalidate common per-page variants
    foreach ([10, 20, 50] as $pp) {
        delete_transient('teinformez_news_p1_pp' . $pp);
    }
}, 20);

// Social media posting: auto-post when juridic Q&A is published
add_action('teinformez_juridic_published', function($item) {
    $social = new TeInformez\Social_Poster();
    $social->post_juridic_on_demand($item, ['facebook', 'twitter', 'instagram']);
});

add_action('teinformez_check_delivery_health', function() {
    $handler = new TeInformez\Delivery_Handler();
    $handler->check_delivery_health();
});

add_action('teinformez_daily_cleanup', function() {
    $publisher = new TeInformez\News_Publisher();
    $publisher->cleanup_old_items(30);
});

// GDPR consent retention cleanup — delete records older than 7 years
add_action('teinformez_gdpr_retention_cleanup', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'teinformez_user_preferences';
    $wpdb->query("DELETE FROM {$table} WHERE gdpr_consent_date IS NOT NULL AND gdpr_consent_date < DATE_SUB(NOW(), INTERVAL 7 YEAR)");
});

// D+1 re-engagement: fires 24h after registration, sends TOP 3 articles if user hasn't read any
add_action('teinformez_welcome_d1_email', function($user_id) {
    global $wpdb;

    $user = get_user_by('id', $user_id);
    if (!$user) {
        return;
    }

    // Skip if user already clicked any article in the past 48h
    $events_table = $wpdb->prefix . 'teinformez_visitor_events';
    $engaged = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE user_id = %d AND event_type = 'article_click' AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)",
        $user_id
    ));

    if ($engaged > 0) {
        error_log('TeInformez D+1: user ' . $user_id . ' already engaged, skipping re-engagement email.');
        return;
    }

    // Get user's subscribed categories
    $subscription_manager = new TeInformez\Subscription_Manager();
    $subscriptions = $subscription_manager->get_user_subscriptions($user_id);
    $subscribed_categories = [];
    foreach ($subscriptions as $sub) {
        if (!empty($sub['category_slug'])) {
            $subscribed_categories[] = $sub['category_slug'];
        }
    }

    // Fetch up to 50 recent published articles (last 24h) to filter from
    $news_table = $wpdb->prefix . 'teinformez_news_queue';
    $candidates = $wpdb->get_results(
        "SELECT id, processed_title, processed_summary, source_name, categories
         FROM {$news_table}
         WHERE status = 'published'
           AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY view_count DESC, published_at DESC
         LIMIT 50",
        ARRAY_A
    );

    // Filter by subscribed categories; fallback to all if no matches or no subscriptions
    $top3 = [];
    if (!empty($subscribed_categories) && !empty($candidates)) {
        foreach ($candidates as $row) {
            $cats = json_decode($row['categories'] ?? '[]', true);
            if (count(array_intersect((array) $cats, $subscribed_categories)) > 0) {
                $top3[] = $row;
                if (count($top3) >= 3) break;
            }
        }
    }
    // Fill remainder with latest articles regardless of category
    if (count($top3) < 3 && !empty($candidates)) {
        $used_ids = array_column($top3, 'id');
        foreach ($candidates as $row) {
            if (!in_array($row['id'], $used_ids, true)) {
                $top3[] = $row;
                if (count($top3) >= 3) break;
            }
        }
    }

    $email_sender = new TeInformez\Email_Sender();
    $display_name = $user->display_name ?: $user->user_login;
    $email_sender->send_d1_reengagement($user->user_email, $display_name, $top3);
    error_log('TeInformez D+1: re-engagement email sent to user ' . $user_id . ' with ' . count($top3) . ' articles.');
});

// Custom cron intervals (must be registered early, before scheduling)
add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display' => 'Every 15 minutes'
    ];
    $schedules['every_30_minutes'] = [
        'interval' => 1800,
        'display' => 'Every 30 minutes'
    ];
    return $schedules;
});

// Add CORS headers for headless (application-level fallback — I-02)
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-cors.php';

    // Merge Config origins into the CORS class via filter
    add_filter('teinformez_allowed_origins', function($origins) {
        return array_merge($origins, TeInformez\Config::get_allowed_origins());
    });

    TeInformez\CORS::init();
});
