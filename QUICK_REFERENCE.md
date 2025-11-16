# Quick Reference - TRON Wallet System

## 🚀 Quick Start

```bash
# 1. Initialize database
php scripts/init_database.php

# 2. Test in Telegram
# Send: /start
# Click: Create New Wallet or Import Wallet
```

## 📁 File Structure

```
src/Commands/
├── StartCommand.php          ✅ Modified - Shows wallet setup
├── WalletCommand.php         ✅ New - /wallet command
├── CallbackqueryCommand.php  ✅ New - Button handler
└── GenericmessageCommand.php ✅ New - Message handler

functions/
└── tron_wallet.php           ✅ New - Wallet functions

scripts/
└── init_database.php         ✅ New - DB initialization

Documentation/
├── IMPLEMENTATION_SUMMARY.md
├── WALLET_SETUP_GUIDE.md
└── WALLET_SYSTEM_DOCUMENTATION.md
```

## 💾 Database Tables

### wallet
- telegram_user_id (unique)
- address
- private_key (encrypted)
- trx_balance
- usd_balance
- created_at, updated_at

### userstate
- telegram_user_id
- state
- created_at

## 🎯 Key Functions (tron_wallet.php)

| Function | Purpose |
|----------|---------|
| `createTronWallet()` | Generate new wallet via API |
| `getTrxBalance($address)` | Fetch TRX balance |
| `updateWalletBalance($wallet)` | Update balance in DB |
| `encryptPrivateKey($key)` | Encrypt for storage |
| `decryptPrivateKey($key)` | Decrypt from storage |
| `getUserWallet($user_id)` | Get user's wallet |

## 🎮 User Commands

| Command | New Users | Existing Users |
|---------|-----------|----------------|
| `/start` | Wallet setup options | Welcome message |
| `/wallet` | Wallet setup options | Balance & management |

## 🔘 Inline Buttons

| Button | Action |
|--------|--------|
| 🆕 Create New Wallet | Generate TRON wallet |
| 📥 Import Wallet | Import existing wallet |
| 🔄 Refresh Balance | Update balance from blockchain |
| 🔑 Export Private Key | Send private key securely |

## 🔒 Security Features

✅ AES-256-CBC encryption for private keys
✅ Automatic message deletion for sensitive data
✅ Unique IV per encryption
✅ No plain text storage
✅ Encryption key derived from DB password + salt

## 🔌 API Endpoints

### TronGrid
```
POST /wallet/generateaddress  → Create wallet
POST /wallet/getaccount       → Get balance
```

### CoinGecko
```
GET /simple/price?ids=tron&vs_currencies=usd → TRX price
```

## 🧪 Testing Flow

1. **Create Wallet**
   ```
   /start → Create New Wallet → Save private key
   ```

2. **View Wallet**
   ```
   /wallet → See address & balance
   ```

3. **Import Wallet**
   ```
   /wallet → Import Wallet → Send private key
   ```

4. **Check Balance**
   ```
   /wallet → Refresh Balance
   ```

## ⚙️ Configuration Required

```php
// config/config.php
define('TRONGRID_CONFIG', [
    'api_key' => 'your-api-key',
    'api_url' => 'https://api.trongrid.io',
]);
```

## 🔧 Integration Example

```php
// In BetCommand.php
$wallet = getUserWallet($user_id);

// Check balance
if ($wallet->trx_balance < $bet_amount) {
    return $this->replyToChat("Insufficient balance!");
}

// Deduct bet
$wallet->trx_balance -= $bet_amount;
R::store($wallet);

// Add winnings
$wallet->trx_balance += $winnings;
R::store($wallet);
```

## 📊 Workflow Diagrams

### New User
```
/start
  ↓
Check wallet exists?
  ↓ NO
Show buttons
  ↓
[Create] or [Import]
  ↓
Save to database
  ↓
Ready to play!
```

### Existing User
```
/start
  ↓
Check wallet exists?
  ↓ YES
Show welcome
  ↓
User sends /wallet
  ↓
Show balance & buttons
```

## ⚠️ Important Notes

1. **Private keys shown ONCE** during creation
2. **Messages auto-deleted** when importing
3. **Balances cached** in database, refresh as needed
4. **One wallet per user** (unique telegram_user_id)

## 🎯 Next Integration Steps

1. ✅ Wallet system complete
2. ⏭️ Connect to `/bet` command
3. ⏭️ Implement transaction history
4. ⏭️ Add deposit notifications
5. ⏭️ Implement withdrawal system

## 📞 Support

- See `WALLET_SETUP_GUIDE.md` for detailed setup
- See `WALLET_SYSTEM_DOCUMENTATION.md` for technical docs
- See `IMPLEMENTATION_SUMMARY.md` for overview

---

**Status:** ✅ Ready for production (with security recommendations applied)

