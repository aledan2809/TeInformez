<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email sending functionality using Brevo API or wp_mail fallback
 */
class Email_Sender {

    private $api_key;
    private $from_email;
    private $from_name;

    public function __construct() {
        $this->api_key = Config::get('brevo_api_key', '');
        $this->from_email = Config::get('from_email', 'noreply@teinformez.eu');
        $this->from_name = Config::get('from_name', 'TeInformez');
    }

    /**
     * Send email using Brevo API or fallback to wp_mail
     */
    public function send($to_email, $subject, $html_content, $text_content = '') {
        // If Brevo API key is configured, use Brevo
        if (!empty($this->api_key)) {
            return $this->send_via_brevo($to_email, $subject, $html_content, $text_content);
        }

        // Fallback to WordPress mail
        return $this->send_via_wp_mail($to_email, $subject, $html_content);
    }

    /**
     * Send via Brevo API
     */
    private function send_via_brevo($to_email, $subject, $html_content, $text_content = '') {
        $url = 'https://api.brevo.com/v3/smtp/email';

        error_log('TeInformez Brevo: Preparing to send email');
        error_log('TeInformez Brevo: From: ' . $this->from_name . ' <' . $this->from_email . '>');
        error_log('TeInformez Brevo: To: ' . $to_email);
        error_log('TeInformez Brevo: Subject: ' . $subject);

        $body = [
            'sender' => [
                'name' => $this->from_name,
                'email' => $this->from_email
            ],
            'to' => [
                ['email' => $to_email]
            ],
            'subject' => $subject,
            'htmlContent' => $html_content
        ];

        if (!empty($text_content)) {
            $body['textContent'] = $text_content;
        }

        // Force IPv4 to avoid Brevo IPv6 whitelist issue
        \add_action('http_api_curl', function($handle) {
            curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        });

        $response = wp_remote_post($url, [
            'headers' => [
                'accept' => 'application/json',
                'api-key' => $this->api_key,
                'content-type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('TeInformez Brevo ERROR: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('TeInformez Brevo Response Status: ' . $status_code);
        error_log('TeInformez Brevo Response Body: ' . $response_body);

        if ($status_code >= 200 && $status_code < 300) {
            error_log('TeInformez Brevo: Email sent successfully!');
            return true;
        }

        error_log('TeInformez Brevo FAILED: Status ' . $status_code . ' - ' . $response_body);
        return false;
    }

    /**
     * Send via WordPress mail (fallback)
     */
    private function send_via_wp_mail($to_email, $subject, $html_content) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>'
        ];

        return wp_mail($to_email, $subject, $html_content, $headers);
    }

    /**
     * Send alert email to site admin using wp_mail (avoids circular dependency with Brevo).
     */
    public function send_admin_alert($subject, $message) {
        $admin_email = get_option('admin_email');

        if (empty($admin_email)) {
            error_log('TeInformez Admin Alert: No admin email configured.');
            return false;
        }

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>'
        ];

        $result = wp_mail($admin_email, $subject, $message, $headers);

        if ($result) {
            error_log('TeInformez Admin Alert: Sent to ' . $admin_email . ' — ' . $subject);
        } else {
            error_log('TeInformez Admin Alert: FAILED to send to ' . $admin_email . ' — ' . $subject);
        }

        return $result;
    }

    /**
     * Send password reset email
     */
    public function send_password_reset($user_email, $reset_link) {
        $subject = 'Resetare parolă - TeInformez';

        $html_content = $this->get_email_template('password_reset', [
            'reset_link' => $reset_link,
            'valid_hours' => '24'
        ]);
        $html_content .= $this->get_unsubscribe_footer($user_email, 'registered');

        error_log('TeInformez: Attempting to send password reset email to: ' . $user_email);
        error_log('TeInformez: Reset link: ' . $reset_link);
        error_log('TeInformez: Brevo API key configured: ' . (!empty($this->api_key) ? 'YES' : 'NO'));

        $result = $this->send($user_email, $subject, $html_content);

        error_log('TeInformez: Email send result: ' . ($result ? 'SUCCESS' : 'FAILED'));

        return $result;
    }

    /**
     * Send welcome email after registration
     */
    public function send_welcome($user_email, $user_name) {
        $subject = 'Bine ai venit pe TeInformez!';

        $html_content = $this->get_email_template('welcome', [
            'user_name' => $user_name,
            'dashboard_link' => Config::get('frontend_url', 'https://teinformez.eu') . '/dashboard'
        ]);
        $html_content .= $this->get_unsubscribe_footer($user_email, 'registered');

        return $this->send($user_email, $subject, $html_content);
    }

