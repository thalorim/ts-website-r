# Avatar Display Feature

## What's New

The channel description now displays the user's avatar image before their username!

## How It Works

The bot automatically:
1. Fetches the user's avatar from their profile
2. Converts relative URLs to absolute URLs
3. Displays the avatar using BBCode `[img]` tag
4. Falls back to default avatar if user hasn't set one

## Channel Description Format (With Avatar)

```bbcode
[center]
[img]http://web.reape.rs/img/avatars/116527_1234567890.png[/img]

Username

✓ ONLINE

View Profile

[Icon] Server Group

📷 Instagram  🐦 Twitter

Profile description...
[/center]
```

## Visual Result in TeamSpeak

```
┌──────────────────────────────┐
│      [Avatar Image]          │
│                              │
│        Username              │
│                              │
│      ✓ ONLINE               │
│                              │
│     View Profile             │
│                              │
│  [Icon] Server Group         │
│                              │
│  📷 Instagram  🐦 Twitter    │
│                              │
│  Profile description...      │
└──────────────────────────────┘
```

## Setting User Avatars

Users can set their avatar via the profile edit page:

1. Visit: `http://web.reape.rs/edit-profile.php`
2. Upload avatar image (PNG, JPG, or WebP)
3. Max file size: 2MB
4. Image is stored in `img/avatars/` directory

## Avatar URL Resolution

The system automatically handles:
- ✅ **Relative URLs**: `img/avatars/116527_123.png` → `http://web.reape.rs/img/avatars/116527_123.png`
- ✅ **Absolute URLs**: Already includes `http://` or `https://`
- ✅ **Default avatar**: Uses `img/icons/defaulticon-128.png` if no custom avatar

## Supported Image Formats

- PNG (recommended for transparency)
- JPG/JPEG
- WebP (modern browsers)
- GIF (for animated avatars)

## Avatar Size Recommendations

For best display in TeamSpeak:
- **Recommended size**: 128x128 pixels
- **Maximum size**: 512x512 pixels
- **Minimum size**: 64x64 pixels
- **Aspect ratio**: 1:1 (square)

## Testing

Test the avatar display:

```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

Then check the channel in TeamSpeak - you should see the user's avatar!

## Customizing Avatar Display

You can customize how avatars are displayed by editing `StatusDisplayManager.php`:

### Example: Smaller Avatar

```php
$description .= "[size=10][img]" . htmlspecialchars($avatarUrl) . "[/img][/size]\n\n";
```

### Example: Avatar with Border

```php
$description .= "━━━━━━━━━━━━━━\n";
$description .= "[img]" . htmlspecialchars($avatarUrl) . "[/img]\n";
$description .= "━━━━━━━━━━━━━━\n\n";
```

### Example: Avatar Link (Clickable)

```php
$description .= "[url=" . $profileUrl . "][img]" . htmlspecialchars($avatarUrl) . "[/img][/url]\n\n";
```

### Example: Side-by-Side with Name

```php
$description .= "[img]" . htmlspecialchars($avatarUrl) . "[/img] ";
$description .= "[size=14][b]" . htmlspecialchars($nickname) . "[/b][/size]\n\n";
```

## TeamSpeak BBCode Image Support

TeamSpeak supports:
- ✅ `[img]URL[/img]` - Display image
- ✅ `[img=WIDTHxHEIGHT]URL[/img]` - Resize image
- ✅ HTTP and HTTPS URLs
- ✅ Most common image formats

## Troubleshooting

### Avatar Not Showing

1. **Check URL is accessible**:
   ```bash
   curl -I http://web.reape.rs/img/avatars/YOUR_AVATAR.png
   ```
   Should return HTTP 200

2. **Check file permissions**:
   ```bash
   ls -la /var/www/web.reape.rs/img/avatars/
   ```
   Files should be readable (644 or 755)

3. **Check TeamSpeak can reach the URL**:
   - Your website must be accessible from the TeamSpeak server
   - If TeamSpeak is on a different server, ensure firewall allows access
   - HTTPS is preferred over HTTP

### Avatar Shows Broken Image Icon

- URL is incorrect or file doesn't exist
- File format not supported by TeamSpeak
- Website requires authentication
- CORS or security headers blocking image

### Default Avatar Not Showing

Check the default icon exists:
```bash
ls -la /var/www/web.reape.rs/img/icons/defaulticon-128.png
```

If missing, create or download one.

## Security Considerations

- ✅ URLs are properly escaped with `htmlspecialchars()`
- ✅ Only uploaded files are used (from profile system)
- ✅ File type validation on upload
- ✅ Size limits enforced (2MB for avatars)

## Performance

- Avatars are fetched once per channel update
- TeamSpeak caches images
- No impact on bot performance
- Images load directly from your web server

## Example Configuration

For user **116527** in channel **251746**:
- User uploads avatar via profile page
- Avatar stored at: `img/avatars/116527_1703955432.png`
- Bot converts to: `http://web.reape.rs/img/avatars/116527_1703955432.png`
- TeamSpeak displays avatar in channel description

## Advanced: Using External Avatar URLs

If you want to use external URLs (e.g., from Discord, Steam, etc.), you can store them in the profile:

```sql
UPDATE ts_profiles 
SET avatar_url = 'https://cdn.discordapp.com/avatars/123456/abc123.png'
WHERE cldbid = 116527;
```

The bot will automatically use the full URL.

## Summary

✅ **Avatar now displays before username**
✅ **Automatic URL resolution**
✅ **Default avatar fallback**
✅ **Works with uploaded or external URLs**
✅ **No additional configuration needed**

Test it now:
```bash
php src/private/php/status-display-bot.php --once
```

Check your TeamSpeak channel - the avatar should be visible! 🎨
