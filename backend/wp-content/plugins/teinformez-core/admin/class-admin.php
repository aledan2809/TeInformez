<?php
namespace TeInformez\Admin;

use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin panel functionality
 */
class Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __('TeInformez', 'teinformez'),
            __('TeInformez', 'teinformez'),
            'manage_options',
            'teinformez',
            [$this, 'render_dashboard'],
            'dashicons-megaphone',
            30
        );

        add_submenu_page(
            'teinformez',
            __('Settings', 'teinformez'),
            __('Settings', 'teinformez'),
            'manage_options',
            'teinformez-settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            'teinformez',
            __('News Queue', 'teinformez'),
            __('News Queue', 'teinformez'),
            'manage_options',
            'teinformez-news-queue',
            [$this, 'render_news_queue']
        );

        add_submenu_page(
            'teinformez',
            __('Juridic Q&A', 'teinformez'),
            __('Juridic Q&A', 'teinformez'),
            'manage_options',
            'teinformez-juridic',
            [$this, 'render_juridic_queue']
        );

        add_submenu_page(
            'teinformez',
            __('Ordine Categorii', 'teinformez'),
            __('Ordine Categorii', 'teinformez'),
            'manage_options',
            'teinformez-category-order',
            [$this, 'render_category_order']
        );

        add_submenu_page(
            'teinformez',
            __('Analytics', 'teinformez'),
            __('Analytics', 'teinformez'),
            'manage_options',
            'teinformez-analytics',
            [$this, 'render_analytics']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'teinformez') === false) {
            return;
        }

        wp_enqueue_style(
            'teinformez-admin',
            TEINFORMEZ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TEINFORMEZ_VERSION
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        echo '<div class="wrap"><h1>' . __('TeInformez Dashboard', 'teinformez') . '</h1>';
        echo '<p>' . __('Welcome to TeInformez! Configure your settings and manage news queue.', 'teinformez') . '</p>';
        echo '</div>';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        if (isset($_POST['teinformez_settings_nonce']) && wp_verify_nonce($_POST['teinformez_settings_nonce'], 'teinformez_save_settings')) {
            $this->save_settings();
        }

        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Render news queue page
     */
    public function render_news_queue() {
        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/views/news-queue.php';
    }

    /**
     * Render Juridic Q&A management page
     */
    public function render_juridic_queue() {
        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/views/juridic-queue.php';
    }

    /**
     * Render category order page
     */
    public function render_category_order() {
        if (isset($_POST['teinformez_catorder_nonce']) && wp_verify_nonce($_POST['teinformez_catorder_nonce'], 'teinformez_save_catorder')) {
            $this->save_category_order();
        }

        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/views/category-order.php';
    }

    /**
     * Render analytics page
     */
    public function render_analytics() {
        require_once TEINFORMEZ_PLUGIN_DIR . 'admin/views/analytics.php';
    }

    /**
     * Save category order
     */
    private function save_category_order() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $order_raw = isset($_POST['category_order']) ? sanitize_text_field($_POST['category_order']) : '';
        $order = array_filter(array_map('trim', explode(',', $order_raw)));

        // Save hidden categories
        $hidden = isset($_POST['hidden_categories']) ? array_map('sanitize_text_field', $_POST['hidden_categories']) : [];
        update_option('teinformez_hidden_categories', $hidden);

        update_option('teinformez_category_order', $order);

        add_settings_error(
            'teinformez_messages',
            'teinformez_message',
            __('Setarile categoriilor au fost salvate.', 'teinformez'),
            'updated'
        );
    }

    /**
     * Save settings
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $fields = [
            'openai_api_key',
            'brevo_api_key',
            'sendgrid_api_key',
            'from_email',
            'from_name',
            'frontend_url',
            'admin_review_period',
            'news_fetch_interval',
            'ga4_property_id',
            'ga4_service_account_email',
            'ga4_private_key',
            // Social media (Phase E)
            'social_posting_enabled',
            'facebook_page_id',
            'facebook_access_token',
            'twitter_api_key',
            'twitter_api_secret',
            'twitter_access_token',
            'twitter_access_token_secret',
            // YouTube
            'youtube_api_key',
        ];

        // Handle checkbox fields (unchecked = not in POST)
        $checkboxes = ['social_posting_enabled'];

        foreach ($fields as $field) {
            if (in_array($field, $checkboxes)) {
                Config::set($field, isset($_POST[$field]) ? '1' : '0');
            } elseif ($field === 'ga4_private_key' && isset($_POST[$field])) {
                // Keep line breaks for PEM format keys.
                $value = trim((string) wp_unslash($_POST[$field]));
                Config::set($field, $value);
            } elseif (isset($_POST[$field])) {
                Config::set($field, sanitize_text_field($_POST[$field]));
            }
        }

        add_settings_error(
            'teinformez_messages',
            'teinformez_message',
            __('Settings saved successfully.', 'teinformez'),
            'updated'
        );
    }
}
