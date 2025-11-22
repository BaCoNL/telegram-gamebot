<?php
/**
 * Diagnostic: Check if wallet addresses match their private keys
 */

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/functions/tron_wallet.php';
require_once BASE_PATH . '/functions/tron_signing_pure_php.php';
require_once BASE_PATH . '/functions/keccak256.php';

echo "========================================\n";
echo "Wallet Address Verification\n";
echo "========================================\n\n";

// Get all wallets from database
try {
    $wallets = R::findAll('wallet');

    if (empty($wallets)) {
        echo "No wallets found in database.\n";
        exit(0);
    }

    echo "Found " . count($wallets) . " wallet(s) in database.\n\n";

    foreach ($wallets as $wallet) {
        echo "Checking wallet ID: " . $wallet->id . "\n";
        echo "  Stored address: " . $wallet->address . "\n";

        // Decrypt private key
        $privateKey = decryptPrivateKey($wallet->private_key);

        if (!$privateKey) {
            echo "  ❌ ERROR: Could not decrypt private key\n\n";
            continue;
        }

        echo "  Private key length: " . strlen($privateKey) . " chars\n";

        // Derive address from private key
        $derivedAddress = getAddressFromPrivateKeyPurePHP($privateKey);

        if (!$derivedAddress) {
            echo "  ❌ ERROR: Could not derive address from private key\n\n";
            continue;
        }

        echo "  Derived address: " . $derivedAddress . "\n";

        // Compare addresses
        if ($wallet->address === $derivedAddress) {
            echo "  ✅ MATCH: Addresses match!\n";
        } else {
            echo "  ❌ MISMATCH: Addresses DO NOT match!\n";
            echo "  ⚠️  This will cause signature verification errors!\n";
            echo "  \n";
            echo "  SOLUTION: Update database with correct address:\n";
            echo "  UPDATE wallet SET address = '$derivedAddress' WHERE id = " . $wallet->id . ";\n";
        }

        echo "\n";
    }

    echo "========================================\n";
    echo "Verification complete.\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

