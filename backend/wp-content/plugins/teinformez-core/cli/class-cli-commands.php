<?php
namespace TeInformez\CLI;

use TeInformez\GA4_Key_Migrator;
use TeInformez\Google_Analytics_Service;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * WP-CLI commands for TeInformez governance / migration tasks.
 *
 * Registered from teinformez-core.php only when WP_CLI is loaded.
 */
class Commands {

    /**
     * Migrate the GA4 service-account private key from wp_options into the
     * filesystem PEM file referenced by TEINFORMEZ_GA4_PRIVATE_KEY_PATH.
     *
     * Does NOT delete the wp_options row — verify the dashboard first, then
     * run `wp option delete teinformez_ga4_private_key`.
     *
     * ## EXAMPLES
     *
     *     wp teinformez migrate-ga4-key
     *
     * @when after_wp_load
     */
    public function migrate_ga4_key(): void {
        $report = GA4_Key_Migrator::run();
        foreach ((array) ($report['details'] ?? []) as $line) {
            \WP_CLI::log((string) $line);
        }
        if (!empty($report['ok'])) {
            \WP_CLI::success((string) $report['message']);
            \WP_CLI::log('Run `wp option delete teinformez_ga4_private_key` once the dashboard is confirmed working.');
            return;
        }
        \WP_CLI::error((string) $report['message']);
    }

    /**
     * Report which source the GA4 service is currently loading the private
     * key from. Useful to verify migration without inspecting the database.
     *
     * ## EXAMPLES
     *
     *     wp teinformez ga4-key-source
     *
     * @when after_wp_load
     */
    public function ga4_key_source(): void {
        $source = Google_Analytics_Service::get_private_key_source();
        \WP_CLI::log('GA4 private key source: ' . $source);
        if ($source === 'filesystem' || $source === 'inline-constant') {
            \WP_CLI::success('Key is loaded outside the database.');
        } elseif ($source === 'none') {
            \WP_CLI::warning('No key configured. GA4 will be unavailable.');
        } else {
            \WP_CLI::warning('Key is still in the database. Run `wp teinformez migrate-ga4-key` to move it out.');
        }
    }
}

\WP_CLI::add_command('teinformez migrate-ga4-key', [Commands::class, 'migrate_ga4_key']);
\WP_CLI::add_command('teinformez ga4-key-source', [Commands::class, 'ga4_key_source']);
