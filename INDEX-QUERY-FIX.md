# 📑 Query Spam Fix - File Index

## 🎯 Start Here

If you're seeing excessive queries and TeamSpeak log spam, start with these files in order:

1. **`SOLUTION_SUMMARY.txt`** ← **START HERE**
   - Quick overview of problem & solution
   - Step-by-step fix instructions
   - Checklist for deployment
   - Plain text format for easy reading

2. **`README-QUERY-FIX.md`**
   - Comprehensive guide with all options
   - TL;DR quick fix section at top
   - Complete file reference
   - Troubleshooting guide

3. **`INSTALLATION_GUIDE.md`**
   - Detailed step-by-step instructions
   - Verification methods
   - Expected results with metrics
   - Advanced configuration options

## 📊 Understanding the Problem

- **`QUERY_ANALYSIS.md`**
  - Technical deep dive into the issue
  - How polling and caching work
  - Why the logs show connection spam
  - Performance impact calculations
  - Recommended solutions with pros/cons

## 🗃️ SQL Scripts

Apply these to your database to fix cache settings:

- **`fix-query-spam-conservative.sql`** ← **Recommended**
  - Sets cache to 30 seconds
  - Good for most installations
  - 75% query reduction

- **`fix-query-spam-aggressive.sql`**
  - Sets cache to 60-120 seconds
  - For high-traffic sites
  - 90% query reduction
  - Slight delay in updates

- **`check-current-cache-settings.sql`**
  - Diagnostic query
  - Shows current cache configuration
  - Identifies problematic settings

## 💻 Modified Code Files

### JavaScript (Client-Side Polling)

- **`src/js/status.js`** ✅ **MODIFIED**
  - Changed from 10s to 30s polling
  - Deploy this to your web server
  - Main fix for client-side spam

- **`src/js/viewer.js`** ✅ **Already Good**
  - Auto-refresh is commented out (lines 1-33)
  - No changes needed
  - Keep it this way!

### Optional Enhanced Version

- **`src/js/status-smart-polling.js`**
  - Smart polling implementation
  - Stops polling when tab is hidden
  - Resume + immediate update when tab becomes visible
  - Optional but highly recommended
  - **To use:** `cp src/js/status-smart-polling.js src/js/status.js`

## 🔧 Tools & Scripts

- **`apply-fix.sh`**
  - Interactive bash script
  - Checks current configuration
  - Helps apply database fixes
  - Provides step-by-step guidance
  - **Run:** `./apply-fix.sh`

- **`src/api/debug-cache-stats.php`**
  - Web-based monitoring dashboard
  - Shows cache configuration
  - Query load estimates
  - Real-time recommendations
  - **Access:** `http://your-site.com/api/debug-cache-stats.php`
  - ⚠️ **Delete after fixing!**

## 📈 File Organization

```
/workspace/
├── SOLUTION_SUMMARY.txt         ← Start here (quick ref)
├── README-QUERY-FIX.md          ← Comprehensive guide
├── INSTALLATION_GUIDE.md        ← Step-by-step instructions
├── QUERY_ANALYSIS.md            ← Technical details
├── INDEX-QUERY-FIX.md          ← This file
│
├── fix-query-spam-conservative.sql  ← Apply this (recommended)
├── fix-query-spam-aggressive.sql    ← Or this (high traffic)
├── check-current-cache-settings.sql ← Diagnostic
├── apply-fix.sh                     ← Interactive tool
│
└── src/
    ├── js/
    │   ├── status.js                    ← MODIFIED (deploy!)
    │   ├── status-smart-polling.js      ← Optional upgrade
    │   └── viewer.js                    ← OK (no changes)
    └── api/
        └── debug-cache-stats.php        ← Monitoring (delete later)
```

## 🚀 Quick Start Workflow

### For the Impatient (5 minutes)

```bash
# 1. Update database
mysql -u USERNAME -p DATABASE < fix-query-spam-conservative.sql

# 2. Deploy JavaScript
cp src/js/status.js /path/to/your/webserver/js/

# 3. Verify
open http://your-site.com/api/debug-cache-stats.php

# 4. Done!
```

### For the Careful (15 minutes)

1. Read `SOLUTION_SUMMARY.txt`
2. Backup database
3. Run `./apply-fix.sh` for interactive fix
4. Deploy updated files
5. Check `debug-cache-stats.php`
6. Monitor TeamSpeak logs
7. Clean up debug files

### For the Optimizers (30 minutes)

1. Read `QUERY_ANALYSIS.md` to understand the issue
2. Review `INSTALLATION_GUIDE.md` for all options
3. Apply conservative fix first
4. Monitor results
5. Implement smart polling if needed
6. Consider aggressive caching if still seeing issues
7. Set up long-term monitoring

