<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Telegram integration API
 */
class Telegram_API extends REST_API {

    private const TOKEN_META_KEY = '_teinformez_telegram_bot_token';
    private const GROUPS_META_KEY = '_teinformez_telegram_groups';

    public function register_routes() {
        register_rest_route($this->namespace, '/telegram/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => [$this, 'is_authenticated'],
        ]);

        register_rest_route($this->namespace, '/telegram/config', [
            'methods' => 'PUT',
            'callback' => [$this, 'save_config'],
            'permission_callback' => [$this, 'is_authenticated'],
        ]);

        register_rest_route($this->namespace, '/telegram/groups/discover', [
            'methods' => 'POST',
            'callback' => [$this, 'discover_groups'],
            'permission_callback' => [$this, 'is_authenticated'],
        ]);

        register_rest_route($this->namespace, '/telegram/messages/read', [
            'methods' => 'POST',
            'callback' => [$this, 'read_messages'],
            'permission_callback' => [$this, 'is_authenticated'],
        ]);

        register_rest_route($this->namespace, '/telegram/messages/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_message'],
            'permission_callback' => [$this, 'is_authenticated'],
        ]);
    }

    public function get_config($request) {
        $user_id = $this->get_current_user_id();
        $token = $this->get_user_token($user_id);
        $groups = $this->get_user_groups($user_id);

        return $this->success([
            'has_token' => !empty($token),
            'token_mask' => $this->mask_token($token),
            'groups' => $groups,
        ]);
    }

    public function save_config($request) {
        $user_id = $this->get_current_user_id();
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $token = isset($params['bot_token']) ? trim((string) $params['bot_token']) : '';
        $groups = $this->normalize_groups($params['groups'] ?? []);

        if (!empty($token)) {
            $validation = $this->telegram_request($token, 'getMe', [], 'GET');
            if (is_wp_error($validation)) {
                return $this->error(
                    __('Token Telegram invalid.', 'teinformez'),
                    'telegram_invalid_token',
                    400,
                    ['details' => $validation->get_error_message()]
                );
            }

            update_user_meta($user_id, self::TOKEN_META_KEY, $token);
        }

        if (is_array($params['groups'] ?? null)) {
            update_user_meta($user_id, self::GROUPS_META_KEY, $groups);
        }

        return $this->success([
            'has_token' => !empty($token) || !empty($this->get_user_token($user_id)),
            'groups' => $this->get_user_groups($user_id),
        ], __('Configurare Telegram salvată.', 'teinformez'));
    }

    public function discover_groups($request) {
        $user_id = $this->get_current_user_id();
        $token = $this->get_user_token($user_id);

        if (empty($token)) {
            return $this->error(__('Configurează mai întâi token-ul Telegram.', 'teinformez'), 'telegram_missing_token', 400);
        }

        $updates = $this->telegram_request($token, 'getUpdates', ['limit' => 100, 'timeout' => 0], 'GET');
        if (is_wp_error($updates)) {
            return $this->error(
                __('Nu s-au putut prelua grupurile Telegram.', 'teinformez'),
                'telegram_discovery_failed',
                502,
                ['details' => $updates->get_error_message()]
            );
        }

        $discovered = $this->extract_groups_from_updates($updates['result'] ?? []);

        $existing = $this->get_user_groups($user_id);
        $merged = $this->merge_groups($existing, $discovered);
        update_user_meta($user_id, self::GROUPS_META_KEY, $merged);

        return $this->success([
            'groups' => $merged,
            'discovered_now' => count($discovered),
        ], __('Grupuri Telegram actualizate.', 'teinformez'));
    }

    public function read_messages($request) {
        $user_id = $this->get_current_user_id();
        $token = $this->get_user_token($user_id);

        if (empty($token)) {
            return $this->error(__('Configurează mai întâi token-ul Telegram.', 'teinformez'), 'telegram_missing_token', 400);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }
        $mode = ($params['mode'] ?? 'parallel') === 'sequential' ? 'sequential' : 'parallel';
        $limit = max(1, min(100, (int) ($params['limit'] ?? 25)));

        $configured_groups = $this->get_user_groups($user_id);
        $requested_group_ids = $this->normalize_group_ids($params['group_ids'] ?? []);
        $group_ids = !empty($requested_group_ids)
            ? $requested_group_ids
            : array_values(array_map(static function ($group) {
                return (string) $group['id'];
            }, $configured_groups));

        if (empty($group_ids)) {
            return $this->error(__('Nu există grupuri selectate.', 'teinformez'), 'telegram_no_groups', 400);
        }

        $updates = $this->telegram_request($token, 'getUpdates', ['limit' => 100, 'timeout' => 0], 'GET');
        if (is_wp_error($updates)) {
            return $this->error(
                __('Nu s-au putut citi mesajele Telegram.', 'teinformez'),
                'telegram_read_failed',
                502,
                ['details' => $updates->get_error_message()]
            );
        }

        $messages_by_group = $this->extract_messages_by_group($updates['result'] ?? [], $group_ids, $limit);

        $groups_report = [];
        foreach ($group_ids as $group_id) {
            $messages = $messages_by_group[$group_id] ?? [];
            $group_meta = $this->find_group($configured_groups, $group_id);

            $groups_report[] = [
                'group_id' => $group_id,
                'title' => $group_meta['title'] ?? ('Group ' . $group_id),
                'messages_count' => count($messages),
                'messages' => $messages,
            ];

            if ($mode === 'sequential') {
                usleep(200000);
            }
        }

        $report = [
            'report_type' => 'read',
            'generated_at' => gmdate('c'),
            'mode' => $mode,
            'groups_count' => count($groups_report),
            'messages_total' => array_sum(array_map(static function ($group) {
                return (int) $group['messages_count'];
            }, $groups_report)),
            'groups' => $groups_report,
        ];

        return $this->success(['report' => $report]);
    }

    public function send_message($request) {
        $user_id = $this->get_current_user_id();
        $token = $this->get_user_token($user_id);

        if (empty($token)) {
            return $this->error(__('Configurează mai întâi token-ul Telegram.', 'teinformez'), 'telegram_missing_token', 400);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }
        $validation = $this->validate_required($params, ['text']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $mode = ($params['mode'] ?? 'parallel') === 'sequential' ? 'sequential' : 'parallel';
        $text = sanitize_textarea_field((string) $params['text']);
        $disable_notification = !empty($params['disable_notification']);

        $configured_groups = $this->get_user_groups($user_id);
        $requested_group_ids = $this->normalize_group_ids($params['group_ids'] ?? []);
        $group_ids = !empty($requested_group_ids)
            ? $requested_group_ids
            : array_values(array_map(static function ($group) {
                return (string) $group['id'];
            }, $configured_groups));

        if (empty($group_ids)) {
            return $this->error(__('Nu există grupuri selectate.', 'teinformez'), 'telegram_no_groups', 400);
        }

        $results = [];
        $sent_count = 0;

        foreach ($group_ids as $group_id) {
            $payload = [
                'chat_id' => $group_id,
                'text' => $text,
                'disable_notification' => $disable_notification,
            ];

            $response = $this->telegram_request($token, 'sendMessage', $payload);
            $group_meta = $this->find_group($configured_groups, $group_id);

            if (is_wp_error($response)) {
                $results[] = [
                    'group_id' => $group_id,
                    'title' => $group_meta['title'] ?? ('Group ' . $group_id),
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'message_id' => null,
                ];

                if ($mode === 'sequential') {
                    break;
                }
            } else {
                $sent_count++;
                $results[] = [
                    'group_id' => $group_id,
                    'title' => $group_meta['title'] ?? ('Group ' . $group_id),
                    'success' => true,
                    'error' => null,
                    'message_id' => (int) ($response['result']['message_id'] ?? 0),
                ];
            }

            if ($mode === 'sequential') {
                usleep(200000);
            }
        }

        $report = [
            'report_type' => 'send',
            'generated_at' => gmdate('c'),
            'mode' => $mode,
            'requested_groups' => count($group_ids),
            'sent_count' => $sent_count,
            'failed_count' => count($results) - $sent_count,
            'results' => $results,
        ];

        return $this->success([
            'report' => $report,
        ], __('Mesaj procesat.', 'teinformez'));
    }

    private function get_user_token($user_id) {
        $token = get_user_meta($user_id, self::TOKEN_META_KEY, true);
        return is_string($token) ? trim($token) : '';
    }

    private function get_user_groups($user_id) {
        $groups = get_user_meta($user_id, self::GROUPS_META_KEY, true);
        return is_array($groups) ? $groups : [];
    }

    private function normalize_groups($groups) {
        if (!is_array($groups)) {
            return [];
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (!is_array($group) || !isset($group['id'])) {
                continue;
            }

            $id = trim((string) $group['id']);
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'title' => sanitize_text_field((string) ($group['title'] ?? ('Group ' . $id))),
            ];
        }

        return $this->deduplicate_groups($normalized);
    }

    private function normalize_group_ids($group_ids) {
        if (!is_array($group_ids)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(static function ($value) {
            return trim((string) $value);
        }, $group_ids), static function ($value) {
            return $value !== '';
        }));

        return array_values(array_unique($normalized));
    }

    private function extract_groups_from_updates($updates) {
        $groups = [];

        foreach ($updates as $update) {
            if (!is_array($update)) {
                continue;
            }

            $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
            if (!is_array($message) || empty($message['chat']) || !is_array($message['chat'])) {
                continue;
            }

            $chat = $message['chat'];
            $chat_type = (string) ($chat['type'] ?? '');
            if (!in_array($chat_type, ['group', 'supergroup'], true)) {
                continue;
            }

            $id = trim((string) ($chat['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $groups[] = [
                'id' => $id,
                'title' => sanitize_text_field((string) ($chat['title'] ?? ('Group ' . $id))),
            ];
        }

        return $this->deduplicate_groups($groups);
    }

    private function extract_messages_by_group($updates, $group_ids, $limit) {
        $group_id_lookup = array_fill_keys($group_ids, true);
        $messages = [];

        foreach ($updates as $update) {
            if (!is_array($update)) {
                continue;
            }

            $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? null;
            if (!is_array($message) || empty($message['chat']) || !is_array($message['chat'])) {
                continue;
            }

            $group_id = trim((string) ($message['chat']['id'] ?? ''));
            if ($group_id === '' || !isset($group_id_lookup[$group_id])) {
                continue;
            }

            if (!isset($messages[$group_id])) {
                $messages[$group_id] = [];
            }

            $messages[$group_id][] = [
                'message_id' => (int) ($message['message_id'] ?? 0),
                'date' => !empty($message['date']) ? gmdate('c', (int) $message['date']) : null,
                'from' => sanitize_text_field((string) ($message['from']['username'] ?? $message['from']['first_name'] ?? 'unknown')),
                'text' => sanitize_textarea_field((string) ($message['text'] ?? $message['caption'] ?? '')),
                'type' => !empty($message['text']) ? 'text' : 'media',
            ];
        }

        foreach ($messages as $group_id => $group_messages) {
            usort($group_messages, static function ($a, $b) {
                return ($b['message_id'] ?? 0) <=> ($a['message_id'] ?? 0);
            });
            $messages[$group_id] = array_slice($group_messages, 0, $limit);
        }

        return $messages;
    }

    private function merge_groups($existing, $discovered) {
        return $this->deduplicate_groups(array_merge(
            is_array($existing) ? $existing : [],
            is_array($discovered) ? $discovered : []
        ));
    }

    private function deduplicate_groups($groups) {
        $seen = [];
        $deduped = [];

        foreach ($groups as $group) {
            if (!is_array($group) || empty($group['id'])) {
                continue;
            }

            $id = (string) $group['id'];
            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $deduped[] = [
                'id' => $id,
                'title' => sanitize_text_field((string) ($group['title'] ?? ('Group ' . $id))),
            ];
        }

        return $deduped;
    }

    private function find_group($groups, $group_id) {
        if (!is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            if ((string) ($group['id'] ?? '') === (string) $group_id) {
                return $group;
            }
        }

        return null;
    }

    private function mask_token($token) {
        if (empty($token)) {
            return null;
        }

        $length = strlen($token);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($token, -4);
    }

    private function telegram_request($token, $method, $params = [], $http_method = 'POST') {
        $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method;

        $args = [
            'timeout' => 20,
        ];

        if (strtoupper($http_method) === 'GET') {
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = $params;
            $response = wp_remote_post($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['ok'])) {
            $description = is_array($decoded) ? (string) ($decoded['description'] ?? '') : '';
            return new \WP_Error(
                'telegram_api_error',
                $description !== '' ? $description : __('Telegram API request failed.', 'teinformez'),
                ['status' => $status]
            );
        }

        return $decoded;
    }
}
