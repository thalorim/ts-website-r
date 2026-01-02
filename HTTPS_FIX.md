# HTTPS URL Fix

## Issue

Links in channel descriptions were showing as `http://` instead of `https://` even though the website uses SSL.

**Example**:
- ❌ `http://web.reape.rs/profile.php?cldbid=116527`
- ❌ `http://web.reape.rs/img/avatars/116527.webp`

**Expected**:
- ✅ `https://web.reape.rs/profile.php?cldbid=116527`
- ✅ `https://web.reape.rs/img/avatars/116527.webp`

## Root Cause

When the bot runs via **CLI (Command Line Interface)**, it doesn't have access to `$_SERVER['HTTPS']` like a web request would. The `getBaseUrl()` method was defaulting to `http://` protocol.

## Fix Applied

Updated `src/private/php/Utils/StatusDisplayManager.php` to:

1. **Detect CLI mode** - When running via command line (bot)
2. **Default to HTTPS** - For production domains
3. **Smart detection** - HTTP for localhost/local domains

### New Logic

```php
if (php_sapi_name() === 'cli') {
    // Running via CLI (bot) - default to HTTPS for production
    $protocol = 'https';
} else {
    // Running via web - check actual HTTPS status
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}

// Override: If localhost/local domain, use HTTP
if (strpos($host, 'localhost') !== false || 
    strpos($host, '127.0.0.1') !== false || 
    strpos($host, '.local') !== false) {
    $protocol = 'http';
}
```

## Testing

### Test 1: Run Bot

```bash
php src/private/php/status-display-bot.php --once
```

### Test 2: Check Channel Description

Open TeamSpeak and check the channel description. All URLs should now use `https://`:

```bbcode
[url=https://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]
[img=250x250]https://web.reape.rs/img/avatars/116527.webp[/img]
[url=https://instagram.com/user][img]https://web.reape.rs/img/icons/ts3-instagram.png[/img][/url]
```

### Test 3: Use Test Page

Visit:
```
http://web.reape.rs/admin/test-base-url.php
```

Should show base URL as: `https://web.reape.rs`

## Manual Override (Optional)

If auto-detection doesn't work perfectly, you can hardcode the base URL:

### Option 1: Simple Hardcode

Edit `src/private/php/Utils/StatusDisplayManager.php`, replace the entire `getBaseUrl()` method with:

```php
private function getBaseUrl(): string {
    return 'https://web.reape.rs';
}
```

### Option 2: Config-Based

Create a config entry in your database:

```sql
INSERT INTO ts_config (identifier, type, value, user_editable) 
VALUES ('base_url', 'STRING', 'https://web.reape.rs', 1);
```

Then update the method:

```php
private function getBaseUrl(): string {
    // Try to get from config first
    $configUrl = Config::get('base_url', null);
    if ($configUrl !== null) {
        return rtrim($configUrl, '/');
    }
    
    // Fall back to auto-detection
    // ... rest of the code ...
}
```

## Verification

### Check All URLs in Channel Description

After running the bot, verify these URLs use HTTPS:

1. **Profile URL**
   ```
   https://web.reape.rs/profile.php?cldbid=116527
   ```

2. **Avatar URL**
   ```
   https://web.reape.rs/img/avatars/116527_1766940753.webp
   ```

3. **Social Media Icon URLs**
   ```
   https://web.reape.rs/img/icons/ts3-instagram.png
   https://web.reape.rs/img/icons/ts3-twitter.png
   https://web.reape.rs/img/icons/ts3-steam.png
   ```

### Test in TeamSpeak

1. **Profile Link** - Click "View Profile" → Should open HTTPS URL
2. **Avatar Image** - Should load securely (padlock icon in browser if checked)
3. **Social Icons** - Should load securely

## Why HTTPS Matters

### Security Benefits
- ✅ **Encrypted connection** - Data transmitted securely
- ✅ **Browser trust** - No "Not Secure" warnings
- ✅ **SEO benefits** - Search engines prefer HTTPS
- ✅ **Modern standard** - Expected for all websites

