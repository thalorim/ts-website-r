# News Channel Display Feature

## Overview

This feature automatically displays your latest news in TeamSpeak channel descriptions. Similar to the Status Display feature, it allows you to configure specific channels to show news from your database.

## Features

- ✅ Configure multiple channels to display news
- ✅ Customizable number of news items per channel (1-20)
- ✅ Automatic updates when news is added/edited
- ✅ Manual update option for all channels or individual channels
- ✅ BBCode formatted display with timestamps
- ✅ Link to full news page on your website
- ✅ Background bot for continuous monitoring

## File Structure

### Core Files Created

1. **`src/private/php/Utils/NewsDisplayManager.php`**
   - Manager class that handles all news display logic
   - Creates and manages database table for configurations
   - Updates TeamSpeak channel descriptions with news
   - Formats news in BBCode for TeamSpeak

2. **`src/admin/news-channel-display.php`**
   - Admin configuration page
   - Add/edit/delete channel configurations
   - View current configurations and last update times
   - Manual update trigger for all channels or individual channels

3. **`src/api/news-display-update.php`**
   - API endpoint for programmatic updates
   - Can update specific channel or all channels
   - Requires admin authentication (CLDBID 3)

4. **`src/private/php/news-display-bot.php`**
   - Background bot for automatic monitoring
   - Detects news changes and updates channels
   - Can run once or as daemon
   - Configurable update interval

### Modified Files

1. **`src/api/save-news.php`**
   - Now automatically triggers news display updates when news is added
   - Ensures channels are updated immediately with new news

2. **`src/private/templates/admin.latte`**
   - Added "News Channel Display" link to admin panel Quick Links

## Database Schema

The feature creates a new table `channel_news_display`:

```sql
CREATE TABLE `channel_news_display` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `channel_id` INT(11) NOT NULL COMMENT 'Channel ID to update description',
    `news_limit` INT(11) NOT NULL DEFAULT '5' COMMENT 'Number of news items to display',
    `enabled` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Whether this configuration is active',
    `last_updated` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time the channel was updated',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_channel` (`channel_id`),
    KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Usage

### 1. Access Admin Configuration

1. Log in to your TeamSpeak website with CLDBID 3 (admin account)
2. Go to Admin Panel
3. Click on "News Channel Display" in Quick Links

### 2. Add Channel Configuration

1. Click "Add Channel Configuration" button
2. Select the channel from dropdown
3. Set the number of news items to display (1-20)
4. Click "Add Configuration"
5. The channel will be immediately updated with news

### 3. Manage Configurations

- **Update News Limit**: Change the number in the table and click the save icon
- **Update Channel Now**: Click the green sync button to manually update a specific channel
- **Update All Channels**: Click "Update All Channels Now" to update all configured channels
- **Delete Configuration**: Click the red trash icon to remove a channel configuration

### 4. Automatic Updates

#### Method 1: Automatic via save-news.php (Default)
When you add news through the admin panel, channels are automatically updated.

#### Method 2: Bot Script
Run the bot for continuous monitoring and updates:

```bash
# Test run (once)
php src/private/php/news-display-bot.php --once

# Run as daemon (checks every 5 minutes)
php src/private/php/news-display-bot.php --daemon --interval=300

# Run in background
nohup php src/private/php/news-display-bot.php --daemon > /var/log/news-display-bot.log 2>&1 &
```

## Channel Description Format

The news is displayed in BBCode format:

```
                    📰 Latest News

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
News Title 1
November 15, 2024

News content preview (up to 300 characters)...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
News Title 2
November 10, 2024 (edited: Nov 12)

News content preview...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

           » View all news on website «

                                Last updated: 2024-11-15 10:30:00
```

## API Usage

### Update All Channels

```bash
curl -X POST https://your-site.com/api/news-display-update.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --cookie "session_cookie=..." 
```

### Update Specific Channel

```bash
curl -X POST https://your-site.com/api/news-display-update.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "channel_id=123" \
  --cookie "session_cookie=..."
```

Response:
```json
{
  "ok": true,
  "message": "All channels updated",
  "stats": {
    "total": 3,
    "updated": 3,
    "failed": 0
  }
}
```

## Troubleshooting

### Channels Not Updating

1. **Check Bot Status**
   ```bash
   ps aux | grep news-display-bot
   ```

2. **Check Logs**
   ```bash
   tail -f /var/log/news-display-bot.log
   ```

3. **Verify Configuration**
   - Ensure channel ID is correct
   - Check that bot has TeamSpeak permissions
   - Verify database connection

### Permission Issues

The bot needs the following TeamSpeak permissions:
- `b_channel_modify_description` - To modify channel descriptions
- Connection permissions to the server

### Database Issues

If the table doesn't exist:
1. Visit the admin configuration page (it auto-creates the table)
2. Or manually run the SQL from the Database Schema section above

## Performance Considerations

- **Update Frequency**: Default bot interval is 300 seconds (5 minutes)
- **News Limit**: Showing 20 news items will create longer descriptions
- **Multiple Channels**: Each configured channel is updated independently
- **Content Length**: News content is truncated to 300 characters per item

## Security

- Only CLDBID 3 (admin) can access configuration
- CSRF protection on all forms
- SQL injection protection via prepared statements
- XSS protection via htmlspecialchars on all output

## Integration with Existing Features

This feature works alongside:
- **Status Display**: Can use both features simultaneously
- **News System**: Uses existing `news` table from the website
- **Admin Panel**: Integrated into existing admin interface

## Future Enhancements

Possible improvements for future versions:
- News categories/tags filtering
- Custom BBCode templates
- Multiple languages support
- News scheduling
- Rich media embedding
- Analytics for channel views

## Credits

Based on the Status Display feature architecture.
Compatible with TS-Website by Wruczek.
