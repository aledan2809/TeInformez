<?php
namespace TeInformez\API;

if (!defined('ABSPATH')) {
    exit;
}

class Legal_API {

    private const VALID_DSR_TYPES = ['ACCESS', 'DELETE', 'RECTIFY', 'PORTABILITY', 'OBJECTION', 'OTHER'];

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        if (!did_action('rest_api_init') && !doing_action('rest_api_init')) {
            return;
        }

        register_rest_route('teinformez/v1', '/legal/dsr', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit_dsr'],
            'permission_callback' => '__return_true',
            'args'                => [
                'name'  => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($v) { return is_string($v) && strlen(trim($v)) >= 2; },
                ],
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email',
                ],
                'type'  => [
                    'required'          => true,
                    'sanitize_callback' => function ($v) { return strtoupper(sanitize_text_field($v)); },
                    'validate_callback' => function ($v) { return in_array(strtoupper($v), self::VALID_DSR_TYPES, true); },
                ],
                'description' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => function ($v) {
                        if ($v === null || $v === '') {
                            return true;
                        }
                        return is_string($v) && mb_strlen($v) <= 500;
                    },
                ],
            ],
        ]);

        register_rest_route('teinformez/v1', '/legal/privacy-version', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_privacy_version'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function submit_dsr(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $name        = $request->get_param('name');
        $email       = $request->get_param('email');
        $type        = $request->get_param('type');
        $description = $request->get_param('description') ?? '';

        $request_id = \TeInformez\Legal_Client::submit_dsr($name, $email, $type, $description);

        if ($request_id === null) {
            return new \WP_Error(
                'dsr_unavailable',
                'Cererea nu a putut fi procesată. Încearcă mai târziu.',
                ['status' => 503]
            );
        }

        return rest_ensure_response([
            'requestId'    => $request_id,
            'originalType' => $type,
            'message'      => 'Cererea ta a fost înregistrată. Vei primi un răspuns în termen de 30 de zile.',
        ]);
    }

    public function get_privacy_version(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response([
            'versionId' => \TeInformez\Legal_Client::get_privacy_version_id(),
        ]);
    }
}
