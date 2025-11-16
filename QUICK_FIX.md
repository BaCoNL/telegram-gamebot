# Quick Fix Checklist

## Immediate Actions to Fix the 500 Error

### 1. Upload the Fixed .htaccess File ✓
The `.htaccess` file in the `src/` folder has been fixed. Upload `src/.htaccess` to the server.
- **Remove the php_flag directives** that caused the error
- The new version only has file protection and DirectoryIndex settings

### 2. Install Composer Dependencies on Server
SSH into the server and run:
```bash
cd /var/www/vhosts/innolabs.nl/telegram.innolabs.nl
composer install --no-dev --optimize-autoloader
```

Or if you can't run composer on the server:
- Run `composer install --no-dev --optimize-autoloader` locally
- Upload the entire `vendor/` directory to the server
- Make sure to preserve the directory structure

### 3. Verify File Permissions
```bash
cd /var/www/vhosts/innolabs.nl/telegram.innolabs.nl
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

### 4. Test the Webhook
After uploading, test by running (from your local machine):
```bash
php scripts/set_webhook.php
```

### 5. Monitor Logs
Watch the server logs for any remaining errors:
```bash
tail -f /var/log/apache2/error.log
```

## Files to Upload
- `src/.htaccess` (fixed version without php_flag)
- `src/telegram-webhook.php` (updated with BASE_PATH)
- `vendor/` directory (if composer install on server fails)

## What Was Fixed
1. ✅ Removed `php_flag` directives from `.htaccess` (caused the 500 error)
2. ✅ Updated `telegram-webhook.php` to use `BASE_PATH` constant for proper path resolution
3. ✅ Added autoloader existence check
4. ✅ Fixed `set_webhook.php` script path

## Expected Result
After these fixes:
- No more `.htaccess` errors
- No more "GuzzleHttp\HandlerStack not found" (after composer install)
- Webhook should respond with 200 OK
- Bot should respond to /start command

