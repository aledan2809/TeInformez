<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News API endpoints
 * (Placeholder - will be implemented in Phase B)
 */
class News_API extends REST_API {
    private const CATEGORY_ALIASES = [
        'news' => 'actualitate',
        'world' => 'international',
        'health' => 'sanatate',
    ];

    private const HOMEPAGE_SECTION_ORDER = [
        'juridic',
        'actualitate',
        'politics',
        'international',
        'justitie',
        'business',
        'finance',
        'tech',
        'sanatate',
        'science',
        'sports',
        'entertainment',
        'auto',
        'lifestyle',
        'opinii',
    ];

    public function register_routes() {
        // Get latest news
        register_rest_route($this->namespace, '/news', [
            'methods' => 'GET',
            'callback' => [$this, 'get_news'],
            'permission_callback' => '__return_true'
        ]);

        // Get single news item
        register_rest_route($this->namespace, '/news/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_single_news'],
            'permission_callback' => '__return_true'
        ]);

        // Get personalized news feed (authenticated)
        register_rest_route($this->namespace, '/news/personalized', [
            'methods' => 'GET',
            'callback' => [$this, 'get_personalized_feed'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Homepage data (categorized news in single call)
        register_rest_route($this->namespace, '/news/homepage', [
            'methods' => 'GET',
            'callback' => [$this, 'get_homepage_data'],
            'permission_callback' => '__return_true'
        ]);

        // Track news view
        register_rest_route($this->namespace, '/news/(?P<id>\d+)/view', [
            'methods' => 'POST',
            'callback' => [$this, 'track_view'],
            'permission_callback' => '__return_true'
        ]);

        // Newsletter subscribe (lightweight, no account needed)
        register_rest_route($this->namespace, '/newsletter/subscribe', [
            'methods' => 'POST',
            'callback' => [$this, 'newsletter_subscribe'],
            'permission_callback' => '__return_true'
        ]);

        // Admin analytics
        register_rest_route($this->namespace, '/admin/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);
    }

    /**
     * Get latest news (searches both active queue and archive)
     */
    public function get_news($request) {
        global $wpdb;

        $page = (int) ($request->get_param('page') ?: 1);
        $per_page = min((int) ($request->get_param('per_page') ?: 20), 50);
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        $include_archive = (bool) $request->get_param('archive');

        $queue = $wpdb->prefix . 'teinformez_news_queue';
        $archive = $wpdb->prefix . 'teinformez_news_archive';

        // Build WHERE clause
        $where = [
            "status = 'published'",
            "(
                (ai_generated_image_url IS NOT NULL AND ai_generated_image_url <> '')
                OR (youtube_embed IS NOT NULL AND youtube_embed <> '')
            )"
        ];
        $values = [];

        if ($search) {
            $where[] = '(processed_title LIKE %s OR original_title LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $like;
            $values[] = $like;
        }

        if ($category) {
            $variants = $this->get_category_filter_variants((string) $category);
            $parts = [];
            foreach ($variants as $variant) {
                $parts[] = 'categories LIKE %s';
                $values[] = '%"' . $wpdb->esc_like($variant) . '"%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $where_sql = implode(' AND ', $where);

        // Shared column list (archive has extra archived_at, so we must use explicit columns for UNION)
        $shared_cols = 'id, original_url, original_title, original_content, original_language, source_name, source_type, processed_title, processed_summary, processed_content, target_language, ai_generated_image_url, youtube_embed, status, admin_notes, categories, tags, view_count, fetched_at, processed_at, reviewed_at, published_at';

        // Build query: active queue + optionally archive
        if ($include_archive) {
            $count_sql = "SELECT COUNT(*) FROM (
                SELECT id FROM {$queue} WHERE {$where_sql}
                UNION ALL
                SELECT id FROM {$archive} WHERE {$where_sql}
            ) AS combined";

            $data_sql = "SELECT * FROM (
                SELECT {$shared_cols}, 'active' AS _source FROM {$queue} WHERE {$where_sql}
                UNION ALL
                SELECT {$shared_cols}, 'archive' AS _source FROM {$archive} WHERE {$where_sql}
            ) AS combined ORDER BY published_at DESC LIMIT %d OFFSET %d";

            // Values appear twice (once per table in UNION)
            $count_values = array_merge($values, $values);
            $data_values = array_merge($values, $values, [$per_page, ($page - 1) * $per_page]);
        } else {
            $count_sql = "SELECT COUNT(*) FROM {$queue} WHERE {$where_sql}";
            $data_sql = "SELECT {$shared_cols}, 'active' AS _source FROM {$queue} WHERE {$where_sql} ORDER BY published_at DESC LIMIT %d OFFSET %d";

            $count_values = $values;
            $data_values = array_merge($values, [$per_page, ($page - 1) * $per_page]);
        }

        $total = (int) ($count_values
            ? $wpdb->get_var($wpdb->prepare($count_sql, ...$count_values))
            : $wpdb->get_var($count_sql));

        $items = $data_values
            ? $wpdb->get_results($wpdb->prepare($data_sql, ...$data_values))
            : $wpdb->get_results($data_sql);

        // Decode JSON fields
        foreach ($items as &$item) {
            $item->categories = $this->normalize_categories_array(json_decode($item->categories, true) ?? []);
            $item->tags = json_decode($item->tags, true) ?? [];
        }
        unset($item);

        $formatted = array_map([$this, 'format_news_item'], $items);

        return $this->success([
            'news' => array_values($formatted),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / max($per_page, 1))
        ]);
    }

    /**
     * Get single news item (checks queue first, then archive)
     */
    public function get_single_news($request) {
        global $wpdb;
        $id = (int) $request->get_param('id');

        if (empty($id)) {
            return $this->error(
                __('News ID is required.', 'teinformez'),
                'missing_id',
                400
            );
        }

        // Try active queue first
        $publisher = new \TeInformez\News_Publisher();
        $item = $publisher->get_item($id);

        // If not found, check archive
        if (!$item) {
            $archive = $wpdb->prefix . 'teinformez_news_archive';
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$archive} WHERE id = %d", $id
            ));
            if ($item) {
                $item->categories = json_decode($item->categories, true) ?? [];
                $item->tags = json_decode($item->tags, true) ?? [];
            }
        }

        if (!$item) {
            return $this->error(
                __('News item not found.', 'teinformez'),
                'not_found',
                404
            );
        }

        if ($item->status !== 'published') {
            return $this->error(
                __('News item not available.', 'teinformez'),
                'not_available',
                404
            );
        }

        if (
            (empty($item->ai_generated_image_url) || trim((string)$item->ai_generated_image_url) === '') &&
            (empty($item->youtube_embed) || trim((string)$item->youtube_embed) === '')
        ) {
            return $this->error(
                __('News item not available.', 'teinformez'),
                'not_available',
                404
            );
        }

        return $this->success([
            'news' => $this->format_news_item($item)
        ]);
    }

    /**
     * Get personalized news feed
     */
    public function get_personalized_feed($request) {
        // Get current user ID (already authenticated via permission_callback)
        $user_id = $this->get_current_user_id();

        if (!$user_id) {
            return $this->error(
                __('Authentication required.', 'teinformez'),
                'unauthorized',
                401
            );
        }

        // Parse query parameters
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 50);

        // Get user subscriptions
        $subscription_manager = new \TeInformez\Subscription_Manager();
        $subscriptions = $subscription_manager->get_user_subscriptions($user_id);

        // Extract subscribed categories
        $subscribed_categories = [];
        foreach ($subscriptions as $sub) {
            if (!empty($sub['category_slug'])) {
                $subscribed_categories[] = $sub['category_slug'];
            }
        }

        // Get all published news
        $publisher = new \TeInformez\News_Publisher();
        $result = $publisher->get_queue([
            'status' => 'published',
            'requires_media' => true,
            'page' => 1,
            'per_page' => 100, // Get more to filter from
            'orderby' => 'published_at',
            'order' => 'DESC'
        ]);

        $all_items = $result['items'];

        // Filter by user's subscribed categories
        $filtered = [];
        foreach ($all_items as $item) {
            // If user has no subscriptions, show all news
            if (empty($subscribed_categories)) {
                $filtered[] = $item;
                continue;
            }

            // Check if item matches any subscribed category
            $matches_category = count(array_intersect($item->categories, $subscribed_categories)) > 0;

            if ($matches_category) {
                $filtered[] = $item;
            }
        }

        // Apply pagination to filtered results
        $total_filtered = count($filtered);
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($filtered, $offset, $per_page);

        // Format response
        $formatted = array_map([$this, 'format_news_item'], $paginated);

        return $this->success([
            'news' => array_values($formatted),
            'total' => $total_filtered,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_filtered / $per_page),
            'subscriptions_count' => count($subscribed_categories)
        ]);
    }

    /**
     * Get homepage data — hero + categorized sections in one call
     */
    public function get_homepage_data($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Get articles from the last 3 days only (homepage = fresh content)
        $items = $wpdb->get_results(
            "SELECT id, processed_title, original_title, processed_summary,
                    ai_generated_image_url, youtube_embed, source_name, categories, tags,
                    published_at, original_url, target_language, view_count
             FROM {$table}
             WHERE status = 'published'
               AND (
                    (ai_generated_image_url IS NOT NULL AND ai_generated_image_url <> '')
                    OR (youtube_embed IS NOT NULL AND youtube_embed <> '')
               )
               AND published_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
             ORDER BY published_at DESC"
        );

        // Parse categories JSON for each item
        foreach ($items as &$item) {
            $item->categories = $this->normalize_categories_array(json_decode($item->categories, true) ?? []);
            $item->tags = json_decode($item->tags, true) ?? [];
        }
        unset($item);

        // Pick hero: latest article with an image
        $hero = null;
        foreach ($items as $item) {
            if (!empty($item->ai_generated_image_url) || !empty($item->youtube_embed)) {
                $hero = $this->format_news_item($item);
                break;
            }
        }
        // Fallback: first available article
        if (!$hero && !empty($items)) {
            $hero = $this->format_news_item($items[0]);
        }

        // Group by primary category (first in the array)
        $by_category = [];
        $hero_id = $hero ? $hero['id'] : null;

        foreach ($items as $item) {
            if ($item->id == $hero_id) continue; // Skip hero article

            $primary_cat = $this->normalize_category_slug((string)($item->categories[0] ?? 'other'));
            if (!isset($by_category[$primary_cat])) {
                $by_category[$primary_cat] = [];
            }
            if (count($by_category[$primary_cat]) < 4) {
                $by_category[$primary_cat][] = $this->format_news_item($item);
            }
        }

        // Get category labels from config
        $config_cats = \TeInformez\Config::DEFAULT_CATEGORIES;
        $sections = [];
        foreach (self::HOMEPAGE_SECTION_ORDER as $slug) {
            if (empty($by_category[$slug])) {
                continue;
            }

            $sections[] = [
                'slug' => $slug,
                'label' => $config_cats[$slug]['label'] ?? ucfirst($slug),
                'emoji' => $config_cats[$slug]['icon'] ?? '📰',
                'articles' => $by_category[$slug],
            ];
        }

        foreach ($by_category as $slug => $articles) {
            if (in_array($slug, self::HOMEPAGE_SECTION_ORDER, true)) {
                continue;
            }

            $sections[] = [
                'slug' => $slug,
                'label' => $config_cats[$slug]['label'] ?? ucfirst($slug),
                'emoji' => $config_cats[$slug]['icon'] ?? '📰',
                'articles' => $articles,
            ];
        }

        return $this->success([
            'hero' => $hero,
            'sections' => $sections,
            'total_articles' => count($items),
        ]);
    }

    /**
     * Track a news article view
     */
    public function track_view($request) {
        global $wpdb;
        $id = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET view_count = view_count + 1 WHERE id = %d AND status = 'published'",
            $id
        ));

        return $this->success(['tracked' => true]);
    }

    /**
     * Get platform analytics (admin)
     */
    public function get_analytics($request) {
        global $wpdb;
        $news_table = $wpdb->prefix . 'teinformez_news_queue';
        $users_table = $wpdb->prefix . 'teinformez_user_preferences';
        $subs_table = $wpdb->prefix . 'teinformez_subscriptions';
        $delivery_table = $wpdb->prefix . 'teinformez_delivery_log';

        // News stats
        $publisher = new \TeInformez\News_Publisher();
        $news_stats = $publisher->get_stats();

        // Total views
        $total_views = (int) $wpdb->get_var("SELECT COALESCE(SUM(view_count), 0) FROM {$news_table}");

        // Top 10 most viewed articles
        $top_articles = $wpdb->get_results(
            "SELECT id, processed_title as title, view_count, source_name as source, published_at
             FROM {$news_table}
             WHERE status = 'published' AND view_count > 0
             ORDER BY view_count DESC LIMIT 10"
        );

        // User stats
        $total_users = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$users_table}");
        $users_last_7d = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$users_table} WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Subscription stats
        $total_subs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs_table} WHERE is_active = 1");
        $top_categories = $wpdb->get_results(
            "SELECT category_slug, COUNT(*) as count FROM {$subs_table}
             WHERE is_active = 1 GROUP BY category_slug ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Delivery stats
        $deliveries_sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$delivery_table} WHERE status = 'sent'");
        $deliveries_failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$delivery_table} WHERE status = 'failed'");

        return $this->success([
            'news' => $news_stats,
            'views' => [
                'total' => $total_views,
                'top_articles' => $top_articles,
            ],
            'users' => [
                'total' => $total_users,
                'new_last_7d' => $users_last_7d,
            ],
            'subscriptions' => [
                'active' => $total_subs,
                'top_categories' => $top_categories,
            ],
            'deliveries' => [
                'sent' => $deliveries_sent,
                'failed' => $deliveries_failed,
            ],
        ]);
    }

    /**
     * Newsletter subscribe (lightweight, no WP account needed)
     */
    public function newsletter_subscribe($request) {
        global $wpdb;

        $email = sanitize_email($request->get_param('email'));
        $gdpr = (bool) $request->get_param('gdpr_consent');
        $visitor_id = sanitize_text_field((string) $request->get_param('visitor_id'));
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));

        if (empty($email) || !is_email($email)) {
            return $this->error('Adresa de email nu este validă.', 'invalid_email', 400);
        }

        if (!$gdpr) {
            return $this->error('Consimțământul GDPR este obligatoriu.', 'gdpr_required', 400);
        }

        $table = $wpdb->prefix . 'teinformez_newsletter_subscribers';

        // Check if already subscribed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE email = %s", $email
        ));

        if ($existing) {
            if ($existing->status === 'active') {
                return $this->success(['message' => 'Ești deja abonat!']);
            }
            // Re-activate
            $wpdb->update($table, [
                'status' => 'active',
                'gdpr_consent' => 1,
                'gdpr_consent_date' => current_time('mysql'),
                'unsubscribed_at' => null,
            ], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table, [
                'email' => $email,
                'gdpr_consent' => 1,
                'gdpr_consent_date' => current_time('mysql'),
                'status' => 'active',
            ]);
        }

        if ($visitor_id !== '' && $session_id !== '') {
            \TeInformez\Visitor_Analytics::track_event([
                'visitor_id' => $visitor_id,
                'session_id' => $session_id,
                'event_type' => 'newsletter_subscribe',
                'page_type' => 'home',
                'page_path' => '/newsletter/subscribe',
                'metadata' => [
                    'source' => 'newsletter_subscribe_endpoint',
                ],
            ]);
        }

        return $this->success(['message' => 'Te-ai abonat cu succes!']);
    }

    /**
     * Format news item for API response
     */
    private function format_news_item($item) {
        $content = $item->processed_content ?: $item->original_content;

        // Wrap plain text paragraphs in <p> tags if not already HTML
        if ($content && strpos($content, '<p>') === false) {
            $paragraphs = preg_split('/\n\s*\n/', trim($content));
            $content = '<p>' . implode('</p><p>', array_map('trim', array_filter($paragraphs))) . '</p>';
        }

        $image = (string)($item->ai_generated_image_url ?? '');
        $youtube = (string)($item->youtube_embed ?? '');

        $image_source = null;
        if ($image) {
            $fallback_host = parse_url($image, PHP_URL_HOST);
            $image_source = $item->source_name ?: ($fallback_host ?: null);
        }

        return [
            'id' => (int) $item->id,
            'title' => $item->processed_title ?: $item->original_title,
            'summary' => $item->processed_summary,
            'content' => $content,
            'image' => $image ?: null,
            'image_source' => $image_source,
            'youtube_url' => $youtube ?: null,
            'source' => $item->source_name,
            'categories' => $this->normalize_categories_array((array) ($item->categories ?? [])),
            'tags' => $item->tags,
            'published_at' => $item->published_at,
            'original_url' => $item->original_url,
            'language' => $item->target_language ?: \TeInformez\Config::SITE_LANGUAGE,
            'view_count' => (int) ($item->view_count ?? 0),
        ];
    }

    private function normalize_category_slug(string $slug): string {
        $slug = sanitize_key(trim($slug));
        return self::CATEGORY_ALIASES[$slug] ?? $slug;
    }

    private function normalize_categories_array(array $categories): array {
        $normalized = [];
        foreach ($categories as $category) {
            if (!is_string($category) || $category === '') {
                continue;
            }

            $canonical = $this->normalize_category_slug($category);
            if (!in_array($canonical, $normalized, true)) {
                $normalized[] = $canonical;
            }
        }
        return $normalized;
    }

    private function get_category_filter_variants(string $slug): array {
        $canonical = $this->normalize_category_slug($slug);
        $variants = [$canonical];

        foreach (self::CATEGORY_ALIASES as $legacy => $mapped) {
            if ($mapped === $canonical) {
                $variants[] = $legacy;
            }
        }

        return array_values(array_unique($variants));
    }
}
