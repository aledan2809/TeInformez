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
