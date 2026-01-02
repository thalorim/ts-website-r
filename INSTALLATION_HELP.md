# Installation Help - Finding Your TS-Website Directory

## Error: "This script must be run from the TS-website root directory"

You're seeing this error because the installation script needs to be run from your TS-website installation directory.

## Solution: Navigate to Your TS-Website Directory

### Step 1: Find Your TS-Website Installation

Your TS-website is likely installed in one of these common locations:

```bash
# Common installation paths:
/var/www/html/ts-website
/var/www/html
/var/www/web.reape.rs
/home/username/public_html
/usr/share/nginx/html
```

To find it, run:

```bash
# Search for the load.php file
find /var/www -name "load.php" -path "*/private/php/*" 2>/dev/null

# Or search for profile.php (a TS-website file)
find /var/www -name "profile.php" -type f 2>/dev/null
```

### Step 2: Navigate to That Directory

Once you find your installation path, navigate there:

```bash
cd /var/www/web.reape.rs  # Replace with your actual path
```

### Step 3: Verify You're in the Right Place

Check if these directories/files exist:

```bash
ls -la src/
ls -la src/private/php/load.php
ls -la src/profile.php
```

You should see output showing these files exist.

### Step 4: Run the Installation Script

Now you can run the installation script:

```bash
bash install-status-display.sh
```

---

## Alternative: Manual Installation

If you prefer not to use the script, or can't find the right directory, use the manual installation guide:

**See: `MANUAL_INSTALL.md`**

The manual installation guide walks you through:
1. Creating the database table (via MySQL command or phpMyAdmin)
2. Verifying files are in place
3. Setting permissions
4. Configuring via web interface
5. Starting the bot

---

## Quick Manual Installation

If you just want to get started quickly:

### 1. Create Database Table

Log into MySQL:

```bash
mysql -u YOUR_USERNAME -p YOUR_DATABASE_NAME
```

Run this SQL (adjust `ts_` prefix if needed):

```sql
CREATE TABLE `ts_channel_status_display` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cldbid` INT(11) NOT NULL,
  `channel_id` INT(11) NOT NULL,
  `server_group_id` INT(11) DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cldbid_channel` (`cldbid`, `channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Set Permissions

From your TS-website root directory:

```bash
chmod +x src/private/php/status-display-bot.php
```

### 3. Configure via Web Interface

Visit: `http://your-domain.com/admin/status-display.php`

Add your configuration:
- Client Database ID: `116527` (example)
- Channel ID: `251746` (example)
- Server Group: Optional

### 4. Start the Bot

```bash
# From your TS-website root directory
php src/private/php/status-display-bot.php --daemon --interval=30
```

Or run in background:

```bash
nohup php src/private/php/status-display-bot.php --daemon --interval=30 > /var/log/ts-status-bot.log 2>&1 &
```

---

## For Your Specific Setup

Based on your command prompt showing `/var/www/web.reape.rs`, try:

```bash
# Navigate to your website directory
cd /var/www/web.reape.rs

# Verify this is the right place
ls -la src/private/php/load.php

# If you see the file, run:
bash install-status-display.sh

# If not, try:
cd /var/www/web.reape.rs/public
# or
cd /var/www/web.reape.rs/public_html
# or
cd /var/www/web.reape.rs/html

# And check again
ls -la src/private/php/load.php
```

---

## Still Having Issues?

1. **Check your web server configuration**
   - Apache: Check DocumentRoot in `/etc/apache2/sites-enabled/`
   - Nginx: Check root in `/etc/nginx/sites-enabled/`

2. **Use manual installation**
   - Follow `MANUAL_INSTALL.md` step by step
   - No need to find the exact directory structure

3. **Get your database prefix**
   ```bash
   # From anywhere, run:
   mysql -u USERNAME -p -e "SHOW TABLES" DATABASE_NAME | grep -i config
   ```
   This will show if you have a prefix (e.g., `ts_config` means prefix is `ts_`)

---

## Need Help?

- **Complete Documentation**: See `STATUS_DISPLAY_FEATURE.md`
- **Manual Installation**: See `MANUAL_INSTALL.md`
- **Quick Start**: See `STATUS_DISPLAY_QUICKSTART.md`
- **Telegram Support**: https://t.me/tswebsite

---

**Remember**: The installation script just automates the manual steps. If it's not working, using the manual installation is perfectly fine and will achieve the same result!
