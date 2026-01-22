<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Publisher
 * Handles approval workflow and auto-publishing of news
 */
class News_Publisher {

    /**
     * Get news queue with filtering
     */
    public function get_queue($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $defaults = [
            'status' => null,
            'category' => null,
            'source' => null,
            'search' => null,
            'page' => 1,
            'per_page' => 20,
            'orderby' => 'fetched_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ['1=1'];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['source']) {
            $where[] = 'source_name = %s';
            $values[] = $args['source'];
        }

        if ($args['search']) {
            $where[] = '(original_title LIKE %s OR processed_title LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Count total
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, ...$values);
        }
        $total = (int) $wpdb->get_var($count_query);

        // Get items
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'fetched_at DESC';

        $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($query, ...$values));

        // Decode JSON fields
        foreach ($items as &$item) {
            $item->categories = json_decode($item->categories, true) ?? [];
            $item->tags = json_decode($item->tags, true) ?? [];
        }

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page']
        ];
    }

    /**
     * Get single news item
     */
    public function get_item($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if ($item) {
            $item->categories = json_decode($item->categories, true) ?? [];
            $item->tags = json_decode($item->tags, true) ?? [];
        }

        return $item;
    }

    /**
     * Approve news item for publishing
     */
    public function approve($id, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $result = $wpdb->update($table, [
            'status' => 'approved',
            'admin_notes' => $notes,
            'reviewed_at' => current_time('mysql')
        ], ['id' => $id]);

        if ($result !== false) {
            error_log('TeInformez: News item #' . $id . ' approved');
            return true;
        }

        return false;
    }

    /**
     * Reject news item
     */
    public function reject($id, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $result = $wpdb->update($table, [
            'status' => 'rejected',
            'admin_notes' => $notes,
            'reviewed_at' => current_time('mysql')
        ], ['id' => $id]);

        if ($result !== false) {
            error_log('TeInformez: News item #' . $id . ' rejected');
            return true;
        }

        return false;
    }

    /**
     * Update news item content (edit before approving)
     */
    public function update_item($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $update_data = [];

        if (isset($data['processed_title'])) {
            $update_data['processed_title'] = sanitize_text_field($data['processed_title']);
        }
        if (isset($data['processed_summary'])) {
            $update_data['processed_summary'] = sanitize_textarea_field($data['processed_summary']);
        }
        if (isset($data['processed_content'])) {
            $update_data['processed_content'] = wp_kses_post($data['processed_content']);
        }
        if (isset($data['categories'])) {
            $update_data['categories'] = json_encode($data['categories']);
        }
        if (isset($data['tags'])) {
            $update_data['tags'] = json_encode($data['tags']);
        }
        if (isset($data['admin_notes'])) {
            $update_data['admin_notes'] = sanitize_textarea_field($data['admin_notes']);
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update($table, $update_data, ['id' => $id]) !== false;
    }

    /**
     * Publish approved news items
     * Called by cron or manually
     */
    public function publish_approved() {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Get approved items ready for publishing
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'approved' ORDER BY reviewed_at ASC LIMIT 50"
        );

        if (empty($items)) {
            return ['published' => 0];
        }

        $published = 0;

        foreach ($items as $item) {
            $result = $this->publish_item($item);
            if ($result) {
                $published++;
            }
        }

        error_log('TeInformez: Published ' . $published . ' news items');
        return ['published' => $published];
    }

    /**
     * Auto-publish items that have been pending review for too long
     */
    public function auto_publish_expired() {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $review_period = Config::get('admin_review_period', Config::ADMIN_REVIEW_PERIOD);
        $cutoff_time = date('Y-m-d H:i:s', time() - $review_period);

        // Get pending_review items that have exceeded the review period
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'pending_review'
             AND processed_at < %s
             ORDER BY processed_at ASC
             LIMIT 50",
            $cutoff_time
        ));

        if (empty($items)) {
            return ['auto_published' => 0];
        }

        $published = 0;

        foreach ($items as $item) {
            // First approve, then publish
            $this->approve($item->id, 'Auto-approved after review period expired');
            $result = $this->publish_item($item);
            if ($result) {
                $published++;
            }
        }

        if ($published > 0) {
            error_log('TeInformez: Auto-published ' . $published . ' news items after review period');
        }

        return ['auto_published' => $published];
    }

    /**
     * Publish a single news item
     */
    private function publish_item($item) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Mark as published
        $result = $wpdb->update($table, [
            'status' => 'published',
            'published_at' => current_time('mysql')
        ], ['id' => $item->id]);

        if ($result === false) {
            error_log('TeInformez ERROR: Failed to mark item #' . $item->id . ' as published');
            return false;
        }

        // Trigger delivery to subscribers
        do_action('teinformez_news_published', $item);

        return true;
    }

    /**
     * Delete old rejected/published items (cleanup)
     */
    public function cleanup_old_items($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE (status = 'rejected' OR status = 'published')
             AND fetched_at < %s",
            $cutoff
        ));

        if ($deleted > 0) {
            error_log('TeInformez: Cleaned up ' . $deleted . ' old news items');
        }

        return ['deleted' => $deleted];
    }

    /**
     * Get queue statistics
     */
    public function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status"
        );

        $result = [
            'fetched' => 0,
            'processing' => 0,
            'pending_review' => 0,
            'approved' => 0,
            'rejected' => 0,
            'published' => 0
        ];

        foreach ($stats as $stat) {
            $result[$stat->status] = (int) $stat->count;
        }

        $result['total'] = array_sum($result);

        return $result;
    }
}
