# Admin Panel Database Configuration

This document describes the comprehensive database configuration system implemented in the admin panel.

## Overview

The admin panel now provides full database-level configuration for various aspects of the website, making it easy to customize without modifying code.

## Access Control

### Admin Panel Access
- **Location:** `src/admin/index.php`
- **Access Method:** Unique ID based authentication
- **Authorized UID:** `Jv/d1+pX7/q343RrIMPTTVpob+U=`
- Users with this Unique ID can access the admin panel and all configuration APIs

### Protected APIs
The following API endpoints require admin authentication:
- `/api/save-config.php` - Save general configuration
- `/api/save-rules.php` - Save rules HTML
- `/api/save-news.php` - Post news articles

## Configuration Options

### 1. Members Page Groups Configuration

**Configuration Key:** `members_groups`  
**Type:** JSON Array  
**Default Value:** `[6, 7]`  
**Description:** Controls which TeamSpeak server groups are displayed on the members page.

**Example:**
```json
[6, 7, 8, 10]
```

**Affected Files:**
- `src/members.php` - Main members page logic
- `src/private/templates/members.latte` - Members page template

**Behavior:**
- The members page will query the TeamSpeak server for all users in the specified groups
- Users can belong to multiple groups and will be categorized by the lowest group ID
- Sorting is done by category first (lowest group ID), then by client database ID
- If no members are found, a helpful message is shown directing users to check the admin panel configuration

### 2. Rank Badge Group ID Range Configuration

**Configuration Key:** `rank_badge_range`  
**Type:** JSON Object with `min` and `max` properties  
**Default Value:** `{"min": 9, "max": 18}`  
**Description:** Defines which TeamSpeak server group IDs are considered rank badges.

**Example:**
```json
{
  "min": 9,
  "max": 18
}
```

**Affected Files:**
- `src/members.php` - Displays rank icons on members list
- `src/profile.php` - Displays rank badges on user profiles

**Behavior:**
- Any server group within the specified range (min to max, inclusive) is treated as a rank badge
- The highest rank group ID a user belongs to determines their displayed rank
- Rank images are fetched from the TeamSpeak server icon cache
- The rank level is calculated as: `rank_level = group_id - (min - 1)`

### 3. Assigner Configuration

**Configuration Key:** `assignerconfig`  
**Type:** JSON Object  
**Description:** Configures automatic group assignment rules.

### 4. Admin Status Groups

**Configuration Key:** `adminstatus_groups`  
**Type:** JSON Array  
**Description:** Array of server group IDs to display in the admin status sidebar.

### 5. Discord Login Webhook

**Configuration Key:** `discord_login_webhook`  
**Type:** String (URL)  
**Description:** Discord webhook URL for login notifications.

## How to Configure

1. **Access the Admin Panel:**
   - Login with the authorized Unique ID
   - Navigate to `/admin/index.php`

2. **Edit Configuration:**
   - Each configuration section has a text area or input field
   - JSON configurations must be valid JSON syntax
   - The interface provides helpful descriptions for each option

3. **Save Changes:**
   - Click "Save Config" button
   - The system validates all inputs before saving
   - Invalid JSON or malformed data will show an error message

4. **Verify Changes:**
   - Configuration changes take effect immediately
   - Navigate to affected pages (e.g., members page) to see the changes

## Technical Details

### Database Storage

All configurations are stored in the `config` table with the following structure:
- `identifier` - Configuration key (string)
- `type` - Data type (STRING, INT, FLOAT, BOOL, JSON)
- `value` - Configuration value (serialized as string)

### Configuration Retrieval

Configurations are retrieved using the `Config` class:
```php
use Wruczek\TSWebsite\Config;

// Get members groups with default fallback
$memberGroups = Config::get("members_groups", [6, 7]);

// Get rank badge range with default fallback
$rankBadgeRange = Config::get("rank_badge_range", ["min" => 9, "max" => 18]);
```

### Default Values

If a configuration is not set in the database, the following defaults are used:
- **members_groups:** `[6, 7]`
- **rank_badge_range:** `{"min": 9, "max": 18}`
- **assignerconfig:** `[]`
- **adminstatus_groups:** `[]`
- **discord_login_webhook:** `""` (empty string)

## Files Modified

### Core Files
- `src/admin/index.php` - Admin panel page (UID authentication)
- `src/api/save-config.php` - Configuration save API (UID authentication, new config options)
- `src/api/save-rules.php` - Rules save API (UID authentication)
- `src/api/save-news.php` - News save API (UID authentication)

### Feature Files
- `src/members.php` - Dynamic member groups and rank badges
- `src/profile.php` - Dynamic rank badge display

### Template Files
- `src/private/templates/admin.latte` - Enhanced admin interface with new options
- `src/private/templates/members.latte` - Updated error message

## Benefits

1. **No Code Changes Required:** All configuration is done through the admin panel
2. **Flexible Group Management:** Easily change which groups are displayed without code modifications
3. **Scalable:** Add or remove groups from the members page without deployment
4. **User-Friendly:** Clear descriptions and JSON formatting in the admin interface
5. **Secure:** All admin functions protected by unique ID authentication

## Troubleshooting

### Members Page Shows "No members found"
1. Check that the configured groups exist in your TeamSpeak server
2. Verify that users actually belong to the configured groups
3. Ensure TeamSpeak server connection is working
4. Review the `members_groups` configuration in the admin panel

### Rank Badges Not Showing
1. Verify the `rank_badge_range` configuration includes the correct group IDs
2. Check that users have the rank groups assigned in TeamSpeak
3. Ensure the groups have icons assigned in TeamSpeak server settings

### Cannot Access Admin Panel
1. Verify you are logged in with the correct Unique ID: `Jv/d1+pX7/q343RrIMPTTVpob+U=`
2. Check that you are authenticated to the website
3. Verify the Auth system is working correctly

## Future Enhancements

Potential future configuration options:
- Customizable rank image URLs
- Pagination settings for members page
- Custom category names for member groups
- Advanced filtering options for members display
- Per-group icon/badge customization
