# Profile Customization Feature

## Overview
This document describes the nickname display style customization feature that allows users to modify how their TeamSpeak nickname appears on their profile.

## Features Implemented

### Nickname Display Style
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
   - Applied custom background color directly to `.avatar-and-meta` if set
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
One new column added:

```sql
ALTER TABLE `profiles` ADD COLUMN `nickname_style` VARCHAR(20) NULL;
```

## How to Use

### For Users:
1. Navigate to Edit Profile page
2. Scroll to "Nickname Display Style" section
3. **Nickname Style**:
   - Select one of the 4 style options
   - Preview is shown in each option
5. Click "Save" to apply changes
6. View your profile to see the changes

## Technical Notes

- Nickname styles use CSS classes applied conditionally
- Customization is optional and stored per user
- Default behavior is maintained when no style is selected
- External GIF URLs are used for sparkle effects (ensure they remain accessible)

## Browser Compatibility

- Color input type is supported in all modern browsers
- CSS animations work in all major browsers with vendor prefixes included
- Fallback to standard display if animations are not supported
