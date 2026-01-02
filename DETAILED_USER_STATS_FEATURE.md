# Detailed User Statistics Display Feature

## Overview

This feature adds comprehensive, detailed user statistics tracking and display for TeamSpeak users. It shows in-depth metrics including first/last connection dates, total time online, connected days, consecutive day streaks, and most popular day of the week.

## Features

### Tracked Metrics

1. **First Connected**: The date when the user first connected to the server
2. **Last Online**: The most recent connection with date and time
3. **Total Time**: Total hours spent online on the server
4. **Connected Days**: Number of unique days the user has connected
5. **Percentage Days**: Percentage of days connected since first seen (connected days / total days)
6. **Most Consecutive Days**: Longest streak of consecutive days connected, with start and end dates
7. **Current Streak**: Current consecutive days streak (if active within last 24h)
8. **Most Popular Day**: Day of the week when user connects most often with count

### Display Format

The display format matches your requested style:

```
🥇 #1 Username

first connected: 9th July, 2011
last online: 30th December, 2025, 9:54pm
total time: 25,151 hrs
connected days: 3,385 days
percentage days: 63.95% (3,385/5,293)
most consecutive days: 104 (28th May, 2014 to 8th September, 2014)
most popular day: Sunday (549)

──────────────────────────────────────

🥈 #2 Another User
...
```

## Database Schema

### New Columns in `user_statistics` Table

```sql
ALTER TABLE `user_statistics` ADD COLUMN `connected_days` INT(11) NOT NULL DEFAULT '0' COMMENT 'Total unique days user connected';
ALTER TABLE `user_statistics` ADD COLUMN `connection_dates` TEXT DEFAULT NULL COMMENT 'JSON array of connection dates for tracking';
ALTER TABLE `user_statistics` ADD COLUMN `current_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Current consecutive days streak';
ALTER TABLE `user_statistics` ADD COLUMN `longest_streak` INT(11) NOT NULL DEFAULT '0' COMMENT 'Longest consecutive days streak';
ALTER TABLE `user_statistics` ADD COLUMN `longest_streak_start` DATE DEFAULT NULL COMMENT 'Start date of longest streak';
ALTER TABLE `user_statistics` ADD COLUMN `longest_streak_end` DATE DEFAULT NULL COMMENT 'End date of longest streak';
ALTER TABLE `user_statistics` ADD COLUMN `day_statistics` TEXT DEFAULT NULL COMMENT 'JSON: connections per day of week';
```

These columns are automatically added when you access the stats display admin page or when the bot runs.

## How It Works

### 1. Data Collection

The `stats-tracking-bot.php` collects data in real-time:

- **On Each Connection**: Records the date and day of week
- **Daily Tracking**: Only counts each date once per user (multiple connections same day = 1 day)
- **Streak Calculation**: Automatically calculates consecutive day streaks
- **Day Statistics**: Tracks which day of week (Sun-Sat) user connects most

### 2. Streak Algorithm

The system uses an intelligent streak calculation algorithm:

```php
- Sorts all connection dates
- Finds gaps of 1 day = consecutive days
- Tracks longest streak with start/end dates
- Calculates current streak (if last seen was today or yesterday)
```

### 3. Data Storage Optimization

- **Connection Dates**: Stored as JSON array, limited to last 400 days to prevent huge database entries
- **Day Statistics**: Stored as JSON array of 7 integers (Sunday=0 to Saturday=6)
- **Automatic Cleanup**: Old dates beyond 400 days are pruned automatically

### 4. Percentage Calculation

```
Total Days Since First Seen = (Today - First Seen Date) + 1
Percentage = (Connected Days / Total Days Since First Seen) × 100
```

## Configuration

### Admin Panel Setup

1. Go to **Admin Panel** → **Stats & Leaderboards**
2. Click **Add New Configuration**
3. Select:
   - **Channel**: The channel where stats will display
   - **Display Type**: Select "Detailed User Stats"
   - **Top Count**: Number of users to show (e.g., 10, 20, 50)
4. Click **Add Configuration**

### Display Type: `detailed_stats`

When you select "Detailed User Stats" as the display type, the channel will show:

- Header: "📊 Detailed User Statistics"
- Rankings based on total online time
- All metrics formatted as shown above
- Horizontal separators between users
- Last updated timestamp

## Bot Configuration

The stats tracking bot must be running to collect this data:

```bash
# Run in daemon mode (continuous)
php src/private/php/stats-tracking-bot.php --daemon --interval 60

# Or via systemd (recommended)
systemctl start ts-stats-bot
systemctl enable ts-stats-bot
```

The bot will:
1. Track every connection and disconnection
2. Record unique connection dates for each user
3. Calculate streaks automatically
4. Update day-of-week statistics
5. Refresh channel descriptions periodically

## API Endpoints

### Update Specific Channel

```bash
curl -X POST "https://your-site.com/api/stats-display-update.php" \
  -H "Content-Type: application/json" \
  -d '{"channel_id": 123}'
```

### Update All Channels

```bash
curl -X POST "https://your-site.com/api/stats-display-update.php" \
  -H "Content-Type: application/json"
