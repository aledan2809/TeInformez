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
        // M-04: 60 events per IP per minute — blocks flood abuse, allows normal browsing
        $rl = $this->check_rate_limit('analytics_track', 60, 1);
        if ($rl) return $rl;

        // AN-02: server-side bot UA filter — drop crawler/scraper events silently
        // (return 200 OK so client doesn't retry, but skip the DB insert to keep
        // dashboard counts honest). Bots that don't run JS never reach this
        // endpoint anyway; this catches headless browsers + scripted clients.
        if (self::is_bot_user_agent((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
            return $this->success(['tracked' => false, 'reason' => 'bot_ua_filtered']);
        }

        // AN-02: server-side referer fallback — frontend SHOULD send `referer`
        // explicitly (document.referrer is the user's external entry point, not
        // the API caller's referer header which would always be teinformez.eu).
        // But if the client sends nothing, fall back to HTTP_REFERER from the
        // POST request as a last resort.
        $referer = (string) ($request->get_param('referer') ?? '');
        if ($referer === '') {
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }

        $ok = Visitor_Analytics::track_event([
            'visitor_id' => $request->get_param('visitor_id'),
            'session_id' => $request->get_param('session_id'),
            'event_type' => $request->get_param('event_type'),
            'page_type' => $request->get_param('page_type'),
            'page_id' => $request->get_param('page_id'),
            'page_path' => $request->get_param('page_path'),
            'duration_seconds' => $request->get_param('duration_seconds'),
            'metadata' => $request->get_param('metadata'),
            'referer' => $referer,
            'utm_source'   => $request->get_param('utm_source'),
            'utm_medium'   => $request->get_param('utm_medium'),
            'utm_campaign' => $request->get_param('utm_campaign'),
            'utm_term'     => $request->get_param('utm_term'),
            'utm_content'  => $request->get_param('utm_content'),
        ]);

        if (!$ok) {
            return $this->error('Tracking payload invalid.', 'invalid_tracking_payload', 400);
        }

        return $this->success(['tracked' => true]);
    }

    /**
     * Bot UA detector — conservative regex covering search-engine crawlers,
     * SEO bots, link unfurlers, headless browsers, and scripted clients
     * confirmed in TeInformez nginx logs (last 7d audit, 2026-05-15).
     * Patterns are case-insensitive on substring; false-positive rate is
     * low because the accepted "real browser" UAs in nginx logs all contain
     * AppleWebKit/Mozilla without bot markers.
     */
    private static function is_bot_user_agent(string $ua): bool {
        if ($ua === '') return true;  // empty UA is suspicious — drop
        return (bool) preg_match(
            '#(bot|crawl|spider|slurp|fetch|HeadlessChrome|node\b|curl/|wget/|python-requests|req/v3|facebookexternalhit|twitterbot|linkedinbot|whatsapp|snapchat|telegrambot|discordbot|metainspector|vercelbot|googleother|ahrefsbot|semrushbot|mj12bot|petalbot|bytespider|gptbot|claudebot|perplexitybot)#i',
            $ua
        );
    }
}
