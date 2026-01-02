# Detailed Stats - Date Display Fix

## Issue

The detailed user statistics were showing incorrect dates because:
1. `first_seen` and `last_seen` dates were pulled from `user_statistics` table, which only tracks from when the stats feature was activated
2. The actual first connection date (`created_ts`) and last connection date (`lastconnected_ts`) are stored in the `profiles` table from TeamSpeak server query data

## What Was Fixed

### 1. Date Source Correction

**Before**: Pulled dates from `user_statistics` table
```php
$firstConnected = date('jS F, Y', strtotime($user['first_seen']));
$lastOnline = date('jS F, Y, g:ia', strtotime($user['last_seen']));
```

**After**: Pulls dates DIRECTLY from TeamSpeak server query (same as viewer.php and profile.php)
```php
// Query TeamSpeak server directly for live data
if (TeamSpeakUtils::i()->checkTSConnection()) {
    $tsInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientDbInfo($cldbid);
    
    // Get client_created (first connection)
    if (isset($tsInfo["client_created"])) {
        $createdTs = (int)$tsInfo["client_created"];
        $firstConnected = date('jS F, Y', $createdTs);
    }
    
    // Get client_lastconnected (last online)
    if (isset($tsInfo["client_lastconnected"])) {
        $lastconnectedTs = (int)$tsInfo["client_lastconnected"];
        $lastOnline = date('jS F, Y, g:ia', $lastconnectedTs);
    }
}
```

This uses the exact same method as `viewer.php` and `profile.php`, ensuring dates are always accurate and up-to-date.

### 2. Direct TeamSpeak Server Query

The detailed stats display now queries TeamSpeak server directly using `clientDbInfo()`:

```php
$tsInfo = TeamSpeakUtils::i()->getTSNodeServer()->clientDbInfo($cldbid);
```

This returns:
- `client_created`: Unix timestamp of first connection
- `client_lastconnected`: Unix timestamp of last connection
- `client_totalconnections`: Total connections count

This is the **exact same method** used by:
- `viewer.php` - for client info popover
- `profile.php` - for profile page display
- `getclientinfo.php` - for API

### 3. Fallback to Database

If TeamSpeak server query fails, it falls back to `profiles` table data.

### 3. Percentage Calculation Fix

**Before**: Used `user_statistics.first_seen` for percentage calculation

**After**: Uses `profiles.created_ts` for accurate total days calculation
```php
if (!empty($user['created_ts']) && $user['created_ts'] > 0) {
    $firstDate = new \DateTime('@' . (int)$user['created_ts']);
    $now = new \DateTime();
    $totalDays = $firstDate->diff($now)->days + 1;
    $percentageDays = round(($connectedDays / $totalDays) * 100, 2);
}
```

### 4. Column Name Corrections

Fixed references from `profiles.connections` to `profiles.totalconnections`:

**StatsDisplayManager.php**:
```php
$profile = $db->get("profiles", "totalconnections", ["cldbid" => $cldbid]);
if ($profile && isset($profile['totalconnections'])) {
    $existingConnections = (int)$profile['totalconnections'];
}
```

**stats-tracking-bot.php**:
```php
$profiles = $db->select("profiles", ["cldbid", "totalconnections", "nickname"], 
    ["totalconnections[>]" => 0]);
```

## Files Modified

1. **src/private/php/Utils/StatsDisplayManager.php**:
   - Modified `buildDetailedUserStats()` to JOIN with `profiles` table
   - Updated date formatting to use `created_ts` and `lastconnected_ts`
   - Modified `recordConnection()` to update `profiles.lastconnected_ts` in real-time
   - Fixed column references from `connections` to `totalconnections`

2. **src/private/php/stats-tracking-bot.php**:
   - Fixed column references from `connections` to `totalconnections`

## Result

Now the detailed statistics display shows:

✅ **Correct First Connected Date**: From TeamSpeak's `client_created` timestamp  
✅ **Real-time Last Online**: Updated by bot on every connection  
✅ **Accurate Percentage**: Based on actual first connection date, not stats activation date  
✅ **Proper Date Format**: "2nd January, 2026" and "2nd January, 2026, 9:25pm"  

## Example Output

```
🥇 #1 Username

first connected: 10th February, 2024
last online: 2nd January, 2026, 9:25pm
total time: 1,234 hrs
connected days: 245 days
percentage days: 36.89% (245/664)
most consecutive days: 45 (1st May, 2025 to 14th June, 2025)
most popular day: Saturday (67)
```

## Database Schema

