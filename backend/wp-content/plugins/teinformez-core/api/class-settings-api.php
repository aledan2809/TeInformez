<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_API extends REST_API {

    public function register_routes() {
        // Get category order (public)
        register_rest_route($this->namespace, '/settings/category-order', [
            'methods' => 'GET',
            'callback' => [$this, 'get_category_order'],
            'permission_callback' => '__return_true',
        ]);

        // Update category order (admin only)
        register_rest_route($this->namespace, '/settings/category-order', [
            'methods' => 'POST',
            'callback' => [$this, 'update_category_order'],
            'permission_callback' => [$this, 'is_admin_user'],
        ]);

        // Delivery health stats (admin only)
        register_rest_route($this->namespace, '/admin/delivery-health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_delivery_health'],
            'permission_callback' => [$this, 'is_admin_user'],
        ]);
    }

    public function is_admin_user($request) {
        if (!$this->is_authenticated($request)) {
            return false;
        }
        $user_id = $this->get_current_user_id();
        return user_can($user_id, 'manage_options');
    }

    public function get_category_order($request) {
        $order = get_option('teinformez_category_order', []);
        return $this->success(['order' => $order]);
    }

    public function get_delivery_health($request) {
        $handler = new \TeInformez\Delivery_Handler();
        $stats = $handler->get_delivery_stats();

        return $this->success($stats);
    }

    public function update_category_order($request) {
        $body = $request->get_json_params();
        $order = isset($body['order']) ? $body['order'] : null;

        if (!is_array($order)) {
            return $this->error('Order must be an array of category slugs', 'invalid_order', 400);
        }

        // Sanitize slugs
        $order = array_map('sanitize_title', $order);

        update_option('teinformez_category_order', $order);
        return $this->success(['order' => $order], 'Category order updated');
    }
}
