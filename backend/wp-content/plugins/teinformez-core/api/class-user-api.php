<?php
namespace TeInformez\API;

use TeInformez\User_Manager;
use TeInformez\Subscription_Manager;
use TeInformez\Delivery_Handler;
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

        // Change password
        register_rest_route($this->namespace, '/user/change-password', [
            'methods' => 'POST',
            'callback' => [$this, 'change_password'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Change email
        register_rest_route($this->namespace, '/user/change-email', [
            'methods' => 'POST',
            'callback' => [$this, 'change_email'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Get delivery history
        register_rest_route($this->namespace, '/user/deliveries', [
            'methods' => 'GET',
            'callback' => [$this, 'get_deliveries'],
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
        // Get ALL subscriptions (not just active ones) for dashboard display
        $subscriptions = $sub_manager->get_user_subscriptions($user_id, false);

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
     * Change password (requires current password)
     */
    public function change_password($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['current_password', 'new_password']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $user = get_userdata($user_id);
        if (!wp_check_password($params['current_password'], $user->user_pass, $user_id)) {
            return $this->error(__('Current password is incorrect.', 'teinformez'), 'wrong_password', 400);
        }

        $new_password = $params['new_password'];
        if (strlen($new_password) < 8) {
            return $this->error(__('New password must be at least 8 characters.', 'teinformez'), 'weak_password', 400);
        }

        wp_set_password($new_password, $user_id);

        // Re-authenticate so current session stays valid
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return $this->success([], __('Password changed successfully.', 'teinformez'));
    }

    /**
     * Change email (requires current password for verification)
     */
    public function change_email($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['new_email', 'password']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $user = get_userdata($user_id);
        if (!wp_check_password($params['password'], $user->user_pass, $user_id)) {
            return $this->error(__('Password is incorrect.', 'teinformez'), 'wrong_password', 400);
        }

        $new_email = sanitize_email($params['new_email']);
        if (!is_email($new_email)) {
            return $this->error(__('Invalid email address.', 'teinformez'), 'invalid_email', 400);
        }

        if (email_exists($new_email) && email_exists($new_email) !== $user_id) {
            return $this->error(__('This email is already in use.', 'teinformez'), 'email_exists', 409);
        }

        $result = wp_update_user([
            'ID' => $user_id,
            'user_email' => $new_email,
            'user_login' => $new_email,
        ]);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 'update_failed', 500);
        }

        return $this->success([
            'email' => $new_email
        ], __('Email changed successfully.', 'teinformez'));
    }

    /**
     * Get delivery history and stats
     */
    public function get_deliveries($request) {
        $user_id = $this->get_current_user_id();
        $handler = new Delivery_Handler();

        $deliveries = $handler->get_user_deliveries($user_id, 50);
        $stats = $handler->get_user_delivery_stats($user_id);

        return $this->success([
            'deliveries' => $deliveries,
            'stats' => $stats,
        ]);
    }

    /**
     * Get available categories
     */
    public function get_categories($request) {
        $categories = Config::get('categories', Config::DEFAULT_CATEGORIES);

        return $this->success(['categories' => $categories]);
    }
}
