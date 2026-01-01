# Discord Status Widget - Setup Guide

## What Was Implemented

A Discord status widget that displays below the Admin Status widget in the profile sidebar, showing:
- Discord avatar (48x48px, circular)
- Display name (global name)
- Username with discriminator (if applicable)
- Real-time status indicator with color (Online/Idle/DND/Offline)

## Features

✅ **Clean, Compact Design**: Matches the existing card styling
✅ **Real-time Status**: Fetches live Discord presence data
✅ **Automatic Display**: Only shows when user has configured Discord ID
✅ **Graceful Fallback**: Silently fails if Discord API is unavailable
✅ **Mobile Friendly**: Responsive design
✅ **Privacy-Focused**: Only displays for users who opt-in by providing their Discord ID

## How Users Can Set It Up

### Step 1: Get Discord User ID
1. Open Discord
2. Go to User Settings → Advanced
3. Enable "Developer Mode"
4. Right-click on their username anywhere in Discord
5. Click "Copy User ID"

### Step 2: Join Lanyard Discord Server
**IMPORTANT:** Users must join the Lanyard Discord server for their presence to be tracked.
- Join link: https://discord.gg/lanyard
- This is required for the API to track their status

### Step 3: Add Discord ID to Profile
1. Go to Edit Profile page on your website
2. Scroll to "Discord User ID" field
3. Paste the Discord ID (17-19 digit number)
4. Click Save

### Step 4: View Profile
The Discord widget will now appear on their profile page sidebar, below the Admin Status widget!

## Technical Implementation

### Files Modified:
1. `/src/edit-profile.php` - Added discord_id column and input handling
2. `/src/private/templates/edit-profile.latte` - Added Discord ID input field
3. `/src/profile.php` - Added Discord data fetching logic
4. `/src/private/templates/sidebar.latte` - Added Discord widget display

### Files Created:
1. `/src/private/php/Utils/DiscordUtils.php` - Discord API integration utility

### Database Changes:
- Added `discord_id` column to `profiles` table (VARCHAR 64)

## API Used

**Lanyard API**: https://api.lanyard.rest/v1/users/{DISCORD_ID}
- Free, public API
- No authentication required
- Real-time Discord presence tracking
- Requires users to be in the Lanyard Discord server

## Status Colors

- 🟢 **Online**: Green (#43b581)
- 🟠 **Idle**: Orange (#faa61a)
- 🔴 **Do Not Disturb**: Red (#f04747)
- ⚫ **Offline**: Gray (#747f8d)

## Widget Layout

```
┌─────────────────────────────────┐
│ 👤 Discord Status               │
├─────────────────────────────────┤
│ [Avatar] John Doe               │
│          @johndoe#1234          │
│          🟢 Online              │
└─────────────────────────────────┘
```

## Troubleshooting

### Widget Not Showing?
1. Verify Discord ID is correct (17-19 digits)
2. Ensure user has joined Lanyard Discord server
3. Check if Discord ID is saved in database
4. Wait a few minutes after joining Lanyard server

### Status Not Updating?
- Status is fetched on each page load (no caching)
- May take a few seconds to reflect changes
- Lanyard API has a small delay

### Avatar Not Loading?
- Ensure Discord account has an avatar set
- API will use default Discord avatar if none set
- Check browser console for CORS or loading errors

## Database Queries

### Find all users with Discord IDs:
```sql
SELECT cldbid, nickname, discord_id 
FROM profiles 
WHERE discord_id IS NOT NULL;
```

### Remove Discord ID for a user:
```sql
UPDATE profiles 
SET discord_id = NULL 
WHERE cldbid = 12345;
```

## Privacy & Security

- Discord IDs are public information
- No sensitive data is stored
- API calls are read-only
- Widget only shows opt-in users
- Users can remove their Discord ID anytime
- 5-second timeout prevents slow page loads

## Future Enhancements (Optional)

- Cache Discord data for 30-60 seconds to reduce API calls
- Display current activity/game being played
- Show Spotify listening status
- Add admin setting to enable/disable feature globally
- Support custom status messages
