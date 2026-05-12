<?php
namespace TeInformez\API;

use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public newsletter subscription endpoint (no account required)
 */
class Newsletter_API extends REST_API {

    public function register_routes() {
        register_rest_route($this->namespace, '/newsletter/subscribe', [
            'methods'             => 'POST',
            'callback'            => [$this, 'subscribe'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function subscribe($request) {
        $rl = $this->check_rate_limit('newsletter_subscribe', 3, 15);
        if ($rl) return $rl;

        $params = $request->get_json_params();

        $email = sanitize_email($params['email'] ?? '');
        if (!is_email($email)) {
            return $this->error('Adresă de email invalidă.', 'invalid_email', 400);
        }

        $categories = [];
        if (!empty($params['categories']) && is_array($params['categories'])) {
            foreach ($params['categories'] as $cat) {
                $categories[] = sanitize_text_field($cat);
            }
        }

        global $wpdb;

        // Get or create a WP user for this email
        $user_id = email_exists($email);

        if (!$user_id) {
            // Create a newsletter-only WP subscriber (random password — no login intended)
            $password = wp_generate_password(24, true, true);
            $user_id = wp_create_user($email, $password, $email);

            if (is_wp_error($user_id)) {
                return $this->error('Eroare la procesarea cererii.', 'user_create_failed', 500);
            }

            // Mark as newsletter-only (not a full account)
            update_user_meta($user_id, 'teinformez_newsletter_only', '1');

            // Create default preferences row so the subscriptions FK is satisfied
            $user_manager = new \TeInformez\User_Manager();
            $user_manager->create_default_preferences($user_id, 'ro');
        }

        // Idempotent: flag existing full-account users as also receiving newsletter
        update_user_meta($user_id, 'teinformez_newsletter_subscribed', '1');

        // Subscribe to requested categories (upsert)
        $table = $wpdb->prefix . 'teinformez_subscriptions';
        foreach ($categories as $slug) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND category_slug = %s",
                $user_id, $slug
            ));
            if (!$existing) {
                $wpdb->insert($table, [
                    'user_id'        => $user_id,
                    'category_slug'  => $slug,
                    'topic_keyword'  => '',
                    'country_filter' => 'romania',
                    'is_active'      => 1,
                    'created_at'     => current_time('mysql'),
                    'updated_at'     => current_time('mysql'),
                ]);
            } else {
                $wpdb->update($table, ['is_active' => 1, 'updated_at' => current_time('mysql')], ['id' => $existing]);
            }
        }

        // Send confirmation email
        $this->send_confirmation_email($email, $categories);

        return $this->success(['subscribed' => true], 'Abonare reușită! Vei primi un email de confirmare.');
    }

    private function send_confirmation_email(string $email, array $categories): void {
        $category_list = empty($categories)
            ? 'toate categoriile'
            : implode(', ', $categories);

        $subject = 'Bine ai venit la TeInformez.eu!';
        $body = "Salut,\n\nAi fost abonat cu succes la newsletter-ul TeInformez.eu.\n\nCategorii alese: {$category_list}\n\nVei primi zilnic un rezumat cu cele mai importante știri, sintetizate de AI.\n\nDacă nu ai solicitat această abonare, ignora acest mesaj.\n\nEchipa TeInformez";

        wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
    }
}
