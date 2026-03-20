<?php
if (!defined('ABSPATH')) exit;

$saved_order = get_option('teinformez_category_order', []);
$hidden_categories = get_option('teinformez_hidden_categories', []);

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
    <p>Trage categoriile pentru a schimba ordinea. Debifează <strong>Vizibil</strong> pentru a ascunde o categorie de pe site.</p>

    <?php settings_errors('teinformez_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('teinformez_save_catorder', 'teinformez_catorder_nonce'); ?>
        <input type="hidden" name="category_order" id="category_order_input" value="<?php echo esc_attr(implode(',', array_column($all_categories, 'slug'))); ?>">

        <table class="wp-list-table widefat fixed striped" style="max-width: 700px;">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 40px;"></th>
                    <th>Categorie</th>
                    <th style="width: 100px;">Slug</th>
                    <th style="width: 80px; text-align: center;">Vizibil</th>
                </tr>
            </thead>
            <tbody id="sortable-categories">
                <?php foreach ($all_categories as $i => $cat):
                    $is_hidden = in_array($cat['slug'], $hidden_categories);
                ?>
                <tr data-slug="<?php echo esc_attr($cat['slug']); ?>" style="cursor: grab;<?php echo $is_hidden ? ' opacity: 0.5;' : ''; ?>">
                    <td class="row-number" style="color: #999; font-weight: bold;"><?php echo $i + 1; ?></td>
                    <td style="cursor: grab; text-align: center; font-size: 16px;">&#x2630;</td>
                    <td>
                        <span style="font-size: 18px; margin-right: 6px;"><?php echo $cat['emoji']; ?></span>
                        <strong class="cat-label"><?php echo $cat['label']; ?></strong>
                    </td>
                    <td><code style="font-size: 12px;"><?php echo esc_html($cat['slug']); ?></code></td>
                    <td style="text-align: center;">
                        <input
                            type="checkbox"
                            name="visible_categories[]"
                            value="<?php echo esc_attr($cat['slug']); ?>"
                            class="visibility-toggle"
                            <?php echo !$is_hidden ? 'checked' : ''; ?>
                            style="width: 18px; height: 18px; cursor: pointer;"
                        >
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Hidden inputs for hidden categories (populated by JS) -->
        <div id="hidden-categories-container"></div>

        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Salveaz&#259;">
        </p>
    </form>
</div>

<script>
(function() {
    var tbody = document.getElementById('sortable-categories');
    var orderInput = document.getElementById('category_order_input');
    var hiddenContainer = document.getElementById('hidden-categories-container');
    var dragRow = null;

    function updateOrder() {
        var rows = tbody.querySelectorAll('tr');
        var slugs = [];
        rows.forEach(function(row, i) {
            slugs.push(row.getAttribute('data-slug'));
            row.querySelector('.row-number').textContent = (i + 1);
        });
        orderInput.value = slugs.join(',');
    }

    function updateHiddenInputs() {
        hiddenContainer.innerHTML = '';
        var checkboxes = tbody.querySelectorAll('.visibility-toggle');
        checkboxes.forEach(function(cb) {
            if (!cb.checked) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'hidden_categories[]';
                input.value = cb.value;
                hiddenContainer.appendChild(input);
            }
        });
    }

    // Toggle row opacity on checkbox change
    tbody.addEventListener('change', function(e) {
        if (e.target.classList.contains('visibility-toggle')) {
            var row = e.target.closest('tr');
            row.style.opacity = e.target.checked ? '1' : '0.5';
            updateHiddenInputs();
        }
    });

    // Initialize hidden inputs
    updateHiddenInputs();

    // Drag and drop
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
            // Restore opacity based on visibility checkbox
            var cb = dragRow.querySelector('.visibility-toggle');
            dragRow.style.opacity = cb && cb.checked ? '1' : '0.5';
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
#sortable-categories .visibility-toggle {
    accent-color: #2271b1;
}
</style>
