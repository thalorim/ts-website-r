# Admin Panel Access Configuration

The admin panel access has been updated to use a flexible, configuration-based authentication system instead of hardcoded values.

## How to Configure Admin Access

You can grant admin access using any of three methods:

### Method 1: By TeamSpeak Unique ID (UID)

Add admin UIDs to your configuration:

```json
{
  "admin_uids": [
    "YourUniqueIdentifierHere=",
    "AnotherAdminUID="
  ]
}
```

**To find your UID:**
1. Connect to your TeamSpeak server
2. Right-click your name → "Client Info"
3. Copy the "Unique ID" value

### Method 2: By Client Database ID (CLDBID)

Add admin CLDBIDs to your configuration:

```json
{
  "admin_cldbids": [
    123456,
    789012
  ]
}
```

**To find your CLDBID:**
1. Log in to the website
2. Your CLDBID is visible in your profile or can be found in the TeamSpeak server logs

### Method 3: By Server Group ID (Recommended)

Grant admin access to specific TeamSpeak server groups:

```json
{
  "admin_groups": [
    9,
    10
  ]
}
```

This method is recommended because:
- Easy to manage multiple admins
- Automatic access when users join/leave admin groups
- Consistent with TeamSpeak permissions

**To find group IDs:**
1. In TeamSpeak Client: Tools → ServerQuery Login
2. Or check your server group settings in the website viewer

## Adding Configuration via Database

If you don't have access to the admin panel yet, you need to add the configuration directly to the database:

### SQLite:

```sql
-- Add your UID
INSERT OR REPLACE INTO config (key, value) 
VALUES ('admin_uids', '["YourUniqueIdentifierHere="]');

-- OR add your CLDBID
INSERT OR REPLACE INTO config (key, value) 
VALUES ('admin_cldbids', '[116527]');

-- OR add admin groups
INSERT OR REPLACE INTO config (key, value) 
VALUES ('admin_groups', '[9, 10]');
```

### MySQL:

```sql
-- Add your UID
INSERT INTO config (config_key, value) 
VALUES ('admin_uids', '["YourUniqueIdentifierHere="]')
ON DUPLICATE KEY UPDATE value = '["YourUniqueIdentifierHere="]';

-- OR add your CLDBID
INSERT INTO config (config_key, value) 
VALUES ('admin_cldbids', '[116527]')
ON DUPLICATE KEY UPDATE value = '[116527]';

-- OR add admin groups
INSERT INTO config (config_key, value) 
VALUES ('admin_groups', '[9, 10]')
ON DUPLICATE KEY UPDATE value = '[9, 10]';
```

## Configuration via Local Config File

You can also add this to `private/config.local.php`:

```php
<?php
return [
    // Method 1: By UID
    'admin_uids' => [
        'YourUniqueIdentifierHere=',
        'AnotherAdminUID=',
    ],
    
    // Method 2: By CLDBID
    'admin_cldbids' => [
        116527,
        789012,
    ],
    
    // Method 3: By Server Groups (recommended)
    'admin_groups' => [
        9,  // Server Admin group
        10, // Web Admin group
    ],
];
```

## Multiple Methods

You can use multiple methods simultaneously. The system checks them in this order:
1. UID-based access
2. CLDBID-based access
3. Server group-based access

A user only needs to match ONE of these criteria to gain admin access.

## Testing Your Configuration

1. Log in to the website with your TeamSpeak account
2. Try to access `/admin/` or `/admin/index.php`
3. If you see a "Forbidden" error, check your configuration

## Troubleshooting

**"401 Unauthorized" Error:**
- You're not logged in to the website
- Solution: Log in using the TeamSpeak verification system

**"403 Forbidden" Error:**
- You're logged in but don't have admin privileges
- Check that your UID/CLDBID/groups are correctly configured
- Verify the JSON syntax is correct (no trailing commas, proper quotes)

**Still Can't Access:**
1. Check your current UID: Look at browser console or session data
2. Verify database configuration was saved correctly
3. Clear the website cache (delete files in `cache/` directory if needed)
4. Check that you're using the correct database table (`config`)

## Security Recommendations

1. **Use server groups** for easier management
2. **Limit admin_uids/admin_cldbids** to trusted users only
3. **Keep your admin list small** - only add users who need full admin access
4. **Regularly audit** who has admin access
5. **Use HTTPS** in production to protect admin sessions

## Need Help?

If you're still having trouble accessing the admin panel:
1. Check the PHP error logs
2. Verify your TeamSpeak connection is working
3. Ensure your database is accessible
4. Contact your system administrator
