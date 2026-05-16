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
            ['name' => 'screenPageViewsPerSession'],
            ['name' => 'bounceRate'],
            ['name' => 'engagementRate'],
            ['name' => 'eventsPerSession'],
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
        $pages_per_session = isset($values[6]['value']) ? (float) $values[6]['value'] : 0.0;
        $bounce_rate = isset($values[7]['value']) ? (float) $values[7]['value'] : 0.0;
        $engagement_rate = isset($values[8]['value']) ? (float) $values[8]['value'] : 0.0;
        $events_per_session = isset($values[9]['value']) ? (float) $values[9]['value'] : 0.0;

        return [
            'sessions' => $sessions,
            'active_users' => $active_users,
            'new_users' => $new_users,
            'returning_users' => max(0, $active_users - $new_users),
            'page_views' => $page_views,
            'avg_session_duration' => (int) round($avg_session_duration),
            'event_count' => $events,
            'pages_per_session' => round($pages_per_session, 2),
            'bounce_rate' => round($bounce_rate * 100, 1),
            'engagement_rate' => round($engagement_rate * 100, 1),
            'engaged_sessions' => $sessions > 0 ? (int) round($engagement_rate * $sessions) : 0,
            'events_per_session' => round($events_per_session, 2),
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

        $private_key = self::resolve_private_key($json);

        $this->property_id = preg_replace('/[^0-9]/', '', $property) ?: '';
        $this->client_email = $client_email;
        $this->private_key = $private_key;
    }

    /**
     * I-05: Resolve GA4 service-account private key in this strict precedence:
     *   1. TEINFORMEZ_GA4_PRIVATE_KEY constant   (inline; discouraged but supported for containers)
     *   2. TEINFORMEZ_GA4_PRIVATE_KEY_PATH       (filesystem PEM; preferred)
     *   3. wp_options.teinformez_ga4_private_key  (legacy; emits one-shot deprecation notice)
     *   4. wp_options legacy aliases + JSON fallback (last resort, same deprecated tier)
     *
     * Keeping DB paths means an un-migrated production install keeps working
     * until ops moves the key to the filesystem, while a fully migrated install
     * exposes the key only at the OS layer (chmod 640 root:www-data).
     */
    public static function resolve_private_key(array $json = []): string {
        // 1. Inline constant (rare; e.g. container env injects via auto_prepend wp-config snippet).
        if (defined('TEINFORMEZ_GA4_PRIVATE_KEY')) {
            $key = trim((string) constant('TEINFORMEZ_GA4_PRIVATE_KEY'));
            if ($key !== '') {
                return self::normalize_pem_newlines($key);
            }
        }

        // 2. Filesystem path constant — preferred production path.
        if (defined('TEINFORMEZ_GA4_PRIVATE_KEY_PATH')) {
            $path = (string) constant('TEINFORMEZ_GA4_PRIVATE_KEY_PATH');
            if ($path !== '' && is_readable($path)) {
                $contents = @file_get_contents($path);
                if (is_string($contents) && $contents !== '') {
                    return self::normalize_pem_newlines(trim($contents));
                }
            }
        }

        // 3. Legacy DB row (deprecated). Emit a one-shot _doing_it_wrong notice
        // so admins see the migration is still pending in WP_DEBUG environments.
        $db_key = trim((string) Config::get('ga4_private_key', ''));
        if ($db_key === '') {
            $db_key = trim((string) Config::get('google_private_key', ''));
        }
        if ($db_key === '' && !empty($json['private_key'])) {
            $db_key = trim((string) $json['private_key']);
        }

        if ($db_key !== '') {
            self::flag_db_key_deprecated();
            return self::normalize_pem_newlines($db_key);
        }

        return '';
    }

    /**
     * Some environments store PEM keys with escaped line breaks ("\n" as two chars
     * instead of a real newline). Convert them back so openssl_sign() accepts them.
     */
    private static function normalize_pem_newlines(string $key): string {
        return str_replace('\\n', "\n", $key);
    }

    /**
     * Logs one-shot deprecation notice per request so we don't spam the log.
     * Wraps _doing_it_wrong() which is a no-op outside WP_DEBUG.
     */
    private static function flag_db_key_deprecated(): void {
        static $logged = false;
        if ($logged) {
            return;
        }
        $logged = true;
        if (function_exists('_doing_it_wrong')) {
            _doing_it_wrong(
                'TeInformez\\Google_Analytics_Service::load_config',
                esc_html__(
                    'GA4 private key is still loaded from the database. Run "wp teinformez migrate-ga4-key" (or use Settings → Migrate to filesystem) and define TEINFORMEZ_GA4_PRIVATE_KEY_PATH in wp-config.php.',
                    'teinformez'
                ),
                '1.1.0'
            );
        }
    }

    /**
     * Used by admin status block + migration runner to report which source the key
     * is currently coming from. Returns one of: 'inline-constant' | 'filesystem' |
     * 'database' | 'database-legacy-alias' | 'database-json' | 'none'.
     */
    public static function get_private_key_source(): string {
        if (defined('TEINFORMEZ_GA4_PRIVATE_KEY')
            && trim((string) constant('TEINFORMEZ_GA4_PRIVATE_KEY')) !== '') {
            return 'inline-constant';
        }
        if (defined('TEINFORMEZ_GA4_PRIVATE_KEY_PATH')) {
            $path = (string) constant('TEINFORMEZ_GA4_PRIVATE_KEY_PATH');
            if ($path !== '' && is_readable($path) && trim((string) @file_get_contents($path)) !== '') {
                return 'filesystem';
            }
        }
        if (trim((string) Config::get('ga4_private_key', '')) !== '') {
            return 'database';
        }
        if (trim((string) Config::get('google_private_key', '')) !== '') {
            return 'database-legacy-alias';
        }
        $json_raw = trim((string) Config::get('ga4_service_account_json', ''));
        if ($json_raw === '') {
            $json_raw = trim((string) Config::get('google_service_account_json', ''));
        }
        if ($json_raw !== '') {
            $decoded = json_decode($json_raw, true);
            if (is_array($decoded) && !empty($decoded['private_key'])) {
                return 'database-json';
            }
        }
        return 'none';
    }

    private function run_report(string $start_date, string $end_date, array $metrics, array $dimensions = [], int $limit = 1) {
        if (!$this->is_configured()) {
            return new \WP_Error('ga4_not_configured', 'Google Analytics is not configured.');
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
            return new \WP_Error('ga4_jwt_sign_failed', 'Failed to sign JWT token for Google Analytics.');
        }

        $segments[] = $this->base64_url_encode($signature);
        return implode('.', $segments);
    }

    private function base64_url_encode(string $input): string {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}
