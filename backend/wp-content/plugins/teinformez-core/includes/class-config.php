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

    // Allowed frontend origins (CORS)
    const ALLOWED_ORIGINS = [
        'http://localhost:3000',           // Local development
        'https://teinformez.eu',           // Production
        'https://teinformez.vercel.app',   // Vercel production
        'https://*.vercel.app',            // Vercel preview deployments
    ];

    // === API CONFIGURATION ===
    const OPENAI_MODEL = 'gpt-4-turbo-preview';
    const OPENAI_IMAGE_MODEL = 'dall-e-3';
    const TRANSLATION_PROVIDER = 'openai';  // or 'deepl', 'google'

    // === NEWS CONFIGURATION ===
    const NEWS_FETCH_INTERVAL = 1800;       // seconds (30 minutes)
    const ADMIN_REVIEW_PERIOD = 7200;       // seconds (2 hours)
    const MAX_SUMMARY_LENGTH = 150;         // words
    const MAX_SOCIAL_SNIPPET_LENGTH = 280;  // characters

    // === EMAIL CONFIGURATION ===
    const EMAIL_PROVIDER = 'brevo';       // 'brevo', 'wp_mail', or 'sendgrid'
    const EMAIL_FROM_NAME = 'TeInformez';
    const EMAIL_FROM_ADDRESS = 'noreply@teinformez.eu';

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
        ]
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
            // Exact match
            if ($origin === $allowed) {
                return true;
            }

            // Wildcard match (e.g., https://*.vercel.app)
            if (strpos($allowed, '*') !== false) {
                // Convert wildcard pattern to regex
                $pattern = str_replace(
                    ['*', '.'],
                    ['.*', '\.'],
                    $allowed
                );
                $pattern = '/^' . $pattern . '$/';

                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
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
