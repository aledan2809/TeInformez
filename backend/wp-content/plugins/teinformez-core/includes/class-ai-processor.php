<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Processor
 * Processes news using OpenAI API for summarization, translation, and categorization
 */
class AI_Processor {

    private $api_key;
    private $model;

    public function __construct() {
        $this->api_key = Config::get('openai_api_key', '');
        $this->model = Config::OPENAI_MODEL;
    }

    /**
     * Process all fetched news items
     */
    public function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Get items waiting to be processed
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'fetched' ORDER BY fetched_at ASC LIMIT 10"
        );

        if (empty($items)) {
            error_log('TeInformez AI: No items to process');
            return ['processed' => 0];
        }

        $processed = 0;

        foreach ($items as $item) {
            $result = $this->process_item($item);
            if ($result['success']) {
                $processed++;
            }
        }

        error_log('TeInformez AI: Processed ' . $processed . ' items');
        return ['processed' => $processed];
    }

    /**
     * Process a single news item
     */
    public function process_item($item) {
        if (empty($this->api_key)) {
            error_log('TeInformez AI ERROR: OpenAI API key not configured');
            return ['success' => false, 'error' => 'API key not configured'];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Mark as processing
        $wpdb->update($table, ['status' => 'processing'], ['id' => $item->id]);

        try {
            // Determine target language (Romanian by default)
            $target_language = Config::SITE_LANGUAGE;

            // Get AI summary and translation
            $result = $this->call_openai([
                'title' => $item->original_title,
                'content' => substr($item->original_content, 0, 4000), // Limit content length
                'source_language' => $item->original_language,
                'target_language' => $target_language,
                'categories' => json_decode($item->categories, true) ?? []
            ]);

            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            // Update item with processed data
            $wpdb->update($table, [
                'processed_title' => $result['data']['title'],
                'processed_summary' => $result['data']['summary'],
                'processed_content' => $result['data']['content'],
                'target_language' => $target_language,
                'categories' => json_encode($result['data']['categories']),
                'tags' => json_encode($result['data']['tags']),
                'status' => 'pending_review',
                'processed_at' => current_time('mysql')
            ], ['id' => $item->id]);

            error_log('TeInformez AI: Successfully processed item #' . $item->id);

            return ['success' => true, 'item_id' => $item->id];

        } catch (\Exception $e) {
            error_log('TeInformez AI ERROR: ' . $e->getMessage());

            // Mark as failed but keep for retry
            $wpdb->update($table, [
                'status' => 'fetched',
                'admin_notes' => 'AI processing failed: ' . $e->getMessage()
            ], ['id' => $item->id]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($data) {
        $prompt = $this->build_prompt($data);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional news editor and translator. You process news articles by summarizing, translating, and categorizing them. Always respond with valid JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'response_format' => ['type' => 'json_object']
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return ['success' => false, 'error' => $body['error']['message'] ?? 'Unknown OpenAI error'];
        }

        if (empty($body['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Empty response from OpenAI'];
        }

        $result = json_decode($body['choices'][0]['message']['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse OpenAI response'];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Build prompt for OpenAI
     */
    private function build_prompt($data) {
        $target_lang_name = $this->get_language_name($data['target_language']);
        $source_lang_name = $this->get_language_name($data['source_language']);
        $categories_list = implode(', ', array_keys(Config::DEFAULT_CATEGORIES));

        $prompt = <<<PROMPT
Process this news article and return a JSON object with the following structure:
{
    "title": "Translated title in {$target_lang_name}",
    "summary": "A concise 2-3 sentence summary in {$target_lang_name} (max 150 words)",
    "content": "The full article content rewritten in {$target_lang_name}, maintaining journalistic quality",
    "categories": ["array", "of", "relevant", "category", "slugs"],
    "tags": ["array", "of", "relevant", "tags", "in", "{$target_lang_name}"]
}

Available categories: {$categories_list}

Original article ({$source_lang_name}):
Title: {$data['title']}

Content:
{$data['content']}

Instructions:
1. Translate the content to {$target_lang_name} if not already in that language
2. Create a concise summary capturing the key points
3. Rewrite the content in clear, professional {$target_lang_name}
4. Select 1-3 most relevant categories from the available list
5. Generate 3-5 relevant tags in {$target_lang_name}
6. Maintain factual accuracy - do not add information not present in the original
PROMPT;

        return $prompt;
    }

    /**
     * Get language name from code
     */
    private function get_language_name($code) {
        $languages = [
            'ro' => 'Romanian',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'hu' => 'Hungarian',
            'bg' => 'Bulgarian'
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Generate AI image for article (optional)
     */
    public function generate_image($prompt) {
        if (empty($this->api_key)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => Config::OPENAI_IMAGE_MODEL,
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard'
            ]),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return ['success' => false, 'error' => $body['error']['message']];
        }

        return [
            'success' => true,
            'url' => $body['data'][0]['url'] ?? null
        ];
    }
}
