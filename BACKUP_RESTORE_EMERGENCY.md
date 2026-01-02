# 🚨 Emergency Restoration Guide

## If Your Website Is Down - Follow These Steps

### Step 1: Stay Calm and Assess

```bash
# Check if website is accessible
curl -I http://localhost

# Check web server status
sudo systemctl status apache2
# or
sudo systemctl status nginx

# Check database status
sudo systemctl status mysql
# or
sudo systemctl status mariadb
```

---

## 🔥 Quick Restoration (5 Minutes)

### Option 1: Restore Everything (Database + Website)

```bash
#!/bin/bash
echo "🚨 EMERGENCY FULL RESTORATION"
echo "================================"

# Stop web server
sudo systemctl stop apache2 2>/dev/null
sudo systemctl stop nginx 2>/dev/null

# Get latest backups
cd /root/backups/database
LATEST_DB=$(ls -t backup_*.sql.gz | head -1)
echo "Latest DB backup: $LATEST_DB"

cd /root/backups/website
LATEST_WEB=$(ls -t website_*.tar.gz | head -1)
echo "Latest website backup: $LATEST_WEB"

read -p "Press ENTER to restore these backups..."

# Restore database
echo "Restoring database..."
cd /root/backups/database
gunzip -c "$LATEST_DB" | mysql -u your_user -p your_database_name

# Restore website
echo "Restoring website files..."
cd /root/backups/website
sudo rm -rf /var/www/html.emergency_backup 2>/dev/null
sudo mv /var/www/html /var/www/html.emergency_backup 2>/dev/null
sudo mkdir -p /var/www/html
sudo tar -xzf "$LATEST_WEB" -C /var/www/html
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

# Start web server
sudo systemctl start apache2 2>/dev/null
sudo systemctl start nginx 2>/dev/null

echo "✅ RESTORATION COMPLETE"
echo "Testing website..."
curl -I http://localhost
```

### Option 2: Restore Database Only

```bash
# If only database is corrupted
cd /root/backups/database

# List available backups
ls -lh backup_*.sql.gz

# Restore latest (or choose specific one)
LATEST=$(ls -t backup_*.sql.gz | head -1)
echo "Restoring: $LATEST"

# Restore
gunzip -c "$LATEST" | mysql -u your_user -p your_database_name

# Restart services
sudo systemctl restart apache2
```

### Option 3: Restore Website Files Only

```bash
# If only website files are corrupted
cd /root/backups/website

# List available backups
ls -lh website_*.tar.gz

# Stop web server
sudo systemctl stop apache2

# Backup current (corrupted) files
sudo mv /var/www/html /var/www/html.corrupted.$(date +%Y%m%d_%H%M%S)

# Restore from backup
LATEST=$(ls -t website_*.tar.gz | head -1)
echo "Restoring: $LATEST"

sudo mkdir -p /var/www/html
sudo tar -xzf "$LATEST" -C /var/www/html

# Fix permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html

# Start web server
sudo systemctl start apache2
```

---

## 📋 Step-by-Step Detailed Restoration

### 1. Prepare for Restoration

```bash
# Create restoration working directory
mkdir -p /tmp/emergency_restore
cd /tmp/emergency_restore

# Check available backups
echo "=== Available Backups ==="
echo "Database:"
ls -lht /root/backups/database/ | head -5
echo ""
echo "Website:"
ls -lht /root/backups/website/ | head -5
```

### 2. Choose Backup to Restore

```bash
# List backups with readable dates
echo "=== Database Backups ==="
for f in $(ls -t /root/backups/database/backup_*.sql.gz | head -10); do
    echo "$f - $(stat -c %y "$f" | cut -d'.' -f1)"
done

echo ""
echo "=== Website Backups ==="
for f in $(ls -t /root/backups/website/website_*.tar.gz | head -10); do
    echo "$f - $(stat -c %y "$f" | cut -d'.' -f1)"
done
```

### 3. Test Backup Integrity

```bash
# Test database backup
cd /root/backups/database
BACKUP_FILE="backup_dbname_20260102_143000.sql.gz"

echo "Testing database backup integrity..."
gunzip -t "$BACKUP_FILE"
if [ $? -eq 0 ]; then
    echo "✓ Database backup is valid"
else
    echo "✗ Database backup is CORRUPTED! Choose another backup."
    exit 1
fi

# Test website backup
cd /root/backups/website
BACKUP_FILE="website_20260102_143000.tar.gz"

echo "Testing website backup integrity..."
tar -tzf "$BACKUP_FILE" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ Website backup is valid"
else
    echo "✗ Website backup is CORRUPTED! Choose another backup."
    exit 1
fi
```

