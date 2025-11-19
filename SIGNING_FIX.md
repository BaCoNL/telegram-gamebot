# 🔧 TRANSACTION SIGNING FIX - TRON Hash Lottery

## ⚠️ Critical Issue: TronGrid Signing Endpoint Deprecated

**Error:** `TronGrid service does not support this API` (404)

**Endpoint Removed:** `/wallet/gettransactionsign`

TronGrid removed this endpoint for security reasons. You must sign transactions locally.

---

## 🚀 SOLUTION 1: Install secp256k1 Library (RECOMMENDED)

### Step 1: Install Composer Package
```bash
cd /var/www/vhosts/innolabs.nl/telegram.innolabs.nl
composer require kornrunner/secp256k1-php
```

### Step 2: Update tron_transactions.php

Replace the `signTransaction()` function with:

```php
function signTransaction($transaction, $privateKey) {
    // Check if library is available
    if (class_exists('kornrunner\Secp256k1')) {
        return signTronTransactionWithSecp256k1($transaction, $privateKey);
    }
    
    // Fallback to alternative method
    return signTransactionFallback($transaction, $privateKey);
}

/**
 * Sign using secp256k1 library (PROPER METHOD)
 */
function signTronTransactionWithSecp256k1($transaction, $privateKey) {
    try {
        require_once BASE_PATH . '/functions/tron_signing.php';
        return signTronTransaction($transaction, $privateKey);
    } catch (Exception $e) {
        error_log("secp256k1 signing failed: " . $e->getMessage());
        return null;
    }
}
```

### Step 3: Test Transaction
```
/bet → Should work with proper signing
```

---

## 🔄 SOLUTION 2: Use TronLink-Like Signing (NO EXTERNAL LIBRARY)

If you can't install composer packages, use this workaround:

### Create a Node.js Signing Service

**File: `scripts/sign_transaction.js`**
```javascript
const TronWeb = require('tronweb');

// Get transaction and private key from command line
const txData = JSON.parse(process.argv[2]);
const privateKey = process.argv[3];

const tronWeb = new TronWeb({
    fullHost: 'https://api.trongrid.io'
});

// Sign transaction
const signedTx = tronWeb.trx.sign(txData, privateKey);
console.log(JSON.stringify(signedTx));
```

**Install TronWeb:**
```bash
npm install tronweb
```

**PHP Function:**
```php
function signTransactionWithNodeJS($transaction, $privateKey) {
    $txJson = json_encode($transaction);
    $command = "node " . BASE_PATH . "/scripts/sign_transaction.js " . 
               escapeshellarg($txJson) . " " . 
               escapeshellarg($privateKey);
    
    $output = shell_exec($command);
    return json_decode($output, true);
}
```

---

## ⚡ SOLUTION 3: Quick Fix with External Signing API

Use a self-hosted signing service or alternative:

### Option A: Use TronScan API (Temporary)
```php
function signTransaction($transaction, $privateKey) {
    // NOT RECOMMENDED FOR PRODUCTION
    // This is a temporary workaround
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://apilist.tronscan.org/api/transaction/sign',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'transaction' => $transaction,
            'privateKey' => $privateKey
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}
```

---

## 🎯 RECOMMENDED IMPLEMENTATION

**Best Practice: Install secp256k1-php**

1. **Install the package:**
   ```bash
   composer require kornrunner/secp256k1-php
   composer require kornrunner/keccak
   ```

2. **Update `functions/tron_transactions.php`:**

Add at the top of the file:
```php
<?php
// Check if secp256k1 is available
if (file_exists(BASE_PATH . '/vendor/kornrunner/secp256k1-php/src/Secp256k1.php')) {
    require_once BASE_PATH . '/functions/tron_signing.php';
}
```

3. **Replace signTransaction function:**

```php
function signTransaction($transaction, $privateKey) {
    try {
        // Try to use proper secp256k1 signing
        if (function_exists('signTronTransaction')) {
            return signTronTransaction($transaction, $privateKey);
        }
        
        // Check for Node.js signing
        if (file_exists(BASE_PATH . '/scripts/sign_transaction.js')) {
            return signTransactionWithNodeJS($transaction, $privateKey);
        }
        
        // Last resort: external API (NOT SECURE)
        error_log("WARNING: Using external signing API - NOT RECOMMENDED FOR PRODUCTION");
        return signTransactionWithExternalAPI($transaction, $privateKey);
        
    } catch (Exception $e) {
        error_log("Transaction signing failed: " . $e->getMessage());
        return null;
    }
}
```

---

## 📋 Implementation Checklist

### ✅ Immediate Fix (Choose One):

**Option 1: Composer Package (BEST)**
- [ ] SSH into server
- [ ] Run: `composer require kornrunner/secp256k1-php`
- [ ] Run: `composer require kornrunner/keccak`
- [ ] Upload `functions/tron_signing.php`
- [ ] Update `functions/tron_transactions.php` to use new signing

**Option 2: Node.js Service (GOOD)**
- [ ] Install Node.js on server
- [ ] Run: `npm install tronweb`
- [ ] Create `scripts/sign_transaction.js`
- [ ] Update PHP to call Node.js script

**Option 3: External API (TEMPORARY ONLY)**
- [ ] Implement external API signing
- [ ] ⚠️ Plan to migrate to Option 1 ASAP

---

## 🧪 Testing

After implementing the fix:

```bash
# Test from command line
php -r "
require 'bootstrap.php';
require 'functions/tron_transactions.php';
\$tx = ['raw_data_hex' => 'test'];
\$result = signTransaction(\$tx, 'yourprivatekey');
var_dump(\$result);
"
```

Then test via Telegram:
```
/bet
→ Select difficulty
→ Enter prediction
→ Confirm bet
✅ Transaction should sign successfully
```

---

## 🔍 Verification

Check error logs for:
- ✅ No more "TronGrid service does not support this API"
- ✅ Transaction signature present
- ✅ Broadcast successful

---

## 📞 Need Help?

If you get errors:

**"secp256k1 not found"**
- Run: `composer install`
- Check: `/vendor/kornrunner/` directory exists

**"Node.js not found"**
- Install Node.js: `apt-get install nodejs npm`
- Verify: `node --version`

**"Signature invalid"**
- Check private key format (64 hex chars)
- Verify transaction has `raw_data_hex` field

---

**Status:** Waiting for implementation
**Priority:** CRITICAL - Betting system won't work without this
**Recommended:** Install secp256k1-php (5 minutes)

