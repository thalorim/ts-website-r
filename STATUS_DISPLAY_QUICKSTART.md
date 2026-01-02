# TeamSpeak Status Display - Quick Start Guide

**Get up and running in 5 minutes!**

## What is this?

This feature automatically updates TeamSpeak channel descriptions to show when a user is online or offline, including their profile link, social media, and server group icon.

## Quick Setup

### 1. Install Database Table (1 minute)

Run the installation script:

```bash
bash install-status-display.sh
```

Or manually install the database table:

```bash
mysql -u USERNAME -p DATABASE_NAME < src/installer/dbinstall_status_display.sql
```

### 2. Configure a User (2 minutes)

1. Go to: `http://your-website.com/admin/status-display.php`
2. Click **"Add New Configuration"**
3. Fill in:
   - **Client Database ID**: User's cldbid (find via TeamSpeak client → right-click user → Client Info)
   - **Channel ID**: Channel to display status in
   - **Server Group**: (optional) Group icon to display
4. Click **"Add Configuration"**

### 3. Start the Bot (2 minutes)

Run the bot to start monitoring:

```bash
# Test mode (run once)
php src/private/php/status-display-bot.php --once

# Production mode (continuous monitoring)
php src/private/php/status-display-bot.php --daemon --interval=30

# Background mode
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &
```

## Example

**Goal**: Show user status for cldbid `116527` in channel `251746`

**Steps**:
1. Add configuration: cldbid=`116527`, channel=`251746`, group=`9`
2. Start bot: `php src/private/php/status-display-bot.php --daemon`
3. Done! Channel `251746` now shows live status

## What You Get

The channel description will display:

```
┌──────────────────────────────────┐
│          Username                │
│                                  │
│       ✓ ONLINE                   │
│                                  │
│      View Profile                │
│                                  │
│   [Icon] Rank 1                  │
│                                  │
│   📷 Instagram  🐦 Twitter       │
│                                  │
│   User's profile description...  │
└──────────────────────────────────┘
```

## Troubleshooting

**Not working?**

1. Check bot is running: `ps aux | grep status-display-bot`
2. Check logs: `tail -f /var/log/ts-status-bot.log`
3. Test manually: `php src/private/php/status-display-bot.php --once`

**Need help?**

- See `STATUS_DISPLAY_FEATURE.md` for full documentation
- See `INSTALL_STATUS_DISPLAY.md` for detailed installation guide
- Join [TS-website Telegram](https://t.me/tswebsite)

## Systemd Service (Production)

For production servers, set up as a systemd service:

```bash
sudo nano /etc/systemd/system/ts-status-display.service
```

```ini
[Unit]
Description=TeamSpeak Status Display Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/ts-website
ExecStart=/usr/bin/php /var/www/ts-website/src/private/php/status-display-bot.php --daemon --interval=30
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable ts-status-display
sudo systemctl start ts-status-display
```

## Cron Alternative

Add to crontab if you prefer cron over daemon:

```bash
crontab -e
```

Add:
```
* * * * * php /path/to/src/private/php/status-display-bot.php --once >> /var/log/ts-status-bot.log 2>&1
```

---

**That's it! Your status display is now running.** 🎉

For more details, see the complete documentation in `STATUS_DISPLAY_FEATURE.md`.
