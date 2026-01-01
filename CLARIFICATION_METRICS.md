# 📊 Understanding the Metrics - What Numbers Matter?

## ⚠️ Important Clarification

The "10 users" you see in `debug-cache-stats.php` is just an **EXAMPLE/ESTIMATE** for calculating load. It's NOT showing actual users or actual query clients!

---

## 🎯 What to Actually Check

### ✅ CORRECT Metrics to Check:

#### 1. **Query Clients on TeamSpeak Server** (Most Important!)

On your TeamSpeak server, run this command:

```bash
# Via ServerQuery (telnet to port 10011):
clientlist

# Count query clients (client_type=1):
clientlist | grep "client_type=1" | wc -l
```

**What you should see:**
- ❌ **Before fix:** 5-20+ query clients
- ✅ **After fix:** 1-2 query clients maximum

**This is the REAL metric that matters!**

#### 2. **Did You Remove the Duplicate?**

Check if duplicate is still there:

```bash
find /var/www -name "TeamSpeakUtils.php" 2>/dev/null
```

**What you should see:**
- ❌ **Before fix:** 2 paths found
- ✅ **After fix:** 1 path only

```
# BAD (duplicate still exists):
/var/www/web.reape.rs/private/php/Utils/TeamSpeakUtils.php
/var/www/private/php/Utils/TeamSpeakUtils.php

# GOOD (duplicate removed):
/var/www/web.reape.rs/private/php/Utils/TeamSpeakUtils.php
```

#### 3. **Active Connections in Logs**

Watch TeamSpeak logs in real-time:

```bash
tail -f /path/to/teamspeak/logs/ts3server_*.log | grep -E "connected|disconnected"
```

**What you should see:**
- ❌ **Before:** "thal1", "RBot" connecting every 1-3 seconds
- ✅ **After:** Only your `query_nickname` connecting every 120 seconds (when cache expires)

---

## ❌ INCORRECT Metrics (Ignore These):

### 1. "Queries per minute (10 users)" in debug dashboard
This is just a **calculation example**, not real data. The dashboard shows:
- "Queries/min per user" - Formula based on cache settings
- "Queries/min (10 users)" - Example multiplied by 10

**This does NOT mean:**
- ❌ 10 actual users are connected
- ❌ 10 query clients are active
- ❌ Anything about your actual load

### 2. Regular users on TeamSpeak
The number of normal users (people with voice clients) is separate from query clients (bots/website).

---

## 🔍 Step-by-Step Verification

Run these commands to see ACTUAL status:

### Command 1: Check for duplicate installations
```bash
find /var/www -name "TeamSpeakUtils.php" 2>/dev/null | wc -l
```
**Expected:** `1` (if you removed duplicate)  
**If you see:** `2` → You haven't removed the duplicate yet!

### Command 2: Check running PHP processes
```bash
ps aux | grep "web.reape.rs\|/var/www/private" | grep -v grep
```
**Expected:** Only processes from one installation  
**If you see:** Both paths → Both are still running!

### Command 3: Check TeamSpeak query clients RIGHT NOW
```bash
# On TeamSpeak server, run:
echo "login serveradmin YOUR_PASSWORD
use port=9987
clientlist
quit" | telnet localhost 10011 2>/dev/null | grep "client_type=1"
```
**Expected:** 1-2 lines (1-2 query clients)  
**If you see:** 5-20+ lines → Still have multiple connections!

### Command 4: Check if disabled directory exists
```bash
ls -la /var/www/ | grep -i disabled
```
**Expected:** See `private.DISABLED` folder  
**If you DON'T see it:** You haven't disabled the duplicate yet!

---

## 🎯 What "Still Says 10 Users" Might Mean

Please tell me which of these you're seeing:

### A. Debug Dashboard Shows "Queries/min (10 users): X"
**This is NORMAL!** It's just an example calculation. The actual number of users doesn't matter - this is just showing "if you had 10 users, you'd have X queries/minute based on your cache settings."

**What to look at instead:**
- "Queries/min per user" should be LOW (around 0.5-1)
- Cache settings should show "✓ GOOD" or "✓ OK"

### B. TeamSpeak Shows 10 Connected Clients
**Check the client TYPE:**
- Regular users (client_type=0) - Normal people with voice clients
- Query clients (client_type=1) - Bots/website connections ← **THIS is what we care about**

Run this to separate them:
```bash
# On TS server:
clientlist | grep "client_type=0" | wc -l  # Regular users
clientlist | grep "client_type=1" | wc -l  # Query bots (should be 1-2)
```

### C. Logs Show 10 Different Bot Names
If you're still seeing "thal1", "thal2", "RBot", etc. in logs:
**The duplicate installation is STILL RUNNING!**

Check:
```bash
# Is the duplicate still there?
ls -la /var/www/private/

# If yes, it's still connecting to TeamSpeak!
```

---

## ⚡ Quick Test: Has Duplicate Been Removed?

Run this one command:

```bash
find /var/www -type d -name "private" | head -5
```

**If you see:**
```
/var/www/web.reape.rs/private
/var/www/private
```
❌ **Duplicate is STILL THERE!** You need to remove `/var/www/private/`

**If you see:**
```
/var/www/web.reape.rs/private
/var/www/private.DISABLED-20260101
```
✅ **Duplicate is disabled!** Now wait 2-5 minutes for connections to clear.

---

## 📊 What Numbers Should You See After Fix?

### Immediately After Removing Duplicate (0-5 min):
- ✅ 1 ts-website installation found
- ⚠️ Still might see some query clients (old connections draining)
- ⚠️ Logs might still show some activity (existing connections)

### 5-10 Minutes After:
- ✅ Only 1-2 query clients on TS server
- ✅ No more "thal1", "RBot" in logs
- ✅ Connections stable, only every 120 seconds

### 15-30 Minutes After:
- ✅ All metrics normalized
- ✅ Clean logs
- ✅ Problem solved

---

## 🆘 If You Haven't Removed Duplicate Yet

The fix won't work until you ACTUALLY remove the duplicate installation!

### Do This Now:

```bash
# 1. Check if duplicate exists:
ls -la /var/www/private/

# 2. If it exists, disable it:
sudo mv /var/www/private /var/www/private.DISABLED-$(date +%Y%m%d)

# 3. Restart services:
sudo systemctl restart php-fpm nginx

# 4. Wait 5 minutes, then check logs:
tail -f /path/to/teamspeak/logs/ts3server_*.log
```

---

## 💬 Reply With This Info

To help you properly, please run these and send me the output:

```bash
# 1. How many installations?
find /var/www -name "TeamSpeakUtils.php" 2>/dev/null

# 2. Are both directories present?
ls -la /var/www/ | grep -E "web.reape.rs|private"

# 3. What are you seeing that says "10 users"?
# - Is it from debug-cache-stats.php?
# - Is it from TeamSpeak client list?
# - Is it from server logs?
# (Describe or screenshot)
```

This will help me understand what you're actually seeing!
