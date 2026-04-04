<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'teinformez_juridic_qa';

// Handle form submission
if (isset($_POST['juridic_action']) && wp_verify_nonce($_POST['_juridic_nonce'], 'juridic_save')) {
    $action = sanitize_text_field($_POST['juridic_action']);

    if ($action === 'save') {
        $data = [
            'question' => sanitize_textarea_field($_POST['question']),
            'question_anonymized' => sanitize_textarea_field($_POST['question_anonymized']),
            'answer' => wp_kses_post($_POST['answer']),
            'answer_summary' => sanitize_textarea_field($_POST['answer_summary']),
            'category' => sanitize_text_field($_POST['category']),
            'subcategory' => sanitize_text_field($_POST['subcategory']),
            'tags' => wp_json_encode(array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['tags']))))),
            'is_weekly_column' => isset($_POST['is_weekly_column']) ? 1 : 0,
            'column_title' => sanitize_text_field($_POST['column_title']),
            'column_date' => sanitize_text_field($_POST['column_date']),
            'author_name' => sanitize_text_field($_POST['author_name']) ?: 'Alina',
            'fb_teaser' => sanitize_textarea_field($_POST['fb_teaser']),
            'status' => sanitize_text_field($_POST['status']),
        ];

        if ($data['status'] === 'published' && empty($_POST['edit_id'])) {
            $data['published_at'] = current_time('mysql');
        }

        if (!empty($_POST['edit_id'])) {
            $edit_id = (int) $_POST['edit_id'];
            $old = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d", $edit_id));
            if ($data['status'] === 'published' && $old && $old->status !== 'published') {
                $data['published_at'] = current_time('mysql');
            }
            $wpdb->update($table, $data, ['id' => $edit_id]);
            if ($data['status'] === 'published' && $old && $old->status !== 'published') {
                $published_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id));
                if ($published_item) {
                    do_action('teinformez_juridic_published', $published_item);
                }
            }
            echo '<div class="notice notice-success"><p>Întrebarea a fost actualizată.</p></div>';
        } else {
            $wpdb->insert($table, $data);
            if ($data['status'] === 'published') {
                $published_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id));
                if ($published_item) {
                    do_action('teinformez_juridic_published', $published_item);
                }
            }
            echo '<div class="notice notice-success"><p>Întrebarea a fost adăugată.</p></div>';
        }
    }

    if ($action === 'delete' && !empty($_POST['delete_id'])) {
        $wpdb->delete($table, ['id' => (int) $_POST['delete_id']]);
        echo '<div class="notice notice-success"><p>Întrebarea a fost ștearsă.</p></div>';
    }

    if ($action === 'publish' && !empty($_POST['publish_id'])) {
        $publish_id = (int) $_POST['publish_id'];
        $wpdb->update($table, ['status' => 'published', 'published_at' => current_time('mysql')], ['id' => $publish_id]);
        $published_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $publish_id));
        if ($published_item) {
            do_action('teinformez_juridic_published', $published_item);
        }
        echo '<div class="notice notice-success"><p>Publicat cu succes.</p></div>';
    }
}

// Load edit item if requested
$edit_item = null;
if (!empty($_GET['edit'])) {
    $edit_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $_GET['edit']));
}

// Get all items
$items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");

$categories = [
    'dreptul-muncii' => 'Dreptul muncii',
    'dreptul-familiei' => 'Dreptul familiei',
    'drept-comercial' => 'Drept comercial',
    'drept-penal' => 'Drept penal',
    'protectia-consumatorului' => 'Protecția consumatorului',
    'drept-administrativ' => 'Drept administrativ',
    'drept-imobiliar' => 'Drept imobiliar',
];
?>

