# News Channel Display - Troubleshooting Guide

This guide helps you resolve common issues with the News Channel Display system.

## Issue 1: PHP Fatal Error - "Failed opening required 'src/private/php/load.php'"

### Problem
```bash
PHP Fatal error: require(): Failed opening required 'src/private/php/load.php'
```

### Solution

**The issue is with the current working directory.** You need to be in the correct directory before running PHP commands.

#### Option A: Change Directory First (Recommended)
```bash
# Navigate to your website root directory
cd /var/www/web.reape.rs

# Then run the command (note: we're already in the right directory)
php -r "require 'src/private/php/load.php'; \$result = \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels(); echo json_encode(\$result, JSON_PRETTY_PRINT) . PHP_EOL;"
```

#### Option B: Use Absolute Path
```bash
# Use the full path to load.php
php -r "require '/var/www/web.reape.rs/src/private/php/load.php'; \$result = \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels(); echo json_encode(\$result, JSON_PRETTY_PRINT) . PHP_EOL;"
```

#### Option C: Use the CLI Script (Easiest!)
We created a dedicated CLI script that handles paths correctly:

```bash
# Find your website directory first
cd /var/www/web.reape.rs

# Run the CLI script
php src/private/php/update-news-channels-cli.php
```

#### Option D: Check Your Website Path
Your website might not be in `/var/www/web.reape.rs`. Find it:

```bash
# Find where your website is located
find /var/www -name "load.php" -path "*/src/private/php/load.php" 2>/dev/null
```

Example output:
```
/var/www/web.reape.rs/public_html/src/private/php/load.php
```

If it's in a subdirectory like `public_html`, adjust your path:
```bash
cd /var/www/web.reape.rs/public_html
php src/private/php/update-news-channels-cli.php
```

---

## Issue 2: API Endpoint Returns 500 Internal Server Error

### Problem
```
Error code: 500 Internal Server Error
The site could be temporarily unavailable or too busy.
```

### Causes and Solutions

#### Cause 1: Missing Configuration Token

**Check if you have set the API token in your config.**

Edit your config file:
```bash
nano /var/www/web.reape.rs/src/private/config.local.php
```

Add this line:
```php
$config['news_channel_update_token'] = 'your-secure-random-token-here';
```

Generate a secure token:
```bash
# Generate a random 32-character token
openssl rand -hex 32
```

Example:
```php
$config['news_channel_update_token'] = 'a7f8b9c2d3e4f5g6h7i8j9k0l1m2n3o4';
```

#### Cause 2: PHP Error in the Code

**Check your PHP error logs:**

```bash
# Common PHP error log locations
tail -f /var/log/php/error.log
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
tail -f /var/www/web.reape.rs/logs/error.log

# If you don't know where logs are:
find /var/log -name "*error*log" -type f 2>/dev/null
```

**Enable error display temporarily (for testing only):**

Create a test file:
```bash
nano /var/www/web.reape.rs/src/api/test-news-update.php
```

Add this content:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define("DISABLE_CSRF_CHECK", true);

require_once __DIR__ . "/../private/php/load.php";

use Wruczek\TSWebsite\Utils\NewsChannelDisplayManager;

try {
    echo "<pre>";
    echo "Testing News Channel Display Update\n";
    echo "====================================\n\n";
    
    $manager = NewsChannelDisplayManager::i();
    echo "✓ Manager initialized\n";
    
    $configs = $manager->getConfigurations();
    echo "✓ Found " . count($configs) . " configuration(s)\n\n";
    
    if (empty($configs)) {
        echo "⚠ No configurations found!\n";
        echo "Please add configurations via admin panel:\n";
        echo "https://web.reape.rs/admin/news-channel-display.php\n";
    } else {
        echo "Updating channels...\n";
        $result = $manager->updateAllChannels();
        echo "\nResult:\n";
        print_r($result);
    }
    
    echo "\n✓ Test completed successfully!\n";
    echo "</pre>";
} catch (\Exception $e) {
    echo "<pre>";
    echo "✗ Error: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
    echo "</pre>";
}
```

Then access it:
```
https://web.reape.rs/api/test-news-update.php
```

#### Cause 3: Missing Database Table

**The table might not have been created.** Access the admin panel to auto-create it:

```
https://web.reape.rs/admin/news-channel-display.php
```

Or create it manually:
```bash
cd /var/www/web.reape.rs
php src/private/php/test-news-channel-setup.php
```

#### Cause 4: TeamSpeak Connection Issue

**Test your TeamSpeak connection:**

```bash
cd /var/www/web.reape.rs
php -r "require 'src/private/php/load.php'; \$ts = \Wruczek\TSWebsite\Utils\TeamSpeakUtils::i(); if (\$ts->checkTSConnection()) { echo 'TeamSpeak OK\n'; } else { echo 'TeamSpeak FAILED\n'; print_r(\$ts->getExceptionsList()); }"
```

#### Cause 5: News Table Empty

**Check if you have news in the database:**

```bash
cd /var/www/web.reape.rs
php -r "require 'src/private/php/load.php'; \$store = new \Wruczek\TSWebsite\News\DefaultNewsStore(); echo 'News count: ' . \$store->getNewsCount() . PHP_EOL;"
```

If it returns 0, add news via the admin panel.

---

## Quick Diagnostic Script

Run this to diagnose all issues at once:

```bash
# Navigate to your website directory
cd /var/www/web.reape.rs  # Adjust this path if needed

