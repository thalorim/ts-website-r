# Steam Signature Feature

## Overview

When a user has a Steam profile linked in their social media, the channel description automatically displays a **clickable Steam signature** below the social media icons.

## How It Works

### Automatic Detection

1. **User adds Steam profile** via profile edit page
2. **Bot extracts Steam ID** from the profile URL
3. **Signature is generated** using your custom signature script
4. **Displayed in channel** as clickable image

### Supported Steam URL Formats

The bot can extract Steam IDs from these formats:

#### Format 1: Direct Profile (Recommended)
```
https://steamcommunity.com/profiles/76561198399534638
```
✅ **Works automatically** - Steam64 ID extracted directly

#### Format 2: Without HTTPS
```
steamcommunity.com/profiles/76561198399534638
```
✅ **Works automatically**

#### Format 3: Custom URL
```
https://steamcommunity.com/id/username
```
❌ **Requires additional setup** - Custom URLs need API lookup

#### Format 4: Direct Steam ID Parameter
```
https://example.com/steam?steamid=76561198399534638
```
✅ **Works automatically** - Extracts from steamid parameter

## Channel Description Output

### When User Has Steam Profile

```bbcode
[center]
[img=250x250]http://web.reape.rs/img/avatars/116527.webp[/img]

[size=14][b][url=client://44347/UID~nickname]nickname[/url][/b][/size]

[color=#00ff00][size=12][b]✓ ONLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

[img]icon_123[/img] Rank 1

[size=10][b]Social Media:[/b][/size]
[url=https://instagram.com/user][img]http://web.reape.rs/img/icons/ts3-instagram.png[/img][/url] [url=https://twitter.com/user][img]http://web.reape.rs/img/icons/ts3-twitter.png[/img][/url] [url=https://steamcommunity.com/profiles/76561198399534638][img]http://web.reape.rs/img/icons/ts3-steam.png[/img][/url]

[url=https://steamcommunity.com/profiles/76561198399534638][img]https://www.reape.rs/signature/signature.php?steamid=76561198399534638[/img][/url]

[hr]
[size=10]Profile description...[/size]

[/center]
[hr]
[right][size=8]Last updated: 2025-12-30 21:15:00[/size][/right]
```

### Visual Result

```
┌────────────────────────────────┐
│                                │
│      [Avatar 250x250]          │
│                                │
│         Nickname               │
│                                │
│        ✓ ONLINE               │
│                                │
│       View Profile             │
│                                │
│     [★] Rank 1                │
│                                │
│     Social Media:              │
│  [IG] [TW] [ST] [YT]          │
│                                │
│  ┌──────────────────────────┐ │
│  │  [Steam Signature]       │ │
│  │  Shows: Avatar, Name,    │ │
│  │  Level, Games, Hours     │ │
│  └──────────────────────────┘ │
│  (clickable - opens Steam)    │
│                                │
│ ─────────────────────────────  │
│  Profile description...        │
│                                │
└────────────────────────────────┘
─────────────────────────────────
Last updated: 2025-12-30 21:15:00
```

## Your Steam Signature Script

### Signature URL Format

```
https://www.reape.rs/signature/signature.php?steamid=STEAM64ID
```

The bot automatically replaces `STEAM64ID` with the extracted ID.

### What the Signature Shows

Your custom signature script typically displays:
- 🖼️ Steam avatar
- 👤 Steam username
- 🎮 Account level
- 📊 Games owned
- ⏰ Hours played
- 🏆 Achievements
- 📈 Status (Online/Offline/In-Game)

## Setup Instructions

### Step 1: User Adds Steam Profile

Users add their Steam profile via profile edit page:
```
http://web.reape.rs/edit-profile.php
```

**Steam URL examples**:
- `https://steamcommunity.com/profiles/76561198399534638`
- `steamcommunity.com/profiles/76561198399534638`

### Step 2: Bot Extracts Steam ID

When the bot updates the channel:
1. Reads user's social media links
2. Finds Steam profile URL
3. Extracts the 64-bit Steam ID
4. Generates signature URL
5. Adds to channel description

### Step 3: Signature Displays

The signature:
- ✅ Loads from `https://www.reape.rs/signature/signature.php`
- ✅ Is clickable (opens Steam profile)
- ✅ Updates dynamically based on Steam status
- ✅ Shows real-time Steam data

## Steam ID Formats

### 64-bit Steam ID (Steam64)

**Format**: 17 digits starting with `7656`

**Example**: `76561198399534638`

This is the format your signature script expects.

### How to Find Steam64 ID

#### Method 1: Steam Profile URL
If profile URL is:
```
https://steamcommunity.com/profiles/76561198399534638
```
The Steam64 ID is: `76561198399534638`

#### Method 2: Using SteamID Converter
Visit: https://steamid.io/
Enter any Steam URL or ID format
Get the Steam64 ID

#### Method 3: Steam API
Call Steam API with custom URL:
```
https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=API_KEY&vanityurl=username
```

## Custom URL Handling

### Issue with Custom URLs

Steam allows custom URLs like:
```
https://steamcommunity.com/id/coolgamer123
```

These require an **API lookup** to get the Steam64 ID.

### Solution: Auto-Convert Feature

