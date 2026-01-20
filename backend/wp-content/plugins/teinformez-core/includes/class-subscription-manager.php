<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User subscriptions and topic preferences
 */
class Subscription_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'teinformez_subscriptions';
    }

    /**
     * Add subscription
     */
    public function add_subscription($user_id, $data) {
        global $wpdb;

        $subscription = [
            'user_id' => $user_id,
            'category_slug' => sanitize_text_field($data['category_slug'] ?? ''),
            'topic_keyword' => sanitize_text_field($data['topic_keyword'] ?? ''),
            'country_filter' => sanitize_text_field($data['country_filter'] ?? 'all'),
            'source_filter' => isset($data['source_filter']) ? json_encode($data['source_filter']) : null,
            'is_active' => 1,
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->table_name, $subscription);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get user subscriptions
     */
    public function get_user_subscriptions($user_id, $active_only = true) {
        global $wpdb;

        $query = "SELECT * FROM {$this->table_name} WHERE user_id = %d";
        $params = [$user_id];

        if ($active_only) {
            $query .= " AND is_active = 1";
        }

        $query .= " ORDER BY created_at DESC";

        $subscriptions = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);

        // Decode JSON fields
        foreach ($subscriptions as &$sub) {
            if (!empty($sub['source_filter'])) {
                $sub['source_filter'] = json_decode($sub['source_filter'], true);
            }
        }

        return $subscriptions;
    }

    /**
     * Update subscription
     */
    public function update_subscription($subscription_id, $data) {
        global $wpdb;

        if (isset($data['source_filter']) && is_array($data['source_filter'])) {
            $data['source_filter'] = json_encode($data['source_filter']);
        }

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $subscription_id]
        );
    }

    /**
     * Delete subscription
     */
    public function delete_subscription($subscription_id, $user_id = null) {
        global $wpdb;

        $where = ['id' => $subscription_id];

        // Security: ensure user can only delete their own subscriptions
        if ($user_id) {
            $where['user_id'] = $user_id;
        }

        return $wpdb->delete($this->table_name, $where);
    }

    /**
     * Toggle subscription active status
     */
    public function toggle_subscription($subscription_id, $user_id) {
        global $wpdb;

        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$this->table_name} WHERE id = %d AND user_id = %d",
            $subscription_id,
            $user_id
        ));

        if ($current === null) {
            return false;
        }

        return $wpdb->update(
            $this->table_name,
            ['is_active' => $current ? 0 : 1, 'updated_at' => current_time('mysql')],
            ['id' => $subscription_id, 'user_id' => $user_id]
        );
    }

    /**
     * Get subscriptions by category
     */
    public function get_subscriptions_by_category($category_slug) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$this->table_name}
             WHERE category_slug = %s AND is_active = 1",
            $category_slug
        ), ARRAY_A);
    }

    /**
     * Get subscriptions by topic keyword
     */
    public function get_subscriptions_by_topic($topic_keyword) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$this->table_name}
             WHERE topic_keyword LIKE %s AND is_active = 1",
            '%' . $wpdb->esc_like($topic_keyword) . '%'
        ), ARRAY_A);
    }

    /**
     * Bulk add subscriptions (used in onboarding)
     */
    public function bulk_add_subscriptions($user_id, $subscriptions) {
        global $wpdb;

        $values = [];
        foreach ($subscriptions as $sub) {
            $values[] = $wpdb->prepare(
                "(%d, %s, %s, %s, %s, 1, NOW())",
                $user_id,
                sanitize_text_field($sub['category_slug'] ?? ''),
                sanitize_text_field($sub['topic_keyword'] ?? ''),
                sanitize_text_field($sub['country_filter'] ?? 'all'),
                isset($sub['source_filter']) ? json_encode($sub['source_filter']) : null
            );
        }

        if (empty($values)) {
            return false;
        }

        $query = "INSERT INTO {$this->table_name}
                  (user_id, category_slug, topic_keyword, country_filter, source_filter, is_active, created_at)
                  VALUES " . implode(', ', $values);

        return $wpdb->query($query);
    }

    /**
     * Get subscription statistics
     */
    public function get_user_stats($user_id) {
        global $wpdb;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));

        $active = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND is_active = 1",
            $user_id
        ));

        $by_category = $wpdb->get_results($wpdb->prepare(
            "SELECT category_slug, COUNT(*) as count
             FROM {$this->table_name}
             WHERE user_id = %d AND is_active = 1
             GROUP BY category_slug",
            $user_id
        ), ARRAY_A);

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'inactive' => (int) ($total - $active),
            'by_category' => $by_category
        ];
    }
}
