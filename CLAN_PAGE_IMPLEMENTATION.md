# Clan Page Implementation for Group 19

## Overview

This implementation adds a clan-style group page for TeamSpeak group ID 19 with administrative editing capabilities for user with cldbid 3.

## Features

### 1. Clan Display Page (`clan.php`)
- **URL**: `clan.php?groupid=19`
- Displays clan information including:
  - Clan name
  - Clan avatar/logo
  - Clan description (with HTML support)
  - Member count
  - List of all members in the group with:
    - Online/offline status indicators
    - Member names with links to profiles
    - Country flags
    - Rank icons
    - Profile view buttons

### 2. Clan Settings Editor (`edit-clan.php`)
- **URL**: `edit-clan.php?groupid=19`
- **Access**: Only cldbid 3 can edit
- Editable settings:
  - **Clan Name**: Custom display name for the clan
  - **Clan Description**: Rich text description with HTML support
  - **Clan Avatar**: Upload custom clan logo/avatar (PNG, JPG, WEBP, max 2MB)

### 3. Database Structure

A new table `clan_groups` is automatically created with the following schema:

```sql
CREATE TABLE `clan_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_id` INT(11) NOT NULL,
    `clan_name` VARCHAR(255) DEFAULT NULL,
    `clan_description` TEXT NULL,
    `clan_avatar` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_group_id` (`group_id`)
);
```

## File Structure

### New Files Created:
```
/workspace/src/
├── clan.php                          # Main clan display page
├── edit-clan.php                     # Clan settings editor (cldbid 3 only)
├── img/
│   └── clans/                        # Directory for uploaded clan avatars
└── private/
    └── templates/
        ├── clan.latte                # Clan page template
        └── edit-clan.latte           # Edit settings template
```

## Usage

### Accessing the Clan Page

1. **Direct URL**: Navigate to `clan.php?groupid=19`
2. The page will display:
   - Clan header with avatar and name
   - Clan description section
   - Complete member list with online status

### Editing Clan Settings (cldbid 3 only)

1. Log in with the account that has cldbid 3
2. Navigate to `edit-clan.php?groupid=19` or click "Edit Settings" button on the clan page
3. Update any of the following:
   - Clan name
   - Clan description (supports HTML: `<b>`, `<i>`, `<br>`, etc.)
   - Upload new clan avatar
4. Click "Save Changes"

### Adding Navigation Link (Optional)

To add a permanent link to the clan page in the navigation bar, edit `/workspace/src/private/templates/body.latte`:

Add after line 88 (after the Members link):

```latte
<li class="nav-item{if $navActiveIndex === 7} active{/if}">
    <a class="nav-link" href="clan.php?groupid=19"><i class="fas fa-shield-alt"></i>Clan</a>
</li>
```

Then in your clan page files, set `"navActiveIndex" => 7` to highlight this nav item.

## Security

- Only users with cldbid 3 can access the edit page
- Non-logged-in users attempting to edit will see "401 Unauthorized"
- Users with other cldbid values will see "403 Forbidden"
- File uploads are validated for:
  - File size (max 2MB)
  - File type (PNG, JPG, WEBP only)
  - MIME type validation

## Member List Features

The member list automatically:
- Fetches members from the live TeamSpeak server when available
- Falls back to database profiles when server is offline
- Displays online status with colored indicators:
  - Green dot = Online
  - Gray dot = Offline
- Sorts members by online status (online first), then by cldbid
- Shows rank icons based on configured rank badge range
- Shows country flags when available
- Links directly to member profiles

## Customization

### Changing Authorized Editor

To allow a different user to edit clan settings, modify line 23 in `edit-clan.php`:

```php
if (Auth::getCldbid() !== 3) {
```

Change `3` to the desired cldbid, or modify to check for a group instead:

```php
// Example: Allow any admin (group 6) to edit
$userGroups = explode(',', Auth::getUserInfo()['client_servergroups'] ?? '');
if (!in_array(6, array_map('intval', $userGroups))) {
```

### Adding More Groups

To create clan pages for other groups, you can:
1. Duplicate the `clan.php` and `edit-clan.php` files
2. Modify the group ID checks
3. Adjust the authorized editor cldbid as needed

Or create a more dynamic version that accepts any group ID (remove the hardcoded group 19 check).

### Styling

The clan page uses the existing Bootstrap 4 theme and custom styles defined in `/workspace/src/css/style.css`. The online/offline status dots are styled inline in the template but can be moved to a CSS file for consistency.

## Testing

To test the implementation:

1. **View Clan Page**: Navigate to `clan.php?groupid=19`
   - Should display clan information and members
   - Members should show with online/offline indicators

2. **Edit Settings** (as cldbid 3):
   - Log in with cldbid 3 account
   - Navigate to `edit-clan.php?groupid=19`
   - Upload a clan avatar
   - Set clan name and description
   - Verify changes appear on clan page

3. **Access Control**:
   - Try accessing edit page with different user → Should show 403 Forbidden
   - Try accessing edit page without login → Should show 401 Unauthorized
   - Try accessing with wrong group ID → Should show 404 Not Found

## Troubleshooting

### Upload Directory Issues
If avatar uploads fail, ensure:
```bash
mkdir -p /workspace/src/img/clans
chmod 775 /workspace/src/img/clans
```

### Database Table Not Created
The table is created automatically on first access. If issues occur, manually run:
```sql
CREATE TABLE IF NOT EXISTS `clan_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_id` INT(11) NOT NULL,
    `clan_name` VARCHAR(255) DEFAULT NULL,
    `clan_description` TEXT NULL,
    `clan_avatar` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Members Not Showing
- Check that group 19 exists in TeamSpeak
- Verify members are assigned to group 19
- Check TeamSpeak connection in the application

## Future Enhancements

Possible improvements:
- Support for multiple clan pages with different group IDs
- Clan achievements/statistics
- Clan events calendar
- Member recruitment system
- Clan vs Clan comparison
- Multiple authorized editors per clan
- Clan banner upload in addition to avatar
- Rich text editor for description
