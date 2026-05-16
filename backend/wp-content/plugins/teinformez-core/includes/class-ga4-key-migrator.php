<?php
namespace TeInformez;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * I-05: One-shot GA4 service-account private-key migration runner.
 *
 * Reads the key from wp_options.teinformez_ga4_private_key, validates PEM
 * format, writes it to the filesystem path declared by
 * TEINFORMEZ_GA4_PRIVATE_KEY_PATH with mode 0640. Same logic is invoked from
 * the WP-CLI command and from the admin "Migrate to filesystem" button.
 *
 * Deliberately DOES NOT delete the DB row — operators verify the dashboard
 * still pulls live metrics first, then run `wp option delete
 * teinformez_ga4_private_key`.
 */
class GA4_Key_Migrator {

    public const REQUIRED_CONSTANT = 'TEINFORMEZ_GA4_PRIVATE_KEY_PATH';

    /**
     * Run migration. Always returns a structured report instead of throwing —
     * lets the admin button and CLI command share the same surface.
     *
     * @return array{ok:bool, message:string, details:string[], path?:string, bytes?:int, source?:string}
     */
    public static function run(): array {
        $details = [];

        // 1. wp-config must define the target path. We refuse to invent one on
        // the fly so operators can't accidentally write the PEM into a
        // web-served directory.
        if (!defined(self::REQUIRED_CONSTANT)) {
            return self::fail(
                __('TEINFORMEZ_GA4_PRIVATE_KEY_PATH is not defined in wp-config.php. Add the constant before running the migration.', 'teinformez'),
                $details
            );
        }
        $path = (string) constant(self::REQUIRED_CONSTANT);
        if ($path === '') {
            return self::fail(
                __('TEINFORMEZ_GA4_PRIVATE_KEY_PATH is empty. Configure the absolute filesystem path before running the migration.', 'teinformez'),
                $details
            );
        }

        // 2. Pull the current DB key (try modern alias first, then legacy).
        $db_key = trim((string) Config::get('ga4_private_key', ''));
        $source = 'database';
        if ($db_key === '') {
            $db_key = trim((string) Config::get('google_private_key', ''));
            if ($db_key !== '') {
                $source = 'database-legacy-alias';
            }
        }
        if ($db_key === '') {
            $json_raw = trim((string) Config::get('ga4_service_account_json', ''));
            if ($json_raw === '') {
                $json_raw = trim((string) Config::get('google_service_account_json', ''));
            }
            if ($json_raw !== '') {
                $decoded = json_decode($json_raw, true);
                if (is_array($decoded) && !empty($decoded['private_key'])) {
                    $db_key = trim((string) $decoded['private_key']);
                    $source = 'database-json';
                }
            }
        }
        if ($db_key === '') {
            return self::fail(
                __('No GA4 private key found in the database — nothing to migrate.', 'teinformez'),
                $details
            );
        }

        // 3. Normalize escaped newlines and validate PEM envelope.
        $pem = str_replace('\\n', "\n", $db_key);
        if (!preg_match('/^-----BEGIN PRIVATE KEY-----[A-Za-z0-9+\/=\s]+-----END PRIVATE KEY-----\s*$/', $pem)) {
            return self::fail(
                __('Stored key is not in PEM format (missing BEGIN/END markers). Refusing to migrate a malformed value.', 'teinformez'),
                $details
            );
        }
        $details[] = sprintf(__('Source: %s', 'teinformez'), $source);
        $details[] = sprintf(__('Key size: %d bytes', 'teinformez'), strlen($pem));

        // 4. Ensure the target directory exists with safe perms. We refuse to
        // create the dir if it would land under the WP document root.
        $dir = dirname($path);
        if (self::path_is_inside_webroot($dir)) {
            return self::fail(
                sprintf(
                    /* translators: %s = directory that would land under the WP webroot. */
                    __('Refusing to write to %s — that directory is inside the WordPress webroot and would be web-served. Choose a path outside it.', 'teinformez'),
                    $dir
                ),
                $details
            );
        }
        if (!is_dir($dir)) {
            $details[] = sprintf(__('Creating directory %s (mode 0750)', 'teinformez'), $dir);
            if (!@mkdir($dir, 0750, true) && !is_dir($dir)) {
                return self::fail(
                    sprintf(__('Failed to create directory %s. Create it manually with chmod 750 root:www-data.', 'teinformez'), $dir),
                    $details
                );
            }
            @chmod($dir, 0750);
        }
        if (!is_writable($dir)) {
            return self::fail(
                sprintf(__('Directory %s is not writable by the PHP process. Fix permissions and rerun.', 'teinformez'), $dir),
                $details
            );
        }

        // 5. Write the PEM atomically (tmp file + rename) and lock perms.
        $tmp = $path . '.tmp.' . wp_generate_password(8, false, false);
        $bytes = @file_put_contents($tmp, $pem);
        if ($bytes === false || $bytes <= 0) {
            @unlink($tmp);
            return self::fail(__('Failed to write the PEM file.', 'teinformez'), $details);
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return self::fail(__('Failed to rename the temporary PEM file into place.', 'teinformez'), $details);
        }
        @chmod($path, 0640);

        $details[] = sprintf(__('Wrote %1$d bytes to %2$s (mode 0640)', 'teinformez'), $bytes, $path);

        // 6. Re-resolve via the live service to confirm the new path wins.
        $resolved_source = Google_Analytics_Service::get_private_key_source();
        $details[] = sprintf(__('Service now resolves key from: %s', 'teinformez'), $resolved_source);
        if ($resolved_source !== 'filesystem') {
            return [
                'ok' => false,
                'message' => __('PEM written but the service still loads from the database — check that TEINFORMEZ_GA4_PRIVATE_KEY_PATH points to the new file and that file_get_contents() succeeds.', 'teinformez'),
                'details' => $details,
                'path' => $path,
                'bytes' => (int) $bytes,
                'source' => $resolved_source,
            ];
        }

        return [
            'ok' => true,
            'message' => __('GA4 private key migrated to filesystem. Verify the dashboard, then DELETE the wp_options row.', 'teinformez'),
            'details' => $details,
            'path' => $path,
            'bytes' => (int) $bytes,
            'source' => $resolved_source,
        ];
    }

    private static function fail(string $message, array $details): array {
        return [
            'ok' => false,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * Returns true when the target directory sits under ABSPATH (i.e. would
     * be served by the webserver). Defends against a misconfigured constant
     * that points at e.g. /var/www/teinformez/wp-content/ga4-key.pem.
     */
    private static function path_is_inside_webroot(string $dir): bool {
        if (!defined('ABSPATH')) {
            return false;
        }
        $abs = realpath(ABSPATH) ?: rtrim((string) ABSPATH, '/\\');
        $target = realpath($dir);
        if ($target === false) {
            // dir doesn't exist yet — compare against the supplied path string.
            $target = rtrim($dir, '/\\');
        }
        if ($abs === '' || $target === '') {
            return false;
        }
        return strpos($target . DIRECTORY_SEPARATOR, $abs . DIRECTORY_SEPARATOR) === 0;
    }
}
