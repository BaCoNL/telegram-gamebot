-- TRON Hash Lottery Database Tables
-- Run this script to create all required tables

-- Bet table - Stores all bet records
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

-- User statistics table - Tracks betting statistics per user
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

-- User state table - Manages conversation state for multi-step flows
CREATE TABLE IF NOT EXISTS userstate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL,
    state VARCHAR(50) NOT NULL,
    data TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,

    INDEX idx_user_state (telegram_user_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wallet table - Stores user wallet information (create if not exists)
CREATE TABLE IF NOT EXISTS wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT NOT NULL UNIQUE,
    address VARCHAR(50) NOT NULL UNIQUE,
    private_key TEXT NOT NULL,
    trx_balance DECIMAL(20,6) DEFAULT 0,
    usd_balance DECIMAL(20,6) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,

    INDEX idx_user (telegram_user_id),
    INDEX idx_address (address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

