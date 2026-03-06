<?php
if (!defined('ABSPATH')) exit;

$saved_order = get_option('teinformez_category_order', []);

// Default categories (same as frontend)
$all_categories = [
    ['slug' => 'toate', 'label' => 'Toate', 'emoji' => '&#x1F4F0;'],
    ['slug' => 'juridic', 'label' => 'Juridic cu Alina', 'emoji' => '&#x1F4CB;'],
    ['slug' => 'actualitate', 'label' => 'Actualitate', 'emoji' => '&#x1F4F0;'],
    ['slug' => 'politics', 'label' => 'Politic&#259;', 'emoji' => '&#x1F3DB;'],
    ['slug' => 'international', 'label' => 'Interna&#539;ional', 'emoji' => '&#x1F30D;'],
    ['slug' => 'justitie', 'label' => 'Justi&#539;ie', 'emoji' => '&#x2696;'],
    ['slug' => 'business', 'label' => 'Business', 'emoji' => '&#x1F4CA;'],
    ['slug' => 'finance', 'label' => 'Finan&#539;e', 'emoji' => '&#x1F4B0;'],
    ['slug' => 'tech', 'label' => 'Tehnologie', 'emoji' => '&#x1F4BB;'],
    ['slug' => 'sanatate', 'label' => 'S&#259;n&#259;tate', 'emoji' => '&#x1F3E5;'],
    ['slug' => 'science', 'label' => '&#536;tiin&#539;&#259;', 'emoji' => '&#x1F52C;'],
    ['slug' => 'sports', 'label' => 'Sport', 'emoji' => '&#x26BD;'],
    ['slug' => 'entertainment', 'label' => 'Divertisment', 'emoji' => '&#x1F3AC;'],
    ['slug' => 'auto', 'label' => 'Auto', 'emoji' => '&#x1F697;'],
    ['slug' => 'lifestyle', 'label' => 'Lifestyle', 'emoji' => '&#x2728;'],
    ['slug' => 'opinii', 'label' => 'Opinii', 'emoji' => '&#x1F4AC;'],
];

// Sort by saved order
if (!empty($saved_order)) {
    $order_map = array_flip($saved_order);
    usort($all_categories, function($a, $b) use ($order_map) {
        $ia = isset($order_map[$a['slug']]) ? $order_map[$a['slug']] : 999;
        $ib = isset($order_map[$b['slug']]) ? $order_map[$b['slug']] : 999;
        return $ia - $ib;
    });
}
?>
<div class="wrap">
    <h1>Ordine Categorii</h1>
    <p>Trage categoriile pentru a schimba ordinea in care apar pe site pentru toti vizitatorii.</p>

    <?php settings_errors('teinformez_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('teinformez_save_catorder', 'teinformez_catorder_nonce'); ?>
        <input type="hidden" name="category_order" id="category_order_input" value="<?php echo esc_attr(implode(',', array_column($all_categories, 'slug'))); ?>">

        <table class="wp-list-table widefat fixed striped" style="max-width: 600px;">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 40px;"></th>
                    <th>Categorie</th>
                    <th style="width: 100px;">Slug</th>
                </tr>
            </thead>
            <tbody id="sortable-categories">
                <?php foreach ($all_categories as $i => $cat): ?>
                <tr data-slug="<?php echo esc_attr($cat['slug']); ?>" style="cursor: grab;">
                    <td class="row-number" style="color: #999; font-weight: bold;"><?php echo $i + 1; ?></td>
                    <td style="cursor: grab; text-align: center; font-size: 16px;">&#x2630;</td>
                    <td>
                        <span style="font-size: 18px; margin-right: 6px;"><?php echo $cat['emoji']; ?></span>
                        <strong><?php echo $cat['label']; ?></strong>
                    </td>
                    <td><code style="font-size: 12px;"><?php echo esc_html($cat['slug']); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Salveaz&#259; ordinea">
        </p>
    </form>
</div>

<script>
(function() {
    var tbody = document.getElementById('sortable-categories');
    var input = document.getElementById('category_order_input');
    var dragRow = null;

    function updateOrder() {
        var rows = tbody.querySelectorAll('tr');
        var slugs = [];
        rows.forEach(function(row, i) {
            slugs.push(row.getAttribute('data-slug'));
            row.querySelector('.row-number').textContent = (i + 1);
        });
        input.value = slugs.join(',');
    }

    tbody.addEventListener('dragstart', function(e) {
        dragRow = e.target.closest('tr');
        if (dragRow) {
            dragRow.style.opacity = '0.4';
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var target = e.target.closest('tr');
        if (target && target !== dragRow && target.parentNode === tbody) {
            var rect = target.getBoundingClientRect();
            var midY = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                tbody.insertBefore(dragRow, target);
            } else {
                tbody.insertBefore(dragRow, target.nextSibling);
            }
        }
    });

    tbody.addEventListener('dragend', function(e) {
        if (dragRow) {
            dragRow.style.opacity = '1';
            dragRow = null;
            updateOrder();
        }
    });

    // Make rows draggable
    var rows = tbody.querySelectorAll('tr');
    rows.forEach(function(row) {
        row.setAttribute('draggable', 'true');
    });
})();
</script>

<style>
#sortable-categories tr:hover {
    background: #f0f6fc !important;
}
#sortable-categories tr[draggable] {
    user-select: none;
}
</style>
