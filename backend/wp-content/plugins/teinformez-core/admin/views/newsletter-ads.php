<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'teinformez'));
}

global $wpdb;
$table = $wpdb->prefix . 'teinformez_newsletter_ads';

// Handle POST actions
if (isset($_POST['teinformez_newsletter_ads_nonce']) && wp_verify_nonce($_POST['teinformez_newsletter_ads_nonce'], 'teinformez_newsletter_ads')) {
    $action = sanitize_key($_POST['ads_action'] ?? '');

    if ($action === 'save') {
        $id           = isset($_POST['ad_id']) ? (int) $_POST['ad_id'] : 0;
        $sponsor_name = sanitize_text_field($_POST['sponsor_name'] ?? '');
        $banner_html  = wp_kses_post(wp_unslash($_POST['banner_html'] ?? ''));
        $start        = sanitize_text_field($_POST['campaign_start'] ?? '');
        $end          = sanitize_text_field($_POST['campaign_end'] ?? '');
        $status       = in_array($_POST['status'] ?? '', ['active', 'paused'], true) ? $_POST['status'] : 'active';

        if ($sponsor_name && $banner_html && $start && $end) {
            $data = [
                'sponsor_name'    => $sponsor_name,
                'banner_html'     => $banner_html,
                'campaign_start'  => $start,
                'campaign_end'    => $end,
                'status'          => $status,
            ];
            $fmt = ['%s', '%s', '%s', '%s', '%s'];
            if ($id > 0) {
                $wpdb->update($table, $data, ['id' => $id], $fmt, ['%d']);
                $notice = __('Campania a fost actualizată.', 'teinformez');
            } else {
                $wpdb->insert($table, $data, $fmt);
                $notice = __('Campania a fost adăugată.', 'teinformez');
            }
        } else {
            $error = __('Toate câmpurile sunt obligatorii.', 'teinformez');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['ad_id'] ?? 0);
        if ($id > 0) {
            $wpdb->delete($table, ['id' => $id], ['%d']);
            $notice = __('Campania a fost ștearsă.', 'teinformez');
        }
    } elseif ($action === 'toggle_status') {
        $id         = (int) ($_POST['ad_id'] ?? 0);
        $new_status = sanitize_key($_POST['new_status'] ?? '');
        if ($id > 0 && in_array($new_status, ['active', 'paused'], true)) {
            $wpdb->update($table, ['status' => $new_status], ['id' => $id], ['%s'], ['%d']);
            $notice = $new_status === 'active'
                ? __('Campania a fost activată.', 'teinformez')
                : __('Campania a fost pausată.', 'teinformez');
        }
    }
}

// Pause toggle (kill switch for all newsletter sends)
if (isset($_POST['teinformez_pause_nonce']) && wp_verify_nonce($_POST['teinformez_pause_nonce'], 'teinformez_pause_toggle')) {
    $new_paused = isset($_POST['pause_value']) && $_POST['pause_value'] === '1' ? 1 : 0;
    update_option('teinformez_newsletter_paused', $new_paused);
    $notice = $new_paused === 1
        ? __('Newsletter sends PAUSED. Cron-ul nu va mai trimite digest-uri până la resume.', 'teinformez')
        : __('Newsletter sends RESUMED. Cron-ul va reîncepe digest delivery.', 'teinformez');
}
$is_paused = (int) get_option('teinformez_newsletter_paused', 0) === 1;

// Edit mode: load existing row
$editing = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $edit_id = (int) $_GET['id'];
    $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id));
}

// Fetch all campaigns
$campaigns = $wpdb->get_results("SELECT * FROM {$table} ORDER BY campaign_start DESC");

