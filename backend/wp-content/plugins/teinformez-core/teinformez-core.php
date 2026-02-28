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

    // Load news processing classes (Phase B)
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-source-manager.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-fetcher.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-ai-processor.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-news-publisher.php';

    // Load delivery system (Phase C)
    require_once TEINFORMEZ_PLUGIN_DIR . 'includes/class-delivery-handler.php';

    // Load API endpoints
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-rest-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-auth-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-user-api.php';
    require_once TEINFORMEZ_PLUGIN_DIR . 'api/class-news-api.php';

    // Initialize REST API
    new TeInformez\API\REST_API();
    new TeInformez\API\Auth_API();
    new TeInformez\API\User_API();
    new TeInformez\API\News_API();

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
});

add_action('teinformez_daily_cleanup', function() {
    $publisher = new TeInformez\News_Publisher();
    $publisher->cleanup_old_items(30);
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
