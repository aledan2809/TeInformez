<?php
namespace TeInformez\API;

use TeInformez\Visitor_Analytics;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Visitor analytics API
 */
class Analytics_API extends REST_API {
    public function register_routes() {
        register_rest_route($this->namespace, '/analytics/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_event'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function track_event($request) {
        $ok = Visitor_Analytics::track_event([
            'visitor_id' => $request->get_param('visitor_id'),
            'session_id' => $request->get_param('session_id'),
            'event_type' => $request->get_param('event_type'),
            'page_type' => $request->get_param('page_type'),
            'page_id' => $request->get_param('page_id'),
            'page_path' => $request->get_param('page_path'),
            'duration_seconds' => $request->get_param('duration_seconds'),
            'metadata' => $request->get_param('metadata'),
        ]);

        if (!$ok) {
            return $this->error('Tracking payload invalid.', 'invalid_tracking_payload', 400);
        }

        return $this->success(['tracked' => true]);
    }
}
