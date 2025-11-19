<?php
/**
 * TRON Wallet Helper Functions
 *
 * Functions for creating, importing, and managing TRON wallets
 */

/**
 * Create a new TRON wallet
 *
 * @return array|null Array with 'address' and 'privateKey' or null on failure
 */
function createTronWallet() {
    try {
        // Generate random private key (32 bytes = 64 hex characters)
        $privateKeyBytes = random_bytes(32);
        $privateKey = bin2hex($privateKeyBytes);

        // Derive address from private key locally
        $address = deriveAddressFromPrivateKey($privateKey);

        if (!$address) {
            error_log("TRON Wallet Creation Error: Could not generate address from private key");
            return null;
        }

        return [
            'address' => $address,
            'privateKey' => $privateKey
        ];
    } catch (Exception $e) {
        error_log("Exception in createTronWallet: " . $e->getMessage());
        return null;
    }
}

/**
 * Derive TRON address from private key
 *
 * IMPORTANT: This is a placeholder implementation.
 * For production, you MUST use a proper secp256k1 library.
 *
 * Current implementation retrieves address from wallet table if it exists,
 * or generates a test address for development.
 *
 * @param string $privateKey Hex-encoded private key
 * @return string|null Address or null on failure
 */
function deriveAddressFromPrivateKey($privateKey) {
    try {
        // Validate private key format
        if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
            error_log("Invalid private key format");
            return null;
        }

        // Try to find existing wallet with this private key in database
        // This is a workaround since we don't have proper crypto libraries
        try {
            $encryptedKey = encryptPrivateKey($privateKey);
            $wallet = R::findOne('wallet', 'private_key = ?', [$encryptedKey]);
            if ($wallet && $wallet->address) {
                return $wallet->address;
            }
        } catch (Exception $e) {
            // Database not available, continue with alternative method
        }

        // For HOUSE wallet, check config
        if (defined('HOUSE_WALLET_PRIVATE_KEY') && $privateKey === HOUSE_WALLET_PRIVATE_KEY) {
            if (defined('HOUSE_WALLET_ADDRESS')) {
                return HOUSE_WALLET_ADDRESS;
            }
        }

        // If we can't derive it properly, we need to return an error
        error_log("Cannot derive TRON address without proper secp256k1 library. Private key not in database.");
        return null;

    } catch (Exception $e) {
        error_log("Exception in deriveAddressFromPrivateKey: " . $e->getMessage());
        return null;
    }
}

/**
 * Encode data in Base58 (without GMP extension)
 *
 * @param string $data Binary data
 * @return string Base58 encoded string
 */
function base58_encode($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    // Convert to byte array
    $bytes = str_split($data);
    $decimal = array_fill(0, 1, 0);

    // Convert bytes to decimal
    foreach ($bytes as $byte) {
        $carry = ord($byte);
        for ($i = 0; $i < count($decimal); $i++) {
            $carry += $decimal[$i] * 256;
            $decimal[$i] = $carry % 58;
            $carry = (int)($carry / 58);
        }
        while ($carry > 0) {
            $decimal[] = $carry % 58;
            $carry = (int)($carry / 58);
        }
    }

    // Convert to base58
    $encoded = '';
    foreach ($decimal as $digit) {
        $encoded = $alphabet[$digit] . $encoded;
    }

    // Add leading '1's for each leading zero byte
    for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
        $encoded = '1' . $encoded;
    }

    return $encoded;
}

/**
 * Decode Base58 string (without GMP extension)
 *
 * @param string $encoded Base58 encoded string
 * @return string|false Binary data or false on error
 */
function base58_decode($encoded) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $length = strlen($encoded);
    $decimal = array_fill(0, 1, 0);

    // Decode base58 to decimal
    for ($i = 0; $i < $length; $i++) {
        $digit = strpos($alphabet, $encoded[$i]);
        if ($digit === false) {
            return false;
        }
        $carry = $digit;
        for ($j = 0; $j < count($decimal); $j++) {
            $carry += $decimal[$j] * 58;
            $decimal[$j] = $carry % 256;
            $carry = (int)($carry / 256);
        }
        while ($carry > 0) {
            $decimal[] = $carry % 256;
            $carry = (int)($carry / 256);
        }
    }

    // Convert to binary
    $binary = '';
    foreach (array_reverse($decimal) as $byte) {
        $binary .= chr($byte);
    }

    // Add leading zero bytes
    for ($i = 0; $i < $length && $encoded[$i] === '1'; $i++) {
        $binary = "\x00" . $binary;
    }

    return $binary;
}

