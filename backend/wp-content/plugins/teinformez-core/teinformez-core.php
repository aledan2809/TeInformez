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
define('TEINFORMEZ_VERSION', '1.0.0');
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
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-visitor-analytics.php';

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

    // Initialize REST API
    new TeInformez\API\REST_API();
    new TeInformez\API\Auth_API();
    new TeInformez\API\User_API();
    new TeInformez\API\News_API();
    new TeInformez\API\Juridic_API();
    new TeInformez\API\Telegram_API();
    new TeInformez\API\Settings_API();
    new TeInformez\API\Analytics_API();

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

// Add CORS headers for headless
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();

        // Use the new wildcard-aware origin checker
        if (TeInformez\Config::is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        }

        return $value;
    });
});
