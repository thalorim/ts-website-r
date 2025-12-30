# Manual Installation Guide - TeamSpeak Status Display

## Step 1: Install Database Table

Connect to your MySQL database and run this SQL:

```sql
-- Replace 'ts_' with your actual database prefix if you use one
-- If no prefix, just remove 'ts_' from the table name

CREATE TABLE `ts_channel_status_display` (
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

### Using MySQL Command Line:

```bash
# Option 1: Using mysql command
mysql -u YOUR_USERNAME -p YOUR_DATABASE_NAME

# Then paste the CREATE TABLE statement above
```

### Using phpMyAdmin:

1. Log into phpMyAdmin
2. Select your database
3. Click "SQL" tab
4. Paste the CREATE TABLE statement
5. Replace `ts_` with your actual prefix (or remove it if no prefix)
6. Click "Go"

## Step 2: Verify Files Are In Place

Navigate to your TS-website directory:

```bash
# Find your TS-website installation
# It should be where you have the 'src' directory

cd /var/www/html/ts-website  # Common location
# or
cd /var/www/web.reape.rs     # Your specific location
# or wherever your TS-website is installed
```

Check if files exist:

```bash
ls -l src/private/php/Utils/StatusDisplayManager.php
ls -l src/private/php/status-display-bot.php
ls -l src/admin/status-display.php
ls -l src/api/status-display-update.php
```

If these files don't exist, you need to upload them to your server first.

## Step 3: Set Permissions

Make the bot script executable:

```bash
chmod +x src/private/php/status-display-bot.php
```

## Step 4: Test the Installation

First, verify the database table was created:

```bash
mysql -u YOUR_USERNAME -p -e "SHOW TABLES LIKE '%channel_status_display%'" YOUR_DATABASE_NAME
```

You should see the table name in the output.

## Step 5: Add Your First Configuration

### Option A: Via Admin Interface (Recommended)

1. Log into your TS-website as admin
2. Go to: `http://web.reape.rs/admin/status-display.php`
3. Click "Add New Configuration"
4. Fill in:
   - Client Database ID: `116527` (or your user's cldbid)
   - Channel ID: `251746` (or your channel ID)
   - Server Group: (optional, select if you want to show group icon)
5. Click "Add Configuration"

### Option B: Via Database (Alternative)

```bash
mysql -u YOUR_USERNAME -p YOUR_DATABASE_NAME
```

Then run:

```sql
INSERT INTO ts_channel_status_display (cldbid, channel_id, server_group_id, enabled) 
VALUES (116527, 251746, 9, 1);
```

(Replace values with your actual cldbid, channel_id, and server_group_id)

## Step 6: Test the Bot

Run the bot once to test:

```bash
php src/private/php/status-display-bot.php --once
```

Expected output:
```
TeamSpeak Status Display Bot started
Mode: Once
---

[2024-12-30 12:00:00] Running status update...
[2024-12-30 12:00:00] Client 116527 state changed to ONLINE/OFFLINE - updating channel 251746
[2024-12-30 12:00:01] Update complete: Total: 1, Updated: 1, Skipped: 0, Failed: 0

Status Display Bot finished
```

## Step 7: Start the Bot (Production)

### Option A: Run in Background (Simple)

```bash
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &
```

Check it's running:
```bash
ps aux | grep status-display-bot
```

### Option B: Systemd Service (Recommended for Production)

Create service file:

```bash
sudo nano /etc/systemd/system/ts-status-display.service
```

Paste this content (adjust paths for your server):

```ini
[Unit]
Description=TeamSpeak Status Display Bot
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/web.reape.rs
ExecStart=/usr/bin/php /var/www/web.reape.rs/src/private/php/status-display-bot.php --daemon --interval=30
Restart=always
RestartSec=10
StandardOutput=append:/var/log/ts-status-bot.log
StandardError=append:/var/log/ts-status-bot.log

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable ts-status-display
sudo systemctl start ts-status-display
sudo systemctl status ts-status-display
```

View logs:
```bash
sudo journalctl -u ts-status-display -f
```

### Option C: Cron Job (Alternative)

Add to crontab:

```bash
crontab -e
```

Add this line (adjust path):

```
* * * * * php /var/www/web.reape.rs/src/private/php/status-display-bot.php --once >> /var/log/ts-status-bot.log 2>&1
```

## Step 8: Verify It's Working

1. Check the TeamSpeak channel description (channel ID you configured)
2. It should show the user's status with formatting
3. Have the user connect/disconnect to see the status change
4. Check logs: `tail -f /var/log/ts-status-bot.log`

## Troubleshooting

### Error: "Cannot connect to TeamSpeak server"

Check your TS-website config file has correct query credentials:

```bash
nano src/private/config.local.php
```

Verify these settings exist:
- `query_hostname`
- `query_port`
- `query_username`
- `query_password`
- `tsserver_port`

### Error: "Failed to ensure database table exists"

The database table wasn't created. Run the CREATE TABLE statement again.

### Error: Permission denied

```bash
chmod +x src/private/php/status-display-bot.php
chmod 755 src/private/php/status-display-bot.php
```

### Channel description not updating

1. Verify bot is running: `ps aux | grep status-display-bot`
2. Check logs: `tail -f /var/log/ts-status-bot.log`
3. Verify query account has permission to edit channel descriptions
4. Test manually: `php src/private/php/status-display-bot.php --once`

## Quick Reference Commands

```bash
# Navigate to TS-website directory
cd /var/www/web.reape.rs

# Test bot
php src/private/php/status-display-bot.php --once

# Start bot in background
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &

# Check if running
ps aux | grep status-display-bot

# View logs
tail -f /var/log/ts-status-bot.log

# Stop bot (if running in background)
pkill -f status-display-bot

# Check systemd service status
sudo systemctl status ts-status-display
```

## Database Prefix Reference

If your database uses a prefix (like `ts_`), make sure to use it in the table name:

- With prefix: `ts_channel_status_display`
- Without prefix: `channel_status_display`

To find your prefix, check:

```bash
grep -i "prefix" src/private/config.local.php
```

## Success!

You should now have:
- ✅ Database table created
- ✅ Configuration added
- ✅ Bot running
- ✅ Channel description updating automatically

Visit your configured channel in TeamSpeak to see the status display in action!
