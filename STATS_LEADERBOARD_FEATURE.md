# 📊 Server Statistics & Leaderboard System

## 🎉 Overview

A comprehensive tracking and leaderboard system that monitors user activity and displays beautiful statistics in TeamSpeak channel descriptions using BBCode tables!

## ✨ Features

### 📈 What Gets Tracked
- **Total Connections** - How many times each user has connected
- **Online Time** - Total time each user has been online (in seconds)
- **Last Seen** - When user was last online
- **First Seen** - When user first appeared
- **Current Online Users** - Real-time count

### 🏆 Display Types

1. **Combined** - Server stats + Top 5 connections + Top 5 online time
2. **Connections Leaderboard** - Full top X users by connections
3. **Online Time Leaderboard** - Full top X users by time
4. **Server Statistics** - Overall server stats only

### 💎 Special Features
- ✅ BBCode tables with `[table][tr][td]` formatting
- ✅ Clickable user profiles
- ✅ Medal emojis (🥇🥈🥉) for top 3
- ✅ Real-time online user count
- ✅ Formatted time displays (5d 3h, 12h 45m, etc.)
- ✅ Automatic tracking via bot
- ✅ Beautiful styling

## 🚀 Quick Start

### Step 1: Start the Tracking Bot
```bash
# Test run
php src/private/php/stats-tracking-bot.php --once

# Run continuously (recommended)
php src/private/php/stats-tracking-bot.php --daemon --interval=60

# Run in background
nohup php src/private/php/stats-tracking-bot.php --daemon > /tmp/stats-bot.log 2>&1 &
```

### Step 2: Configure Channel
1. Visit: `https://your-site.com/admin/stats-leaderboard-display.php`
2. Click **"Add Channel Configuration"**
3. Select channel
4. Choose display type (Combined recommended)
5. Set top count (10 recommended)
6. Click **"Add Configuration"**
7. Done! Channel updates automatically

### Step 3: Check TeamSpeak
- Open TeamSpeak
- View the configured channel description
- See beautiful leaderboard tables!

## 📊 Example Display (Combined Mode)

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    📊 Server Statistics & Leaderboards
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

          Server Overview
👥 Users: 156 | 🟢 Online: 23 | 🔄 Connections: 4,521

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        🏆 Top by Connections

┌──────┬──────────────┬─────────────┐
│ Rank │ User         │ Connections │
├──────┼──────────────┼─────────────┤
│🥇 #1 │ Reaper       │ 1,234       │
│🥈 #2 │ PlayerOne    │ 987         │
│🥉 #3 │ GamerPro     │ 654         │
│   #4 │ User123      │ 432         │
│   #5 │ Admin        │ 321         │
└──────┴──────────────┴─────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        ⏱️ Top by Online Time

┌──────┬──────────────┬────────────┐
│ Rank │ User         │ Time       │
├──────┼──────────────┼────────────┤
│🥇 #1 │ Reaper       │ 45d 12h    │
│🥈 #2 │ GamerPro     │ 32d 5h     │
│🥉 #3 │ PlayerOne    │ 28d 18h    │
│   #4 │ User123      │ 15d 3h     │
│   #5 │ Admin        │ 12d 22h    │
└──────┴──────────────┴────────────┘

          Last updated: 2026-01-02 15:30:00
```

Note: BBCode tables render beautifully in TeamSpeak with proper borders!

## 🗄️ Database Tables

### `user_statistics`
Stores user tracking data:
```sql
CREATE TABLE `user_statistics` (
  `cldbid` INT(11) PRIMARY KEY,
  `cluid` VARCHAR(64),
  `last_nickname` VARCHAR(128),
  `total_connections` INT(11) DEFAULT 0,
  `total_online_time` BIGINT(20) DEFAULT 0,
  `first_seen` TIMESTAMP NULL,
  `last_seen` TIMESTAMP NULL,
  ...indexes on connections, online_time, last_seen
)
```

### `channel_stats_display`
Stores channel configurations:
```sql
CREATE TABLE `channel_stats_display` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `channel_id` INT(11) UNIQUE,
  `display_type` VARCHAR(50),
  `top_count` INT(11) DEFAULT 10,
  `enabled` TINYINT(1),
  `last_updated` TIMESTAMP NULL,
  ...
)
```

## 🤖 Tracking Bot

### How It Works
1. **Checks online users** every 60 seconds (configurable)
2. **Records connections** when new user appears
3. **Tracks online time** for all online users
4. **Updates channels** every 5 minutes
5. **Logs activity** for monitoring

### Bot Commands
```bash
# Test mode (run once)
php stats-tracking-bot.php --once