```

## Technical Details

### Performance Considerations

- **JSON Storage**: Using JSON for dates and day stats keeps database normalized
- **Limited History**: Only last 400 connection dates stored to prevent bloat
- **Efficient Queries**: Indexed on `cldbid` for fast lookups
- **Cached Calculations**: Streaks calculated once per connection, not on display

### Streak Edge Cases

1. **Same Day Multiple Connections**: Counted as 1 day
2. **Timezone**: Uses server timezone (date('Y-m-d'))
3. **Gap Detection**: Any gap > 1 day breaks the streak
4. **Current Streak**: Only valid if last seen was today or yesterday

### Date Format Examples

- **First Connected**: `9th July, 2011` (jS F, Y)
- **Last Online**: `30th December, 2025, 9:54pm` (jS F, Y, g:ia)
- **Streak Dates**: `28th May, 2014 to 8th September, 2014`

## Migration & Compatibility

### Automatic Schema Updates

When you first access the admin page or run the bot:

1. New columns are automatically added to `user_statistics` table
2. Existing user data is preserved
3. No manual SQL required

### Existing Users

For users who already have statistics:

- **First Connection**: Will start tracking from their next connection
- **Historical Data**: Previous connection counts are preserved
- **Gradual Build**: Streak and day data builds over time as users connect

### Fresh Install

For new installations:

- All tables and columns are created automatically
- No manual setup required
- Bot starts tracking immediately

## Troubleshooting

### No Statistics Showing

**Problem**: Channel shows "No statistics available yet"

**Solution**:
1. Ensure bot is running: `ps aux | grep stats-tracking-bot`
2. Check bot logs for errors
3. Verify users have connected since bot started
4. Manually trigger update via admin panel

### Incorrect Streaks

**Problem**: Streak numbers seem wrong

**Solution**:
1. Streaks only track from when feature was activated
2. Historical connections before this feature won't count toward streaks
3. Bot must be running continuously to not miss connection days

### Dates Not Updating

**Problem**: "Last online" shows old date

**Solution**:
1. Ensure bot is running in daemon mode
2. Check bot interval (recommended: 60 seconds)
3. Verify bot has TeamSpeak server query permissions
4. Check bot connection to database

### Performance Issues

**Problem**: Bot using too much CPU/memory

**Solution**:
1. Increase bot interval (e.g., from 60s to 120s)
2. Reduce number of stat display channels
3. Check for database connection leaks
4. Ensure connection_dates array isn't too large (should auto-limit to 400)

## Example Output

Here's what a typical channel description looks like:

```bbcode
[center][size=16][b]📊 Detailed User Statistics[/b][/size][/center]

[size=12]🥇 [b]#1 [url=https://web.reape.rs/profile.php?cldbid=3]Admin[/url][/b][/size]

[size=10][b]first connected:[/b] 1st January, 2020[/size]
[size=10][b]last online:[/b] 2nd January, 2026, 3:45pm[/size]
[size=10][b]total time:[/b] 15,847 hrs[/size]
[size=10][b]connected days:[/b] 1,825 days[/size]
[size=10][b]percentage days:[/b] 82.53% (1,825/2,211)[/size]
[size=10][b]most consecutive days:[/b] 365 (1st Jan, 2024 to 31st Dec, 2024)[/size]
[size=10][b]most popular day:[/b] Saturday (450)[/size]

[hr]

[size=12]🥈 [b]#2 [url=https://web.reape.rs/profile.php?cldbid=125]RegularUser[/url][/b][/size]

[size=10][b]first connected:[/b] 15th March, 2021[/size]
[size=10][b]last online:[/b] 2nd January, 2026, 1:20pm[/size]
[size=10][b]total time:[/b] 8,234 hrs[/size]
[size=10][b]connected days:[/b] 892 days[/size]
[size=10][b]percentage days:[/b] 51.34% (892/1,738)[/size]
[size=10][b]most consecutive days:[/b] 87 (1st Jun, 2023 to 27th Aug, 2023)[/size]
[size=10][b]most popular day:[/b] Sunday (215)[/size]

[hr]

[right][size=8]Last updated: 2026-01-02 15:45:30[/size][/right]
```

## Best Practices

1. **Run Bot Continuously**: Use systemd or screen/tmux to keep bot running
2. **Regular Backups**: Back up `user_statistics` table regularly (contains all tracking data)
3. **Monitor Performance**: Watch bot logs and database size
4. **Update Interval**: 60 seconds is optimal for real-time tracking
5. **Channel Permissions**: Ensure bot has permission to edit channel descriptions
6. **Test First**: Set up on a test channel before production
7. **Documentation**: Keep this file accessible for team members

## Future Enhancements

Potential future additions:

- Average session length calculation
- Peak activity hours tracking
- Monthly/yearly trends graphs (web interface)
- Export statistics to CSV/JSON
- Custom date ranges for statistics
- Comparison between users
- Achievements/badges for milestones

## Support

If you encounter issues:

1. Check bot logs: `tail -f /var/log/ts-stats-bot.log`
2. Verify database columns exist
3. Test manual update via admin panel
4. Check TeamSpeak server query credentials
5. Review this documentation

## Files Modified/Created

### Modified Files
- `src/private/php/Utils/StatsDisplayManager.php`: Added detailed stats tracking and display
- `src/admin/stats-leaderboard-display.php`: Added "Detailed Stats" option
- `src/private/php/stats-tracking-bot.php`: Enhanced to track daily connections and streaks

### New Database Columns
- `user_statistics.connected_days`
- `user_statistics.connection_dates`
- `user_statistics.current_streak`
- `user_statistics.longest_streak`
- `user_statistics.longest_streak_start`
- `user_statistics.longest_streak_end`
- `user_statistics.day_statistics`

---

**Version**: 1.0  
**Last Updated**: January 2, 2026  
**Author**: TS Website Enhancement Team
