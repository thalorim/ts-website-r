# Specific News Selection Feature

## 📰 Overview

You can now select **specific news items** to display in each TeamSpeak channel, instead of just showing the latest news automatically.

## ✨ New Feature Highlights

### Before (v1.0)
- ✅ Show latest 5 news items
- ✅ Configurable limit (1-20)
- ❌ Can't choose which news to show

### After (v1.1)
- ✅ Show latest news (automatic)
- ✅ **OR select specific news items**
- ✅ Choose exactly which news appear
- ✅ Full control per channel

## 🎯 Use Cases

### Example 1: Important Announcements Channel
```
Channel: "📢 Important Announcements"
Selected News:
  - [✓] Server Rules (newsid: 1)
  - [✓] Voting Links (newsid: 2)
  - [✓] Welcome Message (newsid: 3)
  - [ ] Other news...

Result: Only these 3 news items display, always
```

### Example 2: Latest News Channel
```
Channel: "📰 Latest News"
Selected News:
  - [ ] None selected

Result: Shows latest 5 news automatically
```

### Example 3: Events Channel
```
Channel: "🎉 Events & Updates"
Selected News:
  - [✓] Summer Event (newsid: 15)
  - [✓] Tournament Announcement (newsid: 18)

Result: Only these 2 news items display
```

## 🚀 How to Use

### Step 1: Access Admin Panel
1. Go to: `https://your-site.com/admin/news-channel-display.php`
2. Find your channel configuration

### Step 2: Select Specific News

#### For Existing Channels:
1. Find your channel in the table
2. Click the **Edit button** (📝) in the "Display Mode" column
3. **Check the news** you want to display
4. Click **"Save & Update Channel"**
5. Done! Channel updates immediately

#### For New Channels:
1. Click **"Add Channel Configuration"**
2. Select channel
3. **Check the news** you want to display (optional)
4. If no news checked, shows latest automatically
5. Click **"Add Configuration"**

### Step 3: Verify in TeamSpeak
- Open TeamSpeak
- View the channel description
- Only selected news items will appear

## 📊 Display Modes

### Mode 1: Latest News (Default)
**When**: No specific news selected
**Shows**: Latest X news items (based on News Limit)
**Updates**: Automatically when new news added
**Best for**: News channels, updates channels

```
Display Mode: [Latest News]
News Limit: 5
Result: Shows 5 most recent news
```

### Mode 2: Specific News
**When**: One or more news items selected
**Shows**: Only the selected news items
**Updates**: Only when you add/remove selections
**Best for**: Pinned announcements, rules, important info

```
Display Mode: [Specific News]
Selected: 3 news items
Result: Shows only those 3 news (ignores News Limit)
```

## 🎨 Admin Interface

### Configuration Table
```
┌────┬────────┬─────────────┬──────────────┬──────────┬─────────┐
│ ID │Channel │ Display Mode│ News Limit   │ Status   │ Actions │
├────┼────────┼─────────────┼──────────────┼─────────┼─────────┤
│ 1  │ Rules  │[Specific ✎] │ 5            │ Active   │ 🔄 🗑️  │
│    │        │3 news sel.  │              │          │         │
├────┼────────┼─────────────┼──────────────┼─────────┼─────────┤
│ 2  │ News   │[Latest  ✎]  │ 10           │ Active   │ 🔄 🗑️  │
│    │        │Show latest  │              │          │         │
└────┴────────┴─────────────┴──────────────┴─────────┴─────────┘

✎ = Click to edit news selection
🔄 = Update channel now
🗑️ = Delete configuration
```

### News Selection Modal
```
┌─────────────────────────────────────────────────┐
│ Select News to Display                      [×] │
├─────────────────────────────────────────────────┤
│                                                 │
│ ℹ️ Leave all unchecked to show latest news     │
│    automatically. Or select specific news.      │
│                                                 │
│ ┌─────────────────────────────────────────┐   │
│ │ ☑ Server Rules (ID: 1, Jan 1, 2024)     │   │
│ │ ☑ Voting Links (ID: 2, Jan 2, 2024)     │   │
│ │ ☐ Welcome Message (ID: 3, Dec 15, 2023) │   │
│ │ ☐ Event Announcement (ID: 4, ...)       │   │
│ └─────────────────────────────────────────┘   │
│                                                 │
│          [Cancel]  [Save & Update Channel]      │
└─────────────────────────────────────────────────┘
```

## 🔧 Technical Details

### Database Schema Update
New column added to `channel_news_display` table:

```sql
ALTER TABLE `channel_news_display` 
ADD COLUMN `selected_news_ids` TEXT NULL DEFAULT NULL 
COMMENT 'Comma-separated news IDs to display (NULL = show latest)' 
AFTER `news_limit`;
```

**Values:**
- `NULL` or empty = Show latest news (automatic)
- `"1,2,3"` = Show news with IDs 1, 2, and 3
- `"5"` = Show only news with ID 5

### How It Works

#### When Displaying News:
```php
if (selected_news_ids is not empty) {
    // Show specific news
    $newsIds = explode(',', selected_news_ids);
    $news = SELECT * FROM news WHERE newsid IN ($newsIds);
} else {
    // Show latest news
    $news = SELECT * FROM news ORDER BY added DESC LIMIT news_limit;
}
```

