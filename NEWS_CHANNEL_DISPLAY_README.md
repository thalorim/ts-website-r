# 📰 News Channel Display

> Display your website news automatically in TeamSpeak channel descriptions

## 🎯 Quick Start (30 seconds)

1. **Visit**: `https://your-website.com/admin/`
2. **Click**: "News Channel Display" (green button)
3. **Add**: Select a channel and click "Add Configuration"
4. **Done**: Your news now appears in the channel description!

## 📖 Documentation

- **[Quick Overview](NEWS_CHANNEL_DISPLAY_OVERVIEW.md)** - Start here for complete overview
- **[Installation Guide](NEWS_CHANNEL_DISPLAY_INSTALLATION.md)** - Step-by-step setup
- **[Feature Details](NEWS_CHANNEL_DISPLAY_FEATURE.md)** - Deep dive into features
- **[Implementation Summary](NEWS_CHANNEL_DISPLAY_SUMMARY.md)** - Technical details

## ✨ What This Does

Automatically displays your latest news articles in TeamSpeak channel descriptions:
- 📝 Shows news titles, dates, and content previews
- 🔄 Auto-updates when you add new news
- ⚙️ Configurable per channel (how many news items to show)
- 🎨 Beautiful BBCode formatting
- 🔗 Links to your website for full articles

## 🎨 Example Display

Your channels will show:
```
━━━━━━━━━━━━━━━━━━━━━━━━━━
      📰 Latest News
━━━━━━━━━━━━━━━━━━━━━━━━━━

New Feature Released!
November 15, 2024
Check out our latest update with amazing
new features...

Server Maintenance Notice
November 12, 2024
Scheduled maintenance this weekend...

» View all news on website «
```

## 🗂️ Files Created

```
src/
├── admin/
│   └── news-channel-display.php      ← Admin configuration page
├── api/
│   └── news-display-update.php       ← API endpoint
└── private/
    └── php/
        ├── Utils/
        │   └── NewsDisplayManager.php ← Core manager class
        └── news-display-bot.php       ← Monitoring bot
```

## 🚀 Features

- ✅ Web-based configuration
- ✅ Automatic updates on news save
- ✅ Manual update buttons
- ✅ Bot script for continuous monitoring
- ✅ REST API endpoint
- ✅ Beautiful admin interface
- ✅ Security built-in (CSRF, XSS protection)
- ✅ Multiple channel support
- ✅ Customizable news limit per channel

## 🔧 Usage Examples

### Admin Panel (Easiest)
```
1. Login as admin (CLDBID 3)
2. Go to Admin Panel
3. Click "News Channel Display"
4. Click "Add Channel Configuration"
5. Select channel and save
```

### Bot Script (Optional)
```bash
# Run once
php src/private/php/news-display-bot.php --once

# Run continuously (5 min interval)
php src/private/php/news-display-bot.php --daemon --interval=300

# Background process
nohup php src/private/php/news-display-bot.php --daemon > /tmp/news-bot.log 2>&1 &
```

### API (Advanced)
```bash
# Update all channels
curl -X POST https://your-site.com/api/news-display-update.php \
  --cookie "session=..."

# Update specific channel
curl -X POST https://your-site.com/api/news-display-update.php \
  -d "channel_id=123" \
  --cookie "session=..."
```

## 🗄️ Database

Auto-creates table: `channel_news_display`
- Stores channel configurations
- Tracks last update times
- Configures news limit per channel

Uses existing table: `news`
- Your website's news articles
- Fields: newsid, title, content, added, edited

## 🎓 How It Works

```
Add News → Auto-Update Channels → News Appears in TeamSpeak
    ↓
Database
    ↓
NewsDisplayManager → BBCode Format → TeamSpeak API → Channel Description
```

## 🔐 Security

- ✅ Admin-only access (CLDBID 3)
- ✅ CSRF token protection
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ Input validation

## 🎛️ Configuration

**Per Channel Settings:**
- Channel selection (dropdown)
- News limit (1-20 items)
- Enable/disable flag

**Global Settings:**
- Bot update interval (default: 300 seconds)
- BBCode format (customizable in code)

## 🐛 Troubleshooting

**Not updating?**
- Check you're CLDBID 3
- Verify TeamSpeak connection
- Check bot permissions

**Channel not listed?**
- Refresh channel list in TeamSpeak
- Verify channel exists
- Check TeamSpeak connection

**Bot not running?**
- `ps aux | grep news-display-bot`
- Check PHP CLI: `which php`
- Review logs

## 📊 Statistics

Admin panel shows:
- Total news count
- Configured channels count
- Last update timestamps
- Update success/failure stats

## 🎨 Customization

**BBCode Format:**
Edit `buildChannelDescription()` in `NewsDisplayManager.php`

**News Limit:**
Change in admin panel per channel (1-20)

**Update Interval:**
Adjust bot `--interval` parameter (seconds)

**Styling:**
Modify CSS in `news-channel-display.php`

## 📚 Based On

This feature follows the same architecture as:
- `status-display.php` - User status display
- Uses similar patterns and styling
- Compatible with existing features

## ✅ Requirements

- TeamSpeak server with query access
- Existing TS-Website installation
- Admin access (CLDBID 3)
- MySQL database
- PHP with TeamSpeak3 library

## 🎉 Ready to Use!

Everything is set up and ready. Just:
1. Visit admin panel
2. Configure a channel
3. Watch your news appear in TeamSpeak!

## 📝 Documentation Index

| Document | Purpose | Audience |
|----------|---------|----------|
| [README](NEWS_CHANNEL_DISPLAY_README.md) | Quick reference | Everyone |
| [Overview](NEWS_CHANNEL_DISPLAY_OVERVIEW.md) | Complete overview | Developers |
| [Installation](NEWS_CHANNEL_DISPLAY_INSTALLATION.md) | Setup guide | Administrators |
| [Features](NEWS_CHANNEL_DISPLAY_FEATURE.md) | Detailed features | Power users |
| [Summary](NEWS_CHANNEL_DISPLAY_SUMMARY.md) | Technical details | Developers |

## 🤝 Support

1. Check documentation files
2. Review code comments
3. Compare with status-display feature
4. Check error logs
5. Verify TeamSpeak connection

## 📜 License

Same license as TS-Website project.

---

**Status**: ✅ Ready for Production

**Version**: 1.0

**Created**: January 2, 2026

**Enjoy your new News Channel Display feature!** 🎊
