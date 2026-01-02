# Fix "Unknown" Dates - Quick Solution

## Problem

Your stats channel shows:
```
first connected: Unknown
last online: Unknown
```

## Root Cause

The `profiles` table doesn't have TeamSpeak server data populated yet.

## Solution

Run this ONE command:

```bash
cd /path/to/your/website
php src/private/php/sync-profiles-from-ts.php
```

## What This Does

1. Connects to your TeamSpeak server
2. Queries `clientDbInfo()` for each user in your statistics
3. Extracts `client_created` and `client_lastconnected` timestamps
4. Saves to `profiles` table
5. Shows progress for each user

## Expected Output

```
=================================================
Sync Profiles from TeamSpeak Server
=================================================
Started at: 2026-01-02 22:30:15
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

Next step:
  Go to Admin Panel → Stats & Leaderboards → Click 'Update Now'
```

## Then Update Your Channel

1. Go to **Admin Panel** → **Stats & Leaderboards**
2. Find your "Detailed User Stats" configuration
3. Click **"Update Now"**

## Result

Your stats channel will now show:

```
🥇 #1 YourName

first connected: 10th February, 2024        ← Fixed! ✅
last online: 2nd January, 2026, 9:25pm     ← Fixed! ✅
total time: 567 hrs
connected days: 245 days
percentage days: 36.89% (245/664)
most consecutive days: 45 (1st May, 2025 to 14th June, 2025)
most popular day: Saturday (67)
```

## Alternative: Sync Just Your Profile

If you only want to fix your own stats:

```bash
php src/private/php/sync-profiles-from-ts.php --cldbid=3
```

Replace `3` with your CLDBID (find it on your profile page URL).

## Why This Happened

When you first set up detailed stats:
1. ✅ `user_statistics` table was created (has connected_days, streaks, etc.)
2. ❌ `profiles` table was empty (needs TS server data)

The stats display queries TeamSpeak server for dates, but if that fails, it falls back to the `profiles` table. Since the `profiles` table was empty, you saw "Unknown".

Now the sync script populates `profiles` table with TeamSpeak data, so fallback works correctly.

## Future-Proof

After this initial sync:
- **Bot updates**: Stats bot will keep `lastconnected_ts` updated
- **Profile views**: Viewing a profile page updates their data
- **Stats display**: Queries TS → falls back to profiles table → always works

You won't need to run the sync again.

## Troubleshooting

### "Cannot connect to TeamSpeak server"

**Check your `config.php`:**
```php
"server_query" => [
    "host" => "127.0.0.1",
    "port" => 10011,
    "username" => "serveradmin",
    "password" => "your_password"
]
```

**Test TS connection:**
```bash
telnet 127.0.0.1 10011
```

If connection fails, your TS server query is not accessible.

### Still showing "Unknown" after sync

**Check if data was saved:**
```bash
mysql -u user -p database_name

SELECT cldbid, nickname, created_ts, lastconnected_ts 
FROM profiles 
WHERE cldbid = YOUR_CLDBID;
```

If `created_ts` and `lastconnected_ts` are NULL, the sync failed.

**Try visiting your profile page manually:**
```
https://your-site.com/profile.php?cldbid=YOUR_CLDBID
```

This will trigger a TS query and save data.

## Need Help?

See complete guide: `PROFILES_SYNC_GUIDE.md`

---

**TL;DR**: Run `php src/private/php/sync-profiles-from-ts.php`, then update your stats channel. Done! ✅