#### Behavior:
1. **Specific news selected**: Ignores `news_limit`, shows all selected
2. **No specific news**: Uses `news_limit` to show latest
3. **Selected news deleted**: Automatically skips deleted news
4. **Order**: Specific news show in date order (DESC)

## 📋 Common Scenarios

### Scenario 1: Permanent Rules Channel
```
Problem: Rules keep getting pushed down by new news
Solution: Create channel, select only "Rules" news item
Result: Rules always display, never change
```

### Scenario 2: Rotating News
```
Problem: Want to control which news appear
Solution: Select specific important news items
Result: Only selected news appear, ignore others
```

### Scenario 3: Multiple Channels
```
Channel 1 "Rules": Show news IDs 1, 2
Channel 2 "Events": Show news IDs 5, 8, 12
Channel 3 "Latest": Show latest 10 (auto)

Result: Each channel shows different content
```

### Scenario 4: Temporary News
```
Add news ID 20 to selection → Shows immediately
Remove news ID 20 → Disappears immediately
No need to delete news from database
```

## 🔄 Migration

### For Existing Installations
The database column is **automatically added** when you:
1. Visit the admin page
2. Or run the backup/update script

**Existing configurations:**
- `selected_news_ids` = NULL
- Behavior: Shows latest news (same as before)
- **No change to existing behavior**

### Manual Database Update (if needed)
```sql
-- Check if column exists
SHOW COLUMNS FROM channel_news_display LIKE 'selected_news_ids';

-- Add column if missing
ALTER TABLE channel_news_display 
ADD COLUMN selected_news_ids TEXT NULL DEFAULT NULL 
COMMENT 'Comma-separated news IDs to display (NULL = show latest)' 
AFTER news_limit;
```

## 🎯 Best Practices

### 1. Use Latest Mode for News Channels
```
✅ Latest news channel → Show latest
✅ Updates channel → Show latest
✅ Announcements → Show latest
```

### 2. Use Specific Mode for Static Content
```
✅ Rules channel → Specific news
✅ Information channel → Specific news
✅ Welcome channel → Specific news
```

### 3. Combine Both
```
Channel 1: Show latest 5 news
Channel 2: Show specific important news
Channel 3: Show specific rules only
```

### 4. Update Regularly
- Review selected news monthly
- Remove outdated selections
- Add new important news

## 📊 Examples

### Example 1: Server Information Channel
```php
Channel: "ℹ️ Server Information"
Selected News:
  [✓] Server Rules (ID: 1)
  [✓] How to Get Started (ID: 2)
  [✓] Contact Information (ID: 3)
  [✓] FAQ (ID: 4)

Result in TeamSpeak:
━━━━━━━━━━━━━━━━━━━━
     📰 Latest News
━━━━━━━━━━━━━━━━━━━━

Server Rules
January 1, 2024
[Content of Server Rules...]

How to Get Started
January 1, 2024
[Content of Getting Started...]

...and so on
```

### Example 2: Latest Updates Channel
```php
Channel: "📰 Latest Updates"
Selected News: (none)
News Limit: 10

Result: Shows 10 most recent news automatically
```

## 🐛 Troubleshooting

### Selected News Not Showing
**Check:**
1. News ID exists in database: `SELECT * FROM news WHERE newsid = X;`
2. News ID in configuration: Check "Display Mode" column
3. Channel updated: Click refresh button
4. TeamSpeak cache: Reload channel description

### Shows Wrong News
**Solution:**
1. Click edit button (📝) in Display Mode column
2. Verify checked news items
3. Uncheck unwanted news
4. Save and update

### Can't Find News to Select
**Check:**
1. News exists in database: `SELECT * FROM news;`
2. Admin page refreshed: Reload page
3. Database connection: Check error logs

### News Disappeared
**Possible causes:**
1. News deleted from database
2. Configuration changed to "Latest" mode
3. News ID removed from selection
4. Check logs: `/root/backups/logs/`

## 🔍 Testing

### Test Specific News Selection
```bash
# 1. Add a configuration with specific news
# Via admin panel: Select news IDs 1, 2, 3

# 2. Update channel
sudo /root/backup_website.sh --once

# 3. Check database
mysql -u user -p -e "SELECT * FROM channel_news_display;"

# 4. Check TeamSpeak channel description
# Should show only news IDs 1, 2, 3
```

### Test Latest News Mode
```bash
# 1. Edit configuration
# Via admin panel: Uncheck all news

# 2. Update channel
# Click "Update Channel Now" button

# 3. Check TeamSpeak
# Should show latest X news
```

## 📝 API Usage

### Update with Specific News
```bash
# API automatically uses selected_news_ids from database
curl -X POST https://your-site.com/api/news-display-update.php \
  -d "channel_id=123" \
  --cookie "session=..."
```

### Bot Script
```bash
# Bot automatically reads selected_news_ids from configurations
php src/private/php/news-display-bot.php --once
```

## ✅ Summary

**What's New:**
- ✅ Select specific news per channel
- ✅ Or show latest automatically
- ✅ Full control over displayed content
- ✅ Easy selection via checkboxes
- ✅ Backward compatible

**Benefits:**
- 🎯 Control exactly what displays
- 📌 Pin important news permanently
- 🔄 Latest news still auto-update
- 💪 Flexible per channel
- 🚀 Easy to use

**Status**: ✅ Feature Complete (v1.1)
**Added**: January 2, 2026
**Compatibility**: Fully backward compatible
