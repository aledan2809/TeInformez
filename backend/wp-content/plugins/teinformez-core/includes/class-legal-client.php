<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

class Legal_Client {

    const APP_SLUG                 = 'teinformez';
    const CONSENT_TEXT             = 'Am citit și sunt de acord cu Politica de confidențialitate. Îmi pot retrage consimțământul oricând prin dezabonare.';
    const PRIVACY_VERSION_FALLBACK = 'cmosfa7c40003t6czcldfpjfn';

    public static function api_url(): string {
        if (defined('TI_LEGAL_API_URL')) {
            return rtrim(TI_LEGAL_API_URL, '/');
        }
        $env = getenv('TI_LEGAL_API_URL');
        return rtrim($env ?: 'https://legal.knowbest.ro', '/');
    }

    /** Maps WP DSR type codes to Legal Hub enum values (export|delete|rectify). */
    private static function map_dsr_type(string $type): string {
        $map = [
            'ACCESS'      => 'export',
            'PORTABILITY' => 'export',
            'DELETE'      => 'delete',
            'RECTIFY'     => 'rectify',
            'OBJECTION'   => 'export',  // closest match — enters DPO queue
            'OTHER'       => 'export',
        ];
        return $map[$type] ?? 'export';
    }

    private static function api_key(): string {
        if (defined('TI_LEGAL_API_KEY')) {
            return TI_LEGAL_API_KEY;
        }
        return getenv('TI_LEGAL_API_KEY') ?: '';
    }

    public static function get_privacy_version_id(): string {
        return get_option('ti_legal_privacy_version_id', self::PRIVACY_VERSION_FALLBACK);
    }

    /**
     * Fire-and-forget consent record for anonymous newsletter subscribers.
     * Does NOT block the sign-up response (blocking=false).
     */
    public static function record_newsletter_consent(string $ip, string $user_agent): void {
        // Validate and sanitize IP (accept only valid IPv4/IPv6 to prevent header injection).
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        // Strip control characters and sanitize user-agent to prevent header injection.
        $user_agent = sanitize_text_field(preg_replace('/[\r\n\t\0]/', '', $user_agent));
        $user_agent = substr($user_agent, 0, 512);

        $payload = wp_json_encode([
            'appSlug'           => self::APP_SLUG,
            'documentVersionId' => self::get_privacy_version_id(),
            'consentText'       => self::CONSENT_TEXT,
            'method'            => 'IN_APP',
        ]);

        if ($payload === false) {
            error_log('[Legal_Client] wp_json_encode failed for newsletter consent payload');
            return;
        }

        $key = self::api_key();
        $headers = [
            'Content-Type'    => 'application/json',
            'x-forwarded-for' => $ip,
            'user-agent'      => $user_agent,
        ];
        if (!empty($key)) {
            $headers['x-legal-api-key'] = $key;
        }

        $response = wp_remote_post(self::api_url() . '/api/v1/consents/record', [
            'method'   => 'POST',
            'blocking' => false,
            'timeout'  => 5,
            'headers'  => $headers,
            'body'     => $payload,
        ]);

        if (is_wp_error($response)) {
            error_log('[Legal_Client] newsletter consent fire-and-forget error: ' . $response->get_error_message());
        }
    }

    /**
     * Submit a Data Subject Request to Legal Hub.
     * Blocking call — returns requestId on success, null on failure.
     */
    public static function submit_dsr(string $name, string $email, string $type, string $description): ?string {
        $key = self::api_key();
        if (empty($key)) {
            error_log('[Legal_Client] TI_LEGAL_API_KEY not configured — DSR skipped');
            return null;
        }

        $response = wp_remote_post(self::api_url() . '/api/v1/dsr/submit', [
            'method'   => 'POST',
            'blocking' => true,
            'timeout'  => 8,
            'headers'  => [
                'Content-Type'    => 'application/json',
                'x-legal-api-key' => $key,
            ],
            'body'     => wp_json_encode([
                'name'        => $name,
                'email'       => $email,
                'type'        => self::map_dsr_type($type),
                'description' => $description,
                'appSlug'     => self::APP_SLUG,
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('[Legal_Client] DSR submit error: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[Legal_Client] DSR submit HTTP error: ' . $code . ' — ' . wp_remote_retrieve_response_message($response));
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return null;
        }
        $request_id = $body['requestId'] ?? null;
        if ($request_id !== null && !is_string($request_id)) {
            return null;
        }
        return $request_id;
    }

    /**
     * Refresh the cached privacy version ID from Legal Hub.
     * Called daily via WP cron. No-op on network error.
     */
    public static function refresh_privacy_version(): void {
        $response = wp_remote_get(
            self::api_url() . '/api/v1/public/legal/' . self::APP_SLUG . '/privacy',
            ['timeout' => 5]
        );

        if (is_wp_error($response)) {
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return;
        }

        try {
            $body = json_decode(wp_remote_retrieve_body($response), true);
        } catch (\Throwable $e) {
            error_log('[Legal_Client] refresh_privacy_version JSON decode error: ' . $e->getMessage());
            return;
        }

        if (!is_array($body)) {
            return;
        }

        $version_id = $body['version']['id'] ?? '';

        if (!empty($version_id)) {
            update_option('ti_legal_privacy_version_id', sanitize_text_field((string) $version_id));
        }
    }
}
