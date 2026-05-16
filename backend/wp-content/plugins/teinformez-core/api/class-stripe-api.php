<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stripe Subscription API
 * - GET  /teinformez/v1/subscription/status  — current user's tier + expiry (public auth)
 * - POST /teinformez/v1/stripe/internal-sync  — called by Next.js webhook handler (shared secret)
 */
class Stripe_API {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('teinformez/v1', '/subscription/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_subscription_status'],
            'permission_callback' => [$this, 'require_auth'],
        ]);

        register_rest_route('teinformez/v1', '/stripe/internal-sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'internal_sync'],
            'permission_callback' => [$this, 'require_internal_secret'],
        ]);
    }

    // ── Permissions ──────────────────────────────────────────────────────

    public function require_auth($request) {
        return is_user_logged_in();
    }

    public function require_internal_secret($request) {
        $secret = defined('TEINFORMEZ_STRIPE_SYNC_SECRET') ? TEINFORMEZ_STRIPE_SYNC_SECRET : get_option('teinformez_stripe_sync_secret', '');
        if (empty($secret)) {
            return false;
        }
        $header = $request->get_header('x-internal-secret');
        return hash_equals($secret, (string) $header);
    }

    // ── GET /subscription/status ─────────────────────────────────────────

    public function get_subscription_status($request) {
        global $wpdb;
        $user_id = get_current_user_id();

        $table = $wpdb->prefix . 'teinformez_stripe_subscriptions';
        $row   = $wpdb->get_row($wpdb->prepare(
            "SELECT tier, status, current_period_end, cancel_at_period_end, stripe_subscription_id FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        if (!$row) {
            return rest_ensure_response([
                'tier'               => 'free',
                'status'             => 'none',
                'current_period_end' => null,
                'cancel_at_period_end' => false,
            ]);
        }

        $is_active = in_array($row->status, ['active', 'trialing'], true);

        return rest_ensure_response([
            'tier'               => $is_active ? $row->tier : 'free',
            'status'             => $row->status,
            'current_period_end' => $row->current_period_end,
            'cancel_at_period_end' => (bool) $row->cancel_at_period_end,
        ]);
    }

    // ── POST /stripe/internal-sync ────────────────────────────────────────

    public function internal_sync($request) {
        global $wpdb;
        $body = $request->get_json_params();

        $event = sanitize_text_field($body['event']      ?? '');
        $email = sanitize_email($body['customer_email']  ?? '');

        if (empty($event) || empty($email)) {
            return new \WP_Error('bad_request', 'Missing event or customer_email', ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            // User not found — still return 200 to avoid webhook retries on non-subscribers
            return rest_ensure_response(['ok' => true, 'note' => 'user_not_found']);
        }

        $user_id = $user->ID;
        $table   = $wpdb->prefix . 'teinformez_stripe_subscriptions';

        $customer_id     = sanitize_text_field($body['customer_id']     ?? '');
        $subscription_id = sanitize_text_field($body['subscription_id'] ?? '');
        $price_id        = sanitize_text_field($body['price_id']        ?? '');
        $status          = sanitize_text_field($body['status']          ?? 'active');
        $period_end      = !empty($body['current_period_end'])
            ? date('Y-m-d H:i:s', (int) $body['current_period_end'])
            : null;
        $cancel_at_end   = (bool) ($body['cancel_at_period_end'] ?? false);

        $allowed_statuses = ['active', 'canceled', 'past_due', 'trialing', 'incomplete', 'incomplete_expired', 'unpaid', 'paused'];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'active';
        }

        $tier = 'free';
        if (in_array($event, ['checkout.session.completed', 'customer.subscription.updated'], true)) {
            $tier = 'premium';
        }
        if ($event === 'customer.subscription.deleted') {
            $tier   = 'free';
            $status = 'canceled';
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d",
            $user_id
        ));

        $data = [
            'tier'                  => $tier,
            'stripe_customer_id'    => $customer_id,
            'stripe_subscription_id'=> $subscription_id,
            'stripe_price_id'       => $price_id,
            'status'                => $status,
            'current_period_end'    => $period_end,
            'cancel_at_period_end'  => $cancel_at_end ? 1 : 0,
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['user_id' => $user_id]);
        } else {
            $wpdb->insert($table, array_merge($data, ['user_id' => $user_id]));
        }

        return rest_ensure_response(['ok' => true]);
    }
}
