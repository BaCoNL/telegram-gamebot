<?php
// src/telegram-webhook.php

// Define base path - go up one level from src to project root
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Verify autoloader exists
$autoloader = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    die('Error: Composer autoloader not found. Please run "composer install" in the project root.');
}
require_once $autoloader;

// Load bootstrap (which loads config, plugins, functions)
require_once BASE_PATH . '/bootstrap.php';

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Exception\TelegramException;

try {
    // Create Telegram API object using config
    $telegram = new Telegram($telegram_config['bot_token'], $telegram_config['bot_username'] ?? 'YourBotUsername');

    // Set custom commands path
    $telegram->addCommandsPaths([
        __DIR__ . '/Commands'
    ]);

    // Handle telegram webhook request
    $telegram->handle();
} catch (TelegramException $e) {
    error_log('Telegram Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
