<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Processor
 * Processes news using Anthropic Claude API (primary) or OpenAI (fallback)
 * for summarization, translation, and categorization
 */
class AI_Processor {

    private $provider;
    private $anthropic_key;
    private $openai_key;
    private $groq_key;
    private $model;

    public function __construct() {
        $this->provider = Config::AI_PROVIDER;
        $this->anthropic_key = Config::get('anthropic_api_key', '');
        $this->openai_key = Config::get('openai_api_key', '');
        $this->groq_key = Config::get('groq_api_key', '');

        if ($this->provider === 'anthropic') {
            $this->model = Config::ANTHROPIC_MODEL;
        } else {
            $this->model = Config::OPENAI_MODEL;
        }
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
        $api_key = ($this->provider === 'anthropic') ? $this->anthropic_key : $this->openai_key;

        if (empty($api_key)) {
            error_log('TeInformez AI ERROR: ' . $this->provider . ' API key not configured');
            return ['success' => false, 'error' => 'API key not configured for ' . $this->provider];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        // Mark as processing
        $wpdb->update($table, ['status' => 'processing'], ['id' => $item->id]);

        try {
            // Determine target language (Romanian by default)
            $target_language = Config::SITE_LANGUAGE;

            // Get AI summary and translation
            $data = [
                'title' => $item->original_title,
                'content' => mb_substr($item->original_content, 0, 8000),
                'source_language' => $item->original_language,
                'target_language' => $target_language,
                'categories' => json_decode($item->categories, true) ?? []
            ];

            if ($this->provider === 'anthropic') {
                $result = $this->call_anthropic($data);
            } else {
                $result = $this->call_openai($data);
            }

            // Fallback chain: primary -> secondary -> Groq
            if (!$result['success'] && $this->provider === 'anthropic' && !empty($this->openai_key)) {
                error_log('TeInformez AI: Anthropic failed, trying OpenAI fallback');
                $result = $this->call_openai($data);
            } elseif (!$result['success'] && $this->provider === 'openai' && !empty($this->anthropic_key)) {
                error_log('TeInformez AI: OpenAI failed, trying Anthropic fallback');
                $result = $this->call_anthropic($data);
            }

            // Groq as final fallback if both primary providers fail
            if (!$result['success'] && !empty($this->groq_key)) {
                error_log('TeInformez AI: Primary providers failed, trying Groq fallback');
                $result = $this->call_groq($data);
            }

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

            // Trigger Chief Editor review
            do_action('teinformez_article_pending_review', $item->id);

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
     * Call Anthropic Claude API
     */
    private function call_anthropic($data) {
        $prompt = $this->build_prompt($data);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->anthropic_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => Config::ANTHROPIC_MODEL,
                'max_tokens' => 2000,
                'temperature' => 0.3,
                'system' => 'You are a professional news editor and translator. You process news articles by summarizing, translating, and categorizing them. Always respond with valid JSON only — no markdown, no code fences, just the JSON object.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $body['error']['message'] ?? ('HTTP ' . $status_code);
            return ['success' => false, 'error' => $error_msg];
        }

        if (empty($body['content'][0]['text'])) {
            return ['success' => false, 'error' => 'Empty response from Anthropic'];
        }

        $text = $body['content'][0]['text'];

        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse Anthropic response: ' . json_last_error_msg()];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Call OpenAI API (fallback)
     */
    private function call_openai($data) {
        $prompt = $this->build_prompt($data);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => Config::OPENAI_MODEL,
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
     * Call Groq API (final fallback — uses llama-3.3-70b-versatile)
     */
    private function call_groq($data) {
        $prompt = $this->build_prompt($data);

        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groq_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional news editor and translator. You process news articles by summarizing, translating, and categorizing them. Always respond with valid JSON only — no markdown, no code fences, just the JSON object.'
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
            return ['success' => false, 'error' => 'Groq: ' . $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return ['success' => false, 'error' => 'Groq: ' . ($body['error']['message'] ?? 'Unknown error')];
        }

        if (empty($body['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Empty response from Groq'];
        }

        $text = $body['choices'][0]['message']['content'];

        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse Groq response: ' . json_last_error_msg()];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Build prompt for AI processing
     */
    private function build_prompt($data) {
        $target_lang_name = $this->get_language_name($data['target_language']);
        $source_lang_name = $this->get_language_name($data['source_language']);
        $categories_list = implode(', ', array_keys(Config::DEFAULT_CATEGORIES));
        $same_language = ($data['source_language'] === $data['target_language']);
        $content_length = mb_strlen($data['content']);

        if ($same_language && $content_length > 500) {
            // Full article in same language — summarize and condense
            $prompt = <<<PROMPT
You are a news editor for TeInformez.eu. Process this {$source_lang_name} article and return a JSON object:
{
    "title": "A fresh, engaging headline (NOT a copy of the original — rephrase it)",
    "summary": "2-3 sentence summary capturing the key facts (max 100 words)",
    "content": "A condensed version of the article (3-5 paragraphs, ~300-500 words). Keep all key facts, quotes, and data points. Write in clear, neutral journalistic {$target_lang_name}. Do NOT copy sentences verbatim — rephrase everything in your own words.",
    "categories": ["category_slugs"],
    "tags": ["relevant_tags_in_{$target_lang_name}"]
}

Available categories: {$categories_list}

Original article:
Title: {$data['title']}

Content:
{$data['content']}

CRITICAL RULES:
1. The title MUST be different from the original — rephrase it while keeping the meaning
2. Do NOT copy-paste sentences from the original. Rephrase ALL content in your own words
3. Keep factual accuracy — do not invent information
4. The content should be 300-500 words, covering the most important points
5. Select 1-3 categories from the available list
6. Generate 3-5 tags in {$target_lang_name}
7. Return ONLY valid JSON — no markdown, no code fences
PROMPT;
        } else {
            // Different language or short content — translate and expand
            $prompt = <<<PROMPT
You are a news editor and translator for TeInformez.eu. Process this article and return a JSON object:
{
    "title": "An engaging headline in {$target_lang_name} (NOT a literal translation — adapt it naturally)",
    "summary": "2-3 sentence summary in {$target_lang_name} (max 100 words)",
    "content": "The article rewritten in professional {$target_lang_name} (3-5 paragraphs, ~300-500 words). Translate and rephrase — do not translate word-for-word.",
    "categories": ["category_slugs"],
    "tags": ["relevant_tags_in_{$target_lang_name}"]
}

Available categories: {$categories_list}

Original article ({$source_lang_name}):
Title: {$data['title']}

Content:
{$data['content']}

CRITICAL RULES:
1. Translate to natural, fluent {$target_lang_name} — avoid literal translations
2. The title should be adapted for a {$target_lang_name}-speaking audience
3. Keep factual accuracy — do not invent information
4. Content should be 300-500 words
5. Select 1-3 categories from the available list
6. Generate 3-5 tags in {$target_lang_name}
7. Return ONLY valid JSON — no markdown, no code fences
PROMPT;
        }

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
     * Generate AI image for article (optional, OpenAI only)
     */
    public function generate_image($prompt) {
        if (empty($this->openai_key)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured (required for image generation)'];
        }

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_key,
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
