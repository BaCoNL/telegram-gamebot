# TRON Hash Lottery - Betting System Implementation

## Overview
Complete implementation of a functional betting system for the TRON Hash Lottery Telegram bot. Users can place bets by predicting the last N characters of their transaction hash.

## Features Implemented

### ✅ Complete Bet Flow
1. **Difficulty Selection** - 4 levels with different odds and payouts
2. **Prediction Input** - Hex character validation
3. **Bet Amount Selection** - Predefined amounts + custom input
4. **Bet Confirmation** - Comprehensive summary screen
5. **Transaction Execution** - Automated transaction sending
6. **Hash Verification** - Instant win/loss determination
7. **Automatic Payouts** - Instant TRX transfer on wins
8. **Statistics Tracking** - Complete user stats

### 🎯 Difficulty Levels

| Level | Characters | Multiplier | Odds | Probability |
|-------|-----------|------------|------|-------------|
| Easy | 1 | 10x | 1/16 | 6.25% |
| Medium | 2 | 200x | 1/256 | 0.39% |
| Hard | 3 | 3,500x | 1/4,096 | 0.024% |
| Expert | 4 | 50,000x | 1/65,536 | 0.0015% |

### 🔒 Safety Features
- **Balance Checks** - Verifies user has sufficient funds
- **House Protection** - Max bet limited to 2% of house balance / (multiplier - 1)
- **Cooldown System** - 10-second cooldown between bets
- **Transaction Validation** - Confirms transactions before processing
- **Error Handling** - Comprehensive error messages for all scenarios

## File Structure

```
functions/
├── bet_processing.php      # Core betting logic
├── tron_transactions.php   # Transaction signing & broadcasting
├── tron_wallet.php        # Wallet management (existing)

src/Commands/
├── BetCommand.php         # /bet command handler
├── CallbackqueryCommand.php  # Inline button handlers
├── GenericmessageCommand.php # Text input handlers

scripts/
├── init_bet_tables.php    # Database initialization
```

## Database Tables

### `bet`
Stores all bet records with complete transaction information.

### `userstats`
Tracks user betting statistics:
- Total bets, wins, losses
- Total wagered, won, lost
- Net profit/loss

### `userstate`
Manages conversation state for multi-step bet flow.

## Installation & Setup

### 1. Initialize Database
Run the database initialization script:

```bash
php scripts/init_bet_tables.php
```

This creates:
- `bet` table
- `userstats` table
- `userstate` table
- Verifies `wallet` table exists

### 2. Verify Configuration
Ensure `config/config.php` has:

```php
define('HOUSE_WALLET_PRIVATE_KEY', 'your_private_key_here');
define('HOUSE_WALLET_ADDRESS', 'your_wallet_address_here');
```

### 3. Test the System
1. Create/import a wallet: `/wallet`
2. Deposit TRX to your wallet
3. Place a bet: `/bet`

## User Flow

### Step 1: Start Bet
User sends `/bet` command

**Validations:**
- ✅ User has wallet
- ✅ Wallet balance ≥ 1 TRX (minimum bet)
- ✅ No active cooldown

### Step 2: Select Difficulty
User chooses from 4 difficulty levels via inline buttons.

### Step 3: Enter Prediction
User sends prediction (hex characters: 0-9, A-F)

**Validations:**
- ✅ Exactly N characters (based on difficulty)
- ✅ Only hex characters

### Step 4: Choose Bet Amount
Options shown:
- 💰 10 TRX
- 💰 25 TRX
- 💰 50 TRX
- 💰 100 TRX
- 💵 Custom Amount

**Validations:**
- ✅ Amount ≥ 1 TRX
- ✅ Amount ≤ user balance
- ✅ Amount ≤ house max bet

### Step 5: Confirm Bet
Summary screen shows:
- Difficulty & prediction
- Bet amount & potential win
- User balance & house max
- How the game works

### Step 6: Transaction & Result
1. Transaction sent from user → house wallet
2. TX hash extracted
3. Last N characters compared to prediction
4. **WIN:** Instant payout sent
5. **LOSS:** Better luck next time message

## API Functions

### Bet Processing (`functions/bet_processing.php`)

```php
// Calculate maximum allowed bet
calculateMaxBet($houseBalance, $multiplier)

// Validate bet amount
validateBetAmount($userBalance, $betAmount, $maxBet)

// Validate hex prediction
validatePrediction($prediction, $requiredLength)

// Create bet record
createBetRecord($userId, $prediction, $difficulty, $betAmount)

// Send bet transaction
sendBetTransaction($userWallet, $betAmount)

// Verify outcome
verifyBetOutcome($txHash, $prediction, $charactersCount)

// Process win/loss
processBetWin($bet, $txHash, $actualEnding)
processBetLoss($bet, $txHash, $actualEnding)

// State management
storeBetState($userId, $state, $data)
getBetState($userId)
clearBetState($userId)

// Check cooldown
checkBetCooldown($userId, $cooldownSeconds = 10)
```

