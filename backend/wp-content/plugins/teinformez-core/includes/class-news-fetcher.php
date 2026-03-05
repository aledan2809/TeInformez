<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Fetcher
 * Fetches news from configured RSS/API sources
 */
class News_Fetcher {

    private $source_manager;

    public function __construct() {
        $this->source_manager = new News_Source_Manager();
    }

    /**
     * Fetch news from all active sources
     */
    public function fetch_all() {
        $sources = $this->source_manager->get_active_sources();
        $results = [];

        foreach ($sources as $source) {
            // Check if source needs fetching based on interval
            if ($this->should_fetch($source)) {
                $result = $this->fetch_source($source);
                $results[$source['id']] = $result;
            }
        }

        return $results;
    }

    /**
     * Fetch news from a single source
     */
    public function fetch_source($source) {
        error_log('TeInformez: Fetching from source: ' . $source['name']);

        try {
            switch ($source['type']) {
                case 'rss':
                    $items = $this->fetch_rss($source);
                    break;
                case 'api':
                    $items = $this->fetch_api($source);
                    break;
                default:
                    throw new \Exception('Unknown source type: ' . $source['type']);
            }

            // Store items in queue
            $stored = $this->store_items($items, $source);

            // Mark source as fetched
            $this->source_manager->mark_fetched($source['id']);

            error_log('TeInformez: Fetched ' . count($items) . ' items from ' . $source['name'] . ', stored ' . $stored . ' new items');

            return [
                'success' => true,
                'fetched' => count($items),
                'stored' => $stored
            ];

        } catch (\Exception $e) {
            error_log('TeInformez ERROR: Failed to fetch from ' . $source['name'] . ': ' . $e->getMessage());
            $this->source_manager->mark_fetched($source['id'], $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if source should be fetched based on interval
     */
    private function should_fetch($source) {
        if (empty($source['last_fetched'])) {
            return true;
        }

        $last_fetched = strtotime($source['last_fetched']);
        $interval = $source['fetch_interval'] ?? 1800;

        return (time() - $last_fetched) >= $interval;
    }

    /**
     * Fetch RSS feed
     */
    private function fetch_rss($source) {
        $response = wp_remote_get($source['url'], [
            'timeout' => 30,
            'user-agent' => 'TeInformez News Aggregator/1.0'
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new \Exception('Empty response from RSS feed');
        }

        // Parse RSS
        $items = $this->parse_rss($body, $source);

        return $items;
    }

    /**
     * Parse RSS XML to items array
     */
    private function parse_rss($xml_string, $source) {
        // Suppress XML errors
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_string);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \Exception('Failed to parse RSS XML: ' . ($errors[0]->message ?? 'Unknown error'));
        }

        $items = [];

        // Handle both RSS and Atom feeds
        if (isset($xml->channel->item)) {
            // RSS format
            foreach ($xml->channel->item as $item) {
                $items[] = $this->parse_rss_item($item, $source);
            }
        } elseif (isset($xml->entry)) {
            // Atom format
            foreach ($xml->entry as $entry) {
                $items[] = $this->parse_atom_entry($entry, $source);
            }
        }

        return $items;
    }

    /**
     * Parse RSS item
     */
    private function parse_rss_item($item, $source) {
        // Get content from various possible fields
        $content = '';
        if (!empty($item->children('content', true)->encoded)) {
            $content = (string)$item->children('content', true)->encoded;
        } elseif (!empty($item->description)) {
            $content = (string)$item->description;
        }
        $description = (string)$item->description;

        // Get media/image
        $image_url = '';
        if (!empty($item->children('media', true)->content)) {
            $media = $item->children('media', true)->content;
            $image_url = (string)$media->attributes()->url;
        } elseif (!empty($item->enclosure)) {
            $enclosure = $item->enclosure->attributes();
            if (strpos($enclosure->type, 'image') !== false) {
                $image_url = (string)$enclosure->url;
            }
        }

        $youtube_url = $this->extract_youtube_url(
            (string)$item->link,
            $content,
            $description
        );

        // Get publication date
        $pub_date = null;
        if (!empty($item->pubDate)) {
            $pub_date = date('Y-m-d H:i:s', strtotime((string)$item->pubDate));
        }

        return [
            'url' => (string)$item->link,
            'title' => html_entity_decode((string)$item->title, ENT_QUOTES, 'UTF-8'),
            'content' => strip_tags($content),
            'description' => strip_tags($description),
            'image_url' => $image_url,
            'youtube_embed' => $youtube_url,
            'published_at' => $pub_date,
            'source_name' => $source['name'],
            'source_language' => $source['language'],
            'categories' => $source['categories']
        ];
    }

    /**
     * Parse Atom entry
     */
    private function parse_atom_entry($entry, $source) {
        // Get link
        $link = '';
        foreach ($entry->link as $l) {
            $attrs = $l->attributes();
            if ((string)$attrs->rel === 'alternate' || empty($attrs->rel)) {
                $link = (string)$attrs->href;
                break;
            }
        }

        // Get content
        $content = '';
        if (!empty($entry->content)) {
            $content = (string)$entry->content;
        } elseif (!empty($entry->summary)) {
            $content = (string)$entry->summary;
        }
        $summary = (string)$entry->summary;
        $youtube_url = $this->extract_youtube_url($link, $content, $summary);

        // Get published date
        $pub_date = null;
        if (!empty($entry->published)) {
            $pub_date = date('Y-m-d H:i:s', strtotime((string)$entry->published));
        } elseif (!empty($entry->updated)) {
            $pub_date = date('Y-m-d H:i:s', strtotime((string)$entry->updated));
        }

        return [
            'url' => $link,
            'title' => html_entity_decode((string)$entry->title, ENT_QUOTES, 'UTF-8'),
            'content' => strip_tags($content),
            'description' => strip_tags($summary),
            'image_url' => '',
            'youtube_embed' => $youtube_url,
            'published_at' => $pub_date,
            'source_name' => $source['name'],
            'source_language' => $source['language'],
            'categories' => $source['categories']
        ];
    }

    /**
     * Fetch from API source (placeholder for future implementation)
     */
    private function fetch_api($source) {
        // TODO: Implement API fetching (NewsAPI, etc.)
        return [];
    }

    /**
     * Fetch page HTML from URL
     */
    private function fetch_page_html($url) {
        if (empty($url)) {
            return null;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; TeInformez/1.0)',
            'headers' => [
                'Accept' => 'text/html',
                'Accept-Language' => 'ro,en;q=0.9',
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('TeInformez Scraper: Failed to fetch ' . $url . ': ' . $response->get_error_message());
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Scrape full article content from source URL
     */
    private function scrape_full_content($url) {
        $html = $this->fetch_page_html($url);
        if (empty($html)) {
            return null;
        }
        return $this->extract_article_text($html);
    }

    /**
     * Extract og:image from page HTML
     */
    private function extract_og_image($html) {
        if (empty($html)) {
            return '';
        }

        // Quick regex extraction — faster than DOM parsing for just meta tags
        $url = '';
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $url = $m[1];
        } elseif (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $url = $m[1];
        }
        // Decode HTML entities (&amp; → &)
        return html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Scrape both content and og:image from URL in one request
     */
    private function scrape_article($url) {
        $html = $this->fetch_page_html($url);
        if (empty($html)) {
            return ['content' => null, 'image' => '', 'youtube' => ''];
        }
        return [
            'content' => $this->extract_article_text($html),
            'image' => $this->extract_og_image($html),
            'youtube' => $this->extract_youtube_url($html),
        ];
    }

    /**
     * Extract first YouTube URL from one or more text inputs
     */
    private function extract_youtube_url(...$texts) {
        $combined = trim(implode("\n", array_filter(array_map(function($text) {
            return is_string($text) ? $text : '';
        }, $texts))));

        if ($combined === '') {
            return '';
        }

        $patterns = [
            '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})[^\s"\'<]*/i',
            '/https?:\/\/(?:www\.)?youtube\.com\/shorts\/([A-Za-z0-9_-]{11})[^\s"\'<]*/i',
            '/https?:\/\/(?:www\.)?youtube\.com\/embed\/([A-Za-z0-9_-]{11})[^\s"\'<]*/i',
            '/https?:\/\/(?:www\.)?youtu\.be\/([A-Za-z0-9_-]{11})[^\s"\'<]*/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $match)) {
                return 'https://www.youtube.com/watch?v=' . $match[1];
            }
        }

        return '';
    }

    /**
     * Reuse media from similar already-stored stories when current item has no media.
     */
    private function find_related_media($title, $url) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$title));
        if (!$terms) {
            return ['image' => '', 'youtube' => ''];
        }

        $terms = array_values(array_unique(array_filter($terms, function($term) {
            return mb_strlen($term) >= 4;
        })));

        foreach (array_slice($terms, 0, 5) as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT ai_generated_image_url, youtube_embed
                 FROM {$table}
                 WHERE original_url <> %s
                   AND (processed_title LIKE %s OR original_title LIKE %s)
                   AND (
                     (ai_generated_image_url IS NOT NULL AND ai_generated_image_url <> '')
                     OR (youtube_embed IS NOT NULL AND youtube_embed <> '')
                   )
                 ORDER BY published_at DESC, fetched_at DESC
                 LIMIT 1",
                $url,
                $like,
                $like
            ));

