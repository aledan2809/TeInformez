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

        // Track news view
        register_rest_route($this->namespace, '/news/(?P<id>\d+)/view', [
            'methods' => 'POST',
            'callback' => [$this, 'track_view'],
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
     * Get latest news
     */
    public function get_news($request) {
        // Parse query parameters
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 50); // Max 50 items per page
        $category = $request->get_param('category');
        $search = $request->get_param('search');

        // Get published news from News_Publisher
        $publisher = new \TeInformez\News_Publisher();
        $result = $publisher->get_queue([
            'status' => 'published',
            'search' => $search,
            'page' => $page,
            'per_page' => $per_page,
            'orderby' => 'published_at',
            'order' => 'DESC'
        ]);

        $items = $result['items'];

        // Filter by category if specified
        if ($category) {
            $items = array_filter($items, function($item) use ($category) {
                return in_array($category, $item->categories);
            });
        }

        // Format response
        $formatted = array_map([$this, 'format_news_item'], $items);

        return $this->success([
            'news' => array_values($formatted), // Re-index array
            'total' => count($formatted),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil(count($formatted) / $per_page)
        ]);
    }

    /**
     * Get single news item
     */
    public function get_single_news($request) {
        $id = $request->get_param('id');

        if (empty($id)) {
            return $this->error(
                __('News ID is required.', 'teinformez'),
                'missing_id',
                400
            );
        }

        $publisher = new \TeInformez\News_Publisher();
        $item = $publisher->get_item($id);

        if (!$item) {
            return $this->error(
                __('News item not found.', 'teinformez'),
                'not_found',
                404
            );
        }

        // Only return published items to public
        if ($item->status !== 'published') {
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
     * Format news item for API response
     */
    private function format_news_item($item) {
        return [
            'id' => (int) $item->id,
            'title' => $item->processed_title ?: $item->original_title,
            'summary' => $item->processed_summary,
            'content' => $item->processed_content ?: $item->original_content,
            'image' => $item->ai_generated_image_url,
            'source' => $item->source_name,
            'categories' => $item->categories,
            'tags' => $item->tags,
            'published_at' => $item->published_at,
            'original_url' => $item->original_url,
            'language' => $item->target_language ?: \TeInformez\Config::SITE_LANGUAGE,
            'view_count' => (int) ($item->view_count ?? 0),
        ];
    }
}
