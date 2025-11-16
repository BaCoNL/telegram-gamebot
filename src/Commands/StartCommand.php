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
        $first_name = $user->getFirstName();

        // Welcome message
        $text = "🎲 *Welcome to TRON Hash Lottery!* 🎲\n\n";
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
        $text .= "/balance - Check your balance\n";
        $text .= "/stats - View your statistics\n";
        $text .= "/help - Get help\n\n";
        $text .= "Ready to play? Use /bet to get started! 🚀";

        $data = [
            'chat_id' => $chat_id,
            'text'    => $text,
            'parse_mode' => 'Markdown',
        ];

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
        ]);
    }
}

