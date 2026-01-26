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
            'language' => $item->target_language ?: \TeInformez\Config::SITE_LANGUAGE
        ];
    }
}
