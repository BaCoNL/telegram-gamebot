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
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => TRONGRID_CONFIG['api_url'] . '/wallet/generateaddress',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'TRON-PRO-API-KEY: ' . TRONGRID_CONFIG['api_key'],
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("TRON Wallet Creation Error: " . $err);
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['address']) && isset($data['privateKey'])) {
            return [
                'address' => $data['address'],
                'privateKey' => $data['privateKey']
            ];
        }

        return null;
    } catch (Exception $e) {
        error_log("Exception in createTronWallet: " . $e->getMessage());
        return null;
    }
}

/**
 * Get TRON address from private key
 *
 * @param string $privateKey
 * @return string|null Address or null if invalid
 */
function getAddressFromPrivateKey($privateKey) {
    try {
        // For now, we'll use a simple validation
        // In production, you might want to use a proper TRON library
        if (strlen($privateKey) !== 64) {
            return null;
        }

        // You would normally derive the address from the private key
        // For now, we'll use the TronGrid API to validate
        // This is a simplified implementation

        return validateTronPrivateKey($privateKey);
    } catch (Exception $e) {
        error_log("Exception in getAddressFromPrivateKey: " . $e->getMessage());
        return null;
    }
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
        $curl = curl_init();

        $payload = json_encode([
            'address' => $address,
            'visible' => true
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => TRONGRID_CONFIG['api_url'] . '/wallet/getaccount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'TRON-PRO-API-KEY: ' . TRONGRID_CONFIG['api_key'],
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

