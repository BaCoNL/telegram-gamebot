# TRON Transaction Signing Fix

## Problem
The error "Validate signature error: Signature size is 33" occurs because the bot is trying to use a placeholder signature instead of a proper ECDSA signature.

TRON transactions require proper ECDSA secp256k1 signatures which are 65 bytes long. The fallback method was creating invalid 33-byte signatures.

## Solutions (Choose One)

### Solution 1: Install secp256k1 Library (RECOMMENDED)

This is the best solution as it enables local, secure signing.

#### Step 1: Install Composer (if not installed)
Download and install Composer from: https://getcomposer.org/download/

#### Step 2: Install the secp256k1 library
```bash
cd D:\dev\telegram-gamebot
composer install
```

This will install the `kornrunner/secp256k1` library that's now in composer.json.

#### Step 3: Verify installation
After running `composer install`, check that the library is installed:
```bash
composer show kornrunner/secp256k1
```

#### Step 4: Test
Your transactions should now work properly. The `signTransaction()` function will use the secp256k1 library.

### Solution 2: Use Node.js TronWeb

If you prefer Node.js, you can use TronWeb for signing.

#### Step 1: Install Node.js and TronWeb
```bash
cd D:\dev\telegram-gamebot
npm init -y
npm install tronweb
```

#### Step 2: Create signing script
Create a file `scripts/sign_transaction.js`:

```javascript
const TronWeb = require('tronweb');

// Get arguments
const args = process.argv.slice(2);
const transactionJson = args[0];
const privateKey = args[1];

try {
    const transaction = JSON.parse(transactionJson);
    
    // Create TronWeb instance (doesn't need to connect, just for signing)
    const tronWeb = new TronWeb({
        fullHost: 'https://api.trongrid.io'
    });
    
    // Sign the transaction
    const signedTx = tronWeb.trx.sign(transaction, privateKey);
    
    console.log(JSON.stringify(signedTx));
} catch (error) {
    console.error(JSON.stringify({ error: error.message }));
    process.exit(1);
}
```

#### Step 3: Test
Your transactions will now use Node.js for signing.

### Solution 3: Use External Signing Service

If you're using a third-party TRON signing API (like you mentioned in your message), you need to:

1. Create a new function that calls your signing service
2. Make sure to send the JSON as a literal string, not a PHP array

Example:
```php
function signTransactionWithExternalService($transaction, $privateKey, $toAddress, $amount) {
    // Build JSON string manually (not with json_encode on array)
    $postData = '{
        "privateKey": "' . $privateKey . '",
        "toAddress": "' . $toAddress . '",
        "amount": "' . $amount . '"
    }';
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'YOUR_SIGNING_SERVICE_URL',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,  // Send string directly
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}
```

## Current Code Status

The code has been updated to:
1. Include `kornrunner/secp256k1` in composer.json
2. Remove the broken `signTransactionManual()` fallback that was creating invalid signatures
3. Provide clear error messages if the library is not installed

## Next Steps

**You need to install Composer and run `composer install` to get the secp256k1 library.**

If you can't install Composer, let me know and we can implement Solution 2 (Node.js) or Solution 3 (External Service).

