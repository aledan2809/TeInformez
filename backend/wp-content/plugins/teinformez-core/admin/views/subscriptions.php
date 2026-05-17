<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'teinformez'));
}

global $wpdb;

$stripe_table = $wpdb->prefix . 'teinformez_stripe_subscriptions';
$users_table  = $wpdb->prefix . 'users';

// Handle manual tier override (POST)
$override_message = '';
if (
    isset($_POST['teinformez_sub_override_nonce'])
    && wp_verify_nonce($_POST['teinformez_sub_override_nonce'], 'teinformez_sub_override')
    && current_user_can('manage_options')
) {
    $override_user_id = (int) ($_POST['override_user_id'] ?? 0);
    $override_tier    = sanitize_key($_POST['override_tier'] ?? '');
    $allowed_tiers    = ['free', 'premium'];

    if ($override_user_id > 0 && in_array($override_tier, $allowed_tiers, true)) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$stripe_table} WHERE user_id = %d",
            $override_user_id
        ));

        if ($existing) {
            $wpdb->update(
                $stripe_table,
                ['tier' => $override_tier, 'status' => $override_tier === 'free' ? 'canceled' : 'active'],
                ['user_id' => $override_user_id],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $stripe_table,
                [
                    'user_id' => $override_user_id,
                    'tier'    => $override_tier,
                    'status'  => $override_tier === 'free' ? 'none' : 'active',
                ],
                ['%d', '%s', '%s']
            );
        }

        $override_message = sprintf(
            '<div class="notice notice-success is-dismissible"><p>Tier setat manual la <strong>%s</strong> pentru user ID %d.</p></div>',
            esc_html($override_tier),
            $override_user_id
        );
    } else {
        $override_message = '<div class="notice notice-error is-dismissible"><p>Date invalide pentru override.</p></div>';
    }
}

// Fetch all Stripe subscribers
$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stripe_table)) === $stripe_table;
$subscribers  = [];
$stats        = ['total' => 0, 'premium' => 0, 'free' => 0, 'canceled' => 0];

if ($table_exists) {
    $subscribers = $wpdb->get_results(
        "SELECT s.*, u.user_email, u.display_name
         FROM {$stripe_table} s
         LEFT JOIN {$users_table} u ON u.ID = s.user_id
         ORDER BY s.id DESC"
    );

    foreach ($subscribers as $sub) {
        $stats['total']++;
        if ($sub->tier === 'premium' && in_array($sub->status, ['active', 'trialing'], true)) {
            $stats['premium']++;
        } elseif (in_array($sub->status, ['canceled', 'past_due', 'unpaid'], true)) {
            $stats['canceled']++;
        } else {
            $stats['free']++;
        }
    }
}

$stripe_dashboard_url = 'https://dashboard.stripe.com';

