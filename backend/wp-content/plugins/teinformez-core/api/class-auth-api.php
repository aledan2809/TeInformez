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

    /**
     * H-02: Rate limiter — max 5 attempts per IP per 15-minute sliding window.
     * Stores {count, start} in a single transient so the window is preserved on
     * all backends (DB, Redis, Memcached) — no reliance on _transient_timeout_ option.
     * Returns WP_REST_Response error on limit exceeded, null when OK.
     */
    private function check_rate_limit(string $action): ?\WP_Error {
        $ip  = Config::get_client_ip() ?: 'unknown';
        $key = 'teinformez_rl_' . $action . '_' . md5($ip);

        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, ['count' => 1, 'start' => time()], 15 * MINUTE_IN_SECONDS);
            return null;
        }

        if ((int) $data['count'] >= 5) {
            header('Retry-After: 900');
            return $this->error(
                __('Too many attempts. Please try again later.', 'teinformez'),
                'rate_limit_exceeded',
                429
            );
        }

        // Preserve original window start — remaining TTL = 15 min minus elapsed
        $remaining = max(1, 15 * MINUTE_IN_SECONDS - (time() - (int) $data['start']));
        set_transient($key, ['count' => $data['count'] + 1, 'start' => $data['start']], $remaining);

        return null;
    }

    private function clear_rate_limit(string $action): void {
        $ip  = Config::get_client_ip() ?: 'unknown';
        delete_transient('teinformez_rl_' . $action . '_' . md5($ip));
    }

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

        // Set secure httpOnly cookie — M-03: requires authentication
        register_rest_route($this->namespace, '/auth/set-secure-cookie', [
            'methods' => 'POST',
            'callback' => [$this, 'set_secure_cookie'],
            'permission_callback' => [$this, 'is_authenticated']
        ]);
    }

    /**
     * Register new user
     */
    public function register($request) {
        $rl = $this->check_rate_limit('register');
        if ($rl) return $rl;

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

        // Check if user exists — return generic success to prevent email enumeration
        if (email_exists($email)) {
            return $this->success(
                null,
                __('Dacă adresa de email nu este înregistrată, vei primi un email de confirmare.', 'teinformez')
            );
        }

        // Validate password strength
        $password = $params['password'];
        $strength = $this->validate_password_strength($password);
        if (is_wp_error($strength)) {
            return $strength;
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

        // Create default user preferences FIRST (INSERT row)
        $user_manager = new User_Manager();
        $user_manager->create_default_preferences($user_id, $params['preferred_language'] ?? 'ro');

        // Record GDPR consent with IP and policy version (UPDATE existing row)
        $gdpr_handler = new GDPR_Handler();
        $client_ip = Config::get_client_ip();
        $gdpr_handler->record_consent($user_id, $client_ip, Config::PRIVACY_POLICY_VERSION);

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
        $rl = $this->check_rate_limit('login');
        if ($rl) return $rl;

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

        // Clear rate limit window on successful login so the user isn't locked out
        $this->clear_rate_limit('login');

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
     * Generate secure token using HMAC.
     * H-03: The signing secret includes the user's password hash so that
     * any password change or account deletion immediately invalidates all
     * previously issued tokens (no server-side token store needed).
     */
    private function generate_token($user_id) {
        $expires_at = time() + DAY_IN_SECONDS;
        $token_data = $user_id . '|' . $expires_at;

        // Per-user secret: AUTH_KEY + password hash fragment — rotates on password change
        $user_data  = get_userdata((int)$user_id);
        $per_user   = $user_data ? substr($user_data->user_pass, 8, 12) : '';
        $secret     = AUTH_KEY . $per_user;

        $signature = hash_hmac('sha256', $token_data, $secret);

        return base64_encode($token_data . '|' . $signature);
    }

    /**
     * Validate token and return user_id if valid.
     * H-03: Re-derives the per-user secret from the current password hash;
     * old tokens become invalid as soon as the password changes.
     */
    public static function validate_auth_token($token) {
        $decoded = base64_decode($token);
        if (!$decoded) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        list($user_id, $expires_at, $signature) = $parts;

        if (time() > (int)$expires_at) {
            return false;
        }

        $user = get_userdata((int)$user_id);
        if (!$user) {
            return false;
        }

        // Re-derive the same per-user secret used at token generation
        $per_user          = substr($user->user_pass, 8, 12);
        $secret            = AUTH_KEY . $per_user;
        $token_data        = $user_id . '|' . $expires_at;
        $expected_signature = hash_hmac('sha256', $token_data, $secret);

        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        return (int)$user_id;
    }

    /**
     * Request password reset
     */
    public function forgot_password($request) {
        $rl = $this->check_rate_limit('forgot');
        if ($rl) return $rl;

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
        $strength = $this->validate_password_strength($password);
        if (is_wp_error($strength)) {
            return $strength;
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
     * Set secure httpOnly cookie
     * M-03: Requires authentication. Validates the submitted token cryptographically
     * and verifies it belongs to the authenticated user before setting the cookie.
     */
    public function set_secure_cookie($request) {
        $params = $request->get_json_params();

        // Validate token is provided
        if (empty($params['token'])) {
            return $this->error(
                __('Token is required.', 'teinformez'),
                'token_required',
                400
            );
        }

        // M-03: Use wp_unslash only — sanitize_text_field strips base64 chars (+/=)
        $token = wp_unslash($params['token']);

        // M-03: Full cryptographic validation — reject tokens that fail signature/expiry check
        $token_user_id = self::validate_auth_token($token);
        if (!$token_user_id) {
            return $this->error(
                __('Invalid or expired token.', 'teinformez'),
                'invalid_token',
                401
            );
        }

        // M-03: Token must belong to the authenticated user — prevents setting another user's token
        $authenticated_user_id = $this->get_current_user_id();
        if ((int) $token_user_id !== (int) $authenticated_user_id) {
            return $this->error(
                __('Token does not belong to the authenticated user.', 'teinformez'),
                'token_mismatch',
                403
            );
        }

        // Set httpOnly cookie (more secure than js-cookie)
        $cookie_name = 'teinformez_secure_token';
        $expires = time() + (24 * 60 * 60); // 24 hours
        $domain = parse_url(home_url(), PHP_URL_HOST);

        setcookie(
            $cookie_name,
            $token,
            [
                'expires' => $expires,
                'path' => '/',
                'domain' => $domain,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );

        return $this->success([
            'message' => 'Secure cookie set successfully',
            'expires' => $expires
        ]);
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
