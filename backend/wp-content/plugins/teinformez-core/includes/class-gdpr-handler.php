<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GDPR compliance handler
 */
class GDPR_Handler {

    /**
     * Record user consent
     */
    public function record_consent($user_id, $ip_address = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'teinformez_user_preferences';

        return $wpdb->update(
            $table,
            [
                'gdpr_consent' => 1,
                'gdpr_consent_date' => current_time('mysql'),
                'gdpr_ip_address' => sanitize_text_field($ip_address),
                'updated_at' => current_time('mysql')
            ],
            ['user_id' => $user_id]
        );
    }

    /**
     * Revoke user consent
     */
    public function revoke_consent($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'teinformez_user_preferences';

        return $wpdb->update(
            $table,
            [
                'gdpr_consent' => 0,
                'updated_at' => current_time('mysql')
            ],
            ['user_id' => $user_id]
        );
    }

    /**
     * Check if user has given consent
     */
    public function has_consent($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'teinformez_user_preferences';

        $consent = $wpdb->get_var($wpdb->prepare(
            "SELECT gdpr_consent FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        return (bool) $consent;
    }

    /**
     * Get consent details
     */
    public function get_consent_details($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'teinformez_user_preferences';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT gdpr_consent, gdpr_consent_date, gdpr_ip_address
             FROM {$table}
             WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
    }

    /**
     * Anonymize user data (GDPR right to be forgotten)
     */
    public function anonymize_user($user_id) {
        $user_manager = new User_Manager();
        return $user_manager->delete_user_data($user_id);
    }

    /**
     * Export user data (GDPR right to data portability)
     */
    public function export_user_data($user_id) {
        $user_manager = new User_Manager();
        return $user_manager->export_user_data($user_id);
    }

    /**
     * Add GDPR info to WordPress privacy exporter
     */
    public function register_privacy_exporters() {
        return [[
            'exporter_friendly_name' => __('TeInformez User Data', 'teinformez'),
            'callback' => [$this, 'privacy_exporter']
        ]];
    }

    /**
     * Privacy exporter callback
     */
    public function privacy_exporter($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);

        if (!$user) {
            return [
                'data' => [],
                'done' => true
            ];
        }

        $data = $this->export_user_data($user->ID);

        $export_items = [[
            'group_id' => 'teinformez_user_data',
            'group_label' => __('TeInformez Data', 'teinformez'),
            'item_id' => 'user-' . $user->ID,
            'data' => $this->format_for_export($data)
        ]];

        return [
            'data' => $export_items,
            'done' => true
        ];
    }

    /**
     * Add GDPR info to WordPress privacy eraser
     */
    public function register_privacy_erasers() {
        return [[
            'eraser_friendly_name' => __('TeInformez User Data', 'teinformez'),
            'callback' => [$this, 'privacy_eraser']
        ]];
    }

    /**
     * Privacy eraser callback
     */
    public function privacy_eraser($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);

        if (!$user) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [],
                'done' => true
            ];
        }

        $this->anonymize_user($user->ID);

        return [
            'items_removed' => true,
            'items_retained' => false,
            'messages' => [__('TeInformez user data has been anonymized.', 'teinformez')],
            'done' => true
        ];
    }

    /**
     * Format data for WordPress privacy export
     */
    private function format_for_export($data) {
        $formatted = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }

            $formatted[] = [
                'name' => ucfirst(str_replace('_', ' ', $key)),
                'value' => $value
            ];
        }

        return $formatted;
    }
}

// Register privacy handlers
add_filter('wp_privacy_personal_data_exporters', function($exporters) {
    $gdpr = new GDPR_Handler();
    $exporters['teinformez'] = $gdpr->register_privacy_exporters()[0];
    return $exporters;
});

add_filter('wp_privacy_personal_data_erasers', function($erasers) {
    $gdpr = new GDPR_Handler();
    $erasers['teinformez'] = $gdpr->register_privacy_erasers()[0];
    return $erasers;
});
