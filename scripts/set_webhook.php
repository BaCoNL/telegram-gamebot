<?php
/**
 * Set Webhook Script
 *
 * This script sets up the webhook for your Telegram bot.
 * Run this once to tell Telegram where to send updates.
 */

require_once __DIR__ . '/../config/config.php';

echo "Setting up webhook for Telegram bot...\n\n";
echo "Bot Token: " . substr($telegram_config['bot_token'], 0, 10) . "...\n";
echo "Webhook URL: {$telegram_config['webhook_url']}\n\n";

// Telegram API endpoint
$url = "https://api.telegram.org/bot{$telegram_config['bot_token']}/setWebhook";

// Webhook data
$data = [
    'url' => $telegram_config['webhook_url'],
    'allowed_updates' => ['message', 'callback_query']
];

// Make request
$options = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode($result, true);

echo "Response:\n";
print_r($response);

if ($response['ok']) {
    echo "\n✅ Webhook set successfully!\n\n";
    echo "Test your bot by:\n";
    echo "1. Opening Telegram\n";
    echo "2. Finding your bot\n";
    echo "3. Sending /start\n";
} else {
    echo "\n❌ Failed to set webhook!\n";
    echo "Error: " . ($response['description'] ?? 'Unknown error') . "\n";
}

// Get webhook info to verify
echo "\n--- Webhook Info ---\n";
$infoUrl = "https://api.telegram.org/bot{$telegram_config['bot_token']}/getWebhookInfo";
$info = file_get_contents($infoUrl);
$infoData = json_decode($info, true);
print_r($infoData['result']);