# Run the diagnostic script
php src/private/php/test-news-channel-setup.php
```

This will check:
- ✓ Database connection
- ✓ TeamSpeak connection
- ✓ NewsChannelDisplayManager
- ✓ News data availability
- ✓ Channel access
- ✓ Configuration details

---

## Common Command Patterns

### Pattern 1: Run CLI Update Script
```bash
cd /var/www/web.reape.rs
php src/private/php/update-news-channels-cli.php
```

### Pattern 2: Run Test/Diagnostic Script
```bash
cd /var/www/web.reape.rs
php src/private/php/test-news-channel-setup.php
```

### Pattern 3: Cron Job Setup
```bash
# Edit crontab
crontab -e

# Add this line (adjust path to your website)
0 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php >> /var/log/news-channel-update.log 2>&1
```

### Pattern 4: API Call with Token
```bash
# First, set your token in config.local.php
# Then call the API:
curl "https://web.reape.rs/api/update-news-channels.php?token=YOUR_TOKEN_HERE"
```

---

## Step-by-Step First Time Setup

### Step 1: Test Basic Setup
```bash
cd /var/www/web.reape.rs  # Adjust path
php src/private/php/test-news-channel-setup.php
```

### Step 2: Add Configuration
1. Go to: `https://web.reape.rs/admin/news-channel-display.php`
2. Log in as admin
3. Click "Add New Configuration"
4. Select a channel
5. Click "Add Configuration"

### Step 3: Test Manual Update
```bash
cd /var/www/web.reape.rs
php src/private/php/update-news-channels-cli.php
```

### Step 4: Check TeamSpeak Channel
Open TeamSpeak and check if the channel description was updated.

### Step 5: Set Up Automation (Optional)
```bash
crontab -e
# Add:
0 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php
```

---

## FAQ

**Q: Where is my website directory?**
```bash
find /var/www -name "load.php" -path "*/src/private/php/load.php" 2>/dev/null
```

**Q: How do I generate a secure token?**
```bash
openssl rand -hex 32
```

**Q: How do I check PHP error logs?**
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

**Q: The admin panel shows "Forbidden"**
- Check that you're logged in as admin
- Check the admin UID in config matches your user

**Q: Updates work manually but not via API**
- Make sure you set `news_channel_update_token` in config
- Check the token in your API URL matches the config
- Check PHP error logs for details

**Q: "Channel not found" error**
- Verify the channel ID is correct
- Check the channel exists in TeamSpeak
- Make sure the channel wasn't deleted

**Q: "Permission denied" on channel update**
- Bot needs `b_channel_modify_description` permission
- Check your TeamSpeak query bot permissions

---

## Still Having Issues?

1. **Check PHP error logs** (see locations above)
2. **Run the diagnostic script**: `php src/private/php/test-news-channel-setup.php`
3. **Check file permissions**: `ls -la src/private/php/load.php`
4. **Verify PHP version**: `php -v` (should be 7.4+)
5. **Check database connection** via main website
6. **Test TeamSpeak connection** via main website

## Getting Help

When asking for help, provide:
1. Output of: `php src/private/php/test-news-channel-setup.php`
2. PHP error logs (last 50 lines)
3. Your PHP version: `php -v`
4. Your website directory path
5. Any error messages you see

---

Good luck! The most common issue is running commands from the wrong directory. Always `cd` to your website directory first!
