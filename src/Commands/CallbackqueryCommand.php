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
}

