<?php
namespace TeInformez\API;

use TeInformez\User_Manager;
use TeInformez\Subscription_Manager;
use TeInformez\GDPR_Handler;
use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User preferences and subscriptions API
 */
class User_API extends REST_API {

    public function register_routes() {
        // Get preferences
        register_rest_route($this->namespace, '/user/preferences', [
            'methods' => 'GET',
            'callback' => [$this, 'get_preferences'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Update preferences
        register_rest_route($this->namespace, '/user/preferences', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_preferences'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Get subscriptions
        register_rest_route($this->namespace, '/user/subscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subscriptions'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Add subscription
        register_rest_route($this->namespace, '/user/subscriptions', [
            'methods' => 'POST',
            'callback' => [$this, 'add_subscription'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Bulk add subscriptions (onboarding)
        register_rest_route($this->namespace, '/user/subscriptions/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_add_subscriptions'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Update subscription
        register_rest_route($this->namespace, '/user/subscriptions/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_subscription'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Delete subscription
        register_rest_route($this->namespace, '/user/subscriptions/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_subscription'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Toggle subscription
        register_rest_route($this->namespace, '/user/subscriptions/(?P<id>\d+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_subscription'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Get subscription stats
        register_rest_route($this->namespace, '/user/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Export user data (GDPR)
        register_rest_route($this->namespace, '/user/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_data'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Delete account (GDPR)
        register_rest_route($this->namespace, '/user/delete', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_account'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Get available categories
        register_rest_route($this->namespace, '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Get user preferences
     */
    public function get_preferences($request) {
        $user_id = $this->get_current_user_id();
        $user_manager = new User_Manager();
        $preferences = $user_manager->get_user_preferences($user_id);

        return $this->success(['preferences' => $preferences]);
    }

    /**
     * Update user preferences
     */
    public function update_preferences($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $user_manager = new User_Manager();
        $result = $user_manager->update_preferences($user_id, $params);

        if ($result === false) {
            return $this->error(__('Failed to update preferences.', 'teinformez'), 'update_failed', 500);
        }

        $updated = $user_manager->get_user_preferences($user_id);

        return $this->success([
            'preferences' => $updated
        ], __('Preferences updated successfully.', 'teinformez'));
    }

    /**
     * Get user subscriptions
     */
    public function get_subscriptions($request) {
        $user_id = $this->get_current_user_id();
        $sub_manager = new Subscription_Manager();
        $subscriptions = $sub_manager->get_user_subscriptions($user_id);

        return $this->success(['subscriptions' => $subscriptions]);
    }

    /**
     * Add subscription
     */
    public function add_subscription($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $sub_manager = new Subscription_Manager();
        $subscription_id = $sub_manager->add_subscription($user_id, $params);

        if (!$subscription_id) {
            return $this->error(__('Failed to add subscription.', 'teinformez'), 'add_failed', 500);
        }

        return $this->success([
            'subscription_id' => $subscription_id
        ], __('Subscription added successfully.', 'teinformez'), 201);
    }

    /**
     * Bulk add subscriptions (onboarding)
     */
    public function bulk_add_subscriptions($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        if (empty($params['subscriptions']) || !is_array($params['subscriptions'])) {
            return $this->error(__('Invalid subscriptions data.', 'teinformez'), 'invalid_data', 400);
        }

        $sub_manager = new Subscription_Manager();
        $result = $sub_manager->bulk_add_subscriptions($user_id, $params['subscriptions']);

        if ($result === false) {
            return $this->error(__('Failed to add subscriptions.', 'teinformez'), 'bulk_add_failed', 500);
        }

        return $this->success([
            'count' => count($params['subscriptions'])
        ], __('Subscriptions added successfully.', 'teinformez'), 201);
    }

    /**
     * Update subscription
     */
    public function update_subscription($request) {
        $user_id = $this->get_current_user_id();
        $subscription_id = $request->get_param('id');
        $params = $request->get_json_params();

        $sub_manager = new Subscription_Manager();
        $result = $sub_manager->update_subscription($subscription_id, $params);

        if ($result === false) {
            return $this->error(__('Failed to update subscription.', 'teinformez'), 'update_failed', 500);
        }

        return $this->success([], __('Subscription updated successfully.', 'teinformez'));
    }

    /**
     * Delete subscription
     */
    public function delete_subscription($request) {
        $user_id = $this->get_current_user_id();
        $subscription_id = $request->get_param('id');

        $sub_manager = new Subscription_Manager();
        $result = $sub_manager->delete_subscription($subscription_id, $user_id);

        if ($result === false) {
            return $this->error(__('Failed to delete subscription.', 'teinformez'), 'delete_failed', 500);
        }

        return $this->success([], __('Subscription deleted successfully.', 'teinformez'));
    }

    /**
     * Toggle subscription active status
     */
    public function toggle_subscription($request) {
        $user_id = $this->get_current_user_id();
        $subscription_id = $request->get_param('id');

        $sub_manager = new Subscription_Manager();
        $result = $sub_manager->toggle_subscription($subscription_id, $user_id);

        if ($result === false) {
            return $this->error(__('Failed to toggle subscription.', 'teinformez'), 'toggle_failed', 500);
        }

        return $this->success([], __('Subscription toggled successfully.', 'teinformez'));
    }

    /**
     * Get user statistics
     */
    public function get_stats($request) {
        $user_id = $this->get_current_user_id();
        $sub_manager = new Subscription_Manager();
        $stats = $sub_manager->get_user_stats($user_id);

        return $this->success(['stats' => $stats]);
    }

    /**
     * Export user data (GDPR)
     */
    public function export_data($request) {
        $user_id = $this->get_current_user_id();
        $gdpr = new GDPR_Handler();
        $data = $gdpr->export_user_data($user_id);

        return $this->success(['data' => $data]);
    }

    /**
     * Delete account (GDPR right to be forgotten)
     */
    public function delete_account($request) {
        $user_id = $this->get_current_user_id();

        // Anonymize data
        $gdpr = new GDPR_Handler();
        $gdpr->anonymize_user($user_id);

        // Delete WordPress user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $result = wp_delete_user($user_id);

        if (!$result) {
            return $this->error(__('Failed to delete account.', 'teinformez'), 'delete_failed', 500);
        }

        // Logout
        wp_logout();

        return $this->success([], __('Account deleted successfully.', 'teinformez'));
    }

    /**
     * Get available categories
     */
    public function get_categories($request) {
        $categories = Config::get('categories', Config::DEFAULT_CATEGORIES);

        return $this->success(['categories' => $categories]);
    }
}
