# Admin Access Quick Fix Guide

You're getting a "forbidden" error because you need to configure admin access first.

## 🚀 Quick Solution (Choose ONE method)

### Method 1: Automatic Setup (Easiest)

1. **Make sure you're logged in** to the website (TeamSpeak verification)

2. **Visit this URL in your browser:**
   ```
   http://your-website.com/api/setup-admin.php
   ```

3. **You'll see a success message** with admin access granted

4. **IMPORTANT: Delete the file** `/src/api/setup-admin.php` after use for security

5. **Access admin panel:** Go to `http://your-website.com/admin/`

---

### Method 2: Debug & Manual Setup

1. **Check your status:**
   ```
   http://your-website.com/api/admin-debug.php
   ```

2. **Copy the SQL query shown** in the output

3. **Run the SQL query** on your database

4. **Refresh and verify** by visiting the debug page again

---

### Method 3: Direct Database (If not logged in)

If you can't log in or the above methods don't work:

#### SQLite Database:
```sql
-- Replace YOUR_UID_HERE with your TeamSpeak Unique ID
INSERT OR REPLACE INTO config (key, value) 
VALUES ('admin_uids', '["YOUR_UID_HERE"]');
```

#### MySQL Database:
```sql
-- Replace YOUR_UID_HERE with your TeamSpeak Unique ID
INSERT INTO config (config_key, value) 
VALUES ('admin_uids', '["YOUR_UID_HERE"]')
ON DUPLICATE KEY UPDATE value = '["YOUR_UID_HERE"]';
```

**To find your TeamSpeak UID:**
1. Open TeamSpeak client
2. Connect to your server
3. Right-click your name → "Client Info"
4. Copy the "Unique ID" (looks like: `Jv/d1+pX7/q343RrIMPTTVpob+U=`)

---

### Method 4: Config File (Alternative)

Edit `/workspace/src/private/config.local.php`:

```php
<?php
return [
    'admin_uids' => ['YOUR_UID_HERE'],
    // Example: 'admin_uids' => ['Jv/d1+pX7/q343RrIMPTTVpob+U='],
];
```

---

## 🔍 Troubleshooting

### Error: "forbidden"
**Cause:** You're logged in but not configured as admin  
**Fix:** Use Method 1 or Method 2 above

### Error: "unauthorized"  
**Cause:** You're not logged in  
**Fix:** Log in to the website first using TeamSpeak verification

### Still getting errors?

1. **Check PHP error logs** for detailed errors
2. **Verify database connection** is working
3. **Check table name:** 
   - SQLite uses `config` table with `key` column
   - MySQL might use `config` or different table names - check your schema
4. **Clear cache:** Delete files in `/cache/` directory
5. **Restart web server** (Apache/Nginx)

---

## 📋 Quick Command Reference

### Check your current status:
```bash
# Visit in browser or use curl:
curl http://your-website.com/api/admin-debug.php
```

### Grant admin access automatically:
```bash
# Visit in browser while logged in:
http://your-website.com/api/setup-admin.php
```

### Verify you have admin access:
```bash
# Visit in browser:
http://your-website.com/api/whoami.php
# Look for: "is_admin": true
```

---

## ⚠️ Security Notes

1. **Delete setup-admin.php** after use - it's a security risk if left accessible
2. **Use HTTPS** in production to protect admin sessions
3. **Keep admin list minimal** - only grant access to trusted users
4. **Regularly audit** who has admin access

---

## 📞 Need More Help?

1. Run the debug tool: `/api/admin-debug.php`
2. Check the full documentation: `ADMIN_ACCESS_CONFIGURATION.md`
3. Review the changes summary: `ADMIN_PANEL_FIX_SUMMARY.md`
4. Check PHP error logs for detailed error messages

---

## ✅ Success Checklist

- [ ] Logged in to the website
- [ ] Ran `/api/setup-admin.php` OR added config manually
- [ ] Verified admin access with `/api/admin-debug.php`
- [ ] Can access `/admin/` without errors
- [ ] Deleted `/src/api/setup-admin.php` for security

Once all checked, you're ready to use the admin panel! 🎉
