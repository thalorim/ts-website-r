# News Channel Display System

A TeamSpeak channel description updater that automatically displays the latest news from your website in TeamSpeak channel descriptions.

## Features

- 📰 Display latest news in TeamSpeak channel descriptions
- 🎯 Configure multiple channels to show news
- 🔧 Customizable display options (number of items, content preview, etc.)
- 🔄 On-demand updates via admin panel
- ⚙️ Optional automatic updates via cron job
- 🎨 BBCode formatted output for TeamSpeak

## Installation

### 1. Database Setup

The system will automatically create the required database table (`news_channel_display`) when you first access the admin panel.

### 2. Admin Panel Access

Navigate to the admin panel at:
```
https://your-website.com/admin/news-channel-display.php
```

**Note:** Only users with admin privileges can access this page. By default, only CLDBID 116527 can access. To change this, edit the CLDBID check in `src/admin/news-channel-display.php`.

### 3. Configuration

#### Add a News Channel Configuration

1. Click the "Add New Configuration" button
2. Select the channel where you want to display news
3. Configure the display options:
   - **Number of News Items**: How many recent news items to display (1-20)
   - **Show News Content**: Toggle to show content preview or just titles
   - **Maximum Content Length**: Maximum characters of content to display (50-1000)
4. Click "Add Configuration"

#### Display Options Explained

- **News Limit**: Controls how many recent news posts are shown. Lower numbers (3-5) work better for shorter descriptions.
- **Show Content**: When enabled, shows a preview of the news content. When disabled, only shows titles and dates.
- **Max Length**: Controls how many characters of the news content to display. Content longer than this will be truncated with "..."

## Usage

### Manual Updates

#### Update a Single Channel
Click the sync button (<i class="fas fa-sync"></i>) next to any configuration to update that channel immediately.

#### Update All Channels
Click the "Update All Channels" button at the top to update all enabled configurations at once.

### Automatic Updates

You can set up automatic updates using a cron job or the API endpoint.

#### Option 1: Cron Job (Recommended)

Add this to your crontab to update news channels every hour:

```bash
# Update news channels every hour
0 * * * * cd /path/to/your/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
```

For more frequent updates (every 15 minutes):
```bash
*/15 * * * * cd /path/to/your/website && php -r "require 'src/private/php/load.php'; \Wruczek\TSWebsite\Utils\NewsChannelDisplayManager::i()->updateAllChannels();"
```

#### Option 2: API Endpoint with Token

1. Set a secure token in your config:
   ```php
   // In src/private/config.local.php
   $config['news_channel_update_token'] = 'your-secure-random-token-here';
   ```

2. Call the API endpoint:
   ```bash
   curl "https://your-website.com/api/update-news-channels.php?token=your-secure-random-token-here"
   ```

3. Use with cron:
   ```bash
   0 * * * * curl -s "https://your-website.com/api/update-news-channels.php?token=your-secure-random-token-here" > /dev/null
   ```

## Channel Description Format

The news is displayed in the following BBCode format:

```
[center][b][size=15]Latest News[/size][/b][/center]

[b]1. News Title Here[/b]
[size=9][color=gray]Posted: 2026-01-02 15:30[/color][/size]
News content preview truncated to max length...

[b]2. Another News Title[/b]
[size=9][color=gray]Posted: 2026-01-01 10:00[/color][/size]
Another news content preview...

[hr]
[size=8][color=gray]Last updated: 2026-01-02 16:00:00[/color][/size]
```

## Managing Configurations

### Enable/Disable a Configuration

Click the pause/play button to temporarily disable or enable a configuration without deleting it.

- **Green play button**: Configuration is disabled, click to enable
- **Yellow pause button**: Configuration is enabled, click to disable

### Delete a Configuration

Click the red trash button to permanently remove a configuration. This will not delete the channel, only the news display configuration.

## Troubleshooting

### Channel Not Updating

1. **Check TeamSpeak Connection**: Ensure the bot can connect to your TeamSpeak server
2. **Verify Channel ID**: Make sure the channel ID is correct and the channel exists
3. **Check Permissions**: The bot must have permission to edit channel descriptions
4. **Check News Data**: Ensure you have news posts in the database (`news` table)

### Empty Channel Description

- Make sure you have news posts in your database
- Check that "Show Content" is enabled if you want content preview
- Verify the news limit is set appropriately

### Permission Errors

- The bot user needs the `b_channel_modify_description` permission
- Check your TeamSpeak query credentials in the main config

## Technical Details

### Database Table

The system creates a table `news_channel_display` with the following structure:

- `id`: Primary key
- `channel_id`: TeamSpeak channel ID to update
- `news_limit`: Number of news items to display
- `show_content`: Whether to show news content preview
- `content_max_length`: Maximum length of content preview
- `enabled`: Whether this configuration is active
- `last_updated`: Timestamp of last successful update

### Classes Used

- **NewsChannelDisplayManager**: Main manager class (`src/private/php/Utils/NewsChannelDisplayManager.php`)
- **DefaultNewsStore**: News data provider (`src/private/php/News/DefaultNewsStore.php`)
- **TeamSpeakUtils**: TeamSpeak connection handler

## Security Notes

- Admin panel access is restricted to users with admin privileges
- CSRF protection is enabled for all form submissions
- API endpoint requires token authentication when not called from CLI
- Use strong, random tokens for API access
- Keep your token secret and don't commit it to version control

## FAQ

**Q: Can I configure multiple channels to show the same news?**  
A: Yes! You can add multiple configurations, each with different settings.

**Q: How often should I update the news channels?**  
A: It depends on how frequently you post news. Every 15-60 minutes is usually sufficient.

**Q: Can I customize the BBCode format?**  
A: Yes, edit the `formatNewsDescription()` method in `NewsChannelDisplayManager.php`.

**Q: Does this work with SQLite and MySQL?**  
A: Yes, the system supports both database types.

**Q: What happens if there's no news?**  
A: The channel description will show "No news available" in the center.

## Support

For issues or questions:
1. Check the error messages in the admin panel
2. Review your TeamSpeak bot logs
3. Verify your database connection and table structure
4. Check file permissions and PHP error logs
