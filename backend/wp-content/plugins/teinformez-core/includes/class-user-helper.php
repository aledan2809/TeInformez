<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User_Helper — single source of truth for "is this WP user a test/dev user?"
 *
 * Test users are flagged via wp_usermeta key `teinformez_is_test_user = '1'`.
 * Email pattern fallback (auto-flagged on register): @teinformez.test, @example.{com,org,net}.
 *
 * Used by analytics surfaces (visitor_events, /admin/analytics queries,
 * MA emitter, news view_count) to exclude test traffic from real metrics.
 * Also used by the frontend (via /user/preferences `is_test_user` field)
 * to set a `user_type=test` user_property on GA4 so test sessions are
 * filterable in the GA UI.
 */
class User_Helper {
    const META_KEY = 'teinformez_is_test_user';

    /** Email substring patterns that auto-flag a user as test on registration. */
    const TEST_EMAIL_PATTERNS = [
        '@teinformez.test',
        '@example.com',
        '@example.org',
        '@example.net',
    ];

    /**
     * Returns true if the given user (by ID or email) is a test/dev user.
     * Checks usermeta first; falls back to email pattern match.
     */
    public static function is_test_user($user_id_or_email): bool {
        if (empty($user_id_or_email)) {
            return false;
        }

        $email = null;

        // Numeric ID path: check meta, then look up email for fallback.
        if (is_numeric($user_id_or_email)) {
            $user_id = (int) $user_id_or_email;
            if ($user_id <= 0) {
                return false;
            }
            $meta = get_user_meta($user_id, self::META_KEY, true);
            if ($meta === '1' || $meta === 1) {
                return true;
            }
            $u = get_userdata($user_id);
            $email = $u ? $u->user_email : null;
        } elseif (is_string($user_id_or_email) && strpos($user_id_or_email, '@') !== false) {
            $email = $user_id_or_email;
        }

        if ($email === null || $email === '') {
            return false;
        }

        $lower = strtolower($email);
        foreach (self::TEST_EMAIL_PATTERNS as $p) {
            if (strpos($lower, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hook callback for `user_register`: auto-flag pattern-matching emails as test.
     * Idempotent — re-running for an already-flagged user is a no-op.
     */
    public static function auto_flag_on_register(int $user_id): void {
        $u = get_userdata($user_id);
        if (!$u) {
            return;
        }
        if (self::is_test_user($u->user_email)) {
            update_user_meta($user_id, self::META_KEY, '1');
        }
    }

    /**
     * Returns the list of test user IDs as integers (for SQL `NOT IN` clauses).
     * Cached per-request to avoid repeating the meta scan inside loops.
     */
    public static function get_test_user_ids(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        global $wpdb;
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            self::META_KEY,
            '1'
        ));
        $cache = array_map('intval', (array) $rows);
        return $cache;
    }

    /**
     * Returns a SQL fragment safe to interpolate into a WHERE clause that
     * excludes test users. Example:
     *
     *   "SELECT * FROM wp_users WHERE 1=1 AND " . User_Helper::sql_not_test('ID')
     *
     * If no test users exist yet, returns a tautology so the caller's SQL
     * stays valid regardless.
     */
    public static function sql_not_test(string $user_id_column): string {
        $ids = self::get_test_user_ids();
        if (empty($ids)) {
            return '1=1';
        }
        $list = implode(',', array_map('intval', $ids));
        return "({$user_id_column} NOT IN ({$list}))";
    }

    /**
     * SQL fragment to exclude test emails from queries on tables that have NO
     * user_id FK (e.g. wp_teinformez_newsletter). Combines: (1) email IS NOT IN
     * the set of emails belonging to flagged WP users, (2) email doesn't match
     * a known test pattern (@teinformez.test, @example.{com,org,net}).
     */
    public static function sql_email_not_test(string $email_column): string {
        global $wpdb;
        $patterns = [];
        foreach (self::TEST_EMAIL_PATTERNS as $p) {
            // sanitize against backslash + percent in pattern string (defense in depth — patterns are constants)
            $esc = esc_sql($p);
            $patterns[] = "LOWER({$email_column}) NOT LIKE '%" . $esc . "'";
        }
        $pattern_sql = implode(' AND ', $patterns);
        $flagged_emails_subq = "SELECT u.user_email FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID WHERE m.meta_key = '" . esc_sql(self::META_KEY) . "' AND m.meta_value = '1'";
        return "({$email_column} NOT IN ({$flagged_emails_subq}) AND {$pattern_sql})";
    }

    /** Set/unset the test-user flag for a given user. */
    public static function set_test_flag(int $user_id, bool $is_test): void {
        if ($is_test) {
            update_user_meta($user_id, self::META_KEY, '1');
        } else {
            delete_user_meta($user_id, self::META_KEY);
        }
    }
}
