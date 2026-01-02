# News Date Display Fix - "January 1, 1970" Issue

## Problem
News items showing "January 1, 1970" as the date in channel descriptions.

## Cause
The `added` field in your `news` table is either:
1. Set to `0` (Unix epoch timestamp)
2. Set to `NULL`
3. Not a valid Unix timestamp
4. Using MySQL TIMESTAMP/DATETIME format instead of Unix timestamp

## Solution Applied

### 1. Enhanced Date Parser
Added `parseNewsDate()` method that handles multiple date formats:
- **Unix timestamps** (integer) - validates range (2000-2100)
- **MySQL TIMESTAMP/DATETIME** strings - converts using `strtotime()`
- **Invalid dates** - returns 0, displays as "Recent"

### 2. Date Validation
- Rejects timestamps of 0 or very small numbers
- Validates dates are in reasonable range (year 2000+)
- Falls back to "Recent" for invalid dates

## Quick Fix

### Option 1: Update via Admin Panel (Recommended)
After applying the code fix:
1. Go to: `https://your-site.com/admin/news-channel-display.php`
2. Click **"Update All Channels Now"**
3. Check TeamSpeak - dates should now display correctly

### Option 2: Fix Database Directly

If your news has invalid dates, update them with proper timestamps:

```sql
-- Check current news dates
SELECT newsid, title, added, FROM_UNIXTIME(added) as readable_date 
FROM news 
ORDER BY newsid DESC;

-- If 'added' shows 0 or NULL, update with current timestamp
UPDATE news 
SET added = UNIX_TIMESTAMP() 
WHERE added = 0 OR added IS NULL;

-- Or set specific dates (example: set to January 1, 2024)
UPDATE news 
SET added = UNIX_TIMESTAMP('2024-01-01 00:00:00')
WHERE newsid = 1;

-- Update a specific news item to today
UPDATE news 
SET added = UNIX_TIMESTAMP(NOW())
WHERE newsid = 1;
```

### Option 3: If Your Database Uses DATETIME Instead of INT

If your `news` table uses `DATETIME` or `TIMESTAMP` for the `added` field:

```sql
-- Check your table structure
DESCRIBE news;

-- If 'added' is DATETIME/TIMESTAMP, the code will automatically handle it
-- No changes needed! The new parseNewsDate() method converts it automatically
```

## Verify Your News Table

Check what's actually in your database:

```sql
-- See the actual data
SELECT newsid, title, added, edited, 
       FROM_UNIXTIME(added) as date_readable
FROM news 
ORDER BY newsid DESC 
LIMIT 10;
```

Expected results:
- **Good**: `added = 1704067200` (Unix timestamp, shows as readable date)
- **Bad**: `added = 0` (shows as "1970-01-01")
- **Bad**: `added = NULL` (shows as NULL)

## Table Schema Check

Your `news` table should have:

