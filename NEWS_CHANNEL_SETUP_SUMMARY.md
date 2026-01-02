# News Channel Display - Setup Summary

## ✅ System Overview

The News Channel Display system allows you to automatically update TeamSpeak channel descriptions with the latest news from your website database.

## 🔐 Admin Access

**Authentication Method:** CLDBID-based (not UID)

**Default Admin CLDBID:** `116527`

**To access the admin panel, you must:**
1. Be logged in to the website
2. Have CLDBID 116527 (or change this in the code)

**To change the allowed CLDBID:**
- Edit: `src/admin/news-channel-display.php` (line ~20)
- See: `CHANGE_ADMIN_CLDBID.md` for detailed instructions

## 📁 Files Created

### Core System Files
```
src/private/php/Utils/NewsChannelDisplayManager.php  ← Main manager class
src/admin/news-channel-display.php                    ← Admin panel UI
src/api/update-news-channels.php                      ← API endpoint
```

### Helper Scripts
```
src/private/php/update-news-channels-cli.php          ← CLI update script
src/private/php/test-news-channel-setup.php           ← Diagnostic script
```

### Documentation
```
NEWS_CHANNEL_DISPLAY_README.md                        ← Full documentation
NEWS_CHANNEL_DISPLAY_QUICK_START.md                   ← Quick setup guide
NEWS_CHANNEL_DISPLAY_IMPLEMENTATION.md                ← Technical details
NEWS_CHANNEL_CONFIG_EXAMPLE.php                       ← Configuration examples
TROUBLESHOOTING_NEWS_CHANNEL.md                       ← Troubleshooting guide
CHANGE_ADMIN_CLDBID.md                                ← How to change admin access
```

## 🚀 Quick Setup (5 Steps)

### Step 1: Access Admin Panel
```
URL: https://web.reape.rs/admin/news-channel-display.php
Login with: User who has CLDBID 116527
```

If you get "403 Forbidden", you need to change the CLDBID in the code.

### Step 2: Add Configuration
1. Click "Add New Configuration"
2. Select a channel
3. Set news limit (default: 5)
4. Choose display options
5. Click "Add Configuration"

### Step 3: Test Update
Click the blue sync button (🔄) next to your configuration to update immediately.

### Step 4: Verify in TeamSpeak
Open TeamSpeak and check the channel description. You should see formatted news!

### Step 5: Set Up Automation (Optional)
```bash
# Navigate to your website directory
cd /var/www/web.reape.rs  # Adjust this path!

# Test manual update
php src/private/php/update-news-channels-cli.php

# If it works, add to cron:
crontab -e

# Add this line (update every hour):
0 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php
```

## 🔧 Command Reference

### Navigate to Website Directory FIRST
```bash
cd /var/www/web.reape.rs  # Adjust to your actual path
```

### Run Diagnostic Test
```bash
php src/private/php/test-news-channel-setup.php
```

### Manual Update
```bash
php src/private/php/update-news-channels-cli.php
```

### One-Line Test
```bash
cd /var/www/web.reape.rs && php src/private/php/test-news-channel-setup.php
```

## ⚠️ Common Issues

### Issue 1: "Failed opening required 'src/private/php/load.php'"
**Solution:** You're in the wrong directory!
```bash
# Always navigate to your website root FIRST
cd /var/www/web.reape.rs
# Then run the command
php src/private/php/update-news-channels-cli.php
```

### Issue 2: "403 Forbidden" on Admin Panel
**Solution:** You don't have CLDBID 116527
```bash
# Option A: Find your CLDBID and change it in the code
# See: CHANGE_ADMIN_CLDBID.md

# Option B: Log in with the user who has CLDBID 116527
```

### Issue 3: "500 Internal Server Error" on API
**Solutions:**
1. Check PHP error logs: `tail -f /var/log/apache2/error.log`
2. Run diagnostic: `php src/private/php/test-news-channel-setup.php`
3. Make sure database table exists (visit admin panel)
4. Check TeamSpeak connection

### Issue 4: "No news available"
**Solution:** Add news first!
- Go to main admin panel
- Use "Post News" form
- Or check if news exists in database

