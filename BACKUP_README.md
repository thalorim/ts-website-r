# 💾 Complete Backup Solution for Your VPS

## 📚 Documentation Overview

This backup solution provides complete protection for your website and database. Choose your starting point:

### 🚀 [Quick Start (5 minutes)](BACKUP_QUICKSTART.md)
**Start here!** Copy-paste ready script to set up automatic backups immediately.
- ✅ Simple setup
- ✅ Works immediately
- ✅ Daily automatic backups
- ✅ 30-day retention

### 📖 [Complete Guide](BACKUP_GUIDE.md)
Comprehensive backup documentation covering everything.
- Manual backup procedures
- Automated backup scripts
- Restoration procedures
- Best practices
- Troubleshooting
- Cloud backup options

### 🚨 [Emergency Restoration](BACKUP_RESTORE_EMERGENCY.md)
**When things go wrong!** Step-by-step recovery procedures.
- Quick restoration (5 minutes)
- Database-only restoration
- Website-only restoration
- Full system restoration
- Rollback procedures
- Emergency checklist

---

## ⚡ Super Quick Start (Copy-Paste)

### 1. Get Your Database Info
```bash
cat /workspace/src/private/config.local.php | grep -E "user|password|dbname"
```

### 2. Create & Configure Script
```bash
sudo nano /root/backup_website.sh
# Paste script from BACKUP_QUICKSTART.md
# Update the 4 lines at the top with your info
# Save: Ctrl+X, Y, Enter
```

### 3. Make Executable & Test
```bash
sudo chmod +x /root/backup_website.sh
sudo /root/backup_website.sh
```

### 4. Schedule Daily Backups
```bash
sudo crontab -e
# Add this line:
0 2 * * * /root/backup_website.sh
```

✅ **Done!** Your backups run automatically every day at 2 AM.

---

## 📁 What Gets Backed Up

### Database
- All tables and data
- User accounts and permissions
- Stored procedures
- **Format**: Compressed SQL (.sql.gz)
- **Location**: `/root/backups/database/`

### Website Files
- All PHP files
- Images and uploads
- Configuration files
- Templates and themes
- **Format**: Compressed archive (.tar.gz)
- **Location**: `/root/backups/website/`

### Configuration Files
- Database config
- Web server config (nginx/apache)
- PHP config
- **Format**: Compressed archive (.tar.gz)
- **Location**: `/root/backups/config/`

---

## 🔍 Quick Commands

```bash
# Run backup now
sudo /root/backup_website.sh

# List all backups
ls -lh /root/backups/database/
ls -lh /root/backups/website/

# Check total backup size
du -sh /root/backups/

# View today's backup log
cat /root/backups/logs/backup_$(date +%Y%m%d).log

# Check scheduled backups
sudo crontab -l

# Download backup to your computer (run from your PC)
scp root@your-server-ip:/root/backups/database/latest.sql.gz ./
```

---

## 🛡️ Backup Features

### ✅ Automated
- Runs automatically via cron
- No manual intervention needed
- Email notifications (optional)

### ✅ Secure
- Stored in `/root/` (root-only access)
- Compressed to save space
- Encrypted option available
- Password protected

### ✅ Reliable
- Integrity checks
- Retention policy (30 days default)
- Multiple backup locations
- Tested restoration procedures

### ✅ Comprehensive
- Database (all data)
- Website files (all code)
- Configuration files
- Logs for troubleshooting

---

## 📊 Backup Schedule Recommendations

### Small Site (< 1GB)
```bash
# Every 6 hours
0 */6 * * * /root/backup_website.sh
```

### Medium Site (1-10GB)
```bash
# Twice daily (2 AM and 2 PM)
0 2,14 * * * /root/backup_website.sh
```

### Large Site (> 10GB)
```bash
# Daily at 2 AM
0 2 * * * /root/backup_website.sh
```

---

## 💡 Best Practices

### 1. Test Your Backups
```bash
# Test database backup integrity
gunzip -t /root/backups/database/latest.sql.gz

# Test website backup integrity
tar -tzf /root/backups/website/latest.tar.gz > /dev/null
```

### 2. Offsite Copies
```bash
# Copy to another server
rsync -avz /root/backups/ user@backup-server:/backups/

# Copy to cloud (with rclone)
rclone copy /root/backups/ remote:backups/

# Download to your computer
scp -r root@server:/root/backups/ ./local-backups/
```

### 3. Monitor Backups
```bash
# Create monitoring script
echo '#!/bin/bash
RECENT=$(find /root/backups/database -mtime -1 | wc -l)
if [ $RECENT -eq 0 ]; then
    echo "WARNING: No recent backups!"
    # Send alert
fi' > /root/check_backups.sh

chmod +x /root/check_backups.sh

# Add to cron (check daily at 9 AM)
0 9 * * * /root/check_backups.sh
```

### 4. Document Everything
Keep this information somewhere safe:
- Database credentials
- Server IP address
- Backup locations
- Restoration procedures
- Hosting provider contacts

