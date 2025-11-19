<?php
/**
 * Database Initialization Script for Hash Lottery Betting System
 *
 * Run this script to create/update the necessary database tables
 */

require_once __DIR__ . '/../bootstrap.php';

echo "Starting database initialization...\n\n";

// Use PDO directly to avoid RedBeanPHP issues
try {
    $pdo = new PDO(
        'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DBNAME,
        MYSQL_USER,
        MYSQL_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your config/config.php database settings.\n";
    exit(1);
}

try {
    // Create bet table
    echo "Creating 'bet' table...\n";
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 'bet' table created/verified\n\n";

    // Create userstats table
    echo "Creating 'userstats' table...\n";
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 'userstats' table created/verified\n\n";

    // Create userstate table (if not exists)
    echo "Creating 'userstate' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS userstate (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_user_id BIGINT NOT NULL,
            state VARCHAR(50) NOT NULL,
            data TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            
            INDEX idx_user_state (telegram_user_id, state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ 'userstate' table created/verified\n\n";

    // Verify wallet table exists
    echo "Verifying 'wallet' table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wallet'");
    if ($stmt->rowCount() == 0) {
        echo "Creating 'wallet' table...\n";
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    echo "✓ 'wallet' table verified\n\n";

    echo "✅ Database initialization complete!\n\n";
    echo "Summary of tables:\n";
    echo "- bet: Stores all bet records\n";
    echo "- userstats: Tracks user statistics\n";
    echo "- userstate: Manages user conversation states\n";
    echo "- wallet: Stores user wallet information\n\n";

    echo "You can now use the betting system with /bet command!\n";

} catch (PDOException $e) {
    echo "❌ Error during database initialization:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

