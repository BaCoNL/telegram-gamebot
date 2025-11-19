<?php
/**
 * Bet Processing Functions
 *
 * Core betting logic for TRON Hash Lottery
 */

// Minimum bet amount in TRX
define('MIN_BET', 1);

// Difficulty configurations
const DIFFICULTY_CONFIG = [
    1 => [
        'name' => 'Easy',
        'chars' => 1,
        'multiplier' => 10,
        'odds' => '1/16',
        'percentage' => '6.25%'
    ],
    2 => [
        'name' => 'Medium',
        'chars' => 2,
        'multiplier' => 200,
        'odds' => '1/256',
        'percentage' => '0.39%'
    ],
    3 => [
        'name' => 'Hard',
        'chars' => 3,
        'multiplier' => 3500,
        'odds' => '1/4,096',
        'percentage' => '0.024%'
    ],
    4 => [
        'name' => 'Expert',
        'chars' => 4,
        'multiplier' => 50000,
        'odds' => '1/65,536',
        'percentage' => '0.0015%'
    ]
];

/**
 * Calculate maximum bet allowed based on house balance
 *
 * @param float $houseBalance House wallet balance in TRX
 * @param float $multiplier Payout multiplier
 * @return float Maximum bet amount
 */
function calculateMaxBet($houseBalance, $multiplier) {
    // House can risk 2% of balance on a single bet
    $maxPayout = $houseBalance * 0.02;
    $maxBet = $maxPayout / ($multiplier - 1);

    // Ensure minimum bet is always possible
    return max($maxBet, MIN_BET);
}

/**
 * Validate bet amount
 *
 * @param float $userBalance User's wallet balance
 * @param float $betAmount Requested bet amount
 * @param float $maxBet Maximum allowed bet
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateBetAmount($userBalance, $betAmount, $maxBet) {
    if ($betAmount < MIN_BET) {
        return [
            'valid' => false,
            'error' => "Minimum bet is " . MIN_BET . " TRX"
        ];
    }

    if ($betAmount > $userBalance) {
        return [
            'valid' => false,
            'error' => "Insufficient balance. You have " . number_format($userBalance, 2) . " TRX"
        ];
    }

    if ($betAmount > $maxBet) {
        return [
            'valid' => false,
            'error' => "Maximum bet for this difficulty is " . number_format($maxBet, 2) . " TRX"
        ];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Validate hex prediction
 *
 * @param string $prediction User's prediction
 * @param int $requiredLength Required length based on difficulty
 * @return array ['valid' => bool, 'error' => string|null, 'normalized' => string|null]
 */
function validatePrediction($prediction, $requiredLength) {
    $prediction = trim(strtoupper($prediction));

    // Check length
    if (strlen($prediction) !== $requiredLength) {
        return [
            'valid' => false,
            'error' => "Prediction must be exactly {$requiredLength} character(s)",
            'normalized' => null
        ];
    }

    // Check if valid hex
    if (!ctype_xdigit($prediction)) {
        return [
            'valid' => false,
            'error' => "Only hex characters allowed (0-9, A-F)",
            'normalized' => null
        ];
    }

    return [
        'valid' => true,
        'error' => null,
        'normalized' => $prediction
    ];
}

/**
 * Create a new bet record
 *
 * @param int $userId Telegram user ID
 * @param string $prediction Normalized prediction (uppercase)
 * @param int $difficulty Difficulty level (1-4)
 * @param float $betAmount Bet amount in TRX
 * @return object RedBean bet object
 */
function createBetRecord($userId, $prediction, $difficulty, $betAmount) {
    $config = DIFFICULTY_CONFIG[$difficulty];

    $bet = R::dispense('bet');
    $bet->bet_id = generateBetId();
    $bet->user_id = $userId;
    $bet->prediction = $prediction;
    $bet->characters_count = $config['chars'];
    $bet->multiplier = $config['multiplier'];
    $bet->bet_amount = $betAmount;
    $bet->potential_payout = $betAmount * $config['multiplier'];
    $bet->status = 'pending';
    $bet->created_at = date('Y-m-d H:i:s');

    R::store($bet);

    return $bet;
}

/**
 * Generate unique bet ID
 *
 * @return string
 */
