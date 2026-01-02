# Steam Signature Troubleshooting Guide

## Issue: Signature Not Displaying in Channel

If the Steam signature is not appearing in your TeamSpeak channel description, follow these debugging steps:

## Diagnostic Tools

I've created three admin tools to help diagnose the issue:

### 1. Test Steam Signature Extraction
```
http://web.reape.rs/admin/test-steam-signature.php
```

**What it does**:
- Lists all users with social media links
- Shows which ones have Steam profiles
- Tests Steam ID extraction
- Displays what the signature URL will be

### 2. View Channel Description Preview
```
http://web.reape.rs/admin/view-channel-description.php
```

**What it does**:
- Shows the actual BBCode that will be sent to TeamSpeak
- Displays whether signature is included
- Shows extracted Steam ID
- Previews complete channel description

### 3. Check Social Icons
```
http://web.reape.rs/admin/check-social-icons.php
```

**What it does**:
- Verifies social media icon files exist
- Tests icon URLs
- Shows preview of all icons

## Step-by-Step Debugging

### Step 1: Verify User Has Steam Profile

**Check via database**:
```sql
SELECT cldbid, nickname, socials_json 
FROM ts_profiles 
WHERE socials_json LIKE '%steam%';
```

**Check via admin tool**:
Visit: `http://web.reape.rs/admin/test-steam-signature.php`

**Expected**: You should see at least one user with a Steam URL

**If no Steam profile**: User needs to add their Steam profile via `edit-profile.php`

### Step 2: Verify Steam ID Extraction

**Test URL formats**:

✅ **These WILL work**:
```
https://steamcommunity.com/profiles/76561198399534638
steamcommunity.com/profiles/76561198399534638
http://steamcommunity.com/profiles/76561198399534638
```

❌ **These WON'T work** (custom URLs need API):
```
https://steamcommunity.com/id/username
steamcommunity.com/id/coolgamer
```

**Test extraction**:
1. Go to: `http://web.reape.rs/admin/test-steam-signature.php`
2. Enter the Steam URL in the test form
3. Click "Test Extract"
4. Should show the extracted 17-digit Steam ID

**If extraction fails**: 
- URL format is not supported
- Use direct profile URL with Steam64 ID instead

### Step 3: Test Signature URL

**Manual test**:
```
https://www.reape.rs/signature/signature.php?steamid=76561198399534638
```

**What should happen**:
- Opens in browser
- Displays a Steam signature image
- Shows user's Steam profile data

**If signature doesn't load**:
- Check your signature PHP script
- Verify Steam API key (if used)
- Check server logs: `tail -f /var/log/apache2/error.log`

### Step 4: Check Generated BBCode

**View description preview**:
Visit: `http://web.reape.rs/admin/view-channel-description.php`

**What to look for**:
```bbcode
[url=STEAM_URL][img]https://www.reape.rs/signature/signature.php?steamid=ID[/img][/url]
```

**If signature BBCode is missing**:
- Steam ID extraction failed
- Check bot logs: `tail -f /var/log/ts-status-bot.log`
- Check PHP error logs: `tail -f /var/log/php7.4-fpm.log`

### Step 5: Run Bot with Debugging

**Stop existing bot**:
```bash
pkill -f status-display-bot
```

**Run bot once with output**:
```bash
cd /var/www/web.reape.rs
php src/private/php/status-display-bot.php --once
```

**Check output for**:
- "Steam Signature Added: steamid=..." log message
- Any errors about Steam
- Update success/failure

**If no "Steam Signature Added" log**:
- Steam ID not being extracted
- Check extraction logic

### Step 6: View Raw Channel Description

**In TeamSpeak**:
1. Right-click the channel
2. Edit Channel
3. View "Description" field
4. Should contain the BBCode with signature URL

**If BBCode is there but not displaying**:
- TeamSpeak might be blocking external images
- Check TeamSpeak client settings
- Try a different TeamSpeak client

### Step 7: Check Bot Logs

**View bot logs**:
```bash
tail -f /var/log/ts-status-bot.log
```

**Look for**:
- "Steam Signature Added" message
- Any errors or warnings
- Update completion messages

**Enable debug mode** (edit bot script):
Add at top:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Common Issues and Solutions

### Issue 1: Steam ID Not Extracted

**Symptoms**:
- Admin tool shows "Not extracted" for Steam ID
- No signature in channel description
- No "Steam Signature Added" log

**Solutions**:

