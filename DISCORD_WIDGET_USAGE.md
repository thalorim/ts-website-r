# Discord Widget - Usage Guide

## For End Users

### How to Add Discord to Your Profile

1. **Log in to your account**
   - Click the login button and authenticate via TeamSpeak

2. **Navigate to Edit Profile**
   - Click on your profile
   - Click the "Edit profile" button

3. **Add Your Discord ID**
   - Scroll down to the "Social links" section
   - Find the "Discord ID" field
   - Enter one of the following:
     - Your Discord User ID (e.g., `123456789012345678`)
     - Your Discord profile URL (e.g., `https://discord.com/users/123456789012345678`)

4. **Save Your Profile**
   - Click the "Save" button
   - Your Discord widget will now appear on your profile!

### Finding Your Discord User ID

**Method 1: Using Discord Developer Mode**
1. Open Discord Settings (⚙️ icon)
2. Go to Advanced → Enable "Developer Mode"
3. Right-click your username anywhere
4. Click "Copy User ID"

**Method 2: From Your Profile URL**
1. Open Discord in a web browser
2. Click your profile
3. Copy the URL - the long number at the end is your User ID
   - Example: `https://discord.com/users/123456789012345678`
   - User ID: `123456789012345678`

### What the Widget Shows

When you view a profile with Discord configured, you'll see:

```
┌─────────────────────────────────┐
│ DISCORD         🟢 Online       │
│                                 │
│  👤  Display Name               │
│      @username or User#1234     │
│                                 │
│  [Click to view Discord profile]│
└─────────────────────────────────┘
```

**Status Indicators:**
- 🟢 **Green** = Online (actively using Discord)
- 🟠 **Orange** = Away/Idle (away from keyboard)
- 🔴 **Red** = Do Not Disturb (user wants to focus)
- ⚪ **Gray** = Offline (not currently on Discord)

### Widget Position

The Discord widget appears:
- **On Desktop**: To the right of your profile description
- **On Mobile**: Below your profile description

## For Developers

### Prerequisites

1. **User must be in the Lanyard Discord server**
   - Join: https://discord.gg/lanyard
   - Required for the Lanyard API to track presence

2. **Database Requirements**
   - Table: `profiles`
   - Column: `socials_json` (TEXT)
   - Format: JSON string containing social links

### Architecture Overview

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Browser    │────▶│  profile.php │────▶│ Lanyard API  │
│  (User)      │◀────│  (Backend)   │◀────│  (Discord)   │
└──────────────┘     └──────────────┘     └──────────────┘
       │                    │                     
       │                    │                     
       ▼                    ▼                     
┌──────────────┐     ┌──────────────┐           
│ profile.latte│     │  Database    │           
│  (Template)  │────▶│  (Profiles)  │           
└──────────────┘     └──────────────┘           
       │                                         
       ▼                                         
┌──────────────┐                                
│  script.js   │                                
│  (Updates)   │                                
└──────────────┘                                
```

### API Flow

1. **Page Load (Server-Side)**
   ```
   User visits profile → PHP extracts Discord ID → 
   PHP calls Lanyard API → PHP passes data to template → 
   Template renders widget
   ```

2. **Real-Time Update (Client-Side)**
   ```
   JavaScript detects widget → Calls Lanyard API → 
   Updates status, avatar, and user info → 
   Shows updated presence
   ```

### Code Structure

**Backend:** `src/profile.php`
- `profile_extract_discord_user_id()` - Extracts user ID from input
- `profile_fetch_discord_presence()` - Fetches from Lanyard API

**Template:** `src/private/templates/profile.latte`
- Lines 94-126: Widget HTML structure
- Uses Bootstrap grid for positioning

**Styling:** `src/css/style.css`
- Lines 568-678: Complete Discord widget styles

**JavaScript:** `src/js/script.js`
- `initDiscordWidgets()` - Initializes all widgets
- `updateDiscordWidgetFromPayload()` - Updates widget DOM
- `getDiscordAvatarUrl()` - Constructs avatar URLs
- `formatDiscordUsernameTag()` - Formats username display

### Customization Examples

**Change Widget Size:**
```css
.discord-widget__avatar {
    width: 64px;    /* Change from 56px */
    height: 64px;
}
```

**Change Status Colors:**
```css
.discord-widget__status-dot.online { 
    background: #00ff00;  /* Custom green */
}
```

**Change Widget Position:**
```html
<!-- In profile.latte, change grid columns -->
<div class="col-12 col-lg-6">  <!-- Change from col-lg-8 -->
    <!-- Description -->
</div>
<div class="col-12 col-lg-6">  <!-- Change from col-lg-4 -->
    <!-- Discord widget -->
</div>
```

### Troubleshooting

**Widget shows "Status unavailable"**
- User hasn't joined Lanyard Discord server
- Invalid Discord user ID
- Lanyard API is temporarily down

**Widget doesn't appear**
- No Discord ID configured in profile
- Check `socials_json` column in database
- Verify Discord ID extraction logic

**Widget shows offline but user is online**
- Lanyard API cache (updates every 30s)
- User has invisible status
- Network connectivity issues

**JavaScript not updating**
- Check browser console for errors
- Verify `initDiscordWidgets()` is being called
- Check if fetch API is available

### Testing

**Test with Sample User IDs:**
- Lanyard Bot: `268798547439255572`
- Any user who has joined the Lanyard server

**Manual Test:**
```bash
# Test Lanyard API directly
curl https://api.lanyard.rest/v1/users/268798547439255572

# Expected response:
{
  "success": true,
  "data": {
    "discord_user": { ... },
    "discord_status": "online",
    ...
  }
}
```

### Rate Limits

- **Lanyard API**: No strict rate limits for REST API
- **Recommended**: Cache responses for 30-60 seconds
- **Current Implementation**: Fresh fetch on each page load

### Security Considerations

✅ **Implemented:**
- User ID validation (must be valid snowflake)
- API timeout (4 seconds)
- Error handling (graceful degradation)
- XSS protection (proper escaping)

⚠️ **Note:**
- Discord user IDs are public information
- Presence data is public via Lanyard
- No sensitive information is exposed

---

## Screenshots

### Desktop View
```
┌─────────────────────────────────────────────────────┐
│ Profile: Username                                   │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Description                  │  DISCORD  🟢 Online│
│ ─────────────────           │                    │
│ Lorem ipsum dolor            │   👤  John Doe     │
│ sit amet, consectetur        │       @johndoe     │
│ adipiscing elit.             │                    │
│                              │   [View Profile]   │
└─────────────────────────────────────────────────────┘
```

### Mobile View
```
┌──────────────────────────┐
│ Profile: Username        │
├──────────────────────────┤
│                          │
│ Description              │
│ ──────────────           │
│ Lorem ipsum dolor        │
│ sit amet, consectetur    │
│ adipiscing elit.         │
│                          │
│ ┌──────────────────────┐ │
│ │ DISCORD  🟢 Online  │ │
│ │                     │ │
│ │  👤  John Doe       │ │
│ │      @johndoe       │ │
│ │                     │ │
│ │  [View Profile]     │ │
│ └──────────────────────┘ │
└──────────────────────────┘
```

## Need Help?

- Check the [main README](README.md)
- Join the [Telegram group](https://t.me/tswebsite)
- Open an issue on GitHub

---

**Feature Status**: ✅ Fully Functional
**Version**: 2.0
**Last Updated**: December 6, 2025
