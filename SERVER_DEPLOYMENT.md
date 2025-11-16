# Server Deployment Instructions

## Problem
The error `Class "GuzzleHttp\\HandlerStack" not found` indicates that the Composer dependencies are not properly installed on the server.

## Solution

### Option 1: Reinstall Composer Dependencies (RECOMMENDED)

SSH into your server and run the following commands:

```bash
cd /var/www/vhosts/innolabs.nl/telegram.innolabs.nl
composer install --no-dev --optimize-autoloader
```

If composer is not installed globally, you can use:

```bash
cd /var/www/vhosts/innolabs.nl/telegram.innolabs.nl
php composer.phar install --no-dev --optimize-autoloader
```

### Option 2: Upload Local Vendor Directory

If you cannot run composer on the server:

1. Run `composer install --no-dev --optimize-autoloader` locally
2. Upload the entire `vendor/` directory to the server via FTP/SFTP
3. Ensure file permissions are correct (usually 755 for directories, 644 for files)

### Option 3: Fix Document Root Configuration

The current document root is set to the `src` folder, which is not ideal. Change it to the project root:

**In your Apache virtual host configuration:**

```apache
DocumentRoot "/var/www/vhosts/innolabs.nl/telegram.innolabs.nl"

<Directory "/var/www/vhosts/innolabs.nl/telegram.innolabs.nl">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Then create a `.htaccess` file in the root to route webhook requests:

```apache
RewriteEngine On
RewriteRule ^webhook$ src/telegram-webhook.php [L]
```

This way your webhook URL becomes: `https://telegram.innolabs.nl/webhook`

## Verification

After deploying, check the error logs:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/www/vhosts/innolabs.nl/telegram.innolabs.nl/logs/error_log
```

## Required PHP Extensions

Ensure these PHP extensions are installed:
- php-curl
- php-mbstring
- php-json
- php-xml

Check with: `php -m | grep -E 'curl|mbstring|json|xml'`

## PHP Configuration

If you need to configure PHP settings (like error logging), do it in `php.ini` or via the hosting control panel, NOT in `.htaccess` with `php_flag` directives, as they may not be allowed on your server.

Recommended settings in `php.ini`:
```ini
display_errors = Off
log_errors = On
error_log = /var/www/vhosts/innolabs.nl/telegram.innolabs.nl/logs/php_errors.log
```

## Important Note About Document Root

Since your document root is set to `/src/`, the webhook URL should be:
- `https://telegram.innolabs.nl/telegram-webhook.php` (not `/src/telegram-webhook.php`)

The file paths in the code already account for this with the `BASE_PATH` constant.

