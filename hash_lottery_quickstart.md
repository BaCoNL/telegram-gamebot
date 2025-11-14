# TRON Hash Lottery - Quick Start

> Predict your transaction hash endings, win up to 50,000x your bet

## 🎯 What Is This?

A provably fair lottery where players predict the last characters of their own transaction hash. If they guess correctly, they win big. The blockchain's randomness makes it impossible to cheat.

**Example**: Player predicts "ABC", sends 10 TRX, transaction hash ends in "...abc" → Wins 35,000 TRX (3500x payout)

## 🎮 Game Flow

```
1. Player: /bet → Choose difficulty (1-4 characters)
2. Player: Enter prediction (e.g., "7F")
3. Bot: "Send TRX to: TXabc123..."
4. Player: Sends bet amount
5. Bot: Checks hash → Match? → Win! / No match → Try again
```

## 💰 Payouts

| Difficulty | Odds | Payout | Example |
|------------|------|--------|---------|
| 1 char | 1/16 | 10x | Bet 100 TRX → Win 1,000 TRX |
| 2 chars | 1/256 | 200x | Bet 10 TRX → Win 2,000 TRX |
| 3 chars | 1/4,096 | 3,500x | Bet 5 TRX → Win 17,500 TRX |
| 4 chars | 1/65,536 | 50,000x | Bet 1 TRX → Win 50,000 TRX |

## 🏗️ Tech Stack

- **Backend**: PHP 8+
- **Database**: MySQL
- **Blockchain**: TRON (TronGrid API)
- **Bot**: Telegram Bot API
- **Cron**: Every 10 seconds (transaction monitoring)

## 📋 Build Checklist (3 Weeks)

### Week 1: Foundation
- [ ] Setup database (users, bets, transactions tables)
- [ ] TRON integration (wallet generation, transaction monitoring)
- [ ] Basic Telegram bot (/start, /balance, /address)
- [ ] User registration & wallet creation

### Week 2: Core Game
- [ ] `/bet` command (choose difficulty, enter prediction)
- [ ] Transaction monitoring cron job
- [ ] Hash verification (extract last N chars, compare)
- [ ] Automatic payouts (send TRX to winner)
- [ ] Win/loss notifications

### Week 3: Polish & Safety
- [ ] Dynamic bet limits (protect bankroll)
- [ ] Failsafe system (suspend if low balance)
- [ ] User stats & history
- [ ] Admin dashboard
- [ ] Shasta testnet testing
- [ ] Mainnet deployment

## 💾 Core Database Tables

**Users**: `telegram_id`, `tron_address`, `encrypted_private_key`, `stats`

**Bets**: `user_id`, `prediction`, `characters_count`, `bet_amount`, `tx_hash`, `status` (pending/won/lost)

**Transactions**: `tx_hash`, `user_id`, `type` (bet/payout), `amount`, `status`

**System**: `is_active`, `house_balance`, `min_balance_threshold`

## 🔐 Wallet Architecture

```
User Wallets → House Wallet (80%) → Hot Wallet (20%) → Payouts
               (receives bets)       (automated payouts)
```

**Bankroll needed**: 50,000-100,000 TRX (~$8k-16k)

## 🎛️ Key Configuration

```bash
# .env file
TELEGRAM_BOT_TOKEN=your_bot_token
TRONGRID_API_KEY=your_api_key
TRON_NETWORK=mainnet  # or shasta for testing

HOUSE_WALLET_ADDRESS=TXabc...
HOT_WALLET_PRIVATE_KEY=encrypted_key

MIN_BET=1              # TRX
RISK_PERCENT=2         # Max 2% of bankroll per bet
BET_TIMEOUT=10         # Minutes

MIN_HOUSE_BALANCE=50000
MIN_HOT_WALLET=2000
```

## 🚀 Quick Installation

```bash
# 1. Clone & install
git clone <repo>
composer install

# 2. Configure
cp .env.example .env
# Edit .env with your values

# 3. Database
mysql -u root -p < database/schema.sql

# 4. Test on Shasta first
TRON_NETWORK=shasta
php artisan tron:test

# 5. Set up cron (transaction monitoring)
*/10 * * * * php /path/to/cron/monitor.php

# 6. Launch bot
php artisan bot:start
```

## 📱 Essential Bot Commands

**Player**:
- `/start` - Register & get wallet
- `/bet` - Start new bet
- `/balance` - Check balance
- `/stats` - View your stats

**Admin**:
- `/admin` - Dashboard
- `/suspend` - Maintenance mode
- `/refund [bet_id]` - Manual refund

## ⚡ How Hash Verification Works

```php
// 1. Player bets, predicts "F2A"
$prediction = "F2A"; // 3 characters
$bet_amount = 10; // TRX

// 2. Player sends TRX, transaction confirmed
$tx_hash = "a1b2c3d4e5f6789012345f2a"; // from blockchain

// 3. Extract last 3 characters
$actual = strtoupper(substr($tx_hash, -3)); // "F2A"

// 4. Compare
if ($actual === strtoupper($prediction)) {
    // WIN! Pay 3500x
    $payout = $bet_amount * 3500; // 35,000 TRX
    sendPayout($user, $payout);
} else {
    // LOSE - house keeps bet
    notifyLoss($user);
}
```

## 💡 Revenue Model

**House Edge Built Into Payouts**:
- 1 char: 37.5% edge (16x odds, 10x payout)
- 2 char: 21.9% edge (256x odds, 200x payout)
- 3 char: 14.5% edge (4096x odds, 3500x payout)

**Expected Daily Revenue** (moderate):
- 200 bets/day × 30 TRX avg × 20% edge = ~1,200 TRX/day
- Monthly: ~36,000 TRX (~$5,760)

## ✅ Testing Checklist

**Before mainnet launch**:
- [ ] Test all 4 difficulty levels on Shasta
- [ ] Verify win payouts work
- [ ] Verify loss handling works
- [ ] Test timeout refunds
- [ ] Test with multiple users simultaneously
- [ ] Confirm bet limits protect bankroll
- [ ] Test failsafe triggers correctly
- [ ] Run for 48 hours on Shasta with fake users

## 🎯 Why Build This First?

**Foundation for Battle Royale**:
- ✅ User registration system → Reuse 100%
- ✅ Wallet management → Reuse 100%
- ✅ Transaction monitoring → Reuse 100%
- ✅ Telegram bot basics → Reuse 95%
- ✅ Database patterns → Adapt easily
- ✅ Admin tools → Adapt easily

**Then upgrade to Battle Royale with confidence!**

## 🔗 Next Steps

1. **Week 1**: Build foundation (this project)
2. **Week 2**: Complete game loop
3. **Week 3**: Test & launch
4. **Week 4+**: Battle Royale using this codebase

## ⚠️ Legal

- 18+ only
- Comply with local gambling laws
- Provably fair (blockchain-based randomness)
- Play responsibly

---

**Get started**: `composer create-project tron-hash-lottery`

**Questions?** Check full README.md for detailed documentation.

*Built by former Tronscan core dev*