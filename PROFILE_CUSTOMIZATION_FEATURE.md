# Profile Customization Feature

## Overview
This document describes the new profile customization features that allow users to modify their profile appearance.

## Features Implemented

### 1. Background Color Customization
- **Location**: Edit Profile page
- **Feature**: RGB color picker for `.steamlike-header .avatar-and-meta` background
- **Effect**: Selected color automatically gets a 15% darken effect applied
- **Default**: `#1b2838` (original Steam-like blue-gray)
- **Storage**: Database column `profile_bg_color` (VARCHAR 7)

### 2. Nickname Display Style
- **Location**: Edit Profile page
- **Feature**: Four style options for `.steamlike-header .nickname`
- **Styles**:
  - **Default/None**: No special effects
  - **Style 1**: Sparkle background with color-changing animation
    - Background: `url(https://reape.rs/sparkle.gif)`
    - Color: `#ffffff`
    - Font-weight: `600`
    - Animation: `apollochange 20s infinite alternate` (hue rotation)
  - **Style 2**: Cyan glow effect
    - Color: `white`
    - Text-shadow: `0px 2px 2px #03c8ff`
  - **Style 3**: Sparkle background with blue shadow
    - Background: `transparent url('https://i.ibb.co/qjs2j2X/clip-transparent-sparkle-gif-4.gif')`
    - Color: `#FFFFFF`
    - Text-shadow: `0px 2px 2px #cee9f5`
- **Storage**: Database column `nickname_style` (VARCHAR 20)

## Files Modified

### Backend (PHP)
1. **`/workspace/src/edit-profile.php`**
   - Added database schema updates for `profile_bg_color` and `nickname_style` columns
   - Added POST handling for the new fields with validation
   - Color validation: Must match pattern `^#[0-9A-Fa-f]{6}$`
   - Style validation: Must be one of `none`, `style1`, `style2`, `style3`
   - Added fields to template rendering

2. **`/workspace/src/profile.php`**
   - Fetches `profile_bg_color` and `nickname_style` from database
   - Passes values to template for rendering

### Frontend (Templates)
3. **`/workspace/src/private/templates/edit-profile.latte`**
   - Added RGB color picker with:
     - Visual color input
     - Hex text input (synced)
     - Reset button
   - Added nickname style selector with 4 radio options
   - Each style option shows a preview
   - Added JavaScript for color picker synchronization

4. **`/workspace/src/private/templates/profile.latte`**
   - Added PHP logic to calculate darkened background color
   - Applied custom background color to `.avatar-and-meta` if set
   - Applied nickname style class to `.nickname` element

### Styling (CSS)
5. **`/workspace/src/css/style.css`**
   - Added `.nickname-style1`, `.nickname-style2`, `.nickname-style3` classes
   - Added `@keyframes apollochange` for hue rotation animation
   - Added edit profile styles:
     - `.nickname-style-options` - Grid layout for style selector
     - `.nickname-style-option` - Individual style option
     - `.nickname-style-preview` - Preview card with hover/selected states
   - Added preview styles for each nickname style

## Database Schema Changes

### Table: `profiles`
Two new columns added:

```sql
ALTER TABLE `profiles` ADD COLUMN `profile_bg_color` VARCHAR(7) NULL;
ALTER TABLE `profiles` ADD COLUMN `nickname_style` VARCHAR(20) NULL;
```

## How to Use

### For Users:
1. Navigate to Edit Profile page
2. Scroll to "Profile Customization" section
3. **Background Color**:
   - Click the color picker or enter a hex code
   - Color will be automatically darkened when applied
   - Click "Reset" to restore default
4. **Nickname Style**:
   - Select one of the 4 style options
   - Preview is shown in each option
5. Click "Save" to apply changes
6. View your profile to see the changes

## Technical Notes

- Background color darkening is calculated server-side by reducing RGB values by 15%
- Nickname styles use CSS classes applied conditionally
- All customizations are optional and stored per user
- Default behavior is maintained when no customization is set
- Color picker synchronizes between visual and text inputs via JavaScript
- External GIF URLs are used for sparkle effects (ensure they remain accessible)

## Browser Compatibility

- Color input type is supported in all modern browsers
- CSS animations work in all major browsers with vendor prefixes included
- Fallback to standard display if animations are not supported
