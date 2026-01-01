# 🤖 Bot Connection Spam - Separate Issue Analysis

## Important: This is NOT a Website Issue

You're seeing **actual bot clients** connecting to your TeamSpeak server. This is **different** from the website query issue we fixed.

## Your Cache Settings ✅

```
cache_serverinfo:    120s ✓ EXCELLENT
cache_clientlist:    120s ✓ EXCELLENT  
cache_channelist:    120s ✓ EXCELLENT
```

**The website fix is working perfectly.** But these bot connections are a **TeamSpeak server security issue**.

---

## 🔍 What You're Seeing

### Log Pattern Analysis:
```
<21:40:10> "thal1" connected         ← Real client connection
<21:40:10> "thal1" disconnected      ← Not a query client
<21:40:10> "thal1" connected         ← Connecting every second
<21:40:12> "thal1" connected         ← This is too fast for website
<21:40:15> "thal1" connected         
<21:40:42> "RBot" connected          ← Different bot
<21:40:42> "RBot" is now known as "RBot0"  ← Name changing
<21:41:07> "thal1" connected         ← Pattern repeats
```

### Key Observations:
1. **Frequency:** Every 1-3 seconds (way too fast to be website)
2. **Pattern:** Connect → Disconnect → Repeat immediately
3. **Clients:** "thal1", "thal2", "RBot", "RBot0-8", "Unknown"
4. **Channel:** All connecting to `[cspacer]-==| 🏠 |==-`
5. **Behavior:** Disconnecting immediately after connecting

---

## 🎯 What This Means

### This is NOT:
- ❌ Website query client connections
- ❌ Caused by cache settings
- ❌ Related to JavaScript polling
- ❌ Something the website fix can solve

### This IS:
- ✅ Actual bot clients connecting to TS
- ✅ Possibly malicious attack/flood
- ✅ OR legitimate bots malfunctioning
- ✅ Needs to be addressed at TS server level

---

## 🕵️ Identifying the Bots

### Step 1: Check What These Bots Are

In your TeamSpeak server, check:

```
ServerQuery Commands:
servergroupclientlist sgid=6  # List server admin group
clientdblist                  # List all database clients
```

Look for:
- Client database IDs of "thal1", "RBot"
- When they were first seen
- Associated server groups
- IP addresses

### Step 2: Check if They're YOUR Bots

Common legitimate bots that might malfunction:
- **Music bots:** SinusBot, TS3MusicBot
- **Admin bots:** ModBot, AdminBot
- **Status bots:** Server status updaters
- **Channel bots:** Auto-channel managers

**Question to ask yourself:**
- Do you recognize these bot names?
- Did you install any bots recently?
- Are "thal1" or "RBot" supposed to be on your server?

---

## 🛡️ Solutions

### Solution 1: Ban by Client Database ID (Permanent)

If these are malicious/unwanted bots:

```
# In TeamSpeak server console or ServerQuery:
banclient clid=[CLIENT_ID] time=0 banreason="Bot spam"

# Or ban by unique ID:
banadd uid=[UNIQUE_ID] time=0 banreason="Bot spam"
```

**To find their IDs:**
1. Right-click client in TS server
2. "Client Info"
3. Note the Database ID or Unique ID
4. Apply ban

### Solution 2: Ban by IP Address

If they're all from the same IP:

```
banadd ip=[IP_ADDRESS] time=0 banreason="Bot flood"
```

**To find IPs:**
```
clientinfo clid=[CLIENT_ID]
# Look for connection_client_ip
```

### Solution 3: Increase Anti-Flood Protection

In your TeamSpeak server configuration (`ts3server.ini`):

```ini
# Add or modify these settings:
serverinstance_serverquery_flood_commands=10
serverinstance_serverquery_flood_time=3
serverinstance_serverquery_ban_time=600

# Client connection flood protection:
virtualserver_antiflood_points_tick_reduce=5
virtualserver_antiflood_points_needed_warning=25
virtualserver_antiflood_points_needed_kick=35
virtualserver_antiflood_points_needed_ip_block=50
```

Restart TS server after changes.

### Solution 4: Block at Firewall Level

If bots are from specific IPs:

```bash
# Linux iptables:
iptables -A INPUT -s [BOT_IP_ADDRESS] -j DROP

# Or using fail2ban (recommended):
# Add TS3 jail to detect and block rapid connections
```

---

## 🔍 Diagnostic Steps

### 1. Enable Detailed Logging

In TeamSpeak server, enable detailed logging:

```
# ts3server.ini
logquerycommands=1
query_timeout=300
```

This will show exactly what these clients are doing.

### 2. Monitor in Real-Time

```bash
# Watch TS server log:
tail -f /path/to/teamspeak/logs/ts3server_*.log | grep -E "thal|RBot"

# Count connections per minute:
tail -f /path/to/teamspeak/logs/ts3server_*.log | grep "connected to channel" | pgrep -c
```

### 3. Check for Patterns

Look for:
- Same IP addresses
- Same connection times
- Same unique IDs
- Specific channels being targeted

