# Discord Lanyard Widget - Multi-User Support

## Overview
The Discord Lanyard widget displays real-time Discord presence information for users on their profile pages. This implementation fully supports multiple users, with each user having their own independent Discord widget.

## Features
- Real-time Discord status (Online, Idle, DND, Offline)
- Display of current Discord activity (games, Spotify, etc.)
- User avatar and username display
- Automatic updates every 30 seconds
- Per-user configuration

## How It Works

### Data Storage (Per-User)
Each user's Discord ID is stored in their own profile record in the database:
- **Table**: `profiles`
- **Column**: `socials_json` (JSON field containing all social links including Discord ID)
- **Key in JSON**: `discord`

Example of `socials_json`:
```json
{
  "instagram": "https://instagram.com/username",
  "discord": "94490510688792576",
  "steam": "https://steamcommunity.com/id/username"
}
```

### Data Retrieval (Per-Profile)
When viewing a profile:
1. The profile page receives `cldbid` parameter (e.g., `profile.php?cldbid=123`)
2. Backend fetches profile data for that specific user from database
3. Extracts Discord ID from that user's `socials_json` field
4. Passes Discord ID to template for widget initialization
5. JavaScript widget fetches Discord status from Lanyard API for that specific Discord ID

### Code Flow
```php
// profile.php (lines 13, 244, 402-441)
$cldbid = $_GET["cldbid"];                                    // Get which user's profile to show
$dbProfile = $db->get("profiles", "*", ["cldbid" => $cldbid]); // Fetch that user's data
$socials = json_decode($dbProfile["socials_json"], true);     // Parse their socials
$discordId = $socials['discord'];                             // Get their Discord ID
```

## Setup Instructions

### For Users
1. Navigate to "Edit Profile" page
2. Scroll to "Social Links" section
3. Find the "Discord ID" field
4. Enter your Discord User ID (numeric, e.g., `94490510688792576`)
   - To find your Discord ID: Enable Developer Mode in Discord → Right-click your username → Copy ID
5. Click "Save"
6. View your profile to see the Discord widget

### Important Notes
- Discord ID must be numeric (18-19 digits)
- The widget uses the [Lanyard API](https://github.com/Phineas/lanyard)
- Your Discord status must be visible for the widget to work
- Each user's Discord ID is stored independently

## Multi-User Support Verification

### Test Scenario
1. **User A** (cldbid=1):
   - Sets Discord ID: `111111111111111111`
   - Views profile at `profile.php?cldbid=1`
   - Widget displays User A's Discord status

2. **User B** (cldbid=2):
   - Sets Discord ID: `222222222222222222`
   - Views profile at `profile.php?cldbid=2`
   - Widget displays User B's Discord status

3. **Verification**:
   - User A visits `profile.php?cldbid=1` → Shows Discord ID `111111111111111111`
   - User A visits `profile.php?cldbid=2` → Shows Discord ID `222222222222222222`
   - User B visits `profile.php?cldbid=1` → Shows Discord ID `111111111111111111`
   - User B visits `profile.php?cldbid=2` → Shows Discord ID `222222222222222222`

**Result**: Each profile correctly displays its own Discord widget, regardless of who is viewing.

## Implementation Details

### Files Modified
1. **src/js/lanyard.js** (NEW)
   - JavaScript widget implementation
   - Fetches Discord data from Lanyard API
   - Renders widget with status and activity

2. **src/css/style.css**
   - Widget styling
   - Responsive design for mobile devices
   - Status indicators (online, idle, dnd, offline)

3. **src/profile.php**
   - Extracts Discord ID from user's profile data
   - Validates Discord ID (must be numeric)
   - Passes Discord ID to template

4. **src/private/templates/profile.latte**
   - Displays widget when Discord ID is set
   - Unique widget ID per user: `lanyard-widget-{cldbid}`
   - Conditionally shows widget section

5. **src/private/templates/edit-profile.latte**
   - Added Discord ID input field
   - Placeholder and help text for users

### Database Schema
No database changes required. Uses existing `profiles` table:
- `socials_json` TEXT field (already exists)
- Stores all social links as JSON
- Discord ID stored as `discord` key in JSON

### Security Considerations
- Discord IDs are validated (must be numeric)
- No sensitive information is stored
- Widget only displays public Discord status
- XSS protection via proper HTML escaping in JavaScript

## Troubleshooting

### Widget Shows "No Discord ID provided"
- User hasn't set their Discord ID
- Discord ID field is empty in database
- Check `socials_json` in database for that user

### Widget Shows "Unable to connect to Discord"
- Discord ID is invalid
- User's Discord account doesn't exist
- Lanyard API is down
- Network connectivity issues

### Widget Shows Wrong User's Status
**This should NOT happen** with the current implementation. If it does:
1. Check browser cache - clear cache and hard refresh (Ctrl+F5)
2. Verify correct URL parameter: `profile.php?cldbid=X`
3. Check database: Ensure Discord ID is stored in correct user's row
4. Check JavaScript console for errors

### Multiple Widgets on Same Page
While not currently implemented, the code supports multiple widgets:
- Each widget has unique ID: `lanyard-widget-{cldbid}`
- JavaScript initializes all `.lanyard-widget` elements
- Each widget independently fetches its own Discord data

## API Reference

### Lanyard API
- **Endpoint**: `https://api.lanyard.rest/v1/users/{discord_id}`
- **Method**: GET
- **Response**: JSON with Discord presence data
- **Rate Limit**: Reasonable (not officially documented)
- **Documentation**: https://github.com/Phineas/lanyard

### Response Format
```json
{
  "success": true,
  "data": {
    "discord_user": {
      "id": "94490510688792576",
      "username": "username",
      "discriminator": "0001",
      "avatar": "hash"
    },
    "discord_status": "online",
    "activities": [],
    "listening_to_spotify": false,
    "spotify": null
  }
}
```

## Conclusion
The Discord Lanyard widget is fully implemented with proper multi-user support. Each user's Discord ID is stored independently in the database and correctly retrieved when viewing their profile. There are no global variables or shared state that would cause one user's widget to display another user's Discord status.
