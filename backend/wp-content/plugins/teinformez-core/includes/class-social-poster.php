<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Social Media Poster
 * Auto-posts published news to Facebook Page and Twitter/X.
 * Platform-level posting (user_id=0 in delivery_log).
 */
class Social_Poster {

    private $facebook_page_id;
    private $facebook_token;
    private $twitter_api_key;
    private $twitter_api_secret;
    private $twitter_access_token;
    private $twitter_access_secret;
    private $enabled;

    public function __construct() {
        $this->enabled              = Config::get('social_posting_enabled', '0') === '1';
        $this->facebook_page_id     = Config::get('facebook_page_id', '');
        $this->facebook_token       = Config::get('facebook_access_token', '');
        $this->twitter_api_key      = Config::get('twitter_api_key', '');
        $this->twitter_api_secret   = Config::get('twitter_api_secret', '');
        $this->twitter_access_token = Config::get('twitter_access_token', '');
        $this->twitter_access_secret = Config::get('twitter_access_token_secret', '');
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

        return [
            'text' => $fb_text,
            'tweet' => $tweet_base,
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
            'user_id'       => 0, // Platform-level post
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
        $failed = $wpdb->get_results(
            "SELECT dl.id, dl.news_id, dl.channel, dl.metadata,
                    (SELECT COUNT(*) FROM {$table} dl2
                     WHERE dl2.news_id = dl.news_id AND dl2.channel = dl.channel) as attempt_count
             FROM {$table} dl
             WHERE dl.status = 'failed'
               AND dl.channel IN ('facebook_post', 'twitter_post')
               AND dl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             HAVING attempt_count < " . Config::SOCIAL_MAX_RETRY
        );

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

    /**
     * On-demand social publishing for Juridic entries.
     */
    public function post_juridic_on_demand($item, array $platforms = ['facebook', 'twitter', 'instagram']): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'results' => [],
                'error' => 'Social posting disabled',
            ];
        }

        $content = $this->build_juridic_social_content($item);
        $results = [];

        if (in_array('facebook', $platforms, true) && !empty($this->facebook_page_id) && !empty($this->facebook_token)) {
            $res = $this->post_to_facebook($content['facebook'], $content['url']);
            $results['facebook'] = $res;
            $this->log_social_post(
                (int) $item->id,
                'facebook_post',
                $res['success'] ? 'sent' : 'failed',
                $res['error'] ?? null,
                $res['data'] ?? null
            );
        }

        if (in_array('twitter', $platforms, true) && !empty($this->twitter_api_key) && !empty($this->twitter_access_token)) {
            $res = $this->post_to_twitter($content['twitter']);
            $results['twitter'] = $res;
            $this->log_social_post(
                (int) $item->id,
                'twitter_post',
                $res['success'] ? 'sent' : 'failed',
                $res['error'] ?? null,
                $res['data'] ?? null
            );
        }

        if (in_array('instagram', $platforms, true)) {
            $results['instagram'] = [
                'success' => false,
                'error' => 'Instagram requires Business API integration and media publishing pipeline.',
            ];
            $this->log_social_post(
                (int) $item->id,
                'instagram_post',
                'failed',
                $results['instagram']['error'],
                null
            );
        }

        $success = false;
        foreach ($results as $result) {
            if (!empty($result['success'])) {
                $success = true;
                break;
            }
        }

        return [
            'success' => $success,
            'results' => $results,
            'url' => $content['url'],
        ];
    }

    private function build_juridic_social_content($item): array {
        $title = !empty($item->column_title)
            ? $item->column_title
            : 'Juridic cu Alina: intrebare noua';
        $question = trim((string) ($item->question_anonymized ?? $item->question ?? ''));
        $summary = trim((string) ($item->answer_summary ?? ''));
        $url = Config::FRONTEND_URL . '/juridic/' . (int) $item->id;
        $category = trim((string) ($item->category ?? 'juridic'));
        $category_hashtag = '#' . str_replace(['-', '_'], '', $category);
        $base_tags = '#JuridicCuAlina #TeInformez ' . $category_hashtag;

        $fb = $title;
        if ($question !== '') {
            $fb .= "\n\nÎntrebare: " . mb_substr($question, 0, 220);
        }
        if ($summary !== '') {
            $fb .= "\n\nPe scurt: " . mb_substr($summary, 0, 280);
        }
        $fb .= "\n\n" . $url . "\n" . $base_tags;

        $tweet = $title . "\n" . $url . "\n" . $base_tags;
        if (mb_strlen($tweet) > Config::MAX_SOCIAL_SNIPPET_LENGTH) {
            $tweet = mb_substr($title, 0, 120) . "...\n" . $url . "\n#Juridic";
        }

        $instagram = $title;
        if ($summary !== '') {
            $instagram .= "\n\n" . mb_substr($summary, 0, 420);
        } elseif ($question !== '') {
            $instagram .= "\n\n" . mb_substr($question, 0, 420);
        }
        $instagram .= "\n\nLink în bio.\n#Juridic #AlinaRaspunde #LegalTips #Romania";

        return [
            'url' => $url,
            'facebook' => $fb,
            'twitter' => $tweet,
            'instagram' => $instagram,
        ];
    }
}
