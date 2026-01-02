# News Channel Display - Quick Start Guide

Get your TeamSpeak channels showing the latest news from your website in just a few minutes!

## Quick Setup (5 Minutes)

### Step 1: Access the Admin Panel

1. Log in to your website as an admin
2. Go to the main admin panel: `https://your-website.com/admin/`
3. Click on "News Channel Display" button
   - Or go directly to: `https://your-website.com/admin/news-channel-display.php`

### Step 2: Add Your First Configuration

1. Click the **"Add New Configuration"** button (big blue button at the top)
2. Fill in the form:
   ```
   Channel: Select the channel where you want news displayed
   Number of News Items: 5 (default is good to start)
   Show News Content: ✓ Checked (to show preview)
   Maximum Content Length: 200 (characters)
   ```
3. Click **"Add Configuration"**

### Step 3: Update the Channel

1. Click the blue **sync button** (🔄) next to your new configuration
2. The channel description will be updated immediately!
3. Check your TeamSpeak channel to see the news

**That's it! Your channel is now displaying news!**

## What You'll See

The channel description will show something like:

```
╔══════════════════════════════╗
║     Latest News              ║
╚══════════════════════════════╝

1. Server Update v2.0 Released
   Posted: 2026-01-02 15:30
   We're excited to announce the release of our server 
   update v2.0 with many new features...

2. New Rules Effective Immediately
   Posted: 2026-01-01 10:00
   Please take a moment to review our updated community
   rules that are now in effect...

─────────────────────────────────
Last updated: 2026-01-02 16:00:00
```

## Common Configurations

### For Info/Announcement Channels
```
News Items: 3-5
Show Content: ✓ Yes
Max Length: 150-200
```

### For News Archive Channels
```
News Items: 10-15
Show Content: ✓ Yes
Max Length: 300-400
```

### For Quick Headlines Channel
```
News Items: 5-10
Show Content: ✗ No (titles only)
Max Length: N/A
```

## One-Click Updates

After adding news to your website:

1. Go to the News Channel Display admin page
2. Click **"Update All Channels"** (green button at top)
3. All enabled channels will refresh with the latest news

## Pro Tips

### Tip 1: Multiple Channels
You can configure different channels with different settings:
- Main channel: Top 3 news with full content
- Archive channel: Top 20 news with titles only
- Different language channels: Same news, different channels

### Tip 2: Disable Temporarily
Don't want to delete a configuration? Just click the **pause button** (⏸) to disable it temporarily.

### Tip 3: Automatic Updates
Set up a cron job to update automatically (see full README for details):
```bash
# Every hour
0 * * * * cd /path/to/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
```

## Troubleshooting

### "Channel not updating"
- Make sure you have news posts in your database
- Check that the channel ID is correct
- Verify bot has permissions to edit channel descriptions

### "Configuration not found"
- The table might not be created yet
- Try accessing the admin panel page again to trigger table creation

### "Permission denied"
- Make sure you're logged in as an admin
- Check the admin UID in the config matches your user

## Next Steps

1. ✓ Set up your first news channel
2. ✓ Test by updating a channel manually
3. Configure automatic updates (optional)
4. Add more channels with different configurations
5. Customize the BBCode format (advanced)

## Support

Need help? Check:
- Full documentation: `NEWS_CHANNEL_DISPLAY_README.md`
- Admin panel error messages
- TeamSpeak bot logs
- PHP error logs

---

**Enjoy your automated news channels!** 🎉
