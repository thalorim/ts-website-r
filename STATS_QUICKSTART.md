# 🚀 Stats & Leaderboard Quick Start

## ⚡ Setup in 3 Steps (5 Minutes)

### Step 1: Start the Tracking Bot
```bash
# Run in background
nohup php src/private/php/stats-tracking-bot.php --daemon > /tmp/stats-bot.log 2>&1 &

# Verify it's running
ps aux | grep stats-tracking-bot
```

### Step 2: Configure a Channel
1. Visit: `https://your-site.com/admin/stats-leaderboard-display.php`
2. Click **"Add Channel Configuration"**
3. Select a channel
4. Choose **"Combined"** (shows everything)
5. Set top count to **10**
6. Click **"Add Configuration"**

### Step 3: Check TeamSpeak
- Wait ~5-10 minutes for data collection
- Open TeamSpeak
- View the channel description
- See beautiful leaderboard tables! 🎉

## 📊 What You'll See

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  📊 Server Statistics & Leaderboards
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Server Overview
👥 Users: 156 | 🟢 Online: 23 | 🔄 Connections: 4,521

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🏆 Top by Connections
[TABLE WITH MEDALS 🥇🥈🥉]

⏱️ Top by Online Time
[TABLE WITH MEDALS 🥇🥈🥉]
```

## 🎯 Display Types

| Type | What It Shows | Best For |
|------|---------------|----------|
| **Combined** | Stats + Both leaderboards | General channel |
| **Connections** | Full connections leaderboard | Hall of fame |
| **Online Time** | Full time leaderboard | Activity tracking |
| **Server Stats** | Overall statistics only | Info channel |

## 🤖 Bot Commands

```bash
# Start bot (daemon mode)
php src/private/php/stats-tracking-bot.php --daemon

# Test run (once)
php src/private/php/stats-tracking-bot.php --once

# Custom interval (30 seconds)
php src/private/php/stats-tracking-bot.php --daemon --interval=30

# Check if running
ps aux | grep stats-tracking-bot

# View logs
tail -f /tmp/stats-bot.log

# Stop bot
pkill -f stats-tracking-bot
```

## 🏆 Features

✅ **Tracks**: Connections & Online Time
✅ **BBCode Tables**: Beautiful formatting
✅ **Clickable Profiles**: Links to user pages
✅ **Medals**: 🥇🥈🥉 for top 3
✅ **Real-time**: Updates every 5 minutes
✅ **Multiple Types**: 4 display options

## 📋 Common Tasks

### Update a Channel Now
1. Go to admin page
2. Click green 🔄 button next to channel
3. Check TeamSpeak immediately

### Change Display Type
1. Go to admin page
2. Click yellow ✏️ edit button
3. Select new type
4. Save changes

### Add More Channels
- You can configure unlimited channels
- Each can show different type
- Example: One "combined", one "connections only"

## 🐛 Troubleshooting

### No Data Showing?
```bash
# Bot must run for a while first
# Check if bot is running:
ps aux | grep stats-tracking-bot

# If not, start it:
php src/private/php/stats-tracking-bot.php --daemon &
```

### Channel Not Updating?
1. Click "Update All Channels" button
2. Wait 10 seconds
3. Reload TeamSpeak channel
4. If still not working, check bot logs

### Bot Stopped?
```bash
# Restart bot
nohup php src/private/php/stats-tracking-bot.php --daemon > /tmp/stats-bot.log 2>&1 &
```

## 💡 Tips

1. **Let bot run**: Data accumulates over time
2. **Multiple channels**: Show different views
3. **Top count**: 10-15 is optimal for readability
4. **Combined type**: Best for overview
5. **Monitor bot**: Check logs periodically

## 📚 Full Documentation

See **STATS_LEADERBOARD_FEATURE.md** for complete details.

---

**That's it!** Start the bot, configure a channel, and watch the leaderboards populate! 🎉
