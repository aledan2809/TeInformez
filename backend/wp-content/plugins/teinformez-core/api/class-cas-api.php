<?php
namespace TeInformez\API;

use TeInformez\Config;
use TeInformez\CAS_Telemetry;

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

        // Map TeInformez placements → MA CAS slots that carry inventory.
        // Both map to WEBSITE_INFEED — that's where the live "Banner CAS — X pe TeInformez"
        // cross-promo campaigns are assigned (SIDEBAR has only a test ad). MA alias-resolves
        // the `slot` param (not `placement`), so we send `slot`.
        $slot_map = ['banner' => 'infeed', 'infeed' => 'infeed'];
        $query = [
            'slot'   => $slot_map[$placement] ?? $placement,
            'source' => 'teinformez',
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
            // Upstream unreachable counts as an empty impression for fill-rate accounting.
            CAS_Telemetry::record($placement, false);
            return new \WP_REST_Response(['error' => 'upstream_unreachable'], 502);
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $was_filled = ($code === 200 && $body !== '');

        // Fire-and-forget telemetry (AN-03 revenue dashboard).
        CAS_Telemetry::record($placement, $was_filled);

        // Return RAW HTML, not WP_REST_Response: WordPress JSON-encodes any WP_REST_Response
        // body regardless of the Content-Type header, which double-escaped the MA creative
        // (the frontend's resp.text() then rendered the escaped JSON string as text). Echo +
        // exit bypasses the REST JSON serializer so the client receives clean HTML for
        // dangerouslySetInnerHTML (sanitized client-side via DOMPurify).
        status_header($was_filled ? 200 : 204);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: ' . self::CACHE_CONTROL_HEADER);
        header('X-Content-Type-Options: nosniff');
        if ($was_filled) {
            echo $body; // trusted upstream HTML; sanitized client-side via DOMPurify
        }
        exit;
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
