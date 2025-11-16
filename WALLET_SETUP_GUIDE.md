# Wallet System Setup Guide

## Quick Start

You've successfully added a wallet system to your Telegram bot! Here's what was implemented:

## ✅ What's Been Added

### 1. **New Commands**
   - `/start` - Now shows wallet setup for new users
   - `/wallet` - Manage TRON wallet (create, import, view balance)

### 2. **New Files Created**
   ```
   src/Commands/
   ├── WalletCommand.php          # Handles /wallet command
   ├── CallbackqueryCommand.php   # Handles button clicks
   └── GenericmessageCommand.php  # Handles private key import
   
   functions/
   └── tron_wallet.php            # Wallet helper functions
   
   scripts/
   └── init_database.php          # Database initialization
   ```

### 3. **Database Tables**
   The system uses two tables (auto-created by RedBeanPHP):
   - **wallet** - Stores wallet addresses, encrypted private keys, balances
   - **userstate** - Tracks user states during wallet import

## 📋 Setup Steps

### Step 1: Initialize Database
Run this command to set up the database tables:
```bash
php scripts/init_database.php
```

### Step 2: Test the Bot
1. Open Telegram and find your bot
2. Send `/start`
3. You should see wallet setup options:
   - 🆕 Create New Wallet
   - 📥 Import Wallet

### Step 3: Create a Test Wallet
1. Click "Create New Wallet"
2. Save the private key shown (this is important!)
3. The wallet is now ready to use

## 🔧 Configuration

Make sure your `config/config.php` has:
```php
define('TRONGRID_CONFIG', [
    'api_key' => 'your-trongrid-api-key',
    'api_url' => 'https://api.trongrid.io',
]);
```

## 🎯 User Flow

### New Users
1. Send `/start`
2. Choose "Create New Wallet" or "Import Wallet"
3. Wallet is created/imported
4. Ready to play!

### Existing Users
1. Send `/start` - See welcome message
2. Send `/wallet` - View balance and manage wallet
3. Can refresh balance or export private key

## 💾 Database Schema

### Wallet Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| telegram_user_id | BIGINT | Telegram user ID |
| address | VARCHAR(255) | TRON wallet address |
| private_key | TEXT | Encrypted private key |
| trx_balance | DECIMAL(20,6) | Balance in TRX |
| usd_balance | DECIMAL(20,2) | Balance in USD |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

### UserState Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| telegram_user_id | BIGINT | Telegram user ID |
| state | VARCHAR(100) | Current state (e.g., 'awaiting_private_key') |
| created_at | DATETIME | Creation timestamp |

## 🔒 Security Features

1. **Private Key Encryption**
   - Keys encrypted with AES-256-CBC
   - Unique IV for each encryption
   - Encryption key derived from database password + salt

2. **Message Deletion**
   - Private keys in messages are deleted immediately
   - Reduces risk of key exposure

3. **Secure Storage**
   - Private keys never stored in plain text
   - Can only be decrypted with correct encryption key

## 🧪 Testing

### Test Wallet Creation
```bash
# In Telegram:
1. Send /start
2. Click "Create New Wallet"
3. Save the private key shown
4. Send /wallet to view balance
```

### Test Wallet Import
```bash
# In Telegram:
1. Send /wallet
2. Click "Import Wallet"
3. Send your TRON private key
4. Message will be deleted automatically
5. Wallet imported successfully
```

## 📝 Functions Available

### In `functions/tron_wallet.php`

```php
createTronWallet()              // Create new TRON wallet
getTrxBalance($address)         // Get TRX balance
getTrxToUsdRate()              // Get TRX to USD rate
updateWalletBalance($wallet)    // Update wallet balance
encryptPrivateKey($key)        // Encrypt private key
decryptPrivateKey($key)        // Decrypt private key
getUserWallet($user_id)        // Get user's wallet
```

## 🚀 Next Steps

Now that the wallet system is ready, you can:

1. **Connect to Betting System**
   - Check wallet balance before placing bets
   - Deduct bet amounts from wallet
   - Add winnings to wallet

2. **Add Transaction History**
   - Track deposits
   - Track withdrawals
   - Track bet history

3. **Implement Notifications**
   - Notify on deposits received
   - Notify on withdrawals processed
   - Notify on bet results

4. **Add More Features**
   - Wallet backup/restore
   - Multiple wallet support
   - Transaction signing

## ⚠️ Important Notes

### For Production:

1. **Move encryption salt to environment variable**
   ```php
   // Instead of hardcoded salt in tron_wallet.php
   $encryptionKey = hash('sha256', getenv('WALLET_ENCRYPTION_KEY'), true);
   ```

2. **Add rate limiting**
   - Limit wallet creation attempts
   - Limit balance refresh requests

3. **Implement proper TRON key derivation**
   - Current implementation is simplified
   - Use a proper TRON PHP library for production

4. **Add database indexes**
   ```sql
   ALTER TABLE wallet ADD UNIQUE INDEX idx_telegram_user (telegram_user_id);
   ALTER TABLE wallet ADD INDEX idx_address (address);
   ```

5. **Set up monitoring**
   - Log all wallet operations
   - Monitor for suspicious activity
   - Set up alerts for errors

## 🐛 Troubleshooting

### "Undefined function" errors
- Make sure `functions/tron_wallet.php` is loaded via bootstrap
- Check that bootstrap.php is included in webhook

### "Wallet not created" error
- Verify TronGrid API key is valid
- Check API quota hasn't been exceeded
- Check error logs for API responses

### "Invalid private key" when importing
- Private key must be 64 hex characters
- No spaces or special characters
- Must be a valid TRON private key

## 📚 Documentation

For detailed documentation, see:
- `WALLET_SYSTEM_DOCUMENTATION.md` - Complete system documentation
- `functions/tron_wallet.php` - Function documentation

## 🎉 You're Ready!

The wallet system is now fully integrated. Users can:
- ✅ Create new TRON wallets
- ✅ Import existing wallets
- ✅ View balances in TRX and USD
- ✅ Export private keys securely
- ✅ Refresh balances on demand

Test it out by sending `/start` to your bot!

