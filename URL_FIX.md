# URL Fix - Base URL and Avatar Size

## Issues Fixed

### Issue 1: Incorrect URLs
URLs were being generated with the script path included:
- ❌ `http://web.reape.rs/admin/status-display.php/profile.php?cldbid=116527`
- ❌ `http://web.reape.rs/admin/status-display.php/img/avatars/116527.webp`

Should be:
- ✅ `http://web.reape.rs/profile.php?cldbid=116527`
- ✅ `http://web.reape.rs/img/avatars/116527.webp`

### Issue 2: Avatar Size
Avatar was displayed at full size, needed to be resized to 250x250.

## Fixes Applied

### 1. Fixed Base URL Generation

**File**: `src/private/php/Utils/StatusDisplayManager.php`

The `getBaseUrl()` method now:
- Properly detects the website root
- Removes script paths (`/src`, `/admin`, `/private/php`)
- Returns clean base URL: `http://web.reape.rs`

**How it works**:
```php
// Detects: /var/www/web.reape.rs/admin/status-display.php
// Finds: /admin
// Removes: /admin
// Returns: http://web.reape.rs
```

### 2. Resized Avatar to 250x250

**File**: `src/private/php/Utils/StatusDisplayManager.php`

Changed from:
```php
[img]URL[/img]
```

To:
```php
[img=250x250]URL[/img]
```

This uses TeamSpeak BBCode syntax to resize the image to 250x250 pixels.

## Testing

### Step 1: Test Base URL

Visit this test page to verify URLs are correct:
```
http://web.reape.rs/admin/test-base-url.php
```

This will show:
- ✓ Generated base URL
- ✓ Profile URL example
- ✓ Avatar URL example
- ✓ Server variables

### Step 2: Run the Bot

```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

### Step 3: Check TeamSpeak Channel

1. Open TeamSpeak client
2. Navigate to your configured channel (e.g., 251746)
3. Check the description

You should see:
- ✅ Avatar displayed at 250x250 size
- ✅ Profile link: `http://web.reape.rs/profile.php?cldbid=116527`
- ✅ Avatar URL: `http://web.reape.rs/img/avatars/116527.webp`

## Expected Output

```bbcode
[center]
[img=250x250]http://web.reape.rs/img/avatars/116527_1766940753.webp[/img]

[size=14][b]Username[/b][/size]

[color=#00ff00][size=12][b]✓ ONLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

...
[/center]
```

## Visual Result

```
┌──────────────────────────────┐
│                              │
│    [Avatar 250x250]          │
│                              │
│        Username              │
│                              │
│      ✓ ONLINE               │
│                              │
│     View Profile             │
│   (correct link)             │
│                              │
└──────────────────────────────┘
```

## Troubleshooting

### URLs Still Wrong?

1. **Check the test page**: `http://web.reape.rs/admin/test-base-url.php`
2. **View server variables** on the test page
3. **If still wrong**, you can hardcode the base URL:

Edit `StatusDisplayManager.php`, find the `getBaseUrl()` method and replace it with:

```php
private function getBaseUrl(): string {
    return 'http://web.reape.rs'; // Hardcoded
}
```

### Avatar Not Showing?

1. **Check file exists**:
   ```bash
   ls -la /var/www/web.reape.rs/img/avatars/116527_1766940753.webp
   ```

2. **Check URL in browser**:
   ```
   http://web.reape.rs/img/avatars/116527_1766940753.webp
   ```
   Should display the image

3. **Check permissions**:
   ```bash
   chmod 644 /var/www/web.reape.rs/img/avatars/*.webp
   ```

### Avatar Wrong Size?

The `[img=250x250]` BBCode should resize it automatically in TeamSpeak. If it's not working:

1. **Check TeamSpeak version** - Older versions might not support resizing
2. **Manually resize images** before upload (recommended anyway)
3. **Use smaller source images** - 250x250 or 512x512 source images work best

## Different Avatar Sizes

You can change the size by editing `StatusDisplayManager.php`:

### Smaller (128x128)
```php
$description .= "[img=128x128]" . htmlspecialchars($avatarUrl) . "[/img]\n\n";
```

### Larger (512x512)
```php
$description .= "[img=512x512]" . htmlspecialchars($avatarUrl) . "[/img]\n\n";
```

### Original Size
```php
$description .= "[img]" . htmlspecialchars($avatarUrl) . "[/img]\n\n";
```

### Custom Size (200x150)
```php
$description .= "[img=200x150]" . htmlspecialchars($avatarUrl) . "[/img]\n\n";
```

## Summary

✅ **Base URL fixed** - No more script path in URLs
✅ **Avatar resized** - Now displays at 250x250 pixels
✅ **Profile links correct** - Direct to profile page
✅ **Avatar links correct** - Direct to image files

Test now:
```bash
# 1. Test URLs
http://web.reape.rs/admin/test-base-url.php

# 2. Run bot
php src/private/php/status-display-bot.php --once

# 3. Check TeamSpeak channel
```

Everything should now work correctly! 🎉
