# Complete VPS Backup Guide - Website & Database

## 📋 Table of Contents
1. [Prerequisites](#prerequisites)
2. [Manual Backup](#manual-backup)
3. [Automated Backup Script](#automated-backup-script)
4. [Backup Verification](#backup-verification)
5. [Restoration Procedures](#restoration-procedures)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Information
Before starting, gather this information:

```bash
# Database credentials (from your config file)
DB_HOST="localhost"
DB_NAME="your_database_name"
DB_USER="your_database_user"
DB_PASS="your_database_password"

# Website path
WEBSITE_PATH="/var/www/html"  # or /workspace or your actual path

# Backup destination
BACKUP_PATH="/root/backups"  # recommended secure location
```

### Check Available Disk Space
```bash
# Check current disk usage
df -h

# Check website size
du -sh /var/www/html

# Check database size
sudo mysql -e "SELECT table_schema AS 'Database', 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' 
FROM information_schema.TABLES 
GROUP BY table_schema;"
```

---

## Manual Backup

### Step 1: Create Backup Directory

```bash
# Create backup directory
sudo mkdir -p /root/backups
sudo chmod 700 /root/backups

# Create subdirectories for organization
sudo mkdir -p /root/backups/database
sudo mkdir -p /root/backups/website
sudo mkdir -p /root/backups/config
```

### Step 2: Backup Database

```bash
# Navigate to backup directory
cd /root/backups/database

# Create database backup with timestamp
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="your_database_name"
DB_USER="your_database_user"

# Option 1: With password prompt (more secure)
mysqldump -u $DB_USER -p $DB_NAME > backup_$DB_NAME\_$DATE.sql

# Option 2: With password in command (less secure, but automated)
# Replace 'your_password' with actual password
mysqldump -u $DB_USER -pyour_password $DB_NAME > backup_$DB_NAME\_$DATE.sql

# Compress the backup to save space
gzip backup_$DB_NAME\_$DATE.sql

# Result: backup_dbname_20260102_143000.sql.gz

# Verify backup was created
ls -lh backup_$DB_NAME\_$DATE.sql.gz
```

### Step 3: Backup Website Files

```bash
# Navigate to backup directory
cd /root/backups/website

# Create timestamp
DATE=$(date +%Y%m%d_%H%M%S)

# Backup entire website directory
# Adjust /var/www/html to your actual website path
tar -czf website_backup_$DATE.tar.gz -C /var/www/html .

# Alternative: Include specific directories
tar -czf website_backup_$DATE.tar.gz \
    /var/www/html/src \
    /var/www/html/private \
    /var/www/html/img \
    /var/www/html/*.php \
    /var/www/html/*.md

# Verify backup size
ls -lh website_backup_$DATE.tar.gz
```

### Step 4: Backup Configuration Files

```bash
# Navigate to config backup directory
cd /root/backups/config

# Create timestamp
DATE=$(date +%Y%m%d_%H%M%S)

# Backup important config files
tar -czf config_backup_$DATE.tar.gz \
    /var/www/html/src/private/config.local.php \
    /etc/nginx/sites-available/default \
    /etc/apache2/sites-available/000-default.conf \
    /etc/mysql/my.cnf \
    /etc/php/*/apache2/php.ini \
    /etc/php/*/fpm/php.ini \
    2>/dev/null

# Note: Some files might not exist, errors are suppressed with 2>/dev/null
```

### Step 5: Set Proper Permissions

```bash
# Secure backup directory (only root can access)
sudo chmod 700 /root/backups
sudo chmod 600 /root/backups/database/*
sudo chmod 600 /root/backups/website/*
sudo chmod 600 /root/backups/config/*

# Verify permissions
ls -la /root/backups/
```

---

## Automated Backup Script

### Step 1: Create Backup Script

```bash
# Create script file
sudo nano /root/backup_website.sh
```

**Paste this complete script:**

```bash
#!/bin/bash

#############################################################
# Website & Database Backup Script
# Backs up database, website files, and configurations
#############################################################

# Configuration
DB_USER="your_database_user"
DB_PASS="your_database_password"
DB_NAME="your_database_name"
WEBSITE_PATH="/var/www/html"
BACKUP_BASE="/root/backups"
RETENTION_DAYS=30  # Keep backups for 30 days

# Create timestamp
DATE=$(date +%Y%m%d_%H%M%S)
DATE_SIMPLE=$(date +%Y%m%d)

# Create backup directories
mkdir -p "$BACKUP_BASE/database"
mkdir -p "$BACKUP_BASE/website"
mkdir -p "$BACKUP_BASE/config"
mkdir -p "$BACKUP_BASE/logs"

# Log file
LOGFILE="$BACKUP_BASE/logs/backup_$DATE_SIMPLE.log"

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOGFILE"
}

log "=========================================="
log "Starting backup process"
log "=========================================="

#############################################################
# 1. Database Backup
#############################################################
log "Backing up database: $DB_NAME"

DB_BACKUP_FILE="$BACKUP_BASE/database/backup_${DB_NAME}_${DATE}.sql"

# Create database dump
if mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_BACKUP_FILE" 2>> "$LOGFILE"; then
    log "✓ Database dump created successfully"
    
    # Compress the backup
    gzip "$DB_BACKUP_FILE"
    log "✓ Database backup compressed: ${DB_BACKUP_FILE}.gz"
    
    # Get backup size
    BACKUP_SIZE=$(du -h "${DB_BACKUP_FILE}.gz" | cut -f1)
    log "  Backup size: $BACKUP_SIZE"
else
    log "✗ ERROR: Database backup failed!"
    exit 1
fi

#############################################################
# 2. Website Files Backup
#############################################################
log "Backing up website files from: $WEBSITE_PATH"

WEB_BACKUP_FILE="$BACKUP_BASE/website/website_backup_${DATE}.tar.gz"

# Create compressed archive of website
if tar -czf "$WEB_BACKUP_FILE" -C "$WEBSITE_PATH" . 2>> "$LOGFILE"; then
    log "✓ Website files backed up successfully"
    
    # Get backup size
    BACKUP_SIZE=$(du -h "$WEB_BACKUP_FILE" | cut -f1)
    log "  Backup size: $BACKUP_SIZE"
else
    log "✗ ERROR: Website backup failed!"
    exit 1
fi

#############################################################
# 3. Configuration Files Backup
#############################################################
log "Backing up configuration files"

CONFIG_BACKUP_FILE="$BACKUP_BASE/config/config_backup_${DATE}.tar.gz"

# Backup important config files (ignore errors for missing files)
tar -czf "$CONFIG_BACKUP_FILE" \
    "$WEBSITE_PATH/src/private/config.local.php" \
    /etc/nginx/sites-available/* \
    /etc/apache2/sites-available/* \
    /etc/mysql/my.cnf \
    /etc/php/*/apache2/php.ini \
    /etc/php/*/fpm/php.ini \
    2>/dev/null

if [ -f "$CONFIG_BACKUP_FILE" ]; then
    log "✓ Configuration files backed up"
    BACKUP_SIZE=$(du -h "$CONFIG_BACKUP_FILE" | cut -f1)
    log "  Backup size: $BACKUP_SIZE"
else
    log "⚠ Warning: Configuration backup created but may be incomplete"
fi

#############################################################
# 4. Clean Old Backups
#############################################################
log "Cleaning backups older than $RETENTION_DAYS days"

# Count backups before cleanup
BEFORE_DB=$(ls -1 "$BACKUP_BASE/database" | wc -l)
BEFORE_WEB=$(ls -1 "$BACKUP_BASE/website" | wc -l)
BEFORE_CONFIG=$(ls -1 "$BACKUP_BASE/config" | wc -l)

# Remove old backups
find "$BACKUP_BASE/database" -name "*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete
find "$BACKUP_BASE/website" -name "*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete
find "$BACKUP_BASE/config" -name "*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete
find "$BACKUP_BASE/logs" -name "*.log" -type f -mtime +$RETENTION_DAYS -delete

# Count backups after cleanup
AFTER_DB=$(ls -1 "$BACKUP_BASE/database" | wc -l)
AFTER_WEB=$(ls -1 "$BACKUP_BASE/website" | wc -l)
AFTER_CONFIG=$(ls -1 "$BACKUP_BASE/config" | wc -l)

log "✓ Cleanup complete"
log "  Database backups: $BEFORE_DB → $AFTER_DB"
log "  Website backups: $BEFORE_WEB → $AFTER_WEB"
log "  Config backups: $BEFORE_CONFIG → $AFTER_CONFIG"

#############################################################
# 5. Set Permissions
#############################################################
log "Setting secure permissions"

chmod 700 "$BACKUP_BASE"
chmod 600 "$BACKUP_BASE"/database/*.gz 2>/dev/null
chmod 600 "$BACKUP_BASE"/website/*.gz 2>/dev/null
chmod 600 "$BACKUP_BASE"/config/*.gz 2>/dev/null

log "✓ Permissions set"

#############################################################
# 6. Backup Summary
#############################################################
log "=========================================="
log "Backup completed successfully!"
log "=========================================="
log "Summary:"
log "  Database: ${DB_BACKUP_FILE}.gz"
log "  Website: $WEB_BACKUP_FILE"
log "  Config: $CONFIG_BACKUP_FILE"
log "  Total backups retained:"
log "    - Database: $AFTER_DB"
log "    - Website: $AFTER_WEB"
log "    - Config: $AFTER_CONFIG"
log "=========================================="

# Calculate total backup size
TOTAL_SIZE=$(du -sh "$BACKUP_BASE" | cut -f1)
log "Total backup directory size: $TOTAL_SIZE"

# Disk space check
log "Disk space:"
df -h / | tail -1 | awk '{print "  Used: "$3" / "$2" ("$5")"}'

log "=========================================="

exit 0
```

### Step 2: Configure the Script

```bash
# Edit the script to add your credentials
sudo nano /root/backup_website.sh

# Update these lines:
DB_USER="your_database_user"        # Replace with your DB username
DB_PASS="your_database_password"    # Replace with your DB password
DB_NAME="your_database_name"        # Replace with your DB name
WEBSITE_PATH="/var/www/html"        # Replace with your website path
```

### Step 3: Make Script Executable

```bash
# Make the script executable
sudo chmod +x /root/backup_website.sh

# Secure the script (only root can read/execute)
sudo chmod 700 /root/backup_website.sh
```

### Step 4: Test the Script

```bash
# Run the script manually to test
sudo /root/backup_website.sh

# Check the log
tail -f /root/backups/logs/backup_$(date +%Y%m%d).log

# Verify backups were created
ls -lh /root/backups/database/
ls -lh /root/backups/website/
ls -lh /root/backups/config/
```

---

## Automated Scheduled Backups

### Option 1: Daily Backup with Cron

```bash
# Edit root's crontab
sudo crontab -e

# Add this line for daily backup at 2:00 AM
0 2 * * * /root/backup_website.sh

# Or for backup every 6 hours
0 */6 * * * /root/backup_website.sh

# Or for weekly backup (Sunday at 3:00 AM)
0 3 * * 0 /root/backup_website.sh

# Save and exit (Ctrl+X, Y, Enter in nano)
```

### Option 2: Verify Cron Job

```bash
# List current cron jobs
sudo crontab -l

# Check cron service status
sudo systemctl status cron
# or on some systems:
sudo systemctl status crond
```

### Common Cron Schedule Examples

```bash
# Every day at 2:00 AM
0 2 * * * /root/backup_website.sh

# Every day at 2:00 AM and 2:00 PM
0 2,14 * * * /root/backup_website.sh

# Every 6 hours
0 */6 * * * /root/backup_website.sh

# Every Monday at 3:00 AM
0 3 * * 1 /root/backup_website.sh

# First day of every month at 4:00 AM
0 4 1 * * /root/backup_website.sh
```

---

## Backup Verification

### Step 1: List All Backups

```bash
# List all backups with sizes and dates
echo "=== DATABASE BACKUPS ==="
ls -lh /root/backups/database/ | tail -10

echo -e "\n=== WEBSITE BACKUPS ==="
ls -lh /root/backups/website/ | tail -10

echo -e "\n=== CONFIG BACKUPS ==="
ls -lh /root/backups/config/ | tail -10

echo -e "\n=== TOTAL BACKUP SIZE ==="
du -sh /root/backups/
```

### Step 2: Test Database Backup Integrity

```bash
# Extract and test a database backup
cd /root/backups/database

# Get latest backup
LATEST_DB=$(ls -t backup_*.sql.gz | head -1)

# Test if it can be extracted
gunzip -t $LATEST_DB

if [ $? -eq 0 ]; then
    echo "✓ Database backup is valid"
else
    echo "✗ Database backup is corrupted!"
fi
```

### Step 3: Test Website Backup Integrity

```bash
# Test a website backup
cd /root/backups/website

# Get latest backup
LATEST_WEB=$(ls -t website_backup_*.tar.gz | head -1)

# Test if it can be extracted
tar -tzf $LATEST_WEB > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "✓ Website backup is valid"
else
    echo "✗ Website backup is corrupted!"
fi
```

### Step 4: Check Backup Logs

```bash
# View latest backup log
cat /root/backups/logs/backup_$(date +%Y%m%d).log

# View all recent logs
ls -lh /root/backups/logs/

# Search for errors in logs
grep -i "error\|failed" /root/backups/logs/*.log
```

---

## Restoration Procedures

### Restore Database

```bash
# Step 1: Go to backup directory
cd /root/backups/database

# Step 2: List available backups
ls -lh backup_*.sql.gz

# Step 3: Choose backup to restore (replace with actual filename)
BACKUP_FILE="backup_dbname_20260102_143000.sql.gz"

# Step 4: Extract the backup
gunzip -c $BACKUP_FILE > restore_temp.sql

# Step 5: Stop applications using the database (optional but recommended)
sudo systemctl stop apache2
# or
sudo systemctl stop nginx

# Step 6: Restore the database
mysql -u your_user -p your_database_name < restore_temp.sql

# Step 7: Verify restoration
mysql -u your_user -p -e "USE your_database_name; SHOW TABLES;"

# Step 8: Start applications
sudo systemctl start apache2
# or
sudo systemctl start nginx

# Step 9: Clean up
rm restore_temp.sql

echo "✓ Database restored successfully"
```

### Restore Website Files

```bash
# Step 1: Go to backup directory
cd /root/backups/website

# Step 2: List available backups
ls -lh website_backup_*.tar.gz

# Step 3: Create temporary restoration directory
mkdir -p /tmp/website_restore

# Step 4: Extract backup to temp directory
tar -xzf website_backup_20260102_143000.tar.gz -C /tmp/website_restore

# Step 5: Stop web server
sudo systemctl stop apache2
# or
sudo systemctl stop nginx

# Step 6: Backup current website (just in case)
sudo mv /var/www/html /var/www/html.old.$(date +%Y%m%d)

# Step 7: Restore website files
sudo mkdir -p /var/www/html
sudo cp -r /tmp/website_restore/* /var/www/html/

# Step 8: Set proper permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

# Step 9: Start web server
sudo systemctl start apache2
# or
sudo systemctl start nginx

# Step 10: Verify website is working
curl -I http://localhost

# Step 11: Clean up
rm -rf /tmp/website_restore

echo "✓ Website files restored successfully"
```

### Full System Restoration

```bash
# Complete restoration script
#!/bin/bash

echo "=== FULL SYSTEM RESTORATION ==="
echo "WARNING: This will restore database and website files"
read -p "Continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Restoration cancelled"
    exit 1
fi

# 1. Stop services
echo "Stopping services..."
sudo systemctl stop apache2 2>/dev/null
sudo systemctl stop nginx 2>/dev/null

# 2. Restore database
echo "Restoring database..."
cd /root/backups/database
LATEST_DB=$(ls -t backup_*.sql.gz | head -1)
gunzip -c $LATEST_DB | mysql -u your_user -p your_database_name

# 3. Restore website
echo "Restoring website..."
cd /root/backups/website
LATEST_WEB=$(ls -t website_backup_*.tar.gz | head -1)
sudo mv /var/www/html /var/www/html.backup.$(date +%Y%m%d_%H%M%S)
sudo mkdir -p /var/www/html
sudo tar -xzf $LATEST_WEB -C /var/www/html
sudo chown -R www-data:www-data /var/www/html

# 4. Start services
echo "Starting services..."
sudo systemctl start apache2 2>/dev/null
sudo systemctl start nginx 2>/dev/null

echo "✓ Full restoration complete!"
echo "✓ Database restored: $LATEST_DB"
echo "✓ Website restored: $LATEST_WEB"
```

---

## Best Practices

### 1. Multiple Backup Locations

```bash
# Copy backups to remote location (using rsync)
rsync -avz /root/backups/ user@backup-server:/backups/website/

# Or use SCP
scp -r /root/backups/ user@backup-server:/backups/website/

# Or copy to mounted external drive
cp -r /root/backups/* /mnt/external_backup/
```

### 2. Backup to Cloud Storage

#### Using rclone (Google Drive, Dropbox, etc.)

```bash
# Install rclone
curl https://rclone.org/install.sh | sudo bash

# Configure rclone
rclone config

# Copy backups to cloud
rclone copy /root/backups/ remote:backups/

# Add to backup script
echo 'rclone copy /root/backups/ remote:backups/' >> /root/backup_website.sh
```

### 3. Backup Monitoring

Create a monitoring script:

```bash
# Create monitoring script
sudo nano /root/check_backups.sh
```

```bash
#!/bin/bash

# Check if backups are recent (within last 25 hours)
LATEST_DB=$(find /root/backups/database -name "*.sql.gz" -mtime -1 | wc -l)
LATEST_WEB=$(find /root/backups/website -name "*.tar.gz" -mtime -1 | wc -l)

if [ $LATEST_DB -eq 0 ] || [ $LATEST_WEB -eq 0 ]; then
    echo "⚠ WARNING: No recent backups found!"
    echo "Database backups in last 24h: $LATEST_DB"
    echo "Website backups in last 24h: $LATEST_WEB"
    # Send alert (optional)
    # mail -s "Backup Alert" your@email.com < /tmp/alert.txt
    exit 1
else
    echo "✓ Recent backups found"
    echo "Database: $LATEST_DB, Website: $LATEST_WEB"
fi
```

### 4. Encryption (Optional but Recommended)

```bash
# Encrypt a backup with password
gpg --symmetric --cipher-algo AES256 backup_file.tar.gz

# Decrypt when needed
gpg --decrypt backup_file.tar.gz.gpg > backup_file.tar.gz

# Add encryption to backup script
# After creating backup:
gpg --batch --yes --passphrase "your_strong_password" --symmetric --cipher-algo AES256 "$DB_BACKUP_FILE.gz"
```

### 5. Database-Only Quick Backup

```bash
# Quick database backup before major changes
mysqldump -u username -p database_name | gzip > /root/quick_backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

---

## Troubleshooting

### Problem: Backup Script Fails

```bash
# Check script permissions
ls -l /root/backup_website.sh

# Check script for errors
bash -x /root/backup_website.sh

# Check log file
tail -50 /root/backups/logs/backup_$(date +%Y%m%d).log
```

### Problem: "Access Denied" Error

```bash
# Verify database credentials
mysql -u your_user -p -e "SHOW DATABASES;"

# Check database permissions
mysql -u your_user -p -e "SHOW GRANTS;"
```

### Problem: "Disk Space Full"

```bash
# Check disk space
df -h

# Find large files
du -sh /root/backups/*

# Clean old backups manually
find /root/backups -mtime +30 -delete

# Or reduce retention days in script
RETENTION_DAYS=7  # Keep only 7 days instead of 30
```

### Problem: Cron Job Not Running

```bash
# Check cron service
sudo systemctl status cron

# Start cron service
sudo systemctl start cron
sudo systemctl enable cron

# Check cron logs
sudo grep CRON /var/log/syslog
# or
sudo tail -f /var/log/cron

# Test cron job
sudo run-parts /etc/cron.daily
```

### Problem: Permission Denied

```bash
# Fix backup directory permissions
sudo chown -R root:root /root/backups
sudo chmod 700 /root/backups

# Fix script permissions
sudo chmod 700 /root/backup_website.sh
```

---

## Quick Reference Commands

```bash
# Manual backup NOW
sudo /root/backup_website.sh

# List all backups
ls -lh /root/backups/database/
ls -lh /root/backups/website/

# Check backup size
du -sh /root/backups/

# View latest backup log
cat /root/backups/logs/backup_$(date +%Y%m%d).log

# Test database backup
gunzip -t /root/backups/database/latest_backup.sql.gz

# Delete backups older than 30 days
find /root/backups -type f -mtime +30 -delete

# Restore latest database
cd /root/backups/database && gunzip -c $(ls -t *.sql.gz | head -1) | mysql -u user -p dbname
```

---

## Summary Checklist

- [ ] Created `/root/backups` directory
- [ ] Created backup script `/root/backup_website.sh`
- [ ] Updated script with correct credentials
- [ ] Made script executable (`chmod +x`)
- [ ] Tested script manually
- [ ] Verified backups were created
- [ ] Set up cron job for automatic backups
- [ ] Tested restoration procedure
- [ ] Documented database credentials securely
- [ ] Set up offsite backup (optional but recommended)
- [ ] Configured backup monitoring (optional)

---

**Status**: ✅ Complete Backup Solution
**Recommended**: Daily backups, 30-day retention, offsite copies
**Critical**: Test your restoration procedure regularly!
