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

// Add CORS headers for headless
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed_origins = TeInformez\Config::get_allowed_origins();

        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        }

        return $value;
    });
});
