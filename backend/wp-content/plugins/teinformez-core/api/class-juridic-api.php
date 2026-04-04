<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Juridic Q&A API endpoints
 */
class Juridic_API extends REST_API {

    public function register_routes() {
        // Public: list Q&As
        register_rest_route($this->namespace, '/juridic', [
            'methods' => 'GET',
            'callback' => [$this, 'get_list'],
            'permission_callback' => '__return_true'
        ]);

        // Public: single Q&A
        register_rest_route($this->namespace, '/juridic/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_single'],
            'permission_callback' => '__return_true'
        ]);

        // Public: categories
        register_rest_route($this->namespace, '/juridic/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => '__return_true'
        ]);

        // Public: weekly columns
        register_rest_route($this->namespace, '/juridic/columns', [
            'methods' => 'GET',
            'callback' => [$this, 'get_columns'],
            'permission_callback' => '__return_true'
        ]);

        // Admin: create Q&A
        register_rest_route($this->namespace, '/juridic', [
            'methods' => 'POST',
            'callback' => [$this, 'create'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Admin: update Q&A
        register_rest_route($this->namespace, '/juridic/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Admin: delete Q&A
        register_rest_route($this->namespace, '/juridic/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Public: track view
        register_rest_route($this->namespace, '/juridic/(?P<id>\d+)/view', [
            'methods' => 'POST',
            'callback' => [$this, 'track_view'],
            'permission_callback' => '__return_true'
        ]);

        // Admin: import juridic case from Facebook or external source
        register_rest_route($this->namespace, '/juridic/import/facebook', [
            'methods' => 'POST',
            'callback' => [$this, 'import_facebook_case'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Admin: publish juridic entry on social platforms
        register_rest_route($this->namespace, '/juridic/(?P<id>\d+)/publish-social', [
            'methods' => 'POST',
            'callback' => [$this, 'publish_social'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);
    }

    /**
     * List published Q&As
     */
    public function get_list($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';

        $page = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = min((int) ($request->get_param('per_page') ?: 20), 50);
        $category = sanitize_text_field($request->get_param('category') ?: '');
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $column_only = (bool) $request->get_param('column_only');

        $where = "WHERE status = 'published'";
        $params = [];

        if ($category) {
            $where .= " AND category = %s";
            $params[] = $category;
        }

        if ($search) {
            $where .= " AND (question_anonymized LIKE %s OR answer LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($column_only) {
            $where .= " AND is_weekly_column = 1";
        }

        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", ...$params)
                : "SELECT COUNT(*) FROM {$table} {$where}"
        );

        $offset = ($page - 1) * $per_page;
        $order = "ORDER BY published_at DESC LIMIT {$per_page} OFFSET {$offset}";

        $items = $params
            ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} {$where} {$order}", ...$params))
            : $wpdb->get_results("SELECT * FROM {$table} {$where} {$order}");

        $formatted = array_map([$this, 'format_item'], $items);

        return $this->success([
            'items' => $formatted,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    /**
     * Get single Q&A
     */
    public function get_single($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';
        $id = (int) $request->get_param('id');

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'published'", $id
        ));

        if (!$item) {
            return $this->error('Întrebarea nu a fost găsită.', 'not_found', 404);
        }

        // Increment view count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET view_count = view_count + 1 WHERE id = %d", $id
        ));

        return $this->success(['item' => $this->format_item($item)]);
    }

    /**
     * Get juridic subcategories
     */
    public function get_categories($request) {
        $categories = [
            ['slug' => 'dreptul-muncii', 'label' => 'Dreptul muncii'],
            ['slug' => 'dreptul-familiei', 'label' => 'Dreptul familiei'],
            ['slug' => 'drept-comercial', 'label' => 'Drept comercial'],
            ['slug' => 'drept-penal', 'label' => 'Drept penal'],
            ['slug' => 'protectia-consumatorului', 'label' => 'Protecția consumatorului'],
            ['slug' => 'drept-administrativ', 'label' => 'Drept administrativ'],
            ['slug' => 'drept-imobiliar', 'label' => 'Drept imobiliar'],
        ];

        return $this->success(['categories' => $categories]);
    }

    /**
     * Get weekly columns
     */
    public function get_columns($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';

        $page = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        $items = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'published' AND is_weekly_column = 1
             ORDER BY column_date DESC
             LIMIT {$per_page} OFFSET {$offset}"
        );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'published' AND is_weekly_column = 1"
        );

        return $this->success([
            'columns' => array_map([$this, 'format_item'], $items),
            'total' => $total,
            'page' => $page,
        ]);
    }

    /**
     * Create Q&A (admin only)
     */
    public function create($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';

        $data = [
            'question' => sanitize_textarea_field($request->get_param('question')),
            'question_anonymized' => sanitize_textarea_field($request->get_param('question_anonymized')),
            'answer' => wp_kses_post($request->get_param('answer')),
            'answer_summary' => sanitize_textarea_field($request->get_param('answer_summary') ?: ''),
            'category' => sanitize_text_field($request->get_param('category')),
            'subcategory' => sanitize_text_field($request->get_param('subcategory') ?: ''),
            'tags' => wp_json_encode($request->get_param('tags') ?: []),
            'is_weekly_column' => (int) $request->get_param('is_weekly_column'),
            'column_title' => sanitize_text_field($request->get_param('column_title') ?: ''),
            'column_date' => sanitize_text_field($request->get_param('column_date') ?: ''),
            'author_name' => sanitize_text_field($request->get_param('author_name') ?: 'Alina'),
            'fb_teaser' => sanitize_textarea_field($request->get_param('fb_teaser') ?: ''),
            'status' => sanitize_text_field($request->get_param('status') ?: 'draft'),
        ];

        if (empty($data['question']) || empty($data['question_anonymized']) || empty($data['answer']) || empty($data['category'])) {
            return $this->error('Câmpurile obligatorii lipsesc.', 'missing_fields', 400);
        }

        if ($data['status'] === 'published') {
            $data['published_at'] = current_time('mysql');
        }

        $wpdb->insert($table, $data);
        $id = $wpdb->insert_id;

        if (!$id) {
            return $this->error('Eroare la salvare.', 'db_error', 500);
        }

        if ($data['status'] === 'published') {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
            if ($item) {
                do_action('teinformez_juridic_published', $item);
            }
        }

        return $this->success(['id' => $id, 'message' => 'Întrebarea a fost salvată.'], '', 201);
    }

    /**
     * Update Q&A (admin only)
     */
    public function update($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';
        $id = (int) $request->get_param('id');

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$existing) {
            return $this->error('Întrebarea nu a fost găsită.', 'not_found', 404);
        }

        $data = [];
        $fields = ['question', 'question_anonymized', 'answer', 'answer_summary', 'category',
                    'subcategory', 'is_weekly_column', 'column_title', 'column_date',
                    'author_name', 'fb_teaser', 'fb_post_url', 'status'];

        foreach ($fields as $field) {
            $val = $request->get_param($field);
            if ($val !== null) {
                if ($field === 'answer') {
                    $data[$field] = wp_kses_post($val);
                } elseif ($field === 'is_weekly_column') {
                    $data[$field] = (int) $val;
                } else {
                    $data[$field] = sanitize_text_field($val);
                }
            }
        }

        $tags = $request->get_param('tags');
        if ($tags !== null) {
            $data['tags'] = wp_json_encode($tags);
        }

        // Set published_at if transitioning to published
        if (isset($data['status']) && $data['status'] === 'published' && $existing->status !== 'published') {
            $data['published_at'] = current_time('mysql');
        }

        if (!empty($data)) {
            $wpdb->update($table, $data, ['id' => $id]);
        }

        if (isset($data['status']) && $data['status'] === 'published' && $existing->status !== 'published') {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
            if ($item) {
                do_action('teinformez_juridic_published', $item);
            }
        }

        return $this->success(['message' => 'Întrebarea a fost actualizată.']);
    }

    /**
     * Delete Q&A (admin only)
     */
    public function delete($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';
        $id = (int) $request->get_param('id');

        $deleted = $wpdb->delete($table, ['id' => $id]);
        if (!$deleted) {
            return $this->error('Întrebarea nu a fost găsită.', 'not_found', 404);
        }

        return $this->success(['message' => 'Întrebarea a fost ștearsă.']);
    }

    /**
     * Track view
     */
    public function track_view($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';
        $id = (int) $request->get_param('id');

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET view_count = view_count + 1 WHERE id = %d AND status = 'published'", $id
        ));

        return $this->success(['tracked' => true]);
    }

    /**
     * Import juridic case from Facebook or external source
     */
    public function import_facebook_case($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';

        $raw_question = wp_strip_all_tags((string) $request->get_param('question'));
        $source_url = esc_url_raw((string) $request->get_param('source_url'));
        $source_type = sanitize_text_field((string) ($request->get_param('source_type') ?: 'facebook_feed'));
        $category = sanitize_text_field((string) ($request->get_param('category') ?: 'dreptul-muncii'));
        $author_name = sanitize_text_field((string) ($request->get_param('author_name') ?: 'Alina'));

        if ($raw_question === '') {
            return $this->error('Întrebarea este obligatorie.', 'missing_question', 400);
        }

        $question_anonymized = $this->anonymize_question($raw_question);
        $inserted = $wpdb->insert($table, [
            'question' => $raw_question,
            'question_anonymized' => $question_anonymized,
            'answer' => '',
            'answer_summary' => '',
            'category' => $category,
            'subcategory' => '',
            'tags' => wp_json_encode(['imported', 'needs-review', $source_type]),
            'is_weekly_column' => 0,
            'column_title' => '',
            'column_date' => null,
            'author_name' => $author_name,
            'fb_teaser' => '',
            'fb_post_url' => $source_url ?: null,
            'status' => 'draft',
            'created_at' => current_time('mysql'),
        ]);

        if (!$inserted) {
            return $this->error('Eroare la import.', 'db_error', 500);
        }

        $id = (int) $wpdb->insert_id;

        return $this->success([
            'id' => $id,
            'question_anonymized' => $question_anonymized,
            'status' => 'draft',
        ], '', 201);
    }

    /**
     * Publish Juridic post to social media
     */
    public function publish_social($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_juridic_qa';
        $id = (int) $request->get_param('id');
        $platforms = $request->get_param('platforms');

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$item) {
            return $this->error('Întrebarea nu a fost găsită.', 'not_found', 404);
        }

        if ($item->status !== 'published') {
            return $this->error('Doar întrebările publicate pot fi distribuite.', 'not_published', 400);
        }

        $validated_platforms = ['facebook', 'twitter', 'instagram'];
        if (!is_array($platforms) || empty($platforms)) {
            $platforms = ['facebook', 'twitter'];
        } else {
            $platforms = array_values(array_intersect($validated_platforms, array_map('sanitize_text_field', $platforms)));
        }

        $poster = new \TeInformez\Social_Poster();
        $result = $poster->post_juridic_on_demand($item, $platforms);

        return $this->success([
            'id' => $id,
            'platforms' => $platforms,
            'result' => $result,
        ]);
    }

    /**
     * Format Q&A item for response (NEVER expose original question)
     */
    private function format_item($item) {
        return [
            'id' => (int) $item->id,
            'question' => $item->question_anonymized,
            'answer' => $item->answer,
            'answer_summary' => $item->answer_summary,
            'category' => $item->category,
            'subcategory' => $item->subcategory,
            'tags' => json_decode($item->tags, true) ?? [],
            'is_weekly_column' => (bool) $item->is_weekly_column,
            'column_title' => $item->column_title,
            'column_date' => $item->column_date,
            'author_name' => $item->author_name,
            'view_count' => (int) $item->view_count,
            'published_at' => $item->published_at,
        ];
    }

    private function anonymize_question(string $question): string {
        $result = trim($question);

        $result = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email redactat]', $result);
        $result = preg_replace('/\+?\d[\d\s\-\(\)]{7,}\d/', '[telefon redactat]', $result);
        $result = preg_replace('/https?:\/\/\S+/i', '[link redactat]', $result);
        $result = preg_replace('/\b([A-Z][a-z]+)\s+([A-Z][a-z]+)\b/u', '[nume redactat]', $result);

        if (mb_strlen($result) < 30) {
            $result = 'Un cititor întreabă: ' . $result;
        }

        return $result;
    }
}