?>
<div class="wrap">
    <h1 style="margin-bottom:20px;">Abonamente Stripe</h1>

    <?php echo $override_message; ?>

    <div style="display:flex;gap:16px;margin-bottom:28px;flex-wrap:wrap;">
        <?php
        $cards = [
            ['label' => 'Total înregistrați', 'value' => $stats['total'], 'color' => '#2271b1'],
            ['label' => 'Premium activi',      'value' => $stats['premium'], 'color' => '#00a32a'],
            ['label' => 'Anulați',             'value' => $stats['canceled'], 'color' => '#d63638'],
            ['label' => 'Tier Free',           'value' => $stats['free'], 'color' => '#8c8f94'],
        ];
        foreach ($cards as $c) : ?>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:18px 24px;min-width:160px;">
            <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr($c['color']); ?>;">
                <?php echo (int) $c['value']; ?>
            </div>
            <div style="font-size:13px;color:#50575e;margin-top:4px;"><?php echo esc_html($c['label']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$table_exists) : ?>
        <div class="notice notice-warning"><p>Tabelul <code>wp_teinformez_stripe_subscriptions</code> nu există. Activează sau reactivează plugin-ul.</p></div>
    <?php elseif (empty($subscribers)) : ?>
        <p>Niciun abonat înregistrat încă.</p>
    <?php else : ?>

    <table class="widefat striped" style="margin-bottom:30px;">
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>Tier</th>
                <th>Status</th>
                <th>Perioadă curentă (end)</th>
                <th>Stripe Subscription</th>
                <th>Stripe Customer</th>
                <th>Override</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($subscribers as $sub) :
            $period_end = $sub->current_period_end
                ? date('d.m.Y H:i', (int) strtotime((string) $sub->current_period_end))
                : '—';

            $tier_badge_color = $sub->tier === 'premium' ? '#00a32a' : '#8c8f94';
            $status_colors = [
                'active'    => '#00a32a',
                'trialing'  => '#2271b1',
                'canceled'  => '#d63638',
                'past_due'  => '#dba617',
                'unpaid'    => '#d63638',
                'none'      => '#8c8f94',
            ];
            $status_color = $status_colors[$sub->status] ?? '#50575e';

            $sub_link = $sub->stripe_subscription_id
                ? sprintf(
                    '<a href="%s/subscriptions/%s" target="_blank" rel="noopener">%s ↗</a>',
                    $stripe_dashboard_url,
                    esc_attr($sub->stripe_subscription_id),
                    esc_html($sub->stripe_subscription_id)
                  )
                : '—';

            $cus_link = $sub->stripe_customer_id
                ? sprintf(
                    '<a href="%s/customers/%s" target="_blank" rel="noopener">%s ↗</a>',
                    $stripe_dashboard_url,
                    esc_attr($sub->stripe_customer_id),
                    esc_html($sub->stripe_customer_id)
                  )
                : '—';
        ?>
        <tr>
            <td>
                <?php if ($sub->user_id) : ?>
                    <a href="<?php echo esc_url(get_edit_user_link($sub->user_id)); ?>">
                        <?php echo esc_html($sub->display_name ?: 'User #' . $sub->user_id); ?>
                    </a>
                <?php else : ?>
                    <em style="color:#8c8f94;">sters</em>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($sub->user_email ?: '—'); ?></td>
            <td>
                <span style="background:<?php echo esc_attr($tier_badge_color); ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;font-weight:600;text-transform:uppercase;">
                    <?php echo esc_html($sub->tier); ?>
                </span>
            </td>
            <td>
                <span style="color:<?php echo esc_attr($status_color); ?>;font-weight:600;">
                    <?php echo esc_html($sub->status); ?>
                </span>
                <?php if ((int) $sub->cancel_at_period_end === 1) : ?>
                    <br><small style="color:#dba617;">⚠ anulează la scadență</small>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($period_end); ?></td>
            <td style="font-size:12px;"><?php echo $sub_link; ?></td>
            <td style="font-size:12px;"><?php echo $cus_link; ?></td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Schimbi manual tier-ul? Această acțiune nu afectează Stripe.');">
                    <?php wp_nonce_field('teinformez_sub_override', 'teinformez_sub_override_nonce'); ?>
                    <input type="hidden" name="override_user_id" value="<?php echo (int) $sub->user_id; ?>">
                    <select name="override_tier" style="font-size:12px;padding:2px 4px;">
                        <option value="free"    <?php selected($sub->tier, 'free'); ?>>free</option>
                        <option value="premium" <?php selected($sub->tier, 'premium'); ?>>premium</option>
                    </select>
                    <button type="submit" class="button button-small" style="margin-left:4px;">Setează</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>

    <p style="color:#8c8f94;font-size:12px;">
        Override-ul manual modifică doar baza de date locală. Pentru anulări, rambursări sau modificări de abonament folosește
        <a href="<?php echo esc_url($stripe_dashboard_url); ?>" target="_blank" rel="noopener">Stripe Dashboard ↗</a>.
    </p>
</div>
