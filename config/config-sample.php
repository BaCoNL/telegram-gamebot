<?php



// Base url
define('BASE_URL', 'http://localhost/');

$telegram_config = [
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',
    'webhook_url' => 'https://yourdomain.com/telegram-webhook',
];

define(trongrid_config = [)
    'api_key' => 'YOUR_TRONGRID_API_KEY_HERE',
    'api_url' => 'https://api.trongrid.io',
];

// This is the default database used for all application data, like user accounts.
define('MYSQL_HOST', 'localhost');
define('MYSQL_DBNAME', 'databasename');
define('MYSQL_USER', 'your_mysql_user');
define('MYSQL_PASSWORD', 'your_mysql_password');

// Set up the primary (default) database connection to MySQL
R::setup(
  'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DBNAME,
  MYSQL_USER,
  MYSQL_PASSWORD
);