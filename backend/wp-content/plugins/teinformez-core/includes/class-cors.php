<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Application-level CORS fallback (defense-in-depth alongside Nginx CORS)
 *
 * Hooks into rest_pre_serve_request to emit CORS headers when the origin
 * is in the allowed list. Handles OPTIONS preflight with a 200 response.
 */
class CORS {

    /**
     * Hardcoded allowed origins (production).
     */
    const TEINFORMEZ_ALLOWED_ORIGINS = [
        'https://teinformez.eu',
        'https://www.teinformez.eu',
    ];

    /**
     * Register the CORS filter on rest_pre_serve_request.
     */
    public static function init() {
        add_filter('rest_pre_serve_request', [__CLASS__, 'handle_cors'], 10, 1);
    }

    /**
     * Get the full list of allowed origins (hardcoded + filterable).
     *
     * @return string[]
     */
    public static function get_allowed_origins() {
        $origins = self::TEINFORMEZ_ALLOWED_ORIGINS;

        /**
         * Filter the list of allowed CORS origins.
         *
         * @param string[] $origins Allowed origin URLs.
         */
        $origins = apply_filters('teinformez_allowed_origins', $origins);

        return array_values(array_unique($origins));
    }

    /**
     * rest_pre_serve_request callback — emit CORS headers if origin matches.
     *
     * @param mixed $value Pass-through value from the filter.
     * @return mixed
     */
    public static function handle_cors($value) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

        if (empty($origin)) {
            return $value;
        }

        $allowed = self::get_allowed_origins();

        if (!in_array($origin, $allowed, true)) {
            return $value;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');

        // Handle OPTIONS preflight — return 200 and stop further processing
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }

        return $value;
    }
}
