# Social Media Icons Feature

## Overview

Social media links in channel descriptions now display as **clickable image icons** instead of emoji text links.

## Setup

### 1. Upload Icon Images

Place your icon images in the following directory:
```
/var/www/web.reape.rs/img/icons/
```

### 2. Required Files

The following icon files should be uploaded:

| Platform   | Filename              | Purpose                    |
|------------|-----------------------|----------------------------|
| Instagram  | ts3-instagram.png     | Instagram profile links    |
| Facebook   | ts3-facebook.png      | Facebook profile links     |
| YouTube    | ts3-youtube.png       | YouTube channel links      |
| Twitter    | ts3-twitter.png       | Twitter/X profile links    |
| Steam      | ts3-steam.png         | Steam profile links        |
| SoundCloud | ts3-soundcloud.png    | SoundCloud profile links   |
| GitHub     | ts3-github.png        | GitHub profile links       |
| Telegram   | ts3-telegram.png      | Telegram contact links     |
| Twitch     | ts3-twitch.png        | Twitch channel links       |
| Discord    | ts3-discord.png       | Discord invite links       |

### 3. Upload Commands

```bash
# Navigate to icons directory
cd /var/www/web.reape.rs/img/icons/

# Upload your files (example using scp)
scp ts3-*.png user@server:/var/www/web.reape.rs/img/icons/

# Or if files are already on server, move them:
mv /path/to/ts3-*.png /var/www/web.reape.rs/img/icons/

# Set proper permissions
chmod 644 /var/www/web.reape.rs/img/icons/ts3-*.png
chown www-data:www-data /var/www/web.reape.rs/img/icons/ts3-*.png
```

### 4. Verify Files Exist

```bash
ls -la /var/www/web.reape.rs/img/icons/ts3-*.png
```

You should see all 10 icon files listed.

## How It Works

### Before (Emoji Links)

```bbcode
Social Media:
[url=https://instagram.com/user]📷 Instagram[/url] [url=https://twitter.com/user]🐦 Twitter[/url]
```

Visual:
```
Social Media:
📷 Instagram  🐦 Twitter
```

### After (Image Icons)

```bbcode
Social Media:
[url=https://instagram.com/user][img]http://web.reape.rs/img/icons/ts3-instagram.png[/img][/url] [url=https://twitter.com/user][img]http://web.reape.rs/img/icons/ts3-twitter.png[/img][/url]
```

Visual:
```
Social Media:
[Instagram Icon] [Twitter Icon]
(both clickable)
```

## Complete Channel Description Format

```bbcode
[center]
[img=250x250]http://web.reape.rs/img/avatars/116527.webp[/img]

[size=14][b][url=client://44347/UID~nickname]nickname[/url][/b][/size]

[color=#00ff00][size=12][b]✓ ONLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

[img]icon_123[/img] Rank 1

[size=10][b]Social Media:[/b][/size]
[url=https://instagram.com/user][img]http://web.reape.rs/img/icons/ts3-instagram.png[/img][/url] [url=https://twitter.com/user][img]http://web.reape.rs/img/icons/ts3-twitter.png[/img][/url] [url=https://steamcommunity.com/id/user][img]http://web.reape.rs/img/icons/ts3-steam.png[/img][/url]

[hr]
[size=10]Profile description...[/size]

[/center]
[hr]
[right][size=8]Last updated: 2025-12-30 21:00:00[/size][/right]
```

## Visual Result

```
┌────────────────────────────────┐
│                                │
│      [Avatar 250x250]          │
│                                │
│         Nickname               │
│     (clickable link)           │
│                                │
│        ✓ ONLINE               │
│                                │
│       View Profile             │
│                                │
│     [★] Rank 1                │
│                                │
│     Social Media:              │
│  [IG] [TW] [ST] [YT]          │
│  (clickable icons)             │
│                                │
│ ─────────────────────────────  │
│  Profile description...        │
│                                │
└────────────────────────────────┘
─────────────────────────────────
Last updated: 2025-12-30 21:00:00
```

## Icon Specifications

### Recommended Icon Size
- **Dimensions**: 32x32 or 64x64 pixels
- **Format**: PNG (with transparency)
- **File size**: Under 50KB each
- **Background**: Transparent

### Example Icon Design
```
┌──────────┐
│          │
│   [IG]   │  ← Instagram logo
│          │
└──────────┘
  32x32px
```

