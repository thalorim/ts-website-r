# Admin Access Configuration

## Current Configuration

The Status Display admin interface (`status-display.php`) currently uses the same authentication as the main admin panel: **only users with CLDBID 3 can access it**.

This matches the default TS-website admin panel authentication.

## How to Change Admin Access

You have several options to customize who can access the status display admin interface:

### Option 1: Allow All Logged-In Users (No Restriction)

Edit `src/admin/status-display.php`, find lines 12-19 and replace with:

```php
// Check if user is logged in
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// No additional restriction - all logged in users can access
```

### Option 2: Multiple Allowed CLDBIDs

Edit `src/admin/status-display.php`, find lines 12-19 and replace with:

```php
// Check if user is logged in
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Allow specific CLDBIDs
$allowedCldbids = [3, 116527, 123456]; // Add your CLDBIDs here
$userCldbid = Auth::getCldbid();

if (!in_array($userCldbid, $allowedCldbids)) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

### Option 3: Check by Server Groups (Advanced)

If your Auth class supports server groups, edit `src/admin/status-display.php`:

```php
// Check if user is logged in
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Check if user has admin server group
$userCldbid = Auth::getCldbid();

// Get user's server groups from TeamSpeak
try {
    $tsServer = TeamSpeakUtils::i()->getTSNodeServer();
    $userGroups = $tsServer->clientGetServerGroupsByDbid($userCldbid);
    $userGroupIds = array_keys($userGroups);
} catch (Exception $e) {
    $userGroupIds = [];
}

// Define admin groups
$adminGroupIds = [6, 7, 8]; // Replace with your admin group IDs

$isAdmin = false;
foreach ($adminGroupIds as $adminGroup) {
    if (in_array($adminGroup, $userGroupIds)) {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

### Option 4: Use Database Configuration

Store allowed CLDBIDs in the database:

```sql
-- Create table for admin users
CREATE TABLE ts_admin_users (
  cldbid INT(11) NOT NULL,
  can_manage_status_display TINYINT(1) DEFAULT 1,
  PRIMARY KEY (cldbid)
);

-- Add admin users
INSERT INTO ts_admin_users (cldbid, can_manage_status_display) VALUES
(3, 1),
(116527, 1);
```

Then in `src/admin/status-display.php`:

```php
// Check if user is logged in
if (!Auth::isLoggedIn()) {
    TemplateUtils::i()->renderErrorTemplate("401", "Unauthorized", "Please log in to access the admin panel.");
    exit;
}

// Check database for permission
$db = DatabaseUtils::i()->getDb();
$userCldbid = Auth::getCldbid();

$hasPermission = $db->has("admin_users", [
    "cldbid" => $userCldbid,
    "can_manage_status_display" => 1
]);

if (!$hasPermission) {
    TemplateUtils::i()->renderErrorTemplate("403", "Forbidden", "You don't have permission to access this page.");
    exit;
}
```

## Find Your CLDBID

To find your CLDBID:

1. **Via TeamSpeak Client**:
   - Connect to your TeamSpeak server
   - Right-click your name → "Client Info"
   - Look for "Database ID"

2. **Via Website**:
   - Log in to your TS-website
   - Your CLDBID is shown in your profile URL: `profile.php?cldbid=XXXXX`

3. **Via Database**:
   ```sql
   SELECT cldbid, nickname FROM ts_profiles ORDER BY cldbid;
   ```

## Current Default

Currently, the Status Display admin uses: **CLDBID 3 only** (same as main admin panel)

If you want to access it with a different CLDBID, use one of the options above.

## Using the Simple Interface (No Auth Required)

If you just want to test or don't care about authentication, use the simple interface:

`http://web.reape.rs/admin/status-display-simple.php`

This interface shows all errors and doesn't require authentication (useful for debugging).

---

**Note**: For production use, always implement proper authentication to prevent unauthorized access to admin functions!