# Daemon mode (default 60s interval)
php stats-tracking-bot.php --daemon

# Custom interval (30 seconds)
php stats-tracking-bot.php --daemon --interval=30

# Background with logging
nohup php stats-tracking-bot.php --daemon > /var/log/stats-bot.log 2>&1 &

# Check if running
ps aux | grep stats-tracking-bot

# Stop bot
pkill -f stats-tracking-bot
```

### Bot Output
```
=================================================
Stats Tracking Bot
=================================================
Mode: Daemon mode
Interval: 60 seconds
Started at: 2026-01-02 15:00:00
=================================================

[2026-01-02 15:00:01] Processing user tracking...
  [NEW] Reaper (cldbid: 5) connected
  [NEW] GamerPro (cldbid: 12) connected
  Summary: 23 online, 2 new connections, 0 left
  Tracking data updated for 21 users

[2026-01-02 15:05:01] Updating stats display channels...
  Channels updated: 3
  Failed: 0
  Total: 3
```

## 🎨 Display Type Details

### 1. Combined (Recommended)
**Best for**: General stats channels
**Shows**: 
- Server overview (users, online, connections)
- Top 5 by connections
- Top 5 by online time
**Format**: Compact with tables

### 2. Connections Leaderboard
**Best for**: Dedicated connections channel
**Shows**: Full leaderboard with medals
**Columns**: Rank | User (linked) | Connections | Last Seen

### 3. Online Time Leaderboard
**Best for**: Dedicated time tracking channel
**Shows**: Full leaderboard with medals
**Columns**: Rank | User (linked) | Time | Last Seen

### 4. Server Statistics
**Best for**: Server info channel
**Shows**: 
- Total unique users
- Currently online
- Total connections
- Total online time
- Average connections/user
- Average time/user

## 📝 Admin Interface

### Configuration Options
- **Channel**: Which channel to update
- **Display Type**: What to show (4 types)
- **Top Count**: How many users in leaderboard (1-50)

### Actions Available
- ✅ Add new configuration
- ✅ Edit existing (type & count)
- ✅ Update channel manually (🔄 button)
- ✅ Update all channels at once
- ✅ Delete configuration (🗑️ button)

### Server Stats Dashboard
The admin page shows live stats:
- Total unique users tracked
- Currently online
- Total connections
- Total online time

## 🔗 Profile Links

Users in leaderboards are **clickable** and link to:
1. **Website profile**: `https://your-site.com/profile.php?cldbid=X`
2. **TeamSpeak profile**: Can be enhanced with TS client links

## 📊 Statistics Explained

### Connections
- **What**: Count of how many times user has connected
- **Tracked**: Incremented on each connection
- **Use Case**: Shows most active/returning users

### Online Time
- **What**: Total seconds user has been online
- **Tracked**: Updated every interval (60s default)
- **Format**: Days, hours, minutes (e.g., "5d 12h")
- **Use Case**: Shows most dedicated users

### First/Last Seen
- **First Seen**: When user first tracked
- **Last Seen**: Most recent activity
- **Format**: Relative time (e.g., "2h ago", "5d ago")

## 🎯 Use Cases

### Scenario 1: General Community Channel
```
Channel: "📊 Server Stats"
Type: Combined
Top Count: 10

Shows: Overview + Top 10 each category
Perfect for: Main stats channel
```

### Scenario 2: Hall of Fame
```
Channel: "🏆 Hall of Fame"
Type: Connections
Top Count: 50

Shows: Full top 50 most connections
Perfect for: Recognizing loyal members
```

### Scenario 3: Active Users
```
Channel: "⏱️ Most Active"
Type: Online Time
Top Count: 25

Shows: Top 25 by time spent
Perfect for: Activity tracking
```

### Scenario 4: Server Info
```
Channel: "ℹ️ Server Info"
Type: Server Statistics
Top Count: N/A

Shows: Overall stats only
Perfect for: Information channel
```