```sql
CREATE TABLE `news` (
  `newsid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `added` int(11) NOT NULL,          -- Unix timestamp
  `edited` int(11) DEFAULT NULL,      -- Unix timestamp
  PRIMARY KEY (`newsid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Or with TIMESTAMP:

```sql
CREATE TABLE `news` (
  `newsid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `added` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `edited` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`newsid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Both formats are now supported!

## Testing

### Test the Fix

1. **Via Admin Panel:**
   ```
   Admin Panel → News Channel Display → Update All Channels Now
   ```

2. **Check TeamSpeak:**
   - Open TeamSpeak
   - View channel description
   - Date should show correctly (not "January 1, 1970")

3. **Via Bot:**
   ```bash
   php src/private/php/news-display-bot.php --once
   ```

### Expected Results

**Before Fix:**
```
Voting Links
January 1, 1970

Content here...
```

**After Fix:**
```
Voting Links
December 15, 2024

Content here...
```

Or if date is still invalid:
```
Voting Links
Recent

Content here...
```

## Manual News Date Update

If you want to set specific dates for your existing news:

```sql
-- Set "Voting Links" news to January 2, 2024
UPDATE news 
SET added = UNIX_TIMESTAMP('2024-01-02 10:00:00')
WHERE title LIKE '%Voting Links%';

-- Set "Welcome" news to December 1, 2023
UPDATE news 
SET added = UNIX_TIMESTAMP('2023-12-01 12:00:00')
WHERE title LIKE '%Welcome%';

-- Set all news with 0 timestamp to current time
UPDATE news 
SET added = UNIX_TIMESTAMP(NOW())
WHERE added = 0 OR added IS NULL;
```

## Common Scenarios

### Scenario 1: News Added via Admin Panel
- **Expected**: Automatically gets current timestamp
- **Code**: `DefaultNewsStore::addNews()` uses `time()`
- **No action needed**

### Scenario 2: News Added Directly to Database
- **Problem**: Might have 0 or NULL timestamp
- **Solution**: Update with proper timestamps (see SQL above)

### Scenario 3: News Imported from Another System
- **Problem**: Dates might be in different format
- **Solution**: Convert dates during import or run UPDATE script

### Scenario 4: MySQL TIMESTAMP Column
- **Expected**: Works automatically with new parseNewsDate() method
- **No action needed**

## Verification Script

Run this to check all your news dates:

```sql
SELECT 
    newsid,
    title,
    added as timestamp_value,
    CASE 
        WHEN added = 0 THEN '❌ INVALID (0)'
        WHEN added IS NULL THEN '❌ INVALID (NULL)'
        WHEN added < 946684800 THEN '❌ INVALID (too old)'
        ELSE '✅ VALID'
    END as status,
    FROM_UNIXTIME(added) as readable_date
FROM news
ORDER BY newsid DESC;
```

## Preventive Measures

### When Adding News via Admin Panel
- ✅ Automatically handled correctly
- ✅ Uses `time()` function
- ✅ Always valid timestamp

### When Adding News via API
```php
// Good - uses current time
$newsStore->addNews($title, $content);

// Good - specific time
$newsStore->addNews($title, $content, strtotime('2024-01-01'));

// Bad - don't pass 0
$newsStore->addNews($title, $content, 0);
```

### When Adding News via SQL
```sql
-- Good - current timestamp
INSERT INTO news (title, content, added) 
VALUES ('Title', 'Content', UNIX_TIMESTAMP());

-- Good - specific date
INSERT INTO news (title, content, added) 
VALUES ('Title', 'Content', UNIX_TIMESTAMP('2024-01-01 12:00:00'));

-- Bad - don't use 0
INSERT INTO news (title, content, added) 
VALUES ('Title', 'Content', 0);
```

## Changes Made

### File Modified
**`src/private/php/Utils/NewsDisplayManager.php`**

#### New Method Added
```php
private function parseNewsDate($date): int
```

**Features:**
- Handles Unix timestamps (integer)
- Handles MySQL TIMESTAMP/DATETIME (string)
- Validates date range (2000-2100)
- Returns 0 for invalid dates
- Displays "Recent" for invalid dates

#### Updated Method
```php
private function buildChannelDescription(array $newsList): string
```

**Changes:**
- Uses `parseNewsDate()` for both `added` and `edited` fields
- Better fallback handling
- Shows "Recent" instead of "January 1, 1970"

## Summary

✅ **Code Fixed**: Date parser now handles multiple formats
✅ **Validation**: Rejects invalid timestamps (0, NULL)
✅ **Fallback**: Shows "Recent" for unparseable dates
✅ **Flexible**: Supports both INT and TIMESTAMP columns

**Next Steps:**
1. Update channels via admin panel
2. Check dates in TeamSpeak
3. If still showing wrong dates, update database with SQL script
4. Future news will automatically have correct dates

## Support

If dates still show incorrectly after applying this fix:

1. **Check database values:**
   ```sql
   SELECT newsid, title, added FROM news ORDER BY newsid DESC LIMIT 5;
   ```

2. **Check column type:**
   ```sql
   DESCRIBE news;
   ```

3. **Update invalid dates:**
   ```sql
   UPDATE news SET added = UNIX_TIMESTAMP() WHERE added = 0;
   ```

4. **Trigger channel update:**
   - Admin panel → "Update All Channels Now"
   - Or: `php src/private/php/news-display-bot.php --once`

**Status**: ✅ Fixed in Code
**Version**: 1.1.1
**Date**: January 2, 2026