### 4. Stop All Services

```bash
echo "Stopping services..."

# Stop web servers
sudo systemctl stop apache2 2>/dev/null
sudo systemctl stop nginx 2>/dev/null
sudo systemctl stop php7.4-fpm 2>/dev/null
sudo systemctl stop php8.0-fpm 2>/dev/null
sudo systemctl stop php8.1-fpm 2>/dev/null
sudo systemctl stop php8.2-fpm 2>/dev/null

# Verify they're stopped
ps aux | grep -E 'apache|nginx|php-fpm' | grep -v grep
```

### 5. Backup Current State (Safety)

```bash
echo "Creating safety backup of current state..."

# Backup current database
SAFETY_DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u your_user -p your_database_name | gzip > /tmp/safety_db_$SAFETY_DATE.sql.gz

# Backup current website
sudo tar -czf /tmp/safety_web_$SAFETY_DATE.tar.gz -C /var/www/html .

echo "✓ Safety backups created in /tmp/"
ls -lh /tmp/safety_*
```

### 6. Restore Database

```bash
echo "==================================="
echo "RESTORING DATABASE"
echo "==================================="

cd /root/backups/database

# Choose your backup file
BACKUP_FILE="backup_dbname_20260102_143000.sql.gz"

# Extract to temp file
gunzip -c "$BACKUP_FILE" > /tmp/restore_db.sql

# Check size
echo "SQL file size: $(du -h /tmp/restore_db.sql | cut -f1)"

# Drop existing database (optional - only if completely corrupted)
# ⚠️ DANGEROUS - Comment out if unsure
# mysql -u your_user -p -e "DROP DATABASE IF EXISTS your_database_name;"
# mysql -u your_user -p -e "CREATE DATABASE your_database_name;"

# Restore database
echo "Restoring database (this may take a few minutes)..."
mysql -u your_user -p your_database_name < /tmp/restore_db.sql

if [ $? -eq 0 ]; then
    echo "✓ Database restored successfully"
else
    echo "✗ Database restoration FAILED"
    exit 1
fi

# Verify restoration
echo "Verifying database..."
mysql -u your_user -p -e "USE your_database_name; SHOW TABLES;"

# Clean up
rm /tmp/restore_db.sql
```

### 7. Restore Website Files

```bash
echo "==================================="
echo "RESTORING WEBSITE FILES"
echo "==================================="

cd /root/backups/website

# Choose your backup file
BACKUP_FILE="website_20260102_143000.tar.gz"

# Move current website to backup location
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
sudo mv /var/www/html /var/www/html.before_restore.$BACKUP_DATE

# Create fresh directory
sudo mkdir -p /var/www/html

# Extract backup
echo "Extracting website files..."
sudo tar -xzf "$BACKUP_FILE" -C /var/www/html

if [ $? -eq 0 ]; then
    echo "✓ Website files restored successfully"
else
    echo "✗ Website restoration FAILED"
    # Restore old website
    sudo rm -rf /var/www/html
    sudo mv /var/www/html.before_restore.$BACKUP_DATE /var/www/html
    exit 1
fi

# Set correct permissions
echo "Setting permissions..."
sudo chown -R www-data:www-data /var/www/html
sudo find /var/www/html -type d -exec chmod 755 {} \;
sudo find /var/www/html -type f -exec chmod 644 {} \;

# Special permissions for specific directories
sudo chmod -R 777 /var/www/html/src/private/cache 2>/dev/null
sudo chmod -R 777 /var/www/html/src/private/logs 2>/dev/null

echo "✓ Permissions set"
```

### 8. Restore Configuration Files (If Needed)

```bash
echo "==================================="
echo "RESTORING CONFIGURATION FILES"
echo "==================================="

cd /root/backups/config

# Choose your backup file
BACKUP_FILE="config_20260102_143000.tar.gz"

# Extract to temp directory
mkdir -p /tmp/config_restore
tar -xzf "$BACKUP_FILE" -C /tmp/config_restore

# Restore config.local.php
sudo cp /tmp/config_restore/workspace/src/private/config.local.php \
    /var/www/html/src/private/config.local.php

# Restore nginx config (if exists)
if [ -f /tmp/config_restore/etc/nginx/sites-available/default ]; then
    sudo cp /tmp/config_restore/etc/nginx/sites-available/default \
        /etc/nginx/sites-available/default
fi

# Restore apache config (if exists)
if [ -f /tmp/config_restore/etc/apache2/sites-available/000-default.conf ]; then
    sudo cp /tmp/config_restore/etc/apache2/sites-available/000-default.conf \
        /etc/apache2/sites-available/000-default.conf
fi

echo "✓ Configuration files restored"
```