1. **Check URL format**:
   ```bash
   # Should contain 17-digit ID
   https://steamcommunity.com/profiles/76561198399534638
   ```

2. **Test extraction manually**:
   Go to `test-steam-signature.php` and test the URL

3. **Update user's Steam URL**:
   - Edit profile
   - Use direct profile URL format
   - Include the 17-digit Steam64 ID

### Issue 2: Signature URL Returns Error

**Symptoms**:
- Steam ID extracted correctly
- Signature URL in BBCode
- But signature shows error/blank image

**Solutions**:

1. **Test signature URL directly**:
   ```
   https://www.reape.rs/signature/signature.php?steamid=76561198399534638
   ```

2. **Check signature script**:
   ```bash
   tail -f /var/log/apache2/error.log
   ```

3. **Verify Steam API**:
   - If using Steam API, check API key
   - Test API calls manually
   - Check rate limits

### Issue 3: BBCode Present But Not Displaying

**Symptoms**:
- BBCode is in channel description (verified in edit)
- Signature URL works in browser
- But doesn't show in TeamSpeak client

**Solutions**:

1. **Check TeamSpeak client settings**:
   - Settings → Application → Show images in messages
   - Should be enabled

2. **Test with different client**:
   - Try another computer
   - Try TeamSpeak mobile app

3. **Check image URL**:
   - Must be HTTPS (recommended)
   - Must be publicly accessible
   - No authentication required

### Issue 4: Signature Shows Wrong User

**Symptoms**:
- Signature displays
- But shows different user's data

**Solutions**:

1. **Verify extracted Steam ID**:
   Use `test-steam-signature.php`

2. **Check user's Steam URL**:
   Might be linking to wrong profile

3. **Update profile**:
   Edit profile and save again

## Additional Debugging Commands

### Check User's Profile Data
```bash
mysql -u USER -p DATABASE -e "SELECT cldbid, nickname, socials_json FROM ts_profiles WHERE cldbid=116527"
```

### Check Configuration
```bash
mysql -u USER -p DATABASE -e "SELECT * FROM ts_channel_status_display"
```

### Test Bot Manually
```bash
cd /var/www/web.reape.rs
php -r "
require 'src/private/php/load.php';
\$manager = \Wruczek\TSWebsite\Utils\StatusDisplayManager::i();
\$reflection = new ReflectionClass(\$manager);
\$method = \$reflection->getMethod('extractSteamId');
\$method->setAccessible(true);
\$result = \$method->invoke(\$manager, 'https://steamcommunity.com/profiles/76561198399534638');
echo 'Extracted ID: ' . (\$result ?? 'null') . \"\n\";
"
```

## Quick Checklist

Use this checklist to diagnose the issue:

- [ ] User has Steam profile linked (check `test-steam-signature.php`)
- [ ] Steam URL is in correct format (profiles/ID not id/username)
- [ ] Steam ID is extracted (17-digit number)
- [ ] Signature URL works in browser
- [ ] BBCode is in channel description (check via TeamSpeak edit)
- [ ] Bot ran successfully (no errors in logs)
- [ ] TeamSpeak client shows images (check settings)
- [ ] Social media icons uploaded (check `check-social-icons.php`)

## Still Not Working?

If you've gone through all steps and it's still not working:

### Collect Debug Information

1. **Run diagnostic tools**:
   ```
   http://web.reape.rs/admin/test-steam-signature.php
   http://web.reape.rs/admin/view-channel-description.php
   ```

2. **Collect logs**:
   ```bash
   tail -100 /var/log/ts-status-bot.log > debug.txt
   tail -100 /var/log/php7.4-fpm.log >> debug.txt
   ```

3. **Get channel description**:
   - Copy raw BBCode from TeamSpeak channel edit
   - Check if signature URL is present

4. **Test signature URL**:
   - Open in browser
   - Take screenshot if error

### Manual Test

Try adding signature manually to channel description:

```bbcode
[url=https://steamcommunity.com/profiles/76561198399534638][img]https://www.reape.rs/signature/signature.php?steamid=76561198399534638[/img][/url]
```

If this works manually but not via bot:
- Bot code issue
- Check bot is using latest code
- Restart bot

If this doesn't work manually:
- TeamSpeak client issue
- Image URL issue
- Signature script issue

## Contact Information

For further help:
- Check bot logs
- Review all diagnostic tool outputs
- Test signature URL directly
- Verify Steam ID extraction

The diagnostic tools should identify the exact issue! 🔍