### profiles table (TeamSpeak data)
```sql
created_ts INT(11)          -- Unix timestamp: when user first connected to TS server
lastconnected_ts INT(11)    -- Unix timestamp: when user last connected (updated by bot)
totalconnections INT(11)    -- Total connections count from TS
```

### user_statistics table (Tracking data)
```sql
first_seen TIMESTAMP        -- When bot first saw user (for tracking start date)
last_seen TIMESTAMP         -- When bot last tracked user (for bot monitoring)
connected_days INT(11)      -- Unique days connected (tracked by bot)
connection_dates TEXT       -- JSON array of dates (tracked by bot)
longest_streak INT(11)      -- Consecutive days streak (calculated by bot)
```

## How It Works Now

1. **First Connected**: Queries TS server `clientDbInfo()` → `client_created` timestamp
2. **Last Online**: Queries TS server `clientDbInfo()` → `client_lastconnected` timestamp
3. **Total Time**: Calculated from `user_statistics.total_online_hours` (tracked by bot)
4. **Connected Days**: Counted from `user_statistics.connection_dates` (tracked by bot)
5. **Percentage**: `connected_days / (today - client_created)` (accurate from TS first connection)
6. **Streaks**: Calculated from `connection_dates` array (tracked by bot)
7. **Popular Day**: From `day_statistics` JSON (tracked by bot)

### Data Flow

```
Channel Update Triggered
    ↓
For each user in leaderboard:
    ↓
Query TS Server: clientDbInfo(cldbid)
    ↓
Get client_created & client_lastconnected
    ↓
Format dates: "10th February, 2024"
    ↓
Get stats from user_statistics table
    ↓
Build BBCode description
    ↓
Update channel
```

## Verification

To verify the fix is working:

### 1. Check Database
```sql
SELECT 
    us.cldbid,
    us.last_nickname,
    FROM_UNIXTIME(p.created_ts) as first_connected,
    FROM_UNIXTIME(p.lastconnected_ts) as last_online,
    us.connected_days,
    us.total_online_hours
FROM user_statistics us
LEFT JOIN profiles p ON us.cldbid = p.cldbid
ORDER BY us.total_online_hours DESC
LIMIT 10;
```

### 2. Check Channel Display
- Go to the configured stats channel in TeamSpeak
- Dates should now match your profile page dates
- Format: "10th February, 2024" and "2nd January, 2026, 9:25pm"

### 3. Test Real-time Updates
- Connect to TeamSpeak server
- Wait 1-2 minutes for bot to process
- Check channel description - "last online" should update
- Check database - `profiles.lastconnected_ts` should be updated

## Troubleshooting

### Dates still showing wrong

**Check 1**: Ensure bot is running
```bash
ps aux | grep stats-tracking-bot
```

**Check 2**: Verify profiles table has data
```sql
SELECT cldbid, FROM_UNIXTIME(created_ts), FROM_UNIXTIME(lastconnected_ts) 
FROM profiles 
WHERE cldbid = YOUR_CLDBID;
```

**Check 3**: Manual channel update
- Go to Admin Panel → Stats & Leaderboards
- Click "Update Now" next to your detailed stats channel

### "Unknown" dates appearing

**Cause**: `profiles` table doesn't have `created_ts` or `lastconnected_ts` for that user

**Fix**: The dates will populate when:
1. User connects to TS server (TeamSpeak sends the data)
2. Profile page is viewed (pulls from TS server query)
3. Bot processes user connection (updates lastconnected_ts)

### Percentage seems wrong

**Cause**: May be normal if user hasn't connected daily since first connection

**Example**: 
- First connected: 365 days ago
- Connected days: 100 days
- Percentage: 27.4% (connected 100 out of 365 days)

This is correct - it shows what percentage of days since first connection the user has actually connected.

## Migration Notes

- **Automatic**: No manual SQL needed
- **Existing Data**: Preserved and enhanced with TS server data
- **Backward Compatible**: Old stats displays still work
- **Performance**: JOIN operation is efficient (both tables indexed on cldbid)

## Benefits

1. **Historical Accuracy**: Shows actual first connection from TS server, not when stats feature was enabled
2. **Real-time Last Online**: Updates immediately when user connects
3. **Correct Percentages**: Calculated from actual TS server history
4. **Reliable Data**: Uses authoritative TS server query data as source of truth
5. **Enhanced Tracking**: Combines TS server data with bot tracking data

---

**Version**: 1.1  
**Date**: January 2, 2026  
**Status**: ✅ Fixed and Deployed
