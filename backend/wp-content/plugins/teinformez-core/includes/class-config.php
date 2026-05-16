<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration class
 *
 * IMPORTANT: When cloning to a new country/domain:
 * 1. Change SITE_LANGUAGE constant
 * 2. Change SITE_COUNTRY constant
 * 3. Change SITE_TIMEZONE constant
 * 4. Update AVAILABLE_LANGUAGES array
 * 5. Translate language files in /languages/
 */
class Config {

    // === GDPR / PRIVACY ===
    const PRIVACY_POLICY_VERSION = '1.0';

    // === CLONE CONFIGURATION ===
    // Change these values when deploying to new country/domain
    const SITE_LANGUAGE = 'ro';                    // Primary site UI language
    const SITE_COUNTRY = 'Romania';                // Target country
    const SITE_TIMEZONE = 'Europe/Bucharest';      // Default timezone

    // Available languages for news content
    const AVAILABLE_LANGUAGES = [
        'ro' => 'Română',
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'it' => 'Italiano',
        'hu' => 'Magyar',
        'bg' => 'Български'
    ];

    // Allowed frontend origins (CORS) — production only; localhost added at runtime in non-production
    const ALLOWED_ORIGINS = [
        'https://teinformez.eu',           // Production
        'https://www.teinformez.eu',       // Production (www)
        'https://teinformez.vercel.app',   // Vercel production
    ];

    // === API CONFIGURATION ===
    const AI_PROVIDER = 'anthropic';          // 'anthropic' or 'openai'
    const ANTHROPIC_MODEL = 'claude-sonnet-4-20250514';
    const OPENAI_MODEL = 'gpt-4o';
    const OPENAI_IMAGE_MODEL = 'dall-e-3';
    const TRANSLATION_PROVIDER = 'anthropic'; // or 'openai', 'deepl', 'google'

    // === NEWS CONFIGURATION ===
    const NEWS_FETCH_INTERVAL = 1800;       // seconds (30 minutes)
    const ADMIN_REVIEW_PERIOD = 7200;       // seconds (2 hours)
    const MAX_SUMMARY_LENGTH = 150;         // words
    const MAX_SOCIAL_SNIPPET_LENGTH = 280;  // characters

    // === EMAIL CONFIGURATION ===
    const EMAIL_PROVIDER = 'brevo';       // 'brevo', 'wp_mail', or 'sendgrid'
    const EMAIL_FROM_NAME = 'TeInformez';
    const EMAIL_FROM_ADDRESS = 'noreply@teinformez.eu';

    // === SOCIAL MEDIA CONFIGURATION ===
    const FACEBOOK_GRAPH_API = 'https://graph.facebook.com/v18.0';
    const TWITTER_API = 'https://api.twitter.com/2';
    const SOCIAL_MAX_RETRY = 3;

    // === YOUTUBE CONFIGURATION ===
    const YOUTUBE_API = 'https://www.googleapis.com/youtube/v3';
    const YOUTUBE_MAX_PER_EMAIL = 2;

    // === FRONTEND URL ===
    const FRONTEND_URL = 'https://teinformez.eu';

