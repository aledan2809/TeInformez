<?php
namespace TeInformez\API;

use TeInformez\User_Manager;
use TeInformez\Subscription_Manager;
use TeInformez\Delivery_Handler;
use TeInformez\GDPR_Handler;
use TeInformez\Email_Sender;
use TeInformez\Config;
use TeInformez\Database;

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

        // Change email — initiates double opt-in flow (M-16)
        register_rest_route($this->namespace, '/user/change-email', [
            'methods' => 'POST',
            'callback' => [$this, 'change_email'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Confirm email change via token link — public, user clicks from email (M-16)
        register_rest_route($this->namespace, '/user/confirm-email-change', [
            'methods' => 'GET',
            'callback' => [$this, 'confirm_email_change'],
            'permission_callback' => '__return_true'
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

        // Reading history - mark as read
        register_rest_route($this->namespace, '/user/reading-history', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_as_read'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Reading history - get history
        register_rest_route($this->namespace, '/user/reading-history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reading_history'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Bookmarks - get list
        register_rest_route($this->namespace, '/user/bookmarks', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bookmarks'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Bookmarks - add
        register_rest_route($this->namespace, '/user/bookmarks', [
            'methods' => 'POST',
            'callback' => [$this, 'add_bookmark'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Bookmarks - remove
        register_rest_route($this->namespace, '/user/bookmarks/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'remove_bookmark'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);
    }

    /**
     * Get user preferences
     */
    public function get_preferences($request) {
        $user_id = $this->get_current_user_id();
        $user_manager = new User_Manager();
        $preferences = $user_manager->get_user_preferences($user_id);

        // Surface the test-user flag so frontend can set GA4 user_property `user_type=test`
        // and exclude its own session events from analytics dashboards.
        $is_test = class_exists('TeInformez\\User_Helper')
            ? \TeInformez\User_Helper::is_test_user((int) $user_id)
            : false;

        return $this->success([
            'preferences' => $preferences,
            'is_test_user' => $is_test,
        ]);
    }

    /**
     * Update user preferences
     */
    public function update_preferences($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $user_manager = new User_Manager();
        $result = $user_manager->update_preferences($user_id, $params);

        if ($result === null) {
            return $this->error(__('No valid preference fields provided.', 'teinformez'), 'no_valid_fields', 400);
        }

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
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
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
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        if (empty($params['subscriptions']) || !is_array($params['subscriptions'])) {
            return $this->error(__('Invalid subscriptions data.', 'teinformez'), 'invalid_data', 400);
        }

        if (count($params['subscriptions']) > 50) {
            return $this->error(__('Maximum 50 subscriptions per request.', 'teinformez'), 'too_many_subscriptions', 400);
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
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        $user_id = $this->get_current_user_id();
        $subscription_id = $request->get_param('id');
        $params = $request->get_json_params();

        $sub_manager = new Subscription_Manager();
        $result = $sub_manager->update_subscription($subscription_id, $user_id, $params);

        if ($result === false) {
            return $this->error(__('Failed to update subscription.', 'teinformez'), 'update_failed', 500);
        }

        return $this->success([], __('Subscription updated successfully.', 'teinformez'));
    }

    /**
     * Delete subscription
     */
    public function delete_subscription($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
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
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
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
     * Requires current_password for re-authentication (I-01).
     */
    public function delete_account($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        // Require current_password field
        $validation = $this->validate_required($params, ['current_password']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Verify password — generic error to avoid leaking user existence
        $user = get_userdata($user_id);
        if (!$user || !wp_check_password($params['current_password'], $user->user_pass, $user_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid password — re-authentication required',
                'code' => 'reauth_failed'
            ], 403);
        }

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
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
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
        $strength = $this->validate_password_strength($new_password);
        if (is_wp_error($strength)) {
            return $strength;
        }

        wp_set_password($new_password, $user_id);

        // Re-authenticate so current session stays valid
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return $this->success([], __('Password changed successfully.', 'teinformez'));
    }

    /**
     * Change email — double opt-in flow (M-16).
     * Verifies password, stores pending email + token in user_meta,
     * sends confirmation to the NEW address. The change is NOT applied here.
     */
    public function change_email($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
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

        if ($new_email === $user->user_email) {
            return $this->error(__('New email is the same as the current email.', 'teinformez'), 'same_email', 400);
        }

        if (email_exists($new_email) && email_exists($new_email) !== $user_id) {
            return $this->error(__('This email is already in use.', 'teinformez'), 'email_exists', 409);
        }

        // Store pending state — do not apply the change yet
        $token = bin2hex(random_bytes(32));
        update_user_meta($user_id, '_teinformez_pending_email', $new_email);
        update_user_meta($user_id, '_teinformez_email_token', $token);
        update_user_meta($user_id, '_teinformez_email_token_expires', time() + 24 * HOUR_IN_SECONDS);

        $confirm_link = rest_url($this->namespace . '/user/confirm-email-change')
            . '?user_id=' . (int) $user_id
            . '&token=' . urlencode($token);

        $email_sender = new Email_Sender();
        if (!$email_sender->send_email_change_notice_to_old($user->user_email, $user->display_name)) {
            error_log('TeInformez: Failed to send email-change notice to old address for user ' . $user_id);
        }
        $email_sender->send_email_change_confirmation($new_email, $confirm_link, $user->display_name);

        return $this->success(
            [],
            __('A confirmation email has been sent to your new address. Please confirm within 24 hours.', 'teinformez'),
            202
        );
    }

    /**
     * Confirm email change via token (M-16).
     * Public endpoint — user clicks link from email.
     * Validates token, applies change, cleans up meta.
     */
    public function confirm_email_change($request) {
        $user_id = absint($request->get_param('user_id'));
        $token   = preg_replace('/[^a-f0-9]/', '', $request->get_param('token') ?? '');

        $frontend_url = Config::FRONTEND_URL;

        if (!$user_id || !$token) {
            wp_safe_redirect($frontend_url . '/settings?email_change=invalid_link');
            exit;
        }

        $stored_token  = get_user_meta($user_id, '_teinformez_email_token', true);
        $pending_email = get_user_meta($user_id, '_teinformez_pending_email', true);
        $expires       = (int) get_user_meta($user_id, '_teinformez_email_token_expires', true);

        if (empty($stored_token) || empty($pending_email)) {
            wp_safe_redirect($frontend_url . '/settings?email_change=no_pending');
            exit;
        }

        if (!hash_equals($stored_token, $token)) {
            wp_safe_redirect($frontend_url . '/settings?email_change=invalid_token');
            exit;
        }

        if (time() > $expires) {
            delete_user_meta($user_id, '_teinformez_pending_email');
            delete_user_meta($user_id, '_teinformez_email_token');
            delete_user_meta($user_id, '_teinformez_email_token_expires');
            wp_safe_redirect($frontend_url . '/settings?email_change=token_expired');
            exit;
        }

        // Re-check availability at confirmation time
        $existing_id = email_exists($pending_email);
        if ($existing_id && $existing_id !== $user_id) {
            delete_user_meta($user_id, '_teinformez_pending_email');
            delete_user_meta($user_id, '_teinformez_email_token');
            delete_user_meta($user_id, '_teinformez_email_token_expires');
            wp_safe_redirect($frontend_url . '/settings?email_change=email_exists');
            exit;
        }

        $result = wp_update_user([
            'ID'         => $user_id,
            'user_email' => $pending_email,
            'user_login' => $pending_email,
        ]);

        delete_user_meta($user_id, '_teinformez_pending_email');
        delete_user_meta($user_id, '_teinformez_email_token');
        delete_user_meta($user_id, '_teinformez_email_token_expires');

        if (is_wp_error($result)) {
            wp_safe_redirect($frontend_url . '/settings?email_change=update_failed');
            exit;
        }

        wp_safe_redirect($frontend_url . '/settings?email_change=success');
        exit;
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

    /**
     * Mark article as read
     */
    public function mark_as_read($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        global $wpdb;

        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['news_id']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $news_id = absint($params['news_id']);
        $time_spent = isset($params['time_spent']) ? absint($params['time_spent']) : 0;
        $table = Database::get_table('reading_history');

        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle re-reads
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, news_id, read_at, time_spent)
             VALUES (%d, %d, %s, %d)
             ON DUPLICATE KEY UPDATE time_spent = time_spent + VALUES(time_spent), read_at = VALUES(read_at)",
            $user_id,
            $news_id,
            current_time('mysql'),
            $time_spent
        ));

        return $this->success([], __('Article marked as read.', 'teinformez'));
    }

    /**
     * Get reading history with streak calculation
     */
    public function get_reading_history($request) {
        global $wpdb;

        $user_id = $this->get_current_user_id();
        $table = Database::get_table('reading_history');

        // Get all reading history (last 90 days)
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-90 days'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT news_id, read_at, time_spent FROM {$table}
             WHERE user_id = %d AND read_at >= %s
             ORDER BY read_at DESC",
            $user_id,
            $cutoff
        ));

        // Group by date for streak calculation
        $days = [];
        $article_ids = [];
        foreach ($rows as $row) {
            $date = substr($row->read_at, 0, 10); // YYYY-MM-DD
            if (!isset($days[$date])) {
                $days[$date] = [];
            }
            $days[$date][] = (int) $row->news_id;
            $article_ids[] = (int) $row->news_id;
        }

        // Calculate streak
        $streak = $this->calculate_streak(array_keys($days));

        // Total articles read
        $total_read = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        // Build history array matching frontend ReadingDay format
        $history = [];
        foreach ($days as $date => $ids) {
            $history[] = [
                'date' => $date,
                'articlesRead' => $ids,
            ];
        }

        return $this->success([
            'history' => $history,
            'current_streak' => $streak,
            'total_read' => $total_read,
        ]);
    }

    /**
     * Calculate reading streak from an array of date strings (YYYY-MM-DD)
     */
    private function calculate_streak(array $dates): int {
        if (empty($dates)) {
            return 0;
        }

        // Sort descending
        rsort($dates);

        $today = gmdate('Y-m-d');
        $yesterday = gmdate('Y-m-d', strtotime('-1 day'));

        // Streak must include today or yesterday
        if ($dates[0] !== $today && $dates[0] !== $yesterday) {
            return 0;
        }

        $streak = 1;
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $current = strtotime($dates[$i]);
            $prev = strtotime($dates[$i + 1]);
            $diff_days = ($current - $prev) / 86400;

            if ($diff_days === 1.0) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get user bookmarks
     */
    public function get_bookmarks($request) {
        global $wpdb;

        $user_id = $this->get_current_user_id();
        $bookmarks_table = Database::get_table('bookmarks');
        $news_table = Database::get_table('news_queue');
        $archive_table = Database::get_table('news_archive');

        // Get bookmarks joined with news data (check both queue and archive)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id AS bookmark_id, b.news_id, b.created_at AS saved_at,
                    COALESCE(n.processed_title, a.processed_title) AS title,
                    COALESCE(n.processed_summary, a.processed_summary) AS summary,
                    COALESCE(n.ai_generated_image_url, a.ai_generated_image_url) AS image,
                    COALESCE(n.source_name, a.source_name) AS source,
                    COALESCE(n.categories, a.categories) AS categories,
                    COALESCE(n.published_at, a.published_at) AS published_at,
                    COALESCE(n.original_url, a.original_url) AS original_url
             FROM {$bookmarks_table} b
             LEFT JOIN {$news_table} n ON b.news_id = n.id
             LEFT JOIN {$archive_table} a ON b.news_id = a.id AND n.id IS NULL
             WHERE b.user_id = %d
             ORDER BY b.created_at DESC",
            $user_id
        ));

        $bookmarks = [];
        foreach ($rows as $row) {
            $categories = $row->categories ? json_decode($row->categories, true) : [];
            if (!is_array($categories)) {
                $categories = [];
            }

            $bookmarks[] = [
                'id' => (int) $row->news_id,
                'title' => $row->title ?? '',
                'summary' => $row->summary ?? '',
                'image' => $row->image,
                'source' => $row->source ?? '',
                'categories' => $categories,
                'published_at' => $row->published_at ?? '',
                'original_url' => $row->original_url ?? '',
                'savedAt' => $row->saved_at,
            ];
        }

        return $this->success(['bookmarks' => $bookmarks]);
    }

    /**
     * Add bookmark
     */
    public function add_bookmark($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        global $wpdb;

        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['news_id']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $news_id = absint($params['news_id']);
        $table = Database::get_table('bookmarks');

        // Use INSERT IGNORE to silently skip duplicates
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (user_id, news_id, created_at)
             VALUES (%d, %d, %s)",
            $user_id,
            $news_id,
            current_time('mysql')
        ));

        return $this->success([], __('Bookmark added.', 'teinformez'), 201);
    }

    /**
     * Remove bookmark by news_id
     */
    public function remove_bookmark($request) {
        $csrf = $this->check_cookie_csrf($request); if ($csrf) return $csrf;
        global $wpdb;

        $user_id = $this->get_current_user_id();
        $news_id = absint($request->get_param('id'));
        $table = Database::get_table('bookmarks');

        $result = $wpdb->delete($table, [
            'user_id' => $user_id,
            'news_id' => $news_id,
        ], ['%d', '%d']);

        if ($result === false) {
            return $this->error(__('Failed to remove bookmark.', 'teinformez'), 'delete_failed', 500);
        }

        return $this->success([], __('Bookmark removed.', 'teinformez'));
    }
}
