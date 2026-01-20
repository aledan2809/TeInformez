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
        // TODO: Implement in Phase B
        return $this->success([
            'news' => [],
            'total' => 0
        ]);
    }

    /**
     * Get single news item
     */
    public function get_single_news($request) {
        // TODO: Implement in Phase B
        return $this->success(['news' => null]);
    }

    /**
     * Get personalized news feed
     */
    public function get_personalized_feed($request) {
        // TODO: Implement in Phase B based on user subscriptions
        return $this->success([
            'news' => [],
            'total' => 0
        ]);
    }
}