    // === CATEGORIES (can be overridden in admin) ===
    const DEFAULT_CATEGORIES = [
        'tech' => [
            'label' => 'Tehnologie',
            'icon' => '💻',
            'subcategories' => ['smartphones', 'laptops', 'ai', 'software', 'gadgets']
        ],
        'auto' => [
            'label' => 'Auto',
            'icon' => '🚗',
            'subcategories' => ['electric-cars', 'classic-cars', 'motorsport', 'reviews']
        ],
        'finance' => [
            'label' => 'Finanțe',
            'icon' => '💰',
            'subcategories' => ['crypto', 'stocks', 'banking', 'real-estate']
        ],
        'entertainment' => [
            'label' => 'Divertisment',
            'icon' => '🎬',
            'subcategories' => ['movies', 'music', 'gaming', 'celebrities']
        ],
        'sports' => [
            'label' => 'Sport',
            'icon' => '⚽',
            'subcategories' => ['football', 'tennis', 'f1', 'basketball']
        ],
        'science' => [
            'label' => 'Știință',
            'icon' => '🔬',
            'subcategories' => ['space', 'medicine', 'environment', 'research']
        ],
        'politics' => [
            'label' => 'Politică',
            'icon' => '🏛️',
            'subcategories' => ['romania', 'eu', 'usa', 'international']
        ],
        'business' => [
            'label' => 'Business',
            'icon' => '📊',
            'subcategories' => ['startups', 'corporate', 'entrepreneurship', 'economy']
        ],
        'actualitate' => [
            'label' => 'Actualitate',
            'icon' => '📰',
            'subcategories' => ['breaking', 'social', 'educatie', 'cultura', 'romania']
        ],
        'international' => [
            'label' => 'Internațional',
            'icon' => '🌍',
            'subcategories' => ['europa', 'sua', 'orientul-mijlociu', 'asia', 'africa']
        ],
        'justitie' => [
            'label' => 'Justiție',
            'icon' => '⚖️',
            'subcategories' => ['instante', 'dna', 'legislatie', 'cazuri-penale']
        ],
        'sanatate' => [
            'label' => 'Sănătate',
            'icon' => '🏥',
            'subcategories' => ['medicina', 'nutritie', 'fitness', 'mental-health', 'sistem-medical']
        ],
        'lifestyle' => [
            'label' => 'Lifestyle',
            'icon' => '✨',
            'subcategories' => ['travel', 'food', 'fashion', 'home', 'parenting']
        ],
        'opinii' => [
            'label' => 'Opinii',
            'icon' => '💬',
            'subcategories' => ['editoriale', 'analize', 'comentarii', 'interviuri']
        ],
        // Legacy slugs used by the AI classifier
        'news' => [
            'label' => 'Actualitate',
            'icon' => '📰',
            'subcategories' => []
        ],
        'world' => [
            'label' => 'Internațional',
            'icon' => '🌍',
            'subcategories' => []
        ],
        'health' => [
            'label' => 'Sănătate',
            'icon' => '🏥',
            'subcategories' => []
        ],
        'history' => [
            'label' => 'Istorie',
            'icon' => '📜',
            'subcategories' => []
        ],
        'local' => [
            'label' => 'Local',
            'icon' => '📍',
            'subcategories' => []
        ],
        'culture' => [
            'label' => 'Cultură',
            'icon' => '🎭',
            'subcategories' => []
        ],
        'education' => [
            'label' => 'Educație',
            'icon' => '🎓',
            'subcategories' => []
        ],
        'media' => [
            'label' => 'Media',
            'icon' => '📺',
            'subcategories' => []
        ],
        'military' => [
            'label' => 'Militar',
            'icon' => '🎖️',
            'subcategories' => []
        ],
    ];

    /**
     * Get option with fallback
     */
    public static function get($key, $default = null) {
        return get_option('teinformez_' . $key, $default);
    }

    /**
     * Set option
     */
    public static function set($key, $value) {
        return update_option('teinformez_' . $key, $value);
    }

    /**
     * Get API key
     */
    public static function get_api_key($service) {
        $key = self::get($service . '_api_key');
        return !empty($key) ? $key : false;
    }

    /**
     * Get allowed origins for CORS
     */
    public static function get_allowed_origins() {
        $origins = self::ALLOWED_ORIGINS;

        // Add localhost origins only in non-production environments
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'production') {
            $origins[] = 'http://localhost:3000';
            $origins[] = 'http://localhost:3002';
        }

        // Allow custom domains from settings
        $custom_origins = self::get('custom_origins', []);
        if (!empty($custom_origins)) {
            $origins = array_merge($origins, $custom_origins);
        }

