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
                    <label for="sendgrid_api_key"><?php _e('SendGrid API Key', 'teinformez'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="sendgrid_api_key"
                           name="sendgrid_api_key"
                           value="<?php echo esc_attr(Config::get('sendgrid_api_key', '')); ?>"
                           class="regular-text"
                    />
                    <p class="description">
                        <?php _e('Required for sending personalized news emails.', 'teinformez'); ?>
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
