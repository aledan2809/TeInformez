<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized', 'teinformez'));
}

// Config stored as JSON array in wp_options.
// Each entry: {category_slug, provider_name, cta_label, url, logo_url, active}
$option_key = 'teinformez_affiliate_config';

// Handle POST
$notice = $error = '';
if (isset($_POST['teinformez_affiliates_nonce']) && wp_verify_nonce($_POST['teinformez_affiliates_nonce'], 'teinformez_affiliates')) {
    $action = sanitize_key($_POST['aff_action'] ?? '');
    $entries = json_decode(get_option($option_key, '[]'), true);
    if (!is_array($entries)) {
        $entries = [];
    }

    if ($action === 'save') {
        $idx           = isset($_POST['aff_index']) && $_POST['aff_index'] !== '' ? (int) $_POST['aff_index'] : -1;
        $category_slug = sanitize_key($_POST['category_slug'] ?? '');
        $provider_name = sanitize_text_field($_POST['provider_name'] ?? '');
        $cta_label     = sanitize_text_field($_POST['cta_label'] ?? 'Deschide cont');
        $url           = esc_url_raw($_POST['url'] ?? '');
        $logo_url      = esc_url_raw($_POST['logo_url'] ?? '');
        $active        = !empty($_POST['active']);

        if ($category_slug && $provider_name && $url) {
            $row = [
                'category_slug' => $category_slug,
                'provider_name' => $provider_name,
                'cta_label'     => $cta_label ?: 'Deschide cont',
                'url'           => $url,
                'logo_url'      => $logo_url,
                'active'        => $active,
            ];
            if ($idx >= 0 && isset($entries[$idx])) {
                $entries[$idx] = $row;
                $notice = __('Linkul afiliat a fost actualizat.', 'teinformez');
            } else {
                $entries[] = $row;
                $notice = __('Linkul afiliat a fost adăugat.', 'teinformez');
            }
            update_option($option_key, wp_json_encode($entries));
        } else {
            $error = __('Categoria, numele furnizorului și URL-ul sunt obligatorii.', 'teinformez');
        }
    } elseif ($action === 'delete') {
        $idx = (int) ($_POST['aff_index'] ?? -1);
        if ($idx >= 0 && isset($entries[$idx])) {
            array_splice($entries, $idx, 1);
            update_option($option_key, wp_json_encode(array_values($entries)));
            $notice = __('Linkul afiliat a fost șters.', 'teinformez');
        }
    } elseif ($action === 'toggle') {
        $idx = (int) ($_POST['aff_index'] ?? -1);
        if ($idx >= 0 && isset($entries[$idx])) {
            $entries[$idx]['active'] = !$entries[$idx]['active'];
            update_option($option_key, wp_json_encode(array_values($entries)));
            $notice = $entries[$idx]['active']
                ? __('Afiliat activat.', 'teinformez')
                : __('Afiliat dezactivat.', 'teinformez');
        }
    }
}

// Edit mode
$editing_idx = -1;
$editing     = null;
if (isset($_GET['action'], $_GET['idx']) && $_GET['action'] === 'edit') {
    $editing_idx = (int) $_GET['idx'];
    $all = json_decode(get_option($option_key, '[]'), true);
    if (is_array($all) && isset($all[$editing_idx])) {
        $editing = $all[$editing_idx];
    }
}

