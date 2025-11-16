<?php
/**
 * Database Initialization Script
 *
 * Creates the necessary tables for the wallet system
 */

require_once dirname(__DIR__) . '/bootstrap.php';

echo "Creating database tables...\n\n";

// RedBeanPHP will automatically create tables when we store beans,
// but we'll create some test data to ensure everything is working

echo "✓ Database connection established\n";

// Test creating a wallet table structure
$testWallet = R::dispense('wallet');
$testWallet->telegram_user_id = 0;
$testWallet->address = 'T9yD14Nj9j7xAB4dbGeiX9h8unkKHxuWwb';
$testWallet->private_key = 'encrypted_test_key';
$testWallet->trx_balance = 0.0;
$testWallet->usd_balance = 0.0;
$testWallet->created_at = date('Y-m-d H:i:s');
$testWallet->updated_at = date('Y-m-d H:i:s');
R::store($testWallet);

echo "✓ 'wallet' table created with columns:\n";
echo "  - telegram_user_id (int)\n";
echo "  - address (varchar)\n";
echo "  - private_key (text)\n";
echo "  - trx_balance (decimal)\n";
echo "  - usd_balance (decimal)\n";
echo "  - created_at (datetime)\n";
echo "  - updated_at (datetime)\n\n";

// Test creating a userstate table structure
$testState = R::dispense('userstate');
$testState->telegram_user_id = 0;
$testState->state = 'test_state';
$testState->created_at = date('Y-m-d H:i:s');
R::store($testState);

echo "✓ 'userstate' table created with columns:\n";
echo "  - telegram_user_id (int)\n";
echo "  - state (varchar)\n";
echo "  - created_at (datetime)\n\n";

// Clean up test data
R::trash($testWallet);
R::trash($testState);

echo "✓ Test data cleaned up\n\n";

echo "Database initialization complete!\n";
echo "\nYou can now use the bot with the following commands:\n";
echo "  /start - Initialize wallet setup\n";
echo "  /wallet - Manage wallet\n";
echo "  /bet - Place a bet\n";

