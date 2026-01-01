# 🚨 Multiple Query Clients Connecting - Root Cause Analysis

## Critical Discovery

You have **multiple ServerQuery clients** connecting to your TS server with different nicknames:
- "thal1", "thal2"
- "RBot", "RBot0-8"  
- "Unknown"

These are NOT regular clients - they're **query clients** like your website, but something is spawning too many of them.

---

## 🔍 How the Website Connects

From `TeamSpeakUtils.php` (lines 62-83), the website:

1. Connects via ServerQuery
2. Sets nickname to `query_nickname` config value
3. If nickname is taken, adds random number (0-9)
4. Tries up to 5 times with different suffixes

**Expected behavior:** 1 query client named like "TS-website" or "TS-website3"

**What you're seeing:** Multiple query clients with names like "thal1", "RBot", etc.

---

## 🎯 Possible Causes

### Cause 1: Multiple Website Instances (Most Likely)
You might be running the **same website on multiple servers/domains** all connecting to the same TS server.

**Check for:**
```bash
# If you have multiple web servers
ps aux | grep php-fpm
ps aux | grep apache2
ps aux | grep nginx

# Check if website is installed in multiple places
find /var/www -name "config.local.php" -o -name "ts-website"
```

**Signs this is the issue:**
- Each web server = 1 query connection
- Different servers might set different `query_nickname` values
- "thal1" and "RBot" might be configured on different installations

### Cause 2: Connection Not Being Reused
The website uses a **Singleton pattern** (line 14: `use SingletonTait`), but if PHP instances aren't sharing connections, each page load might create a new one.

**Signs this is the issue:**
- High website traffic
- Many simultaneous users
- Connections not being closed properly
- PHP-FPM with many worker processes

### Cause 3: Other Applications Using Same Query Credentials
You might have **other bots/scripts** using the same query username/password:
- Music bots
- Admin bots  
- Status updaters
- Channel managers
- Backup scripts

**Signs this is the issue:**
- "RBot" = Probably a music/admin bot
- "thal" = Unknown application
- Different nickname patterns suggest different applications

### Cause 4: Connection Timeout/Reconnect Loop
If connections are timing out, the website might be reconnecting constantly.

**Signs this is the issue:**
- Rapid connect/disconnect
- Same nickname appearing repeatedly
- Happens even when no users on website

---

## 🔧 Diagnostic Steps

### Step 1: Check Your Query Nickname Config

Check what nickname YOUR website is using:

```sql
SELECT value FROM config WHERE identifier = 'query_nickname';
```

Is it "thal", "RBot", or something else?

### Step 2: Count Active Connections

On your TeamSpeak server, check how many query clients are connected:

```bash
# Via ServerQuery:
clientlist -uid -ip

# Filter for query clients (client_type=1):
clientlist | grep client_type=1
```

You should see:
- How many query clients are connected
- Their nicknames
- Their IP addresses

### Step 3: Match IPs to Sources

Check where these connections are coming from:

```bash
# Get query client IPs:
clientlist -ip | grep client_type=1

# Check if they're from:
# - Your web server IP → Website
# - Other IPs → Other applications
# - Same IP but multiple connections → Broken pooling
```

### Step 4: Check for Multiple Website Installations

```bash
# Find all ts-website installations:
find / -name "config.local.php" -path "*/ts-website/*" 2>/dev/null

# Or search for the TeamSpeakUtils class:
find / -name "TeamSpeakUtils.php" 2>/dev/null
```

---

## 🛠️ Solutions

### Solution 1: If Multiple Websites → Consolidate or Use Separate Credentials

**Option A: Use ONE website instance**
- Remove duplicate installations
- Point all domains to single installation
- Use nginx/apache reverse proxy if needed

**Option B: Give each website different query credentials**
```sql
# On TeamSpeak server, create separate query users:
serverqueryadd query_username=website1 query_password=pass1
serverqueryadd query_username=website2 query_password=pass2

# Then configure each website with its own credentials
```

### Solution 2: Fix Connection Pooling

The website SHOULD reuse connections within the same PHP process. Check:

**A. PHP-FPM Configuration**
```ini
; /etc/php-fpm.d/www.conf or similar
pm.max_children = 10  ; Reduce if too high
pm.max_requests = 500 ; Force worker restart after N requests
```

**B. Add Connection Cleanup**

Create a file `/workspace/src/private/php/cleanup-connections.php`:

```php
<?php
// Add this to your shutdown handler
register_shutdown_function(function() {
    if (class_exists('\Wruczek\TSWebsite\Utils\TeamSpeakUtils')) {
        try {
            \Wruczek\TSWebsite\Utils\TeamSpeakUtils::i()->reset();
        } catch (\Exception $e) {
            // Ignore errors during shutdown
        }
    }
});
```

Then include it in `load.php`.

### Solution 3: Increase Query Client Limit

If you legitimately need multiple query connections, increase the limit:

**On TeamSpeak Server:**
```ini
# ts3server.ini or via ServerQuery:
instanceedit serverinstance_serverquery_max_connections_per_ip=20
```

### Solution 4: Identify and Remove Rogue Applications

**Find what's setting these nicknames:**

