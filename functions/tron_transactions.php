<?php
/**
 * TRON Transaction Functions
 *
 * Handles signing and broadcasting TRON transactions
 */

/**
 * Sign and broadcast a TRX transaction
 *
 * NOTE: This implementation uses TronGrid API for transaction creation and broadcasting.
 * For production, consider using a proper PHP TRON library with local signing for better security.
 *
 * @param string $privateKey Sender's private key (hex)
 * @param string $toAddress Recipient's TRON address
 * @param float $amount Amount in TRX
 * @return array ['success' => bool, 'txHash' => string|null, 'error' => string|null]
 */
function signAndBroadcastTransaction($privateKey, $toAddress, $amount) {
    // Derive address from private key
    $fromAddress = getAddressFromPrivateKey($privateKey);

    if (!$fromAddress) {
        return [
            'success' => false,
            'txHash' => null,
            'error' => 'Failed to derive address from private key'
        ];
    }

    return signAndBroadcastTransactionWithAddress($privateKey, $fromAddress, $toAddress, $amount);
}

/**
 * Sign and broadcast a TRX transaction with explicit from address
 *
 * @param string $privateKey Sender's private key (hex)
 * @param string $fromAddress Sender's TRON address
 * @param string $toAddress Recipient's TRON address
 * @param float $amount Amount in TRX
 * @return array ['success' => bool, 'txHash' => string|null, 'error' => string|null]
 */
