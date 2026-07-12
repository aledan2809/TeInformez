<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Social Media Poster
 * Auto-posts published news to Facebook Page, Twitter/X, and Instagram.
 * Platform-level posting (user_id=NULL in delivery_log — not tied to a WP user).
 */
class Social_Poster {

    private $facebook_page_id;
    private $facebook_token;
    private $twitter_api_key;
    private $twitter_api_secret;
    private $twitter_access_token;
    private $twitter_access_secret;
    private $instagram_business_id;
    private $enabled;

    public function __construct() {
        $this->enabled              = Config::get('social_posting_enabled', '0') === '1';
        $this->facebook_page_id     = Config::get('facebook_page_id', '');
        $this->facebook_token       = Config::get('facebook_access_token', '');
        $this->twitter_api_key      = Config::get('twitter_api_key', '');
        $this->twitter_api_secret   = Config::get('twitter_api_secret', '');
        $this->twitter_access_token = Config::get('twitter_access_token', '');
        $this->twitter_access_secret = Config::get('twitter_access_token_secret', '');
        $this->instagram_business_id = Config::get('instagram_business_id', '');
    }

    /**
     * Hook handler: called when a news item is published
     */
    public function post_on_publish($item) {
        if (!$this->enabled) {
            return;
        }

        $content = $this->build_social_content($item);

        // Post to Facebook if configured
        if (!empty($this->facebook_page_id) && !empty($this->facebook_token)) {
            $fb_result = $this->post_to_facebook(
                $content['text'],
                $content['url'],
                $content['image']
            );
            $this->log_social_post(
                $item->id,
                'facebook_post',
                $fb_result['success'] ? 'sent' : 'failed',
                $fb_result['error'] ?? null,
                $fb_result['data'] ?? null
            );
        }

        // Post to Twitter if configured
        if (!empty($this->twitter_api_key) && !empty($this->twitter_access_token)) {
            $tw_result = $this->post_to_twitter($content['tweet']);
            $this->log_social_post(
                $item->id,
                'twitter_post',
                $tw_result['success'] ? 'sent' : 'failed',
                $tw_result['error'] ?? null,
                $tw_result['data'] ?? null
            );
        }

        // Post to Instagram if configured (Graph API content publishing requires an image)
        if (!empty($this->instagram_business_id) && !empty($this->facebook_token) && !empty($content['image'])) {
            $ig_result = $this->post_to_instagram($content['instagram'], $content['image']);
            $this->log_social_post(
                $item->id,
                'instagram_post',
                $ig_result['success'] ? 'sent' : 'failed',
                $ig_result['error'] ?? null,
                $ig_result['data'] ?? null
            );
        }
    }

    /**
     * Build social content from news item
     */
    private function build_social_content($item) {
        $title = $item->processed_title ?: $item->original_title;
        $summary = $item->processed_summary ?: '';
        $url = Config::FRONTEND_URL . '/news/' . $item->id;
        $image = $item->ai_generated_image_url ?: '';

        // Build category hashtags
        $hashtags = '';
        if (!empty($item->categories)) {
            $cats = is_string($item->categories) ? json_decode($item->categories, true) : (array) $item->categories;
            if (is_array($cats)) {
                $tags = array_map(fn($c) => '#' . str_replace('-', '', $c), array_slice($cats, 0, 3));
                $hashtags = implode(' ', $tags);
            }
        }

        // Facebook: title + summary + hashtags
        $fb_text = $title;
        if (!empty($summary)) {
            $fb_text .= "\n\n" . mb_substr($summary, 0, 200);
        }
        if (!empty($hashtags)) {
            $fb_text .= "\n\n" . $hashtags;
        }

        // Twitter: title + URL + hashtags (max 280 chars)
        $tweet_base = $title . "\n\n" . $url;
        if (!empty($hashtags) && mb_strlen($tweet_base . "\n" . $hashtags) <= Config::MAX_SOCIAL_SNIPPET_LENGTH) {
            $tweet_base .= "\n" . $hashtags;
        }
        // Truncate if still over limit
        if (mb_strlen($tweet_base) > Config::MAX_SOCIAL_SNIPPET_LENGTH) {
            $tweet_base = mb_substr($title, 0, Config::MAX_SOCIAL_SNIPPET_LENGTH - mb_strlen("\n\n" . $url) - 3) . "...\n\n" . $url;
        }

        // Instagram: caption = title + summary + UTM link + hashtags. IG shows no
        // clickable links in captions; the UTM URL rides along for the funnel.
        $ig_caption = $title;
        if (!empty($summary)) {
            $ig_caption .= "\n\n" . mb_substr($summary, 0, 200);
        }
        $ig_caption .= "\n\n" . $url . '?utm_source=instagram&utm_medium=social';
        if (!empty($hashtags)) {
            $ig_caption .= "\n\n" . $hashtags;
        }

        return [
            'text' => $fb_text,
            'tweet' => $tweet_base,
            'instagram' => $ig_caption,
            'url' => $url,
            'image' => $image,
        ];
    }

