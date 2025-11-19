<?php
/**
 * TRON Transaction Signing with secp256k1
 *
 * This file provides proper TRON transaction signing using the secp256k1 elliptic curve.
 * Requires: kornrunner/secp256k1-php
 *
 * Installation: composer require kornrunner/secp256k1-php
 */

use kornrunner\Secp256k1;
use kornrunner\Signature\Signature;

/**
 * Sign a TRON transaction using secp256k1
 *
 * @param array $transaction Unsigned transaction from TronGrid
 * @param string $privateKey Private key in hex format
 * @return array|null Signed transaction or null on failure
 */
function signTronTransaction($transaction, $privateKey) {
    try {
        // Check if secp256k1 library is available
        if (!class_exists('kornrunner\Secp256k1')) {
            error_log("secp256k1 library not found. Install with: composer require kornrunner/secp256k1-php");
            return null;
        }

        // Get raw transaction data
        if (!isset($transaction['raw_data_hex'])) {
            error_log("Transaction missing raw_data_hex");
            return null;
        }

        $rawDataHex = $transaction['raw_data_hex'];

        // Hash the raw data with SHA256
        $hash = hash('sha256', hex2bin($rawDataHex), true);

        // Create secp256k1 instance
        $secp256k1 = new Secp256k1();

        // Sign the hash
        $signature = $secp256k1->sign($hash, $privateKey);

        // Get signature in hex format
        $signatureHex = $signature->toHex();

        // Add signature to transaction
        $transaction['signature'] = [$signatureHex];

        return $transaction;

    } catch (Exception $e) {
        error_log("Exception in signTronTransaction: " . $e->getMessage());
        return null;
    }
}

/**
 * Get TRON address from private key using secp256k1
 *
 * @param string $privateKey Private key in hex
 * @return string|null TRON address or null on failure
 */
function getTronAddressFromPrivateKey($privateKey) {
    try {
        if (!class_exists('kornrunner\Secp256k1')) {
            return null;
        }

        $secp256k1 = new Secp256k1();

        // Get public key from private key
        $publicKey = $secp256k1->publicKeyFromPrivateKey($privateKey);

        // Get uncompressed public key
        $publicKeyHex = $publicKey->getHex();

        // Remove '04' prefix if present
        if (substr($publicKeyHex, 0, 2) === '04') {
            $publicKeyHex = substr($publicKeyHex, 2);
        }

        // Hash public key with Keccak-256
        $hash = hash('sha3-256', hex2bin($publicKeyHex), true);

        // Take last 20 bytes
        $addressBytes = substr($hash, -20);

        // Add TRON mainnet prefix (0x41)
        $addressWithPrefix = "\x41" . $addressBytes;

        // Calculate checksum (double SHA256)
        $checksum = hash('sha256', hash('sha256', $addressWithPrefix, true), true);

        // Add first 4 bytes of checksum
        $addressWithChecksum = $addressWithPrefix . substr($checksum, 0, 4);

        // Encode in Base58
        $address = base58_encode_tron($addressWithChecksum);

        return $address;

    } catch (Exception $e) {
        error_log("Exception in getTronAddressFromPrivateKey: " . $e->getMessage());
        return null;
    }
}

/**
 * Base58 encoding for TRON addresses
 *
 * @param string $data Binary data
 * @return string Base58 encoded string
 */
function base58_encode_tron($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    // Convert binary to decimal (big integer)
    $decimal = gmp_init(bin2hex($data), 16);

    $output = '';
    while (gmp_cmp($decimal, 0) > 0) {
        list($decimal, $remainder) = gmp_div_qr($decimal, $base);
        $output = $alphabet[gmp_intval($remainder)] . $output;
    }

    // Add leading '1's for each leading zero byte
    for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
        $output = '1' . $output;
    }

    return $output;
}

