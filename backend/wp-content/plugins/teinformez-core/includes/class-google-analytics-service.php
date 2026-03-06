<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

class Google_Analytics_Service {
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const DATA_API_BASE = 'https://analyticsdata.googleapis.com/v1beta/properties/';
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private string $property_id = '';
    private string $client_email = '';
    private string $private_key = '';

    public function __construct() {
        $this->load_config();
    }

    public function is_configured(): bool {
        return $this->property_id !== '' && $this->client_email !== '' && $this->private_key !== '';
    }

    public function get_summary(string $start_date, string $end_date) {
        $response = $this->run_report($start_date, $end_date, [
            ['name' => 'sessions'],
            ['name' => 'activeUsers'],
            ['name' => 'newUsers'],
            ['name' => 'screenPageViews'],
            ['name' => 'averageSessionDuration'],
            ['name' => 'eventCount'],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $values = $response['rows'][0]['metricValues'] ?? [];
        $sessions = isset($values[0]['value']) ? (int) $values[0]['value'] : 0;
        $active_users = isset($values[1]['value']) ? (int) $values[1]['value'] : 0;
        $new_users = isset($values[2]['value']) ? (int) $values[2]['value'] : 0;
        $page_views = isset($values[3]['value']) ? (int) $values[3]['value'] : 0;
        $avg_session_duration = isset($values[4]['value']) ? (float) $values[4]['value'] : 0.0;
        $events = isset($values[5]['value']) ? (int) $values[5]['value'] : 0;

        return [
            'sessions' => $sessions,
            'active_users' => $active_users,
            'new_users' => $new_users,
            'returning_users' => max(0, $active_users - $new_users),
            'page_views' => $page_views,
            'avg_session_duration' => (int) round($avg_session_duration),
            'event_count' => $events,
        ];
    }

    public function get_top_pages(string $start_date, string $end_date, int $limit = 10) {
        $response = $this->run_report($start_date, $end_date, [
            ['name' => 'screenPageViews'],
            ['name' => 'sessions'],
            ['name' => 'activeUsers'],
        ], [
            ['name' => 'pagePath'],
        ], $limit);

        if (is_wp_error($response)) {
            return $response;
        }

        $items = [];
        $rows = $response['rows'] ?? [];
        foreach ($rows as $row) {
            $dims = $row['dimensionValues'] ?? [];
            $vals = $row['metricValues'] ?? [];

            $items[] = [
                'path' => (string) ($dims[0]['value'] ?? '/'),
                'views' => (int) ($vals[0]['value'] ?? 0),
                'sessions' => (int) ($vals[1]['value'] ?? 0),
                'users' => (int) ($vals[2]['value'] ?? 0),
            ];
        }

        return $items;
    }

    private function load_config(): void {
        $json_raw = trim((string) Config::get('ga4_service_account_json', ''));
        if ($json_raw === '') {
            $json_raw = trim((string) Config::get('google_service_account_json', ''));
        }

        $json = [];
        if ($json_raw !== '') {
            $decoded = json_decode($json_raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        $property = trim((string) Config::get('ga4_property_id', ''));
        if ($property === '') {
            $property = trim((string) Config::get('google_analytics_property_id', ''));
        }

        $client_email = trim((string) Config::get('ga4_service_account_email', ''));
        if ($client_email === '') {
            $client_email = trim((string) Config::get('google_client_email', ''));
        }
        if ($client_email === '' && !empty($json['client_email'])) {
            $client_email = trim((string) $json['client_email']);
        }

        $private_key = trim((string) Config::get('ga4_private_key', ''));
        if ($private_key === '') {
            $private_key = trim((string) Config::get('google_private_key', ''));
        }
        if ($private_key === '' && !empty($json['private_key'])) {
            $private_key = trim((string) $json['private_key']);
        }

        // Some environments store escaped line breaks.
        $private_key = str_replace('\\n', "\n", $private_key);

        $this->property_id = preg_replace('/[^0-9]/', '', $property) ?: '';
        $this->client_email = $client_email;
        $this->private_key = $private_key;
    }

    private function run_report(string $start_date, string $end_date, array $metrics, array $dimensions = [], int $limit = 1) {
        if (!$this->is_configured()) {
            return new \WP_Error('ga4_not_configured', 'Google Analytics nu este configurat.');
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $url = self::DATA_API_BASE . $this->property_id . ':runReport';
        $payload = [
            'dateRanges' => [[
                'startDate' => $start_date,
                'endDate' => $end_date,
            ]],
            'metrics' => $metrics,
            'dimensions' => $dimensions,
            'limit' => (string) max(1, $limit),
            'orderBys' => [[
                'metric' => ['metricName' => $metrics[0]['name']],
                'desc' => true,
            ]],
        ];

        $resp = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($resp)) {
            return new \WP_Error('ga4_request_failed', $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $message = is_array($data) && isset($data['error']['message']) ? (string) $data['error']['message'] : 'Google Analytics request failed.';
            return new \WP_Error('ga4_request_failed', $message);
        }

        return is_array($data) ? $data : [];
    }

    private function get_access_token() {
        $jwt = $this->build_jwt();
        if (is_wp_error($jwt)) {
            return $jwt;
        }

        $resp = wp_remote_post(self::OAUTH_TOKEN_URL, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        if (is_wp_error($resp)) {
            return new \WP_Error('ga4_auth_failed', $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300 || empty($data['access_token'])) {
            $message = is_array($data) && isset($data['error_description']) ? (string) $data['error_description'] : 'Google auth failed.';
            return new \WP_Error('ga4_auth_failed', $message);
        }

        return (string) $data['access_token'];
    }

    private function build_jwt() {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $this->client_email,
            'scope' => self::SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $segments = [
            $this->base64_url_encode(wp_json_encode($header) ?: '{}'),
            $this->base64_url_encode(wp_json_encode($payload) ?: '{}'),
        ];
        $signing_input = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signing_input, $signature, $this->private_key, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            return new \WP_Error('ga4_jwt_sign_failed', 'Nu am putut semna token-ul JWT pentru Google Analytics.');
        }

        $segments[] = $this->base64_url_encode($signature);
        return implode('.', $segments);
    }

    private function base64_url_encode(string $input): string {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}