$today = date('Y-m-d');
?>
<div class="wrap">
    <h1><?php esc_html_e('Newsletter Ads — Campanii Sponsorizate', 'teinformez'); ?></h1>

    <?php if (!empty($notice)): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- Newsletter pause kill switch -->
    <div style="background:<?php echo $is_paused ? '#fef3c7' : '#f0fdf4'; ?>;border:2px solid <?php echo $is_paused ? '#f59e0b' : '#16a34a'; ?>;border-radius:8px;padding:16px 20px;margin:20px 0;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div>
                <p style="margin:0 0 4px;font-weight:700;font-size:15px;color:<?php echo $is_paused ? '#92400e' : '#15803d'; ?>;">
                    <?php if ($is_paused): ?>
                        ⏸ <?php esc_html_e('Newsletter sends — PAUSED', 'teinformez'); ?>
                    <?php else: ?>
                        ▶ <?php esc_html_e('Newsletter sends — ACTIVE', 'teinformez'); ?>
                    <?php endif; ?>
                </p>
                <p style="margin:0;font-size:13px;color:<?php echo $is_paused ? '#78350f' : '#166534'; ?>;line-height:1.5;">
                    <?php if ($is_paused): ?>
                        <?php esc_html_e('Cron-ul de digest delivery e oprit. Niciun email nu va fi trimis. Resume când integrarea cu MA (CAS — Carousel of Ads) e gata.', 'teinformez'); ?>
                    <?php else: ?>
                        <?php esc_html_e('Cron rulează la 15min. Useri abonați primesc digest-uri conform schedule.', 'teinformez'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <form method="post" style="margin:0;">
                <?php wp_nonce_field('teinformez_pause_toggle', 'teinformez_pause_nonce'); ?>
                <input type="hidden" name="pause_value" value="<?php echo $is_paused ? '0' : '1'; ?>">
                <button type="submit" class="button <?php echo $is_paused ? 'button-primary' : ''; ?>" style="font-weight:600;">
                    <?php echo $is_paused ? esc_html__('Resume sends', 'teinformez') : esc_html__('Pause sends', 'teinformez'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Campaign list -->
    <h2><?php esc_html_e('Campanii existente', 'teinformez'); ?></h2>
    <?php if (empty($campaigns)): ?>
        <p><?php esc_html_e('Nu există campanii. Adaugă prima campanie mai jos.', 'teinformez'); ?></p>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:180px"><?php esc_html_e('Sponsor', 'teinformez'); ?></th>
                <th style="width:110px"><?php esc_html_e('Start', 'teinformez'); ?></th>
                <th style="width:110px"><?php esc_html_e('End', 'teinformez'); ?></th>
                <th style="width:90px"><?php esc_html_e('Status', 'teinformez'); ?></th>
                <th style="width:90px"><?php esc_html_e('Impressii', 'teinformez'); ?></th>
                <th style="width:80px"><?php esc_html_e('Activ azi', 'teinformez'); ?></th>
                <th><?php esc_html_e('Acțiuni', 'teinformez'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campaigns as $row): ?>
            <?php
            $is_today = ($row->status === 'active' && $row->campaign_start <= $today && $row->campaign_end >= $today);
            $toggle_status = $row->status === 'active' ? 'paused' : 'active';
            $toggle_label  = $row->status === 'active'
                ? esc_html__('Pausează', 'teinformez')
                : esc_html__('Activează', 'teinformez');
            ?>
            <tr>
                <td><?php echo esc_html($row->sponsor_name); ?></td>
                <td><?php echo esc_html($row->campaign_start); ?></td>
                <td><?php echo esc_html($row->campaign_end); ?></td>
                <td>
                    <?php if ($row->status === 'active'): ?>
                        <span style="color:#16a34a;font-weight:bold">&#9679; Activ</span>
                    <?php else: ?>
                        <span style="color:#9ca3af">&#9679; Pausat</span>
                    <?php endif; ?>
                </td>
                <td><?php echo (int) $row->impressions; ?></td>
                <td><?php echo $is_today ? '<span style="color:#2563eb;font-weight:bold">&#10003; DA</span>' : '&mdash;'; ?></td>
                <td>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'teinformez-newsletter-ads', 'action' => 'edit', 'id' => $row->id], admin_url('admin.php'))); ?>" class="button button-small"><?php esc_html_e('Editează', 'teinformez'); ?></a>
                    &nbsp;
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field('teinformez_newsletter_ads', 'teinformez_newsletter_ads_nonce'); ?>
                        <input type="hidden" name="ads_action" value="toggle_status">
                        <input type="hidden" name="ad_id" value="<?php echo (int) $row->id; ?>">
                        <input type="hidden" name="new_status" value="<?php echo esc_attr($toggle_status); ?>">
                        <button type="submit" class="button button-small"><?php echo $toggle_label; ?></button>
                    </form>
                    &nbsp;
                    <form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('Ștergi această campanie?', 'teinformez'); ?>')">
                        <?php wp_nonce_field('teinformez_newsletter_ads', 'teinformez_newsletter_ads_nonce'); ?>
                        <input type="hidden" name="ads_action" value="delete">
                        <input type="hidden" name="ad_id" value="<?php echo (int) $row->id; ?>">
                        <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Șterge', 'teinformez'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <hr>

    <!-- Add / Edit form -->
    <h2><?php echo $editing ? esc_html__('Editează campania', 'teinformez') : esc_html__('Adaugă campanie nouă', 'teinformez'); ?></h2>
    <p style="color:#6b7280;font-size:13px;"><?php esc_html_e('Banner-ul HTML trebuie să fie email-safe (max 600px lățime, fără JS, atribute inline style). Va înlocui blocul de promo intern în zilele din intervalul campaniei.', 'teinformez'); ?></p>

    <form method="post" style="max-width:700px">
        <?php wp_nonce_field('teinformez_newsletter_ads', 'teinformez_newsletter_ads_nonce'); ?>
        <input type="hidden" name="ads_action" value="save">
        <input type="hidden" name="ad_id" value="<?php echo $editing ? (int) $editing->id : 0; ?>">

        <table class="form-table">
            <tr>
                <th><label for="sponsor_name"><?php esc_html_e('Nume Sponsor', 'teinformez'); ?></label></th>
                <td><input type="text" id="sponsor_name" name="sponsor_name" class="regular-text" required
                    value="<?php echo $editing ? esc_attr($editing->sponsor_name) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label for="campaign_start"><?php esc_html_e('Data Start', 'teinformez'); ?></label></th>
                <td><input type="date" id="campaign_start" name="campaign_start" required
                    value="<?php echo $editing ? esc_attr($editing->campaign_start) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label for="campaign_end"><?php esc_html_e('Data End', 'teinformez'); ?></label></th>
                <td><input type="date" id="campaign_end" name="campaign_end" required
                    value="<?php echo $editing ? esc_attr($editing->campaign_end) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label for="status"><?php esc_html_e('Status', 'teinformez'); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <option value="active" <?php selected($editing ? $editing->status : 'active', 'active'); ?>><?php esc_html_e('Activ', 'teinformez'); ?></option>
                        <option value="paused" <?php selected($editing ? $editing->status : '', 'paused'); ?>><?php esc_html_e('Pausat', 'teinformez'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="banner_html"><?php esc_html_e('Banner HTML', 'teinformez'); ?></label></th>
                <td>
                    <textarea id="banner_html" name="banner_html" rows="8" class="large-text" required
                        placeholder='<div style="background:#fff;border:2px solid #e5e7eb;border-radius:8px;padding:16px 24px;margin-top:16px;">&#10;  <p style="margin:0 0 4px;font-size:10px;font-weight:bold;color:#9ca3af;text-transform:uppercase;">(Partener) &middot; NumeSponsor</p>&#10;  <h3 style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#111827;">Titlu reclamă</h3>&#10;  <p style="margin:0 0 12px;font-size:13px;color:#4b5563;line-height:1.4;">Descriere scurtă.</p>&#10;  <a href="https://example.com" style="display:inline-block;background:#2563eb;color:#fff;padding:8px 18px;text-decoration:none;border-radius:6px;font-size:13px;font-weight:bold;">CTA Buton</a>&#10;</div>'><?php echo $editing ? esc_textarea($editing->banner_html) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('HTML email-safe. Folosiți atribute style inline. Nu folosiți JS sau resurse externe.', 'teinformez'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button($editing ? __('Actualizează campania', 'teinformez') : __('Adaugă campania', 'teinformez')); ?>
        <?php if ($editing): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'teinformez-newsletter-ads'], admin_url('admin.php'))); ?>" class="button"><?php esc_html_e('Anulează', 'teinformez'); ?></a>
        <?php endif; ?>
    </form>
</div>
