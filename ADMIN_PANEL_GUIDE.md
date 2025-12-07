# Admin Panel Configuration Guide

## Access Information

**Admin Panel URL:** `/admin/index.php`  
**Required Unique ID:** `Jv/d1+pX7/q343RrIMPTTVpob+U=`

## Configuration Interface

The admin panel now includes the following configuration sections:

### 1. Assigner Config (JSON)
Configure automatic group assignment rules.

**Format:** JSON Object
```json
{
  "rules": [
    ...
  ]
}
```

---

### 2. Admin Status Groups (JSON)
Array of server group IDs to display in the admin status sidebar.

**Format:** JSON Array
```json
[10, 11, 12]
```

**Example:**
- `10` - Administrators
- `11` - Moderators  
- `12` - Supporters

---

### 3. Members Page Groups (JSON) ⭐ NEW
Control which TeamSpeak server groups are displayed on the members page.

**Format:** JSON Array  
**Default:** `[6, 7]`

**Examples:**

Display groups 6 and 7:
```json
[6, 7]
```

Display multiple groups:
```json
[6, 7, 8, 10]
```

Display only VIP group:
```json
[15]
```

**What it does:**
- Members page will show all users from these groups
- Users can be in multiple groups
- Sorted by lowest group ID first, then by client database ID
- No members? Update this configuration to include the correct group IDs

---

### 4. Rank Badge Group ID Range (JSON) ⭐ NEW
Define which server group IDs are considered rank badges (displayed as icons next to usernames).

**Format:** JSON Object with `min` and `max`  
**Default:** `{"min": 9, "max": 18}`

**Examples:**

Default range (groups 9-18):
```json
{
  "min": 9,
  "max": 18
}
```

Custom range (groups 10-25):
```json
{
  "min": 10,
  "max": 25
}
```

Single rank group:
```json
{
  "min": 15,
  "max": 15
}
```

**What it does:**
- Any server group ID within this range is treated as a rank badge
- The highest rank group a user has is displayed as their rank icon
- Shown on both the members page and user profile pages
- Icons are pulled from TeamSpeak server group icons

**Example Scenario:**
If you have rank groups set up as:
- Group 9 = Rank 1 (Bronze)
- Group 10 = Rank 2 (Silver)
- Group 11 = Rank 3 (Gold)
- ...
- Group 18 = Rank 10 (Diamond)

Then use: `{"min": 9, "max": 18}`

---

### 5. Discord Login Webhook URL
Discord webhook URL to send login notifications to.

**Format:** URL String  
**Example:** `https://discord.com/api/webhooks/123456789/abcdefghijk`

When a user logs in, a notification is sent to this Discord webhook with:
- Client nickname and CLDBID
- Country flag
- IP address
- Unique ID

---

### 6. Rules HTML
Edit the HTML content for the rules page.

**Format:** HTML  
**Example:**
```html
<h1>Server Rules</h1>
<ol>
  <li>Be respectful</li>
  <li>No spam</li>
  <li>No hacking</li>
</ol>
```

---

### 7. Post News
Add a news article to the homepage.

**Fields:**
- **Title:** News article title
- **Content:** HTML content for the news post

---

## Step-by-Step Configuration

### Configuring Members Page Groups

1. **Identify Your Groups:**
   - Go to your TeamSpeak server
   - Open Server → Permissions → Server Groups
   - Note the group IDs you want to display (usually shown in the group list)

2. **Open Admin Panel:**
   - Login with your authorized UID
   - Navigate to `/admin/index.php`

3. **Edit Configuration:**
   - Scroll to "Members Page Groups (JSON)"
   - Edit the JSON array with your group IDs
   - Example: `[6, 7, 8, 10]`

4. **Save:**
   - Click "Save Config"
   - Wait for success confirmation

5. **Verify:**
   - Go to `/members.php`
   - Check that users from your configured groups are displayed

### Configuring Rank Badges

1. **Identify Your Rank Groups:**
   - Go to your TeamSpeak server
   - Find the server groups that represent ranks
   - Note the lowest and highest rank group IDs
   - Example: Rank groups are 9, 10, 11, 12, 13, 14, 15, 16, 17, 18

2. **Open Admin Panel:**
   - Login with your authorized UID
   - Navigate to `/admin/index.php`

3. **Edit Configuration:**
   - Scroll to "Rank Badge Group ID Range (JSON)"
   - Edit the JSON object with your min and max
   - Example: `{"min": 9, "max": 18}`

4. **Save:**
   - Click "Save Config"
   - Wait for success confirmation

5. **Verify:**
   - Go to `/members.php` or any profile page
   - Check that rank icons appear next to users who have rank groups
   - The icon should correspond to the user's highest rank group

## Common Issues

### Issue: "No members found" message on members page

**Solution:**
1. Check `members_groups` configuration in admin panel
2. Verify the groups exist in TeamSpeak server
3. Ensure users are actually in those groups
4. Make sure TeamSpeak connection is working

### Issue: Rank badges not showing

**Solution:**
1. Check `rank_badge_range` configuration in admin panel
2. Verify users have groups within that range
3. Ensure those groups have icons set in TeamSpeak
4. Check TeamSpeak server connection

### Issue: Cannot access admin panel

**Solution:**
1. Make sure you're logged in to the website
2. Verify your Unique ID is: `Jv/d1+pX7/q343RrIMPTTVpob+U=`
3. Check that you're online in TeamSpeak server
4. Try logging out and logging back in

### Issue: Configuration not saving

**Solution:**
1. Check JSON syntax is valid (use a JSON validator)
2. Ensure all required fields are present
3. Check browser console for error messages
4. Verify database connection is working

## Tips

💡 **Use a JSON Validator:** Before saving, validate your JSON at https://jsonlint.com/

💡 **Test with Small Changes:** Start by modifying one setting at a time to see the effect

💡 **Keep Backups:** Note down your current configuration before making changes

💡 **Check TeamSpeak Groups:** Make sure the group IDs you configure actually exist in your TeamSpeak server

💡 **Browser Cache:** If changes don't appear immediately, try a hard refresh (Ctrl+F5 or Cmd+Shift+R)

## Security Notes

🔒 Only users with the specific Unique ID can access the admin panel

🔒 All admin API endpoints are protected with authentication checks

🔒 Invalid configuration inputs are rejected with error messages

🔒 Configuration is stored securely in the database

## Support

For technical documentation, see: `ADMIN_PANEL_DATABASE_CONFIGURATION.md`

For a summary of changes, see: `CHANGES_SUMMARY.md`
