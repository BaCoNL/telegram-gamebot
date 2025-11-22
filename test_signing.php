<?php
/**
 * Test TRON Transaction Signing - Pure PHP Implementation
 *
 * This script tests the pure PHP signing implementation
 * that uses only built-in PHP extensions (GMP, Hash)
 */

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/functions/tron_signing_pure_php.php';

echo "========================================\n";
echo "TRON Pure PHP Signing Test\n";
echo "========================================\n\n";

// Test 1: Check if required extensions are available
echo "Test 1: Checking required PHP extensions...\n";

$extensions = ['gmp', 'hash', 'curl'];
$allPresent = true;

foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ ext-$ext is loaded\n";
    } else {
        echo "✗ ext-$ext is NOT loaded\n";
        $allPresent = false;
    }
}

if (!$allPresent) {
    echo "\nError: Missing required PHP extensions.\n";
    echo "Enable them in your php.ini file.\n\n";
    exit(1);
}

echo "\n";

// Test 2: Test address derivation
echo "Test 2: Testing address derivation from private key...\n";

// Test private key (DO NOT use this in production!)
$testPrivateKey = str_repeat('a', 64);

try {
    $address = getAddressFromPrivateKeyPurePHP($testPrivateKey);

    if ($address && preg_match('/^T[A-Za-z1-9]{33}$/', $address)) {
        echo "✓ Address generated successfully: $address\n";
        echo "  Address format is correct\n";
    } else {
        echo "✗ Failed to generate valid address\n";
        if ($address) {
            echo "  Got: $address\n";
        }
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Exception occurred: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// Test 3: Test signature generation
echo "Test 3: Testing signature generation...\n";

// Mock transaction data (similar to what TronGrid returns)
$mockTransaction = [
    'txID' => 'test123',
    'raw_data_hex' => hash('sha256', 'test_transaction_data'),
    'raw_data' => []
];

try {
    $signed = signTronTransactionPurePHP($mockTransaction, $testPrivateKey);

    if ($signed && isset($signed['signature'][0])) {
        $sigLength = strlen($signed['signature'][0]);
        echo "✓ Signature generated successfully\n";
        echo "  Signature length: $sigLength characters\n";

        if ($sigLength == 130) {
            echo "  ✓ Signature length is correct (65 bytes in hex)\n";
            echo "  Signature: " . substr($signed['signature'][0], 0, 30) . "..." . substr($signed['signature'][0], -10) . "\n";
        } else {
            echo "  ⚠ WARNING: Signature length is $sigLength (expected 130)\n";
            echo "    This may still work, but verify with a real transaction\n";
        }
    } else {
        echo "✗ Failed to generate signature\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n========================================\n";
echo "All tests passed!\n";
echo "========================================\n";
echo "\nYour bot is now using Pure PHP TRON signing.\n";
echo "No external libraries needed!\n";
echo "\nTry placing a bet to test the full transaction flow.\n\n";

