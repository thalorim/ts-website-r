# Detailed User Stats - Quick Start Guide

## What You Get

A beautiful TeamSpeak channel display showing detailed user statistics:

```
🥇 #1 Username

first connected: 9th July, 2011
last online: 30th December, 2025, 9:54pm
total time: 25,151 hrs
connected days: 3,385 days
percentage days: 63.95% (3,385/5,293)
most consecutive days: 104 (28th May, 2014 to 8th September, 2014)
most popular day: Sunday (549)
```

## 5-Minute Setup

### Step 1: Ensure Bot is Running

The stats tracking bot must be running to collect data:

```bash
# Check if bot is running
ps aux | grep stats-tracking-bot

# If not running, start it:
cd /path/to/your/website
php src/private/php/stats-tracking-bot.php --daemon --interval 60 &

# Or if you set up systemd:
systemctl start ts-stats-bot
systemctl enable ts-stats-bot
```

### Step 2: Configure Display Channel

1. Open your website admin panel: `https://your-site.com/admin`
2. Click **"Stats & Leaderboards"** button
3. Click **"Add New Configuration"**
4. Fill in:
   - **Channel**: Select the channel for display
   - **Display Type**: Select **"Detailed User Stats"**
   - **Top Count**: Enter number of users to show (e.g., `10`)
5. Click **"Add Configuration"**

### Step 3: Wait for Data

The display will start populating as users connect:

- **Immediate**: Basic stats (connections, online time)
- **After 1 day**: First "connected days" data
- **After multiple days**: Streak calculations
- **After 1 week**: Meaningful day-of-week patterns

### Step 4: Test Update

Click the **"Update Now"** button next to your configuration to see current data immediately.

## What Gets Tracked

| Metric | Description | Source | Example |
|--------|-------------|--------|---------|
| First Connected | Date of first TS connection | TeamSpeak Server | 10th Feb, 2024 |
| Last Online | Most recent connection | Bot (real-time) | 2nd Jan, 2026, 9:25pm |
| Total Time | Hours spent online | Bot tracking | 1,234 hrs |
| Connected Days | Unique days connected | Bot tracking | 245 days |
| Percentage Days | % of days since first seen | Calculated | 36.89% (245/664) |
| Consecutive Days | Longest daily streak | Bot tracking | 45 (dates) |
| Popular Day | Most common day of week | Bot tracking | Saturday (67) |

## Auto-Update Schedule

The bot automatically updates:

- **Connection Tracking**: Real-time (every 60 seconds)
- **Channel Display**: Every 5 minutes
- **Streak Calculations**: On each connection

## Verification

### Check Database

```bash
# Connect to MySQL
mysql -u your_user -p your_database

# Check user statistics
SELECT 
    last_nickname,
    total_connections,
    connected_days,
    longest_streak,
    last_seen 
FROM user_statistics 
ORDER BY total_online_hours DESC 
LIMIT 10;
```

### Check Bot Logs

```bash
# View bot activity
tail -f /var/log/ts-stats-bot.log

# Or if using screen:
screen -r ts-stats-bot
```

### Check Channel

1. Open TeamSpeak 3 client
2. Navigate to your configured channel
3. Check the channel description
4. Should see formatted statistics

## Troubleshooting

### "No statistics available yet"

**Cause**: No users have connected since bot started  
**Fix**: Wait for users to connect, or manually trigger update

### Streaks showing as 0

**Cause**: Users haven't connected on consecutive days yet  
**Fix**: Normal - streaks build over time as users connect daily

### Old "last online" date

**Cause**: Bot not running or not updating  
**Fix**: 
```bash
# Restart bot
pkill -f stats-tracking-bot
php src/private/php/stats-tracking-bot.php --daemon --interval 60 &
```

### Bot crashes/stops

**Cause**: Various (memory, permissions, TS connection)  
**Fix**: Set up systemd service for auto-restart:

```bash
# Check systemd status
systemctl status ts-stats-bot

# View logs
journalctl -u ts-stats-bot -f
```

## Tips & Best Practices

1. **Bot Uptime**: Keep bot running 24/7 for accurate tracking
2. **Backup Data**: The `user_statistics` table contains all historical data
3. **Multiple Channels**: You can configure multiple channels with different Top Count values
4. **Testing**: Use a test channel first to verify formatting
5. **Historical Data**: Stats only track from when feature is enabled (no backdating)

## Common Questions

**Q: Does it track connections before I installed this feature?**  
A: Partially. The "first connected" and "last online" dates come from TeamSpeak server data (your actual TS history). However, detailed tracking (connected days, streaks, day-of-week stats) only starts from when the bot first runs.

**Q: How far back does it track connection dates?**  
A: Last 400 connection dates are stored. This is enough for accurate streak calculations while keeping database size reasonable.

**Q: Can I see statistics for a specific user?**  
A: Yes, users are ranked by total online time. You can increase "Top Count" to show more users, or check the database directly.

**Q: What happens if the bot stops for a few days?**  
A: Connection dates during downtime won't be tracked. Streaks may break if users connected during bot downtime. Historical data is safe.

**Q: Can I customize the date format?**  
A: Yes, edit `StatsDisplayManager.php` in the `buildDetailedUserStats` method. Search for `date('jS F, Y')` and `date('jS F, Y, g:ia')`.

**Q: How much database space does this use?**  
A: Minimal. Each user uses ~1-2KB for statistics (JSON arrays). 10,000 users ≈ 10-20MB.

## Next Steps

Once detailed stats are working:

1. **Combine Displays**: Create multiple channels showing different stat types
2. **Web Interface**: View statistics in web browser (coming soon)
3. **Export Data**: Query database for analysis/reports
4. **Achievements**: Implement milestone badges (coming soon)
5. **Customize**: Edit BBCode formatting to match your style

## Quick Reference Commands

```bash
# Start bot (screen)
screen -S ts-stats-bot
php src/private/php/stats-tracking-bot.php --daemon --interval 60

# Start bot (background)
nohup php src/private/php/stats-tracking-bot.php --daemon --interval 60 > /var/log/ts-stats-bot.log 2>&1 &

# Check if running
ps aux | grep stats-tracking-bot

# Stop bot
pkill -f stats-tracking-bot

# View logs
tail -f /var/log/ts-stats-bot.log

# Test manual update
curl -X POST "https://your-site.com/api/stats-display-update.php"
```

## Support

For detailed information, see `DETAILED_USER_STATS_FEATURE.md`

For general stats system info, see `STATS_LEADERBOARD_FEATURE.md`

---

**Setup Time**: 5 minutes  
**Data Available**: Immediate (basic), builds over days (detailed)  
**Maintenance**: Minimal (just keep bot running)
