# Clan Page Quick Start Guide

## What Was Implemented

A complete clan page system for TeamSpeak Group 19 with the following features:
- Public clan page displaying group information and members
- Admin panel for cldbid 3 to customize clan settings
- Automatic database table creation
- Member list with online/offline status
- Avatar upload system

## Quick Access URLs

### View Clan Page
```
http://your-domain.com/clan.php?groupid=19
```

### Edit Clan Settings (cldbid 3 only)
```
http://your-domain.com/edit-clan.php?groupid=19
```

## First-Time Setup

### 1. Access the Clan Page
Simply navigate to `clan.php?groupid=19` - the database table will be created automatically on first access.

### 2. Customize Your Clan (cldbid 3 required)

1. **Log in** with the account that has cldbid 3
2. Navigate to `edit-clan.php?groupid=19`
3. **Set Clan Name**: Enter your clan's display name
4. **Add Description**: Write a description (HTML supported)
   - Example: `<b>Welcome</b> to our clan!<br>We are the <i>best</i> clan!`
5. **Upload Avatar**: Choose a PNG, JPG, or WEBP file (max 2MB)
6. Click **Save Changes**

### 3. View Your Changes
Return to `clan.php?groupid=19` to see the updated clan page.

## Features

### Member List
- Shows all members of group 19
- Green dot = member is online
- Gray dot = member is offline
- Click "View Profile" to see detailed member profiles
- Shows country flags and rank icons

### Clan Information
- Custom clan avatar/logo
- Custom clan name
- Rich text description with HTML support
- Member count

## HTML Formatting in Description

You can use these HTML tags in the clan description:

```html
<b>Bold text</b>
<i>Italic text</i>
<u>Underline text</u>
<br> Line break
<p>Paragraph</p>
<h1>Heading 1</h1> through <h6>Heading 6</h6>
<a href="URL">Link text</a>
<ul><li>Bullet list</li></ul>
<ol><li>Numbered list</li></ol>
```

Example:
```html
<h3>Welcome to Our Clan!</h3>
<p>We are a <b>competitive</b> gaming clan focused on:</p>
<ul>
  <li>Teamwork</li>
  <li>Skill development</li>
  <li>Having fun!</li>
</ul>
<p>Visit our website: <a href="https://example.com">example.com</a></p>
```

## Access Control

- **Viewing**: Anyone can view the clan page (no login required)
- **Editing**: Only cldbid 3 can edit clan settings
- **Group**: Only works with group ID 19

## File Locations

- **Clan Page**: `/workspace/src/clan.php`
- **Edit Page**: `/workspace/src/edit-clan.php`
- **Templates**: `/workspace/src/private/templates/clan.latte` and `edit-clan.latte`
- **Uploads**: `/workspace/src/img/clans/`
- **Documentation**: `/workspace/CLAN_PAGE_IMPLEMENTATION.md`

## Common Tasks

### Change Who Can Edit
Edit `edit-clan.php` line 23:
```php
if (Auth::getCldbid() !== 3) {
```
Change `3` to the desired cldbid.

### Add Navigation Link
Edit `/workspace/src/private/templates/body.latte` around line 88, add:
```latte
<li class="nav-item{if $navActiveIndex === 7} active{/if}">
    <a class="nav-link" href="clan.php?groupid=19"><i class="fas fa-shield-alt"></i>Clan</a>
</li>
```

### Change Default Avatar
Replace `img/icons/defaulticon-128.png` in the code, or simply upload a new avatar through the edit page.

## Troubleshooting

### "403 Forbidden" When Editing
- You must be logged in as cldbid 3
- Check your login status in the top-right corner

### Avatar Upload Fails
- Check file size (must be under 2MB)
- Check file format (PNG, JPG, or WEBP only)
- Verify `/workspace/src/img/clans/` directory is writable

### Members Not Showing
- Verify group 19 exists in TeamSpeak
- Check that users are assigned to group 19
- Verify TeamSpeak connection is working

### Database Table Not Created
The table creates automatically on first page load. If issues persist, check database connection and permissions.

## Support

For detailed implementation information, see `CLAN_PAGE_IMPLEMENTATION.md` in the workspace root.
