# TeamSpeak Website Query Analysis Report

## Issue: Excessive Query Requests

### Current Configuration

**Cache Settings (seconds):**
- `cache_serverinfo`: 10
- `cache_clientlist`: 15  
- `cache_channelist`: 60
- `cache_banlist`: 60
- `cache_servergroups`: 60
- `cache_channelgroups`: 60

**JavaScript Polling:**
- **status.js**: Polls every 10 seconds → `api/getstatus.php`
- **viewer.js**: Auto-refresh DISABLED (commented out) ✓

### The Problem

With the current configuration:
- **1 user with 1 tab open**: 6 requests per minute (1 every 10 seconds)
- **5 users with 1 tab each**: 30 requests per minute
- **10 users with 2 tabs each**: 120 requests per minute

When cache expires, each request queries the TeamSpeak server directly, causing:
- High load on TeamSpeak server
- Rapid bot connect/disconnect events being constantly polled
- Query client spam visible in TeamSpeak logs
- Potential rate limiting or connection issues

### Evidence from Logs

Your logs show rapid bot connections (RBot, thal1, etc.) connecting/disconnecting multiple times per second. The website constantly polling this data creates a feedback loop.

---

## Recommended Solutions

### Solution 1: Increase Cache Times (RECOMMENDED)

**For low-traffic sites (< 50 users online):**
```sql
-- Server info: increase from 10s to 30s
UPDATE config SET value = '30' WHERE identifier = 'cache_serverinfo';

-- Client list: increase from 15s to 30s  
UPDATE config SET value = '30' WHERE identifier = 'cache_clientlist';

-- Channel list: already good at 60s
```

**For high-traffic sites (50+ users online):**
```sql
-- Server info: increase to 20s
UPDATE config SET value = '20' WHERE identifier = 'cache_serverinfo';

-- Client list: increase to 25s
UPDATE config SET value = '25' WHERE identifier = 'cache_clientlist';
```

**Benefits:**
- Reduces queries by 66-75%
- Still provides near-real-time updates
- No code changes needed

---

### Solution 2: Increase Polling Interval

Edit `/workspace/src/js/status.js` line 7:

**Current:**
```javascript
}, 10 * 1000)  // 10 seconds
```

**Recommended:**
```javascript
}, 30 * 1000)  // 30 seconds
```

**Benefits:**
- Reduces AJAX requests by 66%
- Combined with cache increase = 90% reduction in queries

---

### Solution 3: Disable Auto-Refresh (for static servers)

If your TeamSpeak server has relatively stable user counts, consider disabling auto-refresh entirely. Users can manually refresh the page when needed.

Comment out the interval in `status.js`:

```javascript
// DISABLED - Users can refresh page manually
// var intervalId = setInterval(function() {
//     checkStatus()
// }, 10 * 1000)
```

---

### Solution 4: Implement Conditional Polling

Only poll when the page is visible/active:

```javascript
var intervalId;

function startPolling() {
    intervalId = setInterval(function() {
        checkStatus()
    }, 30 * 1000)
}

function stopPolling() {
    clearInterval(intervalId)
}

// Poll only when page is visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopPolling()
    } else {
        checkStatus()  // Immediate update
        startPolling()
    }
})

// Start initially
if (!document.hidden) {
    startPolling()
}
```

---

## Implementation Plan

### Quick Fix (5 minutes)

1. **Increase cache times via database**:
   ```bash
   # Connect to your database and run:
   UPDATE config SET value = '30' WHERE identifier = 'cache_serverinfo';
   UPDATE config SET value = '30' WHERE identifier = 'cache_clientlist';
   ```

2. **Increase polling interval**:
   - Edit `src/js/status.js` line 7
   - Change `10 * 1000` to `30 * 1000`

**Expected Result**: 75-90% reduction in queries

### Optimal Fix (15 minutes)

Apply Quick Fix + implement conditional polling (Solution 4)

**Expected Result**: 90-95% reduction in queries

---

## Monitoring

After applying fixes, monitor:

1. **TeamSpeak query client connections**: Should see significantly fewer
2. **Server load**: Check if CPU/network usage decreases
3. **User experience**: Ensure updates are still frequent enough

---

## Additional Recommendations

1. **Consider WebSockets**: For real-time updates without polling
2. **Implement rate limiting**: Protect API endpoints from abuse
3. **Add connection pooling**: Reuse TeamSpeak query connections
4. **Monitor bot activity**: The rapid RBot connections suggest possible issues on TS server itself

---

## Files to Modify

- `/workspace/src/js/status.js` - Polling interval
- Database `config` table - Cache times
- `/workspace/src/js/viewer.js` - Keep auto-refresh commented out!

---

## Testing

After changes:
1. Open browser DevTools → Network tab
2. Watch AJAX requests to `api/getstatus.php`
3. Verify requests occur at new interval (e.g., every 30s instead of 10s)
4. Check TeamSpeak logs for reduced query client activity
