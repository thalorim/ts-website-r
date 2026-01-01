# 🎯 SOLUTION: Two TS-Website Installations Found!

## Problem Identified ✅

You have **TWO ts-website installations** on your server:

1. **Installation #1:** `/var/www/web.reape.rs/`
2. **Installation #2:** `/var/www/` (root)

**Both are connecting to the same TeamSpeak server**, causing:
- Multiple query client connections
- "thal1", "thal2", "RBot", etc. (different nicknames from each installation)
- Rapid connect/disconnect spam in logs
- Duplicate queries even though cache is set correctly

---

## 🔍 Verify Which Is Active

Check which installation(s) are being served by your web server:

### Step 1: Check Nginx/Apache Configuration

```bash
# For Nginx:
grep -r "web.reape.rs" /etc/nginx/sites-enabled/
grep -r "root /var/www" /etc/nginx/sites-enabled/

# For Apache:
grep -r "DocumentRoot" /etc/apache2/sites-enabled/
grep -r "web.reape.rs" /etc/apache2/sites-enabled/
```

### Step 2: Check Query Nicknames in Each Installation

```bash
# Check Installation #1:
mysql -e "USE your_database; SELECT value FROM config WHERE identifier = 'query_nickname';" 2>/dev/null

# Or check config files directly:
grep -r "query_nickname" /var/www/web.reape.rs/private/
grep -r "query_nickname" /var/www/private/

# Check for database config:
cat /var/www/web.reape.rs/private/config.local.php
cat /var/www/private/config.local.php
```

### Step 3: Check TeamSpeak Connection Settings

```bash
# Installation #1:
grep -E "query_|ts3_|teamspeak" /var/www/web.reape.rs/private/config.local.php

# Installation #2:
grep -E "query_|ts3_|teamspeak" /var/www/private/config.local.php
```

---

## 🎯 Which Installation to Keep?

### Keep Installation #1 (`/var/www/web.reape.rs/`) IF:
- ✓ This is your main/production website
- ✓ Domain `web.reape.rs` points to this
- ✓ This has the correct/updated configuration
- ✓ This is the one you've been working on

### Keep Installation #2 (`/var/www/`) IF:
- ✓ This is actually your main installation
- ✓ `/var/www/web.reape.rs/` is a backup/test
- ✓ This has the production configuration

**Most likely:** Keep `/var/www/web.reape.rs/` (your domain suggests this is the active one)

---

## ⚡ SOLUTION: Remove Duplicate Installation

### Option 1: Complete Removal (Recommended)

If `/var/www/private/` is NOT needed:

```bash
# 1. Backup first (just in case):
tar -czf /root/backup-var-www-private-$(date +%Y%m%d).tar.gz /var/www/private/

# 2. Stop any running processes:
systemctl stop php-fpm  # or php7.4-fpm, php8.1-fpm, etc.

# 3. Remove the duplicate installation:
rm -rf /var/www/private/

# 4. Restart services:
systemctl start php-fpm
systemctl restart nginx  # or apache2

# 5. Verify:
find /var/www -name "TeamSpeakUtils.php" 2>/dev/null
# Should only show: /var/www/web.reape.rs/private/php/Utils/TeamSpeakUtils.php
```

### Option 2: Disable Without Deleting

If you want to keep it as a backup but stop it from connecting:

```bash
# Rename the config to disable it:
mv /var/www/private/config.local.php /var/www/private/config.local.php.DISABLED

# Or remove TS connection credentials:
# Edit /var/www/private/config.local.php and remove query credentials
```

### Option 3: Use Different Query Credentials

If you need BOTH installations (e.g., test + production):

```bash
# On TeamSpeak server, create separate query user:
# Via ServerQuery:
serverqueryadd query_username=website_test query_password=different_password

# Then configure the second installation to use different credentials
# And set a different query_nickname in database
```

---

## ✅ Verify the Fix

### Step 1: Confirm Single Installation

```bash
find /var/www -name "TeamSpeakUtils.php" 2>/dev/null
# Should show only ONE path
```

### Step 2: Check TeamSpeak Query Clients

On your TeamSpeak server (via ServerQuery or telnet to port 10011):

```bash
login serveradmin your_password
use port=9987
clientlist -uid -ip
# Filter for client_type=1 (query clients)
```

**Before fix:** Multiple query clients (thal1, RBot, etc.)  
**After fix:** Only 1-2 query clients (your website + maybe one reconnecting)

### Step 3: Monitor Logs

```bash
# Watch TeamSpeak logs:
tail -f /path/to/teamspeak/logs/ts3server_*.log

# Should see:
# - Far fewer "connected" messages
# - Only your configured query_nickname
# - No more "thal1", "RBot" spam
```

### Step 4: Check Website Still Works

```bash
# Visit your website:
curl http://web.reape.rs/
# Or open in browser: http://web.reape.rs/

# Check if server status displays correctly
# Check if viewer shows online users
```

---

## 📊 Expected Results After Fix

### Before (Current):
```
Query Clients: 5-20+ simultaneous connections
Nicknames: thal1, thal2, RBot, RBot0-8, Unknown
Pattern: Rapid connect/disconnect every 1-3 seconds
Cause: Two websites + nickname collision retries
```

### After (Fixed):
```
Query Clients: 1-2 maximum
Nicknames: Your configured query_nickname (e.g., "TS-website")
Pattern: Stable connection, refreshes every 120 seconds (cache)
Cause: Single website with proper caching
```

---

## 🔍 Detailed Investigation Commands

If you want to understand exactly what's happening before removing anything:

### Check Web Server Configuration

```bash
# Nginx - which directories are served:
nginx -T | grep -E "root|web.reape.rs"

# Apache - which directories are served:
apache2ctl -S | grep -E "DocumentRoot|web.reape.rs"
```

