# Clickable Client Links and Timestamp Footer

## New Features Added

### 1. ✅ Clickable Client Name (When Online)

When a user is **online**, their name in the channel description becomes a **clickable TeamSpeak client link**.

**Format**: `[URL=client://CLID/UID~NICKNAME]NICKNAME[/URL]`

**Example**: `[URL=client://44280/6bOV9YfaghIOi6m6UnY46DxNPJo=~XXIVI]XXIVI[/URL]`

**What happens when clicked**:
- Opens a context menu in TeamSpeak
- Allows you to: Send message, Poke, View info, etc.
- Direct interaction with the user

**When offline**: Name is displayed normally (not clickable)

### 2. ✅ Fixed Offline Status Display

The "OFFLINE" text now properly displays when user is disconnected.

**Before**: Text might not appear correctly  
**After**: Always shows "[color=#ff0000][size=12][b]✗ OFFLINE[/b][/size][/color]"

### 3. ✅ Timestamp Footer

Every channel description now includes a footer showing when it was last updated.

**Format**: 
```bbcode
[hr]
[right][size=8]Last updated: 2025-12-30 20:45:32[/size][/right]
```

**Updates**: Every time the bot runs and updates the channel

## Complete Channel Description Format

### When User is ONLINE

```bbcode
[center]
[img=250x250]http://web.reape.rs/img/avatars/116527.webp[/img]

[size=14][b][url=client://44280/6bOV9YfaghIOi6m6UnY46DxNPJo=~XXIVI]XXIVI[/url][/b][/size]

[color=#00ff00][size=12][b]✓ ONLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

[img]icon_123[/img] Rank 1

[size=10][b]Social Media:[/b][/size]
[url=https://instagram.com/user]📷 Instagram[/url] [url=https://twitter.com/user]🐦 Twitter[/url]

[hr]
[size=10]User's profile description...[/size]

[/center]
[hr]
[right][size=8]Last updated: 2025-12-30 20:45:32[/size][/right]
```

### When User is OFFLINE

```bbcode
[center]
[img=250x250]http://web.reape.rs/img/avatars/116527.webp[/img]

[size=14][b]XXIVI[/b][/size]

[color=#ff0000][size=12][b]✗ OFFLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

[img]icon_123[/img] Rank 1

[size=10][b]Social Media:[/b][/size]
[url=https://instagram.com/user]📷 Instagram[/url] [url=https://twitter.com/user]🐦 Twitter[/url]

[hr]
[size=10]User's profile description...[/size]

[/center]
[hr]
[right][size=8]Last updated: 2025-12-30 20:45:32[/size][/right]
```

## Visual Representation

### Online Display
```
┌─────────────────────────────┐
│                             │
│    [Avatar 250x250]         │
│                             │
│        XXIVI                │
│    (clickable link!)        │
│                             │
│      ✓ ONLINE              │
│    (green color)            │
│                             │
│     View Profile            │
│                             │
│  [★] Rank 1                │
│                             │
│  📷 Instagram  🐦 Twitter   │
│                             │
│ ──────────────────────────  │
│  Profile description...     │
│                             │
└─────────────────────────────┘
──────────────────────────────
Last updated: 2025-12-30 20:45
```

### Offline Display
```
┌─────────────────────────────┐
│                             │
│    [Avatar 250x250]         │
│                             │
│        XXIVI                │
│   (not clickable)           │
│                             │
│      ✗ OFFLINE             │
│     (red color)             │
│                             │
│     View Profile            │
│                             │
│  [★] Rank 1                │
│                             │
│  📷 Instagram  🐦 Twitter   │
│                             │
│ ──────────────────────────  │
│  Profile description...     │
│                             │
└─────────────────────────────┘
──────────────────────────────
Last updated: 2025-12-30 20:45
```

## How Clickable Links Work

### Requirements for Clickable Name:
1. ✅ User must be **online**
2. ✅ Bot must have access to:
   - Client ID (clid)
   - Unique Identifier (uid)
   - Nickname

### Data Sources:
- **Primary**: Live client list from TeamSpeak (when online)
- **Fallback**: Profile database (for UID)
- **Last Resort**: TeamSpeak clientDbInfo query

### URL Format Breakdown:
```
client://[CLID]/[UID]~[NICKNAME]

Example:
client://44280/6bOV9YfaghIOi6m6UnY46DxNPJo=~XXIVI

Parts:
- client:// = TeamSpeak protocol
- 44280 = Client ID (changes each connection)
- 6bOV9YfaghIOi6m6UnY46DxNPJo= = Unique Identifier (permanent)
- ~XXIVI = Nickname (URL encoded)
```

