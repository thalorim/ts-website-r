# How to Change Admin CLDBID

The News Channel Display admin panel is restricted to CLDBID **116527** by default.

## Finding Your CLDBID

### Method 1: From TeamSpeak Client
1. Right-click your name in TeamSpeak
2. Select "View Database ID"
3. Your CLDBID will be displayed (e.g., 116527)

### Method 2: From Database
```sql
-- Check the profiles table
SELECT cldbid, nickname FROM profiles WHERE nickname = 'YourNickname';
```

### Method 3: From PHP
Create a temporary test file:
```php
<?php
require_once __DIR__ . '/src/private/php/load.php';
use Wruczek\TSWebsite\Auth;

if (Auth::isLoggedIn()) {
    echo "Your CLDBID: " . Auth::getCldbid();
} else {
    echo "You must be logged in";
}
```

## Changing the Allowed CLDBID

Edit the file: `/src/admin/news-channel-display.php`

Find this section (around line 17-22):

```php
// Simple admin check - only CLDBID 116527 can access
// You can modify this to suit your needs
$userCldbid = Auth::getCldbid();
if ($userCldbid !== 116527) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page. Only CLDBID 116527 can access the admin panel.");
    exit;
}
```

### Option 1: Change to Your CLDBID
Replace `116527` with your CLDBID:

```php
$userCldbid = Auth::getCldbid();
if ($userCldbid !== YOUR_CLDBID_HERE) {  // e.g., 123456
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page. Only CLDBID YOUR_CLDBID_HERE can access the admin panel.");
    exit;
}
```

### Option 2: Allow Multiple CLDBIDs
Allow multiple admin users:

```php
$userCldbid = Auth::getCldbid();
$allowedCldbids = [116527, 123456, 789012];  // Add your CLDBIDs here

if (!in_array($userCldbid, $allowedCldbids)) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

### Option 3: Use Config File
Store allowed CLDBIDs in config:

In `src/admin/news-channel-display.php`:
```php
$userCldbid = Auth::getCldbid();
$allowedCldbids = Config::get("news_channel_admin_cldbids", [116527]);

if (!in_array($userCldbid, $allowedCldbids)) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

In `src/private/config.local.php`:
```php
$config['news_channel_admin_cldbids'] = [116527, 123456, 789012];
```

### Option 4: Use Server Group Check
Allow access based on TeamSpeak server group:

```php
use Wruczek\TSWebsite\Utils\TeamSpeakUtils;

$userCldbid = Auth::getCldbid();

// Check if user has admin server group (e.g., group ID 6)
$hasPermission = false;
try {
    $ts = TeamSpeakUtils::i();
    if ($ts->checkTSConnection()) {
        $server = $ts->getTSNodeServer();
        $client = $server->clientGetByDbid($userCldbid);
        $info = $client->getInfo(true);
        $groups = explode(',', (string) $info['client_servergroups']);
        
        // Allow if user has server group 6 (admin group)
        if (in_array('6', $groups)) {
            $hasPermission = true;
        }
    }
} catch (\Exception $e) {
    // Handle error
}

if (!$hasPermission) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

## Testing Your Change

1. Edit the file with your CLDBID
2. Save the file
3. Log in to your website
4. Navigate to: `https://your-site.com/admin/news-channel-display.php`
5. You should now have access

## Security Note

- Keep your CLDBID private
- Don't share admin access with untrusted users
- Review access logs regularly
- Consider using server group-based permissions for better security

## Reverting to Default

To revert back to the default (CLDBID 116527):

```php
$userCldbid = Auth::getCldbid();
if ($userCldbid !== 116527) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page. Only CLDBID 116527 can access the admin panel.");
    exit;
}
```