    /**
     * Post to Facebook Page via Graph API
     */
    private function post_to_facebook(string $message, string $link, string $image_url = ''): array {
        $endpoint = Config::FACEBOOK_GRAPH_API . '/' . $this->facebook_page_id . '/feed';

        $body = [
            'message'      => $message,
            'link'         => $link,
            'access_token' => $this->facebook_token,
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($result['id'])) {
            return ['success' => true, 'data' => json_encode(['post_id' => $result['id']])];
        }

        $error = $result['error']['message'] ?? "HTTP {$code}";
        return ['success' => false, 'error' => $error, 'data' => json_encode($result)];
    }

    /**
     * Post to an Instagram Business account via Graph API (2-step: create a
     * media container, then publish it). IG has no text-only feed post — an
     * image_url is required. Reuses the Facebook Page token, which must carry
     * the instagram_content_publish + instagram_basic permissions and be for a
     * Page linked to the IG Business account (instagram_business_id).
     */
    private function post_to_instagram(string $caption, string $image_url): array {
        $base = Config::FACEBOOK_GRAPH_API . '/' . $this->instagram_business_id;

        // Step 1: create the media container
        $create = wp_remote_post($base . '/media', [
            'timeout' => 30,
            'body'    => [
                'image_url'    => $image_url,
                'caption'      => $caption,
                'access_token' => $this->facebook_token,
            ],
        ]);

        if (is_wp_error($create)) {
            return ['success' => false, 'error' => $create->get_error_message()];
        }

        $create_code = wp_remote_retrieve_response_code($create);
        $create_body = json_decode(wp_remote_retrieve_body($create), true);
        $creation_id = $create_body['id'] ?? '';

        if ($create_code !== 200 || $creation_id === '') {
            $error = $create_body['error']['message'] ?? "HTTP {$create_code} (media)";
            return ['success' => false, 'error' => $error, 'data' => json_encode($create_body)];
        }

        // Step 2: publish the container
        $publish = wp_remote_post($base . '/media_publish', [
            'timeout' => 30,
            'body'    => [
                'creation_id'  => $creation_id,
                'access_token' => $this->facebook_token,
            ],
        ]);

        if (is_wp_error($publish)) {
            return ['success' => false, 'error' => $publish->get_error_message()];
        }

        $publish_code = wp_remote_retrieve_response_code($publish);
        $publish_body = json_decode(wp_remote_retrieve_body($publish), true);

        if ($publish_code === 200 && !empty($publish_body['id'])) {
            return ['success' => true, 'data' => json_encode(['post_id' => $publish_body['id']])];
        }

        $error = $publish_body['error']['message'] ?? "HTTP {$publish_code} (publish)";
        return ['success' => false, 'error' => $error, 'data' => json_encode($publish_body)];
    }

