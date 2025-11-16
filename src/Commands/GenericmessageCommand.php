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

        // Check if user has a pending state
        $state = \R::findOne('userstate', 'telegram_user_id = ? ORDER BY id DESC', [$user_id]);

        if ($state && $state->state === 'awaiting_private_key') {
            return $this->handlePrivateKeyImport($user_id, $chat_id, $text, $message->getMessageId());
        }

        return Request::emptyResponse();
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

