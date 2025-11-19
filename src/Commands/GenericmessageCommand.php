<?php
/**
 * Generic message command
 *
 * Handles messages that aren't commands (for wallet import, etc.)
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;

class GenericmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'genericmessage';

    /**
     * @var string
     */
    protected $description = 'Handle generic messages';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Command execute method
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $user_id = $message->getFrom()->getId();
        $chat_id = $message->getChat()->getId();
        $text = $message->getText(true);

        require_once BASE_PATH . '/functions/bet_processing.php';

        // Check if user has a pending state
        $state = getBetState($user_id);

        if (!$state) {
            return Request::emptyResponse();
        }

        // Handle different states
        switch ($state['state']) {
            case 'awaiting_private_key':
                return $this->handlePrivateKeyImport($user_id, $chat_id, $text, $message->getMessageId());

            case 'awaiting_prediction':
                return $this->handlePredictionInput($user_id, $chat_id, $text, $state['data']);

            case 'awaiting_bet_amount':
                return $this->handleCustomBetAmount($user_id, $chat_id, $text, $state['data']);
        }

        return Request::emptyResponse();
    }

    /**
     * Handle prediction input
     */
    private function handlePredictionInput($user_id, $chat_id, $prediction, $stateData): ServerResponse
    {
        require_once BASE_PATH . '/functions/bet_processing.php';
        require_once BASE_PATH . '/functions/tron_wallet.php';

        $difficulty = $stateData['difficulty'];
        $requiredChars = $stateData['chars'];
        $config = getDifficultyConfig($difficulty);

        // Validate prediction
        $validation = validatePrediction($prediction, $requiredChars);

        if (!$validation['valid']) {
            $text = "❌ *Invalid Prediction*\n\n";
            $text .= $validation['error'] . "\n\n";
            $text .= "Please try again or send /cancel to abort.";

            return $this->replyToChat($text, ['parse_mode' => 'Markdown']);
        }

        $normalizedPrediction = $validation['normalized'];

        // Store prediction in state
        storeBetState($user_id, 'awaiting_bet_amount', [
            'difficulty' => $difficulty,
            'prediction' => $normalizedPrediction
        ]);

        // Get wallet balance and calculate max bet
        $wallet = getUserWallet($user_id);
        updateWalletBalance($wallet);

        $houseBalance = getTrxBalance(HOUSE_WALLET_ADDRESS);
        $maxBet = calculateMaxBet($houseBalance, $config['multiplier']);

        // Ensure user has enough balance
        if ($wallet->trx_balance < MIN_BET) {
            clearBetState($user_id);

            $text = "❌ *Insufficient Balance*\n\n";
            $text .= "Your balance: " . number_format($wallet->trx_balance, 2) . " TRX\n";
            $text .= "Minimum bet: " . MIN_BET . " TRX";

            return $this->replyToChat($text, ['parse_mode' => 'Markdown']);
        }

        // Show bet amount selection
        $text = "✅ *Prediction Set: `{$normalizedPrediction}`*\n\n";
        $text .= "💰 *Choose Your Bet Amount:*\n\n";
        $text .= "💼 Your Balance: " . number_format($wallet->trx_balance, 2) . " TRX\n";
        $text .= "🏦 Maximum Bet: " . number_format($maxBet, 2) . " TRX\n\n";
        $text .= "Select an amount or enter a custom amount:";

        // Create bet amount buttons
        $buttons = [];
        $suggestedAmounts = [10, 25, 50, 100];

        foreach ($suggestedAmounts as $amount) {
            if ($amount <= $wallet->trx_balance && $amount <= $maxBet) {
                $buttons[] = [
                    'text' => "💰 {$amount} TRX",
                    'callback_data' => "bet_amount_{$amount}"
                ];
            }
        }

        // Add custom amount button
        $buttons[] = [
            'text' => '💵 Custom Amount',
            'callback_data' => 'bet_amount_custom'
        ];

        // Arrange buttons in rows of 2
        $keyboard = [];
        for ($i = 0; $i < count($buttons); $i += 2) {
            if (isset($buttons[$i + 1])) {
                $keyboard[] = [$buttons[$i], $buttons[$i + 1]];
            } else {
                $keyboard[] = [$buttons[$i]];
            }
        }

        // Add cancel button
        $keyboard[] = [['text' => '❌ Cancel', 'callback_data' => 'bet_cancel']];

        $inlineKeyboard = new \Longman\TelegramBot\Entities\InlineKeyboard(...$keyboard);

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $inlineKeyboard,
        ]);
    }

    /**
     * Handle custom bet amount input
     */
    private function handleCustomBetAmount($user_id, $chat_id, $amountText, $stateData): ServerResponse
    {
        require_once BASE_PATH . '/functions/bet_processing.php';
        require_once BASE_PATH . '/functions/tron_wallet.php';

        $difficulty = $stateData['difficulty'];
        $prediction = $stateData['prediction'];
        $config = getDifficultyConfig($difficulty);

        // Parse amount
        $betAmount = floatval(trim($amountText));

        if ($betAmount <= 0) {
            $text = "❌ *Invalid Amount*\n\n";
            $text .= "Please enter a valid number (e.g., `25` or `37.5`)\n\n";
            $text .= "Or send /cancel to abort.";

            return $this->replyToChat($text, ['parse_mode' => 'Markdown']);
        }

        // Get wallet and validate
        $wallet = getUserWallet($user_id);
        updateWalletBalance($wallet);

        $houseBalance = getTrxBalance(HOUSE_WALLET_ADDRESS);
        $maxBet = calculateMaxBet($houseBalance, $config['multiplier']);
        $validation = validateBetAmount($wallet->trx_balance, $betAmount, $maxBet);

        if (!$validation['valid']) {
            $text = "❌ *Invalid Amount*\n\n";
            $text .= $validation['error'] . "\n\n";
            $text .= "Please enter a different amount or send /cancel to abort.";

            return $this->replyToChat($text, ['parse_mode' => 'Markdown']);
        }

        // Store bet amount and show confirmation
        storeBetState($user_id, 'awaiting_confirmation', [
            'difficulty' => $difficulty,
            'prediction' => $prediction,
            'bet_amount' => $betAmount
        ]);

        $potentialWin = $betAmount * $config['multiplier'];

        $text = "📋 *BET SUMMARY*\n\n";
        $text .= "🎯 *Difficulty:* {$config['name']}\n";
        $text .= "🔮 *Prediction:* `{$prediction}`\n";
        $text .= "💰 *Bet Amount:* " . number_format($betAmount, 2) . " TRX\n";
        $text .= "🎁 *Potential Win:* " . number_format($potentialWin, 2) . " TRX ({$config['multiplier']}x)\n\n";
        $text .= "💼 *Your Balance:* " . number_format($wallet->trx_balance, 2) . " TRX\n";
        $text .= "🏦 *House Max Bet:* " . number_format($maxBet, 2) . " TRX\n\n";
        $text .= "⚠️ *How it works:*\n";
        $text .= "1. Transaction sent from your wallet to house\n";
        $text .= "2. Last {$config['chars']} character(s) of TX hash checked\n";
        $text .= "3. If it matches `{$prediction}`, you win!\n";
        $text .= "4. Instant payout if you win 🎉\n\n";
        $text .= "Ready to play?";

        $keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard([
            ['text' => '✅ Confirm Bet', 'callback_data' => 'bet_confirm'],
        ], [
            ['text' => '❌ Cancel', 'callback_data' => 'bet_cancel']
        ]);

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * Handle private key import
     */
    private function handlePrivateKeyImport($user_id, $chat_id, $privateKey, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/tron_wallet.php';

        // Delete user's message for security
        Request::deleteMessage([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ]);

        // Clear the state
        \R::exec('DELETE FROM userstate WHERE telegram_user_id = ?', [$user_id]);

        // Validate and import the wallet
        $privateKey = trim($privateKey);
        $address = getAddressFromPrivateKey($privateKey);

        if (!$address) {
            return $this->replyToChat(
                "❌ Invalid private key. Please try again with /wallet",
                ['parse_mode' => 'Markdown']
            );
        }

        // Check if wallet already exists
        $existingWallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);
        if ($existingWallet) {
            return $this->replyToChat(
                "❌ You already have a wallet! Use /wallet to manage it.",
                ['parse_mode' => 'Markdown']
            );
        }

        // Save wallet to database
        $wallet = \R::dispense('wallet');
        $wallet->telegram_user_id = $user_id;
        $wallet->address = $address;
        $wallet->private_key = encryptPrivateKey($privateKey);
        $wallet->trx_balance = 0;
        $wallet->usd_balance = 0;
        $wallet->created_at = date('Y-m-d H:i:s');
        $wallet->updated_at = date('Y-m-d H:i:s');
        \R::store($wallet);

        // Update balance
        updateWalletBalance($wallet);

        $text = "✅ *Wallet Imported Successfully!*\n\n";
        $text .= "📍 *Address:*\n`{$address}`\n\n";
        $text .= "💰 *Balance:*\n";
        $text .= "• TRX: " . number_format($wallet->trx_balance, 6) . " TRX\n";
        $text .= "• USD: $" . number_format($wallet->usd_balance, 2) . "\n\n";
        $text .= "You're all set! Use /bet to start playing! 🎲";

        return $this->replyToChat($text, ['parse_mode' => 'Markdown']);
    }
}

