# Query Spam Fix - Installation Guide

## Problem
Your TeamSpeak website is sending too many query requests, causing:
- Spam in TeamSpeak server logs
- High server load
- Potential connection issues
- Query client constantly connecting/disconnecting

## Quick Fix (5 minutes) - RECOMMENDED

### Step 1: Update Database Cache Settings

1. Connect to your database (MySQL/MariaDB)
2. Run one of these SQL scripts:

**Option A: Conservative Fix (Recommended for most users)**
```bash
mysql -u your_username -p your_database < fix-query-spam-conservative.sql
```

**Option B: Aggressive Fix (For high-traffic sites)**
```bash
mysql -u your_username -p your_database < fix-query-spam-aggressive.sql
```

**Option C: Manual SQL**
```sql
-- Run these commands in your database
UPDATE config SET value = '30' WHERE identifier = 'cache_serverinfo';
UPDATE config SET value = '30' WHERE identifier = 'cache_clientlist';
```

### Step 2: Update JavaScript (Already Done)

The file `src/js/status.js` has been updated to poll every 30 seconds instead of 10 seconds.

**No additional action needed** - just ensure you deploy the updated file to your web server.

### Step 3: Clear Cache & Test

1. Clear your browser cache or open an incognito window
2. Open your TeamSpeak website
3. Open browser DevTools (F12) → Network tab
4. Watch for requests to `api/getstatus.php`
5. Verify they occur every ~30 seconds (not every 10 seconds)

## Expected Results

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Cache duration (serverinfo) | 10s | 30s | **3x longer** |
| Cache duration (clientlist) | 15s | 30s | **2x longer** |
| JavaScript polling | 10s | 30s | **3x slower** |
| **Total query reduction** | - | - | **~75-90%** |

### With 10 active users:
- **Before**: ~60 queries per minute
- **After**: ~15 queries per minute

## Advanced Fix - Smart Polling (Optional)

For even better performance, replace `src/js/status.js` with `src/js/status-smart-polling.js`:

```bash
cp src/js/status-smart-polling.js src/js/status.js
```

**Benefits:**
- Stops polling when browser tab is hidden/inactive
- Reduces queries by an additional 50-70% (depending on user behavior)
- Saves server resources and bandwidth

## Verification

### 1. Check Database Settings
```bash
mysql -u your_username -p your_database < check-current-cache-settings.sql
```

Expected output:
```
+-----------------------+---------+--------+
| identifier            | seconds | status |
+-----------------------+---------+--------+
| cache_serverinfo      | 30      | ✓ OK   |
| cache_clientlist      | 30      | ✓ OK   |
| cache_channelist      | 60      | ✓ GOOD |
+-----------------------+---------+--------+
```

### 2. Check JavaScript Polling
1. Open website in browser
2. Press F12 → Console
3. Should see: "Started status polling (every 30s)"

### 3. Monitor TeamSpeak Logs
Watch your TeamSpeak server logs - you should see significantly fewer query client connections/disconnections.

## Troubleshooting

### Issue: "UPDATE config SET..." fails
**Solution**: Replace `DBPREFIX` with your actual database prefix:
- If your config table is named `config` → use empty prefix
- If your config table is named `tswebsite_config` → use `tswebsite_` prefix

Example:
```sql
UPDATE config SET value = '30' WHERE identifier = 'cache_serverinfo';
-- OR
UPDATE tswebsite_config SET value = '30' WHERE identifier = 'cache_serverinfo';
```

### Issue: Changes don't take effect
**Solution**: 
1. Clear PHP opcache (if enabled): `service php-fpm reload` or restart web server
2. Clear browser cache: Ctrl+Shift+Delete or use incognito mode
3. Check file permissions: Ensure updated files are readable by web server

### Issue: Website updates too slowly now
**Solution**: If 30s polling feels too slow, use 20s instead:
```sql
UPDATE config SET value = '20' WHERE identifier = 'cache_serverinfo';
UPDATE config SET value = '20' WHERE identifier = 'cache_clientlist';
```
And in `status.js` change:
```javascript
}, 20 * 1000)  // 20 seconds
```

## Files Modified

- ✅ `src/js/status.js` - Increased polling interval from 10s to 30s
- ✅ `QUERY_ANALYSIS.md` - Detailed analysis of the issue
- ✅ `fix-query-spam-conservative.sql` - SQL script for conservative fix
- ✅ `fix-query-spam-aggressive.sql` - SQL script for aggressive fix
- ✅ `check-current-cache-settings.sql` - SQL script to check current settings
- ✅ `src/js/status-smart-polling.js` - Optional enhanced version with smart polling

## Need Help?

1. Read `QUERY_ANALYSIS.md` for detailed explanation
2. Check TeamSpeak website documentation
3. Join the ts-website Telegram group for support

## Rollback

If you need to revert changes:

**Database:**
```sql
UPDATE config SET value = '10' WHERE identifier = 'cache_serverinfo';
UPDATE config SET value = '15' WHERE identifier = 'cache_clientlist';
```

**JavaScript:**
```bash
git checkout src/js/status.js
```
