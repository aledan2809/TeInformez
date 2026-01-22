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
            'news_fetch_interval'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
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
