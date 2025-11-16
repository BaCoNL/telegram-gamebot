# Telegram Bot Setup Guide

## Overview

This Telegram bot is built using the `longman/telegram-bot` library for PHP. It implements a TRON Hash Lottery game where users can place bets and win based on transaction hash predictions.

## Files Created

### 1. Webhook Handler (`src/telegram-webhook.php`)

This is the main entry point for Telegram webhook requests. It:
- Loads the Telegram Bot library
- Sets up custom commands
- Handles incoming webhook requests from Telegram

### 2. Start Command (`src/Commands/StartCommand.php`)

The `/start` command shows a welcome message when users first interact with your bot. It displays:
- Welcome greeting with the user's first name
- Game explanation
- Multiplier options (10x, 200x, 3,500x, 50,000x)
- Available commands (/bet, /balance, /stats, /help)

### 3. Bet Command (`src/Commands/BetCommand.php`)

The `/bet` command initiates a new betting session. It displays:
- Difficulty level options (1-4 characters)
- Odds for each difficulty
- Payout multipliers
- Interactive inline keyboard buttons for selection

## Setup Instructions

### 1. Update Bot Username

In `src/telegram-webhook.php`, replace `'YourBotUsername'` with your actual bot username:

```php
$telegram = new Telegram($telegram_config['bot_token'], 'your_actual_bot_username');
```

### 2. Set Webhook URL

You need to tell Telegram where to send updates. Run this command (replace with your details):

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/telegram-gamebot/src/telegram-webhook.php"
```

Or use this PHP script:

```php
<?php
require_once 'config/config.php';

$url = "https://api.telegram.org/bot{$telegram_config['bot_token']}/setWebhook";
$data = ['url' => $telegram_config['webhook_url']];

$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo $result;
```

### 3. Test Your Bot

1. Open Telegram and find your bot
2. Send `/start` - You should see the welcome message
3. Send `/bet` - You should see the betting options with buttons

## Customizing Messages

### Modify the Start Command Message

Edit `src/Commands/StartCommand.php` and update the `$text` variable in the `execute()` method:

```php
$text = "🎲 *Your Custom Welcome Message!* 🎲\n\n";
// ... add your custom text
```

### Modify the Bet Command Message

Edit `src/Commands/BetCommand.php` and update the `$text` variable in the `execute()` method:

```php
$text = "🎲 *Your Custom Bet Message!* 🎲\n\n";
// ... add your custom text
```

## Message Formatting

The bot supports Markdown formatting:
- `*bold text*` - **bold**
- `_italic text_` - *italic*
- `[link text](URL)` - hyperlinks
- `` `code` `` - inline code
- Use emoji for visual appeal (🎲 💰 🎯 etc.)

## Next Steps

To complete the betting flow, you'll need to create:

1. **CallbackQuery Handler** - Handle button clicks from the `/bet` command
2. **Balance Command** - Show user's balance
3. **Stats Command** - Show user's statistics
4. **Help Command** - Show help information
5. **Bet Processing Logic** - Handle the actual betting workflow

## Troubleshooting

### Bot doesn't respond

1. Check webhook is set correctly:
   ```
   https://api.telegram.org/bot<TOKEN>/getWebhookInfo
   ```

2. Check webhook URL is accessible from the internet

3. Check PHP error logs for issues

### Commands not found

Make sure the Commands folder structure is correct:
```
src/
  Commands/
    StartCommand.php
    BetCommand.php
```

### PHP Warnings

The IDE may show warnings about `$telegram_config` being undefined. This is a false positive - the variable is defined when `config.php` is included at runtime.

## Security Notes

⚠️ **IMPORTANT**: Your `config/config.php` contains sensitive information (bot token, API keys, database credentials). Make sure it's:
- Added to `.gitignore` (already done)
- Not publicly accessible via web server
- Has proper file permissions

## Resources

- [longman/telegram-bot Documentation](https://github.com/php-telegram-bot/core)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [PHP Telegram Bot Examples](https://github.com/php-telegram-bot/example-bot)