## 📊 Configuration Options Explained

### News Limit (1-20)
- How many news items to show
- Lower = cleaner, shorter description
- Higher = more comprehensive archive

### Show Content (Yes/No)
- **Yes**: Shows title + date + content preview
- **No**: Shows only title + date

### Max Length (50-1000 characters)
- Maximum characters of content to display
- Content longer than this gets truncated with "..."

### Enabled (Yes/No)
- Toggle configuration on/off without deleting
- Disabled configs are skipped during updates

## 🎯 Example Configurations

### Main Info Channel
```
Channel: "📰 Latest News"
News Limit: 3
Show Content: Yes
Max Length: 200
```

### News Archive Channel
```
Channel: "📚 News Archive"
News Limit: 15
Show Content: Yes
Max Length: 300
```

### Headlines Channel
```
Channel: "📌 Headlines"
News Limit: 10
Show Content: No
```

## 🤖 Automation Options

### Option 1: Cron Job (Recommended)
```bash
# Every hour at :00
0 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php

# Every 30 minutes
*/30 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php

# Every 15 minutes
*/15 * * * * cd /var/www/web.reape.rs && php src/private/php/update-news-channels-cli.php
```

### Option 2: API Endpoint
```bash
# Set token in config first:
# $config['news_channel_update_token'] = 'YOUR_TOKEN';

# Then call API:
curl "https://web.reape.rs/api/update-news-channels.php?token=YOUR_TOKEN"

# Or with cron:
0 * * * * curl -s "https://web.reape.rs/api/update-news-channels.php?token=YOUR_TOKEN"
```

### Option 3: After Posting News
```php
// In your news posting code
$newsStore->addNews($title, $content);

// Immediately update channels
\Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();
```

## 📖 Documentation Reference

| File | Purpose |
|------|---------|
| `NEWS_CHANNEL_DISPLAY_README.md` | Complete documentation |
| `NEWS_CHANNEL_DISPLAY_QUICK_START.md` | 5-minute setup guide |
| `TROUBLESHOOTING_NEWS_CHANNEL.md` | Fix common problems |
| `CHANGE_ADMIN_CLDBID.md` | Change admin access |
| `NEWS_CHANNEL_CONFIG_EXAMPLE.php` | Configuration examples |
| `NEWS_CHANNEL_DISPLAY_IMPLEMENTATION.md` | Technical details |

## ✅ Verification Checklist

Before going live, verify:

- [ ] Admin panel accessible: `https://web.reape.rs/admin/news-channel-display.php`
- [ ] CLDBID authentication works (you can log in)
- [ ] Configuration can be added via admin panel
- [ ] Manual update button works
- [ ] Channel description updates in TeamSpeak
- [ ] News displays correctly formatted
- [ ] CLI script works: `php src/private/php/update-news-channels-cli.php`
- [ ] Diagnostic passes: `php src/private/php/test-news-channel-setup.php`
- [ ] Cron job set up (optional)
- [ ] Error logging enabled

## 🆘 Getting Help

**Quick Diagnostic:**
```bash
cd /var/www/web.reape.rs
php src/private/php/test-news-channel-setup.php
```

**Check Logs:**
```bash
tail -f /var/log/apache2/error.log
tail -f /var/log/php/error.log
```

**Common Paths:**
- Admin Panel: `/admin/news-channel-display.php`
- API Endpoint: `/api/update-news-channels.php`
- CLI Script: `/src/private/php/update-news-channels-cli.php`
- Config File: `/src/private/config.local.php`

## 🎉 Success Indicators

You'll know it's working when:

1. ✅ Admin panel loads without errors
2. ✅ Configuration list shows your channels
3. ✅ Update button shows success message
4. ✅ TeamSpeak channel shows formatted news
5. ✅ "Last Updated" timestamp updates
6. ✅ CLI script completes without errors

---

**Current CLDBID Requirement:** 116527

**To change:** Edit `src/admin/news-channel-display.php` line ~20

**Need help?** Run diagnostic script first!
