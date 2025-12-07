# Admin Panel Access - Fix Summary

## Problem
The admin panel was hardcoded to only allow access to a specific UID: `"Jv/d1+pX7/q343RrIMPTTVpob+U="`, preventing other users from accessing the admin panel.

## Solution
Implemented a flexible, configuration-based admin authentication system that supports multiple methods of granting access.

---

## Changes Made

### 1. New `Auth::isAdmin()` Method
**File:** `/workspace/src/private/php/Auth.php`

Added a new static method that checks if the current user is an admin based on configuration:
- Checks `admin_uids` (TeamSpeak Unique IDs)
- Checks `admin_cldbids` (Client Database IDs)
- Checks `admin_groups` (Server Group IDs)

### 2. Updated Admin Panel
**File:** `/workspace/src/admin/index.php`

Replaced hardcoded UID check with `Auth::isAdmin()` method.

### 3. Updated API Endpoints
**Files:**
- `/workspace/src/api/save-config.php`
- `/workspace/src/api/save-news.php`
- `/workspace/src/api/save-rules.php`

All admin API endpoints now use `Auth::isAdmin()` instead of hardcoded checks.

### 4. Created Documentation
**File:** `/workspace/ADMIN_ACCESS_CONFIGURATION.md`

Complete guide on how to configure admin access using all three methods.

### 5. Created Helper Endpoint
**File:** `/workspace/src/api/whoami.php`

New API endpoint to help users find their UID, CLDBID, and server groups.

---

## Quick Setup Guide

### Step 1: Find Your Credentials

Visit this URL while logged in:
```
https://your-website.com/api/whoami.php
```

This will show you:
- Your UID (Unique Identifier)
- Your CLDBID (Client Database ID)
- Your Server Groups
- Example configuration

### Step 2: Add Admin Configuration

Choose ONE of these methods:

#### Option A: Add to Database (Recommended for first-time setup)

**For SQLite:**
```sql
INSERT OR REPLACE INTO config (key, value) 
VALUES ('admin_uids', '["YOUR_UID_HERE="]');
```

**For MySQL:**
```sql
INSERT INTO config (config_key, value) 
VALUES ('admin_uids', '["YOUR_UID_HERE="]')
ON DUPLICATE KEY UPDATE value = '["YOUR_UID_HERE="]';
```

#### Option B: Add to Config File

Edit `private/config.local.php`:
```php
<?php
return [
    'admin_uids' => ['YOUR_UID_HERE='],
    // OR
    'admin_cldbids' => [YOUR_CLDBID_HERE],
    // OR
    'admin_groups' => [9, 10], // Server group IDs
];
```

#### Option C: Use Admin Panel (if you already have access)

Once you can access the admin panel, you can add more admins through the database configuration interface.

---

## Configuration Examples

### Example 1: Single Admin by UID
```json
{
  "admin_uids": ["Jv/d1+pX7/q343RrIMPTTVpob+U="]
}
```

### Example 2: Multiple Admins by CLDBID
```json
{
  "admin_cldbids": [116527, 123456, 789012]
}
```

### Example 3: Admin Groups (Best Practice)
```json
{
  "admin_groups": [9, 10]
}
```
*Anyone in server groups 9 or 10 will have admin access*

### Example 4: Multiple Methods (Most Flexible)
```json
{
  "admin_uids": ["Jv/d1+pX7/q343RrIMPTTVpob+U="],
  "admin_cldbids": [116527],
  "admin_groups": [9, 10]
}
```

---

## Testing Your Setup

1. **Log in** to the website using TeamSpeak verification
2. **Visit** `/api/whoami.php` to see if `is_admin` is `true`
3. **Try accessing** `/admin/` or `/admin/index.php`
4. **Check for errors:**
   - **401 Unauthorized** = Not logged in
   - **403 Forbidden** = Logged in but not an admin
   - **Success** = You're an admin! 🎉

---

## Security Features

✅ **Multiple Authentication Methods**: Choose what works best for your setup  
✅ **Separate Login Check**: Distinguishes between not logged in (401) vs. not authorized (403)  
✅ **No Hardcoded Values**: All admin access is configuration-based  
✅ **Flexible Management**: Use server groups for easy admin management  
✅ **Backward Compatible**: Existing installations just need to add configuration  

---

## Troubleshooting

**Problem:** "403 Forbidden" when accessing admin panel

**Solution:**
1. Visit `/api/whoami.php` to get your credentials
2. Add your UID, CLDBID, or server groups to the configuration
3. Clear browser cache and try again

**Problem:** "401 Unauthorized"

**Solution:**
1. Log in to the website first
2. Make sure you're using the TeamSpeak login system
3. Check that your session hasn't expired

**Problem:** whoami.php shows `is_admin: false`

**Solution:**
1. Check that your configuration was saved correctly in the database
2. Verify the JSON syntax (no trailing commas, proper quotes)
3. Make sure you're checking the right database table

**Problem:** Still can't access after configuration

**Solution:**
1. Check PHP error logs for detailed error messages
2. Verify database connection is working
3. Ensure the `config` table exists in your database
4. Try restarting your web server

---

## For Developers

The `Auth::isAdmin()` method is now available throughout the codebase:

```php
if (Auth::isAdmin()) {
    // User has admin access
    // Show admin features, allow admin actions, etc.
}
```

You can use this to:
- Show/hide admin-only UI elements
- Protect admin-only endpoints
- Add conditional admin features
- Create custom admin tools

---

## Next Steps

1. ✅ Configure your admin access using one of the methods above
2. ✅ Test that you can access `/admin/`
3. ✅ Add other administrators if needed
4. ✅ Review the full documentation in `ADMIN_ACCESS_CONFIGURATION.md`
5. ✅ Set up proper HTTPS in production for security

Need help? Check the detailed guide in `ADMIN_ACCESS_CONFIGURATION.md`
