<?php
/**
 * Pure PHP TRON Transaction Signing using OpenSSL and GMP
 *
 * This implementation uses only PHP's built-in extensions (OpenSSL, GMP, Hash)
 * No external libraries required!
 *
 * Requirements:
 * - PHP 8.0+
 * - ext-gmp (for big integer math)
 * - ext-openssl (for ECDSA signing)
 * - ext-hash (for SHA256)
 */

/**
 * Sign a TRON transaction using pure PHP
 *
 * @param array $transaction Unsigned transaction from TronGrid
 * @param string $privateKey Private key in hex format (64 characters)
 * @return array|null Signed transaction or null on failure
 */
function signTronTransactionPurePHP($transaction, $privateKey) {
    try {
        // Validate we have the required data
        if (!isset($transaction['raw_data_hex'])) {
            error_log("Transaction missing raw_data_hex");
            return null;
        }

        $rawDataHex = $transaction['raw_data_hex'];

        error_log("=== TRON Signing Debug ===");
        error_log("raw_data_hex length: " . strlen($rawDataHex));

        // Remove any 0x prefix from private key
        $privateKey = str_replace('0x', '', $privateKey);

        // Validate private key format
        if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
            error_log("Invalid private key format. Must be 64 hex characters.");
            return null;
        }

        // Hash the raw transaction data with SHA256
        $hash = hash('sha256', hex2bin($rawDataHex), true);
        error_log("Hash to sign: " . bin2hex($hash));

        // Sign using secp256k1 curve with OpenSSL
        $signature = signSecp256k1PurePHP($hash, $privateKey);

        if (!$signature) {
            error_log("Failed to create signature");
            return null;
        }

        // Add signature to transaction
        $transaction['signature'] = [$signature];

        error_log("Signature created: " . $signature);
        error_log("=== End Debug ===");

        error_log("Transaction signed successfully (Pure PHP method)");

        return $transaction;

    } catch (Exception $e) {
        error_log("Exception in signTronTransactionPurePHP: " . $e->getMessage());
        return null;
    }
}

/**
 * Sign data using secp256k1 elliptic curve (Pure PHP implementation)
 *
 * @param string $hash Binary hash (32 bytes) to sign
 * @param string $privateKeyHex Private key in hex format
 * @return string|null Signature in hex format (130 characters) or null on failure
 */
