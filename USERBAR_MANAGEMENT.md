# Profile Userbar Management Guide

## Overview
The profile userbar feature allows certain user groups to display custom userbar images on their profile pages. The feature is fully database-configurable.

## Database Tables

### 1. `userbar_groups` Table
Stores the server group IDs that are allowed to edit userbar URLs.

**Structure:**
- `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
- `sgid` (INT, UNIQUE) - Server Group ID
- `created_at` (TIMESTAMP)

**Default Groups:**
- Group ID 532
- Group ID 556

### 2. `profiles` Table
Added column:
- `userbar_url` (VARCHAR 512) - Stores the userbar image URL for each user

## Managing Allowed Groups

### Add a New Group ID
To allow additional groups to edit userbars, insert a new row:

```sql
INSERT INTO `userbar_groups` (`sgid`) VALUES (YOUR_GROUP_ID);
```

**Example:** Allow group 600 to edit userbars:
```sql
INSERT INTO `userbar_groups` (`sgid`) VALUES (600);
```

### Remove a Group ID
To remove a group's permission:

```sql
DELETE FROM `userbar_groups` WHERE `sgid` = YOUR_GROUP_ID;
```

**Example:** Remove group 532:
```sql
DELETE FROM `userbar_groups` WHERE `sgid` = 532;
```

### View All Allowed Groups
```sql
SELECT * FROM `userbar_groups`;
```

## Managing User Userbars

### View User's Userbar URL
```sql
SELECT `cldbid`, `nickname`, `userbar_url` 
FROM `profiles` 
WHERE `userbar_url` IS NOT NULL;
```

### Manually Set a User's Userbar
```sql
UPDATE `profiles` 
SET `userbar_url` = 'https://example.com/userbar.png' 
WHERE `cldbid` = USER_CLDBID;
```

### Remove a User's Userbar
```sql
UPDATE `profiles` 
SET `userbar_url` = NULL 
WHERE `cldbid` = USER_CLDBID;
```

## Features

1. **Automatic Setup**: When a user visits the edit profile page, the database tables and columns are automatically created if they don't exist.

2. **Permission-Based Access**: Only users belonging to groups listed in `userbar_groups` can see and edit the userbar URL field in their profile settings.

3. **Profile Display**: Userbars are displayed on the right side of the profile header, centered with the avatar section.

4. **Flexible URLs**: The userbar URL field accepts any valid image URL (up to 512 characters).

5. **Responsive Design**: The userbar image automatically scales to fit the profile header (max-height: 50px, max-width: 350px).

## User Workflow

1. **For Authorized Users:**
   - Navigate to Edit Profile page
   - Scroll to "Profile Userbar URL" field (visible only if user is in allowed group)
   - Enter the URL of their userbar image
   - Click Save

2. **Display:**
   - The userbar appears on the profile page header
   - Positioned on the right side, next to the rank image (if present)
   - Centered vertically with the avatar section

## Technical Notes

- The system checks user permissions on every edit profile page load
- No caching is used for group permissions (always fresh from database)
- If a user's group is removed from `userbar_groups`, they lose access to edit the field but their existing userbar remains displayed
- The userbar URL is stored per-user in the profiles table