    /**
     * Post to Twitter/X via API v2 with OAuth 1.0a
     */
    private function post_to_twitter(string $text): array {
        $url = Config::TWITTER_API . '/tweets';

        // Build OAuth 1.0a signature
        $oauth_params = [
            'oauth_consumer_key'     => $this->twitter_api_key,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_token'            => $this->twitter_access_token,
            'oauth_version'          => '1.0',
        ];

        $base_string = $this->build_oauth_base_string('POST', $url, $oauth_params);
        $signing_key = rawurlencode($this->twitter_api_secret) . '&' . rawurlencode($this->twitter_access_secret);
        $oauth_params['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));

        // Build Authorization header
        $auth_parts = [];
        foreach ($oauth_params as $key => $value) {
            $auth_parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        $auth_header = 'OAuth ' . implode(', ', $auth_parts);

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode(['text' => $text]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 201 && !empty($result['data']['id'])) {
            return ['success' => true, 'data' => json_encode(['tweet_id' => $result['data']['id']])];
        }

        $error = $result['detail'] ?? $result['title'] ?? "HTTP {$code}";
        return ['success' => false, 'error' => $error, 'data' => json_encode($result)];
    }

    /**
     * Build OAuth 1.0a base string for Twitter signature
     */
    private function build_oauth_base_string(string $method, string $url, array $params): string {
        ksort($params);
        $param_string = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);
    }

    /**
     * Log social post attempt to delivery_log
     */
    private function log_social_post(int $news_id, string $channel, string $status, ?string $error = null, ?string $metadata = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';

        $wpdb->insert($table, [
            'user_id'       => null, // Platform-level post (no WP user; NULL is exempt from the user_id FK)
            'news_id'       => $news_id,
            'channel'       => $channel,
            'status'        => $status,
            'sent_at'       => current_time('mysql'),
            'error_message' => $error,
            'metadata'      => $metadata,
            'created_at'    => current_time('mysql'),
        ]);
    }

    /**
     * Retry failed social posts (called by cron)
     */
    public function retry_failed_posts() {
        if (!$this->enabled) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_delivery_log';
        $news_table = $wpdb->prefix . 'teinformez_news_queue';

        // Get failed social posts from last 24h, max 3 retries
        $failed = $wpdb->get_results($wpdb->prepare(
            "SELECT dl.id, dl.news_id, dl.channel, dl.metadata,
                    (SELECT COUNT(*) FROM {$table} dl2
                     WHERE dl2.news_id = dl.news_id AND dl2.channel = dl.channel) as attempt_count
             FROM {$table} dl
             WHERE dl.status = 'failed'
               AND dl.channel IN ('facebook_post', 'twitter_post', 'instagram_post')
               AND dl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             HAVING attempt_count < %d",
            Config::SOCIAL_MAX_RETRY
        ));

        foreach ($failed as $post) {
            $item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$news_table} WHERE id = %d AND status = 'published'",
                $post->news_id
            ));

            if (!$item) {
                continue;
            }

            $content = $this->build_social_content($item);

            if ($post->channel === 'facebook_post' && !empty($this->facebook_token)) {
                $result = $this->post_to_facebook($content['text'], $content['url'], $content['image']);
            } elseif ($post->channel === 'twitter_post' && !empty($this->twitter_access_token)) {
                $result = $this->post_to_twitter($content['tweet']);
            } elseif ($post->channel === 'instagram_post' && !empty($this->instagram_business_id) && !empty($this->facebook_token) && !empty($content['image'])) {
                $result = $this->post_to_instagram($content['instagram'], $content['image']);
            } else {
                continue;
            }

            // Update original record status
            if ($result['success']) {
                $wpdb->update($table, [
                    'status'  => 'sent',
                    'sent_at' => current_time('mysql'),
                    'metadata' => $result['data'] ?? null,
                    'error_message' => null,
                ], ['id' => $post->id]);
            } else {
                $wpdb->update($table, [
                    'error_message' => $result['error'] ?? 'Retry failed',
                ], ['id' => $post->id]);
            }
        }
    }

}