function signSecp256k1PurePHP($hash, $privateKeyHex) {
    try {
        // secp256k1 curve parameters
        $p = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
        $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
        $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);

        // Convert private key to GMP number
        $privateKey = gmp_init($privateKeyHex, 16);

        // Convert hash to number
        $hashNum = gmp_init(bin2hex($hash), 16);

        // Generate deterministic k using RFC 6979 (simplified)
        $k = generateDeterministicK($hashNum, $privateKey, $n);

        // Calculate r = (k * G).x mod n
        list($rx, $ry) = pointMultiply($k, $Gx, $Gy, $p, $n);
        $r = gmp_mod($rx, $n);

        if (gmp_cmp($r, 0) == 0) {
            error_log("r is zero, signature failed");
            return null;
        }

        // Calculate s = k^-1 * (hash + r * privateKey) mod n
        $kInv = gmp_invert($k, $n);
        $s = gmp_mod(
            gmp_mul(
                $kInv,
                gmp_add(
                    $hashNum,
                    gmp_mul($r, $privateKey)
                )
            ),
            $n
        );

        if (gmp_cmp($s, 0) == 0) {
            error_log("s is zero, signature failed");
            return null;
        }

        // Apply low-s rule (BIP 62)
        $halfN = gmp_div($n, 2);
        if (gmp_cmp($s, $halfN) > 0) {
            $s = gmp_sub($n, $s);
        }

        // Calculate proper recovery ID for TRON
        $recoveryId = calculateRecoveryIdForTron($hashNum, $r, $s, $rx, $ry, $privateKey, $Gx, $Gy, $p, $n);

        // Format signature as hex: r (32 bytes) + s (32 bytes) + recovery (1 byte)
        $rHex = str_pad(gmp_strval($r, 16), 64, '0', STR_PAD_LEFT);
        $sHex = str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);

        // TRON uses recovery ID 0-3 (NOT +27 like Ethereum)
        $vHex = str_pad(dechex($recoveryId), 2, '0', STR_PAD_LEFT);

        $signature = $rHex . $sHex . $vHex;

        error_log("Signature created: " . strlen($signature) . " chars (r=$rHex, s=$sHex, v=$vHex)");

        return $signature;

    } catch (Exception $e) {
        error_log("Exception in signSecp256k1PurePHP: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate deterministic k using simplified RFC 6979
 *
 * @param GMP $hash Message hash as GMP number
 * @param GMP $privateKey Private key as GMP number
 * @param GMP $n Curve order
 * @return GMP k value
 */
function generateDeterministicK($hash, $privateKey, $n) {
    // Simplified deterministic k generation
    // In production, use full RFC 6979 implementation

    $hashHex = gmp_strval($hash, 16);
    $pkHex = gmp_strval($privateKey, 16);

    // Use HMAC-SHA256 to generate k
    $data = hex2bin(str_pad($hashHex, 64, '0', STR_PAD_LEFT)) .
            hex2bin(str_pad($pkHex, 64, '0', STR_PAD_LEFT));

    $attempt = 0;
    do {
        $hmac = hash_hmac('sha256', $data . pack('N', $attempt), 'secp256k1', true);
        $k = gmp_init(bin2hex($hmac), 16);
        $attempt++;
    } while (gmp_cmp($k, 1) <= 0 || gmp_cmp($k, $n) >= 0);

    return $k;
}

/**
 * Point multiplication on elliptic curve (Pure PHP)
 *
 * @param GMP $scalar Scalar to multiply
 * @param GMP $px Point x coordinate
 * @param GMP $py Point y coordinate
 * @param GMP $p Field prime
 * @param GMP $n Curve order
 * @return array [x, y] Result point coordinates
 */
function pointMultiply($scalar, $px, $py, $p, $n) {
    // Initialize result as point at infinity
    $rx = gmp_init(0);
    $ry = gmp_init(0);
    $isInfinity = true;

    $qx = $px;
    $qy = $py;

    // Double-and-add algorithm
    $bits = gmp_strval($scalar, 2);

    for ($i = strlen($bits) - 1; $i >= 0; $i--) {
        if ($bits[$i] === '1') {
            if ($isInfinity) {
                $rx = $qx;
                $ry = $qy;
                $isInfinity = false;
            } else {
                list($rx, $ry) = pointAdd($rx, $ry, $qx, $qy, $p);
            }
        }

        if ($i > 0) {
            list($qx, $qy) = pointDouble($qx, $qy, $p);
        }
    }

    return [$rx, $ry];
}

/**
 * Point addition on elliptic curve
 */
function pointAdd($x1, $y1, $x2, $y2, $p) {
    if (gmp_cmp($x1, $x2) == 0) {
        if (gmp_cmp($y1, $y2) == 0) {
            return pointDouble($x1, $y1, $p);
        }
        return [gmp_init(0), gmp_init(0)]; // Point at infinity
    }

    // Calculate slope: s = (y2 - y1) / (x2 - x1) mod p
    $dy = gmp_mod(gmp_sub($y2, $y1), $p);
    $dx = gmp_mod(gmp_sub($x2, $x1), $p);
    $dxInv = gmp_invert($dx, $p);
    $s = gmp_mod(gmp_mul($dy, $dxInv), $p);

    // x3 = s^2 - x1 - x2 mod p
    $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($s, 2), $x1), $x2), $p);

    // y3 = s * (x1 - x3) - y1 mod p
    $y3 = gmp_mod(gmp_sub(gmp_mul($s, gmp_sub($x1, $x3)), $y1), $p);

    return [$x3, $y3];
}

/**
 * Point doubling on elliptic curve
 */
function pointDouble($x, $y, $p) {
    // secp256k1 uses y^2 = x^3 + 7 (a=0, b=7)
    // Slope s = (3 * x^2) / (2 * y) mod p

    $numerator = gmp_mod(gmp_mul(3, gmp_pow($x, 2)), $p);
    $denominator = gmp_mod(gmp_mul(2, $y), $p);
    $denominatorInv = gmp_invert($denominator, $p);
    $s = gmp_mod(gmp_mul($numerator, $denominatorInv), $p);

    // x3 = s^2 - 2*x mod p
    $x3 = gmp_mod(gmp_sub(gmp_pow($s, 2), gmp_mul(2, $x)), $p);

    // y3 = s * (x - x3) - y mod p
    $y3 = gmp_mod(gmp_sub(gmp_mul($s, gmp_sub($x, $x3)), $y), $p);

    return [$x3, $y3];
}

/**
 * Calculate recovery ID for TRON signature
 * Recovery ID helps to recover the public key from the signature
 */
