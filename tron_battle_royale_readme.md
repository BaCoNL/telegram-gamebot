# TRON Battle Royale

> The first blockchain-powered Battle Royale game on Telegram using TRON block hashes for provably fair gameplay

[![TRON](https://img.shields.io/badge/TRON-FF0013?style=for-the-badge&logo=tron&logoColor=white)](https://tron.network)
[![Telegram](https://img.shields.io/badge/Telegram-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white)](https://telegram.org)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)

## 🎮 Game Overview

TRON Battle Royale is a fast-paced, multiplayer survival game where 10-100 players compete for a winner-takes-most prize pool. The game leverages TRON blockchain block hashes to create truly random, provably fair game mechanics where no one—not even the house—can predict or manipulate outcomes.

### Core Concept

- **Players enter** by paying an entry fee (5-50 TRX)
- **Prize pool accumulates** (90% of entries, 10% house fee)
- **Game runs for 10-15 rounds** (~30-45 seconds at 3s/block)
- **Each TRON block** triggers game events (eliminations, zone changes, combat)
- **Block hashes determine outcomes** (completely random and verifiable)
- **Last survivors win** (50% / 30% / 20% prize split)

## 🎯 How It Works

### Game Flow

```
1. LOBBY PHASE (60s)
   ├─ Players join and pay entry fee
   ├─ Choose starting zone (North/South/East/West/Center)
   ├─ Minimum 10 players required
   └─ Game starts automatically when ready

2. GAME PHASE (10-15 rounds)
   ├─ Each TRON block = 1 game round (~3 seconds)
   ├─ Block hash determines random events
   ├─ Players choose actions: Move/Attack/Heal/Hide
   ├─ Storm shrinks safe zones each round
   ├─ Combat resolved via hash comparisons
   └─ Eliminations reduce player count

3. END PHASE
   ├─ Last 3 players remain
   ├─ Prize distribution (50%/30%/20%)
   ├─ Automatic TRX payouts
   └─ Statistics recorded
```

### Block Hash Mechanics

Every TRON block hash determines multiple game elements:

```
Block Hash Example: 0000000002b8e5f8a1234567890abcdef1234567890abcdef...

Characters 10-11 (hex): Zone Damage
├─ 00-3F: North zone takes storm damage
├─ 40-7F: East zone takes storm damage
├─ 80-BF: South zone takes storm damage
└─ C0-FF: West zone takes storm damage

Characters 12-13 (hex): Event Type
├─ 00-3F: Weapon spawn
├─ 40-7F: Supply drop (healing items)
├─ 80-BF: Healing zone appears
└─ C0-FF: Storm intensifies (extra damage)

Characters 14-15 (hex): Combat Modifier
├─ Higher value = Offensive advantage
├─ Lower value = Defensive advantage
└─ Matching pairs = Critical hit event

Last 2 Characters: Movement & Tiebreakers
├─ Determines movement success/failure
├─ Loot quality for items found
└─ Random tiebreaker for equal scenarios
```

### Combat System

When players attack each other, their **entry transaction hashes** are compared:

```php
Player A attacks Player B:

Player A TX Hash: ...8C2A
Player B TX Hash: ...3F19

Comparison: 0x8C2A > 0x3F19
Result: Player A hits Player B
Damage: (0x8C2A - 0x3F19) / 100 = ~195 / 100 = ~20 HP damage

If Player B has weapon (+2x multiplier):
└─ Effective defense, reduced damage

If block hash ends in matching pair (AA, BB, etc):
└─ Critical hit: 1.5x damage multiplier
```

## 🏗️ Architecture

### Tech Stack

- **Backend**: PHP 8.x
- **Database**: MySQL 8.x / PostgreSQL
- **Blockchain**: TRON (TronGrid API)
- **Bot**: Telegram Bot API
- **Server**: Linux VPS
- **Queue**: Cron-based job scheduler

### System Components

```
┌─────────────────────────────────────────────┐
│         Telegram Bot (Webhook/Polling)      │
│  Handles user commands & game interactions  │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│           Game Logic Engine                 │
│  - Lobby management                         │
│  - Round processing                         │
│  - Combat resolution                        │
│  - Prize distribution                       │
└──────────────────┬──────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
┌───────▼────────┐   ┌────────▼─────────┐
│   Database     │   │  TRON Blockchain │
│  - Users       │   │  - Block hashes  │
│  - Games       │   │  - Transactions  │
│  - Bets        │   │  - Payouts       │
│  - Stats       │   │  - Wallet mgmt   │
└────────────────┘   └──────────────────┘
```

## 📋 Development Roadmap

### Phase 1: Core Infrastructure (Week 1)

**Goal**: Basic foundation working

- [ ] **Day 1-2: Database Schema**
  - [ ] Create `users` table (telegram_id, tron_address, private_key_encrypted, stats)
  - [ ] Create `games` table (game_id, status, start_block, entry_fee, prize_pool)
  - [ ] Create `players` table (game_id, user_id, zone, hp, status, placement)
  - [ ] Create `game_events` table (game_id, round, block_number, block_hash, event_data)
  - [ ] Create `transactions` table (tx_hash, user_id, type, amount, status)

- [ ] **Day 3-4: TRON Integration**
  - [ ] TronGrid API client class
  - [ ] Wallet generation (one per user)
  - [ ] Private key encryption/storage
  - [ ] Transaction monitoring function
  - [ ] Balance checking
  - [ ] Transaction signing & broadcasting

- [ ] **Day 5: Telegram Bot Setup**
  - [ ] Bot registration via @BotFather
  - [ ] Webhook/polling setup
  - [ ] Basic command routing (/start, /help, /balance)
  - [ ] User registration flow
  - [ ] Wallet address display

**Deliverable**: Users can register, get a TRON wallet, check balance

---

### Phase 2: Game Mechanics (Week 2)

**Goal**: Complete game loop functioning

- [ ] **Day 1-2: Lobby System**
  - [ ] Game creation & lobby management
  - [ ] Player join functionality
  - [ ] Entry fee payment verification
  - [ ] Zone selection
  - [ ] Countdown timer (60 seconds)
  - [ ] Auto-start when minimum players reached

- [ ] **Day 3-4: Round Processing**
  - [ ] Cron job to monitor TRON blocks
  - [ ] Block hash fetching & parsing
  - [ ] Round state machine
  - [ ] Zone damage calculation
  - [ ] Storm shrinking logic
  - [ ] Player elimination tracking

- [ ] **Day 5: Combat System**
  - [ ] Attack command handling
  - [ ] Hash comparison logic
  - [ ] Damage calculation
  - [ ] HP tracking
  - [ ] Elimination detection
  - [ ] Winner determination

**Deliverable**: One complete game can run from start to finish

---

### Phase 3: Actions & Features (Week 3)

**Goal**: Full gameplay experience

- [ ] **Day 1: Player Actions**
  - [ ] `/move [zone]` - Movement between zones
  - [ ] `/attack [player]` - Combat initiation
  - [ ] `/heal` - Use healing items
  - [ ] `/hide` - Defensive positioning
  - [ ] `/loot` - Search for items
  - [ ] Action validation & cooldowns

- [ ] **Day 2: Items & Power-ups**
  - [ ] Weapon system (pistol, shotgun, rifle)
  - [ ] Armor/shields
  - [ ] Med kits
  - [ ] Item inventory per player
  - [ ] Loot spawn based on block hash

- [ ] **Day 3-4: Payout System**
  - [ ] Winner calculation (top 3 players)
  - [ ] Prize split (50% / 30% / 20%)
  - [ ] Automatic TRX transfer
  - [ ] Transaction confirmation
  - [ ] Failure handling & retry logic

- [ ] **Day 5: User Experience**
  - [ ] Real-time game updates
  - [ ] Player status display
  - [ ] Death/elimination messages
  - [ ] Victory celebration
  - [ ] Statistics display

**Deliverable**: Full-featured game with all actions working

---

### Phase 4: Safety & Admin (Week 4)

**Goal**: Production-ready system

- [ ] **Day 1-2: Safety Systems**
  - [ ] Minimum/maximum bet limits
  - [ ] House wallet balance monitoring
  - [ ] Hot wallet auto-refill
  - [ ] Failsafe mode (suspend when low balance)
  - [ ] Rate limiting (per user, global)
  - [ ] Anti-spam measures

- [ ] **Day 3: Admin Dashboard**
  - [ ] Web-based admin panel
  - [ ] Active games monitoring
  - [ ] Pending payouts view
  - [ ] User management
  - [ ] Manual game control (cancel, refund)
  - [ ] System status toggle

- [ ] **Day 4: Testing**
  - [ ] Shasta testnet deployment
  - [ ] End-to-end game testing
  - [ ] Edge case scenarios
  - [ ] Load testing (multiple games)
  - [ ] Payout verification

- [ ] **Day 5: Launch Prep**
  - [ ] Mainnet deployment
  - [ ] Documentation finalization
  - [ ] Beta user invites
  - [ ] Monitoring & alerting setup
  - [ ] Emergency procedures documented

**Deliverable**: Production-ready beta launch

---

## 💾 Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telegram_id BIGINT UNIQUE NOT NULL,
    telegram_username VARCHAR(255),
    tron_address VARCHAR(34) UNIQUE NOT NULL,
    private_key_encrypted TEXT NOT NULL,
    total_games INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_earnings DECIMAL(20,6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_tron_address (tron_address)
);
```

### Games Table
```sql
CREATE TABLE games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(36) UNIQUE NOT NULL,
    status ENUM('lobby', 'active', 'finished', 'cancelled') DEFAULT 'lobby',
    entry_fee DECIMAL(20,6) NOT NULL,
    prize_pool DECIMAL(20,6) DEFAULT 0,
    house_fee DECIMAL(20,6) DEFAULT 0,
    start_block BIGINT,
    current_round INT DEFAULT 0,
    max_rounds INT DEFAULT 12,
    min_players INT DEFAULT 10,
    max_players INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_game_id (game_id)
);
```

### Players Table
```sql
CREATE TABLE players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(36) NOT NULL,
    user_id INT NOT NULL,
    entry_tx_hash VARCHAR(64),
    zone ENUM('north', 'south', 'east', 'west', 'center') NOT NULL,
    hp INT DEFAULT 100,
    armor INT DEFAULT 0,
    weapon VARCHAR(50) DEFAULT NULL,
    is_alive BOOLEAN DEFAULT TRUE,
    placement INT NULL,
    kills INT DEFAULT 0,
    damage_dealt INT DEFAULT 0,
    prize_amount DECIMAL(20,6) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminated_at TIMESTAMP NULL,
    FOREIGN KEY (game_id) REFERENCES games(game_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_game_alive (game_id, is_alive),
    INDEX idx_user_games (user_id, game_id)
);
```

### Game Events Table
```sql
CREATE TABLE game_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id VARCHAR(36) NOT NULL,
    round_number INT NOT NULL,
    block_number BIGINT NOT NULL,
    block_hash VARCHAR(64) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(game_id),
    INDEX idx_game_round (game_id, round_number)
);
```

---

## 🔐 Security Considerations

### Private Key Management
- **Encryption**: AES-256-CBC encryption for all private keys
- **Key derivation**: Use PBKDF2 or Argon2 for encryption keys
- **Storage**: Environment variable for master encryption key
- **Access**: Never log or expose private keys
- **Backup**: Encrypted backups stored separately

### Wallet Architecture
```
User Wallets (Hot)
├─ Generated per user
├─ Encrypted private keys in DB
└─ Used for entry fees only

House Wallet (Receives bets)
├─ Collects all entry fees
├─ 15% of total bankroll
└─ Swept to cold storage daily

Hot Wallet (Payouts)
├─ Handles winner payouts
├─ 5% of total bankroll
└─ Auto-refilled from house wallet

Cold Wallet (Storage)
├─ 80% of total bankroll
├─ Offline storage
└─ Manual transfers only
```

### Rate Limiting
- Max 5 games per user per hour
- Max 1 action per 3 seconds per user
- Max 100 concurrent games globally
- IP-based rate limiting for bot commands

---

## 📊 Game Economics

### Prize Distribution

**Entry Fee Structure**:
```
Example: 20 players × 10 TRX entry

Total Collected: 200 TRX
House Fee (10%): 20 TRX
Prize Pool (90%): 180 TRX

Payouts:
1st Place: 90 TRX (50% of pool)
2nd Place: 54 TRX (30% of pool)
3rd Place: 36 TRX (20% of pool)
```

### Revenue Projections

**Conservative** (10 games/day, 20 players avg, 10 TRX entry):
```
Daily: 10 × 20 × 10 × 0.10 = 200 TRX (~$32)
Monthly: 6,000 TRX (~$960)
```

**Moderate** (50 games/day, 30 players avg, 15 TRX entry):
```
Daily: 50 × 30 × 15 × 0.10 = 2,250 TRX (~$360)
Monthly: 67,500 TRX (~$10,800)
```

**Optimistic** (100 games/day, 40 players avg, 20 TRX entry):
```
Daily: 100 × 40 × 20 × 0.10 = 8,000 TRX (~$1,280)
Monthly: 240,000 TRX (~$38,400)
```

---

## 🚀 Deployment

### Server Requirements
- **OS**: Ubuntu 20.04+ or similar
- **RAM**: 4GB minimum (8GB recommended)
- **Storage**: 50GB SSD
- **PHP**: 8.x with extensions (mysqli, curl, openssl, json)
- **Database**: MySQL 8.x or PostgreSQL 13+
- **Cron**: For block monitoring (every 3 seconds)

### Environment Variables
```bash
# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/webhook

# TRON
TRONGRID_API_KEY=your_trongrid_key
TRON_NETWORK=mainnet  # or shasta for testnet
HOUSE_WALLET_ADDRESS=TXabc123...
HOUSE_WALLET_PRIVATE_KEY=your_encrypted_key
HOT_WALLET_ADDRESS=TXdef456...
HOT_WALLET_PRIVATE_KEY=your_encrypted_key

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=tron_battle_royale
DB_USER=your_db_user
DB_PASS=your_db_password

# Security
ENCRYPTION_KEY=your_32_char_encryption_key
APP_ENV=production
DEBUG_MODE=false

# Game Settings
MIN_PLAYERS=10
MAX_PLAYERS=100
DEFAULT_ENTRY_FEE=10
HOUSE_FEE_PERCENT=10
MIN_BANKROLL_TRX=50000
```

### Installation Steps

```bash
# Clone repository
git clone https://github.com/yourusername/tron-battle-royale.git
cd tron-battle-royale

# Install dependencies
composer install

# Configure environment
cp .env.example .env
nano .env  # Edit with your values

# Database setup
mysql -u root -p < database/schema.sql

# Set permissions
chmod 600 .env
chmod 700 storage/keys/

# Test TRON connection
php artisan tron:test

# Set up cron job
crontab -e
# Add: */3 * * * * php /path/to/artisan game:process-rounds
```

---

## 🧪 Testing

### Shasta Testnet

Before mainnet launch, thoroughly test on Shasta:

```bash
# Switch to testnet
export TRON_NETWORK=shasta

# Get testnet TRX
# Visit: https://www.trongrid.io/shasta/

# Run test game
php artisan game:test --players=5 --rounds=5

# Verify payouts
php artisan test:payouts
```

### Test Scenarios
- [ ] Single player game (should not start)
- [ ] Minimum players game (10 players)
- [ ] Maximum players game (100 players)
- [ ] Mid-game disconnect handling
- [ ] Simultaneous eliminations
- [ ] Payout failures & retries
- [ ] Low bankroll failsafe trigger
- [ ] Invalid transaction handling

---

## 📱 Bot Commands

### Player Commands
```
/start - Register and get your TRON wallet
/help - Show game rules and commands
/balance - Check your wallet balance
/stats - View your game statistics
/join - Join the next game
/play [entry_amount] - Create/join a game with specific entry fee
/lobby - View current lobby status
/quit - Leave current lobby (before game starts)
```

### In-Game Commands
```
/status - View your current game status
/move [zone] - Move to different zone (north/south/east/west/center)
/attack [player_number] - Attack another player in your zone
/heal - Use healing item (if you have one)
/hide - Take defensive position
/loot - Search for items
/zones - See all zone populations
```

### Admin Commands
```
/admin - Access admin panel
/games - List all active games
/cancel [game_id] - Cancel a game and refund players
/suspend - Enable maintenance mode
/resume - Disable maintenance mode
/stats_global - View platform statistics
```

---

## 🎨 Future Enhancements

### Phase 5 (Post-Launch)
- [ ] Leaderboards (daily, weekly, all-time)
- [ ] Player profiles with badges/achievements
- [ ] Referral system (invite friends, earn bonus)
- [ ] Team mode (2v2, squads)
- [ ] Custom game lobbies (private games)
- [ ] Spectator mode for eliminated players
- [ ] Tournament system with scheduled events

### Phase 6 (Multi-Chain)
- [ ] TON blockchain integration
- [ ] Solana version
- [ ] Cross-chain leaderboards
- [ ] Multi-chain tournaments

### Phase 7 (Advanced Features)
- [ ] NFT integration (custom skins, weapons)
- [ ] Season passes & battle passes
- [ ] Clan/guild system
- [ ] In-game chat during matches
- [ ] Replay system
- [ ] Mobile app (React Native)

---

## 🤝 Contributing

This is a private project during development. After beta launch, contributions will be welcome.

### Development Team
- **Lead Developer**: [Your Name] - Former Tronscan Core Dev
- **Backend Developer**: [Team Member 2]
- **Bot Developer**: [Team Member 3]

---

## 📄 License

Proprietary - All rights reserved during beta phase.

Open source license TBD after successful launch.

---

## 🔗 Links

- **Website**: Coming soon
- **Telegram Bot**: [@TronBattleRoyaleBot](https://t.me/yourbotname) (Coming soon)
- **Telegram Community**: [t.me/tronbattleroyale](https://t.me/yourchannel) (Coming soon)
- **Twitter**: [@TronBattleRoyal](https://twitter.com/yourhandle) (Coming soon)

---

## 📞 Support

For beta testing inquiries or partnership opportunities:
- Email: contact@yourdomain.com
- Telegram: @yourusername

---

## ⚠️ Disclaimer

This is a game of skill and chance. Players must be 18+ and comply with local gambling regulations. The game is provably fair - all outcomes are determined by TRON blockchain block hashes which cannot be manipulated. Play responsibly.

**Legal Notice**: This project is in beta. Cryptocurrency gambling may be regulated or prohibited in your jurisdiction. Users are responsible for understanding and complying with their local laws.

---

## 🎮 Why TRON?

Built on TRON by a former Tronscan core developer because:
- ⚡ 3-second block times = fast-paced gameplay
- 💰 Near-zero transaction fees = more prize money for players
- 🔒 High throughput = scales to thousands of players
- 🌐 Large ecosystem = built-in community
- 🛠️ Mature tooling = reliable infrastructure

---

**Built with ❤️ for the TRON community**

*First blockchain Battle Royale. Provably fair. Transparently random. Actually fun.*