### 9. Start Services

```bash
echo "==================================="
echo "STARTING SERVICES"
echo "==================================="

# Start database (if stopped)
sudo systemctl start mysql 2>/dev/null
sudo systemctl start mariadb 2>/dev/null

# Start PHP-FPM (if used)
sudo systemctl start php7.4-fpm 2>/dev/null
sudo systemctl start php8.0-fpm 2>/dev/null
sudo systemctl start php8.1-fpm 2>/dev/null
sudo systemctl start php8.2-fpm 2>/dev/null

# Start web server
sudo systemctl start apache2 2>/dev/null
sudo systemctl start nginx 2>/dev/null

# Wait a moment for services to start
sleep 3

# Check status
echo "Service status:"
sudo systemctl status apache2 --no-pager 2>/dev/null || \
sudo systemctl status nginx --no-pager 2>/dev/null

sudo systemctl status mysql --no-pager 2>/dev/null || \
sudo systemctl status mariadb --no-pager 2>/dev/null
```

### 10. Verify Restoration

```bash
echo "==================================="
echo "VERIFYING RESTORATION"
echo "==================================="

# Test web server response
echo "Testing HTTP response..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)

if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Website is responding (HTTP $HTTP_CODE)"
else
    echo "⚠ Website response: HTTP $HTTP_CODE"
fi

# Test database connection
echo "Testing database connection..."
mysql -u your_user -p -e "SELECT COUNT(*) as tables FROM information_schema.tables WHERE table_schema='your_database_name';"

# Check website files
echo "Checking critical files..."
ls -la /var/www/html/src/private/config.local.php
ls -la /var/www/html/index.php

# Check permissions
echo "Checking permissions..."
ls -ld /var/www/html/

echo "==================================="
echo "RESTORATION VERIFICATION COMPLETE"
echo "==================================="
```

---

## 🔄 Rollback If Restoration Fails

If something goes wrong:

```bash
# Restore from safety backup
echo "Rolling back to safety backup..."

# Restore old database
gunzip -c /tmp/safety_db_$(date +%Y%m%d)_*.sql.gz | mysql -u your_user -p your_database_name

# Restore old website
sudo rm -rf /var/www/html
sudo tar -xzf /tmp/safety_web_$(date +%Y%m%d)_*.tar.gz -C /var/www/html

# Restart services
sudo systemctl restart apache2
sudo systemctl restart mysql

echo "✓ Rolled back to previous state"
```

---

## 📞 Emergency Checklist

- [ ] Services stopped
- [ ] Safety backup created
- [ ] Backup integrity tested
- [ ] Database restored
- [ ] Database verified
- [ ] Website files restored
- [ ] Permissions set correctly
- [ ] Configuration restored
- [ ] Services started
- [ ] Website responding
- [ ] Database accessible
- [ ] Admin panel accessible
- [ ] TeamSpeak connection working

---

## 🆘 If Restoration Still Fails

### Check Logs

```bash
# Apache logs
sudo tail -50 /var/log/apache2/error.log

# Nginx logs
sudo tail -50 /var/log/nginx/error.log

# MySQL logs
sudo tail -50 /var/log/mysql/error.log

# PHP logs
sudo tail -50 /var/log/php*-fpm.log
```

### Common Issues

**Database connection failed:**
```bash
# Check database credentials
cat /var/www/html/src/private/config.local.php

# Test connection manually
mysql -u user -p database_name
```

**Permission errors:**
```bash
# Reset all permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

**Service won't start:**
```bash
# Check service status
sudo systemctl status apache2
sudo journalctl -xe
```

---

## 💾 Contact Information

Keep this information handy:
- Hosting provider support: _______________
- Server IP: _______________
- Database name: _______________
- Database user: _______________
- Backup location: /root/backups/

---

**Remember**: Always test your backups BEFORE you need them!

**Status**: 🚨 Emergency Procedures Ready
**Last Updated**: January 2, 2026
