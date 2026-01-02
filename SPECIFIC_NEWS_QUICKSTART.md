# 🎯 Quick Guide - Select Specific News to Display

## ✨ New Feature!

You can now **select exactly which news items** to display in each channel, instead of just showing the latest news.

## 🚀 How to Use (3 Steps)

### Step 1: Open Admin Panel
```
https://your-site.com/admin/news-channel-display.php
```

### Step 2: Select News
Find your channel and click the **📝 Edit button** in the "Display Mode" column.

### Step 3: Choose News
- ✅ **Check the news** you want to show
- ❌ **Uncheck** to remove from display
- 💡 **Leave all unchecked** to show latest automatically

Click **"Save & Update Channel"** - Done!

## 📊 Two Display Modes

### 🔄 Latest News (Automatic)
```
✓ No news selected
✓ Shows latest X news
✓ Auto-updates when new news added
```

**Example**: News channel showing latest 5 articles

### 📌 Specific News (Manual)
```
✓ Select specific news items
✓ Shows only those news
✓ Stays the same (pinned)
```

**Example**: Rules channel showing only "Server Rules" and "Getting Started"

## 🎯 Perfect For

### Pinned Content
```
Channel: "📋 Server Rules"
Select: 
  [✓] Server Rules (newsid: 1)
  [✓] How to Join (newsid: 2)

Result: These 2 news always show, never change
```

### Important Announcements
```
Channel: "⚠️ Important"
Select:
  [✓] Maintenance Notice
  [✓] New Features
  [✓] Voting Links

Result: Only these 3 news display
```

### Latest Updates
```
Channel: "📰 Latest News"
Select: (nothing)
News Limit: 10

Result: Shows 10 most recent news, auto-updates
```

## 💡 Quick Examples

### Example 1: Rules Channel
1. Go to admin page
2. Find "Rules" channel
3. Click **📝 Edit** button
4. Check ✅ "Server Rules" (newsid: 1)
5. Check ✅ "Getting Started" (newsid: 2)
6. Save
7. ✅ Done! Only these 2 news show

### Example 2: News Channel
1. Go to admin page
2. Find "News" channel
3. Click **📝 Edit** button
4. Uncheck all news
5. Save
6. ✅ Done! Shows latest 5 news automatically

## 🔍 Visual Guide

### Before (v1.0)
```
Channel Configuration:
┌─────────────┬────────────┬────────┐
│ Channel     │ News Limit │ Status │
├─────────────┼────────────┼────────┤
│ Rules       │ 5          │ Active │
│ (Latest 5)  │            │        │
└─────────────┴────────────┴────────┘

Shows: Latest 5 news (always changing)
```

### After (v1.1)
```
Channel Configuration:
┌─────────────┬──────────────┬────────────┬────────┐
│ Channel     │ Display Mode │ News Limit │ Status │
├─────────────┼──────────────┼────────────┼────────┤
│ Rules       │ [Specific ✎] │ 5          │ Active │
│             │ 2 news sel.  │            │        │
└─────────────┴──────────────┴────────────┴────────┘

Shows: Only selected news (pinned)
Click ✎ to select which news to display
```

## 📋 What You See in Admin Panel

### Display Mode Column
```
[Latest News]  ← Shows latest automatically
[Specific News] ← Shows selected news only
     ✎         ← Click to edit selection
```

### Selection Modal
```
┌────────────────────────────────────┐
│ Select News to Display         [×] │
├────────────────────────────────────┤
│ ℹ️ Leave unchecked = show latest   │
│                                    │
│ ☑ Server Rules (ID: 1)             │
│ ☑ Voting Links (ID: 2)             │
│ ☐ Welcome Message (ID: 3)          │
│ ☐ Latest Event (ID: 4)             │
│                                    │
│   [Cancel] [Save & Update Channel] │
└────────────────────────────────────┘
```

## ✅ What Changed

**Database**: Added `selected_news_ids` column (auto-created)
**Admin Page**: Added news selection checkboxes
**API**: Updated to handle selected news
**Bot**: Updated to use selected news

**Your existing configs**: Still work the same! ✅

## 🔧 Troubleshooting

### Can't see the edit button?
- Refresh the page
- Clear browser cache

### Selected news not showing?
- Click "Update Channel Now" button
- Wait a few seconds for TeamSpeak to update

### Want to go back to latest news?
- Click edit button
- Uncheck all news
- Save

## 📚 Full Documentation

See **NEWS_CHANNEL_SPECIFIC_NEWS_FEATURE.md** for complete details.

---

**Status**: ✅ Ready to Use!
**Version**: 1.1
**Date**: January 2, 2026

**Enjoy your new selective news display!** 🎉
