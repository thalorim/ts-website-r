# Status Display Feature - Installation Guide

This guide will help you install and configure the TeamSpeak Status Display feature.

## Prerequisites

- TS-website already installed and working
- TeamSpeak 3 Server Query access configured
- PHP 7.4+ with CLI support
- MySQL/MariaDB database

## Quick Installation

### Step 1: Database Setup

Run the SQL migration to create the required table. Connect to your MySQL database:

```bash
mysql -u your_username -p your_database < src/installer/dbinstall_status_display.sql
```

Or if you're using a database prefix, replace `DBPREFIX` with your actual prefix in the SQL file first.

**Manual installation:**

```sql
-- Replace DBPREFIX with your actual prefix (or remove it if you don't use a prefix)
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

### Step 2: Verify Files

Ensure all required files are present:

```bash
ls -l src/private/php/Utils/StatusDisplayManager.php
ls -l src/private/php/status-display-bot.php
ls -l src/admin/status-display.php
ls -l src/api/status-display-update.php
```

Make the bot script executable:

```bash
chmod +x src/private/php/status-display-bot.php
```

### Step 3: Configure Your First Status Display

1. **Access the Admin Panel**
   - Log in to your TeamSpeak website
   - Navigate to: `http://your-website.com/admin/`
   - Click on "Status Display Configuration"

2. **Add a Configuration**
   - Click "Add New Configuration"
   - Enter the Client Database ID (cldbid) - You can find this in TeamSpeak client by right-clicking a user → "Client Info"
   - Select the Channel ID where status should be displayed
   - Optionally select a Server Group to display the group icon
   - Click "Add Configuration"

3. **Test the Configuration**
   - Click "Test Update All" button in the admin interface
   - Check the configured channel in TeamSpeak - the description should be updated

### Step 4: Start the Monitoring Bot

Choose one of the following methods:

#### Method A: Daemon Mode (Recommended for Production)

Run the bot continuously in the background:

```bash
# Start in foreground (for testing)
php src/private/php/status-display-bot.php --daemon --interval=30

# Start in background (production)
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &

# Check if running
ps aux | grep status-display-bot
```

#### Method B: Systemd Service (Recommended for Linux Servers)

Create a systemd service file:

```bash
sudo nano /etc/systemd/system/ts-status-display.service
```

Add this content (adjust paths as needed):

```ini
[Unit]
Description=TeamSpeak Status Display Bot
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/ts-website
ExecStart=/usr/bin/php /var/www/ts-website/src/private/php/status-display-bot.php --daemon --interval=30
Restart=always
RestartSec=10
StandardOutput=append:/var/log/ts-status-bot.log
StandardError=append:/var/log/ts-status-bot.log

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable ts-status-display.service
sudo systemctl start ts-status-display.service

# Check status
sudo systemctl status ts-status-display.service

# View logs
sudo journalctl -u ts-status-display.service -f
```

#### Method C: Cron Job (Simple Alternative)

Add to crontab:

```bash
crontab -e
```

Add this line (runs every minute):

```
* * * * * php /path/to/workspace/src/private/php/status-display-bot.php --once >> /var/log/ts-status-bot.log 2>&1
```

## Configuration Examples

### Example 1: Single User Status Display

Display the status of user with cldbid `116527` in channel `251746`:

```
Client Database ID: 116527
Channel ID: 251746
Server Group: (leave empty or select a group)
```

### Example 2: Multiple Users

You can add multiple configurations to monitor different users:

| cldbid | Channel ID | Server Group |
|--------|-----------|--------------|
| 116527 | 251746    | 9 (Rank 1)   |
| 123456 | 251747    | 10 (Rank 2)  |
| 789012 | 251748    | 11 (Rank 3)  |

### Example 3: Same User in Multiple Channels

Display the same user's status in multiple locations:

| cldbid | Channel ID | Server Group |
|--------|-----------|--------------|
| 116527 | 251746    | 9 (Rank 1)   |
| 116527 | 100500    | 9 (Rank 1)   |

## Finding Required IDs

### How to Find Client Database ID (cldbid)