---

## 🎯 Most Likely Scenarios

### Scenario 1: Malicious Bot Attack
**Signs:**
- Random/unknown bot names
- Connecting from external IPs
- No legitimate purpose
- High frequency (multiple per second)

**Action:** Ban by IP and/or unique ID

### Scenario 2: Misconfigured Legitimate Bot
**Signs:**
- Recognizable name (your bot)
- Connecting from your server's IP
- Recently installed or updated
- Configuration error causing reconnect loop

**Action:** Fix bot configuration or restart bot service

### Scenario 3: Channel Spacer Script
**Signs:**
- Connecting to spacer channels (`[cspacer]`)
- Short-lived connections
- Might be trying to update channel info

**Action:** Check if you have any channel updater scripts running

### Scenario 4: DDoS/Flood Attack
**Signs:**
- Multiple different names
- Very high frequency
- Different IPs or same IP
- Intentional server disruption

**Action:** Enable anti-flood, ban IPs, contact your hosting provider

---

## 🔧 Quick Fix Commands

### Check Who's Online Right Now:
```bash
# Via ServerQuery:
clientlist

# Look for thal1, RBot in the output
```

### Ban Specific Client:
```bash
# Get client ID first:
clientlist | grep thal

# Then ban:
banclient clid=[ID] time=0 banreason="Spam bot"
```

### List All Bans:
```bash
banlist
```

### Remove Ban (if needed):
```bash
bandel banid=[BAN_ID]
```

---

## 🌐 Website vs. These Bots

### Your Website (FIXED ✅):
- Connects as ServerQuery client
- Frequency: Every 120 seconds (excellent!)
- Purpose: Fetch server info for website
- Impact: Minimal after fix
- Shows in logs: Rarely (only on cache miss)

### These Bots (SEPARATE ISSUE ⚠️):
- Connect as regular clients
- Frequency: Every 1-3 seconds (spam!)
- Purpose: Unknown/malicious
- Impact: High (log spam, potential DoS)
- Shows in logs: Constantly (your screenshots)

---

## 📊 Verification Steps

### 1. Verify Website is NOT Causing This

Check your website debug dashboard:
```
http://your-site.com/api/debug-cache-stats.php
```

Should show:
- Cache: 120s (GOOD ✓)
- Queries per minute: Very low
- Fresh caches serving most requests

If yes → Website is fine, these bots are separate issue ✓

### 2. Verify Bots are External

Check if these clients are:
- Query clients (ServerQuery): NO → They're regular clients
- From your server IP: Check connection_client_ip
- Part of website: NO → Website doesn't create regular client connections

### 3. Check Bot Purpose

Try to identify what "thal" and "RBot" are:
- Google: "teamspeak thal1 bot"
- Google: "teamspeak RBot"
- Check your installed bots/scripts
- Ask your server admins

---

## 🚨 Immediate Actions

### Priority 1 (Do Now):
1. **Identify the bots:**
   - Check client info in TS server
   - Note IP addresses
   - Check if they're yours

2. **Temporary ban:**
   - Ban by client ID or IP
   - Monitor if they return with different IDs

3. **Enable anti-flood:**
   - Update ts3server.ini
   - Restart TS server

### Priority 2 (Do Soon):
1. **Permanent solution:**
   - If malicious: Firewall block
   - If legitimate: Fix configuration
   - If unknown: Investigate further

2. **Monitor:**
   - Watch logs for new patterns
   - Check if bans are working
   - Verify normal clients unaffected

---

## 💡 Key Insight

**The website fix you applied IS working!** ✅

Your cache settings are excellent (120s). The website is querying your TS server very infrequently now.

**However**, you have a **separate security issue** with actual bots connecting to your server as regular clients. This needs to be handled at the TeamSpeak server level, not the website level.

---

## 📚 Resources

- TeamSpeak ServerQuery Manual: https://teamspeak.com/en/downloads/#server
- Anti-Flood Configuration: Check TS3 server docs
- Ban Management: Use TS3 server permissions system
- Firewall Rules: Depends on your OS (iptables, firewalld, ufw, etc.)

---

## 🆘 Need Help?

1. **Check your bot software** - If you use any bots, check their logs
2. **Review recent changes** - Did you install/update anything recently?
3. **Contact TS community** - TeamSpeak forums for bot identification
4. **Server logs** - Check what these bots are doing when connected
5. **Hosting provider** - They may see the source IPs and patterns

---

## ✅ Summary

| Aspect | Status |
|--------|--------|
| **Website Query Issue** | ✅ FIXED (cache at 120s) |
| **Website Performance** | ✅ OPTIMAL |
| **These Bot Connections** | ⚠️ SEPARATE ISSUE |
| **Requires** | TS server-level action (bans/firewall) |
| **Not Related To** | Website polling or cache settings |

**Next Step:** Identify and ban these bots at the TeamSpeak server level.

---

**Last Updated:** 2026-01-01  
**Issue Type:** TeamSpeak Server Security  
**Not Related To:** Website query optimization
