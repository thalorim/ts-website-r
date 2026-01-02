# Backup Quick Start - 5 Minutes Setup

## 🚀 Quick Setup (Copy & Paste)

### Step 1: Find Your Database Credentials

```bash
# View your database config
cat /workspace/src/private/config.local.php | grep -A 5 "dbConfig"
```

Copy down:
- Database name
- Database user
- Database password

### Step 2: Create Backup Script

```bash
# Create the script
sudo nano /root/backup_website.sh
```

**Copy and paste this entire script** (update the top 4 lines with your info):

```bash
#!/bin/bash

# ⚠️ UPDATE THESE 4 LINES WITH YOUR INFO ⚠️
DB_USER="your_db_user"              # From config.local.php
DB_PASS="your_db_password"          # From config.local.php
DB_NAME="your_db_name"              # From config.local.php
WEBSITE_PATH="/workspace"           # Or /var/www/html

# Don't change below this line
BACKUP_BASE="/root/backups"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
DATE_SIMPLE=$(date +%Y%m%d)

mkdir -p "$BACKUP_BASE"/{database,website,config,logs}
LOGFILE="$BACKUP_BASE/logs/backup_$DATE_SIMPLE.log"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOGFILE"; }

log "========== BACKUP STARTED =========="

# Backup Database
log "Backing up database..."
DB_FILE="$BACKUP_BASE/database/backup_${DB_NAME}_${DATE}.sql"
if mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_FILE" 2>> "$LOGFILE"; then
    gzip "$DB_FILE"
    log "✓ Database: $(du -h "${DB_FILE}.gz" | cut -f1)"
else
    log "✗ Database backup FAILED"
    exit 1
fi

# Backup Website
log "Backing up website..."
WEB_FILE="$BACKUP_BASE/website/website_${DATE}.tar.gz"
if tar -czf "$WEB_FILE" -C "$WEBSITE_PATH" . 2>> "$LOGFILE"; then
    log "✓ Website: $(du -h "$WEB_FILE" | cut -f1)"
else
    log "✗ Website backup FAILED"
    exit 1
fi

# Backup Config
log "Backing up configs..."
CONFIG_FILE="$BACKUP_BASE/config/config_${DATE}.tar.gz"
tar -czf "$CONFIG_FILE" \
    "$WEBSITE_PATH/src/private/config.local.php" \
    /etc/nginx/sites-available/* \
    /etc/apache2/sites-available/* \
    2>/dev/null
log "✓ Config: $(du -h "$CONFIG_FILE" | cut -f1)"

# Cleanup old backups
find "$BACKUP_BASE/database" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_BASE/website" -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_BASE/config" -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete

chmod 700 "$BACKUP_BASE"
chmod 600 "$BACKUP_BASE"/database/* "$BACKUP_BASE"/website/* "$BACKUP_BASE"/config/* 2>/dev/null

log "========== BACKUP COMPLETE =========="
log "Total size: $(du -sh "$BACKUP_BASE" | cut -f1)"
log "Database backups: $(ls -1 "$BACKUP_BASE"/database | wc -l)"
log "Website backups: $(ls -1 "$BACKUP_BASE"/website | wc -l)"
```

**Save**: Press `Ctrl+X`, then `Y`, then `Enter`

### Step 3: Make It Executable

```bash
sudo chmod +x /root/backup_website.sh
sudo chmod 700 /root/backup_website.sh
```

### Step 4: Test It

```bash
# Run your first backup
sudo /root/backup_website.sh

# Check if it worked
ls -lh /root/backups/database/
ls -lh /root/backups/website/

# View the log
cat /root/backups/logs/backup_$(date +%Y%m%d).log
```

✅ **If you see files in both directories, it worked!**

### Step 5: Schedule Daily Backups

```bash
# Open cron editor
sudo crontab -e

# Add this line (backups at 2 AM daily):
0 2 * * * /root/backup_website.sh

# Save: Ctrl+X, Y, Enter
```

---

## 📋 Essential Commands

### Check Backups
```bash
# List all backups
ls -lh /root/backups/database/
ls -lh /root/backups/website/

# Total backup size
du -sh /root/backups/

# View today's log
cat /root/backups/logs/backup_$(date +%Y%m%d).log
```

### Manual Backup
```bash
# Run backup now
sudo /root/backup_website.sh
```

### Restore Database
```bash
# 1. List backups
ls -lh /root/backups/database/

# 2. Restore (replace filename with actual backup)
cd /root/backups/database
gunzip -c backup_dbname_20260102_143000.sql.gz | mysql -u your_user -p your_database
```

### Restore Website
```bash
# 1. List backups
ls -lh /root/backups/website/

# 2. Backup current site
sudo mv /var/www/html /var/www/html.old

# 3. Restore (replace filename)
cd /root/backups/website
sudo mkdir -p /var/www/html
sudo tar -xzf website_20260102_143000.tar.gz -C /var/www/html

# 4. Fix permissions
sudo chown -R www-data:www-data /var/www/html
```

---

## 🔍 Troubleshooting

### "Access Denied" Error
```bash
# Test database connection
mysql -u your_user -p

# If it works, update script with correct credentials
sudo nano /root/backup_website.sh
```

### "No Space Left"
```bash
# Check disk space
df -h

# Remove old backups
find /root/backups -mtime +7 -delete
```

### Cron Not Running
```bash
# Check if cron is running
sudo systemctl status cron

# Start it if stopped
sudo systemctl start cron
sudo systemctl enable cron

# Check your cron jobs
sudo crontab -l
```

---

## 💾 Backup Locations

```
/root/backups/
├── database/     # Database backups (.sql.gz)
├── website/      # Website files (.tar.gz)
├── config/       # Config files (.tar.gz)
└── logs/         # Backup logs (.log)
```

---

## ⚠️ Important Notes

1. **Keep backups secure**: Only root can access `/root/backups`
2. **Test restoration**: Try restoring once to make sure it works
3. **Offsite copy**: Copy backups to another server/location
4. **Check logs**: Monitor `/root/backups/logs/` for errors
5. **Retention**: Old backups deleted after 30 days (configurable)

---

## 📥 Download Backups to Your Computer

### Using SCP (from your computer):
```bash
# Download latest database backup
scp root@your-server-ip:/root/backups/database/backup_*.sql.gz ./

# Download latest website backup
scp root@your-server-ip:/root/backups/website/website_*.tar.gz ./

# Download all backups
scp -r root@your-server-ip:/root/backups/ ./backups/
```

### Using SFTP (GUI):
1. Open FileZilla or WinSCP
2. Connect to your server as root
3. Navigate to `/root/backups/`
4. Download files to your computer

---

## ✅ You're Done!

Your backups are now:
- ✅ Running automatically every day at 2 AM
- ✅ Stored in `/root/backups/`
- ✅ Kept for 30 days
- ✅ Compressed to save space
- ✅ Secured (only root access)

**For detailed information**, see: `BACKUP_GUIDE.md`
