<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chief Editor AI Agent
 * Reviews and improves articles before publishing.
 * Activated automatically when an article reaches pending_review status.
 */
class Chief_Editor {

    private $provider;
    private $anthropic_key;
    private $openai_key;
    private $groq_key;
    private $ai_router_url;

    public function __construct() {
        $this->provider = Config::AI_PROVIDER;
        $this->anthropic_key = Config::get('anthropic_api_key', '');
        $this->openai_key = Config::get('openai_api_key', '');
        $this->groq_key = Config::get('groq_api_key', '');
        $this->ai_router_url = get_option('teinformez_ai_router_url', 'http://127.0.0.1:3100/api/ai/chat');
    }

    /**
     * Check if Chief Editor is enabled
     */
    public static function is_enabled() {
        return get_option('teinformez_chief_editor_enabled', '1') === '1';
    }

    /**
     * Review and publish an article that reached pending_review
     */
    public function review_and_publish($article_id) {
        if (!self::is_enabled()) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_news_queue';

        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'pending_review'",
            $article_id
        ));

        if (!$article) {
            $this->log($article_id, 'skipped', null, 'none', 'Article not found or not in pending_review');
            return false;
        }

        error_log('TeInformez Chief Editor: Reviewing article #' . $article_id);

        // Build the review payload
        $payload = json_encode([
            'title' => $article->processed_title,
            'summary' => $article->processed_summary,
            'content' => $article->processed_content,
            'categories' => json_decode($article->categories, true) ?? [],
            'tags' => json_decode($article->tags, true) ?? [],
            'has_image' => !empty($article->ai_generated_image_url),
            'has_video' => !empty($article->youtube_embed),
        ], JSON_UNESCAPED_UNICODE);

        // Call AI with fallback chain
        $ai_result = $this->call_ai($payload);

        if (!$ai_result['success']) {
            // AI failed — log and leave as pending_review (old flow takes over)
            $this->log($article_id, 'error', null, $ai_result['provider'] ?? 'none', 'AI call failed: ' . $ai_result['error']);
            error_log('TeInformez Chief Editor ERROR: ' . $ai_result['error']);
            return false;
        }

        $review = $ai_result['data'];
        $used_provider = $ai_result['provider'];
        $changes = [];

        // Check if article should be rejected
        if (!empty($review['reject_reason'])) {
            $wpdb->update($table, [
                'status' => 'rejected',
                'admin_notes' => 'Chief Editor: ' . $review['reject_reason'],
                'reviewed_at' => current_time('mysql'),
            ], ['id' => $article_id]);

            $this->log($article_id, 'rejected', json_encode(['reject_reason' => $review['reject_reason']]), $used_provider);
            error_log('TeInformez Chief Editor: Rejected article #' . $article_id . ' — ' . $review['reject_reason']);
            return true;
        }

        // Apply improvements
        $update_data = [];

        if (!empty($review['title']) && $review['title'] !== $article->processed_title) {
            $changes['title'] = ['old' => $article->processed_title, 'new' => $review['title']];
            $update_data['processed_title'] = $review['title'];
        }

        if (!empty($review['summary']) && $review['summary'] !== $article->processed_summary) {
            $changes['summary'] = ['old' => mb_substr($article->processed_summary, 0, 100) . '...', 'new' => mb_substr($review['summary'], 0, 100) . '...'];
            $update_data['processed_summary'] = $review['summary'];
        }

        if (!empty($review['content']) && $review['content'] !== $article->processed_content) {
            $changes['content'] = ['modified' => true];
            $update_data['processed_content'] = $review['content'];
        }

        if (!empty($review['categories']) && is_array($review['categories'])) {
            $old_cats = json_decode($article->categories, true) ?? [];
            if ($review['categories'] !== $old_cats) {
                $changes['categories'] = ['old' => $old_cats, 'new' => $review['categories']];
                $update_data['categories'] = json_encode($review['categories']);
            }
        }

        if (!empty($review['tags']) && is_array($review['tags'])) {
            $old_tags = json_decode($article->tags, true) ?? [];
            if ($review['tags'] !== $old_tags) {
                $changes['tags'] = ['old' => $old_tags, 'new' => $review['tags']];
                $update_data['tags'] = json_encode($review['tags']);
            }
        }

        // Always publish — set status and timestamp
        $update_data['status'] = 'published';
        $update_data['published_at'] = current_time('mysql');
        $update_data['reviewed_at'] = current_time('mysql');
        $update_data['admin_notes'] = 'Published by Chief Editor AI' . (!empty($review['media_notes']) ? ' | Media: ' . $review['media_notes'] : '');

        $wpdb->update($table, $update_data, ['id' => $article_id]);

        // Log the review
        $this->log($article_id, 'published', !empty($changes) ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null, $used_provider);

        // Trigger post-publish hooks (social media, delivery, etc.)
        $published_article = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $article_id));
        if ($published_article) {
            do_action('teinformez_news_published', $published_article);
        }

        $change_count = count($changes);
        error_log("TeInformez Chief Editor: Published article #{$article_id} with {$change_count} change(s) via {$used_provider}");

        return true;
    }

    /**
     * Call AI with provider fallback chain
     */
    private function call_ai($user_message) {
        $system_prompt = $this->get_system_prompt();

        // Try AI Router microservice first
        $result = $this->call_ai_router($system_prompt, $user_message);
        if ($result['success']) return $result;

        // AI Router unavailable — fallback to direct provider calls
        if (strpos($result['error'] ?? '', 'AI Router') !== false) {
            error_log('TeInformez Chief Editor: AI Router unavailable, falling back to direct calls');
        }

        // Try primary provider
        if ($this->provider === 'anthropic' && !empty($this->anthropic_key)) {
            $result = $this->call_anthropic($system_prompt, $user_message);
            if ($result['success']) return array_merge($result, ['provider' => 'anthropic']);
            error_log('TeInformez Chief Editor: Anthropic failed, trying OpenAI');
        }

        if (!empty($this->openai_key)) {
            $result = $this->call_openai($system_prompt, $user_message);
            if ($result['success']) return array_merge($result, ['provider' => 'openai']);
            error_log('TeInformez Chief Editor: OpenAI failed, trying Groq');
        }

        if ($this->provider !== 'anthropic' && !empty($this->anthropic_key)) {
            $result = $this->call_anthropic($system_prompt, $user_message);
            if ($result['success']) return array_merge($result, ['provider' => 'anthropic']);
        }

        if (!empty($this->groq_key)) {
            $result = $this->call_groq($system_prompt, $user_message);
            if ($result['success']) return array_merge($result, ['provider' => 'groq']);
        }

        return ['success' => false, 'error' => 'All AI providers failed', 'provider' => 'none'];
    }

    /**
     * Call AI Router microservice
     */
    private function call_ai_router($system_prompt, $user_message) {
        if (empty($this->ai_router_url)) {
            return ['success' => false, 'error' => 'AI Router URL not configured'];
        }

        $response = wp_remote_post($this->ai_router_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'system' => $system_prompt,
                'prompt' => $user_message,
                'maxTokens' => 4096,
                'temperature' => 0.3,
                'projectName' => 'teinformez',
                'jsonMode' => true,
                'languageHint' => 'ro',
                'taskHint' => 'analysis',
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => 'AI Router connection failed: ' . $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($body['text'])) {
            $error = $body['error'] ?? ('AI Router HTTP ' . $status_code);
            return ['success' => false, 'error' => 'AI Router: ' . $error];
        }

        $parsed = $this->parse_json_response($body['text']);

        if ($parsed['success']) {
            $provider = $body['provider'] ?? 'ai-router';
            error_log('TeInformez Chief Editor: Processed via AI Router (provider: ' . $provider . ')');
            return array_merge($parsed, ['provider' => $provider]);
        }

        return $parsed;
    }

    /**
     * System prompt for Chief Editor persona
     */
    private function get_system_prompt() {
        return <<<'PROMPT'
Ești **Redactor-Șef** cu peste 20 de ani de experiență în jurnalism și presă din România.
Sarcina ta: **revizuiește și îmbunătățește** articolele de știri pentru **claritate, acuratețe și stil jurnalistic profesional**.

Reguli:
1. **Titlul**: Trebuie să fie **atractiv dar corect**. Rescrie-l dacă e vag, clickbait sau neatrăgător. Păstrează-l concis (max 100 caractere).
2. **Rezumat**: Verifică să fie clar și complet. Corectează dacă e necesar.
3. **Conținut**:
   - Corectează **gramatica, diacriticele, greșelile de tipar**.
   - Asigură **ton neutru** (fără prejudecăți/senzaționalism).
   - **Structură**: Informația cheie la început, paragrafe scurte.
   - Păstrează toate faptele, citatele și datele importante.
4. **Categorii/Tag-uri**: Verifică relevanța. Corectează dacă sunt clasificate greșit.
5. **Media**: Notează dacă imaginea sau video-ul nu pare relevant.

**Format de răspuns**: Returnează un JSON cu DOAR câmpurile care necesită modificări:
{
    "title": "Titlu nou (doar dacă l-ai schimbat)",
    "summary": "Rezumat revizuit (doar dacă l-ai schimbat)",
    "content": "Conținut complet revizuit (doar dacă l-ai schimbat)",
    "categories": ["lista", "corectată"],
    "tags": ["lista", "corectată"],
    "reject_reason": "Motiv respingere (DOAR dacă articolul e de calitate scăzută, înșelător sau irelevant)",
    "media_notes": "Note despre imagine/video (opțional)"
}

**Reguli stricte**:
- Dacă articolul e **OK fără modificări**, returnează un JSON GOL: {}
- Dacă articolul e **de calitate foarte scăzută, înșelător sau complet irelevant**, setează `reject_reason`.
- Returnează DOAR JSON valid — fără markdown, fără code fences, doar obiectul JSON.
- Limba articolelor este română — respectă regulile gramaticale ale limbii române.
PROMPT;
    }

    /**
     * Call Anthropic Claude API
     */
    private function call_anthropic($system_prompt, $user_message) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->anthropic_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => Config::ANTHROPIC_MODEL,
                'max_tokens' => 4096,
                'temperature' => 0.3,
                'system' => $system_prompt,
                'messages' => [
                    ['role' => 'user', 'content' => $user_message],
                ],
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            return ['success' => false, 'error' => $body['error']['message'] ?? ('HTTP ' . $status_code)];
        }

        if (empty($body['content'][0]['text'])) {
            return ['success' => false, 'error' => 'Empty response from Anthropic'];
        }

        return $this->parse_json_response($body['content'][0]['text']);
    }

    /**
     * Call OpenAI API
     */
    private function call_openai($system_prompt, $user_message) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => Config::OPENAI_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_message],
                ],
                'temperature' => 0.3,
                'max_tokens' => 4096,
                'response_format' => ['type' => 'json_object'],
            ]),
            'timeout' => 90,
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

        return $this->parse_json_response($body['choices'][0]['message']['content']);
    }

    /**
     * Call Groq API (final fallback)
     */
    private function call_groq($system_prompt, $user_message) {
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groq_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_message],
                ],
                'temperature' => 0.3,
                'max_tokens' => 4096,
                'response_format' => ['type' => 'json_object'],
            ]),
            'timeout' => 90,
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

        return $this->parse_json_response($body['choices'][0]['message']['content']);
    }

    /**
     * Parse JSON from AI response text
     */
    private function parse_json_response($text) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);
        $text = trim($text);

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Failed to parse AI response: ' . json_last_error_msg()];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Log Chief Editor action
     */
    private function log($news_id, $action, $changes = null, $ai_provider = 'none', $notes = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_chief_editor_logs';

        // Ensure table exists
        $this->ensure_log_table();

        $wpdb->insert($table, [
            'news_id' => $news_id,
            'action' => $action,
            'changes' => $changes,
            'ai_provider' => $ai_provider,
            'notes' => $notes,
            'processed_at' => current_time('mysql'),
        ]);
    }

    /**
     * Create log table if not exists
     */
    private function ensure_log_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'teinformez_chief_editor_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            news_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            changes JSON DEFAULT NULL,
            ai_provider VARCHAR(20) NOT NULL DEFAULT 'none',
            notes TEXT DEFAULT NULL,
            processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_news_id (news_id),
            INDEX idx_action (action)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
