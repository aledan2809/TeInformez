<?php
namespace TeInformez\API;

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
}
