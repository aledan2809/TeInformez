<?php
if (!defined('ABSPATH')) {
    exit;
}

use TeInformez\News_Publisher;
use TeInformez\News_Fetcher;
use TeInformez\AI_Processor;

$publisher = new News_Publisher();

// Handle actions
$message = '';
$message_type = 'updated';

if (isset($_POST['action']) && isset($_POST['_wpnonce'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'teinformez_queue_action')) {
        $message = __('Security check failed.', 'teinformez');
        $message_type = 'error';
    } else {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

        switch ($_POST['action']) {
            case 'approve':
                if ($publisher->approve($item_id, $notes)) {
                    $message = __('News item approved.', 'teinformez');
                }
                break;
            case 'reject':
                if ($publisher->reject($item_id, $notes)) {
                    $message = __('News item rejected.', 'teinformez');
                }
                break;
            case 'fetch_now':
                $fetcher = new News_Fetcher();
                $result = $fetcher->fetch_all();
                $total = 0;
                foreach ($result as $r) {
                    if (isset($r['stored'])) {
                        $total += $r['stored'];
                    }
                }
                $message = sprintf(__('Fetched %d new items.', 'teinformez'), $total);
                break;
            case 'process_now':
                $processor = new AI_Processor();
                $result = $processor->process_queue();
                $message = sprintf(__('Processed %d items.', 'teinformez'), $result['processed']);
                break;
            case 'publish_approved':
                $result = $publisher->publish_approved();
                $message = sprintf(__('Published %d items.', 'teinformez'), $result['published']);
                break;
        }
    }
}

// Get filter parameters
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending_review';
$current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Get queue data
$queue = $publisher->get_queue([
    'status' => $current_status !== 'all' ? $current_status : null,
    'search' => $search,
    'page' => $current_page,
    'per_page' => 20
]);

$stats = $publisher->get_stats();

// Get single item if viewing/editing
$editing_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editing_item = $publisher->get_item((int)$_GET['edit']);
}
?>

