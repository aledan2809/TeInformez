<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Marketing Automation client — fire-and-forget event dispatcher.
 *
 * Sends events to ma.techbiz.ae via POST /api/internal/marketing/events.
 * Reads endpoint URL and internal key from wp_options (set via WP-CLI on VPS).
 * All failures are logged silently; they never interrupt the user flow.
 */
class MA_Client {

    const DEFAULT_ENDPOINT = 'https://ma.techbiz.ae/api/internal/marketing/events';
    const TIMEOUT_SECONDS  = 5;

    /**
     * Send a marketing event to MA. Non-blocking (blocking=false).
     *
     * @param string $event_name  snake_case event name (e.g. 'user_registered')
     * @param string $entity_id   Unique stable ID for idempotency (e.g. 'user_42', 'nl_<hash>')
     * @param array  $data        Payload forwarded to MA template (recipientEmail, recipientName, etc.)
     */
    public static function send_event( string $event_name, string $entity_id, array $data ): void {
        $key = Config::get('ma_internal_key');
        if ( empty($key) ) {
            error_log('TeInformez MA_Client: ma_internal_key not set — skipping event ' . $event_name);
            return;
        }

        $endpoint = Config::get('ma_endpoint_url') ?: self::DEFAULT_ENDPOINT;

        $payload = wp_json_encode([
            'eventName' => $event_name,
            'auditId'   => $entity_id,
            'data'      => array_merge([
                'brandName'          => 'TeInformez',
                'contactDestination' => Config::FRONTEND_URL,
            ], $data),
        ]);

        $result = wp_remote_post($endpoint, [
            'method'   => 'POST',
            'timeout'  => self::TIMEOUT_SECONDS,
            'blocking' => false,   // fire-and-forget — does not wait for MA response
            'headers'  => [
                'Content-Type' => 'application/json',
                'X-Internal-Key' => $key,
            ],
            'body' => $payload,
        ]);

        if ( is_wp_error($result) ) {
            error_log('TeInformez MA_Client: wp_remote_post error for ' . $event_name . ': ' . $result->get_error_message());
        }
    }
}
