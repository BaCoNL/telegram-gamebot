# TRON Wallet System Documentation

## Overview

This wallet system allows users to create or import TRON wallets directly within the Telegram bot. The system securely stores wallet information in the database and provides balance tracking in both TRX and USD.

## Features

### 1. Wallet Creation
- **New Wallet Generation**: Users can create a brand new TRON wallet
- **Wallet Import**: Users can import existing wallets using their private key
- **Secure Storage**: Private keys are encrypted using AES-256-CBC encryption

### 2. Wallet Management
- **Balance Tracking**: Real-time TRX balance fetched from TronGrid API
- **USD Conversion**: Automatic conversion to USD using CoinGecko API
- **Private Key Export**: Secure export of private keys when needed

### 3. Database Structure

#### Wallet Table
```sql
CREATE TABLE wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    address VARCHAR(255) NOT NULL,
    private_key TEXT NOT NULL,
    trx_balance DECIMAL(20,6) DEFAULT 0,
    usd_balance DECIMAL(20,2) DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    UNIQUE KEY (telegram_user_id)
);
```

#### UserState Table
```sql
CREATE TABLE userstate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    state VARCHAR(100) NOT NULL,
    created_at DATETIME
);
```

## Installation

### 1. Initialize Database
Run the database initialization script:
```bash
php scripts/init_database.php
```

### 2. Configure TRON API
Make sure your `config/config.php` has the TRONGRID_CONFIG defined:
```php
define('TRONGRID_CONFIG', [
    'api_key' => 'your-trongrid-api-key',
    'api_url' => 'https://api.trongrid.io',
]);
```

### 3. Deploy Webhook
Ensure your webhook is set up correctly:
```bash
php scripts/set_webhook.php
```

## User Flow

### First-Time Users

1. User sends `/start`
2. Bot checks if user has a wallet
3. If no wallet exists, bot presents two options:
   - 🆕 **Create New Wallet**
   - 📥 **Import Wallet**

#### Option A: Create New Wallet
1. User clicks "Create New Wallet"
2. Bot generates a new TRON address and private key
3. Private key is displayed **once** (user should save it)
4. Wallet is saved to database with encrypted private key
5. User can now use the bot

#### Option B: Import Wallet
1. User clicks "Import Wallet"
2. Bot prompts for private key
3. User sends private key (message is deleted immediately for security)
4. Bot validates the key and derives the address
5. Wallet is saved to database
6. User can now use the bot

### Existing Users

1. User sends `/start`
2. Bot displays welcome message with game instructions
3. User can use `/wallet` to manage their wallet

## Commands

### /start
- First-time users: Shows wallet setup options
- Existing users: Shows welcome message and game instructions

### /wallet
- **No wallet**: Shows wallet setup options
- **Wallet exists**: Shows wallet information including:
  - TRON address
  - TRX balance
  - USD balance
  - Options to refresh or export private key

## Security Features

### 1. Private Key Encryption
Private keys are encrypted using AES-256-CBC before storage:
```php
function encryptPrivateKey($privateKey) {
    $encryptionKey = hash('sha256', MYSQL_PASSWORD . 'wallet_encryption_salt', true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($privateKey, 'aes-256-cbc', $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
}
```

### 2. Message Deletion
When importing a wallet, the message containing the private key is immediately deleted:
```php
Request::deleteMessage([
    'chat_id' => $chat_id,
    'message_id' => $message_id,
]);
```

### 3. Private Chat Only
Wallet operations can be configured to work only in private chats:
```php
protected $private_only = true;
```

## API Integration

### TronGrid API
Used for:
- Creating new wallets
- Fetching account balances
- Transaction operations

**Endpoint for wallet generation:**
```
POST https://api.trongrid.io/wallet/generateaddress
```

**Endpoint for account info:**
```
POST https://api.trongrid.io/wallet/getaccount
```

### CoinGecko API
Used for TRX to USD conversion:
```
GET https://api.coingecko.com/api/v3/simple/price?ids=tron&vs_currencies=usd
```

## Functions Reference

### `createTronWallet()`
Creates a new TRON wallet using TronGrid API.

**Returns:** Array with 'address' and 'privateKey' or null on failure

### `getAddressFromPrivateKey($privateKey)`
Derives TRON address from a private key.

**Parameters:**
- `$privateKey` (string): 64-character hex string

**Returns:** Address string or null if invalid

### `getTrxBalance($address)`
Fetches current TRX balance for an address.

**Parameters:**
- `$address` (string): TRON address

**Returns:** Float (balance in TRX)

### `getTrxToUsdRate()`
Gets current TRX to USD conversion rate.

**Returns:** Float (USD value per TRX)

### `updateWalletBalance($wallet)`
Updates wallet balance in database.

**Parameters:**
- `$wallet` (object): RedBean wallet object

### `encryptPrivateKey($privateKey)`
Encrypts private key for secure storage.

**Parameters:**
- `$privateKey` (string): Plain private key

**Returns:** Encrypted string (base64)

### `decryptPrivateKey($encryptedKey)`
Decrypts stored private key.

**Parameters:**
- `$encryptedKey` (string): Encrypted private key

**Returns:** Plain private key string

### `getUserWallet($telegram_user_id)`
Retrieves user's wallet from database.

**Parameters:**
- `$telegram_user_id` (int): Telegram user ID

**Returns:** Wallet object or null

## Error Handling

The system includes comprehensive error handling:

1. **Invalid Private Key**: User receives error message and can retry
2. **API Failures**: Fallback values and error logging
3. **Duplicate Wallets**: Prevents creating multiple wallets per user
4. **Network Issues**: Graceful degradation with logged errors

## Production Recommendations

1. **Encryption Key**: Store encryption salt in environment variable instead of hardcoding
2. **API Keys**: Use environment variables for API keys
3. **Rate Limiting**: Implement rate limiting for wallet operations
4. **Backup**: Regular database backups of wallet information
5. **HTTPS**: Ensure all API calls use HTTPS
6. **Monitoring**: Monitor for suspicious wallet creation patterns
7. **TRON Library**: Consider using a proper TRON PHP library for key derivation

## Troubleshooting

### Wallet Not Created
- Check TronGrid API key is valid
- Verify API endpoint is accessible
- Check error logs for API responses

### Balance Not Updating
- Verify TronGrid API is responding
- Check if address format is correct
- Ensure API key has sufficient quota

### Private Key Decryption Fails
- Verify encryption salt hasn't changed
- Check database private_key field isn't corrupted
- Ensure OpenSSL extension is enabled

## Next Steps

After implementing the wallet system, you can:

1. Implement the betting system using wallet balances
2. Add transaction history tracking
3. Implement deposit/withdrawal notifications
4. Add multi-wallet support (multiple addresses per user)
5. Implement wallet backup/recovery features