<div class="wrap">
    <h1><?php _e('News Queue', 'teinformez'); ?></h1>

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="teinformez-quick-actions" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
        <form method="post" style="display: inline-flex; gap: 10px;">
            <?php wp_nonce_field('teinformez_queue_action'); ?>
            <button type="submit" name="action" value="fetch_now" class="button">
                <?php _e('Fetch News Now', 'teinformez'); ?>
            </button>
            <button type="submit" name="action" value="process_now" class="button">
                <?php _e('Process with AI', 'teinformez'); ?>
            </button>
            <button type="submit" name="action" value="publish_approved" class="button button-primary">
                <?php _e('Publish Approved', 'teinformez'); ?>
            </button>
        </form>
    </div>

    <!-- Statistics -->
    <div class="teinformez-stats" style="display: flex; gap: 15px; margin-bottom: 20px;">
        <?php
        $stat_items = [
            'fetched' => ['label' => __('Fetched', 'teinformez'), 'color' => '#6c757d'],
            'processing' => ['label' => __('Processing', 'teinformez'), 'color' => '#0dcaf0'],
            'pending_review' => ['label' => __('Pending Review', 'teinformez'), 'color' => '#ffc107'],
            'approved' => ['label' => __('Approved', 'teinformez'), 'color' => '#198754'],
            'rejected' => ['label' => __('Rejected', 'teinformez'), 'color' => '#dc3545'],
            'published' => ['label' => __('Published', 'teinformez'), 'color' => '#0d6efd']
        ];
        foreach ($stat_items as $key => $item):
        ?>
            <div style="background: <?php echo $item['color']; ?>20; border-left: 4px solid <?php echo $item['color']; ?>; padding: 10px 15px; border-radius: 0 4px 4px 0;">
                <div style="font-size: 24px; font-weight: bold; color: <?php echo $item['color']; ?>;">
                    <?php echo $stats[$key]; ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <?php echo $item['label']; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($editing_item): ?>
        <!-- Edit Item View -->
        <div class="card" style="max-width: 800px; padding: 20px;">
            <h2><?php _e('Edit News Item', 'teinformez'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('teinformez_queue_action'); ?>
                <input type="hidden" name="item_id" value="<?php echo $editing_item->id; ?>">

                <table class="form-table">
                    <tr>
                        <th><?php _e('Source', 'teinformez'); ?></th>
                        <td>
                            <strong><?php echo esc_html($editing_item->source_name); ?></strong>
                            <br>
                            <a href="<?php echo esc_url($editing_item->original_url); ?>" target="_blank">
                                <?php _e('View original', 'teinformez'); ?> ↗
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Original Title', 'teinformez'); ?></th>
                        <td><?php echo esc_html($editing_item->original_title); ?></td>
                    </tr>
                    <tr>
                        <th><label for="processed_title"><?php _e('Processed Title', 'teinformez'); ?></label></th>
                        <td>
                            <input type="text" name="processed_title" id="processed_title"
                                   value="<?php echo esc_attr($editing_item->processed_title); ?>"
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="processed_summary"><?php _e('Summary', 'teinformez'); ?></label></th>
                        <td>
                            <textarea name="processed_summary" id="processed_summary" rows="3" class="large-text"><?php
                                echo esc_textarea($editing_item->processed_summary);
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="processed_content"><?php _e('Content', 'teinformez'); ?></label></th>
                        <td>
                            <?php
                            wp_editor($editing_item->processed_content, 'processed_content', [
                                'textarea_rows' => 10,
                                'media_buttons' => false
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Categories', 'teinformez'); ?></th>
                        <td>
                            <?php echo esc_html(implode(', ', $editing_item->categories)); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Tags', 'teinformez'); ?></th>
                        <td>
                            <?php echo esc_html(implode(', ', $editing_item->tags)); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="admin_notes"><?php _e('Admin Notes', 'teinformez'); ?></label></th>
                        <td>
                            <textarea name="admin_notes" id="admin_notes" rows="2" class="large-text"><?php
                                echo esc_textarea($editing_item->admin_notes);
                            ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="action" value="approve" class="button button-primary">
                        <?php _e('Approve & Save', 'teinformez'); ?>
                    </button>
                    <button type="submit" name="action" value="reject" class="button">
                        <?php _e('Reject', 'teinformez'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=teinformez-news-queue'); ?>" class="button">
                        <?php _e('Cancel', 'teinformez'); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else: ?>
        <!-- Queue List View -->

        <!-- Filters -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo add_query_arg('status', 'all'); ?>"
                   class="<?php echo $current_status === 'all' ? 'current' : ''; ?>">
                    <?php _e('All', 'teinformez'); ?>
                    <span class="count">(<?php echo $stats['total']; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('status', 'pending_review'); ?>"
                   class="<?php echo $current_status === 'pending_review' ? 'current' : ''; ?>">
                    <?php _e('Pending Review', 'teinformez'); ?>
                    <span class="count">(<?php echo $stats['pending_review']; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('status', 'approved'); ?>"
                   class="<?php echo $current_status === 'approved' ? 'current' : ''; ?>">
                    <?php _e('Approved', 'teinformez'); ?>
                    <span class="count">(<?php echo $stats['approved']; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo add_query_arg('status', 'published'); ?>"
                   class="<?php echo $current_status === 'published' ? 'current' : ''; ?>">
                    <?php _e('Published', 'teinformez'); ?>
                    <span class="count">(<?php echo $stats['published']; ?>)</span>
                </a>
            </li>
        </ul>

        <form method="get" style="float: right; margin-top: 5px;">
            <input type="hidden" name="page" value="teinformez-news-queue">
            <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search...', 'teinformez'); ?>">
            <button type="submit" class="button"><?php _e('Search', 'teinformez'); ?></button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 40%;"><?php _e('Title', 'teinformez'); ?></th>
                    <th style="width: 15%;"><?php _e('Source', 'teinformez'); ?></th>
                    <th style="width: 15%;"><?php _e('Categories', 'teinformez'); ?></th>
                    <th style="width: 10%;"><?php _e('Status', 'teinformez'); ?></th>
                    <th style="width: 15%;"><?php _e('Date', 'teinformez'); ?></th>
                    <th style="width: 5%;"><?php _e('Actions', 'teinformez'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue['items'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            <?php _e('No news items found.', 'teinformez'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue['items'] as $item): ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo add_query_arg('edit', $item->id); ?>">
                                        <?php echo esc_html($item->processed_title ?: $item->original_title); ?>
                                    </a>
                                </strong>
                                <?php if ($item->processed_summary): ?>
                                    <p style="color: #666; font-size: 12px; margin: 5px 0 0;">
                                        <?php echo esc_html(wp_trim_words($item->processed_summary, 20)); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item->source_name); ?>
                                <br>
                                <a href="<?php echo esc_url($item->original_url); ?>" target="_blank" style="font-size: 11px;">
                                    <?php _e('Original', 'teinformez'); ?> ↗
                                </a>
                            </td>
                            <td>
                                <?php
                                $cats = is_array($item->categories) ? $item->categories : [];
                                echo esc_html(implode(', ', $cats));
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'fetched' => '#6c757d',
                                    'processing' => '#0dcaf0',
                                    'pending_review' => '#ffc107',
                                    'approved' => '#198754',
                                    'rejected' => '#dc3545',
                                    'published' => '#0d6efd'
                                ];
                                $color = $status_colors[$item->status] ?? '#666';
                                ?>
                                <span style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $item->status))); ?>
                                </span>
                            </td>
                            <td style="font-size: 12px;">
                                <?php echo esc_html(date_i18n('d M Y H:i', strtotime($item->fetched_at))); ?>
                            </td>
                            <td>
                                <?php if ($item->status === 'pending_review'): ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('teinformez_queue_action'); ?>
                                        <input type="hidden" name="item_id" value="<?php echo $item->id; ?>">
                                        <button type="submit" name="action" value="approve" class="button button-small" title="<?php _e('Approve', 'teinformez'); ?>">✓</button>
                                        <button type="submit" name="action" value="reject" class="button button-small" title="<?php _e('Reject', 'teinformez'); ?>">✕</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($queue['pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $current_page,
                        'total' => $queue['pages'],
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;'
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.teinformez-quick-actions {
    display: flex;
    align-items: center;
}
.wp-list-table td {
    vertical-align: middle;
}
</style>
