<?php
/**
 * Wallet command
 *
 * Handles wallet creation and import for TRON network
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Exception\TelegramException;

class WalletCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'wallet';

    /**
     * @var string
     */
    protected $description = 'Manage your TRON wallet';

    /**
     * @var string
     */
    protected $usage = '/wallet';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Command execute method
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();

        // Check if user already has a wallet
        $wallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);

        if ($wallet) {
            // User has a wallet, show wallet info
            return $this->showWalletInfo($chat_id, $wallet);
        } else {
            // User doesn't have a wallet, show setup options
            return $this->showWalletSetup($chat_id);
        }
    }

    /**
     * Show wallet setup options
     *
     * @param int $chat_id
     * @return ServerResponse
     * @throws TelegramException
     */
    private function showWalletSetup($chat_id): ServerResponse
    {
        $text = "🔐 *Wallet Setup*\n\n";
        $text .= "You don't have a wallet yet. Choose an option:\n\n";
        $text .= "🆕 *Create New Wallet* - Generate a new TRON wallet\n";
        $text .= "📥 *Import Wallet* - Import an existing wallet using your private key\n\n";
        $text .= "⚠️ *Important:* Your private key will be encrypted and stored securely.";

        $keyboard = new InlineKeyboard([
            ['text' => '🆕 Create New Wallet', 'callback_data' => 'wallet_create'],
            ['text' => '📥 Import Wallet', 'callback_data' => 'wallet_import']
        ]);

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * Show wallet information
     *
     * @param int $chat_id
     * @param object $wallet
     * @return ServerResponse
     * @throws TelegramException
     */
    private function showWalletInfo($chat_id, $wallet): ServerResponse
    {
        // Update wallet balance before showing
        require_once BASE_PATH . '/functions/tron_wallet.php';
        updateWalletBalance($wallet);

        $text = "💼 *Your Wallet*\n\n";
        $text .= "📍 *Address:*\n`{$wallet->address}`\n\n";
        $text .= "💰 *Balance:*\n";
        $text .= "• TRX: " . number_format($wallet->trx_balance, 6) . " TRX\n";
        $text .= "• USD: $" . number_format($wallet->usd_balance, 2) . "\n\n";
        $text .= "🔄 Use /wallet to refresh your balance";

        $keyboard = new InlineKeyboard([
            ['text' => '🔄 Refresh Balance', 'callback_data' => 'wallet_refresh'],
            ['text' => '🔑 Export Private Key', 'callback_data' => 'wallet_export']
        ]);

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }
}

