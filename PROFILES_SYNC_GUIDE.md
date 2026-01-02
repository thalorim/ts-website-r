# Profile Data Sync Guide

## Problem

Your detailed stats are showing "Unknown" for dates because the `profiles` table doesn't have the TeamSpeak server data (created_ts, lastconnected_ts) populated yet.

## Solution

Run the sync script to populate the profiles table with data from TeamSpeak server.

## Quick Fix

### Option 1: Sync All Statistics Users (Recommended)

```bash
cd /path/to/your/website
php src/private/php/sync-profiles-from-ts.php
```

This will sync all users from your `user_statistics` table with TeamSpeak server data.

### Option 2: Sync Just Your Profile

```bash
php src/private/php/sync-profiles-from-ts.php --cldbid=3
```

Replace `3` with your actual CLDBID.

### Option 3: Sync All Online Users

```bash
php src/private/php/sync-profiles-from-ts.php --all
```

This syncs everyone currently connected to TeamSpeak.

## What It Does

The script:
1. Queries TeamSpeak server for each user: `clientDbInfo(cldbid)`
2. Extracts timestamps:
   - `client_created` → First connection date
   - `client_lastconnected` → Last connection date
   - `client_totalconnections` → Total connections
3. Saves to `profiles` table
4. Shows progress for each user

## Example Output

```
=================================================
Sync Profiles from TeamSpeak Server
=================================================
Mode: Sync all users from user_statistics table

Found 150 users in statistics

[3] Processing Admin... UPDATED (first: 2024-02-10) (last: 2026-01-02 21:25)
[12] Processing User123... UPDATED (first: 2023-05-15) (last: 2026-01-01 18:30)
[45] Processing RegularUser... CREATED (first: 2025-12-01) (last: 2026-01-02 09:15)
...

=================================================
Sync Complete
=================================================
Synced:  148
Skipped: 2
Failed:  0
Total:   150
=================================================

Success! Profile data has been populated.
You can now update your stats channels.
```

## After Running

1. **Update Your Stats Channel**:
   - Go to: Admin Panel → Stats & Leaderboards
   - Click **"Update Now"**

2. **Check TeamSpeak**:
   - View your stats channel
   - Dates should now show correctly!

## Automatic Future Updates

Once the initial sync is done:

- ✅ **Bot updates**: Stats tracking bot updates `lastconnected_ts` on connections
- ✅ **Profile views**: Viewing profile page updates data from TS
- ✅ **Stats display**: Queries TS directly and caches in profiles table

You shouldn't need to run the sync script again unless:
- You add many new historical users
- Your profiles table gets corrupted
- You want to refresh all data

## Troubleshooting

### "ERROR: Cannot connect to TeamSpeak server"

**Cause**: TeamSpeak server query credentials are incorrect or server is offline.

**Fix**:
1. Check your `config.php`:
   ```php
   "server_query" => [
       "host" => "127.0.0.1",
       "username" => "serveradmin",
       "password" => "your_password",
       ...
   ]
   ```
2. Test connection:
   ```bash
   telnet 127.0.0.1 10011
   # If connection fails, TS server query is not accessible
   ```

### "SKIPPED (no TS data)"

**Cause**: User exists in stats but not in TeamSpeak database.

**Solution**: This is normal for users who were deleted from TS or never actually connected. They'll be skipped automatically.

### "FAILED: Database error"

**Cause**: Profiles table doesn't exist or has incorrect schema.

**Fix**: Visit any profile page first to create the table:
```
https://your-site.com/profile.php?cldbid=3
```

## Manual Alternative

If you don't want to run the script, you can manually populate data by:

1. **Visit Your Profile Page**:
   - Go to: `https://your-site.com/profile.php?cldbid=YOUR_ID`
   - This queries TS and saves to profiles table

2. **Visit Each Top User's Profile**:
   - Click through profiles of your top 10-20 users
   - Each visit populates their data

3. **Update Stats Channel**:
   - Admin Panel → Update Now

## Verification

Check if data was synced:

```bash
mysql -u your_user -p your_database

SELECT 
    cldbid,
    nickname,
    FROM_UNIXTIME(created_ts) as first_connected,
    FROM_UNIXTIME(lastconnected_ts) as last_online,
    totalconnections
FROM profiles
WHERE cldbid = YOUR_CLDBID;
```

Should show your correct dates.

## Scheduling

To keep profiles table fresh, add to cron:

```bash
# Sync profiles daily at 3 AM
0 3 * * * cd /path/to/website && php src/private/php/sync-profiles-from-ts.php >/dev/null 2>&1
```

## Performance

- **Speed**: ~50-200ms per user
- **Load**: Minimal (queries are cached)
- **Recommendation**: Run during off-peak hours for large user bases (1000+)

For 150 users: ~7-30 seconds total

## Summary

1. **Run sync script**: `php src/private/php/sync-profiles-from-ts.php`
2. **Wait for completion**: Watch the progress output
3. **Update stats channel**: Admin Panel → Update Now
4. **Verify in TeamSpeak**: Dates should now be correct!

---

**Status**: Ready to run  
**Required**: TeamSpeak server must be online and accessible  
**Time**: < 1 minute for most installations