function signAndBroadcastTransactionWithAddress($privateKey, $fromAddress, $toAddress, $amount) {
    try {
        $config = TRONGRID_CONFIG;

        // Validate addresses are proper TRON format
        if (!preg_match('/^T[A-Za-z1-9]{33}$/', $fromAddress)) {
            error_log("From address invalid format: " . $fromAddress);
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'Invalid sender address format'
            ];
        }

        if (!preg_match('/^T[A-Za-z1-9]{33}$/', $toAddress)) {
            error_log("To address invalid format: " . $toAddress);
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'Invalid recipient address format'
            ];
        }

        // Convert TRX to SUN (1 TRX = 1,000,000 SUN)
        $amountInSun = intval($amount * 1000000);

        // Step 1: Create transaction
        $createTxResult = createTrxTransaction($fromAddress, $toAddress, $amountInSun);

        if (!$createTxResult['success']) {
            return $createTxResult;
        }

        $transaction = $createTxResult['transaction'];

        // Step 2: Sign transaction
        $signedTx = signTransaction($transaction, $privateKey);

        if (!$signedTx) {
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'Failed to sign transaction'
            ];
        }

        // Step 3: Broadcast transaction
        $broadcastResult = broadcastTransaction($signedTx);

        return $broadcastResult;

    } catch (Exception $e) {
        error_log("Transaction error: " . $e->getMessage());
        return [
            'success' => false,
            'txHash' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create a TRX transfer transaction
 *
 * @param string $fromAddress Sender address
 * @param string $toAddress Recipient address
 * @param int $amountInSun Amount in SUN
 * @return array ['success' => bool, 'transaction' => array|null, 'error' => string|null]
 */
function createTrxTransaction($fromAddress, $toAddress, $amountInSun) {
    try {
        $config = TRONGRID_CONFIG;
        $curl = curl_init();

        $payload = json_encode([
            'owner_address' => $fromAddress,
            'to_address' => $toAddress,
            'amount' => $amountInSun,
            'visible' => true
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $config['api_url'] . '/wallet/createtransaction',
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
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("Create transaction error: " . $err);
            return [
                'success' => false,
                'transaction' => null,
                'error' => 'Failed to create transaction: ' . $err
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['txID'])) {
            $errorMsg = $data['Error'] ?? 'Unknown error';
            error_log("Create transaction failed: " . $errorMsg);
            return [
                'success' => false,
                'transaction' => null,
                'error' => 'Transaction creation failed: ' . $errorMsg
            ];
        }

        return [
            'success' => true,
            'transaction' => $data,
            'error' => null
        ];

    } catch (Exception $e) {
        error_log("Exception in createTrxTransaction: " . $e->getMessage());
        return [
            'success' => false,
            'transaction' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sign a transaction with private key
 *
 * IMPORTANT: TronGrid no longer supports the gettransactionsign endpoint.
 * This implementation tries multiple signing methods in order of preference.
 *
 * @param array $transaction Unsigned transaction
 * @param string $privateKey Private key (hex)
 * @return array|null Signed transaction or null on failure
 */
function signTransaction($transaction, $privateKey) {
    try {
        // Method 1: Use secp256k1 library if available (BEST)
        if (class_exists('kornrunner\Secp256k1')) {
            error_log("Using secp256k1 library for signing");
            require_once BASE_PATH . '/functions/tron_signing.php';
            $signed = signTronTransaction($transaction, $privateKey);
            if ($signed) return $signed;
        }

        // Method 2: Use Node.js TronWeb if available (GOOD)
        if (file_exists(BASE_PATH . '/scripts/sign_transaction.js')) {
            error_log("Using Node.js TronWeb for signing");
            $signed = signTransactionWithNodeJS($transaction, $privateKey);
            if ($signed) return $signed;
        }

        // Method 3: Manual signing attempt (LIMITED)
        error_log("WARNING: Using basic signing - may not work correctly");
        error_log("RECOMMENDED: Install composer require kornrunner/secp256k1-php");
        return signTransactionManual($transaction, $privateKey);

    } catch (Exception $e) {
        error_log("Exception in signTransaction: " . $e->getMessage());
        return null;
    }
}

/**
 * Sign transaction using Node.js TronWeb
 *
 * @param array $transaction Unsigned transaction
 * @param string $privateKey Private key in hex
 * @return array|null Signed transaction or null on failure
 */
function signTransactionWithNodeJS($transaction, $privateKey) {
    try {
        $txJson = json_encode($transaction);
        $scriptPath = BASE_PATH . '/scripts/sign_transaction.js';

        // Execute Node.js script
        $command = "node " . escapeshellarg($scriptPath) . " " .
                   escapeshellarg($txJson) . " " .
                   escapeshellarg($privateKey) . " 2>&1";

        $output = shell_exec($command);

        if (!$output) {
            error_log("Node.js signing failed: No output");
            return null;
        }

        $result = json_decode($output, true);

        if ($result && isset($result['signature'])) {
            return $result;
        }

        error_log("Node.js signing failed: " . $output);
        return null;

    } catch (Exception $e) {
        error_log("Exception in signTransactionWithNodeJS: " . $e->getMessage());
        return null;
    }
}

/**
 * Manual transaction signing (basic implementation)
 *
 * WARNING: This is a simplified implementation and may not work for all transactions.
 * Only use as a last resort.
 *
 * @param array $transaction Unsigned transaction
 * @param string $privateKey Private key in hex
 * @return array|null Signed transaction or null on failure
 */
function signTransactionManual($transaction, $privateKey) {
    try {
        // Check if we have the raw_data_hex field (needed for signing)
        if (!isset($transaction['raw_data_hex'])) {
            error_log("Transaction missing raw_data_hex field");
            return null;
        }

        $rawDataHex = $transaction['raw_data_hex'];

        // Hash the raw data with SHA256
        $hash = hash('sha256', hex2bin($rawDataHex));

        // Create a deterministic signature (THIS IS NOT SECURE - FOR TESTING ONLY)
        // In reality, you need ECDSA secp256k1 signing
        error_log("CRITICAL WARNING: Using insecure manual signing");
        error_log("This will likely FAIL at broadcast");
        error_log("SOLUTION: Run: composer require kornrunner/secp256k1-php");

        // Generate placeholder signature
        $r = hash('sha256', $hash . $privateKey);
        $s = hash('sha256', $privateKey . $hash);
        $v = '00'; // Recovery ID

        $signature = $r . $s . $v;

        // Add signature to transaction
        $transaction['signature'] = [$signature];

        return $transaction;

    } catch (Exception $e) {
        error_log("Exception in signTransactionManual: " . $e->getMessage());
        return null;
    }
}

/**
 * Broadcast a signed transaction
 *
 * @param array $signedTransaction Signed transaction
 * @return array ['success' => bool, 'txHash' => string|null, 'error' => string|null]
 */
function broadcastTransaction($signedTransaction) {
    try {
        $config = TRONGRID_CONFIG;
        $curl = curl_init();

        $payload = json_encode($signedTransaction);

        curl_setopt_array($curl, [
            CURLOPT_URL => $config['api_url'] . '/wallet/broadcasttransaction',
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
            error_log("Broadcast transaction error: " . $err);
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'Broadcast failed: ' . $err
            ];
        }

        $data = json_decode($response, true);

        if (!isset($data['result']) || $data['result'] !== true) {
            $errorMsg = $data['message'] ?? 'Unknown error';
            error_log("Broadcast failed: " . $errorMsg);
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'Broadcast failed: ' . $errorMsg
            ];
        }

        $txHash = $signedTransaction['txID'];

        return [
            'success' => true,
            'txHash' => $txHash,
            'error' => null
        ];

    } catch (Exception $e) {
        error_log("Exception in broadcastTransaction: " . $e->getMessage());
        return [
            'success' => false,
            'txHash' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Wait for transaction confirmation
 *
 * @param string $txHash Transaction hash
 * @param int $maxWaitSeconds Maximum time to wait (default 30 seconds)
 * @return array ['confirmed' => bool, 'transaction' => array|null]
 */
function waitForTransactionConfirmation($txHash, $maxWaitSeconds = 30) {
    $startTime = time();

    while ((time() - $startTime) < $maxWaitSeconds) {
        $txInfo = getTransactionInfo($txHash);

        if ($txInfo['found'] && isset($txInfo['data']['blockNumber'])) {
            return [
                'confirmed' => true,
                'transaction' => $txInfo['data']
            ];
        }

        // Wait 2 seconds before checking again
        sleep(2);
    }

    return [
        'confirmed' => false,
        'transaction' => null
    ];
}

/**
 * Get transaction information
 *
 * @param string $txHash Transaction hash
 * @return array ['found' => bool, 'data' => array|null]
 */
function getTransactionInfo($txHash) {
    try {
        $config = TRONGRID_CONFIG;
        $curl = curl_init();

        $payload = json_encode([
            'value' => $txHash
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $config['api_url'] . '/wallet/gettransactionbyid',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
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
            return ['found' => false, 'data' => null];
        }

        $data = json_decode($response, true);

        if (empty($data) || !isset($data['txID'])) {
            return ['found' => false, 'data' => null];
        }

        return ['found' => true, 'data' => $data];

    } catch (Exception $e) {
        error_log("Exception in getTransactionInfo: " . $e->getMessage());
        return ['found' => false, 'data' => null];
    }
}

/**
 * Get transaction receipt
 *
 * @param string $txHash Transaction hash
 * @return array|null Receipt data or null
 */
function getTransactionReceipt($txHash) {
    try {
        $config = TRONGRID_CONFIG;
        $curl = curl_init();

        $payload = json_encode([
            'value' => $txHash
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $config['api_url'] . '/wallet/gettransactioninfobyid',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
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
            return null;
        }

        $data = json_decode($response, true);

        return $data;

    } catch (Exception $e) {
        error_log("Exception in getTransactionReceipt: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify transaction was successful
 *
 * @param string $txHash Transaction hash
 * @return bool
 */
function verifyTransactionSuccess($txHash) {
    $receipt = getTransactionReceipt($txHash);

    if (!$receipt) {
        return false;
    }

    // Check if transaction was successful (no error in receipt)
    if (isset($receipt['receipt']['result']) && $receipt['receipt']['result'] === 'SUCCESS') {
        return true;
    }

    // If no receipt result field, check that transaction exists and has blockNumber
    if (isset($receipt['blockNumber'])) {
        return true;
    }

    return false;
}

