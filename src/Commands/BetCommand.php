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
 * Bet command
 *
 * Initiates a new bet for the hash lottery game
 */
class BetCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'bet';

    /**
     * @var string
     */
    protected $description = 'Place a bet in the hash lottery';

    /**
     * @var string
     */
    protected $usage = '/bet';

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

        // Bet initiation message
        $text = "🎲 *Let's Place a Bet!* 🎲\n\n";
        $text .= "Choose your difficulty level, {$first_name}:\n\n";
        $text .= "1️⃣ *1 Character* (Easy)\n";
        $text .= "   • Odds: 1/16 (6.25%)\n";
        $text .= "   • Payout: 10x your bet\n";
        $text .= "   • Example: Predict 'F'\n\n";
        $text .= "2️⃣ *2 Characters* (Medium)\n";
        $text .= "   • Odds: 1/256 (0.39%)\n";
        $text .= "   • Payout: 200x your bet\n";
        $text .= "   • Example: Predict 'A7'\n\n";
        $text .= "3️⃣ *3 Characters* (Hard)\n";
        $text .= "   • Odds: 1/4,096 (0.024%)\n";
        $text .= "   • Payout: 3,500x your bet\n";
        $text .= "   • Example: Predict 'ABC'\n\n";
        $text .= "4️⃣ *4 Characters* (Expert)\n";
        $text .= "   • Odds: 1/65,536 (0.0015%)\n";
        $text .= "   • Payout: 50,000x your bet\n";
        $text .= "   • Example: Predict 'F8D2'\n\n";
        $text .= "💡 *Tip:* You're predicting the LAST characters of your transaction hash!\n\n";
        $text .= "Select a difficulty to continue:";

        // Create inline keyboard with difficulty options
        $inline_keyboard = new InlineKeyboard([
            ['text' => '1️⃣ Easy (10x)', 'callback_data' => 'bet_difficulty_1'],
            ['text' => '2️⃣ Medium (200x)', 'callback_data' => 'bet_difficulty_2'],
        ], [
            ['text' => '3️⃣ Hard (3,500x)', 'callback_data' => 'bet_difficulty_3'],
            ['text' => '4️⃣ Expert (50,000x)', 'callback_data' => 'bet_difficulty_4'],
        ], [
            ['text' => '❌ Cancel', 'callback_data' => 'bet_cancel'],
        ]);

        return $this->replyToChat($text, [
            'parse_mode' => 'Markdown',
            'reply_markup' => $inline_keyboard,
        ]);
    }
}