1. Open TeamSpeak 3 Client
2. Right-click on the user
3. Select "Client Info"
4. Look for "Database ID" in the info window

### How to Find Channel ID

1. Open TeamSpeak 3 Client
2. Right-click on the channel
3. Select "Channel Info" or edit the channel
4. Look for "Channel ID" (you may need to enable advanced options)

Alternatively, you can use the viewer page on your website - channel IDs are usually visible in the URL or page source.

### How to Find Server Group ID

1. Access your website's admin panel
2. Check the server group configuration
3. Or query directly via TeamSpeak:
   - Connect with ServerQuery
   - Run `servergrouplist`
   - Find the `sgid` value

## Verification

After installation, verify everything is working:

### 1. Check Database Table

```sql
SELECT * FROM channel_status_display;
```

You should see your configurations.

### 2. Test Manual Update

```bash
php src/private/php/status-display-bot.php --once
```

You should see output like:

```
TeamSpeak Status Display Bot started
Mode: Once
---

[2024-12-30 12:00:00] Running status update...
[2024-12-30 12:00:00] Client 116527 state changed to ONLINE - updating channel 251746
[2024-12-30 12:00:01] Update complete: Total: 1, Updated: 1, Skipped: 0, Failed: 0

Status Display Bot finished
```

### 3. Check Channel Description

Open TeamSpeak 3 client and check the configured channel's description. It should display the user's status with BBCode formatting.

### 4. Test Connect/Disconnect

1. Have the monitored user disconnect from TeamSpeak
2. Wait for the bot to detect the change (based on your interval)
3. Check the channel description - it should show "OFFLINE"
4. Have the user reconnect
5. Check again - it should show "ONLINE"

## Troubleshooting

### Bot Not Starting

**Error: "Cannot connect to TeamSpeak server"**

- Verify TeamSpeak query credentials in your website config
- Ensure query port is accessible
- Check firewall rules

**Error: "Failed to ensure database table exists"**

- Verify database credentials
- Ensure database user has CREATE, ALTER privileges
- Check database connection in website config

### Updates Not Appearing

**Channel description not changing:**

1. Check bot is running:
   ```bash
   ps aux | grep status-display-bot
   ```

2. Check bot logs:
   ```bash
   tail -f /var/log/ts-status-bot.log
   ```

3. Verify channel permissions:
   - Query account needs `b_channel_modify_description` permission
   - Channel must not be protected

4. Test manual update:
   ```bash
   php src/private/php/status-display-bot.php --once
   ```

### Permission Denied Errors

```bash
# Fix script permissions
chmod +x src/private/php/status-display-bot.php

# Fix log file permissions
sudo touch /var/log/ts-status-bot.log
sudo chown www-data:www-data /var/log/ts-status-bot.log
```

### High CPU Usage

If the bot is using too much CPU:

1. Increase the update interval:
   ```bash
   php src/private/php/status-display-bot.php --daemon --interval=60
   ```

2. Reduce the number of configured users

3. Use cron job instead of daemon mode

## Uninstallation

To remove the status display feature:

1. **Stop the bot:**
   ```bash
   # If running as systemd service
   sudo systemctl stop ts-status-display.service
   sudo systemctl disable ts-status-display.service
   
   # If running in background
   pkill -f status-display-bot
   ```

2. **Remove database table:**
   ```sql
   DROP TABLE IF EXISTS `channel_status_display`;
   ```

3. **Remove files (optional):**
   ```bash
   rm src/private/php/Utils/StatusDisplayManager.php
   rm src/private/php/status-display-bot.php
   rm src/admin/status-display.php
   rm src/api/status-display-update.php
   ```

## Support

For help and support:

1. Check the main documentation: `STATUS_DISPLAY_FEATURE.md`
2. Review bot logs: `/var/log/ts-status-bot.log`
3. Join the [TS-website Telegram group](https://t.me/tswebsite)

## Next Steps

After installation:

1. Configure user profiles with social media links via `edit-profile.php`
2. Set up server group icons in TeamSpeak
3. Customize the BBCode template in `StatusDisplayManager.php` (optional)
4. Set up monitoring/alerts for the bot process

Enjoy your new status display feature! 🎉