### Color Scheme
Use platform brand colors or match your TeamSpeak theme:
- Instagram: Purple/Pink gradient
- Twitter: Blue (#1DA1F2)
- YouTube: Red (#FF0000)
- Steam: Dark blue/gray
- etc.

## Testing

### Step 1: Upload Icons

```bash
cd /var/www/web.reape.rs/img/icons/
ls -la ts3-*.png
```

Verify all 10 files exist.

### Step 2: Run Bot

```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

### Step 3: Check TeamSpeak

1. Open TeamSpeak client
2. Navigate to your configured channel
3. Check the channel description
4. You should see **image icons** instead of emojis
5. **Click the icons** - they should open the social media URLs

## Troubleshooting

### Icons Not Showing

**Issue**: Icons appear as broken images or don't display

**Solutions**:

1. **Check files exist**:
   ```bash
   ls -la /var/www/web.reape.rs/img/icons/ts3-*.png
   ```

2. **Check file permissions**:
   ```bash
   chmod 644 /var/www/web.reape.rs/img/icons/ts3-*.png
   ```

3. **Test URL in browser**:
   ```
   http://web.reape.rs/img/icons/ts3-instagram.png
   ```
   Should display the icon

4. **Check web server serves PNG files**:
   ```bash
   curl -I http://web.reape.rs/img/icons/ts3-instagram.png
   ```
   Should return `Content-Type: image/png`

### Icons Not Clickable

**Issue**: Icons display but don't open links when clicked

**Cause**: BBCode syntax issue or TeamSpeak version

**Solution**: Verify the BBCode format in channel description:
```bbcode
[url=LINK][img]ICON_URL[/img][/url]
```

### Icons Too Large/Small

**Issue**: Icons appear too big or too small

**Solutions**:

1. **Resize images** to 32x32 or 64x64 pixels:
   ```bash
   convert ts3-instagram.png -resize 32x32 ts3-instagram-small.png
   ```

2. **Or use BBCode sizing** (edit `StatusDisplayManager.php`):
   ```php
   $description .= "[url={$url}][img=32x32]{$iconUrl}[/img][/url] ";
   ```

### Missing Platform Icon

**Issue**: User has a social media link but icon doesn't show

**Cause**: Platform not mapped or file missing

**Solution**: 

1. Check the platform is in the mapping:
   ```php
   $socialIcons = [
       'instagram' => 'ts3-instagram.png',
       // Add missing platform here
   ];
   ```

2. Add the icon file if missing

## Adding New Platforms

To add support for more social media platforms:

### Step 1: Add to Profile Edit Page

Edit `src/edit-profile.php`, add to `$socialKeys` array:
```php
$socialKeys = ["instagram","facebook","youtube","twitter","steam",
               "soundcloud","github","telegram","twitch","discord",
               "tiktok"]; // Add new platform
```

### Step 2: Add Icon Mapping

Edit `src/private/php/Utils/StatusDisplayManager.php`:
```php
$socialIcons = [
    'instagram' => 'ts3-instagram.png',
    // ... other platforms ...
    'tiktok' => 'ts3-tiktok.png', // Add new platform
];
```

### Step 3: Upload Icon

```bash
# Upload ts3-tiktok.png to /var/www/web.reape.rs/img/icons/
```

### Step 4: Test

Update user profile with new social link and run bot.

## Icon Resources

### Where to Get Icons

1. **Official Brand Assets**:
   - Instagram: https://about.instagram.com/brand/
   - Twitter: https://about.twitter.com/en/who-we-are/brand-toolkit
   - YouTube: https://www.youtube.com/about/brand-resources/
   - etc.

2. **Icon Libraries**:
   - Font Awesome (free icons)
   - Flaticon
   - Icons8
   - Material Design Icons

3. **Custom Creation**:
   - Design matching your server theme
   - Use platform colors
   - Keep consistent size/style

### Converting Icons

If you have SVG or other formats:

```bash
# Convert SVG to PNG (32x32)
convert icon.svg -resize 32x32 ts3-platform.png

# Convert JPG to PNG with transparency
convert icon.jpg -transparent white ts3-platform.png

# Batch convert all icons
for file in *.svg; do
    convert "$file" -resize 32x32 "ts3-${file%.svg}.png"
done
```

## Performance Considerations

- ✅ **Small file sizes** - Keep under 50KB per icon
- ✅ **PNG format** - Best for logos with transparency
- ✅ **Cached by TeamSpeak** - Icons are cached client-side
- ✅ **CDN compatible** - Can host on CDN for faster loading
- ✅ **Minimal overhead** - Only loaded once per channel view

## Security

- ✅ **Read-only permissions** - Icons should be 644
- ✅ **No executable code** - PNG files are safe
- ✅ **Validated URLs** - Social links stored in database
- ✅ **HTTPS recommended** - Use HTTPS for icon URLs

## Examples

### User with All Platforms

If user has all 10 platforms linked:

```
Social Media:
[IG][FB][YT][TW][ST][SC][GH][TG][TC][DC]
```

All icons are clickable and open respective profiles.

### User with Limited Platforms

If user only has Instagram and Twitter:

```
Social Media:
[IG][TW]
```

Only these two icons appear.

### No Social Media

If user has no social links:
```
(Social Media section not displayed)
```

## Summary

✅ **Image icons** instead of emojis  
✅ **Clickable links** - Icons open social profiles  
✅ **Professional look** - Clean branded icons  
✅ **10 platforms supported** - All major social networks  
✅ **Easy to add more** - Simple mapping system  

Upload your icons to `/var/www/web.reape.rs/img/icons/` and test:

```bash
php src/private/php/status-display-bot.php --once
```

Your social media links will now display as clickable icons! 🎨