### TeamSpeak Considerations
- ✅ **Image loading** - Some TeamSpeak clients prefer HTTPS
- ✅ **Mixed content** - Avoid HTTP/HTTPS mixing
- ✅ **Security warnings** - Users won't see warnings

## Troubleshooting

### URLs Still Show HTTP

**Check**:
1. Bot is using the new code
2. Restart the bot: `pkill -f status-display-bot && php src/private/php/status-display-bot.php --daemon --interval=30`
3. Run once to update: `php src/private/php/status-display-bot.php --once`

**Test base URL generation**:
```bash
cd /var/www/web.reape.rs
php -r "
require 'src/private/php/load.php';
require 'src/private/php/Utils/StatusDisplayManager.php';
\$manager = \Wruczek\TSWebsite\Utils\StatusDisplayManager::i();
\$reflection = new ReflectionClass(\$manager);
\$method = \$reflection->getMethod('getBaseUrl');
\$method->setAccessible(true);
\$url = \$method->invoke(\$manager);
echo 'Base URL: ' . \$url . PHP_EOL;
echo 'Protocol: ' . parse_url(\$url, PHP_URL_SCHEME) . PHP_EOL;
"
```

Should output:
```
Base URL: https://web.reape.rs
Protocol: https
```

### SSL Certificate Issues

If TeamSpeak can't load HTTPS images:

1. **Check SSL certificate** is valid:
   ```bash
   curl -I https://web.reape.rs/img/icons/ts3-instagram.png
   ```
   Should return HTTP 200 with valid SSL

2. **Test image URL** in browser:
   ```
   https://web.reape.rs/img/icons/ts3-instagram.png
   ```
   Should load without certificate warnings

3. **Check SSL configuration**:
   - Valid certificate (not self-signed)
   - Not expired
   - Correct domain name
   - Intermediate certificates installed

### Localhost Development

If developing on localhost, URLs should automatically use HTTP:

```
http://localhost/profile.php?cldbid=116527
http://127.0.0.1/img/avatars/116527.webp
```

The fix automatically detects localhost and uses HTTP.

## Environment-Specific Configuration

### Production (web.reape.rs)
```
Protocol: HTTPS (automatic)
URL: https://web.reape.rs
```

### Development (localhost)
```
Protocol: HTTP (automatic)
URL: http://localhost
```

### Custom Domain
```
Protocol: HTTPS (automatic for non-localhost)
URL: https://your-domain.com
```

## Testing Checklist

After applying fix:

- [ ] Restart bot with new code
- [ ] Run `--once` to update channel
- [ ] Check channel description in TeamSpeak
- [ ] Verify profile link uses HTTPS
- [ ] Verify avatar URL uses HTTPS
- [ ] Verify social media icon URLs use HTTPS
- [ ] Click links to ensure they work
- [ ] No SSL/security warnings

## Additional Notes

### Bot Restart Required

**Important**: You must restart the bot for changes to take effect:

```bash
# Stop old bot
pkill -f status-display-bot

# Start new bot with updated code
php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &
```

### Web vs CLI Detection

The fix uses `php_sapi_name()` to detect execution context:

- **CLI** (bot): `php_sapi_name() === 'cli'` → Defaults to HTTPS
- **Web** (admin pages): Uses `$_SERVER['HTTPS']` → Auto-detects

### Future Improvements

You can enhance this by:

1. **Database config** - Store base URL in database
2. **Environment variable** - Use `.env` file
3. **Config file** - Add to config.local.php

Example config.local.php:
```php
return [
    'base_url' => 'https://web.reape.rs',
];
```

## Summary

✅ **Bot now generates HTTPS URLs**
✅ **Auto-detects CLI vs web context**
✅ **Smart localhost handling**
✅ **No manual configuration needed**
✅ **All links use secure HTTPS**

Restart the bot and all URLs will use HTTPS! 🔒