<div class="wrap">
    <h1>Juridic cu Alina — Q&A</h1>

    <!-- Add/Edit Form -->
    <div style="background:#fff; padding:20px; margin:20px 0; border:1px solid #ccc; border-radius:4px;">
        <h2><?php echo $edit_item ? 'Editează întrebarea #' . $edit_item->id : 'Adaugă întrebare nouă'; ?></h2>
        <form method="post">
            <?php wp_nonce_field('juridic_save', '_juridic_nonce'); ?>
            <input type="hidden" name="juridic_action" value="save">
            <?php if ($edit_item): ?>
                <input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_item->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label>Întrebarea originală <small>(privat, admin only)</small></label></th>
                    <td><textarea name="question" rows="3" class="large-text" required><?php echo esc_textarea($edit_item->question ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Întrebare anonimizată <small>(GDPR-safe, public)</small></label></th>
                    <td><textarea name="question_anonymized" rows="3" class="large-text" required placeholder="Ex: O cititoare ne întreabă..."><?php echo esc_textarea($edit_item->question_anonymized ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Răspuns complet</label></th>
                    <td><?php
                        wp_editor(
                            $edit_item->answer ?? '',
                            'juridic_answer',
                            ['textarea_name' => 'answer', 'textarea_rows' => 10, 'media_buttons' => false]
                        );
                    ?></td>
                </tr>
                <tr>
                    <th><label>Rezumat răspuns <small>(opțional)</small></label></th>
                    <td><textarea name="answer_summary" rows="2" class="large-text"><?php echo esc_textarea($edit_item->answer_summary ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Categorie</label></th>
                    <td>
                        <select name="category" required>
                            <option value="">— Selectează —</option>
                            <?php foreach ($categories as $slug => $label): ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($edit_item->category ?? '', $slug); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Subcategorie <small>(opțional)</small></label></th>
                    <td><input type="text" name="subcategory" class="regular-text" value="<?php echo esc_attr($edit_item->subcategory ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th><label>Tag-uri <small>(separate cu virgulă)</small></label></th>
                    <td><input type="text" name="tags" class="large-text" value="<?php echo esc_attr(implode(', ', json_decode($edit_item->tags ?? '[]', true) ?: [])); ?>"></td>
                </tr>
                <tr>
                    <th><label>Autor</label></th>
                    <td><input type="text" name="author_name" class="regular-text" value="<?php echo esc_attr($edit_item->author_name ?? 'Alina'); ?>"></td>
                </tr>
                <tr>
                    <th><label>Coloana săptămânală</label></th>
                    <td>
                        <label><input type="checkbox" name="is_weekly_column" value="1" <?php checked($edit_item->is_weekly_column ?? 0, 1); ?>> Este coloana „Alina Răspunde"</label>
                    </td>
                </tr>
                <tr>
                    <th><label>Titlu coloană</label></th>
                    <td><input type="text" name="column_title" class="large-text" value="<?php echo esc_attr($edit_item->column_title ?? ''); ?>" placeholder="Ex: Alina Răspunde #12 — Concediul de maternitate"></td>
                </tr>
                <tr>
                    <th><label>Data coloană</label></th>
                    <td><input type="date" name="column_date" value="<?php echo esc_attr($edit_item->column_date ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th><label>Teaser Facebook</label></th>
                    <td><textarea name="fb_teaser" rows="2" class="large-text" placeholder="Scurt rezumat pentru postarea pe Facebook..."><?php echo esc_textarea($edit_item->fb_teaser ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Status</label></th>
                    <td>
                        <select name="status">
                            <option value="draft" <?php selected($edit_item->status ?? 'draft', 'draft'); ?>>Draft</option>
                            <option value="published" <?php selected($edit_item->status ?? '', 'published'); ?>>Publicat</option>
                            <option value="archived" <?php selected($edit_item->status ?? '', 'archived'); ?>>Arhivat</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php echo $edit_item ? 'Actualizează' : 'Salvează'; ?>">
                <?php if ($edit_item): ?>
                    <a href="<?php echo admin_url('admin.php?page=teinformez-juridic'); ?>" class="button">Anulează</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Items List -->
    <h2>Toate întrebările (<?php echo count($items); ?>)</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px">ID</th>
                <th>Întrebare</th>
                <th style="width:120px">Categorie</th>
                <th style="width:80px">Coloană</th>
                <th style="width:80px">Status</th>
                <th style="width:80px">Vizualizări</th>
                <th style="width:160px">Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="7">Nu există întrebări. Adaugă prima!</td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $item->id; ?></td>
                        <td><?php echo esc_html(mb_substr($item->question_anonymized, 0, 80)) . (mb_strlen($item->question_anonymized) > 80 ? '...' : ''); ?></td>
                        <td><?php echo esc_html($categories[$item->category] ?? $item->category); ?></td>
                        <td><?php echo $item->is_weekly_column ? '✅' : '—'; ?></td>
                        <td>
                            <span style="padding:2px 8px; border-radius:3px; font-size:12px; background:<?php
                                echo $item->status === 'published' ? '#d4edda' : ($item->status === 'draft' ? '#fff3cd' : '#f8d7da');
                            ?>;">
                                <?php echo $item->status === 'published' ? 'Publicat' : ($item->status === 'draft' ? 'Draft' : 'Arhivat'); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($item->view_count); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=teinformez-juridic&edit=' . $item->id); ?>" class="button button-small">Editează</a>
                            <?php if ($item->status === 'draft'): ?>
                                <form method="post" style="display:inline">
                                    <?php wp_nonce_field('juridic_save', '_juridic_nonce'); ?>
                                    <input type="hidden" name="juridic_action" value="publish">
                                    <input type="hidden" name="publish_id" value="<?php echo $item->id; ?>">
                                    <button type="submit" class="button button-small button-primary">Publică</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Sigur vrei să ștergi?')">
                                <?php wp_nonce_field('juridic_save', '_juridic_nonce'); ?>
                                <input type="hidden" name="juridic_action" value="delete">
                                <input type="hidden" name="delete_id" value="<?php echo $item->id; ?>">
                                <button type="submit" class="button button-small" style="color:red">Șterge</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
