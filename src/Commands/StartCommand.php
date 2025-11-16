<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Exception\TelegramException;

/**
 * Start command
 *
 * Gets executed when a user first starts using the bot.
 */
class StartCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'start';

    /**
     * @var string
     */
    protected $description = 'Start command';

    /**
     * @var string
     */
    protected $usage = '/start';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $private_only = false;

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
        $user = $message->getFrom();
        $user_id = $user->getId();
        $first_name = $user->getFirstName();

        // Check if user has a wallet
        $wallet = \R::findOne('wallet', 'telegram_user_id = ?', [$user_id]);

        if (!$wallet) {
            // New user - show wallet setup first
            return $this->showWalletSetup($chat_id, $first_name);
        }

        // Existing user - show regular welcome message
        return $this->showWelcomeMessage($chat_id, $first_name);
    }

    /**
     * Show wallet setup for new users
     *
     * @param int $chat_id
     * @param string $first_name
     * @return ServerResponse
     * @throws TelegramException
     */
    private function showWalletSetup($chat_id, $first_name): ServerResponse
    {
        $text = "🎲 *Welcome to TRON Hash Lottery!* 🎲\n\n";
        $text .= "Hi {$first_name}! 👋\n\n";
        $text .= "To get started, you need to set up your TRON wallet:\n\n";
        $text .= "🆕 *Create New Wallet* - We'll generate a new TRON wallet for you\n";
        $text .= "📥 *Import Wallet* - Use your existing TRON wallet\n\n";
        $text .= "Your wallet will be used to:\n";
        $text .= "• Place bets 🎯\n";
        $text .= "• Receive winnings 💰\n";
        $text .= "• Track your balance 📊\n\n";
        $text .= "Choose an option below to continue:";

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
     * Show welcome message for existing users
     *
     * @param int $chat_id
     * @param string $first_name
     * @return ServerResponse
     * @throws TelegramException
     */
    private function showWelcomeMessage($chat_id, $first_name): ServerResponse
    {
        $text = "🎲 *Welcome back to TRON Hash Lottery!* 🎲\n\n";
        $text .= "Hi {$first_name}! 👋\n\n";
        $text .= "🎯 *How it works:*\n";
        $text .= "• Predict the ending of your transaction hash\n";
        $text .= "• Send your bet in TRX\n";
        $text .= "• If your prediction matches, you WIN BIG! 💰\n\n";
        $text .= "🏆 *Multipliers:*\n";
        $text .= "1️⃣ 1 character - 10x payout\n";
        $text .= "2️⃣ 2 characters - 200x payout\n";
        $text .= "3️⃣ 3 characters - 3,500x payout\n";
        $text .= "4️⃣ 4 characters - 50,000x payout\n\n";
        $text .= "📋 *Available Commands:*\n";
        $text .= "/bet - Start a new bet\n";
        $text .= "/wallet - Manage your wallet\n";
        $text .= "/stats - View your statistics\n";
        $text .= "/help - Get help\n\n";
        $text .= "Ready to play? Use /bet to get started! 🚀";


        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
        ]);
    }
}

