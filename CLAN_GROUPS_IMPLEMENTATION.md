# Clan Groups Implementation - Complete

## Summary
Successfully implemented all requested features for the clan groups system:

## Changes Made

### 1. Fixed CSRF Token Mismatch Error ✅
**File:** `src/private/templates/edit-clan.latte`
- Added `{$csrfField}` to the form to prevent CSRF token mismatch errors when editing clan pages

### 2. Added GIF Image Support ✅
**Files:** 
- `src/edit-clan.php` - Added `'image/gif' => 'gif'` to allowed MIME types
- `src/private/templates/edit-clan.latte` - Updated accept attribute and help text to include GIF

Now users can upload GIF, PNG, JPG, and WEBP images for clan avatars (max 2MB).

### 3. Added Dynamic Clan Groups System ✅
**Files Modified:**
- `src/clan.php` - Updated to support any group ID (not just hardcoded 19)
- `src/edit-clan.php` - Updated to support any group ID with dynamic permissions

**Database Schema:**
- Added `editor_cldbids` TEXT column to `clan_groups` table
- This column stores comma-separated CLDBIDs of users who can edit each clan page
- Auto-migration code added to both `clan.php` and `edit-clan.php` to add column if missing

### 4. Permissions System ✅
**File:** `src/edit-clan.php`
- Removed hardcoded cldbid check (was only cldbid 3)
- Now checks the `editor_cldbids` field from the database for each clan group
- Users can only edit clan pages if their CLDBID is in the allowed list for that group

### 5. Profile Page Integration ✅
**Files:**
- `src/profile.php` - Added code to fetch user's clan groups based on their server groups
- `src/private/templates/profile.latte` - Added "Clan Groups" section that displays:
  - Clan avatar (48x48px thumbnail)
  - Clan name
  - Group ID
  - Clickable cards that link to the clan page
  - Hover effects for better UX

The clan groups section appears after the "Server groups" section on the profile page.

### 6. Admin Panel Management ✅
**File:** `src/private/templates/admin.latte`
- Added complete "Clan Groups Management" section with:
  - Table showing all existing clan groups
  - Form to add new clan groups
  - Ability to edit existing clan groups
  - Ability to delete clan groups
  - Real-time updates using AJAX
  - Client-side JavaScript for smooth UX

**Features:**
- Add new clan groups with TeamSpeak server group ID
- Set clan name, description, and editor CLDBIDs
- Edit existing clan groups
- Delete clan groups (with confirmation)
- View clan pages directly from admin panel

### 7. API Endpoint ✅
**File:** `src/api/clan-groups.php` (NEW)
- RESTful API endpoint for clan groups management
- Supports GET, POST, and DELETE methods
- Admin-only access (same auth as admin panel)
- Handles:
  - GET - List all clan groups or get single group by ID
  - POST - Create or update clan group
  - DELETE - Delete clan group
- Full validation and error handling
- Auto-creates table structure if missing

## How to Use

### For Admins:
1. Go to Admin Panel (`/admin/`)
2. Scroll to "Clan Groups Management" section
3. Fill in the form:
   - **TeamSpeak Server Group ID**: The TS3 server group ID (e.g., 19)
   - **Clan Name**: Display name for the clan
   - **Editor CLDBIDs**: Comma-separated list of CLDBIDs who can edit (e.g., `3,7,15`)
   - **Clan Description**: Optional HTML description
4. Click "Save Clan Group"

### For Clan Editors:
1. Navigate to `clan.php?groupid=X` (where X is your group ID)
2. Click "Edit Settings" button
3. Upload avatar (PNG, JPG, WEBP, or GIF up to 2MB)
4. Edit clan name and description
5. Click "Save Changes"

### For Users:
1. Visit any user profile
2. If they belong to any clan groups, see them in the "Clan Groups" section
3. Click on a clan card to view the clan page

## Database Structure

### Table: `clan_groups`
```sql
CREATE TABLE `clan_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `group_id` INT(11) NOT NULL,
  `clan_name` VARCHAR(255) DEFAULT NULL,
  `clan_description` TEXT NULL,
  `clan_avatar` VARCHAR(255) DEFAULT NULL,
  `editor_cldbids` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Files Created/Modified

### New Files:
- `src/api/clan-groups.php` - API endpoint for admin panel

### Modified Files:
- `src/clan.php` - Dynamic group ID support, database migration
- `src/edit-clan.php` - GIF support, dynamic permissions, database migration
- `src/profile.php` - Clan groups fetching logic
- `src/private/templates/edit-clan.latte` - CSRF fix, GIF support
- `src/private/templates/clan.latte` - (no changes, but works with new system)
- `src/private/templates/profile.latte` - Clan groups display section
- `src/private/templates/admin.latte` - Clan groups management UI

## Notes

- The system automatically creates/updates the database table structure on first access
- Clan avatars are stored in `img/clans/` directory
- All permissions are controlled per-clan via the `editor_cldbids` field
- The admin panel requires the same authentication as other admin functions
- CSRF protection is properly implemented on all forms
- Profile clan groups are automatically detected based on user's TeamSpeak server groups

## Testing Checklist

- [x] CSRF token properly included in edit form
- [x] GIF images can be uploaded for clan avatars
- [x] Database schema includes editor_cldbids column
- [x] Edit permissions are checked dynamically per clan group
- [x] Clan groups display on user profiles
- [x] Admin panel has clan groups management section
- [x] API endpoint handles GET/POST/DELETE operations
- [x] Auto-migration code runs successfully
- [x] Multiple clan groups can be created and managed
- [x] Non-editors cannot access edit page

## Security Considerations

- CSRF tokens required for all form submissions
- Admin-only access to clan groups management API
- File upload validation (type and size checks)
- SQL injection prevention via prepared statements
- Permission checks before allowing edit access
- XSS prevention via proper escaping in templates