    /**
     * Send newsletter double opt-in confirmation email
     */
    public function send_newsletter_confirmation($to_email, $confirm_link) {
        $subject = 'Confirmă abonarea la newsletter - TeInformez';

        $html_content = $this->get_email_template('newsletter_confirmation', [
            'confirm_link' => $confirm_link,
        ]);
        // No unsubscribe footer on confirmation email — they haven't subscribed yet

        error_log('TeInformez: Sending newsletter confirmation to: ' . $to_email);

        return $this->send($to_email, $subject, $html_content);
    }

    /**
     * Generate unsubscribe footer HTML
     *
     * @param string $email  The recipient email address
     * @param string $type   'registered' for WP users, 'newsletter' for newsletter subscribers
     * @param string $token  Newsletter token (required when $type is 'newsletter')
     * @return string HTML footer with unsubscribe link
     */
    public function get_unsubscribe_footer($email, $type = 'registered', $token = '') {
        $frontend_url = Config::get('frontend_url', Config::FRONTEND_URL);

        if ($type === 'newsletter' && !empty($token)) {
            $unsubscribe_url = $frontend_url . '/newsletter/unsubscribe?token=' . urlencode($token);
            $link_text = 'Dezabonare newsletter';
        } else {
            // For registered users, link to dashboard settings
            $unsubscribe_url = $frontend_url . '/dashboard/settings';
            $link_text = 'Gestionează preferințele de email';
        }

        return '
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af;">
                <p>Ai primit acest email deoarece ești abonat la TeInformez.</p>
                <p><a href="' . esc_url($unsubscribe_url) . '" style="color: #6b7280; text-decoration: underline;">' . esc_html($link_text) . '</a></p>
            </div>';
    }

    /**
     * Get email template
     */
    private function get_email_template($template_name, $vars = []) {
        $templates = [
            'password_reset' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>TeInformez</h1>
                        </div>
                        <div class="content">
                            <h2>Resetare parolă</h2>
                            <p>Ai solicitat resetarea parolei pentru contul tău TeInformez.</p>
                            <p>Apasă pe butonul de mai jos pentru a-ți seta o parolă nouă:</p>
                            <p style="text-align: center;">
                                <a href="{{reset_link}}" class="button" style="color: white;">Resetează parola</a>
                            </p>
                            <p>Sau copiază acest link în browser:</p>
                            <p style="word-break: break-all; background: #e5e7eb; padding: 10px; border-radius: 4px; font-size: 14px;">{{reset_link}}</p>
                            <p><strong>Acest link este valid pentru {{valid_hours}} ore.</strong></p>
                            <p>Dacă nu ai solicitat resetarea parolei, poți ignora acest email.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' TeInformez. Toate drepturile rezervate.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'newsletter_confirmation' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>TeInformez</h1>
                        </div>
                        <div class="content">
                            <h2>Confirmă abonarea la newsletter</h2>
                            <p>Mulțumim pentru interesul tău! Pentru a finaliza abonarea, te rugăm să confirmi adresa de email apăsând butonul de mai jos:</p>
                            <p style="text-align: center;">
                                <a href="{{confirm_link}}" class="button" style="color: white;">Confirmă abonarea</a>
                            </p>
                            <p>Sau copiază acest link în browser:</p>
                            <p style="word-break: break-all; background: #e5e7eb; padding: 10px; border-radius: 4px; font-size: 14px;">{{confirm_link}}</p>
                            <p>Dacă nu ai solicitat abonarea, poți ignora acest email.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' TeInformez. Toate drepturile rezervate.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'welcome' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>TeInformez</h1>
                        </div>
                        <div class="content">
                            <h2>Bine ai venit, {{user_name}}!</h2>
                            <p>Mulțumim că te-ai înregistrat pe TeInformez - platforma ta personalizată de știri.</p>
                            <p>Contul tău este activ și poți începe să-ți configurezi preferințele de știri.</p>
                            <p style="text-align: center;">
                                <a href="{{dashboard_link}}" class="button" style="color: white;">Accesează Dashboard</a>
                            </p>
                            <h3>Ce poți face acum:</h3>
                            <ul>
                                <li>Adaugă categorii și subiecte care te interesează</li>
                                <li>Setează frecvența de livrare a știrilor</li>
                                <li>Alege canalele de notificare (email, push, etc.)</li>
                            </ul>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' TeInformez. Toate drepturile rezervate.</p>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];

        $template = $templates[$template_name] ?? '';

        // Replace variables - don't escape URLs
        foreach ($vars as $key => $value) {
            // Don't escape URLs (they contain special characters that need to remain intact)
            if (strpos($key, 'link') !== false || strpos($key, 'url') !== false) {
                $template = str_replace('{{' . $key . '}}', esc_url($value), $template);
            } else {
                $template = str_replace('{{' . $key . '}}', esc_html($value), $template);
            }
        }

        return $template;
    }
}
