<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User preferences and profile management
 */
class User_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'teinformez_user_preferences';
    }

    /**
     * Create default preferences for new user
     */
    public function create_default_preferences($user_id, $language = 'ro') {
        global $wpdb;

        $default_preferences = [
            'user_id' => $user_id,
            'preferred_language' => $language,
            'delivery_channels' => json_encode(['email']),
            'delivery_schedule' => json_encode([
                'frequency' => 'daily',
                'time' => '14:00',
                'timezone' => Config::SITE_TIMEZONE
            ]),
            'gdpr_consent' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        return $wpdb->insert($this->table_name, $default_preferences);
    }

    /**
     * Get user preferences
     */
    public function get_user_preferences($user_id) {
        global $wpdb;

        $prefs = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$prefs) {
            return null;
        }

        // Decode JSON fields
        $prefs['delivery_channels'] = json_decode($prefs['delivery_channels'], true);
        $prefs['delivery_schedule'] = json_decode($prefs['delivery_schedule'], true);

        return $prefs;
    }

    /**
     * Update user preferences
     */
    public function update_preferences($user_id, $data) {
        global $wpdb;

        // Encode JSON fields if they're arrays
        if (isset($data['delivery_channels']) && is_array($data['delivery_channels'])) {
            $data['delivery_channels'] = json_encode($data['delivery_channels']);
        }

        if (isset($data['delivery_schedule']) && is_array($data['delivery_schedule'])) {
            $data['delivery_schedule'] = json_encode($data['delivery_schedule']);
        }

        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $this->table_name,
            $data,
            ['user_id' => $user_id]
        );
    }

    /**
     * Get all users with specific preferences (for batch processing)
     */
    public function get_users_by_schedule($frequency, $time = null) {
        global $wpdb;

        $query = "SELECT user_id, delivery_channels, delivery_schedule
                  FROM {$this->table_name}
                  WHERE JSON_EXTRACT(delivery_schedule, '$.frequency') = %s";

        $params = [$frequency];

        if ($time) {
            $query .= " AND JSON_EXTRACT(delivery_schedule, '$.time') = %s";
            $params[] = $time;
        }

        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }

    /**
     * Delete user data (GDPR right to be forgotten)
     */
    public function delete_user_data($user_id) {
        global $wpdb;

        // Delete preferences
        $wpdb->delete($this->table_name, ['user_id' => $user_id]);

        // Delete subscriptions
        $subscriptions_table = $wpdb->prefix . 'teinformez_subscriptions';
        $wpdb->delete($subscriptions_table, ['user_id' => $user_id]);

        // Anonymize delivery log (keep for stats but remove PII)
        $delivery_table = $wpdb->prefix . 'teinformez_delivery_log';
        $wpdb->update(
            $delivery_table,
            ['user_id' => 0],
            ['user_id' => $user_id]
        );

        return true;
    }

    /**
     * Export user data (GDPR right to data portability)
     */
    public function export_user_data($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        $preferences = $this->get_user_preferences($user_id);

        // Get subscriptions
        $subscriptions_table = $wpdb->prefix . 'teinformez_subscriptions';
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$subscriptions_table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Get delivery history
        $delivery_table = $wpdb->prefix . 'teinformez_delivery_log';
        $delivery_history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$delivery_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 100",
            $user_id
        ), ARRAY_A);

        return [
            'user_info' => [
                'email' => $user->user_email,
                'name' => $user->display_name,
                'registered_at' => $user->user_registered
            ],
            'preferences' => $preferences,
            'subscriptions' => $subscriptions,
            'delivery_history' => $delivery_history,
            'exported_at' => current_time('mysql')
        ];
    }
}
