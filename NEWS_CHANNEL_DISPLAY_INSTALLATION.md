# News Channel Display - Installation Guide

## Quick Start

This feature is now fully integrated into your TeamSpeak website. Follow these steps to start using it:

## Step 1: Verify Installation

All necessary files have been created:
- ✅ NewsDisplayManager utility class
- ✅ Admin configuration page
- ✅ API endpoint
- ✅ Background bot script
- ✅ Auto-update integration with news saving

## Step 2: Access the Configuration Page

1. Log in to your TeamSpeak website as admin (CLDBID 3)
2. Go to: `https://your-website.com/admin/`
3. Click the **"News Channel Display"** button in the Quick Links section
4. Or visit directly: `https://your-website.com/admin/news-channel-display.php`

## Step 3: Add Your First Channel Configuration

1. On the News Channel Display page, click **"Add Channel Configuration"**
2. Select a channel from the dropdown (shows all channels on your TeamSpeak server)
3. Set how many news items to display (default: 5, range: 1-20)
4. Click **"Add Configuration"**
5. The channel will be immediately updated with your latest news!

## Step 4: Verify It's Working

1. Open TeamSpeak client
2. Navigate to the channel you configured
3. Right-click → View Channel Description
4. You should see your latest news formatted nicely with BBCode

## Step 5: Automatic Updates (Optional)

### Option A: Automatic via Web Interface (Already Active!)
News channels are automatically updated when you add news through the admin panel.

### Option B: Bot Script for Continuous Monitoring

If you want continuous monitoring (for manual database edits), run:

```bash
# Start the bot in daemon mode (5 minute check interval)
php src/private/php/news-display-bot.php --daemon --interval=300
```

Or run in background:
```bash
nohup php src/private/php/news-display-bot.php --daemon --interval=300 > /tmp/news-bot.log 2>&1 &
```

## Features Overview

### Admin Panel Features:
- ✨ Add/remove channel configurations
- ✨ Adjust news limit per channel (1-20 items)
- ✨ Manual update button for each channel
- ✨ Bulk update all channels at once
- ✨ View last update timestamp for each channel
- ✨ See total news count and configured channels

### What Gets Displayed:
- 📰 News title (as heading)
- 📅 Publication date (and edit date if edited)
- 📝 Content preview (up to 300 characters)
- 🔗 Link to full news page on your website
- ⏰ Last updated timestamp

## Database Table

The feature automatically creates a table `channel_news_display` with:
- Channel ID
- News limit
- Enable/disable flag
- Last updated timestamp
- Created/updated timestamps

This table is created automatically when you first access the admin page.

## Permissions Required

### TeamSpeak Bot Permissions:
Your TeamSpeak query user needs:
- ✅ `b_channel_modify_description` - To update channel descriptions
- ✅ Server connection permissions

These are usually already granted if your Status Display feature works.

### Web User Permissions:
- ✅ Admin access (CLDBID 3) to configure channels
- ✅ CSRF token validation for security

## Testing

### Test the Setup:
1. Add a news item through admin panel
2. Configure a channel for news display
3. Check the channel description in TeamSpeak
4. Add another news item - channel should auto-update!

### Manual Update Test:
```bash
# Test the bot runs correctly
php src/private/php/news-display-bot.php --once
```

### API Test:
```bash
# If you have curl and are logged in:
curl -X POST https://your-website.com/api/news-display-update.php
```

## Troubleshooting

### "Channel not updating"
- Check TeamSpeak connection in main config
- Verify bot has channel modify permissions
- Check PHP error logs: `tail -f /var/log/apache2/error.log`

### "Configuration not saving"
- Verify database connection
- Check that channel ID exists on your server
- Ensure you're logged in as CLDBID 3

### "Bot not running"
- Check if process is running: `ps aux | grep news-display-bot`
- Check bot logs if you redirected output
- Verify PHP CLI is available: `which php`

## Uninstallation

If you need to remove this feature:

1. Stop bot if running:
   ```bash
   pkill -f news-display-bot.php
   ```

2. Remove database table:
   ```sql
   DROP TABLE IF EXISTS `channel_news_display`;
   ```

3. Delete files:
   ```bash
   rm src/private/php/Utils/NewsDisplayManager.php
   rm src/admin/news-channel-display.php
   rm src/api/news-display-update.php
   rm src/private/php/news-display-bot.php
   ```

4. Revert changes to:
   - `src/api/save-news.php`
   - `src/private/templates/admin.latte`

## Support

For issues or questions:
- Check the main documentation: `NEWS_CHANNEL_DISPLAY_FEATURE.md`
- Review existing Status Display setup (similar architecture)
- Check TeamSpeak server logs
- Check PHP error logs

## Tips

💡 **Best Practices:**
- Start with 5 news items per channel (default)
- Use clear, concise news titles
- Keep news content under 500 characters for better display
- Update channel permissions to prevent users from editing descriptions
- Monitor bot logs periodically
- Test with one channel first before adding multiple

💡 **Performance:**
- Bot default interval: 300 seconds (5 minutes)
- Each channel update takes ~1-2 seconds
- Multiple channels are updated sequentially
- No impact on website performance (runs separately)

Enjoy your new News Channel Display feature! 🎉