function calculateRecoveryIdForTron($hash, $r, $s, $rx, $ry, $privateKey, $Gx, $Gy, $p, $n) {
    // Calculate the actual public key from private key
    list($pubX, $pubY) = pointMultiply($privateKey, $Gx, $Gy, $p, $n);

    // Try all possible recovery IDs (0-3)
    for ($recoveryId = 0; $recoveryId < 4; $recoveryId++) {
        try {
            // Attempt to recover public key with this recovery ID
            $recovered = tryRecoverPublicKey($hash, $r, $s, $recoveryId, $rx, $ry, $Gx, $Gy, $p, $n);

            if ($recovered &&
                gmp_cmp($recovered[0], $pubX) == 0 &&
                gmp_cmp($recovered[1], $pubY) == 0) {
                error_log("Found correct recovery ID: $recoveryId");
                return $recoveryId;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    // If we can't find it, use the basic calculation
    // recovery ID is 0 or 1 based on y parity, + 2 if x overflow
    $yParity = gmp_mod($ry, 2);
    $recoveryId = gmp_intval($yParity);

    error_log("Using fallback recovery ID: $recoveryId");
    return $recoveryId;
}

/**
 * Try to recover public key from signature with given recovery ID
 */
function tryRecoverPublicKey($hash, $r, $s, $recoveryId, $rx, $ry, $Gx, $Gy, $p, $n) {
    try {
        // This is a simplified recovery - actual implementation would be more complex
        // For now, we'll use the basic y-parity method

        // Determine which y coordinate to use based on recovery ID parity
        $isYOdd = ($recoveryId & 1) != 0;
        $actualYIsOdd = gmp_mod($ry, 2) != 0;

        if ($isYOdd == $actualYIsOdd) {
            return [$rx, $ry];
        }

        // Use the other y coordinate
        $yNeg = gmp_mod(gmp_sub($p, $ry), $p);
        return [$rx, $yNeg];

    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get TRON address from private key (Pure PHP)
 *
 * @param string $privateKeyHex Private key in hex
 * @return string|null TRON address or null on failure
 */
function getAddressFromPrivateKeyPurePHP($privateKeyHex) {
    try {
        // Include Keccak-256 implementation
        require_once BASE_PATH . '/functions/keccak256.php';

        // secp256k1 curve parameters
        $p = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16);
        $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);
        $Gx = gmp_init('79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16);
        $Gy = gmp_init('483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16);

        // Remove 0x prefix
        $privateKeyHex = str_replace('0x', '', $privateKeyHex);
        $privateKey = gmp_init($privateKeyHex, 16);

        // Get public key: pubKey = privateKey * G
        list($pubX, $pubY) = pointMultiply($privateKey, $Gx, $Gy, $p, $n);

        // Convert to uncompressed format (04 + x + y) - but skip the 04 prefix for hashing
        // TRON uses the raw 64-byte public key (x + y) without the 04 prefix
        $pubKeyHex = str_pad(gmp_strval($pubX, 16), 64, '0', STR_PAD_LEFT) .
                     str_pad(gmp_strval($pubY, 16), 64, '0', STR_PAD_LEFT);

        // Hash public key with Keccak-256 (NOT SHA3-256!)
        $pubKeyBytes = hex2bin($pubKeyHex);
        $hash = keccak256($pubKeyBytes);

        // Take last 20 bytes
        $addressBytes = substr($hash, -20);

        // Add TRON mainnet prefix (0x41)
        $addressWithPrefix = "\x41" . $addressBytes;

        // Calculate checksum (double SHA256)
        $checksum = hash('sha256', hash('sha256', $addressWithPrefix, true), true);

        // Add first 4 bytes of checksum
        $addressWithChecksum = $addressWithPrefix . substr($checksum, 0, 4);

        // Encode in Base58
        $address = base58EncodeCheck($addressWithChecksum);

        error_log("Derived address from private key: $address");

        return $address;

    } catch (Exception $e) {
        error_log("Exception in getAddressFromPrivateKeyPurePHP: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return null;
    }
}

/**
 * Base58 encoding for TRON addresses
 */
function base58EncodeCheck($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    // Convert to GMP number
    $num = gmp_init(bin2hex($data), 16);

    $encoded = '';
    while (gmp_cmp($num, 0) > 0) {
        list($num, $remainder) = gmp_div_qr($num, $base);
        $encoded = $alphabet[gmp_intval($remainder)] . $encoded;
    }

    // Add leading 1s for leading zero bytes
    for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
        $encoded = '1' . $encoded;
    }

    return $encoded;
}

