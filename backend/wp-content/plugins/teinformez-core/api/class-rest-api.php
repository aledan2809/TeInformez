<?php
namespace TeInformez\API;

use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base REST API class
 */
class REST_API {

    protected $namespace = 'teinformez/v1';
    protected static $authenticated_user_id = null;

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Override in child classes
    }

    /**
     * Check if authenticated user has administrator capability (H-04/H-05/H-06)
     */
    public function is_admin($request) {
        if (!$this->is_authenticated($request)) {
            return new \WP_Error('rest_not_logged_in', __('Authentication required.', 'teinformez'), ['status' => 401]);
        }
        if (!current_user_can('manage_options')) {
            return new \WP_Error('rest_forbidden', __('Administrator access required.', 'teinformez'), ['status' => 403]);
        }
        return true;
    }

    /**
     * Check if user is authenticated via Bearer token or WordPress session
     */
    public function is_authenticated($request) {
        // First check WordPress session (cookie-based auth)
        if (is_user_logged_in()) {
            return true;
        }

        // Then check Bearer token from Authorization header
        $auth_header = $request->get_header('Authorization');
        if (!$auth_header) {
            return false;
        }

        // Extract token from "Bearer <token>"
        if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
            $token = $matches[1];

            // Try to authenticate with the token
            $user_id = $this->validate_token($token);
            if ($user_id) {
                wp_set_current_user($user_id);
                self::$authenticated_user_id = $user_id;
                return true;
            }
        }

        return false;
    }

    /**
     * Validate authentication token
     * Returns user_id if valid, false otherwise
     */
    protected function validate_token($token) {
        // Use the static method from Auth_API
        return Auth_API::validate_auth_token($token);
    }

    /**
     * Get current user ID
     */
    public function get_current_user_id() {
        if (self::$authenticated_user_id) {
            return self::$authenticated_user_id;
        }
        return get_current_user_id();
    }

    /**
     * Success response
     */
    protected function success($data = [], $message = '', $status = 200) {
        return new \WP_REST_Response([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Error response
     */
    protected function error($message = '', $code = 'error', $status = 400, $data = []) {
        return new \WP_Error($code, $message, [
            'status' => $status,
            'data' => $data
        ]);
    }

    /**
     * Validate required fields
     */
    protected function validate_required($data, $required_fields) {
        $missing = [];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->error(
                sprintf(__('Missing required fields: %s', 'teinformez'), implode(', ', $missing)),
                'missing_fields',
                400,
                ['missing_fields' => $missing]
            );
        }

        return true;
    }

    /**
     * Validate password strength (M-02)
     * Returns true on success, WP_Error on failure.
     */
    protected function validate_password_strength(string $password) {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = __('minimum 8 characters', 'teinformez');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = __('at least one uppercase letter', 'teinformez');
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = __('at least one lowercase letter', 'teinformez');
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = __('at least one number', 'teinformez');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = __('at least one special character', 'teinformez');
        }
        if (!empty($errors)) {
            return $this->error(
                sprintf(__('Password must contain: %s.', 'teinformez'), implode(', ', $errors)),
                'weak_password',
                400
            );
        }
        return true;
    }

    /**
     * Sanitize array recursively
     */
    protected function sanitize_array($array) {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->sanitize_array($value);
            } else {
                $value = sanitize_text_field($value);
            }
        }
        return $array;
    }

    /**
     * Transient-based per-IP rate limiter (M-04).
     * Sliding window: max $max requests per $window_minutes minutes per IP.
     * Returns WP_Error on limit exceeded, null when OK.
     */
    protected function check_rate_limit(string $action, int $max = 5, int $window_minutes = 15): ?\WP_Error {
        $ip  = Config::get_client_ip() ?: 'unknown';
        $key = 'teinformez_rl_' . $action . '_' . md5($ip);

        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, ['count' => 1, 'start' => time()], $window_minutes * MINUTE_IN_SECONDS);
            return null;
        }

        if ((int) $data['count'] >= $max) {
            return new \WP_REST_Response(
                ['code' => 'rate_limit_exceeded', 'message' => __('Too many attempts. Please try again later.', 'teinformez')],
                429,
                ['Retry-After' => (string) ($window_minutes * 60)]
            );
        }

        $remaining = max(1, $window_minutes * MINUTE_IN_SECONDS - (time() - (int) $data['start']));
        set_transient($key, ['count' => $data['count'] + 1, 'start' => $data['start']], $remaining);

        return null;
    }

    /**
     * Clear a rate limit bucket for the current IP.
     */
    protected function clear_rate_limit(string $action): void {
        $ip  = Config::get_client_ip() ?: 'unknown';
        delete_transient('teinformez_rl_' . $action . '_' . md5($ip));
    }

    /**
     * CSRF guard for cookie-authenticated requests (M-14).
     * Bearer-authenticated requests are inherently CSRF-safe — skipped.
     * Cookie-authenticated requests must supply a valid WP nonce via X-WP-Nonce header.
     */
    protected function check_cookie_csrf(\WP_REST_Request $request): ?\WP_REST_Response {
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && preg_match('/\bBearer\s+\S+/i', $auth_header)) {
            return null; // Bearer token — CSRF not applicable
        }

        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return null; // Valid nonce
        }

        return new \WP_REST_Response(
            ['code' => 'csrf_verification_failed', 'message' => __('CSRF verification failed.', 'teinformez')],
            403
        );
    }
}
