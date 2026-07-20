<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OP-01 churn prevention — a daily cron that emails users ~3 days before their
 * Premium access ends, nudging a renewal (with an optional promo code).
 *
 * Two audiences (Stripe auto-renews, so only these actually "expire"):
 *   - Stripe subscriptions with cancel_at_period_end = 1 → lapse at current_period_end.
 *   - Referral-granted complimentary Premium (usermeta teinformez_premium_granted_until)
 *     → no auto-renew, a conversion opportunity.
 *
 * Dedup: one email per distinct expiry value (usermeta teinformez_churn_emailed_for);
 * if the user renews (a new period), a future expiry gets its own reminder. Test
 * accounts are skipped. The promo code is read from option teinformez_churn_promo_code
 * (empty = the email simply omits the code line; the renew CTA works regardless).
 */
class Churn_Mailer {

    const EMAILED_META = 'teinformez_churn_emailed_for';
    const GRANTED_META = 'teinformez_premium_granted_until';
    const WINDOW_MIN_DAYS = 2; // email when expiry falls in (now+2d, now+3d]
    const WINDOW_MAX_DAYS = 3;

    /** Daily cron callback. */
    public static function run(): void {
        self::process_stripe_cancellations();
        self::process_granted_expiries();
    }

    private static function window(): array {
        return [
            gmdate('Y-m-d H:i:s', time() + self::WINDOW_MIN_DAYS * DAY_IN_SECONDS),
            gmdate('Y-m-d H:i:s', time() + self::WINDOW_MAX_DAYS * DAY_IN_SECONDS),
        ];
    }

    private static function process_stripe_cancellations(): void {
        global $wpdb;
        [$from, $to] = self::window();
        $table = $wpdb->prefix . 'teinformez_stripe_subscriptions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, current_period_end FROM {$table}
             WHERE cancel_at_period_end = 1
               AND status IN ('active','trialing')
               AND current_period_end IS NOT NULL
               AND current_period_end BETWEEN %s AND %s",
            $from,
            $to
        ), ARRAY_A);

        foreach ((array) $rows as $row) {
            self::maybe_send((int) $row['user_id'], (string) $row['current_period_end'], 'stripe');
        }
    }

    private static function process_granted_expiries(): void {
        global $wpdb;
        [$from, $to] = self::window();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value AS granted FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND meta_value BETWEEN %s AND %s",
            self::GRANTED_META,
            $from,
            $to
        ), ARRAY_A);

        foreach ((array) $rows as $row) {
            self::maybe_send((int) $row['user_id'], (string) $row['granted'], 'granted');
        }
    }

    private static function maybe_send(int $user_id, string $expiry, string $kind): void {
        if ($user_id <= 0 || $expiry === '') {
            return;
        }
        // Skip synthetic/test accounts.
        if (class_exists('TeInformez\\User_Helper') && \TeInformez\User_Helper::is_test_user($user_id)) {
            return;
        }
        // Dedup: already warned about this exact expiry.
        if (get_user_meta($user_id, self::EMAILED_META, true) === $expiry) {
            return;
        }
        // A granted user who also holds an active paid Stripe sub isn't churning.
        if ($kind === 'granted' && self::has_active_stripe($user_id)) {
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return;
        }

        $promo     = (string) get_option('teinformez_churn_promo_code', '');
        $renew_url = rtrim(Config::get('frontend_url', Config::FRONTEND_URL), '/') . '/subscribe';

        $sender = new Email_Sender();
        $sent   = $sender->send_churn_reminder(
            $user->user_email,
            $user->display_name ?: '',
            $expiry,
            $renew_url,
            $promo,
            $kind
        );

        if ($sent) {
            update_user_meta($user_id, self::EMAILED_META, $expiry);
        }
    }

    private static function has_active_stripe(int $user_id): bool {
        global $wpdb;
        $table  = $wpdb->prefix . 'teinformez_stripe_subscriptions';
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$table} WHERE user_id = %d",
            $user_id
        ));
        return in_array($status, ['active', 'trialing'], true);
    }
}