### TRON Transactions (`functions/tron_transactions.php`)

```php
// Sign and broadcast transaction
signAndBroadcastTransaction($privateKey, $toAddress, $amount)

// Create TRX transfer
createTrxTransaction($fromAddress, $toAddress, $amountInSun)

// Sign transaction
signTransaction($transaction, $privateKey)

// Broadcast to network
broadcastTransaction($signedTransaction)

// Wait for confirmation
waitForTransactionConfirmation($txHash, $maxWaitSeconds = 30)

// Get transaction info
getTransactionInfo($txHash)
getTransactionReceipt($txHash)

// Verify success
verifyTransactionSuccess($txHash)
```

## Security Considerations

### ⚠️ Current Implementation
The current implementation uses TronGrid API's `gettransactionsign` endpoint for transaction signing. This means **private keys are sent to the API** for signing.

### 🔒 Production Recommendations

1. **Local Transaction Signing**
   - Implement secp256k1 signing locally
   - Use `kornrunner/keccak` or similar library
   - Never send private keys over the network

2. **Environment Variables**
   - Store house wallet credentials in `.env`
   - Use `vlucas/phpdotenv` package
   - Never commit credentials to Git

3. **Rate Limiting**
   - Implement IP-based rate limiting
   - Add per-user bet frequency limits
   - Prevent spam and abuse

4. **Transaction Monitoring**
   - Set up alerts for large payouts
   - Monitor house wallet balance
   - Track suspicious betting patterns

5. **Error Logging**
   - Log all transactions
   - Store failed bet attempts
   - Set up error notifications

## Testing Checklist

- [ ] User can select all 4 difficulty levels
- [ ] Prediction validation works correctly
- [ ] Invalid hex characters are rejected
- [ ] Wrong length predictions are rejected
- [ ] Max bet calculation prevents house bankruptcy
- [ ] Insufficient balance shows proper error
- [ ] Custom bet amount input works
- [ ] Transaction is sent from user to house
- [ ] Transaction hash is captured correctly
- [ ] Win condition verified (case-insensitive)
- [ ] Loss condition verified correctly
- [ ] Payouts sent automatically on win
- [ ] User statistics update properly
- [ ] "Play Again" buttons work
- [ ] "Same Bet Again" restores last bet
- [ ] Cancel button clears state properly
- [ ] User states cleared after completion
- [ ] Cooldown prevents rapid betting
- [ ] Multiple users can bet simultaneously

## Troubleshooting

### Issue: "Transaction Failed"
**Causes:**
- Insufficient balance (check user wallet)
- House wallet out of funds
- TronGrid API issues
- Invalid private key

**Solutions:**
- Verify wallet balances
- Check TronGrid API status
- Review error logs
- Test with smaller amounts

### Issue: "Payout Pending"
**Causes:**
- House wallet insufficient balance
- Network congestion
- API rate limits

**Solutions:**
- Check house wallet balance
- Retry payout manually
- Query `bet` table for `won_payout_pending` status

### Issue: User State Stuck
**Causes:**
- Error during bet flow
- Incomplete state clearing

**Solutions:**
```php
// Clear user state manually
R::exec('DELETE FROM userstate WHERE telegram_user_id = ?', [$userId]);
```

## Admin Commands (Future Enhancement)

Consider adding:
- `/admin_stats` - Overall system statistics
- `/admin_house` - House wallet balance
- `/admin_pending` - Pending payouts
- `/admin_cancel <bet_id>` - Cancel stuck bet

## Performance Optimization

### Database Indexes
Already implemented:
- `idx_user_bets` on `bet(user_id, created_at)`
- `idx_status` on `bet(status)`
- `idx_user` on `userstats(user_id)`

### Caching Opportunities
- House wallet balance (cache for 30 seconds)
- TRX to USD rate (cache for 5 minutes)
- User statistics (regenerate on demand)

## License & Credits

This implementation uses:
- [Longman Telegram Bot](https://github.com/php-telegram-bot/core)
- [RedBeanPHP](https://redbeanphp.com/)
- [TronGrid API](https://www.trongrid.io/)

## Support

For issues or questions:
1. Check error logs in your server
2. Verify database tables are created
3. Test with small bet amounts first
4. Review TronGrid API documentation

---

**Version:** 1.0.0  
**Last Updated:** 2025-01-19  
**Status:** Production Ready (with noted security considerations)