        return $origins;
    }

    /**
     * Check if origin matches allowed patterns (supports wildcards)
     */
    public static function is_origin_allowed($origin) {
        if (empty($origin)) {
            return false;
        }

        $allowed_origins = self::get_allowed_origins();

        foreach ($allowed_origins as $allowed) {
            if ($origin === $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get client IP address (M-06).
     *
     * Only consults X-Forwarded-For when REMOTE_ADDR is a known local proxy
     * (loopback or RFC-1918 range), preventing IP spoofing by public clients.
     * When behind a proxy, takes the rightmost valid IP — the entry appended
     * by our nginx, not any attacker-injected leftmost values.
     *
     * @return string Validated IP address, or '' if indeterminate.
     */
    public static function get_client_ip(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        if (self::is_trusted_proxy($remote) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Iterate from rightmost to leftmost; first valid IP wins.
            $candidates = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        $ip = filter_var($remote, FILTER_VALIDATE_IP);
        return $ip ?: '';
    }

    /**
     * Returns true when $ip is a loopback or RFC-1918 private address,
     * meaning the direct connection comes from a local reverse proxy we trust.
     */
    private static function is_trusted_proxy(string $ip): bool {
        if (empty($ip)) {
            return false;
        }
        // ::1 is IPv6 loopback — FILTER_FLAG_NO_RES_RANGE misses it on some PHP builds
        if ($ip === '::1') {
            return true;
        }
        // FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE rejects private/reserved.
        // If the flag-check returns false, $ip IS private/reserved = trusted proxy.
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Validate an AI Router URL (M-12).
     * - Must be a well-formed HTTP/HTTPS URL.
     * - http:// is only allowed for loopback / localhost (prevents SSRF to
     *   internal services or cloud-metadata endpoints on external hosts).
     * - https:// is allowed for any host.
     *
     * @param string $url URL to validate.
     * @return bool True when safe to use.
     */
    public static function validate_ai_router_url(string $url): bool {
        if (empty($url)) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        if ($scheme === 'http') {
            // parse_url returns '[::1]' with brackets for IPv6 literals — strip them.
            $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
            return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
        }
        return true; // https is acceptable for any external host
    }

    /**
     * Get translatable UI strings
     */
    public static function get_strings() {
        return [
            'site_name' => __('TeInformez', 'teinformez'),
            'tagline' => __('Știri personalizate, livrate când vrei tu', 'teinformez'),
            'register_cta' => __('Înregistrează-te gratuit', 'teinformez'),
            'login_cta' => __('Autentifică-te', 'teinformez'),
            'logout' => __('Deconectare', 'teinformez'),
            'dashboard' => __('Panou', 'teinformez'),
            'preferences' => __('Preferințe', 'teinformez'),
            'subscriptions' => __('Abonamente', 'teinformez'),
            'categories' => __('Categorii', 'teinformez'),
            'frequency' => __('Frecvență', 'teinformez'),
            'delivery_channels' => __('Canale de livrare', 'teinformez'),
            'privacy_policy' => __('Politica de confidențialitate', 'teinformez'),
            'terms' => __('Termeni și condiții', 'teinformez'),
            'gdpr_consent' => __('Accept politica de confidențialitate și sunt de acord ca datele mele să fie procesate.', 'teinformez'),
            'save' => __('Salvează', 'teinformez'),
            'cancel' => __('Anulează', 'teinformez'),
            'delete' => __('Șterge', 'teinformez'),
            'edit' => __('Editează', 'teinformez'),
        ];
    }

    /**
     * Get available delivery channels
     */
    public static function get_delivery_channels() {
        return [
            'email' => [
                'label' => __('Email', 'teinformez'),
                'icon' => '📧',
                'enabled' => true
            ],
            'facebook' => [
                'label' => __('Facebook', 'teinformez'),
                'icon' => '📘',
                'enabled' => true
            ],
            'twitter' => [
                'label' => __('Twitter/X', 'teinformez'),
                'icon' => '🐦',
                'enabled' => true
            ],
            'instagram' => [
                'label' => __('Instagram', 'teinformez'),
                'icon' => '📸',
                'enabled' => false  // Meta Business API - later
            ],
        ];
    }

    /**
     * Get frequency options
     */
    public static function get_frequency_options() {
        return [
            'realtime' => __('În timp real', 'teinformez'),
            'hourly' => __('La fiecare oră', 'teinformez'),
            'daily' => __('Zilnic', 'teinformez'),
            'weekly' => __('Săptămânal', 'teinformez'),
            'monthly' => __('Lunar', 'teinformez'),
        ];
    }
}
