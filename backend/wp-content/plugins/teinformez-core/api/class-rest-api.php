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

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Override in child classes
    }

    /**
     * Check if user is authenticated
     */
    public function is_authenticated($request) {
        return is_user_logged_in();
    }

    /**
     * Get current user ID
     */
    public function get_current_user_id() {
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