/**
 * Get TRON address from private key using TronGrid API
 * (Deprecated - not supported by TronGrid)
 *
 * @param string $privateKey Hex-encoded private key
 * @return string|null Address or null on failure
 */
function getAddressFromPrivateKeyAPI($privateKey) {
    // This endpoint doesn't exist in TronGrid API
    // Use deriveAddressFromPrivateKey() instead
    return deriveAddressFromPrivateKey($privateKey);
}

/**
 * Get TRON address from private key
 *
 * @param string $privateKey
 * @return string|null Address or null if invalid
 */
function getAddressFromPrivateKey($privateKey) {
    // Use the improved deriveAddressFromPrivateKey function
    return deriveAddressFromPrivateKey($privateKey);
}

/**
 * Validate TRON private key and return address
 *
 * @param string $privateKey
 * @return string|null
 */
function validateTronPrivateKey($privateKey) {
    // Basic hex validation
    if (!ctype_xdigit($privateKey) || strlen($privateKey) !== 64) {
        return null;
    }

    // For production, implement proper key-to-address derivation
    // This is a placeholder that validates the format
    return 'T' . substr(hash('sha256', $privateKey), 0, 33); // Simplified placeholder
}

/**
 * Get TRX balance for an address
 *
 * @param string $address
 * @return float
 */
function getTrxBalance($address) {
    try {
        $config = TRONGRID_CONFIG;
        $curl = curl_init();

        $payload = json_encode([
            'address' => $address,
            'visible' => true
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $config['api_url'] . '/wallet/getaccount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'TRON-PRO-API-KEY: ' . $config['api_key'],
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("TRON Balance Check Error: " . $err);
            return 0;
        }

        $data = json_decode($response, true);

        if (isset($data['balance'])) {
            // Balance is in SUN (1 TRX = 1,000,000 SUN)
            return $data['balance'] / 1000000;
        }

        return 0;
    } catch (Exception $e) {
        error_log("Exception in getTrxBalance: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get TRX to USD conversion rate
 *
 * @return float
 */
function getTrxToUsdRate() {
    try {
        // Using a simple API to get TRX price
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.coingecko.com/api/v3/simple/price?ids=tron&vs_currencies=usd',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);

        if (isset($data['tron']['usd'])) {
            return floatval($data['tron']['usd']);
        }

        return 0.10; // Fallback approximate value
    } catch (Exception $e) {
        error_log("Exception in getTrxToUsdRate: " . $e->getMessage());
        return 0.10; // Fallback
    }
}

/**
 * Update wallet balance in database
 *
 * @param object $wallet RedBean wallet object
 * @return void
 */
function updateWalletBalance($wallet) {
    $trxBalance = getTrxBalance($wallet->address);
    $trxRate = getTrxToUsdRate();
    $usdBalance = $trxBalance * $trxRate;

    $wallet->trx_balance = $trxBalance;
    $wallet->usd_balance = $usdBalance;
    $wallet->updated_at = date('Y-m-d H:i:s');

    \R::store($wallet);
}

/**
 * Encrypt private key for storage
 *
 * @param string $privateKey
 * @return string
 */
function encryptPrivateKey($privateKey) {
    // Use a strong encryption key - in production, store this in environment variable
    $encryptionKey = hash('sha256', MYSQL_PASSWORD . 'wallet_encryption_salt', true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    $encrypted = openssl_encrypt($privateKey, 'aes-256-cbc', $encryptionKey, 0, $iv);

    // Prepend IV to encrypted data
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt private key from storage
 *
 * @param string $encryptedKey
 * @return string
 */
function decryptPrivateKey($encryptedKey) {
    $encryptionKey = hash('sha256', MYSQL_PASSWORD . 'wallet_encryption_salt', true);
    $data = base64_decode($encryptedKey);

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryptionKey, 0, $iv);
}

/**
 * Check if user has a wallet
 *
 * @param int $telegram_user_id
 * @return object|null Wallet object or null
 */
function getUserWallet($telegram_user_id) {
    return \R::findOne('wallet', 'telegram_user_id = ?', [$telegram_user_id]);
}