### Check Database Connections

```bash
# See which config files reference which databases:
grep -r "PDO\|mysqli\|database" /var/www/web.reape.rs/private/config.local.php
grep -r "PDO\|mysqli\|database" /var/www/private/config.local.php

# Check if they use the same database:
# If yes → They might be sharing config, but still connecting separately
```

### Check PHP-FPM Pools

```bash
# See if you have multiple PHP pools configured:
ls -la /etc/php/*/fpm/pool.d/
cat /etc/php/*/fpm/pool.d/*.conf | grep -E "listen|user"
```

### Check Process Count

```bash
# See how many PHP processes are running:
ps aux | grep php-fpm | wc -l

# Each process might open its own TS connection
# With 2 installations × multiple processes = many connections
```

---

## 🎯 Recommended Action Plan

### Immediate (Do Now):

1. **Backup both installations:**
   ```bash
   cd /var/www
   tar -czf ~/backup-ts-websites-$(date +%Y%m%d-%H%M%S).tar.gz web.reape.rs/ private/
   ```

2. **Identify which is active:**
   ```bash
   # Check which directory your web server is using:
   curl -I http://web.reape.rs/ 
   # Note the response
   
   # Temporarily rename one installation and test:
   mv /var/www/private /var/www/private.DISABLED
   curl -I http://web.reape.rs/
   # Does website still work?
   ```

3. **If website works → Remove the disabled one:**
   ```bash
   rm -rf /var/www/private.DISABLED/
   ```

4. **If website breaks → Restore and check the other:**
   ```bash
   mv /var/www/private.DISABLED /var/www/private
   # Check if /var/www/web.reape.rs/ is the duplicate instead
   ```

### Short-term (This Week):

5. **Clear PHP opcache:**
   ```bash
   systemctl restart php-fpm
   # Or: systemctl reload php7.4-fpm
   ```

6. **Monitor TS logs for 24 hours:**
   ```bash
   tail -f /path/to/teamspeak/logs/ts3server_*.log | grep "connected\|disconnected"
   ```

7. **Verify website performance:**
   - Check http://web.reape.rs/api/debug-cache-stats.php
   - Should show only expected query load
   - Cache settings should still be 120s

---

## 🚨 Common Mistakes to Avoid

### ❌ DON'T:
- Remove both installations (keep at least one!)
- Forget to backup before removing
- Remove without checking which is active
- Forget to restart services after changes

### ✅ DO:
- Backup before making changes
- Test after each step
- Monitor logs to confirm fix
- Keep the active/production installation
- Document which installation you kept

---

## 💡 Why This Happened

Likely scenarios:

1. **Website moved/migrated:**
   - Old installation at `/var/www/private/`
   - New installation at `/var/www/web.reape.rs/`
   - Forgot to remove old one

2. **Test installation:**
   - Created second copy for testing
   - Both ended up running in production

3. **Symbolic links or includes:**
   - One might include the other
   - Both connecting independently

4. **Different domains:**
   - Two domains pointing to two installations
   - Both using same TS server

---

## 📞 After Removing Duplicate

### What to Watch For:

**Immediate (0-5 minutes):**
- ✅ Website still loads at http://web.reape.rs/
- ✅ No PHP errors in logs
- ✅ Server status displays correctly

**Short-term (10-30 minutes):**
- ✅ Only 1-2 query clients in TS server
- ✅ No more "thal1", "RBot" connections
- ✅ Logs show normal pattern (every 120s)

**Long-term (24 hours):**
- ✅ No increase in query connections
- ✅ Website remains stable
- ✅ TS server logs clean

---

## 🎯 Quick Commands Summary

```bash
# 1. Backup everything
tar -czf ~/ts-website-backup-$(date +%Y%m%d).tar.gz /var/www/web.reape.rs/ /var/www/private/

# 2. Check which is active
nginx -T | grep "root" | grep -E "web.reape.rs|/var/www"

# 3. Disable duplicate (safer than delete)
mv /var/www/private /var/www/private.DISABLED

# 4. Restart services
systemctl restart php-fpm nginx

# 5. Verify single installation
find /var/www -name "TeamSpeakUtils.php" | grep -v DISABLED

# 6. Monitor TS logs
tail -f /path/to/teamspeak/logs/ts3server_*.log

# 7. If all good after 24 hours, delete:
rm -rf /var/www/private.DISABLED/
```

---

## ✅ Success Criteria

The fix is successful when:

1. ✅ Only ONE ts-website installation found
2. ✅ Website at http://web.reape.rs/ works correctly
3. ✅ Only 1-2 query clients on TS server
4. ✅ No "thal1", "RBot", or unknown query clients
5. ✅ Logs show connections every 120 seconds (not every 1-3s)
6. ✅ Cache stats show healthy metrics
7. ✅ No user complaints

**Timeline:** You should see improvement within 5-10 minutes of removing the duplicate.

---

## 🆘 If Something Goes Wrong

### Website Breaks After Removal:

```bash
# Restore the backup:
cd /var/www
tar -xzf ~/ts-website-backup-*.tar.gz

# Or restore from DISABLED:
mv /var/www/private.DISABLED /var/www/private

# Then investigate which installation is actually active
```

### Still Seeing Multiple Query Clients:

This means there are **additional** applications beyond the two websites:
- Check for music bots (SinusBot, etc.)
- Check cron jobs: `crontab -l`
- Check systemd services: `systemctl list-units | grep -i bot`
- Check running processes: `ps aux | grep -E "thal|rbot|query"`

---

**Action Required:** Remove the duplicate installation at `/var/www/private/` to eliminate the query spam.

This will solve your issue immediately! 🚀
