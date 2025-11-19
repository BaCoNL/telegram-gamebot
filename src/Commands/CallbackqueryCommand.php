<?php
/**
 * Callback query command
 *
 * Handles all inline keyboard button callbacks
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use R;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle callback queries';

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
        $callback_query = $this->getCallbackQuery();
        $callback_data = $callback_query->getData();
        $user_id = $callback_query->getFrom()->getId();
        $chat_id = $callback_query->getMessage()->getChat()->getId();
        $message_id = $callback_query->getMessage()->getMessageId();

        // Answer the callback query first to remove loading state
        Request::answerCallbackQuery([
            'callback_query_id' => $callback_query->getId(),
        ]);

        // Route to appropriate handler based on callback data
        if (strpos($callback_data, 'wallet_') === 0) {
            return $this->handleWalletCallback($callback_data, $user_id, $chat_id, $message_id);
        }

        if (strpos($callback_data, 'bet_') === 0) {
            return $this->handleBetCallback($callback_data, $user_id, $chat_id, $message_id);
        }

        return Request::emptyResponse();
    }

    /**
     * Handle wallet-related callbacks
     *
     * @param string $callback_data
     * @param int $user_id
     * @param int $chat_id
     * @param int $message_id
     * @return ServerResponse
     * @throws TelegramException
     */
    private function handleWalletCallback($callback_data, $user_id, $chat_id, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/tron_wallet.php';

        switch ($callback_data) {
            case 'wallet_create':
                return $this->createNewWallet($user_id, $chat_id, $message_id);

            case 'wallet_import':
                return $this->requestPrivateKey($user_id, $chat_id, $message_id);

            case 'wallet_refresh':
                return $this->refreshWalletBalance($user_id, $chat_id, $message_id);

            case 'wallet_export':
                return $this->exportPrivateKey($user_id, $chat_id, $message_id);

            default:
                return Request::emptyResponse();
        }
    }

    /**
     * Create a new wallet for the user
     */
    private function createNewWallet($user_id, $chat_id, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/tron_wallet.php';

        // Check if user already has a wallet
        $existingWallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);
        if ($existingWallet) {
            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "⚠️ You already have a wallet!\n\nAddress: `{$existingWallet->address}`",
                'parse_mode' => 'Markdown',
            ]);
        }

        // Show loading message
        Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "⏳ Creating your TRON wallet...",
        ]);

        $walletData = createTronWallet();

        if (!$walletData) {
            error_log("Wallet creation failed for user $user_id");
            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "❌ Error creating wallet. Please try again later.\n\nIf the problem persists, contact support.",
            ]);
        }

        // Save wallet to database
        try {
            $wallet = \R::dispense('wallet');
            $wallet->telegram_user_id = $user_id;
            $wallet->address = $walletData['address'];
            $wallet->private_key = encryptPrivateKey($walletData['privateKey']);
            $wallet->trx_balance = 0;
            $wallet->usd_balance = 0;
            $wallet->created_at = date('Y-m-d H:i:s');
            $wallet->updated_at = date('Y-m-d H:i:s');
            \R::store($wallet);

            $text = "✅ *Wallet Created Successfully!*\n\n";
            $text .= "📍 *Address:*\n`{$walletData['address']}`\n\n";
            $text .= "🔑 *Private Key:*\n`{$walletData['privateKey']}`\n\n";
            $text .= "⚠️ *IMPORTANT:* Save your private key in a safe place! ";
            $text .= "This is the only time it will be shown in plain text.\n\n";
            $text .= "You can now deposit TRX to start playing! 🎲";

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (Exception $e) {
            error_log("Database error saving wallet for user $user_id: " . $e->getMessage());
            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => "❌ Error saving wallet. Please try again later.",
            ]);
        }
    }

    /**
     * Request private key for wallet import
     */
    private function requestPrivateKey($user_id, $chat_id, $message_id): ServerResponse
    {
        // Store state that user is importing a wallet
        $state = \R::dispense('userstate');
        $state->telegram_user_id = $user_id;
        $state->state = 'awaiting_private_key';
        $state->created_at = date('Y-m-d H:i:s');
        \R::store($state);

        $text = "📥 *Import Wallet*\n\n";
        $text .= "Please send your TRON private key.\n\n";
        $text .= "⚠️ *Security Note:*\n";
        $text .= "• Your private key will be encrypted\n";
        $text .= "• Delete your message after sending\n";
        $text .= "• Make sure you're in a private chat\n\n";
        $text .= "Send /cancel to abort.";

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Refresh wallet balance
     */
    private function refreshWalletBalance($user_id, $chat_id, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/tron_wallet.php';

        $wallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);

        if (!$wallet) {
            return Request::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQuery()->getId(),
                'text' => 'Wallet not found!',
                'show_alert' => true,
            ]);
        }

        updateWalletBalance($wallet);

        $text = "💼 *Your Wallet*\n\n";
        $text .= "📍 *Address:*\n`{$wallet->address}`\n\n";
        $text .= "💰 *Balance:*\n";
        $text .= "• TRX: " . number_format($wallet->trx_balance, 6) . " TRX\n";
        $text .= "• USD: $" . number_format($wallet->usd_balance, 2) . "\n\n";
        $text .= "🔄 Updated at: " . date('H:i:s');

        $keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard([
            ['text' => '🔄 Refresh Balance', 'callback_data' => 'wallet_refresh'],
            ['text' => '🔑 Export Private Key', 'callback_data' => 'wallet_export']
        ]);

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * Export private key (send as private message)
     */
    private function exportPrivateKey($user_id, $chat_id, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/tron_wallet.php';

        $wallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);

        if (!$wallet) {
            return Request::emptyResponse();
        }

        $privateKey = decryptPrivateKey($wallet->private_key);

        $text = "🔑 *Your Private Key*\n\n";
        $text .= "`{$privateKey}`\n\n";
        $text .= "⚠️ *Never share this with anyone!*\n";
        $text .= "Delete this message after saving it.";

        // Send as new message and delete after 30 seconds
        $result = Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        // Answer callback
        Request::answerCallbackQuery([
            'callback_query_id' => $this->getCallbackQuery()->getId(),
            'text' => 'Private key sent! Please save it and delete the message.',
            'show_alert' => true,
        ]);

        return $result;
    }

    /**
     * Handle bet-related callbacks
     *
     * @param string $callback_data
     * @param int $user_id
     * @param int $chat_id
     * @param int $message_id
     * @return ServerResponse
     * @throws TelegramException
     */
    private function handleBetCallback($callback_data, $user_id, $chat_id, $message_id): ServerResponse
    {
        require_once BASE_PATH . '/functions/bet_processing.php';
        require_once BASE_PATH . '/functions/tron_wallet.php';

        // Parse callback data
        $parts = explode('_', $callback_data);

        // Handle difficulty selection
        if ($parts[1] === 'difficulty' && isset($parts[2])) {
            return $this->handleDifficultySelection($parts[2], $user_id, $chat_id, $message_id);
        }

        // Handle bet amount selection
        if ($parts[1] === 'amount' && isset($parts[2])) {
            return $this->handleBetAmountSelection($parts[2], $user_id, $chat_id, $message_id);
        }

        // Handle custom amount
        if ($callback_data === 'bet_amount_custom') {
            return $this->handleCustomAmountRequest($user_id, $chat_id, $message_id);
        }

        // Handle bet confirmation
        if ($callback_data === 'bet_confirm') {
            return $this->handleBetConfirmation($user_id, $chat_id, $message_id);
        }

        // Handle bet cancellation
        if ($callback_data === 'bet_cancel') {
            return $this->handleBetCancellation($user_id, $chat_id, $message_id);
        }

        // Handle play again
        if ($callback_data === 'bet_play_again') {
            return $this->handlePlayAgain($user_id, $chat_id, $message_id);
        }

        // Handle same bet again
        if ($callback_data === 'bet_same_again') {
            return $this->handleSameBetAgain($user_id, $chat_id, $message_id);
        }

        return Request::emptyResponse();
    }

    /**
     * Handle difficulty selection
     */
    private function handleDifficultySelection($difficulty, $user_id, $chat_id, $message_id): ServerResponse
    {
        $config = getDifficultyConfig($difficulty);

        if (!$config) {
            return Request::emptyResponse();
        }

        // Store state
        storeBetState($user_id, 'awaiting_prediction', [
            'difficulty' => $difficulty,
            'chars' => $config['chars']
        ]);

        $text = "🎯 *{$config['name']} Difficulty Selected*\n\n";
        $text .= "Please enter your prediction:\n\n";
        $text .= "• Enter exactly *{$config['chars']} character(s)*\n";
        $text .= "• Use hex characters: *0-9, A-F*\n";
        $text .= "• Case doesn't matter (A = a)\n\n";
        $text .= "Example: ";

        // Generate example
        $examples = ['A', 'F3', 'C7A', '1B4F'];
        $text .= "`{$examples[$difficulty - 1]}`\n\n";
        $text .= "Send /cancel to abort.";

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Handle bet amount selection
     */
    private function handleBetAmountSelection($amount, $user_id, $chat_id, $message_id): ServerResponse
    {
        $state = getBetState($user_id);

        if (!$state || !isset($state['data']['difficulty']) || !isset($state['data']['prediction'])) {
            return $this->handleBetCancellation($user_id, $chat_id, $message_id);
        }

        $difficulty = $state['data']['difficulty'];
        $prediction = $state['data']['prediction'];
        $config = getDifficultyConfig($difficulty);

        // Get wallet and update balance
        $wallet = getUserWallet($user_id);
        updateWalletBalance($wallet);

        // Calculate max bet
        $houseBalance = getTrxBalance(HOUSE_WALLET_ADDRESS);
        $maxBet = calculateMaxBet($houseBalance, $config['multiplier']);

        // Validate bet amount
        $betAmount = floatval($amount);
        $validation = validateBetAmount($wallet->trx_balance, $betAmount, $maxBet);

        if (!$validation['valid']) {
            Request::answerCallbackQuery([
                'callback_query_id' => $this->getCallbackQuery()->getId(),
                'text' => '❌ ' . $validation['error'],
                'show_alert' => true,
            ]);
            return Request::emptyResponse();
        }

        // Store bet amount in state
        storeBetState($user_id, 'awaiting_confirmation', [
            'difficulty' => $difficulty,
            'prediction' => $prediction,
            'bet_amount' => $betAmount
        ]);

        // Show confirmation
        return $this->showBetConfirmation($user_id, $chat_id, $message_id, $config, $prediction, $betAmount, $wallet);
    }

    /**
     * Handle custom amount request
     */
    private function handleCustomAmountRequest($user_id, $chat_id, $message_id): ServerResponse
    {
        $state = getBetState($user_id);

        if (!$state || !isset($state['data']['prediction'])) {
            return $this->handleBetCancellation($user_id, $chat_id, $message_id);
        }

        // Update state to awaiting custom amount
        $state['data']['awaiting_custom_amount'] = true;
        storeBetState($user_id, 'awaiting_bet_amount', $state['data']);

        $text = "💰 *Enter Custom Bet Amount*\n\n";
        $text .= "Send the amount in TRX (e.g., `25` or `37.5`)\n\n";
        $text .= "Send /cancel to abort.";

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Show bet confirmation screen
     */
    private function showBetConfirmation($user_id, $chat_id, $message_id, $config, $prediction, $betAmount, $wallet): ServerResponse
    {
        $potentialWin = $betAmount * $config['multiplier'];
        $houseBalance = getTrxBalance(HOUSE_WALLET_ADDRESS);
        $maxBet = calculateMaxBet($houseBalance, $config['multiplier']);

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

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * Handle bet confirmation - Execute the bet
     */
    private function handleBetConfirmation($user_id, $chat_id, $message_id): ServerResponse
    {
        $state = getBetState($user_id);

        if (!$state || $state['state'] !== 'awaiting_confirmation') {
            return $this->handleBetCancellation($user_id, $chat_id, $message_id);
        }

        $difficulty = $state['data']['difficulty'];
        $prediction = $state['data']['prediction'];
        $betAmount = $state['data']['bet_amount'];
        $config = getDifficultyConfig($difficulty);

        // Get wallet
        $wallet = getUserWallet($user_id);
        updateWalletBalance($wallet);

        // Final validation
        $houseBalance = getTrxBalance(HOUSE_WALLET_ADDRESS);
        $maxBet = calculateMaxBet($houseBalance, $config['multiplier']);
        $validation = validateBetAmount($wallet->trx_balance, $betAmount, $maxBet);

        if (!$validation['valid']) {
            clearBetState($user_id);

            $text = "❌ *Bet Failed*\n\n";
            $text .= $validation['error'];

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        }

        // Create bet record
        $bet = createBetRecord($user_id, $prediction, $difficulty, $betAmount);

        // Show processing message
        Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "⏳ *Processing Your Bet...*\n\nSending transaction...",
            'parse_mode' => 'Markdown',
        ]);

        // Send transaction
        $txResult = sendBetTransaction($wallet, $betAmount);

        if (!$txResult['success']) {
            // Update bet as cancelled
            $bet->status = 'cancelled';
            $bet->completed_at = date('Y-m-d H:i:s');
            R::store($bet);

            clearBetState($user_id);

            $text = "❌ *Transaction Failed*\n\n";
            $text .= $txResult['error'] . "\n\n";
            $text .= "Your bet has been cancelled.";

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        }

        $txHash = $txResult['txHash'];

        // Update message with TX hash
        Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "⏳ *Transaction Sent!*\n\nTX Hash: `{$txHash}`\n\nVerifying result...",
            'parse_mode' => 'Markdown',
        ]);

        // Verify outcome
        $outcome = verifyBetOutcome($txHash, $prediction, $config['chars']);

        if ($outcome['isWin']) {
            // Process win
            $payoutResult = processBetWin($bet, $txHash, $outcome['actualEnding']);

            clearBetState($user_id);

            $text = "🎉 *WINNER! WINNER!* 🎉\n\n";
            $text .= "🔮 *Your Prediction:* `{$prediction}`\n";
            $text .= "🎯 *Transaction Hash:* `...{$outcome['actualEnding']}`\n\n";
            $text .= "💰 *You Won:* " . number_format($bet->payout_amount, 2) . " TRX!\n\n";

            if ($payoutResult['success']) {
                $text .= "✅ *Payout Sent!*\n";
                $text .= "TX: `{$payoutResult['payoutTxHash']}`\n\n";
            } else {
                $text .= "⚠️ Payout pending: {$payoutResult['error']}\n\n";
            }

            $text .= "Congratulations! 🎊";

            $keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard([
                ['text' => '🎲 Play Again', 'callback_data' => 'bet_play_again'],
                ['text' => '💼 Check Balance', 'callback_data' => 'wallet_refresh']
            ]);

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard,
            ]);
        } else {
            // Process loss
            processBetLoss($bet, $txHash, $outcome['actualEnding']);

            clearBetState($user_id);

            $text = "😔 *Not This Time*\n\n";
            $text .= "🔮 *Your Prediction:* `{$prediction}`\n";
            $text .= "🎯 *Transaction Hash:* `...{$outcome['actualEnding']}`\n\n";
            $text .= "Better luck next time!\n\n";
            $text .= "💡 The odds are always the same - try again!";

            $keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard([
                ['text' => '🔄 Same Bet Again', 'callback_data' => 'bet_same_again'],
            ], [
                ['text' => '🎲 New Bet', 'callback_data' => 'bet_play_again'],
                ['text' => '💼 Check Balance', 'callback_data' => 'wallet_refresh']
            ]);

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard,
            ]);
        }
    }

    /**
     * Handle bet cancellation
     */
    private function handleBetCancellation($user_id, $chat_id, $message_id): ServerResponse
    {
        clearBetState($user_id);

        $text = "❌ *Bet Cancelled*\n\n";
        $text .= "No worries! Use /bet when you're ready to play.";

        return Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Handle play again
     */
    private function handlePlayAgain($user_id, $chat_id, $message_id): ServerResponse
    {
        clearBetState($user_id);

        // Redirect to bet command by simulating it
        $text = "🎲 *New Bet*\n\n";
        $text .= "Starting a new bet! Use /bet to continue.";

        Request::editMessageText([
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        // Send the bet selection (simulate /bet command)
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Use /bet to place a new bet!',
        ]);
    }

    /**
     * Handle same bet again
     */
    private function handleSameBetAgain($user_id, $chat_id, $message_id): ServerResponse
    {
        // Get last bet
        $lastBet = R::findOne('bet', 'user_id = ? AND status IN (?, ?) ORDER BY created_at DESC',
            [$user_id, 'won', 'lost']);

        if (!$lastBet) {
            return $this->handlePlayAgain($user_id, $chat_id, $message_id);
        }

        // Determine difficulty from characters_count
        $difficulty = null;
        foreach (DIFFICULTY_CONFIG as $level => $config) {
            if ($config['chars'] == $lastBet->characters_count) {
                $difficulty = $level;
                break;
            }
        }

        if (!$difficulty) {
            return $this->handlePlayAgain($user_id, $chat_id, $message_id);
        }

        $config = getDifficultyConfig($difficulty);
        $wallet = getUserWallet($user_id);
        updateWalletBalance($wallet);

        // Recreate bet with same parameters
        storeBetState($user_id, 'awaiting_confirmation', [
            'difficulty' => $difficulty,
            'prediction' => $lastBet->prediction,
            'bet_amount' => $lastBet->bet_amount
        ]);

        return $this->showBetConfirmation($user_id, $chat_id, $message_id, $config,
            $lastBet->prediction, $lastBet->bet_amount, $wallet);
    }
}

