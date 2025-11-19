# TRON Hash Lottery - Quick Start Guide

## ✅ Implementation Complete!

Your TRON Hash Lottery betting system is now fully implemented with all required features.

## 📁 New Files Created

### Core Functions
- `functions/bet_processing.php` - Core betting logic (validation, state management, statistics)
- `functions/tron_transactions.php` - TRON transaction signing and broadcasting

### Updated Commands
- `src/Commands/BetCommand.php` - Enhanced with wallet checks and cooldown
- `src/Commands/CallbackqueryCommand.php` - Added comprehensive bet flow handlers
- `src/Commands/GenericmessageCommand.php` - Added prediction and bet amount input handlers

### Scripts
- `scripts/init_bet_tables.php` - Database table initialization

### Documentation
- `BET_SYSTEM_IMPLEMENTATION.md` - Complete implementation documentation

## 🗄️ Database Setup

Since PDO MySQL driver may not be enabled, you can create the tables manually:

### Option 1: Using phpMyAdmin or MySQL Workbench
Run these SQL commands in your database:

```sql
-- Bet table
CREATE TABLE IF NOT EXISTS bet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bet_id VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT NOT NULL,
    prediction VARCHAR(4) NOT NULL,
    characters_count TINYINT NOT NULL,
    multiplier DECIMAL(10,2) NOT NULL,
    bet_amount DECIMAL(20,6) NOT NULL,
    potential_payout DECIMAL(20,6) NOT NULL,
    
    tx_hash VARCHAR(64) DEFAULT NULL,
    actual_hash_ending VARCHAR(4) DEFAULT NULL,
    
    status ENUM('pending', 'won', 'lost', 'cancelled', 'won_payout_pending') DEFAULT 'pending',
    payout_amount DECIMAL(20,6) DEFAULT 0,
    payout_tx_hash VARCHAR(64) DEFAULT NULL,
    
    created_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    
    INDEX idx_user_bets (user_id, created_at),
    INDEX idx_status (status),
    INDEX idx_bet_id (bet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User stats table
CREATE TABLE IF NOT EXISTS userstats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    total_bets INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_losses INT DEFAULT 0,
    total_wagered DECIMAL(20,6) DEFAULT 0,
    total_won DECIMAL(20,6) DEFAULT 0,
    total_lost DECIMAL(20,6) DEFAULT 0,
    net_profit DECIMAL(20,6) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User state table
CREATE TABLE IF NOT EXISTS userstate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    state VARCHAR(50) NOT NULL,
    data TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    
    INDEX idx_user_state (telegram_user_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Option 2: Using MySQL Command Line
```bash
mysql -u root -p telegram-gamebot < create_tables.sql
```

## 🎮 How to Test

### 1. Verify Setup
- ✅ Database tables created
- ✅ House wallet configured in `config/config.php`
- ✅ TronGrid API key set

### 2. Test with Telegram Bot

#### Step 1: Create/Import Wallet
```
/wallet
```
- Click "Create New Wallet" or "Import Wallet"
- Save your private key securely
- Deposit at least 10 TRX to test

#### Step 2: Place a Test Bet
```
/bet
```

Follow the flow:
1. **Select difficulty**: Choose "Easy (10x)" for testing
2. **Enter prediction**: Send a hex character like `F` or `A`
3. **Choose amount**: Select 10 TRX or enter custom amount
4. **Confirm**: Review summary and click "✅ Confirm Bet"
5. **Wait for result**: Transaction sent → Hash verified → Win/Loss shown

### 3. Test Cases

#### ✅ Happy Path
- User has wallet ✓
- User has sufficient balance ✓
- Bet placed successfully ✓
- Transaction sent ✓
- Result determined ✓

#### ⚠️ Error Handling
- No wallet → Shows error with /wallet instruction
- Insufficient balance → Shows deposit address
- Invalid prediction (non-hex) → Re-prompts with error
- Wrong length prediction → Shows error
- Bet too high → Shows maximum allowed
- Cooldown active → Shows wait time

## 🎯 Difficulty Levels Reference

| Level | Chars | Multiplier | Odds | Example Prediction |
|-------|-------|------------|------|-------------------|
| Easy | 1 | 10x | 1/16 | `F` |
| Medium | 2 | 200x | 1/256 | `A7` |
| Hard | 3 | 3,500x | 1/4,096 | `C3A` |
| Expert | 4 | 50,000x | 1/65,536 | `1F4B` |

## 💡 Key Features

### ✅ Implemented
- [x] 4 difficulty levels with different odds
- [x] Hex prediction validation (0-9, A-F)
- [x] Bet amount selection (preset + custom)
- [x] Comprehensive bet summary
- [x] Automatic transaction sending
- [x] Hash verification
- [x] Automatic payouts on wins
- [x] User statistics tracking
- [x] Play again / Same bet again options
- [x] Cooldown system (10 seconds)
- [x] House balance protection (max 2% risk)
- [x] Balance validation
- [x] State management for multi-step flow
- [x] Error handling for all scenarios

### 🔒 Security Features
- Encrypted private key storage
- Balance checks before transaction
- House bankruptcy prevention
- Transaction verification
- Cooldown to prevent spam
- Proper error messages (no sensitive data exposed)

## 📊 User Flow Diagram

```
/bet
  ↓
