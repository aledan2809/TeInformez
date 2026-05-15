<?php
namespace TeInformez\API;

use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CAS (Carousel of Ads) proxy endpoint.
 *
 * Browser-side InFeed/banner slots fetch through this endpoint instead of calling
 * MarketingAutomation directly, so the MA API key never ships in the client bundle.
 *
 * Newsletter sends call MA directly via build_digest_html() (server-to-server, no proxy needed).
 *
 * Route: GET /wp-json/teinformez/v1/cas/render?placement=<infeed|banner>&visitor=<token>
 *
 * Env vars (defined() in wp-config.php OR teinformez_<key> wp_options):
 *   TEINFORMEZ_MA_API_URL   — base URL (e.g. https://ma.techbiz.ae)
 *   TEINFORMEZ_MA_API_KEY   — X-API-Key value
 *   TEINFORMEZ_CAS_SALT     — server-side salt for visitor hashing
 */
class CAS_API extends REST_API {

    const TIMEOUT_SECONDS = 4;
    const ALLOWED_PLACEMENTS = ['infeed', 'banner'];
    const CACHE_CONTROL_HEADER = 'private, max-age=300';

    public function register_routes() {
        register_rest_route($this->namespace, '/cas/render', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_render'],
            'permission_callback' => '__return_true',
            'args' => [
                'placement' => ['type' => 'string', 'required' => true],
                'visitor'   => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function handle_render($request) {
        $placement = sanitize_key((string) $request->get_param('placement'));
        if (!in_array($placement, self::ALLOWED_PLACEMENTS, true)) {
            return new \WP_REST_Response(['error' => 'invalid_placement'], 400);
        }

        $url = self::get_setting('TEINFORMEZ_MA_API_URL', 'ma_cas_url');
        $key = self::get_setting('TEINFORMEZ_MA_API_KEY', 'ma_cas_key');
        if (empty($url) || empty($key)) {
            return new \WP_REST_Response(['error' => 'cas_not_configured'], 503);
        }

        $visitor_token = (string) $request->get_param('visitor');
        $visitor_hash = $visitor_token !== ''
            ? hash('sha256', $visitor_token . self::get_setting('TEINFORMEZ_CAS_SALT', 'cas_salt', ''))
            : '';

        $query = [
            'placement' => $placement,
            'source'    => 'teinformez',
        ];
        if ($visitor_hash !== '') {
            $query['visitor'] = $visitor_hash;
        }

        $endpoint = rtrim($url, '/') . '/api/cas/render?' . http_build_query($query);
        $resp = wp_remote_get($endpoint, [
            'timeout' => self::TIMEOUT_SECONDS,
            'headers' => ['X-API-Key' => $key],
        ]);

        if (is_wp_error($resp)) {
            return new \WP_REST_Response(['error' => 'upstream_unreachable'], 502);
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);

        $response = new \WP_REST_Response(null, $code === 200 ? 200 : 204);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->header('Cache-Control', self::CACHE_CONTROL_HEADER);
        if ($code === 200 && $body !== '') {
            $response->set_data($body);
        } else {
            $response->set_data('');
        }
        return $response;
    }

    /**
     * Read setting: prefer wp-config.php constant, fall back to wp_options.
     */
    private static function get_setting(string $constant, string $option_key, $default = '') {
        if (defined($constant)) {
            $value = constant($constant);
            if (!empty($value)) {
                return $value;
            }
        }
        return Config::get($option_key, $default);
    }
}