---

## 🔄 Restoration Quick Reference

### Restore Database
```bash
cd /root/backups/database
gunzip -c latest_backup.sql.gz | mysql -u user -p database_name
```

### Restore Website
```bash
cd /root/backups/website
sudo mv /var/www/html /var/www/html.old
sudo mkdir -p /var/www/html
sudo tar -xzf latest_backup.tar.gz -C /var/www/html
sudo chown -R www-data:www-data /var/www/html
```

### Full Restoration
See [BACKUP_RESTORE_EMERGENCY.md](BACKUP_RESTORE_EMERGENCY.md) for complete procedures.

---

## 📈 Backup Statistics

After running backups for a while, check statistics:

```bash
# Total number of backups
echo "Database backups: $(ls /root/backups/database/ | wc -l)"
echo "Website backups: $(ls /root/backups/website/ | wc -l)"

# Total backup size
du -sh /root/backups/

# Oldest backup
ls -lt /root/backups/database/ | tail -1

# Newest backup
ls -lt /root/backups/database/ | head -2 | tail -1

# Average backup size
du -s /root/backups/database/* | awk '{sum+=$1; count++} END {print sum/count/1024 " MB"}'
```

---

## ❓ Troubleshooting

### Backup Fails
1. Check disk space: `df -h`
2. Check permissions: `ls -la /root/backups`
3. Check logs: `cat /root/backups/logs/backup_*.log`
4. Verify credentials: `mysql -u user -p`

### Cron Not Running
1. Check cron service: `sudo systemctl status cron`
2. Check cron jobs: `sudo crontab -l`
3. Check cron logs: `grep CRON /var/log/syslog`

### Restoration Fails
1. Test backup integrity first
2. Check file permissions
3. Verify database credentials
4. See [BACKUP_RESTORE_EMERGENCY.md](BACKUP_RESTORE_EMERGENCY.md)

---

## 🌟 Advanced Features

### Cloud Backup Integration
```bash
# Install rclone
curl https://rclone.org/install.sh | sudo bash

# Configure cloud storage (Google Drive, Dropbox, etc.)
rclone config

# Add to backup script
rclone copy /root/backups/ remote:backups/
```

### Email Notifications
```bash
# Install mailutils
sudo apt install mailutils -y

# Add to backup script
echo "Backup completed" | mail -s "Backup Success" your@email.com
```

### Encryption
```bash
# Encrypt backup with GPG
gpg --symmetric --cipher-algo AES256 backup.tar.gz

# Decrypt when needed
gpg --decrypt backup.tar.gz.gpg > backup.tar.gz
```

### Database Replication
For high-availability:
```bash
# Set up MySQL replication to secondary server
# See MySQL replication documentation
```

---

## 📞 Getting Help

### Resources
1. **Quick Setup**: [BACKUP_QUICKSTART.md](BACKUP_QUICKSTART.md)
2. **Full Guide**: [BACKUP_GUIDE.md](BACKUP_GUIDE.md)
3. **Emergency**: [BACKUP_RESTORE_EMERGENCY.md](BACKUP_RESTORE_EMERGENCY.md)

### Support
- Check logs: `/root/backups/logs/`
- Test manually: `sudo /root/backup_website.sh`
- Verify backups exist: `ls -lh /root/backups/`

---

## ✅ Setup Checklist

- [ ] Read BACKUP_QUICKSTART.md
- [ ] Create backup script
- [ ] Update database credentials in script
- [ ] Test script manually
- [ ] Verify backups created
- [ ] Schedule automatic backups (cron)
- [ ] Test restoration procedure
- [ ] Set up offsite backup (recommended)
- [ ] Document credentials securely
- [ ] Set up monitoring (optional)

---

## 🎯 Summary

| Feature | Status | Location |
|---------|--------|----------|
| **Database Backups** | ✅ Automated | `/root/backups/database/` |
| **Website Backups** | ✅ Automated | `/root/backups/website/` |
| **Config Backups** | ✅ Automated | `/root/backups/config/` |
| **Compression** | ✅ Enabled | .gz / .tar.gz |
| **Encryption** | ⚙️ Optional | GPG |
| **Retention** | ✅ 30 days | Configurable |
| **Scheduling** | ✅ Cron | Daily 2 AM |
| **Monitoring** | ⚙️ Optional | Logs available |
| **Offsite Copy** | ⚙️ Recommended | Manual/rsync |
| **Restoration** | ✅ Documented | See guides |

---

## 🚀 Next Steps

1. **Now**: Set up backups using [BACKUP_QUICKSTART.md](BACKUP_QUICKSTART.md)
2. **Today**: Test restoration procedure
3. **This Week**: Set up offsite backup copy
4. **Monthly**: Verify backups and test restoration

---

**Remember**: The best backup is the one you have when you need it!

**Status**: ✅ Complete Backup Solution Ready
**Version**: 1.0
**Last Updated**: January 2, 2026