// Fetch all entries
$all_entries = json_decode(get_option($option_key, '[]'), true);
if (!is_array($all_entries)) {
    $all_entries = [];
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Affiliate Links — Categorii', 'teinformez'); ?></h1>
    <p style="color:#6b7280;font-size:13px"><?php esc_html_e('Asociezi o categorie de știri cu un partener comercial. Linkul apare în sidebar-ul articolelor din acea categorie, separat de conținutul editorial.', 'teinformez'); ?></p>

    <?php if ($notice): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <!-- List -->
    <h2><?php esc_html_e('Afiliați configurați', 'teinformez'); ?></h2>
    <?php if (empty($all_entries)): ?>
        <p><?php esc_html_e('Niciun afiliat configurat. Adaugă primul mai jos.', 'teinformez'); ?></p>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:130px"><?php esc_html_e('Categorie', 'teinformez'); ?></th>
                <th><?php esc_html_e('Furnizor', 'teinformez'); ?></th>
                <th><?php esc_html_e('CTA', 'teinformez'); ?></th>
                <th><?php esc_html_e('URL', 'teinformez'); ?></th>
                <th style="width:90px"><?php esc_html_e('Status', 'teinformez'); ?></th>
                <th><?php esc_html_e('Acțiuni', 'teinformez'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($all_entries as $i => $entry): ?>
            <tr>
                <td><code><?php echo esc_html($entry['category_slug']); ?></code></td>
                <td><?php echo esc_html($entry['provider_name']); ?></td>
                <td><?php echo esc_html($entry['cta_label'] ?? 'Deschide cont'); ?></td>
                <td><a href="<?php echo esc_url($entry['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_parse_url($entry['url'], PHP_URL_HOST) ?: $entry['url']); ?></a></td>
                <td>
                    <?php if (!empty($entry['active'])): ?>
                        <span style="color:#16a34a;font-weight:bold">&#9679; Activ</span>
                    <?php else: ?>
                        <span style="color:#9ca3af">&#9679; Inactiv</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'teinformez-affiliates', 'action' => 'edit', 'idx' => $i], admin_url('admin.php'))); ?>" class="button button-small"><?php esc_html_e('Editează', 'teinformez'); ?></a>
                    &nbsp;
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field('teinformez_affiliates', 'teinformez_affiliates_nonce'); ?>
                        <input type="hidden" name="aff_action" value="toggle">
                        <input type="hidden" name="aff_index" value="<?php echo (int) $i; ?>">
                        <button type="submit" class="button button-small"><?php echo !empty($entry['active']) ? esc_html__('Dezactivează', 'teinformez') : esc_html__('Activează', 'teinformez'); ?></button>
                    </form>
                    &nbsp;
                    <form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e('Ștergi acest afiliat?', 'teinformez'); ?>')">
                        <?php wp_nonce_field('teinformez_affiliates', 'teinformez_affiliates_nonce'); ?>
                        <input type="hidden" name="aff_action" value="delete">
                        <input type="hidden" name="aff_index" value="<?php echo (int) $i; ?>">
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
    <h2><?php echo ($editing !== null) ? esc_html__('Editează afiliat', 'teinformez') : esc_html__('Adaugă afiliat nou', 'teinformez'); ?></h2>

    <form method="post" style="max-width:700px">
        <?php wp_nonce_field('teinformez_affiliates', 'teinformez_affiliates_nonce'); ?>
        <input type="hidden" name="aff_action" value="save">
        <input type="hidden" name="aff_index" value="<?php echo ($editing !== null) ? (int) $editing_idx : ''; ?>">

        <table class="form-table">
            <tr>
                <th><label for="category_slug"><?php esc_html_e('Slug categorie', 'teinformez'); ?></label></th>
                <td>
                    <input type="text" id="category_slug" name="category_slug" class="regular-text" required
                        placeholder="ex: finance, sanatate, tech"
                        value="<?php echo ($editing !== null) ? esc_attr($editing['category_slug']) : ''; ?>">
                    <p class="description"><?php esc_html_e('Slug-ul exact al categoriei (lowercase, fără diacritice).', 'teinformez'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="provider_name"><?php esc_html_e('Nume furnizor', 'teinformez'); ?></label></th>
                <td><input type="text" id="provider_name" name="provider_name" class="regular-text" required
                    placeholder="ex: Banca Transilvania"
                    value="<?php echo ($editing !== null) ? esc_attr($editing['provider_name']) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label for="cta_label"><?php esc_html_e('Text CTA', 'teinformez'); ?></label></th>
                <td><input type="text" id="cta_label" name="cta_label" class="regular-text"
                    placeholder="Deschide cont"
                    value="<?php echo ($editing !== null) ? esc_attr($editing['cta_label'] ?? 'Deschide cont') : 'Deschide cont'; ?>">
                    <p class="description"><?php esc_html_e('Textul butonului CTA (implicit: Deschide cont).', 'teinformez'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="url"><?php esc_html_e('URL afiliat', 'teinformez'); ?></label></th>
                <td><input type="url" id="url" name="url" class="large-text" required
                    placeholder="https://..."
                    value="<?php echo ($editing !== null) ? esc_url($editing['url']) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label for="logo_url"><?php esc_html_e('URL logo (opțional)', 'teinformez'); ?></label></th>
                <td><input type="url" id="logo_url" name="logo_url" class="large-text"
                    placeholder="https://..."
                    value="<?php echo ($editing !== null) ? esc_url($editing['logo_url'] ?? '') : ''; ?>">
                    <p class="description"><?php esc_html_e('Logo-ul furnizorului afișat în widget (opțional).', 'teinformez'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Activ', 'teinformez'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="active" value="1" <?php checked(($editing !== null) ? !empty($editing['active']) : true); ?>>
                        <?php esc_html_e('Afișează widgetul în articolele din această categorie', 'teinformez'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(($editing !== null) ? __('Actualizează', 'teinformez') : __('Adaugă afiliat', 'teinformez')); ?>
        <?php if ($editing !== null): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'teinformez-affiliates'], admin_url('admin.php'))); ?>" class="button"><?php esc_html_e('Anulează', 'teinformez'); ?></a>
        <?php endif; ?>
    </form>

    <hr>
    <h2><?php esc_html_e('Cum funcționează', 'teinformez'); ?></h2>
    <div style="background:#f8fafc;border-radius:8px;padding:16px 20px;font-size:13px;color:#374151;line-height:1.7;">
        <ul style="margin:0;padding-left:18px;">
            <li><?php esc_html_e('Un singur afiliat activ per categorie. Dacă ai mai mulți pe aceeași categorie, primul din listă este afișat.', 'teinformez'); ?></li>
            <li><?php esc_html_e('Widgetul apare în articolele publice din categoria respectivă, sub conținut, cu eticheta clară „Partener comercial" — separat de editorial.', 'teinformez'); ?></li>
            <li><?php esc_html_e('Linkul afiliat se deschide în tab nou cu rel="noopener noreferrer sponsored".', 'teinformez'); ?></li>
        </ul>
    </div>
</div>