            if ($row) {
                return [
                    'image' => (string)($row->ai_generated_image_url ?? ''),
                    'youtube' => (string)($row->youtube_embed ?? ''),
                ];
            }
        }

        return ['image' => '', 'youtube' => ''];
    }

    /**
     * Extract article text from HTML using DOMDocument
     */
    private function extract_article_text($html) {
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Remove script, style, nav, header, footer, aside elements
        $remove_tags = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'iframe', 'form'];
        foreach ($remove_tags as $tag) {
            $nodes = $xpath->query('//' . $tag);
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        // Try selectors in order of specificity
        $selectors = [
            // Common article body class patterns
            "//*[contains(@class, 'article-body')]",
            "//*[contains(@class, 'article-content')]",
            "//*[contains(@class, 'article__body')]",
            "//*[contains(@class, 'article__content')]",
            "//*[contains(@class, 'post-content')]",
            "//*[contains(@class, 'entry-content')]",
            "//*[contains(@class, 'story-body')]",
            "//*[contains(@class, 'content-body')]",
            "//*[contains(@itemprop, 'articleBody')]",
            // Generic article tag
            "//article",
            // Main content area
            "//main",
        ];

        $best_text = '';
        $best_length = 0;

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $paragraphs = $xpath->query('.//p', $node);
                $text_parts = [];

                foreach ($paragraphs as $p) {
                    $p_text = trim($p->textContent);
                    // Skip very short paragraphs (likely captions, labels)
                    if (mb_strlen($p_text) > 30) {
                        $text_parts[] = $p_text;
                    }
                }

                $text = implode("\n\n", $text_parts);

                // Keep the longest extraction (most likely the actual article)
                if (mb_strlen($text) > $best_length) {
                    $best_text = $text;
                    $best_length = mb_strlen($text);
                }
            }
        }

        // Only return if we got meaningful content (at least 200 chars)
        if ($best_length < 200) {
            return null;
        }

        // Limit to ~8000 chars to stay within AI processing limits
        if (mb_strlen($best_text) > 8000) {
            $best_text = mb_substr($best_text, 0, 8000);
            // Cut at last complete sentence
            $last_period = mb_strrpos($best_text, '.');
            if ($last_period > 6000) {
                $best_text = mb_substr($best_text, 0, $last_period + 1);
            }
        }

        return $best_text;
    }

    /**
     * Store fetched items in news queue
     */
    private function store_items($items, $source) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';
        $stored = 0;

        foreach ($items as $item) {
            // Skip if URL already exists (deduplication)
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE original_url = %s",
                $item['url']
            ));

            if ($exists) {
                continue;
            }

            // Scrape full article + og:image in one request
            $content = $item['content'] ?: $item['description'];
            $image_url = $item['image_url'] ?? '';
            $youtube_embed = $item['youtube_embed'] ?? '';

            if (mb_strlen($content) < 500 || empty($image_url)) {
                $scraped = $this->scrape_article($item['url']);
                if ($scraped['content'] && mb_strlen($content) < 500) {
                    $content = $scraped['content'];
                }
                if (empty($image_url) && !empty($scraped['image'])) {
                    $image_url = $scraped['image'];
                }
                if (empty($youtube_embed) && !empty($scraped['youtube'])) {
                    $youtube_embed = $scraped['youtube'];
                }
            }

            if (empty($youtube_embed)) {
                $youtube_embed = $this->extract_youtube_url(
                    $item['url'] ?? '',
                    $item['content'] ?? '',
                    $item['description'] ?? '',
                    $content
                );
            }

            if (empty($image_url) && empty($youtube_embed)) {
                $related_media = $this->find_related_media($item['title'] ?? '', $item['url'] ?? '');
                if (empty($image_url) && !empty($related_media['image'])) {
                    $image_url = $related_media['image'];
                }
                if (empty($youtube_embed) && !empty($related_media['youtube'])) {
                    $youtube_embed = $related_media['youtube'];
                }
            }

            if (empty($image_url) && empty($youtube_embed)) {
                continue;
            }

            // Insert new item
            $result = $wpdb->insert($table, [
                'original_url' => $item['url'],
                'original_title' => $item['title'],
                'original_content' => $content,
                'original_language' => $item['source_language'],
                'source_name' => $item['source_name'],
                'source_type' => $source['type'],
                'categories' => json_encode($item['categories']),
                'ai_generated_image_url' => $image_url,
                'youtube_embed' => $youtube_embed,
                'status' => 'fetched',
                'fetched_at' => current_time('mysql')
            ], [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]);

            if ($result) {
                $stored++;
            }
        }

        return $stored;
    }
}
