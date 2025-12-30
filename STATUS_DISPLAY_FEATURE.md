# TeamSpeak Status Display Feature

## Overview

This feature automatically displays a user's live status (Online/Offline) inside a TeamSpeak channel description. When a configured user connects or disconnects from the TeamSpeak server, a bot automatically updates the channel description with:

- **Online/Offline Status** - Visual indicator with color-coded BBCode
- **Profile Link** - Clickable link to the user's profile page
- **Social Media Icons** - User's configured social media links
- **Server Group Icon** - User's TeamSpeak server group icon and name
- **Profile Description** - User's profile description (truncated)

## Features

- ✅ Real-time status updates on connect/disconnect
- ✅ BBCode formatted channel descriptions
- ✅ Configurable via web admin interface
- ✅ Multiple users can be monitored simultaneously
- ✅ Automatic profile data integration
- ✅ Server group icon display
- ✅ Social media links with emojis
- ✅ Daemon mode for continuous monitoring
- ✅ Manual update via API endpoint

## Installation

### 1. Database Setup

Run the database migration to create the required table:

```sql
-- Connect to your MySQL database and run:
source src/installer/dbinstall_status_display.sql
```

Or manually create the table (replace `DBPREFIX` with your actual database prefix):

```sql
CREATE TABLE `DBPREFIXchannel_status_display` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cldbid` INT(11) NOT NULL COMMENT 'Client database ID to monitor',
  `channel_id` INT(11) NOT NULL COMMENT 'Channel ID to update description',
  `server_group_id` INT(11) DEFAULT NULL COMMENT 'Server group ID to display icon for',
  `enabled` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Whether this configuration is active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cldbid_channel` (`cldbid`, `channel_id`),
  KEY `idx_cldbid` (`cldbid`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Configure Status Displays

1. Log in to your TeamSpeak website as an administrator
2. Navigate to the admin panel: `http://your-website.com/admin/`
3. Access the Status Display Configuration page: `http://your-website.com/admin/status-display.php`
4. Click **"Add New Configuration"**
5. Fill in the form:
   - **Client Database ID**: The TeamSpeak client database ID (cldbid) to monitor
   - **Channel ID**: The channel where the status will be displayed
   - **Server Group** (optional): The server group icon to display

### 3. Start the Monitoring Bot

The bot monitors TeamSpeak for connect/disconnect events and updates channel descriptions automatically.

#### Option A: Run as Daemon (Recommended)

Run continuously in the background:

```bash
# Start the bot with 30-second update interval
php src/private/php/status-display-bot.php --daemon --interval=30

# Run in background (detached from terminal)
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &
```

#### Option B: Run via Cron Job

Add to your crontab to run every minute:

```bash
crontab -e
```

Add this line:

```
* * * * * php /path/to/workspace/src/private/php/status-display-bot.php --once >> /var/log/ts-status-bot.log 2>&1
```

#### Option C: Manual/API Trigger

Call the API endpoint manually or via external automation:

```bash
# Update all configured users
curl http://your-website.com/api/status-display-update.php

# Update specific user by cldbid
curl http://your-website.com/api/status-display-update.php?cldbid=116527
```

## Bot Commands

### Available Options

```bash
php src/private/php/status-display-bot.php [OPTIONS]

Options:
  --daemon           Run continuously as a daemon
  --once             Run once and exit (default)
  --interval=N       Update interval in seconds (default: 30, minimum: 5)
  --help             Show help message
```

### Examples

```bash
# Run once (for testing)
php src/private/php/status-display-bot.php --once

# Run as daemon with 15-second intervals
php src/private/php/status-display-bot.php --daemon --interval=15

# Run as daemon with custom interval
php src/private/php/status-display-bot.php --daemon --interval=60

# Show help
php src/private/php/status-display-bot.php --help
```

## Usage Example

### Scenario

You want to display the status of user with cldbid `116527` in channel `251746`, showing their server group icon for group ID `9`.

### Setup Steps

1. **Add Configuration** (via admin interface):
   - Client Database ID: `116527`
   - Channel ID: `251746`
   - Server Group: `9` (e.g., "Rank 1")

2. **Start Bot**:
   ```bash
   php src/private/php/status-display-bot.php --daemon --interval=30
   ```

3. **Result**:
   - When user `116527` connects: Channel `251746` description shows **ONLINE** status
   - When user `116527` disconnects: Channel `251746` description shows **OFFLINE** status
   - Description includes profile link, social media icons, and server group icon

## Channel Description Format

The bot generates a BBCode-formatted description like this:

```bbcode
[center]
[size=14][b]Username[/b][/size]

[color=#00ff00][size=12][b]✓ ONLINE[/b][/size][/color]

[url=http://web.reape.rs/profile.php?cldbid=116527]View Profile[/url]

[img]icon_123[/img] Rank 1

[size=10][b]Social Media:[/b][/size]
[url=https://instagram.com/user]📷 Instagram[/url] [url=https://twitter.com/user]🐦 Twitter[/url] 

[hr]
[size=10]User's profile description...[/size]
[/center]
```

### Online Status

- Color: **Green** (#00ff00)
- Icon: ✓
- Text: "ONLINE"

### Offline Status

- Color: **Red** (#ff0000)
- Icon: ✗
- Text: "OFFLINE"

## API Endpoints

### Update Status Display

**Endpoint**: `GET /api/status-display-update.php`

**Query Parameters**:
- `cldbid` (optional): Specific client database ID to update

**Response**:

```json
{
  "success": true,
  "stats": {
    "total": 5,
    "updated": 2,
    "failed": 0,
    "skipped": 3
  }
}
```

**Example Usage**:

```bash
# Update all
curl http://your-website.com/api/status-display-update.php

# Update specific user
curl http://your-website.com/api/status-display-update.php?cldbid=116527
```

## Admin Interface

Access the admin interface at: `http://your-website.com/admin/status-display.php`

### Features

- ✅ View all status display configurations
- ✅ Add new configurations
- ✅ Delete existing configurations
- ✅ Test update all configurations manually
- ✅ View channel and user names (resolved from TeamSpeak)
- ✅ Bot execution commands reference

### Permissions

Only users with admin server groups (configured in `adminstatus_groups`) can access this interface.

## File Structure

```
workspace/
├── src/
│   ├── installer/
│   │   └── dbinstall_status_display.sql       # Database migration
│   ├── private/
│   │   └── php/
│   │       ├── Utils/
│   │       │   └── StatusDisplayManager.php   # Core utility class
│   │       └── status-display-bot.php         # Monitoring bot script
│   ├── admin/
│   │   └── status-display.php                 # Admin interface
│   └── api/
│       └── status-display-update.php          # API endpoint
└── STATUS_DISPLAY_FEATURE.md                  # This documentation
```

## Troubleshooting

### Bot Not Updating

1. **Check Bot is Running**:
   ```bash
   ps aux | grep status-display-bot
   ```

2. **Check TeamSpeak Connection**:
   - Verify query credentials in website config
   - Ensure query port is accessible
   - Check bot logs for connection errors

3. **Verify Configuration**:
   - Ensure configurations are enabled in database
   - Verify channel IDs are correct
   - Verify client database IDs are correct

### Permission Errors

1. **Bot Script Not Executable**:
   ```bash
   chmod +x src/private/php/status-display-bot.php
   ```

2. **Database Permissions**:
   - Ensure database user has CREATE, INSERT, UPDATE, DELETE permissions

3. **TeamSpeak Permissions**:
   - Ensure query account has permission to edit channel descriptions
   - Required permissions: `b_channel_modify_description`

### Channel Not Updating

1. **Check Channel ID**:
   - Use TeamSpeak client to verify channel ID (right-click channel → Info)
   
2. **Check Channel Permissions**:
   - Query account needs permission to modify the channel
   
3. **Test Manual Update**:
   ```bash
   php src/private/php/status-display-bot.php --once
   ```

### Logs

View bot logs (if using nohup):

```bash
tail -f /var/log/ts-status-bot.log
```

## Advanced Configuration

### Custom Profile URL Base

Edit `StatusDisplayManager.php` and modify the `getBaseUrl()` method to set a custom base URL:

```php
private function getBaseUrl(): string {
    return "http://custom-domain.com";
}
```

### Custom BBCode Template

Modify the `buildChannelDescription()` method in `StatusDisplayManager.php` to customize the channel description template.

### Multiple Channels Per User

You can configure multiple channels for the same user by adding multiple configurations with the same `cldbid` but different `channel_id` values.

## Performance Considerations

- **Update Interval**: Lower intervals (e.g., 5-10 seconds) provide faster updates but increase server load
- **Recommended Interval**: 30-60 seconds for balanced performance
- **Caching**: The bot forces cache refresh on each iteration to ensure accurate status

## Security Notes

- ✅ Admin interface requires authentication and admin permissions
- ✅ API endpoint does not require authentication (can be restricted if needed)
- ✅ Input validation on all user-provided data
- ✅ SQL injection protection via prepared statements
- ✅ XSS protection via htmlspecialchars

## Support

For issues or questions:

1. Check this documentation
2. Review bot logs
3. Verify TeamSpeak connection and permissions
4. Check database configuration
5. Join the [TS-website Telegram group](https://t.me/tswebsite)

## Credits

This feature was developed as an extension to the [TS-website](https://github.com/Wruczek/ts-website) project by Wruczek.

## License

This feature follows the same license as TS-website (GPL-3.0).
