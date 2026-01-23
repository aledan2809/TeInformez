<?php
/**
 * TeInformez Deploy Script
 *
 * Upload acest fișier în public_html/ și accesează-l din browser:
 * https://teinformez.eu/deploy.php?key=YOUR_SECRET_KEY
 *
 * IMPORTANT: Șterge acest fișier după ce ai terminat deploy-ul!
 */

// Security key - schimbă această valoare!
$deploy_key = 'teinformez_deploy_2024_secret';

// Check authorization
if (!isset($_GET['key']) || $_GET['key'] !== $deploy_key) {
    http_response_code(403);
    die('Unauthorized. Add ?key=YOUR_SECRET_KEY to URL');
}

// Set execution time limit
set_time_limit(120);

// Output as text
header('Content-Type: text/plain; charset=utf-8');

echo "=== TeInformez Deploy Script ===\n\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . php_uname() . "\n\n";

// Change to the plugin directory
$plugin_dir = __DIR__ . '/wp-content/plugins/teinformez-core';

if (!is_dir($plugin_dir)) {
    // Try alternative path
    $plugin_dir = dirname(__DIR__) . '/public_html/wp-content/plugins/teinformez-core';
}

if (!is_dir($plugin_dir)) {
    die("ERROR: Plugin directory not found!\nTried: " . $plugin_dir);
}

echo "Plugin directory: $plugin_dir\n\n";

// Check if git is available
$git_version = shell_exec('git --version 2>&1');
echo "Git version: $git_version\n";

if (strpos($git_version, 'git version') === false) {
    die("ERROR: Git is not available on this server.");
}

// Change to plugin directory and run git pull
chdir($plugin_dir);
echo "Current directory: " . getcwd() . "\n\n";

// Git status before
echo "=== Git Status (Before) ===\n";
echo shell_exec('git status 2>&1');
echo "\n";

// Git fetch
echo "=== Git Fetch ===\n";
echo shell_exec('git fetch origin 2>&1');
echo "\n";

// Git pull
echo "=== Git Pull ===\n";
$pull_output = shell_exec('git pull origin master 2>&1');
echo $pull_output;
echo "\n";

// Git status after
echo "=== Git Status (After) ===\n";
echo shell_exec('git status 2>&1');
echo "\n";

// Check for errors
if (strpos($pull_output, 'Already up to date') !== false) {
    echo "\n✓ Already up to date - no changes needed.\n";
} elseif (strpos($pull_output, 'Updating') !== false || strpos($pull_output, 'Fast-forward') !== false) {
    echo "\n✓ SUCCESS! Code updated.\n";
} elseif (strpos($pull_output, 'error') !== false || strpos($pull_output, 'fatal') !== false) {
    echo "\n✗ ERROR during git pull. Check output above.\n";
} else {
    echo "\n? Unknown result. Check output above.\n";
}

echo "\n=== Deploy Complete ===\n";
echo "SECURITY REMINDER: Delete this deploy.php file after use!\n";