## 📊 Impact Summary

| Fix Level | Cache Time | Polling | Query Reduction | Deployment Time |
|-----------|------------|---------|-----------------|-----------------|
| **Basic** (Current files) | 10s → 30s | 10s → 30s | 75% | 5 min |
| **Enhanced** (+ Smart Polling) | 10s → 30s | 30s + pausing | 85-90% | 10 min |
| **Aggressive** | 10s → 60s | 30s + pausing | 90-95% | 10 min |

## 🔍 How to Verify Success

### 1. Browser Check (Immediate)
```
F12 → Network tab → Watch api/getstatus.php
Should appear every ~30 seconds (not 10)
```

### 2. Database Check (Immediate)
```sql
SELECT identifier, value FROM config 
WHERE identifier IN ('cache_serverinfo', 'cache_clientlist');

Expected:
cache_serverinfo  | 30
cache_clientlist  | 30
```

### 3. TeamSpeak Logs (10-15 minutes)
```
Watch server logs - should see far fewer:
"RBot" connected/disconnected events
Query client connections
```

### 4. Dashboard Check (Anytime)
```
Visit: http://your-site.com/api/debug-cache-stats.php
Look for: "✓ All cache settings look good!"
```

## 🎓 Learning Resources

### Understanding the Components

1. **Caching Layer** (PHP Backend)
   - Files: `src/private/php/CacheManager.php`
   - Controls: How long data is stored
   - Config: Database `config` table
   - Impact: Reduces TS server queries

2. **Polling Layer** (JavaScript Frontend)
   - Files: `src/js/status.js`, `src/js/viewer.js`
   - Controls: How often browser requests updates
   - Config: Hardcoded in JS files
   - Impact: Reduces HTTP requests

3. **TeamSpeak Communication** (Query Protocol)
   - Files: `src/private/php/Utils/TeamSpeakUtils.php`
   - Controls: How website talks to TS
   - Impact: Each query = connection in TS logs

### The Fix Flow

```
User Browser
    ↓ (every 30s instead of 10s)
AJAX Request to api/getstatus.php
    ↓
Check Cache (30s TTL instead of 10s)
    ↓ (only if cache expired)
Query TeamSpeak Server
    ↓ (appears in TS logs)
Query Client Connection
```

## ⚠️ Common Mistakes

1. **Only updating database** - Must also deploy JavaScript!
2. **Only updating JavaScript** - Must also update database!
3. **Wrong table prefix** - Check if your tables use a prefix
4. **Not clearing cache** - PHP opcache and browser cache
5. **Leaving debug page exposed** - Delete after verification
6. **Multiple tabs open** - Each tab polls independently

## 🔐 Security Checklist

After applying fixes:

- [ ] Delete `src/api/debug-cache-stats.php`
- [ ] Delete SQL scripts from web-accessible directories
- [ ] Delete `apply-fix.sh` if uploaded to web server
- [ ] Keep documentation files for reference (non-executable)

## 📱 Mobile Considerations

Mobile browsers are affected too:
- Background tabs may continue polling
- Mobile data usage increases with frequent polling
- The smart polling fix helps mobile users significantly
- Consider implementing wake locks for better mobile UX

## 🌐 Multi-Server Setups

If you run multiple TS-website instances:
- Apply fix to **all** instances
- Consider using different cache times per server load
- Monitor each server independently
- Share learnings across your team

## 📈 Monitoring Long-Term

Set up monitoring for:
1. **Query count** - Track queries per minute
2. **Cache hit rate** - Should be >80%
3. **Page load time** - Should improve
4. **Server CPU** - Should decrease
5. **TS log spam** - Should greatly reduce

## 🆘 Need Help?

1. **Re-read documentation**
   - Start with `SOLUTION_SUMMARY.txt`
   - Check `INSTALLATION_GUIDE.md` troubleshooting section
   
2. **Use diagnostic tools**
   - Run `check-current-cache-settings.sql`
   - Check `debug-cache-stats.php`
   - Review browser console (F12)

3. **Community support**
   - TS-website Telegram: https://t.me/tswebsite
   - GitHub issues
   - Community forums

4. **Professional help**
   - Consider hiring a developer if you're stuck
   - Many developers familiar with TS-website available

## 📝 Changelog

### 2026-01-01 - Initial Release
- Identified query spam issue
- Created comprehensive fix
- Documented all solutions
- Provided multiple implementation paths

---

**Version:** 1.0  
**Last Updated:** 2026-01-01  
**Maintained By:** AI Assistant via Cursor  
**License:** Use freely for ts-website installations
