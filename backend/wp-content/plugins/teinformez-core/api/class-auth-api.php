<?php
namespace TeInformez\API;

use TeInformez\User_Manager;
use TeInformez\GDPR_Handler;
use TeInformez\Email_Sender;
use TeInformez\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication API endpoints
 */
class Auth_API extends REST_API {

    public function register_routes() {
        // Register new user
        register_rest_route($this->namespace, '/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register'],
            'permission_callback' => '__return_true'
        ]);

        // Login
        register_rest_route($this->namespace, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true'
        ]);

        // Logout
        register_rest_route($this->namespace, '/auth/logout', [
            'methods' => 'POST',
            'callback' => [$this, 'logout'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Get current user
        register_rest_route($this->namespace, '/auth/me', [
            'methods' => 'GET',
            'callback' => [$this, 'me'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);

        // Refresh token
        register_rest_route($this->namespace, '/auth/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refresh_token'],
            'permission_callback' => '__return_true'
        ]);

        // Request password reset
        register_rest_route($this->namespace, '/auth/forgot-password', [
            'methods' => 'POST',
            'callback' => [$this, 'forgot_password'],
            'permission_callback' => '__return_true'
        ]);

        // Reset password with token
        register_rest_route($this->namespace, '/auth/reset-password', [
            'methods' => 'POST',
            'callback' => [$this, 'reset_password'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Register new user
     */
    public function register($request) {
        $params = $request->get_json_params();

        // Validate required fields
        $validation = $this->validate_required($params, ['email', 'password', 'gdpr_consent']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Check GDPR consent
        if (empty($params['gdpr_consent']) || $params['gdpr_consent'] !== true) {
            return $this->error(
                __('You must accept the privacy policy to register.', 'teinformez'),
                'gdpr_required',
                400
            );
        }

        // Validate email
        $email = sanitize_email($params['email']);
        if (!is_email($email)) {
            return $this->error(
                __('Invalid email address.', 'teinformez'),
                'invalid_email',
                400
            );
        }

        // Check if user exists
        if (email_exists($email)) {
            return $this->error(
                __('This email is already registered.', 'teinformez'),
                'email_exists',
                409
            );
        }

        // Validate password strength (min 8 chars)
        if (strlen($params['password']) < 8) {
            return $this->error(
                __('Password must be at least 8 characters long.', 'teinformez'),
                'weak_password',
                400
            );
        }

        // Create user
        $user_id = wp_create_user(
            $email,
            $params['password'],
            $email
        );

        if (is_wp_error($user_id)) {
            return $this->error(
                $user_id->get_error_message(),
                'registration_failed',
                500
            );
        }

        // Update display name if provided
        if (!empty($params['name'])) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => sanitize_text_field($params['name']),
                'first_name' => sanitize_text_field($params['name'])
            ]);
        }

        // Record GDPR consent
        $gdpr_handler = new GDPR_Handler();
        $gdpr_handler->record_consent($user_id, $request->get_header('x-forwarded-for') ?: $_SERVER['REMOTE_ADDR']);

        // Create default user preferences
        $user_manager = new User_Manager();
        $user_manager->create_default_preferences($user_id, $params['preferred_language'] ?? 'ro');

        // Auto-login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Generate token
        $token = $this->generate_token($user_id);

        return $this->success([
            'user' => $this->format_user_data($user_id),
            'token' => $token
        ], __('Registration successful!', 'teinformez'), 201);
    }

    /**
     * Login user
     */
    public function login($request) {
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['email', 'password']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $credentials = [
            'user_login' => sanitize_email($params['email']),
            'user_password' => $params['password'],
            'remember' => $params['remember'] ?? true
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            return $this->error(
                __('Invalid email or password.', 'teinformez'),
                'invalid_credentials',
                401
            );
        }

        wp_set_current_user($user->ID);

        $token = $this->generate_token($user->ID);

        return $this->success([
            'user' => $this->format_user_data($user->ID),
            'token' => $token
        ], __('Login successful!', 'teinformez'));
    }

    /**
     * Logout user
     */
    public function logout($request) {
        wp_logout();
        return $this->success([], __('Logged out successfully.', 'teinformez'));
    }

    /**
     * Get current user data
     */
    public function me($request) {
        $user_id = $this->get_current_user_id();

        if (!$user_id) {
            return $this->error(
                __('Not authenticated.', 'teinformez'),
                'not_authenticated',
                401
            );
        }

        return $this->success([
            'user' => $this->format_user_data($user_id)
        ]);
    }

    /**
     * Refresh authentication token
     */
    public function refresh_token($request) {
        $user_id = $this->get_current_user_id();

        if (!$user_id) {
            return $this->error(
                __('Not authenticated.', 'teinformez'),
                'not_authenticated',
                401
            );
        }

        $token = $this->generate_token($user_id);

        return $this->success([
            'token' => $token
        ]);
    }

    /**
     * Generate secure token using HMAC
     */
    private function generate_token($user_id) {
        $expires_at = time() + (7 * DAY_IN_SECONDS); // 7 days

        // Create token data
        $token_data = $user_id . '|' . $expires_at;

        // Sign with WordPress AUTH_KEY (unique per site)
        $signature = hash_hmac('sha256', $token_data, AUTH_KEY);

        // Return base64 encoded token: user_id|expires_at|signature
        return base64_encode($token_data . '|' . $signature);
    }

    /**
     * Validate token and return user_id if valid
     */
    public static function validate_auth_token($token) {
        // Decode token
        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        list($user_id, $expires_at, $signature) = $parts;

        // Check if expired
        if (time() > (int)$expires_at) {
            return false;
        }

        // Verify signature
        $token_data = $user_id . '|' . $expires_at;
        $expected_signature = hash_hmac('sha256', $token_data, AUTH_KEY);

        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        // Verify user exists
        $user = get_userdata((int)$user_id);
        if (!$user) {
            return false;
        }

        return (int)$user_id;
    }

    /**
     * Request password reset
     */
    public function forgot_password($request) {
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['email']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $email = sanitize_email($params['email']);
        $user = get_user_by('email', $email);

        // Always return success to prevent email enumeration attacks
        $success_message = __('If an account exists with this email, you will receive a password reset link.', 'teinformez');

        if (!$user) {
            return $this->success([], $success_message);
        }

        // Generate reset token (valid for 24 hours)
        $reset_token = $this->generate_reset_token($user->ID);

        // Save token in user meta
        update_user_meta($user->ID, '_teinformez_reset_token', $reset_token);
        update_user_meta($user->ID, '_teinformez_reset_expires', time() + (24 * HOUR_IN_SECONDS));

        // Build reset link
        $frontend_url = Config::get('frontend_url', 'https://teinformez.vercel.app');
        $reset_link = $frontend_url . '/reset-password?token=' . urlencode($reset_token) . '&email=' . urlencode($email);

        // Send email
        $email_sender = new Email_Sender();
        $email_sender->send_password_reset($email, $reset_link);

        return $this->success([], $success_message);
    }

    /**
     * Reset password with token
     */
    public function reset_password($request) {
        $params = $request->get_json_params();

        $validation = $this->validate_required($params, ['email', 'token', 'password']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $email = sanitize_email($params['email']);
        $token = sanitize_text_field($params['token']);
        $password = $params['password'];

        // Validate password strength
        if (strlen($password) < 8) {
            return $this->error(
                __('Password must be at least 8 characters long.', 'teinformez'),
                'weak_password',
                400
            );
        }

        $user = get_user_by('email', $email);

        if (!$user) {
            return $this->error(
                __('Invalid reset link.', 'teinformez'),
                'invalid_token',
                400
            );
        }

        // Verify token
        $stored_token = get_user_meta($user->ID, '_teinformez_reset_token', true);
        $expires = get_user_meta($user->ID, '_teinformez_reset_expires', true);

        if (empty($stored_token) || !hash_equals($stored_token, $token)) {
            return $this->error(
                __('Invalid reset link.', 'teinformez'),
                'invalid_token',
                400
            );
        }

        if (time() > (int)$expires) {
            // Clean up expired token
            delete_user_meta($user->ID, '_teinformez_reset_token');
            delete_user_meta($user->ID, '_teinformez_reset_expires');

            return $this->error(
                __('Reset link has expired. Please request a new one.', 'teinformez'),
                'token_expired',
                400
            );
        }

        // Reset password
        wp_set_password($password, $user->ID);

        // Clean up token
        delete_user_meta($user->ID, '_teinformez_reset_token');
        delete_user_meta($user->ID, '_teinformez_reset_expires');

        return $this->success([], __('Password reset successfully. You can now log in with your new password.', 'teinformez'));
    }

    /**
     * Generate secure reset token
     */
    private function generate_reset_token($user_id) {
        $random = bin2hex(random_bytes(32));
        $data = $user_id . '|' . $random . '|' . time();
        return base64_encode($data . '|' . hash_hmac('sha256', $data, AUTH_KEY));
    }

    /**
     * Format user data for response
     */
    private function format_user_data($user_id) {
        $user = get_userdata($user_id);
        $user_manager = new User_Manager();
        $preferences = $user_manager->get_user_preferences($user_id);

        return [
            'id' => $user_id,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'registered_at' => $user->user_registered,
            'preferences' => $preferences,
            'role' => $user->roles[0] ?? 'subscriber'
        ];
    }
}