### What Users Can Do When Clicking:
- 💬 Send text message
- 📣 Poke user
- 👁️ View client info
- 🔊 Whisper
- 📋 Copy client URL
- ➕ Add to contacts
- 🚫 Ignore/block
- 👑 Assign server group (if admin)

## Timestamp Details

### Format:
- **Date**: YYYY-MM-DD (ISO 8601)
- **Time**: HH:MM:SS (24-hour format)
- **Example**: 2025-12-30 20:45:32

### When Updated:
- Every time bot checks and finds state change
- Every time manual update is triggered
- Shows exact time of last channel edit

### Customizing Timestamp Format:

Edit `StatusDisplayManager.php`, find the timestamp line:

**Current**:
```php
$timestamp = date('Y-m-d H:i:s');
```

**Examples**:

**12-hour format**:
```php
$timestamp = date('Y-m-d h:i:s A'); // 2025-12-30 08:45:32 PM
```

**Relative time**:
```php
$timestamp = date('M j, Y @ g:i A'); // Dec 30, 2025 @ 8:45 PM
```

**Unix timestamp**:
```php
$timestamp = time(); // 1735590332
```

**Human readable**:
```php
$timestamp = date('F jS, Y \a\t g:i A'); // December 30th, 2025 at 8:45 PM
```

## Testing

### Test the Updates

```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

### Check Results

1. **Open TeamSpeak client**
2. **Navigate to your configured channel** (e.g., 251746)
3. **Check channel description**

**Verify**:
- ✅ Avatar displays (250x250)
- ✅ Username is clickable when online (right-click to test)
- ✅ Status shows "ONLINE" (green) or "OFFLINE" (red)
- ✅ Timestamp appears at bottom
- ✅ Horizontal line separates sections

### Test Clickable Link

**When user is online**:
1. Right-click the username in description
2. Context menu should appear
3. Options: Send message, Poke, etc.

**When user is offline**:
1. Username is plain text (not clickable)
2. Status shows "✗ OFFLINE" in red

## Troubleshooting

### Username Not Clickable

**Possible causes**:
1. User is offline (expected - not clickable when offline)
2. Bot couldn't get client ID or UID
3. TeamSpeak client version too old

**Solution**:
Check bot logs for errors when fetching client data.

### Offline Status Not Showing

If "OFFLINE" text doesn't appear:
1. Check bot logs: `tail -f /var/log/ts-status-bot.log`
2. Verify user is actually offline
3. Run bot with `--once` flag to test

### Timestamp Not Updating

If timestamp is stuck:
1. Check bot is running: `ps aux | grep status-display-bot`
2. Restart bot: `pkill -f status-display-bot && nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &`

### Wrong Timezone

If timestamp shows wrong time:
1. Check server timezone: `date`
2. Set PHP timezone in `php.ini`: `date.timezone = "Europe/London"`
3. Or set in code:
```php
date_default_timezone_set('Europe/London');
$timestamp = date('Y-m-d H:i:s');
```

## Privacy Considerations

### Clickable Links Expose:
- Client ID (temporary, changes each connection)
- Unique Identifier (permanent, but already visible in TeamSpeak)
- Nickname (public information)

**Note**: This is standard TeamSpeak functionality and doesn't expose additional private information.

## Performance Impact

- ✅ **Minimal** - Fetches client data from cache
- ✅ **No extra queries** - Uses existing client list
- ✅ **Fast** - URL encoding is lightweight
- ✅ **Efficient** - Timestamp is simple date format

## Summary

✅ **Clickable client name** when online  
✅ **Fixed offline status** display  
✅ **Timestamp footer** on all channel updates  
✅ **Horizontal rules** for visual separation  
✅ **Professional appearance**  

Test now:
```bash
php src/private/php/status-display-bot.php --once
```

Then check your TeamSpeak channel - you should see all the new features! 🎉

## Example Real Output

Here's what a real channel description will look like:

**User XXIVI is ONLINE**:
```
         [Avatar Image 250x250]

              XXIVI
          (click to interact!)

            ✓ ONLINE

          View Profile

        [★] Diamond Rank

         Social Media:
      📷 Instagram  🐦 Twitter

    ─────────────────────────
      Competitive CS2 player
      Streaming on Twitch...
    ─────────────────────────
             Last updated: 2025-12-30 20:45:32
```

Try it now! 🚀
