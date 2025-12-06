# Discord Widget Feature - Implementation Summary

## Overview
A Discord status widget has been fully implemented using the Discord Lanyard API. The widget displays real-time Discord presence information on user profile pages.

## Features Implemented

### 1. Backend Integration (`src/profile.php`)
- **Discord User ID Extraction** (lines 16-40):
  - Supports Discord profile URLs (discord.com/users/ID)
  - Supports raw Discord snowflake IDs
  - Validates and extracts user IDs from various formats

- **Lanyard API Integration** (lines 45-141):
  - Fetches real-time Discord presence data
  - Supports both cURL and stream context methods
  - Handles online, idle, DND, and offline statuses
  - Retrieves Discord avatar, username, discriminator, and display name
  - 4-second timeout for API requests
  - Graceful error handling

- **Profile Data Processing** (lines 512-519):
  - Extracts Discord ID from user's social profiles
  - Fetches presence data if Discord ID is available
  - Passes data to template for rendering

### 2. Frontend Template (`src/private/templates/profile.latte`)
- **Widget Positioning** (lines 94-126):
  - Positioned on the right side of the profile description box
  - Responsive grid layout:
    - Description: `col-12 col-lg-8` (takes 2/3 on large screens)
    - Discord Widget: `col-12 col-lg-4` (takes 1/3 on large screens)
  - Full width on mobile devices

- **Widget Structure**:
  - Header with Discord logo and status indicator
  - Clickable body linking to Discord profile
  - Avatar display with status dot
  - Display name and username/discriminator
  - Data attributes for JavaScript updates

### 3. Styling (`src/css/style.css`)
- **Discord Widget Styles** (lines 568-678):
  - Discord-themed dark background (#23272a)
  - Rounded corners (12px border-radius)
  - Status indicators with Discord colors:
    - Online: Green (#43b581)
    - Idle: Orange (#faa61a)
    - DND: Red (#f04747)
    - Offline: Gray (#747f8d)
  - Hover effects and smooth transitions
  - Box shadows for depth
  - Loading and error states
  - Responsive design for mobile

### 4. JavaScript Real-Time Updates (`src/js/script.js`)
- **Widget Initialization** (line 114):
  - Called on page load via `initDiscordWidgets()`
  - Scans for Discord widgets on the page

- **Dynamic Updates** (lines 176-213):
  - Fetches fresh data from Lanyard API every widget load
  - Updates status indicators in real-time
  - Handles loading and error states
  - Only updates widgets that don't already have data

- **Helper Functions** (lines 218-303):
  - `updateDiscordWidgetFromPayload()`: Updates widget DOM elements
  - `getDiscordAvatarUrl()`: Constructs avatar URLs from Discord CDN
  - `formatDiscordUsernameTag()`: Formats username with discriminator

## User Flow

### Adding Discord to Profile
1. User logs in and goes to "Edit Profile"
2. User enters their Discord profile URL or User ID in the "Discord" field
3. System saves the Discord information in the `socials_json` column

### Viewing Discord Widget
1. When viewing a profile with Discord configured:
   - Server-side: PHP fetches initial Discord presence data from Lanyard API
   - Client-side: JavaScript renders the widget with status indicator
   - Widget shows: Avatar, Display Name, Username#Tag, and Online Status
2. Widget is positioned to the right of the profile description
3. Widget is clickable and links to the user's Discord profile

## API Integration Details

### Discord Lanyard API
- **Endpoint**: `https://api.lanyard.rest/v1/users/{USER_ID}`
- **Method**: GET
- **Timeout**: 4 seconds
- **Response Format**: JSON with Discord presence data

### Supported Status Types
- **Online**: User is actively online
- **Idle**: User is away/idle
- **DND**: User has Do Not Disturb enabled
- **Offline**: User is offline or invisible

## Database Schema
The Discord information is stored in the `profiles` table:
- **Column**: `socials_json` (TEXT)
- **Format**: JSON object containing social media links including Discord
- **Example**: `{"discord": "123456789012345678", "steam": "...", ...}`

## Responsive Design
- **Desktop (≥992px)**: Widget appears to the right of description (33% width)
- **Tablet/Mobile (<992px)**: Widget appears below description (100% width)
- Maintains proper spacing and alignment across all screen sizes

## Error Handling
- If Lanyard API is unavailable: Shows fallback offline status
- If Discord user not found: Displays "Status unavailable"
- Network timeouts: Gracefully degrades to offline state
- Invalid user IDs: Ignored silently

## File Changes Summary

### Modified Files
1. `src/profile.php` - Backend logic for Discord integration
2. `src/private/templates/profile.latte` - Widget HTML structure
3. `src/css/style.css` - Discord widget styling
4. `src/js/script.js` - Real-time update functionality

### No Additional Dependencies
- Uses native PHP functions (cURL or file_get_contents)
- Pure vanilla JavaScript (no external libraries)
- CSS uses standard properties (no preprocessors needed)

## Testing Checklist
- [x] Discord user ID extraction from URLs
- [x] Discord user ID extraction from raw snowflakes
- [x] Lanyard API integration
- [x] Status indicator colors (online/idle/dnd/offline)
- [x] Avatar display with fallback
- [x] Username and discriminator formatting
- [x] Widget positioning on right side of description
- [x] Responsive design (mobile/tablet/desktop)
- [x] Error handling and graceful degradation
- [x] Real-time JavaScript updates

## Browser Compatibility
- Modern browsers with ES5+ JavaScript support
- CSS Grid and Flexbox support (all modern browsers)
- Graceful degradation for older browsers

## Performance Considerations
- Lanyard API calls cached server-side initially
- 4-second timeout prevents slow page loads
- JavaScript updates run asynchronously
- Minimal DOM manipulation for updates
- CSS uses hardware-accelerated transforms

## Future Enhancements (Optional)
- WebSocket support for real-time presence updates
- Rich presence activity display (game, Spotify, etc.)
- Custom status message display
- Discord server membership display
- Rate limiting for API calls

---

**Status**: ✅ Fully Implemented and Functional
**Last Updated**: December 6, 2025
