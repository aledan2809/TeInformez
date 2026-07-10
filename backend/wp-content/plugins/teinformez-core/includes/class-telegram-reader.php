<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reader-facing Telegram channel ("primește știrile pe Telegram").
 *
 * Deep-link opt-in flow, mirroring the ecosystem pattern (eat/Tutor/4pro-client):
 *  1. Logged-in reader mints a single-use nonce -> t.me/<bot>?start=<nonce>
 *  2. Telegram webhook receives "/start <nonce>" (PRIVATE chats only, fail-closed)
 *     -> binds chat_id to the WP user -> confirmation message
 *  3. Delivery handler pushes the digest to linked users who chose the
 *     'telegram' channel.
 *
 * Uses a dedicated GLOBAL reader bot (options below) — deliberately separate
 * from the admin Telegram Workspace, whose token lives per-admin in user meta
 * and drives group bulk-messaging (a different tool entirely).
 *
 * Activation (one-time, user action): create a bot via BotFather, then set:
 *   wp option update teinformez_tg_reader_token '<bot token>'
 *   wp option update teinformez_tg_reader_bot 'TeInformezBot'   (username, no @)
 *   wp option update teinformez_tg_reader_webhook_secret "$(openssl rand -hex 24)"
 *   curl "https://api.telegram.org/bot<token>/setWebhook" \
 *     -d url=https://teinformez.eu/wp-json/teinformez/v1/telegram/webhook \
 *     -d secret_token=<the same webhook secret>
 * Until configured, the flow fails soft (mint -> 503; UI shows "indisponibil").
 */
class Telegram_Reader {

    private const OPT_TOKEN   = 'teinformez_tg_reader_token';
    private const OPT_BOT     = 'teinformez_tg_reader_bot';
    private const OPT_SECRET  = 'teinformez_tg_reader_webhook_secret';
    private const META_CHAT   = '_teinformez_telegram_chat_id';
    private const NONCE_TTL   = 15 * MINUTE_IN_SECONDS;
    private const API_TIMEOUT = 10;

    public static function is_configured(): bool {
        return get_option(self::OPT_TOKEN, '') !== '' && get_option(self::OPT_BOT, '') !== '';
    }

    public static function bot_username(): string {
        return (string) get_option(self::OPT_BOT, '');
    }

    public static function webhook_secret(): string {
        return (string) get_option(self::OPT_SECRET, '');
    }

    /** Chat id for a user, or '' when not linked. */
    public static function chat_id(int $user_id): string {
        return (string) get_user_meta($user_id, self::META_CHAT, true);
    }

    public static function unlink(int $user_id): void {
        delete_user_meta($user_id, self::META_CHAT);
    }

    /** Mint a single-use connect nonce and return the deep-link. */
    public static function mint_link(int $user_id): string {
        $nonce = wp_generate_password(24, false, false);
        set_transient('titg_link_' . $nonce, $user_id, self::NONCE_TTL);
        return 'https://t.me/' . self::bot_username() . '?start=' . $nonce;
    }

    /**
     * Handle a Telegram update. Fail-closed: only private chats, only
     * "/start <nonce>" with a live nonce bind anything; everything else gets
     * (at most) a polite pointer back to the site.
     */
    public static function handle_update(array $update): void {
        $msg = $update['message'] ?? null;
        if (!is_array($msg)) {
            return;
        }
        $chat = $msg['chat'] ?? [];
        if (($chat['type'] ?? '') !== 'private') {
            return; // never react in groups — this bot is a 1:1 delivery channel
        }
        $chat_id = (string) ($chat['id'] ?? '');
        $text = trim((string) ($msg['text'] ?? ''));
        if ($chat_id === '' || $text === '') {
            return;
        }

        if (preg_match('/^\/start\s+(\S+)$/', $text, $m)) {
            $nonce = $m[1];
            $user_id = (int) get_transient('titg_link_' . $nonce);
            if ($user_id > 0) {
                delete_transient('titg_link_' . $nonce); // single-use
                update_user_meta($user_id, self::META_CHAT, $chat_id);
                self::send_message($chat_id, "✅ Gata! Contul tău TeInformez e conectat.\n\nDe acum primești aici digestul cu știrile tale — activează canalul Telegram din Setări → Program livrare, dacă nu e deja bifat.");
                return;
            }
            self::send_message($chat_id, "Linkul de conectare a expirat sau a fost deja folosit. Generează unul nou din contul tău de pe teinformez.eu (Panou → Telegram).");
            return;
        }

        if (strpos($text, '/start') === 0) {
            self::send_message($chat_id, "Salut! Ca să primești știrile aici, conectează-ți contul din teinformez.eu → Panou → Telegram.");
        }
    }

    /** Plain sendMessage. Returns true on Telegram-confirmed delivery. */
    public static function send_message(string $chat_id, string $text, bool $html = false): bool {
        $token = (string) get_option(self::OPT_TOKEN, '');
        if ($token === '' || $chat_id === '') {
            return false;
        }
        $body = [
            'chat_id' => $chat_id,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($html) {
            $body['parse_mode'] = 'HTML';
        }
        $res = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
            'timeout' => self::API_TIMEOUT,
            'body' => $body,
        ]);
        if (is_wp_error($res)) {
            return false;
        }
        $json = json_decode((string) wp_remote_retrieve_body($res), true);
        return (bool) ($json['ok'] ?? false);
    }

    /**
     * Compact HTML digest for Telegram: greeting + up to 10 titled links.
     * $news items follow the delivery-handler shape (processed_title, id).
     */
    public static function build_digest(array $news, string $site_url): string {
        $lines = ["📰 <b>Digestul tău TeInformez</b>"];
        $count = 0;
        foreach ($news as $item) {
            if ($count >= 10) {
                break;
            }
            $item = is_object($item) ? (array) $item : $item; // wpdb rows are objects
            $title = html_entity_decode(wp_strip_all_tags((string) ($item['processed_title'] ?? $item['title'] ?? '')), ENT_QUOTES, 'UTF-8');
            $id = (int) ($item['id'] ?? 0);
            if ($title === '' || $id === 0) {
                continue;
            }
            $lines[] = '• <a href="' . esc_url($site_url . '/news/' . $id) . '">' . esc_html($title) . '</a>';
            $count++;
        }
        if (count($news) > $count) {
            $lines[] = '…și încă ' . (count($news) - $count) . ' pe <a href="' . esc_url($site_url) . '">teinformez.eu</a>';
        }
        return implode("\n", $lines);
    }
}
