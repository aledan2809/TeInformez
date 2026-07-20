<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * M5 referral — personal invite links → complimentary Premium reward.
 *
 * Each user has a stable code (usermeta teinformez_referral_code). When a NEW user
 * registers via ?ref=<code>, BOTH the referrer and the new user receive +REWARD_DAYS
 * of complimentary Premium (usermeta teinformez_premium_granted_until, capped at
 * now + CAP_DAYS). Complimentary Premium is SEPARATE from Stripe: the
 * /subscription/status endpoint treats granted_until > now as premium, so no Stripe
 * object is created and the money path is never touched.
 *
 * Anti-abuse (variant A — no email-verification step exists on signup):
 *   - referrer must be a real code owner, referrer != referred
 *   - one reward per referred user (idempotent via wp_teinformez_referrals)
 *   - referred email is new (registration already enforces unique email)
 *   - test/synthetic accounts on either side are skipped (no grant, no metric noise)
 *
 * All timestamps are UTC (WordPress DB convention), stored as 'Y-m-d H:i:s'.
 */
class Referral_Manager {

    const CODE_META    = 'teinformez_referral_code';
    const GRANTED_META = 'teinformez_premium_granted_until';
    const REWARD_DAYS  = 7;
    const CAP_DAYS     = 60;

    /** Return the user's referral code, minting a unique one on first use. */
    public static function get_or_create_code(int $user_id): string {
        $code = get_user_meta($user_id, self::CODE_META, true);
        if (is_string($code) && $code !== '') {
            return $code;
        }
        do {
            $code = self::random_code(8);
        } while (self::code_owner($code) > 0);
        update_user_meta($user_id, self::CODE_META, $code);
        return $code;
    }

    /** Resolve a referral code → owner user_id (0 if none). */
    public static function code_owner(string $code): int {
        global $wpdb;
        $code = trim($code);
        if ($code === '') {
            return 0;
        }
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            self::CODE_META,
            $code
        ));
        return $id ? (int) $id : 0;
    }

    /**
     * Extend a user's complimentary Premium by $days, counting from
     * max(now, current grant), capped at now + CAP_DAYS.
     * Returns the new granted-until value ('Y-m-d H:i:s' UTC).
     */
    public static function grant_premium_days(int $user_id, int $days): string {
        $now  = time();
        $base = $now;

        $current = get_user_meta($user_id, self::GRANTED_META, true);
        if (is_string($current) && $current !== '') {
            $ts = strtotime($current . ' UTC');
            if ($ts && $ts > $now) {
                $base = $ts;
            }
        }

        $new = $base + $days * DAY_IN_SECONDS;
        $cap = $now + self::CAP_DAYS * DAY_IN_SECONDS;
        if ($new > $cap) {
            $new = $cap;
        }

        $value = gmdate('Y-m-d H:i:s', $new);
        update_user_meta($user_id, self::GRANTED_META, $value);
        return $value;
    }

    /** True if the user currently holds complimentary Premium (granted_until > now). */
    public static function has_granted_premium(int $user_id): bool {
        $ts = self::granted_ts($user_id);
        return $ts !== null;
    }

    /**
     * granted_until as 'Y-m-d H:i:s' (UTC), or null if none/expired.
     * Same shape as the Stripe current_period_end field so the UI renders both alike.
     */
    public static function granted_until(int $user_id): ?string {
        $ts = self::granted_ts($user_id);
        return $ts !== null ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * Process a referral at signup. On success grants both parties +REWARD_DAYS.
     * @return array{ok:bool, reason:string}
     */
    public static function process_referral(int $new_user_id, string $new_email, string $ref_code): array {
        global $wpdb;

        $ref_code = trim($ref_code);
        if ($ref_code === '') {
            return ['ok' => false, 'reason' => 'no_code'];
        }

        $referrer_id = self::code_owner($ref_code);
        if ($referrer_id <= 0) {
            return ['ok' => false, 'reason' => 'unknown_code'];
        }
        if ($referrer_id === $new_user_id) {
            return ['ok' => false, 'reason' => 'self_referral'];
        }

        // Never grant/count synthetic (journey-audit / test) accounts on either side.
        if (class_exists('TeInformez\\User_Helper')) {
            if (\TeInformez\User_Helper::is_test_user($new_user_id) ||
                \TeInformez\User_Helper::is_test_user($referrer_id)) {
                return ['ok' => false, 'reason' => 'test_account'];
            }
        }

        $table = $wpdb->prefix . 'teinformez_referrals';

        // One reward per referred user — idempotent guard.
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE referred_user_id = %d LIMIT 1",
            $new_user_id
        ));
        if ($exists) {
            return ['ok' => false, 'reason' => 'already_referred'];
        }

        $inserted = $wpdb->insert($table, [
            'referrer_user_id' => $referrer_id,
            'referred_user_id' => $new_user_id,
            'referred_email'   => $new_email,
            'referral_code'    => $ref_code,
            'rewarded'         => 1,
            'created_at'       => gmdate('Y-m-d H:i:s'),
        ], ['%d', '%d', '%s', '%s', '%d', '%s']);

        if (!$inserted) {
            return ['ok' => false, 'reason' => 'insert_failed'];
        }

        self::grant_premium_days($referrer_id, self::REWARD_DAYS);
        self::grant_premium_days($new_user_id, self::REWARD_DAYS);

        return ['ok' => true, 'reason' => 'granted'];
    }

    /** Stats for the invite page. */
    public static function get_stats(int $user_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_referrals';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE referrer_user_id = %d AND rewarded = 1",
            $user_id
        ));
        return [
            'code'          => self::get_or_create_code($user_id),
            'referred'      => $count,
            'reward_days'   => self::REWARD_DAYS,
            'cap_days'      => self::CAP_DAYS,
            'granted_until' => self::granted_until($user_id),
        ];
    }

    // -------------------------------------------------------------------------

    /** Current grant as a future unix ts, or null if none/expired. */
    private static function granted_ts(int $user_id): ?int {
        $granted = get_user_meta($user_id, self::GRANTED_META, true);
        if (!is_string($granted) || $granted === '') {
            return null;
        }
        $ts = strtotime($granted . ' UTC');
        return ($ts && $ts > time()) ? $ts : null;
    }

    /** Short, unambiguous code (no easily-confused characters). */
    private static function random_code(int $len): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[wp_rand(0, $max)];
        }
        return $out;
    }
}