Check wallet exists
  ↓
Check balance ≥ 1 TRX
  ↓
Check cooldown
  ↓
Show difficulty selection (4 options)
  ↓
User selects difficulty
  ↓
Prompt for prediction
  ↓
User sends prediction (validated)
  ↓
Show bet amount options
  ↓
User selects amount
  ↓
Show bet summary
  ↓
User confirms
  ↓
Send transaction (user → house)
  ↓
Extract TX hash
  ↓
Verify last N characters
  ↓
WIN → Send payout (house → user)
LOSS → Update stats
  ↓
Show result with replay options
```

## 🔧 Configuration

### House Wallet (config/config.php)
```php
define('HOUSE_WALLET_PRIVATE_KEY', 'your_64_char_hex_key');
define('HOUSE_WALLET_ADDRESS', 'TYourWalletAddress');
```

### Minimum Bet (functions/bet_processing.php)
```php
define('MIN_BET', 1); // Change to adjust minimum bet
```

### Cooldown (functions/bet_processing.php)
```php
// In checkBetCooldown() function
$cooldownSeconds = 10; // Change to adjust cooldown period
```

## 🐛 Troubleshooting

### Tables not created?
1. Enable MySQL PDO extension in php.ini
2. Or create tables manually via phpMyAdmin
3. Or import the SQL from above

### Transaction fails?
- Check house wallet has TRX for gas
- Verify TronGrid API key is valid
- Check user wallet balance
- Review error logs

### Payout not sent?
- Check house wallet balance
- Look for `won_payout_pending` status in bet table
- Manually retry payout if needed

### Bot not responding?
- Check webhook is set correctly
- Verify commands are in `src/Commands/` folder
- Check error logs in server
- Test with /start command first

## 📈 Next Steps

### Enhancements to Consider
1. **Admin Panel**
   - View all bets
   - Monitor house balance
   - User statistics
   - Pending payouts

2. **Additional Commands**
   - `/history` - User's bet history
   - `/stats` - User's statistics
   - `/leaderboard` - Top winners

3. **Advanced Features**
   - Multi-bet (place multiple bets)
   - Auto-bet (repeat same bet N times)
   - Betting limits per user
   - VIP tiers with better odds

4. **Security Improvements**
   - Local transaction signing (no API)
   - Rate limiting by IP
   - Two-factor for large bets
   - Withdrawal limits

## 📞 Support

If you encounter issues:
1. Check BET_SYSTEM_IMPLEMENTATION.md for detailed docs
2. Review error logs on your server
3. Test database connection manually
4. Verify all files are uploaded correctly

## ✨ Success!

Your TRON Hash Lottery betting system is ready to use! 

Start testing with small amounts and verify everything works before going live.

**Happy betting! 🎲**

