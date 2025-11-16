<?php
/**
 * Test wallet creation
 */

// Define BASE_PATH
define('BASE_PATH', __DIR__);

// Load bootstrap (includes RedBean and config)
require_once BASE_PATH . '/bootstrap.php';

echo "Testing TRON Wallet Creation...\n";
echo "================================\n\n";

// Check if config is loaded
echo "Config Check:\n";
echo "API URL: " . TRONGRID_CONFIG['api_url'] . "\n";
echo "API Key: " . substr(TRONGRID_CONFIG['api_key'], 0, 10) . "...\n\n";

// Test wallet creation
echo "Creating wallet...\n";
echo "Step 1: Generating random private key...\n";

try {
    $privateKeyBytes = random_bytes(32);
    $privateKey = bin2hex($privateKeyBytes);
    echo "Private key generated: " . substr($privateKey, 0, 10) . "...\n";

    echo "Step 2: Getting address from TronGrid API...\n";
    $address = getAddressFromPrivateKeyAPI($privateKey);

    if ($address) {
        echo "Address generated: " . $address . "\n";
        echo "✅ Wallet created successfully!\n";
        echo "Full Private Key: " . $privateKey . "\n";
    } else {
        echo "❌ Failed to get address from TronGrid API\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\nUsing createTronWallet() function...\n";
$walletData = createTronWallet();

if ($walletData) {
    echo "✅ Wallet created successfully!\n";
    echo "Address: " . $walletData['address'] . "\n";
    echo "Private Key: " . $walletData['privateKey'] . "\n";
} else {
    echo "❌ Wallet creation failed!\n";
    echo "Check the error log for details.\n";
}

echo "\n================================\n";

