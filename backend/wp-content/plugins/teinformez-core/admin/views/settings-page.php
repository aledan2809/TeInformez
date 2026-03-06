<?php
if (!defined('ABSPATH')) {
    exit;
}

use TeInformez\Config;
?>

<div class="wrap">
    <h1><?php _e('TeInformez Settings', 'teinformez'); ?></h1>

    <?php settings_errors('teinformez_messages'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('teinformez_save_settings', 'teinformez_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="openai_api_key"><?php _e('OpenAI API Key', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="openai_api_key"
                           name="openai_api_key"
                           value="<?php echo esc_attr(Config::get('openai_api_key', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Required for AI news processing and translation.', 'teinformez'); ?>
                        <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Get API key', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="brevo_api_key"><?php _e('Brevo API Key', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="brevo_api_key"
                           name="brevo_api_key"
                           value="<?php echo esc_attr(Config::get('brevo_api_key', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Required for sending emails (password reset, notifications). Free: 300 emails/day.', 'teinformez'); ?>
                        <a href="https://app.brevo.com/settings/keys/api" target="_blank"><?php _e('Get API key', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="from_email"><?php _e('From Email', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="email"
                           id="from_email"
                           name="from_email"
                           value="<?php echo esc_attr(Config::get('from_email', 'noreply@teinformez.eu')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Email address used as sender for all outgoing emails.', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="from_name"><?php _e('From Name', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="from_name"
                           name="from_name"
                           value="<?php echo esc_attr(Config::get('from_name', 'TeInformez')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Name shown as sender for all outgoing emails.', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="frontend_url"><?php _e('Frontend URL', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="url"
                           id="frontend_url"
                           name="frontend_url"
                           value="<?php echo esc_attr(Config::get('frontend_url', 'https://teinformez.vercel.app')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('URL of the Next.js frontend (used for password reset links, etc.).', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="sendgrid_api_key"><?php _e('SendGrid API Key (optional)', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="sendgrid_api_key"
                           name="sendgrid_api_key"
                           value="<?php echo esc_attr(Config::get('sendgrid_api_key', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Alternative to Brevo. Only used if Brevo API key is not set.', 'teinformez'); ?>
                        <a href="https://app.sendgrid.com/settings/api_keys" target="_blank"><?php _e('Get API key', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="admin_review_period"><?php _e('Admin Review Period (seconds)', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="admin_review_period"
                           name="admin_review_period"
                           value="<?php echo esc_attr(Config::get('admin_review_period', 7200)); ?>"
                           min="0"
                           step="1"
                    />
                    <p class="description">
                        <?php _e('Time to review AI-processed news before auto-publishing. Default: 7200 (2 hours)', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="news_fetch_interval"><?php _e('News Fetch Interval (seconds)', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="news_fetch_interval"
                           name="news_fetch_interval"
                           value="<?php echo esc_attr(Config::get('news_fetch_interval', 1800)); ?>"
                           min="300"
                           step="300"
                    />
                    <p class="description">
                        <?php _e('How often to fetch new content from sources. Default: 1800 (30 minutes)', 'teinformez'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Google Analytics (GA4)', 'teinformez'); ?></h2>
        <p class="description"><?php _e('Used in admin Analytics page for side-by-side comparison with custom metrics.', 'teinformez'); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ga4_property_id"><?php _e('GA4 Property ID', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="ga4_property_id"
                           name="ga4_property_id"
                           value="<?php echo esc_attr(Config::get('ga4_property_id', Config::get('google_analytics_property_id', ''))); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Numeric Property ID from Google Analytics (example: 123456789).', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ga4_service_account_email"><?php _e('Service Account Email', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="ga4_service_account_email"
                           name="ga4_service_account_email"
                           value="<?php echo esc_attr(Config::get('ga4_service_account_email', Config::get('google_client_email', ''))); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Client email from Google service account JSON key.', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ga4_private_key"><?php _e('Service Account Private Key', 'teinformez'); ?></label>
                </th>
                <td>
                    <textarea id="ga4_private_key"
                              name="ga4_private_key"
                              rows="8"
                              class="large-text code"><?php echo esc_textarea(Config::get('ga4_private_key', Config::get('google_private_key', ''))); ?></textarea>
                    <p class="description">
                        <?php _e('Paste full private key including BEGIN/END lines.', 'teinformez'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Social Media Posting', 'teinformez'); ?></h2>
        <p class="description"><?php _e('Auto-post published news to your social media accounts.', 'teinformez'); ?></p>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="social_posting_enabled"><?php _e('Enable Social Posting', 'teinformez'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               id="social_posting_enabled"
                               name="social_posting_enabled"
                               value="1"
                               <?php checked(Config::get('social_posting_enabled', '0'), '1'); ?>
                        />
                        <?php _e('Auto-post to Facebook and Twitter when news is published', 'teinformez'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="facebook_page_id"><?php _e('Facebook Page ID', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="facebook_page_id"
                           name="facebook_page_id"
                           value="<?php echo esc_attr(Config::get('facebook_page_id', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Your Facebook Page numeric ID.', 'teinformez'); ?>
                        <a href="https://developers.facebook.com/docs/pages-api/" target="_blank"><?php _e('Pages API docs', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="facebook_access_token"><?php _e('Facebook Page Access Token', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="facebook_access_token"
                           name="facebook_access_token"
                           value="<?php echo esc_attr(Config::get('facebook_access_token', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Long-lived Page Access Token (never expires).', 'teinformez'); ?>
                        <a href="https://developers.facebook.com/tools/explorer/" target="_blank"><?php _e('Graph API Explorer', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="twitter_api_key"><?php _e('Twitter API Key', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="twitter_api_key"
                           name="twitter_api_key"
                           value="<?php echo esc_attr(Config::get('twitter_api_key', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Also called Consumer Key (API Key).', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="twitter_api_secret"><?php _e('Twitter API Secret', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="twitter_api_secret"
                           name="twitter_api_secret"
                           value="<?php echo esc_attr(Config::get('twitter_api_secret', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Also called Consumer Secret (API Secret).', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="twitter_access_token"><?php _e('Twitter Access Token', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="twitter_access_token"
                           name="twitter_access_token"
                           value="<?php echo esc_attr(Config::get('twitter_access_token', '')); ?>"
                           class="regular-text"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="twitter_access_token_secret"><?php _e('Twitter Access Token Secret', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="twitter_access_token_secret"
                           name="twitter_access_token_secret"
                           value="<?php echo esc_attr(Config::get('twitter_access_token_secret', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Get all Twitter keys from', 'teinformez'); ?>
                        <a href="https://developer.x.com/en/portal/dashboard" target="_blank"><?php _e('X Developer Portal', 'teinformez'); ?></a>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Site Configuration', 'teinformez'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Primary Language', 'teinformez'); ?></th>
                <td>
                    <strong><?php echo esc_html(Config::SITE_LANGUAGE); ?></strong>
                    <p class="description">
                        <?php _e('To change this, edit SITE_LANGUAGE constant in includes/class-config.php', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Target Country', 'teinformez'); ?></th>
                <td>
                    <strong><?php echo esc_html(Config::SITE_COUNTRY); ?></strong>
                    <p class="description">
                        <?php _e('To change this, edit SITE_COUNTRY constant in includes/class-config.php', 'teinformez'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Timezone', 'teinformez'); ?></th>
                <td>
                    <strong><?php echo esc_html(Config::SITE_TIMEZONE); ?></strong>
                    <p class="description">
                        <?php _e('To change this, edit SITE_TIMEZONE constant in includes/class-config.php', 'teinformez'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'teinformez')); ?>
    </form>
</div>
