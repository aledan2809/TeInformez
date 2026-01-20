<?php
/**
 * PHP Version Test File
 * Upload this to your WordPress root directory
 * Then access it via: https://teinformez.eu/phpinfo-test.php
 */

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Version ID: " . PHP_VERSION_ID . "\n";

if (PHP_VERSION_ID >= 80000) {
    echo "\n✅ PHP 8.0+ detected - Plugin will work!\n";
} else {
    echo "\n❌ PHP version too old - Need 8.0+\n";
}

// Uncomment the line below if you want full PHP info
// phpinfo();
