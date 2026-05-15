<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lightweight telemetry for CAS render calls (AN-03 revenue dashboard).
 *
 * Append-only ledger of every CAS proxy/newsletter render: placement + filled
 * outcome + timestamp. Aggregations (impressions per placement, filled rate)
 * computed at read time — no UPSERT race condition.
 *
 * Schema deliberately minimal — no visitor hash, no IP, no PII (visitor
 * identity already lives in visitor_events; this table is for revenue ops).
 *
 * Created lazily on first write so deploys don't need to touch activator.
 */
class CAS_Telemetry {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'teinformez_cas_telemetry';
    }

    public static function create_table_if_missing(): void {
        global $wpdb;
        $table = self::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            placement VARCHAR(20) NOT NULL,
            was_filled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY placement (placement),
            KEY created_at (created_at)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    /**
     * Record one CAS render event. Fire-and-forget; failures swallowed.
     */
    public static function record(string $placement, bool $was_filled): void {
        global $wpdb;
        self::create_table_if_missing();
        $wpdb->insert(self::table_name(), [
            'placement'  => substr($placement, 0, 20),
            'was_filled' => $was_filled ? 1 : 0,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Aggregate CAS impressions over a date range, grouped by placement.
     *
     * @param int $days  Number of days back from now (default 7)
     * @return array     [ ['placement' => 'newsletter', 'filled' => 12, 'empty' => 300, 'fill_rate' => 0.04], ...]
     */
    public static function aggregate_by_placement(int $days = 7): array {
        global $wpdb;
        $table = self::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return [];
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT placement,
                    SUM(was_filled = 1) AS filled,
                    SUM(was_filled = 0) AS empty
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY placement",
            (int) $days
        ), ARRAY_A);
        $out = [];
        foreach ((array) $rows as $r) {
            $filled = (int) ($r['filled'] ?? 0);
            $empty  = (int) ($r['empty'] ?? 0);
            $total  = $filled + $empty;
            $out[] = [
                'placement' => (string) ($r['placement'] ?? ''),
                'filled'    => $filled,
                'empty'     => $empty,
                'total'     => $total,
                'fill_rate' => $total > 0 ? round($filled / $total, 4) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Total filled impressions across all placements in last N days.
     */
    public static function total_impressions(int $days = 7): int {
        global $wpdb;
        $table = self::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return 0;
        }
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE was_filled = 1
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            (int) $days
        ));
        return (int) $count;
    }

    /**
     * Count active sponsored campaigns (`newsletter_ads` table) for today.
     */
    public static function active_sponsored_today(): int {
        global $wpdb;
        $ads_table = $wpdb->prefix . 'teinformez_newsletter_ads';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ads_table));
        if ($exists !== $ads_table) {
            return 0;
        }
        $today = date('Y-m-d');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$ads_table}
             WHERE status = 'active'
               AND campaign_start <= %s
               AND campaign_end   >= %s",
            $today,
            $today
        ));
        return (int) $count;
    }
}
