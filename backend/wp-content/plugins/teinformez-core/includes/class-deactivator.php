<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivation handler
 * Cleans up scheduled events (does NOT delete data)
 */
class Deactivator {

    public static function deactivate() {
        // Clear scheduled cron jobs
        $cron_hooks = [
            'teinformez_fetch_news',
            'teinformez_process_news',
            'teinformez_check_deliveries',
            'teinformez_daily_cleanup'
        ];

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
