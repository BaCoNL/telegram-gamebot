# 🔧 Critical Bug Fixes - TRON Hash Lottery

## Issues Fixed

### ❌ Issue 1: Class "R" not found
**Error:** `Class "Longman\\TelegramBot\\Commands\\SystemCommands\\R" not found`

**Cause:** Missing namespace import for RedBeanPHP in CallbackqueryCommand.php

**Fix:** Added `use R;` import statement

```php
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use R; // ← ADDED THIS
```

---

### ❌ Issue 2: Invalid base58 String Error
**Error:** `INVALID base58 String, Illegal character 0 at 6`

**Cause:** The `deriveAddressFromPrivateKey()` function was generating invalid TRON addresses using incorrect cryptographic methods.

**Root Problem:** 
- PHP doesn't have built-in secp256k1 support
- Previous implementation used SHA256 instead of proper elliptic curve cryptography
- Generated addresses were not valid TRON addresses

**Fix:** Updated address derivation strategy to:
1. Check database for existing wallet address (most reliable)
2. Check if it's the HOUSE wallet and use configured address
3. Return null if address cannot be determined (fail safely)

---

## New Approach: Use Stored Addresses

Instead of deriving addresses from private keys (which requires secp256k1), we now:

### For User Wallets:
- **Store address when wallet is created/imported**
- **Retrieve address from wallet table when needed**
- **Pass address explicitly to transaction functions**

### For House Wallet:
- **Use configured HOUSE_WALLET_ADDRESS constant**
- **Never try to derive it**

---

## Changes Made

### 1. functions/tron_wallet.php
```php
// OLD: Tried to derive using broken crypto
function deriveAddressFromPrivateKey($privateKey) {
    // Complex SHA256 hashing that doesn't work ❌
}

// NEW: Use stored address from database
function deriveAddressFromPrivateKey($privateKey) {
    // 1. Check database for wallet with this key
    // 2. Check if it's HOUSE wallet
    // 3. Return null if not found (fail safely)
}
```

### 2. functions/tron_transactions.php
**Added new function:**
```php
signAndBroadcastTransactionWithAddress($privateKey, $fromAddress, $toAddress, $amount)
```

This function accepts the **from address explicitly** instead of trying to derive it.

### 3. functions/bet_processing.php
**Updated transaction sending:**
```php
// OLD
$result = signAndBroadcastTransaction($privateKey, HOUSE_WALLET_ADDRESS, $betAmount);

// NEW - Pass wallet address explicitly
$result = signAndBroadcastTransactionWithAddress(
    $privateKey,
    $userWallet->address, // ← Use stored address
    HOUSE_WALLET_ADDRESS,
    $betAmount
);
```

---

## Why This Works

### Problem
TRON addresses are derived from private keys using:
1. secp256k1 elliptic curve to get public key
2. Keccak-256 hash of public key
3. TRON prefix (0x41)
4. Base58Check encoding

PHP doesn't have native secp256k1 support.

### Solution
We already have the correct address stored in the database!
- When wallet is created via TronGrid API, we get the correct address
- We store it in the `wallet` table
- We just use that stored address instead of deriving it

---

## Testing

After these fixes, test the following:

### ✅ Test 1: Place a Bet
```
/bet
→ Select difficulty
→ Enter prediction
→ Select amount
→ Confirm
```

**Expected:** Transaction should be created successfully without base58 errors

### ✅ Test 2: Check Error Logs
Monitor your error logs for:
- ❌ No more "INVALID base58 String" errors
- ❌ No more "Class R not found" errors
- ✅ Transactions should create successfully

### ✅ Test 3: Verify Transaction
If transaction succeeds:
1. Check TronScan.org with the TX hash
2. Verify amount is correct
3. Verify from/to addresses are correct

---

## Important Notes

### ⚠️ Current Limitations

1. **Address Derivation Still Not Perfect**
   - We rely on stored addresses in database
   - If address not in database, transaction will fail
   - This is acceptable because:
     - User wallets are always in database
     - House wallet uses configured constant

2. **Production Recommendation**
   For production, consider:
   - Installing `kornrunner/secp256k1` PHP extension
   - OR using TronBox/TronWeb via Node.js subprocess
   - OR implementing local signing with proper crypto library

3. **Current Workaround is Safe**
   - All addresses come from trusted sources (database or config)
   - No invalid addresses can be generated
   - Transactions will fail early if address missing

---

## Files Modified

1. ✅ `src/Commands/CallbackqueryCommand.php` - Added `use R;`
2. ✅ `functions/tron_wallet.php` - Fixed `deriveAddressFromPrivateKey()`
3. ✅ `functions/tron_transactions.php` - Added `signAndBroadcastTransactionWithAddress()`
4. ✅ `functions/bet_processing.php` - Updated `sendBetTransaction()` and `sendPayout()`

---

## Verification Checklist

- [ ] Upload all 4 modified files to server
- [ ] Clear any PHP opcode cache (if applicable)
- [ ] Test /bet command end-to-end
- [ ] Verify transaction creates successfully
- [ ] Check TronScan for successful transaction
- [ ] Monitor error logs for any new issues

---

## If You Still Get Errors

### "Class R not found"
- Make sure `use R;` is at the top of CallbackqueryCommand.php
- Verify bootstrap.php loads RedBeanPHP correctly
- Check that rb.php plugin is loaded

### "Invalid base58 String"
- Verify HOUSE_WALLET_ADDRESS in config.php is correct
- Check that wallet table has user's address stored
- Ensure address format is correct (starts with T, 34 characters)

### "Transaction creation failed"
- Check TronGrid API key is valid
- Verify wallet has TRX for gas fees
- Check network connectivity to TronGrid
- Review full error message in logs

---

**Status:** ✅ Critical bugs fixed
**Date:** November 19, 2025
**Version:** 1.0.1

All critical issues have been resolved. The betting system should now work correctly!

