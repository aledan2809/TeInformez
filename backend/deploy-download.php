<?php
/**
 * TeInformez Deploy Script (Download method)
 * Descarcă fișierele direct de pe GitHub fără a folosi git
 */

$deploy_key = 'teinformez_deploy_2024_secret';

if (!isset($_GET['key']) || $_GET['key'] !== $deploy_key) {
    http_response_code(403);
    die('Unauthorized');
}

set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

echo "=== TeInformez Deploy (Download Method) ===\n\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// GitHub raw URL base
$github_raw = 'https://raw.githubusercontent.com/aledan2809/TeInformez/master/backend/';
$base_dir = __DIR__;

// Files to download/update
$files = [
    'wp-content/plugins/teinformez-core/teinformez-core.php',
    'wp-content/plugins/teinformez-core/includes/class-config.php',
    'wp-content/plugins/teinformez-core/includes/class-email-sender.php',
    'wp-content/plugins/teinformez-core/includes/class-news-source-manager.php',
    'wp-content/plugins/teinformez-core/includes/class-news-fetcher.php',
    'wp-content/plugins/teinformez-core/includes/class-ai-processor.php',
    'wp-content/plugins/teinformez-core/includes/class-news-publisher.php',
    'wp-content/plugins/teinformez-core/admin/views/news-queue.php',
    'wp-content/plugins/teinformez-core/admin/class-admin.php',
    'wp-content/plugins/teinformez-core/admin/views/settings-page.php',
    'wp-content/plugins/teinformez-core/api/class-auth-api.php',
    'wp-content/plugins/teinformez-core/api/class-user-api.php',
    'webhook.php',
];

$success = 0;
$failed = 0;

foreach ($files as $file) {
    $url = $github_raw . $file;
    $local_path = $base_dir . '/' . $file;

    echo "Downloading: $file\n";

    // Create directory if needed
    $dir = dirname($local_path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo "  ERROR: Cannot create directory: $dir\n";
            $failed++;
            continue;
        }
    }

    // Download file
    $content = @file_get_contents($url);

    if ($content === false) {
        // Try with curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TeInformez-Deploy/1.0');
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || empty($content)) {
            echo "  ERROR: Failed to download (HTTP $http_code)\n";
            $failed++;
            continue;
        }
    }

    // Check if it's a 404 page
    if (strpos($content, '404: Not Found') !== false) {
        echo "  SKIP: File not found on GitHub\n";
        continue;
    }

    // Save file
    if (file_put_contents($local_path, $content) === false) {
        echo "  ERROR: Cannot write file\n";
        $failed++;
        continue;
    }

    echo "  OK (" . strlen($content) . " bytes)\n";
    $success++;
}

echo "\n=== Summary ===\n";
echo "Success: $success files\n";
echo "Failed: $failed files\n";

if ($failed === 0) {
    echo "\n✓ Deploy complete!\n";
} else {
    echo "\n⚠ Deploy completed with errors.\n";
}

echo "\nREMINDER: Delete deploy-download.php after use!\n";