If you want to support custom URLs, you can add Steam API integration:

Edit `src/private/php/Utils/StatusDisplayManager.php`, update the `extractSteamId()` method:

```php
private function extractSteamId(string $steamUrl): ?string {
    // Try to extract 64-bit Steam ID first
    if (preg_match('/\/profiles\/(\d{17})/', $steamUrl, $matches)) {
        return $matches[1];
    }
    
    // If it's a custom URL, resolve it via Steam API
    if (preg_match('/\/id\/([a-zA-Z0-9_-]+)/', $steamUrl, $matches)) {
        $customUrl = $matches[1];
        $apiKey = 'YOUR_STEAM_API_KEY'; // Get from steamcommunity.com/dev/apikey
        
        $apiUrl = "https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?" . 
                  "key={$apiKey}&vanityurl={$customUrl}";
        
        $response = @file_get_contents($apiUrl);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['response']['steamid'])) {
                return $data['response']['steamid'];
            }
        }
    }
    
    return null;
}
```

**Note**: Requires Steam API key from https://steamcommunity.com/dev/apikey

## Testing

### Step 1: Add Steam Profile

1. Log in to website
2. Go to: `http://web.reape.rs/edit-profile.php`
3. Add Steam profile URL: `https://steamcommunity.com/profiles/76561198399534638`
4. Save

### Step 2: Run Bot

```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

### Step 3: Check Channel

1. Open TeamSpeak
2. Navigate to configured channel
3. View channel description
4. You should see:
   - Social media icons (including Steam)
   - Steam signature below icons
   - Signature is clickable

### Step 4: Test Signature

1. **Click the signature** - Should open Steam profile
2. **Check signature loads** - Should show Steam data
3. **Verify Steam ID** - Should match user's profile

## Troubleshooting

### Signature Not Showing

**Possible causes**:

1. **No Steam profile linked**
   - User hasn't added Steam URL
   - Solution: Add Steam profile via edit page

2. **Steam ID not extracted**
   - URL format not recognized
   - Custom URL used (requires API)
   - Solution: Use direct profile URL format

3. **Signature script error**
   - Signature PHP script not working
   - Solution: Test signature URL directly in browser

### Signature Shows Error

**Test signature URL**:
```
https://www.reape.rs/signature/signature.php?steamid=76561198399534638
```

**Should display**: Steam signature image

**If error**: Check your signature script logs

### Wrong Steam ID

**Symptoms**: Signature shows wrong user or error

**Solution**: 
1. Check extracted Steam ID in bot logs
2. Verify user's Steam URL format
3. Test extraction manually

### Signature Not Clickable

**Check BBCode format**:
```bbcode
[url=STEAM_PROFILE_URL][img]SIGNATURE_URL[/img][/url]
```

Both URL tags must be present.

## Customizing Signature

### Change Signature Service

To use a different signature service, edit `StatusDisplayManager.php`:

```php
// Change this line:
$signatureUrl = "https://www.reape.rs/signature/signature.php?steamid={$steamId}";

// To your service:
$signatureUrl = "https://example.com/steam-sig.php?id={$steamId}";
```

### Alternative Signature Services

Popular Steam signature generators:
- SteamSignature.com
- Steamsignature.de
- Custom PHP scripts
- Your own `reape.rs/signature/` service

### Add Additional Parameters

```php
$signatureUrl = "https://www.reape.rs/signature/signature.php?" . 
                "steamid={$steamId}&theme=dark&size=large";
```

## Privacy Considerations

### What's Exposed

- ✅ Steam profile URL (already public)
- ✅ Steam64 ID (public identifier)
- ✅ Steam profile data (from signature)

**Note**: Only data already visible on Steam Community is shown.

### User Control

Users can:
- ✅ Choose to add/remove Steam profile
- ✅ Control signature visibility (by not linking Steam)
- ✅ Change Steam privacy settings on Steam

## Performance

- ✅ **Signature loads from external service** - No impact on bot
- ✅ **Cached by TeamSpeak** - Image cached client-side
- ✅ **No API calls in bot** - Simple URL generation
- ✅ **Fast extraction** - Regex pattern matching

## Example Complete Output

User with full profile including Steam:

```
         [Avatar 250x250]

            XXIVI
        (click to interact!)

          ✓ ONLINE

        View Profile

       [★] Diamond Rank

        Social Media:
     [IG][FB][YT][TW][ST]

    ┌───────────────────────┐
    │   [Steam Signature]   │
    │   XXIVI - Level 42    │
    │   150 Games           │
    │   2,500 Hours         │
    │   Currently In-Game   │
    └───────────────────────┘
         (click for Steam)

  ─────────────────────────
    Profile description...
  ─────────────────────────
       Last updated: 2025-12-30 21:15:00
```

## Summary

✅ **Auto-detects Steam profiles**  
✅ **Extracts Steam64 ID**  
✅ **Displays custom signature**  
✅ **Clickable (opens Steam profile)**  
✅ **Works with direct profile URLs**  
✅ **No manual configuration needed**  

Add a Steam profile to your user account and test:

```bash
php src/private/php/status-display-bot.php --once
```

Your Steam signature will appear in the channel! 🎮