## 🛠️ Troubleshooting

### Bot Not Tracking
**Check:**
```bash
# Is bot running?
ps aux | grep stats-tracking-bot

# Check bot log
tail -f /tmp/stats-bot.log

# Test manually
php stats-tracking-bot.php --once
```

### No Stats Showing
**Solutions:**
1. Run bot for a while to collect data
2. Check database: `SELECT * FROM user_statistics LIMIT 10;`
3. Manually update channel via admin page
4. Check TeamSpeak permissions

### Channel Not Updating
**Check:**
1. Bot has channel edit permissions
2. Configuration exists: Check admin page
3. Channel ID is correct
4. Check PHP error logs

### Wrong Statistics
**Solutions:**
1. Check tracking interval (might be too long)
2. Verify bot hasn't crashed
3. Check database for corruption
4. Restart bot with `--once` to test

## 📈 Performance

### Database Impact
- **Minimal**: Simple SELECT/UPDATE queries
- **Indexes**: Optimized for leaderboard queries
- **Size**: ~100 bytes per user

### Bot Resource Usage
- **CPU**: <1% (checks every 60s)
- **Memory**: <50MB
- **Network**: Minimal (TS query only)

### Channel Update Speed
- **Single Channel**: <1 second
- **Multiple Channels**: ~1-2 seconds each
- **Update Frequency**: Every 5 minutes (bot)

## 🔒 Security

- ✅ Admin-only access (CLDBID 3)
- ✅ CSRF protection
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ No sensitive data exposed

## 📚 API

### Update All Channels
```bash
curl -X POST https://your-site.com/api/stats-display-update.php \
  --cookie "session=..."
```

### Update Specific Channel
```bash
curl -X POST https://your-site.com/api/stats-display-update.php \
  -d "channel_id=123" \
  --cookie "session=..."
```

### Response
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

## 🎓 Advanced Usage

### Custom Intervals
```bash
# Fast tracking (30s)
php stats-tracking-bot.php --daemon --interval=30

# Slow tracking (5 minutes)
php stats-tracking-bot.php --daemon --interval=300
```

### Multiple Channels
You can configure multiple channels with different types:
```
Channel 1: Combined (overview)
Channel 2: Connections (full leaderboard)
Channel 3: Online Time (full leaderboard)
Channel 4: Server Stats (info only)
```

### Monitoring Bot
```bash
# Create monitor script
echo '#!/bin/bash
if ! pgrep -f stats-tracking-bot > /dev/null; then
    echo "Bot not running! Starting..."
    nohup php stats-tracking-bot.php --daemon > /tmp/stats-bot.log 2>&1 &
fi' > /root/monitor-stats-bot.sh

chmod +x /root/monitor-stats-bot.sh

# Add to cron (check every 5 minutes)
*/5 * * * * /root/monitor-stats-bot.sh
```

## 📦 Files Created

### Core Files
1. **`src/private/php/Utils/StatsDisplayManager.php`** - Main manager class
2. **`src/private/php/stats-tracking-bot.php`** - Tracking bot
3. **`src/admin/stats-leaderboard-display.php`** - Admin interface
4. **`src/api/stats-display-update.php`** - API endpoint

### Modified Files
- **`src/private/templates/admin.latte`** - Added admin panel link

## ✅ Summary

| Feature | Status |
|---------|--------|
| User tracking | ✅ Implemented |
| Connections counting | ✅ Implemented |
| Online time tracking | ✅ Implemented |
| BBCode tables | ✅ Implemented |
| Profile links | ✅ Implemented |
| Multiple display types | ✅ Implemented |
| Admin interface | ✅ Implemented |
| Tracking bot | ✅ Implemented |
| API endpoint | ✅ Implemented |
| Real-time updates | ✅ Implemented |

## 🎉 Ready to Use!

1. **Start bot**: `php stats-tracking-bot.php --daemon`
2. **Configure channel**: Via admin page
3. **Wait ~5 minutes**: For data collection
4. **Check TeamSpeak**: See beautiful leaderboards!

---

**Status**: ✅ Feature Complete
**Version**: 1.0
**Date**: January 2, 2026
**Documentation**: Complete with examples
