<?php
// bootstrap.php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__FILE__));
}
// Include core files (config loads $telegram_config, plugin sets up DB)
require_once BASE_PATH . '/plugins/rb.php';
require_once BASE_PATH . '/config/config.php';

// Automatically include all function files
$functionsDir = BASE_PATH . '/functions/';
if (is_dir($functionsDir)) {
  $files = scandir($functionsDir);
  foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
      require_once $functionsDir . $file;
    }
  }
}