```bash
# Search your server for "thal" or "RBot":
grep -r "thal" /var/www /home /opt 2>/dev/null
grep -r "RBot" /var/www /home /opt 2>/dev/null

# Check running processes:
ps aux | grep -i "thal\|rbot"

# Check cron jobs:
crontab -l
ls -la /etc/cron.d/
```

---

## 🎯 Most Likely Scenario

Based on the names you're seeing, I suspect:

1. **"thal1", "thal2"** = One application (maybe old website installation?)
2. **"RBot", "RBot0-8"** = Different application (music bot? admin bot?)
3. **Your current website** = Should be showing as whatever `query_nickname` is set to

**To confirm:**
```sql
-- Check your website's configured nickname:
SELECT value FROM config WHERE identifier = 'query_nickname';
```

If it's NOT "thal" or "RBot", then those are definitely other applications.

---

## 🔍 Quick Diagnostic Command

Run this on your TeamSpeak server:

```bash
# Via telnet or ServerQuery client:
telnet localhost 10011
# Login with your query credentials
login serveradmin password
use port=9987

# List all query clients:
clientlist -uid -ip | grep client_type=1

# You'll see:
# clid=X client_nickname=thal1 connection_client_ip=1.2.3.4
# clid=Y client_nickname=RBot connection_client_ip=1.2.3.5
```

This shows:
- How many query clients
- Their nicknames
- Where they're connecting from

---

## ⚡ Quick Fix

### Temporary: Limit Query Connections

On TeamSpeak server:

```bash
# Via ServerQuery or ts3server.ini:
instanceedit serverinstance_serverquery_max_connections_per_ip=3

# This limits each IP to 3 simultaneous query connections
# Will prevent spam but won't fix root cause
```

### Permanent: Find and Fix Root Cause

1. **Identify sources:**
   ```bash
   # On TS server:
   clientlist -uid -ip | grep client_type=1 > /tmp/query_clients.txt
   cat /tmp/query_clients.txt
   ```

2. **Match to applications:**
   - Check each IP
   - Identify what's running there
   - Check query_nickname in database

3. **Remove/fix:**
   - Shut down duplicate websites
   - Fix broken bots
   - Remove unused applications

---

## 🧪 Test: Is Your Website Causing This?

### Test 1: Stop Your Website Temporarily

```bash
# Stop web server:
systemctl stop nginx  # or apache2 or php-fpm

# Wait 2 minutes

# Check TS logs - are "thal1" and "RBot" still connecting?
# - YES → They're from other applications
# - NO → Your website is creating multiple connections
```

### Test 2: Check Website Query Nickname

```bash
# SSH to your web server:
cd /path/to/ts-website/src

# Check config:
grep -r "thal\|RBot" .

# Check database:
mysql -e "SELECT value FROM config WHERE identifier = 'query_nickname';"
```

If your website is NOT configured with "thal" or "RBot" nicknames, those connections are from other sources.

---

## 📊 Expected vs Actual

### Expected (Healthy):
```
Query Clients Connected: 1-2
Nicknames: "TS-website", "TS-website3" (your website)
Connections: Stable, long-lived
Frequency: Every 120 seconds (cache refresh)
```

### What You Have (Unhealthy):
```
Query Clients Connected: 10-20+
Nicknames: "thal1", "thal2", "RBot", "RBot0-8"
Connections: Rapid connect/disconnect  
Frequency: Every 1-3 seconds (way too fast)
```

---

## ✅ Action Plan

### Phase 1: Identify (15 minutes)
1. [ ] Check your website's `query_nickname` in database
2. [ ] List all connected query clients on TS server
3. [ ] Match IP addresses to servers/applications
4. [ ] Search for "thal" and "RBot" in your file systems

### Phase 2: Isolate (10 minutes)
5. [ ] Temporarily stop your website
6. [ ] Check if connections stop
7. [ ] Identify which connections are from which app

### Phase 3: Fix (30 minutes)
8. [ ] Remove duplicate website installations
9. [ ] Fix/remove rogue bots
10. [ ] Configure proper connection limits
11. [ ] Test with single website instance

### Phase 4: Verify (15 minutes)
12. [ ] Monitor TS logs for 15 minutes
13. [ ] Should see only 1-2 query clients
14. [ ] Connections should be stable
15. [ ] No rapid connect/disconnect

---

## 🔒 Security Note

Multiple query clients with different names suggest:
- **Query credentials leaked?** - Change them!
- **Old installations left running?** - Remove them!
- **Compromised bots?** - Update/remove them!

**Recommended:**
```bash
# On TeamSpeak server, change query password:
serverquerypassword newpassword

# Update your website's config with new password
# Remove/update any other applications
```

---

## 💡 Key Insight

Your **cache settings are perfect** (120s). The problem is **too many separate applications/instances** all using ServerQuery to connect to your TS server.

**This is NOT a caching issue** - it's an **infrastructure/configuration issue**.

You need to:
1. Find all applications using query credentials
2. Consolidate or remove duplicates  
3. Fix any broken connection pooling
4. Monitor to ensure only necessary connections

---

## 📞 Next Steps

Reply with the output of:

```bash
# 1. Your website's configured nickname:
SELECT value FROM config WHERE identifier = 'query_nickname';

# 2. Connected query clients (on TS server):
clientlist -uid -ip | grep client_type=1

# 3. Are you running multiple websites?
find /var/www -type d -name "ts-website" 2>/dev/null
```

This will help identify exactly what's connecting and why.
