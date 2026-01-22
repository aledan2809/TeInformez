<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Source Manager
 * Handles configuration and management of news sources (RSS, API)
 */
class News_Source_Manager {

    private $sources_option = 'teinformez_news_sources';

    /**
     * Get all configured sources
     */
    public function get_sources() {
        $sources = get_option($this->sources_option, []);

        // Return default sources if none configured
        if (empty($sources)) {
            return $this->get_default_sources();
        }

        return $sources;
    }

    /**
     * Get sources by category
     */
    public function get_sources_by_category($category) {
        $sources = $this->get_sources();
        return array_filter($sources, function($source) use ($category) {
            return in_array($category, $source['categories'] ?? []);
        });
    }

    /**
     * Get active sources
     */
    public function get_active_sources() {
        $sources = $this->get_sources();
        return array_filter($sources, function($source) {
            return ($source['is_active'] ?? true);
        });
    }

    /**
     * Add a new source
     */
    public function add_source($source_data) {
        $sources = $this->get_sources();

        $new_source = [
            'id' => uniqid('src_'),
            'name' => sanitize_text_field($source_data['name']),
            'type' => sanitize_text_field($source_data['type'] ?? 'rss'),
            'url' => esc_url_raw($source_data['url']),
            'language' => sanitize_text_field($source_data['language'] ?? 'en'),
            'categories' => array_map('sanitize_text_field', $source_data['categories'] ?? []),
            'is_active' => (bool)($source_data['is_active'] ?? true),
            'fetch_interval' => (int)($source_data['fetch_interval'] ?? 1800),
            'last_fetched' => null,
            'last_error' => null,
            'created_at' => current_time('mysql')
        ];

        $sources[] = $new_source;
        update_option($this->sources_option, $sources);

        return $new_source;
    }

    /**
     * Update a source
     */
    public function update_source($source_id, $source_data) {
        $sources = $this->get_sources();

        foreach ($sources as $key => $source) {
            if ($source['id'] === $source_id) {
                $sources[$key] = array_merge($source, [
                    'name' => sanitize_text_field($source_data['name'] ?? $source['name']),
                    'url' => esc_url_raw($source_data['url'] ?? $source['url']),
                    'language' => sanitize_text_field($source_data['language'] ?? $source['language']),
                    'categories' => array_map('sanitize_text_field', $source_data['categories'] ?? $source['categories']),
                    'is_active' => (bool)($source_data['is_active'] ?? $source['is_active']),
                    'fetch_interval' => (int)($source_data['fetch_interval'] ?? $source['fetch_interval'])
                ]);
                break;
            }
        }

        update_option($this->sources_option, $sources);
        return $sources;
    }

    /**
     * Delete a source
     */
    public function delete_source($source_id) {
        $sources = $this->get_sources();
        $sources = array_filter($sources, function($source) use ($source_id) {
            return $source['id'] !== $source_id;
        });
        update_option($this->sources_option, array_values($sources));
        return true;
    }

    /**
     * Mark source as fetched
     */
    public function mark_fetched($source_id, $error = null) {
        $sources = $this->get_sources();

        foreach ($sources as $key => $source) {
            if ($source['id'] === $source_id) {
                $sources[$key]['last_fetched'] = current_time('mysql');
                $sources[$key]['last_error'] = $error;
                break;
            }
        }

        update_option($this->sources_option, $sources);
    }

    /**
     * Get default news sources for Romania
     */
    private function get_default_sources() {
        return [
            // Tech
            [
                'id' => 'src_techcrunch',
                'name' => 'TechCrunch',
                'type' => 'rss',
                'url' => 'https://techcrunch.com/feed/',
                'language' => 'en',
                'categories' => ['tech'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            [
                'id' => 'src_theverge',
                'name' => 'The Verge',
                'type' => 'rss',
                'url' => 'https://www.theverge.com/rss/index.xml',
                'language' => 'en',
                'categories' => ['tech'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            [
                'id' => 'src_arstechnica',
                'name' => 'Ars Technica',
                'type' => 'rss',
                'url' => 'https://feeds.arstechnica.com/arstechnica/index',
                'language' => 'en',
                'categories' => ['tech', 'science'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            // Auto
            [
                'id' => 'src_autoblog',
                'name' => 'Autoblog',
                'type' => 'rss',
                'url' => 'https://www.autoblog.com/rss.xml',
                'language' => 'en',
                'categories' => ['auto'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            [
                'id' => 'src_electrek',
                'name' => 'Electrek',
                'type' => 'rss',
                'url' => 'https://electrek.co/feed/',
                'language' => 'en',
                'categories' => ['auto', 'tech'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            // Finance
            [
                'id' => 'src_coindesk',
                'name' => 'CoinDesk',
                'type' => 'rss',
                'url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/',
                'language' => 'en',
                'categories' => ['finance'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            // Science
            [
                'id' => 'src_nasa',
                'name' => 'NASA News',
                'type' => 'rss',
                'url' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss',
                'language' => 'en',
                'categories' => ['science'],
                'is_active' => true,
                'fetch_interval' => 3600,
                'last_fetched' => null,
                'last_error' => null
            ],
            // Sports
            [
                'id' => 'src_espn',
                'name' => 'ESPN',
                'type' => 'rss',
                'url' => 'https://www.espn.com/espn/rss/news',
                'language' => 'en',
                'categories' => ['sports'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            // Romanian Sources
            [
                'id' => 'src_digi24',
                'name' => 'Digi24',
                'type' => 'rss',
                'url' => 'https://www.digi24.ro/rss',
                'language' => 'ro',
                'categories' => ['politics', 'business'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ],
            [
                'id' => 'src_hotnews',
                'name' => 'HotNews',
                'type' => 'rss',
                'url' => 'https://www.hotnews.ro/rss',
                'language' => 'ro',
                'categories' => ['politics', 'business'],
                'is_active' => true,
                'fetch_interval' => 1800,
                'last_fetched' => null,
                'last_error' => null
            ]
        ];
    }
}