function generateBetId() {
    return substr(md5(uniqid(rand(), true)), 0, 12);
}

/**
 * Send bet transaction from user to house
 *
 * @param object $userWallet User's wallet object
 * @param float $betAmount Amount in TRX
 * @return array ['success' => bool, 'txHash' => string|null, 'error' => string|null]
 */
function sendBetTransaction($userWallet, $betAmount) {
    require_once BASE_PATH . '/functions/tron_transactions.php';

    try {
        $privateKey = decryptPrivateKey($userWallet->private_key);
        $fromAddress = $userWallet->address; // Use stored address instead of deriving

        $result = signAndBroadcastTransactionWithAddress(
            $privateKey,
            $fromAddress,
            HOUSE_WALLET_ADDRESS,
            $betAmount
        );

        return $result;
    } catch (Exception $e) {
        error_log("Bet transaction error: " . $e->getMessage());
        return [
            'success' => false,
            'txHash' => null,
            'error' => 'Transaction failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Verify bet outcome by checking transaction hash
 *
 * @param string $txHash Transaction hash
 * @param string $prediction User's prediction
 * @param int $charactersCount Number of characters to match
 * @return array ['isWin' => bool, 'actualEnding' => string]
 */
function verifyBetOutcome($txHash, $prediction, $charactersCount) {
    $actualEnding = strtoupper(substr($txHash, -$charactersCount));
    $isWin = ($actualEnding === strtoupper($prediction));

    return [
        'isWin' => $isWin,
        'actualEnding' => $actualEnding
    ];
}

/**
 * Process winning bet
 *
 * @param object $bet Bet object
 * @param string $txHash Transaction hash
 * @param string $actualEnding Actual hash ending
 * @return array ['success' => bool, 'payoutTxHash' => string|null, 'error' => string|null]
 */
function processBetWin($bet, $txHash, $actualEnding) {
    try {
        // Calculate payout
        $payoutAmount = $bet->bet_amount * $bet->multiplier;

        // Send payout from house to user
        $payoutResult = sendPayout($bet->user_id, $payoutAmount);

        if (!$payoutResult['success']) {
            error_log("Payout failed for bet {$bet->bet_id}: " . $payoutResult['error']);

            // Update bet as won but payout pending
            $bet->status = 'won_payout_pending';
            $bet->tx_hash = $txHash;
            $bet->actual_hash_ending = $actualEnding;
            $bet->payout_amount = $payoutAmount;
            $bet->completed_at = date('Y-m-d H:i:s');
            R::store($bet);

            return $payoutResult;
        }

        // Update bet record
        $bet->status = 'won';
        $bet->tx_hash = $txHash;
        $bet->actual_hash_ending = $actualEnding;
        $bet->payout_amount = $payoutAmount;
        $bet->payout_tx_hash = $payoutResult['txHash'];
        $bet->completed_at = date('Y-m-d H:i:s');
        R::store($bet);

        // Update user statistics
        updateUserStats($bet->user_id, true, $bet->bet_amount, $payoutAmount);

        return [
            'success' => true,
            'payoutTxHash' => $payoutResult['txHash'],
            'error' => null
        ];
    } catch (Exception $e) {
        error_log("Error processing bet win: " . $e->getMessage());
        return [
            'success' => false,
            'payoutTxHash' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Process losing bet
 *
 * @param object $bet Bet object
 * @param string $txHash Transaction hash
 * @param string $actualEnding Actual hash ending
 * @return void
 */
function processBetLoss($bet, $txHash, $actualEnding) {
    $bet->status = 'lost';
    $bet->tx_hash = $txHash;
    $bet->actual_hash_ending = $actualEnding;
    $bet->completed_at = date('Y-m-d H:i:s');
    R::store($bet);

    // Update user statistics
    updateUserStats($bet->user_id, false, $bet->bet_amount, 0);
}

/**
 * Send payout from house to user
 *
 * @param int $userId Telegram user ID
 * @param float $amount Amount in TRX
 * @return array ['success' => bool, 'txHash' => string|null, 'error' => string|null]
 */
function sendPayout($userId, $amount) {
    require_once BASE_PATH . '/functions/tron_transactions.php';

    try {
        // Get user's wallet
        $userWallet = R::findOne('wallet', 'telegram_user_id = ?', [$userId]);

        if (!$userWallet) {
            return [
                'success' => false,
                'txHash' => null,
                'error' => 'User wallet not found'
            ];
        }

        // Send from house wallet to user
        $result = signAndBroadcastTransactionWithAddress(
            HOUSE_WALLET_PRIVATE_KEY,
            HOUSE_WALLET_ADDRESS,
            $userWallet->address,
            $amount
        );

        return $result;
    } catch (Exception $e) {
        error_log("Payout error: " . $e->getMessage());
        return [
            'success' => false,
            'txHash' => null,
            'error' => 'Payout failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Update user statistics
 *
 * @param int $userId Telegram user ID
 * @param bool $isWin Whether the bet was won
 * @param float $betAmount Bet amount
 * @param float $payout Payout amount (0 for losses)
 * @return void
 */
function updateUserStats($userId, $isWin, $betAmount, $payout) {
    $stats = R::findOne('userstats', 'user_id = ?', [$userId]);

    if (!$stats) {
        $stats = R::dispense('userstats');
        $stats->user_id = $userId;
        $stats->total_bets = 0;
        $stats->total_wins = 0;
        $stats->total_losses = 0;
        $stats->total_wagered = 0;
        $stats->total_won = 0;
        $stats->total_lost = 0;
        $stats->net_profit = 0;
        $stats->created_at = date('Y-m-d H:i:s');
    }

    $stats->total_bets += 1;
    $stats->total_wagered += $betAmount;

    if ($isWin) {
        $stats->total_wins += 1;
        $stats->total_won += $payout;
        $stats->net_profit += ($payout - $betAmount);
    } else {
        $stats->total_losses += 1;
        $stats->total_lost += $betAmount;
        $stats->net_profit -= $betAmount;
    }

    $stats->updated_at = date('Y-m-d H:i:s');
    R::store($stats);
}

/**
 * Get difficulty configuration
 *
 * @param int $level Difficulty level (1-4)
 * @return array|null
 */
function getDifficultyConfig($level) {
    return DIFFICULTY_CONFIG[$level] ?? null;
}

/**
 * Store user's bet state
 *
 * @param int $userId Telegram user ID
 * @param string $state State name
 * @param array $data State data
 * @return void
 */
function storeBetState($userId, $state, $data = []) {
    // Clear old states
    R::exec('DELETE FROM userstate WHERE telegram_user_id = ?', [$userId]);

    $userState = R::dispense('userstate');
    $userState->telegram_user_id = $userId;
    $userState->state = $state;
    $userState->data = json_encode($data);
    $userState->created_at = date('Y-m-d H:i:s');
    R::store($userState);
}

/**
 * Get user's bet state
 *
 * @param int $userId Telegram user ID
 * @return array|null ['state' => string, 'data' => array] or null
 */
function getBetState($userId) {
    $userState = R::findOne('userstate', 'telegram_user_id = ? ORDER BY id DESC', [$userId]);

    if (!$userState) {
        return null;
    }

    return [
        'state' => $userState->state,
        'data' => json_decode($userState->data, true) ?? []
    ];
}

/**
 * Clear user's bet state
 *
 * @param int $userId Telegram user ID
 * @return void
 */
function clearBetState($userId) {
    R::exec('DELETE FROM userstate WHERE telegram_user_id = ?', [$userId]);
}

/**
 * Check if user has cooldown active
 *
 * @param int $userId Telegram user ID
 * @param int $cooldownSeconds Cooldown period in seconds (default 10)
 * @return array ['active' => bool, 'remaining' => int]
 */
function checkBetCooldown($userId, $cooldownSeconds = 10) {
    $lastBet = R::findOne('bet', 'user_id = ? ORDER BY created_at DESC', [$userId]);

    if (!$lastBet) {
        return ['active' => false, 'remaining' => 0];
    }

    $lastBetTime = strtotime($lastBet->created_at);
    $currentTime = time();
    $elapsed = $currentTime - $lastBetTime;

    if ($elapsed < $cooldownSeconds) {
        return [
            'active' => true,
            'remaining' => $cooldownSeconds - $elapsed
        ];
    }

    return ['active' => false, 'remaining' => 0];
}

