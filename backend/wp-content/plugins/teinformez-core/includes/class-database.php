<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database utility class
 */
class Database {

    /**
     * Get table name with prefix
     */
    public static function get_table($table_suffix) {
        global $wpdb;
        return $wpdb->prefix . 'teinformez_' . $table_suffix;
    }

    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;

        $tables = [
            'user_preferences',
            'subscriptions',
            'news_queue',
            'delivery_log'
        ];

        foreach ($tables as $table) {
            $table_name = self::get_table($table);
            // Use prepare() to prevent SQL injection
            $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
            if ($wpdb->get_var($query) != $table_name) {
                return false;
            }
        }

        return true;
    }
}
